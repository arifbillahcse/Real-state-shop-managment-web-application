<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Customer.php';
requireLogin();

$pageTitle = 'বাকি / পেমেন্ট';

// Customers with their dues for dropdowns
$allCustomers = Customer::getCustomers();
// Only customers who have outstanding dues
$dueCustomers = array_filter($allCustomers, fn($c) => (float)$c['total_due'] > 0);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-cash-stack me-2"></i>বাকি / পেমেন্ট</h4>
    <button class="btn btn-success" onclick="openPaymentModal()">
      <i class="bi bi-cash-coin me-1"></i>পেমেন্ট নিন
    </button>
  </div>

  <!-- Due summary cards -->
  <div class="row g-3 mb-4">
    <?php
      $totalDue      = array_sum(array_column($allCustomers, 'total_due'));
      $dueCount      = count($dueCustomers);
      $totalPurchase = array_sum(array_column($allCustomers, 'total_purchase'));
    ?>
    <div class="col-md-4">
      <div class="card stat-card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-danger text-white">
            <i class="bi bi-exclamation-circle fs-4"></i>
          </div>
          <div>
            <div class="stat-value text-danger"><?= money($totalDue) ?></div>
            <div class="stat-label">মোট বাকি</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-warning text-dark">
            <i class="bi bi-people fs-4"></i>
          </div>
          <div>
            <div class="stat-value"><?= $dueCount ?> জন</div>
            <div class="stat-label">বাকিদার গ্রাহক</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card stat-card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-success text-white">
            <i class="bi bi-cart-check fs-4"></i>
          </div>
          <div>
            <div class="stat-value"><?= money($totalPurchase) ?></div>
            <div class="stat-label">মোট বিক্রয়</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <ul class="nav nav-pills mb-4" role="tablist">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#dueListTab" type="button"
              id="dueListTabBtn">
        <i class="bi bi-people me-1"></i>বাকি তালিকা
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#historyTab" type="button"
              id="historyTabBtn">
        <i class="bi bi-clock-history me-1"></i>পেমেন্ট ইতিহাস
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ===== DUE LIST TAB ===== -->
    <div class="tab-pane fade show active" id="dueListTab">
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>কাস্টমার</th>
                <th>ফোন</th>
                <th class="text-end">মোট ক্রয়</th>
                <th class="text-end">পরিশোধ</th>
                <th class="text-end">বাকি</th>
                <th class="text-center">একশন</th>
              </tr>
            </thead>
            <tbody id="dueListBody">
              <?php
              $dueList = array_values(array_filter($allCustomers, fn($c) => (float)$c['total_due'] > 0));
              if (empty($dueList)): ?>
              <tr>
                <td colspan="7" class="text-center py-5 text-success">
                  <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                  সকল গ্রাহকের বাকি পরিশোধ হয়েছে!
                </td>
              </tr>
              <?php else: ?>
              <?php foreach ($dueList as $i => $c): ?>
              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td class="fw-semibold"><?= e($c['name']) ?></td>
                <td><?= e($c['phone'] ?: '—') ?></td>
                <td class="text-end"><?= money((float)$c['total_purchase']) ?></td>
                <td class="text-end"><?= money((float)$c['total_purchase'] - (float)$c['total_due']) ?></td>
                <td class="text-end">
                  <span class="badge bg-danger fs-6"><?= money((float)$c['total_due']) ?></span>
                </td>
                <td class="text-center">
                  <button class="btn btn-sm btn-success me-1"
                          onclick="goToPayment(<?= $c['id'] ?>)"
                          title="পেমেন্ট নিন">
                    <i class="bi bi-cash-coin me-1"></i>পেমেন্ট নিন
                  </button>
                  <button class="btn btn-sm btn-outline-info me-1"
                          onclick="openNotes(<?= $c['id'] ?>, '<?= e(addslashes($c['name'])) ?>')"
                          title="নোট">
                    <i class="bi bi-sticky"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-secondary"
                          onclick="goToLedger(<?= $c['id'] ?>)"
                          title="খাতা দেখুন">
                    <i class="bi bi-journal-text"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- /dueListTab -->

    <!-- ===== HISTORY TAB ===== -->
    <div class="tab-pane fade" id="historyTab">
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">তারিখ থেকে</label>
              <input type="date" class="form-control form-control-sm" id="hDateFrom">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">তারিখ পর্যন্ত</label>
              <input type="date" class="form-control form-control-sm" id="hDateTo">
            </div>
            <div class="col-md-4">
              <label class="form-label small text-muted mb-1">কাস্টমার</label>
              <select class="form-select form-select-sm" id="hCustomer">
                <option value="">সকল কাস্টমার</option>
                <?php foreach ($allCustomers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <button class="btn btn-primary btn-sm w-100" onclick="loadHistory()">
                <i class="bi bi-search"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-dark">
              <tr>
                <th>তারিখ</th>
                <th>কাস্টমার</th>
                <th>ইনভয়েস</th>
                <th>পদ্ধতি</th>
                <th>রেফ</th>
                <th class="text-end">পরিমাণ</th>
                <th>নোট</th>
              </tr>
            </thead>
            <tbody id="historyBody">
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  ইতিহাস ট্যাবে ক্লিক করলে লোড হবে
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" id="payPaginationBar" style="display:none!important">
          <small class="text-muted" id="payPageInfo"></small>
          <nav><ul class="pagination pagination-sm mb-0" id="payPagination"></ul></nav>
        </div>
      </div>
    </div><!-- /historyTab -->

  </div><!-- /tab-content -->
</div>
</div>

<!-- ===== PAYMENT MODAL ===== -->
<div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>পেমেন্ট নিন</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="paymentForm" onsubmit="submitPayment(event)">

          <div class="mb-3">
            <label class="form-label fw-semibold">কাস্টমার <span class="text-danger">*</span></label>
            <select class="form-select" id="payCustomerId" name="customer_id"
                    onchange="onCustomerChange(this)" required>
              <option value="">-- কাস্টমার নির্বাচন করুন --</option>
              <?php foreach ($allCustomers as $c): ?>
              <option value="<?= $c['id'] ?>" data-due="<?= $c['total_due'] ?>">
                <?= e($c['name']) ?>
                <?php if ((float)$c['total_due'] > 0): ?>
                  — বাকি: <?= money((float)$c['total_due']) ?>
                <?php endif; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="outstandingSection" class="d-none mb-3">
            <label class="form-label fw-semibold text-muted small">বাকি বিক্রয় (ক্লিক করলে স্বয়ংক্রিয় পরিমাণ বসবে)</label>
            <div id="outstandingSalesList" class="border rounded p-2 bg-light">
              <div class="text-center text-muted small py-2">লোড হচ্ছে...</div>
            </div>
          </div>
          <input type="hidden" id="selectedSaleId" name="sale_id" value="">

          <div class="mb-3">
            <label class="form-label fw-semibold">পরিমাণ (৳) <span class="text-danger">*</span></label>
            <input type="number" class="form-control form-control-lg"
                   id="payAmount" name="amount"
                   min="0.01" step="0.01" placeholder="০.০০" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">পেমেন্ট পদ্ধতি</label>
              <select class="form-select" id="payMethod" name="payment_method">
                <option value="cash">নগদ</option>
                <option value="mobile_banking">মোবাইল ব্যাংকিং</option>
                <option value="cheque">চেক</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">তারিখ</label>
              <input type="date" class="form-control" id="payDate"
                     name="payment_date" value="<?= today() ?>">
            </div>
          </div>

          <div id="refNoSection" class="mb-3 d-none">
            <label class="form-label fw-semibold">রেফারেন্স নং (চেক/মোবাইল)</label>
            <input type="text" class="form-control" id="payRefNo"
                   name="reference_no" maxlength="100">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold text-muted">নোট</label>
            <textarea class="form-control" id="payNote" name="note"
                      rows="2" maxlength="500"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="submit" form="paymentForm" class="btn btn-success" id="submitPayBtn">
          <i class="bi bi-check-circle me-2"></i>পেমেন্ট সংরক্ষণ করুন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== CUSTOMER NOTES MODAL ===== -->
<div class="modal fade" id="notesModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-sticky me-2"></i>নোট — <span id="notesCustomerName"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if (User::isAdminOrManager()): ?>
        <form id="noteForm" class="mb-3" onsubmit="submitNote(event)">
          <input type="hidden" id="noteCustomerId">
          <label class="form-label fw-semibold">নতুন নোট যোগ করুন</label>
          <textarea class="form-control mb-2" id="noteText" rows="2"
                    maxlength="500" placeholder="যেমন: আগামী মাসে পরিশোধ করবে" required></textarea>
          <button type="submit" class="btn btn-primary btn-sm" id="noteSaveBtn">
            <i class="bi bi-plus-circle me-1"></i>নোট যোগ করুন
          </button>
        </form>
        <hr>
        <?php endif; ?>
        <div id="notesList">
          <div class="text-center text-muted py-3">লোড হচ্ছে...</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const IS_ADMIN = <?= User::isAdminOrManager() ? 'true' : 'false' ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/payments.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
