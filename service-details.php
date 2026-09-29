<?php
/**
 * service-details.php — Single Service View (v3.0)
 * Packages: Trial / Subscription / Lifetime + Order Now
 * Delivery days + Domain info
 */

require_once __DIR__ . '/includes/header.php';

$slug = trim((string) input('slug', ''));
if ($slug === '') { header('Location: ' . base_url('services.php')); exit; }

global $pdo;

/* ---------- Fetch service ---------- */
$stmt = $pdo->prepare("
    SELECT s.*, c.name_en AS cat_name_en, c.name_bn AS cat_name_bn, c.slug AS cat_slug
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE s.slug = ? AND s.status = 'active'
    LIMIT 1
");
$stmt->execute([$slug]);
$service = $stmt->fetch();

if (!$service) {
    http_response_code(404);
    ?>
    <main id="main-content">
        <div class="container" style="padding:var(--space-2xl) 0;">
            <div class="card empty-state">
                <h2><?= e(t('service_not_found', 'Service not found')) ?></h2>
                <p><?= e(t('service_not_found_sub', 'The service you are looking for does not exist or has been removed.')) ?></p>
                <a class="btn btn-primary btn-sm" style="margin-top:var(--space);" href="<?= e(base_url('services.php')) ?>"><?= e(t('browse_services', 'Browse Services')) ?></a>
            </div>
        </div>
    </main>
    <?php require_once __DIR__ . '/includes/footer.php'; exit;
}

$serviceId = (int) $service['id'];

/* ---------- Localized ---------- */
$title     = ($lang === 'bn' && !empty($service['title_bn'])) ? $service['title_bn'] : $service['title_en'];
$shortDesc = ($lang === 'bn' && !empty($service['short_desc_bn'])) ? $service['short_desc_bn'] : ($service['short_desc_en'] ?? '');
$longDesc  = ($lang === 'bn' && !empty($service['description_bn'])) ? $service['description_bn'] : ($service['description_en'] ?? '');
$catName   = ($lang === 'bn' && !empty($service['cat_name_bn'])) ? $service['cat_name_bn'] : ($service['cat_name_en'] ?? '');

$page_title = $service['meta_title'] ?: ($title . ' — ' . setting('site_name', 'Webcyno'));
$meta_desc  = $service['meta_desc']  ?: ($shortDesc ?: setting('site_description'));

/* ---------- Base price ---------- */
$price = (float) $service['price'];
$disc  = !empty($service['discount_price']) ? (float) $service['discount_price'] : 0;
$final = $disc > 0 ? $disc : $price;

/* ---------- Delivery days ---------- */
$deliveryDays = max(1, (int)($service['delivery_days'] ?? setting('delivery_default_days', 3)));
$deliveryTimeLabel = trim((string)($service['delivery_time'] ?? ''));

/* ---------- Gallery ---------- */
$gallery = [];
if (!empty($service['gallery'])) {
    $decoded = json_decode($service['gallery'], true);
    if (is_array($decoded)) $gallery = $decoded;
}
$mainImage = $service['thumbnail'] ? base_url($service['thumbnail']) : base_url('assets/images/placeholder.png');

/* ---------- Features ---------- */
$features = [];
if (!empty($service['features_en'])) {
    $decoded = json_decode(($lang === 'bn' && !empty($service['features_bn'])) ? $service['features_bn'] : $service['features_en'], true);
    if (is_array($decoded)) $features = $decoded;
}

/* ---------- Packages ---------- */
$packages       = get_service_packages($serviceId);
$trialPkg       = null;
$subPkg         = null;
$lifePkg        = null;
foreach ($packages as $p) {
    if ($p['package_type'] === 'trial' && !$trialPkg)         $trialPkg = $p;
    elseif ($p['package_type'] === 'subscription' && !$subPkg) $subPkg   = $p;
    elseif ($p['package_type'] === 'lifetime' && !$lifePkg)    $lifePkg  = $p;
}

/* ---------- Trial status ---------- */
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userHasTrial = false;
if ($userId > 0 && $trialPkg) {
    $userHasTrial = has_user_used_trial($userId, $serviceId);
}
$trialEnabled = (int) setting('trial_enabled', 1) === 1;
$trialOnePer  = (int) setting('trial_one_per_user', 1) === 1;

/* ---------- Subscription duration options ---------- */
$durationOptions = [];
if ($subPkg) {
    $minM = max(1, (int)($subPkg['min_months'] ?? 1));
    $maxM = max($minM, (int)($subPkg['max_months'] ?? 60));
    $opts = [1, 3, 6, 12, 24, 36];
    foreach ($opts as $m) {
        if ($m >= $minM && $m <= $maxM && !in_array($m, array_column($durationOptions, 'months'))) {
            $calc = calc_package_price($subPkg, $m);
            $durationOptions[] = [
                'months' => $m,
                'label'  => format_duration_label($m),
                'days'   => $calc['duration_days'],
                'price'  => $calc['price'],
            ];
        }
    }
    if (!in_array($minM, array_column($durationOptions, 'months'))) {
        $calc = calc_package_price($subPkg, $minM);
        array_unshift($durationOptions, ['months'=>$minM,'label'=>format_duration_label($minM),'days'=>$calc['duration_days'],'price'=>$calc['price']]);
    }
    usort($durationOptions, fn($a, $b) => $a['months'] <=> $b['months']);
}

/* ---------- Domain feature ---------- */
$domainEnabled = (int) setting('domain_enabled', 1) === 1;

/* ---------- Reviews ---------- */
$revStmt = $pdo->prepare("
    SELECT r.rating, r.comment, r.created_at, u.name AS user_name
    FROM reviews r
    LEFT JOIN users u ON u.id = r.user_id
    WHERE r.service_id = ? AND r.status = 'approved'
    ORDER BY r.id DESC
    LIMIT 6
");
$revStmt->execute([$serviceId]);
$reviews = $revStmt->fetchAll();

$ratingDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$distStmt = $pdo->prepare("
    SELECT rating, COUNT(*) AS cnt FROM reviews
    WHERE service_id = ? AND status = 'approved'
    GROUP BY rating
");
$distStmt->execute([$serviceId]);
foreach ($distStmt->fetchAll() as $row) {
    $ratingDist[(int) $row['rating']] = (int) $row['cnt'];
}

/* ---------- Related ---------- */
$relStmt = $pdo->prepare("
    SELECT s.*, c.name_en AS category_name
    FROM services s
    LEFT JOIN categories c ON c.id = s.category_id
    WHERE s.category_id = ? AND s.id != ? AND s.status = 'active'
    ORDER BY s.total_sales DESC
    LIMIT 4
");
$relStmt->execute([$service['category_id'], $serviceId]);
$related = $relStmt->fetchAll();

/* ---------- Demo ---------- */
$hasDemo      = !empty($service['demo_path']) && (int) $service['demo_active'] === 1;
$demoSiteUrl  = '';
$demoAdminUrl = '';
if ($hasDemo) {
    $rawSiteUrl = trim((string) ($service['demo_site_url'] ?? ''));
    $demoSiteUrl = ($rawSiteUrl !== '' && preg_match('#^https?://#i', $rawSiteUrl)) ? $rawSiteUrl : base_url($service['demo_path'] . '/');
    $rawAdminUrl = trim((string) ($service['demo_admin_url'] ?? ''));
    $demoAdminUrl = ($rawAdminUrl !== '' && preg_match('#^https?://#i', $rawAdminUrl)) ? $rawAdminUrl : base_url($service['demo_path'] . '/admin/');
}
$demoUser  = trim((string) ($service['demo_admin_user'] ?? ''));
$demoPass  = trim((string) ($service['demo_admin_pass'] ?? ''));
$demoNotes = trim((string) ($service['demo_notes'] ?? ''));
?>

<main id="main-content">
    <div class="container" style="padding-top:var(--space-lg);padding-bottom:var(--space-2xl);">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?= e(base_url('index.php')) ?>"><?= e(t('home')) ?></a>
            <span class="sep">/</span>
            <a href="<?= e(base_url('services.php')) ?>"><?= e(t('services')) ?></a>
            <?php if (!empty($service['cat_slug'])): ?>
                <span class="sep">/</span>
                <a href="<?= e(base_url('category.php?slug=' . urlencode($service['cat_slug']))) ?>"><?= e($catName) ?></a>
            <?php endif; ?>
            <span class="sep">/</span>
            <span class="current"><?= e(str_limit($title, 40)) ?></span>
        </nav>

        <!-- Top grid -->
        <div class="service-top-grid" style="display:grid;grid-template-columns:1.1fr 1fr;gap:var(--space-xl);align-items:start;margin-bottom:var(--space-2xl);">

            <!-- Gallery -->
            <div>
                <div class="card" style="padding:0;overflow:hidden;">
                    <img id="main-image" src="<?= e($mainImage) ?>" alt="<?= e($title) ?>" style="width:100%;aspect-ratio:16/10;object-fit:cover;display:block;">
                </div>
                <?php if ($gallery): ?>
                    <div class="flex gap-sm" style="margin-top:var(--space-sm);flex-wrap:wrap;">
                        <button type="button" class="thumb-btn" data-src="<?= e($mainImage) ?>" style="width:80px;height:60px;padding:0;border:2px solid var(--primary);border-radius:var(--radius);overflow:hidden;cursor:pointer;background:none;">
                            <img src="<?= e($mainImage) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                        </button>
                        <?php foreach ($gallery as $g): if (empty($g)) continue; ?>
                            <button type="button" class="thumb-btn" data-src="<?= e(base_url($g)) ?>" style="width:80px;height:60px;padding:0;border:2px solid var(--border);border-radius:var(--radius);overflow:hidden;cursor:pointer;background:none;">
                                <img src="<?= e(base_url($g)) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div>
                <span class="tag tag-primary" style="margin-bottom:10px;"><?= e($catName) ?></span>
                <h1 style="font-size:var(--fs-3xl);margin-bottom:var(--space-sm);"><?= e($title) ?></h1>

                <div class="flex items-center gap" style="margin-bottom:var(--space);flex-wrap:wrap;">
                    <span class="stars">
                        <?php $stars = max(1, min(5, (int) round((float) $service['rating']))); ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg viewBox="0 0 24 24" class="<?= $i > $stars ? 'empty' : '' ?>"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18.5 5.5 22 7 14.5 2 9.5 9 9"/></svg>
                        <?php endfor; ?>
                    </span>
                    <span class="text-muted" style="font-size:var(--fs-sm);">
                        <?= number_format((float) $service['rating'], 1) ?> (<?= (int) $service['total_reviews'] ?> <?= e(t('reviews', 'reviews')) ?>)
                    </span>
                    <?php if (!empty($service['total_sales'])): ?>
                        <span class="text-muted" style="font-size:var(--fs-sm);">• <?= (int) $service['total_sales'] ?> <?= e(t('sold', 'sold')) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($shortDesc): ?>
                    <p style="font-size:var(--fs-md);color:var(--text-muted);margin-bottom:var(--space);"><?= e($shortDesc) ?></p>
                <?php endif; ?>

                <!-- ✨ Delivery + Domain badges row -->
                <div class="svc-meta-badges">
                    <div class="svc-meta-badge">
                        <span class="smb-icon">⏱</span>
                        <div>
                            <div class="smb-label">Delivery</div>
                            <div class="smb-value"><?= $deliveryDays ?> <?= e(t('days', 'Days')) ?><?= $deliveryTimeLabel ? ' (' . e($deliveryTimeLabel) . ')' : '' ?></div>
                        </div>
                    </div>

                    <?php if ($domainEnabled): ?>
                    <div class="svc-meta-badge" style="background:linear-gradient(135deg,#EFF6FF,#F8FAFC);border-color:#BFDBFE;">
                        <span class="smb-icon">🌐</span>
                        <div>
                            <div class="smb-label">Domain Included</div>
                            <div class="smb-value">Own / Free Sub / .com</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Base price -->
                <div style="padding:var(--space) 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border);margin-bottom:var(--space);">
                    <div class="flex items-center gap-sm" style="flex-wrap:wrap;">
                        <span class="text-muted" style="font-size:var(--fs-xs);text-transform:uppercase;letter-spacing:.5px;"><?= e(t('starting_from', 'Starting from')) ?></span>
                    </div>
                    <div class="flex items-center gap-sm" style="flex-wrap:wrap;margin-top:4px;">
                        <span style="font-size:var(--fs-3xl);font-weight:800;color:var(--navy);"><?= e(money($final)) ?></span>
                        <?php if ($disc > 0): ?>
                            <span class="text-light" style="text-decoration:line-through;font-size:var(--fs-lg);"><?= e(money($price)) ?></span>
                            <span class="tag tag-danger">-<?= round((1 - $disc / max($price, 1)) * 100) ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick features -->
                <?php if ($features): ?>
                    <ul style="margin-bottom:var(--space);">
                        <?php foreach (array_slice($features, 0, 5) as $f): ?>
                            <li class="flex items-start gap-sm" style="margin-bottom:8px;font-size:var(--fs-sm);">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" style="flex-shrink:0;margin-top:2px;"><polyline points="20 6 9 17 4 12"/></svg>
                                <span><?= e(is_string($f) ? $f : ($f['title'] ?? json_encode($f))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <!-- Trust badges -->
                <div class="flex items-center gap-md" style="flex-wrap:wrap;font-size:var(--fs-xs);color:var(--text-muted);">
                    <span class="flex items-center gap-xs">🛡 <?= e(t('secure_payment', 'Secure Payment')) ?></span>
                    <span class="flex items-center gap-xs">✓ <?= e(t('money_back', 'Money Back')) ?></span>
                    <span class="flex items-center gap-xs">⏱ <?= e(t('fast_delivery', 'Fast Delivery')) ?></span>
                </div>
            </div>
        </div>

        <!-- ============ PACKAGES SECTION ============ -->
        <?php if ($trialPkg || $subPkg || $lifePkg): ?>
        <section id="packages-section" style="margin-bottom:var(--space-2xl);">
            <div style="text-align:center;margin-bottom:var(--space-lg);">
                <h2 style="font-size:var(--fs-2xl);margin-bottom:6px;">🎁 <?= e(t('choose_your_package', 'Choose Your Package')) ?></h2>
                <p class="text-muted"><?= e(t('package_subtitle', 'Start with a free trial or pick a plan that fits your needs')) ?></p>
            </div>

            <div class="pkg-cards-grid">

                <!-- TRIAL CARD -->
                <?php if ($trialPkg && $trialEnabled):
                    $trialDays  = max(1, (int)$trialPkg['trial_days']);
                    $alertHours = (int)$trialPkg['alert_hours_before'];
                    $tFeatures  = [];
                    if (!empty($trialPkg['features'])) {
                        $d = json_decode($trialPkg['features'], true);
                        if (is_array($d)) $tFeatures = $d;
                    }
                    $isAvailable = !($trialOnePer && $userHasTrial);
                ?>
                <div class="pkg-card pkg-card-trial<?= !$isAvailable ? ' is-disabled' : '' ?>">
                    <?php if ($isAvailable): ?>
                        <span class="pkg-ribbon pkg-ribbon-green">FREE</span>
                    <?php else: ?>
                        <span class="pkg-ribbon pkg-ribbon-gray">USED</span>
                    <?php endif; ?>

                    <div class="pkg-icon" style="background:linear-gradient(135deg,#10B981,#059669);">🎁</div>
                    <h3 class="pkg-name"><?= e($trialPkg['name'] ?: 'Free Trial') ?></h3>
                    <div class="pkg-price"><span class="pkg-amount">FREE</span></div>
                    <p class="pkg-duration"><?= $trialDays ?> <?= e(t('days', 'Days')) ?></p>

                    <?php if ($trialPkg['description']): ?>
                        <p class="pkg-desc"><?= e($trialPkg['description']) ?></p>
                    <?php endif; ?>

                    <?php if ($tFeatures): ?>
                        <ul class="pkg-features">
                            <?php foreach ($tFeatures as $f): ?>
                                <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span><?= e(is_string($f) ? $f : ($f['title'] ?? '')) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!$isAvailable): ?>
                        <div class="pkg-notice pkg-notice-gray">✓ <?= e(t('trial_already_used', 'You have already used this trial')) ?></div>
                    <?php else: ?>
                        <div class="pkg-notice pkg-notice-blue">⏱ <?= e(t('alert_will_be_sent', 'Alert')) ?> <?= $alertHours ?>h <?= e(t('before_expiry', 'before expiry')) ?></div>
                        <button type="button" class="btn btn-success btn-block pkg-cta"
                                data-pkg-order
                                data-service-id="<?= $serviceId ?>"
                                data-package-id="<?= (int)$trialPkg['id'] ?>"
                                data-package-type="trial"
                                data-package-name="<?= e($trialPkg['name']) ?>"
                                data-price="0">
                            <?= e(t('start_free_trial', 'Start Free Trial')) ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- SUBSCRIPTION CARD -->
                <?php if ($subPkg):
                    $sFeatures = [];
                    if (!empty($subPkg['features'])) {
                        $d = json_decode($subPkg['features'], true);
                        if (is_array($d)) $sFeatures = $d;
                    }
                    $defaultOpt = $durationOptions[0] ?? ['months'=>1,'price'=>(float)$subPkg['price_per_month'],'label'=>'1 Month','days'=>30];
                    foreach ($durationOptions as $opt) { if ($opt['months'] === 12) { $defaultOpt = $opt; break; } }
                    $pricePerMonth = (float) $subPkg['price_per_month'];
                    $pricePerYear  = (float) $subPkg['price_per_year'];
                    $saveYear = ($pricePerMonth > 0 && $pricePerYear > 0)
                                ? max(0, round((1 - ($pricePerYear / 12) / $pricePerMonth) * 100))
                                : 0;
                ?>
                <div class="pkg-card pkg-card-sub pkg-card-popular">
                    <span class="pkg-ribbon pkg-ribbon-blue"><?= e(t('popular', 'MOST POPULAR')) ?></span>

                    <div class="pkg-icon" style="background:linear-gradient(135deg,#3B82F6,#2563EB);">📅</div>
                    <h3 class="pkg-name"><?= e($subPkg['name'] ?: 'Subscription') ?></h3>

                    <div class="pkg-price">
                        <span class="pkg-amount" id="sub-price-display"><?= e(money($defaultOpt['price'])) ?></span>
                        <span class="pkg-per" id="sub-duration-display">/ <?= e($defaultOpt['label']) ?></span>
                    </div>

                    <?php if ($saveYear > 0): ?>
                        <p class="pkg-save-badge">💚 <?= e(t('save_up_to', 'Save up to')) ?> <?= $saveYear ?>%</p>
                    <?php endif; ?>

                    <div class="pkg-duration-select">
                        <label style="font-size:var(--fs-xs);color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;font-weight:600;margin-bottom:6px;display:block;">
                            <?= e(t('choose_duration', 'Choose Duration')) ?>
                        </label>
                        <select id="sub-duration-select" class="form-control" style="font-weight:600;">
                            <?php foreach ($durationOptions as $opt):
                                $perMonth = $opt['months'] > 0 ? round($opt['price'] / $opt['months'], 2) : $opt['price'];
                            ?>
                                <option value="<?= (int)$opt['months'] ?>"
                                        data-price="<?= e($opt['price']) ?>"
                                        data-label="<?= e($opt['label']) ?>"
                                        data-days="<?= (int)$opt['days'] ?>"
                                        data-permonth="<?= e($perMonth) ?>"
                                        <?= $opt['months'] === $defaultOpt['months'] ? 'selected' : '' ?>>
                                    <?= e($opt['label']) ?> — <?= e(money($opt['price'])) ?>
                                    <?php if ($opt['months'] > 1): ?> (<?= e(money($perMonth)) ?>/mo)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($subPkg['description']): ?>
                        <p class="pkg-desc"><?= e($subPkg['description']) ?></p>
                    <?php endif; ?>

                    <?php if ($sFeatures): ?>
                        <ul class="pkg-features">
                            <?php foreach ($sFeatures as $f): ?>
                                <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span><?= e(is_string($f) ? $f : ($f['title'] ?? '')) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <button type="button" class="btn btn-primary btn-block pkg-cta"
                            data-pkg-order
                            data-service-id="<?= $serviceId ?>"
                            data-package-id="<?= (int)$subPkg['id'] ?>"
                            data-package-type="subscription"
                            data-package-name="<?= e($subPkg['name']) ?>"
                            data-price="<?= e($defaultOpt['price']) ?>"
                            id="sub-order-btn">
                        <?= e(t('order_now', 'Order Now')) ?>
                    </button>
                </div>
                <?php endif; ?>

                <!-- LIFETIME CARD -->
                <?php if ($lifePkg):
                    $lFeatures = [];
                    if (!empty($lifePkg['features'])) {
                        $d = json_decode($lifePkg['features'], true);
                        if (is_array($d)) $lFeatures = $d;
                    }
                    $lPrice = (float)($lifePkg['lifetime_price'] ?: $lifePkg['price']);
                ?>
                <div class="pkg-card pkg-card-life">
                    <span class="pkg-ribbon pkg-ribbon-amber">♾️ <?= e(t('best_value', 'BEST VALUE')) ?></span>

                    <div class="pkg-icon" style="background:linear-gradient(135deg,#F59E0B,#D97706);">♾️</div>
                    <h3 class="pkg-name"><?= e($lifePkg['name'] ?: 'Lifetime') ?></h3>

                    <div class="pkg-price">
                        <span class="pkg-amount"><?= e(money($lPrice)) ?></span>
                        <span class="pkg-per"><?= e(t('one_time', 'one-time')) ?></span>
                    </div>
                    <p class="pkg-duration"><?= e(t('lifetime_access', 'Lifetime Access')) ?></p>

                    <?php if ($lifePkg['description']): ?>
                        <p class="pkg-desc"><?= e($lifePkg['description']) ?></p>
                    <?php endif; ?>

                    <?php if ($lFeatures): ?>
                        <ul class="pkg-features">
                            <?php foreach ($lFeatures as $f): ?>
                                <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span><?= e(is_string($f) ? $f : ($f['title'] ?? '')) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <button type="button" class="btn btn-warning btn-block pkg-cta"
                            data-pkg-order
                            data-service-id="<?= $serviceId ?>"
                            data-package-id="<?= (int)$lifePkg['id'] ?>"
                            data-package-type="lifetime"
                            data-package-name="<?= e($lifePkg['name']) ?>"
                            data-price="<?= e($lPrice) ?>">
                        <?= e(t('own_it_forever', 'Own It Forever')) ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- ✨ Domain info notice (below packages) -->
            <?php if ($domainEnabled): ?>
            <div class="domain-notice">
                <div class="dn-icon">🌐</div>
                <div style="flex:1;">
                    <div class="dn-title"><?= e(t('domain_included', 'Domain Included')) ?></div>
                    <div class="dn-sub">Order করার সময় আপনি ৩টি অপশন পাবেন:</div>
                    <div class="dn-options">
                        <span class="dn-chip">✅ <?= e(t('domain_own', 'নিজের domain add')) ?></span>
                        <span class="dn-chip">🆓 <?= e(t('domain_free', 'আমাদের free subdomain')) ?></span>
                        <span class="dn-chip">💳 <?= e(t('domain_buy', '.com বা অন্য TLD কিনা')) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- Demo section (unchanged) -->
        <?php if ($hasDemo): ?>
        <section style="margin-bottom:var(--space-2xl);">
            <div class="card" style="padding:var(--space);border:2px solid var(--primary-soft);background:linear-gradient(135deg,#F8FAFC,#EFF6FF);">
                <div class="flex items-start gap-sm" style="margin-bottom:var(--space);">
                    <span style="width:42px;height:42px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;border-radius:var(--radius-md);flex-shrink:0;">🎬</span>
                    <div style="flex:1;">
                        <h3 style="font-size:var(--fs-lg);margin-bottom:4px;color:var(--navy);"><?= e(t('live_demo', 'Live Demo Available')) ?></h3>
                        <p class="text-muted" style="font-size:var(--fs-sm);margin:0;"><?= e(t('live_demo_sub', 'Try before you buy')) ?></p>
                    </div>
                </div>
                <div class="flex gap-sm" style="flex-wrap:wrap;">
                    <a href="<?= e($demoSiteUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary">🌐 <?= e(t('view_demo', 'View Demo')) ?></a>
                    <?php if ($demoAdminUrl): ?>
                        <a href="<?= e($demoAdminUrl) ?>" target="_blank" rel="noopener" class="btn btn-soft">🔐 <?= e(t('admin_panel', 'Admin Panel')) ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($demoUser || $demoPass): ?>
                    <div style="margin-top:var(--space);padding-top:var(--space);border-top:1px solid var(--border);font-size:var(--fs-sm);display:flex;gap:16px;flex-wrap:wrap;">
                        <?php if ($demoUser): ?><span class="text-muted">User: <strong><?= e($demoUser) ?></strong></span><?php endif; ?>
                        <?php if ($demoPass): ?><span class="text-muted">Pass: <strong><?= e($demoPass) ?></strong></span><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($demoNotes): ?>
                    <div style="margin-top:var(--space);padding:10px 12px;background:#FEF3C7;border-radius:var(--radius);font-size:var(--fs-sm);color:#92400E;">💡 <?= nl2br(e($demoNotes)) ?></div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Description + Reviews (unchanged) -->
        <div class="service-bottom-grid" style="display:grid;grid-template-columns:2fr 1fr;gap:var(--space-xl);align-items:start;">
            <div>
                <div class="card" style="padding:var(--space-lg);">
                    <h2 style="font-size:var(--fs-xl);margin-bottom:var(--space);"><?= e(t('description', 'Description')) ?></h2>
                    <div class="service-description" style="font-size:var(--fs-base);line-height:1.75;">
                        <?= $longDesc ?: '<p class="text-muted">' . e(t('no_description', 'No description provided yet.')) . '</p>' ?>
                    </div>
                    <?php if ($features): ?>
                        <h3 style="font-size:var(--fs-lg);margin:var(--space-lg) 0 var(--space);"><?= e(t('whats_included', "What's Included")) ?></h3>
                        <ul>
                            <?php foreach ($features as $f): ?>
                                <li class="flex items-start gap-sm" style="margin-bottom:10px;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" style="flex-shrink:0;margin-top:2px;"><polyline points="20 6 9 17 4 12"/></svg>
                                    <span><?= e(is_string($f) ? $f : ($f['title'] ?? json_encode($f))) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Reviews -->
                <div class="card" style="padding:var(--space-lg);margin-top:var(--space-lg);">
                    <h2 style="font-size:var(--fs-xl);margin-bottom:var(--space);"><?= e(t('reviews', 'Reviews')) ?> (<?= (int) $service['total_reviews'] ?>)</h2>
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted"><?= e(t('no_reviews_yet', 'No reviews yet.')) ?></p>
                    <?php else: ?>
                        <?php foreach ($reviews as $r):
                            $rs = max(1, min(5, (int) $r['rating']));
                        ?>
                        <div style="padding:var(--space) 0;border-bottom:1px solid var(--border);">
                            <div class="flex items-center gap-sm" style="margin-bottom:8px;">
                                <span style="width:36px;height:36px;border-radius:50%;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;"><?= e(mb_strtoupper(mb_substr($r['user_name'] ?? 'U', 0, 1))) ?></span>
                                <div>
                                    <div style="font-weight:600;font-size:var(--fs-sm);"><?= e($r['user_name'] ?? t('anonymous', 'Anonymous')) ?></div>
                                    <div class="text-muted" style="font-size:var(--fs-xs);"><?= e(date('M d, Y', strtotime($r['created_at']))) ?></div>
                                </div>
                                <div class="stars" style="margin-left:auto;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <svg viewBox="0 0 24 24" class="<?= $i > $rs ? 'empty' : '' ?>" style="width:14px;height:14px;"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18.5 5.5 22 7 14.5 2 9.5 9 9"/></svg>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p style="font-size:var(--fs-sm);"><?= e($r['comment']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <aside>
                <div class="card" style="padding:var(--space-lg);text-align:center;">
                    <div style="font-size:56px;font-weight:800;color:var(--navy);line-height:1;"><?= number_format((float) $service['rating'], 1) ?></div>
                    <div class="stars" style="justify-content:center;margin:8px 0;">
                        <?php $sStars = max(1, min(5, (int) round((float) $service['rating']))); ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg viewBox="0 0 24 24" class="<?= $i > $sStars ? 'empty' : '' ?>"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18.5 5.5 22 7 14.5 2 9.5 9 9"/></svg>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted" style="font-size:var(--fs-sm);"><?= (int) $service['total_reviews'] ?> <?= e(t('reviews', 'reviews')) ?></p>
                </div>

                <div class="card" style="padding:var(--space);margin-top:var(--space);">
                    <h4 style="font-size:var(--fs-md);margin-bottom:var(--space);"><?= e(t('service_info', 'Service Info')) ?></h4>
                    <ul style="font-size:var(--fs-sm);display:flex;flex-direction:column;gap:10px;list-style:none;padding:0;">
                        <li class="flex justify-between"><span class="text-muted">⏱ <?= e(t('delivery', 'Delivery')) ?></span><strong><?= $deliveryDays ?> days</strong></li>
                        <li class="flex justify-between"><span class="text-muted"><?= e(t('category', 'Category')) ?></span><strong><?= e($catName) ?></strong></li>
                        <li class="flex justify-between"><span class="text-muted"><?= e(t('total_sales', 'Sales')) ?></span><strong><?= (int) $service['total_sales'] ?></strong></li>
                    </ul>
                </div>
            </aside>
        </div>

        <!-- Related (unchanged) -->
        <?php if ($related): ?>
        <section style="margin-top:var(--space-2xl);">
            <h2 style="font-size:var(--fs-2xl);margin-bottom:var(--space-md);"><?= e(t('related_services', 'Related Services')) ?></h2>
            <div class="grid grid-4">
                <?php foreach ($related as $r):
                    $rTitle = ($lang === 'bn' && !empty($r['title_bn'])) ? $r['title_bn'] : $r['title_en'];
                    $rThumb = $r['thumbnail'] ? base_url($r['thumbnail']) : base_url('assets/images/placeholder.png');
                    $rPrice = (float) $r['price'];
                    $rDisc  = !empty($r['discount_price']) ? (float) $r['discount_price'] : 0;
                    $rFinal = $rDisc > 0 ? $rDisc : $rPrice;
                ?>
                <article class="service-card">
                    <a class="thumb" href="<?= e(base_url('service-details.php?slug=' . urlencode($r['slug']))) ?>">
                        <img src="<?= e($rThumb) ?>" alt="<?= e($rTitle) ?>" loading="lazy">
                    </a>
                    <div class="body">
                        <h3 class="title"><a href="<?= e(base_url('service-details.php?slug=' . urlencode($r['slug']))) ?>"><?= e($rTitle) ?></a></h3>
                        <div class="price-row">
                            <span class="price"><?= e(money($rFinal)) ?></span>
                            <a class="btn btn-primary btn-sm" href="<?= e(base_url('service-details.php?slug=' . urlencode($r['slug']))) ?>"><?= e(t('view', 'View')) ?></a>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</main>

<style>
/* Service meta badges */
.svc-meta-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: var(--space);
}
.svc-meta-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    padding: 8px 12px;
    border-radius: 10px;
    min-width: 160px;
}
.smb-icon {
    width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    background: #fff; border-radius: 8px;
    font-size: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}
.smb-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    color: #64748B; letter-spacing: .4px;
}
.smb-value {
    font-size: 13px; font-weight: 700; color: var(--navy);
    line-height: 1.2;
    margin-top: 2px;
}

/* Package cards */
.pkg-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--space-lg);
    max-width: 1100px;
    margin: 0 auto;
}
.pkg-card {
    position: relative;
    background: #fff;
    border: 2px solid var(--border);
    border-radius: var(--radius-lg);
    padding: var(--space-lg) var(--space);
    text-align: center;
    transition: all .25s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.pkg-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -15px rgba(0,0,0,.12); }
.pkg-card-popular { border-color: var(--primary); box-shadow: 0 10px 30px -10px rgba(37,99,235,.25); }
.pkg-card-life  { border-color: #FDE68A; }
.pkg-card-trial { border-color: #BBF7D0; }
.pkg-card.is-disabled { opacity: .65; }

.pkg-ribbon {
    position: absolute; top: 12px; right: -30px;
    transform: rotate(45deg);
    padding: 4px 40px;
    font-size: 10px; font-weight: 800;
    letter-spacing: .5px; color: #fff; text-transform: uppercase;
}
.pkg-ribbon-green { background:#10B981; }
.pkg-ribbon-blue  { background: var(--primary); }
.pkg-ribbon-amber { background:#F59E0B; }
.pkg-ribbon-gray  { background:#94A3B8; }

.pkg-icon {
    width: 60px; height: 60px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; margin: 0 auto var(--space);
    box-shadow: 0 8px 20px -6px rgba(0,0,0,.15);
}
.pkg-name { font-size: var(--fs-lg); margin-bottom: 8px; color: var(--navy); }
.pkg-price { display: flex; align-items: baseline; justify-content: center; gap: 4px; margin-bottom: 6px; }
.pkg-amount { font-size: 32px; font-weight: 800; color: var(--navy); line-height: 1; }
.pkg-per { font-size: var(--fs-sm); color: var(--text-muted); }
.pkg-duration { font-size: var(--fs-sm); color: var(--text-muted); margin-bottom: 12px; }
.pkg-save-badge {
    display: inline-block; background:#D1FAE5; color:#065F46;
    font-size:12px; font-weight:700; padding:3px 10px;
    border-radius:20px; margin-bottom:12px;
}
.pkg-desc { font-size: var(--fs-sm); color: var(--text-muted); margin-bottom: var(--space); line-height:1.5; }
.pkg-duration-select { margin: 12px 0 var(--space); text-align: left; }
.pkg-features { text-align:left; margin:0 0 var(--space); padding:0; list-style:none; flex:1; }
.pkg-features li {
    display:flex; gap:8px; align-items:flex-start;
    font-size:var(--fs-sm); color:var(--text);
    padding:6px 0; line-height:1.45;
}
.pkg-features li svg { flex-shrink:0; margin-top:2px; }

.pkg-notice {
    font-size: 12px; padding: 8px 10px; border-radius: 6px;
    margin-bottom: 12px; display: flex; gap: 6px; align-items: center;
    text-align: left; line-height: 1.4;
}
.pkg-notice-blue  { background:#EFF6FF; color:#1E40AF; }
.pkg-notice-gray  { background:#F1F5F9; color:#475569; }

.pkg-cta { margin-top: auto; font-weight: 700; }
.btn-success { background: linear-gradient(135deg,#10B981,#059669); color:#fff; border:none; }
.btn-success:hover { background: linear-gradient(135deg,#059669,#047857); }
.btn-warning { background: linear-gradient(135deg,#F59E0B,#D97706); color:#fff; border:none; }
.btn-warning:hover { background: linear-gradient(135deg,#D97706,#B45309); }

/* Domain notice */
.domain-notice {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    background: linear-gradient(135deg,#EFF6FF,#F8FAFC);
    border: 2px solid #BFDBFE;
    border-radius: var(--radius-lg);
    padding: 16px 20px;
    margin-top: var(--space-lg);
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
}
.dn-icon {
    width: 48px; height: 48px;
    background: var(--primary); color: #fff;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; flex-shrink: 0;
}
.dn-title { font-size: 15px; font-weight: 700; color: var(--navy); margin-bottom: 4px; }
.dn-sub   { font-size: 12.5px; color: #64748B; margin-bottom: 8px; }
.dn-options { display: flex; gap: 8px; flex-wrap: wrap; }
.dn-chip {
    background: #fff; border: 1px solid #E2E8F0;
    padding: 5px 10px; border-radius: 20px;
    font-size: 12px; font-weight: 600; color: var(--navy);
}

@media (max-width: 900px) {
    .service-top-grid { grid-template-columns: 1fr !important; }
    .service-bottom-grid { grid-template-columns: 1fr !important; }
}
@media (max-width: 600px) {
    .pkg-cards-grid { grid-template-columns: 1fr; }
    .pkg-amount { font-size: 26px; }
    .domain-notice { flex-direction: column; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* Gallery */
    document.querySelectorAll('.thumb-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var main = document.getElementById('main-image');
            if (main) main.src = btn.dataset.src;
            document.querySelectorAll('.thumb-btn').forEach(b => b.style.borderColor = 'var(--border)');
            btn.style.borderColor = 'var(--primary)';
        });
    });

    /* Subscription duration change */
    var durationSelect = document.getElementById('sub-duration-select');
    var subPriceDisplay = document.getElementById('sub-price-display');
    var subDurationDisplay = document.getElementById('sub-duration-display');
    var subOrderBtn = document.getElementById('sub-order-btn');

    function updateSubPrice() {
        if (!durationSelect) return;
        var opt = durationSelect.options[durationSelect.selectedIndex];
        var price = parseFloat(opt.dataset.price) || 0;
        var label = opt.dataset.label || '';
        var cur = '<?= e(current_currency()) ?>';
        var sym = '<?= e(setting('currency_symbol_bdt', '৳')) ?>';
        var fmt;
        if (cur === 'USD') fmt = '$' + price.toFixed(2);
        else fmt = sym + price.toFixed(2);
        if (subPriceDisplay) subPriceDisplay.textContent = fmt;
        if (subDurationDisplay) subDurationDisplay.textContent = '/ ' + label;
        if (subOrderBtn) subOrderBtn.dataset.price = price;
    }
    if (durationSelect) {
        durationSelect.addEventListener('change', updateSubPrice);
        updateSubPrice();
    }

    /* Package order */
    document.querySelectorAll('[data-pkg-order]').forEach(function (btn) {
        btn.addEventListener('click', async function (e) {
            e.preventDefault();
            var serviceId = btn.dataset.serviceId;
            var packageId = btn.dataset.packageId;
            var pkgType   = btn.dataset.packageType;
            var price     = parseFloat(btn.dataset.price) || 0;

            var isLoggedIn = <?= $userId > 0 ? 'true' : 'false' ?>;
            if (!isLoggedIn) {
                if (typeof toast === 'function') toast('<?= e(t('login_to_order', 'Please log in to place an order')) ?>', 'warning');
                setTimeout(function () {
                    location.href = '<?= e(base_url('login.php')) ?>?redirect=' + encodeURIComponent(location.pathname + location.search);
                }, 800);
                return;
            }

            var months = 1;
            if (pkgType === 'subscription' && durationSelect) {
                months = parseInt(durationSelect.value) || 1;
                var opt = durationSelect.options[durationSelect.selectedIndex];
                price = parseFloat(opt.dataset.price) || 0;
            }

            if (typeof btnLoading === 'function') btnLoading(btn, true);

            try {
                await API.post('cart/add', {
                    service_id: serviceId,
                    package_id: packageId,
                    quantity: 1,
                    months: months,
                    package_type: pkgType,
                });

                if (typeof toast === 'function') toast('Redirecting to checkout...', 'success');
                if (typeof refreshCartCount === 'function') refreshCartCount();
                setTimeout(function () { location.href = '<?= e(base_url('checkout.php')) ?>'; }, 600);
            } catch (err) {
                if (err.status === 401) {
                    if (typeof toast === 'function') toast('Please log in first', 'warning');
                    setTimeout(function () {
                        location.href = '<?= e(base_url('login.php')) ?>?redirect=' + encodeURIComponent(location.pathname + location.search);
                    }, 800);
                } else {
                    if (typeof toast === 'function') toast(err.message || 'Something went wrong', 'error');
                    if (typeof btnLoading === 'function') btnLoading(btn, false);
                }
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>