<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();
$pageTitle = 'পেমেন্ট / খাতা';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
    <div class="container-fluid py-4">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h4 class="mb-0"><i class="bi bi-cash-stack me-2"></i>পেমেন্ট / খাতা</h4>
        </div>
        <div class="text-center py-5">
            <i class="bi bi-clock-history" style="font-size:4rem;color:#ccc;"></i>
            <h4 class="mt-3 text-muted">শীঘ্রই আসছে</h4>
            <p class="text-muted">এই পেজটি পরবর্তী আপডেটে যুক্ত হবে।</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
