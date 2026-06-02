<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Branch.php';
requireLogin();
requireManagerOrAdmin();

$pageTitle = 'ব্রাঞ্চ';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-shop me-2 text-danger"></i>ব্রাঞ্চ ব্যবস্থাপনা</h4>
    <button class="btn btn-primary" onclick="openAddModal()">
      <i class="bi bi-plus-circle me-1"></i>নতুন ব্রাঞ্চ
    </button>
  </div>

  <!-- Info note -->
  <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-info-circle-fill me-2"></i>
    <strong>ব্রাঞ্চ সিস্টেম:</strong>
    প্রতিটি ব্রাঞ্চের আলাদা স্টক থাকবে। অর্ডার তৈরির সময় কোন ব্রাঞ্চ থেকে পণ্য যাবে তা নির্বাচন করা যাবে।
    স্টাফ ইউজারদের নির্দিষ্ট ব্রাঞ্চে assign করা যাবে।
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>ব্রাঞ্চের নাম</th>
            <th>ফোন</th>
            <th>ঠিকানা</th>
            <th class="text-center">স্টাফ</th>
            <th class="text-center">স্টক এন্ট্রি</th>
            <th class="text-center">বিক্রয়</th>
            <th class="text-center">একশন</th>
          </tr>
        </thead>
        <tbody id="branchesBody">
          <tr>
            <td colspan="8" class="text-center py-5 text-muted">
              <div class="spinner-border spinner-border-sm me-2"></div>লোড হচ্ছে...
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>

<!-- Add / Edit Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="branchModalTitle">নতুন ব্রাঞ্চ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="branchForm" onsubmit="submitBranch(event)">
        <div class="modal-body">
          <input type="hidden" id="branchId" name="id" value="">

          <div class="mb-3">
            <label class="form-label fw-semibold">
              ব্রাঞ্চের নাম <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" id="branchName" name="name"
                   required maxlength="150" autocomplete="off"
                   placeholder="যেমন: মিরপুর শাখা, উত্তরা শাখা">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">ফোন নম্বর</label>
            <input type="text" class="form-control" id="branchPhone" name="phone"
                   maxlength="20" placeholder="01XXXXXXXXX">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">ঠিকানা</label>
            <textarea class="form-control" id="branchAddress" name="address"
                      rows="2" maxlength="500"
                      placeholder="ব্রাঞ্চের সম্পূর্ণ ঠিকানা"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
          <button type="submit" class="btn btn-danger" id="branchSaveBtn">
            <i class="bi bi-check-circle me-1"></i>সংরক্ষণ করুন
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/branches.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
