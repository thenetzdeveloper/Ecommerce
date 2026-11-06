<?php
declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

/**
 * 4K STORE Internationalization (i18n) Engine
 * Full bilingual support: Khmer (KM) and English (EN)
 * Stored in `store_translations` table and manageable from Admin Dashboard.
 */

if (!function_exists('ensureTranslationsSchema')) {
    function ensureTranslationsSchema(mysqli $conn): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS `store_translations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `trans_key` varchar(100) NOT NULL,
            `group_name` varchar(50) NOT NULL DEFAULT 'general',
            `label_km` text NOT NULL,
            `label_en` text NOT NULL,
            `description` varchar(255) DEFAULT '',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_trans_key` (`trans_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('Failed to ensure store_translations table: ' . $conn->error);
            return false;
        }

        // Seed default translations if empty or new keys added
        $countRes = $conn->query("SELECT COUNT(*) as cnt FROM `store_translations`");
        $count = $countRes ? (int)($countRes->fetch_assoc()['cnt'] ?? 0) : 0;
        if ($count < count(getDefaultTranslationsMap())) {
            seedDefaultTranslations($conn);
        }

        return true;
    }
}

if (!function_exists('getDefaultTranslationsMap')) {
    function getDefaultTranslationsMap(): array
    {
        return [
            // Navigation
            'nav_home' => ['group' => 'navigation', 'km' => 'ទំព័រដើម', 'en' => 'Home', 'desc' => 'Navbar Home link'],
            'nav_about' => ['group' => 'navigation', 'km' => 'អំពីយើង', 'en' => 'About Us', 'desc' => 'Navbar About Us link'],
            'nav_support' => ['group' => 'navigation', 'km' => 'ជំនួយការ', 'en' => 'Support', 'desc' => 'Navbar Support link'],
            'nav_payments' => ['group' => 'navigation', 'km' => 'ការទូទាត់', 'en' => 'Payments', 'desc' => 'Navbar Payments link'],
            'nav_cart' => ['group' => 'navigation', 'km' => 'កន្ត្រក', 'en' => 'Cart', 'desc' => 'Navbar Cart button text'],
            'nav_telegram' => ['group' => 'navigation', 'km' => 'Telegram', 'en' => 'Telegram', 'desc' => 'Navbar Telegram button'],
            'nav_brand_tagline' => ['group' => 'navigation', 'km' => 'ហាងសម្លៀកបំពាក់ និងម៉ូដ', 'en' => 'Fashion & Apparel', 'desc' => 'Brand tagline under logo'],

            // Hero & Storefront Header
            'hero_curated' => ['group' => 'storefront', 'km' => 'Curated Collection', 'en' => 'Curated Collection', 'desc' => 'Hero badge'],
            'hero_cta' => ['group' => 'storefront', 'km' => 'ទិញទំនិញឥឡូវនេះ (Shop Collection)', 'en' => 'Shop Collection Now', 'desc' => 'Hero call to action button'],
            'stat_quality_title' => ['group' => 'storefront', 'km' => '100%', 'en' => '100%', 'desc' => 'Hero stat quality number'],
            'stat_quality_desc' => ['group' => 'storefront', 'km' => 'គុណភាពធានា', 'en' => 'Quality Guaranteed', 'desc' => 'Hero stat quality text'],
            'stat_shipping_title' => ['group' => 'storefront', 'km' => 'រហ័ស', 'en' => 'Fast', 'desc' => 'Hero stat shipping number'],
            'stat_shipping_desc' => ['group' => 'storefront', 'km' => 'ដឹកជញ្ជូនរហ័ស', 'en' => 'Fast Delivery', 'desc' => 'Hero stat shipping text'],
            'stat_khqr_title' => ['group' => 'storefront', 'km' => 'KHQR', 'en' => 'KHQR', 'desc' => 'Hero stat payment number'],
            'stat_khqr_desc' => ['group' => 'storefront', 'km' => 'ទូទាត់ងាយស្រួល', 'en' => 'Easy Payment', 'desc' => 'Hero stat payment text'],

            // Hero Headline & Description
            'home_hero_title' => ['group' => 'storefront', 'km' => 'គុណភាពខ្ពស់ 100% ម៉ូដទាន់សម័យ', 'en' => '100% Premium Quality & Modern Fashion', 'desc' => 'Hero banner main heading'],
            'home_hero_desc' => ['group' => 'storefront', 'km' => 'ទិញ-លក់សម្លៀកបំពាក់ និងម៉ូដទាន់សម័យ ប្រកបដោយសុវត្ថិភាព មានការធានាជូន និងគាំទ្រការទូទាត់តាម KHQR គ្រប់ធនាគារក្នុងប្រទេសកម្ពុជា។', 'en' => 'Shop curated fashion & modern apparel with confidence. 100% genuine quality, fast nationwide delivery, and seamless Bakong KHQR payments.', 'desc' => 'Hero banner description'],
            'catalog_no_products' => ['group' => 'storefront', 'km' => 'មិនទាន់មានផលិតផលសម្រាប់លក់នៅឡើយទេ', 'en' => 'No products available at the moment.', 'desc' => 'Empty catalog message'],

            // Trust capabilities
            'trust_shipping_title' => ['group' => 'storefront', 'km' => 'ដឹកជញ្ជូនរហ័សទូទាំងប្រទេស', 'en' => 'Fast Nationwide Delivery', 'desc' => 'Trust card 1 title'],
            'trust_shipping_desc' => ['group' => 'storefront', 'km' => 'ដឹកជញ្ជូនរហ័សដល់ដៃ', 'en' => 'Fast shipping to your doorstep', 'desc' => 'Trust card 1 subtitle'],
            'trust_quality_title' => ['group' => 'storefront', 'km' => 'ធានាសុវត្ថិភាព 100%', 'en' => '100% Verified Quality', 'desc' => 'Trust card 2 title'],
            'trust_quality_desc' => ['group' => 'storefront', 'km' => 'គុណភាពពិតប្រាកដ', 'en' => 'Authentic products guaranteed', 'desc' => 'Trust card 2 subtitle'],
            'trust_payment_title' => ['group' => 'storefront', 'km' => 'ទូទាត់រហ័ស KHQR', 'en' => 'Instant KHQR Payment', 'desc' => 'Trust card 3 title'],
            'trust_payment_desc' => ['group' => 'storefront', 'km' => 'Bakong & គ្រប់ធនាគារ', 'en' => 'Bakong & All Cambodian Banks', 'desc' => 'Trust card 3 subtitle'],
            'trust_support_title' => ['group' => 'storefront', 'km' => 'ជំនួយអតិថិជន 24/7', 'en' => 'Customer Support 24/7', 'desc' => 'Trust card 4 title'],
            'trust_support_desc' => ['group' => 'storefront', 'km' => 'Telegram Support Desk', 'en' => 'Telegram Support Desk', 'desc' => 'Trust card 4 subtitle'],

            // Footer
            'footer_payments_title' => ['group' => 'storefront', 'km' => 'វិធីទូទាត់ប្រាក់ និងដៃគូធនាគារ', 'en' => 'Payment Methods & Banking Partners', 'desc' => 'Footer payments heading'],
            'footer_payments_desc' => ['group' => 'storefront', 'km' => 'ទទួលការទូទាត់យ៉ាងរហ័សតាមរយៈប្រព័ន្ធ KHQR និងគ្រប់ធនាគារក្នុងប្រទេសកម្ពុជា', 'en' => 'Fast and secure payments accepted via Bakong KHQR and all major Cambodian banks', 'desc' => 'Footer payments subtitle'],
            'footer_copyright' => ['group' => 'storefront', 'km' => 'រក្សាសិទ្ធិគ្រប់យ៉ាង', 'en' => 'All rights reserved.', 'desc' => 'Footer copyright note'],
            'cart_shipping_badge' => ['group' => 'cart', 'km' => 'គាំទ្រការដឹកជញ្ជូនរហ័ស និងទូទាត់តាម KHQR', 'en' => 'Fast nationwide shipping & Bakong KHQR payment supported', 'desc' => 'Cart drawer shipping guarantee notice'],

            // Catalog Header, Search & Filter
            'catalog_kicker' => ['group' => 'storefront', 'km' => 'THE 4K EDIT', 'en' => 'THE 4K EDIT', 'desc' => 'Catalog section kicker'],
            'catalog_title' => ['group' => 'storefront', 'km' => 'ស្វែងរកផលិតផលដែលអ្នកពេញចិត្ត', 'en' => 'Discover Products You Love', 'desc' => 'Catalog main heading'],
            'catalog_subtitle' => ['group' => 'storefront', 'km' => 'រើសមើលសម្លៀកបំពាក់ ម៉ូដទាន់សម័យ និងទំនិញគុណភាពខ្ពស់', 'en' => 'Browse stylish fashion, modern apparel and premium goods', 'desc' => 'Catalog subtitle'],
            'search_placeholder' => ['group' => 'storefront', 'km' => 'ស្វែងរកតាមឈ្មោះផលិតផល (Search by product name, e.g. T-Shirt, Jeans, Dress...)', 'en' => 'Search by product name (e.g. T-Shirt, Jeans, Dress...)', 'desc' => 'Search input placeholder'],
            'cat_all' => ['group' => 'storefront', 'km' => 'ទាំងអស់ (All)', 'en' => 'All Items', 'desc' => 'Category All filter pill'],
            'cat_men' => ['group' => 'storefront', 'km' => 'បុរស (Men)', 'en' => 'Men', 'desc' => 'Category Men filter pill'],
            'cat_women' => ['group' => 'storefront', 'km' => 'នារី (Women)', 'en' => 'Women', 'desc' => 'Category Women filter pill'],
            'cat_accessories' => ['group' => 'storefront', 'km' => 'គ្រឿងតុបតែង (Accessories)', 'en' => 'Accessories', 'desc' => 'Category Accessories filter pill'],
            'cat_digital' => ['group' => 'storefront', 'km' => 'ឌីជីថល (Digital)', 'en' => 'Digital', 'desc' => 'Category Digital filter pill'],
            'sort_featured' => ['group' => 'storefront', 'km' => 'ពេញនិយម (Featured)', 'en' => 'Featured', 'desc' => 'Sort Featured option'],
            'sort_discount_desc' => ['group' => 'storefront', 'km' => 'បញ្ចុះតម្លៃច្រើនជាងគេ (SALE)', 'en' => 'Biggest Discount (SALE)', 'desc' => 'Sort Biggest Discount option'],
            'sort_price_asc' => ['group' => 'storefront', 'km' => 'តម្លៃ: ពីទាបទៅខ្ពស់', 'en' => 'Price: Low to High', 'desc' => 'Sort Price Low to High option'],
            'sort_price_desc' => ['group' => 'storefront', 'km' => 'តម្លៃ: ពីខ្ពស់ទៅទាប', 'en' => 'Price: High to Low', 'desc' => 'Sort Price High to Low option'],
            'sort_newest' => ['group' => 'storefront', 'km' => 'ទើបមកដល់ (New Arrivals)', 'en' => 'New Arrivals', 'desc' => 'Sort Newest option'],
            'showing_products' => ['group' => 'storefront', 'km' => 'បង្ហាញ', 'en' => 'Showing', 'desc' => 'Showing counter prefix'],
            'products_unit' => ['group' => 'storefront', 'km' => 'ផលិតផល', 'en' => 'Products', 'desc' => 'Products counter suffix'],
            'empty_search_title' => ['group' => 'storefront', 'km' => 'រកមិនឃើញផលិតផលដែលត្រូវនឹងពាក្យស្វែងរក', 'en' => 'No products found matching your search', 'desc' => 'Search empty state title'],
            'empty_search_desc' => ['group' => 'storefront', 'km' => 'សូមសាកល្បងស្វែងរកដោយប្រើពាក្យគន្លឹះផ្សេងទៀត ឬចុចប៊ូតុងខាងក្រោមដើម្បីមើលផលិតផលទាំងអស់ឡើងវិញ។', 'en' => 'Try searching with different keywords or click the button below to view all products.', 'desc' => 'Search empty state description'],
            'reset_filter_btn' => ['group' => 'storefront', 'km' => 'បង្ហាញផលិតផលទាំងអស់ (Reset Filter)', 'en' => 'Reset Filters & Show All', 'desc' => 'Reset search filters button'],

            // Product Cards & Quick View
            'badge_in_stock' => ['group' => 'products', 'km' => 'មានស្តុក', 'en' => 'In Stock', 'desc' => 'Product card in stock badge'],
            'badge_discount' => ['group' => 'products', 'km' => 'បញ្ចុះតម្លៃ', 'en' => 'SALE', 'desc' => 'Product card discount sale badge prefix'],
            'discount_save_text' => ['group' => 'products', 'km' => 'សន្សំបាន', 'en' => 'Save', 'desc' => 'Product card discount savings text'],
            'badge_guarantee' => ['group' => 'products', 'km' => 'ធានាគុណភាព 100%', 'en' => '100% Quality Guaranteed', 'desc' => 'Product card guarantee spec'],
            'price_per_unit' => ['group' => 'products', 'km' => '/ មួយ', 'en' => '/ item', 'desc' => 'Product card per unit text'],
            'btn_quickview' => ['group' => 'products', 'km' => 'មើលព័ត៌មានលម្អិត', 'en' => 'Quick View', 'desc' => 'Product card quick view button tooltip'],
            'btn_add_cart' => ['group' => 'products', 'km' => 'កន្ត្រក', 'en' => 'Cart', 'desc' => 'Product card add to cart button'],
            'btn_buy_now' => ['group' => 'products', 'km' => 'ទិញភ្លាម', 'en' => 'Buy Now', 'desc' => 'Product card buy now button'],
            'qv_stock_notice' => ['group' => 'products', 'km' => 'មានក្នុងស្តុក', 'en' => 'In Stock', 'desc' => 'Quick view stock notification'],
            'qv_qty_label' => ['group' => 'products', 'km' => 'ចំនួន:', 'en' => 'Quantity:', 'desc' => 'Quick view quantity label'],
            'qv_btn_add_cart' => ['group' => 'products', 'km' => 'ដាក់ក្នុងកន្ត្រក', 'en' => 'Add to Cart', 'desc' => 'Quick view add to cart button'],
            'qv_btn_buy_now' => ['group' => 'products', 'km' => 'ទិញឥឡូវ', 'en' => 'Buy Now', 'desc' => 'Quick view buy now button'],
            'toast_cart_added' => ['group' => 'products', 'km' => 'បានដាក់ចូលកន្ត្រកជោគជ័យ!', 'en' => 'Added to cart successfully!', 'desc' => 'Added to cart toast notification'],
            'toast_cart_empty' => ['group' => 'products', 'km' => 'កន្ត្រករបស់អ្នកទទេ! សូមជ្រើសរើសទំនិញជាមុនសិន', 'en' => 'Your cart is empty! Please choose a product first.', 'desc' => 'Empty cart toast warning'],

            // Cart Drawer
            'cart_drawer_title' => ['group' => 'cart', 'km' => 'កន្ត្រកទំនិញរបស់អ្នក', 'en' => 'Your Shopping Cart', 'desc' => 'Cart drawer header'],
            'cart_empty_text' => ['group' => 'cart', 'km' => 'កន្ត្រករបស់អ្នកមិនទាន់មានទំនិញនៅឡើយទេ', 'en' => 'Your shopping cart is currently empty', 'desc' => 'Cart empty description'],
            'cart_subtotal_label' => ['group' => 'cart', 'km' => 'តម្លៃសរុប (Subtotal):', 'en' => 'Subtotal:', 'desc' => 'Cart drawer subtotal label'],
            'cart_btn_checkout' => ['group' => 'cart', 'km' => 'បន្តទៅកាន់ការទូទាត់ (Proceed to Checkout)', 'en' => 'Proceed to Checkout', 'desc' => 'Cart drawer checkout button'],
            'cart_btn_shop_now' => ['group' => 'cart', 'km' => 'ទៅកាន់កាតាឡុកទំនិញ (Shop Now)', 'en' => 'Shop Collection', 'desc' => 'Cart empty shop now button'],

            // About Us Page
            'about_hero_eyebrow' => ['group' => 'about', 'km' => 'ស្តង់ដារ និងគុណភាពខ្ពស់', 'en' => 'Premium Standards & Quality', 'desc' => 'About hero eyebrow pill'],
            'about_hero_title' => ['group' => 'about', 'km' => 'ស្វែងយល់អំពី 4K STORE និងការប្តេជ្ញាចិត្តរបស់យើង', 'en' => 'Discover 4K STORE and Our Commitment', 'desc' => 'About hero headline'],
            'about_hero_lead' => ['group' => 'about', 'km' => '4K STORE គឺជាហាងសម្លៀកបំពាក់ និងម៉ូដទាន់សម័យ ដែលនាំមកជូនលោកអ្នកនូវផលិតផលប្រកបដោយគុណភាពខ្ពស់ ភាពទាន់សម័យ និងទំនុកចិត្ត 100%។ យើងផ្តល់ជូននូវបទពិសោធន៍ទិញទំនិញអនឡាញដ៏ងាយស្រួល សុវត្ថិភាព និងរហ័សទាន់ចិត្ត។', 'en' => '4K STORE is a modern fashion and apparel boutique bringing you premium quality, contemporary trends, and 100% customer trust. We deliver a seamless, secure, and fast online shopping experience.', 'desc' => 'About hero lead text'],
            'about_btn_browse' => ['group' => 'about', 'km' => 'ស្វែងរកផលិតផល', 'en' => 'Shop Collection Now', 'desc' => 'About hero browse button'],
            'about_btn_support' => ['group' => 'about', 'km' => 'ទាក់ទងផ្នែកបម្រើអតិថិជន', 'en' => 'Contact Customer Support', 'desc' => 'About hero support button'],
            'about_val1_desc_short' => ['group' => 'about', 'km' => 'គុណភាពធានា និងផលិតផលពិត', 'en' => 'Guaranteed Quality & Genuine Products', 'desc' => 'About fact quality guaranteed'],
            'about_service_short' => ['group' => 'about', 'km' => 'សេវាកម្មរហ័ស និងការឆ្លើយតប', 'en' => 'Fast Service & Responsive Care', 'desc' => 'About fact fast customer service'],
            'about_payment_short' => ['group' => 'about', 'km' => 'ទូទាត់ប្រាក់ងាយស្រួល និងសុវត្ថិភាព', 'en' => 'Easy & Secure KHQR Payment', 'desc' => 'About fact easy KHQR payment'],
            'about_story_heading_kicker' => ['group' => 'about', 'km' => 'រឿងរ៉ាវ & បេសកកម្ម', 'en' => 'Our Story & Mission', 'desc' => 'About story section kicker'],
            'about_story_heading_title' => ['group' => 'about', 'km' => 'រឿងរ៉ាវ និងបេសកកម្មរបស់យើង', 'en' => 'Our Story & Mission', 'desc' => 'About story section title'],
            'about_story_heading_desc' => ['group' => 'about', 'km' => 'ស្វែងយល់ពីប្រវត្តិ និងគោលបំណងចម្បងក្នុងការបង្កើតឡើងនូវ 4K STORE', 'en' => 'Learn about the founding history and core mission behind 4K STORE', 'desc' => 'About story section subtitle'],
            'about_who_title' => ['group' => 'about', 'km' => 'យើងជានរណា?', 'en' => 'Who We Are', 'desc' => 'About card who we are title'],
            'about_who_desc' => ['group' => 'about', 'km' => '4K STORE បង្កើតឡើងដោយក្តីស្រឡាញ់លើម៉ូដសម្លៀកបំពាក់ និងបំណងប្រាថ្នាក្នុងការផ្តល់ជូនអតិថិជននូវជម្រើសសម្លៀកបំពាក់ស្អាតៗ គុណភាពល្អ សម្រាប់បុរស នារី និងគ្រឿងតុបតែងខ្លួន ដែលស័ក្តិសមនឹងគ្រប់កាលៈទេសៈ។', 'en' => '4K STORE was founded out of a passion for fashion and a dedication to providing curated apparel and stylish accessories for men, women, and everyday occasions.', 'desc' => 'About card who we are description'],
            'about_mission_title' => ['group' => 'about', 'km' => 'បេសកកម្មរបស់យើង', 'en' => 'Our Mission', 'desc' => 'About card mission title'],
            'about_mission_desc' => ['group' => 'about', 'km' => 'បេសកកម្មចម្បងរបស់យើង គឺការផ្តល់ជូននូវបទពិសោធន៍ទិញទំនិញតាមប្រព័ន្ធឌីជីថលដ៏ល្អឥតខ្ចោះ តម្លៃសមរម្យ តម្លាភាព និងសេវាកម្មដឹកជញ្ជូនរហ័សដល់ដៃអតិថិជនប្រកបដោយការពេញចិត្តខ្ពស់បំផុត។', 'en' => 'Our core mission is to provide an exceptional online shopping journey with honest pricing, absolute transparency, and fast, reliable nationwide delivery.', 'desc' => 'About card mission description'],
            'about_values_heading_kicker' => ['group' => 'about', 'km' => 'តម្លៃស្នូលរបស់យើង', 'en' => 'Our Core Values', 'desc' => 'About values kicker'],
            'about_values_heading_title' => ['group' => 'about', 'km' => 'តម្លៃស្នូលដែលយើងប្រកាន់ខ្ជាប់', 'en' => 'Core Values We Live By', 'desc' => 'About values heading'],
            'about_values_heading_desc' => ['group' => 'about', 'km' => 'គោលការណ៍ទាំងនេះណែនាំការងាររបស់យើង និងរបៀបដែលយើងបម្រើអតិថិជនគ្រប់រូប', 'en' => 'These principles guide our operations and define how we serve every customer', 'desc' => 'About values subtitle'],
            'about_val1_title' => ['group' => 'about', 'km' => 'គុណភាពជាចម្បង', 'en' => 'Quality First', 'desc' => 'About value 1 title'],
            'about_val1_desc' => ['group' => 'about', 'km' => 'រាល់ផលិតផលទាំងអស់ត្រូវបានជ្រើសរើស និងត្រួតពិនិត្យយ៉ាងហ្មត់ចត់មុនពេលបញ្ជូនទៅកាន់ដៃអតិថិជន។', 'en' => 'Every product is handpicked and thoroughly inspected before being dispatched to our customers.', 'desc' => 'About value 1 description'],
            'about_val2_title' => ['group' => 'about', 'km' => 'ទំនុកចិត្ត និងតម្លាភាព', 'en' => 'Trust & Transparency', 'desc' => 'About value 2 title'],
            'about_val2_desc' => ['group' => 'about', 'km' => 'ព័ត៌មានតម្លៃ រូបភាព និងទំហំស្តុកពិតប្រាកដ 100% គ្មានការបន្លំ ឬលាក់បាំងព័ត៌មានឡើយ។', 'en' => '100% accurate pricing, genuine photography, and real-time stock levels with zero hidden fees.', 'desc' => 'About value 2 description'],
            'about_val3_title' => ['group' => 'about', 'km' => 'ការទទួលខុសត្រូវខ្ពស់', 'en' => 'Accountability', 'desc' => 'About value 3 title'],
            'about_val3_desc' => ['group' => 'about', 'km' => 'យើងយកចិត្តទុកដាក់ខ្ពស់ចំពោះការពេញចិត្តរបស់អតិថិជន និងត្រៀមខ្លួនជួយដោះស្រាយរាល់ចម្ងល់។', 'en' => 'We are genuinely committed to customer satisfaction and are always ready to assist with any questions.', 'desc' => 'About value 3 description'],
            'about_steps_heading_kicker' => ['group' => 'about', 'km' => 'របៀបទិញទំនិញ', 'en' => 'How To Shop', 'desc' => 'About steps kicker'],
            'about_steps_heading_title' => ['group' => 'about', 'km' => 'របៀបទិញទំនិញងាយៗ ៤ ជំហាន', 'en' => '4 Easy Steps to Shop', 'desc' => 'About steps title'],
            'about_steps_heading_desc' => ['group' => 'about', 'km' => 'បទពិសោធន៍ទិញទំនិញដ៏រហ័ស និងសុវត្ថិភាពបំផុតជាមួយ 4K STORE', 'en' => 'A fast and secure shopping experience with 4K STORE', 'desc' => 'About 4 steps guide description'],
            'about_step1_title' => ['group' => 'about', 'km' => 'ជ្រើសរើសផលិតផល', 'en' => 'Choose Products', 'desc' => 'About step 1 title'],
            'about_step1_desc' => ['group' => 'about', 'km' => 'រើសមើលសម្លៀកបំពាក់ និងម៉ូដដែលអ្នកស្រឡាញ់ក្នុង Storefront', 'en' => 'Explore and select the fashion items you love in the catalog', 'desc' => 'About step 1 description'],
            'about_step2_title' => ['group' => 'about', 'km' => 'ពិនិត្យ និងបញ្ជាទិញ', 'en' => 'Review & Order', 'desc' => 'About step 2 title'],
            'about_step2_desc' => ['group' => 'about', 'km' => 'ពិនិត្យព័ត៌មានលម្អិត បញ្ចូលក្នុងកន្ត្រក ឬចុចទិញភ្លាមៗ', 'en' => 'Inspect details, add items to cart, or click Buy Now directly', 'desc' => 'About step 2 description'],
            'about_step3_title' => ['group' => 'about', 'km' => 'ស្កេនទូទាត់ KHQR', 'en' => 'Scan Bakong KHQR', 'desc' => 'About step 3 title'],
            'about_step3_desc' => ['group' => 'about', 'km' => 'ស្កេនទូទាត់ប្រាក់តាមប្រព័ន្ធ Bakong KHQR គ្រប់ធនាគារ', 'en' => 'Scan and pay seamlessly via Bakong KHQR with any mobile banking app', 'desc' => 'About step 3 description'],
            'about_step4_title' => ['group' => 'about', 'km' => 'ផ្ញើវិក្កយបត្រ & រង់ចាំ', 'en' => 'Upload Receipt & Relax', 'desc' => 'About step 4 title'],
            'about_step4_desc' => ['group' => 'about', 'km' => 'ភ្ជាប់រូបវិក្កយបត្រ រួចរង់ចាំទទួលទំនិញដឹកជញ្ជូនដល់ផ្ទះ', 'en' => 'Attach payment proof and await fast doorstep delivery', 'desc' => 'About step 4 description'],
            'about_help_eyebrow' => ['group' => 'about', 'km' => 'យើងនៅទីនេះដើម្បីជួយអ្នក', 'en' => "WE'RE HERE TO HELP", 'desc' => 'About contact section eyebrow'],
            'about_cta_title' => ['group' => 'about', 'km' => 'មានសំណួរអំពីផលិតផល ឬការបញ្ជាទិញ?', 'en' => 'Have questions about products or orders?', 'desc' => 'About contact CTA title'],
            'about_cta_desc' => ['group' => 'about', 'km' => 'ទាក់ទងក្រុមការងាររបស់យើងតាម Telegram ឬទំព័រជំនួយអតិថិជន សម្រាប់សួរព័ត៌មានបន្ថែមអំពីផលិតផល ការទូទាត់ ឬជំនួយក្រោយការបញ្ជាទិញ។', 'en' => 'Reach out to our friendly support team via Telegram or our Support Page for immediate assistance.', 'desc' => 'About contact CTA description'],
            'about_cta_telegram' => ['group' => 'about', 'km' => 'ទាក់ទងតាម Telegram', 'en' => 'Contact via Telegram', 'desc' => 'About contact Telegram button'],
            'about_cta_form' => ['group' => 'about', 'km' => 'ផ្ញើសារក្នុងទំព័រជំនួយ', 'en' => 'Send Message via Support', 'desc' => 'About contact form button'],

            // Customer Support
            'support_title' => ['group' => 'support', 'km' => 'ជំនួយ & ទំនាក់ទំនងអតិថិជន', 'en' => 'Customer Support & Inquiries', 'desc' => 'Support page title'],
            'support_badge_pill' => ['group' => 'support', 'km' => 'សេវាកម្មអតិថិជន 24/7', 'en' => 'Customer Support 24/7', 'desc' => 'Support kicker badge'],
            'support_hero_title' => ['group' => 'support', 'km' => 'យើងនៅទីនេះដើម្បីជួយអ្នក', 'en' => 'We Are Here to Help You', 'desc' => 'Support left box main headline'],
            'support_lead' => ['group' => 'support', 'km' => 'មានសំណួរអំពីផលិតផល ការកុម្ម៉ង់ទិញ ការទូទាត់ប្រាក់ KHQR ឬការដឹកជញ្ជូនមែនទេ? សូមទាក់ទងមកកាន់យើងតាមបណ្តាញខាងក្រោម ឬផ្ញើសារតាមទម្រង់។', 'en' => 'Have questions regarding sizing, order status, KHQR payments, or shipping? Contact us below or send a message.', 'desc' => 'Support page lead description'],
            'support_tg_title' => ['group' => 'support', 'km' => 'Telegram Support', 'en' => 'Telegram Support', 'desc' => 'Support Telegram channel title'],
            'support_tg_desc' => ['group' => 'support', 'km' => 'ឆ្លើយតបរហ័សទាន់ចិត្ត ក្រោម 10 នាទី', 'en' => 'Fast direct reply in under 10 minutes', 'desc' => 'Support Telegram channel description'],
            'support_phone_title' => ['group' => 'support', 'km' => 'លេខទូរស័ព្ទផ្ទាល់', 'en' => 'Direct Phone Call', 'desc' => 'Support phone channel title'],
            'support_phone_desc' => ['group' => 'support', 'km' => 'ទំនាក់ទំនងផ្ទាល់ក្នុងម៉ោងបម្រើសេវា', 'en' => 'Call us directly during operating hours', 'desc' => 'Support phone channel description'],
            'support_hours_title' => ['group' => 'support', 'km' => 'ម៉ោងបម្រើសេវាកម្ម', 'en' => 'Operating Hours', 'desc' => 'Support operating hours title'],
            'support_hours_desc' => ['group' => 'support', 'km' => 'ច័ន្ទ - អាទិត្យ: 8:00 AM - 10:00 PM', 'en' => 'Monday - Sunday: 8:00 AM - 10:00 PM', 'desc' => 'Support operating hours description'],
            'support_hours_badge' => ['group' => 'support', 'km' => 'កំពុងដំណើរការរាល់ថ្ងៃ', 'en' => 'Active Daily', 'desc' => 'Support operating hours active badge'],
            'support_trust_title' => ['group' => 'support', 'km' => 'ការធានា និងទំនុកចិត្ត', 'en' => 'Trust & Dedication', 'desc' => 'Support trust channel title'],
            'support_trust_desc' => ['group' => 'support', 'km' => '100% យកចិត្តទុកដាក់ ដោះស្រាយបញ្ហារហ័ស', 'en' => '100% dedicated care, swift resolution', 'desc' => 'Support trust channel description'],
            'support_trust_badge' => ['group' => 'support', 'km' => 'ធានាការឆ្លើយតប', 'en' => 'Guaranteed Reply', 'desc' => 'Support trust channel reply guarantee badge'],
            'support_location' => ['group' => 'support', 'km' => 'រាជធានីភ្នំពេញ ព្រះរាជាណាចក្រកម្ពុជា', 'en' => 'Phnom Penh, Kingdom of Cambodia', 'desc' => 'Support location text'],
            'support_form_kicker' => ['group' => 'support', 'km' => 'ផ្ញើសារមកកាន់យើង', 'en' => 'Send Us a Message', 'desc' => 'Support form kicker'],
            'support_form_title' => ['group' => 'support', 'km' => 'ទម្រង់ទំនាក់ទំនង', 'en' => 'Contact Form', 'desc' => 'Support form heading'],
            'support_form_lead' => ['group' => 'support', 'km' => 'សូមបំពេញព័ត៌មានខាងក្រោម ក្រុមការងារយើងនឹងទាក់ទងត្រឡប់ទៅលោកអ្នកក្នុងពេលឆាប់ៗបំផុត។', 'en' => 'Please fill out the form below and our team will get back to you promptly.', 'desc' => 'Support form lead description'],
            'support_name_label' => ['group' => 'support', 'km' => 'ឈ្មោះពេញរបស់អ្នក *', 'en' => 'Your Name (Full Name) *', 'desc' => 'Support name input label'],
            'support_name_ph' => ['group' => 'support', 'km' => 'ឧទាហរណ៍: សុខា', 'en' => 'e.g. Sokha Chan', 'desc' => 'Support name input placeholder'],
            'support_contact_label' => ['group' => 'support', 'km' => 'អ៊ីម៉ែល ឬ Telegram ID ឬលេខទូរស័ព្ទ *', 'en' => 'Email, Telegram ID or Phone Number *', 'desc' => 'Support contact input label'],
            'support_contact_ph' => ['group' => 'support', 'km' => 'ឧទាហរណ៍: sokha@gmail.com ឬ @sokha_kh', 'en' => 'e.g. sokha@gmail.com or @sokha_tg', 'desc' => 'Support contact input placeholder'],
            'support_msg_label' => ['group' => 'support', 'km' => 'សារ ឬសំណួររបស់អ្នក *', 'en' => 'Your Message / Inquiry *', 'desc' => 'Support message textarea label'],
            'support_msg_ph' => ['group' => 'support', 'km' => 'សូមសរសេរសារ ឬសំណួរអំពីផលិតផល និងការបញ្ជាទិញនៅទីនេះ...', 'en' => 'Write your question, sizing inquiry, or order request here...', 'desc' => 'Support message textarea placeholder'],
            'support_btn_send' => ['group' => 'support', 'km' => 'បញ្ជូនសារ', 'en' => 'Send Message', 'desc' => 'Support submit button'],
            'support_alert_success' => ['group' => 'support', 'km' => 'សាររបស់អ្នកត្រូវបានបញ្ជូនដោយជោគជ័យ! យើងនឹងឆ្លើយតបឆាប់ៗនេះ។', 'en' => 'Your message has been sent successfully! We will get back to you shortly.', 'desc' => 'Support success alert message'],
            'support_alert_error' => ['group' => 'support', 'km' => 'មានបញ្ហាក្នុងការបញ្ជូនសារ សូមពិនិត្យព័ត៌មាន និងព្យាយាមម្តងទៀត។', 'en' => 'Failed to send message. Please review your information and try again.', 'desc' => 'Support error alert message'],

            // Checkout Page
            'checkout_page_title' => ['group' => 'checkout', 'km' => 'ទូទាត់ប្រាក់ • Checkout', 'en' => 'Checkout • Secure Order', 'desc' => 'Checkout page title'],
            'checkout_bc_home' => ['group' => 'checkout', 'km' => 'ទំព័រដើម', 'en' => 'Home', 'desc' => 'Checkout breadcrumb home'],
            'checkout_bc_curr' => ['group' => 'checkout', 'km' => 'ទូទាត់ប្រាក់', 'en' => 'Checkout', 'desc' => 'Checkout breadcrumb current'],
            'checkout_box_customer' => ['group' => 'checkout', 'km' => 'ព័ត៌មានអតិថិជន & ដឹកជញ្ជូន', 'en' => 'Customer & Delivery Info', 'desc' => 'Checkout customer details box title'],
            'checkout_name_label' => ['group' => 'checkout', 'km' => 'ឈ្មោះពេញរបស់អ្នក (Full Name) *', 'en' => 'Full Name *', 'desc' => 'Checkout full name label'],
            'checkout_name_ph' => ['group' => 'checkout', 'km' => 'ឧទាហរណ៍: សុខា (Sokha)', 'en' => 'e.g. Sokha Chan', 'desc' => 'Checkout full name placeholder'],
            'checkout_phone_label' => ['group' => 'checkout', 'km' => 'លេខទូរស័ព្ទ / Telegram *', 'en' => 'Phone Number / Telegram *', 'desc' => 'Checkout phone/telegram label'],
            'checkout_phone_ph' => ['group' => 'checkout', 'km' => 'ឧទាហរណ៍: 012 345 678 ឬ @sokha_tg', 'en' => 'e.g. 012 345 678 or @username', 'desc' => 'Checkout phone/telegram placeholder'],
            'checkout_address_label' => ['group' => 'checkout', 'km' => 'អាសយដ្ឋានដឹកជញ្ជូន (Delivery Address) *', 'en' => 'Delivery Address *', 'desc' => 'Checkout address label'],
            'checkout_address_ph' => ['group' => 'checkout', 'km' => 'ផ្ទះលេខ, ផ្លូវលេខ, សង្កាត់, ខណ្ឌ, រាជធានី/ខេត្ត...', 'en' => 'House, Street, Sangkat, Khan, City/Province...', 'desc' => 'Checkout address placeholder'],
            'checkout_note_label' => ['group' => 'checkout', 'km' => 'ចំណាំបន្ថែម (Delivery Notes)', 'en' => 'Delivery Notes (Optional)', 'desc' => 'Checkout notes label'],
            'checkout_note_ph' => ['group' => 'checkout', 'km' => 'បញ្ជាក់ព័ត៌មានបន្ថែមសម្រាប់ការដឹកជញ្ជូន (បើមាន)', 'en' => 'Additional delivery instructions (optional)', 'desc' => 'Checkout notes placeholder'],
            'checkout_box_payment' => ['group' => 'checkout', 'km' => 'ទូទាត់តាមប្រព័ន្ធ Bakong KHQR', 'en' => 'Pay via Bakong KHQR', 'desc' => 'Checkout payment box title'],
            'checkout_qr_scan_note' => ['group' => 'checkout', 'km' => 'ស្កេន QR កូដខាងលើជាមួយ App ធនាគារណាមួយក៏បាន (ABA, Wing, ACLEDA, Canadia...)', 'en' => 'Scan the QR code with any mobile banking app (ABA, Wing, ACLEDA, Canadia...)', 'desc' => 'Checkout QR scan instruction'],
            'checkout_amount_to_pay' => ['group' => 'checkout', 'km' => 'ទឹកប្រាក់ត្រូវទូទាត់:', 'en' => 'Amount to Pay:', 'desc' => 'Checkout amount to pay text'],
            'checkout_box_receipt' => ['group' => 'checkout', 'km' => 'ភ្ជាប់រូបភាពវិក្កយបត្រ (Payment Receipt) *', 'en' => 'Attach Payment Receipt *', 'desc' => 'Checkout receipt box title'],
            'checkout_receipt_drop_text' => ['group' => 'checkout', 'km' => 'ចុចទីនេះដើម្បីជ្រើសរើសរូបវិក្កយបត្រ ឬអូសទម្លាក់រូបចូល', 'en' => 'Click here to choose payment receipt or drag & drop image', 'desc' => 'Checkout receipt dropzone text'],
            'checkout_receipt_formats' => ['group' => 'checkout', 'km' => 'JPG, PNG, WEBP (អតិបរមា 10MB)', 'en' => 'JPG, PNG, WEBP (Max 10MB)', 'desc' => 'Checkout receipt allowed formats'],
            'checkout_box_summary' => ['group' => 'checkout', 'km' => 'សង្ខេបការបញ្ជាទិញ', 'en' => 'Order Summary', 'desc' => 'Checkout summary box title'],
            'checkout_coupon_label' => ['group' => 'checkout', 'km' => 'លេខកូដបញ្ចុះតម្លៃ (Coupon Code)', 'en' => 'Discount Coupon Code', 'desc' => 'Checkout coupon label'],
            'checkout_coupon_ph' => ['group' => 'checkout', 'km' => 'បញ្ចូលកូដបញ្ចុះតម្លៃ...', 'en' => 'Enter coupon code...', 'desc' => 'Checkout coupon placeholder'],
            'checkout_coupon_btn' => ['group' => 'checkout', 'km' => 'អនុវត្ត', 'en' => 'Apply', 'desc' => 'Checkout coupon apply button'],
            'checkout_subtotal_label' => ['group' => 'checkout', 'km' => 'តម្លៃទំនិញសរុប (Subtotal):', 'en' => 'Subtotal:', 'desc' => 'Checkout subtotal label'],
            'checkout_discount_label' => ['group' => 'checkout', 'km' => 'ការបញ្ចុះតម្លៃ (Discount):', 'en' => 'Discount:', 'desc' => 'Checkout discount label'],
            'checkout_shipping_label' => ['group' => 'checkout', 'km' => 'ថ្លៃសេវាដឹកជញ្ជូន (Shipping):', 'en' => 'Shipping:', 'desc' => 'Checkout shipping label'],
            'checkout_shipping_free' => ['group' => 'checkout', 'km' => 'ឥតគិតថ្លៃ (Free)', 'en' => 'FREE', 'desc' => 'Checkout free shipping text'],
            'checkout_total_label' => ['group' => 'checkout', 'km' => 'ទឹកប្រាក់សរុប (Total):', 'en' => 'Grand Total:', 'desc' => 'Checkout grand total label'],
            'checkout_btn_submit' => ['group' => 'checkout', 'km' => 'បញ្ជាក់ការបញ្ជាទិញ (Place Order)', 'en' => 'Place Order Now', 'desc' => 'Checkout place order submit button'],
            'checkout_trust_security' => ['group' => 'checkout', 'km' => '100% សុវត្ថិភាព', 'en' => '100% Secure', 'desc' => 'Checkout security trust text'],
            'checkout_trust_quality' => ['group' => 'checkout', 'km' => 'ធានាគុណភាព', 'en' => 'Quality Guaranteed', 'desc' => 'Checkout quality trust text'],
            'checkout_success_title' => ['group' => 'checkout', 'km' => 'ការបញ្ជាទិញជោគជ័យ!', 'en' => 'Order Placed Successfully!', 'desc' => 'Checkout success modal title'],
            'checkout_success_desc' => ['group' => 'checkout', 'km' => 'ព័ត៌មាន និងវិក្កយបត្ររបស់អ្នកត្រូវបានផ្ញើទៅកាន់ Telegram Admin រួចរាល់ហើយ។', 'en' => 'Your order details and payment receipt have been sent to Telegram Admin.', 'desc' => 'Checkout success modal description'],
            'checkout_order_ref_label' => ['group' => 'checkout', 'km' => 'លេខកូដបញ្ជាទិញ:', 'en' => 'Order Reference:', 'desc' => 'Checkout success order reference label'],
            'checkout_btn_continue' => ['group' => 'checkout', 'km' => 'ត្រឡប់ទៅកាន់ទំព័រដើម (Continue Shopping)', 'en' => 'Continue Shopping', 'desc' => 'Checkout success continue button'],
            'checkout_logged_in_as' => ['group' => 'checkout', 'km' => 'បានចូលគណនីជា', 'en' => 'Logged in as', 'desc' => 'Checkout logged in customer notice'],
            'checkout_guest_prompt' => ['group' => 'checkout', 'km' => 'មានគណនីរួចហើយ? ចូលគណនីដើម្បីបំពេញព័ត៌មានដោយស្វ័យប្រវត្តិ', 'en' => 'Have an account? Log in to pre-fill your delivery info', 'desc' => 'Checkout guest account prompt'],

            // User Authentication & Profile
            'nav_login' => ['group' => 'auth', 'km' => 'ចូលគណនី', 'en' => 'Sign In', 'desc' => 'Navbar Sign In button'],
            'nav_register' => ['group' => 'auth', 'km' => 'ចុះឈ្មោះ', 'en' => 'Register', 'desc' => 'Navbar Register button'],
            'nav_my_profile' => ['group' => 'auth', 'km' => 'គណនីរបស់ខ្ញុំ', 'en' => 'My Profile', 'desc' => 'User dropdown profile link'],
            'nav_my_orders' => ['group' => 'auth', 'km' => 'ប្រវត្តិបញ្ជាទិញ', 'en' => 'My Orders', 'desc' => 'User dropdown orders link'],
            'nav_logout' => ['group' => 'auth', 'km' => 'ចាកចេញ', 'en' => 'Logout', 'desc' => 'User dropdown logout link'],
            'auth_login_title' => ['group' => 'auth', 'km' => 'ចូលប្រើប្រាស់គណនី', 'en' => 'Welcome Back', 'desc' => 'Login card headline'],
            'auth_login_subtitle' => ['group' => 'auth', 'km' => 'ចូលគណនីរបស់អ្នកដើម្បីពិនិត្យការបញ្ជាទិញ និងទិញទំនិញកាន់តែរហ័ស', 'en' => 'Sign in to your account to track orders and checkout faster', 'desc' => 'Login card subtitle'],
            'auth_register_title' => ['group' => 'auth', 'km' => 'បង្កើតគណនីថ្មី', 'en' => 'Create an Account', 'desc' => 'Register card headline'],
            'auth_register_subtitle' => ['group' => 'auth', 'km' => 'ចុះឈ្មោះដើម្បីទទួលបានបទពិសោធន៍ទិញទំនិញដ៏ងាយស្រួល', 'en' => 'Sign up to enjoy faster checkout and track your orders', 'desc' => 'Register card subtitle'],
            'auth_google_btn' => ['group' => 'auth', 'km' => 'ចូលគណនីជាមួយ Google', 'en' => 'Continue with Google', 'desc' => 'Google sign in button label'],
            'auth_or_divider' => ['group' => 'auth', 'km' => 'ឬប្រើអ៊ីម៉ែល', 'en' => 'or with email', 'desc' => 'Divider between google and email login'],
            'auth_email_label' => ['group' => 'auth', 'km' => 'អាសយដ្ឋានអ៊ីម៉ែល (Email / Gmail) *', 'en' => 'Email Address *', 'desc' => 'Auth email field label'],
            'auth_email_ph' => ['group' => 'auth', 'km' => 'ឧទាហរណ៍: name@gmail.com', 'en' => 'e.g. name@gmail.com', 'desc' => 'Auth email field placeholder'],
            'auth_pass_label' => ['group' => 'auth', 'km' => 'ពាក្យសម្ងាត់ (Password) *', 'en' => 'Password *', 'desc' => 'Auth password field label'],
            'auth_pass_ph' => ['group' => 'auth', 'km' => 'យ៉ាងហោចណាស់ ៦ តួអក្សរ', 'en' => 'At least 6 characters', 'desc' => 'Auth password field placeholder'],
            'auth_name_label' => ['group' => 'auth', 'km' => 'ឈ្មោះពេញរបស់អ្នក *', 'en' => 'Full Name *', 'desc' => 'Auth full name label'],
            'auth_name_ph' => ['group' => 'auth', 'km' => 'ឧទាហរណ៍: សុខា', 'en' => 'e.g. Sokha Chan', 'desc' => 'Auth full name placeholder'],
            'auth_phone_label' => ['group' => 'auth', 'km' => 'លេខទូរស័ព្ទ / Telegram', 'en' => 'Phone Number / Telegram', 'desc' => 'Auth phone label'],
            'auth_phone_ph' => ['group' => 'auth', 'km' => 'ឧទាហរណ៍: 012 345 678', 'en' => 'e.g. 012 345 678', 'desc' => 'Auth phone placeholder'],
            'auth_remember_me' => ['group' => 'auth', 'km' => 'ចងចាំគណនីខ្ញុំ', 'en' => 'Remember me', 'desc' => 'Auth remember checkbox label'],
            'auth_btn_login' => ['group' => 'auth', 'km' => 'ចូលគណនី', 'en' => 'Sign In', 'desc' => 'Auth sign in button'],
            'auth_btn_register' => ['group' => 'auth', 'km' => 'បង្កើតគណនី', 'en' => 'Create Account', 'desc' => 'Auth register button'],
            'auth_no_account' => ['group' => 'auth', 'km' => 'មិនទាន់មានគណនីមែនទេ?', 'en' => "Don't have an account?", 'desc' => 'Auth switch prompt to register'],
            'auth_have_account' => ['group' => 'auth', 'km' => 'មានគណនីរួចហើយ?', 'en' => 'Already have an account?', 'desc' => 'Auth switch prompt to login'],
            'auth_switch_register' => ['group' => 'auth', 'km' => 'ចុះឈ្មោះឥឡូវនេះ', 'en' => 'Register now', 'desc' => 'Auth register link'],
            'auth_switch_login' => ['group' => 'auth', 'km' => 'ចូលគណនីឥឡូវនេះ', 'en' => 'Sign in now', 'desc' => 'Auth sign in link'],
            'profile_title' => ['group' => 'profile', 'km' => 'គណនី និងប្រវត្តិបញ្ជាទិញ', 'en' => 'My Profile & Orders', 'desc' => 'Profile page title'],
            'profile_tab_info' => ['group' => 'profile', 'km' => 'ព័ត៌មានផ្ទាល់ខ្លួន', 'en' => 'Personal Info', 'desc' => 'Profile personal info tab'],
            'profile_tab_orders' => ['group' => 'profile', 'km' => 'ប្រវត្តិបញ្ជាទិញ', 'en' => 'Order History', 'desc' => 'Profile order history tab'],
            'profile_tab_security' => ['group' => 'profile', 'km' => 'សុវត្ថិភាព & ពាក្យសម្ងាត់', 'en' => 'Security', 'desc' => 'Profile security tab'],
            'profile_address_label' => ['group' => 'profile', 'km' => 'អាសយដ្ឋានដឹកជញ្ជូនលំនាំដើម', 'en' => 'Default Delivery Address', 'desc' => 'Profile default address label'],
            'profile_address_ph' => ['group' => 'profile', 'km' => 'ផ្ទះលេខ, ផ្លូវ, សង្កាត់, ខណ្ឌ, រាជធានី/ខេត្ត...', 'en' => 'House, Street, Sangkat, Khan, City/Province...', 'desc' => 'Profile default address placeholder'],
            'profile_btn_save' => ['group' => 'profile', 'km' => 'រក្សាទុកព័ត៌មាន', 'en' => 'Save Profile', 'desc' => 'Profile save button'],
            'profile_no_orders' => ['group' => 'profile', 'km' => 'លោកអ្នកមិនទាន់មានការបញ្ជាទិញនៅឡើយទេ', 'en' => 'You have not placed any orders yet.', 'desc' => 'Profile empty orders message'],
            'profile_order_ref' => ['group' => 'profile', 'km' => 'លេខកូដបញ្ជាទិញ', 'en' => 'Order Reference', 'desc' => 'Profile order ref column'],
            'profile_order_date' => ['group' => 'profile', 'km' => 'កាលបរិច្ឆេទ', 'en' => 'Date', 'desc' => 'Profile order date column'],
            'profile_order_total' => ['group' => 'profile', 'km' => 'ទឹកប្រាក់សរុប', 'en' => 'Total Amount', 'desc' => 'Profile order total column'],
            'profile_order_status' => ['group' => 'profile', 'km' => 'ស្ថានភាព', 'en' => 'Status', 'desc' => 'Profile order status column'],
            'profile_receipt_view' => ['group' => 'profile', 'km' => 'មើលវិក្កយបត្រ', 'en' => 'View Receipt', 'desc' => 'Profile view receipt link'],
            'profile_btn_shop_now' => ['group' => 'profile', 'km' => 'ទៅកាន់កាតាឡុកទំនិញ', 'en' => 'Start Shopping', 'desc' => 'Profile empty orders shop now button'],
            'profile_change_pass' => ['group' => 'profile', 'km' => 'ផ្លាស់ប្តូរពាក្យសម្ងាត់', 'en' => 'Change Password', 'desc' => 'Profile change password title'],
            'profile_old_pass' => ['group' => 'profile', 'km' => 'ពាក្យសម្ងាត់បច្ចុប្បន្ន', 'en' => 'Current Password', 'desc' => 'Profile old password label'],
            'profile_new_pass' => ['group' => 'profile', 'km' => 'ពាក្យសម្ងាត់ថ្មី', 'en' => 'New Password', 'desc' => 'Profile new password label'],
        ];
    }
}

if (!function_exists('seedDefaultTranslations')) {
    function seedDefaultTranslations(mysqli $conn): void
    {
        $map = getDefaultTranslationsMap();
        $stmt = $conn->prepare("INSERT INTO `store_translations` (`trans_key`, `group_name`, `label_km`, `label_en`, `description`) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `group_name` = VALUES(`group_name`), `label_km` = VALUES(`label_km`), `label_en` = VALUES(`label_en`), `description` = VALUES(`description`)");
        if ($stmt) {
            foreach ($map as $key => $item) {
                $stmt->bind_param('sssss', $key, $item['group'], $item['km'], $item['en'], $item['desc']);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}

if (!function_exists('getStoreLanguage')) {
    function getStoreLanguage(): string
    {
        // 1. URL query parameter ?lang=
        if (isset($_GET['lang'])) {
            $reqLang = strtolower(trim((string)$_GET['lang']));
            if (in_array($reqLang, ['km', 'en'], true)) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['store_lang'] = $reqLang;
                }
                if (!headers_sent()) {
                    @setcookie('store_lang', $reqLang, time() + (86400 * 30), '/');
                }
                return $reqLang;
            }
        }

        // 2. Session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['store_lang'])) {
            $sessLang = strtolower((string)$_SESSION['store_lang']);
            if (in_array($sessLang, ['km', 'en'], true)) {
                return $sessLang;
            }
        }

        // 3. Cookie
        if (isset($_COOKIE['store_lang'])) {
            $cookieLang = strtolower((string)$_COOKIE['store_lang']);
            if (in_array($cookieLang, ['km', 'en'], true)) {
                return $cookieLang;
            }
        }

        // Default: Khmer
        return 'km';
    }
}

if (!function_exists('loadTranslations')) {
    function loadTranslations(mysqli $conn): array
    {
        static $cachedTranslations = null;
        if ($cachedTranslations !== null) {
            return $cachedTranslations;
        }

        $cachedTranslations = [];
        ensureTranslationsSchema($conn);

        $res = $conn->query("SELECT `trans_key`, `group_name`, `label_km`, `label_en` FROM `store_translations`");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cachedTranslations[$row['trans_key']] = [
                    'group' => $row['group_name'],
                    'km' => $row['label_km'],
                    'en' => $row['label_en']
                ];
            }
        }

        // Merge with defaults in case table was missing keys
        $defaults = getDefaultTranslationsMap();
        foreach ($defaults as $k => $d) {
            if (!isset($cachedTranslations[$k])) {
                $cachedTranslations[$k] = [
                    'group' => $d['group'],
                    'km' => $d['km'],
                    'en' => $d['en']
                ];
            }
        }

        return $cachedTranslations;
    }
}

if (!function_exists('t')) {
    function t(string $key, ?string $fallbackKm = null, ?string $fallbackEn = null): string
    {
        global $conn, $storeTranslationsMap, $storeLang;
        
        $lang = $storeLang ?? getStoreLanguage();
        
        if (!isset($storeTranslationsMap) && isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
            $storeTranslationsMap = loadTranslations($conn);
        }

        $resultText = null;
        if (isset($storeTranslationsMap[$key])) {
            $val = $lang === 'en' ? $storeTranslationsMap[$key]['en'] : $storeTranslationsMap[$key]['km'];
            if (!empty($val)) {
                $resultText = $val;
            }
        }

        // Fallback
        if ($resultText === null) {
            if ($lang === 'en' && $fallbackEn !== null) {
                $resultText = $fallbackEn;
            } elseif ($fallbackKm !== null) {
                $resultText = $fallbackKm;
            } else {
                $defaults = getDefaultTranslationsMap();
                if (isset($defaults[$key])) {
                    $resultText = $lang === 'en' ? $defaults[$key]['en'] : $defaults[$key]['km'];
                } else {
                    $resultText = $key;
                }
            }
        }

        // Dynamically replace store name placeholders and legacy default store name
        static $resolvedStoreName = null;
        if ($resolvedStoreName === null) {
            if (function_exists('getStoreSettings')) {
                $settings = getStoreSettings($conn ?? null);
                $resolvedStoreName = !empty($settings['store_name']) ? (string)$settings['store_name'] : 'SN STORE';
            } else {
                $resolvedStoreName = 'SN STORE';
            }
        }

        if ($resolvedStoreName !== '' && $resolvedStoreName !== '4K STORE') {
            $resultText = str_replace(['{store_name}', '4K STORE'], [$resolvedStoreName, $resolvedStoreName], $resultText);
        } else {
            $resultText = str_replace('{store_name}', $resolvedStoreName, $resultText);
        }

        return $resultText;
    }
}

if (!function_exists('renderLanguageSwitcher')) {
    function renderLanguageSwitcher(string $currentLang, string $extraClass = ''): string
    {
        // Build current URL query without ?lang=
        $params = $_GET;
        unset($params['lang']);
        
        $paramsKm = array_merge($params, ['lang' => 'km']);
        $urlKm = '?' . http_build_query($paramsKm);

        $paramsEn = array_merge($params, ['lang' => 'en']);
        $urlEn = '?' . http_build_query($paramsEn);

        $activeKm = $currentLang === 'km' ? ' is-active' : '';
        $activeEn = $currentLang === 'en' ? ' is-active' : '';

        return '
        <div class="lang-switcher-pill ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '" aria-label="Language Selector">
            <a href="' . htmlspecialchars($urlKm, ENT_QUOTES, 'UTF-8') . '" class="lang-opt-btn' . $activeKm . '" title="ភាសាខ្មែរ (Khmer)">
                <span class="lang-flag">🇰🇭</span> <span class="lang-text">ខ្មែរ</span>
            </a>
            <span class="lang-divider">|</span>
            <a href="' . htmlspecialchars($urlEn, ENT_QUOTES, 'UTF-8') . '" class="lang-opt-btn' . $activeEn . '" title="English">
                <span class="lang-flag">🇬🇧</span> <span class="lang-text">EN</span>
            </a>
        </div>';
    }
}

