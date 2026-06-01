<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
requireLogin();
requireManagerOrAdmin();

$pageTitle = 'পণ্য ফেরত';
$canWrite  = User::isAdminOrManager();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header mb-4">
    <h5><i class="bi bi-arrow-return-left me-2 text-danger"></i>পণ্য ফেরত</h5>
    <?php if ($canWrite): ?>
    <button class="btn btn-primary btn-sm" id="btnNewReturn">
      <i class="bi bi-plus-lg me-1"></i>নতুন ফেরত
    </button>
    <?php endif; ?>
  </div>

  <!-- Search -->
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <input type="text" class="form-control form-control-sm" id="searchInput"
             placeholder="ইনভয়েস নম্বর দিয়ে খুঁজুন...">
    </div>
  </div>

  <div id="returnList">
    <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
  </div>

</div>
</div>

<!-- New Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="bi bi-arrow-return-left me-2"></i>নতুন পণ্য ফেরত</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- Step 1: Find sale -->
        <div id="step1">
          <label class="form-label fw-semibold">ইনভয়েস নম্বর দিয়ে বিক্রয় খুঁজুন</label>
          <div class="input-group mb-3">
            <input type="text" class="form-control" id="invoiceSearch" placeholder="যেমন: INV-20260601-0001">
            <button class="btn btn-primary" onclick="searchSale()">
              <i class="bi bi-search"></i> খুঁজুন
            </button>
          </div>
          <div id="saleSearchResult"></div>
        </div>

        <!-- Step 2: Return form (shown after sale found) -->
        <div id="step2" class="d-none">
          <div class="alert alert-info py-2 mb-3" id="saleInfo"></div>
          <form id="returnForm" onsubmit="submitReturn(event)">
            <input type="hidden" id="rSaleId">
            <div class="table-responsive mb-3">
              <table class="table table-bordered table-sm">
                <thead class="table-dark">
                  <tr>
                    <th><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
                    <th>পণ্য</th>
                    <th>বিক্রিত পরিমাণ</th>
                    <th style="width:120px">ফেরত পরিমাণ</th>
                    <th>ইউনিট মূল্য</th>
                    <th>ফেরত মূল্য</th>
                  </tr>
                </thead>
                <tbody id="returnItemsBody"></tbody>
              </table>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">কারণ <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="rReason" maxlength="500"
                       placeholder="ফেরতের কারণ লিখুন" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">নোট</label>
                <input type="text" class="form-control" id="rNote" maxlength="500">
              </div>
            </div>
          </form>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="submit" form="returnForm" class="btn btn-warning d-none" id="returnSaveBtn">
          <i class="bi bi-check-circle me-1"></i>ফেরত নিশ্চিত করুন
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL  = '<?= BASE_URL ?>';
const CAN_WRITE = <?= $canWrite ? 'true' : 'false' ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/returns.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
