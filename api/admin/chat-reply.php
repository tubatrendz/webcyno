<?php
/**
 * api/admin/chat-reply.php — Admin Reply to User Chat
 * Version: 2.0
 * Method: POST (JSON or multipart)
 * Body:
 *   conversation_id (required)
 *   message?        text
 *   attachment?     file (image OR document)
 * Response: { message: {...}, conversation_id }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$conversationId = (int) input('conversation_id', 0);
$messageRaw     = (string) input('message', '');
$message        = clean($messageRaw, 2000);

if ($conversationId <= 0) {
    json_error('Conversation ID is required.', 422, ['conversation_id' => 'Missing.']);
}

/* ---------- Fetch conversation + user ---------- */
$cStmt = $pdo->prepare("
    SELECT c.id, c.user_id, c.admin_id, c.status, c.subject, c.last_message_at,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone
    FROM chat_conversations c
    INNER JOIN users u ON u.id = c.user_id
    WHERE c.id = ?
    LIMIT 1
");
$cStmt->execute([$conversationId]);
$conv = $cStmt->fetch();

if (!$conv) json_error('Conversation not found.', 404);
if ($conv['status'] !== 'open') {
    json_error('This conversation is closed. Re-open it first.', 410);
}

/* ---------- Rate limit (60 replies / min per admin) ---------- */
$rlKey = 'adm_chat_' . $adminId;
$now = time();
if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 1, 'reset' => $now + 60];
} else {
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > 60) {
        json_error('Too many replies. Slow down a bit.', 429);
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

/* ---------- Validation ---------- */
if ($message === '' && $attachment === null) {
    json_error('Reply cannot be empty.', 422, ['message' => 'Type something or attach a file.']);
}

/* ---------- Insert message ---------- */
try {
    $insStmt = $pdo->prepare("
        INSERT INTO chat_messages
            (conversation_id, sender_type, sender_id, message,
             attachment, attachment_type, attachment_name, attachment_size,
             is_read, is_delivered, created_at)
        VALUES (?, 'admin', ?, ?, ?, ?, ?, ?, 0, 0, NOW())
    ");
    $insStmt->execute([
        $conversationId,
        $adminId,
        $message,
        $attachment,
        $attachmentType,
        $attachmentName,
        $attachmentSize,
    ]);
    $messageId = (int) $pdo->lastInsertId();
} catch (Exception $e) {
    if ($attachment) delete_upload($attachment);
    if (WCB_ENV === 'development') json_error('Reply failed: ' . $e->getMessage(), 500);
    json_error('Could not send reply. Please try again.', 500);
}

/* ---------- Update conversation ---------- */
try {
    $pdo->prepare("
        UPDATE chat_conversations
        SET last_message_at = NOW(),
            admin_id = COALESCE(admin_id, ?),
            unread_user = unread_user + 1
        WHERE id = ?
    ")->execute([$adminId, $conversationId]);
} catch (Exception $e) { /* silent */ }

/* ---------- Notify user (in-app) ---------- */
try {
    $preview = $message !== '' ? str_limit($message, 60) : ('📎 ' . ($attachmentName ?: 'Attachment'));
    push_notification(
        'user',
        (int) $conv['user_id'],
        '💬 New reply from support',
        $preview,
        'user/index.php?open_chat=' . $conversationId,
        'chat'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Email user (optional) ---------- */
$emailEnabled = (int) setting('chat_notify_email', 1) === 1;
if ($emailEnabled && !empty($conv['user_email'])) {
    try {
        $siteName = setting('site_name', 'Webcyno');
        $firstName = explode(' ', trim($conv['user_name'] ?: 'there'))[0];
        $link = base_url('user/index.php?open_chat=' . $conversationId);

        $subject = "💬 New message from {$siteName} support";

        $body = '
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#0F172A;background:#F8FAFC;">
          <div style="background:#fff;border-radius:12px;padding:24px;border:1px solid #E2E8F0;">
            <h2 style="color:#2563EB;margin:0 0 8px;">' . e($siteName) . '</h2>
            <div style="background:#EFF6FF;border:1px solid #BFDBFE;padding:12px 16px;border-radius:10px;margin:16px 0;">
              <strong>💬 You have a new reply</strong>
            </div>
            <p>Hi ' . e($firstName) . ',</p>
            <p>Our support team replied to your chat:</p>
            <div style="background:#F8FAFC;padding:14px;border-radius:8px;border-left:3px solid #2563EB;font-style:italic;color:#334155;">
              ' . nl2br(e($preview)) . '
            </div>
            <p style="margin-top:20px;"><a href="' . $link . '" style="display:inline-block;background:#2563EB;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">Open Chat</a></p>
            <hr style="border:none;border-top:1px solid #E2E8F0;margin:24px 0;">
            <p style="font-size:12px;color:#94A3B8;margin:0;">' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '</p>
          </div>
        </div>';

        send_email($conv['user_email'], $subject, $body, $conv['user_name']);
    } catch (Exception $e) { /* silent */ }
}

/* ---------- Log ---------- */
try {
    log_activity(
        'chat_admin_reply',
        'chat_conversations',
        $conversationId,
        'By admin #' . $adminId . ' — msg #' . $messageId
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Fetch fresh message ---------- */
$mStmt = $pdo->prepare("
    SELECT id, sender_type, sender_id, message, attachment, attachment_type,
           attachment_name, attachment_size, is_read, is_delivered, created_at
    FROM chat_messages WHERE id = ? LIMIT 1
");
$mStmt->execute([$messageId]);
$row = $mStmt->fetch();

/* ---------- Response ---------- */
json_success([
    'conversation_id' => $conversationId,
    'message' => [
        'id'              => (int) $row['id'],
        'sender_type'     => $row['sender_type'],
        'sender_id'       => (int) $row['sender_id'],
        'message'         => $row['message'],
        'attachment_type' => $row['attachment_type'],
        'attachment_url'  => !empty($row['attachment']) ? base_url($row['attachment']) : null,
        'attachment_name' => $row['attachment_name'],
        'attachment_size' => $row['attachment_size'] ? (int)$row['attachment_size'] : null,
        'created_at'      => $row['created_at'],
    ],
], 'Reply sent.', 201);