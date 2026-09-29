<?php
/**
 * user/profile.php — Edit Profile
 * Update: name, phone, avatar, language, currency
 * Email পরিবর্তন করতে হলে verify flow লাগবে — এখানে শুধু notice।
 */

require_once __DIR__ . '/../includes/header.php';

/* ---------- Auth guard ---------- */
if (empty($_SESSION['user_id'])) {
    header('Location: ' . base_url('login.php?redirect=' . urlencode('user/profile.php')));
    exit;
}
$user = current_user();
if (!$user) { header('Location: ' . base_url('logout.php')); exit; }

$userId = (int) $user['id'];
global $pdo;

/* ---------- Handle POST (fallback non-JS submit) ---------- */
$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(input('csrf_token'))) {
        $errors['_general'] = t('csrf_invalid', 'Invalid security token. Please try again.');
    } else {
        $name     = clean(input('name', ''), 100);
        $phone    = clean_phone(input('phone', ''));
        $language = in_array(input('language'), ['bn','en'], true) ? input('language') : ($user['language'] ?? 'bn');
        $currency = in_array(input('currency'), ['BDT','USD'], true) ? input('currency') : ($user['currency'] ?? 'BDT');

        if ($name === '') $errors['name'] = t('name_required', 'Name is required.');

        // Avatar upload
        $avatar = $user['avatar'] ?? null;
        if (!empty($_FILES['avatar']['tmp_name'])) {
            $uploaded = upload_image($_FILES['avatar'], 'users', 2);
            if ($uploaded) {
                if (!empty($avatar)) delete_upload($avatar);
                $avatar = $uploaded;
            } else {
                $errors['avatar'] = t('avatar_invalid', 'Only JPG/PNG/WEBP under 2MB is allowed.');
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET name = ?, phone = ?, avatar = ?, language = ?, currency = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $phone, $avatar, $language, $currency, $userId]);
                $success = t('profile_updated', 'Profile updated successfully.');
                $user    = current_user(); // refresh
            } catch (Exception $ex) {
                $errors['_general'] = t('update_failed', 'Could not update profile. Please try again.');
            }
        }
    }
}

$page_title = t('edit_profile', 'Edit Profile') . ' — ' . setting('site_name', 'Webcyno');
$avatarUrl  = !empty($user['avatar']) ? base_url($user['avatar']) : null;
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <a href="<?= e(base_url('user/index.php')) ?>"><?= e(t('dashboard')) ?></a>
            <span class="sep">/</span>
            <span class="current"><?= e(t('profile', 'Profile')) ?></span>
        </nav>

        <div class="section-header-row" style="margin-bottom:var(--space-lg);">
            <div>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:4px;"><?= e(t('profile', 'Profile')) ?></h1>
                <p class="text-muted"><?= e(t('profile_sub', 'Manage your personal information and preferences.')) ?></p>
            </div>
            <a href="<?= e(base_url('user/settings.php')) ?>" class="btn btn-ghost btn-sm">
                <?= e(t('account_settings', 'Account Settings')) ?> →
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

            <!-- ============ FORM ============ -->
            <section class="card" style="padding:var(--space-lg);">
                <form method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <!-- Avatar -->
                    <div class="flex items-center gap-md" style="margin-bottom:var(--space-lg);flex-wrap:wrap;">
                        <div style="position:relative;">
                            <?php if ($avatarUrl): ?>
                                <img id="avatar-preview" src="<?= e($avatarUrl) ?>" alt=""
                                     style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid var(--border);">
                            <?php else: ?>
                                <span id="avatar-preview"
                                      style="width:88px;height:88px;border-radius:50%;background:var(--primary);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:28px;">
                                    <?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:180px;">
                            <label class="form-label"><?= e(t('profile_photo', 'Profile Photo')) ?></label>
                            <input type="file" name="avatar" id="avatar-input" accept="image/jpeg,image/png,image/webp"
                                   class="form-control" style="padding:6px;">
                            <p class="form-hint"><?= e(t('avatar_hint', 'JPG/PNG/WEBP — max 2MB.')) ?></p>
                            <?php if (!empty($errors['avatar'])): ?>
                                <p class="form-error"><?= e($errors['avatar']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr>

                    <!-- Basic info -->
                    <h3 style="font-size:var(--fs-md);margin-bottom:var(--space);"><?= e(t('basic_info', 'Basic Information')) ?></h3>

                    <div class="grid grid-2" style="gap:var(--space);">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" for="name"><?= e(t('full_name', 'Full Name')) ?> <span class="required">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?= e($user['name'] ?? '') ?>" required>
                            <?php if (!empty($errors['name'])): ?><p class="form-error"><?= e($errors['name']) ?></p><?php endif; ?>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" for="phone"><?= e(t('phone', 'Phone')) ?></label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?= e($user['phone'] ?? '') ?>" placeholder="01XXXXXXXXX">
                        </div>
                    </div>

                    <!-- Email (readonly) -->
                    <div class="form-group" style="margin-top:var(--space);">
                        <label class="form-label" for="email"><?= e(t('email', 'Email')) ?></label>
                        <input type="email" class="form-control" id="email"
                               value="<?= e($user['email'] ?? '') ?>" readonly>
                        <p class="form-hint">
                            <?= e(t('email_change_note', 'Email পরিবর্তন করতে Account Settings → Change Email ব্যবহার করুন।')) ?>
                        </p>
                    </div>

                    <hr>

                    <!-- Preferences -->
                    <h3 style="font-size:var(--fs-md);margin-bottom:var(--space);"><?= e(t('preferences', 'Preferences')) ?></h3>

                    <div class="grid grid-2" style="gap:var(--space);">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" for="language"><?= e(t('language', 'Language')) ?></label>
                            <select name="language" id="language" class="form-control">
                                <option value="bn" <?= ($user['language'] ?? 'bn') === 'bn' ? 'selected' : '' ?>>বাংলা</option>
                                <option value="en" <?= ($user['language'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" for="currency"><?= e(t('currency', 'Currency')) ?></label>
                            <select name="currency" id="currency" class="form-control">
                                <option value="BDT" <?= ($user['currency'] ?? 'BDT') === 'BDT' ? 'selected' : '' ?>>৳ BDT</option>
                                <option value="USD" <?= ($user['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>$ USD</option>
                            </select>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-sm" style="margin-top:var(--space-lg);flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary"><?= e(t('save_changes', 'Save Changes')) ?></button>
                        <a href="<?= e(base_url('user/index.php')) ?>" class="btn btn-ghost"><?= e(t('cancel', 'Cancel')) ?></a>
                    </div>
                </form>
            </section>

            <!-- ============ SIDEBAR ============ -->
            <aside>
                <div class="card" style="padding:var(--space);">
                    <h4 style="font-size:var(--fs-md);margin-bottom:var(--space);"><?= e(t('account_overview', 'Account Overview')) ?></h4>
                    <ul style="display:flex;flex-direction:column;gap:10px;font-size:var(--fs-sm);">
                        <li class="flex justify-between gap-sm">
                            <span class="text-muted"><?= e(t('member_since', 'Member since')) ?></span>
                            <strong><?= e(date('M Y', strtotime($user['created_at'] ?? 'now'))) ?></strong>
                        </li>
                        <li class="flex justify-between gap-sm">
                            <span class="text-muted"><?= e(t('email_status', 'Email')) ?></span>
                            <?php if (!empty($user['email_verified'])): ?>
                                <span class="status status-completed" style="font-size:10px;padding:3px 8px;"><?= e(t('verified', 'Verified')) ?></span>
                            <?php else: ?>
                                <span class="status status-pending" style="font-size:10px;padding:3px 8px;"><?= e(t('unverified', 'Unverified')) ?></span>
                            <?php endif; ?>
                        </li>
                        <li class="flex justify-between gap-sm">
                            <span class="text-muted"><?= e(t('phone_status', 'Phone')) ?></span>
                            <?php if (!empty($user['phone_verified'])): ?>
                                <span class="status status-completed" style="font-size:10px;padding:3px 8px;"><?= e(t('verified', 'Verified')) ?></span>
                            <?php else: ?>
                                <span class="status status-pending" style="font-size:10px;padding:3px 8px;"><?= e(t('unverified', 'Unverified')) ?></span>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>

                <div class="card" style="padding:var(--space);margin-top:var(--space);">
                    <h4 style="font-size:var(--fs-md);margin-bottom:8px;"><?= e(t('security', 'Security')) ?></h4>
                    <p class="text-muted" style="font-size:var(--fs-xs);margin-bottom:var(--space);">
                        <?= e(t('security_note', 'পাসওয়ার্ড পরিবর্তন ও অ্যাকাউন্ট সুরক্ষার সেটিংস এখানে।')) ?>
                    </p>
                    <a href="<?= e(base_url('user/settings.php')) ?>" class="btn btn-ghost btn-sm btn-block">
                        <?= e(t('change_password', 'Change Password')) ?>
                    </a>
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
// Avatar preview
document.getElementById('avatar-input')?.addEventListener('change', function (e) {
    var f = e.target.files[0];
    if (!f || !f.type.startsWith('image/')) return;
    var reader = new FileReader();
    reader.onload = function (ev) {
        var el = document.getElementById('avatar-preview');
        if (el.tagName === 'IMG') {
            el.src = ev.target.result;
        } else {
            var img = document.createElement('img');
            img.id = 'avatar-preview';
            img.src = ev.target.result;
            img.alt = '';
            img.style.cssText = 'width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid var(--border);';
            el.parentNode.replaceChild(img, el);
        }
    };
    reader.readAsDataURL(f);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>