<?php
/**
 * cart.php — Shopping Cart Page (v3.0)
 * Session cart structure (v3.0):
 *   $_SESSION['cart'][key] = [
 *      service_id, package_id, package_type, package_name, months,
 *      duration_days, duration_label, unit_price, qty, is_trial,
 *      delivery_days, expires_at
 *   ]
 */

$page_title = 'Cart — ' . setting('site_name', 'Webcyno');
$meta_desc  = 'Review your cart and proceed to checkout.';

require_once __DIR__ . '/includes/header.php';

global $pdo;

/* ---------- Load cart items ---------- */
$cartItems        = [];
$subtotal         = 0.00;
$hasTrialItem     = false;
$maxDeliveryDays  = 0;

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $itemKey => $item) {
        $serviceId = (int) ($item['service_id'] ?? 0);
        $packageId = !empty($item['package_id']) ? (int) $item['package_id'] : null;
        $qty       = max(1, (int) ($item['qty'] ?? 1));
        $pkgType   = $item['package_type'] ?? null;
        $months    = (int) ($item['months'] ?? 0);
        $isTrial   = !empty($item['is_trial']);
        $durLabel  = $item['duration_label'] ?? null;
        $durDays   = (int) ($item['duration_days'] ?? 0);
        $delvDays  = (int) ($item['delivery_days'] ?? 0);

        if (!$serviceId) continue;

        /* Fetch fresh service */
        $sStmt = $pdo->prepare("
            SELECT id, title_en, title_bn, slug, thumbnail, price, discount_price,
                   delivery_time, delivery_type, delivery_days, status, currency
            FROM services WHERE id = ? LIMIT 1
        ");
        $sStmt->execute([$serviceId]);
        $service = $sStmt->fetch();
        if (!$service || $service['status'] !== 'active') {
            unset($_SESSION['cart'][$itemKey]);
            continue;
        }

        /* Fresh delivery days */
        $serviceDeliveryDays = max(1, (int)($service['delivery_days'] ?? 0));
        if ($serviceDeliveryDays <= 0) {
            $serviceDeliveryDays = max(1, (int) setting('delivery_default_days', 3));
        }
        $delvDays = $serviceDeliveryDays;
        $_SESSION['cart'][$itemKey]['delivery_days'] = $serviceDeliveryDays;

        if ($serviceDeliveryDays > $maxDeliveryDays) $maxDeliveryDays = $serviceDeliveryDays;

        /* Recalculate price */
        $unitPrice = (float) $service['price'];
        if (!empty($service['discount_price']) && (float) $service['discount_price'] > 0) {
            $unitPrice = (float) $service['discount_price'];
        }
        $pkgName = null;

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
                $unitPrice  = (float) $calc['price'];
                $durLabel   = $calc['duration_label'];
                $durDays    = (int) $calc['duration_days'];
                $isTrial    = ($pkgType === 'trial');
            }
        }

        $lineTotal = round($unitPrice * $qty, 2);
        $subtotal += $lineTotal;

        if ($isTrial) $hasTrialItem = true;

        $cartItems[] = [
            'item_id'        => $itemKey,
            'service'        => $service,
            'package_id'     => $packageId,
            'package_type'   => $pkgType,
            'package'        => $pkgName,
            'months'         => $months,
            'duration_days'  => $durDays,
            'duration_label' => $durLabel,
            'is_trial'       => $isTrial,
            'delivery_days'  => $delvDays,
            'qty'            => $qty,
            'unit_price'     => $unitPrice,
            'line_total'     => $lineTotal,
            'currency'       => $service['currency'] ?: current_currency(),
        ];
    }
}

/* ---------- Coupon ---------- */
$couponCode = $_SESSION['coupon']['code']   ?? null;
$discount   = (float) ($_SESSION['coupon']['amount'] ?? 0);
$tax        = 0.00;
$total      = max(0, $subtotal - $discount + $tax);

/* ---------- Domain feature info ---------- */
$domainEnabled = (int) setting('domain_enabled', 1) === 1;

/* ---------- Package type badge helper ---------- */
function cart_pkg_badge(string $type, bool $isTrial): array {
    if ($isTrial || $type === 'trial') {
        return ['label' => 'Trial',       'bg' => '#D1FAE5', 'color' => '#065F46'];
    }
    if ($type === 'lifetime') {
        return ['label' => 'Lifetime',    'bg' => '#FEF3C7', 'color' => '#92400E'];
    }
    return     ['label' => 'Subscription','bg' => '#DBEAFE', 'color' => '#1E40AF'];
}
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <span class="current"><?= e(t('cart')) ?></span>
        </nav>

        <div class="section-header-row" style="margin-bottom:var(--space-lg);">
            <h1 style="font-size:var(--fs-3xl);"><?= e(t('your_cart', 'Your Cart')) ?></h1>
            <?php if (!empty($cartItems)): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(base_url('services.php')) ?>">← <?= e(t('continue_shopping', 'Continue Shopping')) ?></a>
            <?php endif; ?>
        </div>

        <?php if (empty($cartItems)): ?>
            <div class="card empty-state" data-cart-page>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:64px;height:64px;color:var(--border-dark);margin:0 auto var(--space);">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <h3><?= e(t('cart_empty', 'Your cart is empty')) ?></h3>
                <p><?= e(t('cart_empty_sub', 'Add some services to get started.')) ?></p>
                <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('services.php')) ?>"><?= e(t('browse_services', 'Browse Services')) ?></a>
            </div>

        <?php else: ?>
            <div style="display:grid;grid-template-columns:1fr 360px;gap:var(--space-lg);align-items:start;" data-cart-page>

                <!-- ====== ITEMS LIST ====== -->
                <div class="card" style="padding:0;overflow:hidden;">

                    <div class="hide-sm cart-header-row">
                        <span><?= e(t('item', 'Item')) ?></span>
                        <span><?= e(t('price', 'Price')) ?></span>
                        <span><?= e(t('qty', 'Qty')) ?></span>
                        <span style="text-align:right;"><?= e(t('total', 'Total')) ?></span>
                        <span></span>
                    </div>

                    <?php foreach ($cartItems as $ci):
                        $s = $ci['service'];
                        $title = ($lang === 'bn' && !empty($s['title_bn'])) ? $s['title_bn'] : $s['title_en'];
                        $thumb = $s['thumbnail'] ? base_url($s['thumbnail']) : base_url('assets/images/placeholder.png');
                        $badge = cart_pkg_badge((string)$ci['package_type'], $ci['is_trial']);
                        $cur   = $ci['currency'];
                    ?>
                    <div class="cart-row" data-item-id="<?= e($ci['item_id']) ?>">

                        <!-- Item -->
                        <div class="cart-item-cell">
                            <a href="<?= e(base_url('service-details.php?slug=' . urlencode($s['slug']))) ?>" style="flex-shrink:0;">
                                <img src="<?= e($thumb) ?>" alt="<?= e($title) ?>"
                                     style="width:64px;height:64px;object-fit:cover;border-radius:var(--radius);">
                            </a>
                            <div style="min-width:0;flex:1;">
                                <a href="<?= e(base_url('service-details.php?slug=' . urlencode($s['slug']))) ?>"
                                   style="font-weight:600;color:var(--navy);display:block;font-size:var(--fs-sm);line-height:1.35;">
                                    <?= e(str_limit($title, 60)) ?>
                                </a>

                                <!-- Package badge + name -->
                                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;align-items:center;">
                                    <span style="display:inline-block;font-size:10px;font-weight:700;letter-spacing:.3px;
                                                 padding:3px 8px;border-radius:20px;text-transform:uppercase;
                                                 background:<?= e($badge['bg']) ?>;color:<?= e($badge['color']) ?>;">
                                        <?= e($badge['label']) ?>
                                    </span>
                                    <?php if ($ci['package']): ?>
                                        <span class="text-muted" style="font-size:var(--fs-xs);"><?= e($ci['package']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <!-- Duration -->
                                <?php if ($ci['duration_label']): ?>
                                    <div class="text-muted" style="font-size:var(--fs-xs);margin-top:4px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:2px;"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                        <?= e($ci['duration_label']) ?>
                                        <?php if ($ci['is_trial']): ?>
                                            <span style="color:#059669;font-weight:600;margin-left:4px;">• Free</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- ✨ Delivery days badge -->
                                <div class="mini-badge-row">
                                    <span class="mini-badge mini-badge-delivery">
                                        ⏱ Delivery in <strong><?= (int)$ci['delivery_days'] ?> <?= e(t('days', 'days')) ?></strong>
                                    </span>
                                    <?php if ($ci['is_trial']): ?>
                                        <span class="mini-badge mini-badge-trial">
                                            🔔 48h alert
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Unit price -->
                        <div style="font-size:var(--fs-sm);font-weight:600;color:var(--navy);">
                            <?php if ($ci['is_trial']): ?>
                                <span style="color:#059669;font-weight:700;">FREE</span>
                            <?php else: ?>
                                <?= e(money($ci['unit_price'], $cur)) ?>
                            <?php endif; ?>
                        </div>

                        <!-- Qty -->
                        <div class="flex items-center" style="border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;width:fit-content;">
                            <?php if ($ci['is_trial']): ?>
                                <span style="min-width:90px;text-align:center;padding:6px 4px;font-size:var(--fs-xs);color:var(--text-muted);">1 (trial)</span>
                            <?php else: ?>
                                <button type="button" data-cart-dec aria-label="Decrease" style="min-width:30px;height:30px;border:none;background:transparent;cursor:pointer;font-size:16px;">−</button>
                                <input type="number" data-qty-input value="<?= (int) $ci['qty'] ?>" min="1" max="99"
                                       style="width:40px;height:30px;text-align:center;border:none;outline:none;font-size:var(--fs-sm);">
                                <button type="button" data-cart-inc aria-label="Increase" style="min-width:30px;height:30px;border:none;background:transparent;cursor:pointer;font-size:16px;">+</button>
                            <?php endif; ?>
                        </div>

                        <!-- Line total -->
                        <div style="font-size:var(--fs-sm);font-weight:700;color:var(--primary);text-align:right;">
                            <?php if ($ci['is_trial']): ?>
                                <span style="color:#059669;">FREE</span>
                            <?php else: ?>
                                <?= e(money($ci['line_total'], $cur)) ?>
                            <?php endif; ?>
                        </div>

                        <!-- Remove -->
                        <button type="button" data-cart-remove aria-label="Remove"
                                style="width:32px;height:32px;border:1px solid var(--border);border-radius:var(--radius);background:transparent;cursor:pointer;color:var(--danger);display:flex;align-items:center;justify-content:center;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ====== SUMMARY ====== -->
                <aside class="card" style="padding:var(--space-lg);position:sticky;top:calc(var(--header-h) + 16px);">
                    <h3 style="font-size:var(--fs-lg);margin-bottom:var(--space);"><?= e(t('order_summary', 'Order Summary')) ?></h3>

                    <!-- Trial notice -->
                    <?php if ($hasTrialItem): ?>
                        <div style="background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;border-radius:var(--radius);padding:10px 12px;font-size:12.5px;margin-bottom:var(--space);line-height:1.5;">
                            🎁 <strong>Trial included</strong> — আপনার cart এ একটি ফ্রি ট্রায়াল আছে।
                        </div>
                    <?php endif; ?>

                    <!-- ✨ Delivery estimate -->
                    <?php if ($maxDeliveryDays > 0): ?>
                        <div style="background:linear-gradient(135deg,#EFF6FF,#F8FAFC);border:1px solid #BFDBFE;color:#1E40AF;border-radius:var(--radius);padding:10px 12px;font-size:12.5px;margin-bottom:var(--space);line-height:1.5;">
                            ⏱ <strong>Estimated delivery:</strong> <?= (int)$maxDeliveryDays ?> <?= e(t('days', 'days')) ?>
                            <div style="font-size:11px;color:#64748B;margin-top:2px;">All items delivered together</div>
                        </div>
                    <?php endif; ?>

                    <!-- ✨ Domain info notice -->
                    <?php if ($domainEnabled): ?>
                        <div style="background:linear-gradient(135deg,#FEF3C7,#FFFBEB);border:1px solid #FDE68A;color:#92400E;border-radius:var(--radius);padding:10px 12px;font-size:12.5px;margin-bottom:var(--space);line-height:1.5;">
                            🌐 <strong>Domain step next</strong> — checkout এ নিজের domain, free subdomain, বা কিনতে পারবেন।
                        </div>
                    <?php endif; ?>

                    <!-- Coupon -->
                    <form id="coupon-form" style="margin-bottom:var(--space);">
                        <label class="form-label" for="coupon-code"><?= e(t('coupon', 'Coupon Code')) ?></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="coupon-code" name="code"
                                   placeholder="<?= e(t('enter_coupon', 'Enter code')) ?>"
                                   value="<?= e($couponCode ?? '') ?>">
                            <button type="submit" class="btn btn-soft btn-sm" style="min-height:44px;"><?= e(t('apply', 'Apply')) ?></button>
                        </div>
                    </form>

                    <hr>

                    <ul style="display:flex;flex-direction:column;gap:10px;font-size:var(--fs-sm);list-style:none;padding:0;">
                        <li class="flex justify-between">
                            <span class="text-muted"><?= e(t('subtotal', 'Subtotal')) ?></span>
                            <span style="font-weight:600;"><?= e(money($subtotal)) ?></span>
                        </li>
                        <?php if ($discount > 0): ?>
                            <li class="flex justify-between">
                                <span class="text-muted"><?= e(t('discount', 'Discount')) ?> (<?= e($couponCode) ?>)</span>
                                <span style="font-weight:600;color:var(--success);">− <?= e(money($discount)) ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if ($tax > 0): ?>
                            <li class="flex justify-between">
                                <span class="text-muted"><?= e(t('tax', 'Tax')) ?></span>
                                <span style="font-weight:600;"><?= e(money($tax)) ?></span>
                            </li>
                        <?php endif; ?>
                        <li class="flex justify-between" style="padding-top:10px;border-top:1px solid var(--border);font-size:var(--fs-lg);">
                            <span style="font-weight:700;"><?= e(t('total', 'Total')) ?></span>
                            <span style="font-weight:800;color:var(--primary);"><?= e(money($total)) ?></span>
                        </li>
                        <?php if ($domainEnabled): ?>
                            <li style="font-size:11px;color:#92400E;padding:6px 10px;background:#FEF3C7;border-radius:6px;margin-top:4px;">
                                + Domain cost (if purchased) will be added next step
                            </li>
                        <?php endif; ?>
                    </ul>

                    <a href="<?= e(base_url('checkout.php')) ?>" class="btn btn-primary btn-lg btn-block" style="margin-top:var(--space);">
                        <?= e(t('proceed_checkout', 'Proceed to Checkout')) ?> →
                    </a>

                    <a href="<?= e(base_url('services.php')) ?>" class="btn btn-ghost btn-block" style="margin-top:8px;">
                        <?= e(t('continue_shopping', 'Continue Shopping')) ?>
                    </a>

                    <div style="margin-top:var(--space);padding-top:var(--space);border-top:1px solid var(--border);font-size:var(--fs-xs);color:var(--text-muted);display:flex;flex-direction:column;gap:6px;">
                        <span class="flex items-center gap-xs">🛡 <?= e(t('secure_checkout', 'Secure Checkout')) ?></span>
                        <span class="flex items-center gap-xs">✓ <?= e(t('money_back', 'Money Back Guarantee')) ?></span>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* Cart rows */
.cart-header-row, .cart-row {
    display: grid;
    grid-template-columns: 2.2fr 1fr 0.9fr 1fr 44px;
    gap: var(--space-sm);
    align-items: center;
    padding: var(--space);
}
.cart-header-row {
    background: var(--bg-alt);
    padding: 12px var(--space);
    font-size: var(--fs-xs);
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
}
.cart-row { border-top: 1px solid var(--border); }
.cart-item-cell {
    display: flex;
    align-items: flex-start;
    gap: var(--space-sm);
    min-width: 0;
}

/* Mini badges */
.mini-badge-row {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 6px;
}
.mini-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 10.5px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
    line-height: 1;
}
.mini-badge-delivery {
    background: #EFF6FF;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
}
.mini-badge-delivery strong { font-weight: 800; }
.mini-badge-trial {
    background: #FEF3C7;
    color: #92400E;
    border: 1px solid #FDE68A;
}

@media (max-width: 768px) {
    [data-cart-page] > div[style*="grid-template-columns:1fr 360px"] {
        grid-template-columns: 1fr !important;
    }
    aside[style*="position:sticky"] { position: static !important; }
    .cart-header-row { display: none; }
    .cart-row {
        grid-template-columns: 1fr auto !important;
        row-gap: 10px;
    }
    .cart-item-cell { grid-column: 1 / -1; }
    .cart-row > div:nth-child(2) { display: none; }
    .cart-row > div:nth-child(4) { justify-self: end; }
}
</style>

<script>
document.getElementById('coupon-form')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    var code = document.getElementById('coupon-code').value.trim();
    if (!code) return;
    var btn = e.target.querySelector('button');
    if (typeof btnLoading === 'function') btnLoading(btn, true);
    try {
        var res = await API.post('coupon/check', { code: code, subtotal: <?= json_encode($subtotal) ?> });
        toast(res.message || 'Coupon applied', 'success');
        setTimeout(function () { location.reload(); }, 800);
    } catch (err) {
        toast(err.message || 'Invalid coupon', 'error');
        if (typeof btnLoading === 'function') btnLoading(btn, false);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>