<?php
/**
 * user/order-details.php — User Order Details (v3.0)
 * Features: countdown + delivery countdown + handover + domain
 */

$user_page_title = 'Order Details';
$user_active     = 'orders';
require_once __DIR__ . '/../includes/header.php';

global $pdo;

/* ---------- Auth ---------- */
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . base_url('login.php?redirect=' . urlencode('user/order-details.php')));
    exit;
}

/* ---------- Order ID ---------- */
$orderId = (int) input('id', 0);
if ($orderId <= 0) {
    header('Location: ' . base_url('user/orders.php'));
    exit;
}

/* ---------- Fetch order ---------- */
$oStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$oStmt->execute([$orderId, $userId]);
$order = $oStmt->fetch();

if (!$order) {
    ?>
    <main id="main-content">
        <div class="container" style="padding:var(--space-2xl) 0;">
            <div class="card empty-state">
                <h2>Order Not Found</h2>
                <p>The order you are looking for does not exist or does not belong to you.</p>
                <a href="<?= e(base_url('user/orders.php')) ?>" class="btn btn-primary btn-sm" style="margin-top:var(--space);">← Back to Orders</a>
            </div>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

/* ---------- Items ---------- */
$iStmt = $pdo->prepare("
    SELECT oi.*, s.slug AS service_slug, s.thumbnail, s.delivery_type
    FROM order_items oi
    LEFT JOIN services s ON s.id = oi.service_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll();

/* ---------- Payment ---------- */
$pStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$pStmt->execute([$orderId]);
$payment = $pStmt->fetch();

/* ---------- Timeline ---------- */
$timelineSteps = ['pending','confirmed','processing','in_progress','completed'];
$currentIdx    = array_search($order['order_status'], $timelineSteps, true);
if ($order['order_status'] === 'cancelled') $currentIdx = -1;

/* ---------- Helpers ---------- */
function u_status_label(string $s): string {
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
function u_status_class(string $s): string {
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
function u_pay_class(string $s): string {
    return match($s) {
        'paid'     => 'status-completed',
        'pending'  => 'status-pending',
        'failed'   => 'status-cancelled',
        'refunded' => 'status-processing',
        default    => 'status-pending',
    };
}
?>

<style>
/* Item card */
.uo-item {
    display: flex;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    align-items: flex-start;
    flex-wrap: wrap;
}
.uo-item:last-child { border-bottom: none; }
.uo-thumb {
    width: 72px; height: 72px; object-fit: cover;
    border-radius: var(--radius);
    flex-shrink: 0;
}
.uo-item-body { flex: 1; min-width: 200px; }

.pkg-badge {
    display: inline-block;
    font-size: 10px; font-weight: 700;
    padding: 3px 9px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: .4px;
}
.pkg-badge-trial        { background: #D1FAE5; color: #065F46; }
.pkg-badge-subscription { background: #DBEAFE; color: #1E40AF; }
.pkg-badge-lifetime     { background: #FEF3C7; color: #92400E; }

/* Countdown boxes */
.countdown-box {
    margin-top: 10px;
    padding: 12px 14px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, #EFF6FF, #F8FAFC);
    border: 1px solid #BFDBFE;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.countdown-box.warn {
    background: linear-gradient(135deg, #FEF3C7, #FFFBEB);
    border-color: #FDE68A;
}
.countdown-box.danger {
    background: linear-gradient(135deg, #FEE2E2, #FEF2F2);
    border-color: #FECACA;
    animation: pulseRed 2s infinite;
}
.countdown-box.expired {
    background: #F1F5F9;
    border-color: #E2E8F0;
    opacity: .75;
}
.countdown-box.lifetime {
    background: linear-gradient(135deg, #FEF3C7, #FFFBEB);
    border-color: #FDE68A;
}
.countdown-box.ok {
    background: linear-gradient(135deg, #ECFDF5, #F0FDF4);
    border-color: #A7F3D0;
}
@keyframes pulseRed {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    50%      { box-shadow: 0 0 0 6px rgba(239, 68, 68, .15); }
}

.countdown-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 16px; flex-shrink: 0;
    background: var(--primary);
}
.countdown-box.warn .countdown-icon { background: #F59E0B; }
.countdown-box.danger .countdown-icon { background: #DC2626; }
.countdown-box.expired .countdown-icon { background: #64748B; }
.countdown-box.lifetime .countdown-icon { background: #F59E0B; }
.countdown-box.ok .countdown-icon { background: #10B981; }

.countdown-text {
    font-size: 12.5px;
    color: var(--text);
    font-weight: 500;
    line-height: 1.35;
}
.countdown-text strong { color: var(--navy); }

.countdown-timer {
    display: flex; gap: 6px; margin-left: auto;
}
.cd-unit {
    min-width: 42px;
    text-align: center;
    background: #fff;
    border-radius: 6px;
    padding: 4px 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.cd-num {
    font-size: 18px; font-weight: 800; line-height: 1;
    color: var(--navy);
    font-variant-numeric: tabular-nums;
}
.countdown-box.warn .cd-num { color: #B45309; }
.countdown-box.danger .cd-num { color: #DC2626; }
.countdown-box.ok .cd-num { color: #059669; }
.cd-lbl {
    font-size: 9px; font-weight: 600; text-transform: uppercase;
    color: var(--text-muted); letter-spacing: .5px; margin-top: 2px;
}

/* Renew button */
.uo-renew-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px;
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: #fff;
    border-radius: 8px;
    font-weight: 700; font-size: 13px;
    text-decoration: none;
    border: none; cursor: pointer;
    transition: transform .15s, box-shadow .15s;
    margin-top: 10px;
}
.uo-renew-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px -4px rgba(217,119,6,.5); color: #fff; }

/* Domain chip */
.dmn-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: #F1F5F9; border: 1px solid #E2E8F0;
    padding: 6px 10px; border-radius: 8px;
    font-size: 12.5px; color: var(--navy);
    margin-top: 8px; font-family: monospace;
    word-break: break-all;
}
.dmn-chip-icon { font-family: initial; }

/* Handover section */
.handover-panel {
    margin-top: 14px;
    background: linear-gradient(135deg, #ECFDF5, #F0FDF4);
    border: 2px solid #86EFAC;
    border-radius: var(--radius-lg);
    padding: 16px 18px;
    position: relative;
}
.handover-panel-head {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 14px;
}
.handover-panel-title {
    font-size: 15px; font-weight: 800; color: #065F46;
    display: flex; align-items: center; gap: 8px;
}
.handover-panel-sub {
    font-size: 12px; color: #047857; margin: 4px 0 0;
}
.handover-row {
    display: flex;
    gap: 10px;
    align-items: center;
    background: #fff;
    border: 1px solid #D1FAE5;
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    font-size: 13px;
}
.handover-row:last-child { margin-bottom: 0; }
.handover-row-label {
    font-size: 11px; font-weight: 700; color: #059669;
    text-transform: uppercase; letter-spacing: .4px;
    width: 130px; flex-shrink: 0;
}
.handover-row-value {
    flex: 1; color: var(--navy); font-weight: 600;
    word-break: break-all;
}
.handover-row-value a { color: var(--primary); text-decoration: none; }
.handover-row-value a:hover { text-decoration: underline; }
.handover-row-value code {
    background: #F1F5F9; padding: 2px 6px; border-radius: 4px;
    font-family: ui-monospace, monospace; font-size: 12.5px;
    color: var(--navy);
}
.handover-copy-btn {
    background: #10B981; color: #fff;
    border: none; padding: 4px 10px;
    border-radius: 6px; cursor: pointer;
    font-size: 11px; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
    flex-shrink: 0;
}
.handover-note {
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    color: #78350F;
    line-height: 1.6;
    white-space: pre-wrap;
    margin-top: 8px;
}

@media (max-width: 640px) {
    .countdown-timer { margin-left: 0; margin-top: 8px; width: 100%; }
    .cd-unit { flex: 1; }
    .handover-row { flex-direction: column; align-items: flex-start; gap: 4px; }
    .handover-row-label { width: auto; }
}

@media (max-width: 1024px) {
    .uo-grid { grid-template-columns: 1fr !important; }
}
</style>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <a href="<?= e(base_url('user/index.php')) ?>">Dashboard</a>
            <span class="sep">/</span>
            <a href="<?= e(base_url('user/orders.php')) ?>">Orders</a>
            <span class="sep">/</span>
            <span class="current">#<?= e($order['order_number']) ?></span>
        </nav>

        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:var(--space-lg);">
            <div>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:6px;">
                    Order #<?= e($order['order_number']) ?>
                    <?php if ((int)$order['is_renewal'] === 1): ?>
                        <span class="pkg-badge" style="background:#FEF3C7;color:#92400E;vertical-align:middle;margin-left:6px;">🔄 Renewal</span>
                    <?php endif; ?>
                </h1>
                <p class="text-muted" style="font-size:var(--fs-sm);">
                    Placed on <?= e(date('M d, Y g:i A', strtotime($order['created_at']))) ?>
                    • <span class="status <?= e(u_status_class($order['order_status'])) ?>"><?= e(u_status_label($order['order_status'])) ?></span>
                    • <span class="status <?= e(u_pay_class($order['payment_status'])) ?>"><?= e(ucfirst($order['payment_status'])) ?></span>
                </p>
            </div>
            <div>
                <a href="<?= e(base_url('user/orders.php')) ?>" class="btn btn-ghost btn-sm">← Back</a>
            </div>
        </div>

        <!-- Timeline -->
        <?php if ($order['order_status'] !== 'cancelled'): ?>
        <div class="card" style="padding:var(--space);margin-bottom:var(--space-lg);">
            <h3 style="font-size:var(--fs-md);margin-bottom:var(--space);">Order Progress</h3>
            <div style="display:flex;align-items:flex-start;flex-wrap:nowrap;overflow-x:auto;padding-bottom:6px;">
                <?php foreach ($timelineSteps as $i => $step):
                    $done   = $i <= $currentIdx;
                    $active = $i === $currentIdx;
                ?>
                    <div style="flex:1;min-width:110px;text-align:center;position:relative;">
                        <?php if ($i < count($timelineSteps) - 1): ?>
                            <div style="position:absolute;top:16px;left:50%;width:100%;height:2px;background:<?= $i < $currentIdx ? 'var(--primary)' : 'var(--border)' ?>;z-index:0;"></div>
                        <?php endif; ?>
                        <div style="position:relative;z-index:1;width:34px;height:34px;margin:0 auto 8px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:<?= $done ? 'var(--primary)' : 'var(--bg-alt)' ?>;color:<?= $done ? '#fff' : 'var(--text-muted)' ?>;font-weight:700;font-size:12px;">
                            <?php if ($i < $currentIdx): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php else: ?>
                                <?= $i + 1 ?>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:12px;font-weight:<?= $done ? '600' : '500' ?>;color:<?= $done ? 'var(--navy)' : 'var(--text-muted)' ?>;">
                            <?= e(u_status_label($step)) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-danger" style="margin-bottom:20px;">
            <strong>Cancelled</strong> — এই অর্ডার বাতিল করা হয়েছে।
        </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;" class="uo-grid">

            <!-- ============ LEFT ============ -->
            <div>
                <div class="card" style="padding:0;margin-bottom:var(--space-lg);">
                    <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
                        <h3 style="font-size:var(--fs-md);margin:0;">Items (<?= count($items) ?>)</h3>
                    </div>

                    <?php foreach ($items as $it):
                        $thumb = $it['thumbnail'] ? base_url($it['thumbnail']) : base_url('assets/images/placeholder.png');
                        $pkgType = $it['package_type'] ?? null;
                        $isTrial = (int)($it['is_trial'] ?? 0) === 1;
                        $isLifetime = ($pkgType === 'lifetime');
                        $isSubscription = ($pkgType === 'subscription');

                        /* Subscription countdown */
                        $cd = countdown_data($it['expires_at'] ?? null);
                        $cdState = 'info';
                        if ($isLifetime)         $cdState = 'lifetime';
                        elseif ($cd['expired'])  $cdState = 'expired';
                        elseif (empty($it['expires_at'])) $cdState = 'info';
                        else {
                            $secs = $cd['total_seconds'];
                            if ($secs <= 48 * 3600)       $cdState = 'danger';
                            elseif ($secs <= 7 * 86400)   $cdState = 'warn';
                        }

                        /* Delivery countdown */
                        $dlv = delivery_countdown($it['delivery_due_at'] ?? null, $it['delivered_at'] ?? null);

                        $badgeClass = 'pkg-badge-subscription';
                        $badgeLabel = 'Subscription';
                        if ($isTrial)         { $badgeClass = 'pkg-badge-trial'; $badgeLabel = '🎁 Trial'; }
                        elseif ($isLifetime)  { $badgeClass = 'pkg-badge-lifetime'; $badgeLabel = '♾️ Lifetime'; }
                    ?>

                    <div class="uo-item">
                        <img src="<?= e($thumb) ?>" alt="" class="uo-thumb">

                        <div class="uo-item-body">
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:6px;">
                                <span class="pkg-badge <?= e($badgeClass) ?>"><?= e($badgeLabel) ?></span>
                                <?php if (!empty($it['package_name'])): ?>
                                    <span class="text-muted" style="font-size:12px;"><?= e($it['package_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($it['duration_label'])): ?>
                                    <span class="text-muted" style="font-size:12px;">• <?= e($it['duration_label']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="font-size:14px;font-weight:600;color:var(--navy);line-height:1.35;">
                                <?php if ($it['service_slug']): ?>
                                    <a href="<?= e(base_url('service-details.php?slug=' . urlencode($it['service_slug']))) ?>" style="color:inherit;text-decoration:none;">
                                        <?= e($it['service_title']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e($it['service_title']) ?>
                                <?php endif; ?>
                            </div>

                            <div class="text-muted" style="font-size:12.5px;margin-top:4px;">
                                <?= (int) $it['quantity'] ?> × <?= e(money((float)$it['price'], $order['currency'])) ?>
                                = <strong style="color:var(--navy);"><?= e(money((float)$it['total'], $order['currency'])) ?></strong>
                            </div>

                            <!-- Domain chip -->
                            <?php if (($it['domain_type'] ?? 'none') !== 'none' && !empty($it['domain_name'])): 
                                $dmIcon = '🌐';
                                if ($it['domain_type'] === 'subdomain') $dmIcon = '🆓';
                                elseif ($it['domain_type'] === 'purchased') $dmIcon = '💳';
                            ?>
                                <div class="dmn-chip">
                                    <span class="dmn-chip-icon"><?= $dmIcon ?></span>
                                    <span><?= e($it['domain_name']) ?></span>
                                    <?php if ((float)$it['domain_price'] > 0): ?>
                                        <span style="color:#D97706;font-weight:700;font-family:initial;">
                                            (+<?= e(money((float)$it['domain_price'], $order['currency'])) ?>)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- ✨ DELIVERY COUNTDOWN (before delivered) -->
                            <?php if ($dlv['status'] === 'delivered'): ?>
                                <div class="countdown-box ok">
                                    <div class="countdown-icon">✅</div>
                                    <div class="countdown-text">
                                        <strong>Delivered</strong> on <?= e(date('M d, Y g:i A', strtotime($dlv['delivered_at']))) ?>
                                    </div>
                                </div>
                            <?php elseif ($dlv['status'] === 'overdue'): ?>
                                <div class="countdown-box danger">
                                    <div class="countdown-icon">⚠️</div>
                                    <div class="countdown-text">
                                        <strong>Delivery overdue</strong> by <?= (int)$dlv['days'] ?>d <?= (int)$dlv['hours'] ?>h
                                        <br><span style="color:#B91C1C;font-size:11.5px;">আমরা দ্রুত আপনাকে জানাবো</span>
                                    </div>
                                </div>
                            <?php elseif ($dlv['status'] === 'in_progress'): ?>
                                <div class="countdown-box">
                                    <div class="countdown-icon">⏱</div>
                                    <div class="countdown-text">
                                        <strong>Delivery in progress</strong>
                                        <br><span style="font-size:11.5px;color:#64748B;">Due: <?= e(date('M d, Y g:i A', strtotime($dlv['due_at']))) ?></span>
                                    </div>
                                    <div class="countdown-timer" data-delivery-timer data-due="<?= e($dlv['due_at']) ?>">
                                        <div class="cd-unit"><div class="cd-num" data-cd="days"><?= (int)$dlv['days'] ?></div><div class="cd-lbl">Days</div></div>
                                        <div class="cd-unit"><div class="cd-num" data-cd="hours"><?= (int)$dlv['hours'] ?></div><div class="cd-lbl">Hrs</div></div>
                                        <div class="cd-unit"><div class="cd-num" data-cd="mins"><?= (int)$dlv['minutes'] ?></div><div class="cd-lbl">Min</div></div>
                                        <div class="cd-unit"><div class="cd-num" data-cd="secs">00</div><div class="cd-lbl">Sec</div></div>
                                    </div>
                                </div>
                            <?php elseif ($order['payment_status'] !== 'paid'): ?>
                                <div class="countdown-box">
                                    <div class="countdown-icon">⏳</div>
                                    <div class="countdown-text">
                                        Waiting for payment confirmation
                                        <br><span style="font-size:11.5px;color:#64748B;">Payment confirm হলে delivery countdown শুরু হবে</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- ✨ SUBSCRIPTION COUNTDOWN (only after payment) -->
                            <?php if (!empty($it['starts_at']) || $isLifetime || $cd['expired'] || !empty($it['expires_at'])): ?>
                                <div class="countdown-box <?= e($cdState) ?>" data-expires-at="<?= e($it['expires_at'] ?? '') ?>" data-is-lifetime="<?= $isLifetime ? '1' : '0' ?>">
                                    <div class="countdown-icon">
                                        <?php if ($isLifetime): ?>♾️
                                        <?php elseif ($cd['expired']): ?>⛔
                                        <?php elseif ($cdState === 'danger'): ?>⚠️
                                        <?php else: ?>⏱
                                        <?php endif; ?>
                                    </div>
                                    <div class="countdown-text">
                                        <?php if ($isLifetime): ?>
                                            <strong>Lifetime Access</strong> — No expiry
                                        <?php elseif ($cd['expired']): ?>
                                            <strong>Expired</strong> on <?= e(date('M d, Y g:i A', strtotime($it['expires_at']))) ?>
                                        <?php else: ?>
                                            <strong>Access until:</strong> <?= e(date('M d, Y g:i A', strtotime($it['expires_at']))) ?>
                                            <?php if ($isTrial && $cdState === 'danger'): ?>
                                                <br><span style="color:#B91C1C;">Trial শেষের পথে — এখনই upgrade করুন!</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!$isLifetime && !$cd['expired']): ?>
                                        <div class="countdown-timer">
                                            <div class="cd-unit"><div class="cd-num" data-cd="days"><?= (int)$cd['days'] ?></div><div class="cd-lbl">Days</div></div>
                                            <div class="cd-unit"><div class="cd-num" data-cd="hours"><?= (int)$cd['hours'] ?></div><div class="cd-lbl">Hrs</div></div>
                                            <div class="cd-unit"><div class="cd-num" data-cd="mins"><?= (int)$cd['minutes'] ?></div><div class="cd-lbl">Min</div></div>
                                            <div class="cd-unit"><div class="cd-num" data-cd="secs">00</div><div class="cd-lbl">Sec</div></div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$isLifetime && ($isSubscription || $isTrial) && !empty($it['expires_at'])): ?>
                                    <a href="<?= e(base_url('checkout.php?renew_item_id=' . (int)$it['id'])) ?>" class="uo-renew-btn">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="23 4 23 10 17 10"/>
                                            <polyline points="1 20 1 14 7 14"/>
                                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                                        </svg>
                                        <?= $isTrial ? 'Upgrade Now' : 'Renew Subscription' ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- ✨ HANDOVER PANEL -->
                            <?php if (!empty($it['handover_sent_at']) || (!empty($it['handover_site_url']) || !empty($it['handover_admin_url']) || !empty($it['handover_note']))): ?>
                                <div class="handover-panel">
                                    <div class="handover-panel-head">
                                        <div style="width:42px;height:42px;background:#10B981;color:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🎁</div>
                                        <div style="flex:1;">
                                            <div class="handover-panel-title">Your Project is Ready</div>
                                            <p class="handover-panel-sub">Handover details — save this information safely</p>
                                        </div>
                                    </div>

                                    <?php if (!empty($it['handover_site_url'])): ?>
                                        <div class="handover-row">
                                            <div class="handover-row-label">🌐 Site Link</div>
                                            <div class="handover-row-value">
                                                <a href="<?= e($it['handover_site_url']) ?>" target="_blank" rel="noopener"><?= e($it['handover_site_url']) ?></a>
                                            </div>
                                            <button type="button" class="handover-copy-btn" data-copy="<?= e($it['handover_site_url']) ?>">Copy</button>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($it['handover_admin_url'])): ?>
                                        <div class="handover-row">
                                            <div class="handover-row-label">🔐 Admin Panel</div>
                                            <div class="handover-row-value">
                                                <a href="<?= e($it['handover_admin_url']) ?>" target="_blank" rel="noopener"><?= e($it['handover_admin_url']) ?></a>
                                            </div>
                                            <button type="button" class="handover-copy-btn" data-copy="<?= e($it['handover_admin_url']) ?>">Copy</button>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($it['handover_admin_user'])): ?>
                                        <div class="handover-row">
                                            <div class="handover-row-label">👤 Username</div>
                                            <div class="handover-row-value"><code><?= e($it['handover_admin_user']) ?></code></div>
                                            <button type="button" class="handover-copy-btn" data-copy="<?= e($it['handover_admin_user']) ?>">Copy</button>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($it['handover_admin_pass'])): ?>
                                        <div class="handover-row">
                                            <div class="handover-row-label">🔑 Password</div>
                                            <div class="handover-row-value"><code><?= e($it['handover_admin_pass']) ?></code></div>
                                            <button type="button" class="handover-copy-btn" data-copy="<?= e($it['handover_admin_pass']) ?>">Copy</button>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($it['handover_note'])): ?>
                                        <div class="handover-note">
                                            📝 <?= nl2br(e($it['handover_note'])) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($it['handover_sent_at'])): ?>
                                        <p style="font-size:11px;color:#047857;margin-top:12px;margin-bottom:0;">
                                            ✓ Sent to your email on <?= e(date('M d, Y g:i A', strtotime($it['handover_sent_at']))) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Totals -->
                    <div style="padding:16px 20px;background:var(--bg-alt);">
                        <ul style="display:flex;flex-direction:column;gap:8px;font-size:14px;max-width:340px;margin-left:auto;list-style:none;padding:0;">
                            <li style="display:flex;justify-content:space-between;"><span class="text-muted">Subtotal</span><span style="font-weight:600;"><?= e(money((float) $order['subtotal'], $order['currency'])) ?></span></li>
                            <?php
                            $domainTotal = 0;
                            foreach ($items as $chkIt) { $domainTotal += (float)($chkIt['domain_price'] ?? 0); }
                            if ($domainTotal > 0):
                            ?>
                                <li style="display:flex;justify-content:space-between;"><span class="text-muted">Domain</span><span style="font-weight:600;"><?= e(money($domainTotal, $order['currency'])) ?></span></li>
                            <?php endif; ?>
                            <?php if ((float)$order['discount'] > 0): ?>
                                <li style="display:flex;justify-content:space-between;"><span class="text-muted">Discount</span><span style="color:var(--success);font-weight:600;">− <?= e(money((float) $order['discount'], $order['currency'])) ?></span></li>
                            <?php endif; ?>
                            <li style="display:flex;justify-content:space-between;padding-top:10px;border-top:1px solid var(--border);font-size:17px;">
                                <span style="font-weight:700;">Total</span>
                                <span style="font-weight:800;color:var(--primary);"><?= e(money((float) $order['total'], $order['currency'])) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <?php if (!empty($order['notes'])): ?>
                <div class="card" style="padding:var(--space);">
                    <h3 style="font-size:var(--fs-md);margin-bottom:var(--space);">Your Note</h3>
                    <p style="padding:12px;background:var(--bg-alt);border-radius:8px;font-size:13.5px;margin:0;white-space:pre-wrap;"><?= e($order['notes']) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- ============ RIGHT ============ -->
            <aside>
                <div class="card" style="padding:var(--space);margin-bottom:var(--space);">
                    <h3 style="font-size:var(--fs-md);margin-bottom:var(--space);">Payment</h3>
                    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px;font-size:13.5px;">
                        <li style="display:flex;justify-content:space-between;"><span class="text-muted">Method</span><strong><?= e($order['payment_method'] ?: '—') ?></strong></li>
                        <li style="display:flex;justify-content:space-between;"><span class="text-muted">Status</span><span class="status <?= e(u_pay_class($order['payment_status'])) ?>" style="font-size:11px;"><?= e(ucfirst($order['payment_status'])) ?></span></li>
                        <?php if ($payment && !empty($payment['transaction_id'])): ?>
                            <li style="display:flex;justify-content:space-between;gap:8px;"><span class="text-muted">Txn ID</span><strong style="font-size:12px;word-break:break-all;text-align:right;"><?= e($payment['transaction_id']) ?></strong></li>
                        <?php endif; ?>
                        <?php if ($payment && !empty($payment['paid_at'])): ?>
                            <li style="display:flex;justify-content:space-between;"><span class="text-muted">Paid at</span><strong style="font-size:12px;"><?= e(date('M d, Y g:i A', strtotime($payment['paid_at']))) ?></strong></li>
                        <?php endif; ?>
                    </ul>

                    <?php if ($order['payment_status'] === 'pending'): ?>
                        <div style="margin-top:14px;padding:12px;background:#FEF3C7;border:1px solid #FDE68A;border-radius:8px;font-size:12.5px;color:#92400E;">
                            ⏳ Payment এখনো pending। Admin যাচাই করে confirm করলে কাজ শুরু হবে।
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card" style="padding:var(--space);margin-bottom:var(--space);">
                    <h3 style="font-size:var(--fs-md);margin-bottom:var(--space);">Order Info</h3>
                    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                        <li style="display:flex;justify-content:space-between;"><span class="text-muted">Order #</span><strong style="font-size:12px;"><?= e($order['order_number']) ?></strong></li>
                        <li style="display:flex;justify-content:space-between;"><span class="text-muted">Placed</span><strong style="font-size:12px;"><?= e(date('M d, Y', strtotime($order['created_at']))) ?></strong></li>
                        <?php if ((int)$order['is_renewal'] === 1): ?>
                            <li style="display:flex;justify-content:space-between;"><span class="text-muted">Type</span><strong style="font-size:12px;color:#92400E;">🔄 Renewal</strong></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="card" style="padding:var(--space);">
                    <h3 style="font-size:var(--fs-md);margin-bottom:var(--space);">Billing</h3>
                    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px;font-size:13px;">
                        <li><strong><?= e($order['customer_name']) ?></strong></li>
                        <li class="text-muted" style="font-size:12px;word-break:break-all;"><?= e($order['customer_email']) ?></li>
                        <?php if (!empty($order['customer_phone'])): ?>
                            <li class="text-muted" style="font-size:12px;"><?= e($order['customer_phone']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($order['billing_address'])): ?>
                            <li class="text-muted" style="font-size:12px;padding-top:8px;border-top:1px solid var(--border);white-space:pre-wrap;"><?= e($order['billing_address']) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ============ SUBSCRIPTION COUNTDOWN ============ */
    document.querySelectorAll('.countdown-box[data-expires-at]').forEach(function (box) {
        var expiresStr = box.dataset.expiresAt;
        var isLifetime = box.dataset.isLifetime === '1';
        if (isLifetime || !expiresStr) return;

        var expiresTs = Math.floor(new Date(expiresStr.replace(' ', 'T')).getTime() / 1000);
        if (!expiresTs || isNaN(expiresTs)) return;

        var itemEl = box.querySelector('.countdown-timer');
        if (!itemEl) return;

        function tick() {
            var now = Math.floor(Date.now() / 1000);
            var diff = expiresTs - now;
            if (diff <= 0) {
                box.classList.remove('warn','danger');
                box.classList.add('expired');
                var txt = box.querySelector('.countdown-text');
                if (txt) txt.innerHTML = '<strong>Expired</strong> just now';
                if (itemEl) itemEl.style.display = 'none';
                clearInterval(id);
                return;
            }
            var d = Math.floor(diff/86400), h = Math.floor((diff%86400)/3600), m = Math.floor((diff%3600)/60), s = diff%60;
            var dn = itemEl.querySelector('[data-cd="days"]');
            var hn = itemEl.querySelector('[data-cd="hours"]');
            var mn = itemEl.querySelector('[data-cd="mins"]');
            var sn = itemEl.querySelector('[data-cd="secs"]');
            if (dn) dn.textContent = String(d).padStart(2,'0');
            if (hn) hn.textContent = String(h).padStart(2,'0');
            if (mn) mn.textContent = String(m).padStart(2,'0');
            if (sn) sn.textContent = String(s).padStart(2,'0');
            box.classList.remove('warn','danger','expired');
            if (diff <= 48*3600)      box.classList.add('danger');
            else if (diff <= 7*86400) box.classList.add('warn');
        }
        tick();
        var id = setInterval(tick, 1000);
    });

    /* ============ DELIVERY COUNTDOWN ============ */
    document.querySelectorAll('[data-delivery-timer]').forEach(function (timer) {
        var due = timer.dataset.due;
        if (!due) return;
        var dueTs = Math.floor(new Date(due.replace(' ', 'T')).getTime() / 1000);

        function tick2() {
            var diff = dueTs - Math.floor(Date.now()/1000);
            if (diff <= 0) {
                timer.innerHTML = '<span style="color:#DC2626;font-weight:700;">Due</span>';
                clearInterval(id2);
                return;
            }
            var d = Math.floor(diff/86400), h = Math.floor((diff%86400)/3600), m = Math.floor((diff%3600)/60), s = diff%60;
            var dn = timer.querySelector('[data-cd="days"]');
            var hn = timer.querySelector('[data-cd="hours"]');
            var mn = timer.querySelector('[data-cd="mins"]');
            var sn = timer.querySelector('[data-cd="secs"]');
            if (dn) dn.textContent = String(d).padStart(2,'0');
            if (hn) hn.textContent = String(h).padStart(2,'0');
            if (mn) mn.textContent = String(m).padStart(2,'0');
            if (sn) sn.textContent = String(s).padStart(2,'0');
        }
        tick2();
        var id2 = setInterval(tick2, 1000);
    });

    /* ============ COPY TO CLIPBOARD ============ */
    var toastEl = null;
    function showToast(msg) {
        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.style.cssText = 'position:fixed;bottom:100px;left:50%;transform:translateX(-50%) translateY(20px);background:#0F172A;color:#fff;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;opacity:0;pointer-events:none;transition:all .3s;z-index:9999;box-shadow:0 10px 30px -8px rgba(0,0,0,.3);';
            document.body.appendChild(toastEl);
        }
        toastEl.textContent = '✓ ' + msg;
        toastEl.style.opacity = '1';
        toastEl.style.transform = 'translateX(-50%) translateY(0)';
        setTimeout(function () {
            toastEl.style.opacity = '0';
            toastEl.style.transform = 'translateX(-50%) translateY(20px)';
        }, 1600);
    }

    document.querySelectorAll('.handover-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.dataset.copy;
            if (!text) return;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () { showToast('Copied'); });
            } else {
                var tmp = document.createElement('textarea');
                tmp.value = text;
                tmp.style.cssText = 'position:absolute;left:-9999px;';
                document.body.appendChild(tmp);
                tmp.select();
                try { document.execCommand('copy'); showToast('Copied'); } catch (e) {}
                document.body.removeChild(tmp);
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>