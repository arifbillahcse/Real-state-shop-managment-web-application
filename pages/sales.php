<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Customer.php';
require_once __DIR__ . '/../classes/Branch.php';
requireLogin();

$pageTitle    = 'বিক্রয়';
$_isStaff     = isStaff();
$staffBranch  = getSessionBranchId();

$customers = Customer::getCustomers();
$branches  = Branch::getBranches();
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

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-cart-check me-2"></i>বিক্রয়</h4>
    <?php if (!$_isStaff): ?>
    <button class="btn btn-primary" id="btnToggleSaleView" type="button" onclick="toggleSaleView()">
      <i class="bi bi-plus-circle me-1"></i>নতুন বিক্রয়
    </button>
    <?php endif; ?>
  </div>

  <div class="tab-content">

    <!-- ===== NEW SALE TAB ===== -->
    <?php if (!$_isStaff): ?>
    <div class="tab-pane fade" id="newSaleTab">
      <form id="saleForm" onsubmit="submitSale(event)">

        <!-- Header row -->
        <div class="row g-3 mb-3">
          <div class="col-md-<?= !empty($branches) ? '4' : '5' ?>">
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
          <?php if (!empty($branches)): ?>
          <div class="col-md-3">
            <label class="form-label fw-semibold">ব্রাঞ্চ <span class="text-danger">*</span></label>
            <select class="form-select" id="saleBranchId" name="branch_id" required>
              <option value="">— ব্রাঞ্চ নির্বাচন করুন —</option>
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-<?= !empty($branches) ? '2' : '3' ?>">
            <label class="form-label fw-semibold">তারিখ <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="saleDate" name="sale_date"
                   value="<?= today() ?>" required>
          </div>
          <div class="col-md-3">
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
    <?php endif; ?>

    <!-- ===== HISTORY TAB ===== -->
    <div class="tab-pane fade show active" id="historyTab">

      <!-- Filters -->
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
              <label class="form-label small text-muted mb-1">তারিখ থেকে</label>
              <input type="date" class="form-control form-control-sm" id="filterDateFrom">
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label small text-muted mb-1">তারিখ পর্যন্ত</label>
              <input type="date" class="form-control form-control-sm" id="filterDateTo">
            </div>
            <div class="col-12 col-md-2">
              <label class="form-label small text-muted mb-1">কাস্টমার</label>
              <select class="form-select form-select-sm" id="filterCustomer">
                <option value="">সকল কাস্টমার</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (!empty($branches)): ?>
            <div class="col-12 col-md-2">
              <label class="form-label small text-muted mb-1">ব্রাঞ্চ</label>
              <select class="form-select form-select-sm" id="filterBranch">
                <option value="">সকল ব্রাঞ্চ</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="col-8 col-md-2">
              <label class="form-label small text-muted mb-1">স্ট্যাটাস</label>
              <select class="form-select form-select-sm" id="filterStatus">
                <option value="">সকল</option>
                <option value="completed">সম্পন্ন</option>
                <option value="cancelled">বাতিল</option>
              </select>
            </div>
            <div class="col-4 col-md-1">
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
                <?php if (!empty($branches)): ?>
                <th>ব্রাঞ্চ</th>
                <?php endif; ?>
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
          </table>
        </div>
        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" id="salesPaginationBar" style="display:none!important">
          <small class="text-muted" id="salesPageInfo"></small>
          <nav><ul class="pagination pagination-sm mb-0" id="salesPagination"></ul></nav>
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
        <?php if (User::isAdminOrManager()): ?>
        <button type="button" class="btn btn-warning" id="btnEditInvoice" onclick="openEditSale()">
          <i class="bi bi-pencil-square me-1"></i>সম্পাদনা
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="printInvoice()">
          <i class="bi bi-printer me-1"></i>প্রিন্ট করুন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Sale Modal -->
<div class="modal fade" id="editSaleModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>বিক্রয় সম্পাদনা</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editSaleForm" onsubmit="submitEditSale(event)">
          <input type="hidden" id="esSaleId">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">কাস্টমার</label>
              <select class="form-select" id="esCustomer">
                <option value="0">Walk-in / অজ্ঞাত</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">বিক্রয়ের তারিখ</label>
              <input type="date" class="form-control" id="esSaleDate">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">পেমেন্ট পদ্ধতি</label>
              <select class="form-select" id="esPayMethod">
                <option value="cash">নগদ</option>
                <option value="credit">বাকি</option>
                <option value="mobile_banking">মোবাইল ব্যাংকিং</option>
                <option value="cheque">চেক</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label fw-semibold">ছাড় (৳)</label>
              <input type="number" class="form-control" id="esDiscount" min="0" step="0.01" oninput="calcEditSaleTotal()">
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label fw-semibold">পরিশোধ (৳)</label>
              <input type="number" class="form-control" id="esPaid" min="0" step="0.01">
            </div>
            <div class="col-md-9">
              <label class="form-label fw-semibold">নোট</label>
              <input type="text" class="form-control" id="esNote" maxlength="500">
            </div>
          </div>

          <!-- Items -->
          <div class="table-responsive mb-2">
            <table class="table table-bordered table-sm">
              <thead class="table-dark">
                <tr>
                  <th style="min-width:200px">পণ্য</th>
                  <th style="width:100px">পরিমাণ</th>
                  <th style="width:130px">ইউনিট মূল্য (৳)</th>
                  <th style="width:130px">মোট (৳)</th>
                  <th style="width:50px"></th>
                </tr>
              </thead>
              <tbody id="editSaleItemsBody"></tbody>
              <tfoot>
                <tr>
                  <td colspan="5">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addEditSaleRow()">
                      <i class="bi bi-plus-lg me-1"></i>পণ্য যোগ করুন
                    </button>
                  </td>
                </tr>
                <tr class="table-light fw-bold">
                  <td colspan="3" class="text-end">সাবটোটাল:</td>
                  <td id="esSubtotal">০.০০ ৳</td><td></td>
                </tr>
                <tr class="table-light fw-bold">
                  <td colspan="3" class="text-end">মোট:</td>
                  <td id="esTotal" class="text-success">০.০০ ৳</td><td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="submit" form="editSaleForm" class="btn btn-warning" id="editSaleSaveBtn">
          <i class="bi bi-check-circle me-1"></i>আপডেট করুন
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL      = '<?= BASE_URL ?>';
const IS_ADMIN      = <?= User::isAdminOrManager() ? 'true' : 'false' ?>;
const IS_STAFF      = <?= $_isStaff ? 'true' : 'false' ?>;
const STAFF_BRANCH  = <?= $staffBranch ?? 'null' ?>;
const PRODUCTS      = <?= json_encode(array_values($products)) ?>;
const BRANCHES      = <?= json_encode(array_values($branches)) ?>;
const HAS_BRANCHES  = <?= !empty($branches) ? 'true' : 'false' ?>;
const CUSTOMERS     = <?= json_encode(array_values($customers)) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/sales.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
