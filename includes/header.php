<?php
// $pageTitle must be set before including header
$pageTitle = $pageTitle ?? APP_NAME;
$shopName  = Setting::get('shop_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — <?= e($shopName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="navbar navbar-dark bg-dark px-3 py-2 fixed-top shadow">
    <div class="d-flex align-items-center gap-2">
        <!-- Sidebar toggle -->
        <button class="btn btn-sm btn-outline-secondary" id="sidebarToggle">
            <i class="bi bi-list fs-5"></i>
        </button>
        <span class="navbar-brand mb-0 fw-bold fs-6">
            <i class="bi bi-shop me-1 text-danger"></i><?= e($shopName) ?>
        </span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="text-light small d-none d-md-inline">
            <i class="bi bi-person-circle me-1"></i>
            <?= e($_SESSION['user_name'] ?? '') ?>
            <span class="badge bg-<?= User::isAdmin() ? 'danger' : 'secondary' ?> ms-1">
                <?= User::isAdmin() ? 'Admin' : 'Staff' ?>
            </span>
        </span>
        <a href="<?= BASE_URL ?>/pages/logout.php" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">লগআউট</span>
        </a>
    </div>
</nav>
