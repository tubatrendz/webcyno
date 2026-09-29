<?php
/**
 * api/user/notifications.php — User Notifications (JSON / AJAX)
 * Method: GET  → list notifications
 * Method: POST → actions (read, read_all, delete, clear_read)
 *
 * GET Query:  ?filter=all|unread&page=1&per_page=15
 * POST Body:  { action: read|read_all|delete|clear_read, notif_id? }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$userId = require_user();
global $pdo;

/* ========================================================
   GET — List notifications
   ======================================================== */
if ($method === 'GET') {

    $filter  = input('filter', 'all');       // all | unread
    $page    = max(1, (int) input('page', 1));
    $perPage = (int) input('per_page', 15);
    if ($perPage < 1 || $perPage > 50) $perPage = 15;
    $offset  = ($page - 1) * $perPage;

    $where  = "user_type = 'user' AND user_id = ?";
    $params = [$userId];
    if ($filter === 'unread') {
        $where .= " AND is_read = 0";
    }

    // Count
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $where");
    $cntStmt->execute($params);
    $total = (int) $cntStmt->fetchColumn();

    // Fetch
    $stmt = $pdo->prepare("
        SELECT id, title, message, link, icon, is_read, created_at
        FROM notifications
        WHERE $where
        ORDER BY id DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Unread badge count
    $uStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_type='user' AND user_id=? AND is_read=0");
    $uStmt->execute([$userId]);
    $unread = (int) $uStmt->fetchColumn();

    // Format
    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'id'         => (int) $r['id'],
            'title'      => $r['title'],
            'message'    => $r['message'],
            'link'       => $r['link'],
            'link_url'   => $r['link'] ? (preg_match('#^https?://#i', $r['link']) ? $r['link'] : base_url($r['link'])) : null,
            'icon'       => $r['icon'] ?: 'info',
            'is_read'    => (int) $r['is_read'],
            'created_at' => $r['created_at'],
            'time_ago'   => time_ago($r['created_at']),
        ];
    }

    json_success([
        'items'      => $items,
        'unread'     => $unread,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / max($perPage, 1)),
        ],
    ], 'Notifications loaded.');
}

/* ========================================================
   POST — Actions
   ======================================================== */
if ($method === 'POST') {

    check_csrf();
    $action  = input('action', '');
    $notifId = (int) input('notif_id', 0);

    try {
        switch ($action) {
            case 'read':
                if ($notifId <= 0) json_error('Notification ID required.', 422);
                $pdo->prepare("
                    UPDATE notifications SET is_read = 1
                    WHERE id = ? AND user_type = 'user' AND user_id = ?
                ")->execute([$notifId, $userId]);
                $msg = 'Marked as read.';
                break;

            case 'read_all':
                $pdo->prepare("
                    UPDATE notifications SET is_read = 1
                    WHERE user_type = 'user' AND user_id = ? AND is_read = 0
                ")->execute([$userId]);
                $msg = 'All notifications marked as read.';
                break;

            case 'delete':
                if ($notifId <= 0) json_error('Notification ID required.', 422);
                $pdo->prepare("
                    DELETE FROM notifications
                    WHERE id = ? AND user_type = 'user' AND user_id = ?
                ")->execute([$notifId, $userId]);
                $msg = 'Notification deleted.';
                break;

            case 'clear_read':
                $pdo->prepare("
                    DELETE FROM notifications
                    WHERE user_type = 'user' AND user_id = ? AND is_read = 1
                ")->execute([$userId]);
                $msg = 'Read notifications cleared.';
                break;

            default:
                json_error('Unknown action.', 422);
        }
    } catch (Exception $e) {
        json_error('Could not complete the action.', 500);
    }

    // Fresh unread count
    $uStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_type='user' AND user_id=? AND is_read=0");
    $uStmt->execute([$userId]);
    $unread = (int) $uStmt->fetchColumn();

    json_success([
        'action' => $action,
        'unread' => $unread,
    ], $msg);
}

/* ---------- Fallback ---------- */
json_error('Method not allowed.', 405);