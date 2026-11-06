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
$loginStoreSettings = function_exists('getStoreSettings') ? getStoreSettings($conn ?? null) : [];
$storeName = !empty($loginStoreSettings['store_name']) ? (string)$loginStoreSettings['store_name'] : 'SN STORE';
$storeLogoUrl = $loginStoreSettings['store_logo_url'] ?? '';
$storeContactUrl = !empty($loginStoreSettings['store_contact_url']) ? (string)$loginStoreSettings['store_contact_url'] : 'https://t.me/remsonich';
$googleClientId = trim((string)($loginStoreSettings['google_client_id'] ?? ''));

if ($googleClientId === '') {
    $googleClientId = trim((string)(getenv('GOOGLE_CLIENT_ID') ?: ''));
}

// Redirect target
$redirectUrl = filter_var($_GET['redirect'] ?? 'index.php', FILTER_SANITIZE_URL);
if (empty($redirectUrl) || preg_match('/^(?:https?:)?\/\//i', $redirectUrl)) {
    $redirectUrl = 'index.php';
}

// If already logged in, redirect to profile or intended page
if (userIsLoggedIn($conn)) {
    header('Location: ' . ($redirectUrl !== 'index.php' ? $redirectUrl : 'profile.php'), true, 303);
    exit;
}

$mode = isset($_GET['mode']) && $_GET['mode'] === 'register' ? 'register' : 'login';
$error = '';
$success = '';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Google One-Tap / Button Sign-In
    if ($action === 'google_auth') {
        $credential = (string)($_POST['credential'] ?? '');
        if ($credential !== '') {
            $res = userGoogleLogin($conn, $credential);
            if ($res['success']) {
                header('Location: ' . $redirectUrl, true, 303);
                exit;
            } else {
                $error = $res['message'];
            }
        } else {
            $error = 'Missing Google credentials.';
        }
    }
    // 2. Standard Email Login
    elseif ($action === 'login') {
        $email = (string)($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        $res = userLogin($conn, $email, $password, $remember);
        if ($res['success']) {
            header('Location: ' . $redirectUrl, true, 303);
            exit;
        } else {
            $error = $res['message'];
        }
    }
    // 3. Register
    elseif ($action === 'register') {
        $name = (string)($_POST['name'] ?? '');
        $email = (string)($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $phone = (string)($_POST['phone'] ?? '');

        $res = userRegister($conn, $email, $password, $name, $phone);
        if ($res['success']) {
            header('Location: ' . $redirectUrl, true, 303);
            exit;
        } else {
            $mode = 'register';
            $error = $res['message'];
        }
    }
}

// Google Client ID is loaded dynamically from system_settings or env
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($storeLang, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'register' ? t('auth_register_title', 'បង្កើតគណនីថ្មី', 'Create an Account') : t('auth_login_title', 'ចូលប្រើប្រាស់គណនី', 'Sign In'); ?> • <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?></title>
    <!-- Icons & Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="./logo/logome.png" type="image/x-icon">
    <!-- Storefront Stylesheets -->
    <link rel="stylesheet" href="./page.css?v=<?= filemtime(__DIR__ . '/page.css'); ?>">
    <link rel="stylesheet" href="./fashion-theme.css?v=<?= filemtime(__DIR__ . '/fashion-theme.css'); ?>">
    <link rel="stylesheet" href="../css/theme-mode.css?v=<?= filemtime(__DIR__ . '/../css/theme-mode.css'); ?>">
    <script src="../js/page-navigation.js?v=<?= filemtime(__DIR__ . '/../js/page-navigation.js'); ?>" defer></script>
    <script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js'); ?>" defer></script>
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        .auth-container {
            max-width: 480px;
            margin: 40px auto 80px;
            padding: 0 clamp(16px, 4vw, 24px);
            position: relative;
            z-index: 10;
        }

        .auth-card {
            background: #ffffff;
            border: 1px solid #ded5cb;
            border-radius: 12px;
            padding: clamp(24px, 5vw, 36px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        }

        [data-theme="dark"] .auth-card {
            background: #171614;
            border-color: #2e2b26;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .auth-tabs {
            display: flex;
            border-bottom: 2px solid #ede7df;
            margin-bottom: 24px;
        }

        [data-theme="dark"] .auth-tabs {
            border-color: #2b2722;
        }

        .auth-tab-btn {
            flex: 1;
            text-align: center;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: 700;
            color: #7d756d;
            background: transparent;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .auth-tab-btn.is-active {
            color: #203f3a;
            border-bottom-color: #203f3a;
        }

        [data-theme="dark"] .auth-tab-btn.is-active {
            color: #d8c3b4;
            border-bottom-color: #805f4b;
        }

        .auth-google-box {
            margin-bottom: 20px;
        }

        .btn-google-custom {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 12px 18px;
            background: #ffffff;
            border: 1px solid #ded5cb;
            border-radius: 8px;
            color: #3c4043;
            font-size: 14.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-google-custom:hover {
            background: #f8f6f2;
            border-color: #c4b9ac;
        }

        [data-theme="dark"] .btn-google-custom {
            background: #201e1b;
            border-color: #3b3731;
            color: #e5ded7;
        }

        [data-theme="dark"] .btn-google-custom:hover {
            background: #2a2723;
            border-color: #4a453e;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: #9c948b;
            font-size: 12.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e6dfd5;
        }

        [data-theme="dark"] .auth-divider::before,
        [data-theme="dark"] .auth-divider::after {
            background: #2b2722;
        }

        .auth-input-group {
            margin-bottom: 18px;
        }

        .auth-input-group label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: #203f3a;
            margin-bottom: 6px;
        }

        [data-theme="dark"] .auth-input-group label {
            color: #ded5cb;
        }

        .auth-input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 14px;
            border: 1px solid #ded5cb;
            border-radius: 8px;
            background: #faf8f5;
            color: #1a1918;
            font-size: 14.5px;
            transition: all 0.2s;
            outline: none;
            font-family: inherit;
        }

        .auth-input:focus {
            border-color: #203f3a;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(32, 63, 58, 0.12);
        }

        [data-theme="dark"] .auth-input {
            background: #201e1b;
            border-color: #3b3731;
            color: #f2eee9;
        }

        [data-theme="dark"] .auth-input:focus {
            border-color: #805f4b;
            background: #171614;
            box-shadow: 0 0 0 3px rgba(128, 95, 75, 0.25);
        }

        .btn-auth-submit {
            width: 100%;
            padding: 14px 20px;
            background: #203f3a;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-auth-submit:hover {
            background: #152b27;
            transform: translateY(-1px);
        }

        .auth-alert-error {
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

        [data-theme="dark"] .auth-alert-error {
            background: rgba(220, 38, 38, 0.2);
            color: #f87171;
            border-color: rgba(248, 113, 113, 0.3);
        }
    </style>
</head>

<body class="fashion-storefront">
    <div class="cyber-grid-lines"></div>

    <?php
    $nav_active = 'auth';
    $nav_home_url = 'index.php';
    $nav_about_url = 'index.php?page=about';
    $nav_support_url = '../customer-service/index.php';
    $nav_payments_url = 'checkout.php';
    $nav_store_name = $storeName;
    $nav_brand_logo = $storeLogoUrl;
    $nav_contact_url = $storeContactUrl;
    require __DIR__ . '/navbar.php';
    ?>

    <div class="auth-container">
        <div class="auth-card">
            <!-- Tabs: Login vs Register -->
            <div class="auth-tabs">
                <a href="login.php?mode=login<?= $redirectUrl !== 'index.php' ? '&redirect=' . urlencode($redirectUrl) : ''; ?>" class="auth-tab-btn <?= $mode === 'login' ? 'is-active' : ''; ?>">
                    <i class="fas fa-right-to-bracket"></i> <?= t('auth_btn_login', 'ចូលគណនី', 'Sign In'); ?>
                </a>
                <a href="login.php?mode=register<?= $redirectUrl !== 'index.php' ? '&redirect=' . urlencode($redirectUrl) : ''; ?>" class="auth-tab-btn <?= $mode === 'register' ? 'is-active' : ''; ?>">
                    <i class="fas fa-user-plus"></i> <?= t('auth_btn_register', 'បង្កើតគណនី', 'Create Account'); ?>
                </a>
            </div>

            <div style="text-align: center; margin-bottom: 22px;">
                <h2 style="font-size: 22px; margin: 0 0 6px; color: #203f3a; font-family: Georgia, 'Kantumruy Pro', serif;">
                    <?= $mode === 'register' ? t('auth_register_title', 'បង្កើតគណនីថ្មី', 'Create an Account') : t('auth_login_title', 'ចូលប្រើប្រាស់គណនី', 'Welcome Back'); ?>
                </h2>
                <p style="font-size: 13.5px; color: #7d756d; margin: 0; line-height: 1.5;">
                    <?= $mode === 'register' ? t('auth_register_subtitle', 'ចុះឈ្មោះដើម្បីទទួលបានបទពិសោធន៍ទិញទំនិញដ៏ងាយស្រួល', 'Sign up to enjoy faster checkout and track your orders') : t('auth_login_subtitle', 'ចូលគណនីរបស់អ្នកដើម្បីពិនិត្យការបញ្ជាទិញ និងទិញទំនិញកាន់តែរហ័ស', 'Sign in to your account to track orders and checkout faster'); ?>
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="auth-alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Google Sign-In Section -->
            <div class="auth-google-box">
                <?php if (!empty($googleClientId)): ?>
                    <!-- Standard Google GIS button container -->
                    <div id="g_id_onload"
                        data-client_id="<?= htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8'); ?>"
                        data-callback="handleGoogleCredentialResponse"
                        data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin"
                        data-type="standard"
                        data-size="large"
                        data-theme="outline"
                        data-text="continue_with"
                        data-shape="rectangular"
                        data-logo_alignment="left"
                        data-width="100%">
                    </div>
                <?php else: ?>
                    <button type="button" class="btn-google-unconfigured" onclick="alert('<?= $storeLang === 'en' ? 'Google Sign-In is ready! Please configure your Google OAuth Client ID in Dashboard Admin -> Store Settings to activate 1-click Google Login.' : 'មុខងារ Google Sign-In រួចរាល់ហើយ! សូមបញ្ចូល Google OAuth Client ID របស់អ្នកនៅក្នុង Dashboard Admin -> Store Settings ដើម្បីដំណើរការ។'; ?>')" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 12px 16px; border: 1px solid #ded5cb; border-radius: 8px; background: #ffffff; color: #3c4043; font-weight: 600; font-size: 14.5px; cursor: pointer; transition: all 0.2s;">
                        <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.55 10.78l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                        <span><?= t('auth_google_btn', 'ចូលគណនីជាមួយ Google', 'Continue with Google'); ?></span>
                    </button>
                <?php endif; ?>

                <!-- Custom Google Button Fallback / Interactive Trigger -->
                <form id="googleAuthForm" method="POST" action="login.php<?= $redirectUrl !== 'index.php' ? '?redirect=' . urlencode($redirectUrl) : ''; ?>" style="display:none;">
                    <input type="hidden" name="action" value="google_auth">
                    <input type="hidden" name="credential" id="googleCredentialInput">
                </form>
            </div>

            <div class="auth-divider">
                <span><?= t('auth_or_divider', 'ឬប្រើអ៊ីម៉ែល', 'or with email'); ?></span>
            </div>

            <!-- Login Form -->
            <?php if ($mode === 'login'): ?>
                <form method="POST" action="login.php<?= $redirectUrl !== 'index.php' ? '?redirect=' . urlencode($redirectUrl) : ''; ?>">
                    <input type="hidden" name="action" value="login">

                    <div class="auth-input-group">
                        <label for="inputEmail"><i class="fas fa-envelope" style="color: #805f4b; margin-right: 4px;"></i> <?= t('auth_email_label', 'អាសយដ្ឋានអ៊ីម៉ែល (Email / Gmail) *', 'Email Address *'); ?></label>
                        <input type="email" id="inputEmail" name="email" class="auth-input" placeholder="<?= htmlspecialchars(t('auth_email_ph', 'ឧទាហរណ៍: name@gmail.com', 'e.g. name@gmail.com'), ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
                    </div>

                    <div class="auth-input-group">
                        <label for="inputPassword"><i class="fas fa-lock" style="color: #805f4b; margin-right: 4px;"></i> <?= t('auth_pass_label', 'ពាក្យសម្ងាត់ (Password) *', 'Password *'); ?></label>
                        <input type="password" id="inputPassword" name="password" class="auth-input" placeholder="<?= htmlspecialchars(t('auth_pass_ph', 'យ៉ាងហោចណាស់ ៦ តួអក្សរ', 'At least 6 characters'), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; font-size: 13.5px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; color: #5a544e;">
                            <input type="checkbox" name="remember" value="1" checked style="accent-color: #203f3a;">
                            <span><?= t('auth_remember_me', 'ចងចាំគណនីខ្ញុំ', 'Remember me'); ?></span>
                        </label>
                    </div>

                    <button type="submit" class="btn-auth-submit">
                        <i class="fas fa-right-to-bracket"></i> <?= t('auth_btn_login', 'ចូលគណនី', 'Sign In'); ?>
                    </button>
                </form>

                <div style="text-align: center; margin-top: 24px; font-size: 13.5px; color: #7d756d;">
                    <?= t('auth_no_account', 'មិនទាន់មានគណនីមែនទេ?', "Don't have an account?"); ?>
                    <a href="login.php?mode=register<?= $redirectUrl !== 'index.php' ? '&redirect=' . urlencode($redirectUrl) : ''; ?>" style="color: #203f3a; font-weight: 700; text-decoration: none; margin-left: 4px;">
                        <?= t('auth_switch_register', 'ចុះឈ្មោះឥឡូវនេះ', 'Register now'); ?> &rarr;
                    </a>
                </div>

            <!-- Register Form -->
            <?php else: ?>
                <form method="POST" action="login.php?mode=register<?= $redirectUrl !== 'index.php' ? '&redirect=' . urlencode($redirectUrl) : ''; ?>">
                    <input type="hidden" name="action" value="register">

                    <div class="auth-input-group">
                        <label for="regName"><i class="fas fa-user" style="color: #805f4b; margin-right: 4px;"></i> <?= t('auth_name_label', 'ឈ្មោះពេញរបស់អ្នក *', 'Full Name *'); ?></label>
                        <input type="text" id="regName" name="name" class="auth-input" placeholder="<?= htmlspecialchars(t('auth_name_ph', 'ឧទាហរណ៍: សុខា', 'e.g. Sokha Chan'), ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
                    </div>

                    <div class="auth-input-group">
                        <label for="regEmail"><i class="fas fa-envelope" style="color: #805f4b; margin-right: 4px;"></i> <?= t('auth_email_label', 'អាសយដ្ឋានអ៊ីម៉ែល (Email / Gmail) *', 'Email Address *'); ?></label>
                        <input type="email" id="regEmail" name="email" class="auth-input" placeholder="<?= htmlspecialchars(t('auth_email_ph', 'ឧទាហរណ៍: name@gmail.com', 'e.g. name@gmail.com'), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="auth-input-group">
                        <label for="regPhone"><i class="fas fa-phone" style="color: #805f4b; margin-right: 4px;"></i> <?= t('auth_phone_label', 'លេខទូរស័ព្ទ / Telegram', 'Phone Number / Telegram'); ?></label>
                        <input type="text" id="regPhone" name="phone" class="auth-input" placeholder="<?= htmlspecialchars(t('auth_phone_ph', 'ឧទាហរណ៍: 012 345 678', 'e.g. 012 345 678'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="auth-input-group">
                        <label for="regPassword"><i class="fas fa-lock" style="color: #805f4b; margin-right: 4px;"></i> <?= t('auth_pass_label', 'ពាក្យសម្ងាត់ (Password) *', 'Password *'); ?></label>
                        <input type="password" id="regPassword" name="password" class="auth-input" minlength="6" placeholder="<?= htmlspecialchars(t('auth_pass_ph', 'យ៉ាងហោចណាស់ ៦ តួអក្សរ', 'At least 6 characters'), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <button type="submit" class="btn-auth-submit">
                        <i class="fas fa-user-plus"></i> <?= t('auth_btn_register', 'បង្កើតគណនី', 'Create Account'); ?>
                    </button>
                </form>

                <div style="text-align: center; margin-top: 24px; font-size: 13.5px; color: #7d756d;">
                    <?= t('auth_have_account', 'មានគណនីរួចហើយ?', 'Already have an account?'); ?>
                    <a href="login.php?mode=login<?= $redirectUrl !== 'index.php' ? '&redirect=' . urlencode($redirectUrl) : ''; ?>" style="color: #203f3a; font-weight: 700; text-decoration: none; margin-left: 4px;">
                        <?= t('auth_switch_login', 'ចូលគណនីឥឡូវនេះ', 'Sign in now'); ?> &rarr;
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function handleGoogleCredentialResponse(response) {
            if (response && response.credential) {
                document.getElementById('googleCredentialInput').value = response.credential;
                document.getElementById('googleAuthForm').submit();
            }
        }
    </script>
</body>

</html>

