<?php
/**
 * admin/notifications.php — Admin Notifications
 * List + mark read + mark all read + delete + clear read + filters
 */

$admin_page_title = 'Notifications';
$admin_active     = 'notifications';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Actions (POST) ---------- */
$success = '';
$error   = '';
$action  = input('action', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(input('csrf_token'))) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $notifId = (int) input('notif_id', 0);

        try {
            if ($action === 'read' && $notifId > 0) {
                $pdo->prepare("
                    UPDATE notifications SET is_read = 1
                    WHERE id = ? AND user_type = 'admin'
                ")->execute([$notifId]);
                $success = 'Marked as read.';
            } elseif ($action === 'read_all') {
                $pdo->prepare("
                    UPDATE notifications SET is_read = 1
                    WHERE user_type = 'admin' AND is_read = 0
                ")->execute();
                $success = 'All notifications marked as read.';
            } elseif ($action === 'delete' && $notifId > 0) {
                $pdo->prepare("
                    DELETE FROM notifications
                    WHERE id = ? AND user_type = 'admin'
                ")->execute([$notifId]);
                $success = 'Notification deleted.';
            } elseif ($action === 'clear_read') {
                $pdo->prepare("
                    DELETE FROM notifications
                    WHERE user_type = 'admin' AND is_read = 1
                ")->execute();
                $success = 'Read notifications cleared.';
            }
        } catch (Exception $ex) {
            $error = 'Could not complete the action.';
        }
    }
}

/* ---------- Filters ---------- */
$filter  = input('filter', 'all'); // all | unread
$page    = max(1, (int) input('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

/* ---------- Where ---------- */
$where  = "user_type = 'admin'";
if ($filter === 'unread') $where .= " AND is_read = 0";

/* ---------- Counts ---------- */
$totalStmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_type = 'admin'");
$totalAll  = (int) $totalStmt->fetchColumn();

$unreadStmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_type = 'admin' AND is_read = 0");
$unreadTotal = (int) $unreadStmt->fetchColumn();

/* ---------- Filtered count + fetch ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $where");
$cStmt->execute();
$total = (int) $cStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT id, title, message, link, icon, is_read, created_at
    FROM notifications
    WHERE $where
    ORDER BY id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute();
$notifications = $stmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- Helpers ---------- */
function notif_icon(string $type): string {
    switch ($type) {
        case 'order':   return '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>';
        case 'payment': return '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>';
        case 'message': return '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>';
        case 'success': return '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';
        case 'warning': return '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>';
        case 'star':    return '<polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18.5 5.5 22 7 14.5 2 9.5 9 9"/>';
        default:        return '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>';
    }
}
function notif_url(string $link): string {
    if (preg_match('#^https?://#i', $link)) return $link;
    // admin/ link হলে admin_url, নাহলে base_url
    if (strpos($link, 'admin/') === 0) return base_url($link);
    return base_url($link);
}
function notif_url_with(array $override): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('notifications.php') . ($p ? '?' . http_build_query($p) : '');
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Notifications</span>
        </nav>
        <h1 class="admin-page-title">Notifications</h1>
        <p class="admin-page-sub">
            <?php if ($unreadTotal > 0): ?>
                <strong style="color:var(--primary);"><?= (int) $unreadTotal ?> unread</strong> • <?= (int) $totalAll ?> total
            <?php else: ?>
                <?= (int) $totalAll ?> total
            <?php endif; ?>
        </p>
    </div>
    <?php if ($totalAll > 0): ?>
        <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="read_all">
            <button type="submit" class="admin-btn admin-btn-ghost" <?= $unreadTotal === 0 ? 'disabled' : '' ?>>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Mark all read
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= e($success) ?></span>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<!-- ============ TABS ============ -->
<div class="admin-tabs" style="margin-bottom:16px;">
    <a href="<?= e(notif_url_with(['filter' => null, 'page' => null])) ?>"
       class="admin-tab<?= $filter === 'all' ? ' active' : '' ?>">
        All <span style="opacity:.7;font-size:11px;">(<?= (int) $totalAll ?>)</span>
    </a>
    <a href="<?= e(notif_url_with(['filter' => 'unread', 'page' => null])) ?>"
       class="admin-tab<?= $filter === 'unread' ? ' active' : '' ?>">
        Unread <span style="opacity:.7;font-size:11px;">(<?= (int) $unreadTotal ?>)</span>
    </a>
</div>

<!-- ============ LIST ============ -->
<?php if (empty($notifications)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <h3>No notifications</h3>
            <p><?= $filter === 'unread' ? 'All caught up!' : 'Notifications will appear here.' ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="admin-card" style="padding:0;overflow:hidden;">
        <?php foreach ($notifications as $n):
            $isUnread = !$n['is_read'];
            $iconType = $n['icon'] ?: 'info';
        ?>
            <div style="display:flex;gap:16px;padding:16px 20px;border-bottom:1px solid var(--border);align-items:flex-start;<?= $isUnread ? 'background:var(--primary-soft);' : '' ?>">

                <!-- Icon -->
                <span style="width:40px;height:40px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:<?= $isUnread ? 'var(--primary)' : 'var(--bg-alt)' ?>;color:<?= $isUnread ? '#fff' : 'var(--text-muted)' ?>;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= notif_icon($iconType) ?></svg>
                </span>

                <!-- Body -->
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                        <strong style="font-size:14px;color:var(--navy);"><?= e($n['title']) ?></strong>
                        <?php if ($isUnread): ?>
                            <span class="tag tag-primary" style="font-size:10px;padding:2px 6px;">NEW</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($n['message'])): ?>
                        <p style="font-size:13.5px;color:var(--text-muted);margin:0 0 8px;line-height:1.55;">
                            <?= e($n['message']) ?>
                        </p>
                    <?php endif; ?>

                    <div style="display:flex;align-items:center;gap:16px;font-size:12px;color:var(--text-light);flex-wrap:wrap;">
                        <span><?= e(time_ago($n['created_at'])) ?></span>

                        <?php if (!empty($n['link'])): ?>
                            <a href="<?= e(notif_url($n['link'])) ?>" style="color:var(--primary);font-weight:600;">
                                View →
                            </a>
                        <?php endif; ?>

                        <?php if ($isUnread): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="read">
                                <input type="hidden" name="notif_id" value="<?= (int) $n['id'] ?>">
                                <button type="submit" style="background:none;border:none;padding:0;color:var(--primary);font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;">
                                    Mark read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Delete -->
                <form method="post" onsubmit="return confirm('Delete this notification?');" style="flex-shrink:0;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="notif_id" value="<?= (int) $n['id'] ?>">
                    <button type="submit" aria-label="Delete"
                            class="admin-btn admin-btn-ghost admin-btn-icon" style="color:var(--danger);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Clear read -->
    <?php if ($filter === 'all' && ($totalAll - $unreadTotal) > 0): ?>
        <div style="display:flex;justify-content:flex-end;margin-top:16px;">
            <form method="post" onsubmit="return confirm('Clear all read notifications?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="clear_read">
                <button type="submit" class="admin-btn admin-btn-ghost">Clear read notifications</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="admin-pagination">
            <?php if ($page > 1): ?><a href="<?= e(notif_url_with(['page' => $page - 1])) ?>">←</a><?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= e(notif_url_with(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(notif_url_with(['page' => $page + 1])) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>