<?php
/**
 * api/orders/create.php — Create Order from Cart or Renewal (v3.0)
 * Version: 3.0 (domain + delivery + renewal)
 * Method: POST (login required)
 *
 * Body (Normal):
 *   customer_name, customer_email, customer_phone?, billing_address?, notes?,
 *   payment_method, payment_method_id, transaction_id?, sender_number?, coupon_code?,
 *   domain_type (none|own|subdomain|purchased),
 *   domain_name?, domain_tld?, domain_years?
 *
 * Body (Renewal):
 *   ...same billing... + is_renewal=1, renew_item_id=42
 *   (domain fields ignored)
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('POST');
check_csrf();

$userId = require_user();
global $pdo;

/* ---------- Load user ---------- */
$uStmt = $pdo->prepare("SELECT id, name, email, phone, status FROM users WHERE id = ? LIMIT 1");
$uStmt->execute([$userId]);
$user = $uStmt->fetch();
if (!$user || $user['status'] !== 'active') json_error('Your account is not active.', 403);

/* ---------- Input ---------- */
$customerName  = clean(input('customer_name', ''), 100);
$customerEmail = clean_email(input('customer_email', ''));
$customerPhone = clean_phone(input('customer_phone', ''));
$billingAddr   = clean(input('billing_address', ''), 500);
$notes         = clean(input('notes', ''), 1000);
$payMethodCode = clean(input('payment_method', ''), 50);
$payMethodId   = (int) input('payment_method_id', 0);
$txnId         = clean(input('transaction_id', ''), 120);
$senderNumber  = clean_phone(input('sender_number', ''));
$couponCode    = strtoupper(clean(input('coupon_code', ''), 50));

$isRenewal     = (int) input('is_renewal', 0) === 1;
$renewItemId   = (int) input('renew_item_id', 0);

// Domain
$domainType    = strtolower(clean(input('domain_type', 'none'), 20));
$domainName    = clean(input('domain_name', ''), 150);
$domainTld     = strtolower(clean(input('domain_tld', ''), 20));
$domainYears   = max(1, min(5, (int) input('domain_years', 1)));

/* ---------- Validate ---------- */
$errors = [];
if ($customerName === '')    $errors['customer_name']  = 'Name is required.';
if (!$customerEmail)         $errors['customer_email'] = 'Valid email is required.';
if ($payMethodCode === '')   $errors['payment_method'] = 'Please select a payment method.';
if ($isRenewal && $renewItemId <= 0) $errors['renew_item_id'] = 'Invalid renewal item.';
if ($errors) json_error('Please fix the highlighted fields.', 422, $errors);

/* ---------- Rate limit ---------- */
$rlKey = 'ord_create_' . $userId;
$now = time(); $win = 600;
if (!isset($_SESSION[$rlKey]) || $now > $_SESSION[$rlKey]['reset']) {
    $_SESSION[$rlKey] = ['count' => 1, 'reset' => $now + $win];
} else {
    $_SESSION[$rlKey]['count']++;
    if ($_SESSION[$rlKey]['count'] > 10) {
        json_error('Too many orders placed recently. Please wait a few minutes.', 429);
    }
}

/* ---------- Payment method ---------- */
$pmStmt = $pdo->prepare("SELECT id, name, code, type FROM payment_methods WHERE code = ? AND status = 1 LIMIT 1");
$pmStmt->execute([$payMethodCode]);
$paymentMethod = $pmStmt->fetch();
if (!$paymentMethod) {
    json_error('Selected payment method is not available.', 422, ['payment_method' => 'Unavailable.']);
}
if ($payMethodId > 0 && (int) $paymentMethod['id'] !== $payMethodId) {
    $payMethodId = (int) $paymentMethod['id'];
} elseif ($payMethodId === 0) {
    $payMethodId = (int) $paymentMethod['id'];
}

/* ========================================================
   DOMAIN VALIDATION (only for normal orders)
   ======================================================== */
$domainInfo = [
    'type'         => 'none',
    'name'         => null,
    'tld'          => null,
    'price'        => 0.00,
    'period_years' => 0,
    'notes'        => null,
];

if (!$isRenewal && $domainType !== 'none') {
    $domainEnabled = (int) setting('domain_enabled', 1) === 1;
    if (!$domainEnabled) {
        json_error('Domain selection is not available.', 422);
    }

    $domainInfo = calc_domain_info($domainType, $domainName, $domainTld, $domainYears);

    if (!$domainInfo['valid']) {
        json_error($domainInfo['error'] ?? 'Invalid domain info.', 422, ['domain_name' => $domainInfo['error'] ?? 'Invalid.']);
    }

    /* ----- Free subdomain uniqueness check ----- */
    if ($domainInfo['type'] === 'subdomain') {
        $checkStmt = $pdo->prepare("
            SELECT oi.id FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE oi.domain_type = 'subdomain'
              AND oi.domain_name = ?
              AND o.order_status NOT IN ('cancelled')
            LIMIT 1
        ");
        $checkStmt->execute([$domainInfo['name']]);
        if ($checkStmt->fetch()) {
            json_error('This subdomain is already taken. Please choose another.', 409, [
                'domain_name' => 'Already in use.'
            ]);
        }
    }

    /* ----- Purchased domain uniqueness check ----- */
    if ($domainInfo['type'] === 'purchased') {
        $checkStmt = $pdo->prepare("
            SELECT oi.id FROM order_items oi
            INNER JOIN orders o ON o.id = oi.order_id
            WHERE oi.domain_type = 'purchased'
              AND oi.domain_name = ?
              AND o.order_status NOT IN ('cancelled')
            LIMIT 1
        ");
        $checkStmt->execute([$domainInfo['name']]);
        if ($checkStmt->fetch()) {
            json_error('This domain is already in our system. Please choose another or use your own.', 409, [
                'domain_name' => 'Already in use.'
            ]);
        }
    }
}

/* ========================================================
   BUILD ORDER ITEMS
   ======================================================== */
$orderItemsData = [];
$subtotal       = 0.0;
$currency       = current_currency();
$isTrialOrder   = false;
$renewalData    = null;

if ($isRenewal) {
    /* ============ RENEWAL ============ */
    $rStmt = $pdo->prepare("
        SELECT oi.*, s.title_en, s.title_bn, s.slug, s.currency AS svc_currency,
               s.status AS svc_status, s.id AS svc_id, s.delivery_days AS svc_delivery_days
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        LEFT JOIN services s ON s.id = oi.service_id
        WHERE oi.id = ? AND o.user_id = ?
        LIMIT 1
    ");
    $rStmt->execute([$renewItemId, $userId]);
    $renewItem = $rStmt->fetch();

    if (!$renewItem) json_error('Renewal item not found or does not belong to you.', 404);
    if (($renewItem['package_type'] ?? '') === 'lifetime') json_error('Lifetime packages do not need renewal.', 422);
    if (($renewItem['package_type'] ?? '') === 'trial') json_error('Trial packages cannot be renewed directly.', 422);
    if ($renewItem['svc_status'] !== 'active') json_error('This service is no longer available.', 410);

    $pStmt = $pdo->prepare("SELECT * FROM service_packages WHERE id = ? LIMIT 1");
    $pStmt->execute([(int)$renewItem['package_id']]);
    $pkg = $pStmt->fetch();
    if (!$pkg || (int)$pkg['is_active'] !== 1) {
        json_error('The package is no longer available for renewal.', 410);
    }

    $months = max(1, (int)(($renewItem['duration_days'] ?? 30) / 30));
    $months = max((int)$pkg['min_months'], $months);

    $calc = calc_package_price($pkg, $months);
    $unitPrice = (float) $calc['price'];
    if ($unitPrice <= 0) json_error('Renewal price could not be calculated.', 500);

    $deliveryDays = max(1, (int)($renewItem['svc_delivery_days'] ?? setting('delivery_default_days', 3)));

    $orderItemsData[] = [
        'service_id'       => (int)$renewItem['svc_id'],
        'package_id'       => (int)$pkg['id'],
        'package_type'     => $pkg['package_type'],
        'is_trial'         => 0,
        'service_title'    => !empty($renewItem['title_bn']) && current_lang() === 'bn'
                               ? $renewItem['title_bn'] : $renewItem['title_en'],
        'package_name'     => $pkg['name'],
        'price'            => $unitPrice,
        'quantity'         => 1,
        'total'            => $unitPrice,
        'duration_days'    => (int)$calc['duration_days'],
        'duration_label'   => $calc['duration_label'],
        'months'           => $months,
        'delivery_days'    => $deliveryDays,

        // Domain — renewal এ কোনো domain change নেই
        'domain_type'      => 'none',
        'domain_name'      => null,
        'domain_tld'       => null,
        'domain_price'     => 0,
        'domain_period_years' => 0,
        'domain_notes'     => null,
    ];

    $subtotal = $unitPrice;
    $currency = $renewItem['svc_currency'] ?: current_currency();

    $renewalData = [
        'renew_item_id'     => $renewItemId,
        'original_order_id' => (int)$renewItem['order_id'],
        'old_expires_at'    => $renewItem['expires_at'],
    ];

} else {
    /* ============ NORMAL CART ============ */
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        json_error('Your cart is empty.', 422, ['cart' => 'Cart is empty.']);
    }

    foreach ($_SESSION['cart'] as $itemKey => $row) {
        $sid      = (int) ($row['service_id'] ?? 0);
        $pid      = (int) ($row['package_id'] ?? 0);
        $qty      = max(1, min(99, (int) ($row['qty'] ?? 1)));
        $months   = (int) ($row['months'] ?? 0);
        $isTrial  = !empty($row['is_trial']);
        if ($sid <= 0) continue;

        /* Fetch fresh service */
        $sStmt = $pdo->prepare("
            SELECT id, title_en, title_bn, slug, price, discount_price, currency, status, delivery_days
            FROM services WHERE id = ? LIMIT 1
        ");
        $sStmt->execute([$sid]);
        $svc = $sStmt->fetch();

        if (!$svc || $svc['status'] !== 'active') {
            unset($_SESSION['cart'][$itemKey]);
            continue;
        }

        $currency = $svc['currency'] ?: $currency;

        /* Delivery days snapshot */
        $deliveryDays = max(1, (int)($svc['delivery_days'] ?? 0));
        if ($deliveryDays <= 0) $deliveryDays = max(1, (int) setting('delivery_default_days', 3));

        $unitPrice = (float) $svc['price'];
        if (!empty($svc['discount_price']) && (float) $svc['discount_price'] > 0) {
            $unitPrice = (float) $svc['discount_price'];
        }

        $pkgName  = null;
        $pkgType  = null;
        $durDays  = 0;
        $durLabel = null;

        if ($pid > 0) {
            $pStmt = $pdo->prepare("SELECT * FROM service_packages WHERE id = ? AND service_id = ? LIMIT 1");
            $pStmt->execute([$pid, $sid]);
            $pkg = $pStmt->fetch();

            if ($pkg) {
                $pkgName = $pkg['name'];
                $pkgType = $pkg['package_type'];

                if ($pkgType === 'trial') {
                    if ((int) setting('trial_enabled', 1) !== 1) continue;
                    if ((int) setting('trial_one_per_user', 1) === 1 && has_user_used_trial($userId, $sid)) continue;
                    $calc = calc_package_price($pkg, 0);
                    $unitPrice = 0;
                    $isTrial   = true;
                    $qty       = 1;
                } elseif ($pkgType === 'lifetime') {
                    $calc = calc_package_price($pkg, 0);
                    $unitPrice = (float)$calc['price'];
                } else {
                    $calc = calc_package_price($pkg, max(1, $months));
                    $unitPrice = (float)$calc['price'];
                }

                $durDays  = (int)$calc['duration_days'];
                $durLabel = $calc['duration_label'];
            }
        }

        $lineTotal = $isTrial ? 0.0 : round($unitPrice * $qty, 2);
        $subtotal += $lineTotal;

        $title = (!empty($svc['title_bn']) && current_lang() === 'bn') ? $svc['title_bn'] : $svc['title_en'];

        $orderItemsData[] = [
            'service_id'       => $sid,
            'package_id'       => $pid ?: null,
            'package_type'     => $pkgType,
            'is_trial'         => $isTrial ? 1 : 0,
            'service_title'    => $title,
            'package_name'     => $pkgName,
            'price'            => $isTrial ? 0 : $unitPrice,
            'quantity'         => $qty,
            'total'            => $lineTotal,
            'duration_days'    => $durDays,
            'duration_label'   => $durLabel,
            'months'           => $pkgType === 'subscription' ? max(1, $months) : 0,
            'delivery_days'    => $deliveryDays,

            // Domain — apply to first item only (main product)
            'domain_type'      => 'none',
            'domain_name'      => null,
            'domain_tld'       => null,
            'domain_price'     => 0,
            'domain_period_years' => 0,
            'domain_notes'     => null,
        ];

        if ($isTrial) $isTrialOrder = true;
    }

    if (empty($orderItemsData)) {
        json_error('No valid items in your cart. Please add services again.', 422);
    }

    /* ---------- Apply domain to the first item (main product) ---------- */
    if ($domainInfo['type'] !== 'none' && !empty($orderItemsData)) {
        $orderItemsData[0]['domain_type']         = $domainInfo['type'];
        $orderItemsData[0]['domain_name']         = $domainInfo['name'];
        $orderItemsData[0]['domain_tld']          = $domainInfo['tld'];
        $orderItemsData[0]['domain_price']        = $domainInfo['price'];
        $orderItemsData[0]['domain_period_years'] = $domainInfo['period_years'];
        $orderItemsData[0]['domain_notes']        = $domainInfo['notes'];
    }
}

/* ========================================================
   COUPON (normal only)
   ======================================================== */
$discount        = 0.0;
$couponCodeFinal = null;
$couponRow       = null;

if (!$isRenewal && $couponCode !== '') {
    $cStmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 1 LIMIT 1");
    $cStmt->execute([$couponCode]);
    $couponRow = $cStmt->fetch();

    if (!$couponRow) json_error('Invalid coupon code.', 422, ['coupon' => 'Invalid.']);
    $today = date('Y-m-d');
    if (!empty($couponRow['start_date']) && $couponRow['start_date'] > $today) json_error('Coupon is not yet active.', 422, ['coupon' => 'Not active.']);
    if (!empty($couponRow['end_date']) && $couponRow['end_date'] < $today) json_error('Coupon has expired.', 422, ['coupon' => 'Expired.']);
    if (!empty($couponRow['usage_limit']) && (int) $couponRow['used_count'] >= (int) $couponRow['usage_limit']) json_error('Coupon usage limit reached.', 422, ['coupon' => 'Limit reached.']);
    if ((float) $couponRow['min_order'] > 0 && $subtotal < (float) $couponRow['min_order']) json_error('Minimum order for this coupon is ' . money((float) $couponRow['min_order']) . '.', 422, ['coupon' => 'Min order not met.']);
    if ((int) $couponRow['per_user_limit'] > 0) {
        $usedStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND coupon_code = ?");
        $usedStmt->execute([$userId, $couponCode]);
        if ((int) $usedStmt->fetchColumn() >= (int) $couponRow['per_user_limit']) json_error('You have already used this coupon.', 422, ['coupon' => 'Already used.']);
    }

    if ($couponRow['type'] === 'percent') {
        $discount = round($subtotal * ((float) $couponRow['value'] / 100), 2);
        if (!empty($couponRow['max_discount']) && $discount > (float) $couponRow['max_discount']) {
            $discount = (float) $couponRow['max_discount'];
        }
    } else {
        $discount = (float) $couponRow['value'];
    }
    $discount = min($discount, $subtotal);
    $couponCodeFinal = $couponCode;
}

/* ---------- Domain price ---------- */
$domainPrice = 0.0;
if (!$isRenewal && $domainInfo['type'] !== 'none') {
    $domainPrice = (float) $domainInfo['price'];
}

/* ---------- Totals ---------- */
$tax   = 0.00;
$total = max(0, round($subtotal + $domainPrice - $discount + $tax, 2));

/* ---------- Order number ---------- */
$orderNumber = 'WCB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

/* ---------- Manual payment txn requirement ---------- */
$isManual = ($paymentMethod['type'] === 'manual');
if ($isManual) {
    if ($txnId === '') {
        json_error('Transaction ID is required for manual payment.', 422, ['transaction_id' => 'Required.']);
    }
    if (strlen($txnId) < 4) {
        json_error('Transaction ID looks invalid.', 422, ['transaction_id' => 'Too short.']);
    }
    if ($senderNumber === null || strlen($senderNumber) < 6) {
        json_error('Sender number is required and must be valid.', 422, ['sender_number' => 'Enter valid number.']);
    }
}

/* ========================================================
   DB Transaction
   ======================================================== */
try {
    $pdo->beginTransaction();

    /* Order */
    $insOrder = $pdo->prepare("
        INSERT INTO orders
            (order_number, user_id, subtotal, discount, tax, total, currency,
             coupon_code, payment_method, payment_status, order_status,
             is_renewal, renews_item_id, renewal_of_order_id,
             customer_name, customer_email, customer_phone, billing_address, notes,
             created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending',
                ?, ?, ?,
                ?, ?, ?, ?, ?, NOW())
    ");
    $insOrder->execute([
        $orderNumber, $userId,
        $subtotal, $discount, $tax, $total, $currency,
        $couponCodeFinal,
        $paymentMethod['code'],
        $isRenewal ? 1 : 0,
        $isRenewal ? $renewalData['renew_item_id'] : null,
        $isRenewal ? $renewalData['original_order_id'] : null,
        $customerName, $customerEmail, $customerPhone, $billingAddr, $notes,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    /* Order items */
    $insItem = $pdo->prepare("
        INSERT INTO order_items
            (order_id, service_id, package_id, package_type, is_trial,
             service_title, package_name, price, quantity, total,
             duration_days, duration_label, starts_at, expires_at,
             delivery_days,
             domain_type, domain_name, domain_tld, domain_price, domain_period_years, domain_notes,
             delivery_status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    foreach ($orderItemsData as $it) {
        $insItem->execute([
            $orderId,
            $it['service_id'],
            $it['package_id'],
            $it['package_type'],
            $it['is_trial'],
            $it['service_title'],
            $it['package_name'],
            $it['price'],
            $it['quantity'],
            $it['total'],
            $it['duration_days'],
            $it['duration_label'],
            $it['delivery_days'],
            $it['domain_type'],
            $it['domain_name'],
            $it['domain_tld'],
            $it['domain_price'],
            $it['domain_period_years'],
            $it['domain_notes'],
        ]);
    }

    /* Payment */
    $insPay = $pdo->prepare("
        INSERT INTO payments
            (order_id, user_id, method, amount, currency,
             transaction_id, sender_number, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $insPay->execute([
        $orderId, $userId, $paymentMethod['code'],
        $total, $currency,
        $txnId ?: null, $senderNumber ?: null,
    ]);

    /* Coupon usage */
    if ($couponRow) {
        $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")
            ->execute([(int) $couponRow['id']]);
    }

    /* Sales count */
    foreach ($orderItemsData as $it) {
        if ((int)$it['is_trial'] === 1) continue;
        $pdo->prepare("UPDATE services SET total_sales = total_sales + ? WHERE id = ?")
            ->execute([(int) $it['quantity'], (int) $it['service_id']]);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (WCB_ENV === 'development') json_error('Order create failed: ' . $e->getMessage(), 500);
    json_error('Could not create order. Please try again.', 500);
}

/* ---------- Clear cart ---------- */
if (!$isRenewal) {
    unset($_SESSION['cart']);
    unset($_SESSION['coupon']);
}

/* ---------- Notifications ---------- */
try {
    $title = $isRenewal ? '🔄 Renewal submitted — #' . $orderNumber : 'Order placed — #' . $orderNumber;
    $msg = $isRenewal
        ? 'আপনার renewal order জমা হয়েছে। Admin approve করলে subscription extend হবে।'
        : 'আপনার অর্ডার সফলভাবে জমা হয়েছে। পেমেন্ট কনফার্ম হলে আমরা কাজ শুরু করব।';
    push_notification('user', $userId, $title, $msg, 'user/order-details.php?id=' . $orderId, 'order');

    $adminTitle = $isRenewal ? 'Renewal #' . $orderNumber : 'New order #' . $orderNumber;
    push_notification(
        'admin', null, $adminTitle,
        $customerName . ' placed ' . ($isRenewal ? 'a renewal' : 'an order') . ' of ' . money($total, $currency) . '.',
        'admin/order-details.php?id=' . $orderId,
        'order'
    );
} catch (Exception $e) { /* silent */ }

/* ---------- Email ---------- */
try {
    $siteName = setting('site_name', 'Webcyno');
    $subj = ($isRenewal ? 'Renewal' : 'Order') . " Confirmation — #{$orderNumber}";

    $domainLine = '';
    if (!$isRenewal && $domainInfo['type'] !== 'none') {
        $domainLine = '<tr><td style="padding:8px 0;color:#64748B;">Domain</td><td style="text-align:right;">'
                    . e($domainInfo['name']) . ' (' . e($domainInfo['type']) . ')</td></tr>';
    }

    $bodyHtml = '
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#0F172A;">
            <h2 style="color:#2563EB;margin:0 0 12px;">' . e($siteName) . '</h2>
            <p>Hi ' . e($customerName) . ',</p>
            <p>' . ($isRenewal ? 'Your renewal request has been received.' : 'Thank you for your order!') . '</p>
            <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
                <tr><td style="padding:8px 0;color:#64748B;">Order</td><td style="text-align:right;font-weight:600;">#' . e($orderNumber) . '</td></tr>
                ' . $domainLine . '
                <tr><td style="padding:8px 0;color:#64748B;">Subtotal</td><td style="text-align:right;">' . e(money($subtotal, $currency)) . '</td></tr>
                ' . ($domainPrice > 0 ? '<tr><td style="padding:8px 0;color:#64748B;">Domain</td><td style="text-align:right;">' . e(money($domainPrice, $currency)) . '</td></tr>' : '') . '
                ' . ($discount > 0 ? '<tr><td style="padding:8px 0;color:#64748B;">Discount</td><td style="text-align:right;color:#16A34A;">− ' . e(money($discount, $currency)) . '</td></tr>' : '') . '
                <tr><td style="padding:12px 0;border-top:1px solid #E2E8F0;font-weight:700;">Total</td><td style="text-align:right;border-top:1px solid #E2E8F0;font-weight:800;color:#2563EB;">' . e(money($total, $currency)) . '</td></tr>
            </table>
            <p>Payment method: <strong>' . e($paymentMethod['name']) . '</strong></p>
            <p><a href="' . base_url('user/order-details.php?id=' . $orderId) . '" style="display:inline-block;background:#2563EB;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;">View Order</a></p>
            <hr style="border:none;border-top:1px solid #E2E8F0;margin:20px 0;">
            <p style="font-size:12px;color:#94A3B8;">' . e(setting('footer_copyright', '© ' . date('Y') . ' ' . $siteName)) . '</p>
        </div>';

    send_email($customerEmail, $subj, $bodyHtml, $customerName);
} catch (Exception $e) { /* silent */ }

/* ---------- Log ---------- */
try {
    $logMsg = ($isRenewal ? 'Renewal ' : 'Order ') . "#{$orderNumber}, total {$total} {$currency}";
    if ($isRenewal) $logMsg .= " — renews item #{$renewalData['renew_item_id']}";
    if (!$isRenewal && $domainInfo['type'] !== 'none') {
        $logMsg .= " — domain: {$domainInfo['type']}:{$domainInfo['name']}";
    }
    log_activity($isRenewal ? 'order_renewal_created' : 'order_created', 'orders', $orderId, $logMsg);
} catch (Exception $e) { /* silent */ }

/* ---------- Response ---------- */
json_success([
    'order_id'       => $orderId,
    'order_number'   => $orderNumber,
    'is_renewal'     => $isRenewal ? 1 : 0,
    'renew_item_id'  => $isRenewal ? $renewalData['renew_item_id'] : null,
    'subtotal'       => $subtotal,
    'domain_price'   => $domainPrice,
    'domain_type'    => $domainInfo['type'],
    'domain_name'    => $domainInfo['name'],
    'discount'       => $discount,
    'total'          => $total,
    'total_fmt'      => money($total, $currency),
    'currency'       => $currency,
    'payment_status' => 'pending',
    'redirect'       => base_url('user/order-details.php?id=' . $orderId),
], $isRenewal ? 'Renewal submitted successfully!' : 'Order placed successfully!', 201);