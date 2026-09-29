<?php
/**
 * admin/blog.php — Blog Posts Listing + Add/Edit Modal (Quill.js editor)
 * Backend: api/admin/blog-save.php, api/admin/blog-delete.php
 */

$admin_page_title = 'Blog Posts';
$admin_active     = 'blog';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Filters ---------- */
$q        = trim((string) input('q', ''));
$status   = trim((string) input('status', ''));
$category = trim((string) input('category', ''));
$page     = max(1, (int) input('page', 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

$allowedStatus = ['published', 'draft'];

/* ---------- Where ---------- */
$where  = ["1=1"];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(title_en LIKE ? OR title_bn LIKE ? OR slug LIKE ?)";
    array_push($params, $like, $like, $like);
}
if ($status !== '' && in_array($status, $allowedStatus, true)) {
    $where[]  = "status = ?";
    $params[] = $status;
}
if ($category !== '') {
    $where[]  = "category = ?";
    $params[] = $category;
}
$whereSql = implode(' AND ', $where);

/* ---------- Counts (tabs) ---------- */
$tabCounts = ['all' => 0, 'published' => 0, 'draft' => 0];
try {
    $row = $pdo->query("
        SELECT
            COUNT(*) AS all_c,
            SUM(status = 'published') AS published_c,
            SUM(status = 'draft') AS draft_c
        FROM blog_posts
    ")->fetch();
    $tabCounts['all']       = (int) ($row['all_c']       ?? 0);
    $tabCounts['published'] = (int) ($row['published_c'] ?? 0);
    $tabCounts['draft']     = (int) ($row['draft_c']     ?? 0);
} catch (Exception $e) { /* silent */ }

/* ---------- Count filtered ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch posts ---------- */
$listStmt = $pdo->prepare("
    SELECT id, title_en, title_bn, slug, thumbnail, category, tags,
           status, views, published_at, created_at,
           (SELECT name FROM admins WHERE id = blog_posts.author_id) AS author_name
    FROM blog_posts
    WHERE $whereSql
    ORDER BY id DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$posts = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- Unique categories for filter + suggestions ---------- */
$categories = [];
try {
    $catStmt = $pdo->query("
        SELECT DISTINCT category FROM blog_posts
        WHERE category IS NOT NULL AND category != ''
        ORDER BY category ASC
    ");
    foreach ($catStmt->fetchAll() as $c) $categories[] = $c['category'];
} catch (Exception $e) { /* silent */ }

/* ---------- URL helper ---------- */
function blog_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('blog.php') . ($p ? '?' . http_build_query($p) : '');
}
?>

<!-- Quill.js -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Blog Posts</span>
        </nav>
        <h1 class="admin-page-title">Blog Posts</h1>
        <p class="admin-page-sub"><?= number_format($total) ?> post(s)</p>
    </div>
    <button type="button" class="admin-btn admin-btn-primary" id="btn-add-post">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Post
    </button>
</div>

<!-- ============ TABS ============ -->
<div class="admin-tabs" style="margin-bottom:16px;">
    <?php
    $tabs = [
        'all'       => ['label' => 'All',       'count' => $tabCounts['all'],       'filter' => null],
        'published' => ['label' => 'Published', 'count' => $tabCounts['published'], 'filter' => 'published'],
        'draft'     => ['label' => 'Drafts',    'count' => $tabCounts['draft'],     'filter' => 'draft'],
    ];
    foreach ($tabs as $key => $tab):
        $isActive = ($tab['filter'] === null && $status === '') || ($tab['filter'] !== null && $status === $tab['filter']);
    ?>
        <a href="<?= e(blog_url(['status' => $tab['filter'], 'page' => null])) ?>"
           class="admin-tab<?= $isActive ? ' active' : '' ?>">
            <?= e($tab['label']) ?>
            <span style="opacity:.7;font-size:11px;">(<?= (int) $tab['count'] ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<!-- ============ FILTER ============ -->
<form class="admin-filter-bar" method="get" action="<?= e(admin_url('blog.php')) ?>">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>

    <input type="search" name="q" value="<?= e($q) ?>" class="form-control"
           placeholder="Search title or slug..." style="min-width:240px;">

    <select name="category" data-filter-submit>
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                <?= e($cat) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Apply</button>
    <a href="<?= e(admin_url('blog.php')) ?>" class="admin-btn admin-btn-ghost admin-btn-sm">Reset</a>
</form>

<!-- ============ TABLE ============ -->
<?php if (empty($posts)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
            </svg>
            <h3>No blog posts yet</h3>
            <p>Publish your first article to engage customers.</p>
            <button type="button" class="admin-btn admin-btn-primary" style="margin-top:16px;" onclick="document.getElementById('btn-add-post').click();">
                Write First Post
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="admin-table-wrap">
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Image</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Views</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $p):
                        $title = $lang === 'bn' && !empty($p['title_bn']) ? $p['title_bn'] : $p['title_en'];
                        $thumb = $p['thumbnail'] ? base_url($p['thumbnail']) : base_url('assets/images/placeholder.png');
                        $date  = $p['published_at'] ?: $p['created_at'];
                        $statusCls = $p['status'] === 'published' ? 'tag-success' : 'tag-warning';
                    ?>
                        <tr>
                            <td>
                                <img src="<?= e($thumb) ?>" alt="" class="admin-thumb">
                            </td>
                            <td>
                                <div class="admin-cell-title"><?= e(str_limit($title, 60)) ?></div>
                                <div class="admin-cell-sub">
                                    by <?= e($p['author_name'] ?: 'Admin') ?>
                                    <?php if (!empty($p['tags'])): ?>
                                        • <?= e(str_limit($p['tags'], 40)) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="admin-cell-sub">
                                <?= $p['category'] ? e($p['category']) : '—' ?>
                            </td>
                            <td><?= (int) $p['views'] ?></td>
                            <td>
                                <span class="tag <?= e($statusCls) ?>" style="text-transform:capitalize;">
                                    <?= e($p['status']) ?>
                                </span>
                            </td>
                            <td class="admin-cell-sub"><?= e(date('M d, Y', strtotime($date))) ?></td>
                            <td>
                                <div class="cell-actions">
                                    <?php if ($p['status'] === 'published'): ?>
                                        <a href="<?= e(base_url('blog-details.php?slug=' . urlencode($p['slug']))) ?>"
                                           target="_blank" class="admin-btn admin-btn-ghost admin-btn-icon" title="View">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon"
                                            data-edit-post="<?= (int) $p['id'] ?>" title="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" style="color:var(--danger);"
                                            data-confirm-delete
                                            data-url="admin/blog/delete"
                                            data-message="Delete this post permanently?"
                                            data-id="<?= (int) $p['id'] ?>"
                                            title="Delete">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="admin-pagination">
            <?php if ($page > 1): ?><a href="<?= e(blog_url(['page' => $page - 1])) ?>">←</a><?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= e(blog_url(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(blog_url(['page' => $page + 1])) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- ============ ADD / EDIT MODAL ============ -->
<div id="post-modal" class="admin-modal">
    <div class="admin-modal-backdrop" data-modal-close></div>
    <div class="admin-modal-card" style="max-width:900px;">
        <div class="admin-modal-header">
            <h3 class="admin-card-title" id="post-modal-title">New Blog Post</h3>
            <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" data-modal-close aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="post-form" data-ajax data-endpoint="admin/blog/save" data-redirect="admin/blog.php"
              enctype="multipart/form-data" style="padding:20px;max-height:78vh;overflow-y:auto;">

            <input type="hidden" name="id" id="b-id" value="0">

            <!-- Basic -->
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Title (EN) <span class="required">*</span></label>
                    <input type="text" name="title_en" id="b-title_en" class="form-control" required maxlength="220">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Title (BN)</label>
                    <input type="text" name="title_bn" id="b-title_bn" class="form-control" maxlength="220">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Slug</label>
                    <input type="text" name="slug" id="b-slug" class="form-control" placeholder="auto from title" maxlength="250">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Category</label>
                    <input type="text" name="category" id="b-category" class="form-control"
                           placeholder="e.g. Marketing" list="cat-suggestions" maxlength="80">
                    <datalist id="cat-suggestions">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Tags</label>
                <input type="text" name="tags" id="b-tags" class="form-control"
                       placeholder="comma separated: seo, marketing, ai" maxlength="300">
            </div>

            <!-- Excerpts -->
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Excerpt (EN)</label>
                    <textarea name="excerpt_en" id="b-excerpt_en" class="form-control" rows="2" maxlength="500"></textarea>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Excerpt (BN)</label>
                    <textarea name="excerpt_bn" id="b-excerpt_bn" class="form-control" rows="2" maxlength="500"></textarea>
                </div>
            </div>

            <!-- Thumbnail -->
            <div class="admin-form-group">
                <label class="admin-label">Thumbnail</label>
                <div class="flex items-center gap-md">
                    <img id="b-thumb-preview" src="<?= e(base_url('assets/images/placeholder.png')) ?>" alt=""
                         style="width:100px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
                    <div style="flex:1;">
                        <input type="file" name="thumbnail" id="b-thumbnail" accept="image/jpeg,image/png,image/webp"
                               data-preview="#b-thumb-preview" class="form-control" style="padding:6px;">
                        <p class="admin-help">JPG/PNG/WEBP — max 5MB. Recommended 1200×628.</p>
                    </div>
                </div>
            </div>

            <!-- Content: Quill editors for EN and BN -->
            <div class="admin-form-group">
                <label class="admin-label">Content (EN)</label>
                <div id="b-content-en-editor" style="height:260px;background:#fff;border-radius:8px;overflow:hidden;"></div>
                <input type="hidden" name="content_en" id="b-content_en">
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Content (BN)</label>
                <div id="b-content-bn-editor" style="height:220px;background:#fff;border-radius:8px;overflow:hidden;"></div>
                <input type="hidden" name="content_bn" id="b-content_bn">
            </div>

            <!-- SEO -->
            <details style="margin-bottom:16px;border:1px solid var(--border);border-radius:8px;padding:12px;">
                <summary style="cursor:pointer;font-weight:600;color:var(--navy);">SEO & Meta</summary>
                <div style="margin-top:12px;">
                    <div class="admin-form-group">
                        <label class="admin-label">Meta Title</label>
                        <input type="text" name="meta_title" id="b-meta_title" class="form-control" maxlength="200">
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Meta Description</label>
                        <textarea name="meta_desc" id="b-meta_desc" class="form-control" rows="2" maxlength="300"></textarea>
                    </div>
                </div>
            </details>

            <!-- Publish options -->
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Status</label>
                    <select name="status" id="b-status" class="form-control">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Published At</label>
                    <input type="datetime-local" name="published_at" id="b-published_at" class="form-control">
                    <p class="admin-help">खाली রাখলে auto = now (published হলে)</p>
                </div>
            </div>

            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary" id="b-submit">Save Post</button>
            </div>
        </form>
    </div>
</div>

<style>
.ql-toolbar.ql-snow {
    border-radius: 8px 8px 0 0;
    border-color: var(--border);
    background: var(--bg-alt);
}
.ql-container.ql-snow {
    border-radius: 0 0 8px 8px;
    border-color: var(--border);
    font-family: inherit;
    font-size: 14px;
}
.ql-editor { min-height: 180px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('post-modal');
    var form  = document.getElementById('post-form');
    var title = document.getElementById('post-modal-title');

    /* ---------- Quill editors ---------- */
    var quillEn, quillBn;

    function initQuill() {
        if (quillEn && quillBn) return;

        var toolbarOptions = [
            [{ header: [2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            [{ align: [] }],
            ['clean']
        ];

        quillEn = new Quill('#b-content-en-editor', {
            theme: 'snow',
            placeholder: 'Write your content in English...',
            modules: { toolbar: toolbarOptions }
        });
        quillBn = new Quill('#b-content-bn-editor', {
            theme: 'snow',
            placeholder: 'বাংলা কনটেন্ট লিখুন...',
            modules: { toolbar: toolbarOptions }
        });

        // Sync into hidden inputs on change
        quillEn.on('text-change', function () {
            document.getElementById('b-content_en').value = quillEn.root.innerHTML;
        });
        quillBn.on('text-change', function () {
            document.getElementById('b-content_bn').value = quillBn.root.innerHTML;
        });
    }

    /* ---------- Open / Close ---------- */
    async function openModal(postId) {
        initQuill();
        form.reset();
        form.querySelector('#b-id').value = 0;
        document.getElementById('b-thumb-preview').src = '<?= e(base_url('assets/images/placeholder.png')) ?>';
        quillEn.root.innerHTML = '';
        quillBn.root.innerHTML = '';
        document.getElementById('b-content_en').value = '';
        document.getElementById('b-content_bn').value = '';

        if (postId && postId > 0) {
            title.textContent = 'Edit Blog Post';
            try {
                var res = await API.get('admin/blog/get', { id: postId });
                var p = res.data.post;
                form.querySelector('#b-id').value         = p.id;
                form.querySelector('#b-title_en').value   = p.title_en || '';
                form.querySelector('#b-title_bn').value   = p.title_bn || '';
                form.querySelector('#b-slug').value       = p.slug || '';
                form.querySelector('#b-category').value   = p.category || '';
                form.querySelector('#b-tags').value       = p.tags || '';
                form.querySelector('#b-excerpt_en').value = p.excerpt_en || '';
                form.querySelector('#b-excerpt_bn').value = p.excerpt_bn || '';
                form.querySelector('#b-meta_title').value = p.meta_title || '';
                form.querySelector('#b-meta_desc').value  = p.meta_desc || '';
                form.querySelector('#b-status').value     = p.status || 'draft';
                form.querySelector('#b-published_at').value = p.published_at || '';

                if (p.thumbnail) {
                    document.getElementById('b-thumb-preview').src = '<?= e(base_url()) ?>/' + p.thumbnail;
                }

                quillEn.root.innerHTML = p.content_en || '';
                quillBn.root.innerHTML = p.content_bn || '';
                document.getElementById('b-content_en').value = p.content_en || '';
                document.getElementById('b-content_bn').value = p.content_bn || '';
            } catch (err) {
                toast(err.message || 'Could not load post.', 'error');
                return;
            }
        } else {
            title.textContent = 'New Blog Post';
        }
        modal.classList.add('open');
    }

    function closeModal() { modal.classList.remove('open'); }

    document.getElementById('btn-add-post')?.addEventListener('click', function () { openModal(0); });

    document.querySelectorAll('[data-edit-post]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = parseInt(btn.getAttribute('data-edit-post'), 10) || 0;
            openModal(id);
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    /* ---------- Auto-slug ---------- */
    form.querySelector('#b-title_en')?.addEventListener('blur', function () {
        var slugEl = form.querySelector('#b-slug');
        if (slugEl.value.trim() !== '' || !this.value.trim()) return;
        slugEl.value = this.value.toLowerCase()
            .replace(/[^\p{L}\p{N}\s-]/gu, '')
            .replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    });

    /* ---------- Before submit: sync hidden Quill fields ---------- */
    form.addEventListener('submit', function () {
        if (quillEn) document.getElementById('b-content_en').value = quillEn.root.innerHTML;
        if (quillBn) document.getElementById('b-content_bn').value = quillBn.root.innerHTML;
    }, true);
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>