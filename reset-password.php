<?php
/**
 * reset-password.php — Reset Password (OTP + New Password)
 * URL: reset-password.php?email=user@example.com
 * Flow: forgot-password.php → email → OTP → এখানে → api/auth/reset → login.php
 */

$page_title = 'Reset Password — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Set a new password for your account.';

require_once __DIR__ . '/includes/header.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . base_url('user/index.php'));
    exit;
}

$email = trim((string) input('email', ''));
if ($email === '') {
    header('Location: ' . base_url('forgot-password.php'));
    exit;
}

// Masked email display
$parts  = explode('@', $email);
$masked = $email;
if (count($parts) === 2) {
    $local  = $parts[0];
    $domain = $parts[1];
    $masked = mb_substr($local, 0, 1) . str_repeat('•', max(1, mb_strlen($local) - 1)) . '@' . $domain;
}
?>

<main id="main-content">
    <section style="padding:var(--space-xl) 0 var(--space-2xl);min-height:calc(100vh - var(--header-h) - 200px);">
        <div class="container">
            <div style="max-width:480px;margin:0 auto;">

                <div class="card" style="padding:var(--space-xl);">

                    <!-- Header -->
                    <div class="text-center" style="margin-bottom:var(--space-lg);">
                        <span style="width:64px;height:64px;background:var(--primary-soft);color:var(--primary);display:inline-flex;align-items:center;justify-content:center;border-radius:50%;margin-bottom:var(--space);">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                <circle cx="12" cy="16" r="1"/>
                            </svg>
                        </span>
                        <h1 style="font-size:var(--fs-2xl);margin-bottom:6px;"><?= e(t('reset_password_title', 'Reset Password')) ?></h1>
                        <p class="text-muted" style="font-size:var(--fs-sm);">
                            <?= e(t('reset_code_sent', 'আমরা একটি 6-digit কোড পাঠিয়েছি')) ?>
                        </p>
                        <p style="font-weight:600;color:var(--navy);font-size:var(--fs-sm);margin-top:4px;">
                            <?= e($masked) ?>
                        </p>
                    </div>

                    <!-- Form (JS handles #reset-form) -->
                    <form id="reset-form" novalidate autocomplete="off">
                        <input type="hidden" name="email" value="<?= e($email) ?>">

                        <!-- OTP boxes -->
                        <div class="otp-inputs" id="otp-inputs" style="display:flex;gap:10px;justify-content:center;margin-bottom:var(--space-lg);">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <input type="text"
                                       class="otp-box"
                                       inputmode="numeric"
                                       pattern="[0-9]*"
                                       maxlength="1"
                                       data-index="<?= $i ?>"
                                       autocomplete="one-time-code"
                                       aria-label="OTP digit <?= $i + 1 ?>"
                                       style="width:52px;height:60px;text-align:center;font-size:22px;font-weight:700;border:1.5px solid var(--border);border-radius:var(--radius-md);outline:none;background:var(--white);color:var(--navy);transition:all var(--t-fast);"
                                       <?= $i === 0 ? 'autofocus' : '' ?>>
                            <?php endfor; ?>
                        </div>

                        <input type="hidden" name="otp" id="otp-value">

                        <hr>

                        <!-- New password -->
                        <div class="form-group" style="margin-top:var(--space);">
                            <label class="form-label" for="password"><?= e(t('new_password', 'New Password')) ?> <span class="required">*</span></label>
                            <div style="position:relative;">
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="<?= e(t('password_placeholder', 'At least 8 characters')) ?>"
                                       autocomplete="new-password" required minlength="8"
                                       style="padding-right:44px;">
                                <button type="button" data-toggle-password="#password" aria-label="Show"
                                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:transparent;border:none;cursor:pointer;color:var(--text-muted);padding:6px;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <p class="form-hint"><?= e(t('password_hint', 'Must contain letters and numbers, min 8 chars.')) ?></p>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password_confirm"><?= e(t('confirm_password', 'Confirm New Password')) ?> <span class="required">*</span></label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                                   placeholder="<?= e(t('retype_password', 'Re-type your new password')) ?>"
                                   autocomplete="new-password" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block" id="reset-btn">
                            <?= e(t('reset_password_btn', 'Reset Password')) ?>
                        </button>
                    </form>

                    <!-- Resend -->
                    <div class="text-center" style="margin-top:var(--space-lg);font-size:var(--fs-sm);color:var(--text-muted);">
                        <span><?= e(t('didnt_receive', "Didn't receive the code?")) ?></span>
                        <button type="button" id="resend-btn" class="btn btn-link" style="background:none;border:none;color:var(--primary);font-weight:600;cursor:pointer;padding:0;font-size:inherit;">
                            <?= e(t('resend_otp', 'Resend OTP')) ?>
                        </button>
                        <span id="resend-timer" style="display:none;color:var(--text-light);">
                            (<?= e(t('resend_in', 'in')) ?> <span id="timer-count">60</span>s)
                        </span>
                    </div>

                    <!-- Back to login -->
                    <p class="text-center text-muted" style="font-size:var(--fs-xs);margin-top:var(--space);">
                        <a href="<?= e(base_url('login.php')) ?>" style="font-weight:600;">← <?= e(t('back_to_login', 'Back to Login')) ?></a>
                    </p>
                </div>

                <!-- Security note -->
                <p class="text-center text-muted" style="font-size:var(--fs-xs);margin-top:var(--space);display:flex;align-items:center;justify-content:center;gap:6px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <?= e(t('reset_secure_note', 'OTP কোড কারো সাথে শেয়ার করবেন না।')) ?>
                </p>
            </div>
        </div>
    </section>
</main>

<style>
.otp-box:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
}
.otp-box.filled {
    border-color: var(--primary);
    background: var(--primary-soft);
}
.otp-box.error {
    border-color: var(--danger) !important;
    background: var(--danger-soft);
    animation: shake .35s;
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25%      { transform: translateX(-6px); }
    75%      { transform: translateX(6px); }
}
@media (max-width: 480px) {
    .otp-box { width: 44px !important; height: 52px !important; font-size: 18px !important; }
    #otp-inputs { gap: 6px !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var boxes   = Array.prototype.slice.call(document.querySelectorAll('.otp-box'));
    var hidden  = document.getElementById('otp-value');
    var form    = document.getElementById('reset-form');
    var vBtn    = document.getElementById('reset-btn');
    var resBtn  = document.getElementById('resend-btn');
    var timerEl = document.getElementById('resend-timer');
    var timCnt  = document.getElementById('timer-count');
    var email   = form.email.value;

    /* ---------- OTP collect ---------- */
    function collect() {
        var val = boxes.map(function (b) { return b.value.replace(/\D/g, ''); }).join('');
        hidden.value = val;
        boxes.forEach(function (b) {
            b.classList.toggle('filled', b.value !== '');
            b.classList.remove('error');
        });
        return val;
    }

    /* ---------- OTP UX ---------- */
    boxes.forEach(function (box, idx) {
        box.addEventListener('input', function (e) {
            var v = e.target.value.replace(/\D/g, '');
            e.target.value = v.slice(0, 1);
            if (v && idx < boxes.length - 1) boxes[idx + 1].focus();
            collect();
        });

        box.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                boxes[idx - 1].focus();
                boxes[idx - 1].value = '';
                collect();
            }
            if (e.key === 'ArrowLeft'  && idx > 0)                boxes[idx - 1].focus();
            if (e.key === 'ArrowRight' && idx < boxes.length - 1) boxes[idx + 1].focus();
        });

        box.addEventListener('paste', function (e) {
            var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            if (!pasted) return;
            e.preventDefault();
            var digits = pasted.slice(0, 6).split('');
            digits.forEach(function (d, i) { if (boxes[i]) boxes[i].value = d; });
            var last = Math.min(digits.length, boxes.length) - 1;
            if (boxes[last]) boxes[last].focus();
            collect();
        });
    });

    /* ---------- Submit ---------- */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        var otp      = collect();
        var password = form.password.value;
        var confirm  = form.password_confirm.value;

        var errors = {};
        if (otp.length !== 6)         errors.otp = 'Enter all 6 digits.';
        if (!password)                errors.password = 'Password is required.';
        else if (password.length < 8) errors.password = 'Minimum 8 characters.';
        if (password !== confirm)     errors.password_confirm = 'Passwords do not match.';

        if (Object.keys(errors).length) {
            if (errors.otp) boxes.forEach(function (b) { if (!b.value) b.classList.add('error'); });
            showFormErrors(form, errors);
            toast('Please fix the highlighted fields.', 'warning');
            return;
        }

        showFormErrors(form, null);
        btnLoading(vBtn, true);

        try {
            var res = await API.post('auth/reset', {
                email: email,
                otp: otp,
                password: password,
                password_confirm: confirm
            });
            toast(res.message || 'Password reset successfully!', 'success');
            var redirect = (res.data && res.data.redirect) || '<?= e(base_url('login.php')) ?>?reset=1';
            setTimeout(function () { location.href = redirect; }, 900);
        } catch (err) {
            if (err.errors && err.errors.otp) {
                boxes.forEach(function (b) { b.classList.add('error'); });
                setTimeout(function () {
                    boxes.forEach(function (b) { b.value = ''; b.classList.remove('error', 'filled'); });
                    hidden.value = ''; boxes[0].focus();
                }, 900);
            }
            if (err.errors) showFormErrors(form, err.errors);
            toast(err.message || 'Could not reset password.', 'error');
            btnLoading(vBtn, false);
        }
    });

    /* ---------- Resend OTP ---------- */
    var cooldown = 0;
    function startTimer(sec) {
        cooldown = sec;
        resBtn.disabled = true;
        resBtn.style.opacity = '.5';
        resBtn.style.cursor = 'not-allowed';
        timerEl.style.display = 'inline';
        timCnt.textContent = sec;
        var t = setInterval(function () {
            cooldown--;
            timCnt.textContent = cooldown;
            if (cooldown <= 0) {
                clearInterval(t);
                resBtn.disabled = false;
                resBtn.style.opacity = '1';
                resBtn.style.cursor = 'pointer';
                timerEl.style.display = 'none';
            }
        }, 1000);
    }

    resBtn.addEventListener('click', async function () {
        if (cooldown > 0) return;
        resBtn.disabled = true;
        btnLoading(resBtn, true);
        try {
            var res = await API.post('auth/send-otp', { email: email, purpose: 'reset' });
            toast(res.message || 'New reset code sent.', 'success');
            startTimer(60);
        } catch (err) {
            if (err.status === 429 && err.data && err.data.cooldown) {
                startTimer(err.data.cooldown);
                toast('Please wait ' + err.data.cooldown + 's.', 'warning');
            } else {
                toast(err.message || 'Could not resend code.', 'error');
            }
            resBtn.disabled = false;
            resBtn.style.opacity = '1';
        } finally {
            btnLoading(resBtn, false);
            if (cooldown === 0) {
                resBtn.style.opacity = '1';
                resBtn.style.cursor = 'pointer';
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>