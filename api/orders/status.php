<?php
/**
 * api/orders/status.php — Update Order / Payment / Item Delivery / Handover (v3.0)
 * Version: 3.0 (delivery + domain + handover)
 * Method: POST
 *
 * Body variants:
 *   { order_id, order_status? }
 *   { order_id, payment_status?, transaction_id? }
 *   { order_id, item_id, delivery_note? }  → mark item delivered
 *   { order_id, item_id, handover_* }      → save handover info
 *   { order_id, item_id, handover_*, handover_send_now:1 }  → save + send
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$orderId       = (int) input('order_id', 0);
$itemId        = (int) input('item_id', 0);
$orderStatus   = clean(input('order_status', ''), 30);
$paymentStatus = clean(input('payment_status', ''), 30);
$txnId         = clean(input('transaction_id', ''), 120);
$deliveryNote  = clean(input('delivery_note', ''), 500);
$adminNote     = input('admin_note') !== null ? clean(input('admin_note', ''), 2000) : null;

// Handover fields
$hoSiteUrl   = input('handover_site_url')   !== null ? clean(input('handover_site_url', ''), 500)   : null;
$hoAdminUrl  = input('handover_admin_url')  !== null ? clean(input('handover_admin_url', ''), 500)  : null;
$hoAdminUser = input('handover_admin_user') !== null ? clean(input('handover_admin_user', ''), 150) : null;
$hoAdminPass = input('handover_admin_pass') !== null ? clean(input('handover_admin_pass', ''), 200) : null;
$hoNote      = input('handover_note')       !== null ? clean(input('handover_note', ''), 3000)      : null;
$hoSendNow   = (int) input('handover_send_now', 0) === 1;

if ($orderId <= 0) json_error('Order ID is required.', 422, ['order_id' => 'Missing.']);

/* ---------- Fetch order ---------- */
$oStmt = $pdo->prepare("
    SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id = ?
    LIMIT 1
");
$oStmt->execute([$orderId]);
$order = $oStmt->fetch();
if (!$order) json_error('Order not found.', 404);

$allowedOrderStatus = ['pending','confirmed','processing','in_progress','completed','cancelled'];
$allowedPayStatus   = ['pending','paid','failed','refunded'];

$changesMade   = [];
$extendedItems = [];
$handoverSent  = false;

/* ========================================================
   1. HANDOVER SAVE (item-specific)
   ======================================================== */
if ($itemId > 0 && ($hoSiteUrl !== null || $hoAdminUrl !== null || $hoNote !== null || $hoAdminUser !== null)) {
    $iStmt = $pdo->prepare("SELECT id, service_id, service_title FROM order_items WHERE id = ? AND order_id = ? LIMIT 1");
    $iStmt->execute([$itemId, $orderId]);
    $item = $iStmt->fetch();
    if (!$item) json_error('Order item not found.', 404);

    try {
        // Track whether any handover field changed → reset handover_sent_at
        $resetSentAt = false;
        $existing = $pdo->prepare("SELECT handover_site_url, handover_admin_url, handover_admin_user, handover_admin_pass, handover_note FROM order_items WHERE id = ?");
        $existing->execute([$itemId]);
        $oldHo = $existing->fetch() ?: [];
        if (
            ($oldHo['handover_site_url']   ?? '') !== ($hoSiteUrl   ?? '') ||
            ($oldHo['handover_admin_url']  ?? '') !== ($hoAdminUrl  ?? '') ||
            ($oldHo['handover_admin_user'] ?? '') !== ($hoAdminUser ?? '') ||
            ($oldHo['handover_admin_pass'] ?? '') !== ($hoAdminPass ?? '') ||
            ($oldHo['handover_note']       ?? '') !== ($hoNote      ?? '')
        ) {
            $resetSentAt = true;
        }

        $sql = "UPDATE order_items SET
                    handover_site_url   = ?,
                    handover_admin_url  = ?,
                    handover_admin_user = ?,
                    handover_admin_pass = ?,
                    handover_note       = ?";
        $params = [
            $hoSiteUrl   ?? null,
            $hoAdminUrl  ?? null,
            $hoAdminUser ?? null,
            $hoAdminPass ?? null,
            $hoNote      ?? null,
        ];
        if ($resetSentAt) {
            $sql .= ", handover_sent_at = NULL";
        }
        $sql .= " WHERE id = ?";
        $params[] = $itemId;

        $pdo->prepare($sql)->execute($params);
        $changesMade[] = "Handover saved for item #{$itemId}";

        /* ---------- Send now ---------- */
        if ($hoSendNow && $resetSentAt) {
            try {
                $sent = send_handover_email($itemId, (int)$order['user_id'], $item['service_title']);
                if ($sent) {
                    $pdo->prepare("UPDATE order_items SET handover_sent_at = NOW() WHERE id = ?")
                        ->execute([$itemId]);

                    // In-app notification
                    push_notification(
                        'user',
                        (int)$order['user_id'],
                        '🎁 Your project is ready!',
                        "Handover details for \"{$item['service_title']}\" are now available in your account.",
                        'user/order-details.php?id=' . $orderId,
                        'success'
                    );
                    $handoverSent  = true;
                    $changesMade[] = "Handover email sent to user";
                } else {
                    $changesMade[] = "⚠ Handover saved but email failed";
                }
            } catch (Exception $e) {
                error_log('Handover send failed: ' . $e->getMessage());
                $changesMade[] = "⚠ Handover email exception";
            }
        }
    } catch (Exception $e) {
        if (WCB_ENV === 'development') json_error('Handover save failed: ' . $e->getMessage(), 500);
        json_error('Could not save handover info.', 500);
    }
}

/* ========================================================
   2. ITEM DELIVERY MARK
   ======================================================== */
if ($itemId > 0 && $deliveryNote !== '' || ($itemId > 0 && (int) input('mark_delivered', 0) === 1)) {
    $iStmt = $pdo->prepare("SELECT id, delivery_status, delivered_at FROM order_items WHERE id = ? AND order_id = ? LIMIT 1");
    $iStmt->execute([$itemId, $orderId]);
    $item = $iStmt->fetch();
    if (!$item) json_error('Order item not found.', 404);

    if ($item['delivery_status'] === 'delivered') {
        $changesMade[] = "Item #{$itemId} already delivered";
    } else {
        try {
            $pdo->prepare("
                UPDATE order_items
                SET delivery_status = 'delivered',
                    delivered_at = NOW(),
                    delivery_note = COALESCE(?, delivery_note)
                WHERE id = ?
            ")->execute([$deliveryNote ?: null, $itemId]);

            $changesMade[] = "Item #{$itemId} marked delivered";

            // Notify user
            push_notification(
                'user',
                (int)$order['user_id'],
                '📦 Delivery confirmed',
                'Your order item has been marked as delivered.',
                'user/order-details.php?id=' . $orderId,
                'success'
            );
        } catch (Exception $e) {
            json_error('Could not update item.', 500);
        }
    }
}

/* ========================================================
   3. ORDER STATUS UPDATE
   ======================================================== */
if ($orderStatus !== '' && in_array($orderStatus, $allowedOrderStatus, true)) {
    if ($orderStatus !== $order['order_status']) {
        try {
            $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?")
                ->execute([$orderStatus, $orderId]);
            $changesMade[] = "Status: {$orderStatus}";

            push_notification(
                'user', (int)$order['user_id'],
                'Order #' . $order['order_number'] . ' updated',
                'New status: ' . ucfirst(str_replace('_', ' ', $orderStatus)),
                'user/order-details.php?id=' . $orderId,
                'info'
            );

            $order['order_status'] = $orderStatus;
        } catch (Exception $e) {
            json_error('Could not update status.', 500);
        }
    }
}

/* ========================================================
   4. PAYMENT STATUS UPDATE
   ======================================================== */
$becamePaidNow = false;

if ($paymentStatus !== '' && in_array($paymentStatus, $allowedPayStatus, true)) {
    $oldPayStatus = $order['payment_status'];
    $becamePaidNow = ($paymentStatus === 'paid' && $oldPayStatus !== 'paid');

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?")
            ->execute([$paymentStatus, $orderId]);

        $payStatusMap = [
            'paid'     => 'success',
            'failed'   => 'failed',
            'refunded' => 'refunded',
            'pending'  => 'pending',
        ];
        $payStatusVal = $payStatusMap[$paymentStatus] ?? 'pending';

        $payUpdate = "UPDATE payments SET status = ?";
        $payParams = [$payStatusVal];
        if ($txnId !== '') {
            $payUpdate .= ", transaction_id = ?";
            $payParams[] = $txnId;
        }
        if ($paymentStatus === 'paid') {
            $payUpdate .= ", paid_at = NOW()";
        }
        $payUpdate .= " WHERE id = (SELECT id FROM (SELECT id FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1) AS t)";
        $payParams[] = $orderId;

        $pdo->prepare($payUpdate)->execute($payParams);

        if ($becamePaidNow && in_array($order['order_status'], ['pending'], true)) {
            $pdo->prepare("UPDATE orders SET order_status = 'confirmed' WHERE id = ?")->execute([$orderId]);
            $order['order_status'] = 'confirmed';
            $changesMade[] = 'Auto-confirmed after payment';
        }

        $changesMade[] = "Payment: {$paymentStatus}";
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (WCB_ENV === 'development') json_error('Payment update failed: ' . $e->getMessage(), 500);
        json_error('Could not update payment.', 500);
    }
}

/* ========================================================
   5. WHEN PAID → ACTIVATE SUBSCRIPTIONS + START DELIVERY
   ======================================================== */
if ($becamePaidNow) {
    try {
        $extendedItems = process_paid_order($pdo, $orderId, (int)$order['user_id']);
        if (!empty($extendedItems)) {
            $changesMade[] = count($extendedItems) . ' subscription(s) activated/extended';
        }
        if (!empty($extendedItems)) {
            $lines = [];
            foreach ($extendedItems as $ext) {
                $lines[] = "• {$ext['service_title']} — {$ext['action']}" .
                           (!empty($ext['new_expires_at']) ? " (until " . date('M d, Y', strtotime($ext['new_expires_at'])) . ")" : '');
            }
            push_notification(
                'user', (int)$order['user_id'],
                ($order['is_renewal'] ? '🔄 Renewal approved' : '✅ Subscription activated'),
                "Order #{$order['order_number']}:\n" . implode("\n", $lines),
                'user/order-details.php?id=' . $orderId,
                'success'
            );
        }
    } catch (Exception $e) {
        error_log('Subscription extend failed: ' . $e->getMessage());
        $changesMade[] = '⚠ Extend failed — check logs';
    }
}

/* ========================================================
   6. ADMIN NOTE
   ======================================================== */
if ($adminNote !== null) {
    try {
        $pdo->prepare("UPDATE orders SET admin_note = ? WHERE id = ?")->execute([$adminNote, $orderId]);
        $changesMade[] = 'Admin note saved';
    } catch (Exception $e) { /* silent */ }
}

/* ========================================================
   7. LOG
   ======================================================== */
try {
    log_activity('order_updated', 'orders', $orderId,
        'By admin #' . $adminId . ' — ' . implode('; ', $changesMade));
} catch (Exception $e) { /* silent */ }

/* ========================================================
   8. RESPONSE
   ======================================================== */
$newOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$newOrder->execute([$orderId]);
$freshOrder = $newOrder->fetch();

json_success([
    'order_id'       => $orderId,
    'order_status'   => $freshOrder['order_status'],
    'payment_status' => $freshOrder['payment_status'],
    'is_renewal'     => (int)$freshOrder['is_renewal'],
    'changes'        => $changesMade,
    'extended_items' => $extendedItems,
    'handover_sent'  => $handoverSent,
], $handoverSent ? 'Handover saved & sent to customer.' : 'Order updated successfully.');

/* ========================================================
   HELPER: Process order after payment confirmed
   - Subscription → set starts_at / expires_at / delivery_due_at
   - Trial → set starts_at / expires_at / delivery_due_at
   - Lifetime → set starts_at, no expiry
   - Renewal → extend original item
   ======================================================== */
function process_paid_order(PDO $pdo, int $orderId, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT oi.*, o.is_renewal, o.renews_item_id
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();

    $results = [];

    foreach ($items as $it) {
        $pkgType = $it['package_type'] ?? null;
        $isTrial = (int)$it['is_trial'] === 1;

        /* Delivery countdown calculation */
        $deliveryDays = max(1, (int)($it['delivery_days'] ?? 0));
        if ($deliveryDays <= 0) {
            // Fallback: service default
            if (!empty($it['service_id'])) {
                $sQ = $pdo->prepare("SELECT delivery_days FROM services WHERE id = ? LIMIT 1");
                $sQ->execute([(int)$it['service_id']]);
                $deliveryDays = max(1, (int)$sQ->fetchColumn() ?: 3);
            } else {
                $deliveryDays = 3;
            }
        }
        $deliveryDueAt = date('Y-m-d H:i:s', time() + ($deliveryDays * 86400));

        /* Skip if already processed */
        if (!empty($it['starts_at']) && !$it['is_renewal'] && empty($it['renews_item_id'])) {
            if ($pkgType === 'lifetime') continue;
            if (!empty($it['expires_at'])) continue;
        }

        /* ================================================
           RENEWAL — extend original
           ================================================ */
        if (!empty($it['is_renewal']) || !empty($it['renews_item_id'])) {
            $origId = (int)($it['renews_item_id'] ?? 0);
            if ($origId <= 0) continue;

            $orig = $pdo->prepare("SELECT * FROM order_items WHERE id = ? LIMIT 1");
            $orig->execute([$origId]);
            $origItem = $orig->fetch();
            if (!$origItem) continue;

            $now = time();
            $addDays = max(1, (int)($it['duration_days'] ?? 30));

            $baseTime = $now;
            if (!empty($origItem['expires_at']) && strtotime($origItem['expires_at']) > $now) {
                $baseTime = strtotime($origItem['expires_at']);
            }

            $newExpires = date('Y-m-d H:i:s', $baseTime + ($addDays * 86400));

            $pdo->prepare("UPDATE order_items SET expires_at = ?, alert_sent_at = NULL WHERE id = ?")
                ->execute([$newExpires, $origId]);

            $pdo->prepare("
                UPDATE order_items
                SET starts_at = NOW(), expires_at = ?, delivery_status = 'delivered',
                    delivered_at = NOW(),
                    delivery_note = CONCAT(COALESCE(delivery_note, ''), 'Renewal applied. ')
                WHERE id = ?
            ")->execute([$newExpires, (int)$it['id']]);

            $results[] = [
                'item_id'        => $origId,
                'service_title'  => $origItem['service_title'],
                'action'         => 'Renewed +' . $addDays . ' days',
                'old_expires_at' => $origItem['expires_at'],
                'new_expires_at' => $newExpires,
            ];
            continue;
        }

        /* ================================================
           TRIAL
           ================================================ */
        if ($isTrial || $pkgType === 'trial') {
            $days = max(1, (int)($it['duration_days'] ?? 7));
            $startsAt  = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));

            $pdo->prepare("
                UPDATE order_items
                SET starts_at = ?, expires_at = ?,
                    delivery_days = ?,
                    delivery_started_at = NOW(),
                    delivery_due_at = ?,
                    alert_sent_at = NULL
                WHERE id = ?
            ")->execute([$startsAt, $expiresAt, $deliveryDays, $deliveryDueAt, (int)$it['id']]);

            $results[] = [
                'item_id'        => (int)$it['id'],
                'service_title'  => $it['service_title'],
                'action'         => "Trial started ({$days} days)",
                'old_expires_at' => null,
                'new_expires_at' => $expiresAt,
            ];
            continue;
        }

        /* ================================================
           LIFETIME
           ================================================ */
        if ($pkgType === 'lifetime') {
            $pdo->prepare("
                UPDATE order_items
                SET starts_at = NOW(), expires_at = NULL,
                    delivery_days = ?,
                    delivery_started_at = NOW(),
                    delivery_due_at = ?,
                    alert_sent_at = NULL
                WHERE id = ?
            ")->execute([$deliveryDays, $deliveryDueAt, (int)$it['id']]);

            $results[] = [
                'item_id'        => (int)$it['id'],
                'service_title'  => $it['service_title'],
                'action'         => 'Lifetime activated',
                'old_expires_at' => null,
                'new_expires_at' => null,
            ];
            continue;
        }

        /* ================================================
           NORMAL SUBSCRIPTION
           ================================================ */
        $days = max(1, (int)($it['duration_days'] ?? 30));
        $startsAt  = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));

        $pdo->prepare("
            UPDATE order_items
            SET starts_at = ?, expires_at = ?,
                delivery_days = ?,
                delivery_started_at = NOW(),
                delivery_due_at = ?,
                alert_sent_at = NULL
            WHERE id = ?
        ")->execute([$startsAt, $expiresAt, $deliveryDays, $deliveryDueAt, (int)$it['id']]);

        $results[] = [
            'item_id'        => (int)$it['id'],
            'service_title'  => $it['service_title'],
            'action'         => "Activated ({$days} days)",
            'old_expires_at' => null,
            'new_expires_at' => $expiresAt,
        ];
    }

    return $results;
}