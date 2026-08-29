<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Branch.php';
requireLogin();

$pageTitle = 'স্টক সতর্কতা কেন্দ্র';
$branches  = Branch::getVisibleBranches();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-bell me-2"></i>স্টক সতর্কতা কেন্দ্র (Low Stock Alert Center)</h4>
  </div>

  <!-- Legend + filters -->
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end">
        <?php if (!empty($branches)): ?>
        <div class="col-12 col-md-4">
          <label class="form-label small text-muted mb-1">ব্রাঞ্চ</label>
          <select class="form-select form-select-sm" id="alertBranch" onchange="loadAlerts()"
                  <?= lockedBranchId() !== null ? 'disabled' : '' ?>>
            <?php if (lockedBranchId() === null): ?>
            <option value="">সব ব্রাঞ্চ একসাথে (গ্লোবাল স্টক)</option>
            <?php endif; ?>
            <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= lockedBranchId() !== null ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-12 col-md-4">
          <div class="form-check form-switch mt-md-4">
            <input class="form-check-input" type="checkbox" id="showAll" onchange="loadAlerts()">
            <label class="form-check-label small" for="showAll">সবুজ (স্বাভাবিক) পণ্যও দেখান</label>
          </div>
        </div>
        <div class="col-12 col-md-4 text-md-end small">
          <span class="badge bg-danger">লাল</span> সীমার ৫০% এর নিচে &nbsp;
          <span class="badge bg-warning text-dark">হলুদ</span> সীমার কাছাকাছি &nbsp;
          <span class="badge bg-success">সবুজ</span> স্বাভাবিক
        </div>
      </div>
    </div>
  </div>

  <ul class="nav nav-pills mb-3">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#alertsTab" type="button">
      <i class="bi bi-exclamation-triangle me-1"></i>বর্তমান সতর্কতা <span class="badge bg-danger" id="alertCount">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#historyTab" type="button" onclick="loadHistory()">
      <i class="bi bi-clock-history me-1"></i>ইতিহাস ট্র্যাকিং</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="alertsTab">
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th style="width:80px" class="text-center">স্তর</th>
                <th>পণ্য</th>
                <th>ক্যাটাগরি</th>
                <?php if (!empty($branches)): ?><th>ব্রাঞ্চ</th><?php endif; ?>
                <th class="text-end">বর্তমান স্টক</th>
                <th class="text-end">সর্বনিম্ন সীমা</th>
                <th class="text-end">অবস্থা</th>
              </tr>
            </thead>
            <tbody id="alertsBody">
              <tr><td colspan="7" class="text-center text-muted py-4">লোড হচ্ছে...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <p class="small text-muted mt-2">
        <i class="bi bi-info-circle me-1"></i>সীমা পরিবর্তন: পণ্য পেজে (কেন্দ্রীয়) বা পণ্যের "ব্রাঞ্চ মূল্য" মডালে (ব্রাঞ্চভিত্তিক) মিনিমাম স্টক সেট করুন।
      </p>
    </div>

    <div class="tab-pane fade" id="historyTab">
      <div class="card shadow-sm mb-2">
        <div class="card-body py-2 d-flex align-items-end gap-2 flex-wrap">
          <div>
            <label class="form-label small text-muted mb-1">সময়কাল</label>
            <select class="form-select form-select-sm" id="historyDays" onchange="loadHistory()" data-no-search="1">
              <option value="30">শেষ ৩০ দিন</option>
              <option value="90" selected>শেষ ৯০ দিন</option>
              <option value="180">শেষ ১৮০ দিন</option>
              <option value="365">শেষ ১ বছর</option>
            </select>
          </div>
          <p class="small text-muted mb-1 ms-2">
            কোন পণ্য কতবার সতর্কতা তালিকায় এসেছে — বেশি এলে বুঝবেন চাহিদা বেশি, ঘন ঘন কিনতে হয়।
          </p>
        </div>
      </div>
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>পণ্য</th>
                <th class="text-end">মোট সতর্কতা দিন</th>
                <th class="text-end">লাল (জরুরি) দিন</th>
                <th>প্রথম সতর্কতা</th>
                <th>সর্বশেষ সতর্কতা</th>
              </tr>
            </thead>
            <tbody id="historyBody">
              <tr><td colspan="5" class="text-center text-muted py-4">—</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<script>
const BASE_URL     = '<?= BASE_URL ?>';
const HAS_BRANCHES = <?= !empty($branches) ? 'true' : 'false' ?>;

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

const TIER = {
    red:    ['<span class="badge bg-danger">লাল</span>',              'table-danger'],
    yellow: ['<span class="badge bg-warning text-dark">হলুদ</span>', 'table-warning'],
    green:  ['<span class="badge bg-success">সবুজ</span>',            ''],
};

async function loadAlerts() {
    const branchId = document.getElementById('alertBranch')?.value || '';
    const all      = document.getElementById('showAll').checked ? '1' : '';
    const tbody    = document.getElementById('alertsBody');
    const cols     = HAS_BRANCHES ? 7 : 6;
    tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>`;
    try {
        const res  = await fetch(`${BASE_URL}/api/get_low_stock_alerts.php?branch_id=${branchId}&all=${all}`);
        const data = await res.json();
        const alerts = (data.alerts) || [];
        const urgent = alerts.filter(a => a.tier !== 'green').length;
        document.getElementById('alertCount').textContent = urgent;

        tbody.innerHTML = alerts.length ? alerts.map(a => {
            const [badge, rowCls] = TIER[a.tier] || TIER.green;
            const pct = parseFloat(a.min_stock) > 0
                ? Math.round(parseFloat(a.current_stock) / parseFloat(a.min_stock) * 100) : 100;
            return `<tr class="${rowCls}">
                <td class="text-center">${badge}</td>
                <td class="fw-semibold">${esc(a.product_name)}</td>
                <td class="small">${esc(a.product_type || '—')}</td>
                ${HAS_BRANCHES ? `<td class="small">${esc(a.branch_name || 'গ্লোবাল')}</td>` : ''}
                <td class="text-end">${parseFloat(a.current_stock)} ${esc(a.unit)}</td>
                <td class="text-end">${parseFloat(a.min_stock)} ${esc(a.unit)}</td>
                <td class="text-end small">${pct}% (সীমার তুলনায়)</td>
            </tr>`;
        }).join('')
        : `<tr><td colspan="${cols}" class="text-center text-success py-4">
             <i class="bi bi-check-circle me-1"></i>কোনো স্টক সতর্কতা নেই — সব পণ্যের স্টক ঠিক আছে
           </td></tr>`;
    } catch {
        tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-danger py-4">লোড করা যায়নি</td></tr>`;
    }
}

async function loadHistory() {
    const days  = document.getElementById('historyDays').value;
    const tbody = document.getElementById('historyBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>';
    try {
        const res  = await fetch(`${BASE_URL}/api/get_low_stock_history.php?days=${days}`);
        const data = await res.json();
        const rows = (data.history) || [];
        tbody.innerHTML = rows.length ? rows.map(h => `
            <tr>
                <td class="fw-semibold">${esc(h.product_name)}</td>
                <td class="text-end"><span class="badge bg-secondary">${h.alert_days} দিন</span></td>
                <td class="text-end">${+h.red_days ? `<span class="badge bg-danger">${h.red_days} দিন</span>` : '—'}</td>
                <td>${h.first_alerted}</td>
                <td>${h.last_alerted}</td>
            </tr>`).join('')
            : '<tr><td colspan="5" class="text-center text-muted py-4">এই সময়ে কোনো সতর্কতার রেকর্ড নেই</td></tr>';
    } catch {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">লোড করা যায়নি</td></tr>';
    }
}

loadAlerts();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
