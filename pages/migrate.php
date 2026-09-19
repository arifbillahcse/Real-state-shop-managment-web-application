<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Migrator.php';
requireLogin();
requireAdmin();

$pageTitle = 'ডাটাবেস আপডেট';

$pending = [];
$loadError = '';
try {
    $pending = Migrator::pending();
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}
$applied = [];
try {
    $applied = Database::fetchAll(
        'SELECT version, name, applied_at FROM schema_migrations ORDER BY version DESC LIMIT 30'
    );
} catch (Throwable $e) { /* table may not exist yet */ }

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-database-gear me-2"></i>ডাটাবেস আপডেট</h4>
  </div>

  <?php if ($loadError): ?>
    <div class="alert alert-danger">
      <strong>স্ট্যাটাস পড়া যায়নি:</strong> <?= e($loadError) ?>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <?php if (!$pending): ?>
        <div class="d-flex align-items-center gap-2 text-success mb-0">
          <i class="bi bi-check-circle-fill fs-4"></i>
          <span class="fw-semibold">ডাটাবেস আপ-টু-ডেট। কিছু করার নেই।</span>
        </div>
      <?php else: ?>
        <h6 class="fw-semibold mb-2">
          <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
          <?= count($pending) ?>টি আপডেট বাকি আছে
        </h6>
        <ul class="small mb-3">
          <?php foreach ($pending as $m): ?>
            <li>v<?= (int)$m['version'] ?> — <?= e($m['name']) ?></li>
          <?php endforeach; ?>
        </ul>
        <div class="alert alert-warning py-2 small">
          চালানোর আগে cPanel থেকে ডাটাবেসের একটা ব্যাকআপ নিয়ে রাখা ভালো।
        </div>
        <button class="btn btn-primary" id="btnRunMigrations">
          <i class="bi bi-play-fill me-1"></i>এখনই আপডেট করুন
        </button>
      <?php endif; ?>
      <div id="migrateResult" class="mt-3"></div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header fw-semibold">
      <i class="bi bi-clock-history me-1"></i>প্রয়োগ করা আপডেট
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-dark">
          <tr><th>ভার্সন</th><th>নাম</th><th>কখন</th></tr>
        </thead>
        <tbody>
          <?php if (!$applied): ?>
            <tr><td colspan="3" class="text-center text-muted py-3">কোনো রেকর্ড নেই</td></tr>
          <?php else: foreach ($applied as $a): ?>
            <tr>
              <td>v<?= (int)$a['version'] ?></td>
              <td><?= e($a['name']) ?></td>
              <td class="text-muted small"><?= e($a['applied_at']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
document.getElementById('btnRunMigrations')?.addEventListener('click', async function () {
    if (!confirm('ডাটাবেস আপডেট চালাবেন?')) return;
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>চলছে...';
    const box = document.getElementById('migrateResult');
    try {
        const res = await fetch(`${BASE_URL}/api/run_migrations.php`, { method: 'POST' });
        const raw = await res.text();
        let data;
        try { data = JSON.parse(raw); }
        catch {
            box.innerHTML = `<div class="alert alert-danger">সার্ভার JSON দেয়নি (HTTP ${res.status}):
                             <pre class="small mb-0">${raw.slice(0, 400)}</pre></div>`;
            return;
        }
        const list = (data.applied || []).map(a => `<li>${a}</li>`).join('');
        box.innerHTML = `
            <div class="alert alert-${data.success ? 'success' : 'danger'}">
                <strong>${data.message}</strong>
                ${list ? `<ul class="small mt-2 mb-0">${list}</ul>` : ''}
                ${data.failed ? `<div class="small mt-2">
                    <strong>v${data.failed.version} (${data.failed.name})</strong>:
                    ${data.failed.error}</div>` : ''}
            </div>`;
        if (data.success) setTimeout(() => location.reload(), 1500);
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-play-fill me-1"></i>এখনই আপডেট করুন';
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
