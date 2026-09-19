<?php

require_once __DIR__ . '/BaseModel.php';

/**
 * Low Stock Alert Center (§9).
 * Tiers against the per-product (or per-branch) threshold:
 *   red    → stock below 50% of threshold (অত্যন্ত জরুরি)
 *   yellow → stock at/below threshold      (সতর্কতা)
 *   green  → stock above threshold          (স্বাভাবিক)
 * Every red/yellow sighting is recorded once per day in low_stock_history.
 */
class AlertCenter
{
    public static function tier(float $stock, float $threshold): string
    {
        if ($threshold <= 0)              return 'green';
        if ($stock < $threshold * 0.5)    return 'red';
        if ($stock <= $threshold)         return 'yellow';
        return 'green';
    }

    // $branchId: null = global stock; N = that branch (uses branch thresholds)
    public static function getAlerts(?int $branchId, bool $includeGreen = false): array
    {
        if ($branchId) {
            $rows = Database::fetchAll(
                'SELECT product_id, product_name, product_type, unit,
                        current_stock, min_stock, branch_id, branch_name
                 FROM vw_branch_stock
                 WHERE branch_id = ? AND min_stock > 0
                 ORDER BY current_stock / min_stock ASC',
                [$branchId]
            );
        } else {
            $rows = Database::fetchAll(
                'SELECT product_id, product_name, product_type, unit,
                        current_stock, min_stock,
                        NULL AS branch_id, NULL AS branch_name
                 FROM vw_current_stock
                 WHERE min_stock > 0
                 ORDER BY current_stock / min_stock ASC'
            );
        }

        $out = [];
        foreach ($rows as $r) {
            $t = self::tier((float)$r['current_stock'], (float)$r['min_stock']);
            if ($t === 'green' && !$includeGreen) continue;
            $r['tier'] = $t;
            $out[] = $r;
        }
        return $out;
    }

    // Record today's red/yellow alerts (once per product/branch/day)
    public static function logToday(?int $branchId): int
    {
        $alerts = self::getAlerts($branchId, false);
        $logged = 0;
        foreach ($alerts as $a) {
            $logged += Database::execute(
                'INSERT IGNORE INTO low_stock_history
                    (product_id, branch_id, stock_qty, threshold, tier, alerted_on)
                 VALUES (?, ?, ?, ?, ?, CURDATE())',
                [(int)$a['product_id'], $a['branch_id'] !== null ? (int)$a['branch_id'] : null,
                 (float)$a['current_stock'], (float)$a['min_stock'], $a['tier']]
            );
        }
        return $logged;
    }

    // How often each product has been on the alert list (demand signal)
    public static function history(int $days = 90): array
    {
        return Database::fetchAll(
            'SELECT h.product_id, p.name AS product_name, p.unit,
                    COUNT(*)                    AS alert_days,
                    SUM(h.tier = "red")         AS red_days,
                    MAX(h.alerted_on)           AS last_alerted,
                    MIN(h.alerted_on)           AS first_alerted
             FROM low_stock_history h
             JOIN products p ON p.id = h.product_id
             WHERE h.alerted_on >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY h.product_id
             ORDER BY alert_days DESC, red_days DESC',
            [$days]
        );
    }

    // Queue an SMS summary to the manager (delivered once a gateway is set)
    public static function notifyManager(?int $branchId): bool|string
    {
        $alerts = self::getAlerts($branchId, false);
        if (empty($alerts)) return 'NO_ALERTS';

        $phone = Setting::get('alert_phone', Setting::get('shop_phone', ''));
        if ($phone === '' || $phone === '01XXXXXXXXX') return 'NO_PHONE';

        $lines = array_map(
            fn($a) => $a['product_name'] . ': ' . rtrim(rtrim($a['stock_qty'] ?? $a['current_stock'], '0'), '.')
                    . '/' . rtrim(rtrim($a['min_stock'], '0'), '.') . ' ' . $a['unit'],
            array_slice($alerts, 0, 10)
        );
        $msg = "স্টক সতর্কতা (" . count($alerts) . " পণ্য):\n" . implode("\n", $lines);

        require_once __DIR__ . '/Notifier.php';
        Notifier::send($phone, $msg, 'sms', 'low_stock_history', null);
        return true;
    }
}
