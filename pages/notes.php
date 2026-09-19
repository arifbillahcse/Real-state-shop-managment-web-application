<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
requireLogin();
requireManagerOrAdmin();

$pageTitle = 'নোট';
$canWrite  = User::isAdminOrManager();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header mb-4">
    <h5><i class="bi bi-sticky me-2 text-danger"></i>নোট</h5>
  </div>

  <div class="row g-4">

    <!-- ── Add note form ─────────────────────────────────────────────── -->
    <?php if ($canWrite): ?>
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white fw-semibold">
          <i class="bi bi-plus-circle me-1"></i>নতুন নোট
        </div>
        <div class="card-body">
          <form id="noteForm" onsubmit="submitNote(event)">

            <div class="mb-3">
              <label class="form-label fw-semibold">কাস্টমারের নাম <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="nCustomerName"
                     maxlength="150" placeholder="যেকোনো নাম লিখুন" required>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">তারিখ</label>
              <input type="date" class="form-control" id="nDate" value="<?= date('Y-m-d') ?>">
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">নোট <span class="text-danger">*</span></label>
              <textarea class="form-control" id="nText" rows="5"
                        maxlength="2000" placeholder="এখানে নোট লিখুন..." required></textarea>
              <div class="text-end text-muted small mt-1"><span id="charCount">0</span> / 2000</div>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="noteSaveBtn">
              <i class="bi bi-check-circle me-1"></i>সংরক্ষণ করুন
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Notes list ────────────────────────────────────────────────── -->
    <div class="col-lg-<?= $canWrite ? '8' : '12' ?>">

      <!-- Filter + Search -->
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-center">
            <div class="col-12 col-sm-auto">
              <div class="d-flex flex-wrap gap-1" role="group" id="statusFilter">
                <button type="button" class="btn btn-sm btn-danger active" data-status="">সব</button>
                <button type="button" class="btn btn-sm btn-outline-warning" data-status="pending">
                  <i class="bi bi-hourglass-split me-1"></i>পেন্ডিং
                </button>
                <button type="button" class="btn btn-sm btn-outline-success" data-status="done">
                  <i class="bi bi-check-circle me-1"></i>সফল
                </button>
              </div>
            </div>
            <div class="col">
              <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0">
                  <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0" id="searchInput"
                       placeholder="কাস্টমারের নাম দিয়ে খুঁজুন...">
                <button class="btn btn-outline-secondary" onclick="clearSearch()">
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div id="notesList">
        <div class="text-center py-5">
          <div class="spinner-border text-primary"></div>
        </div>
      </div>

    </div>
  </div>
</div>
</div>

<script>
const BASE_URL  = '<?= BASE_URL ?>';
const CAN_WRITE = <?= $canWrite ? 'true' : 'false' ?>;
</script>

<!-- Edit Modal -->
<div class="modal fade" id="editNoteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>নোট এডিট করুন</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editNoteForm" onsubmit="submitEdit(event)">
          <input type="hidden" id="eNoteId">
          <div class="mb-3">
            <label class="form-label fw-semibold">কাস্টমারের নাম <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="eCustomerName" maxlength="150" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">তারিখ</label>
            <input type="date" class="form-control" id="eDate">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">নোট <span class="text-danger">*</span></label>
            <textarea class="form-control" id="eText" rows="5" maxlength="2000" required></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="submit" form="editNoteForm" class="btn btn-primary" id="editSaveBtn">
          <i class="bi bi-check-circle me-1"></i>আপডেট করুন
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= asset('assets/js/notes.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
