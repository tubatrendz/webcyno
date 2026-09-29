<?php
/**
 * verify-email.php — Email OTP Verification Page
 * User এখানে এসে 6-digit OTP দিয়ে account verify করবে।
 * URL: verify-email.php?email=user@example.com
 *
 * Flow:
 *  1. Register → OTP email যাবে → এখানে redirect
 *  2. User 6-digit OTP input করবে → api/auth/verify-email
 *  3. সঠিক হলে email_verified=1 → user dashboard
 */

$page_title = 'Verify Email — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Verify your email address to activate your account.';

require_once __DIR__ . '/includes/header.php';

// Already logged in & verified → dashboard
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo ?? null;
    if (!$stmt) { global $pdo; }
    $chk = $pdo->prepare("SELECT email_verified FROM users WHERE id = ? LIMIT 1");
    $chk->execute([(int) $_SESSION['user_id']]);
    if ((int) $chk->fetchColumn() === 1) {
        header('Location: ' . base_url('user/index.php'));
        exit;
    }
}

$email = trim((string) input('email', ''));

// Email না থাকলে — register-এ পাঠিয়ে দিই
if ($email === '') {
    header('Location: ' . base_url('register.php'));
    exit;
}

// Partial email mask (u***@example.com)
$parts = explode('@', $email);
$masked = '';
if (count($parts) === 2) {
    $local  = $parts[0];
    $domain = $parts[1];
    $masked = mb_substr($local, 0, 1) . str_repeat('•', max(1, mb_strlen($local) - 1)) . '@' . $domain;
} else {
    $masked = $email;
}
?>

<main id="main-content">
    <section style="padding:var(--space-xl) 0 var(--space-2xl);min-height:calc(100vh - var(--header-h) - 200px);">
        <div class="container">
            <div style="max-width:480px;margin:0 auto;">

                <div class="card" style="padding:var(--space-xl);">

                    <!-- Icon + Header -->
                    <div class="text-center" style="margin-bottom:var(--space-lg);">
                        <span style="width:72px;height:72px;background:var(--primary-soft);color:var(--primary);display:inline-flex;align-items:center;justify-content:center;border-radius:50%;margin-bottom:var(--space);">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"/>
                                <path d="m22 6-10 7L2 6"/>
                            </svg>
                        </span>
                        <h1 style="font-size:var(--fs-2xl);margin-bottom:6px;"><?= e(t('verify_email', 'Verify Your Email')) ?></h1>
                        <p class="text-muted" style="font-size:var(--fs-sm);">
                            <?= e(t('otp_sent_to', 'আমরা একটি 6-digit কোড পাঠিয়েছি')) ?>
                        </p>
                        <p style="font-weight:600;color:var(--navy);font-size:var(--fs-sm);margin-top:4px;">
                            <?= e($masked) ?>
                        </p>
                    </div>

                    <!-- OTP Form (main.js handles #otp-form) -->
                    <form id="otp-form" novalidate autocomplete="off">
                        <input type="hidden" name="email" value="<?= e($email) ?>">

                        <!-- 6 boxes -->
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

                        <!-- Hidden combined value (JS ভরে দেবে) -->
                        <input type="hidden" name="otp" id="otp-value">

                        <!-- Verify button -->
                        <button type="submit" class="btn btn-primary btn-lg btn-block" id="verify-btn">
                            <?= e(t('verify_now', 'Verify Now')) ?>
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

                    <!-- Change email -->
                    <p class="text-center text-muted" style="font-size:var(--fs-xs);margin-top:var(--space);">
                        <?= e(t('wrong_email', 'Wrong email?')) ?>
                        <a href="<?= e(base_url('register.php')) ?>" style="font-weight:600;"><?= e(t('register_again', 'Register again')) ?></a>
                    </p>
                </div>

                <!-- Security note -->
                <p class="text-center text-muted" style="font-size:var(--fs-xs);margin-top:var(--space);display:flex;align-items:center;justify-content:center;gap:6px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <?= e(t('otp_secure_note', 'OTP কোড কারো সাথে শেয়ার করবেন না।')) ?>
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
    var form    = document.getElementById('otp-form');
    var vBtn    = document.getElementById('verify-btn');
    var resBtn  = document.getElementById('resend-btn');
    var timerEl = document.getElementById('resend-timer');
    var timCnt  = document.getElementById('timer-count');
    var email   = form.email.value;

    /* ---------- Collect digits ---------- */
    function collect() {
        var val = boxes.map(function (b) { return b.value.replace(/\D/g, ''); }).join('');
        hidden.value = val;
        boxes.forEach(function (b) {
            b.classList.toggle('filled', b.value !== '');
            b.classList.remove('error');
        });
        return val;
    }

    /* ---------- Auto-advance & backspace ---------- */
    boxes.forEach(function (box, idx) {
        box.addEventListener('input', function (e) {
            var v = e.target.value.replace(/\D/g, '');
            e.target.value = v.slice(0, 1);
            if (v && idx < boxes.length - 1) boxes[idx + 1].focus();
            collect();
            // 6-digit complete হলে auto submit
            if (collect().length === 6) form.requestSubmit ? form.requestSubmit() : form.submit();
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
            digits.forEach(function (d, i) {
                if (boxes[i]) boxes[i].value = d;
            });
            var last = Math.min(digits.length, boxes.length) - 1;
            if (boxes[last]) boxes[last].focus();
            collect();
            if (collect().length === 6) form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    });

    /* ---------- Submit ---------- */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var code = collect();
        if (code.length !== 6) {
            toast('Please enter the 6-digit code.', 'warning');
            boxes.forEach(function (b) { if (!b.value) b.classList.add('error'); });
            return;
        }

        btnLoading(vBtn, true);
        try {
            var res = await API.post('auth/verify-email', { email: email, otp: code });
            toast(res.message || 'Email verified successfully!', 'success');
            var redirect = (res.data && res.data.redirect) || '<?= e(base_url('user/index.php')) ?>';
            setTimeout(function () { location.href = redirect; }, 800);
        } catch (err) {
            boxes.forEach(function (b) { b.classList.add('error'); });
            toast(err.message || 'Invalid or expired OTP.', 'error');
            btnLoading(vBtn, false);
            // Clear boxes for retry
            setTimeout(function () {
                boxes.forEach(function (b) { b.value = ''; b.classList.remove('error', 'filled'); });
                hidden.value = '';
                boxes[0].focus();
            }, 900);
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
            var res = await API.post('auth/send-otp', { email: email, purpose: 'email' });
            toast(res.message || 'New OTP sent to your email.', 'success');
            startTimer(60);
        } catch (err) {
            toast(err.message || 'Could not resend OTP.', 'error');
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

<?php require_once __DIR__ . '/includes/footer.php'; ?><?php
/**
 * verify-email.php — Email OTP Verification Page
 * User এখানে এসে 6-digit OTP দিয়ে account verify করবে।
 * URL: verify-email.php?email=user@example.com
 *
 * Flow:
 *  1. Register → OTP email যাবে → এখানে redirect
 *  2. User 6-digit OTP input করবে → api/auth/verify-email
 *  3. সঠিক হলে email_verified=1 → user dashboard
 */

$page_title = 'Verify Email — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Verify your email address to activate your account.';

require_once __DIR__ . '/includes/header.php';

// Already logged in & verified → dashboard
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo ?? null;
    if (!$stmt) { global $pdo; }
    $chk = $pdo->prepare("SELECT email_verified FROM users WHERE id = ? LIMIT 1");
    $chk->execute([(int) $_SESSION['user_id']]);
    if ((int) $chk->fetchColumn() === 1) {
        header('Location: ' . base_url('user/index.php'));
        exit;
    }
}

$email = trim((string) input('email', ''));

// Email না থাকলে — register-এ পাঠিয়ে দিই
if ($email === '') {
    header('Location: ' . base_url('register.php'));
    exit;
}

// Partial email mask (u***@example.com)
$parts = explode('@', $email);
$masked = '';
if (count($parts) === 2) {
    $local  = $parts[0];
    $domain = $parts[1];
    $masked = mb_substr($local, 0, 1) . str_repeat('•', max(1, mb_strlen($local) - 1)) . '@' . $domain;
} else {
    $masked = $email;
}
?>

<main id="main-content">
    <section style="padding:var(--space-xl) 0 var(--space-2xl);min-height:calc(100vh - var(--header-h) - 200px);">
        <div class="container">
            <div style="max-width:480px;margin:0 auto;">

                <div class="card" style="padding:var(--space-xl);">

                    <!-- Icon + Header -->
                    <div class="text-center" style="margin-bottom:var(--space-lg);">
                        <span style="width:72px;height:72px;background:var(--primary-soft);color:var(--primary);display:inline-flex;align-items:center;justify-content:center;border-radius:50%;margin-bottom:var(--space);">
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"/>
                                <path d="m22 6-10 7L2 6"/>
                            </svg>
                        </span>
                        <h1 style="font-size:var(--fs-2xl);margin-bottom:6px;"><?= e(t('verify_email', 'Verify Your Email')) ?></h1>
                        <p class="text-muted" style="font-size:var(--fs-sm);">
                            <?= e(t('otp_sent_to', 'আমরা একটি 6-digit কোড পাঠিয়েছি')) ?>
                        </p>
                        <p style="font-weight:600;color:var(--navy);font-size:var(--fs-sm);margin-top:4px;">
                            <?= e($masked) ?>
                        </p>
                    </div>

                    <!-- OTP Form (main.js handles #otp-form) -->
                    <form id="otp-form" novalidate autocomplete="off">
                        <input type="hidden" name="email" value="<?= e($email) ?>">

                        <!-- 6 boxes -->
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

                        <!-- Hidden combined value (JS ভরে দেবে) -->
                        <input type="hidden" name="otp" id="otp-value">

                        <!-- Verify button -->
                        <button type="submit" class="btn btn-primary btn-lg btn-block" id="verify-btn">
                            <?= e(t('verify_now', 'Verify Now')) ?>
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

                    <!-- Change email -->
                    <p class="text-center text-muted" style="font-size:var(--fs-xs);margin-top:var(--space);">
                        <?= e(t('wrong_email', 'Wrong email?')) ?>
                        <a href="<?= e(base_url('register.php')) ?>" style="font-weight:600;"><?= e(t('register_again', 'Register again')) ?></a>
                    </p>
                </div>

                <!-- Security note -->
                <p class="text-center text-muted" style="font-size:var(--fs-xs);margin-top:var(--space);display:flex;align-items:center;justify-content:center;gap:6px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <?= e(t('otp_secure_note', 'OTP কোড কারো সাথে শেয়ার করবেন না।')) ?>
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
    var form    = document.getElementById('otp-form');
    var vBtn    = document.getElementById('verify-btn');
    var resBtn  = document.getElementById('resend-btn');
    var timerEl = document.getElementById('resend-timer');
    var timCnt  = document.getElementById('timer-count');
    var email   = form.email.value;

    /* ---------- Collect digits ---------- */
    function collect() {
        var val = boxes.map(function (b) { return b.value.replace(/\D/g, ''); }).join('');
        hidden.value = val;
        boxes.forEach(function (b) {
            b.classList.toggle('filled', b.value !== '');
            b.classList.remove('error');
        });
        return val;
    }

    /* ---------- Auto-advance & backspace ---------- */
    boxes.forEach(function (box, idx) {
        box.addEventListener('input', function (e) {
            var v = e.target.value.replace(/\D/g, '');
            e.target.value = v.slice(0, 1);
            if (v && idx < boxes.length - 1) boxes[idx + 1].focus();
            collect();
            // 6-digit complete হলে auto submit
            if (collect().length === 6) form.requestSubmit ? form.requestSubmit() : form.submit();
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
            digits.forEach(function (d, i) {
                if (boxes[i]) boxes[i].value = d;
            });
            var last = Math.min(digits.length, boxes.length) - 1;
            if (boxes[last]) boxes[last].focus();
            collect();
            if (collect().length === 6) form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    });

    /* ---------- Submit ---------- */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var code = collect();
        if (code.length !== 6) {
            toast('Please enter the 6-digit code.', 'warning');
            boxes.forEach(function (b) { if (!b.value) b.classList.add('error'); });
            return;
        }

        btnLoading(vBtn, true);
        try {
            var res = await API.post('auth/verify-email', { email: email, otp: code });
            toast(res.message || 'Email verified successfully!', 'success');
            var redirect = (res.data && res.data.redirect) || '<?= e(base_url('user/index.php')) ?>';
            setTimeout(function () { location.href = redirect; }, 800);
        } catch (err) {
            boxes.forEach(function (b) { b.classList.add('error'); });
            toast(err.message || 'Invalid or expired OTP.', 'error');
            btnLoading(vBtn, false);
            // Clear boxes for retry
            setTimeout(function () {
                boxes.forEach(function (b) { b.value = ''; b.classList.remove('error', 'filled'); });
                hidden.value = '';
                boxes[0].focus();
            }, 900);
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
            var res = await API.post('auth/send-otp', { email: email, purpose: 'email' });
            toast(res.message || 'New OTP sent to your email.', 'success');
            startTimer(60);
        } catch (err) {
            toast(err.message || 'Could not resend OTP.', 'error');
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