<?php
/**
 * contact.php — Contact Us page
 * Form → api/public/contact.php (POST)
 */

$page_title = 'Contact Us — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Get in touch with ' . setting('site_name', 'Webcyno') . ' — support, queries, or partnerships.';

require_once __DIR__ . '/includes/header.php';

$siteEmail = setting('site_email', '');
$sitePhone = setting('site_phone', '');
$siteAddr  = setting('site_address', '');

$socials = [
    'facebook'  => setting('social_facebook', ''),
    'twitter'   => setting('social_twitter', ''),
    'instagram' => setting('social_instagram', ''),
    'linkedin'  => setting('social_linkedin', ''),
    'youtube'   => setting('social_youtube', ''),
];
?>

<main id="main-content">

    <!-- HERO -->
    <section class="hero" style="padding:var(--space-xl) 0;">
        <div class="container text-center" style="max-width:720px;">
            <span class="hero-eyebrow"><span class="dot"></span> <?= e(t('contact', 'Contact')) ?></span>
            <h1 style="font-size:var(--fs-4xl);margin-bottom:var(--space-sm);">
                <?= e(t('get_in_touch', 'Get in Touch')) ?>
            </h1>
            <p class="hero-lead" style="margin:0 auto;">
                <?= e(t('contact_sub', 'প্রশ্ন, সাপোর্ট বা পার্টনারশিপ — যেকোনো বিষয়ে আমাদের লিখুন।')) ?>
            </p>
        </div>
    </section>

    <!-- CONTENT -->
    <section class="section">
        <div class="container">
            <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:var(--space-xl);align-items:start;">

                <!-- ============ FORM ============ -->
                <div class="card" style="padding:var(--space-lg);">
                    <h2 style="font-size:var(--fs-xl);margin-bottom:var(--space-sm);">
                        <?= e(t('send_message', 'Send us a Message')) ?>
                    </h2>
                    <p class="text-muted" style="font-size:var(--fs-sm);margin-bottom:var(--space-lg);">
                        <?= e(t('send_message_sub', 'সাধারণত ২৪ ঘণ্টার মধ্যে উত্তর দিই।')) ?>
                    </p>

                    <form id="contact-form" novalidate autocomplete="on">

                        <!-- Honeypot (bots fill this; humans don't see it) -->
                        <input type="text" name="website" tabindex="-1" autocomplete="off"
                               style="position:absolute;left:-9999px;opacity:0;height:0;width:0;" aria-hidden="true">

                        <div class="grid grid-2" style="gap:var(--space);">
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="name"><?= e(t('full_name', 'Full Name')) ?> <span class="required">*</span></label>
                                <input type="text" class="form-control" id="name" name="name"
                                       placeholder="<?= e(t('name_placeholder', 'Your full name')) ?>"
                                       required autofocus autocomplete="name">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="email"><?= e(t('email', 'Email')) ?> <span class="required">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="you@example.com" required autocomplete="email">
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:var(--space);">
                            <label class="form-label" for="phone"><?= e(t('phone', 'Phone')) ?> <span class="text-muted" style="font-weight:400;">(<?= e(t('optional', 'optional')) ?>)</span></label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   placeholder="01XXXXXXXXX" autocomplete="tel">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="subject"><?= e(t('subject', 'Subject')) ?></label>
                            <select class="form-control" id="subject" name="subject">
                                <option value=""><?= e(t('select_subject', '— Select a subject —')) ?></option>
                                <option value="General Inquiry"><?= e(t('subj_general', 'General Inquiry')) ?></option>
                                <option value="Order Support"><?= e(t('subj_order', 'Order Support')) ?></option>
                                <option value="Payment Issue"><?= e(t('subj_payment', 'Payment Issue')) ?></option>
                                <option value="Refund Request"><?= e(t('subj_refund', 'Refund Request')) ?></option>
                                <option value="Partnership"><?= e(t('subj_partnership', 'Partnership')) ?></option>
                                <option value="Other"><?= e(t('subj_other', 'Other')) ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="message"><?= e(t('message', 'Message')) ?> <span class="required">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="5"
                                      placeholder="<?= e(t('message_placeholder', 'How can we help you?')) ?>"
                                      required maxlength="3000"></textarea>
                            <p class="form-hint" style="text-align:right;">
                                <span id="char-count">0</span>/3000
                            </p>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block" id="contact-submit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            <?= e(t('send_message_btn', 'Send Message')) ?>
                        </button>
                    </form>
                </div>

                <!-- ============ CONTACT INFO ============ -->
                <aside>
                    <div class="card" style="padding:var(--space-lg);margin-bottom:var(--space);">
                        <h3 style="font-size:var(--fs-lg);margin-bottom:var(--space);"><?= e(t('contact_info', 'Contact Information')) ?></h3>

                        <ul style="display:flex;flex-direction:column;gap:var(--space);font-size:var(--fs-sm);">
                            <?php if ($siteEmail): ?>
                            <li class="flex items-start gap-sm">
                                <span style="width:38px;height:38px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;border-radius:var(--radius);flex-shrink:0;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>
                                </span>
                                <div>
                                    <div class="text-muted" style="font-size:var(--fs-xs);"><?= e(t('email', 'Email')) ?></div>
                                    <a href="mailto:<?= e($siteEmail) ?>" style="font-weight:600;color:var(--navy);word-break:break-all;"><?= e($siteEmail) ?></a>
                                </div>
                            </li>
                            <?php endif; ?>

                            <?php if ($sitePhone): ?>
                            <li class="flex items-start gap-sm">
                                <span style="width:38px;height:38px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;border-radius:var(--radius);flex-shrink:0;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                </span>
                                <div>
                                    <div class="text-muted" style="font-size:var(--fs-xs);"><?= e(t('phone', 'Phone')) ?></div>
                                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $sitePhone)) ?>" style="font-weight:600;color:var(--navy);"><?= e($sitePhone) ?></a>
                                </div>
                            </li>
                            <?php endif; ?>

                            <?php if ($siteAddr): ?>
                            <li class="flex items-start gap-sm">
                                <span style="width:38px;height:38px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;border-radius:var(--radius);flex-shrink:0;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                </span>
                                <div>
                                    <div class="text-muted" style="font-size:var(--fs-xs);"><?= e(t('address', 'Address')) ?></div>
                                    <span style="font-weight:600;color:var(--navy);"><?= nl2br(e($siteAddr)) ?></span>
                                </div>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Socials -->
                    <?php if (array_filter($socials)): ?>
                    <div class="card" style="padding:var(--space-lg);margin-bottom:var(--space);">
                        <h3 style="font-size:var(--fs-md);margin-bottom:var(--space);"><?= e(t('follow_us', 'Follow Us')) ?></h3>
                        <div class="flex gap-sm" style="flex-wrap:wrap;">
                            <?php
                            $socIcons = [
                                'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
                                'twitter'   => '<path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>',
                                'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>',
                                'linkedin'  => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
                                'youtube'   => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48"/>',
                            ];
                            foreach ($socials as $key => $url):
                                if (!$url) continue;
                            ?>
                                <a href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($key)) ?>"
                                   style="width:42px;height:42px;background:var(--bg-alt);color:var(--navy);display:flex;align-items:center;justify-content:center;border-radius:var(--radius);transition:all var(--t-fast);"
                                   onmouseover="this.style.background='var(--primary)';this.style.color='#fff';"
                                   onmouseout="this.style.background='var(--bg-alt)';this.style.color='var(--navy)';">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $socIcons[$key] ?></svg>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Response time -->
                    <div class="card" style="padding:var(--space-lg);">
                        <div class="flex items-start gap-sm">
                            <span style="width:38px;height:38px;background:var(--success-soft);color:var(--success);display:flex;align-items:center;justify-content:center;border-radius:var(--radius);flex-shrink:0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </span>
                            <div>
                                <h4 style="font-size:var(--fs-sm);margin-bottom:4px;"><?= e(t('fast_response', 'Fast Response')) ?></h4>
                                <p class="text-muted" style="font-size:var(--fs-xs);">
                                    <?= e(t('fast_response_sub', 'সাধারণত ২৪ ঘণ্টার মধ্যে, ব্যস্ত সময়েও ৪৮ ঘণ্টার বেশি নয়।')) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<style>
@media (max-width: 900px) {
    main#main-content > .section > .container > div[style*="grid-template-columns:1.2fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('contact-form');
    if (!form) return;

    // Char counter
    var msgEl    = document.getElementById('message');
    var charEl   = document.getElementById('char-count');
    if (msgEl && charEl) {
        var update = function () { charEl.textContent = msgEl.value.length; };
        msgEl.addEventListener('input', update);
        update();
    }

    // Submit
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var btn = document.getElementById('contact-submit');

        var payload = {
            name:    form.name.value.trim(),
            email:   form.email.value.trim(),
            phone:   form.phone.value.trim(),
            subject: form.subject.value,
            message: form.message.value.trim(),
            website: form.website.value   // honeypot
        };

        // Client-side validate
        var errors = {};
        if (!payload.name)                          errors.name    = 'Name is required.';
        if (!payload.email)                         errors.email   = 'Email is required.';
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email))
                                                    errors.email   = 'Enter a valid email.';
        if (!payload.message)                       errors.message = 'Message is required.';
        else if (payload.message.length < 10)       errors.message = 'Minimum 10 characters.';

        if (Object.keys(errors).length) {
            showFormErrors(form, errors);
            toast('Please fix the highlighted fields.', 'warning');
            return;
        }
        showFormErrors(form, null);
        btnLoading(btn, true);

        try {
            var res = await API.post('public/contact', payload);
            toast(res.message || 'Message sent successfully!', 'success');
            form.reset();
            if (charEl) charEl.textContent = '0';
        } catch (err) {
            if (err.errors) showFormErrors(form, err.errors);
            toast(err.message || 'Could not send message. Please try again.', 'error');
        } finally {
            btnLoading(btn, false);
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>