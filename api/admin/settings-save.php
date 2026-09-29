<?php
/**
 * api/admin/settings-save.php — Save Settings (partial, per tab) v4.0
 * Method: POST (multipart form-data)
 *
 * Tabs: general | appearance | currency | email | sms | chat | social | seo | domain | orders | broadcast
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$adminId = require_admin();
global $pdo;

/* ---------- Input ---------- */
$tab = trim((string) input('tab', 'general'));

$allowedTabs = ['general','appearance','currency','email','sms','chat','social','seo','domain','orders','broadcast'];
if (!in_array($tab, $allowedTabs, true)) {
    json_error('Invalid settings tab.', 422, ['tab' => 'Unknown tab.']);
}

/* ---------- Define allowed keys per tab ---------- */
$allowedKeys = [
    'general' => [
        'site_name','site_tagline','site_description','site_email','site_phone','site_address',
        'footer_about','footer_copyright',
        'site_logo','site_favicon',
        'subcategory_enabled',          // ✨ NEW
    ],
    'appearance' => ['primary_color','dark_color','active_theme'],
    'currency'   => [
        'default_currency','default_language',
        'currency_symbol_bdt','currency_symbol_usd',
        'currency_bdt_rate','currency_usd_rate',
        // ✨ NEW
        'currency_rate_mode',
        'currency_live_api_key',
        'currency_auto_update',
    ],
    'email' => [
        'smtp_host','smtp_port','smtp_user','smtp_pass',
        'smtp_from_name','smtp_from_email','enable_email_verify',
    ],
    'sms' => ['sms_gateway','sms_api_key','sms_sender_id','sms_enabled','enable_phone_verify'],
    'chat' => [
        'chat_welcome_msg','chat_auto_reply',
        // ✨ NEW
        'chat_enabled',
        'chat_max_image_mb',
        'chat_max_file_mb',
        'chat_cleanup_days',
        'chat_auto_reply_enabled',
        'chat_notify_email',
    ],
    'social' => ['social_facebook','social_twitter','social_instagram','social_linkedin','social_youtube'],
    'seo'    => ['seo_title','seo_description','seo_keywords'],
    'domain' => [
        'domain_enabled',
        'domain_own_enabled',
        'domain_subdomain_enabled',
        'domain_subdomain_suffix',
        'domain_purchase_enabled',
        'domain_tld_com_price',
        'domain_tld_net_price',
        'domain_tld_org_price',
        'domain_tld_xyz_price',
        'domain_tld_bd_price',
        'domain_tld_info_price',
        'domain_tld_store_price',
    ],
    'orders' => [
        'delivery_default_days',
        'delivery_late_alert_hours',
        'trial_enabled',
        'trial_one_per_user',
        'trial_require_login',
        'default_trial_days',
        'default_trial_alert_hours',
        'renewal_enabled',
        'renewal_alert_days_before',
        'allow_custom_duration',
    ],

    // ✨ NEW TAB
    'broadcast' => [
        'broadcast_email_enabled',
        'broadcast_sms_enabled',
        'broadcast_batch_size',
    ],
];

/* ---------- Boolean keys ---------- */
$booleanKeys = [
    'enable_email_verify', 'enable_phone_verify',
    'sms_enabled', 'enable_otp_login',

    // Domain
    'domain_enabled','domain_own_enabled','domain_subdomain_enabled','domain_purchase_enabled',

    // Orders
    'trial_enabled','trial_one_per_user','trial_require_login','renewal_enabled',

    // ✨ NEW
    'subcategory_enabled',
    'currency_auto_update',
    'chat_enabled',
    'chat_auto_reply_enabled',
    'chat_notify_email',
    'broadcast_email_enabled',
    'broadcast_sms_enabled',
];

/* ---------- Numeric keys ---------- */
$numericKeys = [
    'currency_bdt_rate','currency_usd_rate','smtp_port',
    'domain_tld_com_price','domain_tld_net_price','domain_tld_org_price',
    'domain_tld_xyz_price','domain_tld_bd_price','domain_tld_info_price','domain_tld_store_price',
    'delivery_default_days','delivery_late_alert_hours',
    'default_trial_days','default_trial_alert_hours',
    'renewal_alert_days_before',

    // ✨ NEW
    'chat_max_image_mb',
    'chat_max_file_mb',
    'chat_cleanup_days',
    'broadcast_batch_size',
];

/* ---------- Image keys ---------- */
$imageKeys = ['site_logo', 'site_favicon'];
$imageFolder = ['site_logo' => 'services', 'site_favicon' => 'services'];

/* ---------- Sanitize helper ---------- */
function sanitize_setting_value(string $key, $value, array $numericKeys) {
    if (is_array($value)) return '';
    $value = (string) $value;

    if (in_array($key, $numericKeys, true)) {
        $value = preg_replace('/[^0-9\.\-]/', '', $value);
        if ($value === '' || $value === '-' || $value === '.') return '0';
        return $value;
    }

    if (strpos($key, 'social_') === 0 || $key === 'site_email' || $key === 'smtp_from_email') {
        $value = trim($value);
        if ($value === '') return '';
        if ($key === 'site_email' || $key === 'smtp_from_email') {
            $v = filter_var($value, FILTER_VALIDATE_EMAIL);
            return $v ?: '';
        }
        if (!preg_match('#^https?://#i', $value)) {
            if (strpos($value, '.') !== false) $value = 'https://' . ltrim($value, '/');
        }
        return $value;
    }

    if ($key === 'domain_subdomain_suffix') {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9\.\-]/', '', $value);
        return $value;
    }

    // Rate mode whitelist
    if ($key === 'currency_rate_mode') {
        $value = strtolower(trim($value));
        if (!in_array($value, ['manual','live','fixed'], true)) $value = 'manual';
        return $value;
    }

    return trim($value);
}

/* ---------- Fetch existing ---------- */
$existingRows = $pdo->query("SELECT key_name, value, type, group_name FROM settings")->fetchAll();
$existing = [];
foreach ($existingRows as $r) $existing[$r['key_name']] = $r;

/* ---------- Collect ---------- */
$updates = [];
$newUploads = [];

foreach ($allowedKeys[$tab] as $key) {
    if (in_array($key, $imageKeys, true)) continue;

    if (in_array($key, $booleanKeys, true)) {
        $val = (int) input($key, 0) === 1 ? '1' : '0';
        $updates[$key] = $val;
        continue;
    }

    $val = input($key, null);
    if ($val === null) continue;
    $updates[$key] = sanitize_setting_value($key, $val, $numericKeys);
}

/* ---------- Image uploads ---------- */
if ($tab === 'general') {
    foreach ($imageKeys as $key) {
        if (empty($_FILES[$key]['tmp_name'])) continue;
        $uploaded = upload_image($_FILES[$key], $imageFolder[$key], 3);
        if (!$uploaded) {
            json_error("{$key} upload failed. JPG/PNG/WEBP/SVG under 3MB.", 422, [$key => 'Invalid image.']);
        }
        $updates[$key] = $uploaded;
        $newUploads[$key] = $uploaded;
    }
}

/* ---------- SMTP password — skip if unchanged ---------- */
if ($tab === 'email' && isset($updates['smtp_pass'])) {
    $newPass = (string) $updates['smtp_pass'];
    $oldPass = (string) ($existing['smtp_pass']['value'] ?? '');
    if ($newPass === '' || $newPass === $oldPass) {
        unset($updates['smtp_pass']);
    }
}

/* ---------- ✨ Currency: if rate mode = 'live', try to fetch now ---------- */
if ($tab === 'currency' && ($updates['currency_rate_mode'] ?? '') === 'live') {
    $liveRate = fetch_live_usd_rate();
    if ($liveRate && $liveRate > 0) {
        $updates['currency_usd_rate'] = (string) round($liveRate, 2);
        $updates['currency_last_sync_at'] = date('Y-m-d H:i:s');
    }
}

/* ---------- No changes ---------- */
if (empty($updates)) {
    json_error('No changes to save.', 422);
}

/* ---------- Save in transaction ---------- */
try {
    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("
        INSERT INTO settings (key_name, value, type, group_name)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");

    foreach ($updates as $key => $value) {
        $type      = $existing[$key]['type']       ?? guess_setting_type($key);
        $groupName = $existing[$key]['group_name'] ?? $tab;
        $insertStmt->execute([$key, (string) $value, $type, $groupName]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($newUploads as $u) delete_upload($u);
    if (WCB_ENV === 'development') json_error('Save failed: ' . $e->getMessage(), 500);
    json_error('Could not save settings. Please try again.', 500);
}

/* ---------- Delete previous logo/favicon ---------- */
if ($tab === 'general') {
    foreach ($imageKeys as $key) {
        if (!empty($updates[$key]) && !empty($existing[$key]['value']) && $existing[$key]['value'] !== $updates[$key]) {
            delete_upload($existing[$key]['value']);
        }
    }
}

/* ---------- Clear caches ---------- */
try {
    cache_forget('public_settings_v1');
    cache_forget('payment_methods_v1');
    cache_forget('domain_pricing_v1');
    cache_forget('currency_rate_v1');
} catch (Exception $e) { /* silent */ }

/* ---------- Activity log ---------- */
try {
    $changedKeys = array_keys($updates);
    log_activity(
        'settings_updated',
        'settings',
        null,
        'By admin #' . $adminId . ' — tab: ' . $tab . ' — keys: ' . implode(', ', $changedKeys)
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'tab'          => $tab,
    'updated_keys' => array_keys($updates),
    'redirect'     => base_url('admin/settings.php?tab=' . $tab),
], 'Settings saved successfully.');

/* ========================================================
   Helper — guess setting type
   ======================================================== */
function guess_setting_type(string $key): string {
    if (strpos($key, 'enable_') === 0
        || strpos($key, 'sms_enabled') === 0
        || strpos($key, 'subcategory_enabled') === 0
        || strpos($key, 'currency_auto_update') === 0
        || strpos($key, 'chat_enabled') === 0
        || strpos($key, 'chat_auto_reply_enabled') === 0
        || strpos($key, 'chat_notify_email') === 0
        || strpos($key, 'broadcast_email_enabled') === 0
        || strpos($key, 'broadcast_sms_enabled') === 0
    ) return 'boolean';
    if (strpos($key, 'domain_enabled') === 0
        || strpos($key, 'domain_own') === 0
        || strpos($key, 'domain_subdomain_enabled') === 0
        || strpos($key, 'domain_purchase') === 0
        || strpos($key, 'trial_enabled') === 0
        || strpos($key, 'trial_one') === 0
        || strpos($key, 'trial_require') === 0
        || strpos($key, 'renewal_enabled') === 0
    ) return 'boolean';
    if (preg_match('/rate|port|_count|_limit|_size|_days|_hours|_price|_before|_mb|_batch/', $key)) return 'number';
    if (preg_match('/logo|favicon|image|_img/', $key))  return 'image';
    if (preg_match('/description|about|content|message|note|policy|auto_reply|welcome/', $key)) return 'textarea';
    if (preg_match('/json/', $key))                    return 'json';
    return 'text';
}