<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Customer.php';
requireLogin();
// Staff panel manages other people's tasks/dues and shows a cross-branch
// collection ranking — not for a branch-scoped assistant manager.
if (isAssistantManager()) {
    redirect(BASE_URL . '/pages/dashboard.php');
}

$pageTitle = 'স্টাফ প্যানেল';
$canWrite  = User::isAdminOrManager();
$staffList = Database::fetchAll(
    'SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name'
);
$customers = $canWrite ? Customer::getCustomers() : [];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-person-workspace me-2"></i>স্টাফ প্যানেল</h4>
  </div>

  <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#taskTab" type="button">
      <i class="bi bi-list-task me-1"></i>কাজের তালিকা</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#dueTab" type="button">
      <i class="bi bi-cash-coin me-1"></i>হিসাব ট্রান্সফার / টাকা উত্তোলন</button></li>
    <?php if ($canWrite): ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#rankTab" type="button" id="rankTabBtn">
      <i class="bi bi-trophy me-1"></i>মাসিক র‌্যাংকিং</button></li>
    <?php endif; ?>
  </ul>

  <div class="tab-content">

    <!-- Tasks -->
    <div class="tab-pane fade show active" id="taskTab">
      <?php if ($canWrite): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
          <h6 class="fw-semibold mb-2"><i class="bi bi-plus-circle me-1"></i>নতুন কাজ অ্যাসাইন করুন</h6>
          <div class="row g-2 align-items-end">
            <div class="col-md-3">
              <select class="form-select form-select-sm" id="taskUser">
                <option value="">— স্টাফ/সদস্য —</option>
                <?php foreach ($staffList as $u): ?>
                <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['role']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <input type="text" class="form-control form-control-sm" id="taskTitle" placeholder="কাজের শিরোনাম *" maxlength="200">
            </div>
            <div class="col-md-3">
              <input type="text" class="form-control form-control-sm" id="taskDetails" placeholder="বিস্তারিত (ঐচ্ছিক)">
            </div>
            <div class="col-md-2">
              <input type="date" class="form-control form-control-sm" id="taskDueDate">
            </div>
            <div class="col-md-1 d-grid">
              <button class="btn btn-primary btn-sm" onclick="addTask()"><i class="bi bi-plus-lg"></i></button>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>কাজ</th><th>যাকে দেওয়া হয়েছে</th><th>শেষ তারিখ</th>
                <th class="text-center">স্ট্যাটাস</th><th class="text-center" style="width:130px">একশন</th>
              </tr>
            </thead>
            <tbody id="taskBody">
              <tr><td colspan="5" class="text-center text-muted py-3">লোড হচ্ছে...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Due assignments -->
    <div class="tab-pane fade" id="dueTab">
      <?php if ($canWrite): ?>
      <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
          <h6 class="fw-semibold mb-2"><i class="bi bi-arrow-left-right me-1"></i>কাস্টমারের বাকি স্টাফকে ট্রান্সফার করুন</h6>
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <select class="form-select form-select-sm" id="dueCustomer">
                <option value="">— কাস্টমার —</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' — ' . e($c['phone']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <select class="form-select form-select-sm" id="dueStaff">
                <option value="">— স্টাফ/সদস্য —</option>
                <?php foreach ($staffList as $u): ?>
                <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <input type="text" class="form-control form-control-sm" id="dueNote" placeholder="নোট (ঐচ্ছিক)">
            </div>
            <div class="col-md-1 d-grid">
              <button class="btn btn-primary btn-sm" onclick="assignDue()"><i class="bi bi-check-lg"></i></button>
            </div>
          </div>
          <small class="text-muted">ট্রান্সফারের পর ওই কাস্টমারের টাকা জমা হলে তা সংশ্লিষ্ট স্টাফের "টাকা উত্তোলন" খাতায় যুক্ত হবে।</small>
        </div>
      </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th>কাস্টমার</th><th>দায়িত্বপ্রাপ্ত স্টাফ</th><th>তারিখ</th>
                <th class="text-end">বর্তমান ব্যালেন্স</th><th>নোট</th>
                <?php if ($canWrite): ?><th class="text-center" style="width:90px">একশন</th><?php endif; ?>
              </tr>
            </thead>
            <tbody id="dueBody">
              <tr><td colspan="6" class="text-center text-muted py-3">লোড হচ্ছে...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Ranking -->
    <?php if ($canWrite): ?>
    <div class="tab-pane fade" id="rankTab">
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-8 col-md-3">
              <label class="form-label small text-muted mb-1">মাস</label>
              <input type="month" class="form-control form-control-sm" id="rankMonth" value="<?= date('Y-m') ?>">
            </div>
            <div class="col-4 col-md-2 d-grid">
              <button class="btn btn-primary btn-sm" onclick="loadRanking()">দেখুন</button>
            </div>
          </div>
        </div>
      </div>
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th style="width:70px">র‌্যাংক</th><th>স্টাফ/সদস্য</th>
                <th class="text-end">আদায়ের সংখ্যা</th><th class="text-end">মোট আদায় (৳)</th>
              </tr>
            </thead>
            <tbody id="rankBody">
              <tr><td colspan="4" class="text-center text-muted py-3">মাস নির্বাচন করে দেখুন</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
</div>

<script>
const BASE_URL  = '<?= BASE_URL ?>';
const CAN_WRITE = <?= $canWrite ? 'true' : 'false' ?>;

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    }) + ' ৳';
}

// ── Tasks ────────────────────────────────────────────────────────────────────
async function loadTasks() {
    const tbody = document.getElementById('taskBody');
    try {
        const res  = await fetch(`${BASE_URL}/api/get_staff_tasks.php`);
        const data = await res.json();
        const tasks = (data.tasks) || [];
        tbody.innerHTML = tasks.length ? tasks.map(t => `
            <tr class="${t.status === 'done' ? 'text-muted' : ''}">
                <td><strong>${esc(t.title)}</strong>
                    ${t.details ? `<div class="small text-muted">${esc(t.details)}</div>` : ''}</td>
                <td>${esc(t.user_name)}
                    ${t.assigned_by_name ? `<div class="small text-muted">অ্যাসাইন: ${esc(t.assigned_by_name)}</div>` : ''}</td>
                <td>${t.due_date || '—'}</td>
                <td class="text-center">${t.status === 'done'
                    ? '<span class="badge bg-success">সম্পন্ন</span>'
                    : '<span class="badge bg-warning text-dark">পেন্ডিং</span>'}</td>
                <td class="text-center text-nowrap">
                    ${t.status === 'pending'
                        ? `<button class="btn btn-sm btn-outline-success" onclick="setTaskStatus(${t.id}, 'done')" title="সম্পন্ন">
                             <i class="bi bi-check-lg"></i></button>`
                        : `<button class="btn btn-sm btn-outline-secondary" onclick="setTaskStatus(${t.id}, 'pending')" title="পুনরায় পেন্ডিং">
                             <i class="bi bi-arrow-counterclockwise"></i></button>`}
                    ${CAN_WRITE ? `
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTask(${t.id})" title="ডিলিট">
                        <i class="bi bi-trash"></i></button>` : ''}
                </td>
            </tr>`).join('')
            : '<tr><td colspan="5" class="text-center text-muted py-3">কোনো কাজ নেই</td></tr>';
    } catch {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">লোড করা যায়নি</td></tr>';
    }
}

function addTask() {
    ajaxPost(`${BASE_URL}/api/add_staff_task.php`, {
        user_id:  document.getElementById('taskUser').value,
        title:    document.getElementById('taskTitle').value,
        details:  document.getElementById('taskDetails').value,
        due_date: document.getElementById('taskDueDate').value,
    }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
            document.getElementById('taskTitle').value = '';
            document.getElementById('taskDetails').value = '';
            document.getElementById('taskDueDate').value = '';
            loadTasks();
        }
    });
}

function setTaskStatus(id, status) {
    ajaxPost(`${BASE_URL}/api/update_staff_task.php`, { id, status }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadTasks();
    });
}

function deleteTask(id) {
    if (!confirm('কাজটি ডিলিট করবেন?')) return;
    ajaxPost(`${BASE_URL}/api/update_staff_task.php`, { id, action: 'delete' }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadTasks();
    });
}

// ── Due assignments ──────────────────────────────────────────────────────────
async function loadAssignments() {
    const tbody = document.getElementById('dueBody');
    try {
        const res  = await fetch(`${BASE_URL}/api/get_due_assignments.php`);
        const data = await res.json();
        const rows = (data.assignments) || [];
        tbody.innerHTML = rows.length ? rows.map(a => `
            <tr>
                <td><strong>${esc(a.customer_name)}</strong>
                    ${a.customer_phone ? `<div class="small text-muted">${esc(a.customer_phone)}</div>` : ''}</td>
                <td>${esc(a.staff_name)}</td>
                <td>${a.assigned_date}</td>
                <td class="text-end ${parseFloat(a.current_balance) > 0 ? 'text-danger fw-semibold' : 'text-success'}">
                    ${fmt(a.current_balance)}</td>
                <td class="small text-muted">${esc(a.note || '—')}</td>
                ${CAN_WRITE ? `
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-secondary" onclick="closeAssignment(${a.id})" title="বন্ধ করুন">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </td>` : ''}
            </tr>`).join('')
            : `<tr><td colspan="6" class="text-center text-muted py-3">কোনো সক্রিয় হিসাব ট্রান্সফার নেই</td></tr>`;
    } catch {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">লোড করা যায়নি</td></tr>';
    }
}

function assignDue() {
    ajaxPost(`${BASE_URL}/api/assign_due.php`, {
        customer_id: document.getElementById('dueCustomer').value,
        staff_id:    document.getElementById('dueStaff').value,
        note:        document.getElementById('dueNote').value,
    }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) {
            document.getElementById('dueNote').value = '';
            loadAssignments();
        }
    });
}

function closeAssignment(id) {
    if (!confirm('এই হিসাব ট্রান্সফারটি বন্ধ করবেন?')) return;
    ajaxPost(`${BASE_URL}/api/close_due_assignment.php`, { id }, res => {
        showToast(res.message, res.success ? 'success' : 'danger');
        if (res.success) loadAssignments();
    });
}

// ── Ranking ──────────────────────────────────────────────────────────────────
async function loadRanking() {
    const tbody = document.getElementById('rankBody');
    if (!tbody) return;
    const month = document.getElementById('rankMonth').value;
    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></td></tr>';
    try {
        const res  = await fetch(`${BASE_URL}/api/get_staff_ranking.php?month=${month}`);
        const data = await res.json();
        const rows = (data.ranking) || [];
        const medal = ['🥇', '🥈', '🥉'];
        tbody.innerHTML = rows.length ? rows.map((r, i) => `
            <tr>
                <td class="fs-5">${medal[i] || (i + 1)}</td>
                <td class="fw-semibold">${esc(r.name)} <span class="badge bg-light text-dark border">${esc(r.role)}</span></td>
                <td class="text-end">${r.deposit_count}</td>
                <td class="text-end fw-bold text-success">${fmt(r.collected)}</td>
            </tr>`).join('')
            : '<tr><td colspan="4" class="text-center text-muted py-3">এই মাসে কোনো আদায় নেই</td></tr>';
    } catch {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3">লোড করা যায়নি</td></tr>';
    }
}

// ── Init ─────────────────────────────────────────────────────────────────────
loadTasks();
loadAssignments();
if (CAN_WRITE) loadRanking();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
