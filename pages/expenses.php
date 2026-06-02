<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Expense.php';
require_once __DIR__ . '/../classes/Branch.php';
requireLogin();
requireManagerOrAdmin();

$pageTitle  = 'খরচ ট্র্যাকিং';
$categories = Expense::getCategories();
$branches   = Branch::getBranches();

$thisMonth  = Expense::getTotalExpenses(date('Y-m-01'), date('Y-m-d'));
$thisYear   = Expense::getTotalExpenses(date('Y-01-01'), date('Y-m-d'));
$today      = Expense::getTotalExpenses(date('Y-m-d'), date('Y-m-d'));

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-cash-stack me-2 text-danger"></i>খরচ ট্র্যাকিং</h4>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
      <i class="bi bi-plus-lg me-1"></i>নতুন খরচ
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-danger text-white"><i class="bi bi-calendar-day fs-4"></i></div>
          <div>
            <div class="stat-value text-danger"><?= money($today) ?></div>
            <div class="stat-label">আজকের খরচ</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-warning text-dark"><i class="bi bi-calendar-month fs-4"></i></div>
          <div>
            <div class="stat-value"><?= money($thisMonth) ?></div>
            <div class="stat-label">এই মাসের খরচ</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-secondary text-white"><i class="bi bi-calendar fs-4"></i></div>
          <div>
            <div class="stat-value"><?= money($thisYear) ?></div>
            <div class="stat-label">এই বছরের খরচ</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-pills mb-4" role="tablist">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#expListTab" type="button">
        <i class="bi bi-list-ul me-1"></i>খরচ তালিকা
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#profitTab" type="button"
              id="profitTabBtn">
        <i class="bi bi-graph-up-arrow me-1"></i>লাভ-ক্ষতি
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#catTab" type="button">
        <i class="bi bi-tags me-1"></i>ক্যাটাগরি
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ===== EXPENSE LIST TAB ===== -->
    <div class="tab-pane fade show active" id="expListTab">
      <!-- Filter -->
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">তারিখ থেকে</label>
              <input type="date" class="form-control form-control-sm" id="eFrom"
                     value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">তারিখ পর্যন্ত</label>
              <input type="date" class="form-control form-control-sm" id="eTo"
                     value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">ক্যাটাগরি</label>
              <select class="form-select form-select-sm" id="eCat">
                <option value="">সকল ক্যাটাগরি</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (!empty($branches)): ?>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">ব্রাঞ্চ</label>
              <select class="form-select form-select-sm" id="eBranch">
                <option value="">সকল ব্রাঞ্চ</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
              <button class="btn btn-primary btn-sm w-100" onclick="loadExpenses()">
                <i class="bi bi-search"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-dark">
              <tr>
                <th>তারিখ</th>
                <th>ক্যাটাগরি</th>
                <?php if (!empty($branches)): ?><th>ব্রাঞ্চ</th><?php endif; ?>
                <th>বিবরণ</th>
                <th class="text-end">পরিমাণ</th>
                <th class="text-center">একশন</th>
              </tr>
            </thead>
            <tbody id="expenseBody">
              <tr><td colspan="6" class="text-center py-5 text-muted">
                <span class="spinner-border spinner-border-sm me-2"></span>লোড হচ্ছে...
              </td></tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" id="expPaginationBar" style="display:none!important">
          <small class="text-muted" id="expPageInfo"></small>
          <nav><ul class="pagination pagination-sm mb-0" id="expPagination"></ul></nav>
        </div>
      </div>
    </div><!-- /expListTab -->

    <!-- ===== PROFIT / LOSS TAB ===== -->
    <div class="tab-pane fade" id="profitTab">
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">তারিখ থেকে</label>
              <input type="date" class="form-control form-control-sm" id="pFrom"
                     value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">তারিখ পর্যন্ত</label>
              <input type="date" class="form-control form-control-sm" id="pTo"
                     value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-2">
              <button class="btn btn-primary btn-sm w-100" onclick="loadProfitLoss()">
                <i class="bi bi-search"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div id="plContent">
        <div class="text-center text-muted py-5">
          <i class="bi bi-graph-up-arrow fs-1 d-block mb-2 opacity-25"></i>
          উপরে তারিখ নির্বাচন করে অনুসন্ধান করুন
        </div>
      </div>
    </div><!-- /profitTab -->

    <!-- ===== CATEGORY TAB ===== -->
    <div class="tab-pane fade" id="catTab">
      <div class="row g-3">
        <div class="col-md-5">
          <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">
              <i class="bi bi-plus-circle me-1"></i>নতুন ক্যাটাগরি
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">নাম <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="newCatName" placeholder="যেমন: খাবার খরচ">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">আইকন</label>
                <select class="form-select" id="newCatIcon">
                  <option value="bi-receipt">🧾 রিসিট</option>
                  <option value="bi-house-door">🏠 বাড়ি/ভাড়া</option>
                  <option value="bi-person-badge">👤 বেতন</option>
                  <option value="bi-lightning-charge">⚡ বিদ্যুৎ</option>
                  <option value="bi-wifi">📡 ইন্টারনেট</option>
                  <option value="bi-truck">🚛 পরিবহন</option>
                  <option value="bi-tools">🔧 মেরামত</option>
                  <option value="bi-bag">🛍️ কেনাকাটা</option>
                  <option value="bi-telephone">📞 ফোন</option>
                  <option value="bi-water">💧 পানি বিল</option>
                  <option value="bi-three-dots">⋯ বিবিধ</option>
                </select>
              </div>
              <button class="btn btn-primary w-100" onclick="addCategory()">
                <i class="bi bi-plus-lg me-1"></i>যোগ করুন
              </button>
            </div>
          </div>
        </div>
        <div class="col-md-7">
          <div class="card shadow-sm">
            <div class="card-header fw-semibold">
              <i class="bi bi-tags me-1"></i>বিদ্যমান ক্যাটাগরি
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr><th>ক্যাটাগরি</th><th class="text-center">একশন</th></tr>
                </thead>
                <tbody id="catBody">
                  <?php foreach ($categories as $c): ?>
                  <tr id="catrow<?= $c['id'] ?>">
                    <td><i class="bi <?= e($c['icon']) ?> me-2 text-secondary"></i><?= e($c['name']) ?></td>
                    <td class="text-center">
                      <button class="btn btn-sm btn-outline-danger"
                              onclick="deleteCategory(<?= $c['id'] ?>, '<?= e(addslashes($c['name'])) ?>')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /catTab -->

  </div><!-- /tab-content -->
</div>
</div>

<!-- ===== ADD/EDIT EXPENSE MODAL ===== -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="expModalTitle">
          <i class="bi bi-cash-stack me-2"></i>নতুন খরচ
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="expEditId">

        <div class="mb-3">
          <label class="form-label fw-semibold">পরিমাণ (৳) <span class="text-danger">*</span></label>
          <input type="number" class="form-control form-control-lg" id="expAmount"
                 min="0.01" step="0.01" placeholder="০.০০">
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">ক্যাটাগরি</label>
            <select class="form-select" id="expCategory">
              <option value="">— নির্বাচন করুন —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">তারিখ <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="expDate" value="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <?php if (!empty($branches)): ?>
        <div class="mb-3">
          <label class="form-label fw-semibold">ব্রাঞ্চ</label>
          <select class="form-select" id="expBranch">
            <option value="">— সকল / সাধারণ —</option>
            <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label fw-semibold text-muted">বিবরণ</label>
          <textarea class="form-control" id="expDesc" rows="2" maxlength="500"
                    placeholder="যেমন: মে মাসের অফিস ভাড়া"></textarea>
        </div>

        <div id="expError" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-danger" id="btnSaveExpense" onclick="saveExpense()">
          <i class="bi bi-check-lg me-1"></i>সংরক্ষণ করুন
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL  = '<?= BASE_URL ?>';
const CAN_WRITE = <?= User::isAdminOrManager() ? 'true' : 'false' ?>;
const HAS_BRANCHES = <?= !empty($branches) ? 'true' : 'false' ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/expenses.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
