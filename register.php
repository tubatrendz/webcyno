<?php
/**
 * register.php — User Registration
 * Form: name, email, phone, password, password_confirm
 * JS hook: #register-form → main.js → api/auth/register
 */

$page_title = 'Create Account — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Create your free ' . setting('site_name', 'Webcyno') . ' account.';

require_once __DIR__ . '/includes/header.php';

// Already logged in → redirect
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . base_url('user/index.php'));
    exit;
}

$redirect = trim((string) input('redirect', 'user/index.php'));
?>

<main id="main-content">
    <section style="padding:var(--space-xl) 0 var(--space-2xl);min-height:calc(100vh - var(--header-h) - 200px);">
        <div class="container">
            <div style="max-width:480px;margin:0 auto;">

                <div class="card" style="padding:var(--space-xl);">

                    <!-- Header -->
                    <div class="text-center" style="margin-bottom:var(--space-lg);">
                        <a href="<?= e(base_url('index.php')) ?>" style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;">
                            <span class="brand-mark" style="width:44px;height:44px;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;border-radius:var(--radius-md);font-weight:800;font-size:var(--fs-lg);">W</span>
                        </a>
                        <h1 style="font-size:var(--fs-2xl);margin-top:var(--space);margin-bottom:6px;"><?= e(t('create_account', 'Create Your Account')) ?></h1>
                        <p class="text-muted" style="font-size:var(--fs-sm);"><?= e(t('register_sub', 'Join Webcyno and start ordering AI-powered services.')) ?></p>
                    </div>

                    <!-- Register Form (main.js handles #register-form) -->
                    <form id="register-form" novalidate>

                        <div class="form-group">
                            <label class="form-label" for="name"><?= e(t('full_name', 'Full Name')) ?> <span class="required">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   placeholder="<?= e(t('name_placeholder', 'Your full name')) ?>"
                                   autocomplete="name" required autofocus>
                        </div>

                        <div class="grid grid-2" style="gap:var(--space);">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" for="email"><?= e(t('email', 'Email')) ?> <span class="required">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="you@example.com" autocomplete="email" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label" for="phone"><?= e(t('phone', 'Phone')) ?></label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       placeholder="01XXXXXXXXX" autocomplete="tel">
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:var(--space);">
                            <label class="form-label" for="password"><?= e(t('password', 'Password')) ?> <span class="required">*</span></label>
                            <div style="position:relative;">
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="<?= e(t('password_placeholder', 'At least 8 characters')) ?>"
                                       autocomplete="new-password" required minlength="8"
                                       style="padding-right:44px;">
                                <button type="button" data-toggle-password="#password" aria-label="Show password"
                                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:transparent;border:none;cursor:pointer;color:var(--text-muted);padding:6px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="form-hint"><?= e(t('password_hint', 'Must contain letters and numbers, min 8 chars.')) ?></p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password_confirm"><?= e(t('confirm_password', 'Confirm Password')) ?> <span class="required">*</span></label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                                   placeholder="<?= e(t('retype_password', 'Re-type your password')) ?>"
                                   autocomplete="new-password" required>
                        </div>

                        <div class="form-check" style="margin-bottom:var(--space);">
                            <input type="checkbox" id="terms" name="terms" value="1" required>
                            <label for="terms" style="font-size:var(--fs-sm);color:var(--text-muted);cursor:pointer;">
                                <?= e(t('agree_to', 'I agree to the')) ?>
                                <a href="<?= e(base_url('terms.php')) ?>" target="_blank"><?= e(t('terms', 'Terms')) ?></a> &
                                <a href="<?= e(base_url('privacy-policy.php')) ?>" target="_blank"><?= e(t('privacy', 'Privacy Policy')) ?></a>.
                            </label>
                        </div>

                        <!-- Hidden redirect -->
                        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <?= e(t('create_account_btn', 'Create Account')) ?>
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="divider" style="margin:var(--space-lg) 0;"><?= e(t('or', 'OR')) ?></div>

                    <!-- Social (placeholder) -->
                    <div class="flex flex-col gap-sm">
                        <button type="button" class="btn btn-ghost btn-block" disabled style="opacity:.5;cursor:not-allowed;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                            <?= e(t('signup_google', 'Sign up with Google')) ?>
                        </button>
                        <button type="button" class="btn btn-ghost btn-block" disabled style="opacity:.5;cursor:not-allowed;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.07C24 5.41 18.63 0 12 0S0 5.41 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                            <?= e(t('signup_facebook', 'Sign up with Facebook')) ?>
                        </button>
                    </div>

                    <!-- Login CTA -->
                    <p class="text-center text-muted" style="font-size:var(--fs-sm);margin-top:var(--space-lg);">
                        <?= e(t('have_account', 'Already have an account?')) ?>
                        <a href="<?= e(base_url('login.php')) ?>" style="font-weight:600;"><?= e(t('login', 'Login')) ?></a>
                    </p>
                </div>

                <!-- Security note -->
                <p class="text-center text-muted" style="font-size:var(--fs-xs);margin-top:var(--space);display:flex;align-items:center;justify-content:center;gap:6px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <?= e(t('secure_register', 'Your data is protected with SSL encryption.')) ?>
                </p>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>