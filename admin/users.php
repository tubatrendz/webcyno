<?php
/**
 * admin/users.php — Users Listing + Filters + Detail Modal + Status Update
 * Backend: api/admin/user-save.php
 */

$admin_page_title = 'Users';
$admin_active     = 'users';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Filters ---------- */
$q      = trim((string) input('q', ''));
$status = trim((string) input('status', ''));
$verify = trim((string) input('verify', ''));
$page   = max(1, (int) input('page', 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$allowedStatus = ['active', 'inactive', 'banned'];

/* ---------- Where ---------- */
$where  = ["1=1"];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    array_push($params, $like, $like, $like);
}
if ($status !== '' && in_array($status, $allowedStatus, true)) {
    $where[]  = "u.status = ?";
    $params[] = $status;
}
if ($verify === 'email') {
    $where[] = "u.email_verified = 0";
} elseif ($verify === 'phone') {
    $where[] = "u.phone_verified = 0";
} elseif ($verify === 'verified') {
    $where[] = "u.email_verified = 1";
}
$whereSql = implode(' AND ', $where);

/* ---------- Counts (tabs) ---------- */
$tabCounts = ['all' => 0, 'active' => 0, 'inactive' => 0, 'banned' => 0, 'unverified' => 0];
try {
    $row = $pdo->query("
        SELECT
            COUNT(*) AS all_c,
            SUM(status = 'active') AS active_c,
            SUM(status = 'inactive') AS inactive_c,
            SUM(status = 'banned') AS banned_c,
            SUM(email_verified = 0) AS unverified_c
        FROM users
    ")->fetch();
    $tabCounts['all']        = (int) ($row['all_c'] ?? 0);
    $tabCounts['active']     = (int) ($row['active_c'] ?? 0);
    $tabCounts['inactive']   = (int) ($row['inactive_c'] ?? 0);
    $tabCounts['banned']     = (int) ($row['banned_c'] ?? 0);
    $tabCounts['unverified'] = (int) ($row['unverified_c'] ?? 0);
} catch (Exception $e) { /* silent */ }

/* ---------- Count filtered ---------- */
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
$cStmt->execute($params);
$total = (int) $cStmt->fetchColumn();

/* ---------- Fetch users ---------- */
$listStmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.phone, u.avatar, u.status,
           u.email_verified, u.phone_verified, u.language, u.currency,
           u.created_at, u.last_login,
           (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
           (SELECT COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN o.total ELSE 0 END),0)
              FROM orders o WHERE o.user_id = u.id) AS total_spent
    FROM users u
    WHERE $whereSql
    ORDER BY u.id DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$users = $listStmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

/* ---------- URL helper ---------- */
function usr_url(array $override = []): string {
    $p = array_merge($_GET, $override);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return admin_url('users.php') . ($p ? '?' . http_build_query($p) : '');
}

/* ---------- Helpers ---------- */
function user_status_class(string $s): string {
    return match($s) {
        'active'   => 'tag-success',
        'inactive' => 'tag-warning',
        'banned'   => 'tag-danger',
        default    => 'tag',
    };
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Users</span>
        </nav>
        <h1 class="admin-page-title">Users</h1>
        <p class="admin-page-sub"><?= number_format($total) ?> user(s) found</p>
    </div>
</div>

<!-- ============ STATUS TABS ============ -->
<div class="admin-tabs" style="margin-bottom:16px;">
    <?php
    $tabs = [
        'all'         => ['label' => 'All',         'count' => $tabCounts['all'],        'filter' => null],
        'active'      => ['label' => 'Active',      'count' => $tabCounts['active'],     'filter' => 'active'],
        'inactive'    => ['label' => 'Inactive',    'count' => $tabCounts['inactive'],   'filter' => 'inactive'],
        'banned'      => ['label' => 'Banned',      'count' => $tabCounts['banned'],     'filter' => 'banned'],
        'unverified'  => ['label' => 'Unverified',  'count' => $tabCounts['unverified'], 'filter' => '_unverified'],
    ];
    foreach ($tabs as $key => $tab):
        if ($tab['filter'] === '_unverified') {
            $href = usr_url(['status' => null, 'verify' => 'email', 'page' => null]);
            $isActive = ($verify === 'email');
        } else {
            $href = usr_url(['status' => $tab['filter'], 'verify' => null, 'page' => null]);
            $isActive = ($tab['filter'] === null && $status === '' && $verify === '')
                     || ($tab['filter'] !== null && $status === $tab['filter']);
        }
    ?>
        <a href="<?= e($href) ?>" class="admin-tab<?= $isActive ? ' active' : '' ?>">
            <?= e($tab['label']) ?>
            <span style="opacity:.7;font-size:11px;">(<?= (int) $tab['count'] ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<!-- ============ FILTER ============ -->
<form class="admin-filter-bar" method="get" action="<?= e(admin_url('users.php')) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" class="form-control"
           placeholder="Name, email, phone..." style="min-width:240px;">

    <select name="status" data-filter-submit>
        <option value="">All Status</option>
        <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        <option value="banned"   <?= $status === 'banned'   ? 'selected' : '' ?>>Banned</option>
    </select>

    <select name="verify" data-filter-submit>
        <option value="">All Verification</option>
        <option value="verified" <?= $verify === 'verified' ? 'selected' : '' ?>>Email Verified</option>
        <option value="email"    <?= $verify === 'email'    ? 'selected' : '' ?>>Email Unverified</option>
        <option value="phone"    <?= $verify === 'phone'    ? 'selected' : '' ?>>Phone Unverified</option>
    </select>

    <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Apply</button>
    <a href="<?= e(admin_url('users.php')) ?>" class="admin-btn admin-btn-ghost admin-btn-sm">Reset</a>
</form>

<!-- ============ TABLE ============ -->
<?php if (empty($users)): ?>
    <div class="admin-card">
        <div class="admin-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <h3>No users found</h3>
            <p>Try adjusting the filters.</p>
        </div>
    </div>
<?php else: ?>
    <div class="admin-table-wrap">
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Orders</th>
                        <th>Spent</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-sm">
                                    <?php if (!empty($u['avatar'])): ?>
                                        <img src="<?= e(base_url($u['avatar'])) ?>" alt="" class="admin-avatar" style="object-fit:cover;">
                                    <?php else: ?>
                                        <span class="admin-avatar"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
                                    <?php endif; ?>
                                    <div>
                                        <div class="admin-cell-title"><?= e(str_limit($u['name'], 30)) ?></div>
                                        <div class="admin-cell-sub">
                                            #<?= (int) $u['id'] ?>
                                            <?php if ((int) $u['email_verified'] === 1): ?>
                                                <span style="color:var(--success);">✓ email</span>
                                            <?php endif; ?>
                                            <?php if ((int) $u['phone_verified'] === 1): ?>
                                                <span style="color:var(--success);">✓ phone</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="admin-cell-sub" style="color:var(--text);word-break:break-all;font-size:12.5px;"><?= e(str_limit($u['email'], 34)) ?></div>
                                <?php if (!empty($u['phone'])): ?>
                                    <div class="admin-cell-sub"><?= e($u['phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= (int) $u['order_count'] ?></strong></td>
                            <td><strong><?= e(money((float) $u['total_spent'])) ?></strong></td>
                            <td>
                                <span class="tag <?= e(user_status_class($u['status'])) ?>" style="text-transform:capitalize;">
                                    <?= e($u['status']) ?>
                                </span>
                            </td>
                            <td class="admin-cell-sub"><?= e(date('M d, Y', strtotime($u['created_at']))) ?></td>
                            <td>
                                <div class="cell-actions">
                                    <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon"
                                            data-view-user='<?= e(json_encode([
                                                "id"             => (int) $u["id"],
                                                "name"           => $u["name"],
                                                "email"          => $u["email"],
                                                "phone"          => $u["phone"],
                                                "avatar"         => $u["avatar"],
                                                "status"         => $u["status"],
                                                "email_verified" => (int) $u["email_verified"],
                                                "phone_verified" => (int) $u["phone_verified"],
                                                "language"       => $u["language"],
                                                "currency"       => $u["currency"],
                                                "order_count"    => (int) $u["order_count"],
                                                "total_spent"    => (float) $u["total_spent"],
                                                "created_at"     => $u["created_at"],
                                                "last_login"     => $u["last_login"],
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
                                            title="View">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <a href="<?= e(admin_url('orders.php?q=' . urlencode($u['email']))) ?>"
                                       class="admin-btn admin-btn-ghost admin-btn-icon" title="View Orders">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
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
            <?php if ($page > 1): ?><a href="<?= e(usr_url(['page' => $page - 1])) ?>">←</a><?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= e(usr_url(['page' => $i])) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(usr_url(['page' => $page + 1])) ?>">→</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<!-- ============ USER DETAIL MODAL ============ -->
<div id="user-modal" class="admin-modal">
    <div class="admin-modal-backdrop" data-modal-close></div>
    <div class="admin-modal-card" style="max-width:680px;">
        <div class="admin-modal-header">
            <h3 class="admin-card-title" id="user-modal-title">User Details</h3>
            <button type="button" class="admin-btn admin-btn-ghost admin-btn-icon" data-modal-close aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div style="padding:20px;max-height:70vh;overflow-y:auto;">

            <!-- Profile header -->
            <div class="flex items-center gap-md" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border);">
                <span class="admin-avatar" id="u-avatar" style="width:64px;height:64px;font-size:22px;">U</span>
                <div style="flex:1;min-width:0;">
                    <div id="u-name" style="font-size:18px;font-weight:700;color:var(--navy);margin-bottom:4px;">—</div>
                    <div id="u-email" class="admin-cell-sub" style="word-break:break-all;">—</div>
                </div>
            </div>

            <!-- Info grid -->
            <div class="admin-form-row">
                <div>
                    <div class="admin-label">Phone</div>
                    <div id="u-phone" style="font-size:14px;">—</div>
                </div>
                <div>
                    <div class="admin-label">Language</div>
                    <div id="u-language" style="font-size:14px;">—</div>
                </div>
                <div>
                    <div class="admin-label">Currency</div>
                    <div id="u-currency" style="font-size:14px;">—</div>
                </div>
            </div>

            <div class="admin-form-row">
                <div>
                    <div class="admin-label">Orders</div>
                    <div id="u-orders" style="font-size:14px;font-weight:700;">0</div>
                </div>
                <div>
                    <div class="admin-label">Total Spent</div>
                    <div id="u-spent" style="font-size:14px;font-weight:700;color:var(--primary);">—</div>
                </div>
                <div>
                    <div class="admin-label">Joined</div>
                    <div id="u-joined" style="font-size:14px;">—</div>
                </div>
            </div>

            <div class="admin-form-row">
                <div>
                    <div class="admin-label">Email Verified</div>
                    <div id="u-email-verified">—</div>
                </div>
                <div>
                    <div class="admin-label">Phone Verified</div>
                    <div id="u-phone-verified">—</div>
                </div>
                <div>
                    <div class="admin-label">Last Login</div>
                    <div id="u-last-login" style="font-size:14px;">—</div>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

            <!-- Status update -->
            <form data-ajax data-endpoint="admin/user-save" data-method="POST">
                <input type="hidden" name="id" id="u-id-input" value="0">
                <input type="hidden" name="name" id="u-name-input" value="">
                <input type="hidden" name="email" id="u-email-input" value="">
                <input type="hidden" name="phone" id="u-phone-input" value="">

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-label">Status</label>
                        <select name="status" id="u-status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="banned">Banned</option>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Email Verified</label>
                        <select name="email_verified" id="u-email-verified-input" class="form-control">
                            <option value="0">Unverified</option>
                            <option value="1">Verified</option>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Phone Verified</label>
                        <select name="phone_verified" id="u-phone-verified-input" class="form-control">
                            <option value="0">Unverified</option>
                            <option value="1">Verified</option>
                        </select>
                    </div>
                </div>

                <div class="admin-modal-footer">
                    <a href="#" id="u-orders-link" class="admin-btn admin-btn-ghost">View Orders</a>
                    <button type="submit" class="admin-btn admin-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('user-modal');
    if (!modal) return;

    var BASE = window.WCB_CONFIG.baseUrl || '';
    var CUR  = '<?= e(setting('currency_symbol_bdt', '৳')) ?>';

    function openModal(u) {
        document.getElementById('u-avatar').textContent = (u.name || 'U').charAt(0).toUpperCase();
        document.getElementById('u-name').textContent   = u.name || '—';
        document.getElementById('u-email').textContent  = u.email || '—';
        document.getElementById('u-phone').textContent  = u.phone || '—';
        document.getElementById('u-language').textContent = u.language || '—';
        document.getElementById('u-currency').textContent = u.currency || '—';
        document.getElementById('u-orders').textContent = u.order_count || 0;
        document.getElementById('u-spent').textContent  = CUR + Number(u.total_spent || 0).toLocaleString();
        document.getElementById('u-joined').textContent = u.created_at ? new Date(u.created_at).toDateString() : '—';
        document.getElementById('u-last-login').textContent = u.last_login ? new Date(u.last_login).toDateString() : 'Never';

        document.getElementById('u-email-verified').innerHTML = u.email_verified
            ? '<span class="tag tag-success">Verified</span>'
            : '<span class="tag tag-warning">Unverified</span>';
        document.getElementById('u-phone-verified').innerHTML = u.phone_verified
            ? '<span class="tag tag-success">Verified</span>'
            : '<span class="tag tag-warning">Unverified</span>';

        // form prefill
        document.getElementById('u-id-input').value          = u.id;
        document.getElementById('u-name-input').value        = u.name || '';
        document.getElementById('u-email-input').value       = u.email || '';
        document.getElementById('u-phone-input').value       = u.phone || '';
        document.getElementById('u-status').value            = u.status || 'active';
        document.getElementById('u-email-verified-input').value = u.email_verified ? '1' : '0';
        document.getElementById('u-phone-verified-input').value = u.phone_verified ? '1' : '0';

        document.getElementById('u-orders-link').href = BASE + '/admin/orders.php?q=' + encodeURIComponent(u.email || '');

        modal.classList.add('open');
    }

    function closeModal() { modal.classList.remove('open'); }

    document.querySelectorAll('[data-view-user]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { openModal(JSON.parse(btn.getAttribute('data-view-user'))); }
            catch (e) { console.error(e); }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>