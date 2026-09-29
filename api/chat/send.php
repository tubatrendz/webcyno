<?php
/**
 * api/chat/send.php — Send Chat Message (text + attachment)
 * Version: 2.0
 * Method: POST (multipart OR JSON)
 * Body:
 *   conversation_id (required)
 *   message?        text
 *   attachment?     file (image OR document)
 * Response: { message: {...}, conversation_id }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$userId = require_user();
global $pdo;

/* ---------- Settings ---------- */
$chatEnabled = (int) setting('chat_enabled', 1) === 1;
if (!$chatEnabled) json_error('Live chat is currently unavailable.', 403);

/* ---------- Input ---------- */
$conversationId = (int) input('conversation_id', 0);
$messageRaw     = (string) input('message', '');
$message        = clean($messageRaw, 2000);

if ($conversationId <= 0) {
    json_error('Conversation ID is required.', 422, ['conversation_id' => 'Missing.']);
}

/* ---------- Verify conversation belongs to user ---------- */
$cStmt = $pdo->prepare("
    SELECT id, user_id, admin_id, status, last_message_at
    FROM chat_conversations
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$cStmt->execute([$conversationId, $userId]);
$conv = $cStmt->fetch();

if (!$conv) json_error('Conversation not found.', 404);
if ($conv['status'] !== 'open') {
    json_error('This conversation is closed.', 410);
}

/* ---------- Rate limit (30 msgs / min per user) ---------- */
$rlKey = 'chat_send_' . $userId;
$now = time();
if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 1, 'reset' => $now + 60];
} else {
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > 30) {
        json_error('Too many messages. Please slow down.', 429);
    }
}

/* ---------- Attachment upload ---------- */
$attachment     = null;
$attachmentType = 'none';
$attachmentName = null;
$attachmentSize = null;

if (!empty($_FILES['attachment']['tmp_name'])) {
    $upload = upload_chat_attachment($_FILES['attachment']);
    if ($upload) {
        $attachment     = $upload['path'];
        $attachmentType = $upload['type'];
        $attachmentName = $upload['name'];
        $attachmentSize = $upload['size'];
    }
}

/* ---------- Must have at least message or attachment ---------- */
if ($message === '' && $attachment === null) {
    json_error('Message cannot be empty.', 422, ['message' => 'Type something or attach a file.']);
}

/* ---------- Insert message ---------- */
try {
    $insStmt = $pdo->prepare("
        INSERT INTO chat_messages
            (conversation_id, sender_type, sender_id, message,
             attachment, attachment_type, attachment_name, attachment_size,
             is_read, is_delivered, created_at)
        VALUES (?, 'user', ?, ?, ?, ?, ?, ?, 0, 0, NOW())
    ");
    $insStmt->execute([
        $conversationId,
        $userId,
        $message,
        $attachment,
        $attachmentType,
        $attachmentName,
        $attachmentSize,
    ]);
    $messageId = (int) $pdo->lastInsertId();
} catch (Exception $e) {
    // Clean up uploaded file on failure
    if ($attachment) delete_upload($attachment);
    if (WCB_ENV === 'development') json_error('Send failed: ' . $e->getMessage(), 500);
    json_error('Could not send message. Please try again.', 500);
}

/* ---------- Update conversation ---------- */
try {
    $pdo->prepare("
        UPDATE chat_conversations
        SET last_message_at = NOW(),
            unread_admin = unread_admin + 1
        WHERE id = ?
    ")->execute([$conversationId]);
} catch (Exception $e) { /* silent */ }

/* ---------- Notify admin ---------- */
try {
    // Get user info for notification
    $uStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();

    $preview = $message !== '' ? str_limit($message, 60) : '[Attachment]';
    push_notification(
        'admin', null,
        '💬 New message from ' . ($user['name'] ?? 'User'),
        $preview,
        'admin/chat.php?conversation_id=' . $conversationId,
        'chat'
    );

    // Optional: email admin
    $notifyEmail = (int) setting('chat_notify_email', 1) === 1;
    if ($notifyEmail) {
        $adminEmail = setting('site_email', '');
        if ($adminEmail) {
            try {
                $siteName = setting('site_name', 'Webcyno');
                $link = base_url('admin/chat.php?conversation_id=' . $conversationId);
                $subject = "💬 New chat message — {$siteName}";

                $body = '
                <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#0F172A;">
                  <h2 style="color:#2563EB;margin:0 0 12px;">' . e($siteName) . '</h2>
                  <div style="background:#EFF6FF;border:1px solid #BFDBFE;padding:12px 16px;border-radius:8px;margin:16px 0;">
                    <strong>💬 New chat message</strong>
                  </div>
                  <p><strong>' . e($user['name'] ?? 'User') . '</strong> sent you a message:</p>
                  <div style="background:#F8FAFC;padding:14px;border-radius:8px;border-left:3px solid #2563EB;font-style:italic;">
                    ' . nl2br(e($preview)) . '
                  </div>
                  <p style="margin-top:20px;"><a href="' . $link . '" style="display:inline-block;background:#2563EB;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;">Reply Now</a></p>
                  <hr style="border:none;border-top:1px solid #E2E8F0;margin:20px 0;">
                  <p style="font-size:12px;color:#94A3B8;">' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '</p>
                </div>';

                send_email($adminEmail, $subject, $body, 'Admin');
            } catch (Exception $e) { /* silent */ }
        }
    }
} catch (Exception $e) { /* silent */ }

/* ---------- Fetch freshly inserted message ---------- */
$mStmt = $pdo->prepare("
    SELECT id, sender_type, sender_id, message, attachment, attachment_type,
           attachment_name, attachment_size, is_read, is_delivered, created_at
    FROM chat_messages WHERE id = ? LIMIT 1
");
$mStmt->execute([$messageId]);
$row = $mStmt->fetch();

$messageOut = [
    'id'              => (int) $row['id'],
    'sender_type'     => $row['sender_type'],
    'sender_id'       => (int) $row['sender_id'],
    'message'         => $row['message'],
    'attachment_type' => $row['attachment_type'],
    'attachment_url'  => !empty($row['attachment']) ? base_url($row['attachment']) : null,
    'attachment_name' => $row['attachment_name'],
    'attachment_size' => $row['attachment_size'] ? (int) $row['attachment_size'] : null,
    'is_read'         => (int) $row['is_read'],
    'is_delivered'    => (int) $row['is_delivered'],
    'created_at'      => $row['created_at'],
    'time_ago'        => 'just now',
];

/* ---------- Response ---------- */
json_success([
    'conversation_id' => $conversationId,
    'message'         => $messageOut,
], 'Message sent.', 201);