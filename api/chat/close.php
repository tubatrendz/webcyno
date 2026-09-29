<?php
/**
 * api/chat/close.php — Close Chat Conversation (by user)
 * Version: 1.0
 * Method: POST
 * Body: { conversation_id }
 * Response: { closed: true, conversation_id }
 *
 * User নিজে chat বন্ধ করতে পারবে। Admin চাইলে re-open করতে পারবে।
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$userId = require_user();
global $pdo;

/* ---------- Input ---------- */
$conversationId = (int) input('conversation_id', 0);

if ($conversationId <= 0) {
    json_error('Conversation ID is required.', 422, ['conversation_id' => 'Missing.']);
}

/* ---------- Verify ownership ---------- */
$cStmt = $pdo->prepare("
    SELECT id, user_id, status, subject
    FROM chat_conversations
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$cStmt->execute([$conversationId, $userId]);
$conv = $cStmt->fetch();

if (!$conv) json_error('Conversation not found.', 404);

if ($conv['status'] === 'closed') {
    json_success([
        'conversation_id' => $conversationId,
        'status'          => 'closed',
    ], 'Conversation already closed.');
}

/* ---------- Close ---------- */
try {
    $pdo->prepare("
        UPDATE chat_conversations
        SET status = 'closed'
        WHERE id = ?
    ")->execute([$conversationId]);

    // System message to note closure
    $pdo->prepare("
        INSERT INTO chat_messages
            (conversation_id, sender_type, sender_id, message, is_read, is_delivered, created_at)
        VALUES (?, 'system', NULL, ?, 1, 1, NOW())
    ")->execute([$conversationId, 'This conversation has been closed by the user.']);

} catch (Exception $e) {
    if (WCB_ENV === 'development') json_error('Close failed: ' . $e->getMessage(), 500);
    json_error('Could not close conversation.', 500);
}

/* ---------- Notify admin (informational) ---------- */
try {
    $uStmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();

    push_notification(
        'admin', null,
        '💬 Chat closed by user',
        ($user['name'] ?? 'User') . ' closed their chat conversation.',
        'admin/chat.php?conversation_id=' . $conversationId,
        'chat'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Log ---------- */
try {
    log_activity('chat_closed_by_user', 'chat_conversations', $conversationId, 'By user #' . $userId);
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'conversation_id' => $conversationId,
    'status'          => 'closed',
    'closed'          => true,
], 'Conversation closed.');