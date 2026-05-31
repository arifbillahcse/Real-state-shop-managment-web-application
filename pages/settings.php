<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
requireLogin();
requireAdmin();

$pageTitle = 'সেটিংস';
$s = Setting::getAll();

// ── System stats ──────────────────────────────────────────────────────────────
$dbVersion   = Database::fetchOne('SELECT VERSION() AS v')['v'] ?? '—';
$totalUsers  = Database::fetchOne('SELECT COUNT(*) AS c FROM users WHERE is_active = 1')['c'] ?? 0;
$totalProd   = Database::fetchOne('SELECT COUNT(*) AS c FROM products WHERE is_active = 1')['c'] ?? 0;
$totalCust   = Database::fetchOne('SELECT COUNT(*) AS c FROM customers WHERE is_active = 1')['c'] ?? 0;
$totalSales  = Database::fetchOne('SELECT COUNT(*) AS c FROM sales WHERE status = "completed"')['c'] ?? 0;
$totalCats   = Database::fetchOne('SELECT COUNT(*) AS c FROM product_categories')['c'] ?? 0;

$diskFree    = function_exists('disk_free_space')  ? disk_free_space('/')  : null;
$diskTotal   = function_exists('disk_total_space') ? disk_total_space('/') : null;
$memLimit    = ini_get('memory_limit');
$uploadMax   = ini_get('upload_max_filesize');
$serverSW    = $_SERVER['SERVER_SOFTWARE'] ?? '—';

function fmtBytes(float $bytes): string {
    if ($bytes >= 1_073_741_824) return round($bytes / 1_073_741_824, 1) . ' GB';
    if ($bytes >= 1_048_576)     return round($bytes / 1_048_576, 1)     . ' MB';
    return round($bytes / 1024, 1) . ' KB';
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <h4 class="mb-4"><i class="bi bi-gear me-2"></i>সেটিংস</h4>

  <div class="row g-4">

    <!-- ── Shop settings form ──────────────────────────────────────────── -->
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

    <!-- ── Right column ─────────────────────────────────────────────────── -->
    <div class="col-lg-4 d-flex flex-column gap-4">

      <!-- System info -->
      <div class="card shadow-sm">
        <div class="card-header fw-semibold bg-light">
          <i class="bi bi-cpu me-1 text-danger"></i>সিস্টেম তথ্য
        </div>
        <div class="card-body p-0">

          <div class="px-3 py-2 border-bottom bg-light">
            <small class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.5px">
              সফটওয়্যার
            </small>
          </div>

          <?php
          $rows = [
              ['bi-box-seam',     'অ্যাপ ভার্সন',   APP_VERSION],
              ['bi-filetype-php', 'PHP ভার্সন',      PHP_VERSION],
              ['bi-database',     'MySQL ভার্সন',    $dbVersion],
              ['bi-hdd-rack',     'সার্ভার সফটওয়্যার', $serverSW],
          ];
          foreach ($rows as [$icon, $label, $value]): ?>
          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <span class="text-muted small"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></span>
            <span class="fw-semibold small text-end" style="max-width:55%;word-break:break-all"><?= e($value) ?></span>
          </div>
          <?php endforeach; ?>

          <div class="px-3 py-2 border-bottom bg-light mt-1">
            <small class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.5px">
              সার্ভার
            </small>
          </div>

          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <span class="text-muted small"><i class="bi bi-clock me-1"></i>সার্ভার সময়</span>
            <span class="fw-semibold small"><?= date('d M Y, h:i A') ?></span>
          </div>
          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <span class="text-muted small"><i class="bi bi-globe me-1"></i>টাইমজোন</span>
            <span class="fw-semibold small"><?= e(date_default_timezone_get()) ?></span>
          </div>
          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <span class="text-muted small"><i class="bi bi-memory me-1"></i>মেমোরি লিমিট</span>
            <span class="fw-semibold small"><?= e($memLimit) ?></span>
          </div>
          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <span class="text-muted small"><i class="bi bi-upload me-1"></i>আপলোড লিমিট</span>
            <span class="fw-semibold small"><?= e($uploadMax) ?></span>
          </div>
          <?php if ($diskTotal): ?>
          <div class="px-3 py-2 border-bottom">
            <div class="d-flex justify-content-between small mb-1">
              <span class="text-muted"><i class="bi bi-hdd me-1"></i>ডিস্ক স্পেস</span>
              <span class="fw-semibold"><?= fmtBytes($diskFree) ?> ফ্রি / <?= fmtBytes($diskTotal) ?></span>
            </div>
            <div class="progress" style="height:5px">
              <div class="progress-bar bg-danger"
                   style="width:<?= round(($diskTotal - $diskFree) / $diskTotal * 100) ?>%"></div>
            </div>
          </div>
          <?php endif; ?>

          <div class="px-3 py-2 border-bottom bg-light mt-1">
            <small class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.5px">
              ডেটাবেস পরিসংখ্যান
            </small>
          </div>

          <?php
          $stats = [
              ['bi-people',       'ব্যবহারকারী',  $totalUsers],
              ['bi-box-seam',     'পণ্য',          $totalProd],
              ['bi-tags',         'ক্যাটাগরি',     $totalCats],
              ['bi-person-lines-fill', 'কাস্টমার', $totalCust],
              ['bi-cart-check',   'বিক্রয় (সম্পন্ন)', $totalSales],
          ];
          foreach ($stats as [$icon, $label, $count]): ?>
          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <span class="text-muted small"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></span>
            <span class="badge bg-secondary"><?= number_format((int)$count) ?></span>
          </div>
          <?php endforeach; ?>

        </div>
      </div>

      <!-- Developed by Softorio -->
      <div class="card shadow-sm border-0" style="background:linear-gradient(135deg,#1a1a2e,#16213e)">
        <div class="card-body text-center py-4">
          <div class="mb-2">
            <i class="bi bi-code-slash text-white" style="font-size:2rem"></i>
          </div>
          <p class="text-white-50 small mb-1">এই সফটওয়্যারটি তৈরি করেছে</p>
          <h5 class="text-white fw-bold mb-1">Softorio</h5>
          <p class="text-white-50 small mb-3">
            Custom Software Development<br>Bangladesh
          </p>
          <a href="https://softorio.com/our-founders.html"
             target="_blank" rel="noopener noreferrer"
             class="btn btn-sm btn-outline-light px-4">
            <i class="bi bi-people me-1"></i>আমাদের সম্পর্কে জানুন
          </a>
        </div>
      </div>

    </div><!-- /col right -->

  </div><!-- /row -->
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
