<?php
/**
 * admin/pages.php — Static Pages Content Editor
 * Pages: about, privacy-policy, terms, refund-policy
 * Storage: settings.pages_content (JSON) — { slug: { title, content, meta } }
 * Editor: Quill.js (EN only currently)
 * Backend: api/admin/pages-save.php
 */

$admin_page_title = 'Pages';
$admin_active     = 'pages';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Load existing pages ---------- */
$pagesRaw = setting('pages_content', '');
$pages    = $pagesRaw ? json_decode($pagesRaw, true) : [];
if (!is_array($pages)) $pages = [];

/* Ensure all keys exist with defaults */
$defaults = [
    'about' => [
        'title' => 'About Us',
        'content' => '<p>Webcyno একটি AI-powered digital service marketplace — যেখানে আপনি ওয়েবসাইট, ল্যান্ডিং পেজ, Facebook Ads, Google Ads সহ বিভিন্ন ডিজিটাল সার্ভিস অর্ডার করতে পারবেন।</p>',
        'meta' => ['title' => 'About Webcyno', 'desc' => 'Learn about Webcyno.'],
    ],
    'privacy-policy' => [
        'title' => 'Privacy Policy',
        'content' => '<p>আমরা আপনার গোপনীয়তাকে সম্মান করি। এই পেজে ব্যাখ্যা করা হয়েছে কী তথ্য সংগ্রহ করি, কীভাবে ব্যবহার করি।</p>',
        'meta' => ['title' => 'Privacy Policy', 'desc' => 'How we handle your data.'],
    ],
    'terms' => [
        'title' => 'Terms & Conditions',
        'content' => '<p>Webcyno ব্যবহার করে আপনি এই শর্তাবলীতে সম্মত হচ্ছেন।</p>',
        'meta' => ['title' => 'Terms & Conditions', 'desc' => 'Terms of service.'],
    ],
    'refund-policy' => [
        'title' => 'Refund Policy',
        'content' => '<p>আমরা কাস্টমার সন্তুষ্টিকে গুরুত্ব দিই। নিচে আমাদের রিফান্ড পলিসি দেওয়া হলো।</p>',
        'meta' => ['title' => 'Refund Policy', 'desc' => 'Our refund policy.'],
    ],
];

foreach ($defaults as $slug => $default) {
    if (!isset($pages[$slug]) || !is_array($pages[$slug])) {
        $pages[$slug] = $default;
    } else {
        $pages[$slug]['title']   = $pages[$slug]['title']   ?? $default['title'];
        $pages[$slug]['content'] = $pages[$slug]['content'] ?? $default['content'];
        $pages[$slug]['meta']    = $pages[$slug]['meta']    ?? $default['meta'];
    }
}

$pageLabels = [
    'about'          => 'About Us',
    'privacy-policy' => 'Privacy Policy',
    'terms'          => 'Terms & Conditions',
    'refund-policy'  => 'Refund Policy',
];

/* ---------- Active tab ---------- */
$activeTab = (string) input('tab', 'about');
if (!isset($pages[$activeTab])) $activeTab = 'about';
?>

<!-- Quill.js -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Pages</span>
        </nav>
        <h1 class="admin-page-title">Static Pages</h1>
        <p class="admin-page-sub">About, Privacy, Terms, Refund Policy — এখান থেকে সম্পাদনা করুন।</p>
    </div>
    <div class="flex gap-sm" style="flex-wrap:wrap;">
        <a href="<?= e(base_url('about.php')) ?>" target="_blank" class="admin-btn admin-btn-ghost">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Preview
        </a>
    </div>
</div>

<!-- ============ TABS ============ -->
<div class="admin-tabs" style="margin-bottom:20px;">
    <?php foreach ($pageLabels as $slug => $label): ?>
        <a href="<?= e(admin_url('pages.php?tab=' . urlencode($slug))) ?>"
           class="admin-tab<?= $activeTab === $slug ? ' active' : '' ?>">
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- ============ EDITOR ============ -->
<div class="admin-card">
    <form id="page-form" data-ajax data-endpoint="admin/pages/save" data-redirect="admin/pages.php?tab=<?= e($activeTab) ?>">

        <input type="hidden" name="slug" value="<?= e($activeTab) ?>">
        <input type="hidden" name="content" id="page-content-input">

        <div class="admin-form-row">
            <div class="admin-form-group">
                <label class="admin-label">Page Title <span class="required">*</span></label>
                <input type="text" name="title" class="form-control"
                       value="<?= e($pages[$activeTab]['title']) ?>"
                       required maxlength="200">
            </div>
            <div class="admin-form-group">
                <label class="admin-label">Preview URL</label>
                <input type="text" class="form-control"
                       value="<?= e(base_url($activeTab . '.php')) ?>" readonly
                       style="background:var(--bg-alt);color:var(--text-muted);">
            </div>
        </div>

        <div class="admin-form-group">
            <label class="admin-label">Content</label>
            <div id="page-editor" style="height:420px;background:#fff;border-radius:8px;overflow:hidden;"></div>
        </div>

        <!-- SEO -->
        <div style="margin-top:16px;padding:16px;background:var(--bg-alt);border-radius:8px;">
            <h4 style="font-size:13px;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:.3px;margin-bottom:12px;">
                SEO Meta
            </h4>
            <div class="admin-form-row">
                <div class="admin-form-group" style="margin:0;">
                    <label class="admin-label">Meta Title</label>
                    <input type="text" name="meta_title" class="form-control"
                           value="<?= e($pages[$activeTab]['meta']['title'] ?? '') ?>"
                           maxlength="200">
                </div>
                <div class="admin-form-group" style="margin:0;">
                    <label class="admin-label">Meta Description</label>
                    <input type="text" name="meta_desc" class="form-control"
                           value="<?= e($pages[$activeTab]['meta']['desc'] ?? '') ?>"
                           maxlength="300">
                </div>
            </div>
        </div>

        <div class="flex gap-sm" style="margin-top:20px;justify-content:flex-end;">
            <a href="<?= e(base_url($activeTab . '.php')) ?>" target="_blank" class="admin-btn admin-btn-ghost">
                Preview Page
            </a>
            <button type="submit" class="admin-btn admin-btn-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Save Page
            </button>
        </div>
    </form>
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
.ql-editor { min-height: 360px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var initialContent = <?= json_encode($pages[$activeTab]['content'], JSON_UNESCAPED_UNICODE) ?>;
    var form = document.getElementById('page-form');
    var hiddenInput = document.getElementById('page-content-input');

    /* ---------- Quill init ---------- */
    var quill = new Quill('#page-editor', {
        theme: 'snow',
        placeholder: 'Write page content...',
        modules: {
            toolbar: [
                [{ header: [2, 3, 4, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'code-block'],
                ['link'],
                [{ align: [] }],
                ['clean']
            ]
        }
    });

    quill.root.innerHTML = initialContent || '';
    hiddenInput.value    = quill.root.innerHTML;

    quill.on('text-change', function () {
        hiddenInput.value = quill.root.innerHTML;
    });

    /* ---------- Before submit sync ---------- */
    form.addEventListener('submit', function () {
        hiddenInput.value = quill.root.innerHTML;
    }, true);
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>