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
    <!-- Apply saved theme before paint to avoid flash -->
    <script>(function(){try{if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-bs-theme','dark');}catch(e){}})();</script>
    <title><?= e($pageTitle) ?> — <?= e($shopName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
</head>
<body>

<!-- TOP NAVBAR -->
<?php
    $_roleLabel = User::isAdmin() ? 'Admin' : (isManager() ? 'Manager' : 'Staff');
    $_roleColor = User::isAdmin() ? 'danger' : (isManager() ? 'primary' : 'secondary');
    $_userName  = $_SESSION['user_name'] ?? '';
    $_initial   = mb_substr(trim($_userName), 0, 1, 'UTF-8') ?: 'U';
?>
<nav class="navbar app-navbar px-3 py-2 fixed-top">
    <div class="d-flex align-items-center gap-2">
        <!-- Sidebar toggle -->
        <button class="btn btn-sm nav-toggle-btn" id="sidebarToggle">
            <i class="bi bi-list fs-5"></i>
        </button>
        <span class="navbar-brand app-brand mb-0 fw-bold fs-6">
            <span class="brand-logo"><i class="bi bi-shop"></i></span>
            <span class="d-none d-sm-inline"><?= e($shopName) ?></span>
        </span>
    </div>
    <div class="d-flex align-items-center gap-2 gap-md-3">
        <button class="btn btn-sm theme-toggle" id="themeToggle" title="থিম পরিবর্তন" type="button">
            <i class="bi bi-moon-stars"></i>
        </button>
        <div class="user-chip d-none d-md-flex">
            <span class="user-avatar"><?= e($_initial) ?></span>
            <span class="user-meta">
                <span class="user-name"><?= e($_userName) ?></span>
                <span class="badge role-badge bg-<?= $_roleColor ?>"><?= $_roleLabel ?></span>
            </span>
        </div>
        <a href="<?= BASE_URL ?>/pages/logout.php" class="btn btn-sm btn-logout">
            <i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">লগআউট</span>
        </a>
    </div>
</nav>
