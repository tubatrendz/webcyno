<?php
/**
 * api/public/pages.php — Static Pages (about / privacy / terms / refund)
 * Method: GET
 * URL:  /api/pages/{slug}   (router dynamic → $_GET['__params'])
 *   বা /api/public/pages?slug=privacy-policy
 * Response: { page: { slug, title, content, meta } }
 *
 * Storage: settings টেবিলে pages key (JSON object), admin panel এডিট করে।
 */

if (!defined('WCB_ENV')) { http_response_code(403); exit; }

require_method('GET');

/* ---------- Resolve slug ---------- */
$slug = trim((string) input('slug', ''));
if ($slug === '' && !empty($_GET['__params'][0])) {
    $slug = trim((string) $_GET['__params'][0]);
}
if ($slug === '') {
    json_error('Page slug is required.', 422);
}

/* ---------- Allowed slugs (whitelist) ---------- */
$allowed = ['about', 'privacy-policy', 'terms', 'refund-policy'];

if (!in_array($slug, $allowed, true)) {
    json_error('Page not found.', 404);
}

/* ---------- Load pages from settings ---------- */
$cacheKey = 'pages_content_v1';
$pages    = cache_get($cacheKey, 300);

if ($pages === null) {
    $raw = setting('pages_content', '');
    $decoded = $raw ? json_decode($raw, true) : null;

    if (is_array($decoded)) {
        $pages = $decoded;
    } else {
        // Default fallback content
        $pages = [
            'about' => [
                'title'   => 'About Us',
                'content' => '<p>Webcyno একটি AI-powered digital service marketplace — যেখানে আপনি ওয়েবসাইট, ল্যান্ডিং পেজ, Facebook Ads, Google Ads, গ্রাফিক ডিজাইন, SEO সহ বিভিন্ন ডিজিটাল সার্ভিস পেয়ে যাবেন একটি জায়গায়।</p><p>আমাদের লক্ষ্য — ছোট ও মাঝারি ব্যবসাগুলোকে সাশ্রয়ী দামে প্রফেশনাল ডিজিটাল সেবা পৌঁছে দেওয়া।</p>',
                'meta'    => ['title' => 'About Webcyno', 'desc' => 'Learn about Webcyno and our mission.'],
            ],
            'privacy-policy' => [
                'title'   => 'Privacy Policy',
                'content' => '<p>আমরা আপনার গোপনীয়তাকে সম্মান করি। এই পেজে ব্যাখ্যা করা হয়েছে আমরা কী তথ্য সংগ্রহ করি, কীভাবে ব্যবহার করি এবং কীভাবে সুরক্ষিত রাখি।</p><h3>তথ্য সংগ্রহ</h3><p>নাম, ইমেইল, ফোন নম্বর, এবং অর্ডার সংক্রান্ত তথ্য।</p><h3>তথ্য ব্যবহার</h3><p>অর্ডার প্রসেস, কাস্টমার সাপোর্ট, এবং সেবা উন্নয়নের জন্য।</p><h3>তৃতীয় পক্ষ</h3><p>আমরা কখনো আপনার তথ্য বিক্রি করি না।</p>',
                'meta'    => ['title' => 'Privacy Policy', 'desc' => 'How Webcyno handles your data.'],
            ],
            'terms' => [
                'title'   => 'Terms & Conditions',
                'content' => '<p>Webcyno ব্যবহার করে আপনি এই শর্তাবলীতে সম্মত হচ্ছেন।</p><h3>সেবা</h3><p>আমরা AI-powered ডিজিটাল সেবা প্রদান করি — নির্দিষ্ট সময়সীমা ও শর্ত সাপেক্ষে।</p><h3>পেমেন্ট</h3><p>সব মূল্য BDT/USD-তে দেখানো, পেমেন্ট কনফার্ম হলে অর্ডার প্রসেস শুরু হয়।</p><h3>দায়বদ্ধতা</h3><p>সেবা ব্যবহারে উদ্ভূত কোনো পরিণতির জন্য Webcyno দায়ী নয়।</p>',
                'meta'    => ['title' => 'Terms & Conditions', 'desc' => 'Terms of service for Webcyno.'],
            ],
            'refund-policy' => [
                'title'   => 'Refund Policy',
                'content' => '<p>আমরা কাস্টমার সন্তুষ্টিকে গুরুত্ব দিই। নিচে আমাদের রিফান্ড পলিসি দেওয়া হলো।</p><h3>সম্পূর্ণ রিফান্ড</h3><p>অর্ডার করার ২৪ ঘণ্টার মধ্যে এবং কাজ শুরুর আগে ক্যান্সেল করলে সম্পূর্ণ রিফান্ড।</p><h3>আংশিক রিফান্ড</h3><p>কাজ শুরু হয়ে গেলে কিন্তু ডেলিভারির আগে ক্যান্সেল করলে আংশিক রিফান্ড — পরিস্থিতি অনুযায়ী।</p><h3>রিফান্ড প্রসেস</h3><p>রিফান্ড সাধারণত ৫–১০ কর্মদিবসের মধ্যে প্রসেস হয় — যেই মেথডে পেমেন্ট করেছিলেন সেটাতেই ফেরত।</p>',
                'meta'    => ['title' => 'Refund Policy', 'desc' => 'Our refund policy details.'],
            ],
        ];

        try {
            global $pdo;
            $pdo->prepare("
                INSERT INTO settings (key_name, value, type, group_name)
                VALUES ('pages_content', ?, 'json', 'general')
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ")->execute([json_encode($pages, JSON_UNESCAPED_UNICODE)]);
        } catch (Exception $e) { /* silent */ }
    }

    cache_set($cacheKey, $pages);
}

/* ---------- Check page exists ---------- */
if (empty($pages[$slug])) {
    json_error('Page not found.', 404);
}

$page = $pages[$slug];

/* ---------- Response ---------- */
json_success([
    'page' => [
        'slug'    => $slug,
        'title'   => $page['title']   ?? ucfirst(str_replace('-', ' ', $slug)),
        'content' => $page['content'] ?? '',
        'meta'    => [
            'title' => $page['meta']['title'] ?? ($page['title'] ?? ''),
            'desc'  => $page['meta']['desc']  ?? '',
        ],
        'url'     => base_url($slug . '.php'),
    ],
], 'Page loaded.');