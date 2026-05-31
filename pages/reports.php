<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
requireLogin();
requireAdmin();

$pageTitle = 'রিপোর্ট';

$defaultFrom = date('Y-m-01');
$defaultTo   = today();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>রিপোর্ট ও বিশ্লেষণ</h4>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
      <i class="bi bi-printer me-1"></i>প্রিন্ট করুন
    </button>
  </div>

  <!-- Date Range Filter -->
  <div class="card shadow-sm mb-4">
    <div class="card-body py-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small text-muted mb-1">তারিখ থেকে</label>
          <input type="date" class="form-control form-control-sm" id="rFrom"
                 value="<?= $defaultFrom ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label small text-muted mb-1">তারিখ পর্যন্ত</label>
          <input type="date" class="form-control form-control-sm" id="rTo"
                 value="<?= $defaultTo ?>">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary btn-sm w-100" onclick="loadReport()">
            <i class="bi bi-search me-1"></i>রিপোর্ট দেখুন
          </button>
        </div>
        <div class="col-md-4">
          <div class="btn-group btn-group-sm w-100" role="group">
            <button class="btn btn-outline-secondary" onclick="setRange('today')">আজ</button>
            <button class="btn btn-outline-secondary" onclick="setRange('week')">এ সপ্তাহ</button>
            <button class="btn btn-outline-secondary" onclick="setRange('month')">এ মাস</button>
            <button class="btn btn-outline-secondary" onclick="setRange('year')">এ বছর</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="reportLoading" class="text-center py-5 d-none">
    <div class="spinner-border text-primary"></div>
    <p class="text-muted mt-2">রিপোর্ট তৈরি হচ্ছে...</p>
  </div>

  <div id="reportContent">

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card stat-card p-3 h-100">
          <p class="text-muted small mb-1">মোট বিক্রয়</p>
          <h5 class="fw-bold mb-0" id="sumTotalSales">—</h5>
          <small class="text-muted" id="sumSaleCount">—</small>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card stat-card p-3 h-100">
          <p class="text-muted small mb-1">আদায়কৃত পেমেন্ট</p>
          <h5 class="fw-bold mb-0 text-success" id="sumPayments">—</h5>
          <small class="text-muted">এই সময়ে</small>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card stat-card p-3 h-100">
          <p class="text-muted small mb-1">নতুন বাকি</p>
          <h5 class="fw-bold mb-0 text-danger" id="sumDue">—</h5>
          <small class="text-muted">এই সময়ের বিক্রয়ে</small>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card stat-card p-3 h-100">
          <p class="text-muted small mb-1">আনুমানিক লাভ</p>
          <h5 class="fw-bold mb-0 text-primary" id="sumProfit">—</h5>
          <small class="text-muted">বিক্রয় − ক্রয়মূল্য</small>
        </div>
      </div>
    </div>

    <!-- Charts row -->
    <div class="row g-3 mb-4">
      <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-graph-up me-1 text-danger"></i>দৈনিক বিক্রয়
          </div>
          <div class="card-body">
            <canvas id="dailySalesChart" height="100"></canvas>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-pie-chart me-1 text-danger"></i>রড vs সিমেন্ট
          </div>
          <div class="card-body d-flex align-items-center justify-content-center">
            <canvas id="typeChart" height="220"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Top products + dues -->
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-trophy me-1 text-warning"></i>সর্বাধিক বিক্রিত পণ্য
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>পণ্য</th>
                  <th class="text-end">পরিমাণ</th>
                  <th class="text-end">আয়</th>
                </tr>
              </thead>
              <tbody id="topProductsBody">
                <tr><td colspan="3" class="text-center text-muted py-3">—</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold d-flex justify-content-between">
            <span><i class="bi bi-exclamation-circle me-1 text-danger"></i>বাকিদার গ্রাহক</span>
            <span class="badge bg-danger align-self-center" id="duesTotalBadge">—</span>
          </div>
          <div class="table-responsive" style="max-height:300px;overflow-y:auto">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>গ্রাহক</th>
                  <th class="text-end">বাকি</th>
                </tr>
              </thead>
              <tbody id="duesBody">
                <tr><td colspan="2" class="text-center text-muted py-3">—</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Stock valuation -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between flex-wrap">
        <span><i class="bi bi-boxes me-1 text-info"></i>বর্তমান স্টক মূল্যায়ন</span>
        <span class="small">
          ক্রয়মূল্য: <span class="fw-bold" id="stockCostTotal">—</span> |
          বিক্রয়মূল্য: <span class="fw-bold text-success" id="stockValueTotal">—</span>
        </span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>পণ্য</th>
              <th>ধরন</th>
              <th class="text-end">স্টক</th>
              <th class="text-end">ক্রয়মূল্য</th>
              <th class="text-end">স্টক মূল্য (ক্রয়)</th>
              <th class="text-end">স্টক মূল্য (বিক্রয়)</th>
            </tr>
          </thead>
          <tbody id="stockBody">
            <tr><td colspan="6" class="text-center text-muted py-3">—</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Purchase / payment small summary -->
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h6 class="fw-semibold mb-3"><i class="bi bi-cart-plus me-1 text-secondary"></i>ক্রয় সারসংক্ষেপ</h6>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">ক্রয় সংখ্যা</span>
              <span class="fw-semibold" id="purchaseCount">—</span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="text-muted">মোট ক্রয় খরচ</span>
              <span class="fw-semibold" id="purchaseCost">—</span>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h6 class="fw-semibold mb-3"><i class="bi bi-cash-coin me-1 text-success"></i>লাভ বিশ্লেষণ</h6>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">মোট আয় (বিক্রয়)</span>
              <span class="fw-semibold" id="profitRevenue">—</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted">পণ্যের ক্রয়মূল্য</span>
              <span class="fw-semibold" id="profitCost">—</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between">
              <span class="fw-semibold">আনুমানিক লাভ</span>
              <span class="fw-bold text-primary" id="profitNet">—</span>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /reportContent -->
</div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/reports.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
