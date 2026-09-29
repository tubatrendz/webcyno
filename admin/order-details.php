<?php
/**
 * admin/order-details.php — Single Order View (v3.0)
 * URL: admin/order-details.php?id=123
 *
 * Features:
 *   - Order status / payment status update
 *   - Item delivery mark (with countdown tracking)
 *   - Domain info display
 *   - ✨ Handover note form (site URL, admin URL, credentials, note)
 *   - Handover save → user notification + email
 */

$admin_page_title = 'Order Details';
$admin_active     = 'orders';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Order ID ---------- */
$orderId = (int) input('id', 0);
if ($orderId <= 0) {
    header('Location: ' . admin_url('orders.php'));
    exit;
}

/* ---------- Fetch order ---------- */
$oStmt = $pdo->prepare("
    SELECT o.*, u.name AS user_name, u.email AS user_email, u.avatar AS user_avatar
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id = ?
    LIMIT 1
");
$oStmt->execute([$orderId]);
$order = $oStmt->fetch();

if (!$order) {
    ?>
    <div class="admin-page-header">
        <h1 class="admin-page-title">Order Not Found</h1>
    </div>
    <div class="admin-card">
        <div class="admin-empty">
            <h3>Order not found</h3>
            <p>The order you are looking for does not exist or has been removed.</p>
            <a href="<?= e(admin_url('orders.php')) ?>" class="admin-btn admin-btn-primary" style="margin-top:16px;">← Back to Orders</a>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/admin-footer.php';
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
function a_status_label(string $s): string {
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
function a_status_class(string $s): string {
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
function a_pay_class(string $s): string {
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
/* Delivery countdown badge */
.dlv-box {
    margin-top: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.dlv-box.info   { background:#EFF6FF; border:1px solid #BFDBFE; color:#1E40AF; }
.dlv-box.warn   { background:#FEF3C7; border:1px solid #FDE68A; color:#92400E; }
.dlv-box.danger { background:#FEE2E2; border:1px solid #FECACA; color:#B91C1C; animation:pulseRed 2s infinite; }
.dlv-box.ok     { background:#D1FAE5; border:1px solid #A7F3D0; color:#065F46; }
@keyframes pulseRed {
    0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
    50%     { box-shadow: 0 0 0 6px rgba(239,68,68,.15); }
}
.dlv-timer { display:flex; gap:6px; margin-left:auto; }
.dlv-unit {
    min-width:44px; text-align:center; background:#fff;
    border-radius:6px; padding:4px 6px;
    box-shadow:0 1px 3px rgba(0,0,0,.06);
}
.dlv-num { font-size:15px; font-weight:800; color:inherit; line-height:1; }
.dlv-lbl { font-size:9px; font-weight:600; text-transform:uppercase; color:#64748B; margin-top:2px; }

/* Domain info box */
.dmn-box {
    margin-top: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #F1F5F9;
    border: 1px dashed #CBD5E1;
    font-size: 12.5px;
    line-height: 1.5;
}
.dmn-tag {
    display: inline-block;
    font-size: 10px; font-weight: 700;
    padding: 2px 8px; border-radius: 20px;
    background: #DBEAFE; color: #1E40AF;
    text-transform: uppercase; letter-spacing: .3px;
}
.dmn-tag-own        { background:#E0E7FF; color:#3730A3; }
.dmn-tag-subdomain  { background:#D1FAE5; color:#065F46; }
.dmn-tag-purchased  { background:#FEF3C7; color:#92400E; }

/* Handover form */
.handover-card {
    background: linear-gradient(135deg, #F0FDF4, #F8FAFC);
    border: 2px solid #86EFAC;
    border-radius: var(--radius-lg);
    padding: 18px;
    margin-top: 16px;
}
.handover-card.sent {
    background: linear-gradient(135deg, #ECFDF5, #F0FDF4);
    border-color: #6EE7B7;
}
.handover-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #10B981; color: #fff;
    padding: 4px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 700;
}
.handover-field {
    display: flex; gap: 8px; align-items: center;
    background: #fff; border: 1px solid #E2E8F0;
    padding: 8px 10px; border-radius: 6px;
    font-size: 13px; margin-top: 6px;
    word-break: break-all;
}
.handover-field code {
    font-family: ui-monospace, monospace;
    background: transparent; color: var(--navy); font-weight: 600;
}
.handover-readonly {
    background: #F8FAFC;
    border: 1px dashed #CBD5E1;
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
}
</style>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <a href="<?= e(admin_url('orders.php')) ?>">Orders</a>
            <span class="sep">/</span>
            <span class="current">#<?= e($order['order_number']) ?></span>
        </nav>
        <h1 class="admin-page-title">
            Order #<?= e($order['order_number']) ?>
            <?php if ((int)$order['is_renewal'] === 1): ?>
                <span class="tag tag-warning" style="margin-left:6px;">🔄 Renewal</span>
            <?php endif; ?>
        </h1>
        <p class="admin-page-sub">
            Placed on <?= e(date('M d, Y g:i A', strtotime($order['created_at']))) ?>
            • <span class="status <?= e(a_status_class($order['order_status'])) ?>"><?= e(a_status_label($order['order_status'])) ?></span>
            • <span class="status <?= e(a_pay_class($order['payment_status'])) ?>"><?= e(ucfirst($order['payment_status'])) ?></span>
        </p>
    </div>
    <div class="flex gap-sm" style="flex-wrap:wrap;">
        <a href="<?= e(admin_url('orders.php')) ?>" class="admin-btn admin-btn-ghost">← Back</a>
        <button type="button" class="admin-btn admin-btn-primary" data-print>🖨 Print</button>
    </div>
</div>

<!-- ============ TIMELINE ============ -->
<?php if ($order['order_status'] !== 'cancelled'): ?>
<div class="admin-card">
    <div class="admin-card-header"><h3 class="admin-card-title">Order Progress</h3></div>
    <div class="flex" style="align-items:flex-start;flex-wrap:nowrap;overflow-x:auto;padding-bottom:6px;">
        <?php foreach ($timelineSteps as $i => $step):
            $done   = $i <= $currentIdx;
            $active = $i === $currentIdx;
        ?>
            <div style="flex:1;min-width:120px;text-align:center;position:relative;">
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
                    <?= e(a_status_label($step)) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="alert alert-danger" style="margin-bottom:20px;"><strong>Cancelled</strong> — এই অর্ডার বাতিল করা হয়েছে।</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;" class="admin-od-grid">

    <!-- ============ LEFT ============ -->
    <div>

        <!-- ============ ITEMS ============ -->
        <div class="admin-card" style="padding:0;margin-bottom:20px;">
            <div class="admin-card-header" style="padding:16px 20px;margin:0;">
                <h3 class="admin-card-title">Order Items (<?= count($items) ?>)</h3>
            </div>

            <?php foreach ($items as $it):
                $thumb = $it['thumbnail'] ? base_url($it['thumbnail']) : base_url('assets/images/placeholder.png');
                $pkgType = $it['package_type'] ?? null;
                $isTrial = (int)($it['is_trial'] ?? 0) === 1;
                $isLifetime = ($pkgType === 'lifetime');

                $deliveryCls = match($it['delivery_status']) {
                    'delivered' => 'tag-success',
                    'cancelled' => 'tag-danger',
                    default     => 'tag-warning',
                };

                // Delivery countdown
                $dlv = delivery_countdown($it['delivery_due_at'] ?? null, $it['delivered_at'] ?? null);
            ?>
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);">

                <!-- Top: thumbnail + info -->
                <div style="display:flex;gap:16px;align-items:flex-start;">
                    <img src="<?= e($thumb) ?>" alt="" class="admin-thumb" style="width:64px;height:64px;">

                    <div style="flex:1;min-width:0;">
                        <div class="admin-cell-title" style="font-size:14px;">
                            <?= e($it['service_title']) ?>
                        </div>
                        <?php if (!empty($it['package_name'])): ?>
                            <div class="admin-cell-sub">Package: <?= e($it['package_name']) ?>
                                <?php if ($isTrial): ?> (Trial)<?php endif; ?>
                                <?php if ($isLifetime): ?> (Lifetime)<?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="admin-cell-sub" style="margin-top:4px;">
                            <?= (int) $it['quantity'] ?> × <?= e(money((float) $it['price'], $order['currency'])) ?>
                            = <strong style="color:var(--navy);"><?= e(money((float) $it['total'], $order['currency'])) ?></strong>
                        </div>

                        <div style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span class="tag <?= e($deliveryCls) ?>" style="font-size:11px;text-transform:capitalize;">
                                Delivery: <?= e($it['delivery_status']) ?>
                            </span>
                            <?php if (!empty($it['delivery_days'])): ?>
                                <span class="tag" style="font-size:11px;">⏱ <?= (int)$it['delivery_days'] ?> days</span>
                            <?php endif; ?>
                        </div>

                        <!-- ✨ Delivery countdown -->
                        <?php if (!empty($it['delivery_due_at']) || !empty($it['delivered_at'])): ?>
                            <?php if ($dlv['status'] === 'delivered'): ?>
                                <div class="dlv-box ok">
                                    <span>✅</span>
                                    <span>Delivered on <strong><?= e(date('M d, Y g:i A', strtotime($dlv['delivered_at']))) ?></strong></span>
                                </div>
                            <?php elseif ($dlv['status'] === 'overdue'): ?>
                                <div class="dlv-box danger">
                                    <span>⚠️</span>
                                    <span>OVERDUE by <strong><?= (int)$dlv['days'] ?>d <?= (int)$dlv['hours'] ?>h</strong></span>
                                </div>
                            <?php else: ?>
                                <div class="dlv-box info">
                                    <span>⏱</span>
                                    <span>Delivery due: <strong><?= e(date('M d, Y g:i A', strtotime($dlv['due_at']))) ?></strong></span>
                                    <div class="dlv-timer">
                                        <div class="dlv-unit"><div class="dlv-num"><?= (int)$dlv['days'] ?></div><div class="dlv-lbl">Days</div></div>
                                        <div class="dlv-unit"><div class="dlv-num"><?= (int)$dlv['hours'] ?></div><div class="dlv-lbl">Hrs</div></div>
                                        <div class="dlv-unit"><div class="dlv-num"><?= (int)$dlv['minutes'] ?></div><div class="dlv-lbl">Min</div></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- ✨ DOMAIN INFO -->
                        <?php if (($it['domain_type'] ?? 'none') !== 'none'): 
                            $dmTag = 'dmn-tag';
                            $dmLbl = 'Domain';
                            if ($it['domain_type'] === 'own')         { $dmTag .= ' dmn-tag-own';       $dmLbl = '🌐 Own Domain'; }
                            elseif ($it['domain_type'] === 'subdomain'){ $dmTag .= ' dmn-tag-subdomain'; $dmLbl = '🆓 Free Subdomain'; }
                            elseif ($it['domain_type'] === 'purchased'){ $dmTag .= ' dmn-tag-purchased'; $dmLbl = '💳 Purchased'; }
                        ?>
                            <div class="dmn-box">
                                <span class="<?= e($dmTag) ?>"><?= e($dmLbl) ?></span>
                                <?php if (!empty($it['domain_name'])): ?>
                                    <span style="margin-left:8px;font-weight:600;color:var(--navy);font-family:monospace;">
                                        <?= e($it['domain_name']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ((float)$it['domain_price'] > 0): ?>
                                    <div class="admin-cell-sub" style="margin-top:4px;">
                                        Domain cost: <strong><?= e(money((float)$it['domain_price'], $order['currency'])) ?></strong>
                                        <?php if (!empty($it['domain_period_years'])): ?>
                                            for <?= (int)$it['domain_period_years'] ?> year(s)
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($it['domain_notes'])): ?>
                                    <div class="admin-cell-sub" style="margin-top:2px;"><?= e($it['domain_notes']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ============ ✨ HANDOVER FORM (only for delivered / delivery status change) ============ -->
                <div class="handover-card<?= !empty($it['handover_sent_at']) ? ' sent' : '' ?>" style="margin-top:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
                        <div>
                            <h4 style="font-size:14px;margin:0 0 4px;color:var(--navy);">
                                🎁 Handover Note
                            </h4>
                            <p style="font-size:12px;color:#64748B;margin:0;">
                                Delivered করার সময় এই তথ্য customer কে email এ ও account এ পাঠানো হবে।
                            </p>
                        </div>
                        <?php if (!empty($it['handover_sent_at'])): ?>
                            <span class="handover-badge">
                                ✓ Sent on <?= e(date('M d, Y', strtotime($it['handover_sent_at']))) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <form class="handover-form" data-item-id="<?= (int)$it['id'] ?>" data-order-id="<?= (int)$order['id'] ?>">
                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label class="admin-label">🌐 Site Link</label>
                                <input type="url" name="handover_site_url" class="form-control"
                                       value="<?= e($it['handover_site_url'] ?? '') ?>"
                                       placeholder="https://customer-site.com">
                            </div>
                            <div class="admin-form-group">
                                <label class="admin-label">🔐 Admin Panel Link</label>
                                <input type="url" name="handover_admin_url" class="form-control"
                                       value="<?= e($it['handover_admin_url'] ?? '') ?>"
                                       placeholder="https://customer-site.com/admin">
                            </div>
                        </div>

                        <div class="admin-form-row">
                            <div class="admin-form-group">
                                <label class="admin-label">👤 Admin Username</label>
                                <input type="text" name="handover_admin_user" class="form-control"
                                       value="<?= e($it['handover_admin_user'] ?? '') ?>"
                                       placeholder="admin">
                            </div>
                            <div class="admin-form-group">
                                <label class="admin-label">🔑 Admin Password</label>
                                <input type="text" name="handover_admin_pass" class="form-control"
                                       value="<?= e($it['handover_admin_pass'] ?? '') ?>"
                                       placeholder="xxxxx"
                                       style="font-family:monospace;">
                            </div>
                        </div>

                        <div class="admin-form-group">
                            <label class="admin-label">📝 Note (customer এর জন্য special instruction)</label>
                            <textarea name="handover_note" class="form-control" rows="3" maxlength="2000"
                                      placeholder="যেমন: SSL installed, website ready, SEO setup সম্পন্ন। যেকোনো সমস্যায় যোগাযোগ করুন..."><?= e($it['handover_note'] ?? '') ?></textarea>
                        </div>

                        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                            <button type="button" class="admin-btn admin-btn-ghost" data-save-handover data-send="0">
                                💾 Save (without notify)
                            </button>
                            <button type="button" class="admin-btn admin-btn-success" data-save-handover data-send="1">
                                ✉️ Save & Send to Customer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Totals -->
            <div style="padding:16px 20px;background:var(--bg);">
                <ul style="display:flex;flex-direction:column;gap:8px;font-size:14px;max-width:360px;margin-left:auto;list-style:none;padding:0;">
                    <li class="flex justify-between"><span class="text-muted">Subtotal</span><span style="font-weight:600;"><?= e(money((float) $order['subtotal'], $order['currency'])) ?></span></li>
                    <?php if ((float) $order['discount'] > 0): ?>
                        <li class="flex justify-between"><span class="text-muted">Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span style="color:var(--success);font-weight:600;">− <?= e(money((float) $order['discount'], $order['currency'])) ?></span></li>
                    <?php endif; ?>
                    <li class="flex justify-between" style="padding-top:10px;border-top:1px solid var(--border);font-size:18px;">
                        <span style="font-weight:700;">Total</span>
                        <span style="font-weight:800;color:var(--primary);"><?= e(money((float) $order['total'], $order['currency'])) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ============ NOTES ============ -->
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Notes</h3></div>

            <?php if (!empty($order['notes'])): ?>
                <div style="margin-bottom:16px;">
                    <div class="admin-label">Customer Note</div>
                    <p style="padding:12px;background:var(--bg-alt);border-radius:8px;font-size:13.5px;margin:0;white-space:pre-wrap;"><?= e($order['notes']) ?></p>
                </div>
            <?php endif; ?>

            <form data-ajax data-endpoint="orders/status" data-method="POST" id="note-form">
                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                <div class="admin-form-group">
                    <label class="admin-label">Admin Note (internal)</label>
                    <textarea name="admin_note" class="form-control" rows="3" maxlength="2000"><?= e($order['admin_note'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="admin-btn admin-btn-primary">Save Note</button>
            </form>
        </div>
    </div>

    <!-- ============ RIGHT ============ -->
    <aside>

        <!-- Status update -->
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Update Order Status</h3></div>
            <form data-ajax data-endpoint="orders/status" data-method="POST">
                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                <div class="admin-form-group">
                    <label class="admin-label">Order Status</label>
                    <select name="order_status" class="form-control">
                        <option value="">— Keep current —</option>
                        <?php foreach ($timelineSteps as $s): ?>
                            <option value="<?= e($s) ?>" <?= $order['order_status'] === $s ? 'disabled' : '' ?>><?= e(a_status_label($s)) ?></option>
                        <?php endforeach; ?>
                        <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'disabled' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="admin-btn admin-btn-primary" style="width:100%;">Update Status</button>
            </form>
        </div>

        <!-- Payment -->
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Payment</h3></div>
            <ul style="list-style:none;padding:0;margin:0 0 16px;display:flex;flex-direction:column;gap:10px;font-size:13.5px;">
                <li class="flex justify-between"><span class="text-muted">Method</span><strong><?= e($order['payment_method'] ?: '—') ?></strong></li>
                <li class="flex justify-between"><span class="text-muted">Status</span><span class="status <?= e(a_pay_class($order['payment_status'])) ?>" style="font-size:11px;"><?= e(ucfirst($order['payment_status'])) ?></span></li>
                <?php if ($payment && !empty($payment['transaction_id'])): ?>
                    <li class="flex justify-between gap-sm"><span class="text-muted">Txn ID</span><strong style="font-size:12px;word-break:break-all;text-align:right;"><?= e($payment['transaction_id']) ?></strong></li>
                <?php endif; ?>
                <?php if ($payment && !empty($payment['sender_number'])): ?>
                    <li class="flex justify-between"><span class="text-muted">Sender</span><strong style="font-size:12px;"><?= e($payment['sender_number']) ?></strong></li>
                <?php endif; ?>
                <?php if ($payment && !empty($payment['paid_at'])): ?>
                    <li class="flex justify-between"><span class="text-muted">Paid at</span><strong style="font-size:12px;"><?= e(date('M d, Y g:i A', strtotime($payment['paid_at']))) ?></strong></li>
                <?php endif; ?>
            </ul>
            <form data-ajax data-endpoint="orders/status" data-method="POST">
                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                <div class="admin-form-group">
                    <label class="admin-label">Payment Status</label>
                    <select name="payment_status" class="form-control">
                        <option value="">— Keep current —</option>
                        <option value="pending"  <?= $order['payment_status'] === 'pending'  ? 'disabled' : '' ?>>Pending</option>
                        <option value="paid"     <?= $order['payment_status'] === 'paid'     ? 'disabled' : '' ?>>Paid</option>
                        <option value="failed"   <?= $order['payment_status'] === 'failed'   ? 'disabled' : '' ?>>Failed</option>
                        <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'disabled' : '' ?>>Refunded</option>
                    </select>
                </div>
                <div class="admin-form-group" id="txn-field">
                    <label class="admin-label">Transaction ID</label>
                    <input type="text" name="transaction_id" class="form-control" value="<?= e($payment['transaction_id'] ?? '') ?>" maxlength="120">
                </div>
                <button type="submit" class="admin-btn admin-btn-success" style="width:100%;">💾 Save Payment Update</button>
            </form>
        </div>

        <!-- Customer -->
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Customer</h3></div>
            <?php if (!empty($order['user_id'])): ?>
                <div class="flex items-center gap-sm" style="margin-bottom:16px;">
                    <span class="admin-avatar" style="width:40px;height:40px;font-size:15px;">
                        <?= e(mb_strtoupper(mb_substr($order['user_name'] ?? 'U', 0, 1))) ?>
                    </span>
                    <div style="flex:1;min-width:0;">
                        <div class="admin-cell-title" style="font-size:13.5px;"><?= e($order['user_name'] ?? 'Unknown') ?></div>
                        <div class="admin-cell-sub"><?= e($order['user_email'] ?? '') ?></div>
                    </div>
                    <a href="<?= e(admin_url('users.php?id=' . (int) $order['user_id'])) ?>" class="admin-btn admin-btn-ghost admin-btn-icon" title="View User">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>
                </div>
            <?php endif; ?>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px;font-size:13.5px;">
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

<style>
@media (max-width: 1024px) { .admin-od-grid { grid-template-columns: 1fr !important; } }
@media print {
    .admin-topbar, .admin-sidebar, .admin-btn, .handover-form { display: none !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Print ---------- */
    document.querySelector('[data-print]')?.addEventListener('click', () => window.print());

    /* ---------- Mark item delivered ---------- */
    document.querySelectorAll('[data-mark-delivered]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Mark this item as delivered?')) return;
            btnLoading(btn, true);
            try {
                var res = await API.post('orders/status', {
                    order_id: btn.dataset.orderId,
                    item_id:  btn.dataset.itemId,
                    delivery_note: ''
                });
                toast(res.message || 'Item marked delivered', 'success');
                setTimeout(() => location.reload(), 700);
            } catch (err) {
                toast(err.message || 'Update failed', 'error');
                btnLoading(btn, false);
            }
        });
    });

    /* ---------- Save Handover ---------- */
    document.querySelectorAll('[data-save-handover]').forEach(function (btn) {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            var form = btn.closest('form');
            if (!form) return;

            var itemId  = form.dataset.itemId;
            var orderId = form.dataset.orderId;
            var send    = btn.dataset.send === '1' ? 1 : 0;

            var payload = {
                order_id: orderId,
                item_id: itemId,
                handover_site_url:   form.querySelector('[name="handover_site_url"]').value.trim(),
                handover_admin_url:  form.querySelector('[name="handover_admin_url"]').value.trim(),
                handover_admin_user: form.querySelector('[name="handover_admin_user"]').value.trim(),
                handover_admin_pass: form.querySelector('[name="handover_admin_pass"]').value.trim(),
                handover_note:       form.querySelector('[name="handover_note"]').value.trim(),
                handover_send_now:   send,
            };

            if (send === 1) {
                if (!payload.handover_site_url && !payload.handover_admin_url && !payload.handover_note) {
                    toast('Please fill at least Site Link or a Note before sending.', 'warning');
                    return;
                }
                if (!confirm('Save & send handover info to customer via email?')) return;
            }

            btnLoading(btn, true);
            try {
                var res = await API.post('orders/status', payload);
                toast(res.message || 'Handover saved', 'success');
                if (send === 1) setTimeout(() => location.reload(), 1200);
            } catch (err) {
                toast(err.message || 'Save failed', 'error');
            } finally {
                btnLoading(btn, false);
            }
        });
    });

    /* ---------- Payment method select — txn field ---------- */
    var paySel = document.querySelector('select[name="payment_status"]');
    var txnField = document.getElementById('txn-field');
    if (paySel && txnField) {
        function toggleTxn() {
            txnField.style.display = (paySel.value === 'paid' || paySel.value === '') ? '' : 'none';
        }
        paySel.addEventListener('change', toggleTxn);
        toggleTxn();
    }
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>