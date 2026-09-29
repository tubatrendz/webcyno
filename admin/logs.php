<?php
/**
 * admin/logs.php — Activity Log Viewer
 */
$admin_page_title = 'Activity Log';
$admin_active     = 'logs';
require_once __DIR__ . '/includes/admin-header.php';

$page    = max(1, (int) input('page', 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;
$action  = trim((string) input('action', ''));

$where  = "1=1";
$params = [];
if ($action !== '') {
    $where .= " AND action LIKE ?";
    $params[] = '%' . $action . '%';
}

$cStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE $where");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT l.*, a.name AS admin_name, a.email AS admin_email
    FROM activity_logs l
    LEFT JOIN admins a ON a.id = l.admin_id
    WHERE $where
    ORDER BY l.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $perPage));

function log_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('logs.php') . ($p ? '?' . http_build_query($p) : '');
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Activity Log</span>
        </nav>
        <h1 class="admin-page-title">Activity Log</h1>
        <p class="admin-page-sub"><?= number_format($total) ?> log entries</p>
    </div>
</div>

<form class="admin-filter-bar" method="get" action="<?= e(admin_url('logs.php')) ?>">
    <input type="search" name="action" value="<?= e($action) ?>" class="form-control" placeholder="Filter by action..." style="min-width:240px;">
    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Filter</button>
    <a href="<?= e(admin_url('logs.php')) ?>" class="admin-btn admin-btn-ghost admin-btn-sm">Reset</a>
</form>

<?php if (empty($logs)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <h3>No activity logs</h3>
            <p>Activities will appear here as you use the admin panel.</p>
        </div>
    </div>
<?php else: ?>
    <div class="admin-table-wrap">
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:60px;">ID</th>
                        <th>Action</th>
                        <th>Admin</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>IP</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td class="admin-cell-sub">#<?= (int) $l['id'] ?></td>
                            <td>
                                <span class="tag tag-primary" style="font-size:11px;"><?= e($l['action']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($l['admin_name'])): ?>
                                    <div class="admin-cell-title" style="font-size:13px;"><?= e($l['admin_name']) ?></div>
                                    <div class="admin-cell-sub"><?= e($l['admin_email']) ?></div>
                                <?php else: ?>
                                    <span class="admin-cell-sub">System</span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-cell-sub">
                                <?= e($l['entity'] ?? '—') ?>
                                <?php if (!empty($l['entity_id'])): ?>
                                    <strong>#<?= (int) $l['entity_id'] ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="admin-cell-sub" style="max-width:320px;">
                                <?= e(str_limit($l['details'] ?? '—', 80)) ?>
                            </td>
                            <td class="admin-cell-sub"><?= e($l['ip_address'] ?? '—') ?></td>
                            <td class="admin-cell-sub"><?= e(date('M d, Y g:i A', strtotime($l['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="admin-pagination">
            <?php if ($page > 1): ?><a href="<?= e(log_url(['page' => $page - 1])) ?>">←</a><?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= e(log_url(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(log_url(['page' => $page + 1])) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>