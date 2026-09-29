<?php
/**
 * api/chat/start.php — Start or Resume Chat Conversation
 * Version: 2.0
 * Method: POST (login required)
 * Body: { subject? }
 * Response: { conversation: {...}, messages: [...] }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$userId = require_user();
global $pdo;

/* ---------- Settings ---------- */
$chatEnabled = (int) setting('chat_enabled', 1) === 1;
if (!$chatEnabled) {
    json_error('Live chat is currently unavailable.', 403);
}

/* ---------- Fetch user ---------- */
$uStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();
if (!$user) json_error('User not found.', 404);

/* ---------- Input ---------- */
$subject = clean(input('subject', ''), 200);

/* ========================================================
   1. Find existing open conversation
   ======================================================== */
$cStmt = $pdo->prepare("
    SELECT id, subject, status, admin_id, last_message_at, created_at
    FROM chat_conversations
    WHERE user_id = ? AND status = 'open'
    ORDER BY id DESC
    LIMIT 1
");
$cStmt->execute([$userId]);
$conversation = $cStmt->fetch();

$isNew = false;

/* ========================================================
   2. Create new if none exists
   ======================================================== */
if (!$conversation) {
    $isNew = true;
    try {
        $insStmt = $pdo->prepare("
            INSERT INTO chat_conversations
                (user_id, subject, status, last_message_at, created_at)
            VALUES (?, ?, 'open', NOW(), NOW())
        ");
        $insStmt->execute([$userId, $subject ?: null]);
        $convId = (int) $pdo->lastInsertId();

        // Welcome message from system
        $welcomeMsg = setting('chat_welcome_msg', 'Hello! How can we help you today?');
        $wMsgStmt = $pdo->prepare("
            INSERT INTO chat_messages
                (conversation_id, sender_type, sender_id, message, is_read, is_delivered, created_at)
            VALUES (?, 'system', NULL, ?, 1, 1, NOW())
        ");
        $wMsgStmt->execute([$convId, $welcomeMsg]);

        // Notify admin
        try {
            push_notification(
                'admin', null,
                '💬 New chat started',
                $user['name'] . ' started a chat.',
                'admin/chat.php?conversation_id=' . $convId,
                'chat'
            );
        } catch (Exception $e) { /* silent */ }

        // Fetch newly created
        $cStmt2 = $pdo->prepare("SELECT id, subject, status, admin_id, last_message_at, created_at FROM chat_conversations WHERE id = ? LIMIT 1");
        $cStmt2->execute([$convId]);
        $conversation = $cStmt2->fetch();

    } catch (Exception $e) {
        if (WCB_ENV === 'development') json_error('Could not start conversation: ' . $e->getMessage(), 500);
        json_error('Could not start chat. Please try again.', 500);
    }
}

$conversationId = (int) $conversation['id'];

/* ========================================================
   3. Recalculate unread for this user
   ======================================================== */
try {
    if (function_exists('recalc_chat_unread')) {
        recalc_chat_unread($conversationId);
    }
} catch (Exception $e) { /* silent */ }

/* ========================================================
   4. Fetch recent messages (last 50)
   ======================================================== */
$mStmt = $pdo->prepare("
    SELECT id, sender_type, sender_id, message, attachment, attachment_type,
           attachment_name, attachment_size, is_read, is_delivered, created_at
    FROM chat_messages
    WHERE conversation_id = ?
    ORDER BY id ASC
    LIMIT 50
");
$mStmt->execute([$conversationId]);
$messages = [];
foreach ($mStmt->fetchAll() as $m) {
    $messages[] = [
        'id'              => (int) $m['id'],
        'sender_type'     => $m['sender_type'],
        'sender_id'       => $m['sender_id'] ? (int) $m['sender_id'] : null,
        'message'         => $m['message'],
        'attachment_type' => $m['attachment_type'] ?? 'none',
        'attachment_url'  => !empty($m['attachment']) ? base_url($m['attachment']) : null,
        'attachment_name' => $m['attachment_name'] ?? null,
        'attachment_size' => $m['attachment_size'] ? (int) $m['attachment_size'] : null,
        'is_read'         => (int) $m['is_read'],
        'is_delivered'    => (int) $m['is_delivered'],
        'created_at'      => $m['created_at'],
        'time_ago'        => time_ago($m['created_at']),
    ];
}

/* ========================================================
   5. Admin online? (last seen within 5 min)
   ======================================================== */
$adminOnline = false;
try {
    $aStmt = $pdo->prepare("
        SELECT COUNT(*) FROM admins
        WHERE status = 1 AND last_login IS NOT NULL
          AND last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $aStmt->execute();
    $adminOnline = (int) $aStmt->fetchColumn() > 0;
} catch (Exception $e) { /* silent */ }

/* ========================================================
   6. Auto-reply if new conversation and no admin online
   ======================================================== */
if ($isNew && !$adminOnline) {
    $autoReplyEnabled = (int) setting('chat_auto_reply_enabled', 1) === 1;
    $autoReplyText = trim((string) setting('chat_auto_reply', ''));

    if ($autoReplyEnabled && $autoReplyText !== '') {
        // Check if already sent (avoid duplicates)
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM chat_messages
            WHERE conversation_id = ? AND sender_type = 'system'
              AND message = ?
        ");
        $checkStmt->execute([$conversationId, $autoReplyText]);
        $alreadySent = (int) $checkStmt->fetchColumn() > 0;

        if (!$alreadySent) {
            try {
                // Insert with 1-second delay effect — we'll do it via a flag
                $pdo->prepare("
                    INSERT INTO chat_messages
                        (conversation_id, sender_type, sender_id, message, is_read, is_delivered, created_at)
                    VALUES (?, 'system', NULL, ?, 0, 1, NOW())
                ")->execute([$conversationId, $autoReplyText]);
            } catch (Exception $e) { /* silent */ }
        }
    }
}

/* ========================================================
   7. Response
   ======================================================== */
json_success([
    'conversation' => [
        'id'              => $conversationId,
        'subject'         => $conversation['subject'],
        'status'          => $conversation['status'],
        'admin_id'        => $conversation['admin_id'] ? (int)$conversation['admin_id'] : null,
        'admin_online'    => $adminOnline,
        'last_message_at' => $conversation['last_message_at'],
        'created_at'      => $conversation['created_at'],
        'is_new'          => $isNew,
    ],
    'messages' => $messages,
    'user' => [
        'id'   => (int) $user['id'],
        'name' => $user['name'],
    ],
], $isNew ? 'Chat started.' : 'Chat resumed.');