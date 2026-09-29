<?php
/**
 * api/chat/upload.php — Upload Chat Attachment (image/file)
 * Version: 1.0
 * Method: POST (multipart)
 * Body: {
 *   conversation_id (required),
 *   attachment (file)
 * }
 * Response: { attachment: { type, url, name, size }, message_id }
 *
 * Note: this endpoint uploads the file AND inserts a message with
 *       attachment info + empty text. Client can then render it.
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
$captionText    = clean((string) input('message', ''), 2000);

if ($conversationId <= 0) {
    json_error('Conversation ID is required.', 422, ['conversation_id' => 'Missing.']);
}

/* ---------- Verify ownership ---------- */
$cStmt = $pdo->prepare("
    SELECT id, user_id, status FROM chat_conversations
    WHERE id = ? AND user_id = ? LIMIT 1
");
$cStmt->execute([$conversationId, $userId]);
$conv = $cStmt->fetch();

if (!$conv) json_error('Conversation not found.', 404);
if ($conv['status'] !== 'open') json_error('This conversation is closed.', 410);

/* ---------- Rate limit (20 uploads / 5 min) ---------- */
$rlKey = 'chat_upload_' . $userId;
$now = time();
if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 1, 'reset' => $now + 300];
} else {
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > 20) {
        json_error('Too many uploads. Please wait a bit.', 429);
    }
}

/* ---------- Must have file ---------- */
if (empty($_FILES['attachment']['tmp_name'])) {
    json_error('No file uploaded.', 422, ['attachment' => 'File is required.']);
}

/* ---------- Upload attachment ---------- */
$upload = upload_chat_attachment($_FILES['attachment']);
if (!$upload) {
    json_error('Upload failed. Please try again.', 500);
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
        $captionText,
        $upload['path'],
        $upload['type'],
        $upload['name'],
        $upload['size'],
    ]);
    $messageId = (int) $pdo->lastInsertId();
} catch (Exception $e) {
    delete_upload($upload['path']);
    if (WCB_ENV === 'development') json_error('Insert failed: ' . $e->getMessage(), 500);
    json_error('Could not save attachment.', 500);
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
    $uStmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();

    $preview = $captionText !== '' ? str_limit($captionText, 60) : ('📎 ' . $upload['name']);
    push_notification(
        'admin', null,
        '💬 New ' . ($upload['type'] === 'image' ? 'image' : 'file') . ' from ' . ($user['name'] ?? 'User'),
        $preview,
        'admin/chat.php?conversation_id=' . $conversationId,
        'chat'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'message_id'      => $messageId,
    'conversation_id' => $conversationId,
    'attachment' => [
        'type'       => $upload['type'],
        'url'        => base_url($upload['path']),
        'name'       => $upload['name'],
        'size'       => (int) $upload['size'],
        'size_fmt'   => human_file_size((int) $upload['size']),
    ],
    'message' => $captionText,
], 'Uploaded.', 201);

/* ========================================================
   Helper — human readable file size
   ======================================================== */
function human_file_size(int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B','KB','MB','GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}