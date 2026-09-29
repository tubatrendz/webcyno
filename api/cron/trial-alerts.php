<?php
/**
 * api/cron/trial-alerts.php — Automatic Trial & Renewal Alerts (cron job)
 * Version: 1.0
 *
 * কখন চালাবে: প্রতিদিন একবার (cron)
 * cPanel Cron Command:
 *   /usr/local/bin/php /home/USERNAME/public_html/api/cron/trial-alerts.php
 *
 * কাজ:
 *   1. Trial যেগুলো 48h এর মধ্যে শেষ হবে → user কে alert
 *   2. Subscription যেগুলো 7 দিনের মধ্যে শেষ হবে → renewal reminder
 *   3. Expired হয়ে যাওয়া trial → final notice
 *   4. সব alert একবারই পাঠাবে (alert_sent_at check)
 */

define('CRON_RUN', true);

// Set working dir + load config
$baseDir = dirname(__DIR__, 2);   // project root
chdir($baseDir);

if (!file_exists($baseDir . '/api/config.php')) {
    fwrite(STDERR, "Config not found\n");
    exit(1);
}

require_once $baseDir . '/api/config.php';
require_once $baseDir . '/api/helpers.php';

// CLI check — শুধু command line থেকে চালানো যাবে (web access block)
if (php_sapi_name() !== 'cli' && !defined('WCB_ENV')) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $pdo;

$startTime = microtime(true);
$logFile   = __DIR__ . '/../../logs/cron-alerts.log';
$now       = time();
$today     = date('Y-m-d H:i:s');

function cron_log(string $msg): void {
    global $logFile;
    @file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n",
        FILE_APPEND
    );
}

cron_log('===== Cron started =====');

/* ---------- Settings ---------- */
$trialAlertHours     = (int) setting('default_trial_alert_hours', 48);
$renewalAlertDays    = (int) setting('renewal_alert_days_before', 7);
$renewalEnabled      = (int) setting('renewal_enabled', 1) === 1;
$siteName            = setting('site_name', 'Webcyno');

/* ========================================================
   1. TRIAL ALERTS (48 ঘণ্টার মধ্যে শেষ হবে)
   ======================================================== */
$stats = [
    'trial_alerts'    => 0,
    'renewal_alerts'  => 0,
    'expired_notices' => 0,
    'errors'          => 0,
];

try {
    $trialSql = "
        SELECT oi.id AS item_id, oi.expires_at, oi.service_title, oi.package_name,
               oi.duration_days, oi.alert_sent_at,
               o.id AS order_id, o.order_number, o.user_id, o.currency,
               u.name AS user_name, u.email AS user_email,
               s.slug AS service_slug
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN users u ON u.id = o.user_id
        LEFT JOIN services s ON s.id = oi.service_id
        WHERE oi.is_trial = 1
          AND oi.expires_at IS NOT NULL
          AND oi.expires_at > NOW()
          AND oi.expires_at <= DATE_ADD(NOW(), INTERVAL ? HOUR)
          AND (oi.alert_sent_at IS NULL)
          AND o.payment_status = 'paid'
          AND o.order_status NOT IN ('cancelled')
    ";
    $stmt = $pdo->prepare($trialSql);
    $stmt->execute([$trialAlertHours]);
    $trialRows = $stmt->fetchAll();

    cron_log("Found " . count($trialRows) . " trial(s) expiring within {$trialAlertHours}h");

    foreach ($trialRows as $t) {
        $itemId = (int) $t['item_id'];
        $userId = (int) $t['user_id'];
        $secsLeft = strtotime($t['expires_at']) - $now;
        $hoursLeft = max(1, ceil($secsLeft / 3600));
        $expDate = date('M d, Y g:i A', strtotime($t['expires_at']));

        // ---- In-app notification ----
        try {
            push_notification(
                'user',
                $userId,
                '⚠️ Trial ending soon',
                "Your free trial for \"{$t['service_title']}\" will expire in {$hoursLeft} hours ({$expDate}). Upgrade now to keep access.",
                'user/order-details.php?id=' . (int) $t['order_id'],
                'warning'
            );
        } catch (Exception $e) {
            cron_log("Notify failed (item #{$itemId}): " . $e->getMessage());
            $stats['errors']++;
        }

        // ---- Email ----
        try {
            send_trial_alert_email($t, $hoursLeft, $siteName);
        } catch (Exception $e) {
            cron_log("Email failed (item #{$itemId}): " . $e->getMessage());
            $stats['errors']++;
        }

        // ---- Mark alert sent ----
        try {
            $pdo->prepare("UPDATE order_items SET alert_sent_at = NOW() WHERE id = ?")
                ->execute([$itemId]);
            $stats['trial_alerts']++;
        } catch (Exception $e) {
            cron_log("Mark alert failed (item #{$itemId}): " . $e->getMessage());
        }

        cron_log("  → Trial alert sent to user #{$userId} (item #{$itemId})");
    }

} catch (Exception $e) {
    cron_log('TRIAL ALERT ERROR: ' . $e->getMessage());
    $stats['errors']++;
}

/* ========================================================
   2. RENEWAL ALERTS (7 দিনের মধ্যে শেষ হবে)
   ======================================================== */
if ($renewalEnabled) {
    try {
        $renewSql = "
            SELECT oi.id AS item_id, oi.expires_at, oi.service_title, oi.package_name,
                   oi.duration_days, oi.alert_sent_at,
                   o.id AS order_id, o.order_number, o.user_id, o.currency,
                   u.name AS user_name, u.email AS user_email,
                   s.slug AS service_slug
            FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            INNER JOIN users u ON u.id = o.user_id
            LEFT JOIN services s ON s.id = oi.service_id
            WHERE oi.package_type = 'subscription'
              AND oi.expires_at IS NOT NULL
              AND oi.expires_at > NOW()
              AND oi.expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
              AND (oi.alert_sent_at IS NULL)
              AND o.payment_status = 'paid'
              AND o.order_status NOT IN ('cancelled')
        ";
        $stmt = $pdo->prepare($renewSql);
        $stmt->execute([$renewalAlertDays]);
        $renewalRows = $stmt->fetchAll();

        cron_log("Found " . count($renewalRows) . " subscription(s) expiring within {$renewalAlertDays}d");

        foreach ($renewalRows as $r) {
            $itemId = (int) $r['item_id'];
            $userId = (int) $r['user_id'];
            $secsLeft = strtotime($r['expires_at']) - $now;
            $daysLeft = max(1, ceil($secsLeft / 86400));
            $expDate  = date('M d, Y', strtotime($r['expires_at']));

            // ---- In-app notification ----
            try {
                push_notification(
                    'user',
                    $userId,
                    '🔄 Subscription renewal due',
                    "Your subscription for \"{$r['service_title']}\" expires in {$daysLeft} days ({$expDate}). Renew now to avoid interruption.",
                    'checkout.php?renew_item_id=' . $itemId,
                    'warning'
                );
            } catch (Exception $e) {
                cron_log("Renewal notify failed: " . $e->getMessage());
                $stats['errors']++;
            }

            // ---- Email ----
            try {
                send_renewal_alert_email($r, $daysLeft, $siteName);
            } catch (Exception $e) {
                cron_log("Renewal email failed: " . $e->getMessage());
                $stats['errors']++;
            }

            // ---- Mark ----
            try {
                $pdo->prepare("UPDATE order_items SET alert_sent_at = NOW() WHERE id = ?")
                    ->execute([$itemId]);
                $stats['renewal_alerts']++;
            } catch (Exception $e) {
                cron_log("Renewal mark failed: " . $e->getMessage());
            }

            cron_log("  → Renewal reminder sent to user #{$userId} (item #{$itemId})");
        }

    } catch (Exception $e) {
        cron_log('RENEWAL ALERT ERROR: ' . $e->getMessage());
        $stats['errors']++;
    }
}

/* ========================================================
   3. EXPIRED NOTICE (যদি alert না পাঠানো হয় এবং এখন expired হয়ে গেছে)
   ======================================================== */
try {
    $expSql = "
        SELECT oi.id AS item_id, oi.expires_at, oi.service_title,
               o.id AS order_id, o.user_id, o.currency,
               u.email AS user_email, u.name AS user_name
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN users u ON u.id = o.user_id
        WHERE oi.expires_at IS NOT NULL
          AND oi.expires_at <= NOW()
          AND oi.expires_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
          AND (oi.alert_sent_at IS NULL OR oi.alert_sent_at < DATE_SUB(NOW(), INTERVAL 1 DAY))
          AND oi.package_type IN ('trial', 'subscription')
          AND o.payment_status = 'paid'
          AND o.order_status NOT IN ('cancelled')
    ";
    $expStmt = $pdo->prepare($expSql);
    $expStmt->execute();
    $expiredRows = $expStmt->fetchAll();

    cron_log("Found " . count($expiredRows) . " just-expired item(s)");

    foreach ($expiredRows as $e) {
        $userId = (int) $e['user_id'];
        $itemId = (int) $e['item_id'];

        try {
            push_notification(
                'user',
                $userId,
                '❌ Subscription expired',
                "Your access to \"{$e['service_title']}\" has expired. Renew now to restore it.",
                'user/order-details.php?id=' . (int) $e['order_id'],
                'error'
            );
        } catch (Exception $ex) {
            cron_log("Expired notify failed: " . $ex->getMessage());
            $stats['errors']++;
        }

        try {
            $pdo->prepare("UPDATE order_items SET alert_sent_at = NOW() WHERE id = ?")
                ->execute([$itemId]);
            $stats['expired_notices']++;
        } catch (Exception $ex) { /* silent */ }
    }

} catch (Exception $e) {
    cron_log('EXPIRED NOTICE ERROR: ' . $e->getMessage());
    $stats['errors']++;
}

/* ========================================================
   4. CLEANUP — পুরনো alert_sent_at reset করা (নতুন cycle এর জন্য)
   ======================================================== */
try {
    // ১ মাস আগে যেসব expiry ছিল সেগুলার alert_sent_at clear করে দিই না — 
    // ওগুলো historical records
    // তবে ৩ মাসের বেশি পুরনো অচল records ignore করা
    cron_log('Cleanup skipped — historical records preserved');
} catch (Exception $e) { /* silent */ }

/* ========================================================
   FINAL LOG
   ======================================================== */
$elapsed = round(microtime(true) - $startTime, 2);
$summary = sprintf(
    'DONE in %ss — Trial: %d, Renewal: %d, Expired: %d, Errors: %d',
    $elapsed,
    $stats['trial_alerts'],
    $stats['renewal_alerts'],
    $stats['expired_notices'],
    $stats['errors']
);
cron_log($summary);
cron_log('===== Cron finished =====');

/* ---------- Output (CLI হলে দেখাবে) ---------- */
if (php_sapi_name() === 'cli') {
    echo $summary . "\n";
}

/* ========================================================
   HELPER: Trial alert email
   ======================================================== */
function send_trial_alert_email(array $t, int $hoursLeft, string $siteName): void {
    if (empty($t['user_email'])) return;

    $firstName = explode(' ', trim($t['user_name'] ?: 'there'))[0];
    $expDate = date('M d, Y g:i A', strtotime($t['expires_at']));
    $orderUrl = base_url('user/order-details.php?id=' . (int)$t['order_id']);

    $subject = "⚠️ Your {$siteName} trial ends in {$hoursLeft} hours";

    $body = '
    <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;color:#0F172A;background:#F8FAFC;">
      <div style="background:#fff;border-radius:12px;padding:24px;border:1px solid #E2E8F0;">
        <h2 style="color:#2563EB;margin:0 0 16px;">' . e($siteName) . '</h2>

        <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:14px;margin-bottom:20px;">
          <p style="margin:0;font-size:14px;color:#92400E;">
            <strong>⏱ Trial ending soon!</strong>
          </p>
        </div>

        <p>Hi ' . e($firstName) . ',</p>
        <p>Your free trial for <strong>' . e($t['service_title']) . '</strong> will end in
           <strong style="color:#DC2626;">' . $hoursLeft . ' hours</strong>.</p>

        <div style="background:#F1F5F9;padding:14px;border-radius:8px;margin:16px 0;font-size:14px;">
          <div style="color:#64748B;font-size:12px;margin-bottom:4px;">EXPIRES</div>
          <div style="font-weight:700;color:#0F172A;">' . e($expDate) . '</div>
        </div>

        <p>Upgrade now to keep your access without interruption.</p>

        <a href="' . $orderUrl . '"
           style="display:inline-block;background:#2563EB;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;margin:8px 0;">
          Upgrade Now →
        </a>

        <hr style="border:none;border-top:1px solid #E2E8F0;margin:24px 0;">
        <p style="font-size:12px;color:#94A3B8;margin:0;">
          ' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '
        </p>
      </div>
    </div>';

    send_email($t['user_email'], $subject, $body, $t['user_name']);
}

/* ========================================================
   HELPER: Renewal alert email
   ======================================================== */
function send_renewal_alert_email(array $r, int $daysLeft, string $siteName): void {
    if (empty($r['user_email'])) return;

    $firstName = explode(' ', trim($r['user_name'] ?: 'there'))[0];
    $expDate = date('M d, Y', strtotime($r['expires_at']));
    $renewUrl = base_url('checkout.php?renew_item_id=' . (int)$r['item_id']);

    $subject = "🔄 Renewal reminder: {$siteName} subscription expires in {$daysLeft} days";

    $body = '
    <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;color:#0F172A;background:#F8FAFC;">
      <div style="background:#fff;border-radius:12px;padding:24px;border:1px solid #E2E8F0;">
        <h2 style="color:#2563EB;margin:0 0 16px;">' . e($siteName) . '</h2>

        <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:14px;margin-bottom:20px;">
          <p style="margin:0;font-size:14px;color:#92400E;">
            <strong>🔄 Time to renew</strong>
          </p>
        </div>

        <p>Hi ' . e($firstName) . ',</p>
        <p>Your subscription for <strong>' . e($r['service_title']) . '</strong> expires in
           <strong style="color:#D97706;">' . $daysLeft . ' days</strong>.</p>

        <div style="background:#F1F5F9;padding:14px;border-radius:8px;margin:16px 0;font-size:14px;">
          <div style="color:#64748B;font-size:12px;margin-bottom:4px;">EXPIRES ON</div>
          <div style="font-weight:700;color:#0F172A;">' . e($expDate) . '</div>
        </div>

        <p>Renew now to continue enjoying uninterrupted service.</p>

        <a href="' . $renewUrl . '"
           style="display:inline-block;background:#F59E0B;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;margin:8px 0;">
          Renew Subscription →
        </a>

        <hr style="border:none;border-top:1px solid #E2E8F0;margin:24px 0;">
        <p style="font-size:12px;color:#94A3B8;margin:0;">
          ' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '
        </p>
      </div>
    </div>';

    send_email($r['user_email'], $subject, $body, $r['user_name']);
}