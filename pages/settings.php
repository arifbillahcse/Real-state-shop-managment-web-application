<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
requireLogin();
requireAdmin();

$pageTitle = 'সেটিংস';
$s = Setting::getAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <h4 class="mb-4"><i class="bi bi-gear me-2"></i>দোকানের সেটিংস</h4>

  <div class="row">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <i class="bi bi-shop me-1"></i>দোকানের তথ্য
        </div>
        <div class="card-body">
          <form id="settingsForm" onsubmit="saveSettings(event)">

            <div class="mb-3">
              <label class="form-label fw-semibold">দোকানের নাম</label>
              <input type="text" class="form-control" name="shop_name"
                     value="<?= e($s['shop_name'] ?? '') ?>" maxlength="150">
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">ঠিকানা</label>
              <textarea class="form-control" name="shop_address" rows="2"
                        maxlength="500"><?= e($s['shop_address'] ?? '') ?></textarea>
            </div>

            <div class="row g-3">
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">ফোন নম্বর</label>
                <input type="text" class="form-control" name="shop_phone"
                       value="<?= e($s['shop_phone'] ?? '') ?>" maxlength="50">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">ইমেইল</label>
                <input type="email" class="form-control" name="shop_email"
                       value="<?= e($s['shop_email'] ?? '') ?>" maxlength="100">
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">মুদ্রা (Currency)</label>
                <input type="text" class="form-control" name="currency"
                       value="<?= e($s['currency'] ?? 'BDT') ?>" maxlength="10">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">ইনভয়েস প্রিফিক্স</label>
                <input type="text" class="form-control" name="invoice_prefix"
                       value="<?= e($s['invoice_prefix'] ?? 'INV') ?>" maxlength="10">
                <small class="text-muted">যেমন: INV-20260531-0001</small>
              </div>
            </div>

            <button type="submit" class="btn btn-primary" id="settingsSaveBtn">
              <i class="bi bi-check-circle me-1"></i>সংরক্ষণ করুন
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header bg-light fw-semibold">
          <i class="bi bi-info-circle me-1"></i>সিস্টেম তথ্য
        </div>
        <div class="card-body small">
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">অ্যাপ ভার্সন</span>
            <span class="fw-semibold"><?= e(APP_VERSION) ?></span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">PHP ভার্সন</span>
            <span class="fw-semibold"><?= e(PHP_VERSION) ?></span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted">টাইমজোন</span>
            <span class="fw-semibold"><?= e(date_default_timezone_get()) ?></span>
          </div>
          <div class="d-flex justify-content-between py-1">
            <span class="text-muted">সার্ভার সময়</span>
            <span class="fw-semibold"><?= date('d M Y, h:i A') ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';

function saveSettings(e) {
    e.preventDefault();
    const form = document.getElementById('settingsForm');
    const data = Object.fromEntries(new FormData(form).entries());
    const btn  = document.getElementById('settingsSaveBtn');
    btn.disabled = true;
    ajaxPost(BASE_URL + '/api/save_settings.php', data, res => {
        btn.disabled = false;
        showToast(res.message, res.success ? 'success' : 'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
