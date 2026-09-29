<?php
/**
 * user/settings.php — Account Settings
 * Password change, notification preferences, danger zone
 */

require_once __DIR__ . '/../includes/header.php';

/* ---------- Auth guard ---------- */
if (empty($_SESSION['user_id'])) {
    header('Location: ' . base_url('login.php?redirect=' . urlencode('user/settings.php')));
    exit;
}
$user = current_user();
if (!$user) { header('Location: ' . base_url('logout.php')); exit; }

$userId = (int) $user['id'];
global $pdo;

$errors  = [];
$success = '';
$action  = input('action', '');

/* ========================================================
   ACTION: Change password
   ======================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'password') {
    if (!verify_csrf(input('csrf_token'))) {
        $errors['_general'] = t('csrf_invalid', 'Invalid security token.');
    } else {
        $current = (string) input('current_password', '');
        $new     = (string) input('new_password', '');
        $confirm = (string) input('confirm_password', '');

        // Verify current
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors['current_password'] = t('wrong_password', 'Current password is incorrect.');
        } elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $errors['new_password'] = t('weak_password', 'Min 8 chars, letters and numbers.');
        } elseif ($new !== $confirm) {
            $errors['confirm_password'] = t('password_mismatch', 'Passwords do not match.');
        } else {
            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ?, remember_token = NULL WHERE id = ?")
                ->execute([$newHash, $userId]);
            $success = t('password_updated', 'Password updated successfully.');
            log_activity('user_password_change', 'users', $userId, 'User changed own password');
        }
    }
}

/* ========================================================
   ACTION: Notification prefs (simple JSON in session for now)
   ======================================================== */
$notifPrefs = $_SESSION['notif_prefs'] ?? ['email' => 1, 'sms' => 0, 'promo' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'notifications') {
    if (!verify_csrf(input('csrf_token'))) {
        $errors['_general'] = t('csrf_invalid', 'Invalid security token.');
    } else {
        $notifPrefs = [
            'email' => input('email') ? 1 : 0,
            'sms'   => input('sms')   ? 1 : 0,
            'promo' => input('promo') ? 1 : 0,
        ];
        $_SESSION['notif_prefs'] = $notifPrefs;
        $success = t('prefs_saved', 'Notification preferences saved.');
    }
}
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <a href="<?= e(base_url('user/index.php')) ?>"><?= e(t('dashboard')) ?></a>
            <span class="sep">/</span>
            <span class="current"><?= e(t('settings', 'Settings')) ?></span>
        </nav>

        <div class="section-header-row" style="margin-bottom:var(--space-lg);">
            <div>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:4px;"><?= e(t('account_settings', 'Account Settings')) ?></h1>
                <p class="text-muted"><?= e(t('settings_sub', 'Manage your password, notifications and account.')) ?></p>
            </div>
            <a href="<?= e(base_url('user/profile.php')) ?>" class="btn btn-ghost btn-sm">
                ← <?= e(t('edit_profile', 'Edit Profile')) ?>
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['_general'])): ?>
            <div class="alert alert-danger">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= e($errors['_general']) ?></span>
            </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 300px;gap:var(--space-lg);align-items:start;">

            <!-- ============ MAIN ============ -->
            <div>

                <!-- Change password -->
                <section class="card" style="padding:var(--space-lg);margin-bottom:var(--space-lg);">
                    <h3 style="font-size:var(--fs-lg);margin-bottom:var(--space);">
                        <span class="flex items-center gap-sm">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <?= e(t('change_password', 'Change Password')) ?>
                        </span>
                    </h3>
                    <p class="text-muted" style="font-size:var(--fs-sm);margin-bottom:var(--space);">
                        <?= e(t('change_password_sub', 'পাসওয়ার্ড পরিবর্তনের পর সব ডিভাইস থেকে লগআউট হয়ে যাবে।')) ?>
                    </p>

                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="password">

                        <div class="form-group">
                            <label class="form-label" for="current_password"><?= e(t('current_password', 'Current Password')) ?> <span class="required">*</span></label>
                            <div style="position:relative;">
                                <input type="password" class="form-control" id="current_password" name="current_password"
                                       autocomplete="current-password" required style="padding-right:44px;">
                                <button type="button" data-toggle-password="#current_password" aria-label="Show"
                                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:transparent;border:none;cursor:pointer;color:var(--text-muted);padding:6px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <?php if (!empty($errors['current_password'])): ?><p class="form-error"><?= e($errors['current_password']) ?></p><?php endif; ?>
                        </div>

                        <div class="grid grid-2" style="gap:var(--space);">
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="new_password"><?= e(t('new_password', 'New Password')) ?> <span class="required">*</span></label>
                                <input type="password" class="form-control" id="new_password" name="new_password"
                                       autocomplete="new-password" required minlength="8">
                                <?php if (!empty($errors['new_password'])): ?><p class="form-error"><?= e($errors['new_password']) ?></p><?php endif; ?>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="confirm_password"><?= e(t('confirm_new_password', 'Confirm New Password')) ?> <span class="required">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                       autocomplete="new-password" required>
                                <?php if (!empty($errors['confirm_password'])): ?><p class="form-error"><?= e($errors['confirm_password']) ?></p><?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top:var(--space);">
                            <?= e(t('update_password', 'Update Password')) ?>
                        </button>
                    </form>
                </section>

                <!-- Notification preferences -->
                <section class="card" style="padding:var(--space-lg);margin-bottom:var(--space-lg);">
                    <h3 style="font-size:var(--fs-lg);margin-bottom:var(--space);">
                        <span class="flex items-center gap-sm">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <?= e(t('notifications', 'Notifications')) ?>
                        </span>
                    </h3>

                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="notifications">

                        <div class="flex flex-col gap-sm">
                            <label class="form-check">
                                <input type="checkbox" name="email" value="1" <?= !empty($notifPrefs['email']) ? 'checked' : '' ?>>
                                <span>
                                    <strong style="font-size:var(--fs-sm);"><?= e(t('notif_email', 'Email notifications')) ?></strong>
                                    <span class="text-muted" style="display:block;font-size:var(--fs-xs);"><?= e(t('notif_email_sub', 'Order updates, invoices, and account alerts.')) ?></span>
                                </span>
                            </label>

                            <label class="form-check">
                                <input type="checkbox" name="sms" value="1" <?= !empty($notifPrefs['sms']) ? 'checked' : '' ?>>
                                <span>
                                    <strong style="font-size:var(--fs-sm);"><?= e(t('notif_sms', 'SMS notifications')) ?></strong>
                                    <span class="text-muted" style="display:block;font-size:var(--fs-xs);"><?= e(t('notif_sms_sub', 'OTP and order confirmation via SMS.')) ?></span>
                                </span>
                            </label>

                            <label class="form-check">
                                <input type="checkbox" name="promo" value="1" <?= !empty($notifPrefs['promo']) ? 'checked' : '' ?>>
                                <span>
                                    <strong style="font-size:var(--fs-sm);"><?= e(t('notif_promo', 'Promotional emails')) ?></strong>
                                    <span class="text-muted" style="display:block;font-size:var(--fs-xs);"><?= e(t('notif_promo_sub', 'Offers, discounts and new services.')) ?></span>
                                </span>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top:var(--space);">
                            <?= e(t('save_prefs', 'Save Preferences')) ?>
                        </button>
                    </form>
                </section>

                <!-- Danger zone -->
                <section class="card" style="padding:var(--space-lg);border-color:var(--danger-soft);">
                    <h3 style="font-size:var(--fs-lg);margin-bottom:8px;color:var(--danger);">
                        <span class="flex items-center gap-sm">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <?= e(t('danger_zone', 'Danger Zone')) ?>
                        </span>
                    </h3>
                    <p class="text-muted" style="font-size:var(--fs-sm);margin-bottom:var(--space);">
                        <?= e(t('danger_zone_sub', 'অ্যাকাউন্ট নিষ্ক্রিয় করলে সব ডেটা মুছে যাবে না, কিন্তু আর লগইন করা যাবে না।')) ?>
                    </p>
                    <button type="button" class="btn btn-danger" id="deactivate-btn">
                        <?= e(t('deactivate_account', 'Deactivate Account')) ?>
                    </button>
                </section>
            </div>

            <!-- ============ SIDEBAR ============ -->
            <aside>
                <div class="card" style="padding:var(--space);">
                    <h4 style="font-size:var(--fs-md);margin-bottom:var(--space);"><?= e(t('quick_links', 'Quick Links')) ?></h4>
                    <nav class="flex flex-col gap-xs">
                        <a href="<?= e(base_url('user/index.php')) ?>" style="padding:9px 12px;border-radius:var(--radius);font-size:var(--fs-sm);color:var(--text);">
                            <?= e(t('dashboard', 'Dashboard')) ?>
                        </a>
                        <a href="<?= e(base_url('user/profile.php')) ?>" style="padding:9px 12px;border-radius:var(--radius);font-size:var(--fs-sm);color:var(--text);">
                            <?= e(t('profile', 'Profile')) ?>
                        </a>
                        <a href="<?= e(base_url('user/orders.php')) ?>" style="padding:9px 12px;border-radius:var(--radius);font-size:var(--fs-sm);color:var(--text);">
                            <?= e(t('my_orders', 'My Orders')) ?>
                        </a>
                        <a href="<?= e(base_url('user/notifications.php')) ?>" style="padding:9px 12px;border-radius:var(--radius);font-size:var(--fs-sm);color:var(--text);">
                            <?= e(t('notifications', 'Notifications')) ?>
                        </a>
                        <a href="<?= e(base_url('logout.php')) ?>" style="padding:9px 12px;border-radius:var(--radius);font-size:var(--fs-sm);color:var(--danger);">
                            <?= e(t('logout', 'Logout')) ?>
                        </a>
                    </nav>
                </div>

                <div class="card" style="padding:var(--space);margin-top:var(--space);">
                    <h4 style="font-size:var(--fs-md);margin-bottom:8px;"><?= e(t('security_tips', 'Security Tips')) ?></h4>
                    <ul style="font-size:var(--fs-xs);color:var(--text-muted);display:flex;flex-direction:column;gap:6px;line-height:1.5;">
                        <li>• <?= e(t('tip1', 'Use a strong password with letters and numbers.')) ?></li>
                        <li>• <?= e(t('tip2', 'Never share your password with anyone.')) ?></li>
                        <li>• <?= e(t('tip3', 'Log out from public devices.')) ?></li>
                    </ul>
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

<script>
// Danger zone confirm
document.getElementById('deactivate-btn')?.addEventListener('click', async function () {
    if (!confirm('<?= e(t('confirm_deactivate', 'Are you sure you want to deactivate your account? You will be logged out.')) ?>')) return;
    if (!confirm('<?= e(t('confirm_deactivate_2', 'This action cannot be undone automatically. Continue?')) ?>')) return;
    try {
        var res = await API.post('user/deactivate', { confirm: 1 });
        toast(res.message || 'Account deactivated', 'success');
        setTimeout(function () { location.href = '<?= e(base_url('logout.php')) ?>'; }, 1000);
    } catch (err) {
        toast(err.message || 'Could not deactivate account', 'error');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>