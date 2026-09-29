<?php
/**
 * api/reviews/add.php — Submit a Review
 * Method: POST (login required)
 * Body: { service_id, order_id, rating (1-5), comment? }
 *
 * Rules:
 *  - User অবশ্যই service টা কিনে থাকবে (order_id verified)
 *  - Order status must be 'completed'
 *  - Per service per user একবারই review (একই order_id দিয়ে)
 *  - Rating 1-5, comment max 1000 chars
 *  - review pending → admin approve করলে দেখাবে
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$userId = require_user();
global $pdo;

/* ---------- Input ---------- */
$serviceId = (int) input('service_id', 0);
$orderId   = (int) input('order_id', 0);
$rating    = (int) input('rating', 0);
$comment   = clean(input('comment', ''), 1000);

/* ---------- Validate ---------- */
$errors = [];
if ($serviceId <= 0)             $errors['service_id'] = 'Service required.';
if ($orderId <= 0)               $errors['order_id']   = 'Order required.';
if ($rating < 1 || $rating > 5)  $errors['rating']     = 'Rating must be 1–5.';
if (mb_strlen($comment) > 1000)  $errors['comment']    = 'Comment too long (max 1000).';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Order belongs to user + completed ---------- */
$oStmt = $pdo->prepare("
    SELECT id, order_status, user_id
    FROM orders
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$oStmt->execute([$orderId, $userId]);
$order = $oStmt->fetch();

if (!$order) {
    json_error('Order not found.', 404, ['order_id' => 'Not your order.']);
}
if ($order['order_status'] !== 'completed') {
    json_error('You can review only after the order is completed.', 403);
}

/* ---------- Service must be in that order ---------- */
$iStmt = $pdo->prepare("
    SELECT id FROM order_items
    WHERE order_id = ? AND service_id = ?
    LIMIT 1
");
$iStmt->execute([$orderId, $serviceId]);
if (!$iStmt->fetch()) {
    json_error('This service is not part of the given order.', 422, ['service_id' => 'Not in order.']);
}

/* ---------- Duplicate check (this order + this service) ---------- */
$dStmt = $pdo->prepare("
    SELECT id, status FROM reviews
    WHERE user_id = ? AND service_id = ? AND order_id = ?
    LIMIT 1
");
$dStmt->execute([$userId, $serviceId, $orderId]);
$existing = $dStmt->fetch();

if ($existing) {
    if ($existing['status'] === 'rejected') {
        // Previously rejected → update it back to pending for re-review
        $pdo->prepare("
            UPDATE reviews
            SET rating = ?, comment = ?, status = 'pending', created_at = NOW()
            WHERE id = ?
        ")->execute([$rating, $comment, $existing['id']]);

        // Notify admin
        try {
            push_notification(
                'admin', null,
                'Review resubmitted',
                'User #' . $userId . ' resubmitted a review for service #' . $serviceId,
                'admin/reviews.php',
                'info'
            );
        } catch (Exception $e) { /* silent */ }

        json_success([
            'review_id' => (int) $existing['id'],
            'status'    => 'pending',
        ], 'Review resubmitted for approval.', 201);
    }

    json_error('You have already reviewed this service.', 409, [
        'review' => 'Already reviewed for this order.',
    ]);
}

/* ---------- Insert review ---------- */
try {
    $stmt = $pdo->prepare("
        INSERT INTO reviews
            (service_id, user_id, order_id, rating, comment, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$serviceId, $userId, $orderId, $rating, $comment]);
    $reviewId = (int) $pdo->lastInsertId();
} catch (Exception $e) {
    json_error('Could not submit review. Please try again.', 500);
}

/* ---------- Notify admin ---------- */
try {
    push_notification(
        'admin', null,
        'New review pending',
        'User #' . $userId . ' submitted a ' . $rating . '★ review for service #' . $serviceId . '.',
        'admin/reviews.php',
        'star'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Notify user ---------- */
try {
    push_notification(
        'user', $userId,
        t('review_submitted_title', 'Review submitted'),
        t('review_submitted_msg', 'Thank you! Your review is pending approval and will appear soon.'),
        'user/orders.php',
        'info'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Log ---------- */
try {
    log_activity('review_submitted', 'reviews', $reviewId, 'Rating ' . $rating . ' for service #' . $serviceId);
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'review_id' => $reviewId,
    'status'    => 'pending',
], 'Thank you! Your review will appear after admin approval.', 201);