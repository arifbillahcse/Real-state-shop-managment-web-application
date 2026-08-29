<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Customer.php';
requireLogin();

$pageTitle = 'কাস্টমার';
$staffList = Database::fetchAll('SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>কাস্টমার ব্যবস্থাপনা</h4>
    <button class="btn btn-primary" onclick="openAddModal()">
      <i class="bi bi-person-plus me-1"></i>নতুন কাস্টমার
    </button>
  </div>

  <div class="mb-3">
    <input type="text" id="searchInput" class="form-control"
           placeholder="নাম, ফোন, একাউন্ট বা বই নং দিয়ে খুঁজুন...">
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="customersTable">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>নাম</th>
            <th>একাউন্ট নং</th>
            <th>ধরন</th>
            <th>ফোন</th>
            <th>ঠিকানা</th>
            <th class="text-end">মোট ক্রয়</th>
            <th class="text-end">বাকি</th>
            <th class="text-center">একশন</th>
          </tr>
        </thead>
        <tbody id="customersBody">
          <tr>
            <td colspan="9" class="text-center py-5 text-muted">
              <div class="spinner-border spinner-border-sm me-2"></div>লোড হচ্ছে...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" id="custPaginationBar" style="display:none!important">
      <small class="text-muted" id="custPageInfo"></small>
      <nav><ul class="pagination pagination-sm mb-0" id="custPagination"></ul></nav>
    </div>
  </div>

</div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalTitle">নতুন কাস্টমার</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="customerForm" onsubmit="submitCustomer(event)">
        <div class="modal-body">
          <input type="hidden" id="customerId" name="id" value="">
          <input type="hidden" id="customerPhoto" name="photo" value="">

          <!-- Account type (add mode only) -->
          <div class="mb-3" id="accountTypeWrap">
            <label class="form-label fw-semibold d-block">একাউন্টের ধরন</label>
            <div class="btn-group btn-group-sm" role="group">
              <input type="radio" class="btn-check" name="account_type" id="typeFull" value="full" checked>
              <label class="btn btn-outline-primary" for="typeFull">ফুল একাউন্ট</label>
              <input type="radio" class="btn-check" name="account_type" id="typeShort" value="short">
              <label class="btn btn-outline-secondary" for="typeShort">শর্ট একাউন্ট</label>
            </div>
            <small class="text-muted d-block mt-1">
              শর্ট একাউন্ট = শুধু নাম, ঠিকানা, মোবাইল ও বই নং। পরে ফুল একাউন্টে রূপান্তর করা যাবে।
            </small>
          </div>

          <div class="row g-2">
            <div class="col-md-6 mb-2">
              <label class="form-label fw-semibold">নাম <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="customerName"
                     name="name" required maxlength="150" autocomplete="off">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label fw-semibold">মোবাইল নাম্বার <span class="text-danger">*</span></label>
              <div class="input-group">
                <input type="text" class="form-control" id="customerPhone" name="phone" maxlength="20" required>
                <button type="button" class="btn btn-outline-secondary" id="btnAddPhone" title="আরো নাম্বার">
                  <i class="bi bi-plus-lg"></i>
                </button>
              </div>
              <div id="extraPhones" class="d-flex flex-wrap gap-1 mt-1"></div>
            </div>
          </div>

          <div class="row g-2 cust-full-only">
            <div class="col-md-6 mb-2">
              <label class="form-label fw-semibold">WhatsApp নাম্বার</label>
              <input type="text" class="form-control" id="customerWhatsapp" name="whatsapp" maxlength="20">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label fw-semibold">Imo নাম্বার</label>
              <input type="text" class="form-control" id="customerImo" name="imo" maxlength="20">
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold">ঠিকানা <span class="text-danger">*</span></label>
            <textarea class="form-control" id="customerAddress"
                      name="address" rows="2" maxlength="500"></textarea>
          </div>

          <div class="row g-2">
            <div class="col-md-4 mb-2">
              <label class="form-label fw-semibold">বই নাম্বার</label>
              <input type="text" class="form-control" id="customerBookNo" name="book_no" maxlength="20"
                     placeholder="যেমন: 12">
              <small class="text-muted">একাউন্ট নং অটো হবে (যেমন 12/45)</small>
            </div>
            <div class="col-md-4 mb-2 cust-full-only">
              <label class="form-label fw-semibold">বাকির সীমা (৳)</label>
              <input type="number" class="form-control" id="customerDueLimit" name="due_limit"
                     min="0" step="0.01" value="0">
              <small class="text-muted">০ = সীমা নেই</small>
            </div>
            <div class="col-md-4 mb-2 cust-full-only">
              <label class="form-label fw-semibold">কাস্টমারের ছবি</label>
              <div class="d-flex gap-2 align-items-center">
                <input type="file" class="form-control" id="custPhotoFile" accept="image/jpeg,image/png,image/webp">
                <span id="custPhotoPreviewWrap" class="d-none">
                  <img src="" id="custPhotoPreview" class="rounded-circle border"
                       style="width:40px;height:40px;object-fit:cover">
                </span>
              </div>
            </div>
          </div>

          <!-- Reference person — add mode only. Editing an existing customer
               uses the dedicated references modal, which handles several. -->
          <div class="card border mt-3" id="addRefWrap">
            <div class="card-body py-3">
              <h6 class="fw-semibold mb-1">
                <i class="bi bi-person-check me-1 text-danger"></i>রেফারেন্স তথ্যঃ
              </h6>
              <small class="text-muted d-block mb-2">
                কাস্টমারকে খুঁজে না পেলে যাকে ফোন করা যাবে। ঐচ্ছিক — পরে যোগ করা যাবে।
              </small>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">রেফারেন্সের নাম</label>
                  <input type="text" class="form-control form-control-sm" id="newRefName" maxlength="150">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">রেফারেন্সের ফোন</label>
                  <input type="text" class="form-control form-control-sm" id="newRefPhone" maxlength="20">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">রেফারেন্সের ঠিকানা</label>
                  <input type="text" class="form-control form-control-sm" id="newRefAddress" maxlength="500">
                </div>
                <div class="col-md-6">
                  <label class="form-label small fw-semibold mb-1">রেফারেন্সের ছবি</label>
                  <div class="d-flex gap-2 align-items-center">
                    <input type="file" class="form-control form-control-sm" id="newRefPhotoFile"
                           accept="image/jpeg,image/png,image/webp">
                    <span id="newRefPhotoWrap" class="d-none">
                      <img src="" id="newRefPhotoPreview" class="rounded border"
                           style="width:36px;height:36px;object-fit:cover">
                    </span>
                  </div>
                  <input type="hidden" id="newRefPhoto">
                </div>
              </div>
            </div>
          </div>

          <div id="acctNoInfo" class="alert alert-info py-2 d-none mt-3 mb-0"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
          <button type="submit" class="btn btn-primary" id="saveBtn">সংরক্ষণ করুন</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- References Modal -->
<div class="modal fade" id="refModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-person-check me-2 text-danger"></i>রেফারেন্স — <span id="refCustName"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="refCustomerId">
        <div id="refList" class="mb-3"></div>

        <div class="card bg-light border">
          <div class="card-body py-3">
            <h6 class="fw-semibold mb-2"><i class="bi bi-plus-circle me-1"></i>নতুন রেফারেন্স</h6>
            <div class="row g-2">
              <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" id="refName" placeholder="নাম *" maxlength="150">
              </div>
              <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" id="refPhone" placeholder="ফোন নাম্বার" maxlength="20">
              </div>
              <div class="col-md-6">
                <input type="text" class="form-control form-control-sm" id="refAddress" placeholder="ঠিকানা" maxlength="500">
              </div>
              <div class="col-md-6">
                <select class="form-select form-select-sm" id="refUserId" data-no-search="1">
                  <option value="">স্টাফ/ম্যানেজার রেফারেন্স? (সেলস ট্র্যাকিং)</option>
                  <?php foreach ($staffList as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['role']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-8">
                <input type="file" class="form-control form-control-sm" id="refPhotoFile"
                       accept="image/jpeg,image/png,image/webp">
              </div>
              <div class="col-md-4 d-grid">
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveRef">
                  <i class="bi bi-check-lg me-1"></i>যোগ করুন
                </button>
              </div>
            </div>
            <div class="alert alert-danger py-2 mt-2 d-none mb-0" id="refError"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Upgrade to Full Modal -->
<div class="modal fade" id="upgradeModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h6 class="modal-title"><i class="bi bi-arrow-up-circle me-1"></i>ফুল একাউন্টে রূপান্তর</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="upgradeCustomerId">
        <p class="small mb-2"><strong id="upgradeCustName"></strong> কে ফুল একাউন্টে রূপান্তর করা হবে।</p>
        <label class="form-label small fw-semibold">বই নাম্বার</label>
        <input type="text" class="form-control form-control-sm" id="upgradeBookNo" placeholder="যেমন: 12">
        <small class="text-muted">একাউন্ট নং না থাকলে অটো তৈরি হবে।</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-success btn-sm" id="btnConfirmUpgrade">রূপান্তর করুন</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const IS_ADMIN = <?= canWriteBranchData() ? 'true' : 'false' ?>;
</script>
<script src="<?= asset('assets/js/customers.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
