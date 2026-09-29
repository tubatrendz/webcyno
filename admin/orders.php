<?php
/**
 * admin/orders.php — Orders Listing
 * Filters: status, payment_status, q, date range
 * Features: status update inline, detail link, CSV export hint
 */

$admin_page_title = 'Orders';
$admin_active     = 'orders';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Filters ---------- */
$q          = trim((string) input('q', ''));
$status     = trim((string) input('status', ''));
$payStatus  = trim((string) input('payment_status', ''));
$dateFrom   = trim((string) input('date_from', ''));
$dateTo     = trim((string) input('date_to', ''));
$page       = max(1, (int) input('page', 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

$allowedStatus    = ['pending','confirmed','processing','in_progress','completed','cancelled'];
$allowedPayStatus = ['pending','paid','failed','refunded'];

/* ---------- Where ---------- */
$where  = ["1=1"];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ?)";
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '' && in_array($status, $allowedStatus, true)) {
    $where[]  = "o.order_status = ?";
    $params[] = $status;
}
if ($payStatus !== '' && in_array($payStatus, $allowedPayStatus, true)) {
    $where[]  = "o.payment_status = ?";
    $params[] = $payStatus;
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[]  = "DATE(o.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[]  = "DATE(o.created_at) <= ?";
    $params[] = $dateTo;
}
$whereSql = implode(' AND ', $where);

/* ---------- Counts per status (tabs) ---------- */
$tabCounts = ['all' => 0, 'pending' => 0, 'processing' => 0, 'completed' => 0, 'cancelled' => 0];
try {
    $row = $pdo->query("
        SELECT
            COUNT(*) AS all_c,
            SUM(order_status = 'pending') AS pending_c,
            SUM(order_status IN ('processing','confirmed','in_progress')) AS processing_c,
            SUM(order_status = 'completed') AS completed_c,
            SUM(order_status = 'cancelled') AS cancelled_c
        FROM orders
    ")->fetch();
    $tabCounts['all']        = (int) ($row['all_c']        ?? 0);
    $tabCounts['pending']    = (int) ($row['pending_c']    ?? 0);
    $tabCounts['processing'] = (int) ($row['processing_c'] ?? 0);
    $tabCounts['completed']  = (int) ($row['completed_c']  ?? 0);
    $tabCounts['cancelled']  = (int) ($row['cancelled_c']  ?? 0);
} catch (Exception $e) { /* silent */ }

/* ---------- Total filtered ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch orders ---------- */
$listStmt = $pdo->prepare("
    SELECT o.id, o.order_number, o.customer_name, o.customer_email, o.customer_phone,
           o.total, o.currency, o.payment_method, o.payment_status, o.order_status,
           o.created_at,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM orders o
    WHERE $whereSql
    ORDER BY o.id DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$orders = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- URL helper ---------- */
function ord_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('orders.php') . ($p ? '?' . http_build_query($p) : '');
}

/* ---------- Status label/class helpers ---------- */
function ord_status_class(string $s): string {
    return match($s) {
        'pending'     => 'status-pending',
        'confirmed'   => 'status-confirmed',
        'processing'  => 'status-processing',
        'in_progress' => 'status-in_progress',
        'completed'   => 'status-completed',
        'cancelled'   => 'status-cancelled',
        default       => 'status-pending',
    };
}
function pay_status_class(string $s): string {
    return match($s) {
        'paid'     => 'status-completed',
        'pending'  => 'status-pending',
        'failed'   => 'status-cancelled',
        'refunded' => 'status-processing',
        default    => 'status-pending',
    };
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Orders</span>
        </nav>
        <h1 class="admin-page-title">Orders</h1>
        <p class="admin-page-sub"><?= number_format($total) ?> order(s) matching filters</p>
    </div>
</div>

<!-- ============ STATUS TABS ============ -->
<div class="admin-tabs" style="margin-bottom:16px;">
    <?php
    $tabs = [
        'all'        => ['label' => 'All',        'count' => $tabCounts['all']],
        'pending'    => ['label' => 'Pending',    'count' => $tabCounts['pending'],    'filter' => 'pending'],
        'processing' => ['label' => 'Processing', 'count' => $tabCounts['processing'], 'filter' => 'processing'],
        'completed'  => ['label' => 'Completed',  'count' => $tabCounts['completed'],  'filter' => 'completed'],
        'cancelled'  => ['label' => 'Cancelled',  'count' => $tabCounts['cancelled'],  'filter' => 'cancelled'],
    ];
    $currentFilter = $status;
    foreach ($tabs as $key => $tab):
        $href = ord_url(['status' => $tab['filter'] ?? null, 'page' => null]);
        $isActive = ($key === 'all' && $currentFilter === '') || (isset($tab['filter']) && $currentFilter === $tab['filter']);
    ?>
        <a href="<?= e($href) ?>" class="admin-tab<?= $isActive ? ' active' : '' ?>">
            <?= e($tab['label']) ?>
            <span style="opacity:.7;font-size:11px;">(<?= (int) $tab['count'] ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<!-- ============ FILTER ============ -->
<form class="admin-filter-bar" method="get" action="<?= e(admin_url('orders.php')) ?>">
    <?php if ($status): ?>
        <input type="hidden" name="status" value="<?= e($status) ?>">
    <?php endif; ?>

    <input type="search" name="q" value="<?= e($q) ?>" class="form-control"
           placeholder="Order #, name, email, phone..." style="min-width:240px;">

    <select name="payment_status" data-filter-submit>
        <option value="">All Payments</option>
        <option value="pending"  <?= $payStatus === 'pending'  ? 'selected' : '' ?>>Pending</option>
        <option value="paid"     <?= $payStatus === 'paid'     ? 'selected' : '' ?>>Paid</option>
        <option value="failed"   <?= $payStatus === 'failed'   ? 'selected' : '' ?>>Failed</option>
        <option value="refunded" <?= $payStatus === 'refunded' ? 'selected' : '' ?>>Refunded</option>
    </select>

    <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control" style="max-width:160px;" title="From">
    <input type="date" name="date_to"   value="<?= e($dateTo) ?>"   class="form-control" style="max-width:160px;" title="To">

    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Apply</button>
    <a href="<?= e(admin_url('orders.php')) ?>" class="admin-btn admin-btn-ghost admin-btn-sm">Reset</a>
</form>

<!-- ============ TABLE ============ -->
<?php if (empty($orders)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
            <h3>No orders found</h3>
            <p>Try adjusting the filters or search query.</p>
        </div>
    </div>
<?php else: ?>
    <div class="admin-table-wrap">
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td>
                                <a href="<?= e(admin_url('order-details.php?id=' . (int) $o['id'])) ?>"
                                   style="font-weight:700;color:var(--primary);">
                                    #<?= e($o['order_number']) ?>
                                </a>
                            </td>
                            <td>
                                <div class="admin-cell-title"><?= e(str_limit($o['customer_name'], 30)) ?></div>
                                <div class="admin-cell-sub"><?= e(str_limit($o['customer_email'], 30)) ?></div>
                            </td>
                            <td class="admin-cell-sub"><?= (int) $o['item_count'] ?> item(s)</td>
                            <td><strong><?= e(money((float) $o['total'], $o['currency'])) ?></strong></td>
                            <td>
                                <span class="status <?= e(pay_status_class($o['payment_status'])) ?>" style="font-size:11px;">
                                    <?= e(ucfirst($o['payment_status'])) ?>
                                </span>
                                <div class="admin-cell-sub" style="margin-top:2px;"><?= e($o['payment_method']) ?></div>
                            </td>
                            <td>
                                <span class="status <?= e(ord_status_class($o['order_status'])) ?>" style="font-size:11px;">
                                    <?= e(ucfirst(str_replace('_', ' ', $o['order_status']))) ?>
                                </span>
                            </td>
                            <td class="admin-cell-sub"><?= e(date('M d, Y', strtotime($o['created_at']))) ?></td>
                            <td>
                                <div class="cell-actions">
                                    <a href="<?= e(admin_url('order-details.php?id=' . (int) $o['id'])) ?>"
                                       class="admin-btn admin-btn-ghost admin-btn-icon" title="View">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
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
            <?php if ($page > 1): ?><a href="<?= e(ord_url(['page' => $page - 1])) ?>">←</a><?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= e(ord_url(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(ord_url(['page' => $page + 1])) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>