<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
requireLogin();

$pageTitle = 'ড্যাশবোর্ড';

// --- Quick stats ---
$todaySales = Database::fetchOne(
    "SELECT COALESCE(SUM(total_amount),0) AS total,
            COALESCE(SUM(paid_amount),0)  AS paid,
            COUNT(*) AS count
     FROM sales
     WHERE sale_date = CURDATE() AND status = 'completed'"
);

$totalDue = Database::fetchOne(
    "SELECT COALESCE(SUM(due_amount),0) AS total FROM sales WHERE status = 'completed'"
);

$stockValue = Database::fetchOne(
    "SELECT COALESCE(SUM(current_stock * buy_price),0) AS total FROM vw_current_stock"
);

$lowStockItems = Database::fetchAll(
    'SELECT * FROM vw_current_stock WHERE current_stock <= min_stock AND min_stock > 0'
);

// Last 7 days sales for chart
$chartData = Database::fetchAll(
    "SELECT sale_date, COALESCE(SUM(total_amount),0) AS total
     FROM sales
     WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       AND status = 'completed'
     GROUP BY sale_date
     ORDER BY sale_date"
);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">

    <!-- Page Header -->
    <div class="page-header">
        <h5><i class="bi bi-speedometer2 me-2 text-danger"></i>ড্যাশবোর্ড</h5>
        <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y, l') ?></span>
    </div>

    <!-- Low stock alert -->
    <?php if ($lowStockItems): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong><?= count($lowStockItems) ?>টি পণ্যের স্টক কম!</strong>
        <?php foreach ($lowStockItems as $item): ?>
            <span class="badge bg-danger ms-1"><?= e($item['product_name']) ?>
                (<?= $item['current_stock'] ?> <?= e($item['unit']) ?>)
            </span>
        <?php endforeach; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">আজকের বিক্রয়</p>
                        <h5 class="fw-bold mb-0"><?= money((float)$todaySales['total']) ?></h5>
                        <small class="text-muted"><?= $todaySales['count'] ?>টি লেনদেন</small>
                    </div>
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-cart-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">আজকের নগদ</p>
                        <h5 class="fw-bold mb-0"><?= money((float)$todaySales['paid']) ?></h5>
                        <small class="text-muted">প্রদত্ত</small>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">মোট বাকি</p>
                        <h5 class="fw-bold text-danger mb-0"><?= money((float)$totalDue['total']) ?></h5>
                        <small class="text-muted">সকল কাস্টমার</small>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">স্টক মূল্য</p>
                        <h5 class="fw-bold mb-0"><?= money((float)$stockValue['total']) ?></h5>
                        <small class="text-muted">বর্তমান স্টক</small>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-boxes"></i>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Sales Chart + Low Stock Table -->
    <div class="row g-3">

        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-bar-chart me-1 text-danger"></i> গত ৭ দিনের বিক্রয়
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="120"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-exclamation-triangle me-1 text-warning"></i> কম স্টকের পণ্য
                </div>
                <div class="card-body p-0">
                    <?php if (empty($lowStockItems)): ?>
                    <p class="text-center text-muted py-4 small">সব পণ্যের স্টক ঠিক আছে।</p>
                    <?php else: ?>
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>পণ্য</th><th>স্টক</th><th>মিনিমাম</th></tr></thead>
                        <tbody>
                        <?php foreach ($lowStockItems as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?></td>
                            <td class="low-stock"><?= $item['current_stock'] ?> <?= e($item['unit']) ?></td>
                            <td><?= $item['min_stock'] ?> <?= e($item['unit']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Prepare chart data from PHP
const rawData  = <?= json_encode($chartData) ?>;
const labels   = [];
const amounts  = [];

// Fill all 7 days even if no sales
for (let i = 6; i >= 0; i--) {
    const d   = new Date();
    d.setDate(d.getDate() - i);
    const key = d.toISOString().slice(0, 10);
    const row = rawData.find(r => r.sale_date === key);
    labels.push(d.toLocaleDateString('bn-BD', { weekday: 'short', day: 'numeric' }));
    amounts.push(row ? parseFloat(row.total) : 0);
}

new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'বিক্রয় (৳)',
            data: amounts,
            backgroundColor: 'rgba(230,57,70,.75)',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '৳' + v.toLocaleString() } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
