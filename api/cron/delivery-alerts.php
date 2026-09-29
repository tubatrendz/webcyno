<?php
/**
 * api/cron/delivery-alerts.php — Delivery Deadline Alerts (cron job)
 * Version: 1.0
 *
 * কখন চালাবে: প্রতি ঘণ্টায়
 * cPanel Cron Command:
 *   /usr/local/bin/php /home/USERNAME/public_html/api/cron/delivery-alerts.php
 *
 * কাজ:
 *   1. Admin কে alert — delivery due date এর X ঘণ্টা আগে
 *   2. Admin কে escalation — overdue হলে
 *   3. User কে inform — delivery overdue হলে (auto-notify)
 *   4. সব alert একবারই (per stage)
 */

define('CRON_RUN', true);

$baseDir = dirname(__DIR__, 2);
chdir($baseDir);

if (!file_exists($baseDir . '/api/config.php')) {
    fwrite(STDERR, "Config not found\n");
    exit(1);
}

require_once $baseDir . '/api/config.php';
require_once $baseDir . '/api/helpers.php';

if (php_sapi_name() !== 'cli' && !defined('WCB_ENV')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $pdo;

$startTime = microtime(true);
$logFile   = __DIR__ . '/../../logs/cron-delivery.log';
$now       = time();

function cron_log(string $msg): void {
    global $logFile;
    @file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n",
        FILE_APPEND
    );
}

cron_log('===== Delivery cron started =====');

/* ---------- Settings ---------- */
$alertHoursBefore = max(1, (int) setting('delivery_late_alert_hours', 12));
$siteName         = setting('site_name', 'Webcyno');

$stats = [
    'pre_due_alerts'  => 0,
    'overdue_alerts'  => 0,
    'user_notices'    => 0,
    'errors'          => 0,
];

/* ========================================================
   1. PRE-DUE ALERTS — Admin কে জানাব (due date এর আগে)
   ======================================================== */
try {
    $sql = "
        SELECT oi.id AS item_id, oi.delivery_due_at, oi.service_title,
               oi.delivery_days,
               o.id AS order_id, o.order_number, o.user_id,
               u.name AS user_name, u.email AS user_email,
               TIMESTAMPDIFF(SECOND, NOW(), oi.delivery_due_at) AS seconds_left
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN users u ON u.id = o.user_id
        WHERE oi.delivery_due_at IS NOT NULL
          AND oi.delivered_at IS NULL
          AND oi.delivery_due_at > NOW()
          AND oi.delivery_due_at <= DATE_ADD(NOW(), INTERVAL ? HOUR)
          AND o.payment_status = 'paid'
          AND o.order_status NOT IN ('cancelled','completed')
          AND (oi.delivery_pre_alert_sent_at IS NULL)
    ";

    // Check if column exists; if not, fallback to no-alert tracking
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$alertHoursBefore]);
        $preDueItems = $stmt->fetchAll();
    } catch (Exception $e) {
        // Column doesn't exist — use delivery alerts via notification dedup
        $preDueItems = [];
        cron_log("Pre-due query failed (col missing?): " . $e->getMessage());
    }

    cron_log("Found " . count($preDueItems) . " item(s) due in {$alertHoursBefore}h");

    foreach ($preDueItems as $it) {
        $itemId    = (int) $it['item_id'];
        $orderId   = (int) $it['order_id'];
        $secsLeft  = (int) $it['seconds_left'];
        $hoursLeft = max(1, (int) ceil($secsLeft / 3600));
        $dueDate   = date('M d, Y g:i A', strtotime($it['delivery_due_at']));

        /* Admin notification */
        try {
            push_notification(
                'admin',
                null,
                '⏰ Delivery deadline approaching',
                "Order #{$it['order_number']} — \"{$it['service_title']}\" is due in {$hoursLeft} hours ({$dueDate}). Please complete delivery.",
                'admin/order-details.php?id=' . $orderId,
                'warning'
            );
            $stats['pre_due_alerts']++;
        } catch (Exception $e) {
            cron_log("Admin notify failed: " . $e->getMessage());
            $stats['errors']++;
        }

        /* Mark as alerted (best-effort) */
        try {
            $pdo->prepare("UPDATE order_items SET delivery_pre_alert_sent_at = NOW() WHERE id = ?")
                ->execute([$itemId]);
        } catch (Exception $e) {
            // Column missing → try without
            cron_log("Could not stamp pre_alert_sent_at: " . $e->getMessage());
        }

        cron_log("  → Pre-due alert: item #{$itemId} (order #{$orderId}), {$hoursLeft}h left");
    }

} catch (Exception $e) {
    cron_log('PRE-DUE ERROR: ' . $e->getMessage());
    $stats['errors']++;
}

/* ========================================================
   2. OVERDUE ALERTS — Admin কে escalation + user inform
   ======================================================== */
try {
    $sqlOverdue = "
        SELECT oi.id AS item_id, oi.delivery_due_at, oi.service_title,
               o.id AS order_id, o.order_number, o.user_id,
               u.name AS user_name, u.email AS user_email,
               TIMESTAMPDIFF(SECOND, oi.delivery_due_at, NOW()) AS seconds_late
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN users u ON u.id = o.user_id
        WHERE oi.delivery_due_at IS NOT NULL
          AND oi.delivered_at IS NULL
          AND oi.delivery_due_at < NOW()
          AND oi.delivery_due_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND o.payment_status = 'paid'
          AND o.order_status NOT IN ('cancelled','completed')
          AND (oi.delivery_overdue_sent_at IS NULL)
    ";

    try {
        $stmtOv = $pdo->prepare($sqlOverdue);
        $stmtOv->execute();
        $overdueItems = $stmtOv->fetchAll();
    } catch (Exception $e) {
        $overdueItems = [];
        cron_log("Overdue query failed: " . $e->getMessage());
    }

    cron_log("Found " . count($overdueItems) . " overdue item(s)");

    foreach ($overdueItems as $it) {
        $itemId    = (int) $it['item_id'];
        $orderId   = (int) $it['order_id'];
        $userId    = (int) $it['user_id'];
        $secsLate  = (int) $it['seconds_late'];
        $hoursLate = max(1, (int) floor($secsLate / 3600));
        $dueDate   = date('M d, Y g:i A', strtotime($it['delivery_due_at']));

        /* Admin notification (escalation) */
        try {
            push_notification(
                'admin',
                null,
                '🚨 OVERDUE: Delivery past deadline',
                "Order #{$it['order_number']} — \"{$it['service_title']}\" is {$hoursLate}h OVERDUE (was due {$dueDate}). Immediate action needed.",
                'admin/order-details.php?id=' . $orderId,
                'error'
            );
            $stats['overdue_alerts']++;
        } catch (Exception $e) {
            cron_log("Overdue admin notify failed: " . $e->getMessage());
            $stats['errors']++;
        }

        /* User notification — apologetic + update */
        try {
            push_notification(
                'user',
                $userId,
                '⏳ Your order is taking a bit longer',
                "We're sorry — your order #{$it['order_number']} for \"{$it['service_title']}\" is taking a little more time than expected. Our team is actively working on it and will keep you updated.",
                'user/order-details.php?id=' . $orderId,
                'warning'
            );
            $stats['user_notices']++;
        } catch (Exception $e) {
            cron_log("User overdue notify failed: " . $e->getMessage());
            $stats['errors']++;
        }

        /* Mark overdue notified */
        try {
            $pdo->prepare("UPDATE order_items SET delivery_overdue_sent_at = NOW() WHERE id = ?")
                ->execute([$itemId]);
        } catch (Exception $e) {
            cron_log("Could not stamp overdue_sent_at: " . $e->getMessage());
        }

        cron_log("  → Overdue alert: item #{$itemId} (order #{$orderId}), {$hoursLate}h late");
    }

} catch (Exception $e) {
    cron_log('OVERDUE ERROR: ' . $e->getMessage());
    $stats['errors']++;
}

/* ========================================================
   3. OPTIONAL: Auto-complete old orders (delivered items → order completed)
   ======================================================== */
try {
    $completeStmt = $pdo->prepare("
        SELECT o.id, o.order_number
        FROM orders o
        WHERE o.order_status NOT IN ('completed','cancelled')
          AND o.payment_status = 'paid'
          AND NOT EXISTS (
              SELECT 1 FROM order_items oi
              WHERE oi.order_id = o.id
                AND oi.delivered_at IS NULL
          )
          AND EXISTS (SELECT 1 FROM order_items oi2 WHERE oi2.order_id = o.id)
        LIMIT 20
    ");
    $completeStmt->execute();
    $readyOrders = $completeStmt->fetchAll();

    foreach ($readyOrders as $ord) {
        try {
            $pdo->prepare("UPDATE orders SET order_status = 'completed' WHERE id = ?")
                ->execute([(int)$ord['id']]);

            push_notification(
                'user',
                null,
                '✅ Order completed',
                "Order #{$ord['order_number']} is now complete. Thank you!",
                'user/order-details.php?id=' . (int)$ord['id'],
                'success'
            );
            cron_log("  → Auto-completed order #{$ord['order_number']}");
        } catch (Exception $e) {
            cron_log("Auto-complete failed for #{$ord['order_number']}: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    cron_log('AUTO-COMPLETE ERROR: ' . $e->getMessage());
}

/* ========================================================
   FINAL LOG
   ======================================================== */
$elapsed = round(microtime(true) - $startTime, 2);
$summary = sprintf(
    'DONE in %ss — Pre-due: %d, Overdue: %d, User notices: %d, Errors: %d',
    $elapsed,
    $stats['pre_due_alerts'],
    $stats['overdue_alerts'],
    $stats['user_notices'],
    $stats['errors']
);
cron_log($summary);
cron_log('===== Delivery cron finished =====');

if (php_sapi_name() === 'cli') {
    echo $summary . "\n";
}