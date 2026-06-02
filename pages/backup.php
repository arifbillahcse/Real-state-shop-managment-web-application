<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
requireAdmin();

$pageTitle = 'ব্যাকআপ ও রিস্টোর';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-database-fill-down me-2"></i>ব্যাকআপ ও রিস্টোর</h4>
  </div>

  <!-- Alert area -->
  <div id="importAlert" class="d-none mb-4"></div>

  <div class="row g-4">

    <!-- Export -->
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
              <i class="bi bi-download fs-4"></i>
            </div>
            <div>
              <h5 class="mb-0 fw-bold">ডেটা এক্সপোর্ট</h5>
              <small class="text-muted">SQL ফাইল হিসেবে ব্যাকআপ নিন</small>
            </div>
          </div>

          <p class="text-muted mb-4">
            সমস্ত ডেটা (কাস্টমার, বিক্রয়, স্টক, পেমেন্ট ইত্যাদি) একটি <code>.sql</code> ফাইলে ডাউনলোড করুন।
            এই ফাইল দিয়ে যেকোনো সময় ডেটাবেস পূর্বাবস্থায় ফিরিয়ে আনা যাবে।
          </p>

          <ul class="list-unstyled mb-4">
            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>সব টেবিলের সম্পূর্ণ ডেটা</li>
            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>টেবিল স্ট্রাকচার সহ</li>
            <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>phpMyAdmin এ সরাসরি ইম্পোর্টযোগ্য</li>
          </ul>

          <a href="<?= BASE_URL ?>/api/export_db.php" class="btn btn-primary w-100" id="exportBtn">
            <i class="bi bi-download me-2"></i>SQL ব্যাকআপ ডাউনলোড করুন
          </a>
          <small class="text-muted d-block text-center mt-2">ফাইলের নাম: backup_<?= DB_NAME ?>_YYYYMMDD_HHMMSS.sql</small>
        </div>
      </div>
    </div>

    <!-- Import -->
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
              <i class="bi bi-upload fs-4"></i>
            </div>
            <div>
              <h5 class="mb-0 fw-bold">ডেটা ইম্পোর্ট</h5>
              <small class="text-muted">SQL ফাইল থেকে রিস্টোর করুন</small>
            </div>
          </div>

          <div class="alert alert-warning d-flex gap-2 mb-3 py-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <small><strong>সতর্কতা:</strong> ইম্পোর্ট করলে বর্তমান ডেটা মুছে যাবে। শুধুমাত্র বিশ্বস্ত ব্যাকআপ ফাইল ব্যবহার করুন।</small>
          </div>

          <p class="text-muted mb-4">
            পূর্বে ডাউনলোড করা <code>.sql</code> ব্যাকআপ ফাইল আপলোড করে ডেটাবেস পুনরুদ্ধার করুন।
            সর্বোচ্চ ফাইল সাইজ: <strong>৫০ MB</strong>।
          </p>

          <form id="importForm">
            <div class="mb-3">
              <label class="form-label fw-semibold">SQL ফাইল নির্বাচন করুন</label>
              <input type="file" class="form-control" id="sqlFile" accept=".sql" required>
            </div>
            <button type="submit" class="btn btn-danger w-100" id="importBtn">
              <i class="bi bi-upload me-2"></i>ইম্পোর্ট ও রিস্টোর করুন
            </button>
          </form>

          <!-- Confirm Import Modal -->
          <div class="modal fade" id="confirmImportModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                  <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>বিপজ্জনক কাজ — নিশ্চিত করুন</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="alert alert-danger mb-3">
                    <strong>এই কাজটি অপরিবর্তনীয়!</strong> ইম্পোর্ট করলে:
                    <ul class="mb-0 mt-2">
                      <li>বর্তমান <strong>সমস্ত ডেটা মুছে যাবে</strong></li>
                      <li>সব বিক্রয়, পেমেন্ট, স্টক রেকর্ড হারিয়ে যাবে</li>
                      <li>এই কাজ পূর্বাবস্থায় ফেরানো <strong>সম্ভব নয়</strong></li>
                    </ul>
                  </div>
                  <p class="mb-2 fw-semibold">নিশ্চিত করতে নিচের বাক্সে <code class="text-danger">আমি নিশ্চিত</code> টাইপ করুন:</p>
                  <input type="text" id="confirmPhrase" class="form-control" placeholder='এখানে টাইপ করুন...' autocomplete="off">
                  <div class="form-text text-muted mt-1">হুবহু বাংলায় টাইপ করতে হবে</div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল করুন</button>
                  <button type="button" class="btn btn-danger" id="confirmImportBtn" disabled>
                    <i class="bi bi-upload me-2"></i>হ্যাঁ, ইম্পোর্ট করুন
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Info cards -->
  <div class="row g-4 mt-2">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2 text-info"></i>ব্যাকআপ সম্পর্কিত তথ্য</h6>
          <div class="row g-3">
            <div class="col-md-4">
              <div class="p-3 bg-light rounded">
                <h6 class="text-muted small mb-1">নিয়মিত ব্যাকআপ</h6>
                <p class="mb-0 small">প্রতিদিন বা সাপ্তাহিক ব্যাকআপ নেওয়ার অভ্যাস রাখুন। বিশেষত বড় বিক্রয়ের পর ব্যাকআপ নিন।</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-3 bg-light rounded">
                <h6 class="text-muted small mb-1">ফাইল সংরক্ষণ</h6>
                <p class="mb-0 small">ব্যাকআপ ফাইল গুগল ড্রাইভ বা পেনড্রাইভে রাখুন। একাধিক জায়গায় রাখলে নিরাপদ।</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="p-3 bg-light rounded">
                <h6 class="text-muted small mb-1">রিস্টোর প্রক্রিয়া</h6>
                <p class="mb-0 small">রিস্টোর করার আগে বর্তমান ডেটার ব্যাকআপ নিয়ে নিন। ইম্পোর্ট সম্পন্ন হলে পেজ রিলোড করুন।</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const CONFIRM_PHRASE = 'আমি নিশ্চিত';

let confirmModal = null;

// Step 1: form submit → open confirmation modal
document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const file = document.getElementById('sqlFile').files[0];
    if (!file) return;

    // Reset modal state
    document.getElementById('confirmPhrase').value = '';
    document.getElementById('confirmImportBtn').disabled = true;

    if (!confirmModal) confirmModal = new bootstrap.Modal(document.getElementById('confirmImportModal'));
    confirmModal.show();

    // Focus phrase input after modal opens
    document.getElementById('confirmImportModal').addEventListener('shown.bs.modal', () => {
        document.getElementById('confirmPhrase').focus();
    }, { once: true });
});

// Enable confirm button only when phrase matches exactly
document.getElementById('confirmPhrase').addEventListener('input', function() {
    document.getElementById('confirmImportBtn').disabled = (this.value !== CONFIRM_PHRASE);
});

// Step 2: confirmed — run import
document.getElementById('confirmImportBtn').addEventListener('click', function() {
    const file = document.getElementById('sqlFile').files[0];
    if (!file) return;

    confirmModal.hide();

    const importBtn = document.getElementById('importBtn');
    importBtn.disabled = true;
    importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>ইম্পোর্ট হচ্ছে...';

    const formData = new FormData();
    formData.append('sql_file', file);

    fetch(BASE_URL + '/api/import_db.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            const alertEl = document.getElementById('importAlert');
            alertEl.className = 'mb-4 alert alert-' + (res.success ? 'success' : 'danger');
            alertEl.innerHTML = '<i class="bi bi-' + (res.success ? 'check-circle' : 'x-circle') + ' me-2"></i>' + res.message;
            alertEl.classList.remove('d-none');
            alertEl.scrollIntoView({ behavior: 'smooth' });

            importBtn.disabled = false;
            importBtn.innerHTML = '<i class="bi bi-upload me-2"></i>ইম্পোর্ট ও রিস্টোর করুন';

            if (res.success) document.getElementById('sqlFile').value = '';
        })
        .catch(() => {
            const alertEl = document.getElementById('importAlert');
            alertEl.className = 'mb-4 alert alert-danger';
            alertEl.innerHTML = '<i class="bi bi-x-circle me-2"></i>সার্ভারের সাথে সংযোগ বিচ্ছিন্ন হয়েছে।';
            alertEl.classList.remove('d-none');

            importBtn.disabled = false;
            importBtn.innerHTML = '<i class="bi bi-upload me-2"></i>ইম্পোর্ট ও রিস্টোর করুন';
        });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
