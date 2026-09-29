<?php
/**
 * api/chat/messages.php — Chat Messages Load / Actions
 * Version: 2.0
 * Method: GET  → load messages
 * Method: POST → actions (mark_read, mark_delivered, delete?)
 *
 * GET:  ?conversation_id=42&since_id=100
 * POST: { conversation_id, action }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$userId = require_user();
global $pdo;

/* ========================================================
   GET — Load messages
   ======================================================== */
if ($method === 'GET') {

    $conversationId = (int) input('conversation_id', 0);
    $sinceId        = (int) input('since_id', 0);

    if ($conversationId <= 0) {
        json_error('Conversation ID is required.', 422, ['conversation_id' => 'Missing.']);
    }

    /* ---------- Verify ownership ---------- */
    $cStmt = $pdo->prepare("
        SELECT id, user_id, admin_id, status, last_message_at, unread_user, unread_admin
        FROM chat_conversations
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $cStmt->execute([$conversationId, $userId]);
    $conv = $cStmt->fetch();

    if (!$conv) json_error('Conversation not found.', 404);

    /* ---------- Fetch messages ---------- */
    $params = [$conversationId];
    $where  = "conversation_id = ?";

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

    /* ---------- Auto-mark user-side delivered (best effort) ---------- */
    try {
        $pdo->prepare("
            UPDATE chat_messages
            SET is_delivered = 1
            WHERE conversation_id = ? AND sender_type = 'admin' AND is_delivered = 0
        ")->execute([$conversationId]);
    } catch (Exception $e) { /* silent */ }

    /* ---------- Recalc unread ---------- */
    try {
        if (function_exists('recalc_chat_unread')) recalc_chat_unread($conversationId);
    } catch (Exception $e) { /* silent */ }

    /* ---------- Refresh unread counts ---------- */
    $uStmt = $pdo->prepare("
        SELECT unread_user, unread_admin FROM chat_conversations WHERE id = ? LIMIT 1
    ");
    $uStmt->execute([$conversationId]);
    $fresh = $uStmt->fetch() ?: ['unread_user' => 0, 'unread_admin' => 0];

    json_success([
        'conversation_id' => $conversationId,
        'status'          => $conv['status'],
        'messages'        => $messages,
        'count'           => count($messages),
        'unread_user'     => (int) $fresh['unread_user'],
        'unread_admin'    => (int) $fresh['unread_admin'],
        'last_message_at' => $conv['last_message_at'],
    ], 'Messages loaded.');
}

/* ========================================================
   POST — Actions
   ======================================================== */
if ($method === 'POST') {

    check_csrf();

    $conversationId = (int) input('conversation_id', 0);
    $action         = strtolower(trim((string) input('action', '')));

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

    $allowed = ['mark_read', 'mark_delivered'];
    if (!in_array($action, $allowed, true)) {
        json_error('Invalid action.', 422, ['action' => 'Unknown action.']);
    }

    try {
        if ($action === 'mark_read') {
            // User has read all admin messages
            $pdo->prepare("
                UPDATE chat_messages
                SET is_read = 1, read_at = NOW()
                WHERE conversation_id = ? AND sender_type = 'admin' AND is_read = 0
            ")->execute([$conversationId]);

            $pdo->prepare("
                UPDATE chat_conversations
                SET unread_user = 0, user_last_seen_at = NOW()
                WHERE id = ?
            ")->execute([$conversationId]);

            $msg = 'Marked as read.';
        }

        if ($action === 'mark_delivered') {
            $pdo->prepare("
                UPDATE chat_messages
                SET is_delivered = 1
                WHERE conversation_id = ? AND sender_type = 'admin' AND is_delivered = 0
            ")->execute([$conversationId]);
            $msg = 'Marked as delivered.';
        }
    } catch (Exception $e) {
        json_error('Could not complete action.', 500);
    }

    json_success([
        'action'          => $action,
        'conversation_id' => $conversationId,
    ], $msg);
}

/* ---------- Fallback ---------- */
json_error('Method not allowed.', 405);