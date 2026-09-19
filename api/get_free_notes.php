<?php
require_once __DIR__ . '/_guard.php';

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');   // 'pending' | 'done' | ''
$limit  = min(200, max(1, (int)($_GET['limit'] ?? 200)));

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = 'customer_name LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($status === 'pending' || $status === 'done') {
    $where[]  = 'status = ?';
    $params[] = $status;
}

$sql = 'SELECT * FROM free_notes WHERE ' . implode(' AND ', $where)
     . ' ORDER BY is_pinned DESC, note_date DESC, id DESC LIMIT ' . $limit;

jsonResponse(true, '', ['notes' => Database::fetchAll($sql, $params)]);
