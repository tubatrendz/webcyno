<?php
/**
 * forgot-password.php — Forgot Password (Email Input)
 * Flow: email → api/auth/forgot → reset-password.php?email=...
 */

$page_title = 'Forgot Password — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Reset your ' . setting('site_name', 'Webcyno') . ' account password.';

require_once __DIR__ . '/includes/header.php';

// Already logged in → dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . base_url('user/index.php'));
    exit;
}
?>

<main id="main-content">
    <section style="padding:var(--space-xl) 0 var(--space-2xl);min-height:calc(100vh - var(--header-h) - 200px);">
        <div class="container">
            <div style="max-width:440px;margin:0 auto;">

                <div class="card" style="padding:var(--space-xl);">

                    <!-- Header -->
                    <div class="text-center" style="margin-bottom:var(--space-lg);">
                        <span style="width:64px;height:64px;background:var(--warning-soft);color:#B45309;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;margin-bottom:var(--space);">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <h1 style="font-size:var(--fs-2xl);margin-bottom:6px;"><?= e(t('forgot_password_title', 'Forgot Password?')) ?></h1>
                        <p class="text-muted" style="font-size:var(--fs-sm);">
                            <?= e(t('forgot_password_sub', 'আপনার email দিন — আমরা একটি reset code পাঠাব।')) ?>
                        </p>
                    </div>

                    <!-- Form (JS handles #forgot-form) -->
                    <form id="forgot-form" novalidate autocomplete="on">

                        <div class="form-group">
                            <label class="form-label" for="email"><?= e(t('email', 'Email')) ?> <span class="required">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="you@example.com" autocomplete="email" required autofocus>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <?= e(t('send_reset_code', 'Send Reset Code')) ?>
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="divider" style="margin:var(--space-lg) 0;"><?= e(t('or', 'OR')) ?></div>

                    <!-- Back to login -->
                    <p class="text-center text-muted" style="font-size:var(--fs-sm);margin:0;">
                        <?= e(t('remembered_password', 'Remembered your password?')) ?>
                        <a href="<?= e(base_url('login.php')) ?>" style="font-weight:600;"><?= e(t('back_to_login', 'Back to Login')) ?></a>
                    </p>
                </div>

                <!-- Security note -->
                <p class="text-center text-muted" style="font-size:var(--fs-xs);margin-top:var(--space);display:flex;align-items:center;justify-content:center;gap:6px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <?= e(t('forgot_secure_note', 'আমরা কখনোই আপনার পাসওয়ার্ড email-এ পাঠাই না।')) ?>
                </p>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('forgot-form');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var btn   = form.querySelector('button[type=submit]');
        var email = form.email.value.trim();

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showFormErrors(form, { email: 'Please enter a valid email.' });
            toast('Please enter a valid email.', 'warning');
            return;
        }
        showFormErrors(form, null);
        btnLoading(btn, true);

        try {
            var res = await API.post('auth/forgot', { email: email });
            toast(res.message || 'Reset code sent to your email.', 'success');

            // Redirect
            var redirect = (res.data && res.data.redirect)
                || ('<?= e(base_url('reset-password.php')) ?>?email=' + encodeURIComponent(email));
            setTimeout(function () { location.href = redirect; }, 800);
        } catch (err) {
            if (err.status === 429) {
                var cd = err.data && err.data.cooldown;
                toast(cd ? ('Please wait ' + cd + 's before retry.') : (err.message || 'Too many attempts.'), 'warning');
            } else if (err.errors) {
                showFormErrors(form, err.errors);
                toast(err.message || 'Could not send reset code.', 'error');
            } else {
                toast(err.message || 'Could not send reset code.', 'error');
            }
            btnLoading(btn, false);
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>