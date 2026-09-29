<?php
/**
 * api/admin/chat-close.php — Admin: Close or Re-open Chat
 * Version: 2.0
 * Method: POST
 * Body: {
 *   conversation_id,
 *   action?  'close' (default) | 'reopen'
 * }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$conversationId = (int) input('conversation_id', 0);
$action         = strtolower(trim((string) input('action', 'close')));

if ($conversationId <= 0) {
    json_error('Conversation ID is required.', 422, ['conversation_id' => 'Missing.']);
}
if (!in_array($action, ['close', 'reopen'], true)) {
    json_error('Invalid action.', 422, ['action' => 'Must be close or reopen.']);
}

/* ---------- Fetch conversation ---------- */
$cStmt = $pdo->prepare("
    SELECT c.id, c.user_id, c.status, c.subject,
           u.name AS user_name, u.email AS user_email
    FROM chat_conversations c
    LEFT JOIN users u ON u.id = c.user_id
    WHERE c.id = ?
    LIMIT 1
");
$cStmt->execute([$conversationId]);
$conv = $cStmt->fetch();

if (!$conv) json_error('Conversation not found.', 404);

/* ---------- Already in desired state? ---------- */
if ($action === 'close' && $conv['status'] === 'closed') {
    json_success([
        'conversation_id' => $conversationId,
        'status'          => 'closed',
    ], 'Conversation already closed.');
}
if ($action === 'reopen' && $conv['status'] === 'open') {
    json_success([
        'conversation_id' => $conversationId,
        'status'          => 'open',
    ], 'Conversation already open.');
}

/* ---------- Update status ---------- */
try {
    $newStatus = $action === 'close' ? 'closed' : 'open';

    $pdo->prepare("
        UPDATE chat_conversations
        SET status = ?,
            admin_id = COALESCE(admin_id, ?),
            last_message_at = NOW()
        WHERE id = ?
    ")->execute([$newStatus, $adminId, $conversationId]);

    // System message
    $sysMsg = $action === 'close'
        ? 'This conversation has been closed by support. You can start a new chat anytime.'
        : 'This conversation has been re-opened by support. Feel free to continue.';

    $pdo->prepare("
        INSERT INTO chat_messages
            (conversation_id, sender_type, sender_id, message, is_read, is_delivered, created_at)
        VALUES (?, 'system', NULL, ?, 1, 1, NOW())
    ")->execute([$conversationId, $sysMsg]);

} catch (Exception $e) {
    if (WCB_ENV === 'development') json_error('Update failed: ' . $e->getMessage(), 500);
    json_error('Could not update conversation.', 500);
}

/* ---------- Notify user (only for close) ---------- */
if ($action === 'close' && !empty($conv['user_id'])) {
    try {
        push_notification(
            'user',
            (int) $conv['user_id'],
            '💬 Chat closed',
            'Support has closed your chat. You can start a new conversation anytime.',
            'user/index.php',
            'chat'
        );
    } catch (Exception $e) { /* silent */ }
}

/* ---------- Log ---------- */
try {
    log_activity(
        $action === 'close' ? 'chat_admin_closed' : 'chat_admin_reopened',
        'chat_conversations',
        $conversationId,
        'By admin #' . $adminId
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'conversation_id' => $conversationId,
    'status'          => $action === 'close' ? 'closed' : 'open',
    'action'          => $action,
], $action === 'close' ? 'Conversation closed.' : 'Conversation re-opened.');