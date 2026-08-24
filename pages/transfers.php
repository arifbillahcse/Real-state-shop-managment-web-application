<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Branch.php';
requireLogin();

$pageTitle = 'পণ্য ট্রান্সফার';
$products  = Product::getProducts();
// Transfers need BOTH sides: a branch-locked user may only send FROM their
// own branch, but must still be able to send TO any other branch — so the
// full list is needed for the destination and for display/labels.
$branches       = Branch::getBranches();
$sourceBranches = Branch::getVisibleBranches();
$canWrite       = canWriteBranchData();
$_isStaff       = isStaff();
$staffBranch    = getSessionBranchId();
$lockedBranch   = lockedBranchId();
// Order-placing managers: same roles allowed to write a transfer, so whoever
// is filling this form always appears in their own dropdown.
$orderManagers  = Database::fetchAll(
    "SELECT name FROM users
     WHERE is_active = 1 AND role IN ('admin','manager','assistant_manager')
     ORDER BY name"
);
$currentUserName = $_SESSION['user_name'] ?? '';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>পণ্য ট্রান্সফার সিস্টেম</h4>
  </div>

  <?php if (count($branches) < 2): ?>
  <div class="alert alert-info">
    ট্রান্সফার করতে কমপক্ষে দুটি সক্রিয় ব্রাঞ্চ প্রয়োজন।
  </div>
  <?php else: ?>

  <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto">
    <?php if ($canWrite): ?>
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#newTransferTab" type="button">
      <i class="bi bi-plus-circle me-1"></i>পণ্য ট্রান্সফার</button></li>
    <?php endif; ?>
    <li class="nav-item"><button class="nav-link <?= $canWrite ? '' : 'active' ?>" data-bs-toggle="pill" data-bs-target="#listTab" type="button" id="listTabBtn">
      <i class="bi bi-list-ul me-1"></i>ট্রান্সফার তালিকা</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#searchTab" type="button">
      <i class="bi bi-search me-1"></i>সার্চ</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#receiveTab" type="button" id="receiveTabBtn">
      <i class="bi bi-box-arrow-in-down me-1"></i>পণ্য রিসিভ <span class="badge bg-danger d-none" id="incomingCount"></span></button></li>
  </ul>

  <div class="tab-content">

    <!-- ═══ New transfer entry ═══ -->
    <?php if ($canWrite): ?>
    <div class="tab-pane fade show active" id="newTransferTab">
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">
          <i class="bi bi-truck me-1 text-danger"></i>নতুন ট্রান্সফার এন্ট্রি (এন্ট্রি ফর্ম)
        </div>
        <div class="card-body">
          <form id="transferForm" onsubmit="submitTransferEntry(event)">
            <input type="hidden" id="tEntryId">
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label fw-semibold small">তারিখ</label>
                <input type="date" class="form-control form-control-sm" id="tDate" value="<?= today() ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">প্রেরক ব্রাঞ্চ <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="tFromBranch" required
                        <?= $lockedBranch !== null ? 'disabled' : '' ?>>
                  <?php if ($lockedBranch === null): ?>
                  <option value="">— নির্বাচন —</option>
                  <?php endif; ?>
                  <?php foreach ($sourceBranches as $b): ?>
                  <option value="<?= $b['id'] ?>" <?= $lockedBranch !== null ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label fw-semibold small">গন্তব্য ব্রাঞ্চ <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="tToBranch" required>
                  <option value="">— নির্বাচন —</option>
                  <?php foreach ($branches as $b): ?>
                  <?php if ($lockedBranch !== null && (int)$b['id'] === $lockedBranch) continue; ?>
                  <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label fw-semibold small">কাস্টমারের নাম <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" id="tCustomerName" maxlength="150" required>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">ঠিকানা</label>
                <input type="text" class="form-control form-control-sm" id="tCustomerAddress" maxlength="500">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">মোবাইল নাম্বার <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" id="tCustomerMobile" maxlength="20" required>
              </div>

              <div class="col-md-4">
                <label class="form-label fw-semibold small">অর্ডার প্রদানকারী ম্যানেজারের নাম</label>
                <select class="form-select form-select-sm" id="tOrderManager">
                  <option value="">— নির্বাচন —</option>
                  <?php foreach ($orderManagers as $m): ?>
                  <option value="<?= e($m['name']) ?>"><?= e($m['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">ড্রাইভারের নাম <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" id="tDriverName" maxlength="150" required>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">ড্রাইভারের মোবাইল <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" id="tDriverMobile" maxlength="20" required>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold small mb-1">পণ্য <span class="text-danger">*</span></label>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle mb-2" id="tItemsTable">
                    <thead class="table-light">
                      <tr>
                        <th style="min-width:220px">পণ্য</th>
                        <th style="width:140px">পরিমাণ</th>
                        <th style="width:44px"></th>
                      </tr>
                    </thead>
                    <tbody id="tItemsBody"></tbody>
                  </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="tAddRowBtn" onclick="addTransferItemRow()">
                  <i class="bi bi-plus-lg me-1"></i>আরেকটা পণ্য
                </button>
                <div class="text-muted small mt-1 d-none" id="tNoItemsAlert">কমপক্ষে একটি পণ্য যোগ করুন।</div>
              </div>

              <div class="col-md-6 mt-2">
                <label class="form-label fw-semibold small">নোট</label>
                <input type="text" class="form-control form-control-sm" id="tNote" maxlength="500">
              </div>
            </div>

            <div class="alert alert-info py-2 small mt-3 mb-2">
              <i class="bi bi-info-circle me-1"></i>"শিটে যুক্ত করুন" দিলে তথ্য সাথে সাথে ট্রান্সফার হবে না —
              এই কাস্টমারের জন্য যোগ করা সবগুলো পণ্য একসাথে ট্রান্সফার শিটের তালিকায় যুক্ত হবে।
              শিট থেকে <strong>ট্রান্সফার</strong> বাটনে চাপলে পণ্য পাঠানো হবে।
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary" id="tSubmitBtn">
                <i class="bi bi-plus-circle me-1"></i>শিটে যুক্ত করুন (এন্টার)
              </button>
              <button type="button" class="btn btn-outline-secondary d-none" id="tCancelEditBtn" onclick="cancelEditEntry()">
                বাতিল
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Today's sheet under the form -->
      <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-card-list me-1 text-danger"></i>আজকের ট্রান্সফার শিট</span>
          <button class="btn btn-sm btn-outline-secondary" onclick="loadTodaySheet()">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>কাস্টমার</th><th>মোবাইল</th><th>পণ্য</th><th class="text-end">পরিমাণ</th>
                <th>গন্তব্য</th><th>ড্রাইভার</th><th class="text-center">স্ট্যাটাস</th>
                <th class="text-center" style="width:150px">একশন</th>
              </tr>
            </thead>
            <tbody id="todaySheetBody">
              <tr><td colspan="8" class="text-center text-muted py-3">লোড হচ্ছে...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ═══ Transfer list (date-wise) ═══ -->
    <div class="tab-pane fade <?= $canWrite ? '' : 'show active' ?>" id="listTab">
      <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
          <i class="bi bi-calendar3 me-1 text-danger"></i>তারিখ অনুযায়ী ট্রান্সফার তালিকা (সর্বশেষ উপরে)
        </div>
        <div id="dateListWrap" class="p-3">
          <p class="text-muted text-center mb-0">লোড হচ্ছে...</p>
        </div>
      </div>
      <div class="card shadow-sm d-none" id="sheetCard">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-card-list me-1 text-danger"></i>শিট: <span id="sheetDateLabel"></span></span>
          <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('sheetCard').classList.add('d-none')">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>কাস্টমার</th><th>মোবাইল</th><th>পণ্য</th><th class="text-end">পরিমাণ</th>
                <th>রুট</th><th>ড্রাইভার</th><th class="text-center">স্ট্যাটাস</th>
                <th class="text-center" style="width:150px">একশন</th>
              </tr>
            </thead>
            <tbody id="sheetBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══ Search ═══ -->
    <div class="tab-pane fade" id="searchTab">
      <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
              <label class="form-label small fw-semibold">সার্চের ধরন</label>
              <select class="form-select form-select-sm" id="searchType" data-no-search="1">
                <option value="customer">কাস্টমার সার্চ</option>
                <option value="driver">ড্রাইভার সার্চ</option>
              </select>
            </div>
            <div class="col-12 col-md-7">
              <label class="form-label small fw-semibold">নাম / মোবাইল নাম্বার</label>
              <input type="text" class="form-control form-control-sm" id="searchQuery"
                     placeholder="যেকোনো অক্ষর বা সংখ্যা লিখলেই ফলাফল দেখাবে...">
            </div>
            <div class="col-12 col-md-2 d-grid">
              <button class="btn btn-primary btn-sm" onclick="runTransferSearch()">
                <i class="bi bi-search me-1"></i>সার্চ
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="card shadow-sm d-none" id="searchResultCard">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>তারিখ</th><th>কাস্টমার</th><th>মোবাইল</th><th>পণ্য</th>
                <th class="text-end">পরিমাণ</th><th>রুট</th><th>ড্রাইভার</th><th class="text-center">স্ট্যাটাস</th>
              </tr>
            </thead>
            <tbody id="searchResultBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══ Receive ═══ -->
    <div class="tab-pane fade" id="receiveTab">
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
              <label class="form-label small fw-semibold">গ্রহণকারী ব্রাঞ্চ</label>
              <select class="form-select form-select-sm" id="rcvBranch"
                      <?= $lockedBranch !== null ? 'disabled' : '' ?>>
                <?php foreach (($lockedBranch !== null ? $sourceBranches : $branches) as $b): ?>
                <option value="<?= $b['id'] ?>"
                        <?= ($lockedBranch !== null || ($_isStaff && $staffBranch == $b['id'])) ? 'selected' : '' ?>>
                  <?= e($b['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-8 col-md-4">
              <label class="form-label small fw-semibold">তারিখ (খালি = সব)</label>
              <input type="date" class="form-control form-control-sm" id="rcvDate" value="<?= today() ?>">
            </div>
            <div class="col-4 col-md-3 d-grid">
              <button class="btn btn-primary btn-sm" onclick="loadIncoming()">
                <i class="bi bi-arrow-clockwise me-1"></i>লোড করুন
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>তারিখ</th><th>প্রেরক ব্রাঞ্চ</th><th>কাস্টমার</th><th>পণ্য</th>
                <th class="text-end">পরিমাণ</th><th>ড্রাইভার</th>
                <th class="text-center" style="width:170px">একশন</th>
              </tr>
            </thead>
            <tbody id="incomingBody">
              <tr><td colspan="7" class="text-center text-muted py-3">লোড করুন বাটনে ক্লিক করুন</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
  <?php endif; ?>

</div>
</div>

<script>
const BASE_URL     = '<?= BASE_URL ?>';
const CAN_WRITE    = <?= $canWrite ? 'true' : 'false' ?>;
const IS_STAFF     = <?= $_isStaff ? 'true' : 'false' ?>;
const STAFF_BRANCH = <?= $staffBranch ?? 'null' ?>;
const TODAY        = '<?= today() ?>';
const TRANSFER_PRODUCTS = <?= json_encode(array_map(
    fn($p) => ['id' => $p['id'], 'name' => $p['name'], 'unit' => $p['unit']], $products
)) ?>;
const CURRENT_USER_NAME = <?= json_encode($currentUserName) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/transfers.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
