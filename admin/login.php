<?php
/**
 * admin/login.php — Admin Login (standalone, no public header)
 * Flow: form submit → api/admin/login → redirect to dashboard
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helpers.php';

/* ---------- Already logged in → dashboard ---------- */
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . base_url('admin/dashboard.php'));
    exit;
}

/* ---------- Base URL fallback ---------- */
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string {
        static $base = null;
        if ($base === null) {
            $root    = str_replace('\\', '/', dirname(__DIR__));
            $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
            $base    = ($docRoot !== '' && strpos($root, $docRoot) === 0)
                ? substr($root, strlen($docRoot))
                : '';
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

$csrf     = csrf_token();
$redirect = trim((string) ($_GET['redirect'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0F172A">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title>Admin Login — <?= e(setting('site_name', 'Webcyno')) ?></title>

    <link rel="icon" href="<?= e(base_url('assets/icons/favicon.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= e(base_url('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('css/responsive.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('admin/assets/admin.css')) ?>">
</head>
<body class="admin-body">

<div class="admin-login-wrap">
    <div class="admin-login-card">

        <!-- Brand -->
        <div class="admin-login-brand">
            <div class="mark">W</div>
            <h1><?= e(setting('site_name', 'Webcyno')) ?> Admin</h1>
            <p><?= e(t('admin_login_sub', 'Sign in to manage your marketplace')) ?></p>
        </div>

        <!-- Login form (submits via JS to api/admin/login) -->
        <form id="admin-login-form" novalidate>

            <div class="form-group">
                <label class="form-label" for="email"><?= e(t('email', 'Email')) ?> <span class="required">*</span></label>
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="admin@example.com" autocomplete="username" required autofocus>
            </div>

            <div class="form-group" style="margin-bottom:var(--space);">
                <div class="flex justify-between items-center" style="margin-bottom:6px;">
                    <label class="form-label" for="password" style="margin-bottom:0;">
                        <?= e(t('password', 'Password')) ?> <span class="required">*</span>
                    </label>
                </div>
                <div style="position:relative;">
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="••••••••" autocomplete="current-password" required
                           style="padding-right:44px;">
                    <button type="button" data-toggle-password="#password" aria-label="Show password"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:transparent;border:none;cursor:pointer;color:var(--text-muted);padding:6px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

            <button type="submit" class="btn btn-primary btn-lg btn-block" id="admin-login-btn">
                <?= e(t('login', 'Login')) ?>
            </button>
        </form>

        <!-- Security note -->
        <p class="text-muted text-center" style="font-size:var(--fs-xs);margin-top:var(--space-lg);display:flex;align-items:center;justify-content:center;gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <?= e(t('admin_login_secure', 'Restricted area — authorized personnel only.')) ?>
        </p>

        <!-- Back to site -->
        <p class="text-center" style="font-size:var(--fs-xs);margin-top:var(--space);">
            <a href="<?= e(base_url('index.php')) ?>" style="color:var(--text-muted);">
                ← <?= e(t('back_to_site', 'Back to website')) ?>
            </a>
        </p>
    </div>
</div>

<!-- Config for JS -->
<script>
    window.WCB_CONFIG = {
        apiUrl:  "<?= e(base_url('api')) ?>",
        baseUrl: "<?= e(base_url()) ?>",
        adminUrl:"<?= e(base_url('admin')) ?>",
        lang:    "en",
        isAdmin: false
    };
</script>

<script src="<?= e(base_url('js/api.js')) ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('admin-login-form');
    var btn  = document.getElementById('admin-login-btn');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        var email    = form.email.value.trim();
        var password = form.password.value;
        var redirect = form.redirect.value;

        // Client-side check
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showFormErrors(form, { email: 'Enter a valid email.' });
            toast('Please enter a valid email.', 'warning');
            return;
        }
        if (!password) {
            showFormErrors(form, { password: 'Password is required.' });
            toast('Password is required.', 'warning');
            return;
        }
        showFormErrors(form, null);
        btnLoading(btn, true);

        try {
            var res = await API.post('admin/login', {
                email: email,
                password: password,
                redirect: redirect
            });
            toast(res.message || 'Login successful', 'success');

            var target = (res.data && res.data.redirect)
                || (window.WCB_CONFIG.adminUrl + '/dashboard.php');

            setTimeout(function () { location.href = target; }, 600);
        } catch (err) {
            if (err.errors) showFormErrors(form, err.errors);
            toast(err.message || 'Login failed', 'error');
            btnLoading(btn, false);
        }
    });

    // Password show/hide toggle (JS hook already in main.js but admin page doesn't load main.js)
    document.querySelectorAll('[data-toggle-password]').forEach(function (el) {
        el.addEventListener('click', function () {
            var input = document.querySelector(el.dataset.togglePassword);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });
});
</script>

</body>
</html>