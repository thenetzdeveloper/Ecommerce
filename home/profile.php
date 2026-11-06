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

// Require login
if (!$currentUser) {
    if (!headers_sent()) {
        header('Location: login.php?redirect=profile.php', true, 303);
    } else {
        echo '<script>window.location.href="login.php?redirect=profile.php";</script>';
    }
    exit;
}

$profileStoreSettings = function_exists('getStoreSettings') ? getStoreSettings($conn ?? null) : [];
$storeName = !empty($profileStoreSettings['store_name']) ? (string)$profileStoreSettings['store_name'] : 'SN STORE';
$storeLogoUrl = $profileStoreSettings['store_logo_url'] ?? '';
$storeContactUrl = !empty($profileStoreSettings['store_contact_url']) ? (string)$profileStoreSettings['store_contact_url'] : 'https://t.me/remsonich';

$activeTab = $_GET['tab'] ?? 'info';
if (!in_array($activeTab, ['info', 'orders', 'security'], true)) {
    $activeTab = 'info';
}

$successMsg = '';
$errorMsg = '';

// Handle Profile Updates
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $updateData = [
            'name' => $_POST['name'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'bio' => $_POST['bio'] ?? ''
        ];
        $res = userUpdateProfile($conn, (int)$currentUser['id'], $updateData);
        if ($res['success']) {
            $successMsg = $storeLang === 'en' ? 'Profile updated successfully!' : 'ព័ត៌មានត្រូវបានកែប្រែដោយជោគជ័យ!';
            $currentUser = getCurrentUser($conn); // refresh
        } else {
            $errorMsg = $res['message'];
        }
    } elseif ($action === 'change_password') {
        $activeTab = 'security';
        $oldPass = (string)($_POST['old_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if ($newPass !== $confirmPass) {
            $errorMsg = $storeLang === 'en' ? 'New passwords do not match.' : 'ពាក្យសម្ងាត់ថ្មីទាំងពីរមិនដូចគ្នាទេ។';
        } else {
            $res = userChangePassword($conn, (int)$currentUser['id'], $oldPass, $newPass);
            if ($res['success']) {
                $successMsg = $storeLang === 'en' ? 'Password changed successfully!' : 'ពាក្យសម្ងាត់ត្រូវបានផ្លាស់ប្តូរដោយជោគជ័យ!';
            } else {
                $errorMsg = $res['message'];
            }
        }
    }
}

// Fetch user orders
$userOrders = getUserOrders($conn, (int)$currentUser['id'], $currentUser['phone']);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($storeLang, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('profile_title', 'គណនី និងប្រវត្តិបញ្ជាទិញ', 'My Profile & Orders'); ?> • <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?></title>
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
        .profile-container {
            max-width: 1120px;
            margin: 32px auto 80px;
            padding: 0 clamp(16px, 4vw, 32px);
            position: relative;
            z-index: 10;
        }

        .profile-breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            color: #7d756d;
            margin-bottom: 24px;
        }

        .profile-breadcrumbs a {
            color: #203f3a;
            text-decoration: none;
            font-weight: 600;
        }

        [data-theme="dark"] .profile-breadcrumbs a {
            color: #d8c3b4;
        }

        .profile-layout-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 28px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .profile-layout-grid {
                grid-template-columns: 1fr;
            }
        }

        .profile-card {
            background: #ffffff;
            border: 1px solid #ded5cb;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        [data-theme="dark"] .profile-card {
            background: #171614;
            border-color: #2e2b26;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }

        .user-avatar-wrap {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            overflow: hidden;
            background: #e8e1d8;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #203f3a;
        }

        .user-avatar-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-avatar-initials {
            font-size: 32px;
            font-weight: 700;
            color: #203f3a;
            text-transform: uppercase;
        }

        [data-theme="dark"] .user-avatar-wrap {
            border-color: #805f4b;
            background: #2b2722;
        }

        [data-theme="dark"] .user-avatar-initials {
            color: #d8c3b4;
        }

        .profile-nav-menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ede7df;
        }

        [data-theme="dark"] .profile-nav-menu {
            border-color: #2b2722;
        }

        .profile-nav-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-radius: 8px;
            color: #4a443e;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .profile-nav-item:hover {
            background: #f7f4ef;
            color: #203f3a;
        }

        .profile-nav-item.is-active {
            background: #203f3a;
            color: #ffffff;
        }

        [data-theme="dark"] .profile-nav-item {
            color: #ded5cb;
        }

        [data-theme="dark"] .profile-nav-item:hover {
            background: #24211d;
            color: #f2eee9;
        }

        [data-theme="dark"] .profile-nav-item.is-active {
            background: #805f4b;
            color: #ffffff;
        }

        .profile-field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        @media (max-width: 600px) {
            .profile-field-row {
                grid-template-columns: 1fr;
            }
        }

        .profile-input-group {
            margin-bottom: 18px;
        }

        .profile-input-group label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: #203f3a;
            margin-bottom: 6px;
        }

        [data-theme="dark"] .profile-input-group label {
            color: #ded5cb;
        }

        .profile-input,
        .profile-textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 11px 14px;
            border: 1px solid #ded5cb;
            border-radius: 8px;
            background: #faf8f5;
            color: #1a1918;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
            font-family: inherit;
        }

        .profile-input:focus,
        .profile-textarea:focus {
            border-color: #203f3a;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(32, 63, 58, 0.12);
        }

        [data-theme="dark"] .profile-input,
        [data-theme="dark"] .profile-textarea {
            background: #201e1b;
            border-color: #3b3731;
            color: #f2eee9;
        }

        [data-theme="dark"] .profile-input:focus,
        [data-theme="dark"] .profile-textarea:focus {
            border-color: #805f4b;
            background: #171614;
            box-shadow: 0 0 0 3px rgba(128, 95, 75, 0.25);
        }

        .profile-alert-success {
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #15803d;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        [data-theme="dark"] .profile-alert-success {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            border-color: rgba(74, 222, 128, 0.3);
        }

        .profile-alert-error {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.3);
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        [data-theme="dark"] .profile-alert-error {
            background: rgba(220, 38, 38, 0.2);
            color: #f87171;
            border-color: rgba(248, 113, 113, 0.3);
        }

        .btn-profile-save {
            padding: 12px 28px;
            background: #203f3a;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-profile-save:hover {
            background: #152b27;
            transform: translateY(-1px);
        }

        /* Order Cards */
        .order-card-item {
            background: #faf8f5;
            border: 1px solid #e8e1d8;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 14px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all 0.2s;
        }

        .order-card-item:hover {
            border-color: #203f3a;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        [data-theme="dark"] .order-card-item {
            background: #1f1d1a;
            border-color: #332f29;
        }

        [data-theme="dark"] .order-card-item:hover {
            border-color: #805f4b;
        }

        .order-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-confirmed, .status-paid {
            background: #dcfce7;
            color: #166534;
        }

        .status-shipped {
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>

<body class="fashion-storefront">
    <div class="cyber-grid-lines"></div>

    <?php
    $nav_active = 'profile';
    $nav_home_url = 'index.php';
    $nav_about_url = 'index.php?page=about';
    $nav_support_url = '../customer-service/index.php';
    $nav_payments_url = 'checkout.php';
    $nav_store_name = $storeName;
    $nav_brand_logo = $storeLogoUrl;
    $nav_contact_url = $storeContactUrl;
    require __DIR__ . '/navbar.php';
    ?>

    <div class="profile-container">
        <!-- Breadcrumbs -->
        <div class="profile-breadcrumbs">
            <a href="index.php"><i class="fas fa-house"></i> <?= t('nav_home', 'ទំព័រដើម', 'Home'); ?></a>
            <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
            <span><?= t('profile_title', 'គណនី និងប្រវត្តិបញ្ជាទិញ', 'My Profile & Orders'); ?></span>
        </div>

        <div class="profile-layout-grid">
            <!-- Left User Sidebar -->
            <aside class="profile-card">
                <div class="user-avatar-wrap">
                    <?php if (!empty($currentUser['profile_picture'])): ?>
                        <img src="<?= htmlspecialchars($currentUser['profile_picture'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?>" referrerpolicy="no-referrer">
                    <?php else: ?>
                        <span class="user-avatar-initials"><?= htmlspecialchars(mb_substr($currentUser['name'] ?: 'U', 0, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>

                <div style="text-align: center;">
                    <h3 style="margin: 0 0 4px; font-size: 18px; color: #203f3a; font-family: Georgia, 'Kantumruy Pro', serif;">
                        <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </h3>
                    <p style="margin: 0 0 10px; font-size: 13px; color: #7d756d;">
                        <?= htmlspecialchars($currentUser['email'] ?: $currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                    <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; padding: 3px 10px; border-radius: 20px; background: #eef5f3; color: #1e4d45; font-weight: 700;">
                        <?php if ($currentUser['auth_provider'] === 'google'): ?>
                            <i class="fab fa-google" style="color: #ea4335;"></i> Google Account
                        <?php else: ?>
                            <i class="fas fa-envelope" style="color: #203f3a;"></i> Email Member
                        <?php endif; ?>
                    </div>
                </div>

                <nav class="profile-nav-menu">
                    <a href="profile.php?tab=info" class="profile-nav-item <?= $activeTab === 'info' ? 'is-active' : ''; ?>">
                        <span><i class="fas fa-user-pen" style="width: 20px;"></i> <?= t('profile_tab_info', 'ព័ត៌មានផ្ទាល់ខ្លួន', 'Personal Info'); ?></span>
                        <i class="fas fa-chevron-right" style="font-size: 11px;"></i>
                    </a>
                    <a href="profile.php?tab=orders" class="profile-nav-item <?= $activeTab === 'orders' ? 'is-active' : ''; ?>">
                        <span><i class="fas fa-box-archive" style="width: 20px;"></i> <?= t('profile_tab_orders', 'ប្រវត្តិបញ្ជាទិញ', 'Order History'); ?></span>
                        <span style="font-size: 11px; padding: 2px 7px; border-radius: 10px; background: rgba(0,0,0,0.08); font-weight: 700;"><?= count($userOrders); ?></span>
                    </a>
                    <a href="profile.php?tab=security" class="profile-nav-item <?= $activeTab === 'security' ? 'is-active' : ''; ?>">
                        <span><i class="fas fa-shield-halved" style="width: 20px;"></i> <?= t('profile_tab_security', 'សុវត្ថិភាព & ពាក្យសម្ងាត់', 'Security'); ?></span>
                        <i class="fas fa-chevron-right" style="font-size: 11px;"></i>
                    </a>
                    <a href="logout.php" class="profile-nav-item" style="color: #b91c1c; margin-top: 8px;">
                        <span><i class="fas fa-right-from-bracket" style="width: 20px;"></i> <?= t('nav_logout', 'ចាកចេញ', 'Logout'); ?></span>
                    </a>
                </nav>
            </aside>

            <!-- Right Content Area -->
            <main class="profile-card">
                <?php if (!empty($successMsg)): ?>
                    <div class="profile-alert-success">
                        <i class="fas fa-circle-check"></i>
                        <span><?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="profile-alert-error">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Tab 1: Personal Info & Default Delivery Address -->
                <?php if ($activeTab === 'info'): ?>
                    <div style="margin-bottom: 22px;">
                        <h2 style="font-size: 20px; margin: 0 0 6px; color: #203f3a; font-family: Georgia, 'Kantumruy Pro', serif;">
                            <?= t('profile_tab_info', 'ព័ត៌មានផ្ទាល់ខ្លួន', 'Personal Info'); ?>
                        </h2>
                        <p style="margin: 0; font-size: 13.5px; color: #7d756d;">
                            <?= $storeLang === 'en' ? 'Manage your personal details and default delivery address for instant checkout.' : 'គ្រប់គ្រងព័ត៌មានផ្ទាល់ខ្លួន និងអាសយដ្ឋានដឹកជញ្ជូនលំនាំដើមសម្រាប់ការទូទាត់ប្រាក់រហ័ស។'; ?>
                        </p>
                    </div>

                    <form method="POST" action="profile.php?tab=info">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="profile-field-row">
                            <div class="profile-input-group">
                                <label for="profName"><i class="fas fa-user" style="color: #805f4b; margin-right: 4px;"></i> <?= t('auth_name_label', 'ឈ្មោះពេញរបស់អ្នក *', 'Full Name *'); ?></label>
                                <input type="text" id="profName" name="name" class="profile-input" value="<?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>

                            <div class="profile-input-group">
                                <label for="profEmail"><i class="fas fa-envelope" style="color: #805f4b; margin-right: 4px;"></i> <?= t('auth_email_label', 'អាសយដ្ឋានអ៊ីម៉ែល', 'Email Address'); ?></label>
                                <input type="email" id="profEmail" class="profile-input" value="<?= htmlspecialchars($currentUser['email'] ?: $currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>" disabled style="opacity: 0.7; cursor: not-allowed;">
                            </div>
                        </div>

                        <div class="profile-field-row">
                            <div class="profile-input-group">
                                <label for="profPhone"><i class="fas fa-phone" style="color: #805f4b; margin-right: 4px;"></i> <?= t('auth_phone_label', 'លេខទូរស័ព្ទ / Telegram', 'Phone Number / Telegram'); ?></label>
                                <input type="text" id="profPhone" name="phone" class="profile-input" value="<?= htmlspecialchars($currentUser['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?= htmlspecialchars(t('auth_phone_ph', 'ឧទាហរណ៍: 012 345 678', 'e.g. 012 345 678'), ENT_QUOTES, 'UTF-8'); ?>">
                            </div>

                            <div class="profile-input-group">
                                <label for="profCity"><i class="fas fa-city" style="color: #805f4b; margin-right: 4px;"></i> <?= $storeLang === 'en' ? 'City / Province' : 'រាជធានី / ខេត្ត'; ?></label>
                                <input type="text" id="profCity" name="city" class="profile-input" value="<?= htmlspecialchars($currentUser['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?= $storeLang === 'en' ? 'e.g. Phnom Penh' : 'ឧទាហរណ៍: រាជធានីភ្នំពេញ'; ?>">
                            </div>
                        </div>

                        <div class="profile-input-group">
                            <label for="profAddress"><i class="fas fa-location-dot" style="color: #805f4b; margin-right: 4px;"></i> <?= t('profile_address_label', 'អាសយដ្ឋានដឹកជញ្ជូនលំនាំដើម', 'Default Delivery Address'); ?></label>
                            <textarea id="profAddress" name="address" class="profile-textarea" rows="3" placeholder="<?= htmlspecialchars(t('profile_address_ph', 'ផ្ទះលេខ, ផ្លូវ, សង្កាត់, ខណ្ឌ, រាជធានី/ខេត្ត...', 'House, Street, Sangkat, Khan, City/Province...'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($currentUser['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="profile-input-group">
                            <label for="profBio"><i class="fas fa-pencil" style="color: #805f4b; margin-right: 4px;"></i> <?= $storeLang === 'en' ? 'Delivery Note / Bio' : 'កំណត់សម្គាល់បន្ថែម ឬការពិពណ៌នា'; ?></label>
                            <textarea id="profBio" name="bio" class="profile-textarea" rows="2" placeholder="<?= $storeLang === 'en' ? 'Special instructions for delivery...' : 'ព័ត៌មានបន្ថែមសម្រាប់ការដឹកជញ្ជូន...'; ?>"><?= htmlspecialchars($currentUser['bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <button type="submit" class="btn-profile-save">
                            <i class="fas fa-floppy-disk"></i> <?= t('profile_btn_save', 'រក្សាទុកព័ត៌មាន', 'Save Profile'); ?>
                        </button>
                    </form>

                <!-- Tab 2: My Orders -->
                <?php elseif ($activeTab === 'orders'): ?>
                    <div style="margin-bottom: 22px;">
                        <h2 style="font-size: 20px; margin: 0 0 6px; color: #203f3a; font-family: Georgia, 'Kantumruy Pro', serif;">
                            <?= t('profile_tab_orders', 'ប្រវត្តិបញ្ជាទិញ', 'Order History'); ?>
                        </h2>
                        <p style="margin: 0; font-size: 13.5px; color: #7d756d;">
                            <?= $storeLang === 'en' ? 'Track all your previous orders, live order statuses, and receipts.' : 'តាមដានរាល់ការបញ្ជាទិញមុនៗ ស្ថានភាពបច្ចុប្បន្ន និងវិក្កយបត្ររបស់អ្នក។'; ?>
                        </p>
                    </div>

                    <?php if (empty($userOrders)): ?>
                        <div style="text-align: center; padding: 48px 20px;">
                            <div style="font-size: 42px; color: #c8bfb4; margin-bottom: 12px;"><i class="fas fa-box-open"></i></div>
                            <h4 style="font-size: 16px; margin: 0 0 8px; color: #203f3a;"><?= t('profile_no_orders', 'លោកអ្នកមិនទាន់មានការបញ្ជាទិញនៅឡើយទេ', 'You have not placed any orders yet.'); ?></h4>
                            <p style="font-size: 13.5px; color: #7d756d; max-width: 400px; margin: 0 auto 20px;">
                                <?= $storeLang === 'en' ? 'Explore our curated collection and enjoy 100% genuine quality with Bakong KHQR payment.' : 'ស្វែងរកផលិតផលសម្លៀកបំពាក់ និងម៉ូដទាន់សម័យគុណភាពខ្ពស់ 100% ជាមួយការទូទាត់ប្រាក់ KHQR។'; ?>
                            </p>
                            <a href="index.php" class="btn-profile-save" style="text-decoration: none;">
                                <i class="fas fa-bag-shopping"></i> <?= t('profile_btn_shop_now', 'ទៅកាន់កាតាឡុកទំនិញ', 'Start Shopping'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="orders-list">
                            <?php foreach ($userOrders as $ord): ?>
                                <?php
                                $status = strtolower($ord['status'] ?? 'pending');
                                $statusClass = 'status-pending';
                                if (in_array($status, ['confirmed', 'paid', 'completed'])) $statusClass = 'status-confirmed';
                                elseif ($status === 'shipped') $statusClass = 'status-shipped';
                                elseif ($status === 'cancelled') $statusClass = 'status-cancelled';
                                ?>
                                <article class="order-card-item">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
                                        <div>
                                            <span style="font-size: 11px; text-transform: uppercase; color: #7d756d; font-weight: 700; letter-spacing: 0.5px;"><?= t('profile_order_ref', 'លេខកូដបញ្ជាទិញ', 'Order Reference'); ?></span>
                                            <div style="font-family: monospace; font-size: 16px; font-weight: 700; color: #203f3a;">
                                                <?= htmlspecialchars($ord['order_reference'] ?: '#' . $ord['id'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span class="order-status-badge <?= $statusClass; ?>">
                                                <i class="fas fa-circle" style="font-size: 6px;"></i>
                                                <?= htmlspecialchars(ucfirst($ord['status']), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <span style="font-size: 16px; font-weight: 700; color: #203f3a;">
                                                $<?= number_format((float)($ord['unit_price'] * $ord['quantity'] - ($ord['discount_amount'] ?? 0)), 2); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div style="font-size: 14px; color: #4a443e; padding: 10px 0; border-top: 1px solid #ede7df; border-bottom: 1px solid #ede7df; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                        <div>
                                            <strong><?= htmlspecialchars($ord['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span style="color: #7d756d; font-size: 13px;">(x<?= (int)$ord['quantity']; ?>)</span>
                                        </div>
                                        <div style="font-size: 12.5px; color: #7d756d;">
                                            <i class="far fa-calendar-alt" style="margin-right: 4px;"></i> <?= htmlspecialchars(date('M d, Y • h:i A', strtotime($ord['created_at'])), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; color: #7d756d;">
                                        <div>
                                            <i class="fas fa-location-dot" style="margin-right: 4px; color: #805f4b;"></i>
                                            <?= htmlspecialchars($ord['location'] ?: 'Phnom Penh', ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-credit-card" style="margin-right: 4px;"></i> Bakong KHQR
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <!-- Tab 3: Security & Password -->
                <?php elseif ($activeTab === 'security'): ?>
                    <div style="margin-bottom: 22px;">
                        <h2 style="font-size: 20px; margin: 0 0 6px; color: #203f3a; font-family: Georgia, 'Kantumruy Pro', serif;">
                            <?= t('profile_tab_security', 'សុវត្ថិភាព & ពាក្យសម្ងាត់', 'Security'); ?>
                        </h2>
                        <p style="margin: 0; font-size: 13.5px; color: #7d756d;">
                            <?= $storeLang === 'en' ? 'Manage your account password and authentication settings.' : 'គ្រប់គ្រងពាក្យសម្ងាត់គណនី និងការកំណត់សុវត្ថិភាព។'; ?>
                        </p>
                    </div>

                    <?php if ($currentUser['auth_provider'] === 'google'): ?>
                        <div style="padding: 20px; border-radius: 8px; background: #eef5f3; border: 1px solid #c2ded9; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 6px; color: #1e4d45; display: flex; align-items: center; gap: 8px;">
                                <i class="fab fa-google" style="color: #ea4335;"></i> Google Authentication
                            </h4>
                            <p style="margin: 0; font-size: 13.5px; color: #3b5f58; line-height: 1.6;">
                                <?= $storeLang === 'en' ? 'Your account is securely authenticated using your Google account (' . htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') . '). Password changes are managed via Google.' : 'គណនីរបស់អ្នកត្រូវបានភ្ជាប់សុវត្ថិភាពជាមួយគណនី Google (' . htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') . ')។ ការផ្លាស់ប្តូរពាក្យសម្ងាត់ត្រូវបានគ្រប់គ្រងតាម Google។'; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="profile.php?tab=security" style="max-width: 480px;">
                            <input type="hidden" name="action" value="change_password">

                            <div class="profile-input-group">
                                <label for="oldPass"><?= t('profile_old_pass', 'ពាក្យសម្ងាត់បច្ចុប្បន្ន', 'Current Password'); ?> *</label>
                                <input type="password" id="oldPass" name="old_password" class="profile-input" required>
                            </div>

                            <div class="profile-input-group">
                                <label for="newPass"><?= t('profile_new_pass', 'ពាក្យសម្ងាត់ថ្មី', 'New Password'); ?> *</label>
                                <input type="password" id="newPass" name="new_password" class="profile-input" minlength="6" required>
                            </div>

                            <div class="profile-input-group">
                                <label for="confirmPass"><?= $storeLang === 'en' ? 'Confirm New Password *' : 'បញ្ជាក់ពាក្យសម្ងាត់ថ្មី *'; ?></label>
                                <input type="password" id="confirmPass" name="confirm_password" class="profile-input" minlength="6" required>
                            </div>

                            <button type="submit" class="btn-profile-save">
                                <i class="fas fa-key"></i> <?= t('profile_change_pass', 'ផ្លាស់ប្តូរពាក្យសម្ងាត់', 'Change Password'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>

</html>

