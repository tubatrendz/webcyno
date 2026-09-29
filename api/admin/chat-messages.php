<?php
/**
 * api/admin/chat-messages.php — Admin: Load Chat Messages (polling)
 * Version: 2.0
 * Method: GET
 * Query: ?conversation_id=42&since_id=100
 * Response: { conversation, messages, unread_admin, unread_user }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$conversationId = (int) input('conversation_id', 0);
$sinceId        = (int) input('since_id', 0);

if ($conversationId <= 0) {
    json_error('Conversation ID is required.', 422, ['conversation_id' => 'Missing.']);
}

/* ---------- Fetch conversation ---------- */
$cStmt = $pdo->prepare("
    SELECT c.id, c.user_id, c.admin_id, c.status, c.subject, c.last_message_at,
           c.unread_admin, c.unread_user,
           u.name AS user_name, u.email AS user_email, u.avatar AS user_avatar
    FROM chat_conversations c
    LEFT JOIN users u ON u.id = c.user_id
    WHERE c.id = ?
    LIMIT 1
");
$cStmt->execute([$conversationId]);
$conv = $cStmt->fetch();

if (!$conv) json_error('Conversation not found.', 404);

/* ---------- Messages ---------- */
$where  = "conversation_id = ?";
$params = [$conversationId];

if ($sinceId > 0) {
    $where .= " AND id > ?";
    $params[] = $sinceId;
}

$mStmt = $pdo->prepare("
    SELECT id, sender_type, sender_id, message, attachment, attachment_type,
           attachment_name, attachment_size, is_read, is_delivered, created_at
    FROM chat_messages
    WHERE $where
    ORDER BY id ASC
    LIMIT 200
");
$mStmt->execute($params);
$rows = $mStmt->fetchAll();

$messages = [];
foreach ($rows as $m) {
    $messages[] = [
        'id'              => (int) $m['id'],
        'sender_type'     => $m['sender_type'],
        'sender_id'       => $m['sender_id'] ? (int)$m['sender_id'] : null,
        'message'         => $m['message'],
        'attachment_type' => $m['attachment_type'] ?? 'none',
        'attachment_url'  => !empty($m['attachment']) ? base_url($m['attachment']) : null,
        'attachment_name' => $m['attachment_name'],
        'attachment_size' => $m['attachment_size'] ? (int)$m['attachment_size'] : null,
        'is_read'         => (int) $m['is_read'],
        'is_delivered'    => (int) $m['is_delivered'],
        'created_at'      => $m['created_at'],
        'time_ago'        => time_ago($m['created_at']),
    ];
}

/* ---------- Mark user messages delivered to admin ---------- */
try {
    $pdo->prepare("
        UPDATE chat_messages
        SET is_delivered = 1
        WHERE conversation_id = ? AND sender_type = 'user' AND is_delivered = 0
    ")->execute([$conversationId]);
} catch (Exception $e) { /* silent */ }

/* ---------- Recalc unread ---------- */
try {
    if (function_exists('recalc_chat_unread')) recalc_chat_unread($conversationId);
} catch (Exception $e) { /* silent */ }

/* ---------- Refresh counts ---------- */
$fStmt = $pdo->prepare("SELECT unread_admin, unread_user FROM chat_conversations WHERE id = ? LIMIT 1");
$fStmt->execute([$conversationId]);
$fresh = $fStmt->fetch() ?: ['unread_admin' => 0, 'unread_user' => 0];

/* ---------- Response ---------- */
json_success([
    'conversation' => [
        'id'              => (int) $conv['id'],
        'user_id'         => (int) $conv['user_id'],
        'user_name'       => $conv['user_name'],
        'user_email'      => $conv['user_email'],
        'user_avatar'     => $conv['user_avatar'] ? base_url($conv['user_avatar']) : null,
        'subject'         => $conv['subject'],
        'status'          => $conv['status'],
        'last_message_at' => $conv['last_message_at'],
    ],
    'messages'     => $messages,
    'count'        => count($messages),
    'unread_admin' => (int) $fresh['unread_admin'],
    'unread_user'  => (int) $fresh['unread_user'],
], 'Messages loaded.');