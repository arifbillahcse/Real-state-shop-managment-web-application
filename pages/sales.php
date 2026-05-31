<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Customer.php';
requireLogin();

$pageTitle = 'বিক্রয়';

$customers = Customer::getCustomers();
$products  = Database::fetchAll(
    'SELECT product_id, product_name, product_type, unit, sell_price, current_stock
     FROM vw_current_stock
     ORDER BY product_type, product_name'
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <h4 class="mb-4"><i class="bi bi-cart-check me-2"></i>বিক্রয়</h4>

  <ul class="nav nav-pills mb-4" id="salesTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#newSaleTab" type="button">
        <i class="bi bi-plus-circle me-1"></i>নতুন বিক্রয়
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="pill" data-bs-target="#historyTab" type="button"
              id="historyTabBtn">
        <i class="bi bi-list-ul me-1"></i>বিক্রয় ইতিহাস
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ===== NEW SALE TAB ===== -->
    <div class="tab-pane fade show active" id="newSaleTab">
      <form id="saleForm" onsubmit="submitSale(event)">

        <!-- Header row -->
        <div class="row g-3 mb-3">
          <div class="col-md-5">
            <label class="form-label fw-semibold">কাস্টমার</label>
            <select class="form-select" id="saleCustomerId" name="customer_id">
              <option value="">Walk-in Customer (নাম নেই)</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>">
                <?= e($c['name']) ?><?= $c['phone'] ? ' — ' . e($c['phone']) : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">তারিখ <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="saleDate" name="sale_date"
                   value="<?= today() ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">পেমেন্ট পদ্ধতি</label>
            <select class="form-select" id="paymentMethod" name="payment_method">
              <option value="cash">নগদ (Cash)</option>
              <option value="credit">বাকি (Credit)</option>
              <option value="mobile_banking">মোবাইল ব্যাংকিং</option>
              <option value="cheque">চেক</option>
            </select>
          </div>
        </div>

        <!-- Items card -->
        <div class="card shadow-sm mb-3">
          <div class="card-header d-flex justify-content-between align-items-center py-2">
            <span class="fw-semibold"><i class="bi bi-box-seam me-1"></i>পণ্য তালিকা</span>
            <button type="button" class="btn btn-sm btn-success" onclick="addItemRow()">
              <i class="bi bi-plus-circle me-1"></i>পণ্য যোগ করুন
            </button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="min-width:220px">পণ্য</th>
                  <th style="width:120px">পরিমাণ</th>
                  <th style="width:140px">একক মূল্য (৳)</th>
                  <th style="width:130px" class="text-end">মোট (৳)</th>
                  <th style="width:46px"></th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                <!-- dynamic rows -->
              </tbody>
            </table>
          </div>
          <div id="noItemsAlert" class="text-center text-muted py-3 d-none">
            উপরের বাটনে ক্লিক করে পণ্য যোগ করুন
          </div>
        </div>

        <!-- Totals + submit -->
        <div class="row justify-content-end">
          <div class="col-lg-5 col-md-7">
            <table class="table table-sm table-borderless">
              <tr>
                <td class="text-muted">সাবটোটাল</td>
                <td class="text-end fw-semibold" id="subtotalDisplay">০.০০ ৳</td>
              </tr>
              <tr>
                <td class="text-muted align-middle">ছাড় (৳)</td>
                <td class="text-end">
                  <input type="number" class="form-control form-control-sm text-end ms-auto"
                         style="width:130px" id="discount" name="discount"
                         value="0" min="0" step="0.01" oninput="calcGrandTotal()">
                </td>
              </tr>
              <tr class="table-dark">
                <td class="fw-bold">মোট</td>
                <td class="text-end fw-bold fs-6" id="totalDisplay">০.০০ ৳</td>
              </tr>
              <tr>
                <td class="text-muted align-middle">নগদ প্রদান (৳)</td>
                <td class="text-end">
                  <input type="number" class="form-control form-control-sm text-end ms-auto"
                         style="width:130px" id="paidAmount" name="paid_amount"
                         value="0" min="0" step="0.01" oninput="calcGrandTotal()">
                </td>
              </tr>
              <tr class="table-warning">
                <td class="fw-semibold">বাকি</td>
                <td class="text-end fw-bold text-danger" id="dueDisplay">০.০০ ৳</td>
              </tr>
            </table>
            <div class="mb-3">
              <label class="form-label text-muted small">নোট</label>
              <textarea class="form-control form-control-sm" id="saleNote" name="note"
                        rows="2" maxlength="500"></textarea>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg" id="submitSaleBtn">
                <i class="bi bi-check-circle me-2"></i>বিক্রয় সম্পন্ন করুন
              </button>
            </div>
          </div>
        </div>

      </form>
    </div><!-- /newSaleTab -->

    <!-- ===== HISTORY TAB ===== -->
    <div class="tab-pane fade" id="historyTab">

      <!-- Filters -->
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">তারিখ থেকে</label>
              <input type="date" class="form-control form-control-sm" id="filterDateFrom">
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">তারিখ পর্যন্ত</label>
              <input type="date" class="form-control form-control-sm" id="filterDateTo">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-muted mb-1">কাস্টমার</label>
              <select class="form-select form-select-sm" id="filterCustomer">
                <option value="">সকল কাস্টমার</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small text-muted mb-1">স্ট্যাটাস</label>
              <select class="form-select form-select-sm" id="filterStatus">
                <option value="">সকল</option>
                <option value="completed">সম্পন্ন</option>
                <option value="cancelled">বাতিল</option>
              </select>
            </div>
            <div class="col-md-1">
              <button class="btn btn-primary btn-sm w-100" onclick="loadSalesHistory()">
                <i class="bi bi-search"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sales table -->
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-dark">
              <tr>
                <th>ইনভয়েস</th>
                <th>তারিখ</th>
                <th>কাস্টমার</th>
                <th class="text-center">পণ্য</th>
                <th class="text-end">মোট</th>
                <th class="text-end">পরিশোধ</th>
                <th class="text-end">বাকি</th>
                <th class="text-center">স্ট্যাটাস</th>
                <th class="text-center">একশন</th>
              </tr>
            </thead>
            <tbody id="salesBody">
              <tr>
                <td colspan="9" class="text-center py-5 text-muted">
                  ইতিহাস ট্যাবে ক্লিক করলে লোড হবে
                </td>
              </tr>
            </tbody>
            <tfoot id="salesFooter"></tfoot>
          </table>
        </div>
      </div>
    </div><!-- /historyTab -->

  </div><!-- /tab-content -->
</div>
</div>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>ইনভয়েস</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4" id="invoiceContent">
        <div class="text-center py-4">
          <div class="spinner-border text-primary"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
        <button type="button" class="btn btn-primary" onclick="printInvoice()">
          <i class="bi bi-printer me-1"></i>প্রিন্ট করুন
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const IS_ADMIN = <?= User::isAdmin() ? 'true' : 'false' ?>;
const PRODUCTS = <?= json_encode(array_values($products)) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/sales.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
