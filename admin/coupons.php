<?php
/**
 * admin/coupons.php — Coupons Listing + Add/Edit Modal + Delete
 * Backend: api/admin/coupon-save.php, api/admin/coupon-delete.php
 */

$admin_page_title = 'Coupons';
$admin_active     = 'coupons';
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
    $where[] = "(code LIKE ?)";
    $params[] = $like;
}
if ($status === 'active') {
    $where[] = "status = 1 AND (end_date IS NULL OR end_date >= CURDATE())";
} elseif ($status === 'inactive') {
    $where[] = "status = 0";
} elseif ($status === 'expired') {
    $where[] = "end_date IS NOT NULL AND end_date < CURDATE()";
}
$whereSql = implode(' AND ', $where);

/* ---------- Counts ---------- */
$tabCounts = ['all' => 0, 'active' => 0, 'expired' => 0, 'inactive' => 0];
try {
    $row = $pdo->query("
        SELECT
            COUNT(*) AS all_c,
            SUM(status = 1 AND (end_date IS NULL OR end_date >= CURDATE())) AS active_c,
            SUM(end_date IS NOT NULL AND end_date < CURDATE()) AS expired_c,
            SUM(status = 0) AS inactive_c
        FROM coupons
    ")->fetch();
    $tabCounts['all']      = (int) ($row['all_c'] ?? 0);
    $tabCounts['active']   = (int) ($row['active_c'] ?? 0);
    $tabCounts['expired']  = (int) ($row['expired_c'] ?? 0);
    $tabCounts['inactive'] = (int) ($row['inactive_c'] ?? 0);
} catch (Exception $e) { /* silent */ }

/* ---------- Filtered count ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch ---------- */
$listStmt = $pdo->prepare("
    SELECT * FROM coupons
    WHERE $whereSql
    ORDER BY id DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$coupons = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- URL helper ---------- */
function cp_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('coupons.php') . ($p ? '?' . http_build_query($p) : '');
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Coupons</span>
        </nav>
        <h1 class="admin-page-title">Coupons</h1>
        <p class="admin-page-sub"><?= number_format($total) ?> coupon(s)</p>
    </div>
    <button type="button" class="admin-btn admin-btn-primary" id="btn-add-coupon">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Coupon
    </button>
</div>

<!-- ============ TABS ============ -->
<div class="admin-tabs" style="margin-bottom:16px;">
    <?php
    $tabs = [
        'all'      => ['label' => 'All',      'count' => $tabCounts['all'],      'filter' => null],
        'active'   => ['label' => 'Active',   'count' => $tabCounts['active'],   'filter' => 'active'],
        'expired'  => ['label' => 'Expired',  'count' => $tabCounts['expired'],  'filter' => 'expired'],
        'inactive' => ['label' => 'Inactive', 'count' => $tabCounts['inactive'], 'filter' => 'inactive'],
    ];
    foreach ($tabs as $key => $tab):
        $isActive = ($tab['filter'] === null && $status === '') || ($tab['filter'] !== null && $status === $tab['filter']);
    ?>
        <a href="<?= e(cp_url(['status' => $tab['filter'], 'page' => null])) ?>" class="admin-tab<?= $isActive ? ' active' : '' ?>">
            <?= e($tab['label']) ?>
            <span style="opacity:.7;font-size:11px;">(<?= (int) $tab['count'] ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<!-- ============ FILTER ============ -->
<form class="admin-filter-bar" method="get" action="<?= e(admin_url('coupons.php')) ?>">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search coupon code..." style="min-width:220px;">
    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Search</button>
    <a href="<?= e(admin_url('coupons.php')) ?>" class="admin-btn admin-btn-ghost admin-btn-sm">Reset</a>
</form>

<!-- ============ TABLE ============ -->
<?php if (empty($coupons)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            <h3>No coupons yet</h3>
            <p>Create your first discount coupon to boost sales.</p>
            <button type="button" class="admin-btn admin-btn-primary" style="margin-top:16px;" onclick="document.getElementById('btn-add-coupon').click();">
                Create Coupon
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="admin-table-wrap">
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Min Order</th>
                        <th>Usage</th>
                        <th>Validity</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $c):
                        $isExpired = !empty($c['end_date']) && $c['end_date'] < date('Y-m-d');
                        $isFull    = !empty($c['usage_limit']) && (int) $c['used_count'] >= (int) $c['usage_limit'];
                        $isActive  = (int) $c['status'] === 1 && !$isExpired && !$isFull;
                    ?>
                        <tr>
                            <td>
                                <code style="background:var(--primary-soft);color:var(--primary);padding:3px 10px;border-radius:6px;font-weight:700;font-size:12px;"><?= e($c['code']) ?></code>
                            </td>
                            <td>
                                <?php if ($c['type'] === 'percent'): ?>
                                    <strong><?= (float) $c['value'] ?>%</strong>
                                    <?php if (!empty($c['max_discount'])): ?>
                                        <div class="admin-cell-sub">max <?= e(money((float) $c['max_discount'])) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <strong><?= e(money((float) $c['value'])) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((float) $c['min_order'] > 0): ?>
                                    <?= e(money((float) $c['min_order'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= (int) $c['used_count'] ?></strong>
                                <?php if (!empty($c['usage_limit'])): ?>
                                    <span class="text-muted">/ <?= (int) $c['usage_limit'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">/ ∞</span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-cell-sub">
                                <?php if (!empty($c['start_date']) || !empty($c['end_date'])): ?>
                                    <?= $c['start_date'] ? e(date('M d, Y', strtotime($c['start_date']))) : 'Now' ?>
                                    →
                                    <?= $c['end_date'] ? e(date('M d, Y', strtotime($c['end_date']))) : 'No expiry' ?>
                                <?php else: ?>
                                    No dates set
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="tag tag-success">Active</span>
                                <?php elseif ($isExpired): ?>
                                    <span class="tag tag-warning">Expired</span>
                                <?php elseif ($isFull): ?>
                                    <span class="tag tag-warning">Limit reached</span>
                                <?php else: ?>
                                    <span class="tag tag-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="cell-actions">
                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" title="Copy"
                                            data-copy="<?= e($c['code']) ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    </button>
                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon"
                                            data-edit-coupon='<?= e(json_encode([
                                                "id"            => (int) $c["id"],
                                                "code"          => $c["code"],
                                                "type"          => $c["type"],
                                                "value"         => (float) $c["value"],
                                                "min_order"     => (float) $c["min_order"],
                                                "max_discount"  => $c["max_discount"] !== null ? (float) $c["max_discount"] : "",
                                                "usage_limit"   => $c["usage_limit"] !== null ? (int) $c["usage_limit"] : "",
                                                "per_user_limit"=> (int) $c["per_user_limit"],
                                                "start_date"    => $c["start_date"],
                                                "end_date"      => $c["end_date"],
                                                "status"        => (int) $c["status"],
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
                                            title="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" style="color:var(--danger);"
                                            data-confirm-delete
                                            data-url="admin/coupons/delete"
                                            data-message="Delete this coupon?"
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

    <?php if ($totalPages > 1): ?>
        <nav class="admin-pagination">
            <?php if ($page > 1): ?><a href="<?= e(cp_url(['page' => $page - 1])) ?>">←</a><?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= e(cp_url(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(cp_url(['page' => $page + 1])) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- ============ ADD / EDIT MODAL ============ -->
<div id="coupon-modal" class="admin-modal">
    <div class="admin-modal-backdrop" data-modal-close></div>
    <div class="admin-modal-card" style="max-width:640px;">
        <div class="admin-modal-header">
            <h3 class="admin-card-title" id="coupon-modal-title">Add Coupon</h3>
            <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" data-modal-close aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="coupon-form" data-ajax data-endpoint="admin/coupons/save" data-redirect="admin/coupons.php"
              style="padding:20px;max-height:70vh;overflow-y:auto;">

            <input type="hidden" name="id" id="k-id" value="0">

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Code <span class="required">*</span></label>
                    <input type="text" name="code" id="k-code" class="form-control"
                           required maxlength="50" placeholder="e.g. SAVE20"
                           style="text-transform:uppercase;font-weight:700;letter-spacing:.5px;">
                    <p class="admin-help">Uppercase, no spaces.</p>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Type <span class="required">*</span></label>
                    <select name="type" id="k-type" class="form-control" required>
                        <option value="fixed">Fixed (৳ amount off)</option>
                        <option value="percent">Percent (% off)</option>
                    </select>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Value <span class="required">*</span></label>
                    <input type="text" name="value" id="k-value" class="form-control" data-numeric required placeholder="e.g. 20">
                    <p class="admin-help" id="k-value-hint">Amount in default currency, বা %</p>
                </div>
                <div class="admin-form-group" id="k-max-discount-group">
                    <label class="admin-label">Max Discount</label>
                    <input type="text" name="max_discount" id="k-max_discount" class="form-control" data-numeric placeholder="0 = no cap">
                    <p class="admin-help">Percent coupon-এর জন্য cap (optional)</p>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Min Order</label>
                    <input type="text" name="min_order" id="k-min_order" class="form-control" data-numeric placeholder="0">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Usage Limit</label>
                    <input type="text" name="usage_limit" id="k-usage_limit" class="form-control" data-numeric placeholder="Empty = unlimited">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Per-User Limit</label>
                    <input type="text" name="per_user_limit" id="k-per_user_limit" class="form-control" data-numeric value="1">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Start Date</label>
                    <input type="date" name="start_date" id="k-start_date" class="form-control">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">End Date</label>
                    <input type="date" name="end_date" id="k-end_date" class="form-control">
                    <p class="admin-help">খালি রাখলে no expiry</p>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="status" id="k-status" value="1" checked>
                    <span class="admin-switch-track"></span>
                    <span>Active</span>
                </label>
            </div>

            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Save Coupon</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('coupon-modal');
    var form  = document.getElementById('coupon-form');
    var title = document.getElementById('coupon-modal-title');

    function openModal(data) {
        if (data && data.id) {
            title.textContent = 'Edit Coupon';
            form.querySelector('#k-id').value             = data.id;
            form.querySelector('#k-code').value           = data.code || '';
            form.querySelector('#k-type').value           = data.type || 'fixed';
            form.querySelector('#k-value').value          = data.value || 0;
            form.querySelector('#k-max_discount').value   = data.max_discount || '';
            form.querySelector('#k-min_order').value      = data.min_order || 0;
            form.querySelector('#k-usage_limit').value    = data.usage_limit || '';
            form.querySelector('#k-per_user_limit').value = data.per_user_limit || 1;
            form.querySelector('#k-start_date').value     = data.start_date || '';
            form.querySelector('#k-end_date').value       = data.end_date || '';
            form.querySelector('#k-status').checked       = !!data.status;
        } else {
            title.textContent = 'Add Coupon';
            form.reset();
            form.querySelector('#k-id').value = 0;
            form.querySelector('#k-per_user_limit').value = 1;
            form.querySelector('#k-status').checked = true;
        }
        toggleMaxDiscount();
        modal.classList.add('open');
    }

    function closeModal() { modal.classList.remove('open'); }

    function toggleMaxDiscount() {
        var type = form.querySelector('#k-type').value;
        var grp  = document.getElementById('k-max-discount-group');
        grp.style.display = (type === 'percent') ? '' : 'none';
        document.getElementById('k-value-hint').textContent = (type === 'percent')
            ? 'Percentage (e.g. 20 = 20% off)'
            : 'Fixed amount in default currency';
    }

    document.getElementById('btn-add-coupon')?.addEventListener('click', function () { openModal(null); });

    document.querySelectorAll('[data-edit-coupon]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { openModal(JSON.parse(btn.getAttribute('data-edit-coupon'))); }
            catch (e) { console.error(e); }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    form.querySelector('#k-type')?.addEventListener('change', toggleMaxDiscount);
    form.querySelector('#k-code')?.addEventListener('input', function () {
        this.value = this.value.toUpperCase().replace(/\s+/g, '');
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>