<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Stock.php';
require_once __DIR__ . '/../classes/Supplier.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Branch.php';
requireLogin();

$pageTitle    = 'স্টক ম্যানেজমেন্ট';
$_isStaff     = isStaff();
$staffBranch  = getSessionBranchId();
$allStock     = Stock::getAllStock();
$history      = Stock::getStockInbound(null, $_isStaff ? $staffBranch : null);
$suppliers    = Supplier::getSuppliers();
$products     = Product::getProducts();
$branches     = Branch::getBranches();

$lowStock   = array_filter($allStock, fn($r) => $r['min_stock'] > 0 && $r['current_stock'] <= $r['min_stock']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">

    <!-- Page Header -->
    <div class="page-header">
        <h5><i class="bi bi-stack me-2 text-danger"></i>স্টক ম্যানেজমেন্ট</h5>
        <?php if (!$_isStaff): ?>
        <button class="btn btn-primary btn-sm" id="btnAddInbound">
            <i class="bi bi-plus-lg me-1"></i>পণ্য কেনা (Stock In)
        </button>
        <?php endif; ?>
    </div>

    <!-- Low stock banner -->
    <?php if ($lowStock): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        <strong><?= count($lowStock) ?>টি পণ্যের স্টক কম!</strong>
        <?php foreach ($lowStock as $r): ?>
            <span class="badge bg-danger ms-1">
                <?= e($r['product_name']) ?> (<?= rtrim(rtrim($r['current_stock'],'0'),'.') ?> <?= e($r['unit']) ?>)
            </span>
        <?php endforeach; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <?php if (!$_isStaff): ?>
        <li class="nav-item">
            <button class="nav-link <?= $staffBranch ? '' : 'active' ?>" data-bs-toggle="tab" data-bs-target="#currentStockTab">
                <i class="bi bi-boxes me-1"></i>বর্তমান স্টক
                <span class="badge bg-secondary ms-1"><?= count($allStock) ?></span>
            </button>
        </li>
        <?php endif; ?>
        <?php if (!empty($branches)): ?>
        <li class="nav-item">
            <button class="nav-link <?= $_isStaff ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#branchStockTab" id="btnBranchStockTab">
                <i class="bi bi-shop me-1"></i>ব্রাঞ্চ স্টক
            </button>
        </li>
        <?php endif; ?>
        <?php if (!$_isStaff): ?>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#inboundTab">
                <i class="bi bi-arrow-down-circle me-1"></i>ক্রয় ইতিহাস
                <span class="badge bg-secondary ms-1"><?= count($history) ?></span>
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content">

        <!-- ===== CURRENT STOCK TAB ===== -->
        <div class="tab-pane fade show active" id="currentStockTab">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>পণ্যের নাম</th>
                                    <th>ধরন</th>
                                    <th>সাইজ/ব্র্যান্ড</th>
                                    <th class="text-end">বর্তমান স্টক</th>
                                    <th class="text-end">মিনিমাম</th>
                                    <th class="text-end">ক্রয় দাম</th>
                                    <th class="text-end">মোট মূল্য</th>
                                    <th class="text-center">অবস্থা</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($allStock)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">
                                    এখনো কোনো পণ্য যোগ হয়নি।
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($allStock as $r):
                                    $low   = $r['min_stock'] > 0 && $r['current_stock'] <= $r['min_stock'];
                                    $qty   = rtrim(rtrim($r['current_stock'],'0'),'.');
                                    $total = (float)$r['current_stock'] * (float)$r['buy_price'];
                                ?>
                                <tr class="<?= $low ? 'table-danger' : '' ?>">
                                    <td class="fw-semibold"><?= e($r['product_name']) ?></td>
                                    <td>
                                        <span class="badge <?= $r['product_type']==='rod' ? 'bg-primary' : 'bg-warning text-dark' ?>">
                                            <?= $r['product_type']==='rod' ? 'রড' : 'সিমেন্ট' ?>
                                        </span>
                                    </td>
                                    <td><?= e($r['size_brand'] ?? '—') ?></td>
                                    <td class="text-end <?= $low ? 'low-stock' : '' ?>">
                                        <?= $qty ?> <?= e($r['unit']) ?>
                                    </td>
                                    <td class="text-end"><?= rtrim(rtrim($r['min_stock'],'0'),'.') ?> <?= e($r['unit']) ?></td>
                                    <td class="text-end"><?= money((float)$r['buy_price']) ?></td>
                                    <td class="text-end"><?= money($total) ?></td>
                                    <td class="text-center">
                                        <?php if ($low): ?>
                                            <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>কম</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="bi bi-check me-1"></i>ঠিক আছে</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                            <?php if (!empty($allStock)):
                                $grandTotal = array_sum(array_map(fn($r) => $r['current_stock'] * $r['buy_price'], $allStock));
                            ?>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="6" class="text-end fw-bold">মোট স্টক মূল্য:</td>
                                    <td class="text-end fw-bold text-danger"><?= money($grandTotal) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== BRANCH STOCK TAB ===== -->
        <?php if (!empty($branches)): ?>
        <div class="tab-pane fade <?= $_isStaff ? 'show active' : '' ?>" id="branchStockTab">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (!$_isStaff): ?>
                    <div class="row g-2 align-items-center mb-3">
                        <div class="col-auto">
                            <label class="form-label fw-semibold mb-0">ব্রাঞ্চ নির্বাচন করুন:</label>
                        </div>
                        <div class="col-sm-4">
                            <select class="form-select" id="branchStockSelector">
                                <option value="">— ব্রাঞ্চ বেছে নিন —</option>
                                <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <?php $myBranch = array_filter($branches, fn($b) => $b['id'] == $staffBranch); $myBranch = reset($myBranch); ?>
                        <span class="badge bg-secondary fs-6"><i class="bi bi-shop me-1"></i><?= $myBranch ? e($myBranch['name']) : 'আমার ব্রাঞ্চ' ?></span>
                    </div>
                    <?php endif; ?>
                    <div id="branchStockTableWrap" class="table-responsive" style="display:none">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>পণ্যের নাম</th>
                                    <th>ধরন</th>
                                    <th>সাইজ/ব্র্যান্ড</th>
                                    <th class="text-end">মোট আনা</th>
                                    <th class="text-end">মোট বিক্রি</th>
                                    <th class="text-end">বর্তমান স্টক</th>
                                    <th class="text-center">অবস্থা</th>
                                </tr>
                            </thead>
                            <tbody id="branchStockBody"></tbody>
                        </table>
                    </div>
                    <div id="branchStockEmpty" class="text-center text-muted py-5" style="display:none">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                        এই ব্রাঞ্চে কোনো স্টক নেই।
                    </div>
                    <div id="branchStockPrompt" class="text-center text-muted py-5">
                        <i class="bi bi-shop fs-1 d-block mb-2 opacity-25"></i>
                        উপরে থেকে একটি ব্রাঞ্চ নির্বাচন করুন।
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== INBOUND HISTORY TAB ===== -->
        <div class="tab-pane fade" id="inboundTab">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>তারিখ</th>
                                    <th>পণ্য</th>
                                    <th>ব্রাঞ্চ</th>
                                    <th>সাপ্লাইয়ার</th>
                                    <th class="text-end">পরিমাণ</th>
                                    <th class="text-end">ক্রয় দাম</th>
                                    <th class="text-end">মোট খরচ</th>
                                    <th>নোট</th>
                                    <th class="text-center" style="width:100px">অ্যাকশন</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">
                                    এখনো কোনো ক্রয় রেকর্ড নেই।
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($h['inbound_date'])) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($h['product_name']) ?></div>
                                        <small class="text-muted"><?= $h['product_type']==='rod' ? 'রড' : 'সিমেন্ট' ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($h['branch_name'])): ?>
                                            <span class="badge bg-secondary"><i class="bi bi-shop me-1"></i><?= e($h['branch_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($h['supplier_name'] ?? '—') ?></td>
                                    <td class="text-end"><?= rtrim(rtrim($h['quantity'],'0'),'.') ?> <?= e($h['unit']) ?></td>
                                    <td class="text-end"><?= money((float)$h['buy_price']) ?></td>
                                    <td class="text-end fw-semibold"><?= money((float)$h['total_cost']) ?></td>
                                    <td><small class="text-muted"><?= e($h['note'] ?? '') ?></small></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary btn-edit-inbound"
                                                data-id="<?= $h['id'] ?>" title="সম্পাদনা">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-inbound"
                                                data-id="<?= $h['id'] ?>"
                                                data-name="<?= e($h['product_name']) ?>" title="ডিলিট">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php if (!$_isStaff): ?>
<!-- ===== STOCK INBOUND MODAL ===== -->
<div class="modal fade" id="inboundModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="inboundForm">
                <div class="modal-header">
                    <h6 class="modal-title" id="inboundModalTitle">
                        <i class="bi bi-arrow-down-circle me-1 text-danger"></i>পণ্য কেনা (Stock In)
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="inboundId">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">পণ্য <span class="text-danger">*</span></label>
                        <select name="product_id" id="inboundProduct" class="form-select" required>
                            <option value="">— পণ্য নির্বাচন করুন —</option>
                            <?php
                            $rods    = array_filter($products, fn($p) => $p['type']==='rod');
                            $cements = array_filter($products, fn($p) => $p['type']==='cement');
                            if ($rods): ?>
                            <optgroup label="রড">
                                <?php foreach ($rods as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['size_brand']) ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                            <?php if ($cements): ?>
                            <optgroup label="সিমেন্ট">
                                <?php foreach ($cements as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['size_brand']) ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">পরিমাণ <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="quantity"
                                   id="inboundQty" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">ক্রয় দাম (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="buy_price"
                                   id="inboundPrice" class="form-control" required placeholder="0.00">
                        </div>
                    </div>

                    <?php if (!empty($branches)): ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ব্রাঞ্চ <span class="text-danger">*</span></label>
                        <select name="branch_id" id="inboundBranch" class="form-select" required>
                            <option value="">— ব্রাঞ্চ নির্বাচন করুন —</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="row g-2">
                        <div class="col-7 mb-3">
                            <label class="form-label fw-semibold">সাপ্লাইয়ার</label>
                            <select name="supplier_id" id="inboundSupplier" class="form-select">
                                <option value="">— নির্বাচন করুন (ঐচ্ছিক) —</option>
                                <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5 mb-3">
                            <label class="form-label fw-semibold">তারিখ <span class="text-danger">*</span></label>
                            <input type="date" name="inbound_date" id="inboundDate"
                                   class="form-control" value="<?= today() ?>" required>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 mb-3" id="totalPreview" style="display:none">
                        <i class="bi bi-calculator me-1"></i>
                        মোট খরচ: <strong id="totalPreviewAmt"></strong>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold">নোট</label>
                        <input type="text" name="note" id="inboundNote"
                               class="form-control" placeholder="ঐচ্ছিক নোট">
                    </div>

                    <div class="alert alert-danger py-2 d-none" id="inboundError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveInbound">
                        <i class="bi bi-check-lg me-1"></i>সেভ করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const STAFF_BRANCH_ID = <?= $staffBranch ?? 'null' ?>;
const IS_STAFF_VIEW   = <?= $_isStaff ? 'true' : 'false' ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/stock.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
