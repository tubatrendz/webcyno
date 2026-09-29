<?php
/**
 * blog-details.php — Single Blog Post
 * URL: blog-details.php?slug=xyz
 * Features: full content, view counter, related posts, share buttons, SEO meta
 */

require_once __DIR__ . '/includes/header.php';

global $pdo;

/* ---------- Resolve slug ---------- */
$slug = trim((string) input('slug', ''));
if ($slug === '') {
    header('Location: ' . base_url('blog.php'));
    exit;
}

/* ---------- Fetch post ---------- */
$stmt = $pdo->prepare("
    SELECT b.*,
           (SELECT name FROM admins WHERE id = b.author_id) AS author_name
    FROM blog_posts b
    WHERE b.slug = ? AND b.status = 'published'
    LIMIT 1
");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    ?>
    <main id="main-content">
        <div class="container" style="padding:var(--space-2xl) 0;">
            <div class="card empty-state">
                <h2><?= e(t('post_not_found', 'Post not found')) ?></h2>
                <p><?= e(t('post_not_found_sub', 'This article may have been removed or unpublished.')) ?></p>
                <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('blog.php')) ?>">
                    ← <?= e(t('back_to_blog', 'Back to Blog')) ?>
                </a>
            </div>
        </div>
    </main>
    <?php require_once __DIR__ . '/includes/footer.php'; exit;
}

$postId = (int) $post['id'];

/* ---------- View increment (session-based, once per post) ---------- */
$viewKey = 'blog_view_' . $postId;
if (empty($_SESSION[$viewKey])) {
    try {
        $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?")->execute([$postId]);
        $post['views'] = (int) $post['views'] + 1;
        $_SESSION[$viewKey] = 1;
    } catch (Exception $e) { /* silent */ }
}

/* ---------- Localized fields ---------- */
$title   = ($lang === 'bn' && !empty($post['title_bn']))   ? $post['title_bn']   : $post['title_en'];
$excerpt = ($lang === 'bn' && !empty($post['excerpt_bn'])) ? $post['excerpt_bn'] : ($post['excerpt_en'] ?? '');
$content = ($lang === 'bn' && !empty($post['content_bn'])) ? $post['content_bn'] : ($post['content_en'] ?? '');

$tags = [];
if (!empty($post['tags'])) {
    $tags = array_values(array_filter(array_map('trim', explode(',', $post['tags']))));
}

$author   = $post['author_name'] ?: 'Admin';
$pubDate  = $post['published_at'] ?: $post['created_at'];
$thumbUrl = $post['thumbnail'] ? base_url($post['thumbnail']) : null;
$postUrl  = base_url('blog-details.php?slug=' . urlencode($post['slug']));

/* Override page title + meta */
$page_title = $post['meta_title'] ?: ($title . ' — ' . setting('site_name', 'Webcyno'));
$meta_desc  = $post['meta_desc']  ?: str_limit($excerpt, 160);

/* ---------- Related posts ---------- */
$related = [];
if (!empty($post['category'])) {
    $rStmt = $pdo->prepare("
        SELECT id, title_en, title_bn, slug, excerpt_en, excerpt_bn, thumbnail,
               published_at, created_at
        FROM blog_posts
        WHERE status = 'published' AND category = ? AND id != ?
        ORDER BY COALESCE(published_at, created_at) DESC
        LIMIT 3
    ");
    $rStmt->execute([$post['category'], $postId]);
    $related = $rStmt->fetchAll();
}
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <a href="<?= e(base_url('blog.php')) ?>"><?= e(t('blog')) ?></a>
            <?php if (!empty($post['category'])): ?>
                <span class="sep">/</span>
                <a href="<?= e(base_url('blog.php?category=' . urlencode($post['category']))) ?>"><?= e($post['category']) ?></a>
            <?php endif; ?>
            <span class="sep">/</span>
            <span class="current"><?= e(str_limit($title, 40)) ?></span>
        </nav>

        <div style="display:grid;grid-template-columns:1fr 300px;gap:var(--space-xl);align-items:start;">

            <!-- ============ MAIN ============ -->
            <article>
                <!-- Hero image -->
                <?php if ($thumbUrl): ?>
                    <div style="border-radius:var(--radius-lg);overflow:hidden;margin-bottom:var(--space-lg);">
                        <img src="<?= e($thumbUrl) ?>" alt="<?= e($title) ?>"
                             style="width:100%;max-height:480px;object-fit:cover;display:block;">
                    </div>
                <?php endif; ?>

                <!-- Title + meta -->
                <header style="margin-bottom:var(--space-lg);">
                    <?php if (!empty($post['category'])): ?>
                        <span class="tag tag-primary" style="margin-bottom:var(--space-sm);display:inline-block;">
                            <?= e($post['category']) ?>
                        </span>
                    <?php endif; ?>

                    <h1 style="font-size:var(--fs-3xl);line-height:1.25;margin-bottom:var(--space);">
                        <?= e($title) ?>
                    </h1>

                    <?php if ($excerpt): ?>
                        <p class="text-muted" style="font-size:var(--fs-md);line-height:1.7;margin-bottom:var(--space);">
                            <?= e($excerpt) ?>
                        </p>
                    <?php endif; ?>

                    <div class="flex items-center gap-md" style="font-size:var(--fs-sm);color:var(--text-muted);flex-wrap:wrap;padding-bottom:var(--space);border-bottom:1px solid var(--border);">
                        <span class="flex items-center gap-sm">
                            <span style="width:32px;height:32px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;border-radius:50%;font-weight:700;font-size:var(--fs-xs);">
                                <?= e(mb_strtoupper(mb_substr($author, 0, 1))) ?>
                            </span>
                            <strong style="color:var(--navy);"><?= e($author) ?></strong>
                        </span>
                        <span>•</span>
                        <span><?= e(date('F d, Y', strtotime($pubDate))) ?></span>
                        <span>•</span>
                        <span class="flex items-center gap-xs">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <?= (int) $post['views'] ?> <?= e(t('views', 'views')) ?>
                        </span>
                    </div>
                </header>

                <!-- Content -->
                <div class="article-content" style="font-size:var(--fs-md);line-height:1.85;color:var(--text);">
                    <?= $content ?: '<p class="text-muted">' . e(t('no_content', 'No content available.')) . '</p>' ?>
                </div>

                <!-- Tags -->
                <?php if ($tags): ?>
                    <div class="flex items-center gap-sm" style="margin-top:var(--space-lg);padding-top:var(--space-lg);border-top:1px solid var(--border);flex-wrap:wrap;">
                        <strong style="font-size:var(--fs-sm);color:var(--navy);"><?= e(t('tags', 'Tags')) ?>:</strong>
                        <?php foreach ($tags as $tag): ?>
                            <a href="<?= e(base_url('blog.php?q=' . urlencode($tag))) ?>"
                               class="tag tag-primary" style="text-decoration:none;">
                                #<?= e($tag) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Share -->
                <div style="margin-top:var(--space-lg);padding:var(--space);background:var(--bg-alt);border-radius:var(--radius-md);">
                    <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:var(--space);">
                        <strong style="font-size:var(--fs-sm);color:var(--navy);">
                            <?= e(t('share_article', 'Share this article')) ?>
                        </strong>
                        <div class="flex items-center gap-sm">
                            <?php
                            $shareUrl   = urlencode($postUrl);
                            $shareTitle = urlencode($title);
                            $shares = [
                                'facebook' => [
                                    'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . $shareUrl,
                                    'icon'=> '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
                                    'bg'  => '#1877F2',
                                ],
                                'twitter' => [
                                    'url' => 'https://twitter.com/intent/tweet?url=' . $shareUrl . '&text=' . $shareTitle,
                                    'icon'=> '<path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>',
                                    'bg'  => '#1DA1F2',
                                ],
                                'linkedin' => [
                                    'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $shareUrl,
                                    'icon'=> '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
                                    'bg'  => '#0A66C2',
                                ],
                            ];
                            foreach ($shares as $key => $s):
                            ?>
                                <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener"
                                   aria-label="Share on <?= e(ucfirst($key)) ?>"
                                   style="width:38px;height:38px;background:<?= e($s['bg']) ?>;color:#fff;display:flex;align-items:center;justify-content:center;border-radius:var(--radius);transition:transform var(--t-fast);"
                                   onmouseover="this.style.transform='translateY(-2px)';"
                                   onmouseout="this.style.transform='translateY(0)';">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><?= $s['icon'] ?></svg>
                                </a>
                            <?php endforeach; ?>

                            <button type="button" id="copy-link"
                                    aria-label="<?= e(t('copy_link', 'Copy link')) ?>"
                                    style="width:38px;height:38px;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;border-radius:var(--radius);border:none;cursor:pointer;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Back to blog -->
                <div style="margin-top:var(--space-lg);">
                    <a href="<?= e(base_url('blog.php')) ?>" class="btn btn-ghost">
                        ← <?= e(t('back_to_blog', 'Back to Blog')) ?>
                    </a>
                </div>
            </article>

            <!-- ============ SIDEBAR ============ -->
            <aside style="position:sticky;top:calc(var(--header-h) + 16px);">

                <!-- Author card -->
                <div class="card" style="padding:var(--space-lg);margin-bottom:var(--space);">
                    <div class="text-center">
                        <span style="width:64px;height:64px;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;font-weight:800;font-size:var(--fs-xl);margin-bottom:var(--space-sm);">
                            <?= e(mb_strtoupper(mb_substr($author, 0, 1))) ?>
                        </span>
                        <h4 style="font-size:var(--fs-md);margin-bottom:4px;"><?= e($author) ?></h4>
                        <p class="text-muted" style="font-size:var(--fs-xs);margin:0;">
                            <?= e(t('author_role', 'Author at ') . setting('site_name', 'Webcyno')) ?>
                        </p>
                    </div>
                </div>

                <!-- Share sidebar -->
                <div class="card" style="padding:var(--space-lg);margin-bottom:var(--space);">
                    <h4 style="font-size:var(--fs-md);margin-bottom:var(--space);">
                        <?= e(t('share', 'Share')) ?>
                    </h4>
                    <p class="text-muted" style="font-size:var(--fs-xs);margin-bottom:var(--space);">
                        <?= e(t('share_note', 'Help others discover this article.')) ?>
                    </p>
                    <div class="flex flex-col gap-sm">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($postUrl) ?>"
                           target="_blank" rel="noopener" class="btn btn-ghost btn-sm btn-block"
                           style="justify-content:flex-start;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877F2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                            Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?= urlencode($postUrl) ?>&text=<?= urlencode($title) ?>"
                           target="_blank" rel="noopener" class="btn btn-ghost btn-sm btn-block"
                           style="justify-content:flex-start;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#1DA1F2"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
                            Twitter
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($postUrl) ?>"
                           target="_blank" rel="noopener" class="btn btn-ghost btn-sm btn-block"
                           style="justify-content:flex-start;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#0A66C2"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                            LinkedIn
                        </a>
                    </div>
                </div>

                <!-- More from blog -->
                <div class="card" style="padding:var(--space-lg);">
                    <h4 style="font-size:var(--fs-md);margin-bottom:var(--space);">
                        <?= e(t('more_from_blog', 'More from Blog')) ?>
                    </h4>
                    <a href="<?= e(base_url('blog.php')) ?>" class="btn btn-primary btn-sm btn-block">
                        <?= e(t('view_all_posts', 'View All Posts')) ?>
                    </a>
                </div>
            </aside>
        </div>

        <!-- ============ RELATED POSTS ============ -->
        <?php if ($related): ?>
            <section style="margin-top:var(--space-2xl);">
                <h2 style="font-size:var(--fs-2xl);margin-bottom:var(--space-md);">
                    <?= e(t('related_articles', 'Related Articles')) ?>
                </h2>
                <div class="grid grid-3">
                    <?php foreach ($related as $r):
                        $rTitle = ($lang === 'bn' && !empty($r['title_bn'])) ? $r['title_bn'] : $r['title_en'];
                        $rExc   = ($lang === 'bn' && !empty($r['excerpt_bn'])) ? $r['excerpt_bn'] : ($r['excerpt_en'] ?? '');
                        $rThumb = $r['thumbnail'] ? base_url($r['thumbnail']) : base_url('assets/images/placeholder.png');
                        $rDate  = $r['published_at'] ?: $r['created_at'];
                        $rUrl   = base_url('blog-details.php?slug=' . urlencode($r['slug']));
                    ?>
                        <article class="card" style="padding:0;overflow:hidden;">
                            <a href="<?= e($rUrl) ?>" style="display:block;overflow:hidden;">
                                <img src="<?= e($rThumb) ?>" alt="<?= e($rTitle) ?>" loading="lazy"
                                     style="width:100%;aspect-ratio:16/9;object-fit:cover;display:block;">
                            </a>
                            <div style="padding:var(--space);">
                                <h3 style="font-size:var(--fs-md);line-height:1.4;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                    <a href="<?= e($rUrl) ?>" style="color:var(--navy);"><?= e($rTitle) ?></a>
                                </h3>
                                <p class="text-muted" style="font-size:var(--fs-sm);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                    <?= e(str_limit($rExc, 100)) ?>
                                </p>
                                <span class="text-muted" style="font-size:var(--fs-xs);display:block;margin-top:10px;">
                                    <?= e(date('M d, Y', strtotime($rDate))) ?>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<style>
.article-content h2,
.article-content h3 { margin-top: var(--space-lg); margin-bottom: var(--space-sm); color: var(--navy); }
.article-content h2 { font-size: var(--fs-2xl); }
.article-content h3 { font-size: var(--fs-xl); }
.article-content p { margin-bottom: var(--space); }
.article-content ul,
.article-content ol { margin: var(--space) 0 var(--space) 1.5em; }
.article-content ul li { list-style: disc; margin-bottom: 6px; }
.article-content ol li { list-style: decimal; margin-bottom: 6px; }
.article-content a { color: var(--primary); text-decoration: underline; }
.article-content img { border-radius: var(--radius-md); margin: var(--space) 0; }
.article-content blockquote {
    border-left: 4px solid var(--primary);
    padding: var(--space-sm) var(--space);
    margin: var(--space) 0;
    background: var(--primary-soft);
    border-radius: var(--radius);
    font-style: italic;
    color: var(--navy);
}
.article-content pre {
    background: var(--navy);
    color: #E2E8F0;
    padding: var(--space);
    border-radius: var(--radius-md);
    overflow-x: auto;
    font-size: var(--fs-sm);
    margin: var(--space) 0;
}
.article-content code {
    background: var(--bg-alt);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.9em;
}
.article-content pre code { background: transparent; padding: 0; color: inherit; }

@media (max-width: 900px) {
    main#main-content > .container > div[style*="grid-template-columns:1fr 300px"] {
        grid-template-columns: 1fr !important;
    }
    aside[style*="position:sticky"] { position: static !important; }
}
</style>

<script>
// Copy link button
document.getElementById('copy-link')?.addEventListener('click', function () {
    var url = <?= json_encode($postUrl) ?>;
    var btn = this;

    function done() {
        toast('<?= e(t('link_copied', 'Link copied to clipboard')) ?>', 'success');
        btn.style.background = 'var(--success)';
        setTimeout(function () { btn.style.background = 'var(--navy)'; }, 1500);
    }

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(done);
    } else {
        var tmp = document.createElement('textarea');
        tmp.value = url;
        tmp.style.position = 'absolute';
        tmp.style.left = '-9999px';
        document.body.appendChild(tmp);
        tmp.select();
        try { document.execCommand('copy'); done(); }
        catch (e) { toast('<?= e(t('copy_failed', 'Copy failed — please copy manually')) ?>', 'error'); }
        document.body.removeChild(tmp);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>