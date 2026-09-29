<?php
/**
 * user/index.php — User Dashboard
 * Stats + recent orders + quick links
 */

require_once __DIR__ . '/../includes/header.php';

/* ---------- Auth guard ---------- */
if (empty($_SESSION['user_id'])) {
    header('Location: ' . base_url('login.php?redirect=' . urlencode('user/index.php')));
    exit;
}

$user = current_user();
if (!$user) {
    header('Location: ' . base_url('logout.php'));
    exit;
}

global $pdo;
$userId = (int) $user['id'];

/* ---------- Stats ---------- */
$stats = [
    'total'     => 0,
    'pending'   => 0,
    'completed' => 0,
    'cancelled' => 0,
];

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(order_status = 'pending')   AS pending,
        SUM(order_status = 'completed') AS completed,
        SUM(order_status = 'cancelled') AS cancelled
    FROM orders WHERE user_id = ?
");
$stmt->execute([$userId]);
$row = $stmt->fetch();
if ($row) {
    $stats['total']     = (int) $row['total'];
    $stats['pending']   = (int) $row['pending'];
    $stats['completed'] = (int) $row['completed'];
    $stats['cancelled'] = (int) $row['cancelled'];
}

/* ---------- Recent orders ---------- */
$ordStmt = $pdo->prepare("
    SELECT id, order_number, total, currency, order_status, payment_status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 5
");
$ordStmt->execute([$userId]);
$recentOrders = $ordStmt->fetchAll();

/* ---------- Unread notifications ---------- */
$nStmt = $pdo->prepare("
    SELECT COUNT(*) FROM notifications
    WHERE user_type = 'user' AND user_id = ? AND is_read = 0
");
$nStmt->execute([$userId]);
$unreadNotifs = (int) $nStmt->fetchColumn();

/* ---------- Helper: status label ---------- */
function status_label(string $status): string {
    $map = [
        'pending'     => t('status_pending', 'Pending'),
        'confirmed'   => t('status_confirmed', 'Confirmed'),
        'processing'  => t('status_processing', 'Processing'),
        'in_progress' => t('status_in_progress', 'In Progress'),
        'completed'   => t('status_completed', 'Completed'),
        'cancelled'   => t('status_cancelled', 'Cancelled'),
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
}
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <span class="current"><?= e(t('dashboard')) ?></span>
        </nav>

        <!-- Welcome header -->
        <div class="flex justify-between items-center" style="margin-bottom:var(--space-lg);flex-wrap:wrap;gap:var(--space);">
            <div>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:4px;">
                    <?= e(t('welcome_back_user', 'Welcome back,')) ?> <?= e($user['name']) ?> 👋
                </h1>
                <p class="text-muted"><?= e(t('dashboard_sub', 'Manage your orders and account settings here.')) ?></p>
            </div>
            <div class="flex items-center gap-sm">
                <?php if ($unreadNotifs > 0): ?>
                    <a href="<?= e(base_url('user/notifications.php')) ?>" class="btn btn-soft btn-sm" style="position:relative;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?= $unreadNotifs ?> <?= e(t('new', 'new')) ?>
                    </a>
                <?php endif; ?>
                <a href="<?= e(base_url('services.php')) ?>" class="btn btn-primary btn-sm">
                    <?= e(t('browse_services', 'Browse Services')) ?>
                </a>
            </div>
        </div>

        <!-- ============ STAT CARDS ============ -->
        <div class="grid grid-4" style="margin-bottom:var(--space-lg);">
            <?php
            $cards = [
                ['label' => t('total_orders', 'Total Orders'),    'value' => $stats['total'],     'color' => 'var(--primary)', 'icon' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>'],
                ['label' => t('pending', 'Pending'),              'value' => $stats['pending'],   'color' => 'var(--warning)', 'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
                ['label' => t('completed', 'Completed'),          'value' => $stats['completed'], 'color' => 'var(--success)', 'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
                ['label' => t('cancelled', 'Cancelled'),          'value' => $stats['cancelled'], 'color' => 'var(--danger)',  'icon' => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>'],
            ];
            foreach ($cards as $c): ?>
                <div class="card" style="padding:var(--space);">
                    <div class="flex items-center gap-sm" style="margin-bottom:10px;">
                        <span style="width:36px;height:36px;border-radius:var(--radius);background:var(--bg-alt);color:<?= e($c['color']) ?>;display:flex;align-items:center;justify-content:center;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $c['icon'] ?></svg>
                        </span>
                        <span class="text-muted" style="font-size:var(--fs-sm);"><?= e($c['label']) ?></span>
                    </div>
                    <div style="font-size:var(--fs-3xl);font-weight:800;color:var(--navy);line-height:1;">
                        <?= (int) $c['value'] ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="display:grid;grid-template-columns:1fr 300px;gap:var(--space-lg);align-items:start;">

            <!-- ============ RECENT ORDERS ============ -->
            <section class="card" style="padding:0;overflow:hidden;">
                <div class="flex justify-between items-center" style="padding:var(--space);border-bottom:1px solid var(--border);">
                    <h2 style="font-size:var(--fs-lg);"><?= e(t('recent_orders', 'Recent Orders')) ?></h2>
                    <a href="<?= e(base_url('user/orders.php')) ?>" style="font-size:var(--fs-sm);font-weight:600;">
                        <?= e(t('view_all', 'View All')) ?> →
                    </a>
                </div>

                <?php if (empty($recentOrders)): ?>
                    <div class="empty-state" style="padding:var(--space-xl) var(--space);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;color:var(--border-dark);margin:0 auto var(--space);">
                            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                        </svg>
                        <h3 style="font-size:var(--fs-md);"><?= e(t('no_orders', 'No orders yet')) ?></h3>
                        <p style="font-size:var(--fs-sm);"><?= e(t('no_orders_sub', 'Place your first order to get started.')) ?></p>
                        <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('services.php')) ?>"><?= e(t('browse_services', 'Browse Services')) ?></a>
                    </div>
                <?php else: ?>
                    <div class="table-wrap" style="border:none;border-radius:0;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= e(t('order_no', 'Order #')) ?></th>
                                    <th><?= e(t('date', 'Date')) ?></th>
                                    <th><?= e(t('total', 'Total')) ?></th>
                                    <th><?= e(t('status', 'Status')) ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $o): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= e(base_url('user/order-details.php?id=' . (int) $o['id'])) ?>"
                                               style="font-weight:600;color:var(--primary);">
                                                #<?= e($o['order_number']) ?>
                                            </a>
                                        </td>
                                        <td class="text-muted" style="font-size:var(--fs-xs);">
                                            <?= e(date('M d, Y', strtotime($o['created_at']))) ?>
                                        </td>
                                        <td style="font-weight:600;">
                                            <?= e(money((float) $o['total'], $o['currency'])) ?>
                                        </td>
                                        <td>
                                            <span class="status status-<?= e($o['order_status']) ?>">
                                                <?= e(status_label($o['order_status'])) ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="<?= e(base_url('user/order-details.php?id=' . (int) $o['id'])) ?>"
                                               class="btn btn-ghost btn-sm"><?= e(t('view', 'View')) ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ============ SIDEBAR ============ -->
            <aside>
                <!-- Profile card -->
                <div class="card" style="padding:var(--space);text-align:center;margin-bottom:var(--space);">
                    <span style="width:72px;height:72px;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;font-weight:800;font-size:var(--fs-2xl);margin-bottom:var(--space-sm);">
                        <?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
                    </span>
                    <h3 style="font-size:var(--fs-md);margin-bottom:2px;"><?= e($user['name']) ?></h3>
                    <p class="text-muted" style="font-size:var(--fs-sm);margin-bottom:var(--space);"><?= e($user['email']) ?></p>
                    <a href="<?= e(base_url('user/profile.php')) ?>" class="btn btn-ghost btn-sm btn-block">
                        <?= e(t('edit_profile', 'Edit Profile')) ?>
                    </a>
                </div>

                <!-- Quick links -->
                <div class="card" style="padding:var(--space);">
                    <h4 style="font-size:var(--fs-md);margin-bottom:var(--space);"><?= e(t('quick_links', 'Quick Links')) ?></h4>
                    <nav class="flex flex-col gap-xs">
                        <?php
                        $links = [
                            ['href' => 'user/index.php',         'label' => t('dashboard', 'Dashboard'),          'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'],
                            ['href' => 'user/orders.php',        'label' => t('my_orders', 'My Orders'),          'icon' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>'],
                            ['href' => 'user/notifications.php', 'label' => t('notifications', 'Notifications'),  'icon' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>', 'badge' => $unreadNotifs],
                            ['href' => 'user/profile.php',       'label' => t('profile', 'Profile'),              'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
                            ['href' => 'user/settings.php',      'label' => t('settings', 'Settings'),            'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
                            ['href' => 'logout.php',             'label' => t('logout', 'Logout'),                'icon' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>', 'color' => 'var(--danger)'],
                        ];
                        $currentPath = 'user/index.php';
                        foreach ($links as $l):
                            $isActive = ($l['href'] === $currentPath);
                        ?>
                            <a href="<?= e(base_url($l['href'])) ?>"
                               class="flex items-center gap-sm"
                               style="padding:9px 12px;border-radius:var(--radius);font-size:var(--fs-sm);<?= $isActive ? 'background:var(--primary-soft);color:var(--primary);font-weight:600;' : 'color:' . ($l['color'] ?? 'var(--text)') . ';' ?>transition:all var(--t-fast);">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $l['icon'] ?></svg>
                                <span style="flex:1;"><?= e($l['label']) ?></span>
                                <?php if (!empty($l['badge'])): ?>
                                    <span style="background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:999px;"><?= (int) $l['badge'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </aside>
        </div>
    </div>
</main>

<style>
@media (max-width: 900px) {
    main#main-content > .container > div[style*="grid-template-columns:1fr 300px"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>