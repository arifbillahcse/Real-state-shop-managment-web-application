<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Customer.php';
require_once __DIR__ . '/../classes/Branch.php';
requireLogin();

$pageTitle    = 'বিক্রয়';
$_isStaff     = isStaff();
// §৭ নতুন ক্রেতা: creating the account mid-sale, rather than sending the
// seller off to the customers page and back.
$canWriteCustomers = canWriteBranchData();
$staffBranch  = getSessionBranchId();

$customers = Customer::getCustomers();
$branches  = Branch::getVisibleBranches();
$products  = Database::fetchAll(
    'SELECT product_id, product_name, product_type, unit, sell_price, current_stock
     FROM vw_current_stock
     ORDER BY product_type, product_name'
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
<div class="container-fluid py-4">

  <div class="page-header">
    <h4 class="mb-0"><i class="bi bi-cart-check me-2"></i>বিক্রয়</h4>
    <?php if (!$_isStaff): ?>
    <button class="btn btn-primary" id="btnToggleSaleView" type="button" onclick="toggleSaleView()">
      <i class="bi bi-plus-circle me-1"></i>নতুন বিক্রয়
    </button>
    <?php endif; ?>
  </div>

  <div class="tab-content">

    <!-- ===== NEW SALE TAB ===== -->
    <?php if (!$_isStaff): ?>
    <div class="tab-pane fade" id="newSaleTab">
      <form id="saleForm" onsubmit="submitSale(event)">

        <!-- Header row -->
        <div class="row g-3 mb-3">
          <div class="col-md-<?= !empty($branches) ? '4' : '5' ?>">
            <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
              <span>কাস্টমার</span>
              <?php if ($canWriteCustomers): ?>
              <button type="button" class="btn btn-sm btn-outline-primary py-0"
                      onclick="openNewCustomerModal()">
                <i class="bi bi-person-plus me-1"></i>নতুন ক্রেতা
              </button>
              <?php endif; ?>
            </label>
            <select class="form-select" id="saleCustomerId" name="customer_id">
              <option value="">Walk-in Customer (নাম নেই)</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>">
                <?= e($c['name']) ?><?= $c['phone'] ? ' — ' . e($c['phone']) : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (!empty($branches)): ?>
          <div class="col-md-3">
            <label class="form-label fw-semibold">ব্রাঞ্চ <span class="text-danger">*</span></label>
            <select class="form-select" id="saleBranchId" name="branch_id" required>
              <option value="">— ব্রাঞ্চ নির্বাচন করুন —</option>
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="col-md-<?= !empty($branches) ? '2' : '3' ?>">
            <label class="form-label fw-semibold">তারিখ <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="saleDate" name="sale_date"
                   value="<?= today() ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">পেমেন্ট পদ্ধতি</label>
            <select class="form-select" id="paymentMethod" name="payment_method">
              <option value="cash">নগদ (Cash)</option>
              <option value="credit">বাকি (Credit)</option>
              <option value="mobile_banking">মোবাইল ব্যাংকিং</option>
              <option value="cheque">চেক</option>
            </select>
          </div>

          <!-- Who served the customer — kept as free text because the seller
               is often a staff member without a login. -->
          <div class="col-md-3">
            <label class="form-label fw-semibold">বিক্রয়কারী কর্মচারীর নাম</label>
            <input type="text" class="form-control" id="soldByName" name="sold_by_name" maxlength="150">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">কর্মচারীর মোবাইল</label>
            <input type="text" class="form-control" id="soldByMobile" name="sold_by_mobile" maxlength="20">
          </div>

          <!-- Buyer details for a sale with no account behind it. Hidden the
               moment an account is chosen, because that account's own name,
               number and address are what the memo then prints. -->
          <div class="col-12 d-none" id="walkInWrap">
            <div class="card bg-light border">
              <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                  <div class="col-12">
                    <span class="small text-muted">
                      <i class="bi bi-person-lines-fill me-1"></i>ক্রেতার তথ্য (একাউন্ট ছাড়া বিক্রয়) —
                      মেমোতে ছাপা হবে
                    </span>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">কাস্টমারের নাম</label>
                    <input type="text" class="form-control form-control-sm" id="walkinName"
                           name="walkin_name" maxlength="150" placeholder="যেমন: আশিকুর রহমান">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">মোবাইল নাম্বার</label>
                    <input type="text" class="form-control form-control-sm" id="walkinMobile"
                           name="walkin_mobile" maxlength="20">
                  </div>
                  <div class="col-md-5">
                    <label class="form-label small fw-semibold mb-1">ঠিকানা</label>
                    <input type="text" class="form-control form-control-sm" id="walkinAddress"
                           name="walkin_address" maxlength="500">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Items card -->
        <div class="card shadow-sm mb-3">
          <div class="card-header d-flex justify-content-between align-items-center py-2 flex-wrap gap-2">
            <span class="fw-semibold"><i class="bi bi-box-seam me-1"></i>পণ্য তালিকা</span>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div class="btn-group btn-group-sm" role="group" title="লেবার/আনলোড/ভাড়া কীভাবে যুক্ত হবে">
                <input type="radio" class="btn-check" name="saleChargeMode" id="saleChargeCombined" value="combined" checked>
                <label class="btn btn-outline-secondary" for="saleChargeCombined">একত্রে খরচ</label>
                <input type="radio" class="btn-check" name="saleChargeMode" id="saleChargePerItem" value="per_item">
                <label class="btn btn-outline-secondary" for="saleChargePerItem">পণ্যভিত্তিক খরচ</label>
              </div>
              <button type="button" class="btn btn-sm btn-success" onclick="addItemRow()">
                <i class="bi bi-plus-circle me-1"></i>পণ্য যোগ করুন
              </button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="min-width:200px">পণ্য</th>
                  <th style="width:110px">রেট টাইপ</th>
                  <th style="width:100px">পরিমাণ</th>
                  <th style="width:120px">একক মূল্য (৳)</th>
                  <th class="sale-charge-col d-none" style="width:100px">আনলোড</th>
                  <th class="sale-charge-col d-none" style="width:100px">লেবার</th>
                  <th class="sale-charge-col d-none" style="width:100px">ভাড়া</th>
                  <th style="width:120px" class="text-end">মোট (৳)</th>
                  <th style="width:46px"></th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                <!-- dynamic rows -->
              </tbody>
            </table>
          </div>
          <div id="noItemsAlert" class="text-center text-muted py-3 d-none">
            উপরের বাটনে ক্লিক করে পণ্য যোগ করুন
          </div>
          <!-- §৭: স্টকের বেশি পরিমাণ দিলে সতর্কতা -->
          <div id="stockWarning" class="alert alert-danger py-2 small mb-0 mx-3 d-none"></div>
        </div>

        <!-- Combined charges (hidden in per-item mode) -->
        <div class="card shadow-sm mb-3" id="combinedSaleCharges">
          <div class="card-body py-2">
            <div class="row g-2">
              <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">আনলোড বিল (একত্রে)</label>
                <input type="number" class="form-control form-control-sm" id="saleUnload"
                       min="0" step="0.01" value="0" oninput="calcGrandTotal()">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">লেবার বিল (একত্রে)</label>
                <input type="number" class="form-control form-control-sm" id="saleLabor"
                       min="0" step="0.01" value="0" oninput="calcGrandTotal()">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">গাড়িভাড়া (একত্রে)</label>
                <input type="number" class="form-control form-control-sm" id="saleTransport"
                       min="0" step="0.01" value="0" oninput="calcGrandTotal()">
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">ডেলিভারি চার্জ</label>
                <input type="number" class="form-control form-control-sm" id="saleDelivery"
                       min="0" step="0.01" value="0" oninput="calcGrandTotal()">
              </div>
            </div>
          </div>
        </div>

        <!-- Totals + submit -->
        <div class="row justify-content-end">
          <div class="col-lg-6 col-md-8">
            <table class="table table-sm table-borderless">
              <tr>
                <td class="text-muted">সাবটোটাল</td>
                <td class="text-end fw-semibold" id="subtotalDisplay">০.০০ ৳</td>
              </tr>
              <tr>
                <td class="text-muted">খরচ (আনলোড/লেবার/ভাড়া/ডেলিভারি)</td>
                <td class="text-end fw-semibold" id="chargesDisplay">০.০০ ৳</td>
              </tr>
              <tr>
                <td class="text-muted align-middle">
                  ছাড়
                  <div class="btn-group btn-group-sm ms-1" role="group">
                    <input type="radio" class="btn-check" name="discountType" id="discTaka" value="amount" checked>
                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" for="discTaka">৳</label>
                    <input type="radio" class="btn-check" name="discountType" id="discPercent" value="percent">
                    <label class="btn btn-outline-secondary btn-sm py-0 px-2" for="discPercent">%</label>
                  </div>
                </td>
                <td class="text-end">
                  <input type="number" class="form-control form-control-sm text-end ms-auto"
                         style="width:130px" id="discount" name="discount"
                         value="0" min="0" step="0.01" oninput="calcGrandTotal()">
                  <small class="text-muted" id="discountCalcHint"></small>
                </td>
              </tr>
              <tr>
                <td class="text-muted align-middle small">ছাড়ের কারণ (নোট)</td>
                <td class="text-end">
                  <input type="text" class="form-control form-control-sm" id="discountNote"
                         maxlength="300" placeholder="ঐচ্ছিক">
                </td>
              </tr>
              <tr class="table-dark">
                <td class="fw-bold">মোট</td>
                <td class="text-end fw-bold fs-6" id="totalDisplay">০.০০ ৳</td>
              </tr>
              <tr>
                <td colspan="2" class="text-end text-muted small" id="totalInWords"></td>
              </tr>
              <tr>
                <td class="text-muted align-middle">নগদ প্রদান (৳)</td>
                <td class="text-end">
                  <input type="number" class="form-control form-control-sm text-end ms-auto"
                         style="width:130px" id="paidAmount" name="paid_amount"
                         value="0" min="0" step="0.01" oninput="calcGrandTotal()">
                </td>
              </tr>
              <tr class="table-warning">
                <td class="fw-semibold">বাকি</td>
                <td class="text-end fw-bold text-danger" id="dueDisplay">০.০০ ৳</td>
              </tr>
              <tr id="prevDueRow" class="d-none">
                <td class="text-muted">পূর্বের বাকি</td>
                <td class="text-end" id="prevDueDisplay">০.০০ ৳</td>
              </tr>
              <tr id="grandDueRow" class="d-none table-danger">
                <td class="fw-bold">সর্বমোট দেয় (পূর্বের সহ)</td>
                <td class="text-end fw-bold" id="grandDueDisplay">০.০০ ৳</td>
              </tr>
            </table>
            <div class="form-check mb-2" id="prevDueWrap">
              <input class="form-check-input" type="checkbox" id="includePrevDue" onchange="calcGrandTotal()">
              <label class="form-check-label small" for="includePrevDue">
                পূর্বের বাকি এই মেমোতে দেখান
                <span class="text-muted d-block" style="font-size:.8em">
                  শুধু মেমোতে দেখাবে — পুরনো বিলের হিসাব দুবার যোগ হবে না।
                </span>
              </label>
            </div>
            <!-- §৭: "চাইলে মেমোটি তার মূল একাউন্টের লেজারে যুক্ত করা যাবে" -->
            <div class="form-check mb-2 d-none" id="addToLedgerWrap">
              <input class="form-check-input" type="checkbox" id="addToLedger">
              <label class="form-check-label small" for="addToLedger">
                মেমোটি কাস্টমারের খাতায় যুক্ত করুন
                <span class="text-muted d-block" style="font-size:.8em">
                  খাতায় গেলে বাকিটা সেখান থেকেই হিসাব হবে, আর মেমোটি পরে এডিট/বাতিল করা যাবে না।
                </span>
              </label>
            </div>
            <div id="dueLimitWarning" class="alert alert-warning py-2 d-none small mb-2"></div>
            <div class="mb-3">
              <label class="form-label text-muted small">নোট</label>
              <textarea class="form-control form-control-sm" id="saleNote" name="note"
                        rows="2" maxlength="500"></textarea>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary btn-lg" id="submitSaleBtn">
                <i class="bi bi-check-circle me-2"></i>বিক্রয় সম্পন্ন করুন
              </button>
            </div>
          </div>
        </div>

      </form>
    </div><!-- /newSaleTab -->
    <?php endif; ?>

    <!-- ===== HISTORY TAB ===== -->
    <div class="tab-pane fade show active" id="historyTab">

      <!-- Filters -->
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
              <label class="form-label small text-muted mb-1">তারিখ থেকে</label>
              <input type="date" class="form-control form-control-sm" id="filterDateFrom">
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label small text-muted mb-1">তারিখ পর্যন্ত</label>
              <input type="date" class="form-control form-control-sm" id="filterDateTo">
            </div>
            <div class="col-12 col-md-2">
              <label class="form-label small text-muted mb-1">কাস্টমার</label>
              <select class="form-select form-select-sm" id="filterCustomer">
                <option value="">সকল কাস্টমার</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (!empty($branches)): ?>
            <div class="col-12 col-md-2">
              <label class="form-label small text-muted mb-1">ব্রাঞ্চ</label>
              <select class="form-select form-select-sm" id="filterBranch">
                <option value="">সকল ব্রাঞ্চ</option>
                <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="col-8 col-md-2">
              <label class="form-label small text-muted mb-1">স্ট্যাটাস</label>
              <select class="form-select form-select-sm" id="filterStatus">
                <option value="">সকল</option>
                <option value="completed">সম্পন্ন</option>
                <option value="cancelled">বাতিল</option>
              </select>
            </div>
            <div class="col-4 col-md-1">
              <button class="btn btn-primary btn-sm w-100" onclick="loadSalesHistory()">
                <i class="bi bi-search"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sales table -->
      <div class="card shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-dark">
              <tr>
                <th>ইনভয়েস</th>
                <th>তারিখ</th>
                <th>কাস্টমার</th>
                <?php if (!empty($branches)): ?>
                <th>ব্রাঞ্চ</th>
                <?php endif; ?>
                <th class="text-center">পণ্য</th>
                <th class="text-end">মোট</th>
                <th class="text-end">পরিশোধ</th>
                <th class="text-end">বাকি</th>
                <th class="text-center">স্ট্যাটাস</th>
                <th class="text-center">একশন</th>
              </tr>
            </thead>
            <tbody id="salesBody">
              <tr>
                <td colspan="9" class="text-center py-5 text-muted">
                  ইতিহাস ট্যাবে ক্লিক করলে লোড হবে
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top" id="salesPaginationBar" style="display:none!important">
          <small class="text-muted" id="salesPageInfo"></small>
          <nav><ul class="pagination pagination-sm mb-0" id="salesPagination"></ul></nav>
        </div>
      </div>
    </div><!-- /historyTab -->

  </div><!-- /tab-content -->
</div>
</div>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>ইনভয়েস</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4" id="invoiceContent">
        <div class="text-center py-4">
          <div class="spinner-border text-primary"></div>
        </div>
      </div>
      <div class="modal-footer flex-wrap">
        <button type="button" class="btn btn-success" id="btnShareWhatsApp" onclick="shareInvoice('whatsapp')">
          <i class="bi bi-whatsapp me-1"></i>WhatsApp
        </button>
        <button type="button" class="btn btn-info text-white" id="btnShareImo" onclick="shareInvoice('imo')">
          <i class="bi bi-share me-1"></i>Imo
        </button>
        <button type="button" class="btn btn-info text-white" id="btnShareSms" onclick="shareInvoice('sms')">
          <i class="bi bi-chat-dots me-1"></i>SMS
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
        <?php if (canWriteBranchData()): ?>
        <button type="button" class="btn btn-warning" id="btnEditInvoice" onclick="openEditSale()">
          <i class="bi bi-pencil-square me-1"></i>সম্পাদনা
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-primary" onclick="printInvoice()">
          <i class="bi bi-printer me-1"></i>প্রিন্ট করুন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- New buyer (§৭: নতুন ক্রেতা → ফুল / শর্ট একাউন্ট) -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>নতুন ক্রেতা</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="btn-group w-100 mb-3" role="group">
          <input type="radio" class="btn-check" name="ncType" id="ncTypeFull" value="full" checked>
          <label class="btn btn-outline-primary" for="ncTypeFull">
            ফুল একাউন্ট
          </label>
          <input type="radio" class="btn-check" name="ncType" id="ncTypeShort" value="short">
          <label class="btn btn-outline-primary" for="ncTypeShort">
            শর্ট একাউন্ট
          </label>
        </div>

        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small fw-semibold">নাম <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="ncName" maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">মোবাইল নাম্বার <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="ncPhone" maxlength="20">
          </div>
          <div class="col-md-8">
            <label class="form-label small fw-semibold">ঠিকানা <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="ncAddress" maxlength="500">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">বই নাম্বার <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="ncBookNo" maxlength="20">
            <small class="text-muted">একাউন্ট নাম্বার সিরিয়াল অনুযায়ী অটো হবে</small>
          </div>

          <!-- Full account carries the extra profile fields; a short account
               is deliberately just the four above. -->
          <div class="col-12 nc-full-only">
            <hr class="my-2">
          </div>
          <div class="col-md-4 nc-full-only">
            <label class="form-label small fw-semibold">WhatsApp নাম্বার</label>
            <input type="text" class="form-control form-control-sm" id="ncWhatsapp" maxlength="20">
          </div>
          <div class="col-md-4 nc-full-only">
            <label class="form-label small fw-semibold">Imo নাম্বার</label>
            <input type="text" class="form-control form-control-sm" id="ncImo" maxlength="20">
          </div>
          <div class="col-md-4 nc-full-only">
            <label class="form-label small fw-semibold">বাকির সীমা (৳)</label>
            <input type="number" class="form-control form-control-sm" id="ncDueLimit"
                   min="0" step="0.01" value="0">
            <small class="text-muted">০ = সীমা নেই</small>
          </div>
          <div class="col-12 nc-full-only">
            <div class="alert alert-info py-2 small mb-0">
              <i class="bi bi-info-circle me-1"></i>ছবি ও রেফারেন্স পরে কাস্টমার একাউন্ট পেজ
              থেকে যুক্ত করা যাবে।
            </div>
          </div>
          <div class="col-12 nc-short-only d-none">
            <div class="alert alert-warning py-2 small mb-0">
              <i class="bi bi-info-circle me-1"></i>শর্ট একাউন্ট পরে যেকোনো সময় ফুল একাউন্টে
              রূপান্তর করা যাবে।
            </div>
          </div>
        </div>

        <div id="ncError" class="alert alert-danger py-2 small mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-primary" id="btnSaveNewCustomer" onclick="saveNewCustomer()">
          <i class="bi bi-check-lg me-1"></i>তৈরি করে বিক্রয়ে যুক্ত করুন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Manager Approval Modal (over-limit credit sale) -->
<div class="modal fade" id="approvalModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning">
        <h6 class="modal-title"><i class="bi bi-shield-lock me-1"></i>ম্যানেজার অনুমোদন</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="small mb-2" id="approvalInfo"></p>
        <label class="form-label small fw-semibold">ম্যানেজার ইউজারনেম</label>
        <input type="text" class="form-control form-control-sm mb-2" id="approverUsername" autocomplete="off">
        <label class="form-label small fw-semibold">পাসওয়ার্ড</label>
        <input type="password" class="form-control form-control-sm" id="approverPassword" autocomplete="new-password">
        <div class="alert alert-danger py-1 px-2 small mt-2 d-none" id="approvalError"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">বাতিল</button>
        <button type="button" class="btn btn-warning btn-sm" id="btnConfirmApproval" onclick="confirmApproval()">
          <i class="bi bi-check-lg me-1"></i>অনুমোদন দিন
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Sale Modal -->
<div class="modal fade" id="editSaleModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>বিক্রয় সম্পাদনা</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editSaleForm" onsubmit="submitEditSale(event)">
          <input type="hidden" id="esSaleId">
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">কাস্টমার</label>
              <select class="form-select" id="esCustomer">
                <option value="0">Walk-in / অজ্ঞাত</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">বিক্রয়ের তারিখ</label>
              <input type="date" class="form-control" id="esSaleDate">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">পেমেন্ট পদ্ধতি</label>
              <select class="form-select" id="esPayMethod">
                <option value="cash">নগদ</option>
                <option value="credit">বাকি</option>
                <option value="mobile_banking">মোবাইল ব্যাংকিং</option>
                <option value="cheque">চেক</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label fw-semibold">ছাড় (৳)</label>
              <input type="number" class="form-control" id="esDiscount" min="0" step="0.01" oninput="calcEditSaleTotal()">
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label fw-semibold">পরিশোধ (৳)</label>
              <input type="number" class="form-control" id="esPaid" min="0" step="0.01">
            </div>
            <div class="col-md-9">
              <label class="form-label fw-semibold">নোট</label>
              <input type="text" class="form-control" id="esNote" maxlength="500">
            </div>
          </div>

          <!-- Items -->
          <div class="table-responsive mb-2">
            <table class="table table-bordered table-sm">
              <thead class="table-dark">
                <tr>
                  <th style="min-width:200px">পণ্য</th>
                  <th style="width:100px">পরিমাণ</th>
                  <th style="width:130px">ইউনিট মূল্য (৳)</th>
                  <th style="width:130px">মোট (৳)</th>
                  <th style="width:50px"></th>
                </tr>
              </thead>
              <tbody id="editSaleItemsBody"></tbody>
              <tfoot>
                <tr>
                  <td colspan="5">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addEditSaleRow()">
                      <i class="bi bi-plus-lg me-1"></i>পণ্য যোগ করুন
                    </button>
                  </td>
                </tr>
                <tr class="table-light fw-bold">
                  <td colspan="3" class="text-end">সাবটোটাল:</td>
                  <td id="esSubtotal">০.০০ ৳</td><td></td>
                </tr>
                <tr class="table-light fw-bold">
                  <td colspan="3" class="text-end">মোট:</td>
                  <td id="esTotal" class="text-success">০.০০ ৳</td><td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
        <button type="submit" form="editSaleForm" class="btn btn-warning" id="editSaleSaveBtn">
          <i class="bi bi-check-circle me-1"></i>আপডেট করুন
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL      = '<?= BASE_URL ?>';
const CURRENT_USER_NAME = <?= json_encode($_SESSION['user_name'] ?? '') ?>;
const IS_ADMIN      = <?= canWriteBranchData() ? 'true' : 'false' ?>;
const IS_STAFF      = <?= $_isStaff ? 'true' : 'false' ?>;
const STAFF_BRANCH  = <?= $staffBranch ?? 'null' ?>;
const PRODUCTS      = <?= json_encode(array_values($products)) ?>;
const BRANCHES      = <?= json_encode(array_values($branches)) ?>;
const HAS_BRANCHES  = <?= !empty($branches) ? 'true' : 'false' ?>;
const CUSTOMERS     = <?= json_encode(array_values($customers)) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/sales.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
