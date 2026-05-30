<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdmin     = User::isAdmin();

$menuItems = [
    ['icon' => 'bi-speedometer2',  'label' => 'ড্যাশবোর্ড',          'href' => 'dashboard.php',  'admin' => false],
    ['icon' => 'bi-box-seam',      'label' => 'পণ্য',                'href' => 'products.php',   'admin' => true],
    ['icon' => 'bi-stack',         'label' => 'স্টক',                'href' => 'stock.php',      'admin' => false],
    ['icon' => 'bi-cart-check',    'label' => 'বিক্রয়',              'href' => 'sales.php',      'admin' => false],
    ['icon' => 'bi-people',        'label' => 'কাস্টমার',            'href' => 'customers.php',  'admin' => false],
    ['icon' => 'bi-wallet2',       'label' => 'বাকি / পেমেন্ট',     'href' => 'payments.php',   'admin' => false],
    ['icon' => 'bi-truck',         'label' => 'সাপ্লাইয়ার',          'href' => 'suppliers.php',  'admin' => true],
    ['icon' => 'bi-bar-chart-line','label' => 'রিপোর্ট',             'href' => 'reports.php',    'admin' => true],
    ['icon' => 'bi-people-fill',   'label' => 'ব্যবহারকারী',         'href' => 'users.php',      'admin' => true],
    ['icon' => 'bi-gear',          'label' => 'সেটিংস',             'href' => 'settings.php',   'admin' => true],
];
?>
<div id="sidebar" class="sidebar bg-dark">
    <ul class="nav flex-column px-2 pt-2">
        <?php foreach ($menuItems as $item): ?>
            <?php if ($item['admin'] && !$isAdmin) continue; ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/pages/<?= $item['href'] ?>"
                   class="nav-link text-white rounded mb-1 <?= $currentPage === $item['href'] ? 'active-menu' : '' ?>">
                    <i class="<?= $item['icon'] ?> me-2"></i><?= $item['label'] ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
