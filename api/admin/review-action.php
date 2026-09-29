<?php
/**
 * api/admin/review-action.php — Approve / Reject / Delete Review
 * Method: POST
 * Body: { id, action: approve|reject|delete }
 *
 * Side effects on approve/reject/delete:
 *  - Recalculate services.rating + services.total_reviews
 *    (only counting status = 'approved' reviews)
 *  - Notify user on approve/reject
 *  - Activity log
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$id     = (int) input('id', 0);
$action = trim((string) input('action', ''));

if ($id <= 0) {
    json_error('Review ID is required.', 422, ['id' => 'Missing.']);
}

$allowedActions = ['approve', 'reject', 'delete'];
if (!in_array($action, $allowedActions, true)) {
    json_error('Invalid action. Allowed: approve, reject, delete.', 422, ['action' => 'Invalid.']);
}

/* ---------- Fetch review ---------- */
$rStmt = $pdo->prepare("
    SELECT id, service_id, user_id, order_id, rating, comment, status
    FROM reviews WHERE id = ? LIMIT 1
");
$rStmt->execute([$id]);
$review = $rStmt->fetch();

if (!$review) json_error('Review not found.', 404);

$serviceId = (int) $review['service_id'];
$userId    = (int) $review['user_id'];

/* ---------- Apply action ---------- */
try {
    $pdo->beginTransaction();

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
        $newStatus = null;
        $message   = 'Review deleted.';
        $logAction = 'review_deleted';
    } else {
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        if ($newStatus === $review['status']) {
            $pdo->rollBack();
            json_error('Review is already ' . $newStatus . '.', 409);
        }
        $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ?")
            ->execute([$newStatus, $id]);
        $message   = 'Review ' . $newStatus . ' successfully.';
        $logAction = 'review_' . $newStatus;
    }

    /* ---------- Recalculate service rating ---------- */
    $aggStmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating
        FROM reviews
        WHERE service_id = ? AND status = 'approved'
    ");
    $aggStmt->execute([$serviceId]);
    $agg = $aggStmt->fetch() ?: ['cnt' => 0, 'avg_rating' => 0];

    $newCount = (int) $agg['cnt'];
    $newAvg   = round((float) $agg['avg_rating'], 2);

    $pdo->prepare("
        UPDATE services
        SET total_reviews = ?, rating = ?
        WHERE id = ?
    ")->execute([$newCount, $newAvg, $serviceId]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (WCB_ENV === 'development') {
        json_error('Action failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not complete action. Please try again.', 500);
}

/* ---------- Notify user ---------- */
if ($action !== 'delete' && $userId > 0) {
    try {
        $title = $action === 'approve' ? 'Review approved ✓' : 'Review rejected';
        $msg   = $action === 'approve'
            ? 'Your review has been approved and is now visible on the service page.'
            : 'Your review was not approved. You can submit a new one from your order page.';
        push_notification(
            'user',
            $userId,
            $title,
            $msg,
            'user/orders.php',
            $action === 'approve' ? 'success' : 'warning'
        );
    } catch (Exception $e) { /* silent */ }
}

/* ---------- Activity log ---------- */
try {
    log_activity(
        $logAction,
        'reviews',
        $id,
        'By admin #' . $adminId
            . ' — service #' . $serviceId
            . ', rating ' . (int) $review['rating']
            . ($newStatus ? ', status → ' . $newStatus : '')
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'id'            => $id,
    'action'        => $action,
    'status'        => $newStatus,
    'service_id'    => $serviceId,
    'total_reviews' => $newCount,
    'avg_rating'    => $newAvg,
], $message);