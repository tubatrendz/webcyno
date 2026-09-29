<?php
/**
 * admin/admins.php — Admin Users List
 */
$admin_page_title = 'Admin Users';
$admin_active     = 'admins';
require_once __DIR__ . '/includes/admin-header.php';

// Only super admins can view
if (($currentAdmin['role'] ?? '') !== 'super') {
    echo '<div class="admin-card"><div class="admin-empty"><h3>Access Denied</h3><p>Only super admins can access this page.</p></div></div>';
    require_once __DIR__ . '/includes/admin-footer.php';
    exit;
}

$admins = $pdo->query("
    SELECT id, name, email, role, status, last_login, created_at
    FROM admins
    ORDER BY id ASC
")->fetchAll();
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Admin Users</span>
        </nav>
        <h1 class="admin-page-title">Admin Users</h1>
        <p class="admin-page-sub"><?= count($admins) ?> admin user(s)</p>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">All Admins</h3>
    </div>

    <p class="admin-cell-sub" style="margin-bottom:16px;">
        ℹ️ নতুন admin যোগ করতে cPanel → phpMyAdmin → <code>admins</code> table-এ manual insert করুন।
    </p>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Admin</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-sm">
                                <span class="admin-avatar" style="width:36px;height:36px;font-size:14px;">
                                    <?= e(mb_strtoupper(mb_substr($a['name'], 0, 1))) ?>
                                </span>
                                <div>
                                    <div class="admin-cell-title" style="font-size:13.5px;"><?= e($a['name']) ?></div>
                                    <div class="admin-cell-sub">#<?= (int) $a['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="admin-cell-sub"><?= e($a['email']) ?></td>
                        <td>
                            <span class="tag <?= $a['role'] === 'super' ? 'tag-primary' : 'tag-info' ?>" style="text-transform:capitalize;">
                                <?= e($a['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ((int) $a['status'] === 1): ?>
                                <span class="tag tag-success">Active</span>
                            <?php else: ?>
                                <span class="tag tag-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="admin-cell-sub">
                            <?= $a['last_login'] ? e(date('M d, Y g:i A', strtotime($a['last_login']))) : 'Never' ?>
                        </td>
                        <td class="admin-cell-sub"><?= e(date('M d, Y', strtotime($a['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>