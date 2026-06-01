<?php
require_once __DIR__ . '/_guard.php';

$search = trim($_GET['search'] ?? '');
$limit  = min(200, max(1, (int)($_GET['limit'] ?? 100)));

if ($search !== '') {
    $rows = Database::fetchAll(
        'SELECT * FROM free_notes WHERE customer_name LIKE ? ORDER BY note_date DESC, id DESC LIMIT ' . $limit,
        ['%' . $search . '%']
    );
} else {
    $rows = Database::fetchAll(
        'SELECT * FROM free_notes ORDER BY note_date DESC, id DESC LIMIT ' . $limit
    );
}

jsonResponse(true, '', ['notes' => $rows]);
