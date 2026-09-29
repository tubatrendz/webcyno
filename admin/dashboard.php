<?php
/**
 * admin/dashboard.php — Admin Dashboard
 * Stats + recent orders + top services + quick actions
 */

$admin_page_title = 'Dashboard';
$admin_active     = 'dashboard';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Stats ---------- */
$stats = [
    'total_sales'    => 0,
    'total_orders'   => 0,
    'total_users'    => 0,
    'total_services' => 0,
    'pending_orders' => 0,
    'paid_orders'    => 0,
];

try {
    $row = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS total_sales,
            COUNT(*) AS total_orders,
            SUM(payment_status = 'paid') AS paid_orders,
            SUM(order_status = 'pending') AS pending_orders
        FROM orders
    ")->fetch();

    $stats['total_sales']    = (float) ($row['total_sales'] ?? 0);
    $stats['total_orders']   = (int)   ($row['total_orders'] ?? 0);
    $stats['paid_orders']    = (int)   ($row['paid_orders'] ?? 0);
    $stats['pending_orders'] = (int)   ($row['pending_orders'] ?? 0);

    $stats['total_users']    = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_services'] = (int) $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
} catch (Exception $e) { /* silent */ }

/* ---------- This month vs last month (sales trend) ---------- */
$thisMonth = 0; $lastMonth = 0; $trendPct = 0;
try {
    $m = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                              AND payment_status = 'paid' THEN total ELSE 0 END), 0) AS this_month,
            COALESCE(SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')
                              AND payment_status = 'paid' THEN total ELSE 0 END), 0) AS last_month
        FROM orders
    ")->fetch();

    $thisMonth = (float) ($m['this_month'] ?? 0);
    $lastMonth = (float) ($m['last_month'] ?? 0);
    if ($lastMonth > 0) {
        $trendPct = round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
    }
} catch (Exception $e) { /* silent */ }

/* ---------- Recent orders ---------- */
$recentOrders = [];
try {
    $recentOrders = $pdo->query("
        SELECT o.id, o.order_number, o.total, o.currency, o.order_status, o.payment_status,
               o.customer_name, o.created_at
        FROM orders o
        ORDER BY o.id DESC
        LIMIT 8
    ")->fetchAll();
} catch (Exception $e) { /* silent */ }

/* ---------- Top services (by sales) ---------- */
$topServices = [];
try {
    $topServices = $pdo->query("
        SELECT id, title_en, title_bn, thumbnail, price, discount_price, currency, total_sales, rating
        FROM services
        WHERE status = 'active'
        ORDER BY total_sales DESC, rating DESC
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) { /* silent */ }

/* ---------- Latest users ---------- */
$latestUsers = [];
try {
    $latestUsers = $pdo->query("
        SELECT id, name, email, avatar, created_at
        FROM users
        ORDER BY id DESC
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) { /* silent */ }

/* ---------- Sales chart data (last 7 days) ---------- */
$chartLabels = []; $chartData = [];
try {
    $chartRows = $pdo->query("
        SELECT DATE(created_at) AS d,
               COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS s
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY d ASC
    ")->fetchAll();

    $indexed = [];
    foreach ($chartRows as $r) { $indexed[$r['d']] = (float) $r['s']; }

    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $chartLabels[] = date('M d', strtotime($d));
        $chartData[]   = $indexed[$d] ?? 0;
    }
} catch (Exception $e) { /* silent */ }
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Dashboard</span>
        </nav>
        <h1 class="admin-page-title">Welcome, <?= e($currentAdmin['name']) ?> 👋</h1>
        <p class="admin-page-sub">Overview of your marketplace activity.</p>
    </div>
    <div class="flex gap-sm" style="flex-wrap:wrap;">
        <a href="<?= e(admin_url('services.php?action=new')) ?>" class="admin-btn admin-btn-primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Service
        </a>
        <a href="<?= e(admin_url('orders.php')) ?>" class="admin-btn admin-btn-ghost">
            View Orders
        </a>
    </div>
</div>

<!-- ============ STAT CARDS ============ -->
<div class="admin-stats">
    <div class="admin-stat">
        <div class="admin-stat-icon green">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <div class="admin-stat-body">
            <div class="admin-stat-label">Total Sales</div>
            <div class="admin-stat-value"><?= e(money($stats['total_sales'])) ?></div>
            <?php if ($trendPct != 0): ?>
                <div class="admin-stat-trend <?= $trendPct > 0 ? 'up' : 'down' ?>">
                    <?= $trendPct > 0 ? '↑' : '↓' ?>
                    <?= e(abs($trendPct)) ?>% this month
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-stat">
        <div class="admin-stat-icon blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
        </div>
        <div class="admin-stat-body">
            <div class="admin-stat-label">Total Orders</div>
            <div class="admin-stat-value"><?= number_format($stats['total_orders']) ?></div>
            <div class="admin-stat-trend">
                <?= (int) $stats['pending_orders'] ?> pending
            </div>
        </div>
    </div>

    <div class="admin-stat">
        <div class="admin-stat-icon orange">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="admin-stat-body">
            <div class="admin-stat-label">Total Users</div>
            <div class="admin-stat-value"><?= number_format($stats['total_users']) ?></div>
        </div>
    </div>

    <div class="admin-stat">
        <div class="admin-stat-icon purple">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
        </div>
        <div class="admin-stat-body">
            <div class="admin-stat-label">Total Services</div>
            <div class="admin-stat-value"><?= number_format($stats['total_services']) ?></div>
        </div>
    </div>
</div>

<!-- ============ CHART + TOP SERVICES ============ -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;" class="admin-dash-grid">

    <!-- Sales chart -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Sales Overview</h3>
            <span class="admin-cell-sub">Last 7 days</span>
        </div>
        <div style="position:relative;height:220px;">
            <canvas id="salesChart" style="width:100%;height:100%;"></canvas>
        </div>
    </div>

    <!-- Top services -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Top Services</h3>
            <a href="<?= e(admin_url('services.php')) ?>" class="admin-cell-sub" style="color:var(--primary);">View all →</a>
        </div>
        <?php if (empty($topServices)): ?>
            <div class="admin-empty" style="padding:20px;">
                <p>No services yet.</p>
            </div>
        <?php else: ?>
            <ul style="display:flex;flex-direction:column;gap:12px;list-style:none;padding:0;margin:0;">
                <?php foreach ($topServices as $s):
                    $title = $lang === 'bn' && !empty($s['title_bn']) ? $s['title_bn'] : $s['title_en'];
                    $thumb = $s['thumbnail'] ? base_url($s['thumbnail']) : base_url('assets/images/placeholder.png');
                    $price = (float) $s['price'];
                    if (!empty($s['discount_price']) && (float) $s['discount_price'] > 0) $price = (float) $s['discount_price'];
                ?>
                    <li class="flex items-center gap-sm">
                        <img src="<?= e($thumb) ?>" alt="" class="admin-thumb" style="width:40px;height:40px;">
                        <div style="flex:1;min-width:0;">
                            <div class="admin-cell-title" style="font-size:13px;margin-bottom:2px;"><?= e(str_limit($title, 34)) ?></div>
                            <div class="admin-cell-sub"><?= (int) $s['total_sales'] ?> sold • <?= e(money($price, $s['currency'])) ?></div>
                        </div>
                        <span class="tag tag-primary" style="font-size:10px;">★ <?= number_format((float) $s['rating'], 1) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- ============ RECENT ORDERS + LATEST USERS ============ -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;" class="admin-dash-grid">

    <!-- Recent orders -->
    <div class="admin-card" style="padding:0;">
        <div class="admin-card-header" style="padding:16px 20px;margin:0;">
            <h3 class="admin-card-title">Recent Orders</h3>
            <a href="<?= e(admin_url('orders.php')) ?>" class="admin-cell-sub" style="color:var(--primary);">View all →</a>
        </div>

        <?php if (empty($recentOrders)): ?>
            <div class="admin-empty" style="padding:40px 20px;">
                <h3>No orders yet</h3>
                <p>Orders will appear here once customers start buying.</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap" style="border:none;border-radius:0;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $o):
                            $statusClass = match($o['order_status']) {
                                'completed'   => 'status-completed',
                                'cancelled'   => 'status-cancelled',
                                'pending'     => 'status-pending',
                                'processing', 'confirmed' => 'status-processing',
                                'in_progress' => 'status-in_progress',
                                default       => 'status-pending',
                            };
                        ?>
                            <tr>
                                <td><strong>#<?= e($o['order_number']) ?></strong></td>
                                <td><?= e(str_limit($o['customer_name'], 22)) ?></td>
                                <td><strong><?= e(money((float) $o['total'], $o['currency'])) ?></strong></td>
                                <td>
                                    <span class="status <?= e($statusClass) ?>" style="font-size:11px;">
                                        <?= e(ucfirst(str_replace('_', ' ', $o['order_status']))) ?>
                                    </span>
                                </td>
                                <td class="admin-cell-sub"><?= e(date('M d, Y', strtotime($o['created_at']))) ?></td>
                                <td style="text-align:right;">
                                    <a href="<?= e(admin_url('order-details.php?id=' . (int) $o['id'])) ?>"
                                       class="admin-btn admin-btn-ghost admin-btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Latest users -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">New Users</h3>
            <a href="<?= e(admin_url('users.php')) ?>" class="admin-cell-sub" style="color:var(--primary);">View all →</a>
        </div>
        <?php if (empty($latestUsers)): ?>
            <div class="admin-empty" style="padding:20px;">
                <p>No users yet.</p>
            </div>
        <?php else: ?>
            <ul style="display:flex;flex-direction:column;gap:12px;list-style:none;padding:0;margin:0;">
                <?php foreach ($latestUsers as $u): ?>
                    <li class="flex items-center gap-sm">
                        <span class="admin-avatar" style="width:36px;height:36px;font-size:14px;">
                            <?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?>
                        </span>
                        <div style="flex:1;min-width:0;">
                            <div class="admin-cell-title" style="font-size:13px;margin-bottom:2px;"><?= e(str_limit($u['name'], 26)) ?></div>
                            <div class="admin-cell-sub"><?= e(str_limit($u['email'], 30)) ?></div>
                        </div>
                        <a href="<?= e(admin_url('users.php?id=' . (int) $u['id'])) ?>"
                           class="admin-btn admin-btn-ghost admin-btn-icon" title="View">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<style>
@media (max-width: 900px) {
    .admin-dash-grid { grid-template-columns: 1fr !important; }
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var ctx = document.getElementById('salesChart');
    if (!ctx || typeof Chart === 'undefined') return;

    var labels = <?= json_encode($chartLabels) ?>;
    var data   = <?= json_encode($chartData) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Sales',
                data: data,
                borderColor: '#2563EB',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#2563EB',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0F172A',
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function (ctx) {
                            return '<?= e(setting('currency_symbol_bdt', '৳')) ?>' + Number(ctx.parsed.y).toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748B', font: { size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#E2E8F0', drawBorder: false },
                    ticks: {
                        color: '#64748B',
                        font: { size: 11 },
                        callback: function (v) { return '<?= e(setting('currency_symbol_bdt', '৳')) ?>' + v; }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>