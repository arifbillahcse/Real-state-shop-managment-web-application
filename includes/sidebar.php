<?php
$currentPage    = basename($_SERVER['PHP_SELF']);
$isAdmin        = User::isAdmin();
$_isStaff       = isStaff();
$_isManager     = isManager();

// Columns: admin=true means only strict admin can see it
//          manager=true means manager can also see it
//          staff=true means staff can also see it
$menuItems = [
    ['icon' => 'bi-speedometer2',  'label' => 'ড্যাশবোর্ড',      'href' => 'dashboard.php',  'admin' => false, 'manager' => true,  'staff' => true],
    ['icon' => 'bi-box-seam',      'label' => 'পণ্য',            'href' => 'products.php',   'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-stack',         'label' => 'স্টক',            'href' => 'stock.php',      'admin' => false, 'manager' => true,  'staff' => true],
    ['icon' => 'bi-cart-check',    'label' => 'বিক্রয়',          'href' => 'sales.php',       'admin' => false, 'manager' => true,  'staff' => true],
    ['icon' => 'bi-file-earmark-text','label' => 'কোটেশন',       'href' => 'quotations.php',  'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-people',        'label' => 'কাস্টমার',        'href' => 'customers.php',   'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-wallet2',       'label' => 'বাকি / পেমেন্ট', 'href' => 'payments.php',    'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-calendar-check','label' => 'কিস্তি',          'href' => 'installments.php','admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-shop',          'label' => 'ব্রাঞ্চ',         'href' => 'branches.php',   'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-truck',         'label' => 'সাপ্লাইয়ার',      'href' => 'suppliers.php',  'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-journal-text', 'label' => 'খাতা',            'href' => 'khata.php',      'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-bar-chart-line','label' => 'রিপোর্ট',         'href' => 'reports.php',    'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-cash-stack',   'label' => 'খরচ',             'href' => 'expenses.php',   'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-sticky',       'label' => 'নোট',             'href' => 'notes.php',      'admin' => false, 'manager' => true,  'staff' => false],
    ['icon' => 'bi-people-fill',   'label' => 'ব্যবহারকারী',     'href' => 'users.php',      'admin' => true,  'manager' => false, 'staff' => false],
    ['icon' => 'bi-database-fill-down', 'label' => 'ব্যাকআপ',   'href' => 'backup.php',     'admin' => true,  'manager' => false, 'staff' => false],
    ['icon' => 'bi-gear',          'label' => 'সেটিংস',         'href' => 'settings.php',   'admin' => true,  'manager' => false, 'staff' => false],
];
?>
<div id="sidebar" class="sidebar">
    <ul class="nav flex-column px-2 pt-3 pb-4">
        <?php foreach ($menuItems as $item): ?>
            <?php if ($item['admin'] && !$isAdmin) continue; ?>
            <?php if ($_isManager && !$item['manager']) continue; ?>
            <?php if ($_isStaff && !$item['staff']) continue; ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/pages/<?= $item['href'] ?>"
                   class="nav-link sidebar-link <?= $currentPage === $item['href'] ? 'active-menu' : '' ?>">
                    <i class="<?= $item['icon'] ?>"></i><span><?= $item['label'] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
