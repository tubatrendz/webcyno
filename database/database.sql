-- ========================================================
-- Webcyno — AI Service Marketplace
-- Database Schema v1.0
-- MySQL 5.7+ / MariaDB 10.3+
-- ========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+06:00";

-- ========================================================
-- 1. USERS
-- ========================================================
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `phone_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `email_token` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  `language` VARCHAR(5) NOT NULL DEFAULT 'en',
  `currency` VARCHAR(3) NOT NULL DEFAULT 'BDT',
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 2. ADMINS
-- ========================================================
CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('super','manager','support') NOT NULL DEFAULT 'manager',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 3. CATEGORIES
-- ========================================================
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name_en` VARCHAR(120) NOT NULL,
  `name_bn` VARCHAR(120) DEFAULT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `icon` VARCHAR(255) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `description_en` TEXT DEFAULT NULL,
  `description_bn` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cat_slug` (`slug`),
  KEY `idx_cat_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 4. SERVICES
-- ========================================================
CREATE TABLE `services` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `title_en` VARCHAR(200) NOT NULL,
  `title_bn` VARCHAR(200) DEFAULT NULL,
  `slug` VARCHAR(220) NOT NULL,
  `short_desc_en` VARCHAR(500) DEFAULT NULL,
  `short_desc_bn` VARCHAR(500) DEFAULT NULL,
  `description_en` LONGTEXT DEFAULT NULL,
  `description_bn` LONGTEXT DEFAULT NULL,
  `thumbnail` VARCHAR(255) DEFAULT NULL,
  `gallery` JSON DEFAULT NULL,
  `features_en` JSON DEFAULT NULL,
  `features_bn` JSON DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_price` DECIMAL(10,2) DEFAULT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'BDT',
  `delivery_type` ENUM('digital','custom','both') NOT NULL DEFAULT 'custom',
  `delivery_time` VARCHAR(50) DEFAULT NULL,
  `stock` INT DEFAULT NULL,
  `total_sales` INT NOT NULL DEFAULT 0,
  `rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `total_reviews` INT NOT NULL DEFAULT 0,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','draft','inactive') NOT NULL DEFAULT 'active',
  `meta_title` VARCHAR(200) DEFAULT NULL,
  `meta_desc` VARCHAR(300) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_service_slug` (`slug`),
  KEY `idx_srv_cat` (`category_id`),
  KEY `idx_srv_status` (`status`),
  KEY `idx_srv_featured` (`is_featured`),
  CONSTRAINT `fk_srv_cat` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 5. SERVICE PACKAGES
-- ========================================================
CREATE TABLE `service_packages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_time` VARCHAR(50) DEFAULT NULL,
  `features` JSON DEFAULT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pkg_srv` (`service_id`),
  CONSTRAINT `fk_pkg_srv` FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 6. SERVICE FILES (for digital delivery)
-- ========================================================
CREATE TABLE `service_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `file_name` VARCHAR(200) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT DEFAULT NULL,
  `file_type` VARCHAR(30) DEFAULT NULL,
  `download_limit` INT DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_file_srv` (`service_id`),
  CONSTRAINT `fk_file_srv` FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 7. ORDERS
-- ========================================================
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(30) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'BDT',
  `coupon_code` VARCHAR(50) DEFAULT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `order_status` ENUM('pending','confirmed','processing','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_email` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(20) DEFAULT NULL,
  `billing_address` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `admin_note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_order_no` (`order_number`),
  KEY `idx_ord_user` (`user_id`),
  KEY `idx_ord_status` (`order_status`),
  KEY `idx_ord_paystatus` (`payment_status`),
  CONSTRAINT `fk_ord_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 8. ORDER ITEMS
-- ========================================================
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `service_id` INT UNSIGNED DEFAULT NULL,
  `package_id` INT UNSIGNED DEFAULT NULL,
  `service_title` VARCHAR(200) NOT NULL,
  `package_name` VARCHAR(80) DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `delivery_status` ENUM('pending','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `delivery_note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_order` (`order_id`),
  KEY `idx_item_srv` (`service_id`),
  CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 9. PAYMENTS
-- ========================================================
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `method` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'BDT',
  `transaction_id` VARCHAR(120) DEFAULT NULL,
  `sender_number` VARCHAR(30) DEFAULT NULL,
  `status` ENUM('pending','success','failed','refunded') NOT NULL DEFAULT 'pending',
  `gateway_response` TEXT DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pay_order` (`order_id`),
  KEY `idx_pay_user` (`user_id`),
  CONSTRAINT `fk_pay_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 10. PAYMENT METHODS (Admin configurable)
-- ========================================================
CREATE TABLE `payment_methods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `type` ENUM('manual','gateway') NOT NULL DEFAULT 'manual',
  `logo` VARCHAR(255) DEFAULT NULL,
  `account_number` VARCHAR(100) DEFAULT NULL,
  `account_type` VARCHAR(50) DEFAULT NULL,
  `instructions` TEXT DEFAULT NULL,
  `config` JSON DEFAULT NULL,
  `currency` VARCHAR(3) DEFAULT 'BDT',
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pm_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 11. COUPONS
-- ========================================================
CREATE TABLE `coupons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `type` ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
  `value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_order` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` DECIMAL(10,2) DEFAULT NULL,
  `usage_limit` INT DEFAULT NULL,
  `used_count` INT NOT NULL DEFAULT 0,
  `per_user_limit` INT NOT NULL DEFAULT 1,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 12. REVIEWS
-- ========================================================
CREATE TABLE `reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `rating` TINYINT NOT NULL DEFAULT 5,
  `comment` TEXT DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rev_srv` (`service_id`),
  KEY `idx_rev_user` (`user_id`),
  CONSTRAINT `fk_rev_srv` FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 13. BLOG POSTS
-- ========================================================
CREATE TABLE `blog_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title_en` VARCHAR(220) NOT NULL,
  `title_bn` VARCHAR(220) DEFAULT NULL,
  `slug` VARCHAR(250) NOT NULL,
  `excerpt_en` VARCHAR(500) DEFAULT NULL,
  `excerpt_bn` VARCHAR(500) DEFAULT NULL,
  `content_en` LONGTEXT DEFAULT NULL,
  `content_bn` LONGTEXT DEFAULT NULL,
  `thumbnail` VARCHAR(255) DEFAULT NULL,
  `author_id` INT UNSIGNED DEFAULT NULL,
  `category` VARCHAR(80) DEFAULT NULL,
  `tags` VARCHAR(300) DEFAULT NULL,
  `views` INT NOT NULL DEFAULT 0,
  `status` ENUM('published','draft') NOT NULL DEFAULT 'draft',
  `meta_title` VARCHAR(200) DEFAULT NULL,
  `meta_desc` VARCHAR(300) DEFAULT NULL,
  `published_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blog_slug` (`slug`),
  KEY `idx_blog_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 14. SETTINGS (key-value)
-- ========================================================
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name` VARCHAR(120) NOT NULL,
  `value` LONGTEXT DEFAULT NULL,
  `type` ENUM('text','textarea','image','json','boolean','number') NOT NULL DEFAULT 'text',
  `group_name` VARCHAR(60) NOT NULL DEFAULT 'general',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_setting_key` (`key_name`),
  KEY `idx_setting_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 15. NOTIFICATIONS
-- ========================================================
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `user_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT DEFAULT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `icon` VARCHAR(50) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_type`,`user_id`),
  KEY `idx_notif_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 16. CHAT CONVERSATIONS
-- ========================================================
CREATE TABLE `chat_conversations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED DEFAULT NULL,
  `subject` VARCHAR(200) DEFAULT NULL,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `last_message_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_user` (`user_id`),
  KEY `idx_chat_status` (`status`),
  CONSTRAINT `fk_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 17. CHAT MESSAGES
-- ========================================================
CREATE TABLE `chat_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `sender_type` ENUM('user','admin','system') NOT NULL DEFAULT 'user',
  `sender_id` INT UNSIGNED DEFAULT NULL,
  `message` TEXT NOT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_conv` (`conversation_id`),
  CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 18. OTP VERIFICATIONS
-- ========================================================
CREATE TABLE `otp_verifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(150) NOT NULL,
  `purpose` ENUM('email','phone','login','reset') NOT NULL DEFAULT 'email',
  `otp_code` VARCHAR(10) NOT NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `is_used` TINYINT(1) NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_ident` (`identifier`,`purpose`),
  KEY `idx_otp_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 19. TRANSLATIONS (i18n)
-- ========================================================
CREATE TABLE `translations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lang_code` VARCHAR(5) NOT NULL DEFAULT 'en',
  `key_name` VARCHAR(150) NOT NULL,
  `value` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_trans` (`lang_code`,`key_name`),
  KEY `idx_trans_lang` (`lang_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 20. ACTIVITY LOGS (Admin actions)
-- ========================================================
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity` VARCHAR(80) DEFAULT NULL,
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_admin` (`admin_id`),
  KEY `idx_log_entity` (`entity`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED DATA
-- ========================================================

-- Default Admin (password: admin123 — setup wizard এ পরিবর্তন হবে)
-- Hash generated with password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `admins` (`name`, `email`, `password`, `role`) VALUES
('Webcyno Admin', 'admin@webcyno.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlCS4bZb2T/Af8.h8Bx6Pj6n3wN0Ky', 'super');

-- Default Categories
INSERT INTO `categories` (`name_en`, `name_bn`, `slug`, `sort_order`, `is_featured`, `status`) VALUES
('Website Design & Development', 'ওয়েবসাইট ডিজাইন ও ডেভেলপমেন্ট', 'website-design-development', 1, 1, 1),
('Landing Page Design', 'ল্যান্ডিং পেজ ডিজাইন', 'landing-page-design', 2, 1, 1),
('Facebook Ads Management', 'ফেসবুক অ্যাডস ম্যানেজমেন্ট', 'facebook-ads-management', 3, 1, 1),
('Google Ads Management', 'গুগল অ্যাডস ম্যানেজমেন্ট', 'google-ads-management', 4, 1, 1),
('Graphic Design', 'গ্রাফিক ডিজাইন', 'graphic-design', 5, 1, 1),
('SEO & Digital Marketing', 'এসইও ও ডিজিটাল মার্কেটিং', 'seo-digital-marketing', 6, 0, 1),
('AI Services', 'এআই সার্ভিস', 'ai-services', 7, 0, 1),
('Content Writing', 'কনটেন্ট রাইটিং', 'content-writing', 8, 0, 1);

-- Default Payment Methods
INSERT INTO `payment_methods` (`name`, `code`, `type`, `account_number`, `instructions`, `sort_order`, `status`) VALUES
('bKash', 'bkash', 'manual', 'YOUR_BKASH_NUMBER', 'Send money to the bKash number above and submit the Transaction ID.', 1, 1),
('Nagad', 'nagad', 'manual', 'YOUR_NAGAD_NUMBER', 'Send money to the Nagad number above and submit the Transaction ID.', 2, 1),
('SSLCommerz', 'sslcommerz', 'gateway', NULL, 'Pay securely using card, mobile banking, or net banking via SSLCommerz.', 3, 0),
('Rocket', 'rocket', 'manual', 'YOUR_ROCKET_NUMBER', 'Send money to the Rocket number above and submit the Transaction ID.', 4, 1);

-- Default Settings
INSERT INTO `settings` (`key_name`, `value`, `type`, `group_name`) VALUES
('site_name', 'Webcyno', 'text', 'general'),
('site_tagline', 'AI-Powered Digital Solutions', 'text', 'general'),
('site_description', 'Get professional AI-powered services like website, landing page, Facebook ads, Google ads and more — all in one place.', 'textarea', 'general'),
('site_logo', 'assets/images/logo.png', 'image', 'general'),
('site_favicon', 'assets/images/favicon.ico', 'image', 'general'),
('site_email', 'YOUR_EMAIL', 'text', 'general'),
('site_phone', 'YOUR_PHONE_NUMBER', 'text', 'general'),
('site_address', 'YOUR_ADDRESS', 'textarea', 'general'),
('active_theme', 'default', 'text', 'appearance'),
('primary_color', '#2563EB', 'text', 'appearance'),
('dark_color', '#0F172A', 'text', 'appearance'),
('default_language', 'en', 'text', 'general'),
('default_currency', 'BDT', 'text', 'general'),
('currency_bdt_rate', '1', 'number', 'general'),
('currency_usd_rate', '110', 'number', 'general'),
('currency_symbol_bdt', '৳', 'text', 'general'),
('currency_symbol_usd', '$', 'text', 'general'),
('enable_email_verify', '1', 'boolean', 'auth'),
('enable_phone_verify', '0', 'boolean', 'auth'),
('enable_otp_login', '0', 'boolean', 'auth'),
('smtp_host', 'YOUR_SMTP_HOST', 'text', 'email'),
('smtp_port', '587', 'text', 'email'),
('smtp_user', 'YOUR_SMTP_USER', 'text', 'email'),
('smtp_pass', 'YOUR_SMTP_PASSWORD', 'text', 'email'),
('smtp_from_name', 'Webcyno', 'text', 'email'),
('smtp_from_email', 'YOUR_EMAIL', 'text', 'email'),
('sms_gateway', 'none', 'text', 'sms'),
('sms_api_key', 'YOUR_SMS_API_KEY', 'text', 'sms'),
('sms_sender_id', 'YOUR_SMS_SENDER_ID', 'text', 'sms'),
('chat_auto_reply', 'আসসালামু আলাইকুম! আমাদের প্রতিনিধিরা এখন একটু ব্যস্ত আছেন, অনুগ্রহ করে অপেক্ষা করুন — আমরা দ্রুত আপনার সাথে যোগাযোগ করব।', 'textarea', 'chat'),
('chat_welcome_msg', 'Hello! How can we help you today?', 'textarea', 'chat'),
('social_facebook', 'https://facebook.com/', 'text', 'social'),
('social_twitter', 'https://twitter.com/', 'text', 'social'),
('social_instagram', 'https://instagram.com/', 'text', 'social'),
('social_linkedin', 'https://linkedin.com/', 'text', 'social'),
('social_youtube', '', 'text', 'social'),
('footer_about', 'AI-powered digital solutions for your business. Build, grow and succeed with Webcyno.', 'textarea', 'general'),
('footer_copyright', '© 2025 Webcyno. All rights reserved.', 'text', 'general'),
('seo_title', 'Webcyno — AI-Powered Digital Services Marketplace', 'text', 'seo'),
('seo_description', 'Professional AI-powered digital services: website, landing page, Facebook ads, Google ads and more.', 'textarea', 'seo'),
('seo_keywords', 'webcyno, ai services, website design, facebook ads, digital marketing', 'text', 'seo');

-- Sample Translations (basic set — Admin আরও যোগ করতে পারবে)
INSERT INTO `translations` (`lang_code`, `key_name`, `value`) VALUES
('en', 'home', 'Home'),
('en', 'services', 'Services'),
('en', 'about', 'About'),
('en', 'blog', 'Blog'),
('en', 'contact', 'Contact'),
('en', 'login', 'Login'),
('en', 'register', 'Register'),
('en', 'cart', 'Cart'),
('en', 'search', 'Search'),
('en', 'add_to_cart', 'Add to Cart'),
('en', 'buy_now', 'Buy Now'),
('en', 'checkout', 'Checkout'),
('en', 'dashboard', 'Dashboard'),
('en', 'my_orders', 'My Orders'),
('en', 'profile', 'Profile'),
('en', 'logout', 'Logout'),
('bn', 'home', 'হোম'),
('bn', 'services', 'সার্ভিস'),
('bn', 'about', 'সম্পর্কে'),
('bn', 'blog', 'ব্লগ'),
('bn', 'contact', 'যোগাযোগ'),
('bn', 'login', 'লগইন'),
('bn', 'register', 'রেজিস্টার'),
('bn', 'cart', 'কার্ট'),
('bn', 'search', 'খুঁজুন'),
('bn', 'add_to_cart', 'কার্টে যোগ করুন'),
('bn', 'buy_now', 'এখনই কিনুন'),
('bn', 'checkout', 'চেকআউট'),
('bn', 'dashboard', 'ড্যাশবোর্ড'),
('bn', 'my_orders', 'আমার অর্ডার'),
('bn', 'profile', 'প্রোফাইল'),
('bn', 'logout', 'লগআউট');