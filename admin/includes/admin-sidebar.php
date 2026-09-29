<?php
/**
 * admin/includes/admin-sidebar.php — Admin Sidebar Navigation (v4.0)
 */

global $pdo;

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');

/* ---------- Live counts ---------- */
$unreadChat = 0;
$pendingOrders = 0;
$pendingPayments = 0;
$unreadNotifications = 0;

try {
    $unreadChat = (int) $pdo->query("
        SELECT COUNT(*) FROM chat_conversations WHERE status = 'open' AND unread_admin > 0
    ")->fetchColumn();
} catch (Exception $e) {}

try {
    $pendingOrders = (int) $pdo->query("
        SELECT COUNT(*) FROM orders WHERE order_status = 'pending'
    ")->fetchColumn();
} catch (Exception $e) {}

try {
    $pendingPayments = (int) $pdo->query("
        SELECT COUNT(*) FROM payments WHERE status = 'pending'
    ")->fetchColumn();
} catch (Exception $e) {}

try {
    $unreadNotifications = (int) $pdo->query("
        SELECT COUNT(*) FROM notifications WHERE user_type = 'admin' AND is_read = 0
    ")->fetchColumn();
} catch (Exception $e) {}

/* ---------- Admin info ---------- */
$adminName  = $_SESSION['admin_name']  ?? 'Admin';
$adminEmail = $_SESSION['admin_email'] ?? '';
$adminRole  = $_SESSION['admin_role']  ?? 'manager';
$adminInitial = mb_strtoupper(mb_substr($adminName, 0, 1));

/* ---------- Menu structure ---------- */
$menus = [
    'MAIN' => [
        ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => admin_url('dashboard.php'), 'key' => 'dashboard'],
        ['icon' => 'orders',    'label' => 'Orders',    'url' => admin_url('orders.php'),    'key' => 'orders',    'badge' => $pendingOrders],
    ],
    'CATALOG' => [
        ['icon' => 'services',   'label' => 'Services',     'url' => admin_url('services.php'),   'key' => 'services'],
        ['icon' => 'subcat',     'label' => 'Sub-categories','url' => admin_url('subcategories.php'), 'key' => 'subcategories'],
        ['icon' => 'demos',      'label' => 'Website Demos','url' => admin_url('service-cpanel.php'), 'key' => 'service-cpanel'],
        ['icon' => 'categories', 'label' => 'Categories',   'url' => admin_url('categories.php'), 'key' => 'categories'],
        ['icon' => 'reviews',    'label' => 'Reviews',      'url' => admin_url('reviews.php'),    'key' => 'reviews'],
        ['icon' => 'coupons',    'label' => 'Coupons',      'url' => admin_url('coupons.php'),    'key' => 'coupons'],
    ],
    'PEOPLE' => [
        ['icon' => 'users',         'label' => 'Users',         'url' => admin_url('users.php'),         'key' => 'users'],
        ['icon' => 'chat',          'label' => 'Live Chat',     'url' => admin_url('chat.php'),          'key' => 'chat',          'badge' => $unreadChat, 'badgeColor' => 'red'],
        ['icon' => 'notifications', 'label' => 'Notifications', 'url' => admin_url('notifications.php'), 'key' => 'notifications', 'badge' => $unreadNotifications],
        ['icon' => 'broadcast',     'label' => 'Broadcast',     'url' => admin_url('broadcast.php'),     'key' => 'broadcast'],
    ],
    'CONTENT' => [
        ['icon' => 'blog',     'label' => 'Blog Posts', 'url' => admin_url('blog.php'),     'key' => 'blog'],
        ['icon' => 'pages',    'label' => 'Pages',      'url' => admin_url('pages.php'),    'key' => 'pages'],
        ['icon' => 'homepage', 'label' => 'Homepage',   'url' => admin_url('homepage.php'), 'key' => 'homepage'],
    ],
    'SYSTEM' => [
        ['icon' => 'payments',     'label' => 'Payments',     'url' => admin_url('payments.php'),     'key' => 'payments',     'badge' => $pendingPayments],
        ['icon' => 'translations', 'label' => 'Translations', 'url' => admin_url('translations.php'), 'key' => 'translations'],
        ['icon' => 'seo',          'label' => 'SEO',          'url' => admin_url('seo.php'),          'key' => 'seo'],
        ['icon' => 'settings',     'label' => 'Settings',     'url' => admin_url('settings.php'),     'key' => 'settings'],
    ],
    'RESTRICTED' => [
        ['icon' => 'admins', 'label' => 'Admin Users', 'url' => admin_url('admins.php'), 'key' => 'admins'],
        ['icon' => 'logs',   'label' => 'Activity Log','url' => admin_url('logs.php'),   'key' => 'logs'],
    ],
];

/* ---------- Icon SVG helper ---------- */
function sidebar_icon(string $name): string {
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'orders'    => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'services'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'subcat'    => '<path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M3 14h7v7H3z"/><path d="M14 14h7v7h-7z"/>',
        'demos'     => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
        'categories'=> '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'reviews'   => '<polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18.5 5.5 22 7 14.5 2 9.5 9 9"/>',
        'coupons'   => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'chat'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'notifications' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'broadcast' => '<path d="M3 11l19-9-9 19-2-8-8-2z"/>',
        'blog'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'pages'     => '<path d="M4 4h16v16H4z"/><path d="M4 10h16"/><path d="M10 4v16"/>',
        'homepage'  => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'payments'  => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'translations' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'seo'       => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'admins'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'logs'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>',
    ];
    return $icons[$name] ?? $icons['dashboard'];
}
?>

<aside class="admin-sidebar" id="adminSidebar">

    <!-- Brand -->
    <div class="admin-sidebar-brand">
        <a href="<?= e(admin_url('dashboard.php')) ?>">
            <span class="admin-sidebar-logo">W</span>
            <span class="admin-sidebar-name">Webcyno</span>
        </a>
        <button type="button" class="admin-sidebar-close" id="sidebarClose" aria-label="Close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <!-- Menu -->
    <nav class="admin-sidebar-nav">

        <?php foreach ($menus as $sectionName => $items): ?>
            <div class="admin-sidebar-section">
                <div class="admin-sidebar-section-title"><?= e($sectionName) ?></div>

                <?php foreach ($items as $item):
                    $isActive = ($currentPage === basename(parse_url($item['url'], PHP_URL_PATH)));
                    $hasBadge = !empty($item['badge']) && $item['badge'] > 0;
                    $badgeColor = $item['badgeColor'] ?? 'primary';
                ?>
                    <a href="<?= e($item['url']) ?>"
                       class="admin-sidebar-link<?= $isActive ? ' active' : '' ?>">
                        <span class="admin-sidebar-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <?= sidebar_icon($item['icon']) ?>
                            </svg>
                        </span>
                        <span class="admin-sidebar-label"><?= e($item['label']) ?></span>

                        <?php if ($hasBadge): ?>
                            <span class="admin-sidebar-badge admin-sidebar-badge-<?= e($badgeColor) ?>">
                                <?= (int)$item['badge'] > 99 ? '99+' : (int)$item['badge'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <!-- User footer -->
    <div class="admin-sidebar-footer">
        <div class="admin-sidebar-user">
            <span class="admin-sidebar-avatar"><?= e($adminInitial) ?></span>
            <div class="admin-sidebar-user-info">
                <div class="admin-sidebar-user-name"><?= e(str_limit($adminName, 18)) ?></div>
                <div class="admin-sidebar-user-role"><?= e(ucfirst($adminRole)) ?></div>
            </div>
        </div>
    </div>
</aside>