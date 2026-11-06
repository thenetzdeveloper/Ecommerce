<?php
declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/i18n.php';
require_once __DIR__ . '/../api/user-auth.php';
$storeLang = getStoreLanguage();

$storeSettings = [];
$settingsResult = $conn->query('SELECT setting_name, setting_value FROM system_settings');
if ($settingsResult) {
    while ($setting = $settingsResult->fetch_assoc()) {
        $storeSettings[$setting['setting_name']] = $setting['setting_value'];
    }
}
$storeName = $storeSettings['store_name'] ?? 'SN STORE';
$storeCurrency = $storeSettings['currency_symbol'] ?? '$';
$nav_brand_logo = $storeSettings['store_logo_url'] ?? '';
$nav_store_name = $storeName;
$nav_contact_url = $storeSettings['store_contact_url'] ?? 'https://t.me/netstillloser';
$heroTitle = $storeSettings['home_hero_title'] ?? 'គុណភាពខ្ពស់ 100% ម៉ូដទាន់សម័យ';
$heroDescription = $storeSettings['home_hero_description'] ?? 'ទិញ-លក់សម្លៀកបំពាក់ និងម៉ូដទាន់សម័យ ប្រកបដោយសុវត្ថិភាព មានការធានាជូន និងគាំទ្រការទូទាត់តាម KHQR គ្រប់ធនាគារក្នុងប្រទេសកម្ពុជា។';
$heroBannerUrl = $storeSettings['home_banner_url'] ?? '';
$resolvedHeroBanner = '';
if (!empty($heroBannerUrl)) {
    if (preg_match('~^(https?://|data:)~i', $heroBannerUrl)) {
        $resolvedHeroBanner = $heroBannerUrl;
    } elseif (str_starts_with($heroBannerUrl, './') || str_starts_with($heroBannerUrl, '../')) {
        $resolvedHeroBanner = $heroBannerUrl;
    } else {
        $resolvedHeroBanner = '../' . ltrim($heroBannerUrl, '/');
    }
} else {
    $resolvedHeroBanner = './logo/banner.png';
}

date_default_timezone_set('Asia/Phnom_Penh');
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = "អរុណសួស្តីពេលព្រឹក";
    $greeting_en = "Good Morning";
    $greeting_icon = "☀️";
} elseif ($hour < 18) {
    $greeting = "ទិវាសួស្តីពេលរសៀល";
    $greeting_en = "Good Afternoon";
    $greeting_icon = "🌤️";
} else {
    $greeting = "រាត្រីសួស្តីពេលរាត្រី";
    $greeting_en = "Good Evening";
    $greeting_icon = "🌙";
}

if (empty($_SESSION['order_csrf_token'])) {
    $_SESSION['order_csrf_token'] = bin2hex(random_bytes(32));
}
$orderCsrfToken = $_SESSION['order_csrf_token'];

$page = $_GET['page'] ?? '';
if ($page === 'admin' || isset($_GET['admin'])) {
    header('Location: ../admin/index.php');
    exit;
}
$item = $_GET['item'] ?? '';
$is_checkout = ($page === 'checkout' || (is_string($item) && $item !== '' && ctype_digit($item)));

// Fallback category resolver
if (!function_exists('resolveStoreCategory')) {
    function resolveStoreCategory(string $name): string {
        $n = strtolower($name);
        if (strpos($n, 'boot') !== false || strpos($n, 'belt') !== false || strpos($n, 'bag') !== false || strpos($n, 'shoe') !== false) {
            return 'accessories';
        }
        if (strpos($n, 'women') !== false || strpos($n, 'dress') !== false || strpos($n, 'skirt') !== false || strpos($n, 'blouse') !== false || strpos($n, 'camisole') !== false) {
            return 'women';
        }
        if (preg_match('/\bmen\b/i', $name) || strpos($n, 'men ') !== false || strpos($n, 't-shirt') !== false || strpos($n, 'chino') !== false) {
            return 'men';
        }
        if (strpos($n, 'capcut') !== false || strpos($n, 'gpt') !== false || strpos($n, 'pic') !== false || 
            strpos($n, 'sportify') !== false || strpos($n, 'spotify') !== false || strpos($n, 'tiktok') !== false || 
            strpos($n, 'motion') !== false) {
            return 'digital';
        }
        return 'fashion';
    }
}

$catalogItems = [];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($storeLang, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page === 'about' ? ($storeLang === 'en' ? 'About Us • ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') : 'អំពីយើង • ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8')) : htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . ($storeLang === 'en' ? ' • Modern Fashion & Apparel' : ' • ហាងសម្លៀកបំពាក់ និងម៉ូដទាន់សម័យ'); ?></title>
    <!-- Icons & Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="./logo/logome.png" type="image/x-icon">
    <link rel="apple-touch-icon" href="./logo/logome.png">
    <!-- Storefront Stylesheets -->
    <link rel="stylesheet" href="./page.css?v=<?= filemtime(__DIR__ . '/page.css'); ?>">
    <link rel="stylesheet" href="./fashion-theme.css?v=<?= filemtime(__DIR__ . '/fashion-theme.css'); ?>">
    <link rel="stylesheet" href="../css/theme-mode.css?v=<?= filemtime(__DIR__ . '/../css/theme-mode.css'); ?>">
    <script src="../js/page-navigation.js?v=<?= filemtime(__DIR__ . '/../js/page-navigation.js'); ?>" defer></script>
    <style>
        /* Product Card Discount & Promotion Badges */
        .card-discount-tag {
            position: absolute;
            top: 18px;
            left: 18px;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 9999px;
            background: #e11d48;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 10px rgba(225, 29, 72, 0.35);
            animation: pulse-badge 2s infinite ease-in-out;
        }

        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .cyber-price-pill.has-discount {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            margin: auto 0 10px;
        }

        .price-row-main {
            display: flex;
            align-items: baseline;
            gap: 6px;
            flex-wrap: wrap;
        }

        .price-current {
            font-size: 18px;
            font-weight: 800;
            color: #e11d48;
        }

        .price-original {
            font-size: 13px;
            font-weight: 600;
            color: #9c9489;
            text-decoration: line-through;
        }

        .price-discount-tag {
            font-size: 10.5px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 4px;
            background: #fee2e2;
            color: #b91c1c;
            display: inline-block;
        }

        .price-unit-sub {
            font-size: 11px;
            color: #81766d;
            font-weight: 500;
        }

        [data-theme="dark"] .card-discount-tag {
            background: #f43f5e;
            box-shadow: 0 4px 12px rgba(244, 63, 94, 0.4);
        }

        [data-theme="dark"] .price-current {
            color: #fb7185;
        }

        [data-theme="dark"] .price-original {
            color: #8b95a5;
        }

        [data-theme="dark"] .price-discount-tag {
            background: rgba(225, 29, 72, 0.25);
            color: #fca5a5;
        }

        [data-theme="dark"] .price-unit-sub {
            color: #94a3b8;
        }

        /* Enhanced Product Card & Action Flow */
        .card-action-row {
            display: flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            margin-top: 10px;
        }

        .btn-card-quickview {
            width: 38px;
            height: 38px;
            border-radius: 4px;
            border: 1px solid #dcd3c8;
            background: #f7f4ef;
            color: #443e38;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .btn-card-quickview:hover {
            background: #203f3a;
            color: #fff;
            border-color: #203f3a;
        }

        .btn-card-addcart {
            flex: 1;
            min-height: 38px;
            padding: 8px 10px;
            border-radius: 4px;
            border: 1px solid #203f3a;
            background: transparent;
            color: #203f3a;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-card-addcart:hover {
            background: #203f3a;
            color: #ffffff;
        }

        .btn-card-buynow {
            flex: 1;
            text-decoration: none;
            min-height: 38px;
            padding: 8px 10px;
            border-radius: 4px;
            border: 1px solid #805f4b;
            background: #805f4b;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-card-buynow:hover {
            background: #5d4334;
            border-color: #5d4334;
        }

        /* Catalog Toolbar Controls */
        .catalog-toolbar-bar {
            max-width: 1180px;
            margin: 20px auto 30px;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
        }

        .catalog-count-pill {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.7);
            padding: 6px 14px;
            border-radius: 9999px;
            border: 1px solid #e8e1d8;
        }

        .catalog-sort-select {
            padding: 8px 14px;
            border-radius: 9999px;
            border: 1px solid #e8e1d8;
            background: #ffffff;
            font: inherit;
            font-size: 13px;
            color: var(--text-white);
            outline: none;
            cursor: pointer;
        }

        /* Cart Badge in Navigation */
        .nav-cart-trigger {
            position: relative;
            cursor: pointer;
        }

        .nav-cart-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #b17b60;
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            min-width: 18px;
            height: 18px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }

        /* Slide-over Shopping Cart Drawer */
        .cart-drawer-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(20, 16, 12, 0.5);
            backdrop-filter: blur(4px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .cart-drawer-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .cart-drawer-panel {
            position: fixed;
            top: 0;
            right: 0;
            width: 100%;
            max-width: 440px;
            height: 100vh;
            background: #ffffff;
            box-shadow: -10px 0 35px rgba(0, 0, 0, 0.15);
            z-index: 100000;
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            color: #302923;
        }

        .cart-drawer-overlay.open .cart-drawer-panel {
            transform: translateX(0);
        }

        .cart-drawer-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e8e1d8;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-drawer-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #203f3a;
        }

        .cart-drawer-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
        }

        .cart-item-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f0eae1;
        }

        .cart-item-thumb {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #e8e1d8;
            background: #faf8f5;
            flex-shrink: 0;
        }

        .cart-item-info {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #302923;
            margin: 0 0 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-price {
            font-size: 13px;
            color: #805f4b;
            font-weight: 700;
        }

        .cart-qty-stepper {
            display: inline-flex;
            align-items: center;
            border: 1px solid #ded5cb;
            border-radius: 4px;
            overflow: hidden;
            background: #faf8f5;
            margin-top: 6px;
        }

        .cart-qty-stepper button {
            background: transparent;
            border: 0;
            padding: 3px 8px;
            cursor: pointer;
            font-weight: 700;
            color: #302923;
        }

        .cart-qty-stepper button:hover {
            background: #ede7de;
        }

        .cart-qty-val {
            padding: 0 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .cart-item-del {
            background: transparent;
            border: 0;
            color: #b87470;
            cursor: pointer;
            padding: 6px;
            font-size: 15px;
        }

        .cart-item-del:hover {
            color: #dc2626;
        }

        .cart-drawer-footer {
            padding: 20px 24px;
            border-top: 1px solid #e8e1d8;
            background: #faf8f5;
        }

        .cart-subtotal-line {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: 700;
            color: #302923;
            margin-bottom: 14px;
        }

        .btn-cart-checkout {
            width: 100%;
            padding: 14px;
            border-radius: 6px;
            background: #203f3a;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            border: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background 0.2s;
        }

        .btn-cart-checkout:hover {
            background: #2d564f;
        }

        /* Floating Cart Quick Access Pill */
        .floating-cart-pill {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999;
            background: #203f3a;
            color: #ffffff;
            border-radius: 9999px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.25);
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .floating-cart-pill:hover {
            transform: translateY(-2px);
            background: #2d564f;
        }

        .floating-cart-count {
            background: #b17b60;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 9999px;
        }

        /* Quick View Modal */
        .quickview-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 999999 !important;
            background: rgba(20, 16, 12, 0.65) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 20px !important;
            opacity: 0;
            visibility: hidden;
            overflow-y: auto !important;
            transition: opacity 0.25s ease, visibility 0.25s ease !important;
        }

        .quickview-overlay.open {
            opacity: 1 !important;
            visibility: visible !important;
        }

        .quickview-card {
            background: #ffffff !important;
            border-radius: 16px !important;
            border: 1px solid #e8e1d8 !important;
            max-width: 760px !important;
            width: 100% !important;
            max-height: calc(100vh - 40px) !important;
            overflow-y: auto !important;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.28) !important;
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            transform: scale(0.96);
            transition: transform 0.25s ease !important;
            position: relative !important;
            margin: auto !important;
            z-index: 1000000 !important;
        }

        .quickview-overlay.open .quickview-card {
            transform: scale(1) !important;
        }

        .quickview-image-pane {
            background: #f8f5f0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 28px !important;
            min-height: 280px !important;
        }

        .quickview-image-pane img {
            width: 100% !important;
            max-height: 360px !important;
            object-fit: cover !important;
            border-radius: 10px !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08) !important;
        }

        .quickview-info-pane {
            padding: 32px 28px !important;
            display: flex !important;
            flex-direction: column !important;
            color: #302923 !important;
        }

        .quickview-close-btn {
            position: absolute !important;
            top: 14px !important;
            right: 14px !important;
            width: 36px !important;
            height: 36px !important;
            border-radius: 50% !important;
            background: #f0ebe4 !important;
            border: 1px solid #ded5cb !important;
            font-size: 16px !important;
            color: #554d45 !important;
            cursor: pointer !important;
            display: grid !important;
            place-items: center !important;
            z-index: 20 !important;
            transition: all 0.2s ease !important;
        }

        .quickview-close-btn:hover {
            background: #203f3a !important;
            color: #ffffff !important;
            border-color: #203f3a !important;
        }

        @media (max-width: 680px) {
            .quickview-card {
                grid-template-columns: 1fr !important;
                max-height: 90vh !important;
                overflow-y: auto !important;
            }
            .quickview-image-pane {
                height: 240px !important;
                padding: 16px !important;
            }
        }

        /* Toast Popup Notification */
        .store-toast-box {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: #203f3a;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            z-index: 1000000;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .store-toast-box.show {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        @media (max-width: 680px) {
            .quickview-card {
                grid-template-columns: 1fr;
                max-height: 90vh;
                overflow-y: auto;
            }
            .quickview-image-pane {
                height: 240px;
            }
        }

        /* ============================================================= */
        /* LUXURY ABOUT US SECTION STYLES                               */
        /* ============================================================= */
        .about-cyber-layer {
            max-width: 1200px;
            margin: 36px auto 72px;
            padding: 0 clamp(16px, 4vw, 36px);
            position: relative;
            z-index: 10;
        }

        .about-hero {
            background: #ffffff;
            border: 1px solid #e8e1d8;
            border-radius: 16px;
            padding: clamp(36px, 6vw, 68px) clamp(20px, 4vw, 48px);
            text-align: center;
            box-shadow: 0 10px 35px rgba(48, 41, 35, 0.05);
            margin-bottom: 24px;
        }

        .about-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            background: #faf8f5;
            border: 1px solid #ded5cb;
            color: #805f4b;
            margin-bottom: 16px;
        }

        .about-hero h2 {
            font-family: Georgia, 'Kantumruy Pro', serif;
            font-size: clamp(26px, 4vw, 40px);
            font-weight: 700;
            color: #203f3a;
            line-height: 1.35;
            margin: 0 auto 16px;
            max-width: 840px;
        }

        .about-lead {
            font-size: clamp(14px, 1.8vw, 16px);
            color: #756d64;
            max-width: 820px;
            margin: 0 auto 28px;
            line-height: 1.85;
        }

        .about-hero-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 38px;
        }

        .about-hero-actions .cyber-glow-btn,
        .about-contact .cyber-glow-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 9px !important;
            padding: 13px 26px !important;
            border-radius: 4px !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            text-decoration: none !important;
            box-shadow: none !important;
            cursor: pointer !important;
            transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease !important;
        }

        .about-btn-primary {
            background: #203f3a !important;
            border: 1px solid #203f3a !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(32, 63, 58, 0.22) !important;
        }

        .about-btn-primary:hover {
            background: #2d564f !important;
            border-color: #2d564f !important;
            color: #ffffff !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(32, 63, 58, 0.32) !important;
        }

        .about-btn-primary i {
            color: #ffffff !important;
        }

        .about-btn-secondary {
            background: #faf8f5 !important;
            border: 1.5px solid #ded5cb !important;
            color: #332d28 !important;
            box-shadow: 0 2px 8px rgba(48, 41, 35, 0.04) !important;
        }

        .about-btn-secondary:hover {
            background: #805f4b !important;
            border-color: #805f4b !important;
            color: #ffffff !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 18px rgba(128, 95, 75, 0.24) !important;
        }

        .about-btn-secondary i {
            color: #805f4b !important;
            transition: color 0.15s ease;
        }

        .about-btn-secondary:hover i {
            color: #ffffff !important;
        }

        [data-theme="dark"] .about-btn-primary {
            background: #203f3a !important;
            border-color: #2d564f !important;
            color: #ffffff !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.45) !important;
        }

        [data-theme="dark"] .about-btn-primary:hover {
            background: #2d564f !important;
            border-color: #34d399 !important;
        }

        [data-theme="dark"] .about-btn-secondary {
            background: #1e1915 !important;
            border-color: #383028 !important;
            color: #f2eee9 !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important;
        }

        [data-theme="dark"] .about-btn-secondary i {
            color: #d8c3b4 !important;
        }

        [data-theme="dark"] .about-btn-secondary:hover {
            background: #805f4b !important;
            border-color: #805f4b !important;
            color: #ffffff !important;
        }

        .about-facts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            max-width: 840px;
            margin: 0 auto;
        }

        .about-fact {
            padding: 20px 16px;
            border-radius: 12px;
            background: #faf8f5;
            border: 1px solid #e8e1d8;
            text-align: center;
        }

        .about-fact strong {
            display: block;
            font-size: 24px;
            font-weight: 800;
            color: #203f3a;
            margin-bottom: 4px;
            font-family: 'Space Grotesk', sans-serif;
        }

        .about-fact span {
            font-size: 13px;
            color: #756d64;
        }

        .about-section {
            margin-top: 24px;
            padding: clamp(28px, 5vw, 48px);
            background: #ffffff;
            border: 1px solid #e8e1d8;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(48, 41, 35, 0.04);
        }

        .about-section-heading {
            text-align: center;
            max-width: 720px;
            margin: 0 auto 32px;
        }

        .about-section-heading span {
            text-transform: uppercase;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .08em;
            color: #805f4b;
            display: block;
            margin-bottom: 8px;
        }

        .about-section-heading h3 {
            font-family: Georgia, 'Kantumruy Pro', serif;
            font-size: clamp(22px, 3vw, 30px);
            font-weight: 700;
            color: #203f3a;
            margin: 0 0 8px;
        }

        .about-section-heading p {
            font-size: 14px;
            color: #756d64;
            line-height: 1.7;
            margin: 0;
        }

        .about-story-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .about-values-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .about-steps-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            counter-reset: about-step;
        }

        .about-info-card,
        .about-value-card,
        .about-step-card {
            padding: 24px;
            border-radius: 12px;
            background: #faf8f5;
            border: 1px solid #e8e1d8;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .about-info-card:hover,
        .about-value-card:hover,
        .about-step-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(48, 41, 35, 0.05);
        }

        .about-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #f4efe9;
            color: #805f4b;
            display: grid;
            place-items: center;
            font-size: 20px;
            margin-bottom: 14px;
        }

        .about-info-card h4,
        .about-value-card h4,
        .about-step-card h4 {
            font-size: 16px;
            font-weight: 700;
            color: #203f3a;
            margin: 0 0 10px;
        }

        .about-info-card p,
        .about-value-card p,
        .about-step-card p {
            font-size: 13.5px;
            color: #756d64;
            line-height: 1.8;
            margin: 0;
        }

        .about-step-card {
            position: relative;
        }

        .about-step-card::before {
            counter-increment: about-step;
            content: "0" counter(about-step);
            font-size: 18px;
            font-weight: 800;
            color: #805f4b;
            font-family: 'Space Grotesk', sans-serif;
            display: block;
            margin-bottom: 10px;
        }

        .about-contact {
            margin-top: 24px;
            padding: clamp(32px, 5vw, 56px);
            background: linear-gradient(135deg, #f7f4ef 0%, #efeae2 100%);
            border: 1px solid #ded5cb;
            border-radius: 16px;
            text-align: center;
        }

        @media (max-width: 860px) {
            .about-story-grid,
            .about-values-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .about-steps-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .about-facts {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .about-story-grid,
            .about-values-grid,
            .about-steps-grid,
            .about-facts {
                grid-template-columns: 1fr;
            }
        }

        /* Dark Mode overrides */
        [data-theme="dark"] .about-hero,
        [data-theme="dark"] .about-section {
            background: #171d28 !important;
            border-color: #343c4a !important;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4) !important;
        }
        [data-theme="dark"] .about-eyebrow {
            background: #10151e !important;
            border-color: #343c4a !important;
            color: #c29b88 !important;
        }
        [data-theme="dark"] .about-hero h2,
        [data-theme="dark"] .about-section-heading h3,
        [data-theme="dark"] .about-info-card h4,
        [data-theme="dark"] .about-value-card h4,
        [data-theme="dark"] .about-step-card h4,
        [data-theme="dark"] .about-fact strong {
            color: #edf1f7 !important;
        }
        [data-theme="dark"] .about-lead,
        [data-theme="dark"] .about-section-heading p,
        [data-theme="dark"] .about-info-card p,
        [data-theme="dark"] .about-value-card p,
        [data-theme="dark"] .about-step-card p,
        [data-theme="dark"] .about-fact span {
            color: #b0bac8 !important;
        }
        [data-theme="dark"] .about-fact,
        [data-theme="dark"] .about-info-card,
        [data-theme="dark"] .about-value-card,
        [data-theme="dark"] .about-step-card {
            background: #10151e !important;
            border-color: #343c4a !important;
        }
        [data-theme="dark"] .about-icon {
            background: #1f2735 !important;
            color: #c29b88 !important;
        }
        [data-theme="dark"] .about-contact {
            background: #10151e !important;
            border-color: #343c4a !important;
        }
        [data-theme="dark"] .about-contact h3 {
            color: #edf1f7 !important;
        }
        [data-theme="dark"] .quickview-card {
            background: #171d28 !important;
            border-color: #343c4a !important;
            color: #edf1f7 !important;
        }
        [data-theme="dark"] .quickview-image-pane {
            background: #10151e !important;
        }
        [data-theme="dark"] .quickview-info-pane {
            color: #edf1f7 !important;
        }
        [data-theme="dark"] #qvTitle {
            color: #edf1f7 !important;
        }
        [data-theme="dark"] #qvDescription {
            color: #b0bac8 !important;
        }
        [data-theme="dark"] .quickview-close-btn {
            background: #1f2735 !important;
            border-color: #3a4352 !important;
            color: #b0bac8 !important;
        }
        [data-theme="dark"] .quickview-close-btn:hover {
            background: #203f3a !important;
            color: #fff !important;
        }
        [data-theme="dark"] .cart-drawer-panel {
            background: #171d28 !important;
            color: #edf1f7 !important;
        }
        [data-theme="dark"] .cart-drawer-header {
            border-bottom-color: #343c4a !important;
        }
        [data-theme="dark"] .cart-drawer-header h3 {
            color: #edf1f7 !important;
        }
        [data-theme="dark"] .cart-item-row {
            border-bottom-color: #273142 !important;
        }
        [data-theme="dark"] .cart-item-name {
            color: #edf1f7 !important;
        }
        [data-theme="dark"] .cart-item-thumb {
            background: #10151e !important;
            border-color: #343c4a !important;
        }
        [data-theme="dark"] .cart-drawer-footer {
            background: #10151e !important;
            border-top-color: #343c4a !important;
        }
        [data-theme="dark"] .cart-subtotal-line {
            color: #edf1f7 !important;
        }
    </style>
</head>

<body class="fashion-storefront">
    <!-- Live 3D DNA Helix Canvas Background -->
    <canvas id="dna-canvas"></canvas>
    <div class="canvas-overlay-vignette"></div>
    <div class="cyber-grid-lines"></div>

    <!-- Main Content Layer -->
    <div class="app-content-layer">
        <?php if ($page === 'about'): ?>
            <?php
            $nav_active = 'about';
            $nav_home_url = 'index.php';
            $nav_about_url = 'index.php?page=about';
            $nav_support_url = '../customer-service/index.php';
            $nav_payments_url = 'checkout.php';
            require __DIR__ . '/navbar.php';
            ?>

            <div class="about-cyber-layer">
                <main>
                    <!-- About Hero -->
                    <section class="about-hero">
                        <span class="about-eyebrow"><i class="fas fa-sparkles"></i> <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> • <?= t('about_hero_eyebrow', 'ស្តង់ដារ និងគុណភាពខ្ពស់', 'Premium Standards & Quality'); ?></span>
                        <h2><?= t('about_hero_title', 'ស្វែងយល់អំពី ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . ' និងការប្តេជ្ញាចិត្តរបស់យើង', 'Discover ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . ' and Our Commitment'); ?></h2>
                        <p class="about-lead">
                            <?= t('about_hero_lead', htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . ' គឺជាហាងសម្លៀកបំពាក់ និងម៉ូដទាន់សម័យ ដែលនាំមកជូនលោកអ្នកនូវផលិតផលប្រកបដោយគុណភាពខ្ពស់ ភាពទាន់សម័យ និងទំនុកចិត្ត 100%។ យើងផ្តល់ជូននូវបទពិសោធន៍ទិញទំនិញអនឡាញដ៏ងាយស្រួល សុវត្ថិភាព និងរហ័សទាន់ចិត្ត។', htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . ' is a modern fashion and apparel boutique bringing you premium quality, contemporary trends, and 100% customer trust. We deliver a seamless, secure, and fast online shopping experience.'); ?>
                        </p>
                        <div class="about-hero-actions">
                            <a href="index.php" class="cyber-glow-btn about-btn-primary">
                                <i class="fas fa-bag-shopping"></i> <?= t('about_btn_browse', 'ស្វែងរកផលិតផល', 'Shop Collection Now'); ?>
                            </a>
                            <a href="../customer-service/index.php" class="cyber-glow-btn about-btn-secondary">
                                <i class="fas fa-headset"></i> <?= t('about_btn_support', 'ទាក់ទងផ្នែកបម្រើអតិថិជន', 'Contact Customer Support'); ?>
                            </a>
                        </div>
                        <div class="about-facts">
                            <div class="about-fact">
                                <strong>100%</strong>
                                <span><?= t('about_val1_desc_short', 'គុណភាពធានា និងផលិតផលពិត', 'Guaranteed Quality & Genuine Products'); ?></span>
                            </div>
                            <div class="about-fact">
                                <strong>24/7</strong>
                                <span><?= t('about_service_short', 'សេវាកម្មរហ័ស និងការឆ្លើយតប', 'Fast Service & Responsive Care'); ?></span>
                            </div>
                            <div class="about-fact">
                                <strong>KHQR</strong>
                                <span><?= t('about_payment_short', 'ទូទាត់ប្រាក់ងាយស្រួល និងសុវត្ថិភាព', 'Easy & Secure KHQR Payment'); ?></span>
                            </div>
                        </div>
                    </section>

                    <!-- Story & Mission Section -->
                    <section class="about-section">
                        <div class="about-section-heading">
                            <span><?= t('about_story_heading_kicker', 'រឿងរ៉ាវ & បេសកកម្ម', 'Our Story & Mission'); ?></span>
                            <h3><?= t('about_story_heading_title', 'រឿងរ៉ាវ និងបេសកកម្មរបស់យើង', 'Our Story & Mission'); ?></h3>
                            <p><?= t('about_story_heading_desc', 'ស្វែងយល់ពីប្រវត្តិ និងគោលបំណងចម្បងក្នុងការបង្កើតឡើងនូវ ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'), 'Learn about the founding history and core mission behind ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8')); ?></p>
                        </div>
                        <div class="about-story-grid">
                            <article class="about-info-card">
                                <div class="about-icon"><i class="fas fa-store"></i></div>
                                <h4><?= t('about_who_title', 'យើងជានរណា?', 'Who We Are'); ?></h4>
                                <p><?= t('about_who_desc', htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . ' បង្កើតឡើងដោយក្តីស្រឡាញ់លើម៉ូដសម្លៀកបំពាក់ និងបំណងប្រាថ្នាក្នុងការផ្តល់ជូនអតិថិជននូវជម្រើសសម្លៀកបំពាក់ស្អាតៗ គុណភាពល្អ សម្រាប់បុរស នារី និងគ្រឿងតុបតែងខ្លួន ដែលស័ក្តិសមនឹងគ្រប់កាលៈទេសៈ。', htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') . ' was founded out of a passion for fashion and a dedication to providing curated apparel and stylish accessories for men, women, and everyday occasions.'); ?></p>
                            </article>
                            <article class="about-info-card">
                                <div class="about-icon"><i class="fas fa-bullseye"></i></div>
                                <h4><?= t('about_mission_title', 'បេសកកម្មរបស់យើង', 'Our Mission'); ?></h4>
                                <p><?= t('about_mission_desc', 'បេសកកម្មចម្បងរបស់យើង គឺការផ្តល់ជូននូវបទពិសោធន៍ទិញទំនិញតាមប្រព័ន្ធឌីជីថលដ៏ល្អឥតខ្ចោះ តម្លៃសមរម្យ តម្លាភាព និងសេវាកម្មដឹកជញ្ជូនរហ័សដល់ដៃអតិថិជនប្រកបដោយការពេញចិត្តខ្ពស់បំផុត。', 'Our core mission is to provide an exceptional online shopping journey with honest pricing, absolute transparency, and fast, reliable nationwide delivery.'); ?></p>
                            </article>
                        </div>
                    </section>

                    <!-- Core Values -->
                    <section class="about-section">
                        <div class="about-section-heading">
                            <span><?= t('about_values_heading_kicker', 'តម្លៃស្នូលរបស់យើង', 'Our Core Values'); ?></span>
                            <h3><?= t('about_values_heading_title', 'តម្លៃស្នូលដែលយើងប្រកាន់ខ្ជាប់', 'Core Values We Live By'); ?></h3>
                            <p><?= t('about_values_heading_desc', 'គោលការណ៍ទាំងនេះណែនាំការងាររបស់យើង និងរបៀបដែលយើងបម្រើអតិថិជនគ្រប់រូប', 'These principles guide our operations and define how we serve every customer'); ?></p>
                        </div>
                        <div class="about-values-grid">
                            <article class="about-value-card">
                                <div class="about-icon"><i class="fas fa-award"></i></div>
                                <h4><?= t('about_val1_title', 'គុណភាពជាចម្បង', 'Quality First'); ?></h4>
                                <p><?= t('about_val1_desc', 'រាល់ផលិតផលទាំងអស់ត្រូវបានជ្រើសរើស និងត្រួតពិនិត្យយ៉ាងហ្មត់ចត់មុនពេលបញ្ជូនទៅកាន់ដៃអតិថិជន。', 'Every product is handpicked and thoroughly inspected before being dispatched to our customers.'); ?></p>
                            </article>
                            <article class="about-value-card">
                                <div class="about-icon"><i class="fas fa-handshake"></i></div>
                                <h4><?= t('about_val2_title', 'ទំនុកចិត្ត និងតម្លាភាព', 'Trust & Transparency'); ?></h4>
                                <p><?= t('about_val2_desc', 'ព័ត៌មានតម្លៃ រូបភាព និងទំហំស្តុកពិតប្រាកដ 100% គ្មានការបន្លំ ឬលាក់បាំងព័ត៌មានឡើយ。', '100% accurate pricing, genuine photography, and real-time stock levels with zero hidden fees.'); ?></p>
                            </article>
                            <article class="about-value-card">
                                <div class="about-icon"><i class="fas fa-heart"></i></div>
                                <h4><?= t('about_val3_title', 'ការទទួលខុសត្រូវខ្ពស់', 'Accountability'); ?></h4>
                                <p><?= t('about_val3_desc', 'យើងយកចិត្តទុកដាក់ខ្ពស់ចំពោះការពេញចិត្តរបស់អតិថិជន និងត្រៀមខ្លួនជួយដោះស្រាយរាល់ចម្ងល់。', 'We are genuinely committed to customer satisfaction and are always ready to assist with any questions.'); ?></p>
                            </article>
                        </div>
                    </section>

                    <!-- 4 Steps Shopping Guide -->
                    <section class="about-section">
                        <div class="about-section-heading">
                            <span><?= t('about_steps_heading_kicker', 'របៀបទិញទំនិញ', 'How To Shop'); ?></span>
                            <h3><?= t('about_steps_heading_title', 'របៀបទិញទំនិញងាយៗ ៤ ជំហាន', '4 Easy Steps to Shop'); ?></h3>
                            <p><?= t('about_steps_heading_desc', 'បទពិសោធន៍ទិញទំនិញដ៏រហ័ស និងសុវត្ថិភាពបំផុតជាមួយ ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'), 'A fast and secure shopping experience with ' . htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8')); ?></p>
                        </div>
                        <div class="about-steps-grid">
                            <article class="about-step-card">
                                <h4><?= t('about_step1_title', 'ជ្រើសរើសផលិតផល', 'Choose Products'); ?></h4>
                                <p><?= t('about_step1_desc', 'រើសមើលសម្លៀកបំពាក់ និងម៉ូដដែលអ្នកស្រឡាញ់ក្នុង Storefront', 'Explore and select the fashion items you love in the catalog'); ?></p>
                            </article>
                            <article class="about-step-card">
                                <h4><?= t('about_step2_title', 'ពិនិត្យ និងបញ្ជាទិញ', 'Review & Order'); ?></h4>
                                <p><?= t('about_step2_desc', 'ពិនិត្យព័ត៌មានលម្អិត បញ្ចូលក្នុងកន្ត្រក ឬចុចទិញភ្លាមៗ', 'Inspect details, add items to cart, or click Buy Now directly'); ?></p>
                            </article>
                            <article class="about-step-card">
                                <h4><?= t('about_step3_title', 'ស្កេនទូទាត់ KHQR', 'Scan Bakong KHQR'); ?></h4>
                                <p><?= t('about_step3_desc', 'ស្កេនទូទាត់ប្រាក់តាមប្រព័ន្ធ Bakong KHQR គ្រប់ធនាគារ', 'Scan and pay seamlessly via Bakong KHQR with any mobile banking app'); ?></p>
                            </article>
                            <article class="about-step-card">
                                <h4><?= t('about_step4_title', 'ផ្ញើវិក្កយបត្រ & រង់ចាំ', 'Upload Receipt & Relax'); ?></h4>
                                <p><?= t('about_step4_desc', 'ភ្ជាប់រូបវិក្កយបត្រ រួចរង់ចាំទទួលទំនិញដឹកជញ្ជូនដល់ផ្ទះ', 'Attach payment proof and await fast doorstep delivery'); ?></p>
                            </article>
                        </div>
                    </section>

                    <!-- Contact & Support CTA -->
                    <section class="about-contact">
                        <span class="about-eyebrow"><i class="fas fa-headset"></i> <?= t('about_help_eyebrow', 'យើងនៅទីនេះដើម្បីជួយអ្នក', "WE'RE HERE TO HELP"); ?></span>
                        <h3 style="margin-top: 14px; font-size: clamp(22px, 3vw, 30px); color: #203f3a; font-family: Georgia, 'Kantumruy Pro', serif;"><?= t('about_cta_title', 'មានសំណួរអំពីផលិតផល ឬការបញ្ជាទិញ?', 'Have questions about products or orders?'); ?></h3>
                        <p style="color: #756d64; max-width: 650px; margin: 10px auto 20px; font-size: 14.5px; line-height: 1.8;">
                            <?= t('about_cta_desc', 'ទាក់ទងក្រុមការងាររបស់យើងតាម Telegram ឬទំព័រជំនួយអតិថិជន សម្រាប់សួរព័ត៌មានបន្ថែមអំពីផលិតផល ការទូទាត់ ឬជំនួយក្រោយការបញ្ជាទិញ។', 'Reach out to our friendly support team via Telegram or our Support Page for immediate assistance.'); ?>
                        </p>
                        <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                            <a href="<?= htmlspecialchars($nav_contact_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="cyber-glow-btn about-btn-primary">
                                <i class="fab fa-telegram-plane"></i> <?= t('about_cta_telegram', 'ទាក់ទងតាម Telegram', 'Contact via Telegram'); ?>
                            </a>
                            <a href="../customer-service/index.php" class="cyber-glow-btn about-btn-secondary">
                                <i class="fas fa-envelope"></i> <?= t('about_cta_form', 'ផ្ញើសារក្នុងទំព័រជំនួយ', 'Send Message via Support'); ?>
                            </a>
                        </div>
                    </section>
                </main>
                <footer class="about-page-footer" style="text-align: center; margin-top: 36px; padding: 20px 0; color: var(--text-dim); font-size: 13px;">
                    &copy; <?= date('Y'); ?> <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> • <?= t('footer_copyright', 'រក្សាសិទ្ធិគ្រប់យ៉ាង', 'All rights reserved.'); ?>
                </footer>
            </div>
        <?php else: ?>
            <?php
            $nav_active = 'home';
            $nav_home_url = 'index.php';
            $nav_about_url = 'index.php?page=about';
            $nav_support_url = '../customer-service/index.php';
            $nav_payments_url = 'checkout.php';
            require __DIR__ . '/navbar.php';
            ?>

        <!-- Hero Section -->
        <main class="hero-deck">
            <div class="cyber-hud-bar">
                <div class="hud-item">
                    <span style="font-size: 16px;"><?= $greeting_icon; ?></span>
                    <span style="color: #fff; font-weight: 700;"><?= $storeLang === 'en' ? $greeting_en : $greeting; ?>!</span>
                </div>
                <div class="hud-item">
                    <i class="far fa-clock" style="color: var(--cyber-cyan);"></i>
                    <span id="liveClockDisplay" style="font-family: 'Space Grotesk', monospace; color: #fff;">--:--:--</span>
                    <span style="color: var(--text-dim); font-size: 11px;">Phnom Penh</span>
                </div>
                <div class="hud-item">
                    <div class="dna-signal-pulse"></div>
                    <span style="color: var(--cyber-emerald); font-weight: 700;">Online 24/7</span>
                </div>
            </div>

            <!-- Hero Centerpiece Card -->
            <div class="hero-centerpiece">
                <div class="hero-text-side">
                    <div class="hero-tag-pill">
                        <i class="fas fa-sparkles"></i> <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> • Curated Collection
                    </div>
                    <h2><?= htmlspecialchars(t('home_hero_title', $heroTitle, '100% Premium Quality & Modern Fashion'), ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p><?= htmlspecialchars(t('home_hero_desc', $heroDescription, 'Shop curated fashion & modern apparel with confidence. 100% genuine quality, fast nationwide delivery, and seamless Bakong KHQR payments.'), ENT_QUOTES, 'UTF-8'); ?></p>
                    <a class="fashion-shop-cta" href="#productsCatalogMatrix">
                        <span><?= t('hero_cta', 'ទិញទំនិញឥឡូវនេះ (Shop Collection)', 'Shop Collection Now'); ?></span>
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    </a>
                    <div class="hero-stats-row">
                        <div class="stat-chip">
                            <div class="stat-num">100<span>%</span></div>
                            <div class="stat-label"><?= t('stat_quality_desc', 'គុណភាពធានា', 'Quality Guaranteed'); ?></div>
                        </div>
                        <div class="stat-chip">
                            <div class="stat-num">Fast</div>
                            <div class="stat-label"><?= t('stat_shipping_desc', 'ដឹកជញ្ជូនរហ័ស', 'Fast Delivery'); ?></div>
                        </div>
                        <div class="stat-chip">
                            <div class="stat-num">KHQR</div>
                            <div class="stat-label"><?= t('stat_khqr_desc', 'ទូទាត់ងាយស្រួល', 'Easy Payment'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="hero-visual-side" aria-hidden="true">
                    <img class="hero-managed-banner" src="<?= htmlspecialchars($resolvedHeroBanner, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> Hero" onerror="this.src='./logo/banner.png'">
                    <span class="hero-brand-caption"><?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> · CURATED GOODS</span>
                    <span class="hero-brand-mark">4K</span>
                </div>
            </div>
        </main>

        <!-- Product Catalog Section -->
        <section class="catalog-header" id="productsCatalog">
            <div class="catalog-heading-copy">
                <span class="catalog-kicker"><i class="fas fa-sparkles"></i> <?= t('catalog_kicker', 'THE 4K EDIT', 'THE 4K EDIT'); ?></span>
                <h2><?= t('catalog_title', 'ស្វែងរកផលិតផលដែលអ្នកពេញចិត្ត', 'Discover Products You Love'); ?></h2>
                <p><?= t('catalog_subtitle', 'រើសមើលសម្លៀកបំពាក់ ម៉ូដទាន់សម័យ និងទំនិញគុណភាពខ្ពស់', 'Browse stylish fashion, modern apparel and premium goods'); ?></p>
            </div>

            <!-- Instant Live Search Bar -->
            <div class="cyber-search-box">
                <i class="fas fa-search search-beam-icon"></i>
                <input type="text" id="liveSearchInput" placeholder="<?= htmlspecialchars(t('search_placeholder', 'ស្វែងរកតាមឈ្មោះផលិតផល (Search by product name, e.g. T-Shirt, Jeans, Dress, Capcut...)', 'Search by product name (e.g. T-Shirt, Jeans, Dress...)'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                <button type="button" id="clearStorefrontSearch" class="search-clear-btn" aria-label="សម្អាតស្វែងរក"><i class="fas fa-times"></i></button>
            </div>

            <!-- Category Filter Pills with Live Product Counters -->
            <div class="category-tab-strip">
                <button type="button" class="cyber-category-pill active" data-category="all"><i class="fas fa-th-large"></i> <?= t('cat_all', 'ទាំងអស់ (All)', 'All Items'); ?> <span class="cat-pill-count" id="countCatAll">0</span></button>
                <button type="button" class="cyber-category-pill" data-category="men"><i class="fas fa-mars"></i> <?= t('cat_men', 'បុរស (Men)', 'Men'); ?> <span class="cat-pill-count" id="countCatMen">0</span></button>
                <button type="button" class="cyber-category-pill" data-category="women"><i class="fas fa-venus"></i> <?= t('cat_women', 'នារី (Women)', 'Women'); ?> <span class="cat-pill-count" id="countCatWomen">0</span></button>
                <button type="button" class="cyber-category-pill" data-category="accessories"><i class="fas fa-shoe-prints"></i> <?= t('cat_accessories', 'គ្រឿងតុបតែង (Accessories)', 'Accessories'); ?> <span class="cat-pill-count" id="countCatAcc">0</span></button>
                <button type="button" class="cyber-category-pill" data-category="digital"><i class="fas fa-bolt"></i> <?= t('cat_digital', 'ឌីជីថល (Digital)', 'Digital'); ?> <span class="cat-pill-count" id="countCatDig">0</span></button>
            </div>
        </section>

        <!-- Sorting & Results Toolbar -->
        <div class="catalog-toolbar-bar">
            <div class="catalog-count-pill" id="catalogShowingCountText">
                <?= t('showing_products', 'បង្ហាញ', 'Showing'); ?> <strong id="catalogShowingCountNum">0</strong> <?= t('products_unit', 'ផលិតផល', 'Products'); ?>
            </div>
            <div>
                <select id="catalogSortSelect" class="catalog-sort-select" aria-label="Sort products">
                    <option value="featured"><?= t('sort_featured', 'ពេញនិយម (Featured)', 'Featured'); ?></option>
                    <option value="discount-desc"><?= t('sort_discount_desc', 'បញ្ចុះតម្លៃច្រើនជាងគេ (SALE)', 'Biggest Discount (SALE)'); ?></option>
                    <option value="price-asc"><?= t('sort_price_asc', 'តម្លៃ៖ ពីទាបទៅខ្ពស់', 'Price: Low to High'); ?></option>
                    <option value="price-desc"><?= t('sort_price_desc', 'តម្លៃ៖ ពីខ្ពស់ទៅទាប', 'Price: High to Low'); ?></option>
                    <option value="name-asc"><?= $storeLang === "en" ? "Name: A to Z" : "ឈ្មោះ៖ A ដល់ Z"; ?></option>
                </select>
            </div>
        </div>

        <!-- Product Grid Matrix -->
        <div class="cyber-cards-matrix" id="productsCatalogMatrix">
            <?php
            $catalogItems = [];
            if (isset($conn) && !$conn->connect_error) {
                if (function_exists('ensureProductDiscountColumns')) {
                    ensureProductDiscountColumns($conn);
                }
                $db_result = $conn->query("SELECT id, name, category, description, price, original_price, discount_percent, cost_price, image, stock, low_stock_threshold FROM products WHERE stock > 0 ORDER BY id DESC");
                if ($db_result && $db_result->num_rows > 0) {
                    while ($p = $db_result->fetch_assoc()) {
                        $p_id = (int)$p['id'];
                        $p_name = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
                        $p_price = (float)$p['price'];
                        $p_orig = isset($p['original_price']) && is_numeric($p['original_price']) ? (float)$p['original_price'] : null;
                        $p_discount = (int)($p['discount_percent'] ?? 0);
                        $has_discount = false;
                        if ($p_orig !== null && $p_orig > $p_price) {
                            $has_discount = true;
                            if ($p_discount === 0) {
                                $p_discount = (int) round((($p_orig - $p_price) / $p_orig) * 100);
                            }
                        } elseif ($p_discount > 0) {
                            $has_discount = true;
                            if ($p_orig === null || $p_orig <= $p_price) {
                                $p_orig = round($p_price / (1 - ($p_discount / 100)), 2);
                            }
                        }

                        $p_stock = (int)$p['stock'];
                        $p_desc = htmlspecialchars($p['description'] ?? 'ផលិតផលមានគុណភាពខ្ពស់ និងមានការធានាជូន', ENT_QUOTES, 'UTF-8');
                        $p_cat = !empty($p['category']) ? $p['category'] : resolveStoreCategory($p['name']);
                        $p_img = !empty($p['image'])
                            ? (preg_match('~^https?://~i', $p['image']) ? $p['image'] : '../' . $p['image'])
                            : './logo/logome.png';

                        $catalogItems[] = [
                            'id' => $p_id,
                            'name' => $p['name'],
                            'category' => $p_cat,
                            'price' => $p_price,
                            'original_price' => $p_orig,
                            'discount_percent' => $p_discount,
                            'has_discount' => $has_discount,
                            'stock' => $p_stock,
                            'desc' => $p['description'] ?? '',
                            'img' => $p_img
                        ];
            ?>
                        <div class="cyber-card storefront-prod-card" 
                            data-id="<?= $p_id ?>" 
                            data-category="<?= strtolower($p_cat); ?>" 
                            data-name="<?= strtolower($p['name']); ?>"
                            data-price="<?= $p_price ?>"
                            data-original-price="<?= $p_orig !== null ? $p_orig : '' ?>"
                            data-discount="<?= $p_discount ?>"
                            data-stock="<?= $p_stock ?>"
                            data-desc="<?= $p_desc ?>"
                            data-img="<?= htmlspecialchars($p_img, ENT_QUOTES, 'UTF-8'); ?>">
                            
                            <?php if ($has_discount && $p_discount > 0): ?>
                                <span class="card-discount-tag"><i class="fas fa-bolt"></i> -<?= $p_discount ?>%</span>
                            <?php endif; ?>
                            <span class="card-top-tag"><i class="fas fa-check-circle"></i> <?= t('badge_in_stock', 'មានស្តុក', 'In Stock'); ?> (<?= $p_stock ?>)</span>
                            <div class="card-hologram-frame" data-quickview-id="<?= $p_id ?>" style="cursor: pointer;" title="<?= htmlspecialchars(t('btn_quickview', 'មើលព័ត៌មានលម្អិត', 'Quick View'), ENT_QUOTES, 'UTF-8'); ?>">
                                <img src="<?= htmlspecialchars($p_img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= $p_name; ?>" loading="lazy" onerror="this.src='./logo/logome.png'">
                            </div>
                            <h3 class="card-app-title" data-quickview-id="<?= $p_id ?>" style="cursor: pointer;" title="<?= htmlspecialchars(t('btn_quickview', 'មើលព័ត៌មានលម្អិត', 'Quick View'), ENT_QUOTES, 'UTF-8'); ?>"><?= $p_name; ?></h3>
                            <div class="card-feature-specs">
                                <span><i class="fas fa-shield-alt" style="color:var(--cyber-emerald);"></i> <?= t('badge_guarantee', 'ធានាគុណភាព 100%', '100% Quality Guaranteed'); ?></span>
                            </div>

                            <?php if ($has_discount && $p_orig !== null): ?>
                                <div class="cyber-price-pill has-discount">
                                    <div class="price-row-main">
                                        <span class="price-current"><?= htmlspecialchars($storeCurrency, ENT_QUOTES, 'UTF-8'); ?><?= number_format($p_price, 2); ?></span>
                                        <span class="price-original"><?= htmlspecialchars($storeCurrency, ENT_QUOTES, 'UTF-8'); ?><?= number_format($p_orig, 2); ?></span>
                                        <span class="price-discount-tag">-<?= $p_discount ?>%</span>
                                    </div>
                                    <span class="price-unit-sub"><?= t('price_per_unit', '/ មួយ', '/ item'); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="cyber-price-pill">
                                    <?= htmlspecialchars($storeCurrency, ENT_QUOTES, 'UTF-8'); ?><?= number_format($p_price, 2); ?> <span><?= t('price_per_unit', '/ មួយ', '/ item'); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Flow Buttons -->
                            <div class="card-action-row">
                                <button type="button" class="btn-card-quickview" data-quickview-id="<?= $p_id ?>" title="<?= htmlspecialchars(t('btn_quickview', 'មើលព័ត៌មានលម្អិត', 'Quick View'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn-card-addcart" data-add-cart-id="<?= $p_id ?>">
                                    <i class="fas fa-cart-plus"></i> <?= t('btn_add_cart', 'កន្ត្រក', 'Cart'); ?>
                                </button>
                                <a href="checkout.php?item=<?= $p_id ?>&qty=1" class="btn-card-buynow" data-buy-now-id="<?= $p_id ?>">
                                    <i class="fas fa-bolt"></i> <?= t('btn_buy_now', 'ទិញភ្លាម', 'Buy Now'); ?>
                                </a>
                            </div>
                        </div>
            <?php
                    }
                } else {
            ?>
                    <p class="catalog-empty-state"><?= t('catalog_no_products', 'មិនទាន់មានផលិតផលសម្រាប់លក់នៅឡើយទេ', 'No products available at the moment.'); ?></p>
            <?php
                }
            }
            ?>
            <!-- Empty state for search / filter -->
            <div id="catalogEmptyState" class="catalog-empty-notice" style="display: none; grid-column: 1 / -1; text-align: center; padding: 60px 20px;">
                <div style="width: 70px; height: 70px; border-radius: 50%; background: #f4efe9; color: #805f4b; display: grid; place-items: center; font-size: 28px; margin: 0 auto 16px;">
                    <i class="fas fa-search"></i>
                </div>
                <h3 style="font-size: 20px; font-weight: 700; color: #203f3a; margin-bottom: 8px;"><?= t('empty_search_title', 'រកមិនឃើញផលិតផលដែលត្រូវនឹងពាក្យស្វែងរក', 'No products found matching your search'); ?></h3>
                <p style="font-size: 14px; color: var(--text-muted); max-width: 440px; margin: 0 auto 18px;">
                    <?= t('empty_search_desc', 'សូមសាកល្បងស្វែងរកដោយប្រើពាក្យគន្លឹះផ្សេងទៀត ឬចុចប៊ូតុងខាងក្រោមដើម្បីមើលផលិតផលទាំងអស់ឡើងវិញ។', 'Try searching with different keywords or click the button below to view all products.'); ?>
                </p>
                <button type="button" id="btnResetCatalogFilter" class="btn-card-buynow" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; border-radius: 6px; cursor: pointer; margin: 0 auto;">
                    <i class="fas fa-rotate-right"></i> <?= t('reset_filter_btn', 'បង្ហាញផលិតផលទាំងអស់ (Reset Filter)', 'Reset Filters & Show All'); ?>
                </button>
            </div>
        </div>

        <!-- Capabilities Trust Grid -->
        <div class="cyber-trust-grid">
            <div class="cyber-trust-card">
                <div class="trust-glow-icon"><i class="fas fa-bolt"></i></div>
                <div class="trust-texts">
                    <h4><?= t('trust_shipping_title', 'ដឹកជញ្ជូនរហ័សទូទាំងប្រទេស', 'Fast Nationwide Delivery'); ?></h4>
                    <p><?= t('trust_shipping_desc', 'ដឹកជញ្ជូនរហ័សដល់ដៃ', 'Fast shipping to your doorstep'); ?></p>
                </div>
            </div>
            <div class="cyber-trust-card">
                <div class="trust-glow-icon" style="color: var(--cyber-emerald); border-color: var(--cyber-emerald);"><i class="fas fa-shield-halved"></i></div>
                <div class="trust-texts">
                    <h4><?= t('trust_quality_title', 'ធានាសុវត្ថិភាព 100%', '100% Verified Quality'); ?></h4>
                    <p><?= t('trust_quality_desc', 'គុណភាពពិតប្រាកដ', 'Authentic products guaranteed'); ?></p>
                </div>
            </div>
            <div class="cyber-trust-card">
                <div class="trust-glow-icon" style="color: var(--cyber-pink); border-color: var(--cyber-pink);"><i class="fas fa-qrcode"></i></div>
                <div class="trust-texts">
                    <h4><?= t('trust_payment_title', 'ទូទាត់រហ័ស KHQR', 'Instant KHQR Payment'); ?></h4>
                    <p><?= t('trust_payment_desc', 'Bakong & គ្រប់ធនាគារ', 'Bakong & All Cambodian Banks'); ?></p>
                </div>
            </div>
            <div class="cyber-trust-card">
                <div class="trust-glow-icon" style="color: var(--cyber-amber); border-color: var(--cyber-amber);"><i class="fas fa-headset"></i></div>
                <div class="trust-texts">
                    <h4><?= t('trust_support_title', 'ជំនួយអតិថិជន 24/7', 'Customer Support 24/7'); ?></h4>
                    <p><?= t('trust_support_desc', 'Telegram Support Desk', 'Telegram Support Desk'); ?></p>
                </div>
            </div>
        </div>

        <!-- Payments Section Footer -->
        <footer class="cyber-footer" id="payments">
            <h3>
                <i class="fas fa-credit-card" style="color: var(--cyber-cyan);"></i>
                <?= t('footer_payments_title', 'វិធីទូទាត់ប្រាក់ និងដៃគូធនាគារ', 'Payment Methods & Banking Partners'); ?>
            </h3>
            <p style="font-size: 13.5px; color: var(--text-muted); max-width: 600px;">
                <?= t('footer_payments_desc', 'ទទួលការទូទាត់យ៉ាងរហ័សតាមរយៈប្រព័ន្ធ KHQR និងគ្រប់ធនាគារក្នុងប្រទេសកម្ពុជា', 'Fast and secure payments accepted via Bakong KHQR and all major Cambodian banks'); ?>
            </p>
            <div class="bank-capsules-row">
                <div class="bank-cyber-chip">
                    <img src="../img/khqr.png" alt="KHQR" onerror="this.style.display='none'">
                    <span>Bakong KHQR</span>
                </div>
                <div class="bank-cyber-chip">
                    <img src="../img/aba.png" alt="ABA Bank" onerror="this.style.display='none'">
                    <span>ABA Bank</span>
                </div>
                <div class="bank-cyber-chip">
                    <img src="../img/wink.png" alt="Wing Bank" onerror="this.style.display='none'">
                    <span>Wing Bank</span>
                </div>
                <div class="bank-cyber-chip">
                    <img src="../img/aceleda.png" alt="ACLEDA Bank" onerror="this.style.display='none'">
                    <span>ACLEDA</span>
                </div>
                <div class="bank-cyber-chip">
                    <img src="../img/canadia.png" alt="Canadia Bank" onerror="this.style.display='none'">
                    <span>Canadia</span>
                </div>
            </div>
            <div style="font-size: 12px; color: var(--text-dim); border-top: 1px solid rgba(0, 240, 255, 0.15); padding-top: 18px; width: 100%; font-family: 'Space Grotesk', sans-serif;">
                &copy; <?= date('Y'); ?> <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> • <?= t('footer_copyright', 'រក្សាសិទ្ធិគ្រប់យ៉ាង (All rights reserved)', 'All rights reserved.'); ?>
            </div>
        </footer>
        <?php endif; ?>
    </div>

    <!-- ============================================================== -->
    <!-- QUICK VIEW PRODUCT MODAL                                       -->
    <!-- ============================================================== -->
    <div class="quickview-overlay" id="quickViewModal">
        <div class="quickview-card" role="dialog" aria-modal="true">
            <button type="button" class="quickview-close-btn" id="closeQuickViewBtn" aria-label="បិទ"><i class="fas fa-xmark"></i></button>
            <div class="quickview-image-pane">
                <img id="qvImage" src="./logo/logome.png" alt="Product Image">
            </div>
            <div class="quickview-info-pane">
                <span class="prod-cat-badge" id="qvCategory" style="align-self: flex-start; margin-bottom: 8px;">CATEGORY</span>
                <h2 id="qvTitle" style="font-size: 22px; font-weight: 800; margin: 0 0 8px; color:#203f3a;">Product Title</h2>
                <div style="display: flex; align-items: baseline; gap: 8px; margin-bottom: 14px; flex-wrap: wrap;">
                    <span style="font-size: 22px; font-weight: 800; color: #805f4b;" id="qvPrice">$0.00</span>
                    <span style="font-size: 15px; font-weight: 600; color: #9c9489; text-decoration: line-through; display: none;" id="qvOriginalPrice">$0.00</span>
                    <span class="price-discount-tag" style="display: none; font-size: 11px; padding: 2px 7px;" id="qvDiscountBadge">-0%</span>
                </div>
                <p id="qvDescription" style="font-size: 13.5px; color: var(--text-muted); line-height: 1.7; flex: 1; margin: 0 0 18px;">
                    Description...
                </p>
                <div style="font-size: 12.5px; color: #2e7d32; font-weight: 700; margin-bottom: 14px;" id="qvStockNotice">
                    <i class="fas fa-check-circle"></i> <?= t('qv_stock_notice', 'មានក្នុងស្តុក', 'In Stock'); ?>
                </div>
                
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 18px;">
                    <span style="font-size: 13px; font-weight: 700;"><?= t('qv_qty_label', 'ចំនួន:', 'Quantity:'); ?></span>
                    <div class="cart-qty-stepper" style="font-size: 14px;">
                        <button type="button" id="qvQtyMinus">&minus;</button>
                        <span class="cart-qty-val" id="qvQtyVal">1</span>
                        <button type="button" id="qvQtyPlus">&plus;</button>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-card-addcart" id="qvAddToCartBtn" style="padding: 12px; font-size: 13px;">
                        <i class="fas fa-cart-plus"></i> <?= t('qv_btn_add_cart', 'ដាក់ក្នុងកន្ត្រក', 'Add to Cart'); ?>
                    </button>
                    <button type="button" class="btn-card-buynow" id="qvBuyNowBtn" style="padding: 12px; font-size: 13px;">
                        <i class="fas fa-bolt"></i> <?= t('qv_btn_buy_now', 'ទិញឥឡូវ', 'Buy Now'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- SLIDE-OVER SHOPPING CART DRAWER                                -->
    <!-- ============================================================== -->
    <div class="cart-drawer-overlay" id="cartDrawerOverlay">
        <aside class="cart-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="cartDrawerTitle">
            <div class="cart-drawer-header">
                <h3 id="cartDrawerTitle"><i class="fas fa-shopping-bag"></i> <?= t('cart_drawer_title', 'កន្ត្រកទំនិញរបស់អ្នក', 'Your Shopping Cart'); ?> (<span id="drawerCartCount">0</span>)</h3>
                <button type="button" class="quickview-close-btn" id="closeCartDrawerBtn" aria-label="បិទ"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="cart-drawer-body" id="cartDrawerBody">
                <!-- Injected via JavaScript -->
            </div>
            <div class="cart-drawer-footer" id="cartDrawerFooter">
                <div class="cart-subtotal-line">
                    <span><?= t('cart_subtotal_label', 'សរុបបឋម (Subtotal):', 'Subtotal:'); ?></span>
                    <span id="drawerCartSubtotal"><?= $storeCurrency; ?>0.00</span>
                </div>
                <div style="font-size: 12px; color: #2e7d32; margin-bottom: 14px;">
                    <i class="fas fa-truck-fast"></i> <?= t('cart_shipping_badge', 'គាំទ្រការដឹកជញ្ជូនរហ័ស និងទូទាត់តាម KHQR', 'Fast nationwide shipping & Bakong KHQR payment supported'); ?>
                </div>
                <a href="checkout.php" class="btn-cart-checkout" id="cartDrawerCheckoutBtn" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-credit-card"></i> <?= t('cart_btn_checkout', 'បន្តទៅកាន់ការទូទាត់ (Checkout)', 'Proceed to Checkout'); ?>
                </a>
            </div>
        </aside>
    </div>

    <!-- ============================================================== -->
    <!-- FLOATING CART QUICK BUTTON                                     -->
    <!-- ============================================================== -->
    <div class="floating-cart-pill" id="floatingCartBtn">
        <i class="fas fa-shopping-bag"></i>
        <span><?= t('nav_cart', 'កន្ត្រក', 'Cart'); ?></span>
        <span class="floating-cart-count" id="floatingCartCount">0</span>
    </div>

    <!-- ============================================================== -->
    <!-- Success Confirmation Modal -->
    <div id="cyberSuccessModal" class="cyber-notify-overlay" role="presentation" hidden>
        <section class="cyber-notify-card" role="alertdialog" aria-modal="true" tabindex="-1">
            <div class="cyber-notify-icon">
                <i class="fas fa-check-circle" style="color: #2e7d32;"></i>
            </div>
            <h3 style="font-size: 21px; margin-bottom: 8px;">ការបញ្ជាទិញជោគជ័យ!</h3>
            <p id="successNotifyMessage" class="success-notify-text">
                ព័ត៌មានរបស់អ្នកត្រូវបានផ្ញើទៅកាន់ Telegram Admin រួចរាល់ហើយ។
            </p>
            <div class="success-ref-pill">
                លេខកូដបញ្ជាទិញ: <strong id="successOrderRef" style="color: #805f4b;">--</strong>
            </div>
            <button type="button" id="closeSuccessNotifyBtn" class="cyber-glow-btn" style="width: 100%; padding: 12px; font-size: 14px;">
                យល់ព្រម (Done)
            </button>
        </section>
    </div>

    <!-- Toast Notification Popup -->
    <div class="store-toast-box" id="storeToast">
        <i class="fas fa-check-circle"></i> <span id="storeToastMsg">ជោគជ័យ!</span>
    </div>

    <!-- ============================================================== -->
    <!-- CLIENT JAVASCRIPT ENGINE (SEARCH, CART, QUICKVIEW, CHECKOUT)   -->
    <!-- ============================================================== -->
    <script>
        (() => {
            const storeCurrency = <?= json_encode($storeCurrency); ?>;
            const catalogData = <?= json_encode($catalogItems ?? []); ?> || [];
            const storeLang = <?= json_encode($storeLang); ?>;
            const i18n = {
                cartAdded: <?= json_encode(t('toast_cart_added', 'បានដាក់ចូលកន្ត្រកជោគជ័យ!', 'Added to cart successfully!')); ?>,
                cartEmptyNotice: <?= json_encode(t('cart_empty_text', 'កន្ត្រករបស់អ្នកមិនទាន់មានទំនិញនៅឡើយទេ', 'Your shopping cart is currently empty')); ?>,
                shopNow: <?= json_encode(t('cart_btn_shop_now', 'ទៅកាន់កាតាឡុកទំនិញ (Shop Now)', 'Shop Collection')); ?>,
                inStock: <?= json_encode(t('qv_stock_notice', 'មានក្នុងស្តុក', 'In Stock')); ?>,
                itemsUnit: <?= json_encode($storeLang === 'en' ? 'units' : 'គ្រឿង'); ?>,
                showing: <?= json_encode(t('showing_products', 'បង្ហាញ', 'Showing')); ?>,
                products: <?= json_encode(t('products_unit', 'ផលិតផល', 'Products')); ?>
            };
            const catalogMap = new Map();
            if (Array.isArray(catalogData)) {
                catalogData.forEach(item => catalogMap.set(item.id, item));
            }

            // Toast helper
            const toastEl = document.getElementById('storeToast');
            const toastMsg = document.getElementById('storeToastMsg');
            function showToast(msg) {
                toastMsg.textContent = msg;
                toastEl.classList.add('show');
                setTimeout(() => toastEl.classList.remove('show'), 2500);
            }

            // Live Phnom Penh Clock
            function updateClock() {
                const el = document.getElementById('liveClockDisplay');
                if (el) {
                    const now = new Date();
                    el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                }
            }
            setInterval(updateClock, 1000);
            updateClock();

            // -------------------------------------------------------------
            // SHOPPING CART (LOCALSTORAGE ENGINE)
            // -------------------------------------------------------------
            const CART_STORAGE_KEY = '4k_store_cart_v1';
            let cart = [];

            try {
                const saved = localStorage.getItem(CART_STORAGE_KEY);
                if (saved) cart = JSON.parse(saved);
            } catch (e) { cart = []; }

            function saveCart() {
                try {
                    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
                } catch (e) {}
                updateCartUI();
            }

            function addToCart(productId, qty = 1) {
                const p = catalogMap.get(productId);
                if (!p) return;
                const existing = cart.find(it => it.id === productId);
                if (existing) {
                    existing.qty = Math.min(p.stock, existing.qty + qty);
                } else {
                    cart.push({ id: p.id, name: p.name, price: p.price, img: p.img, qty: Math.min(p.stock, qty) });
                }
                saveCart();
                showToast(storeLang === 'en' ? `Added "${p.name}" to your cart!` : `បានបន្ថែម "${p.name}" ទៅក្នុងកន្ត្រក!`);
            }

            function updateCartUI() {
                const totalItems = cart.reduce((sum, it) => sum + it.qty, 0);
                const subtotal = cart.reduce((sum, it) => sum + (it.price * it.qty), 0);

                // Update badge numbers
                const navBadge = document.getElementById('navCartCount');
                if (navBadge) navBadge.textContent = totalItems;
                const mobBadge = document.getElementById('mobileCartCount');
                if (mobBadge) mobBadge.textContent = totalItems;
                const floatCount = document.getElementById('floatingCartCount');
                if (floatCount) floatCount.textContent = totalItems;
                const drawerCount = document.getElementById('drawerCartCount');
                if (drawerCount) drawerCount.textContent = totalItems;

                // Update drawer items list
                const drawerBody = document.getElementById('cartDrawerBody');
                const drawerFooter = document.getElementById('cartDrawerFooter');
                const subtotalEl = document.getElementById('drawerCartSubtotal');
                if (subtotalEl) subtotalEl.textContent = `${storeCurrency}${subtotal.toFixed(2)}`;

                if (drawerBody) {
                    if (cart.length === 0) {
                        drawerBody.innerHTML = `
                            <div style="text-align:center; padding: 40px 10px; color: var(--text-muted);">
                                <i class="fas fa-shopping-bag" style="font-size: 42px; color: #ded5cb; margin-bottom: 12px; display:block;"></i>
                                <p style="font-size: 14px; margin: 0 0 16px;">${i18n.cartEmptyNotice}</p>
                                <a href="#productsCatalog" class="cyber-glow-btn" style="padding: 8px 18px; font-size: 13px;" onclick="document.getElementById('cartDrawerOverlay').classList.remove('open');">${i18n.shopNow}</a>
                            </div>`;
                        if (drawerFooter) drawerFooter.style.display = 'none';
                    } else {
                        if (drawerFooter) drawerFooter.style.display = 'block';
                        drawerBody.innerHTML = cart.map(it => `
                            <div class="cart-item-row" data-item-id="${it.id}">
                                <img src="${it.img}" alt="${it.name}" class="cart-item-thumb">
                                <div class="cart-item-info">
                                    <h4 class="cart-item-name">${it.name}</h4>
                                    <div class="cart-item-price">${storeCurrency}${it.price.toFixed(2)}</div>
                                    <div class="cart-qty-stepper">
                                        <button type="button" class="cart-minus-btn" data-id="${it.id}">&minus;</button>
                                        <span class="cart-qty-val">${it.qty}</span>
                                        <button type="button" class="cart-plus-btn" data-id="${it.id}">&plus;</button>
                                    </div>
                                </div>
                                <button type="button" class="cart-item-del" data-id="${it.id}" title="លុបចេញ"><i class="fas fa-trash-can"></i></button>
                            </div>
                        `).join('');

                        // Attach listeners to items
                        drawerBody.querySelectorAll('.cart-minus-btn').forEach(b => {
                            b.addEventListener('click', () => {
                                const id = parseInt(b.dataset.id, 10);
                                const it = cart.find(x => x.id === id);
                                if (it) {
                                    if (it.qty > 1) it.qty--;
                                    else cart = cart.filter(x => x.id !== id);
                                    saveCart();
                                }
                            });
                        });
                        drawerBody.querySelectorAll('.cart-plus-btn').forEach(b => {
                            b.addEventListener('click', () => {
                                const id = parseInt(b.dataset.id, 10);
                                const it = cart.find(x => x.id === id);
                                const p = catalogMap.get(id);
                                if (it && p && it.qty < p.stock) {
                                    it.qty++;
                                    saveCart();
                                } else {
                                    showToast('ស្តុកមានកំណត់!');
                                }
                            });
                        });
                        drawerBody.querySelectorAll('.cart-item-del').forEach(b => {
                            b.addEventListener('click', () => {
                                const id = parseInt(b.dataset.id, 10);
                                cart = cart.filter(x => x.id !== id);
                                saveCart();
                            });
                        });
                    }
                }
            }

            // Open/close Cart Drawer
            const cartDrawer = document.getElementById('cartDrawerOverlay');
            const openCartNav = document.getElementById('navbarCartBtn');
            const openCartMobile = document.getElementById('mobileCartBtn');
            const openCartFloat = document.getElementById('floatingCartBtn');
            const closeCartDrawer = document.getElementById('closeCartDrawerBtn');

            function openDrawer() { cartDrawer.classList.add('open'); }
            function closeDrawer() { cartDrawer.classList.remove('open'); }

            if (openCartNav) openCartNav.addEventListener('click', openDrawer);
            if (openCartMobile) openCartMobile.addEventListener('click', openDrawer);
            if (openCartFloat) openCartFloat.addEventListener('click', openDrawer);
            if (closeCartDrawer) closeCartDrawer.addEventListener('click', closeDrawer);
            cartDrawer.addEventListener('click', (e) => {
                if (e.target === cartDrawer) closeDrawer();
            });

            // Initial cart UI render
            updateCartUI();

            // -------------------------------------------------------------
            // LIVE SEARCH, CATEGORY FILTER & SORTING
            // -------------------------------------------------------------
            const searchInput = document.getElementById('liveSearchInput');
            const clearSearchBtn = document.getElementById('clearStorefrontSearch');
            const catPills = document.querySelectorAll('.cyber-category-pill');
            const sortSelect = document.getElementById('catalogSortSelect');
            const matrix = document.getElementById('productsCatalogMatrix');
            const cards = Array.from(document.querySelectorAll('.storefront-prod-card'));
            const countNumEl = document.getElementById('catalogShowingCountNum');

            // Populate category counts
            const catCounterMap = { all: cards.length, men: 0, women: 0, accessories: 0, digital: 0 };
            cards.forEach(c => {
                const cat = (c.dataset.category || '').toLowerCase();
                if (catCounterMap[cat] !== undefined) catCounterMap[cat]++;
            });
            const elAll = document.getElementById('countCatAll');
            const elMen = document.getElementById('countCatMen');
            const elWomen = document.getElementById('countCatWomen');
            const elAcc = document.getElementById('countCatAcc');
            const elDig = document.getElementById('countCatDig');
            if (elAll) elAll.textContent = catCounterMap.all;
            if (elMen) elMen.textContent = catCounterMap.men;
            if (elWomen) elWomen.textContent = catCounterMap.women;
            if (elAcc) elAcc.textContent = catCounterMap.accessories;
            if (elDig) elDig.textContent = catCounterMap.digital;

            function filterCatalog() {
                const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
                const activePill = document.querySelector('.cyber-category-pill.active');
                const activeCat = activePill ? activePill.dataset.category : 'all';

                let visibleCount = 0;
                cards.forEach(card => {
                    const name = card.dataset.name || '';
                    const cat = card.dataset.category || 'other';

                    const matchSearch = query === '' || name.includes(query);
                    const matchCategory = activeCat === 'all' || cat === activeCat;

                    if (matchSearch && matchCategory) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                if (countNumEl) countNumEl.textContent = visibleCount;
                const emptyNotice = document.getElementById('catalogEmptyState');
                if (emptyNotice) {
                    emptyNotice.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            }

            function sortCatalog() {
                const sortType = sortSelect ? sortSelect.value : 'featured';
                const sorted = cards.slice().sort((a, b) => {
                    const idA = parseInt(a.dataset.id, 10);
                    const idB = parseInt(b.dataset.id, 10);
                    const priceA = parseFloat(a.dataset.price);
                    const priceB = parseFloat(b.dataset.price);
                    const discA = parseInt(a.dataset.discount, 10) || 0;
                    const discB = parseInt(b.dataset.discount, 10) || 0;
                    const nameA = a.dataset.name;
                    const nameB = b.dataset.name;

                    if (sortType === 'featured') return idB - idA;
                    if (sortType === 'discount-desc') return (discB - discA) || (idB - idA);
                    if (sortType === 'price-asc') return priceA - priceB;
                    if (sortType === 'price-desc') return priceB - priceA;
                    if (sortType === 'name-asc') return nameA.localeCompare(nameB);
                    return 0;
                });
                sorted.forEach(c => matrix.appendChild(c));
            }

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    if (clearSearchBtn) clearSearchBtn.style.display = searchInput.value.length > 0 ? 'block' : 'none';
                    filterCatalog();
                });
            }
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', () => {
                    searchInput.value = '';
                    clearSearchBtn.style.display = 'none';
                    searchInput.focus();
                    filterCatalog();
                });
            }
            catPills.forEach(pill => {
                pill.addEventListener('click', () => {
                    catPills.forEach(p => p.classList.remove('active'));
                    pill.classList.add('active');
                    filterCatalog();
                });
            });
            if (sortSelect) sortSelect.addEventListener('change', () => {
                sortCatalog();
                filterCatalog();
            });

            const btnResetCatalogFilter = document.getElementById('btnResetCatalogFilter');
            if (btnResetCatalogFilter) {
                btnResetCatalogFilter.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    if (clearSearchBtn) clearSearchBtn.style.display = 'none';
                    catPills.forEach(p => p.classList.remove('active'));
                    const allPill = document.querySelector('.cyber-category-pill[data-category="all"]');
                    if (allPill) allPill.classList.add('active');
                    filterCatalog();
                });
            }

            // Initial count
            filterCatalog();

            // -------------------------------------------------------------
            // QUICK VIEW MODAL
            // -------------------------------------------------------------
            const qvModal = document.getElementById('quickViewModal');
            const qvClose = document.getElementById('closeQuickViewBtn');
            const qvImage = document.getElementById('qvImage');
            const qvTitle = document.getElementById('qvTitle');
            const qvCategory = document.getElementById('qvCategory');
            const qvPrice = document.getElementById('qvPrice');
            const qvOriginalPrice = document.getElementById('qvOriginalPrice');
            const qvDiscountBadge = document.getElementById('qvDiscountBadge');
            const qvDesc = document.getElementById('qvDescription');
            const qvStockNotice = document.getElementById('qvStockNotice');
            const qvQtyVal = document.getElementById('qvQtyVal');
            const qvQtyMinus = document.getElementById('qvQtyMinus');
            const qvQtyPlus = document.getElementById('qvQtyPlus');
            const qvAddCartBtn = document.getElementById('qvAddToCartBtn');
            const qvBuyNowBtn = document.getElementById('qvBuyNowBtn');
            let qvCurrentItem = null;
            let qvCurrentQty = 1;

            function openQuickView(productId) {
                const p = catalogMap.get(productId);
                if (!p) return;
                qvCurrentItem = p;
                qvCurrentQty = 1;
                qvQtyVal.textContent = '1';
                qvImage.src = p.img;
                qvTitle.textContent = p.name;
                qvCategory.textContent = p.category.toUpperCase();
                if (p.has_discount && p.original_price > p.price) {
                    qvPrice.textContent = `${storeCurrency}${p.price.toFixed(2)}`;
                    qvPrice.style.color = '#e11d48';
                    if (qvOriginalPrice) {
                        qvOriginalPrice.textContent = `${storeCurrency}${p.original_price.toFixed(2)}`;
                        qvOriginalPrice.style.display = 'inline';
                    }
                    if (qvDiscountBadge) {
                        qvDiscountBadge.textContent = `-${p.discount_percent}% OFF`;
                        qvDiscountBadge.style.display = 'inline-block';
                    }
                } else {
                    qvPrice.textContent = `${storeCurrency}${p.price.toFixed(2)}`;
                    qvPrice.style.color = '#805f4b';
                    if (qvOriginalPrice) qvOriginalPrice.style.display = 'none';
                    if (qvDiscountBadge) qvDiscountBadge.style.display = 'none';
                }
                const currentStoreName = <?= json_encode($storeName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                qvDesc.textContent = p.desc || (storeLang === 'en' ? 'Premium quality guaranteed by ' + currentStoreName + '.' : 'ផលិតផលគុណភាពខ្ពស់ មានការធានាជូនត្រឹមត្រូវពី ' + currentStoreName);
                qvStockNotice.innerHTML = `<i class="fas fa-check-circle"></i> ${i18n.inStock} (${p.stock} ${i18n.itemsUnit})`;
                qvModal.classList.add('open');
                document.body.style.overflow = 'hidden';
            }

            function closeQuickView() {
                qvModal.classList.remove('open');
                document.body.style.overflow = '';
            }

            if (qvClose) qvClose.addEventListener('click', closeQuickView);
            qvModal.addEventListener('click', (e) => {
                if (e.target === qvModal) closeQuickView();
            });

            if (qvQtyMinus) {
                qvQtyMinus.addEventListener('click', () => {
                    if (qvCurrentQty > 1) {
                        qvCurrentQty--;
                        qvQtyVal.textContent = qvCurrentQty;
                    }
                });
            }
            if (qvQtyPlus) {
                qvQtyPlus.addEventListener('click', () => {
                    if (qvCurrentItem && qvCurrentQty < qvCurrentItem.stock) {
                        qvCurrentQty++;
                        qvQtyVal.textContent = qvCurrentQty;
                    }
                });
            }
            if (qvAddCartBtn) {
                qvAddCartBtn.addEventListener('click', () => {
                    if (qvCurrentItem) {
                        addToCart(qvCurrentItem.id, qvCurrentQty);
                        closeQuickView();
                    }
                });
            }
            if (qvBuyNowBtn) {
                qvBuyNowBtn.addEventListener('click', () => {
                    if (qvCurrentItem) {
                        window.location.href = `checkout.php?item=${encodeURIComponent(qvCurrentItem.id)}&qty=${encodeURIComponent(qvCurrentQty)}`;
                    }
                });
            }

            // Bind Card Buttons (Quick View, Add Cart, Buy Now)
            document.querySelectorAll('[data-quickview-id]').forEach(btn => {
                btn.addEventListener('click', () => openQuickView(parseInt(btn.dataset.quickviewId, 10)));
            });
            document.querySelectorAll('[data-add-cart-id]').forEach(btn => {
                btn.addEventListener('click', () => addToCart(parseInt(btn.dataset.addCartId, 10), 1));
            });
            document.querySelectorAll('[data-buy-now-id]').forEach(btn => {
                btn.addEventListener('click', () => startSingleCheckout(parseInt(btn.dataset.buyNowId, 10), 1));
            });

            // -------------------------------------------------------------
            // CHECKOUT ROUTING ENGINE
            function startSingleCheckout(productId, qty = 1) {
                window.location.href = `checkout.php?item=${encodeURIComponent(productId)}&qty=${encodeURIComponent(qty)}`;
            }

            function startCartCheckout(e) {
                if (e && e.preventDefault) e.preventDefault();
                if (!cart || cart.length === 0) {
                    showToast('កន្ត្រករបស់អ្នកទទេ! សូមជ្រើសរើសទំនិញជាមុនសិន');
                    return;
                }
                window.location.href = 'checkout.php';
            }

            const cartDrawerCheckoutBtn = document.getElementById('cartDrawerCheckoutBtn');
            if (cartDrawerCheckoutBtn) {
                cartDrawerCheckoutBtn.addEventListener('click', startCartCheckout);
            }

            // If user arrived with URL ?item=ID, trigger single checkout automatically
            <?php if ($is_checkout && $item !== '' && ctype_digit((string)$item)): ?>
                startSingleCheckout(<?= (int)$item; ?>, 1);
            <?php endif; ?>

            // If user arrived with URL ?qv=ID, open Quick View modal automatically
            const qvParam = new URLSearchParams(window.location.search).get('qv');
            if (qvParam && /^\d+$/.test(qvParam)) {
                setTimeout(() => openQuickView(parseInt(qvParam, 10)), 100);
            }
        })();
    </script>
    <script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js'); ?>"></script>
</body>

</html>
