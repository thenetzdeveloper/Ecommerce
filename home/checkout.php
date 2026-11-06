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
$currentUser = getCurrentUser($conn);

if (empty($_SESSION['order_csrf_token'])) {
    $_SESSION['order_csrf_token'] = bin2hex(random_bytes(32));
}
$orderCsrfToken = $_SESSION['order_csrf_token'];

// Store settings & currency
$checkoutSettings = function_exists('getStoreSettings') ? getStoreSettings($conn ?? null) : [];
$storeName = !empty($checkoutSettings['store_name']) ? (string)$checkoutSettings['store_name'] : 'SN STORE';
$storeCurrency = !empty($checkoutSettings['currency_symbol']) ? (string)$checkoutSettings['currency_symbol'] : '$';
$storeContactUrl = !empty($checkoutSettings['store_contact_url']) ? (string)$checkoutSettings['store_contact_url'] : 'https://t.me/remsonich';
$storeLogoUrl = $checkoutSettings['store_logo_url'] ?? '';

if (isset($_GET['admin']) || (isset($_GET['page']) && $_GET['page'] === 'admin')) {
    header('Location: ../admin/index.php');
    exit;
}

// Single item checkout check
$itemId = filter_var($_GET['item'] ?? null, FILTER_VALIDATE_INT);
$itemQty = max(1, filter_var($_GET['qty'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$singleProduct = null;

if ($itemId && $itemId > 0 && isset($conn) && !$conn->connect_error) {
    $stmt = $conn->prepare("SELECT id, name, category, description, price, stock, image FROM products WHERE id = ? AND stock > 0");
    if ($stmt) {
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $singleProduct = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$nav_active = 'payments';
$nav_home_url = 'index.php';
$nav_about_url = 'index.php?page=about';
$nav_support_url = '../customer-service/index.php';
$nav_payments_url = 'checkout.php';
$nav_store_name = $storeName;
$nav_brand_logo = $storeLogoUrl;
$nav_contact_url = $storeContactUrl;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($storeLang, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('checkout_page_title', 'ទូទាត់ប្រាក់ • Checkout', 'Checkout • Secure Order'); ?> • <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?></title>
    <!-- Icons & Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="./logo/logome.png" type="image/x-icon">
    <!-- Storefront Stylesheets -->
    <link rel="stylesheet" href="./page.css?v=<?= filemtime(__DIR__ . '/page.css'); ?>">
    <link rel="stylesheet" href="./fashion-theme.css?v=<?= filemtime(__DIR__ . '/fashion-theme.css'); ?>">
    <link rel="stylesheet" href="../css/theme-mode.css?v=<?= filemtime(__DIR__ . '/../css/theme-mode.css'); ?>">
    <script src="../js/page-navigation.js?v=<?= filemtime(__DIR__ . '/../js/page-navigation.js'); ?>" defer></script>
    <script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js'); ?>" defer></script>
    <style>
        .checkout-page-container {
            max-width: 1240px;
            margin: 32px auto 80px;
            padding: 0 clamp(16px, 4vw, 36px);
        }

        .checkout-breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .checkout-breadcrumbs a {
            color: #805f4b;
            text-decoration: none;
            font-weight: 600;
        }

        .checkout-breadcrumbs a:hover {
            text-decoration: underline;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1.25fr 1fr;
            gap: 32px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .checkout-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }

        .checkout-card {
            background: #ffffff;
            border: 1px solid #e8e1d8;
            border-radius: 14px;
            padding: 28px 30px;
            box-shadow: 0 6px 25px rgba(48, 41, 35, 0.04);
            margin-bottom: 24px;
        }

        .checkout-card h2 {
            margin: 0 0 18px;
            font-size: 19px;
            font-weight: 700;
            color: #203f3a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkout-form-group {
            margin-bottom: 18px;
        }

        .checkout-form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 13.5px;
            font-weight: 700;
            color: #443e38;
        }

        .checkout-input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 16px;
            border: 1.5px solid #ded5cb;
            border-radius: 8px;
            background: #faf8f5;
            color: #2e2924;
            font: inherit;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
        }

        .checkout-input:focus {
            border-color: #805f4b;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(128, 95, 75, 0.12);
        }

        .khqr-pay-box {
            background: #f0f7f6;
            border: 1px solid #c2e2dc;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin: 18px 0;
        }

        .khqr-image-wrapper {
            width: 200px;
            height: 200px;
            margin: 14px auto;
            background: #ffffff;
            border: 1px solid #ded5cb;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }

        .khqr-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .receipt-upload-card {
            border: 2px dashed #ded5cb;
            border-radius: 10px;
            padding: 22px;
            text-align: center;
            background: #faf8f5;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .receipt-upload-card:hover {
            border-color: #805f4b;
            background: #fbf7f2;
        }

        .receipt-upload-card i {
            font-size: 32px;
            color: #805f4b;
            margin-bottom: 8px;
        }

        .receipt-preview-box {
            display: none;
            margin-top: 14px;
            text-align: center;
        }

        .receipt-preview-box img {
            max-width: 180px;
            max-height: 180px;
            border-radius: 8px;
            border: 1px solid #ded5cb;
            object-fit: cover;
        }

        .order-summary-sticky {
            position: sticky;
            top: 24px;
        }

        .order-item-tile {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 0;
            border-bottom: 1px solid #f0eae1;
        }

        .order-item-tile img {
            width: 54px;
            height: 54px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #ded5cb;
            background: #f4f0ea;
            flex-shrink: 0;
        }

        .order-item-title {
            font-weight: 700;
            font-size: 14px;
            color: #203f3a;
            margin-bottom: 3px;
        }

        .order-item-sub {
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .order-item-price {
            font-weight: 700;
            font-size: 14.5px;
            color: #203f3a;
            margin-left: auto;
        }

        .order-price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #554d45;
            margin-bottom: 10px;
        }

        .order-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 18px;
            font-weight: 800;
            color: #203f3a;
            padding-top: 14px;
            margin-top: 14px;
            border-top: 2px dashed #e8e1d8;
        }

        .btn-submit-order {
            width: 100%;
            padding: 16px;
            border: 0;
            border-radius: 9px;
            background: #203f3a;
            color: #ffffff;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(32, 63, 58, 0.25);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-submit-order:hover {
            background: #2d564f;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(32, 63, 58, 0.3);
        }

        .btn-submit-order:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Dark Theme Support */
        body.fashion-storefront[data-theme="dark"] .checkout-card {
            background: #171d28;
            border-color: #343c4a;
            color: #edf1f7;
        }

        body.fashion-storefront[data-theme="dark"] .checkout-card h2,
        body.fashion-storefront[data-theme="dark"] .order-item-title,
        body.fashion-storefront[data-theme="dark"] .order-item-price,
        body.fashion-storefront[data-theme="dark"] .order-total-row {
            color: #edf1f7;
        }

        body.fashion-storefront[data-theme="dark"] .checkout-form-group label,
        body.fashion-storefront[data-theme="dark"] .order-price-row {
            color: #cbd5e1;
        }

        body.fashion-storefront[data-theme="dark"] .checkout-input {
            background: #10151e;
            border-color: #343c4a;
            color: #edf1f7;
        }

        body.fashion-storefront[data-theme="dark"] .checkout-input:focus {
            border-color: #34d399;
            box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.2);
        }

        body.fashion-storefront[data-theme="dark"] .khqr-pay-box {
            background: #121c25;
            border-color: #28444a;
            color: #edf1f7;
        }

        body.fashion-storefront[data-theme="dark"] .receipt-upload-card {
            background: #10151e;
            border-color: #343c4a;
        }

        body.fashion-storefront[data-theme="dark"] .btn-submit-order {
            background: #203f3a;
            border: 1px solid #34d399;
            color: #ffffff;
        }

        body.fashion-storefront[data-theme="dark"] .btn-submit-order:hover {
            background: #28554d;
        }
    </style>
</head>

<body class="fashion-storefront">
    <!-- Navbar Component -->
    <?php require __DIR__ . '/navbar.php'; ?>

    <main class="checkout-page-container">
        <!-- Breadcrumbs -->
        <nav class="checkout-breadcrumbs" aria-label="Breadcrumb">
            <a href="index.php"><i class="fas fa-home"></i> <?= t('checkout_bc_home', 'ទំព័រដើម', 'Home'); ?></a>
            <i class="fas fa-chevron-right" style="font-size: 11px;"></i>
            <a href="index.php#productsCatalog"><?= t('products_unit', 'ផលិតផល', 'Products'); ?></a>
            <i class="fas fa-chevron-right" style="font-size: 11px;"></i>
            <span><?= t('checkout_bc_curr', 'ទូទាត់ប្រាក់ (Checkout)', 'Checkout'); ?></span>
        </nav>

        <form id="standaloneCheckoutForm" action="../api/telegram-order.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($orderCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="item" id="hiddenSingleId" value="<?= $singleProduct ? (int)$singleProduct['id'] : ''; ?>">
            <input type="hidden" name="quantity" id="hiddenSingleQty" value="<?= $singleProduct ? $itemQty : '1'; ?>">
            <input type="hidden" name="cart_items" id="hiddenCartItems" value="">
            <input type="hidden" name="coupon_code" id="hiddenCouponCode" value="">

            <div class="checkout-grid">
                <!-- Left Column: Customer Details & Payment -->
                <div class="checkout-main-col">
                    <!-- Step 1: Customer Details -->
                    <div class="checkout-card">
                        <h2><i class="fas fa-user-circle" style="color: #805f4b;"></i> <?= t('checkout_box_customer', 'ព័ត៌មានដឹកជញ្ជូន (Delivery Details)', 'Customer & Delivery Info'); ?></h2>
                        
                        <?php if ($currentUser): ?>
                            <div style="background: rgba(32, 63, 58, 0.08); border: 1px solid rgba(32, 63, 58, 0.2); border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 13.5px; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-circle-user" style="color: #203f3a; font-size: 16px;"></i>
                                    <span><?= t('checkout_logged_in_as', 'បានចូលគណនីជា', 'Logged in as'); ?> <strong><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?></strong> (<?= htmlspecialchars($currentUser['email'] ?: $currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                                </div>
                                <a href="profile.php?tab=info" style="color: #203f3a; font-weight: 700; text-decoration: underline; font-size: 12.5px;"><?= t('nav_my_profile', 'គណនីរបស់ខ្ញុំ', 'Edit Profile'); ?></a>
                            </div>
                        <?php else: ?>
                            <div style="background: rgba(128, 95, 75, 0.08); border: 1px solid rgba(128, 95, 75, 0.2); border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px; display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 8px; color: #5a544e;">
                                    <i class="fas fa-lightbulb" style="color: #805f4b;"></i>
                                    <span><?= t('checkout_guest_prompt', 'មានគណនីរួចហើយ? ចូលគណនីដើម្បីបំពេញព័ត៌មានដោយស្វ័យប្រវត្តិ', 'Have an account? Log in to pre-fill your delivery info'); ?></span>
                                </div>
                                <a href="login.php?redirect=checkout.php" style="color: #203f3a; font-weight: 700; text-decoration: underline;"><?= t('nav_login', 'ចូលគណនី', 'Sign In'); ?> &rarr;</a>
                            </div>
                        <?php endif; ?>

                        <div class="checkout-form-group">
                            <label for="checkoutName"><?= t('checkout_name_label', 'ឈ្មោះអ្នកទទួលទំនិញ (Full Name) *', 'Full Name *'); ?></label>
                            <input type="text" id="checkoutName" name="customer_name" class="checkout-input" value="<?= htmlspecialchars($currentUser['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?= htmlspecialchars(t('checkout_name_ph', 'ឧ. សុខ ចិន្តា', 'e.g. Sokha Chan'), ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="name">
                        </div>

                        <div class="checkout-form-group">
                            <label for="checkoutPhone"><?= t('checkout_phone_label', 'លេខទូរស័ព្ទ (Phone Number) *', 'Phone Number / Telegram *'); ?></label>
                            <input type="tel" id="checkoutPhone" name="phone_number" class="checkout-input" value="<?= htmlspecialchars($currentUser['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?= htmlspecialchars(t('checkout_phone_ph', '012 345 678', '012 345 678'), ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="tel">
                        </div>

                        <div class="checkout-form-group">
                            <label for="checkoutLocation"><?= t('checkout_address_label', 'អាសយដ្ឋានដឹកជញ្ជូន (Address / Location) *', 'Delivery Address *'); ?></label>
                            <textarea id="checkoutLocation" name="location" class="checkout-input" style="min-height: 80px; resize: vertical;" placeholder="<?= htmlspecialchars(t('checkout_address_ph', 'ផ្ទះលេខ, ផ្លូវ, សង្កាត់/ឃុំ, ខណ្ឌ/ស្រុក, រាជធានី/ខេត្ត...', 'House, Street, Sangkat, Khan, City/Province...'), ENT_QUOTES, 'UTF-8'); ?>" required><?= htmlspecialchars($currentUser['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>

                    <!-- Step 2: Payment & KHQR -->
                    <div class="checkout-card">
                        <h2><i class="fas fa-qrcode" style="color: #805f4b;"></i> <?= t('checkout_box_payment', 'វិធីសាស្ត្រទូទាត់ប្រាក់ (Payment via KHQR)', 'Pay via Bakong KHQR'); ?></h2>
                        
                        <div class="khqr-pay-box">
                            <div style="font-weight: 700; color: #203f3a; font-size: 15px; margin-bottom: 6px;">
                                <i class="fas fa-shield-check" style="color: #2e7d32;"></i> <?= $storeLang === "en" ? "Scan & Pay Instantly with Bakong KHQR" : "ស្កេនទូទាត់ប្រាក់ភ្លាមៗតាម KHQR Bakong"; ?>
                            </div>
                            <p style="margin: 0; font-size: 13px; color: #554d45;">
                                <?= t('checkout_qr_scan_note', 'គាំទ្រគ្រប់កម្មវិធីធនាគារទាំងអស់៖ ABA, ACLEDA, Wing, Canadia, Sathapana, Vattanac...', 'Scan the QR code with any mobile banking app (ABA, Wing, ACLEDA, Canadia...)'); ?>
                            </p>

                            <div class="khqr-image-wrapper">
                                <img src="../img/khqr.png" alt="KHQR Payment QR Code" onerror="this.src='./logo/icon.png'">
                            </div>

                            <div style="font-size: 14px; font-weight: 700; color: #203f3a;">
                                <?= t('checkout_amount_to_pay', 'ទឹកប្រាក់ត្រូវទូទាត់:', 'Amount to Pay:'); ?> <span id="khqrPayAmount" style="color: #2e7d32; font-size: 18px;"><?= $storeCurrency; ?>0.00</span>
                            </div>
                        </div>

                        <!-- Step 3: Receipt Upload -->
                        <div class="checkout-form-group" style="margin-top: 20px;">
                            <label><?= t('checkout_box_receipt', 'ភ្ជាប់រូបថតវិក្កយបត្របង់ប្រាក់ (Payment Receipt) *', 'Attach Payment Receipt *'); ?></label>
                            <label for="checkoutReceiptFile" class="receipt-upload-card" id="uploadCardTrigger">
                                <i class="fas fa-cloud-arrow-up"></i>
                                <div style="font-weight: 700; font-size: 14px; color: #203f3a; margin-bottom: 4px;"><?= t('checkout_receipt_drop_text', 'ចុចទីនេះដើម្បីបង្ហោះរូបភាពវិក្កយបត្រ', 'Click here to choose payment receipt or drag & drop image'); ?></div>
                                <div style="font-size: 12.5px; color: var(--text-muted);"><?= t('checkout_receipt_formats', 'ទទួលយក JPG, PNG, WEBP (ទំហំមិនលើសពី 5MB)', 'JPG, PNG, WEBP (Max 10MB)'); ?></div>
                                <div id="selectedReceiptName" style="margin-top: 8px; font-weight: 600; font-size: 13px; color: #805f4b;"></div>
                            </label>
                            <input type="file" id="checkoutReceiptFile" name="receipt" accept="image/jpeg,image/png,image/webp,image/heic,application/pdf" style="display: none;" required>

                            <div class="receipt-preview-box" id="receiptPreviewBox">
                                <img id="receiptPreviewImg" src="" alt="Receipt Preview">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Order Summary (Sticky) -->
                <div class="checkout-sidebar-col">
                    <div class="checkout-card order-summary-sticky">
                        <h2><i class="fas fa-shopping-bag" style="color: #805f4b;"></i> <?= t('checkout_box_summary', 'ការសង្ខេបការបញ្ជាទិញ (Summary)', 'Order Summary'); ?></h2>

                        <div id="checkoutItemsList">
                            <?php if ($singleProduct): ?>
                                <?php
                                $sPrice = (float)$singleProduct['price'];
                                $sTotal = $sPrice * $itemQty;
                                $sImg = !empty($singleProduct['image'])
                                    ? (preg_match('~^https?://~i', $singleProduct['image']) ? $singleProduct['image'] : '../' . $singleProduct['image'])
                                    : './logo/logome.png';
                                ?>
                                <div class="order-item-tile">
                                    <img src="<?= htmlspecialchars($sImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($singleProduct['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <div style="flex: 1;">
                                        <div class="order-item-title"><?= htmlspecialchars($singleProduct['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="order-item-sub" style="display: flex; align-items: center; gap: 8px; margin-top: 5px;">
                                            <span style="font-weight: 600; color: #805f4b;"><?= $storeCurrency; ?><?= number_format($sPrice, 2); ?></span>
                                            <span style="display: inline-flex; align-items: center; border: 1px solid #ded5cb; border-radius: 6px; overflow: hidden; background: #fff;">
                                                <button type="button" id="singleQtyMinus" style="padding: 3px 10px; border: 0; background: #faf8f5; cursor: pointer; font-size: 14px; color: #443e38;" title="<?= $storeLang === 'en' ? 'Decrease' : 'បន្ថយ'; ?>">&minus;</button>
                                                <span id="singleQtyDisplay" style="padding: 3px 12px; font-weight: 700; font-size: 13px; color: #203f3a;"><?= $itemQty; ?></span>
                                                <button type="button" id="singleQtyPlus" style="padding: 3px 10px; border: 0; background: #faf8f5; cursor: pointer; font-size: 14px; color: #443e38;" title="<?= $storeLang === 'en' ? 'Increase' : 'បន្ថែម'; ?>">&plus;</button>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="order-item-price" id="singlePriceDisplay"><?= $storeCurrency; ?><?= number_format($sTotal, 2); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Coupon Code Box -->
                        <div style="margin: 20px 0; padding: 14px; background: #faf8f5; border: 1px solid #e8e1d8; border-radius: 8px;">
                            <label style="display:block; margin-bottom: 6px; font-size: 13px; font-weight: 700; color: #443e38;">
                                <i class="fas fa-ticket-alt" style="color: #805f4b;"></i> <?= t('checkout_coupon_label', 'កូដបញ្ចុះតម្លៃ (Coupon Code)', 'Discount Coupon Code'); ?>
                            </label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="couponCodeInput" class="checkout-input" style="padding: 9px 12px; font-size: 13px;" placeholder="<?= htmlspecialchars(t('checkout_coupon_ph', 'ឧ. SAVE10 ឬ WELCOME5', 'Enter coupon code...'), ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="button" id="btnApplyCoupon" class="btn-card-buynow" style="padding: 9px 16px; border-radius: 8px; flex: none;"><?= t('checkout_coupon_btn', 'អនុវត្ត', 'Apply'); ?></button>
                            </div>
                            <div id="couponStatusMessage" style="font-size: 12.5px; margin-top: 6px;"></div>
                        </div>

                        <!-- Price Breakdown -->
                        <div style="margin-top: 14px;">
                            <div class="order-price-row">
                                <span><?= t('checkout_subtotal_label', 'តម្លៃទំនិញសរុប (Subtotal):', 'Subtotal:'); ?></span>
                                <strong id="summarySubtotalText"><?= $storeCurrency; ?>0.00</strong>
                            </div>
                            <div class="order-price-row" id="summaryDiscountRow" style="display: none; color: #166534;">
                                <span><?= t('checkout_discount_label', 'ការបញ្ចុះតម្លៃ (Discount):', 'Discount:'); ?></span>
                                <strong id="summaryDiscountText">-<?= $storeCurrency; ?>0.00</strong>
                            </div>
                            <div class="order-price-row">
                                <span><?= t('checkout_shipping_label', 'ថ្លៃសេវាដឹកជញ្ជូន (Shipping):', 'Shipping:'); ?></span>
                                <strong style="color: #2e7d32;"><i class="fas fa-truck-fast"></i> <?= t('checkout_shipping_free', 'ឥតគិតថ្លៃ (Free)', 'FREE'); ?></strong>
                            </div>
                            <div class="order-total-row">
                                <span><?= t('checkout_total_label', 'ទឹកប្រាក់សរុប (Total):', 'Grand Total:'); ?></span>
                                <span id="summaryTotalText"><?= $storeCurrency; ?>0.00</span>
                            </div>
                        </div>

                        <div id="checkoutErrorMessage" style="display: none; margin-top: 14px; padding: 10px 14px; background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; color: #dc2626; font-size: 13px;"></div>

                        <button type="submit" id="btnSubmitOrder" class="btn-submit-order">
                            <i class="fab fa-telegram-plane"></i> <?= t('checkout_btn_submit', 'បញ្ជាក់ការបញ្ជាទិញ (Place Order)', 'Place Order Now'); ?>
                        </button>

                        <div style="margin-top: 16px; text-align: center; font-size: 12px; color: var(--text-muted); display: flex; align-items: center; justify-content: center; gap: 14px;">
                            <span><i class="fas fa-lock" style="color: #2e7d32;"></i> <?= t('checkout_trust_security', '100% សុវត្ថិភាព', '100% Secure'); ?></span>
                            <span><i class="fas fa-shield-alt" style="color: #805f4b;"></i> <?= t('checkout_trust_quality', 'ធានាគុណភាព', 'Quality Guaranteed'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- Success Confirmation Dialog -->
    <div id="checkoutSuccessModal" class="cyber-notify-overlay" role="presentation" hidden>
        <section class="cyber-notify-card" role="alertdialog" aria-modal="true" tabindex="-1" style="max-width: 460px; text-align: center; padding: 32px 24px; border-radius: 16px; background: #ffffff; border: 1px solid #e8e1d8; box-shadow: 0 25px 60px rgba(0,0,0,0.2);">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: #e8f5e9; color: #2e7d32; display: grid; place-items: center; font-size: 28px; margin: 0 auto 16px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="font-size: 22px; margin: 0 0 8px; color: #203f3a; font-weight: 800;">ការបញ្ជាទិញជោគជ័យ!</h3>
            <p style="font-size: 14px; color: #554d45; line-height: 1.6; margin: 0 0 16px;">
                ព័ត៌មាន និងវិក្កយបត្ររបស់អ្នកត្រូវបានផ្ញើទៅកាន់ Telegram Admin រួចរាល់ហើយ។
            </p>
            <div style="background: #f0f7f6; border: 1px solid #c2e2dc; border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 700; color: #203f3a;">
                លេខកូដបញ្ជាទិញ: <strong id="successOrderRefNumber" style="color: #805f4b;">--</strong>
            </div>
            <a href="index.php" class="btn-card-buynow" style="display: block; width: 100%; text-align: center; text-decoration: none; padding: 14px; border-radius: 8px; font-size: 14px; font-weight: 700;">
                <i class="fas fa-arrow-left"></i> ត្រឡប់ទៅកាន់ទំព័រដើម (Continue Shopping)
            </a>
        </section>
    </div>

    <!-- Client-side Logic -->
    <script>
        (() => {
            const storeCurrency = <?= json_encode($storeCurrency); ?>;
            const isSingleProduct = <?= $singleProduct ? 'true' : 'false'; ?>;
            const singlePrice = <?= $singleProduct ? (float)$singleProduct['price'] : '0'; ?>;
            const singleQty = <?= $singleProduct ? $itemQty : '1'; ?>;
            const singleId = <?= $singleProduct ? (int)$singleProduct['id'] : '0'; ?>;
            const storeLang = <?= json_encode($storeLang); ?>;

            let subtotal = 0.0;
            let discount = 0.0;
            let currentCart = [];

            const CART_STORAGE_KEY = '4k_store_cart_v1';
            const itemsContainer = document.getElementById('checkoutItemsList');
            const subtotalText = document.getElementById('summarySubtotalText');
            const discountRow = document.getElementById('summaryDiscountRow');
            const discountText = document.getElementById('summaryDiscountText');
            const totalText = document.getElementById('summaryTotalText');
            const khqrPayAmount = document.getElementById('khqrPayAmount');
            const hiddenCartItems = document.getElementById('hiddenCartItems');
            const hiddenCouponCode = document.getElementById('hiddenCouponCode');
            const errorMsg = document.getElementById('checkoutErrorMessage');

            // Update navbar badge count
            function refreshNavCartBadge() {
                try {
                    const saved = localStorage.getItem(CART_STORAGE_KEY);
                    const items = saved ? JSON.parse(saved) : [];
                    const totalCount = items.reduce((sum, it) => sum + (it.qty || 1), 0);
                    const navBadge = document.getElementById('navCartCount');
                    if (navBadge) navBadge.textContent = totalCount;
                    const mobBadge = document.getElementById('mobileCartCount');
                    if (mobBadge) mobBadge.textContent = totalCount;
                } catch (e) {}
            }
            refreshNavCartBadge();

            const navbarCartBtn = document.getElementById('navbarCartBtn');
            if (navbarCartBtn) {
                navbarCartBtn.addEventListener('click', () => {
                    window.location.href = 'index.php#productsCatalogMatrix';
                });
            }

            // Single item state
            let liveSingleQty = singleQty;
            const singleQtyDisplay = document.getElementById('singleQtyDisplay');
            const singlePriceDisplay = document.getElementById('singlePriceDisplay');
            const hiddenSingleQtyInput = document.getElementById('hiddenSingleQty');

            if (isSingleProduct) {
                subtotal = singlePrice * liveSingleQty;
                updateTotals();

                const btnMinus = document.getElementById('singleQtyMinus');
                const btnPlus = document.getElementById('singleQtyPlus');

                if (btnMinus) {
                    btnMinus.addEventListener('click', () => {
                        if (liveSingleQty > 1) {
                            liveSingleQty--;
                            singleQtyDisplay.textContent = liveSingleQty;
                            hiddenSingleQtyInput.value = liveSingleQty;
                            const itemTotal = singlePrice * liveSingleQty;
                            singlePriceDisplay.textContent = `${storeCurrency}${itemTotal.toFixed(2)}`;
                            subtotal = itemTotal;
                            updateTotals();
                        }
                    });
                }

                if (btnPlus) {
                    btnPlus.addEventListener('click', () => {
                        liveSingleQty++;
                        singleQtyDisplay.textContent = liveSingleQty;
                        hiddenSingleQtyInput.value = liveSingleQty;
                        const itemTotal = singlePrice * liveSingleQty;
                        singlePriceDisplay.textContent = `${storeCurrency}${itemTotal.toFixed(2)}`;
                        subtotal = itemTotal;
                        updateTotals();
                    });
                }
            } else {
                function renderCartList() {
                    try {
                        const saved = localStorage.getItem(CART_STORAGE_KEY);
                        if (saved) currentCart = JSON.parse(saved);
                    } catch (e) { currentCart = []; }

                    refreshNavCartBadge();

                    if (!currentCart || currentCart.length === 0) {
                        itemsContainer.innerHTML = `
                            <div style="text-align:center; padding: 36px 12px; color: var(--text-muted);">
                                <i class="fas fa-shopping-bag" style="font-size: 46px; color: #ded5cb; margin-bottom: 14px; display:block;"></i>
                                <p style="font-size: 15px; margin: 0 0 16px; font-weight: 600; color: #554d45;">${storeLang === 'en' ? 'Your shopping cart is currently empty' : 'កន្ត្រករបស់អ្នកមិនទាន់មានទំនិញនៅឡើយទេ'}</p>
                                <a href="index.php#productsCatalogMatrix" class="btn-card-buynow" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px; padding: 11px 22px; border-radius: 8px;">
                                    <i class="fas fa-store"></i> ${storeLang === 'en' ? 'Shop Collection' : 'ទៅកាន់កាតាឡុកទំនិញ (Shop Now)'}
                                </a>
                            </div>`;
                        document.getElementById('btnSubmitOrder').disabled = true;
                        subtotal = 0;
                        hiddenCartItems.value = '[]';
                        updateTotals();
                        return;
                    }

                    document.getElementById('btnSubmitOrder').disabled = false;
                    subtotal = currentCart.reduce((sum, it) => sum + (it.price * it.qty), 0);
                    hiddenCartItems.value = JSON.stringify(currentCart.map(x => ({ id: x.id, quantity: x.qty })));

                    itemsContainer.innerHTML = currentCart.map((it, idx) => `
                        <div class="order-item-tile" data-index="${idx}">
                            <img src="${it.img}" alt="${it.name}" onerror="this.src='./logo/logome.png'">
                            <div style="flex:1;">
                                <div class="order-item-title">${it.name}</div>
                                <div class="order-item-sub" style="display:flex; align-items:center; gap:8px; margin-top:5px;">
                                    <span style="font-weight:600; color:#805f4b;">${storeCurrency}${it.price.toFixed(2)}</span>
                                    <span style="display:inline-flex; align-items:center; border:1px solid #ded5cb; border-radius:6px; overflow:hidden; background:#fff;">
                                        <button type="button" class="cart-tile-minus" data-id="${it.id}" style="padding:2px 8px; border:0; background:#faf8f5; cursor:pointer; font-size:13px;" title="${storeLang === 'en' ? 'Decrease' : 'បន្ថយ'}">&minus;</button>
                                        <span style="padding:2px 10px; font-weight:700; font-size:12.5px; color:#203f3a;">${it.qty}</span>
                                        <button type="button" class="cart-tile-plus" data-id="${it.id}" style="padding:2px 8px; border:0; background:#faf8f5; cursor:pointer; font-size:13px;" title="${storeLang === 'en' ? 'Increase' : 'បន្ថែម'}">&plus;</button>
                                    </span>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div class="order-item-price">${storeCurrency}${(it.price * it.qty).toFixed(2)}</div>
                                <button type="button" class="cart-tile-remove" data-id="${it.id}" style="background:none; border:none; color:#dc2626; font-size:12px; cursor:pointer; padding:4px 0;" title="${storeLang === 'en' ? 'Remove item' : 'លុបចោល'}"><i class="fas fa-trash-can"></i> ${storeLang === 'en' ? 'Remove' : 'លុប'}</button>
                            </div>
                        </div>
                    `).join('');

                    // Bind item controls
                    document.querySelectorAll('.cart-tile-minus').forEach(b => {
                        b.addEventListener('click', () => {
                            const pId = parseInt(b.dataset.id, 10);
                            const found = currentCart.find(x => x.id === pId);
                            if (found) {
                                if (found.qty > 1) {
                                    found.qty--;
                                } else {
                                    currentCart = currentCart.filter(x => x.id !== pId);
                                }
                                localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(currentCart));
                                renderCartList();
                            }
                        });
                    });

                    document.querySelectorAll('.cart-tile-plus').forEach(b => {
                        b.addEventListener('click', () => {
                            const pId = parseInt(b.dataset.id, 10);
                            const found = currentCart.find(x => x.id === pId);
                            if (found) {
                                found.qty++;
                                localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(currentCart));
                                renderCartList();
                            }
                        });
                    });

                    document.querySelectorAll('.cart-tile-remove').forEach(b => {
                        b.addEventListener('click', () => {
                            const pId = parseInt(b.dataset.id, 10);
                            currentCart = currentCart.filter(x => x.id !== pId);
                            localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(currentCart));
                            renderCartList();
                        });
                    });

                    updateTotals();
                }

                renderCartList();
            }

            function updateTotals() {
                const finalTotal = Math.max(0, subtotal - discount);
                subtotalText.textContent = `${storeCurrency}${subtotal.toFixed(2)}`;
                if (discount > 0) {
                    discountRow.style.display = 'flex';
                    discountText.textContent = `-${storeCurrency}${discount.toFixed(2)}`;
                } else {
                    discountRow.style.display = 'none';
                }
                totalText.textContent = `${storeCurrency}${finalTotal.toFixed(2)}`;
                khqrPayAmount.textContent = `${storeCurrency}${finalTotal.toFixed(2)}`;
            }

            // Coupon handling
            const couponInput = document.getElementById('couponCodeInput');
            const btnApplyCoupon = document.getElementById('btnApplyCoupon');
            const couponMsg = document.getElementById('couponStatusMessage');

            if (btnApplyCoupon && couponInput) {
                btnApplyCoupon.addEventListener('click', async () => {
                    const code = couponInput.value.trim();
                    if (!code) return;
                    btnApplyCoupon.disabled = true;
                    btnApplyCoupon.textContent = '...';
                    couponMsg.textContent = '';

                    try {
                        const params = new URLSearchParams();
                        params.append('coupon_code', code);
                        if (isSingleProduct) {
                            params.append('product_id', singleId);
                            params.append('quantity', singleQty);
                        } else {
                            params.append('subtotal', subtotal.toFixed(2));
                        }

                        const res = await fetch('../api/validate-coupon.php', { method: 'POST', body: params });
                        const data = await res.json();
                        if (data.success) {
                            discount = parseFloat(data.discount);
                            hiddenCouponCode.value = data.coupon_code;
                            couponMsg.innerHTML = `<span style="color:#2e7d32;">✅ ${storeLang === 'en' ? 'Discount applied: ' : 'បានបញ្ចុះតម្លៃ '}${storeCurrency}${discount.toFixed(2)}</span>`;
                            updateTotals();
                        } else {
                            couponMsg.innerHTML = `<span style="color:#dc2626;">❌ ${data.message || (storeLang === 'en' ? 'Invalid coupon code' : 'កូដមិនត្រឹមត្រូវ')}</span>`;
                        }
                    } catch (e) {
                        couponMsg.innerHTML = `<span style="color:#dc2626;">❌ ${storeLang === 'en' ? 'Failed to verify coupon code' : 'បរាជ័យក្នុងការផ្ទៀងផ្ទាត់'}</span>`;
                    } finally {
                        btnApplyCoupon.disabled = false;
                        btnApplyCoupon.textContent = 'Apply';
                    }
                });
            }

            // Receipt upload preview
            const receiptFileInput = document.getElementById('checkoutReceiptFile');
            const selectedReceiptName = document.getElementById('selectedReceiptName');
            const receiptPreviewBox = document.getElementById('receiptPreviewBox');
            const receiptPreviewImg = document.getElementById('receiptPreviewImg');

            if (receiptFileInput) {
                receiptFileInput.addEventListener('change', () => {
                    if (receiptFileInput.files && receiptFileInput.files[0]) {
                        const file = receiptFileInput.files[0];
                        selectedReceiptName.textContent = `${storeLang === 'en' ? 'Selected receipt: ' : 'បានជ្រើសរើស: '}${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)`;
                        
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                receiptPreviewImg.src = e.target.result;
                                receiptPreviewBox.style.display = 'block';
                            };
                            reader.readAsDataURL(file);
                        } else {
                            receiptPreviewBox.style.display = 'none';
                        }
                    } else {
                        selectedReceiptName.textContent = '';
                        receiptPreviewBox.style.display = 'none';
                    }
                });
            }

            // Form Submit via AJAX
            const checkoutForm = document.getElementById('standaloneCheckoutForm');
            const submitBtn = document.getElementById('btnSubmitOrder');
            const successModal = document.getElementById('checkoutSuccessModal');
            const successRef = document.getElementById('successOrderRefNumber');

            if (checkoutForm) {
                checkoutForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    if (!receiptFileInput.files || !receiptFileInput.files[0]) {
                        errorMsg.textContent = storeLang === 'en' ? 'Please attach payment receipt before proceeding!' : 'សូមភ្ជាប់រូបភាពវិក្កយបត្របង់ប្រាក់មុននឹងបន្ត!';
                        errorMsg.style.display = 'block';
                        return;
                    }
                    errorMsg.style.display = 'none';
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = storeLang === 'en' ? '<i class="fas fa-spinner fa-spin"></i> Submitting order...' : '<i class="fas fa-spinner fa-spin"></i> កំពុងបញ្ជូនការបញ្ជាទិញ...';

                    try {
                        const formData = new FormData(checkoutForm);
                        const res = await fetch(checkoutForm.action, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (data.success) {
                            // If cart order, clear localStorage cart
                            if (!isSingleProduct) {
                                localStorage.removeItem(CART_STORAGE_KEY);
                            }
                            successRef.textContent = data.order_reference;
                            successModal.hidden = false;
                        } else {
                            errorMsg.textContent = data.message || (storeLang === 'en' ? 'Could not submit order. Please try again.' : 'មិនអាចបញ្ជូនការបញ្ជាទិញបានទេ សូមព្យាយាមម្តងទៀត។');
                            errorMsg.style.display = 'block';
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fab fa-telegram-plane"></i> ' + (storeLang === 'en' ? 'Place Order Now' : 'បញ្ជាក់ការបញ្ជាទិញ (Place Order)');
                        }
                    } catch (err) {
                        errorMsg.textContent = storeLang === 'en' ? 'A technical issue occurred. Please try again.' : 'មានបញ្ហាបច្ចេកទេសក្នុងការបញ្ជូនសំណើ។ សូមព្យាយាមម្តងទៀត។';
                        errorMsg.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fab fa-telegram-plane"></i> បញ្ជាក់ការបញ្ជាទិញ (Place Order)';
                    }
                });
            }
        })();
    </script>
</body>

</html>
