<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Stock.php';
require_once __DIR__ . '/../classes/Supplier.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Branch.php';
requireLogin();

$pageTitle   = 'স্টক ম্যানেজমেন্ট';
$_isStaff    = isStaff();
$staffBranch = getSessionBranchId();
$allStock    = Stock::getAllStock();
$history     = Stock::getStockInbound(null, $_isStaff ? $staffBranch : null);
$suppliers   = Supplier::getSuppliers();
$products    = Product::getProducts();
$branches    = Branch::getBranches();

$lowStock = array_filter($allStock, fn($r) => $r['min_stock'] > 0 && $r['current_stock'] <= $r['min_stock']);

// Group products by category for modal selects
$productsByType = [];
foreach ($products as $p) { $productsByType[$p['category_name']][] = $p; }

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">

    <!-- Page Header -->
    <div class="page-header">
        <h5><i class="bi bi-stack me-2 text-danger"></i>স্টক ম্যানেজমেন্ট</h5>
        <?php if (!$_isStaff): ?>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary btn-sm" id="btnAddInbound">
                <i class="bi bi-plus-lg me-1"></i>পণ্য কেনা
            </button>
            <button class="btn btn-warning btn-sm" id="btnAdjustStock">
                <i class="bi bi-sliders me-1"></i>স্টক সংশোধন
            </button>
            <?php if (!empty($branches)): ?>
            <button class="btn btn-info btn-sm text-white" id="btnTransferStock">
                <i class="bi bi-arrow-left-right me-1"></i>ব্রাঞ্চ ট্রান্সফার
            </button>
            <?php endif; ?>
        </div>
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
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#currentStockTab">
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
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#adjustTab" id="btnAdjustTab">
                <i class="bi bi-sliders me-1"></i>সংশোধন ইতিহাস
            </button>
        </li>
        <?php if (!empty($branches)): ?>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#transferTab" id="btnTransferTab">
                <i class="bi bi-arrow-left-right me-1"></i>ট্রান্সফার ইতিহাস
            </button>
        </li>
        <?php endif; ?>
        <?php endif; ?>
    </ul>

    <div class="tab-content">

        <!-- ===== CURRENT STOCK TAB ===== -->
        <?php if (!$_isStaff): ?>
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
                                    <th class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($allStock)): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">এখনো কোনো পণ্য নেই।</td></tr>
                            <?php else: ?>
                                <?php foreach ($allStock as $r):
                                    $low   = $r['min_stock'] > 0 && $r['current_stock'] <= $r['min_stock'];
                                    $qty   = rtrim(rtrim($r['current_stock'],'0'),'.');
                                    $total = (float)$r['current_stock'] * (float)$r['buy_price'];
                                ?>
                                <tr class="<?= $low ? 'table-danger' : '' ?>">
                                    <td class="fw-semibold"><?= e($r['product_name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= e($r['product_type']) ?></span></td>
                                    <td><?= e($r['size_brand'] ?? '—') ?></td>
                                    <td class="text-end <?= $low ? 'low-stock' : '' ?>"><?= $qty ?> <?= e($r['unit']) ?></td>
                                    <td class="text-end"><?= rtrim(rtrim($r['min_stock'],'0'),'.') ?> <?= e($r['unit']) ?></td>
                                    <td class="text-end"><?= money((float)$r['buy_price']) ?></td>
                                    <td class="text-end"><?= money($total) ?></td>
                                    <td class="text-center">
                                        <?= $low
                                            ? '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>কম</span>'
                                            : '<span class="badge bg-success"><i class="bi bi-check me-1"></i>ঠিক আছে</span>' ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-warning"
                                                onclick="openAdjustFor(<?= $r['product_id'] ?>)"
                                                title="স্টক সংশোধন">
                                            <i class="bi bi-sliders"></i>
                                        </button>
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
                                    <td></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== BRANCH STOCK TAB (ENHANCED) ===== -->
        <?php if (!empty($branches)): ?>
        <div class="tab-pane fade <?= $_isStaff ? 'show active' : '' ?>" id="branchStockTab">

            <?php if (!$_isStaff): ?>
            <!-- Sub-nav for admin: comparison vs individual -->
            <ul class="nav nav-pills mb-3" id="branchSubNav">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#branchComparePane" id="btnBranchCompare">
                        <i class="bi bi-grid me-1"></i>ব্রাঞ্চ তুলনা
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#branchSinglePane">
                        <i class="bi bi-shop me-1"></i>আলাদা ব্রাঞ্চ
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Comparison pane -->
                <div class="tab-pane fade show active" id="branchComparePane">
                    <!-- Summary cards -->
                    <div id="branchSummaryCards" class="row g-2 mb-3"></div>
                    <!-- Comparison matrix -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <div id="branchCompareLoading" class="text-center py-5 text-muted">
                                    <div class="spinner-border spinner-border-sm me-2"></div>লোড হচ্ছে...
                                </div>
                                <table class="table table-hover align-middle mb-0 d-none" id="branchCompareTable">
                                    <thead id="branchCompareHead" class="table-dark"></thead>
                                    <tbody id="branchCompareBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Individual branch pane -->
                <div class="tab-pane fade" id="branchSinglePane">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
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
                            <div id="branchStockTableWrap" class="table-responsive" style="display:none">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>পণ্যের নাম</th><th>ধরন</th><th>সাইজ/ব্র্যান্ড</th>
                                            <th class="text-end">মোট আনা</th>
                                            <th class="text-end">সংশোধন</th>
                                            <th class="text-end">ট্রান্সফার ইন</th>
                                            <th class="text-end">ট্রান্সফার আউট</th>
                                            <th class="text-end">মোট বিক্রি</th>
                                            <th class="text-end">বর্তমান স্টক</th>
                                            <th class="text-center">অবস্থা</th>
                                            <?php if (!$_isStaff): ?><th class="text-center">একশন</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody id="branchStockBody"></tbody>
                                </table>
                            </div>
                            <div id="branchStockEmpty" class="text-center text-muted py-5" style="display:none">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>এই ব্রাঞ্চে কোনো স্টক নেই।
                            </div>
                            <div id="branchStockPrompt" class="text-center text-muted py-5">
                                <i class="bi bi-shop fs-1 d-block mb-2 opacity-25"></i>উপরে থেকে একটি ব্রাঞ্চ নির্বাচন করুন।
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: /* staff view */ ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php $myBranch = array_filter($branches, fn($b) => $b['id'] == $staffBranch); $myBranch = reset($myBranch); ?>
                    <div class="mb-3">
                        <span class="badge bg-secondary fs-6"><i class="bi bi-shop me-1"></i><?= $myBranch ? e($myBranch['name']) : 'আমার ব্রাঞ্চ' ?></span>
                    </div>
                    <div id="branchStockTableWrap" class="table-responsive" style="display:none">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>পণ্যের নাম</th><th>ধরন</th><th>সাইজ/ব্র্যান্ড</th>
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
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>এই ব্রাঞ্চে কোনো স্টক নেই।
                    </div>
                    <div id="branchStockPrompt" class="text-center text-muted py-5">
                        <i class="bi bi-shop fs-1 d-block mb-2 opacity-25"></i>লোড হচ্ছে...
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ===== INBOUND HISTORY TAB ===== -->
        <?php if (!$_isStaff): ?>
        <div class="tab-pane fade" id="inboundTab">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>তারিখ</th><th>পণ্য</th><th>ব্রাঞ্চ</th><th>সাপ্লাইয়ার</th>
                                    <th class="text-end">পরিমাণ</th>
                                    <th class="text-end">ক্রয় দাম</th>
                                    <th class="text-end">মোট খরচ</th>
                                    <th>নোট</th>
                                    <th class="text-center" style="width:100px">অ্যাকশন</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">এখনো কোনো ক্রয় রেকর্ড নেই।</td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($h['inbound_date'])) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($h['product_name']) ?></div>
                                        <small class="text-muted"><?= e($h['product_type']) ?></small>
                                    </td>
                                    <td><?= !empty($h['branch_name']) ? '<span class="badge bg-secondary"><i class="bi bi-shop me-1"></i>'.e($h['branch_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
                                    <td><?= e($h['supplier_name'] ?? '—') ?></td>
                                    <td class="text-end"><?= rtrim(rtrim($h['quantity'],'0'),'.') ?> <?= e($h['unit']) ?></td>
                                    <td class="text-end"><?= money((float)$h['buy_price']) ?></td>
                                    <td class="text-end fw-semibold"><?= money((float)$h['total_cost']) ?></td>
                                    <td><small class="text-muted"><?= e($h['note'] ?? '') ?></small></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary btn-edit-inbound" data-id="<?= $h['id'] ?>"><i class="bi bi-pencil"></i></button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-inbound" data-id="<?= $h['id'] ?>" data-name="<?= e($h['product_name']) ?>"><i class="bi bi-trash"></i></button>
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

        <!-- ===== ADJUSTMENT HISTORY TAB ===== -->
        <div class="tab-pane fade" id="adjustTab">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>তারিখ</th><th>পণ্য</th><th>ব্রাঞ্চ</th>
                                    <th class="text-end">পরিমাণ</th>
                                    <th>কারণ</th><th>নোট</th><th>করেছেন</th>
                                    <th class="text-center">একশন</th>
                                </tr>
                            </thead>
                            <tbody id="adjustBody">
                                <tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>লোড হচ্ছে...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== TRANSFER HISTORY TAB ===== -->
        <?php if (!empty($branches)): ?>
        <div class="tab-pane fade" id="transferTab">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>তারিখ</th><th>পণ্য</th>
                                    <th>উৎস ব্রাঞ্চ</th>
                                    <th>গন্তব্য ব্রাঞ্চ</th>
                                    <th class="text-end">পরিমাণ</th>
                                    <th>নোট</th><th>করেছেন</th>
                                    <th class="text-center">একশন</th>
                                </tr>
                            </thead>
                            <tbody id="transferBody">
                                <tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>লোড হচ্ছে...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; /* !$_isStaff */ ?>

    </div><!-- /tab-content -->
</div><!-- /main-content -->

<?php if (!$_isStaff): ?>

<!-- ===== STOCK IN MODAL ===== -->
<div class="modal fade" id="inboundModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="inboundForm">
                <div class="modal-header">
                    <h6 class="modal-title" id="inboundModalTitle"><i class="bi bi-arrow-down-circle me-1 text-danger"></i>পণ্য কেনা (Stock In)</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="inboundId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">পণ্য <span class="text-danger">*</span></label>
                        <select name="product_id" id="inboundProduct" class="form-select" required>
                            <option value="">— পণ্য নির্বাচন করুন —</option>
                            <?php foreach ($productsByType as $type => $list): ?>
                            <optgroup label="<?= e($type) ?>">
                                <?php foreach ($list as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['size_brand']) ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">পরিমাণ <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="quantity" id="inboundQty" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">ক্রয় দাম (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="buy_price" id="inboundPrice" class="form-control" required placeholder="0.00">
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
                                <option value="">— ঐচ্ছিক —</option>
                                <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5 mb-3">
                            <label class="form-label fw-semibold">তারিখ <span class="text-danger">*</span></label>
                            <input type="date" name="inbound_date" id="inboundDate" class="form-control" value="<?= today() ?>" required>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 mb-3" id="totalPreview" style="display:none">
                        <i class="bi bi-calculator me-1"></i>মোট খরচ: <strong id="totalPreviewAmt"></strong>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">নোট</label>
                        <input type="text" name="note" id="inboundNote" class="form-control" placeholder="ঐচ্ছিক নোট">
                    </div>
                    <div class="alert alert-danger py-2 d-none" id="inboundError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveInbound"><i class="bi bi-check-lg me-1"></i>সেভ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== ADJUSTMENT MODAL ===== -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h6 class="modal-title" id="adjModalTitle"><i class="bi bi-sliders me-1"></i>স্টক সংশোধন</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">পণ্য <span class="text-danger">*</span></label>
                    <select id="adjProduct" class="form-select" required>
                        <option value="">— পণ্য নির্বাচন করুন —</option>
                        <?php foreach ($productsByType as $type => $list): ?>
                        <optgroup label="<?= $type === 'rod' ? 'রড' : 'সিমেন্ট' ?>">
                            <?php foreach ($list as $p): ?>
                            <option value="<?= $p['id'] ?>" data-unit="<?= e($p['unit']) ?>"><?= e($p['name']) ?> (<?= e($p['size_brand']) ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($branches)): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">ব্রাঞ্চ (ঐচ্ছিক)</label>
                    <select id="adjBranch" class="form-select">
                        <option value="">— গ্লোবাল (কোনো ব্রাঞ্চ নয়) —</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">ধরন <span class="text-danger">*</span></label>
                    <div class="d-flex gap-2">
                        <div class="form-check form-check-inline flex-fill">
                            <input class="form-check-input" type="radio" name="adjDir" id="adjAdd" value="add" checked>
                            <label class="form-check-label text-success fw-semibold" for="adjAdd"><i class="bi bi-plus-circle me-1"></i>বাড়ানো (+)</label>
                        </div>
                        <div class="form-check form-check-inline flex-fill">
                            <input class="form-check-input" type="radio" name="adjDir" id="adjSub" value="subtract">
                            <label class="form-check-label text-danger fw-semibold" for="adjSub"><i class="bi bi-dash-circle me-1"></i>কমানো (−)</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">পরিমাণ <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="adjQty" class="form-control" placeholder="0.00" required>
                    <div class="form-text" id="adjCurrentStock"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">কারণ <span class="text-danger">*</span></label>
                    <select id="adjReason" class="form-select">
                        <option value="count_correction">গণনা সংশোধন</option>
                        <option value="damage">ক্ষতিগ্রস্ত / নষ্ট</option>
                        <option value="return">রিটার্ন</option>
                        <option value="other">অন্যান্য</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">নোট</label>
                    <input type="text" id="adjNote" class="form-control" placeholder="ঐচ্ছিক নোট">
                </div>
                <div class="alert alert-danger py-2 d-none" id="adjError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn btn-warning" id="btnSaveAdj"><i class="bi bi-check-lg me-1"></i>সংশোধন করুন</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== TRANSFER MODAL ===== -->
<?php if (!empty($branches)): ?>
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h6 class="modal-title" id="trfModalTitle"><i class="bi bi-arrow-left-right me-1"></i>ব্রাঞ্চ ট্রান্সফার</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">পণ্য <span class="text-danger">*</span></label>
                    <select id="trfProduct" class="form-select" required>
                        <option value="">— পণ্য নির্বাচন করুন —</option>
                        <?php foreach ($productsByType as $type => $list): ?>
                        <optgroup label="<?= $type === 'rod' ? 'রড' : 'সিমেন্ট' ?>">
                            <?php foreach ($list as $p): ?>
                            <option value="<?= $p['id'] ?>" data-unit="<?= e($p['unit']) ?>"><?= e($p['name']) ?> (<?= e($p['size_brand']) ?>)</option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">উৎস ব্রাঞ্চ <span class="text-danger">*</span></label>
                        <select id="trfFrom" class="form-select" required>
                            <option value="">— বেছে নিন —</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-info" id="trfFromStock"></div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">গন্তব্য ব্রাঞ্চ <span class="text-danger">*</span></label>
                        <select id="trfTo" class="form-select" required>
                            <option value="">— বেছে নিন —</option>
                            <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">পরিমাণ <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0.01" id="trfQty" class="form-control" placeholder="0.00" required>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">নোট</label>
                    <input type="text" id="trfNote" class="form-control" placeholder="ঐচ্ছিক নোট">
                </div>
                <div class="alert alert-danger py-2 d-none" id="trfError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn btn-info text-white" id="btnSaveTrf"><i class="bi bi-check-lg me-1"></i>ট্রান্সফার করুন</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; /* !$_isStaff */ ?>

<script>
const STAFF_BRANCH_ID = <?= $staffBranch ?? 'null' ?>;
const IS_STAFF_VIEW   = <?= $_isStaff ? 'true' : 'false' ?>;
const HAS_BRANCHES    = <?= !empty($branches) ? 'true' : 'false' ?>;
const CAN_WRITE       = <?= (!$_isStaff && User::isAdminOrManager()) ? 'true' : 'false' ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/stock.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
