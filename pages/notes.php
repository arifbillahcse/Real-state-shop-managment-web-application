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

      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="input-group">
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
<script src="<?= BASE_URL ?>/assets/js/notes.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
