<?php
/**
 * admin/services.php — Services Listing + Add/Edit Modal + Delete + Package Manager
 * Version: 3.0 (delivery_days support)
 */

$admin_page_title = 'Services';
$admin_active     = 'services';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Filters ---------- */
$q        = trim((string) input('q', ''));
$catId    = (int) input('category', 0);
$status   = trim((string) input('status', ''));
$sortBy   = trim((string) input('sort', 'latest'));
$page     = max(1, (int) input('page', 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

$allowedStatus = ['active', 'draft', 'inactive'];

/* ---------- Where ---------- */
$where  = ["1=1"];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(s.title_en LIKE ? OR s.title_bn LIKE ? OR s.slug LIKE ?)";
    array_push($params, $like, $like, $like);
}
if ($catId > 0) { $where[] = "s.category_id = ?"; $params[] = $catId; }
if ($status !== '' && in_array($status, $allowedStatus, true)) { $where[] = "s.status = ?"; $params[] = $status; }
$whereSql = implode(' AND ', $where);

/* ---------- Sort ---------- */
$sortMap = [
    'latest'  => 's.id DESC',
    'oldest'  => 's.id ASC',
    'title'   => 's.title_en ASC',
    'sales'   => 's.total_sales DESC',
    'price'   => 's.price DESC',
];
$orderSql = $sortMap[$sortBy] ?? $sortMap['latest'];

/* ---------- Count ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM services s WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch ---------- */
$listStmt = $pdo->prepare("
    SELECT s.id, s.title_en, s.title_bn, s.slug, s.thumbnail, s.price, s.discount_price,
           s.currency, s.status, s.is_featured, s.total_sales, s.rating,
           s.delivery_type, s.delivery_time, s.delivery_days, s.created_at,
           s.demo_path, s.demo_active, s.category_id,
           c.name_en AS category_name,
           (SELECT COUNT(*) FROM service_packages sp WHERE sp.service_id = s.id AND sp.is_active = 1) AS pkg_count,
           (SELECT COUNT(*) FROM service_packages sp WHERE sp.service_id = s.id AND sp.package_type = 'trial' AND sp.is_active = 1) AS trial_count,
           (SELECT COUNT(*) FROM service_packages sp WHERE sp.service_id = s.id AND sp.package_type = 'subscription' AND sp.is_active = 1) AS sub_count,
           (SELECT COUNT(*) FROM service_packages sp WHERE sp.service_id = s.id AND sp.package_type = 'lifetime' AND sp.is_active = 1) AS life_count
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE $whereSql
    ORDER BY $orderSql
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$services = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- Categories ---------- */
$categories = $pdo->query("
    SELECT id, name_en, name_bn FROM categories WHERE status = 1 ORDER BY sort_order ASC
")->fetchAll();

/* ---------- URL helper ---------- */
function svc_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('services.php') . ($p ? '?' . http_build_query($p) : '');
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Services</span>
        </nav>
        <h1 class="admin-page-title">Services</h1>
        <p class="admin-page-sub"><?= number_format($total) ?> service(s) found</p>
    </div>
    <div class="flex gap-sm">
        <a href="<?= e(admin_url('service-cpanel.php')) ?>" class="admin-btn admin-btn-ghost">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            Website Demos
        </a>
        <button type="button" class="admin-btn admin-btn-primary" id="btn-add-service">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add New Service
        </button>
    </div>
</div>

<!-- ============ FILTER BAR ============ -->
<form class="admin-filter-bar" method="get" action="<?= e(admin_url('services.php')) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search title or slug..." style="min-width:220px;">

    <select name="category" data-filter-submit>
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>>
                <?= e($c['name_en']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="status" data-filter-submit>
        <option value="">All Status</option>
        <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="draft"    <?= $status === 'draft'    ? 'selected' : '' ?>>Draft</option>
        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>

    <select name="sort" data-filter-submit>
        <option value="latest" <?= $sortBy === 'latest' ? 'selected' : '' ?>>Latest First</option>
        <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
        <option value="title"  <?= $sortBy === 'title'  ? 'selected' : '' ?>>Title A→Z</option>
        <option value="sales"  <?= $sortBy === 'sales'  ? 'selected' : '' ?>>Most Sold</option>
        <option value="price"  <?= $sortBy === 'price'  ? 'selected' : '' ?>>Highest Price</option>
    </select>

    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Apply</button>
    <a href="<?= e(admin_url('services.php')) ?>" class="admin-btn admin-btn-ghost admin-btn-sm">Reset</a>
</form>

<!-- ============ TABLE ============ -->
<?php if (empty($services)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            <h3>No services found</h3>
            <p>Try adjusting filters or add a new service.</p>
            <button type="button" class="admin-btn admin-btn-primary" style="margin-top:16px;" onclick="document.getElementById('btn-add-service').click();">
                Add Your First Service
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="admin-table-wrap">
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Image</th>
                        <th>Service</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th style="text-align:center;">Packages</th>
                        <th style="text-align:center;">Delivery</th>
                        <th>Sales</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $s):
                        $title = $lang === 'bn' && !empty($s['title_bn']) ? $s['title_bn'] : $s['title_en'];
                        $thumb = $s['thumbnail'] ? base_url($s['thumbnail']) : base_url('assets/images/placeholder.png');
                        $price = (float) $s['price'];
                        $disc  = !empty($s['discount_price']) ? (float) $s['discount_price'] : 0;
                        $final = $disc > 0 ? $disc : $price;
                        $statusCls = match($s['status']) {
                            'active'   => 'tag-success',
                            'draft'    => 'tag-warning',
                            'inactive' => 'tag-danger',
                            default    => 'tag',
                        };
                        $hasDemo  = !empty($s['demo_path']);
                        $pkgCount = (int) $s['pkg_count'];
                        $delDays  = (int)($s['delivery_days'] ?? 3);
                    ?>
                        <tr>
                            <td><img src="<?= e($thumb) ?>" alt="" class="admin-thumb"></td>
                            <td>
                                <div class="admin-cell-title">
                                    <?= e(str_limit($title, 60)) ?>
                                    <?php if ($hasDemo): ?>
                                        <span class="tag tag-primary" style="font-size:10px;padding:2px 6px;margin-left:4px;">DEMO</span>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-cell-sub">
                                    <?= e($s['delivery_type']) ?>
                                    <?php if ($s['is_featured']): ?> • ★ Featured<?php endif; ?>
                                </div>
                            </td>
                            <td class="admin-cell-sub"><?= e($s['category_name'] ?: '—') ?></td>
                            <td>
                                <strong><?= e(money($final, $s['currency'])) ?></strong>
                                <?php if ($disc > 0): ?>
                                    <div class="admin-cell-sub" style="text-decoration:line-through;"><?= e(money($price, $s['currency'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($pkgCount > 0): ?>
                                    <div style="display:inline-flex;gap:4px;align-items:center;">
                                        <?php if ((int)$s['trial_count'] > 0): ?>
                                            <span class="tag tag-info" style="font-size:10px;" title="Trial">T</span>
                                        <?php endif; ?>
                                        <?php if ((int)$s['sub_count'] > 0): ?>
                                            <span class="tag tag-success" style="font-size:10px;" title="Subscription">S</span>
                                        <?php endif; ?>
                                        <?php if ((int)$s['life_count'] > 0): ?>
                                            <span class="tag tag-warning" style="font-size:10px;" title="Lifetime">L</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="admin-cell-sub" style="font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="tag tag-primary" style="font-size:11px;"><?= $delDays ?> d</span>
                            </td>
                            <td><?= (int) $s['total_sales'] ?></td>
                            <td>
                                <span class="tag <?= e($statusCls) ?>" style="text-transform:capitalize;"><?= e($s['status']) ?></span>
                            </td>
                            <td>
                                <div class="cell-actions">
                                    <!-- Packages -->
                                    <button type="button"
                                            class="admin-btn admin-btn-primary admin-btn-icon"
                                            data-manage-packages
                                            data-service-id="<?= (int)$s['id'] ?>"
                                            data-service-title="<?= e($title) ?>"
                                            data-service-currency="<?= e($s['currency']) ?>"
                                            title="Manage Packages">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                            <line x1="12" y1="22.08" x2="12" y2="12"/>
                                        </svg>
                                    </button>

                                    <?php if ($hasDemo): ?>
                                        <a href="<?= e(admin_url('service-files.php?id=' . (int) $s['id'])) ?>"
                                           class="admin-btn admin-btn-ghost admin-btn-icon" title="Browse Files">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                        </a>
                                    <?php endif; ?>

                                    <a href="<?= e(base_url('service-details.php?slug=' . urlencode($s['slug']))) ?>"
                                       target="_blank" class="admin-btn admin-btn-ghost admin-btn-icon" title="View">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>

                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon"
                                            data-edit-service='<?= e(json_encode([
                                                "id"             => (int) $s["id"],
                                                "title_en"       => $s["title_en"],
                                                "title_bn"       => $s["title_bn"],
                                                "slug"           => $s["slug"],
                                                "category_id"    => (int) ($s["category_id"] ?? 0),
                                                "price"          => (float) $s["price"],
                                                "discount_price" => (float) ($s["discount_price"] ?? 0),
                                                "currency"       => $s["currency"],
                                                "delivery_type"  => $s["delivery_type"],
                                                "delivery_time"  => $s["delivery_time"],
                                                "delivery_days"  => (int) ($s["delivery_days"] ?? 3),
                                                "status"         => $s["status"],
                                                "is_featured"    => (int) $s["is_featured"],
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
                                            title="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>

                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" style="color:var(--danger);"
                                            data-confirm-delete
                                            data-url="admin/services/delete"
                                            data-message="Delete this service? This will also delete all its packages. This cannot be undone."
                                            data-id="<?= (int) $s['id'] ?>"
                                            title="Delete">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="admin-pagination">
            <?php if ($page > 1): ?>
                <a href="<?= e(svc_url(['page' => $page - 1])) ?>">←</a>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
                $act = $i === $page;
            ?>
                <a href="<?= e(svc_url(['page' => $i])) ?>" class="<?= $act ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= e(svc_url(['page' => $page + 1])) ?>">→</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- ============================================================
     ADD / EDIT SERVICE MODAL
     ============================================================ -->
<div id="service-modal" class="admin-modal">
    <div class="admin-modal-backdrop" data-modal-close></div>
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3 class="admin-card-title" id="modal-title">Add New Service</h3>
            <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" data-modal-close aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="service-form" data-ajax data-endpoint="admin/services/save" data-redirect="admin/services.php"
              enctype="multipart/form-data" style="padding:20px;max-height:70vh;overflow-y:auto;">

            <input type="hidden" name="id" id="f-id" value="0">

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Title (EN) <span class="required">*</span></label>
                    <input type="text" name="title_en" id="f-title_en" class="form-control" required maxlength="200">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Title (BN)</label>
                    <input type="text" name="title_bn" id="f-title_bn" class="form-control" maxlength="200">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Slug</label>
                    <input type="text" name="slug" id="f-slug" class="form-control" placeholder="auto-generated if empty" maxlength="220">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Category <span class="required">*</span></label>
                    <select name="category_id" id="f-category_id" class="form-control" required>
                        <option value="">— Select category —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['name_en']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Short Description (EN)</label>
                    <input type="text" name="short_desc_en" id="f-short_desc_en" class="form-control" maxlength="500">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Short Description (BN)</label>
                    <input type="text" name="short_desc_bn" id="f-short_desc_bn" class="form-control" maxlength="500">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Base Price <span class="required">*</span></label>
                    <input type="text" name="price" id="f-price" class="form-control" data-numeric required placeholder="0.00">
                    <p class="admin-help">Fallback price — package না থাকলে এটাই ব্যবহার হবে।</p>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Discount Price</label>
                    <input type="text" name="discount_price" id="f-discount_price" class="form-control" data-numeric placeholder="0.00">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Currency</label>
                    <select name="currency" id="f-currency" class="form-control">
                        <option value="BDT">BDT ৳</option>
                        <option value="USD">USD $</option>
                    </select>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Delivery Type</label>
                    <select name="delivery_type" id="f-delivery_type" class="form-control">
                        <option value="custom">Custom (manual)</option>
                        <option value="digital">Digital (instant)</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Delivery Time (Label)</label>
                    <input type="text" name="delivery_time" id="f-delivery_time" class="form-control" placeholder="e.g. 3-5 days" maxlength="50">
                    <p class="admin-help">Customer যে দেখবে (যেমন "3-5 days")</p>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Delivery Days <span class="required">*</span></label>
                    <input type="number" name="delivery_days" id="f-delivery_days" class="form-control" min="1" max="90" value="3" required>
                    <p class="admin-help">Countdown এ ব্যবহার হবে (numeric days)</p>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Status</label>
                    <select name="status" id="f-status" class="form-control">
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Thumbnail</label>
                <div class="flex items-center gap-md">
                    <img id="f-thumb-preview" src="<?= e(base_url('assets/images/placeholder.png')) ?>" alt=""
                         style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                    <div style="flex:1;">
                        <input type="file" name="thumbnail" id="f-thumbnail" accept="image/jpeg,image/png,image/webp"
                               data-preview="#f-thumb-preview" class="form-control" style="padding:6px;">
                        <p class="admin-help">JPG/PNG/WEBP — max 5MB. Leave empty to keep current.</p>
                    </div>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="is_featured" id="f-is_featured" value="1">
                    <span class="admin-switch-track"></span>
                    <span>Feature this service on homepage</span>
                </label>
            </div>

            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary" id="service-submit-btn">Save Service</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     PACKAGE MANAGER MODAL (unchanged from v2)
     ============================================================ -->
<div id="package-modal" class="admin-modal">
    <div class="admin-modal-backdrop" data-pkg-close></div>
    <div class="admin-modal-card" style="max-width:900px;">
        <div class="admin-modal-header">
            <div>
                <h3 class="admin-card-title" id="pkg-modal-title">Manage Packages</h3>
                <p class="admin-cell-sub" id="pkg-modal-sub" style="margin-top:2px;">—</p>
            </div>
            <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" data-pkg-close aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div style="padding:20px;max-height:75vh;overflow-y:auto;">
            <div id="pkg-loading" style="text-align:center;padding:40px;">
                <span class="spinner"></span>
                <p class="admin-cell-sub" style="margin-top:12px;">Loading packages...</p>
            </div>

            <div id="pkg-content" style="display:none;">
                <input type="hidden" id="pkg-service-id" value="0">
                <input type="hidden" id="pkg-currency" value="BDT">

                <div class="admin-tabs" style="margin-bottom:20px;">
                    <button type="button" class="admin-tab active" data-pkg-tab="trial">🎁 Trial</button>
                    <button type="button" class="admin-tab" data-pkg-tab="subscription">📅 Subscription</button>
                    <button type="button" class="admin-tab" data-pkg-tab="lifetime">♾️ Lifetime</button>
                </div>

                <!-- TRIAL -->
                <div class="pkg-tab-panel" data-pkg-panel="trial">
                    <div class="admin-card" style="background:#EFF6FF;border-color:#BFDBFE;padding:14px;margin-bottom:16px;">
                        <div style="display:flex;gap:10px;align-items:flex-start;font-size:13px;color:#1E40AF;">
                            <span style="font-size:20px;">💡</span>
                            <div><strong>Trial Package:</strong> ইউজার ফ্রি ট্রায়াল পাবে। Trial শেষ হওয়ার আগে countdown alert দেখাবে।</div>
                        </div>
                    </div>

                    <form class="pkg-form" data-pkg-type="trial" data-pkg-id="0">
                        <input type="hidden" name="package_type" value="trial">
                        <input type="hidden" name="service_id" class="pkg-service-id" value="0">

                        <div class="admin-form-group">
                            <label class="admin-switch">
                                <input type="checkbox" name="is_active" class="pkg-active" value="1">
                                <span class="admin-switch-track"></span>
                                <span>Trial package চালু করুন</span>
                            </label>
                        </div>

                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label class="admin-label">Trial Duration (Days) <span class="required">*</span></label>
                                <input type="number" name="trial_days" class="form-control" min="1" max="365" value="7" required>
                            </div>
                            <div class="admin-form-group">
                                <label class="admin-label">Alert Before (Hours) <span class="required">*</span></label>
                                <input type="number" name="alert_hours_before" class="form-control" min="1" max="720" value="48" required>
                            </div>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Short Description</label>
                            <input type="text" name="description" class="form-control" maxlength="500" placeholder="e.g. Try it FREE for 7 days">
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Features (এক লাইনে একটি)</label>
                            <textarea name="features_text" class="form-control" rows="4" placeholder="Full access&#10;No credit card required&#10;Cancel anytime"></textarea>
                        </div>

                        <div class="admin-modal-footer" style="padding:0;border:none;margin-top:16px;">
                            <button type="button" class="admin-btn admin-btn-ghost pkg-delete-btn" style="color:var(--danger);display:none;">Delete Trial</button>
                            <div style="flex:1;"></div>
                            <button type="submit" class="admin-btn admin-btn-primary">Save Trial Package</button>
                        </div>
                    </form>
                </div>

                <!-- SUBSCRIPTION -->
                <div class="pkg-tab-panel" data-pkg-panel="subscription" style="display:none;">
                    <div class="admin-card" style="background:#F0FDF4;border-color:#BBF7D0;padding:14px;margin-bottom:16px;">
                        <div style="display:flex;gap:10px;align-items:flex-start;font-size:13px;color:#166534;">
                            <span style="font-size:20px;">💡</span>
                            <div><strong>Subscription:</strong> User duration select করবে (১ মাস, ২ মাস, ৬ মাস, ১ বছর ইত্যাদি) — price auto-calculate হবে।</div>
                        </div>
                    </div>

                    <form class="pkg-form" data-pkg-type="subscription" data-pkg-id="0">
                        <input type="hidden" name="package_type" value="subscription">
                        <input type="hidden" name="service_id" class="pkg-service-id" value="0">

                        <div class="admin-form-group">
                            <label class="admin-switch">
                                <input type="checkbox" name="is_active" class="pkg-active" value="1">
                                <span class="admin-switch-track"></span>
                                <span>Subscription package চালু করুন</span>
                            </label>
                        </div>

                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label class="admin-label">Price per Month <span class="required">*</span></label>
                                <input type="text" name="price_per_month" class="form-control" data-numeric value="0.00" required>
                            </div>
                            <div class="admin-form-group">
                                <label class="admin-label">Price per Year</label>
                                <input type="text" name="price_per_year" class="form-control" data-numeric value="0.00">
                                <p class="admin-help">ডিসকাউন্ট দিতে চাইলে কম দিন</p>
                            </div>
                        </div>

                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label class="admin-label">Min Months</label>
                                <input type="number" name="min_months" class="form-control" min="1" max="120" value="1">
                            </div>
                            <div class="admin-form-group">
                                <label class="admin-label">Max Months</label>
                                <input type="number" name="max_months" class="form-control" min="1" max="120" value="60">
                            </div>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Short Description</label>
                            <input type="text" name="description" class="form-control" maxlength="500">
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Features (এক লাইনে একটি)</label>
                            <textarea name="features_text" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="admin-modal-footer" style="padding:0;border:none;margin-top:16px;">
                            <button type="button" class="admin-btn admin-btn-ghost pkg-delete-btn" style="color:var(--danger);display:none;">Delete Subscription</button>
                            <div style="flex:1;"></div>
                            <button type="submit" class="admin-btn admin-btn-primary">Save Subscription Package</button>
                        </div>
                    </form>
                </div>

                <!-- LIFETIME -->
                <div class="pkg-tab-panel" data-pkg-panel="lifetime" style="display:none;">
                    <div class="admin-card" style="background:#FEF3C7;border-color:#FDE68A;padding:14px;margin-bottom:16px;">
                        <div style="display:flex;gap:10px;align-items:flex-start;font-size:13px;color:#92400E;">
                            <span style="font-size:20px;">💡</span>
                            <div><strong>Lifetime:</strong> এককালীন পেমেন্টে ইউজার সারাজীবন access পাবে। কোনো expiry নেই।</div>
                        </div>
                    </div>

                    <form class="pkg-form" data-pkg-type="lifetime" data-pkg-id="0">
                        <input type="hidden" name="package_type" value="lifetime">
                        <input type="hidden" name="service_id" class="pkg-service-id" value="0">

                        <div class="admin-form-group">
                            <label class="admin-switch">
                                <input type="checkbox" name="is_active" class="pkg-active" value="1">
                                <span class="admin-switch-track"></span>
                                <span>Lifetime package চালু করুন</span>
                            </label>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Lifetime Price <span class="required">*</span></label>
                            <input type="text" name="lifetime_price" class="form-control" data-numeric value="0.00" required>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Short Description</label>
                            <input type="text" name="description" class="form-control" maxlength="500">
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">Features (এক লাইনে একটি)</label>
                            <textarea name="features_text" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="admin-modal-footer" style="padding:0;border:none;margin-top:16px;">
                            <button type="button" class="admin-btn admin-btn-ghost pkg-delete-btn" style="color:var(--danger);display:none;">Delete Lifetime</button>
                            <div style="flex:1;"></div>
                            <button type="submit" class="admin-btn admin-btn-primary">Save Lifetime Package</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ========================================================
       SERVICE ADD/EDIT MODAL
       ======================================================== */
    var modal = document.getElementById('service-modal');
    var form  = document.getElementById('service-form');
    var title = document.getElementById('modal-title');

    function openModal(editData) {
        if (editData && editData.id) {
            title.textContent = 'Edit Service';
            form.querySelector('#f-id').value              = editData.id;
            form.querySelector('#f-title_en').value        = editData.title_en || '';
            form.querySelector('#f-title_bn').value        = editData.title_bn || '';
            form.querySelector('#f-slug').value            = editData.slug || '';
            form.querySelector('#f-category_id').value     = editData.category_id || '';
            form.querySelector('#f-price').value           = editData.price || 0;
            form.querySelector('#f-discount_price').value  = editData.discount_price || 0;
            form.querySelector('#f-currency').value        = editData.currency || 'BDT';
            form.querySelector('#f-delivery_type').value   = editData.delivery_type || 'custom';
            form.querySelector('#f-delivery_time').value   = editData.delivery_time || '';
            form.querySelector('#f-delivery_days').value   = editData.delivery_days || 3;
            form.querySelector('#f-status').value          = editData.status || 'active';
            form.querySelector('#f-is_featured').checked   = !!editData.is_featured;
        } else {
            title.textContent = 'Add New Service';
            form.reset();
            form.querySelector('#f-id').value = 0;
            form.querySelector('#f-delivery_days').value = 3;
            document.getElementById('f-thumb-preview').src = '<?= e(base_url('assets/images/placeholder.png')) ?>';
        }
        modal.classList.add('open');
    }
    function closeModal() { modal.classList.remove('open'); }

    document.getElementById('btn-add-service')?.addEventListener('click', function () { openModal(null); });

    document.querySelectorAll('[data-edit-service]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { openModal(JSON.parse(btn.getAttribute('data-edit-service'))); }
            catch (e) { console.error(e); }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (el) { el.addEventListener('click', closeModal); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

    form.querySelector('#f-title_en')?.addEventListener('blur', function () {
        var slugEl = form.querySelector('#f-slug');
        if (slugEl.value.trim() !== '') return;
        if (!this.value.trim()) return;
        var s = this.value.toLowerCase().replace(/[^\p{L}\p{N}\s-]/gu, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
        slugEl.value = s;
    });

    /* ========================================================
       PACKAGE MANAGER
       ======================================================== */
    var pkgModal = document.getElementById('package-modal');
    var pkgForms = document.querySelectorAll('.pkg-form');

    function openPkgModal(serviceId, serviceTitle, currency) {
        document.getElementById('pkg-modal-title').textContent = '📦 Packages — ' + serviceTitle;
        document.getElementById('pkg-modal-sub').textContent   = '৩ ধরনের package সেট করুন: Trial / Subscription / Lifetime';
        document.getElementById('pkg-service-id').value = serviceId;
        document.getElementById('pkg-currency').value   = currency || 'BDT';

        document.querySelectorAll('[data-pkg-tab]').forEach(t => t.classList.remove('active'));
        document.querySelector('[data-pkg-tab="trial"]').classList.add('active');
        document.querySelectorAll('.pkg-tab-panel').forEach(p => p.style.display = 'none');
        document.querySelector('[data-pkg-panel="trial"]').style.display = 'block';

        pkgForms.forEach(resetPkgForm);

        document.getElementById('pkg-loading').style.display = 'block';
        document.getElementById('pkg-content').style.display = 'none';

        pkgModal.classList.add('open');
        loadPackages(serviceId);
    }
    function closePkgModal() { pkgModal.classList.remove('open'); }

    function resetPkgForm(f) {
        var type = f.dataset.pkgType;
        f.reset();
        f.dataset.pkgId = '0';
        f.querySelector('[name="service_id"]').value = document.getElementById('pkg-service-id').value;
        if (type === 'trial') {
            f.querySelector('[name="trial_days"]').value = 7;
            f.querySelector('[name="alert_hours_before"]').value = 48;
        } else if (type === 'subscription') {
            f.querySelector('[name="price_per_month"]').value = '0.00';
            f.querySelector('[name="price_per_year"]').value = '0.00';
            f.querySelector('[name="min_months"]').value = 1;
            f.querySelector('[name="max_months"]').value = 60;
        } else if (type === 'lifetime') {
            f.querySelector('[name="lifetime_price"]').value = '0.00';
        }
        f.querySelector('.pkg-delete-btn').style.display = 'none';
    }

    async function loadPackages(serviceId) {
        try {
            var res = await API.get('services/packages', { service_id: serviceId });
            var packages = (res.data && res.data.packages) || [];
            packages.forEach(function(p) {
                var f = document.querySelector('.pkg-form[data-pkg-type="' + p.package_type + '"]');
                if (!f) return;
                f.dataset.pkgId = p.id;
                f.querySelector('[name="service_id"]').value = serviceId;
                f.querySelector('.pkg-active').checked = (parseInt(p.is_active) === 1);

                if (p.package_type === 'trial') {
                    f.querySelector('[name="trial_days"]').value = p.trial_days || 7;
                    f.querySelector('[name="alert_hours_before"]').value = p.alert_hours_before || 48;
                } else if (p.package_type === 'subscription') {
                    f.querySelector('[name="price_per_month"]').value = p.price_per_month || '0.00';
                    f.querySelector('[name="price_per_year"]').value = p.price_per_year || '0.00';
                    f.querySelector('[name="min_months"]').value = p.min_months || 1;
                    f.querySelector('[name="max_months"]').value = p.max_months || 60;
                } else if (p.package_type === 'lifetime') {
                    f.querySelector('[name="lifetime_price"]').value = p.lifetime_price || '0.00';
                }
                if (p.description) f.querySelector('[name="description"]').value = p.description;
                if (p.features) {
                    var arr = Array.isArray(p.features) ? p.features : [];
                    f.querySelector('[name="features_text"]').value = arr.join('\n');
                }
                f.querySelector('.pkg-delete-btn').style.display = 'inline-flex';
            });
            document.getElementById('pkg-loading').style.display = 'none';
            document.getElementById('pkg-content').style.display = 'block';
        } catch (err) {
            document.getElementById('pkg-loading').innerHTML =
                '<p style="color:var(--danger);">Failed to load packages: ' + (err.message || 'Error') + '</p>';
        }
    }

    document.querySelectorAll('[data-manage-packages]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPkgModal(btn.dataset.serviceId, btn.dataset.serviceTitle, btn.dataset.serviceCurrency);
        });
    });
    document.querySelectorAll('[data-pkg-close]').forEach(el => el.addEventListener('click', closePkgModal));
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && pkgModal.classList.contains('open')) closePkgModal(); });

    document.querySelectorAll('[data-pkg-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.dataset.pkgTab;
            document.querySelectorAll('[data-pkg-tab]').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            document.querySelectorAll('.pkg-tab-panel').forEach(p => p.style.display = 'none');
            document.querySelector('.pkg-tab-panel[data-pkg-panel="' + target + '"]').style.display = 'block';
        });
    });

    pkgForms.forEach(function (f) {
        f.addEventListener('submit', async function (e) {
            e.preventDefault();
            var submitBtn = f.querySelector('button[type="submit"]');
            btnLoading(submitBtn, true);

            var type = f.dataset.pkgType;
            var pkgId = f.dataset.pkgId || 0;
            var serviceId = document.getElementById('pkg-service-id').value;
            var isActive = f.querySelector('.pkg-active').checked ? 1 : 0;

            var featuresArr = (f.querySelector('[name="features_text"]').value || '')
                .split('\n').map(s => s.trim()).filter(s => s);

            var payload = {
                id: pkgId,
                service_id: serviceId,
                package_type: type,
                is_active: isActive,
                description: f.querySelector('[name="description"]').value.trim(),
                features_text: featuresArr.join('\n'),
            };

            if (type === 'trial') {
                payload.trial_days = parseInt(f.querySelector('[name="trial_days"]').value) || 7;
                payload.alert_hours_before = parseInt(f.querySelector('[name="alert_hours_before"]').value) || 48;
            } else if (type === 'subscription') {
                payload.price_per_month = f.querySelector('[name="price_per_month"]').value || '0';
                payload.price_per_year  = f.querySelector('[name="price_per_year"]').value || '0';
                payload.min_months      = parseInt(f.querySelector('[name="min_months"]').value) || 1;
                payload.max_months      = parseInt(f.querySelector('[name="max_months"]').value) || 60;
            } else if (type === 'lifetime') {
                payload.lifetime_price = f.querySelector('[name="lifetime_price"]').value || '0';
            }

            try {
                var res = await API.post('admin/package-save', payload);
                toast(res.message || 'Package saved', 'success');
                if (res.data && res.data.package_id) {
                    f.dataset.pkgId = res.data.package_id;
                    f.querySelector('.pkg-delete-btn').style.display = 'inline-flex';
                }
            } catch (err) {
                toast(err.message || 'Save failed', 'error');
            } finally {
                btnLoading(submitBtn, false);
            }
        });
    });

    pkgForms.forEach(function (f) {
        var delBtn = f.querySelector('.pkg-delete-btn');
        if (!delBtn) return;
        delBtn.addEventListener('click', async function () {
            var pkgId = f.dataset.pkgId;
            if (!pkgId || pkgId === '0') return;
            if (!confirm('এই package টি delete করবেন?')) return;
            btnLoading(delBtn, true);
            try {
                await API.post('admin/package-delete', { id: pkgId });
                toast('Package deleted', 'success');
                resetPkgForm(f);
            } catch (err) {
                toast(err.message || 'Delete failed', 'error');
            } finally {
                btnLoading(delBtn, false);
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>