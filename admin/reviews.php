<?php
/**
 * admin/reviews.php — Reviews Moderation
 * Features: status tabs, filters (service, rating, user), approve/reject/delete actions
 * Backend: api/admin/review-action.php (approve | reject | delete)
 */

$admin_page_title = 'Reviews';
$admin_active     = 'reviews';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Filters ---------- */
$q        = trim((string) input('q', ''));
$status   = trim((string) input('status', ''));
$rating   = (int) input('rating', 0);
$page     = max(1, (int) input('page', 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$allowedStatus = ['pending', 'approved', 'rejected'];

/* ---------- Where ---------- */
$where  = ["1=1"];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR r.comment LIKE ? OR s.title_en LIKE ?)";
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '' && in_array($status, $allowedStatus, true)) {
    $where[]  = "r.status = ?";
    $params[] = $status;
}
if ($rating >= 1 && $rating <= 5) {
    $where[]  = "r.rating = ?";
    $params[] = $rating;
}
$whereSql = implode(' AND ', $where);

/* ---------- Counts (tabs) ---------- */
$tabCounts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $row = $pdo->query("
        SELECT
            COUNT(*) AS all_c,
            SUM(status = 'pending')  AS pending_c,
            SUM(status = 'approved') AS approved_c,
            SUM(status = 'rejected') AS rejected_c
        FROM reviews
    ")->fetch();
    $tabCounts['all']      = (int) ($row['all_c']      ?? 0);
    $tabCounts['pending']  = (int) ($row['pending_c']  ?? 0);
    $tabCounts['approved'] = (int) ($row['approved_c'] ?? 0);
    $tabCounts['rejected'] = (int) ($row['rejected_c'] ?? 0);
} catch (Exception $e) { /* silent */ }

/* ---------- Filtered count ---------- */
$cStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reviews r
    LEFT JOIN users u    ON u.id = r.user_id
    LEFT JOIN services s ON s.id = r.service_id
    WHERE $whereSql
");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch ---------- */
$listStmt = $pdo->prepare("
    SELECT r.id, r.rating, r.comment, r.status, r.created_at,
           u.id AS user_id, u.name AS user_name, u.email AS user_email, u.avatar AS user_avatar,
           s.id AS service_id, s.title_en AS service_title_en, s.title_bn AS service_title_bn,
           s.slug AS service_slug, s.thumbnail AS service_thumbnail
    FROM reviews r
    LEFT JOIN users u    ON u.id = r.user_id
    LEFT JOIN services s ON s.id = r.service_id
    WHERE $whereSql
    ORDER BY (r.status = 'pending') DESC, r.id DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$reviews = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- URL helper ---------- */
function rev_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('reviews.php') . ($p ? '?' . http_build_query($p) : '');
}

/* ---------- Status class ---------- */
function rev_status_class(string $s): string {
    return match($s) {
        'approved' => 'tag-success',
        'rejected' => 'tag-danger',
        default    => 'tag-warning',
    };
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Reviews</span>
        </nav>
        <h1 class="admin-page-title">Reviews</h1>
        <p class="admin-page-sub">
            <?= number_format($total) ?> review(s) • <?= (int) $tabCounts['pending'] ?> pending approval
        </p>
    </div>
</div>

<!-- ============ STATUS TABS ============ -->
<div class="admin-tabs" style="margin-bottom:16px;">
    <?php
    $tabs = [
        'all'      => ['label' => 'All',      'count' => $tabCounts['all'],      'filter' => null],
        'pending'  => ['label' => 'Pending',  'count' => $tabCounts['pending'],  'filter' => 'pending'],
        'approved' => ['label' => 'Approved', 'count' => $tabCounts['approved'], 'filter' => 'approved'],
        'rejected' => ['label' => 'Rejected', 'count' => $tabCounts['rejected'], 'filter' => 'rejected'],
    ];
    foreach ($tabs as $key => $tab):
        $isActive = ($tab['filter'] === null && $status === '') || ($tab['filter'] !== null && $status === $tab['filter']);
    ?>
        <a href="<?= e(rev_url(['status' => $tab['filter'], 'page' => null])) ?>"
           class="admin-tab<?= $isActive ? ' active' : '' ?>">
            <?= e($tab['label']) ?>
            <span style="opacity:.7;font-size:11px;">(<?= (int) $tab['count'] ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<!-- ============ FILTER ============ -->
<form class="admin-filter-bar" method="get" action="<?= e(admin_url('reviews.php')) ?>">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>

    <input type="search" name="q" value="<?= e($q) ?>" class="form-control"
           placeholder="Search user, comment, service..." style="min-width:240px;">

    <select name="rating" data-filter-submit>
        <option value="">All Ratings</option>
        <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= $rating === $i ? 'selected' : '' ?>>
                <?= str_repeat('★', $i) . str_repeat('☆', 5 - $i) ?>
            </option>
        <?php endfor; ?>
    </select>

    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Apply</button>
    <a href="<?= e(admin_url('reviews.php')) ?>" class="admin-btn admin-btn-ghost admin-btn-sm">Reset</a>
</form>

<!-- ============ TABLE ============ -->
<?php if (empty($reviews)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18.5 5.5 22 7 14.5 2 9.5 9 9"/>
            </svg>
            <h3>No reviews found</h3>
            <p>Reviews will appear here once customers submit them.</p>
        </div>
    </div>
<?php else: ?>
    <div class="admin-table-wrap">
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>User</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $r):
                        $stars = max(1, min(5, (int) $r['rating']));
                        $sTitle = $lang === 'bn' && !empty($r['service_title_bn'])
                            ? $r['service_title_bn'] : ($r['service_title_en'] ?? '—');
                    ?>
                        <tr>
                            <td>
                                <div style="color:var(--warning);font-size:14px;letter-spacing:1px;white-space:nowrap;">
                                    <?= str_repeat('★', $stars) . str_repeat('☆', 5 - $stars) ?>
                                </div>
                                <div class="admin-cell-sub" style="font-weight:600;"><?= (int) $r['rating'] ?>/5</div>
                            </td>
                            <td style="max-width:320px;">
                                <div style="font-size:13px;line-height:1.5;color:var(--text);">
                                    <?= e(str_limit($r['comment'] ?: '—', 120)) ?>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-sm">
                                    <span class="admin-avatar" style="width:30px;height:30px;font-size:12px;">
                                        <?= e(mb_strtoupper(mb_substr($r['user_name'] ?? 'U', 0, 1))) ?>
                                    </span>
                                    <div>
                                        <div class="admin-cell-title" style="font-size:13px;margin-bottom:2px;">
                                            <?= e(str_limit($r['user_name'] ?? 'Unknown', 22)) ?>
                                        </div>
                                        <div class="admin-cell-sub"><?= e(str_limit($r['user_email'] ?? '', 24)) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($r['service_slug'])): ?>
                                    <a href="<?= e(base_url('service-details.php?slug=' . urlencode($r['service_slug']))) ?>"
                                       target="_blank" class="admin-cell-title" style="font-size:13px;text-decoration:none;">
                                        <?= e(str_limit($sTitle, 28)) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="admin-cell-sub">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="tag <?= e(rev_status_class($r['status'])) ?>" style="text-transform:capitalize;">
                                    <?= e($r['status']) ?>
                                </span>
                            </td>
                            <td class="admin-cell-sub"><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
                            <td>
                                <div class="cell-actions">
                                    <?php if ($r['status'] !== 'approved'): ?>
                                        <button type="button" class="admin-btn admin-btn-success admin-btn-sm"
                                                data-review-action="approve"
                                                data-id="<?= (int) $r['id'] ?>"
                                                title="Approve">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            Approve
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($r['status'] !== 'rejected'): ?>
                                        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm"
                                                data-review-action="reject"
                                                data-id="<?= (int) $r['id'] ?>"
                                                title="Reject">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            Reject
                                        </button>
                                    <?php endif; ?>

                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" style="color:var(--danger);"
                                            data-confirm-delete
                                            data-url="admin/reviews/delete"
                                            data-message="Delete this review permanently?"
                                            data-id="<?= (int) $r['id'] ?>"
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
            <?php if ($page > 1): ?><a href="<?= e(rev_url(['page' => $page - 1])) ?>">←</a><?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= e(rev_url(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(rev_url(['page' => $page + 1])) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Approve / Reject actions
    document.querySelectorAll('[data-review-action]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var action = btn.getAttribute('data-review-action');
            var id     = btn.getAttribute('data-id');
            if (!id) return;
            if (!confirm('Are you sure you want to ' + action + ' this review?')) return;

            btnLoading(btn, true);
            try {
                var res = await API.post('admin/reviews/action', { id: id, action: action });
                toast(res.message || ('Review ' + action + 'd successfully.'), 'success');
                setTimeout(function () { location.reload(); }, 700);
            } catch (err) {
                toast(err.message || 'Action failed.', 'error');
                btnLoading(btn, false);
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>