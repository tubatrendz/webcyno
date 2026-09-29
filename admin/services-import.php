<?php
/**
 * admin/services-import.php — Bulk Import Services via CSV
 */
$admin_page_title = 'Import Services';
$admin_active     = 'services';
require_once __DIR__ . '/includes/admin-header.php';

$success = '';
$errors  = [];
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file']['tmp_name'])) {
    if (!verify_csrf(input('csrf_token'))) {
        $errors[] = 'Invalid security token.';
    } else {
        $file = $_FILES['csv_file'];

        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $errors[] = 'Only CSV files allowed. Please save your file as .csv';
        } else {
            // Process import
            try {
                $result = process_service_import($pdo, $file['tmp_name']);
                $summary = $result;
                if ($result['inserted'] > 0) {
                    $success = "{$result['inserted']} service(s) successfully imported!";
                }
                if (!empty($result['errors'])) {
                    $errors = $result['errors'];
                }
            } catch (Exception $e) {
                $errors[] = 'Import failed: ' . $e->getMessage();
            }
        }
    }
}

/**
 * Process CSV file and insert services
 */
function process_service_import($pdo, $filePath) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('Could not open file');
    }

    // Read header row (with UTF-8 BOM strip)
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new Exception('Empty file or unreadable CSV');
    }

    // Strip UTF-8 BOM from first column
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $header = array_map('trim', $header);

    // Required columns
    $required = ['title_en', 'category_slug', 'price'];
    foreach ($required as $col) {
        if (!in_array($col, $header, true)) {
            fclose($handle);
            throw new Exception("Missing required column: {$col}");
        }
    }

    // Load all categories into memory (slug → id)
    $categories = [];
    foreach ($pdo->query("SELECT id, slug FROM categories")->fetchAll() as $c) {
        $categories[$c['slug']] = (int) $c['id'];
    }

    $inserted = 0;
    $skipped  = 0;
    $errors   = [];
    $rowNum   = 1; // header is row 1

    $pdo->beginTransaction();

    try {
        $insertStmt = $pdo->prepare("
            INSERT INTO services
                (title_en, title_bn, slug, category_id,
                 short_desc_en, short_desc_bn,
                 price, discount_price, currency,
                 delivery_type, delivery_time,
                 status, is_featured,
                 total_sales, rating, total_reviews, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0.00, 0, NOW())
        ");

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip empty rows
            if (empty(array_filter($row))) continue;

            // Pad/trim to header length
            $row = array_pad($row, count($header), '');
            $row = array_slice($row, 0, count($header));
            $data = array_combine($header, $row);

            // Validate required
            $titleEn  = trim((string) ($data['title_en'] ?? ''));
            $catSlug  = trim((string) ($data['category_slug'] ?? ''));
            $price    = (float) ($data['price'] ?? 0);

            if ($titleEn === '') {
                $errors[] = "Row {$rowNum}: title_en is empty";
                $skipped++;
                continue;
            }

            if (!isset($categories[$catSlug])) {
                $errors[] = "Row {$rowNum}: category_slug '{$catSlug}' not found";
                $skipped++;
                continue;
            }

            if ($price < 0) {
                $errors[] = "Row {$rowNum}: price cannot be negative";
                $skipped++;
                continue;
            }

            // Generate unique slug
            $baseSlug = make_slug($titleEn);
            $slug = $baseSlug;
            $i = 1;
            $checkStmt = $pdo->prepare("SELECT id FROM services WHERE slug = ? LIMIT 1");
            while (true) {
                $checkStmt->execute([$slug]);
                if (!$checkStmt->fetch()) break;
                $slug = $baseSlug . '-' . (++$i);
            }

            $insertStmt->execute([
                $titleEn,
                trim((string) ($data['title_bn'] ?? '')) ?: null,
                $slug,
                $categories[$catSlug],
                trim((string) ($data['short_desc_en'] ?? '')) ?: null,
                trim((string) ($data['short_desc_bn'] ?? '')) ?: null,
                $price,
                ((float) ($data['discount_price'] ?? 0)) > 0 ? (float) $data['discount_price'] : null,
                in_array(($data['currency'] ?? 'BDT'), ['BDT','USD'], true) ? $data['currency'] : 'BDT',
                in_array(($data['delivery_type'] ?? 'custom'), ['digital','custom','both'], true) ? $data['delivery_type'] : 'custom',
                trim((string) ($data['delivery_time'] ?? '')) ?: null,
                in_array(($data['status'] ?? 'active'), ['active','draft','inactive'], true) ? $data['status'] : 'active',
                (int) ($data['is_featured'] ?? 0) === 1 ? 1 : 0,
            ]);

            $inserted++;
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        fclose($handle);
        throw $e;
    }

    fclose($handle);

    try {
        log_activity('services_imported', 'services', null, "Imported {$inserted} services, skipped {$skipped}");
    } catch (Exception $e) {}

    return [
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'errors'   => array_slice($errors, 0, 20), // Max 20 errors shown
    ];
}
?>

<div class="admin-page-header">
    <div>
        <nav class="admin-breadcrumb">
            <a href="<?= e(admin_url('dashboard.php')) ?>">Admin</a>
            <span class="sep">/</span>
            <a href="<?= e(admin_url('services.php')) ?>">Services</a>
            <span class="sep">/</span>
            <span class="current">Bulk Import</span>
        </nav>
        <h1 class="admin-page-title">Bulk Import Services</h1>
        <p class="admin-page-sub">একসাথে অনেকগুলো সার্ভিস CSV file থেকে যোগ করুন</p>
    </div>
    <a href="<?= e(admin_url('services.php')) ?>" class="admin-btn admin-btn-ghost">← Back to Services</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom:20px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= e($success) ?></span>
    </div>
<?php endif; ?>

<?php if ($summary): ?>
    <div class="admin-card" style="border-left:4px solid var(--primary);">
        <h3 class="admin-card-title" style="margin-bottom:12px;">Import Summary</h3>
        <div class="flex gap-lg" style="flex-wrap:wrap;">
            <div><strong style="color:var(--success);font-size:20px;"><?= (int) $summary['inserted'] ?></strong> <span class="admin-cell-sub">Inserted</span></div>
            <div><strong style="color:var(--warning);font-size:20px;"><?= (int) $summary['skipped'] ?></strong> <span class="admin-cell-sub">Skipped</span></div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom:20px;">
        <div>
            <strong>Issues found:</strong>
            <ul style="margin:8px 0 0;padding-left:20px;">
                <?php foreach (array_slice($errors, 0, 10) as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- Upload Card -->
<div class="admin-card">
    <h3 class="admin-card-title" style="margin-bottom:16px;">Upload CSV File</h3>

    <div class="alert alert-info" style="margin-bottom:20px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <div>
            <strong>Instructions:</strong>
            <ol style="margin:8px 0 0;padding-left:20px;line-height:1.8;">
                <li>নিচের <strong>Sample CSV</strong> download করুন</li>
                <li>Excel/Google Sheets এ খুলুন</li>
                <li>আপনার সার্ভিস দিয়ে row গুলো পূরণ করুন</li>
                <li><strong>File → Save As → CSV</strong> করুন</li>
                <li>এই পেজে upload করুন</li>
            </ol>
        </div>
    </div>

    <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
        <a href="data:text/csv;charset=utf-8,<?= rawurlencode("title_en,title_bn,category_slug,price,discount_price,currency,delivery_type,delivery_time,short_desc_en,short_desc_bn,status,is_featured\nBusiness Website Design,ব্যবসার ওয়েবসাইট,website-design-development,15000,12000,BDT,custom,7-10 days,Modern responsive business website,আধুনিক ওয়েবসাইট,active,1\nLanding Page Design,ল্যান্ডিং পেজ,landing-page-design,5000,4000,BDT,custom,3-5 days,High converting landing page,ল্যান্ডিং পেজ,active,1\n") ?>"
           download="webcyno-services-template.csv"
           class="admin-btn admin-btn-ghost">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download Sample CSV
        </a>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <div class="admin-form-group">
            <label class="admin-label">CSV File <span class="required">*</span></label>
            <input type="file" name="csv_file" accept=".csv,text/csv" required
                   class="form-control" style="padding:12px;">
            <p class="admin-help">Max 5MB • UTF-8 encoded CSV • Excel থেকে save করলে সঠিক হবে</p>
        </div>

        <div class="flex gap-sm" style="margin-top:20px;">
            <button type="submit" class="admin-btn admin-btn-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Import Services
            </button>
            <a href="<?= e(admin_url('services.php')) ?>" class="admin-btn admin-btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<!-- Category slug reference -->
<div class="admin-card">
    <h3 class="admin-card-title" style="margin-bottom:12px;">Category Slugs (Reference)</h3>
    <?php
    $cats = $pdo->query("SELECT slug, name_en FROM categories ORDER BY sort_order ASC")->fetchAll();
    ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Slug</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cats as $c): ?>
                    <tr>
                        <td><?= e($c['name_en']) ?></td>
                        <td>
                            <code style="background:#F1F5F9;padding:3px 8px;border-radius:4px;font-size:12px;"
                                  data-copy="<?= e($c['slug']) ?>"><?= e($c['slug']) ?></code>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>