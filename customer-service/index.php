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

if (isset($_GET['admin']) || (isset($_GET['page']) && $_GET['page'] === 'admin')) {
    header('Location: ../admin/index.php');
    exit;
}

$systemSettings = function_exists('getStoreSettings') ? getStoreSettings($conn ?? null) : [];
$storeName = !empty($systemSettings['store_name']) ? (string)$systemSettings['store_name'] : 'SN STORE';
$storeContactUrl = !empty($systemSettings['store_contact_url']) ? (string)$systemSettings['store_contact_url'] : 'https://t.me/remsonich';
$telegramUsername = !empty($systemSettings['telegram_username']) ? (string)$systemSettings['telegram_username'] : '@remsonich';
$storeLogoUrl = $systemSettings['store_logo_url'] ?? '';
$supportPhone = $systemSettings['support_phone'] ?? '';
$supportHours = $systemSettings['support_hours'] ?? '';
$storeLocation = $systemSettings['store_location'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($storeLang, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $storeLang === "en" ? "Customer Support • " . htmlspecialchars($storeName, ENT_QUOTES, "UTF-8") : "ជំនួយអតិថិជន • " . htmlspecialchars($storeName, ENT_QUOTES, "UTF-8"); ?></title>
    <!-- Icons & Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="../home/logo/logome.png" type="image/x-icon">
    <!-- Storefront Stylesheets -->
    <link rel="stylesheet" href="../home/page.css?v=<?= filemtime(__DIR__ . '/../home/page.css'); ?>">
    <link rel="stylesheet" href="../home/fashion-theme.css?v=<?= filemtime(__DIR__ . '/../home/fashion-theme.css'); ?>">
    <link rel="stylesheet" href="../css/theme-mode.css?v=<?= filemtime(__DIR__ . '/../css/theme-mode.css'); ?>">
    <script src="../js/page-navigation.js?v=<?= filemtime(__DIR__ . '/../js/page-navigation.js'); ?>" defer></script>
    <script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js'); ?>" defer></script>
    <style>
        .support-page-container {
            max-width: 1180px;
            margin: 32px auto 80px;
            padding: 0 clamp(16px, 4vw, 36px);
            position: relative;
            z-index: 10;
        }

        .support-breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        .support-breadcrumbs a {
            color: #805f4b;
            text-decoration: none;
            font-weight: 600;
        }

        .support-breadcrumbs a:hover {
            text-decoration: underline;
        }

        .support-layout-grid {
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 28px;
            align-items: start;
        }

        .support-box {
            background: #ffffff;
            border: 1px solid #e8e1d8;
            border-radius: 14px;
            padding: 32px 30px;
            box-shadow: 0 6px 25px rgba(48, 41, 35, 0.04);
        }

        .support-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 14px;
            border-radius: 9999px;
            background: #faf8f5;
            border: 1px solid #ded5cb;
            color: #805f4b;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .support-box h2 {
            font-family: Georgia, 'Kantumruy Pro', serif;
            font-size: clamp(22px, 3vw, 28px);
            font-weight: 700;
            color: #203f3a;
            margin: 0 0 10px;
            line-height: 1.35;
        }

        .support-box p.lead {
            font-size: 14px;
            color: #756d64;
            line-height: 1.75;
            margin: 0 0 24px;
        }

        .support-channel-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 28px;
        }

        .support-channel-card {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px;
            border-radius: 10px;
            background: #faf8f5;
            border: 1px solid #e8e1d8;
            transition: all 0.2s ease;
        }

        .support-channel-card:hover {
            border-color: #805f4b;
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(48, 41, 35, 0.06);
        }

        .channel-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #f4efe9;
            color: #805f4b;
            display: grid;
            place-items: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .channel-info h4 {
            font-size: 14px;
            font-weight: 700;
            color: #203f3a;
            margin: 0 0 3px;
        }

        .channel-info p {
            font-size: 12.5px;
            color: #756d64;
            margin: 0 0 4px;
            line-height: 1.5;
        }

        .channel-info a {
            font-size: 13px;
            color: #805f4b;
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .channel-info a:hover {
            text-decoration: underline;
        }

        .support-form-field {
            margin-bottom: 20px;
        }

        .support-form-field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #302923;
            margin-bottom: 8px;
        }

        .support-form-input,
        .support-form-textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px 16px;
            border: 1.5px solid #ded5cb;
            border-radius: 8px;
            background: #faf8f5;
            color: #2e2924;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
        }

        .support-form-input:focus,
        .support-form-textarea:focus {
            border-color: #805f4b;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(128, 95, 75, 0.12);
        }

        .support-form-textarea {
            min-height: 130px;
            resize: vertical;
        }

        .btn-send-message {
            width: 100%;
            padding: 14px 20px;
            border: 0;
            border-radius: 8px;
            background: #203f3a;
            color: #ffffff;
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background 0.2s ease, transform 0.15s ease;
        }

        .btn-send-message:hover {
            background: #2d564f;
            transform: translateY(-1px);
        }

        .support-alert {
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .support-alert-success {
            background: #eaf5ea;
            border: 1px solid #a3d9a5;
            color: #2e7d32;
        }

        .support-alert-error {
            background: #fde8e8;
            border: 1px solid #f8b4b4;
            color: #c81e1e;
        }

        @media (max-width: 860px) {
            .support-layout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Dark Mode overrides */
        [data-theme="dark"] .support-box {
            background: #171d28 !important;
            border-color: #343c4a !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
        }
        [data-theme="dark"] .support-kicker {
            background: #10151e !important;
            border-color: #343c4a !important;
            color: #c29b88 !important;
        }
        [data-theme="dark"] .support-box h2,
        [data-theme="dark"] .support-form-field label,
        [data-theme="dark"] .channel-info h4 {
            color: #edf1f7 !important;
        }
        [data-theme="dark"] .support-box p.lead,
        [data-theme="dark"] .channel-info p {
            color: #b0bac8 !important;
        }
        [data-theme="dark"] .support-channel-card {
            background: #10151e !important;
            border-color: #343c4a !important;
        }
        [data-theme="dark"] .channel-icon-wrap {
            background: #1f2735 !important;
            color: #c29b88 !important;
        }
        [data-theme="dark"] .support-form-input,
        [data-theme="dark"] .support-form-textarea {
            background: #10151e !important;
            border-color: #343c4a !important;
            color: #edf1f7 !important;
        }
        [data-theme="dark"] .support-form-input:focus,
        [data-theme="dark"] .support-form-textarea:focus {
            border-color: #805f4b !important;
        }
        [data-theme="dark"] .support-alert-success {
            background: rgba(46, 125, 50, 0.2) !important;
            border-color: rgba(74, 222, 128, 0.4) !important;
            color: #4ade80 !important;
        }
        [data-theme="dark"] .support-alert-error {
            background: rgba(220, 38, 38, 0.2) !important;
            border-color: rgba(248, 113, 113, 0.4) !important;
            color: #f87171 !important;
        }
    </style>
</head>

<body class="fashion-storefront">
    <div class="cyber-grid-lines"></div>

    <?php
    $nav_active = 'support';
    $nav_home_url = '../home/index.php';
    $nav_about_url = '../home/index.php?page=about';
    $nav_support_url = 'index.php';
    $nav_payments_url = '../home/checkout.php';
    $nav_store_name = $storeName;
    $nav_brand_logo = $storeLogoUrl;
    $nav_contact_url = $storeContactUrl;
    require __DIR__ . '/../home/navbar.php';
    ?>

    <div class="support-page-container">
        <div class="support-breadcrumbs">
            <a href="../home/index.php"><i class="fas fa-house"></i> <?= t('nav_home', 'ទំព័រដើម', 'Home'); ?></a>
            <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
            <span><?= t('support_title', 'ជំនួយ & សេវាកម្មអតិថិជន', 'Customer Support'); ?></span>
        </div>

        <div class="support-layout-grid">
            <!-- Left Info Column -->
            <div class="support-box">
                <span class="support-kicker"><i class="fas fa-headset"></i> <?= t('support_badge_pill', 'សេវាកម្មអតិថិជន 24/7', 'Customer Support 24/7'); ?></span>
                <h2><?= t('support_hero_title', 'យើងនៅទីនេះដើម្បីជួយអ្នក', 'We Are Here to Help You'); ?></h2>
                <p class="lead">
                    <?= t('support_lead', 'មានសំណួរអំពីផលិតផល ការកុម្ម៉ង់ទិញ ការទូទាត់ប្រាក់ KHQR ឬការដឹកជញ្ជូនមែនទេ? សូមទាក់ទងមកកាន់យើងតាមបណ្តាញខាងក្រោម ឬផ្ញើសារតាមទម្រង់។', 'Have questions regarding sizing, order status, KHQR payments, or shipping? Contact us below or send a message.'); ?>
                </p>

                <div class="support-channel-list">
                    <div class="support-channel-card">
                        <div class="channel-icon-wrap">
                            <i class="fab fa-telegram"></i>
                        </div>
                        <div class="channel-info">
                            <h4><?= t('support_tg_title', 'Telegram Support', 'Telegram Support'); ?></h4>
                            <p><?= t('support_tg_desc', 'ឆ្លើយតបរហ័សទាន់ចិត្ត ក្រោម 10 នាទី', 'Fast direct reply in under 10 minutes'); ?></p>
                            <a href="<?= htmlspecialchars($storeContactUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fas fa-paper-plane"></i> <?= htmlspecialchars($telegramUsername, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>
                    </div>

                    <?php if (!empty($supportPhone)): ?>
                    <div class="support-channel-card">
                        <div class="channel-icon-wrap">
                            <i class="fas fa-phone-volume"></i>
                        </div>
                        <div class="channel-info">
                            <h4><?= t('support_phone_title', 'លេខទូរស័ព្ទផ្ទាល់', 'Direct Phone Call'); ?></h4>
                            <p><?= t('support_phone_desc', 'ទំនាក់ទំនងផ្ទាល់ក្នុងម៉ោងបម្រើសេវា', 'Call us directly during operating hours'); ?></p>
                            <a href="tel:<?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="support-channel-card">
                        <div class="channel-icon-wrap">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="channel-info">
                            <h4><?= t('support_hours_title', 'ម៉ោងបម្រើសេវាកម្ម', 'Operating Hours'); ?></h4>
                            <p><?= !empty($supportHours) ? htmlspecialchars($supportHours, ENT_QUOTES, 'UTF-8') : t('support_hours_desc', 'ច័ន្ទ - អាទិត្យ: 8:00 AM - 10:00 PM', 'Monday - Sunday: 8:00 AM - 10:00 PM'); ?></p>
                            <span style="font-size: 12px; color: #2e7d32; font-weight: 700;"><i class="fas fa-circle" style="font-size: 8px;"></i> <?= t('support_hours_badge', 'កំពុងដំណើរការរាល់ថ្ងៃ', 'Active Daily'); ?></span>
                        </div>
                    </div>

                    <div class="support-channel-card">
                        <div class="channel-icon-wrap">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <div class="channel-info">
                            <h4><?= t('support_trust_title', 'ការធានា និងទំនុកចិត្ត', 'Trust & Dedication'); ?></h4>
                            <p><?= t('support_trust_desc', '100% យកចិត្តទុកដាក់ ដោះស្រាយបញ្ហារហ័ស', '100% dedicated care, swift resolution'); ?></p>
                            <span style="font-size: 12px; color: #805f4b; font-weight: 600;"><i class="fas fa-check"></i> <?= t('support_trust_badge', 'ធានាការឆ្លើយតប', 'Guaranteed Reply'); ?></span>
                        </div>
                    </div>
                </div>

                <div style="padding-top: 18px; border-top: 1px solid #e8e1d8; font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-location-dot" style="color: #805f4b;"></i>
                    <span><?= !empty($storeLocation) ? htmlspecialchars($storeLocation, ENT_QUOTES, 'UTF-8') : t('support_location', 'រាជធានីភ្នំពេញ ព្រះរាជាណាចក្រកម្ពុជា', 'Phnom Penh, Kingdom of Cambodia'); ?></span>
                </div>
            </div>

            <!-- Right Form Column -->
            <div class="support-box">
                <span class="support-kicker"><i class="fas fa-envelope-open-text"></i> <?= t('support_form_kicker', 'ផ្ញើសារមកកាន់យើង', 'Send Us a Message'); ?></span>
                <h2><?= t('support_form_title', 'ទម្រង់ទំនាក់ទំនង', 'Contact Form'); ?></h2>
                <p class="lead">
                    <?= t('support_form_lead', 'សូមបំពេញព័ត៌មានខាងក្រោម ក្រុមការងារយើងនឹងទាក់ទងត្រឡប់ទៅលោកអ្នកក្នុងពេលឆាប់ៗបំផុត។', 'Please fill out the form below and our team will get back to you promptly.'); ?>
                </p>

                <?php if (isset($_GET['success'])): ?>
                    <div class="support-alert support-alert-success">
                        <i class="fas fa-circle-check" style="font-size: 18px;"></i>
                        <span><?= t('support_alert_success', 'សាររបស់អ្នកត្រូវបានបញ្ជូនដោយជោគជ័យ! យើងនឹងឆ្លើយតបឆាប់ៗនេះ។', 'Your message has been sent successfully! We will get back to you shortly.'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="support-alert support-alert-error">
                        <i class="fas fa-triangle-exclamation" style="font-size: 18px;"></i>
                        <span><?= t('support_alert_error', 'មានបញ្ហាក្នុងការបញ្ជូនសារ សូមពិនិត្យព័ត៌មាន និងព្យាយាមម្តងទៀត។', 'Failed to send message. Please review your information and try again.'); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="submit-form.php">
                    <input type="hidden" name="lang" value="<?= htmlspecialchars($storeLang, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="support-form-field">
                        <label for="inputName"><i class="fas fa-user" style="color: #805f4b; margin-right: 6px;"></i> <?= t('support_name_label', 'ឈ្មោះពេញរបស់អ្នក *', 'Your Name (Full Name) *'); ?></label>
                        <input type="text" id="inputName" name="name" class="support-form-input" maxlength="100" placeholder="<?= htmlspecialchars(t('support_name_ph', 'ឧទាហរណ៍: សុខា', 'e.g. Sokha Chan'), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="support-form-field">
                        <label for="inputEmail"><i class="fas fa-envelope" style="color: #805f4b; margin-right: 6px;"></i> <?= t('support_contact_label', 'អ៊ីម៉ែល ឬ Telegram ID ឬលេខទូរស័ព្ទ *', 'Email, Telegram ID or Phone Number *'); ?></label>
                        <input type="text" id="inputEmail" name="email" class="support-form-input" maxlength="100" placeholder="<?= htmlspecialchars(t('support_contact_ph', 'ឧទាហរណ៍: sokha@gmail.com ឬ @sokha_kh', 'e.g. sokha@gmail.com or @sokha_tg'), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>

                    <div class="support-form-field">
                        <label for="inputMessage"><i class="fas fa-comment-dots" style="color: #805f4b; margin-right: 6px;"></i> <?= t('support_msg_label', 'សារ ឬសំណួររបស់អ្នក *', 'Your Message / Inquiry *'); ?></label>
                        <textarea id="inputMessage" name="message" class="support-form-textarea" maxlength="10000" placeholder="<?= htmlspecialchars(t('support_msg_ph', 'សូមសរសេរសារ ឬសំណួរអំពីផលិតផល និងការបញ្ជាទិញនៅទីនេះ...', 'Write your question, sizing inquiry, or order request here...'), ENT_QUOTES, 'UTF-8'); ?>" required></textarea>
                    </div>

                    <button type="submit" class="btn-send-message">
                        <i class="fas fa-paper-plane"></i> <?= t('support_btn_send', 'បញ្ជូនសារ', 'Send Message'); ?>
                    </button>
                </form>
            </div>
        </div>

        <footer style="margin-top: 48px; text-align: center; color: var(--text-dim); font-size: 13px; font-family: 'Space Grotesk', sans-serif;">
            &copy; <?= date('Y'); ?> <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?> • <?= t('footer_copyright', 'រក្សាសិទ្ធិគ្រប់យ៉ាង', 'All rights reserved.'); ?>
        </footer>
    </div>
</body>

</html>
