<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Category.php';
requireManagerOrAdmin();

$pageTitle  = 'পণ্য ম্যানেজমেন্ট';
$categories = Category::getAll();

// Build products grouped by category
$productsByCategory = [];
foreach ($categories as $cat) {
    $productsByCategory[$cat['id']] = Product::getProducts((int)$cat['id']);
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">

    <!-- Page Header -->
    <div class="page-header">
        <h5><i class="bi bi-box-seam me-2 text-danger"></i>পণ্য ম্যানেজমেন্ট</h5>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="btnManageCategories"
                    data-bs-toggle="modal" data-bs-target="#categoryModal">
                <i class="bi bi-tags me-1"></i> ক্যাটাগরি
            </button>
            <button class="btn btn-primary btn-sm" id="btnAddProduct">
                <i class="bi bi-plus-lg me-1"></i> নতুন পণ্য
            </button>
        </div>
    </div>

    <?php if (empty($categories)): ?>
    <div class="alert alert-info">
        কোনো ক্যাটাগরি নেই। উপরের <strong>ক্যাটাগরি</strong> বাটন থেকে প্রথমে ক্যাটাগরি যোগ করুন।
    </div>
    <?php else: ?>

    <!-- Category Tabs -->
    <ul class="nav nav-tabs mb-3" id="productTabs">
        <?php foreach ($categories as $i => $cat): ?>
        <li class="nav-item">
            <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                    data-bs-toggle="tab"
                    data-bs-target="#catTab<?= $cat['id'] ?>">
                <i class="bi bi-tag me-1"></i>
                <?= e($cat['name']) ?>
                <span class="badge bg-secondary ms-1"><?= count($productsByCategory[$cat['id']]) ?></span>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach ($categories as $i => $cat): ?>
        <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="catTab<?= $cat['id'] ?>">
            <?php renderProductTable($productsByCategory[$cat['id']]); ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php
function renderProductTable(array $items): void { ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>পণ্যের নাম</th>
                            <th>সাইজ / ব্র্যান্ড</th>
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
                            কোন পণ্য নেই। "নতুন পণ্য" বাটনে ক্লিক করুন।
                        </td></tr>
                    <?php else: foreach ($items as $p): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($p['name']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($p['size_brand'] ?: '—') ?></span></td>
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
                        <select name="category_id" id="productCategory" class="form-select" required>
                            <option value="">— ক্যাটাগরি নির্বাচন করুন —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">পণ্যের নাম <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="productName" class="form-control"
                               placeholder="যেমন: Steel Rod 12mm / Lafarge Cement" required>
                    </div>

                    <div class="row g-2">
                        <div class="col-7 mb-3">
                            <label class="form-label fw-semibold">সাইজ / ব্র্যান্ড</label>
                            <input type="text" name="size_brand" id="sizeBrand" class="form-control"
                                   placeholder="যেমন: 12mm / LAFARGE / 60×60cm">
                        </div>
                        <div class="col-5 mb-3">
                            <label class="form-label fw-semibold">ইউনিট <span class="text-danger">*</span></label>
                            <select name="unit" id="productUnit" class="form-select" required>
                                <option value="ton">টন (ton)</option>
                                <option value="bag">ব্যাগ (bag)</option>
                                <option value="pcs">পিস (pcs)</option>
                                <option value="sqft">বর্গফুট (sqft)</option>
                                <option value="cft">ঘনফুট (cft)</option>
                                <option value="kg">কেজি (kg)</option>
                                <option value="liter">লিটার (liter)</option>
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

<!-- ============ CATEGORY MANAGEMENT MODAL ============ -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="bi bi-tags me-1 text-danger"></i> ক্যাটাগরি ম্যানেজমেন্ট
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Existing categories list -->
                <ul class="list-group mb-3" id="categoryList">
                    <?php foreach ($categories as $cat): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span><?= e($cat['name']) ?></span>
                        <button class="btn btn-sm btn-outline-danger btn-del-cat"
                                data-id="<?= $cat['id'] ?>" data-name="<?= e($cat['name']) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                    <li class="list-group-item text-muted text-center" id="noCatMsg">কোনো ক্যাটাগরি নেই</li>
                    <?php endif; ?>
                </ul>

                <!-- Add new category -->
                <div class="input-group">
                    <input type="text" class="form-control" id="newCategoryName"
                           placeholder="নতুন ক্যাটাগরির নাম" maxlength="100">
                    <button class="btn btn-primary" id="btnAddCategory">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                <div class="alert alert-danger py-2 mt-2 d-none" id="catError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i>রিফ্রেশ করুন
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============ DELETE CATEGORY CONFIRM MODAL ============ -->
<div class="modal fade" id="delCatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-1"></i> ক্যাটাগরি ডিলিট
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2 small">
                    আপনি <strong class="text-danger" id="delCatName"></strong> ক্যাটাগরিটি
                    ডিলিট করতে চাচ্ছেন। এটি স্থায়ীভাবে মুছে যাবে।
                </p>
                <p class="mb-2 small text-muted">
                    নিশ্চিত করতে নিচে ক্যাটাগরির নাম <strong>হুবহু</strong> লিখুন:
                </p>
                <input type="text" class="form-control" id="delCatConfirmInput"
                       placeholder="ক্যাটাগরির নাম লিখুন" autocomplete="off">
                <input type="hidden" id="delCatId">
                <div class="alert alert-danger py-2 mt-2 d-none" id="delCatError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelCat" disabled>
                    <i class="bi bi-trash me-1"></i> ডিলিট নিশ্চিত করুন
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE       = '<?= BASE_URL ?>';
const CATEGORIES = <?= json_encode(array_values($categories)) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/products.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
