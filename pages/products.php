<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Product.php';
requireManagerOrAdmin();

$pageTitle = 'পণ্য ম্যানেজমেন্ট';

$rods    = Product::getProducts('rod');
$cements = Product::getProducts('cement');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">

    <!-- Page Header -->
    <div class="page-header">
        <h5><i class="bi bi-box-seam me-2 text-danger"></i>পণ্য ম্যানেজমেন্ট</h5>
        <button class="btn btn-primary btn-sm" id="btnAddProduct">
            <i class="bi bi-plus-lg me-1"></i> নতুন পণ্য
        </button>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="productTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rodTab">
                <i class="bi bi-bezier2 me-1"></i> রড <span class="badge bg-secondary ms-1"><?= count($rods) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#cementTab">
                <i class="bi bi-bricks me-1"></i> সিমেন্ট <span class="badge bg-secondary ms-1"><?= count($cements) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- ROD TAB -->
        <div class="tab-pane fade show active" id="rodTab">
            <?php renderProductTable($rods, 'rod'); ?>
        </div>
        <!-- CEMENT TAB -->
        <div class="tab-pane fade" id="cementTab">
            <?php renderProductTable($cements, 'cement'); ?>
        </div>
    </div>
</div>

<?php
/** Render a product table for a given list. */
function renderProductTable(array $items, string $type): void {
    $col = $type === 'rod' ? 'সাইজ' : 'ব্র্যান্ড';
?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>পণ্যের নাম</th>
                            <th><?= $col ?></th>
                            <th>ইউনিট</th>
                            <th class="text-end">ক্রয় দাম</th>
                            <th class="text-end">বিক্রয় দাম</th>
                            <th class="text-end">মিনিমাম স্টক</th>
                            <th class="text-center" style="width:120px">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">
                            কোন পণ্য নেই। “নতুন পণ্য” বাটনে ক্লিক করুন।
                        </td></tr>
                    <?php else: foreach ($items as $p): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($p['name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($p['size_brand']) ?></span></td>
                            <td><?= e($p['unit']) ?></td>
                            <td class="text-end"><?= money((float)$p['buy_price']) ?></td>
                            <td class="text-end"><?= money((float)$p['sell_price']) ?></td>
                            <td class="text-end"><?= rtrim(rtrim($p['min_stock'], '0'), '.') ?> <?= e($p['unit']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary btn-edit"
                                        data-id="<?= $p['id'] ?>" title="সম্পাদনা">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-delete"
                                        data-id="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" title="ডিলিট">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } ?>

<!-- ============ PRODUCT MODAL ============ -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="productForm">
                <div class="modal-header">
                    <h6 class="modal-title" id="modalTitle">
                        <i class="bi bi-box-seam me-1 text-danger"></i> নতুন পণ্য
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="productId">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">পণ্যের ধরন <span class="text-danger">*</span></label>
                        <select name="type" id="productType" class="form-select" required>
                            <option value="rod">রড (Rod)</option>
                            <option value="cement">সিমেন্ট (Cement)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">পণ্যের নাম <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="productName" class="form-control"
                               placeholder="যেমন: Steel Rod 12mm / Lafarge Cement" required>
                    </div>

                    <div class="row g-2">
                        <div class="col-7 mb-3">
                            <label class="form-label fw-semibold" id="sizeBrandLabel">সাইজ</label>
                            <input type="text" name="size_brand" id="sizeBrand" class="form-control"
                                   placeholder="8mm / LAFARGE">
                        </div>
                        <div class="col-5 mb-3">
                            <label class="form-label fw-semibold">ইউনিট <span class="text-danger">*</span></label>
                            <select name="unit" id="productUnit" class="form-select" required>
                                <option value="ton">টন (ton)</option>
                                <option value="bag">ব্যাগ (bag)</option>
                                <option value="pcs">পিস (pcs)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">ক্রয় দাম (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="buy_price"
                                   id="buyPrice" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">বিক্রয় দাম (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="sell_price"
                                   id="sellPrice" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold">মিনিমাম স্টক (Alert level)</label>
                        <input type="number" step="0.01" min="0" name="min_stock"
                               id="minStock" class="form-control" value="0">
                        <small class="text-muted">এই পরিমাণের নিচে নামলে সতর্ক দেখাবে।</small>
                    </div>

                    <div class="alert alert-danger py-2 d-none" id="formError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" class="btn btn-primary" id="btnSave">
                        <i class="bi bi-check-lg me-1"></i> সেভ করুন
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/products.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
