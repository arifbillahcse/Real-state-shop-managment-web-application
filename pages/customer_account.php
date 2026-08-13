<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Customer.php';
require_once __DIR__ . '/../classes/Ledger.php';
require_once __DIR__ . '/../classes/Product.php';
requireLogin();

$customerId = (int)($_GET['id'] ?? 0);
$customer   = $customerId > 0 ? Customer::getCustomerById($customerId) : false;
// Branch-locked users must not reach another branch's customer by URL.
if (!$customer || !Customer::isVisibleToCurrentUser($customerId)) {
    redirect(BASE_URL . '/pages/customers.php');
}

$pageTitle = 'একাউন্ট — ' . $customer['name'];
$products  = Product::getProducts();
$canWrite  = canWriteBranchData();
$balance   = Ledger::balance($customerId);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <!-- Profile header -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <?php if (!empty($customer['photo'])): ?>
        <img src="<?= BASE_URL . '/' . e($customer['photo']) ?>" class="rounded-circle border"
             style="width:64px;height:64px;object-fit:cover">
        <?php else: ?>
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle border bg-light text-muted"
              style="width:64px;height:64px;font-size:1.6rem"><i class="bi bi-person"></i></span>
        <?php endif; ?>
        <div class="flex-grow-1">
          <h4 class="mb-1">
            <?= e($customer['name']) ?>
            <?php if ($customer['account_no']): ?>
            <span class="badge bg-dark ms-1"><?= e($customer['account_no']) ?></span>
            <?php endif; ?>
            <?= ($customer['account_type'] ?? 'full') === 'short'
                ? '<span class="badge bg-warning text-dark">শর্ট</span>'
                : '<span class="badge bg-success">ফুল</span>' ?>
          </h4>
          <div class="text-muted small">
            <i class="bi bi-telephone me-1"></i><?= e($customer['phone'] ?: '—') ?>
            <?php if ($customer['whatsapp']): ?>
              <span class="ms-2"><i class="bi bi-whatsapp me-1 text-success"></i><?= e($customer['whatsapp']) ?></span>
            <?php endif; ?>
            <span class="ms-2"><i class="bi bi-geo-alt me-1"></i><?= e($customer['address'] ?: '—') ?></span>
          </div>
        </div>
        <div class="text-end">
          <p class="small text-muted mb-0">বর্তমান ব্যালেন্স</p>
          <h3 class="fw-bold mb-0 <?= $balance > 0 ? 'text-danger' : 'text-success' ?>" id="balanceDisplay">
            <?= money(abs($balance)) ?>
          </h3>
          <small class="text-muted" id="balanceLabel">
            <?= $balance > 0 ? 'বাকি আছে' : ($balance < 0 ? 'অগ্রিম জমা' : 'পরিশোধিত') ?>
          </small>
        </div>
      </div>
    </div>
  </div>

  <!-- Action menu -->
  <?php if ($canWrite): ?>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <button class="btn btn-primary btn-sm" onclick="openGoodsModal(true)">
      <i class="bi bi-cart-plus me-1"></i>মালামাল এন্ট্রি
    </button>
    <button class="btn btn-success btn-sm" onclick="openDepositModal()">
      <i class="bi bi-cash-coin me-1"></i>টাকা জমা
    </button>
    <button class="btn btn-outline-danger btn-sm" onclick="openMoneyReturnModal()">
      <i class="bi bi-cash-stack me-1"></i>টাকা ফেরত
    </button>
    <button class="btn btn-outline-warning btn-sm" onclick="openProductReturnModal()">
      <i class="bi bi-arrow-return-left me-1"></i>রিটার্ন পণ্য
    </button>
    <button class="btn btn-outline-secondary btn-sm" onclick="openExpenseModal()">
      <i class="bi bi-receipt me-1"></i>অন্যান্য খরচ
    </button>
    <button class="btn btn-outline-primary btn-sm" onclick="openGoodsModal(false)">
      <i class="bi bi-journal-text me-1"></i>খসড়া মেমো
    </button>
    <button class="btn btn-outline-dark btn-sm" onclick="openAgreementModal()">
      <i class="bi bi-file-earmark-ruled me-1"></i>চুক্তিপত্র / ডিট
    </button>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <ul class="nav nav-pills mb-3">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#ledgerTab" type="button">
      <i class="bi bi-journal-bookmark me-1"></i>লেনদেন খাতা</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#draftTab" type="button">
      <i class="bi bi-journal-text me-1"></i>খসড়া মেমো <span class="badge bg-secondary" id="draftCount">0</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#summaryTab" type="button">
      <i class="bi bi-boxes me-1"></i>পণ্য সারাংশ</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#agreementTab" type="button">
      <i class="bi bi-file-earmark-ruled me-1"></i>চুক্তিপত্র <span class="badge bg-secondary" id="agrCount">0</span></button></li>
  </ul>

  <div class="tab-content">
    <!-- Ledger -->
    <div class="tab-pane fade show active" id="ledgerTab">
      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span class="fw-semibold"><i class="bi bi-journal-bookmark me-1 text-danger"></i>লেনদেনের ইতিহাস</span>
          <button class="btn btn-outline-secondary btn-sm" onclick="printLedger()">
            <i class="bi bi-printer me-1"></i>খাতা প্রিন্ট
          </button>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-dark">
              <tr>
                <th>তারিখ</th>
                <th>বিবরণ</th>
                <th class="text-end">ডেবিট (৳)</th>
                <th class="text-end">ক্রেডিট (৳)</th>
                <th class="text-end">ব্যালেন্স (৳)</th>
              </tr>
            </thead>
            <tbody id="ledgerBody">
              <tr><td colspan="5" class="text-center text-muted py-4">
                <span class="spinner-border spinner-border-sm me-1"></span>লোড হচ্ছে...
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Drafts -->
    <div class="tab-pane fade" id="draftTab">
      <div id="draftList"><p class="text-muted text-center py-4">কোনো খসড়া মেমো নেই</p></div>
    </div>

    <!-- Product summary -->
    <div class="tab-pane fade" id="summaryTab">
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>পণ্য</th>
                <th class="text-end">মোট নেওয়া</th>
                <th class="text-end">মোট ফেরত</th>
                <th class="text-end">মোট মূল্য (৳)</th>
              </tr>
            </thead>
            <tbody id="summaryBody">
              <tr><td colspan="4" class="text-center text-muted py-3">—</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Agreements -->
    <div class="tab-pane fade" id="agreementTab">
      <div id="agreementList"><p class="text-muted text-center py-4">কোনো চুক্তিপত্র নেই</p></div>
    </div>
  </div>

</div>
</div>

<!-- ═══ Goods entry / Draft memo modal ═══ -->
<div class="modal fade" id="goodsModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="goodsModalTitle"><i class="bi bi-cart-plus me-2"></i>মালামাল এন্ট্রি</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-md-3">
            <label class="form-label fw-semibold small">তারিখ</label>
            <input type="date" class="form-control form-control-sm" id="gDate" value="<?= today() ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label fw-semibold small">খরচ যুক্ত করার পদ্ধতি</label>
            <div class="btn-group btn-group-sm w-100" role="group">
              <input type="radio" class="btn-check" name="chargeMode" id="chargeCombined" value="combined" checked>
              <label class="btn btn-outline-primary" for="chargeCombined">একত্রে খরচ</label>
              <input type="radio" class="btn-check" name="chargeMode" id="chargePerItem" value="per_item">
              <label class="btn btn-outline-primary" for="chargePerItem">পণ্যভিত্তিক খরচ</label>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">নোট</label>
            <input type="text" class="form-control form-control-sm" id="gNote" maxlength="500">
          </div>
        </div>

        <div class="table-responsive mb-2">
          <table class="table table-bordered table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:200px">পণ্য</th>
                <th style="width:100px">পরিমাণ</th>
                <th style="width:120px">দর (৳)</th>
                <th class="charge-col" style="width:110px">আনলোড বিল</th>
                <th class="charge-col" style="width:110px">লেবার বিল</th>
                <th class="charge-col" style="width:110px">গাড়িভাড়া</th>
                <th style="width:120px" class="text-end">মোট (৳)</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody id="gItemsBody"></tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addGoodsRow()">
          <i class="bi bi-plus-lg me-1"></i>পণ্য যোগ করুন
        </button>

        <div class="row g-2 justify-content-end" id="combinedChargesRow">
          <div class="col-md-3">
            <label class="form-label small text-muted">আনলোড বিল (একত্রে)</label>
            <input type="number" class="form-control form-control-sm" id="gUnload" min="0" step="0.01" value="0" oninput="calcGoodsTotal()">
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted">লেবার বিল (একত্রে)</label>
            <input type="number" class="form-control form-control-sm" id="gLabor" min="0" step="0.01" value="0" oninput="calcGoodsTotal()">
          </div>
          <div class="col-md-3">
            <label class="form-label small text-muted">গাড়িভাড়া (একত্রে)</label>
            <input type="number" class="form-control form-control-sm" id="gTransport" min="0" step="0.01" value="0" oninput="calcGoodsTotal()">
          </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
          <div class="text-end">
            <p class="text-muted small mb-0">সর্বমোট</p>
            <h4 class="fw-bold mb-0" id="gGrandTotal">০.০০ ৳</h4>
          </div>
        </div>
        <div class="alert alert-danger py-2 mt-2 d-none" id="gError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-outline-primary" id="btnSaveDraft" onclick="saveGoods(false)">
          <i class="bi bi-journal-text me-1"></i>খসড়া হিসেবে রাখুন
        </button>
        <button type="button" class="btn btn-primary" id="btnSaveGoods" onclick="saveGoods(true)">
          <i class="bi bi-check-circle me-1"></i>সেভ (একাউন্টে যুক্ত)
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Deposit modal ═══ -->
<div class="modal fade" id="depositModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>টাকা জমা</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label fw-semibold small">তারিখ</label>
            <input type="date" class="form-control" id="dDate" value="<?= today() ?>">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">পরিমাণ (৳) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="dAmount" min="0.01" step="0.01">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">পদ্ধতি</label>
            <select class="form-select" id="dMethod" data-no-search="1">
              <option value="নগদ">নগদ</option>
              <option value="ব্যাংক">ব্যাংক</option>
              <option value="বিকাশ">বিকাশ</option>
              <option value="নগদ (মোবাইল)">নগদ (মোবাইল)</option>
              <option value="রকেট">রকেট</option>
              <option value="চেক">চেক</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">নোট</label>
            <input type="text" class="form-control" id="dNote" maxlength="500">
          </div>
        </div>
        <div class="alert alert-info py-2 mt-3 mb-0 small">
          <i class="bi bi-chat-dots me-1"></i>জমা সম্পন্ন হলে কাস্টমারের মোবাইলে অটো SMS যাবে
          (পরিমাণ, তারিখ ও বর্তমান ব্যালেন্সসহ)।
        </div>
        <div class="alert alert-danger py-2 mt-2 d-none" id="dError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-success" id="btnSaveDeposit" onclick="saveDeposit()">
          <i class="bi bi-check-circle me-1"></i>জমা করুন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Money return modal ═══ -->
<div class="modal fade" id="moneyReturnModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-cash-stack me-2"></i>টাকা ফেরত (রিটার্ন)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label fw-semibold small">তারিখ</label>
            <input type="date" class="form-control" id="mrDate" value="<?= today() ?>">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">ফেরতের পরিমাণ (৳) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="mrAmount" min="0.01" step="0.01">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small">কী কারণে ফেরত দেওয়া হচ্ছে (নোট)</label>
            <textarea class="form-control" id="mrReason" rows="2" maxlength="500"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small">কে টাকা গ্রহণ করছে (নাম/পরিচয়)</label>
            <input type="text" class="form-control" id="mrReceivedBy" maxlength="150">
          </div>
        </div>
        <div class="alert alert-danger py-2 mt-2 d-none" id="mrError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-danger" id="btnSaveMoneyReturn" onclick="saveMoneyReturn()">
          <i class="bi bi-check-circle me-1"></i>ফেরত এন্ট্রি করুন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Product return modal ═══ -->
<div class="modal fade" id="productReturnModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="bi bi-arrow-return-left me-2"></i>রিটার্ন পণ্য</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold small">তারিখ</label>
            <input type="date" class="form-control form-control-sm" id="prDate" value="<?= today() ?>">
          </div>
          <div class="col-md-8">
            <label class="form-label fw-semibold small">নোট</label>
            <input type="text" class="form-control form-control-sm" id="prNote" maxlength="500">
          </div>
        </div>

        <div class="card bg-light border mb-2">
          <div class="card-body py-2">
            <div class="row g-2 align-items-end">
              <div class="col-md-5">
                <label class="form-label small fw-semibold">পণ্য</label>
                <select class="form-select form-select-sm" id="prProduct">
                  <option value="">— পণ্য নির্বাচন —</option>
                  <?php foreach ($products as $p): ?>
                  <option value="<?= $p['id'] ?>" data-unit="<?= e($p['unit']) ?>" data-name="<?= e($p['name']) ?>">
                    <?= e($p['name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small fw-semibold">পূর্বের ক্রয় রেট</label>
                <select class="form-select form-select-sm" id="prRate" data-no-search="1">
                  <option value="">— পণ্য নির্বাচন করুন —</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small fw-semibold">পরিমাণ</label>
                <input type="number" class="form-control form-control-sm" id="prQty" min="0.01" step="0.01">
              </div>
              <div class="col-md-2 d-grid">
                <button type="button" class="btn btn-warning btn-sm" onclick="addReturnItem()">
                  <i class="bi bi-plus-lg"></i> যোগ
                </button>
              </div>
            </div>
            <small class="text-muted">এই কাস্টমার পূর্বে যে যে রেটে পণ্যটি কিনেছে সেগুলো দেখাবে — একটি বেছে নিন বা কাস্টম রেট লিখুন।</small>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr><th>পণ্য</th><th class="text-end">পরিমাণ</th><th class="text-end">রেট (৳)</th>
                  <th class="text-end">মোট (৳)</th><th style="width:44px"></th></tr>
            </thead>
            <tbody id="prItemsBody">
              <tr><td colspan="5" class="text-center text-muted py-2">কোনো পণ্য যোগ হয়নি</td></tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-end mt-2">
          <h5 class="fw-bold" id="prTotal">মোট ফেরত: ০.০০ ৳</h5>
        </div>
        <div class="alert alert-danger py-2 mt-2 d-none" id="prError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-warning" id="btnSaveProductReturn" onclick="saveProductReturn()">
          <i class="bi bi-check-circle me-1"></i>রিটার্ন সম্পন্ন করুন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Expense modal ═══ -->
<div class="modal fade" id="expenseModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>অন্যান্য খরচ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label fw-semibold small">তারিখ</label>
            <input type="date" class="form-control" id="eDate" value="<?= today() ?>">
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small">টাকার পরিমাণ (৳) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="eAmount" min="0.01" step="0.01">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small">খরচের বিবরণ (কী কারণে) <span class="text-danger">*</span></label>
            <textarea class="form-control" id="eDescription" rows="2" maxlength="500"
                      placeholder="যেমন: কাস্টমারের পক্ষে বিল পরিশোধ / বাইরে থেকে পণ্য কেনা"></textarea>
          </div>
        </div>
        <div class="alert alert-danger py-2 mt-2 d-none" id="eError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-dark" id="btnSaveExpense" onclick="saveExpense()">
          <i class="bi bi-check-circle me-1"></i>খরচ এন্ট্রি করুন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Agreement modal ═══ -->
<div class="modal fade" id="agreementModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="bi bi-file-earmark-ruled me-2"></i>নতুন চুক্তিপত্র / ডিট (অগ্রিম পণ্য ক্রয়)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-3">
          <div class="col-md-3">
            <label class="form-label fw-semibold small">তারিখ</label>
            <input type="date" class="form-control form-control-sm" id="agDate" value="<?= today() ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">জমাকৃত টাকা (৳)</label>
            <input type="number" class="form-control form-control-sm" id="agDeposit" min="0" step="0.01" value="0">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">জমা পদ্ধতি</label>
            <input type="text" class="form-control form-control-sm" id="agMethod" placeholder="যেমন: ব্যাংক / নগদ" maxlength="100">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">নোট</label>
            <input type="text" class="form-control form-control-sm" id="agNote" maxlength="500">
          </div>
        </div>

        <div class="table-responsive mb-2">
          <table class="table table-bordered table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:200px">পণ্য</th>
                <th style="width:110px">পরিমাণ</th>
                <th style="width:130px">দাম (৳)</th>
                <th style="width:130px" class="text-end">গুণফল (৳)</th>
                <th style="width:44px"></th>
              </tr>
            </thead>
            <tbody id="agItemsBody"></tbody>
          </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-dark mb-3" onclick="addAgRow()">
          <i class="bi bi-plus-lg me-1"></i>পণ্য যোগ করুন
        </button>

        <div class="d-flex justify-content-end">
          <div class="text-end">
            <p class="text-muted small mb-0">সর্বমোট</p>
            <h4 class="fw-bold mb-0" id="agGrandTotal">০.০০ ৳</h4>
            <small class="text-muted" id="agTotalWords"></small>
          </div>
        </div>
        <div class="alert alert-danger py-2 mt-2 d-none" id="agError"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-dark" id="btnSaveAgreement" onclick="saveAgreement()">
          <i class="bi bi-check-circle me-1"></i>চুক্তিপত্র তৈরি করুন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ Agreement view / delivery modal ═══ -->
<div class="modal fade" id="agViewModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-ruled me-2 text-danger"></i>চুক্তিপত্র — <span id="agvNo"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="agvBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ</button>
        <button type="button" class="btn btn-primary" onclick="printAgreement()">
          <i class="bi bi-printer me-1"></i>প্রিন্ট
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL      = '<?= BASE_URL ?>';
const CUSTOMER_ID   = <?= $customerId ?>;
const CUSTOMER_NAME = <?= json_encode($customer['name']) ?>;
const CUSTOMER      = <?= json_encode([
    'name' => $customer['name'], 'phone' => $customer['phone'],
    'address' => $customer['address'], 'account_no' => $customer['account_no'],
    'whatsapp' => $customer['whatsapp'] ?? '',
]) ?>;
const PRODUCTS      = <?= json_encode(array_map(fn($p) => [
    'id' => (int)$p['id'], 'name' => $p['name'], 'unit' => $p['unit'],
    'sell_price' => (float)$p['sell_price'],
], $products)) ?>;
const CAN_WRITE     = <?= $canWrite ? 'true' : 'false' ?>;
const SHOP          = <?= json_encode([
    'name'    => Setting::get('shop_name', APP_NAME),
    'address' => Setting::get('shop_address', ''),
    'phone'   => Setting::get('shop_phone', ''),
]) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/customer_account.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
