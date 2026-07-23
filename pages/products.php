<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Category.php';
require_once __DIR__ . '/../classes/Branch.php';
requireManagerOrAdmin();

$pageTitle     = 'পণ্য ম্যানেজমেন্ট';
$categories    = Category::getAll();
$subcategories = Product::getSubcategories();
$branches      = Branch::getBranches();

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
        <div class="d-flex gap-2 flex-wrap">
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
            <?php renderProductTable($productsByCategory[$cat['id']], !empty($branches)); ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php
function renderProductTable(array $items, bool $hasBranches): void { ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:56px">ছবি</th>
                            <th>পণ্যের নাম</th>
                            <th>কোড</th>
                            <th>সাব-ক্যাটাগরি</th>
                            <th>সাইজ / ব্র্যান্ড</th>
                            <th>ইউনিট</th>
                            <th class="text-end">ক্রয় দাম</th>
                            <th class="text-end">বিক্রয় দাম</th>
                            <th class="text-end">পাইকারি</th>
                            <th class="text-end">মিন. স্টক</th>
                            <th class="text-center" style="width:170px">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">
                            কোন পণ্য নেই। "নতুন পণ্য" বাটনে ক্লিক করুন।
                        </td></tr>
                    <?php else: foreach ($items as $p): ?>
                        <tr>
                            <td>
                                <?php if (!empty($p['image'])): ?>
                                <img src="<?= BASE_URL . '/' . e($p['image']) ?>" alt=""
                                     class="rounded border btn-view" style="width:42px;height:42px;object-fit:cover;cursor:pointer"
                                     data-id="<?= $p['id'] ?>" title="বড় করে দেখুন">
                                <?php else: ?>
                                <span class="d-inline-flex align-items-center justify-content-center rounded border bg-light text-muted"
                                      style="width:42px;height:42px"><i class="bi bi-image"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?= e($p['name']) ?></td>
                            <td><code class="small"><?= e($p['product_code'] ?? '—') ?></code></td>
                            <td><?= $p['subcategory_name'] ? '<span class="badge bg-info-subtle text-info-emphasis border">' . e($p['subcategory_name']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($p['size_brand'] ?: '—') ?></span></td>
                            <td><?= e($p['unit']) ?></td>
                            <td class="text-end"><?= money((float)$p['buy_price']) ?></td>
                            <td class="text-end"><?= money((float)$p['sell_price']) ?></td>
                            <td class="text-end"><?= (float)$p['wholesale_price'] > 0 ? money((float)$p['wholesale_price']) : '—' ?></td>
                            <td class="text-end"><?= rtrim(rtrim($p['min_stock'], '0'), '.') ?> <?= e($p['unit']) ?></td>
                            <td class="text-center text-nowrap">
                                <button class="btn btn-sm btn-outline-info btn-view"
                                        data-id="<?= $p['id'] ?>" title="বিস্তারিত দেখুন">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary btn-qr"
                                        data-id="<?= $p['id'] ?>" data-code="<?= e($p['product_code'] ?? '') ?>"
                                        data-name="<?= e($p['name']) ?>" title="QR কোড">
                                    <i class="bi bi-qr-code"></i>
                                </button>
                                <?php if ($hasBranches): ?>
                                <button class="btn btn-sm btn-outline-success btn-branch-price"
                                        data-id="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" title="ব্রাঞ্চ মূল্য">
                                    <i class="bi bi-buildings"></i>
                                </button>
                                <?php endif; ?>
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
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
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
                    <input type="hidden" name="image" id="productImage">

                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold">ক্যাটাগরি <span class="text-danger">*</span></label>
                            <select name="category_id" id="productCategory" class="form-select" required>
                                <option value="">— ক্যাটাগরি নির্বাচন করুন —</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold">সাব-ক্যাটাগরি</label>
                            <select name="subcategory_id" id="productSubcategory" class="form-select" data-no-search="1">
                                <option value="">— নেই —</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold">পণ্যের নাম <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="productName" class="form-control"
                               placeholder="যেমন: Steel Rod 12mm / Lafarge Cement" required>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <label class="form-label fw-semibold">পণ্য কোড</label>
                            <input type="text" name="product_code" id="productCode" class="form-control"
                                   placeholder="খালি রাখলে অটো হবে" maxlength="50">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label fw-semibold">সাইজ / ব্র্যান্ড</label>
                            <input type="text" name="size_brand" id="sizeBrand" class="form-control"
                                   placeholder="যেমন: 12mm / LAFARGE">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label fw-semibold">ইউনিট <span class="text-danger">*</span></label>
                            <select name="unit" id="productUnit" class="form-select" required>
                                <option value="ton">টন (ton)</option>
                                <option value="bag">ব্যাগ (bag)</option>
                                <option value="pcs">পিস (pcs)</option>
                                <option value="sqft">বর্গফুট (sqft)</option>
                                <option value="cft">ঘনফুট (cft)</option>
                                <option value="kg">কেজি (kg)</option>
                                <option value="liter">লিটার (liter)</option>
                                <option value="other">অন্যান্য</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <label class="form-label fw-semibold">ক্রয় দাম (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="buy_price"
                                   id="buyPrice" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label fw-semibold">বিক্রয় দাম (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="sell_price"
                                   id="sellPrice" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label fw-semibold">পাইকারি দাম (৳)</label>
                            <input type="number" step="0.01" min="0" name="wholesale_price"
                                   id="wholesalePrice" class="form-control" value="0">
                        </div>
                    </div>

                    <div class="row g-2 align-items-end">
                        <div class="col-md-4 mb-2">
                            <label class="form-label fw-semibold">মিনিমাম স্টক (Alert)</label>
                            <input type="number" step="0.01" min="0" name="min_stock"
                                   id="minStock" class="form-control" value="0">
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="form-label fw-semibold">পণ্যের ছবি</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="file" class="form-control" id="imageFile" accept="image/jpeg,image/png,image/webp">
                                <span id="imagePreviewWrap" class="d-none">
                                    <img src="" id="imagePreview" class="rounded border"
                                         style="width:44px;height:44px;object-fit:cover">
                                </span>
                            </div>
                            <small class="text-muted">JPG / PNG / WebP, সর্বোচ্চ ৩ MB।</small>
                        </div>
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

<!-- ============ QR MODAL ============ -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-qr-code me-1 text-danger"></i> পণ্যের QR কোড</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center" id="qrBody">
                <div id="qrCanvas" class="d-inline-block p-2 bg-white border rounded"></div>
                <p class="fw-semibold mt-2 mb-0" id="qrProductName"></p>
                <code id="qrProductCode"></code>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বন্ধ</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnPrintQr">
                    <i class="bi bi-printer me-1"></i>প্রিন্ট
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============ PRODUCT VIEW MODAL ============ -->
<div class="modal fade" id="productViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-eye me-1 text-info"></i> পণ্যের বিস্তারিত</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <!-- Image -->
                    <div class="col-md-5 text-center">
                        <div id="pvImageWrap" class="border rounded bg-light d-flex align-items-center justify-content-center"
                             style="min-height:240px;overflow:hidden">
                            <img src="" id="pvImage" alt="" class="img-fluid d-none"
                                 style="max-height:320px;object-fit:contain;cursor:zoom-in"
                                 title="পূর্ণ আকারে দেখতে ক্লিক করুন">
                            <span id="pvNoImage" class="text-muted"><i class="bi bi-image fs-1"></i><br>ছবি নেই</span>
                        </div>
                    </div>
                    <!-- Details -->
                    <div class="col-md-7">
                        <h4 class="fw-bold mb-1" id="pvName">—</h4>
                        <p class="mb-3"><code id="pvCode" class="fs-6"></code></p>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><td class="text-muted" style="width:45%">ক্যাটাগরি</td><td class="fw-semibold" id="pvCategory">—</td></tr>
                                <tr><td class="text-muted">সাব-ক্যাটাগরি</td><td id="pvSubcategory">—</td></tr>
                                <tr><td class="text-muted">সাইজ / ব্র্যান্ড</td><td id="pvSizeBrand">—</td></tr>
                                <tr><td class="text-muted">ইউনিট</td><td id="pvUnit">—</td></tr>
                                <tr><td class="text-muted">ক্রয় দাম</td><td class="fw-semibold" id="pvBuy">—</td></tr>
                                <tr><td class="text-muted">বিক্রয় দাম</td><td class="fw-semibold text-success" id="pvSell">—</td></tr>
                                <tr><td class="text-muted">পাইকারি দাম</td><td id="pvWholesale">—</td></tr>
                                <tr><td class="text-muted">মিনিমাম স্টক (Alert)</td><td id="pvMinStock">—</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">বন্ধ</button>
                <button type="button" class="btn btn-primary btn-sm" id="pvEditBtn">
                    <i class="bi bi-pencil me-1"></i>সম্পাদনা
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============ BRANCH PRICE MODAL ============ -->
<?php if (!empty($branches)): ?>
<div class="modal fade" id="branchPriceModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="bi bi-buildings me-1 text-danger"></i>
                    ব্রাঞ্চ ভিত্তিক মূল্য — <span id="bpProductName"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    খালি রাখলে কেন্দ্রীয় (সেন্ট্রাল) দাম প্রযোজ্য হবে। শুধু যে ব্রাঞ্চে ভিন্ন দাম দরকার সেখানে লিখুন।
                </p>
                <input type="hidden" id="bpProductId">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ব্রাঞ্চ</th>
                                <th style="width:120px">ক্রয় (৳)</th>
                                <th style="width:120px">বিক্রয় (৳)</th>
                                <th style="width:120px">পাইকারি (৳)</th>
                                <th style="width:110px">মিন. স্টক</th>
                                <th style="width:80px" class="text-center">বিক্রয়যোগ্য</th>
                            </tr>
                        </thead>
                        <tbody id="bpBody"></tbody>
                    </table>
                </div>
                <div class="alert alert-danger py-2 mt-2 d-none" id="bpError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">বাতিল</button>
                <button type="button" class="btn btn-primary" id="btnSaveBranchPrices">
                    <i class="bi bi-check-lg me-1"></i> সংরক্ষণ করুন
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============ CATEGORY MANAGEMENT MODAL ============ -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="bi bi-tags me-1 text-danger"></i> ক্যাটাগরি ম্যানেজমেন্ট
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-pills nav-fill mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#catManageTab" type="button">ক্যাটাগরি</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#subcatManageTab" type="button">সাব-ক্যাটাগরি</button></li>
                </ul>

                <div class="tab-content">
                    <!-- Categories -->
                    <div class="tab-pane fade show active" id="catManageTab">
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
                        <div class="input-group">
                            <input type="text" class="form-control" id="newCategoryName"
                                   placeholder="নতুন ক্যাটাগরির নাম" maxlength="100">
                            <button class="btn btn-primary" id="btnAddCategory">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <div class="alert alert-danger py-2 mt-2 d-none" id="catError"></div>
                    </div>

                    <!-- Sub-categories -->
                    <div class="tab-pane fade" id="subcatManageTab">
                        <ul class="list-group mb-3" id="subcategoryList">
                            <?php foreach ($subcategories as $sc): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2"
                                data-subcat-id="<?= $sc['id'] ?>">
                                <span>
                                    <?= e($sc['name']) ?>
                                    <small class="text-muted">(<?= e($sc['category_name']) ?>)</small>
                                </span>
                                <button class="btn btn-sm btn-outline-danger btn-del-subcat" data-id="<?= $sc['id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </li>
                            <?php endforeach; ?>
                            <?php if (empty($subcategories)): ?>
                            <li class="list-group-item text-muted text-center" id="noSubcatMsg">কোনো সাব-ক্যাটাগরি নেই</li>
                            <?php endif; ?>
                        </ul>
                        <div class="row g-2">
                            <div class="col-5">
                                <select class="form-select" id="newSubcatCategory" data-no-search="1">
                                    <option value="">ক্যাটাগরি</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-7">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="newSubcatName"
                                           placeholder="সাব-ক্যাটাগরির নাম" maxlength="100">
                                    <button class="btn btn-primary" id="btnAddSubcat">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-danger py-2 mt-2 d-none" id="subcatError"></div>
                    </div>
                </div>
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

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const BASE          = '<?= BASE_URL ?>';
const CATEGORIES    = <?= json_encode(array_values($categories)) ?>;
const SUBCATEGORIES = <?= json_encode(array_values($subcategories)) ?>;
const BRANCHES      = <?= json_encode(array_values($branches)) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/products.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
