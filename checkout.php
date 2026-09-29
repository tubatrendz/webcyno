<?php
/**
 * checkout.php — Checkout Page (v3.0 with Domain Selection + Renewal)
 * Supports:
 *   - Normal checkout from cart
 *   - Renewal checkout (via ?renew_item_id=X)
 *   - Domain selection (own / free subdomain / purchased)
 */

require_once __DIR__ . '/includes/header.php';

/* ---------- Auth ---------- */
if (empty($_SESSION['user_id'])) {
    $redirectQuery = $_SERVER['QUERY_STRING'] ?? '';
    $redir = 'checkout.php' . ($redirectQuery ? '?' . $redirectQuery : '');
    header('Location: ' . base_url('login.php?redirect=' . urlencode($redir)));
    exit;
}

$user = current_user();
if (!$user || $user['status'] !== 'active') {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . base_url('login.php'));
    exit;
}

global $pdo;

/* ========================================================
   RENEWAL MODE
   ======================================================== */
$renewItemId   = (int) input('renew_item_id', 0);
$isRenewalMode = false;
$renewItem     = null;
$renewalMeta   = null;

if ($renewItemId > 0) {
    $rStmt = $pdo->prepare("
        SELECT oi.*, o.order_number AS original_order_number, o.user_id,
               s.title_en, s.title_bn, s.slug, s.thumbnail, s.currency, s.status AS svc_status
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        LEFT JOIN services s ON s.id = oi.service_id
        WHERE oi.id = ? AND o.user_id = ?
        LIMIT 1
    ");
    $rStmt->execute([$renewItemId, (int)$user['id']]);
    $renewItem = $rStmt->fetch();

    if (!$renewItem) { header('Location: ' . base_url('user/orders.php')); exit; }
    if (($renewItem['package_type'] ?? '') === 'lifetime') {
        header('Location: ' . base_url('user/order-details.php?id=' . (int)$renewItem['order_id']));
        exit;
    }
    if (($renewItem['package_type'] ?? '') === 'trial') {
        header('Location: ' . base_url('user/order-details.php?id=' . (int)$renewItem['order_id']));
        exit;
    }
    $isRenewalMode = true;
}

/* ========================================================
   LOAD ITEMS
   ======================================================== */
$cartItems = [];
$subtotal  = 0.00;

if ($isRenewalMode) {
    /* Renewal — single item */
    $pkgId = (int)($renewItem['package_id'] ?? 0);
    $months = max(1, (int)(($renewItem['duration_days'] ?? 30) / 30));

    $pkgStmt = $pdo->prepare("SELECT * FROM service_packages WHERE id = ? LIMIT 1");
    $pkgStmt->execute([$pkgId]);
    $pkg = $pkgStmt->fetch();

    if (!$pkg || (int)$pkg['is_active'] !== 1) {
        header('Location: ' . base_url('user/orders.php'));
        exit;
    }

    $months = max((int)$pkg['min_months'], $months);
    $calc = calc_package_price($pkg, $months);

    $unitPrice = (float)$calc['price'];
    $lineTotal = $unitPrice;
    $subtotal  = $lineTotal;

    $renewalMeta = [
        'item_id'        => $renewItemId,
        'original_order' => $renewItem['original_order_number'],
        'old_expires_at' => $renewItem['expires_at'],
        'package_id'     => $pkgId,
        'package_name'   => $pkg['name'],
        'package_type'   => $pkg['package_type'],
        'service_id'     => (int)$renewItem['service_id'],
        'service_title'  => $renewItem['service_title'],
        'service_slug'   => $renewItem['slug'],
        'thumbnail'      => $renewItem['thumbnail'],
        'months'         => $months,
        'duration_days'  => $calc['duration_days'],
        'duration_label' => $calc['duration_label'],
        'unit_price'     => $unitPrice,
    ];

    $cartItems[] = [
        'item_id'        => 'renewal_' . $renewItemId,
        'service'        => [
            'id'         => (int)$renewItem['service_id'],
            'title_en'   => $renewItem['title_en'],
            'title_bn'   => $renewItem['title_bn'],
            'slug'       => $renewItem['slug'],
            'thumbnail'  => $renewItem['thumbnail'],
            'currency'   => $renewItem['currency'] ?: current_currency(),
        ],
        'package_id'     => $pkgId,
        'package_type'   => $pkg['package_type'],
        'package'        => $pkg['name'],
        'months'         => $months,
        'duration_days'  => $calc['duration_days'],
        'duration_label' => $calc['duration_label'],
        'is_trial'       => false,
        'qty'            => 1,
        'unit_price'     => $unitPrice,
        'line_total'     => $lineTotal,
        'currency'       => $renewItem['currency'] ?: current_currency(),
    ];

} else {
    /* Normal cart */
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $itemId => $item) {
            $serviceId = (int) ($item['service_id'] ?? 0);
            $packageId = !empty($item['package_id']) ? (int) $item['package_id'] : null;
            $qty       = max(1, (int) ($item['qty'] ?? 1));
            $months    = (int) ($item['months'] ?? 0);
            $pkgType   = $item['package_type'] ?? null;
            $isTrial   = !empty($item['is_trial']);
            if (!$serviceId) continue;

            $sStmt = $pdo->prepare("SELECT id, title_en, title_bn, slug, thumbnail, price, discount_price, delivery_time, status, currency FROM services WHERE id = ? LIMIT 1");
            $sStmt->execute([$serviceId]);
            $service = $sStmt->fetch();
            if (!$service || $service['status'] !== 'active') continue;

            $unitPrice = (float) $service['price'];
            if (!empty($service['discount_price']) && (float) $service['discount_price'] > 0) {
                $unitPrice = (float) $service['discount_price'];
            }

            $pkgName  = null;
            $durLabel = null;
            $durDays  = 0;

            if ($packageId) {
                $pStmt = $pdo->prepare("SELECT * FROM service_packages WHERE id = ? AND service_id = ? LIMIT 1");
                $pStmt->execute([$packageId, $serviceId]);
                $pkg = $pStmt->fetch();
                if ($pkg) {
                    $pkgName = $pkg['name'];
                    $pkgType = $pkg['package_type'];
                    if ($pkgType === 'subscription') {
                        $calc = calc_package_price($pkg, max(1, $months));
                    } else {
                        $calc = calc_package_price($pkg, 0);
                    }
                    $unitPrice = (float)$calc['price'];
                    $durLabel  = $calc['duration_label'];
                    $durDays   = (int)$calc['duration_days'];
                    $isTrial   = ($pkgType === 'trial');
                }
            }

            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $cartItems[] = [
                'item_id'        => $itemId,
                'service'        => $service,
                'package_id'     => $packageId,
                'package_type'   => $pkgType,
                'package'        => $pkgName,
                'months'         => $months,
                'duration_days'  => $durDays,
                'duration_label' => $durLabel,
                'is_trial'       => $isTrial,
                'qty'            => $qty,
                'unit_price'     => $unitPrice,
                'line_total'     => $lineTotal,
                'currency'       => $service['currency'] ?: current_currency(),
            ];
        }
    }

    if (empty($cartItems)) {
        header('Location: ' . base_url('cart.php'));
        exit;
    }
}

/* ---------- Coupon ---------- */
$couponCode = $isRenewalMode ? null : ($_SESSION['coupon']['code']   ?? null);
$discount   = $isRenewalMode ? 0 : (float) ($_SESSION['coupon']['amount'] ?? 0);
$tax        = 0.00;
$total      = max(0, $subtotal - $discount + $tax);

/* ---------- Domain feature ---------- */
$domainEnabled   = (int) setting('domain_enabled', 1) === 1;
$ownEnabled      = (int) setting('domain_own_enabled', 1) === 1;
$subEnabled      = (int) setting('domain_subdomain_enabled', 1) === 1;
$buyEnabled      = (int) setting('domain_purchase_enabled', 1) === 1;
$subdomainSuffix = trim((string) setting('domain_subdomain_suffix', 'webcyno.com'));
$domainPricing   = get_domain_pricing();
$availableTlds   = array_filter($domainPricing, fn($d) => $d['enabled']);

/* Domain only applies to non-renewal (first-time setup) */
$showDomainStep = $domainEnabled && !$isRenewalMode && ($ownEnabled || $subEnabled || $buyEnabled);

/* ---------- Payment methods ---------- */
$pmStmt = $pdo->query("
    SELECT id, name, code, type, logo, account_number, instructions, currency
    FROM payment_methods WHERE status = 1 ORDER BY sort_order ASC, id ASC
");
$paymentMethods = $pmStmt->fetchAll();

$page_title = ($isRenewalMode ? 'Renew Subscription — ' : 'Checkout — ') . setting('site_name', 'Webcyno');
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <nav class="breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <?php if ($isRenewalMode): ?>
                <a href="<?= e(base_url('user/orders.php')) ?>"><?= e(t('my_orders', 'My Orders')) ?></a>
                <span class="sep">/</span>
                <span class="current"><?= e(t('renew', 'Renew')) ?></span>
            <?php else: ?>
                <a href="<?= e(base_url('cart.php')) ?>"><?= e(t('cart')) ?></a>
                <span class="sep">/</span>
                <span class="current"><?= e(t('checkout', 'Checkout')) ?></span>
            <?php endif; ?>
        </nav>

        <?php if ($isRenewalMode): ?>
        <div class="card" style="padding:var(--space);margin-bottom:var(--space-lg);background:linear-gradient(135deg,#FEF3C7,#FFFBEB);border:2px solid #FDE68A;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <div style="width:44px;height:44px;background:#F59E0B;color:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">🔄</div>
                <div style="flex:1;">
                    <h2 style="font-size:var(--fs-lg);margin-bottom:4px;color:#92400E;">Renew Subscription</h2>
                    <p class="text-muted" style="margin:0;font-size:var(--fs-sm);color:#78350F;">
                        Renewing <strong><?= e(str_limit($renewItem['service_title'], 60)) ?></strong>
                        <?php if (!empty($renewalMeta['old_expires_at'])): ?>
                            — Current expiry: <strong><?= e(date('M d, Y', strtotime($renewalMeta['old_expires_at']))) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <h1 style="font-size:var(--fs-3xl);margin-bottom:var(--space-lg);">
            <?= $isRenewalMode ? '🔄 ' . e(t('renew_subscription', 'Renew Subscription')) : e(t('checkout', 'Checkout')) ?>
        </h1>

        <form id="checkout-form" novalidate>
            <input type="hidden" name="is_renewal" value="<?= $isRenewalMode ? 1 : 0 ?>">
            <input type="hidden" name="renew_item_id" value="<?= $isRenewalMode ? (int)$renewalMeta['item_id'] : 0 ?>">
            <input type="hidden" name="domain_price" id="f-domain-price" value="0">
            <input type="hidden" name="domain_type" id="f-domain-type" value="none">

            <div style="display:grid;grid-template-columns:1fr 380px;gap:var(--space-lg);align-items:start;">

                <!-- LEFT -->
                <div>

                    <!-- ==================================================
                         ✨ DOMAIN SELECTION STEP
                         ================================================== -->
                    <?php if ($showDomainStep): ?>
                    <section class="card" style="padding:var(--space-lg);margin-bottom:var(--space-lg);border:2px solid #BFDBFE;background:linear-gradient(135deg,#F8FAFC,#EFF6FF);">
                        <h2 style="font-size:var(--fs-lg);margin-bottom:6px;display:flex;align-items:center;gap:8px;">
                            🌐 <?= e(t('choose_domain', 'Choose Your Domain')) ?>
                        </h2>
                        <p class="text-muted" style="font-size:var(--fs-sm);margin-bottom:var(--space);">
                            <?= e(t('domain_step_note', 'Pick how you want your project domain. This affects the total price.')) ?>
                        </p>

                        <div class="domain-options">

                            <!-- Option: OWN -->
                            <?php if ($ownEnabled): ?>
                            <label class="domain-option" data-domain-opt="own">
                                <input type="radio" name="domain_choice" value="own" checked>
                                <div class="do-body">
                                    <div class="do-head">
                                        <span class="do-icon" style="background:#E0E7FF;color:#3730A3;">🌐</span>
                                        <div>
                                            <div class="do-title"><?= e(t('domain_own_title', 'নিজের ডোমেইন')) ?></div>
                                            <div class="do-price">FREE</div>
                                        </div>
                                    </div>
                                    <p class="do-desc"><?= e(t('domain_own_desc', 'আপনার নিজের domain থাকলে এখানে সেটা add করুন।')) ?></p>
                                    <input type="text" class="form-control do-input" data-input="own_name"
                                           placeholder="example.com" maxlength="150">
                                </div>
                            </label>
                            <?php endif; ?>

                            <!-- Option: SUBDOMAIN -->
                            <?php if ($subEnabled): ?>
                            <label class="domain-option" data-domain-opt="subdomain">
                                <input type="radio" name="domain_choice" value="subdomain" <?= !$ownEnabled ? 'checked' : '' ?>>
                                <div class="do-body">
                                    <div class="do-head">
                                        <span class="do-icon" style="background:#D1FAE5;color:#065F46;">🆓</span>
                                        <div>
                                            <div class="do-title"><?= e(t('domain_free_title', 'ফ্রি সাব-ডোমেইন')) ?></div>
                                            <div class="do-price" style="color:#059669;">FREE</div>
                                        </div>
                                    </div>
                                    <p class="do-desc">
                                        <?= e(t('domain_free_desc', 'আমাদের ডোমেইনে আপনার ফ্রি subdomain।')) ?>
                                        <code>your-name.<?= e($subdomainSuffix) ?></code>
                                    </p>
                                    <input type="text" class="form-control do-input" data-input="sub_name"
                                           placeholder="your-business-name" maxlength="60">
                                </div>
                            </label>
                            <?php endif; ?>

                            <!-- Option: PURCHASED -->
                            <?php if ($buyEnabled && !empty($availableTlds)): ?>
                            <label class="domain-option" data-domain-opt="purchased">
                                <input type="radio" name="domain_choice" value="purchased">
                                <div class="do-body">
                                    <div class="do-head">
                                        <span class="do-icon" style="background:#FEF3C7;color:#92400E;">💳</span>
                                        <div>
                                            <div class="do-title"><?= e(t('domain_buy_title', 'আমাদের থেকে ডোমেইন কিনুন')) ?></div>
                                            <div class="do-price" style="color:#D97706;"><?= e(t('paid', 'Paid')) ?></div>
                                        </div>
                                    </div>
                                    <p class="do-desc"><?= e(t('domain_buy_desc', '.com, .net ইত্যাদি — আমাদের থেকে সরাসরি কিনুন।')) ?></p>

                                    <div class="domain-purchase-fields">
                                        <input type="text" class="form-control do-input" data-input="buy_name"
                                               placeholder="your-domain" maxlength="60" style="margin-bottom:8px;">

                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                            <select class="form-control" data-input="buy_tld">
                                                <?php foreach ($availableTlds as $tld): ?>
                                                    <option value="<?= e($tld['tld']) ?>"
                                                            data-price="<?= e($tld['price']) ?>">
                                                        <?= e($tld['label']) ?> — <?= e($tld['price_fmt']) ?>/yr
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select class="form-control" data-input="buy_years">
                                                <option value="1">1 Year</option>
                                                <option value="2">2 Years</option>
                                                <option value="3">3 Years</option>
                                                <option value="5">5 Years</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            <?php endif; ?>

                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- BILLING -->
                    <section class="card" style="padding:var(--space-lg);margin-bottom:var(--space-lg);">
                        <h2 style="font-size:var(--fs-lg);margin-bottom:var(--space);">📍 <?= e(t('billing_details', 'Billing Details')) ?></h2>

                        <div class="grid grid-2" style="gap:var(--space);">
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="customer_name"><?= e(t('full_name', 'Full Name')) ?> <span class="required">*</span></label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?= e($user['name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="customer_email"><?= e(t('email', 'Email')) ?> <span class="required">*</span></label>
                                <input type="email" class="form-control" id="customer_email" name="customer_email" value="<?= e($user['email'] ?? '') ?>" required>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="customer_phone"><?= e(t('phone', 'Phone')) ?></label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="01XXXXXXXXX">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label class="form-label" for="country"><?= e(t('country', 'Country')) ?></label>
                                <input type="text" class="form-control" id="country" name="country" value="Bangladesh" readonly>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:var(--space);">
                            <label class="form-label" for="billing_address"><?= e(t('billing_address', 'Billing Address')) ?></label>
                            <textarea class="form-control" id="billing_address" name="billing_address" rows="2"></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" for="notes"><?= e(t('order_notes', 'Order Notes (optional)')) ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="<?= $isRenewalMode ? 'Renewal এর কোনো note...' : 'Business requirements, color preferences, ইত্যাদি...' ?>"></textarea>
                        </div>
                    </section>

                    <!-- PAYMENT -->
                    <section class="card" style="padding:var(--space-lg);">
                        <h2 style="font-size:var(--fs-lg);margin-bottom:var(--space);">💳 <?= e(t('payment_method', 'Payment Method')) ?></h2>

                        <?php if (empty($paymentMethods)): ?>
                            <div class="alert alert-warning"><?= e(t('no_payment_methods', 'No payment methods available.')) ?></div>
                        <?php else: ?>
                            <div class="flex flex-col gap-sm" id="payment-methods">
                                <?php foreach ($paymentMethods as $i => $pm): ?>
                                    <label class="pay-option" style="display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;">
                                        <input type="radio" name="payment_method" value="<?= e($pm['code']) ?>"
                                               data-method-id="<?= (int) $pm['id'] ?>"
                                               data-method-type="<?= e($pm['type']) ?>"
                                               <?= $i === 0 ? 'checked' : '' ?>
                                               style="margin-top:4px;accent-color:var(--primary);">
                                        <span style="flex:1;">
                                            <span style="display:flex;align-items:center;gap:8px;font-weight:600;color:var(--navy);">
                                                <?php if (!empty($pm['logo'])): ?>
                                                    <img src="<?= e(base_url($pm['logo'])) ?>" alt="" style="height:22px;">
                                                <?php endif; ?>
                                                <?= e($pm['name']) ?>
                                            </span>
                                            <?php if ($pm['type'] === 'manual' && !empty($pm['account_number'])): ?>
                                                <span class="text-muted" style="display:block;font-size:var(--fs-xs);margin-top:4px;">
                                                    <?= e(t('send_to', 'Send to')) ?>: <strong><?= e($pm['account_number']) ?></strong>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div id="manual-fields" style="margin-top:var(--space);padding:var(--space);background:var(--bg-alt);border-radius:var(--radius);display:none;">
                                <p style="font-size:var(--fs-sm);color:var(--text-muted);margin-bottom:var(--space);">
                                    <?= e(t('manual_note', 'Please complete the payment first, then submit the transaction details below.')) ?>
                                </p>
                                <div class="grid grid-2" style="gap:var(--space);">
                                    <div class="form-group" style="margin:0;">
                                        <label class="form-label" for="transaction_id"><?= e(t('transaction_id', 'Transaction ID')) ?> <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="transaction_id" name="transaction_id" placeholder="e.g. 8N7A2X9PQR">
                                    </div>
                                    <div class="form-group" style="margin:0;">
                                        <label class="form-label" for="sender_number"><?= e(t('sender_number', 'Sender Number')) ?> <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="sender_number" name="sender_number" placeholder="01XXXXXXXXX">
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- RIGHT: SUMMARY -->
                <aside class="card" style="padding:var(--space-lg);position:sticky;top:calc(var(--header-h) + 16px);">
                    <h3 style="font-size:var(--fs-lg);margin-bottom:var(--space);"><?= e(t('order_summary', 'Order Summary')) ?></h3>

                    <ul style="display:flex;flex-direction:column;gap:12px;list-style:none;padding:0;margin:0;">
                        <?php foreach ($cartItems as $ci):
                            $s = $ci['service'];
                            $title = ($lang === 'bn' && !empty($s['title_bn'])) ? $s['title_bn'] : $s['title_en'];
                            $thumb = $s['thumbnail'] ? base_url($s['thumbnail']) : base_url('assets/images/placeholder.png');
                        ?>
                        <li class="flex items-start gap-sm">
                            <img src="<?= e($thumb) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;flex-shrink:0;">
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:var(--fs-sm);font-weight:600;color:var(--navy);line-height:1.35;"><?= e(str_limit($title, 50)) ?></div>
                                <div class="text-muted" style="font-size:var(--fs-xs);">
                                    <?php if ($ci['duration_label']): ?><?= e($ci['duration_label']) ?> • <?php endif; ?>
                                    <?= (int) $ci['qty'] ?> × <?= e(money($ci['unit_price'])) ?>
                                </div>
                            </div>
                            <span style="font-size:var(--fs-sm);font-weight:600;color:var(--navy);"><?= e(money($ci['line_total'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <hr>

                    <ul style="display:flex;flex-direction:column;gap:10px;font-size:var(--fs-sm);list-style:none;padding:0;">
                        <li class="flex justify-between">
                            <span class="text-muted"><?= e(t('subtotal', 'Subtotal')) ?></span>
                            <span style="font-weight:600;"><?= e(money($subtotal)) ?></span>
                        </li>

                        <!-- ✨ Domain price line -->
                        <li class="flex justify-between" id="domain-price-row" style="display:none;">
                            <span class="text-muted" id="domain-price-label">🌐 Domain</span>
                            <span style="font-weight:600;" id="domain-price-value">৳0.00</span>
                        </li>

                        <?php if ($discount > 0): ?>
                            <li class="flex justify-between">
                                <span class="text-muted"><?= e(t('discount', 'Discount')) ?></span>
                                <span style="font-weight:600;color:var(--success);">− <?= e(money($discount)) ?></span>
                            </li>
                        <?php endif; ?>

                        <li class="flex justify-between" style="padding-top:10px;border-top:1px solid var(--border);font-size:var(--fs-lg);">
                            <span style="font-weight:700;"><?= e(t('total', 'Total')) ?></span>
                            <span style="font-weight:800;color:var(--primary);" id="final-total">
                                <?= e(money($total)) ?>
                            </span>
                        </li>
                    </ul>

                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="place-order-btn" style="margin-top:var(--space);">
                        <?= $isRenewalMode ? '🔄 ' . e(t('submit_renewal', 'Submit Renewal')) : e(t('place_order', 'Place Order')) ?>
                    </button>

                    <p class="text-muted" style="font-size:var(--fs-xs);text-align:center;margin-top:10px;">
                        <?= e(t('agree_terms', 'By placing your order, you agree to our')) ?>
                        <a href="<?= e(base_url('terms.php')) ?>"><?= e(t('terms', 'Terms')) ?></a>
                    </p>
                </aside>
            </div>
        </form>
    </div>
</main>

<style>
.pay-option:has(input:checked) { border-color: var(--primary) !important; background: var(--primary-soft); }

/* Domain options */
.domain-options { display: flex; flex-direction: column; gap: 10px; }
.domain-option {
    display: flex;
    gap: 12px;
    padding: 14px;
    background: #fff;
    border: 2px solid #E2E8F0;
    border-radius: 12px;
    cursor: pointer;
    transition: all .15s;
}
.domain-option:hover { border-color: #93C5FD; }
.domain-option:has(input:checked) {
    border-color: var(--primary);
    background: linear-gradient(135deg,#EFF6FF,#F8FAFC);
    box-shadow: 0 4px 12px -4px rgba(37,99,235,.2);
}
.domain-option input[type="radio"] {
    margin-top: 4px;
    accent-color: var(--primary);
    flex-shrink: 0;
}
.do-body { flex: 1; min-width: 0; }
.do-head { display: flex; gap: 10px; align-items: center; margin-bottom: 8px; }
.do-icon {
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px; font-size: 20px;
    flex-shrink: 0;
}
.do-title { font-size: 14px; font-weight: 700; color: var(--navy); }
.do-price { font-size: 13px; font-weight: 800; color: var(--primary); margin-top: 2px; }
.do-desc { font-size: 12.5px; color: #64748B; margin: 0 0 10px; line-height: 1.5; }
.do-desc code {
    background: #F1F5F9; padding: 2px 6px; border-radius: 4px;
    font-family: monospace; font-size: 11.5px;
}
.do-input { font-size: 13px; }

.domain-purchase-fields { margin-top: 4px; }

@media (max-width: 768px) {
    #checkout-form > div[style*="grid-template-columns:1fr 380px"] { grid-template-columns: 1fr !important; }
    aside[style*="position:sticky"] { position: static !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('checkout-form');
    var manualBox = document.getElementById('manual-fields');
    var radios = document.querySelectorAll('input[name="payment_method"]');
    var baseSubtotal = <?= json_encode($subtotal) ?>;
    var baseDiscount = <?= json_encode($discount) ?>;
    var baseTotal    = <?= json_encode($total) ?>;
    var currencySym  = '<?= e(setting('currency_symbol_bdt', '৳')) ?>';
    var currencyCode = '<?= e(current_currency()) ?>';
    var subdomainSuffix = '<?= e($subdomainSuffix) ?>';

    function fmt(amount) {
        if (currencyCode === 'USD') return '$' + Number(amount).toFixed(2);
        return currencySym + Number(amount).toFixed(2);
    }

    /* ---------- Payment method → manual fields ---------- */
    function toggleManual() {
        var checked = document.querySelector('input[name="payment_method"]:checked');
        if (!checked || !manualBox) return;
        manualBox.style.display = (checked.dataset.methodType === 'manual') ? 'block' : 'none';
    }
    radios.forEach(r => r.addEventListener('change', toggleManual));
    toggleManual();

    /* ---------- Domain selection logic ---------- */
    var domainRadios = document.querySelectorAll('input[name="domain_choice"]');
    var fDomainPrice = document.getElementById('f-domain-price');
    var fDomainType  = document.getElementById('f-domain-type');
    var domainPriceRow = document.getElementById('domain-price-row');
    var domainPriceValue = document.getElementById('domain-price-value');
    var domainPriceLabel = document.getElementById('domain-price-label');
    var finalTotalEl = document.getElementById('final-total');

    function calcDomainPrice() {
        var selected = document.querySelector('input[name="domain_choice"]:checked');
        if (!selected) return { price: 0, type: 'none', label: '' };

        var type = selected.value;
        if (type === 'own') return { price: 0, type: 'own', label: '🌐 Own Domain' };
        if (type === 'subdomain') return { price: 0, type: 'subdomain', label: '🆓 Free Subdomain' };

        if (type === 'purchased') {
            var tldSel = document.querySelector('[data-input="buy_tld"]');
            var yrsSel = document.querySelector('[data-input="buy_years"]');
            if (tldSel && yrsSel) {
                var tldPrice = parseFloat(tldSel.options[tldSel.selectedIndex].dataset.price) || 0;
                var yrs      = parseInt(yrsSel.value) || 1;
                return { price: tldPrice * yrs, type: 'purchased', label: '💳 Domain (' + yrs + ' yr)' };
            }
        }
        return { price: 0, type: type, label: '' };
    }

    function updateTotals() {
        var dom = calcDomainPrice();
        fDomainPrice.value = dom.price;
        fDomainType.value  = dom.type;

        if (dom.price > 0) {
            domainPriceRow.style.display = 'flex';
            domainPriceValue.textContent = fmt(dom.price);
            domainPriceLabel.textContent = dom.label;
        } else {
            domainPriceRow.style.display = 'none';
        }

        var newTotal = baseTotal + dom.price;
        if (finalTotalEl) finalTotalEl.textContent = fmt(newTotal);
    }

    domainRadios.forEach(function (r) {
        r.addEventListener('change', function () {
            // Toggle disabled on inputs
            document.querySelectorAll('.domain-option').forEach(function (opt) {
                var isActive = opt.querySelector('input').checked;
                opt.querySelectorAll('.do-input, .do-input ~ *, .domain-purchase-fields select').forEach(function (el) {
                    // Only disable inputs of inactive options for accessibility
                });
            });
            updateTotals();
        });
    });

    // Domain purchase field changes
    document.querySelector('[data-input="buy_tld"]')?.addEventListener('change', updateTotals);
    document.querySelector('[data-input="buy_years"]')?.addEventListener('change', updateTotals);

    updateTotals();

    /* ---------- Form submit ---------- */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var btn = document.getElementById('place-order-btn');
        var checked = document.querySelector('input[name="payment_method"]:checked');
        if (!checked) { toast('Please select a payment method.', 'warning'); return; }

        var isRenewal = form.querySelector('[name="is_renewal"]').value === '1';
        var renewItemId = form.querySelector('[name="renew_item_id"]').value;

        /* ---------- Domain payload ---------- */
        var domainPayload = {
            domain_type: 'none',
            domain_name: '',
            domain_tld: '',
            domain_years: 0,
        };

        var domSelected = document.querySelector('input[name="domain_choice"]:checked');
        if (domSelected && !isRenewal) {
            var dtype = domSelected.value;
            domainPayload.domain_type = dtype;

            if (dtype === 'own') {
                var ownName = document.querySelector('[data-input="own_name"]').value.trim();
                if (!ownName) {
                    showFormErrors(form, { domain_name: 'Please enter your domain name.' });
                    toast('Domain name is required.', 'error');
                    return;
                }
                domainPayload.domain_name = ownName;
            } else if (dtype === 'subdomain') {
                var subName = document.querySelector('[data-input="sub_name"]').value.trim();
                if (!subName || subName.length < 3) {
                    showFormErrors(form, { domain_name: 'Enter a subdomain name (min 3 chars).' });
                    toast('Subdomain name is required.', 'error');
                    return;
                }
                domainPayload.domain_name = subName;
            } else if (dtype === 'purchased') {
                var buyName = document.querySelector('[data-input="buy_name"]').value.trim();
                var tldSel  = document.querySelector('[data-input="buy_tld"]');
                var yrsSel  = document.querySelector('[data-input="buy_years"]');
                if (!buyName || buyName.length < 2) {
                    showFormErrors(form, { domain_name: 'Enter a domain name (min 2 chars).' });
                    toast('Domain name is required.', 'error');
                    return;
                }
                domainPayload.domain_name  = buyName;
                domainPayload.domain_tld   = tldSel.value;
                domainPayload.domain_years = parseInt(yrsSel.value) || 1;
            }
        }

        var payload = {
            customer_name:    form.customer_name.value.trim(),
            customer_email:   form.customer_email.value.trim(),
            customer_phone:   form.customer_phone.value.trim(),
            billing_address:  form.billing_address.value.trim(),
            notes:            form.notes.value.trim(),
            payment_method:   checked.value,
            payment_method_id:checked.dataset.methodId,
            transaction_id:   document.getElementById('transaction_id')?.value.trim() || '',
            sender_number:    document.getElementById('sender_number')?.value.trim() || '',
            coupon_code:      <?= json_encode($couponCode) ?>,
            is_renewal:       isRenewal ? 1 : 0,
            renew_item_id:    renewItemId || 0,

            // Domain
            domain_type:  domainPayload.domain_type,
            domain_name:  domainPayload.domain_name,
            domain_tld:   domainPayload.domain_tld,
            domain_years: domainPayload.domain_years,
        };

        /* ---------- Validation ---------- */
        var errors = {};
        if (!payload.customer_name)  errors.customer_name  = 'Name is required.';
        if (!payload.customer_email) errors.customer_email = 'Email is required.';

        var payType = checked.dataset.methodType || 'manual';
        if (payType === 'manual') {
            var txn = payload.transaction_id;
            var snd = payload.sender_number;
            if (!txn) errors.transaction_id = 'Transaction ID is required.';
            if (!snd) errors.sender_number  = 'Sender Number is required.';
            if (txn && txn.length < 4) errors.transaction_id = 'Transaction ID too short.';
            if (snd && snd.length < 6) errors.sender_number  = 'Enter a valid mobile number.';
        }

        if (Object.keys(errors).length) {
            showFormErrors(form, errors);
            toast('Please fill all required fields.', 'error');
            return;
        }

        showFormErrors(form, null);
        btnLoading(btn, true);

        try {
            var res = await API.post('orders/create', payload);
            toast(res.message || 'Order placed!', 'success');
            if (typeof refreshCartCount === 'function') refreshCartCount();
            var redirect = (res.data && res.data.redirect) || '<?= e(base_url('user/orders.php')) ?>';
            setTimeout(() => { location.href = redirect; }, 900);
        } catch (err) {
            if (err.status === 401) {
                toast('Session expired. Please log in again.', 'warning');
                setTimeout(() => location.href = '<?= e(base_url('login.php')) ?>', 1200);
                return;
            }
            if (err.errors) showFormErrors(form, err.errors);
            toast(err.message || 'Could not place order.', 'error');
            btnLoading(btn, false);
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>