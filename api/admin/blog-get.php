<?php
/**
 * api/admin/blog-get.php — Get Single Blog Post (for edit modal)
 * Method: GET
 * Query: ?id=123
 * Response: { post: {...full row...} }
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

require_admin();
global $pdo;

$id = (int) input('id', 0);
if ($id <= 0) {
    json_error('Post ID is required.', 422, ['id' => 'Missing.']);
}

$stmt = $pdo->prepare("
    SELECT b.*,
           (SELECT name FROM admins WHERE id = b.author_id) AS author_name
    FROM blog_posts b
    WHERE b.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) json_error('Post not found.', 404);

json_success([
    'post' => [
        'id'           => (int) $post['id'],
        'title_en'     => $post['title_en'],
        'title_bn'     => $post['title_bn'],
        'slug'         => $post['slug'],
        'excerpt_en'   => $post['excerpt_en'],
        'excerpt_bn'   => $post['excerpt_bn'],
        'content_en'   => $post['content_en'],
        'content_bn'   => $post['content_bn'],
        'thumbnail'    => $post['thumbnail'],
        'category'     => $post['category'],
        'tags'         => $post['tags'],
        'meta_title'   => $post['meta_title'],
        'meta_desc'    => $post['meta_desc'],
        'status'       => $post['status'],
        'views'        => (int) $post['views'],
        'author'       => $post['author_name'] ?: 'Admin',
        'published_at' => $post['published_at'] ? date('Y-m-d\TH:i', strtotime($post['published_at'])) : '',
        'created_at'   => $post['created_at'],
        'updated_at'   => $post['updated_at'],
    ],
], 'Post loaded.');