<?php
/**
 * admin/categories.php — Categories Listing + Add/Edit Modal + Delete
 * Backend: api/admin/category-save.php, api/admin/category-delete.php
 */

$admin_page_title = 'Categories';
$admin_active     = 'categories';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Filters ---------- */
$q      = trim((string) input('q', ''));
$status = trim((string) input('status', ''));
$page   = max(1, (int) input('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

/* ---------- Where ---------- */
$where  = ["1=1"];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(name_en LIKE ? OR name_bn LIKE ? OR slug LIKE ?)";
    array_push($params, $like, $like, $like);
}
if ($status === 'active' || $status === 'inactive') {
    $where[]  = "status = ?";
    $params[] = $status === 'active' ? 1 : 0;
}
$whereSql = implode(' AND ', $where);

/* ---------- Count ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch ---------- */
$listStmt = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id) AS service_count
    FROM categories c
    WHERE $whereSql
    ORDER BY c.sort_order ASC, c.id DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$categories = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- URL helper ---------- */
function cat_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('categories.php') . ($p ? '?' . http_build_query($p) : '');
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Categories</span>
        </nav>
        <h1 class="admin-page-title">Categories</h1>
        <p class="admin-page-sub"><?= number_format($total) ?> category(ies)</p>
    </div>
    <button type="button" class="admin-btn admin-btn-primary" id="btn-add-category">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Category
    </button>
</div>

<!-- ============ FILTER ============ -->
<form class="admin-filter-bar" method="get" action="<?= e(admin_url('categories.php')) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search name or slug..." style="min-width:220px;">
    <select name="status" data-filter-submit>
        <option value="">All Status</option>
        <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Apply</button>
    <a href="<?= e(admin_url('categories.php')) ?>" class="admin-btn admin-btn-ghost admin-btn-sm">Reset</a>
</form>

<!-- ============ TABLE ============ -->
<?php if (empty($categories)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            <h3>No categories</h3>
            <p>Add your first category to organize services.</p>
            <button type="button" class="admin-btn admin-btn-primary" style="margin-top:16px;" onclick="document.getElementById('btn-add-category').click();">
                Add Category
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="admin-table-wrap">
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:70px;">Icon</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Services</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c):
                        $icon = !empty($c['icon']) ? base_url($c['icon']) : null;
                    ?>
                        <tr>
                            <td>
                                <?php if ($icon): ?>
                                    <img src="<?= e($icon) ?>" alt="" class="admin-thumb" style="width:40px;height:40px;object-fit:contain;background:#fff;padding:4px;">
                                <?php else: ?>
                                    <span style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;background:var(--bg-alt);border-radius:6px;color:var(--text-muted);">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-cell-title"><?= e($c['name_en']) ?></div>
                                <?php if (!empty($c['name_bn'])): ?>
                                    <div class="admin-cell-sub"><?= e($c['name_bn']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="admin-cell-sub"><code style="background:var(--bg-alt);padding:2px 6px;border-radius:4px;font-size:12px;"><?= e($c['slug']) ?></code></td>
                            <td><strong><?= (int) $c['service_count'] ?></strong></td>
                            <td class="admin-cell-sub"><?= (int) $c['sort_order'] ?></td>
                            <td>
                                <?php if ((int) $c['status'] === 1): ?>
                                    <span class="tag tag-success">Active</span>
                                <?php else: ?>
                                    <span class="tag tag-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="cell-actions">
                                    <a href="<?= e(base_url('category.php?slug=' . urlencode($c['slug']))) ?>" target="_blank"
                                       class="admin-btn admin-btn-ghost admin-btn-icon" title="View">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon"
                                            data-edit-category='<?= e(json_encode([
                                                "id"          => (int) $c["id"],
                                                "name_en"     => $c["name_en"],
                                                "name_bn"     => $c["name_bn"],
                                                "slug"        => $c["slug"],
                                                "icon"        => $c["icon"],
                                                "description_en" => $c["description_en"],
                                                "description_bn" => $c["description_bn"],
                                                "sort_order"  => (int) $c["sort_order"],
                                                "is_featured" => (int) $c["is_featured"],
                                                "status"      => (int) $c["status"],
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
                                            title="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" style="color:var(--danger);"
                                            data-confirm-delete
                                            data-url="admin/categories/delete"
                                            data-message="Delete this category? Services inside will become uncategorized."
                                            data-id="<?= (int) $c['id'] ?>"
                                            title="Delete">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
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
            <?php if ($page > 1): ?><a href="<?= e(cat_url(['page' => $page - 1])) ?>">←</a><?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= e(cat_url(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(cat_url(['page' => $page + 1])) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- ============ ADD / EDIT MODAL ============ -->
<div id="category-modal" class="admin-modal">
    <div class="admin-modal-backdrop" data-modal-close></div>
    <div class="admin-modal-card" style="max-width:640px;">
        <div class="admin-modal-header">
            <h3 class="admin-card-title" id="cat-modal-title">Add Category</h3>
            <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" data-modal-close aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="category-form" data-ajax data-endpoint="admin/categories/save" data-redirect="admin/categories.php"
              enctype="multipart/form-data" style="padding:20px;max-height:70vh;overflow-y:auto;">

            <input type="hidden" name="id" id="c-id" value="0">

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Name (EN) <span class="required">*</span></label>
                    <input type="text" name="name_en" id="c-name_en" class="form-control" required maxlength="120">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Name (BN)</label>
                    <input type="text" name="name_bn" id="c-name_bn" class="form-control" maxlength="120">
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Slug</label>
                <input type="text" name="slug" id="c-slug" class="form-control" placeholder="auto-generated if empty" maxlength="150">
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Description (EN)</label>
                    <textarea name="description_en" id="c-description_en" class="form-control" rows="2" maxlength="500"></textarea>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Description (BN)</label>
                    <textarea name="description_bn" id="c-description_bn" class="form-control" rows="2" maxlength="500"></textarea>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Sort Order</label>
                    <input type="text" name="sort_order" id="c-sort_order" class="form-control" data-numeric value="0">
                    <p class="admin-help">ছোট সংখ্যা আগে দেখাবে</p>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Status</label>
                    <select name="status" id="c-status" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Icon / Image</label>
                <div class="flex items-center gap-md">
                    <img id="c-icon-preview" src="<?= e(base_url('assets/images/placeholder.png')) ?>" alt=""
                         style="width:60px;height:60px;object-fit:contain;border-radius:8px;border:1px solid var(--border);background:#fff;padding:4px;">
                    <div style="flex:1;">
                        <input type="file" name="icon" id="c-icon" accept="image/jpeg,image/png,image/webp,image/svg+xml"
                               data-preview="#c-icon-preview" class="form-control" style="padding:6px;">
                        <p class="admin-help">JPG/PNG/WEBP/SVG — max 5MB</p>
                    </div>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="is_featured" id="c-is_featured" value="1">
                    <span class="admin-switch-track"></span>
                    <span>Show on homepage "Popular Categories"</span>
                </label>
            </div>

            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('category-modal');
    var form  = document.getElementById('category-form');
    var title = document.getElementById('cat-modal-title');

    function openModal(data) {
        if (data && data.id) {
            title.textContent = 'Edit Category';
            form.querySelector('#c-id').value              = data.id;
            form.querySelector('#c-name_en').value         = data.name_en || '';
            form.querySelector('#c-name_bn').value         = data.name_bn || '';
            form.querySelector('#c-slug').value            = data.slug || '';
            form.querySelector('#c-description_en').value  = data.description_en || '';
            form.querySelector('#c-description_bn').value  = data.description_bn || '';
            form.querySelector('#c-sort_order').value      = data.sort_order || 0;
            form.querySelector('#c-status').value          = data.status ? '1' : '0';
            form.querySelector('#c-is_featured').checked   = !!data.is_featured;
            var iconPreview = document.getElementById('c-icon-preview');
            iconPreview.src = data.icon ? '<?= e(base_url()) ?>/' + data.icon : '<?= e(base_url('assets/images/placeholder.png')) ?>';
        } else {
            title.textContent = 'Add Category';
            form.reset();
            form.querySelector('#c-id').value = 0;
            form.querySelector('#c-sort_order').value = 0;
            document.getElementById('c-icon-preview').src = '<?= e(base_url('assets/images/placeholder.png')) ?>';
        }
        modal.classList.add('open');
    }

    function closeModal() { modal.classList.remove('open'); }

    document.getElementById('btn-add-category')?.addEventListener('click', function () { openModal(null); });

    document.querySelectorAll('[data-edit-category]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { openModal(JSON.parse(btn.getAttribute('data-edit-category'))); }
            catch (e) { console.error(e); }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    // Auto-slug
    form.querySelector('#c-name_en')?.addEventListener('blur', function () {
        var slugEl = form.querySelector('#c-slug');
        if (slugEl.value.trim() !== '' || !this.value.trim()) return;
        slugEl.value = this.value.toLowerCase()
            .replace(/[^\p{L}\p{N}\s-]/gu, '')
            .replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>