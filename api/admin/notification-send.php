<?php
/**
 * api/admin/notification-send.php — Send Broadcast
 * Version: 1.0
 * Method: POST (JSON)
 * Body: {
 *   target_type: 'all' | 'specific' | 'single',
 *   title, message, link?, cta_text?, icon?,
 *   channel_in_app, channel_email, channel_sms,
 *   send_now: 0|1,
 *   only_verified?: 0|1,
 *   only_active?: 0|1,
 *   user_ids?: '1,2,3'       (specific),
 *   single_user_id?: 12      (single)
 * }
 * Response: {
 *   broadcast_id,
 *   target_count,
 *   sent_count,
 *   email_sent,
 *   email_failed,
 *   message
 * }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Settings ---------- */
$emailEnabled = (int) setting('broadcast_email_enabled', 1) === 1;
$smsEnabled   = (int) setting('broadcast_sms_enabled', 0) === 1
             && (int) setting('sms_enabled', 0) === 1;
$batchSize    = max(10, min(500, (int) setting('broadcast_batch_size', 50)));

/* ---------- Inputs ---------- */
$targetType   = strtolower(trim((string) input('target_type', 'all')));
$title        = clean(input('title', ''), 200);
$message      = clean(input('message', ''), 5000);
$link         = clean(input('link', ''), 500);
$ctaText      = clean(input('cta_text', ''), 40);
$icon         = strtolower(clean(input('icon', 'info'), 20));

$channelInApp = (int) input('channel_in_app', 1) === 1 ? 1 : 0;
$channelEmail = (int) input('channel_email', 0) === 1 ? 1 : 0;
$channelSms   = (int) input('channel_sms', 0) === 1 ? 1 : 0;
$sendNow      = (int) input('send_now', 1) === 1 ? 1 : 0;

$onlyVerified = (int) input('only_verified', 0) === 1 ? 1 : 0;
$onlyActive   = (int) input('only_active', 0) === 1 ? 1 : 0;

$userIdsRaw   = trim((string) input('user_ids', ''));
$singleUserId = (int) input('single_user_id', 0);

/* ---------- Validation ---------- */
if (!in_array($targetType, ['all', 'specific', 'single'], true)) {
    json_error('Invalid target type.', 422);
}
if ($title === '')   json_error('Title is required.', 422, ['title' => 'Required.']);
if ($message === '') json_error('Message is required.', 422, ['message' => 'Required.']);
if (!$channelInApp && !$channelEmail && !$channelSms) {
    json_error('Select at least one channel.', 422);
}
if ($channelEmail && !$emailEnabled) {
    json_error('Email channel is disabled in settings.', 422);
}
if ($channelSms && !$smsEnabled) {
    json_error('SMS channel is not configured.', 422);
}

if ($targetType === 'specific' && $userIdsRaw === '') {
    json_error('Please select at least one user.', 422);
}
if ($targetType === 'single' && $singleUserId <= 0) {
    json_error('Please pick a user.', 422);
}

/* ---------- Icon whitelist ---------- */
$allowedIcons = ['info','success','warning','error','order','chat','promo'];
if (!in_array($icon, $allowedIcons, true)) $icon = 'info';

/* ========================================================
   RESOLVE TARGET USERS
   ======================================================== */
$recipients = [];   // array of user rows: ['id'=>, 'name'=>, 'email'=>, 'phone'=>]

try {
    if ($targetType === 'all') {
        $where  = ["1=1"];
        $params = [];

        if ($onlyActive) {
            $where[] = "status = 'active'";
        }
        if ($onlyVerified) {
            $where[] = "email_verified = 1";
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone
            FROM users
            WHERE $whereSql
            ORDER BY id ASC
        ");
        $stmt->execute($params);
        $recipients = $stmt->fetchAll();

    } elseif ($targetType === 'specific') {
        $ids = array_values(array_filter(
            array_map('intval', explode(',', $userIdsRaw)),
            fn($i) => $i > 0
        ));
        $ids = array_slice(array_unique($ids), 0, 5000);

        if (empty($ids)) json_error('No valid users selected.', 422);

        $place = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone
            FROM users
            WHERE id IN ($place)
            ORDER BY id ASC
        ");
        $stmt->execute($ids);
        $recipients = $stmt->fetchAll();

    } else { // single
        $stmt = $pdo->prepare("
            SELECT id, name, email, phone
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$singleUserId]);
        $one = $stmt->fetch();
        if ($one) $recipients = [$one];
    }
} catch (Exception $e) {
    if (WCB_ENV === 'development') json_error('Resolve failed: ' . $e->getMessage(), 500);
    json_error('Could not resolve recipients.', 500);
}

$targetCount = count($recipients);
if ($targetCount === 0) {
    json_error('No users matched your criteria.', 422);
}

/* ========================================================
   CREATE BROADCAST LOG
   ======================================================== */
$channels = [];
if ($channelInApp) $channels[] = 'in_app';
if ($channelEmail) $channels[] = 'email';
if ($channelSms)   $channels[] = 'sms';
$channelsStr = implode(',', $channels);

$broadcastId = 0;
try {
    $stmt = $pdo->prepare("
        INSERT INTO broadcast_logs
            (admin_id, title, message, channels, target_type, target_count,
             link, icon, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $adminId, $title, $message, $channelsStr, $targetType,
        $targetCount,
        $link ?: null, $icon,
        $sendNow ? 'processing' : 'queued',
    ]);
    $broadcastId = (int) $pdo->lastInsertId();
} catch (Exception $e) {
    if (WCB_ENV === 'development') json_error('Log create failed: ' . $e->getMessage(), 500);
    json_error('Could not create broadcast log.', 500);
}

/* ---------- If draft (send_now=0), stop here ---------- */
if (!$sendNow) {
    try {
        log_activity('broadcast_created', 'broadcast_logs', $broadcastId,
            "Draft — target: {$targetType}, count: {$targetCount}");
    } catch (Exception $e) { /* silent */ }

    json_success([
        'broadcast_id' => $broadcastId,
        'target_count' => $targetCount,
        'sent_count'   => 0,
        'draft'        => true,
    ], 'Broadcast saved as draft.', 201);
}

/* ========================================================
   BUILD NOTIFICATION LINK
   ======================================================== */
$finalLink = $link;
if ($finalLink !== '' && !preg_match('#^https?://#i', $finalLink)) {
    // Assume relative path
    $finalLink = base_url(ltrim($finalLink, '/'));
}

/* ========================================================
   SEND — in-app + email + sms
   ======================================================== */
$inAppSent   = 0;
$emailSent   = 0;
$emailFailed = 0;
$smsSent     = 0;
$smsFailed   = 0;

/* ---------- Prepare email HTML (same for all) ---------- */
$emailHtml = '';
if ($channelEmail) {
    $emailHtml = broadcast_email_html($title, $message, $finalLink ?: null, $ctaText ?: null);
}

/* ---------- Batch loop ---------- */
try {
    $totalBatches = (int) ceil($targetCount / $batchSize);
    $batchIndex   = 0;

    foreach ($recipients as $idx => $u) {
        $uid    = (int) $u['id'];
        $uname  = $u['name'] ?? 'User';
        $uemail = $u['email'] ?? '';
        $uphone = $u['phone'] ?? '';

        /* ----- In-app notification ----- */
        if ($channelInApp) {
            try {
                push_notification(
                    'user',
                    $uid,
                    $title,
                    $message,
                    $finalLink ?: null,
                    $icon
                );
                $inAppSent++;
            } catch (Exception $e) { /* silent */ }
        }

        /* ----- Email ----- */
        if ($channelEmail && $uemail) {
            try {
                $personalHtml = str_replace(
                    '{{name}}',
                    htmlspecialchars($uname),
                    $emailHtml
                );
                $ok = send_email($uemail, $title, $personalHtml, $uname);
                if ($ok) $emailSent++;
                else     $emailFailed++;
            } catch (Exception $e) {
                $emailFailed++;
            }
        }

        /* ----- SMS ----- */
        if ($channelSms && $uphone && function_exists('send_sms')) {
            try {
                $smsBody = $title . "\n" . str_limit($message, 140);
                if ($finalLink) $smsBody .= "\n" . $finalLink;
                $ok = send_sms($uphone, $smsBody);
                if ($ok) $smsSent++;
                else     $smsFailed++;
            } catch (Exception $e) {
                $smsFailed++;
            }
        }

        /* ----- Batch log ----- */
        if (($idx + 1) % $batchSize === 0) {
            $batchIndex++;
            // Small pause to avoid SMTP rate limits
            usleep(300000); // 0.3 sec
        }
    }
} catch (Exception $e) {
    if (WCB_ENV === 'development') {
        // We already created log — mark as failed
        try {
            $pdo->prepare("UPDATE broadcast_logs SET status = 'failed' WHERE id = ?")
                ->execute([$broadcastId]);
        } catch (Exception $e2) { /* silent */ }
        json_error('Send failed: ' . $e->getMessage(), 500);
    }
    json_error('Broadcast sending encountered an error.', 500);
}

/* ========================================================
   UPDATE BROADCAST LOG
   ======================================================== */
$totalSent = $inAppSent + $emailSent + $smsSent;
$finalStatus = 'sent';
if ($totalSent === 0 && ($targetCount > 0)) $finalStatus = 'failed';

try {
    $pdo->prepare("
        UPDATE broadcast_logs
        SET delivered_count = ?, failed_count = ?, status = ?, sent_at = NOW()
        WHERE id = ?
    ")->execute([
        $totalSent,
        ($emailFailed + $smsFailed),
        $finalStatus,
        $broadcastId,
    ]);
} catch (Exception $e) { /* silent */ }

/* ---------- Activity log ---------- */
try {
    log_activity(
        'broadcast_sent',
        'broadcast_logs',
        $broadcastId,
        "Target: {$targetType} ({$targetCount}) — In-app: {$inAppSent}, Email: {$emailSent}/{$emailFailed}, SMS: {$smsSent}/{$smsFailed}"
    );
} catch (Exception $e) { /* silent */ }

/* ========================================================
   RESPONSE
   ======================================================== */
$parts = [];
if ($channelInApp) $parts[] = "{$inAppSent} in-app";
if ($channelEmail) $parts[] = "{$emailSent} email" . ($emailFailed > 0 ? " ({$emailFailed} failed)" : '');
if ($channelSms)   $parts[] = "{$smsSent} SMS" . ($smsFailed > 0 ? " ({$smsFailed} failed)" : '');

json_success([
    'broadcast_id'  => $broadcastId,
    'target_count'  => $targetCount,
    'sent_count'    => $totalSent,
    'in_app_sent'   => $inAppSent,
    'email_sent'    => $emailSent,
    'email_failed'  => $emailFailed,
    'sms_sent'      => $smsSent,
    'sms_failed'    => $smsFailed,
    'status'        => $finalStatus,
    'channels'      => $channelsStr,
], 'Broadcast sent: ' . implode(', ', $parts) . '.', 201);