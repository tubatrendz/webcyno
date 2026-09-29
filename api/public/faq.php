<?php
/**
 * api/public/faq.php — Public FAQ (JSON)
 * Method: GET
 * Response: { items: [{q, a}], total }
 *
 * Storage: settings টেবিলে `faq_items` key (JSON array) হিসেবে রাখা হয়।
 *          Admin panel ভবিষ্যতে এটা edit করবে। না থাকলে fallback defaults।
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

/* ---------- Load from settings (cached) ---------- */
$cacheKey = 'faq_items_v1';
$items    = cache_get($cacheKey, 300);

if ($items === null) {
    $raw = setting('faq_items', '');
    $decoded = $raw ? json_decode($raw, true) : null;

    if (is_array($decoded) && !empty($decoded)) {
        $items = $decoded;
    } else {
        // Default FAQ (admin panel এডিট না করা পর্যন্ত)
        $items = [
            ['q' => 'Webcyno কী?', 'a' => 'Webcyno একটি AI-powered digital service marketplace — যেখানে আপনি ওয়েবসাইট, ল্যান্ডিং পেজ, Facebook Ads, Google Ads সহ বিভিন্ন ডিজিটাল সার্ভিস অর্ডার করতে পারবেন।'],
            ['q' => 'আমি কীভাবে অর্ডার করব?', 'a' => 'সার্ভিস ব্রাউজ করুন → কার্টে যোগ করুন → চেকআউটে গিয়ে billing info ও পেমেন্ট মেথড দিন → অর্ডার কনফার্ম করুন। এরপর email/SMS-এ কনফার্মেশন পাবেন।'],
            ['q' => 'কোন কোন পেমেন্ট মেথড আছে?', 'a' => 'আমরা bKash, Nagad, Rocket সহ ম্যানুয়াল পেমেন্ট এবং SSLCommerz গেটওয়ে সাপোর্ট করি। পেমেন্ট মেথড অ্যাডমিন প্যানেল থেকে যোগ/পরিবর্তন করা যায়।'],
            ['q' => 'ডেলিভারিতে কত সময় লাগে?', 'a' => 'প্রতিটি সার্ভিসের ডেলিভারি টাইম সার্ভিস পেজে উল্লেখ থাকে — সাধারণত ২–৭ কর্মদিবস। কাস্টম সার্ভিসে আমরা আলাদাভাবে যোগাযোগ করি।'],
            ['q' => 'রিফান্ড পলিসি কী?', 'a' => 'অর্ডার কনফার্ম হওয়ার ২৪ ঘণ্টার মধ্যে ক্যান্সেল করলে সম্পূর্ণ রিফান্ড পাবেন। কাজ শুরু হয়ে গেলে রিফান্ড পলিসি শর্তসাপেক্ষ। বিস্তারিত refund-policy.php-তে দেখুন।'],
            ['q' => 'পাসওয়ার্ড ভুলে গেলে কী করব?', 'a' => 'লগইন পেজে "Forgot Password?" লিংকে ক্লিক করুন → আপনার email দিন → OTP পাবেন → সেটি দিয়ে নতুন পাসওয়ার্ড সেট করুন।'],
            ['q' => 'সাপোর্টে কীভাবে যোগাযোগ করব?', 'a' => 'Contact page-এর form, live chat widget, অথবা সরাসরি email/phone-এ যোগাযোগ করতে পারেন। আমরা সাধারণত ২৪ ঘণ্টার মধ্যে উত্তর দিই।'],
        ];

        // Persist defaults so admin panel-এ এডিট করা যায়
        try {
            global $pdo;
            $pdo->prepare("
                INSERT INTO settings (key_name, value, type, group_name)
                VALUES ('faq_items', ?, 'json', 'general')
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ")->execute([json_encode($items, JSON_UNESCAPED_UNICODE)]);
        } catch (Exception $e) { /* silent */ }
    }

    cache_set($cacheKey, $items);
}

/* ---------- Format ---------- */
$lang  = current_lang();
$final = [];

foreach ($items as $i => $row) {
    $q = $row['q'] ?? ($lang === 'bn' ? ($row['q_bn'] ?? '') : ($row['q_en'] ?? ''));
    $a = $row['a'] ?? ($lang === 'bn' ? ($row['a_bn'] ?? '') : ($row['a_en'] ?? ''));

    if ($q === '' || $a === '') continue;

    $final[] = [
        'id'    => $i + 1,
        'q'     => $q,
        'a'     => $a,
    ];
}

/* ---------- Response ---------- */
json_success([
    'items' => $final,
    'total' => count($final),
], 'FAQ loaded.');