<?php
/**
 * api/public/contact.php — Contact Form Submission
 * Method: POST
 * Body: { name, email, subject?, message, phone? }
 *
 * Actions:
 *  - Save as admin notification
 *  - Send email to site admin
 *  - Optional: save to activity_logs
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

/* ---------- Input ---------- */
$name    = clean(input('name', ''), 100);
$email   = clean_email(input('email', ''));
$phone   = clean_phone(input('phone', ''));
$subject = clean(input('subject', ''), 200);
$message = clean(input('message', ''), 3000);

/* ---------- Validate ---------- */
$errors = [];
if ($name === '')                    $errors['name']    = 'Name is required.';
elseif (mb_strlen($name) < 2)        $errors['name']    = 'Name must be at least 2 characters.';
if (!$email)                         $errors['email']   = 'A valid email is required.';
if ($message === '')                 $errors['message'] = 'Message is required.';
elseif (mb_strlen($message) < 10)    $errors['message'] = 'Message must be at least 10 characters.';

if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Rate limit (3 per 10 min per IP) ---------- */
$rlKey = 'contact_' . md5(client_ip());
$now   = time();
$win   = 600;
$max   = 3;

if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 1, 'reset' => $now + $win];
} else {
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > $max) {
        $wait = $_SESSION[$rlKey]['reset'] - $now;
        header('Retry-After: ' . max(1, $wait));
        json_error('Too many messages. Please try again in ' . ceil($wait / 60) . ' minute(s).', 429);
    }
}

/* ---------- Honeypot / Spam guard ---------- */
// Client form-এ hidden "website" field থাকলে bot সেটা পূরণ করে — আমরা silently reject করি
if (!empty(input('website'))) {
    // Bot detected — success response দিয়ে ভুইয়া দিই
    json_success([], 'Message sent successfully.');
}

/* ---------- Save admin notification ---------- */
try {
    $subjText = $subject !== '' ? $subject : 'New contact message';
    push_notification(
        'admin',
        null,
        'Contact: ' . str_limit($subjText, 80),
        $name . ' (' . $email . ($phone ? ' • ' . $phone : '') . '): ' . str_limit($message, 160),
        'admin/notifications.php',
        'message'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Send email to site admin ---------- */
try {
    $siteName = setting('site_name', 'Webcyno');
    $adminEmail = setting('site_email', '');
    if ($adminEmail) {
        $mailSubject = '[' . $siteName . '] ' . ($subject !== '' ? $subject : 'New contact message');

        $body = '
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#0F172A;">
                <h2 style="color:#2563EB;margin:0 0 16px;">' . e($siteName) . ' — Contact Form</h2>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr><td style="padding:6px 0;color:#64748B;width:110px;">Name</td><td style="font-weight:600;">' . e($name) . '</td></tr>
                    <tr><td style="padding:6px 0;color:#64748B;">Email</td><td><a href="mailto:' . e($email) . '">' . e($email) . '</a></td></tr>
                    ' . ($phone ? '<tr><td style="padding:6px 0;color:#64748B;">Phone</td><td>' . e($phone) . '</td></tr>' : '') . '
                    ' . ($subject ? '<tr><td style="padding:6px 0;color:#64748B;">Subject</td><td>' . e($subject) . '</td></tr>' : '') . '
                </table>
                <hr style="border:none;border-top:1px solid #E2E8F0;margin:16px 0;">
                <div style="white-space:pre-wrap;font-size:14px;line-height:1.6;">' . nl2br(e($message)) . '</div>
                <hr style="border:none;border-top:1px solid #E2E8F0;margin:20px 0;">
                <p style="font-size:12px;color:#94A3B8;">Sent from ' . base_url('contact.php') . ' — IP: ' . client_ip() . '</p>
            </div>';

        send_email($adminEmail, $mailSubject, $body, $name);
    }
} catch (Exception $e) { /* silent */ }

/* ---------- Optional auto-reply to user ---------- */
try {
    $siteName = setting('site_name', 'Webcyno');
    $autoReply = '
        <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#0F172A;">
            <h2 style="color:#2563EB;margin:0 0 12px;">' . e($siteName) . '</h2>
            <p>Hi ' . e($name) . ',</p>
            <p>ধন্যবাদ আপনার message পাঠানোর জন্য। আমরা শীঘ্রই যোগাযোগ করব — সাধারণত ২৪ ঘণ্টার মধ্যে।</p>
            <p style="font-size:13px;color:#64748B;">আপনার পাঠানো message-এর সারাংশ:</p>
            <blockquote style="border-left:3px solid #E2E8F0;padding-left:12px;color:#64748B;font-size:13px;">' . nl2br(e(str_limit($message, 400))) . '</blockquote>
            <hr style="border:none;border-top:1px solid #E2E8F0;margin:20px 0;">
            <p style="font-size:12px;color:#94A3B8;">' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '</p>
        </div>';
    send_email($email, 'We received your message — ' . $siteName, $autoReply, $name);
} catch (Exception $e) { /* silent */ }

/* ---------- Activity log ---------- */
try {
    log_activity('contact_form', 'contact', null, $name . ' <' . $email . '>: ' . str_limit($subject ?: $message, 120));
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'received_at' => date('c'),
], 'Thank you! Your message has been sent. We will get back to you soon.');