<?php
/**
 * user/notifications.php — User Notifications (v2.0)
 * Broadcast messages + system notifications
 */

$user_page_title = 'Notifications';
require_once __DIR__ . '/../includes/header.php';

global $pdo;

/* ---------- Auth ---------- */
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . base_url('login.php?redirect=' . urlencode('user/notifications.php')));
    exit;
}

/* ---------- Filters ---------- */
$filter  = trim((string) input('filter', 'all'));
$page    = max(1, (int) input('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

/* ---------- Where ---------- */
$where  = ["user_type = 'user' AND user_id = ?"];
$params = [$userId];

if ($filter === 'unread') {
    $where[] = "is_read = 0";
} elseif ($filter === 'read') {
    $where[] = "is_read = 1";
}
$whereSql = implode(' AND ', $where);

/* ---------- Count total ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Count unread (for filter badge) ---------- */
$uStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_type='user' AND user_id=? AND is_read=0");
$uStmt->execute([$userId]);
$unreadTotal = (int) $uStmt->fetchColumn();

/* ---------- Fetch notifications ---------- */
$nStmt = $pdo->prepare("
    SELECT id, title, message, link, icon, is_read, created_at
    FROM notifications
    WHERE $whereSql
    ORDER BY id DESC
    LIMIT $perPage OFFSET $offset
");
$nStmt->execute($params);
$notifications = $nStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- Icon helper ---------- */
function notif_icon(string $icon, string $title): array {
    $map = [
        'info'    => ['🔔', 'linear-gradient(135deg,#3B82F6,#2563EB)'],
        'success' => ['✅', 'linear-gradient(135deg,#10B981,#059669)'],
        'warning' => ['⚠️', 'linear-gradient(135deg,#F59E0B,#D97706)'],
        'error'   => ['❌', 'linear-gradient(135deg,#EF4444,#DC2626)'],
        'order'   => ['📦', 'linear-gradient(135deg,#8B5CF6,#7C3AED)'],
        'chat'    => ['💬', 'linear-gradient(135deg,#06B6D4,#0891B2)'],
        'promo'   => ['🎉', 'linear-gradient(135deg,#EC4899,#DB2777)'],
        'verify'  => ['✉️', 'linear-gradient(135deg,#3B82F6,#2563EB)'],
    ];
    if (isset($map[$icon])) return $map[$icon];

    // Auto-detect from title keywords
    $t = mb_strtolower($title);
    if (strpos($t, 'welcome') !== false || strpos($t, 'verified') !== false) return $map['success'];
    if (strpos($t, 'order') !== false)   return $map['order'];
    if (strpos($t, 'trial') !== false)   return $map['warning'];
    if (strpos($t, 'chat') !== false || strpos($t, 'reply') !== false) return $map['chat'];
    if (strpos($t, 'password') !== false) return $map['warning'];

    return $map['info'];
}

/* ---------- Filter URL ---------- */
function notif_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return base_url('user/notifications.php') . ($p ? '?' . http_build_query($p) : '');
}
?>

<style>
.notif-tabs {
    display: flex;
    gap: 6px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 20px;
    padding-bottom: 2px;
}
.notif-tab {
    padding: 10px 16px;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text-muted);
    text-decoration: none;
    border-radius: 8px 8px 0 0;
    border-bottom: 2px solid transparent;
    margin-bottom: -3px;
    transition: all .15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.notif-tab:hover { color: var(--primary); }
.notif-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.notif-tab .cnt {
    display: inline-block;
    background: var(--bg-alt);
    color: var(--text-muted);
    font-size: 10.5px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}
.notif-tab.active .cnt { background: var(--primary-soft); color: var(--primary); }
.notif-tab .cnt.alert { background: #EF4444; color: #fff; }

/* Notification item */
.notif-item {
    display: flex;
    gap: 14px;
    padding: 16px 18px;
    background: #fff;
    border-bottom: 1px solid var(--border);
    align-items: flex-start;
    transition: background .12s;
    position: relative;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #F8FAFC; }
.notif-item.unread { background: linear-gradient(90deg, #EFF6FF, #F8FAFC 30%); }
.notif-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--primary);
}
.notif-item.unread:hover { background: linear-gradient(90deg, #DBEAFE, #F1F5F9 30%); }

.notif-icon-box {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    color: #fff;
    box-shadow: 0 4px 10px -3px rgba(0,0,0,.15);
}

.notif-body { flex: 1; min-width: 0; }
.notif-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 4px;
    line-height: 1.35;
}
.notif-message {
    font-size: 13px;
    color: #475569;
    line-height: 1.55;
    margin-bottom: 6px;
    white-space: pre-wrap;
    word-break: break-word;
}
.notif-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11.5px;
    color: var(--text-muted);
    flex-wrap: wrap;
}
.notif-meta a {
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
}
.notif-meta a:hover { text-decoration: underline; }

.notif-actions {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
    align-self: flex-start;
    opacity: 0;
    transition: opacity .15s;
}
.notif-item:hover .notif-actions { opacity: 1; }
.notif-action-btn {
    width: 30px; height: 30px;
    border: 1px solid var(--border);
    background: #fff;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    transition: all .12s;
}
.notif-action-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: #EFF6FF;
}
.notif-action-btn.danger:hover {
    border-color: var(--danger);
    color: var(--danger);
    background: #FEF2F2;
}

/* Empty state */
.notif-empty {
    text-align: center;
    padding: 60px 20px;
}
.notif-empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: .35;
}

@media (max-width: 640px) {
    .notif-item { padding: 14px 12px; gap: 10px; }
    .notif-icon-box { width: 38px; height: 38px; font-size: 18px; }
    .notif-actions { opacity: 1; }
}
</style>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <a href="<?= e(base_url('user/index.php')) ?>">Dashboard</a>
            <span class="sep">/</span>
            <span class="current">Notifications</span>
        </nav>

        <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;margin-bottom:var(--space-lg);">
            <div>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:4px;">🔔 <?= e(t('notifications', 'Notifications')) ?></h1>
                <p class="text-muted">
                    <?= number_format($total) ?> notification(s)
                    <?php if ($unreadTotal > 0): ?>
                        • <strong style="color:var(--primary);"><?= (int)$unreadTotal ?> unread</strong>
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($unreadTotal > 0): ?>
                <button type="button" class="btn btn-soft btn-sm" id="btn-mark-all">
                    ✓ Mark all as read
                </button>
            <?php endif; ?>
        </div>

        <!-- Filter tabs -->
        <nav class="notif-tabs">
            <a class="notif-tab<?= $filter === 'all'    ? ' active' : '' ?>" href="<?= e(notif_url(['filter' => null, 'page' => null])) ?>">
                All
            </a>
            <a class="notif-tab<?= $filter === 'unread' ? ' active' : '' ?>" href="<?= e(notif_url(['filter' => 'unread', 'page' => null])) ?>">
                Unread
                <?php if ($unreadTotal > 0): ?>
                    <span class="cnt alert"><?= $unreadTotal ?></span>
                <?php endif; ?>
            </a>
            <a class="notif-tab<?= $filter === 'read'   ? ' active' : '' ?>" href="<?= e(notif_url(['filter' => 'read', 'page' => null])) ?>">
                Read
            </a>
        </nav>

        <?php if (empty($notifications)): ?>
            <div class="card notif-empty">
                <div class="notif-empty-icon">🔕</div>
                <h3 style="margin:0 0 8px;color:var(--navy);">
                    <?= $filter === 'unread' ? 'No unread notifications' : 'No notifications yet' ?>
                </h3>
                <p class="text-muted" style="margin:0 0 16px;">
                    <?= $filter === 'unread' ? 'All caught up! 🎉' : 'When something happens on your account, you\'ll see it here.' ?>
                </p>
                <?php if ($filter !== 'all'): ?>
                    <a class="btn btn-primary btn-sm" href="<?= e(base_url('user/notifications.php')) ?>">
                        View All
                    </a>
                <?php else: ?>
                    <a class="btn btn-primary btn-sm" href="<?= e(base_url('services.php')) ?>">
                        Browse Services
                    </a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <div class="card" style="padding:0;overflow:hidden;">
                <?php foreach ($notifications as $n):
                    $iconData = notif_icon($n['icon'] ?? 'info', $n['title']);
                    $iconEmoji = $iconData[0];
                    $iconGrad  = $iconData[1];
                    $isUnread  = (int)$n['is_read'] === 0;

                    // Build final URL
                    $finalUrl = null;
                    if (!empty($n['link'])) {
                        if (preg_match('#^https?://#i', $n['link'])) {
                            $finalUrl = $n['link'];
                        } else {
                            $finalUrl = base_url(ltrim($n['link'], '/'));
                        }
                    }
                ?>
                <div class="notif-item<?= $isUnread ? ' unread' : '' ?>" data-notif-id="<?= (int)$n['id'] ?>">

                    <div class="notif-icon-box" style="background:<?= e($iconGrad) ?>;">
                        <?= $iconEmoji ?>
                    </div>

                    <div class="notif-body">
                        <div class="notif-title"><?= e($n['title']) ?></div>
                        <div class="notif-message"><?= nl2br(e($n['message'])) ?></div>
                        <div class="notif-meta">
                            <span>🕒 <?= e(time_ago($n['created_at'])) ?></span>
                            <?php if ($finalUrl): ?>
                                <a href="<?= e($finalUrl) ?>">
                                    View →
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="notif-actions">
                        <?php if ($isUnread): ?>
                            <button type="button" class="notif-action-btn"
                                    data-action="read"
                                    data-notif-id="<?= (int)$n['id'] ?>"
                                    title="Mark as read">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="notif-action-btn danger"
                                data-action="delete"
                                data-notif-id="<?= (int)$n['id'] ?>"
                                title="Delete">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav style="display:flex;justify-content:center;gap:6px;margin-top:var(--space-xl);flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(notif_url(['page' => $page - 1])) ?>">← Prev</a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a class="btn <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm" href="<?= e(notif_url(['page' => $i])) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(notif_url(['page' => $page + 1])) ?>">Next →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var API_URL = '/api/user/notifications';

    /* ---------- Mark single as read ---------- */
    document.querySelectorAll('[data-action="read"]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var id = this.dataset.notifId;
            btnLoading(this, true);
            try {
                await API.post('user/notifications', { action: 'read', notif_id: id });
                var item = document.querySelector('[data-notif-id="' + id + '"]');
                if (item) {
                    item.classList.remove('unread');
                    var actBtn = item.querySelector('[data-action="read"]');
                    if (actBtn) actBtn.remove();
                }
                toast('Marked as read', 'success');
                updateUnreadBadge();
            } catch (err) {
                toast(err.message || 'Failed', 'error');
                btnLoading(this, false);
            }
        });
    });

    /* ---------- Delete single ---------- */
    document.querySelectorAll('[data-action="delete"]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this notification?')) return;
            var id = this.dataset.notifId;
            var item = document.querySelector('[data-notif-id="' + id + '"]');
            btnLoading(this, true);
            try {
                await API.post('user/notifications', { action: 'delete', notif_id: id });
                if (item) {
                    item.style.opacity = '0';
                    item.style.transition = 'opacity .25s';
                    setTimeout(function () {
                        item.remove();
                        updateUnreadBadge();
                    }, 260);
                }
                toast('Deleted', 'success');
            } catch (err) {
                toast(err.message || 'Failed', 'error');
                btnLoading(this, false);
            }
        });
    });

    /* ---------- Mark all as read ---------- */
    document.getElementById('btn-mark-all')?.addEventListener('click', async function () {
        if (!confirm('Mark all notifications as read?')) return;
        btnLoading(this, true);
        try {
            await API.post('user/notifications', { action: 'read_all' });
            toast('All marked as read', 'success');
            document.querySelectorAll('.notif-item.unread').forEach(function (el) {
                el.classList.remove('unread');
                var btn = el.querySelector('[data-action="read"]');
                if (btn) btn.remove();
            });
            var btnAll = document.getElementById('btn-mark-all');
            if (btnAll) btnAll.remove();
            updateUnreadBadge();
        } catch (err) {
            toast(err.message || 'Failed', 'error');
            btnLoading(this, false);
        }
    });

    /* ---------- Update nav badge ---------- */
    function updateUnreadBadge() {
        var badge = document.querySelector('.nav-notif-badge');
        if (badge) {
            var count = document.querySelectorAll('.notif-item.unread').length;
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>