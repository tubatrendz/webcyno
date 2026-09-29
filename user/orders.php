<?php
/**
 * user/orders.php — User Orders List (v3.0 with delivery + handover badges)
 */

$user_page_title = 'My Orders';
require_once __DIR__ . '/../includes/header.php';

global $pdo;

/* ---------- Auth ---------- */
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . base_url('login.php?redirect=' . urlencode('user/orders.php')));
    exit;
}

/* ---------- Filters ---------- */
$filter   = trim((string) input('filter', 'all'));
$page     = max(1, (int) input('page', 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

/* ---------- Where ---------- */
$where  = ["o.user_id = ?"];
$params = [$userId];

if ($filter === 'active') {
    $where[] = "o.order_status IN ('confirmed','processing','in_progress')";
} elseif ($filter === 'pending') {
    $where[] = "o.order_status = 'pending'";
} elseif ($filter === 'completed') {
    $where[] = "o.order_status = 'completed'";
} elseif ($filter === 'renewals') {
    $where[] = "o.is_renewal = 1";
}

$whereSql = implode(' AND ', $where);

/* ---------- Count ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch orders ---------- */
$oStmt = $pdo->prepare("
    SELECT o.*
    FROM orders o
    WHERE $whereSql
    ORDER BY o.id DESC
    LIMIT $perPage OFFSET $offset
");
$oStmt->execute($params);
$orders = $oStmt->fetchAll();

/* ---------- Fetch items ---------- */
$orderItems = [];
$orderIds = array_column($orders, 'id');
if (!empty($orderIds)) {
    $place = implode(',', array_fill(0, count($orderIds), '?'));
    $iStmt = $pdo->prepare("
        SELECT oi.*, s.slug AS service_slug, s.thumbnail
        FROM order_items oi
        LEFT JOIN services s ON s.id = oi.service_id
        WHERE oi.order_id IN ($place)
        ORDER BY oi.id ASC
    ");
    $iStmt->execute($orderIds);
    foreach ($iStmt->fetchAll() as $it) {
        $orderItems[(int)$it['order_id']][] = $it;
    }
}

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- Helpers ---------- */
function o_status_label(string $s): string {
    return match($s) {
        'pending'     => 'Pending',
        'confirmed'   => 'Confirmed',
        'processing'  => 'Processing',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
        default       => ucfirst(str_replace('_', ' ', $s)),
    };
}
function o_status_cls(string $s): string {
    return match($s) {
        'pending'     => 'status-pending',
        'confirmed'   => 'status-confirmed',
        'processing'  => 'status-processing',
        'in_progress' => 'status-in_progress',
        'completed'   => 'status-completed',
        'cancelled'   => 'status-cancelled',
        default       => 'status-pending',
    };
}
function o_pay_cls(string $s): string {
    return match($s) {
        'paid'     => 'status-completed',
        'pending'  => 'status-pending',
        'failed'   => 'status-cancelled',
        'refunded' => 'status-processing',
        default    => 'status-pending',
    };
}
function o_filter_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return base_url('user/orders.php') . ($p ? '?' . http_build_query($p) : '');
}

/* ---------- Per-order summary badge ---------- */
function order_delivery_summary(array $items): array {
    $totalItems     = count($items);
    $deliveredCount = 0;
    $inProgress     = 0;
    $overdue        = 0;
    $hasHandover    = false;

    foreach ($items as $it) {
        if (($it['delivery_status'] ?? '') === 'delivered' || !empty($it['delivered_at'])) {
            $deliveredCount++;
        } else if (!empty($it['delivery_due_at'])) {
            if (strtotime($it['delivery_due_at']) < time()) $overdue++;
            else $inProgress++;
        }

        if (!empty($it['handover_sent_at']) || !empty($it['handover_site_url']) || !empty($it['handover_note'])) {
            $hasHandover = true;
        }
    }

    if ($deliveredCount === $totalItems && $totalItems > 0) {
        return ['type'=>'delivered','label'=>'✓ Delivered','cls'=>'ok'];
    }
    if ($overdue > 0) {
        return ['type'=>'overdue','label'=>'⚠ Delivery overdue','cls'=>'danger'];
    }
    if ($inProgress > 0) {
        return ['type'=>'in_progress','label'=>'⏱ Delivery in progress','cls'=>'info'];
    }
    return ['type'=>'pending','label'=>'⏳ Awaiting payment','cls'=>'gray'];
}
?>

<style>
.uord-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: var(--space-lg); border-bottom: 1px solid var(--border); padding-bottom: 2px; }
.uord-tab { padding: 8px 14px; font-size: 13px; font-weight: 600; color: var(--text-muted); border-radius: 6px 6px 0 0; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -3px; transition: all .15s; }
.uord-tab:hover { color: var(--primary); }
.uord-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

.uord-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius-lg); margin-bottom: var(--space); overflow: hidden; transition: box-shadow .15s; }
.uord-card:hover { box-shadow: 0 4px 16px -6px rgba(0,0,0,.08); }

.uord-head { padding: 14px 20px; background: var(--bg-alt); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border); }
.uord-no { font-size: 14px; font-weight: 700; color: var(--navy); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.uord-badges { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.uord-body { padding: 16px 20px; }

.uord-item-row { display: flex; gap: 12px; align-items: flex-start; padding: 12px 0; border-bottom: 1px dashed var(--border); }
.uord-item-row:last-child { border-bottom: none; padding-bottom: 0; }
.uord-thumb { width: 52px; height: 52px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }

.pkg-badge { display: inline-block; font-size: 9.5px; font-weight: 700; padding: 2px 7px; border-radius: 20px; text-transform: uppercase; letter-spacing: .3px; }
.pkg-badge-trial        { background: #D1FAE5; color: #065F46; }
.pkg-badge-subscription { background: #DBEAFE; color: #1E40AF; }
.pkg-badge-lifetime     { background: #FEF3C7; color: #92400E; }

/* Delivery badge */
.dlv-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 700;
    padding: 3px 9px; border-radius: 20px;
    line-height: 1.3;
}
.dlv-badge.info    { background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE; }
.dlv-badge.ok      { background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0; }
.dlv-badge.danger  { background:#FEE2E2; color:#B91C1C; border:1px solid #FECACA; animation:pulseRed 2s infinite; }
.dlv-badge.gray    { background:#F1F5F9; color:#475569; border:1px solid #E2E8F0; }
@keyframes pulseRed {
    0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
    50%     { box-shadow: 0 0 0 6px rgba(239,68,68,.15); }
}

/* Handover ready badge */
.ho-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 700;
    padding: 3px 9px; border-radius: 20px;
    background: linear-gradient(135deg,#10B981,#059669); color: #fff;
    line-height: 1.3;
}

/* Mini countdown pill */
.mini-cd {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 600;
    padding: 3px 8px; border-radius: 20px;
    margin-top: 4px;
    background: #EFF6FF; color: #1E40AF;
    border: 1px solid #BFDBFE;
}
.mini-cd.warn   { background: #FEF3C7; color: #92400E; border-color: #FDE68A; }
.mini-cd.danger { background: #FEE2E2; color: #B91C1C; border-color: #FECACA; }
.mini-cd.expired{ background: #F1F5F9; color: #64748B; border-color: #E2E8F0; }
.mini-cd.lifetime{ background: #FEF3C7; color: #92400E; border-color: #FDE68A; }
.mini-cd .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; animation: pulseDot 1.5s infinite; }
@keyframes pulseDot { 0%,100% { opacity: 1; } 50% { opacity: .35; } }

/* Domain chip */
.dmn-mini {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600;
    padding: 2px 8px; border-radius: 6px;
    background: #F1F5F9; color: var(--navy);
    border: 1px dashed #CBD5E1;
    font-family: monospace;
    margin-top: 4px;
    word-break: break-all;
}

.uord-foot { padding: 12px 20px; background: var(--bg-alt); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-top: 1px solid var(--border); }
.uord-total { font-size: 15px; font-weight: 700; color: var(--navy); }
.uord-total span { color: var(--primary); font-weight: 800; font-size: 17px; }
.uord-renew-tag { display: inline-block; background: #FEF3C7; color: #92400E; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 6px; letter-spacing: .3px; }
</style>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <a href="<?= e(base_url('user/index.php')) ?>">Dashboard</a>
            <span class="sep">/</span>
            <span class="current">Orders</span>
        </nav>

        <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:12px;margin-bottom:var(--space-lg);">
            <div>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:4px;"><?= e(t('my_orders', 'My Orders')) ?></h1>
                <p class="text-muted"><?= number_format($total) ?> order(s)</p>
            </div>
            <a href="<?= e(base_url('services.php')) ?>" class="btn btn-primary btn-sm">+ <?= e(t('browse_services', 'Browse Services')) ?></a>
        </div>

        <!-- Filter tabs -->
        <nav class="uord-tabs">
            <a class="uord-tab<?= $filter === 'all'      ? ' active' : '' ?>" href="<?= e(o_filter_url(['filter' => null, 'page' => null])) ?>"><?= e(t('all', 'All')) ?></a>
            <a class="uord-tab<?= $filter === 'active'   ? ' active' : '' ?>" href="<?= e(o_filter_url(['filter' => 'active', 'page' => null])) ?>"><?= e(t('active', 'Active')) ?></a>
            <a class="uord-tab<?= $filter === 'pending'  ? ' active' : '' ?>" href="<?= e(o_filter_url(['filter' => 'pending', 'page' => null])) ?>"><?= e(t('pending', 'Pending')) ?></a>
            <a class="uord-tab<?= $filter === 'completed'? ' active' : '' ?>" href="<?= e(o_filter_url(['filter' => 'completed', 'page' => null])) ?>"><?= e(t('completed', 'Completed')) ?></a>
            <a class="uord-tab<?= $filter === 'renewals' ? ' active' : '' ?>" href="<?= e(o_filter_url(['filter' => 'renewals', 'page' => null])) ?>">🔄 <?= e(t('renewals', 'Renewals')) ?></a>
        </nav>

        <?php if (empty($orders)): ?>
            <div class="card empty-state" style="padding:var(--space-2xl) var(--space);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:64px;height:64px;color:var(--border-dark);margin:0 auto var(--space);">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
                <h3><?= e(t('no_orders', 'No orders yet')) ?></h3>
                <p><?= e(t('no_orders_sub', 'You haven\'t placed any orders. Start browsing our services.')) ?></p>
                <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('services.php')) ?>"><?= e(t('browse_services', 'Browse Services')) ?></a>
            </div>
        <?php else: ?>

            <?php foreach ($orders as $o):
                $items = $orderItems[(int)$o['id']] ?? [];
                $isRenewal = (int)$o['is_renewal'] === 1;
                $deliverySummary = order_delivery_summary($items);
            ?>
            <div class="uord-card">

                <!-- HEAD -->
                <div class="uord-head">
                    <div class="uord-no">
                        #<?= e($o['order_number']) ?>
                        <?php if ($isRenewal): ?>
                            <span class="uord-renew-tag">🔄 RENEWAL</span>
                        <?php endif; ?>
                    </div>
                    <div class="uord-badges">
                        <span class="status <?= e(o_status_cls($o['order_status'])) ?>" style="font-size:11px;padding:3px 8px;"><?= e(o_status_label($o['order_status'])) ?></span>
                        <span class="status <?= e(o_pay_cls($o['payment_status'])) ?>" style="font-size:11px;padding:3px 8px;"><?= e(ucfirst($o['payment_status'])) ?></span>
                        <span class="text-muted" style="font-size:12px;"><?= e(date('M d, Y', strtotime($o['created_at']))) ?></span>
                    </div>
                </div>

                <!-- BODY -->
                <div class="uord-body">
                    <?php foreach (array_slice($items, 0, 3) as $it):
                        $thumb = $it['thumbnail'] ? base_url($it['thumbnail']) : base_url('assets/images/placeholder.png');
                        $pkgType = $it['package_type'] ?? null;
                        $isTrial = (int)($it['is_trial'] ?? 0) === 1;
                        $isLifetime = ($pkgType === 'lifetime');
                        $cd = countdown_data($it['expires_at'] ?? null);

                        $cdClass = '';
                        if ($isLifetime)          $cdClass = 'lifetime';
                        elseif ($cd['expired'])   $cdClass = 'expired';
                        elseif (!empty($it['expires_at'])) {
                            $secs = $cd['total_seconds'];
                            if ($secs <= 48*3600)     $cdClass = 'danger';
                            elseif ($secs <= 7*86400) $cdClass = 'warn';
                        }

                        $badgeClass = 'pkg-badge-subscription';
                        $badgeLabel = 'Subscription';
                        if ($isTrial)         { $badgeClass = 'pkg-badge-trial'; $badgeLabel = '🎁 Trial'; }
                        elseif ($isLifetime)  { $badgeClass = 'pkg-badge-lifetime'; $badgeLabel = '♾️ Lifetime'; }

                        $hasHandover = !empty($it['handover_sent_at']) || !empty($it['handover_site_url']) || !empty($it['handover_note']);
                    ?>
                    <div class="uord-item-row">
                        <img src="<?= e($thumb) ?>" alt="" class="uord-thumb">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                <span class="pkg-badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
                                <?php if (!empty($it['duration_label'])): ?>
                                    <span class="text-muted" style="font-size:11.5px;"><?= e($it['duration_label']) ?></span>
                                <?php endif; ?>
                                <?php if ($hasHandover): ?>
                                    <span class="ho-badge">🎁 Handover ready</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:13.5px;font-weight:600;color:var(--navy);margin-top:4px;line-height:1.35;">
                                <?php if ($it['service_slug']): ?>
                                    <a href="<?= e(base_url('service-details.php?slug=' . urlencode($it['service_slug']))) ?>" style="color:inherit;text-decoration:none;">
                                        <?= e(str_limit($it['service_title'], 70)) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e(str_limit($it['service_title'], 70)) ?>
                                <?php endif; ?>
                            </div>

                            <!-- Domain chip -->
                            <?php if (($it['domain_type'] ?? 'none') !== 'none' && !empty($it['domain_name'])): 
                                $dmIcon = '🌐';
                                if ($it['domain_type'] === 'subdomain') $dmIcon = '🆓';
                                elseif ($it['domain_type'] === 'purchased') $dmIcon = '💳';
                            ?>
                                <div class="dmn-mini"><?= $dmIcon ?> <?= e($it['domain_name']) ?></div>
                            <?php endif; ?>

                            <!-- Delivery badge (per item) -->
                            <?php if (!empty($it['delivery_due_at']) || ($it['delivery_status'] ?? '') === 'delivered'): 
                                $dlv = delivery_countdown($it['delivery_due_at'] ?? null, $it['delivered_at'] ?? null);
                            ?>
                                <?php if ($dlv['status'] === 'delivered'): ?>
                                    <div class="dlv-badge ok" style="margin-top:6px;">✓ Delivered</div>
                                <?php elseif ($dlv['status'] === 'overdue'): ?>
                                    <div class="dlv-badge danger" style="margin-top:6px;">⚠ Delivery overdue</div>
                                <?php elseif ($dlv['status'] === 'in_progress'): ?>
                                    <div class="dlv-badge info" style="margin-top:6px;">⏱ In progress • due in <?= (int)$dlv['days'] ?>d <?= (int)$dlv['hours'] ?>h</div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Subscription countdown -->
                            <?php if ($isLifetime): ?>
                                <div class="mini-cd lifetime" style="margin-top:6px;"><span class="dot"></span> ♾️ Lifetime access</div>
                            <?php elseif (!empty($it['expires_at'])): ?>
                                <div class="mini-cd <?= e($cdClass) ?>" data-expires-at="<?= e($it['expires_at']) ?>" style="margin-top:6px;">
                                    <span class="dot"></span>
                                    <span data-cd-text>
                                        <?php if ($cd['expired']): ?>
                                            Expired <?= e(date('M d, Y', strtotime($it['expires_at']))) ?>
                                        <?php else: ?>
                                            <?= (int)$cd['days'] ?>d <?= (int)$cd['hours'] ?>h <?= (int)$cd['minutes'] ?>m left
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php elseif ($o['payment_status'] !== 'paid'): ?>
                                <div class="mini-cd" style="margin-top:6px;"><span class="dot"></span> ⏳ Waiting for payment</div>
                            <?php endif; ?>
                        </div>
                        <div style="text-align:right;font-size:13px;font-weight:600;color:var(--navy);white-space:nowrap;">
                            <?= e(money((float)$it['total'], $o['currency'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (count($items) > 3): ?>
                        <div class="text-muted" style="font-size:12px;padding-top:8px;">+ <?= count($items) - 3 ?> more item(s)</div>
                    <?php endif; ?>
                </div>

                <!-- FOOT -->
                <div class="uord-foot">
                    <div class="uord-total">Total: <span><?= e(money((float)$o['total'], $o['currency'])) ?></span></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <?php if ($deliverySummary['cls'] === 'ok'): ?>
                            <span class="dlv-badge ok"><?= e($deliverySummary['label']) ?></span>
                        <?php elseif ($deliverySummary['cls'] === 'danger'): ?>
                            <span class="dlv-badge danger"><?= e($deliverySummary['label']) ?></span>
                        <?php elseif ($deliverySummary['cls'] === 'info'): ?>
                            <span class="dlv-badge info"><?= e($deliverySummary['label']) ?></span>
                        <?php endif; ?>

                        <a href="<?= e(base_url('user/order-details.php?id=' . (int)$o['id'])) ?>" class="btn btn-soft btn-sm"><?= e(t('view_details', 'View Details')) ?></a>

                        <?php
                        $needsRenewal = false;
                        foreach ($items as $chkIt) {
                            if (($chkIt['package_type'] ?? '') === 'subscription' && !empty($chkIt['expires_at'])) {
                                $secs = strtotime($chkIt['expires_at']) - time();
                                if ($secs > 0 && $secs <= 7 * 86400) { $needsRenewal = true; break; }
                            }
                        }
                        if ($needsRenewal):
                        ?>
                            <a href="<?= e(base_url('user/order-details.php?id=' . (int)$o['id'])) ?>" class="btn btn-warning btn-sm" style="background:linear-gradient(135deg,#F59E0B,#D97706);border:none;color:#fff;">🔄 <?= e(t('renew_now', 'Renew Now')) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav style="display:flex;justify-content:center;gap:6px;margin-top:var(--space-xl);flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(o_filter_url(['page' => $page - 1])) ?>">← Prev</a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a class="btn <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm" href="<?= e(o_filter_url(['page' => $i])) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= e(o_filter_url(['page' => $page + 1])) ?>">Next →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* Subscription countdown (every minute refresh) */
    document.querySelectorAll('.mini-cd[data-expires-at]').forEach(function (box) {
        var expiresStr = box.dataset.expiresAt;
        if (!expiresStr) return;
        var expiresTs = Math.floor(new Date(expiresStr.replace(' ', 'T')).getTime() / 1000);
        if (!expiresTs || isNaN(expiresTs)) return;

        var textEl = box.querySelector('[data-cd-text]');
        if (!textEl) return;

        function tick() {
            var diff = expiresTs - Math.floor(Date.now() / 1000);
            if (diff <= 0) {
                box.classList.remove('warn', 'danger');
                box.classList.add('expired');
                textEl.textContent = 'Expired';
                clearInterval(id);
                return;
            }
            var d = Math.floor(diff / 86400);
            var h = Math.floor((diff % 86400) / 3600);
            var m = Math.floor((diff % 3600) / 60);

            var txt = '';
            if (d > 0)      txt = d + 'd ' + h + 'h left';
            else if (h > 0) txt = h + 'h ' + m + 'm left';
            else            txt = m + 'm left';
            textEl.textContent = txt;

            box.classList.remove('warn', 'danger');
            if (diff <= 48 * 3600)      box.classList.add('danger');
            else if (diff <= 7 * 86400) box.classList.add('warn');
        }
        tick();
        var id = setInterval(tick, 60000);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>