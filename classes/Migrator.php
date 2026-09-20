<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Applies pending SQL migrations from sql/migration_v<N>_<name>.sql.
 *
 * The point is that upgrading the database stops being a manual phpMyAdmin
 * chore: the app notices which migrations a database has not seen and can
 * apply the rest itself, in order, recording each one so it never runs twice.
 */
class Migrator
{
    /**
     * Highest migration a pre-existing database is assumed to already have.
     *
     * Stops at 17 rather than 18 on purpose. Whether v18 was applied depends
     * on whether upgrade_to_v3.sql was actually run, which we cannot know —
     * and assuming it was would silently leave the assistant_manager role
     * broken. v18 is written to be safe to re-run, so letting it execute is
     * the honest choice: harmless if already applied, corrective if not.
     */
    private const BASELINE_VERSION = 17;

    public static function ensureTable(): void
    {
        Database::execute(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version    INT UNSIGNED NOT NULL PRIMARY KEY,
                name       VARCHAR(150) NOT NULL,
                applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::baselineIfNeeded();
    }

    /**
     * A database that already existed before this runner was added has had
     * v1-v18 applied by install.sql or upgrade_to_v3.sql, but has an empty
     * schema_migrations table. Re-running those files is not the answer —
     * the older ones are not all idempotent. So on the very first run, mark
     * everything up to the baseline as applied, and let only genuinely new
     * migrations (v19 and up) execute.
     *
     * The marker for "this database is already set up" is the users table:
     * a truly fresh database has nothing at all, and install.sql creates
     * schema_migrations rows itself.
     */
    private static function baselineIfNeeded(): void
    {
        $row = Database::fetchOne('SELECT COUNT(*) AS c FROM schema_migrations');
        if ((int)($row['c'] ?? 0) > 0) return;

        $installed = Database::fetchOne(
            "SELECT 1 AS x FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' LIMIT 1"
        );
        if (!$installed) return; // brand-new database — let migrations run

        foreach (self::files() as $m) {
            if ($m['version'] <= self::BASELINE_VERSION) {
                Database::execute(
                    'INSERT IGNORE INTO schema_migrations (version, name) VALUES (?, ?)',
                    [$m['version'], $m['name']]
                );
            }
        }
    }

    /** Every migration file on disk, oldest first. */
    public static function files(): array
    {
        $out = [];
        foreach (glob(__DIR__ . '/../sql/migration_v*.sql') ?: [] as $path) {
            if (preg_match('/migration_v(\d+)_(.+)\.sql$/', basename($path), $m)) {
                $out[] = ['version' => (int)$m[1], 'name' => $m[2], 'path' => $path];
            }
        }
        usort($out, fn($a, $b) => $a['version'] <=> $b['version']);
        return $out;
    }

    /** Migrations on disk that this database has not recorded yet. */
    public static function pending(): array
    {
        self::ensureTable();
        $applied = array_column(
            Database::fetchAll('SELECT version FROM schema_migrations'), 'version'
        );
        $applied = array_map('intval', $applied);
        return array_values(array_filter(
            self::files(), fn($m) => !in_array($m['version'], $applied, true)
        ));
    }

    /**
     * Run every pending migration in order, stopping at the first failure so
     * a later migration never lands on a half-applied earlier one.
     *
     * @return array{applied: array, failed: ?array, message: string}
     */
    public static function runPending(): array
    {
        $pending = self::pending();
        if (!$pending) {
            return ['applied' => [], 'failed' => null,
                    'message' => 'ডাটাবেস ইতিমধ্যে আপ-টু-ডেট।'];
        }

        // Two admins clicking at once must not both run the same ALTER.
        $lock = Database::fetchOne("SELECT GET_LOCK('shop_migrate', 10) AS ok");
        if ((int)($lock['ok'] ?? 0) !== 1) {
            return ['applied' => [], 'failed' => null,
                    'message' => 'আরেকটি আপডেট এখন চলছে। একটু পরে আবার চেষ্টা করুন।'];
        }

        $applied = [];
        $failed  = null;
        try {
            foreach ($pending as $m) {
                try {
                    foreach (self::splitStatements((string)file_get_contents($m['path'])) as $sql) {
                        Database::execute($sql);
                    }
                    Database::execute(
                        'INSERT IGNORE INTO schema_migrations (version, name) VALUES (?, ?)',
                        [$m['version'], $m['name']]
                    );
                    $applied[] = 'v' . $m['version'] . ' — ' . $m['name'];
                } catch (Throwable $e) {
                    error_log('Migration v' . $m['version'] . ' failed: ' . $e->getMessage());
                    $failed = ['version' => $m['version'], 'name' => $m['name'],
                               'error' => $e->getMessage()];
                    break;
                }
            }
        } finally {
            Database::fetchOne("SELECT RELEASE_LOCK('shop_migrate') AS ok");
        }

        $message = $failed
            ? count($applied) . 'টি আপডেট হয়েছে, তারপর v' . $failed['version'] . ' ব্যর্থ হয়েছে।'
            : count($applied) . 'টি আপডেট সফলভাবে প্রয়োগ করা হয়েছে।';

        return ['applied' => $applied, 'failed' => $failed, 'message' => $message];
    }

    /**
     * Split a .sql file into executable statements.
     *
     * A plain explode(';') corrupts this project's files: they define stored
     * procedures whose bodies contain semicolons, wrapped in DELIMITER $$.
     * So walk the text once, tracking what we are inside of — a quoted
     * string, an identifier, a comment — and only break on a delimiter that
     * is actually at statement level. DELIMITER lines change the terminator
     * rather than being sent to the server.
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buf        = '';
        $delimiter  = ';';
        $len        = strlen($sql);
        $i          = 0;

        while ($i < $len) {
            $ch   = $sql[$i];
            $rest = substr($sql, $i);

            // DELIMITER directive — only meaningful at the start of a line
            if (($buf === '' || substr($buf, -1) === "\n")
                && preg_match('/^DELIMITER[ \t]+(\S+)[ \t]*(\r?\n|$)/i', $rest, $m)) {
                $delimiter = $m[1];
                $i += strlen($m[0]);
                continue;
            }

            // Line comments
            if ($ch === '#' || ($ch === '-' && substr($rest, 0, 3) === '-- ')
                || ($ch === '-' && $rest === '--')) {
                $nl = strpos($sql, "\n", $i);
                $i  = $nl === false ? $len : $nl + 1;
                $buf .= "\n";
                continue;
            }

            // Block comments
            if ($ch === '/' && substr($rest, 0, 2) === '/*') {
                $end = strpos($sql, '*/', $i + 2);
                $i   = $end === false ? $len : $end + 2;
                continue;
            }

            // Quoted string / quoted identifier — copy through verbatim
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf  .= $ch;
                $i++;
                while ($i < $len) {
                    $c = $sql[$i];
                    if ($c === '\\' && $quote !== '`' && $i + 1 < $len) {
                        $buf .= $c . $sql[$i + 1];   // escaped char
                        $i   += 2;
                        continue;
                    }
                    $buf .= $c;
                    $i++;
                    if ($c === $quote) {
                        // doubled quote ('' or "") is an escaped quote, not an end
                        if ($i < $len && $sql[$i] === $quote) { $buf .= $sql[$i]; $i++; continue; }
                        break;
                    }
                }
                continue;
            }

            // Statement terminator at top level
            if (substr($rest, 0, strlen($delimiter)) === $delimiter) {
                $stmt = trim($buf);
                if ($stmt !== '') $statements[] = $stmt;
                $buf = '';
                $i  += strlen($delimiter);
                continue;
            }

            $buf .= $ch;
            $i++;
        }

        $tail = trim($buf);
        if ($tail !== '') $statements[] = $tail;

        return $statements;
    }
}
