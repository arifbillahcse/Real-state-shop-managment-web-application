<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Customer.php';
requireLogin();

$pageTitle = 'কাস্টমার';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>কাস্টমার ব্যবস্থাপনা</h4>
    <button class="btn btn-primary" onclick="openAddModal()">
      <i class="bi bi-person-plus me-1"></i>নতুন কাস্টমার
    </button>
  </div>

  <div class="mb-3">
    <input type="text" id="searchInput" class="form-control"
           placeholder="নাম বা ফোন দিয়ে খুঁজুন...">
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover mb-0" id="customersTable">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>নাম</th>
            <th>ফোন</th>
            <th>ঠিকানা</th>
            <th class="text-end">মোট ক্রয়</th>
            <th class="text-end">বাকি</th>
            <th class="text-center">একশন</th>
          </tr>
        </thead>
        <tbody id="customersBody">
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
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
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalTitle">নতুন কাস্টমার</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="customerForm" onsubmit="submitCustomer(event)">
        <div class="modal-body">
          <input type="hidden" id="customerId" name="id" value="">
          <div class="mb-3">
            <label class="form-label fw-semibold">
              নাম <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" id="customerName"
                   name="name" required maxlength="150" autocomplete="off">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">ফোন নম্বর</label>
            <input type="text" class="form-control" id="customerPhone"
                   name="phone" maxlength="20">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">ঠিকানা</label>
            <textarea class="form-control" id="customerAddress"
                      name="address" rows="2" maxlength="500"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
          <button type="submit" class="btn btn-primary" id="saveBtn">সংরক্ষণ করুন</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const IS_ADMIN = <?= User::isAdminOrManager() ? 'true' : 'false' ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/customers.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
