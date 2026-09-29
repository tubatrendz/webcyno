<?php
/**
 * admin/settings.php — Site Settings (tabbed) v4.0
 * Tabs: General / Appearance / Currency / Email / SMS / Chat / Social / SEO / Domain / Orders / Broadcast
 */

$admin_page_title = 'Settings';
$admin_active     = 'settings';
require_once __DIR__ . '/includes/admin-header.php';

/* ---------- Load all settings ---------- */
$allSettings = [];
try {
    $rows = $pdo->query("SELECT key_name, value, type, group_name FROM settings")->fetchAll();
    foreach ($rows as $r) $allSettings[$r['key_name']] = $r;
} catch (Exception $e) { /* silent */ }

function s(string $key, $default = '') {
    global $allSettings;
    return $allSettings[$key]['value'] ?? $default;
}

$tab = (string) input('tab', 'general');
$allowedTabs = ['general','appearance','currency','email','sms','chat','social','seo','domain','orders','broadcast'];
if (!in_array($tab, $allowedTabs, true)) $tab = 'general';

$tabsList = [
    'general'    => 'General',
    'appearance' => 'Appearance',
    'currency'   => '💱 Currency',
    'email'      => 'Email / SMTP',
    'sms'        => 'SMS',
    'chat'       => '💬 Chat',
    'social'     => 'Social Links',
    'seo'        => 'SEO',
    'domain'     => '🌐 Domain',
    'orders'     => '📦 Orders',
    'broadcast'  => '📢 Broadcast',
];
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <span class="current">Settings</span>
        </nav>
        <h1 class="admin-page-title">Settings</h1>
        <p class="admin-page-sub">সাইটের সব কনফিগারেশন এখান থেকে পরিবর্তন করুন।</p>
    </div>
</div>

<!-- ============ TABS ============ -->
<div class="admin-tabs" style="margin-bottom:20px;flex-wrap:wrap;">
    <?php foreach ($tabsList as $key => $label): ?>
        <a href="<?= e(admin_url('settings.php?tab=' . urlencode($key))) ?>"
           class="admin-tab<?= $tab === $key ? ' active' : '' ?>">
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<form id="settings-form" data-ajax data-endpoint="admin/settings/save" data-redirect="admin/settings.php?tab=<?= e($tab) ?>" enctype="multipart/form-data">

    <input type="hidden" name="tab" value="<?= e($tab) ?>">

    <!-- ============================================================
         GENERAL
         ============================================================ -->
    <?php if ($tab === 'general'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Site Info</h3></div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Site Name <span class="required">*</span></label>
                    <input type="text" name="site_name" class="form-control" value="<?= e(s('site_name', 'Webcyno')) ?>" required maxlength="100">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Tagline</label>
                    <input type="text" name="site_tagline" class="form-control" value="<?= e(s('site_tagline')) ?>" maxlength="150">
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Site Description</label>
                <textarea name="site_description" class="form-control" rows="3" maxlength="500"><?= e(s('site_description')) ?></textarea>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Contact Email</label>
                    <input type="email" name="site_email" class="form-control" value="<?= e(s('site_email')) ?>" maxlength="150">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Contact Phone</label>
                    <input type="text" name="site_phone" class="form-control" value="<?= e(s('site_phone')) ?>" maxlength="30">
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Address</label>
                <textarea name="site_address" class="form-control" rows="2" maxlength="300"><?= e(s('site_address')) ?></textarea>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Footer About</label>
                    <textarea name="footer_about" class="form-control" rows="2" maxlength="500"><?= e(s('footer_about')) ?></textarea>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Footer Copyright</label>
                    <input type="text" name="footer_copyright" class="form-control" value="<?= e(s('footer_copyright')) ?>" maxlength="200">
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Features</h3></div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="subcategory_enabled" value="1" <?= (int) s('subcategory_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span><strong>Sub-category চালু রাখুন</strong> — Service add করার সময় sub-category select করতে পারবেন</span>
                </label>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Branding</h3></div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Logo</label>
                    <div class="flex items-center gap-md">
                        <img id="logo-preview" src="<?= e(s('site_logo') ? base_url(s('site_logo')) : base_url('assets/images/placeholder.png')) ?>" alt="" style="width:80px;height:80px;object-fit:contain;border-radius:8px;border:1px solid var(--border);background:#fff;padding:6px;">
                        <div style="flex:1;">
                            <input type="file" name="site_logo" accept="image/*" data-preview="#logo-preview" class="form-control" style="padding:6px;">
                            <p class="admin-help">PNG/SVG recommended. Max 2MB.</p>
                        </div>
                    </div>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Favicon</label>
                    <div class="flex items-center gap-md">
                        <img id="favicon-preview" src="<?= e(s('site_favicon') ? base_url(s('site_favicon')) : base_url('assets/icons/favicon.png')) ?>" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:6px;border:1px solid var(--border);background:#fff;padding:4px;">
                        <div style="flex:1;">
                            <input type="file" name="site_favicon" accept="image/*" data-preview="#favicon-preview" class="form-control" style="padding:6px;">
                            <p class="admin-help">ICO or PNG 32×32.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         APPEARANCE
         ============================================================ -->
    <?php if ($tab === 'appearance'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Theme Colors</h3></div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Primary Color</label>
                    <div class="flex items-center gap-sm">
                        <input type="color" name="primary_color" value="<?= e(s('primary_color', '#2563EB')) ?>" style="width:52px;height:42px;border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                        <input type="text" class="form-control" value="<?= e(s('primary_color', '#2563EB')) ?>" readonly style="background:var(--bg-alt);">
                    </div>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Dark (Navy) Color</label>
                    <div class="flex items-center gap-sm">
                        <input type="color" name="dark_color" value="<?= e(s('dark_color', '#0F172A')) ?>" style="width:52px;height:42px;border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                        <input type="text" class="form-control" value="<?= e(s('dark_color', '#0F172A')) ?>" readonly style="background:var(--bg-alt);">
                    </div>
                </div>
            </div>
            <div class="admin-form-group">
                <label class="admin-label">Active Theme</label>
                <select name="active_theme" class="form-control">
                    <option value="default" <?= s('active_theme') === 'default' ? 'selected' : '' ?>>Default (Blue + Navy)</option>
                    <option value="minimal" <?= s('active_theme') === 'minimal' ? 'selected' : '' ?>>Minimal (coming soon)</option>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         ✨ CURRENCY (v4.0 — enhanced)
         ============================================================ -->
    <?php if ($tab === 'currency'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Currency Settings</h3></div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Default Currency</label>
                    <select name="default_currency" class="form-control">
                        <option value="BDT" <?= s('default_currency', 'BDT') === 'BDT' ? 'selected' : '' ?>>BDT ৳ (Bangladeshi Taka)</option>
                        <option value="USD" <?= s('default_currency') === 'USD' ? 'selected' : '' ?>>USD $ (US Dollar)</option>
                    </select>
                    <p class="admin-help">Guest user এর জন্য এটা default। Login করলে user এর preference apply হবে।</p>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Default Language</label>
                    <select name="default_language" class="form-control">
                        <option value="bn" <?= s('default_language', 'bn') === 'bn' ? 'selected' : '' ?>>বাংলা</option>
                        <option value="en" <?= s('default_language') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">BDT Symbol</label>
                    <input type="text" name="currency_symbol_bdt" class="form-control" value="<?= e(s('currency_symbol_bdt', '৳')) ?>" maxlength="5">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">USD Symbol</label>
                    <input type="text" name="currency_symbol_usd" class="form-control" value="<?= e(s('currency_symbol_usd', '$')) ?>" maxlength="5">
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Exchange Rate</h3></div>

            <div style="background:#EFF6FF;border:1px solid #BFDBFE;padding:14px;border-radius:8px;margin-bottom:16px;font-size:13px;color:#1E40AF;">
                💡 <strong>কীভাবে কাজ করে:</strong> Product সব BDT তে save হয়। User USD select করলে এই rate দিয়ে automatically convert হয়ে দেখাবে।
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Rate Mode</label>
                <select name="currency_rate_mode" class="form-control">
                    <option value="manual" <?= s('currency_rate_mode', 'manual') === 'manual' ? 'selected' : '' ?>>Manual — আমি নিজে rate লিখব</option>
                    <option value="live"   <?= s('currency_rate_mode') === 'live'   ? 'selected' : '' ?>>Live — Auto fetch from API</option>
                </select>
                <p class="admin-help">Manual = প্রতি সপ্তাহে নিজে update করবেন। Live = page save করলেই fresh rate নিয়ে আসবে।</p>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Base Rate (1 BDT = ?)</label>
                    <input type="text" name="currency_bdt_rate" class="form-control" value="<?= e(s('currency_bdt_rate', '1')) ?>" data-numeric>
                    <p class="admin-help">সাধারণত 1 রাখুন</p>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">USD Rate (1 USD = ? BDT)</label>
                    <input type="text" name="currency_usd_rate" class="form-control" value="<?= e(s('currency_usd_rate', '110')) ?>" data-numeric>
                    <p class="admin-help">উদাহরণ: 110 (তাহলে ৳1100 → $10)</p>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Live API Key (optional)</label>
                <input type="text" name="currency_live_api_key" class="form-control" value="<?= e(s('currency_live_api_key')) ?>" placeholder="exchangerate-api.com key (ফ্রি)">
                <p class="admin-help">
                    ফ্রি key পাবেন: <a href="https://www.exchangerate-api.com/" target="_blank" rel="noopener">exchangerate-api.com</a><br>
                    আমরা default ফ্রি endpoint ব্যবহার করি — API key ছাড়াও কাজ করতে পারে।
                </p>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="currency_auto_update" value="1" <?= (int) s('currency_auto_update', 0) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span><strong>Auto update rate daily</strong> — চাইলে cron এ daily update হবে</span>
                </label>
            </div>

            <?php if (s('currency_last_sync_at')): ?>
                <div style="margin-top:12px;padding:10px 12px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;font-size:12.5px;color:#166534;">
                    ✓ Last synced: <strong><?= e(date('M d, Y g:i A', strtotime(s('currency_last_sync_at')))) ?></strong>
                </div>
            <?php endif; ?>

            <!-- Live preview -->
            <div style="margin-top:20px;padding:16px;background:linear-gradient(135deg,#F8FAFC,#EFF6FF);border-radius:10px;border:1px solid #E2E8F0;">
                <div style="font-size:12px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">📊 Live Preview</div>
                <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:14px;">
                    <div>
                        <span class="text-muted">৳1,000 =</span>
                        <strong style="color:var(--navy);" id="preview-usd">$<?= number_format(1000 / max((float)s('currency_usd_rate', 110), 1), 2) ?></strong>
                    </div>
                    <div>
                        <span class="text-muted">$100 =</span>
                        <strong style="color:var(--navy);" id="preview-bdt">৳<?= number_format(100 * (float)s('currency_usd_rate', 110), 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.querySelector('input[name="currency_usd_rate"]')?.addEventListener('input', function () {
            var rate = parseFloat(this.value) || 0;
            if (rate <= 0) return;
            document.getElementById('preview-usd').textContent = '$' + (1000 / rate).toFixed(2);
            document.getElementById('preview-bdt').textContent = '৳' + (100 * rate).toFixed(2);
        });
        </script>
    <?php endif; ?>

    <!-- ============================================================
         EMAIL / SMTP (unchanged)
         ============================================================ -->
    <?php if ($tab === 'email'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">SMTP Configuration</h3></div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control" value="<?= e(s('smtp_host')) ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">SMTP Port</label>
                    <input type="text" name="smtp_port" class="form-control" value="<?= e(s('smtp_port', '465')) ?>" maxlength="6">
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">SMTP Username</label>
                    <input type="text" name="smtp_user" class="form-control" value="<?= e(s('smtp_user')) ?>" autocomplete="off">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">SMTP Password</label>
                    <input type="password" name="smtp_pass" class="form-control" value="<?= e(s('smtp_pass')) ?>" autocomplete="new-password">
                    <p class="admin-help">পরিবর্তন না করলে খালি রাখুন।</p>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">From Name</label>
                    <input type="text" name="smtp_from_name" class="form-control" value="<?= e(s('smtp_from_name', 'Webcyno')) ?>" maxlength="100">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">From Email</label>
                    <input type="email" name="smtp_from_email" class="form-control" value="<?= e(s('smtp_from_email')) ?>" maxlength="150">
                </div>
            </div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="enable_email_verify" value="1" <?= (int) s('enable_email_verify', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Require email verification for new users</span>
                </label>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         SMS
         ============================================================ -->
    <?php if ($tab === 'sms'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">SMS Gateway</h3></div>

            <div style="background:#FEF3C7;border:1px solid #FDE68A;padding:12px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;color:#92400E;">
                ⚠️ <strong>SMS gateway এখনো সেটআপ করা হয়নি।</strong> BulkSMSBD এ account খুলে API Key + Sender ID দিলে SMS কাজ করবে। ততক্ষণ শুধু লগ হবে।
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Gateway</label>
                <select name="sms_gateway" class="form-control">
                    <option value="none" <?= s('sms_gateway', 'none') === 'none' ? 'selected' : '' ?>>Disabled</option>
                    <option value="bulksmsbd" <?= s('sms_gateway') === 'bulksmsbd' ? 'selected' : '' ?>>BulkSMSBD</option>
                    <option value="sslwireless" <?= s('sms_gateway') === 'sslwireless' ? 'selected' : '' ?>>SSL Wireless</option>
                    <option value="custom" <?= s('sms_gateway') === 'custom' ? 'selected' : '' ?>>Custom / Other</option>
                </select>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">API Key</label>
                    <input type="text" name="sms_api_key" class="form-control" value="<?= e(s('sms_api_key')) ?>" autocomplete="off">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Sender ID</label>
                    <input type="text" name="sms_sender_id" class="form-control" value="<?= e(s('sms_sender_id')) ?>" maxlength="30">
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="sms_enabled" value="1" <?= (int) s('sms_enabled', 0) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Enable SMS notifications</span>
                </label>
            </div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="enable_phone_verify" value="1" <?= (int) s('enable_phone_verify', 0) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Require phone verification</span>
                </label>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         ✨ CHAT (v4.0 — enhanced)
         ============================================================ -->
    <?php if ($tab === 'chat'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Live Chat</h3></div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="chat_enabled" value="1" <?= (int) s('chat_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span><strong>Live chat চালু করুন</strong> — user admin এর সাথে chat করতে পারবে</span>
                </label>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="chat_auto_reply_enabled" value="1" <?= (int) s('chat_auto_reply_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Auto reply (admin offline থাকলে)</span>
                </label>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="chat_notify_email" value="1" <?= (int) s('chat_notify_email', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>নতুন message এলে admin কে email পাঠাও</span>
                </label>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Messages</h3></div>

            <div class="admin-form-group">
                <label class="admin-label">Welcome Message</label>
                <textarea name="chat_welcome_msg" class="form-control" rows="2" maxlength="500"><?= e(s('chat_welcome_msg')) ?></textarea>
                <p class="admin-help">Chat widget খুললে user এটা দেখবে।</p>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Auto Reply Message</label>
                <textarea name="chat_auto_reply" class="form-control" rows="3" maxlength="500"><?= e(s('chat_auto_reply')) ?></textarea>
                <p class="admin-help">Admin offline থাকলে এটা auto reply যাবে।</p>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">File Sharing & Cleanup</h3></div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Max Image Size (MB)</label>
                    <input type="number" name="chat_max_image_mb" class="form-control" min="1" max="50" value="<?= e(s('chat_max_image_mb', '5')) ?>">
                    <p class="admin-help">User কত MB পর্যন্ত image পাঠাতে পারবে</p>
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Max File Size (MB)</label>
                    <input type="number" name="chat_max_file_mb" class="form-control" min="1" max="200" value="<?= e(s('chat_max_file_mb', '20')) ?>">
                    <p class="admin-help">User কত MB পর্যন্ত file পাঠাতে পারবে</p>
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Auto-delete After (days)</label>
                <input type="number" name="chat_cleanup_days" class="form-control" min="1" max="365" value="<?= e(s('chat_cleanup_days', '7')) ?>">
                <p class="admin-help">
                    এই কত দিন পর chat message + attachment auto delete হবে।
                    <br>উদাহরণ: <code>7</code> → 1 তারিখের chat 8 তারিখে delete হবে
                </p>
            </div>

            <div style="background:#F0FDF4;border:1px solid #BBF7D0;padding:12px 14px;border-radius:8px;font-size:13px;color:#166534;margin-top:12px;">
                💡 <strong>কীভাবে cleanup কাজ করে:</strong> Cron daily চলে cutoff date এর আগের সব chat delete করে। উদাহরণ: আজ 8 তারিখ, cutoff = 1 তারিখ → 1 তারিখের সব chat delete হবে।
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         SOCIAL (unchanged)
         ============================================================ -->
    <?php if ($tab === 'social'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Social Media Links</h3></div>
            <?php foreach ([
                'social_facebook'  => 'Facebook',
                'social_twitter'   => 'Twitter / X',
                'social_instagram' => 'Instagram',
                'social_linkedin'  => 'LinkedIn',
                'social_youtube'   => 'YouTube',
            ] as $key => $label): ?>
                <div class="admin-form-group">
                    <label class="admin-label"><?= e($label) ?></label>
                    <input type="url" name="<?= e($key) ?>" class="form-control" value="<?= e(s($key)) ?>" maxlength="200">
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         SEO (unchanged)
         ============================================================ -->
    <?php if ($tab === 'seo'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Default SEO Meta</h3></div>
            <div class="admin-form-group">
                <label class="admin-label">SEO Title</label>
                <input type="text" name="seo_title" class="form-control" value="<?= e(s('seo_title')) ?>" maxlength="200">
            </div>
            <div class="admin-form-group">
                <label class="admin-label">SEO Description</label>
                <textarea name="seo_description" class="form-control" rows="3" maxlength="300"><?= e(s('seo_description')) ?></textarea>
            </div>
            <div class="admin-form-group">
                <label class="admin-label">SEO Keywords</label>
                <input type="text" name="seo_keywords" class="form-control" value="<?= e(s('seo_keywords')) ?>" maxlength="300">
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         DOMAIN (unchanged from v3)
         ============================================================ -->
    <?php if ($tab === 'domain'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Domain Feature</h3></div>

            <div style="background:#EFF6FF;border:1px solid #BFDBFE;padding:12px 14px;border-radius:8px;margin-bottom:16px;font-size:13px;color:#1E40AF;">
                💡 Order করার সময় user ৩টি option পাবে: নিজের domain, free subdomain, বা আমাদের থেকে কিনা।
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="domain_enabled" value="1" <?= (int) s('domain_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span><strong>Domain feature চালু করুন</strong> (master switch)</span>
                </label>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="domain_own_enabled" value="1" <?= (int) s('domain_own_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>✅ User নিজের domain add করতে পারবে (ফ্রি)</span>
                </label>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="domain_subdomain_enabled" value="1" <?= (int) s('domain_subdomain_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>🆓 Free subdomain</span>
                </label>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Subdomain Suffix</label>
                <input type="text" name="domain_subdomain_suffix" class="form-control" value="<?= e(s('domain_subdomain_suffix', 'webcyno.com')) ?>" maxlength="100" placeholder="webcyno.com">
                <p class="admin-help">User পাবে: <code>his-name.<span id="suffix-preview"><?= e(s('domain_subdomain_suffix', 'webcyno.com')) ?></span></code></p>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="domain_purchase_enabled" value="1" <?= (int) s('domain_purchase_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>💳 আমাদের থেকে domain কিনতে পারবে</span>
                </label>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">TLD Pricing (per year)</h3></div>
            <?php foreach ([
                'com' => '.com', 'net' => '.net', 'org' => '.org', 'xyz' => '.xyz',
                'bd' => '.bd', 'info' => '.info', 'store' => '.store',
            ] as $tld => $label):
                $key = 'domain_tld_' . $tld . '_price';
            ?>
                <div class="admin-form-group">
                    <label class="admin-label"><?= e($label) ?> — <?= e(current_currency()) ?></label>
                    <input type="text" name="<?= e($key) ?>" class="form-control" value="<?= e(s($key, '0')) ?>" data-numeric>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
        document.querySelector('input[name="domain_subdomain_suffix"]')?.addEventListener('input', function () {
            var pv = document.getElementById('suffix-preview');
            if (pv) pv.textContent = this.value || 'webcyno.com';
        });
        </script>
    <?php endif; ?>

    <!-- ============================================================
         ORDERS (unchanged from v3)
         ============================================================ -->
    <?php if ($tab === 'orders'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Delivery</h3></div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Default Delivery Days</label>
                    <input type="number" name="delivery_default_days" class="form-control" min="1" max="90" value="<?= e(s('delivery_default_days', '3')) ?>">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Late Alert (Hours before due)</label>
                    <input type="number" name="delivery_late_alert_hours" class="form-control" min="1" max="240" value="<?= e(s('delivery_late_alert_hours', '12')) ?>">
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Trial Settings</h3></div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="trial_enabled" value="1" <?= (int) s('trial_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Trial system চালু রাখুন</span>
                </label>
            </div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="trial_one_per_user" value="1" <?= (int) s('trial_one_per_user', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>প্রতি user প্রতি service এ একবারই trial</span>
                </label>
            </div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="trial_require_login" value="1" <?= (int) s('trial_require_login', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Trial এ login বাধ্যতামূলক</span>
                </label>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Default Trial Days</label>
                    <input type="number" name="default_trial_days" class="form-control" min="1" max="365" value="<?= e(s('default_trial_days', '7')) ?>">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Trial Alert (Hours before)</label>
                    <input type="number" name="default_trial_alert_hours" class="form-control" min="1" max="720" value="<?= e(s('default_trial_alert_hours', '48')) ?>">
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Renewal Settings</h3></div>
            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="renewal_enabled" value="1" <?= (int) s('renewal_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span>Renewal reminder চালু রাখুন</span>
                </label>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label class="admin-label">Renewal Alert (Days before)</label>
                    <input type="number" name="renewal_alert_days_before" class="form-control" min="1" max="60" value="<?= e(s('renewal_alert_days_before', '7')) ?>">
                </div>
                <div class="admin-form-group">
                    <label class="admin-label">Allow Custom Duration</label>
                    <select name="allow_custom_duration" class="form-control">
                        <option value="1" <?= (int) s('allow_custom_duration', 1) === 1 ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= (int) s('allow_custom_duration', 1) === 0 ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         ✨ BROADCAST (NEW TAB)
         ============================================================ -->
    <?php if ($tab === 'broadcast'): ?>
        <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">📢 Broadcast Defaults</h3></div>

            <div style="background:#EFF6FF;border:1px solid #BFDBFE;padding:14px;border-radius:8px;margin-bottom:16px;font-size:13px;color:#1E40AF;">
                💡 <strong>Broadcast কী?</strong> Admin panel থেকে সব user বা selective user দের কাছে message / offer পাঠানো। প্রতিটা message user এর account এ (notification bell) ও email এ যাবে। SMS চাইলে পরে gateway setup করলে এখান থেকে চালু করা যাবে।
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="broadcast_email_enabled" value="1" <?= (int) s('broadcast_email_enabled', 1) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span><strong>Email চ্যানেল চালু</strong> — broadcast email এ পাঠাবে</span>
                </label>
            </div>

            <div class="admin-form-group">
                <label class="admin-switch">
                    <input type="checkbox" name="broadcast_sms_enabled" value="1" <?= (int) s('broadcast_sms_enabled', 0) === 1 ? 'checked' : '' ?>>
                    <span class="admin-switch-track"></span>
                    <span><strong>SMS চ্যানেল চালু</strong> — SMS gateway setup থাকলে কাজ করবে</span>
                </label>
                <?php if ((int) s('sms_enabled', 0) !== 1): ?>
                    <p class="admin-help" style="color:#B45309;">⚠️ SMS gateway এখনো disable। আগে SMS tab এ configure করুন।</p>
                <?php endif; ?>
            </div>

            <div class="admin-form-group">
                <label class="admin-label">Email Batch Size</label>
                <input type="number" name="broadcast_batch_size" class="form-control" min="10" max="500" value="<?= e(s('broadcast_batch_size', '50')) ?>">
                <p class="admin-help">একবারে কত email পাঠাবে (hosting limit অনুযায়ী)। উদাহরণ: 50 → প্রতি batch এ 50 email, 2 সেকেন্ড gap।</p>
            </div>
        </div>

        <div class="admin-card" style="padding:0;">
            <div style="padding:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h3 style="margin:0 0 6px;font-size:16px;">📨 Broadcast তৈরি করুন</h3>
                        <p class="text-muted" style="margin:0;font-size:13px;">নতুন message পাঠাতে Broadcast page এ যান</p>
                    </div>
                    <a href="<?= e(admin_url('broadcast.php')) ?>" class="admin-btn admin-btn-primary">
                        📢 Open Broadcast Center →
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============ SAVE BAR ============ -->
    <div class="admin-card save-bar">
        <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:12px;">
            <div class="admin-cell-sub">পরিবর্তন করার পর Save চাপুন।</div>
            <div class="flex gap-sm">
                <button type="reset" class="admin-btn admin-btn-ghost">Reset Form</button>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Save Settings
                </button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="color"]').forEach(function (picker) {
        picker.addEventListener('input', function () {
            var text = picker.parentNode.querySelector('input[type="text"]');
            if (text) text.value = picker.value;
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>