<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';

if (!isLoggedIn() || !User::isAdmin()) {
    jsonResponse(false, 'Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method');
}

$file = $_FILES['sql_file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'ফাইল আপলোড করতে সমস্যা হয়েছে');
}

// Validate extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'sql') {
    jsonResponse(false, 'শুধুমাত্র .sql ফাইল গ্রহণযোগ্য');
}

// Max 50 MB
if ($file['size'] > 50 * 1024 * 1024) {
    jsonResponse(false, 'ফাইলের আকার সর্বোচ্চ ৫০ MB হতে পারে');
}

$sql = file_get_contents($file['tmp_name']);
if ($sql === false || trim($sql) === '') {
    jsonResponse(false, 'ফাইলটি পড়া যাচ্ছে না বা খালি');
}

// Split into individual statements (handles semicolons inside strings naively but works for mysqldump-style output)
$statements = array_filter(
    array_map('trim', preg_split('/;\s*\n/', $sql)),
    fn($s) => $s !== '' && !str_starts_with($s, '--')
);

$pdo = Database::getInstance();

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"');

    $count = 0;
    foreach ($statements as $stmt) {
        if (trim($stmt) === '') continue;
        $pdo->exec($stmt);
        $count++;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    jsonResponse(true, "ইম্পোর্ট সফল হয়েছে। মোট $count টি স্টেটমেন্ট চালানো হয়েছে।");
} catch (\Throwable $e) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    error_log('DB import error: ' . $e->getMessage());
    jsonResponse(false, 'ইম্পোর্ট ব্যর্থ হয়েছে: ' . $e->getMessage());
}
