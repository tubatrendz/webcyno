<?php
/**
 * api/admin/blog-delete.php — Delete Blog Post
 * Method: POST / PUT / DELETE
 * Body: { id }
 * Cleans up thumbnail file as well.
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'POST');
if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    json_error('Method not allowed.', 405);
}

check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$id = (int) input('id', 0);
if ($id <= 0) {
    json_error('Post ID is required.', 422, ['id' => 'Missing.']);
}

/* ---------- Fetch post ---------- */
$stmt = $pdo->prepare("SELECT id, title_en, thumbnail FROM blog_posts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) json_error('Post not found.', 404);

/* ---------- Delete ---------- */
try {
    $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([$id]);
} catch (PDOException $e) {
    if (WCB_ENV === 'development') {
        json_error('Delete failed: ' . $e->getMessage(), 500);
    }
    json_error('Could not delete post. Please try again.', 500);
}

/* ---------- File cleanup ---------- */
if (!empty($post['thumbnail'])) {
    delete_upload($post['thumbnail']);
}

/* ---------- Activity log ---------- */
try {
    log_activity(
        'blog_deleted',
        'blog_posts',
        $id,
        'By admin #' . $adminId . ' — ' . $post['title_en']
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'id'       => $id,
    'redirect' => base_url('admin/blog.php'),
], 'Post deleted successfully.');