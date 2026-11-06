<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/admin-auth.php';
require_once __DIR__ . '/primary-navigation.php';

if (!ensureAdminSecuritySchema($conn) || !ensureAdminOperationsSchema($conn) || !ensureStoreManagementSchema($conn) || !adminStartSession()) {
    http_response_code(500);
    exit('Could not prepare store management. Check the server error log.');
}
$currentAdmin = adminRequireLogin($conn);
$csrfToken = adminCsrfToken();
$views = [
    'promotions' => ['permission' => 'promotions', 'label' => 'Promotions'],
    'homepage' => ['permission' => 'store_settings', 'label' => 'Homepage'],
    'store' => ['permission' => 'store_settings', 'label' => 'Store settings'],
    'translations' => ['permission' => 'store_settings', 'label' => 'Page Content & Notes (ខ្លឹមសារ & Note តាមទំព័រ)'],
    'notifications' => ['permission' => 'notifications', 'label' => 'Notifications'],
];
$view = is_string($_GET['view'] ?? null) && isset($views[$_GET['view']]) ? $_GET['view'] : 'promotions';
adminRequirePermission($currentAdmin, $views[$view]['permission']);
$primarySection = ['promotions' => 'promotions', 'homepage' => 'homepage', 'store' => 'settings', 'translations' => 'translations', 'notifications' => 'notifications'][$view];
$errorMessage = '';
$flashMessage = is_string($_SESSION['store_management_flash'] ?? null) ? $_SESSION['store_management_flash'] : '';
unset($_SESSION['store_management_flash']);

function storeManagementEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function adminResolveAssetUrl(?string $url, string $default): string
{
    $val = trim((string) ($url ?? ''));
    if ($val === '') {
        $val = $default;
    }
    if (preg_match('~^(https?://|data:)~i', $val)) {
        return $val;
    }
    if (str_starts_with($val, './')) {
        return '../home/' . substr($val, 2);
    }
    if (str_starts_with($val, '../')) {
        return $val;
    }
    return '../' . ltrim($val, '/');
}

function storeManagementRedirect(string $view, string $message, array $extraParams = []): void
{
    $_SESSION['store_management_flash'] = $message;
    $params = array_merge(['view' => $view], $extraParams);
    header('Location: store-management.php?' . http_build_query($params));
    exit;
}

function storeManagementDate(?string $value): string|false|null
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, new DateTimeZone('Asia/Phnom_Penh'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
        return false;
    }
    return $date->format('Y-m-d H:i:s');
}

function storeManagementSaveSettings(mysqli $connection, int $adminId, array $settings): bool
{
    if (!$connection->begin_transaction()) {
        return false;
    }
    $statement = $connection->prepare('INSERT INTO system_settings (setting_name, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)');
    if (!$statement) {
        $connection->rollback();
        error_log('Could not prepare store setting update: ' . $connection->error);
        return false;
    }
    foreach ($settings as $key => $entry) {
        $name = $key;
        $value = $entry['value'];
        $description = $entry['description'];
        $statement->bind_param('sss', $name, $value, $description);
        if (!$statement->execute()) {
            $statement->close();
            $connection->rollback();
            error_log('Could not save store setting: ' . $connection->error);
            return false;
        }
    }
    $statement->close();
    if (!adminWriteAudit($connection, 'store_settings_updated', null, null, array_map(static fn(array $entry): string => $entry['value'], $settings)) || !$connection->commit()) {
        $connection->rollback();
        return false;
    }
    return true;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!adminCsrfValid()) {
        http_response_code(403);
        exit('The form expired. Reload the page and try again.');
    }
    $operation = is_string($_POST['operation'] ?? null) ? $_POST['operation'] : '';
    if ($operation === 'save_translations') {
        adminRequirePermission($currentAdmin, 'store_settings');
        require_once __DIR__ . '/../api/i18n.php';
        ensureTranslationsSchema($conn);
        $transPost = is_array($_POST['trans'] ?? null) ? $_POST['trans'] : [];
        $activeTab = is_string($_POST['active_tab'] ?? null) && trim($_POST['active_tab']) !== '' ? trim($_POST['active_tab']) : 'step1_home';
        $stmt = $conn->prepare("INSERT INTO store_translations (trans_key, group_name, label_km, label_en, description) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE label_km = VALUES(label_km), label_en = VALUES(label_en)");
        $savedCount = 0;
        if ($stmt) {
            foreach ($transPost as $key => $vals) {
                if (!is_string($key) || $key === '') continue;
                $km = is_string($vals['km'] ?? null) ? trim($vals['km']) : '';
                $en = is_string($vals['en'] ?? null) ? trim($vals['en']) : '';
                $group = is_string($vals['group'] ?? null) ? trim($vals['group']) : 'general';
                $desc = is_string($vals['desc'] ?? null) ? trim($vals['desc']) : '';
                $stmt->bind_param('sssss', $key, $group, $km, $en, $desc);
                $stmt->execute();
                $savedCount++;
            }
            $stmt->close();
        }

        // Process optional Hero Banner upload from PC
        if (isset($_FILES['step1_hero_banner_file']) && $_FILES['step1_hero_banner_file']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['step1_hero_banner_file'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($f['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'];
            if (isset($allowed[$mime])) {
                $ext = $allowed[$mime];
                $dir = __DIR__ . '/../img/banners/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fn = 'home_banner_' . date('Ymd_His_') . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . $fn)) {
                    $bannerPath = 'img/banners/' . $fn;
                    $bStmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value) VALUES ('home_banner_url', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    if ($bStmt) {
                        $bStmt->bind_param('s', $bannerPath);
                        $bStmt->execute();
                        $bStmt->close();
                    }
                }
            }
        }

        // Process optional Store Logo upload from PC
        if (isset($_FILES['step7_store_logo_file']) && $_FILES['step7_store_logo_file']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['step7_store_logo_file'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($f['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'];
            if (isset($allowed[$mime])) {
                $ext = $allowed[$mime];
                $dir = __DIR__ . '/../img/store/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fn = 'store_logo_' . date('Ymd_His_') . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . $fn)) {
                    $logoPath = 'img/store/' . $fn;
                    $lStmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value) VALUES ('store_logo_url', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    if ($lStmt) {
                        $lStmt->bind_param('s', $logoPath);
                        $lStmt->execute();
                        $lStmt->close();
                    }
                }
            }
        }

        adminWriteAudit($conn, 'translations_updated', null, null, ['count' => $savedCount, 'tab' => $activeTab]);
        storeManagementRedirect('translations', "បានរក្សាទុកខ្លឹមសារ និង Note តាមទំព័រជោគជ័យ ($savedCount items saved successfully)!", ['tab' => $activeTab]);
    }
    if ($operation === 'save_coupon') {
        adminRequirePermission($currentAdmin, 'promotions');
        $couponId = filter_var($_POST['coupon_id'] ?? null, FILTER_VALIDATE_INT);
        $code = strtoupper(trim(is_string($_POST['coupon_code'] ?? null) ? $_POST['coupon_code'] : ''));
        $type = is_string($_POST['discount_type'] ?? null) ? $_POST['discount_type'] : '';
        $valueInput = is_string($_POST['discount_value'] ?? null) ? trim($_POST['discount_value']) : '';
        $minimumInput = is_string($_POST['minimum_subtotal'] ?? null) ? trim($_POST['minimum_subtotal']) : '0';
        $maxUsesInput = is_string($_POST['max_uses'] ?? null) ? trim($_POST['max_uses']) : '';
        $startsAt = storeManagementDate(is_string($_POST['starts_at'] ?? null) ? $_POST['starts_at'] : null);
        $expiresAt = storeManagementDate(is_string($_POST['expires_at'] ?? null) ? $_POST['expires_at'] : null);
        $valueValid = preg_match('/^\\d{1,8}(?:\\.\\d{1,2})?$/D', $valueInput) === 1;
        $minimumValid = preg_match('/^\\d{1,8}(?:\\.\\d{1,2})?$/D', $minimumInput) === 1;
        $maxUses = $maxUsesInput === '' ? null : filter_var($maxUsesInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        $discountValue = $valueValid ? (float) $valueInput : 0.0;
        $minimumSubtotal = $minimumValid ? (float) $minimumInput : 0.0;
        if (
            preg_match('/^[A-Z0-9_-]{3,40}$/D', $code) !== 1
            || !in_array($type, ['percent', 'fixed'], true)
            || !$valueValid
            || $discountValue <= 0
            || ($type === 'percent' && $discountValue > 100)
            || !$minimumValid
            || ($maxUsesInput !== '' && $maxUses === false)
            || ($startsAt === false || $expiresAt === false)
            || ($startsAt !== null && $expiresAt !== null && $startsAt >= $expiresAt)
        ) {
            $errorMessage = 'Enter a valid code, discount, minimum, and date range.';
        } else {
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            if ($couponId && $couponId > 0) {
                $statement = $conn->prepare('UPDATE discount_coupons SET coupon_code = ?, discount_type = ?, discount_value = ?, minimum_subtotal = ?, max_uses = ?, starts_at = ?, expires_at = ?, is_active = ? WHERE id = ?');
                if ($statement) {
                    $statement->bind_param('ssddissii', $code, $type, $discountValue, $minimumSubtotal, $maxUses, $startsAt, $expiresAt, $isActive, $couponId);
                }
                $action = 'coupon_updated';
            } else {
                $statement = $conn->prepare('INSERT INTO discount_coupons (coupon_code, discount_type, discount_value, minimum_subtotal, max_uses, starts_at, expires_at, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                if ($statement) {
                    $adminId = (int) $currentAdmin['id'];
                    $statement->bind_param('ssddissii', $code, $type, $discountValue, $minimumSubtotal, $maxUses, $startsAt, $expiresAt, $isActive, $adminId);
                }
                $action = 'coupon_created';
            }
            if (!$statement) {
                $errorMessage = 'Could not save the coupon.';
            } elseif (!$conn->begin_transaction()) {
                $statement->close();
                $errorMessage = 'Could not start coupon update.';
            } else {
                $saved = $statement->execute();
                $couponId = $couponId ?: (int) $conn->insert_id;
                $statementError = $statement->errno;
                $statement->close();
                $auditSaved = $saved && adminWriteAudit($conn, $action, null, null, ['coupon_id' => $couponId, 'code' => $code, 'type' => $type, 'value' => $discountValue, 'active' => $isActive]);
                if ($auditSaved && $conn->commit()) {
                    storeManagementRedirect('promotions', 'Coupon saved.');
                }
                $conn->rollback();
                $errorMessage = $statementError === 1062 ? 'That coupon code already exists.' : 'Could not save the coupon and audit record.';
            }
        }
    } elseif ($operation === 'toggle_coupon') {
        adminRequirePermission($currentAdmin, 'promotions');
        $couponId = filter_var($_POST['coupon_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$couponId || $couponId < 1) {
            $errorMessage = 'Choose a valid coupon.';
        } else {
            $statement = $conn->prepare('UPDATE discount_coupons SET is_active = 1 - is_active WHERE id = ?');
            if (!$statement) {
                $errorMessage = 'Could not update the coupon.';
            } elseif (!$conn->begin_transaction()) {
                $statement->close();
                $errorMessage = 'Could not start coupon update.';
            } else {
                $statement->bind_param('i', $couponId);
                $saved = $statement->execute() && $statement->affected_rows === 1;
                $statement->close();
                if ($saved && adminWriteAudit($conn, 'coupon_toggled', null, null, ['coupon_id' => $couponId]) && $conn->commit()) {
                    storeManagementRedirect('promotions', 'Coupon status updated.');
                }
                $conn->rollback();
                $errorMessage = 'Could not update the coupon.';
            }
        }
    } elseif ($operation === 'save_homepage' || $operation === 'save_store') {
        $requiredPermission = $operation === 'save_homepage' ? 'store_settings' : 'store_settings';
        adminRequirePermission($currentAdmin, $requiredPermission);
        $fields = $operation === 'save_homepage'
            ? ['home_hero_title' => 160, 'home_hero_description' => 800, 'home_banner_url' => 500]
            : [
                'store_name' => 80,
                'store_logo_url' => 500,
                'store_contact_url' => 500,
                'telegram_username' => 80,
                'support_phone' => 50,
                'support_hours' => 120,
                'store_location' => 200,
                'google_client_id' => 255,
                'currency_symbol' => 12
            ];
        $settings = [];
        foreach ($fields as $key => $maxLength) {
            $value = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
            if (strlen($value) > $maxLength) {
                $errorMessage = 'One or more settings exceed the allowed length.';
                break;
            }
            if ($key === 'store_contact_url' && $value !== '') {
                $scheme = parse_url($value, PHP_URL_SCHEME);
                if (!filter_var($value, FILTER_VALIDATE_URL) || !in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                    $errorMessage = 'Contact link must be a valid URL (e.g. https://t.me/your_store).';
                    break;
                }
            }
            if (in_array($key, ['store_logo_url', 'home_banner_url'], true) && $value !== '') {
                $scheme = parse_url($value, PHP_URL_SCHEME);
                if ($scheme !== null && !in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                    $errorMessage = 'Image URL must use HTTP/HTTPS or be a valid local file path.';
                    break;
                }
            }
            if ($key === 'currency_symbol' && preg_match('/^[A-Za-z$€£៛]{1,12}$/u', $value) !== 1) {
                $errorMessage = 'Enter a short currency symbol or code.';
                break;
            }
            $settings[$key] = ['value' => $value, 'description' => str_replace('_', ' ', ucfirst($key))];
        }

        // Process file uploads from PC if present
        if ($operation === 'save_homepage' && isset($_FILES['home_banner_file']) && $_FILES['home_banner_file']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['home_banner_file'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($f['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'];
            if (isset($allowed[$mime])) {
                $ext = $allowed[$mime];
                $dir = __DIR__ . '/../img/banners/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fn = 'home_banner_' . date('Ymd_His_') . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . $fn)) {
                    $settings['home_banner_url']['value'] = 'img/banners/' . $fn;
                }
            }
        }
        if ($operation === 'save_store' && isset($_FILES['store_logo_file']) && $_FILES['store_logo_file']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['store_logo_file'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($f['tmp_name']);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'];
            if (isset($allowed[$mime])) {
                $ext = $allowed[$mime];
                $dir = __DIR__ . '/../img/store/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fn = 'store_logo_' . date('Ymd_His_') . bin2hex(random_bytes(6)) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dir . $fn)) {
                    $settings['store_logo_url']['value'] = 'img/store/' . $fn;
                }
            }
        }
        if ($errorMessage === '') {
            if (storeManagementSaveSettings($conn, (int) $currentAdmin['id'], $settings)) {
                if ($operation === 'save_homepage') {
                    if (!empty($settings['home_hero_title']['value'])) {
                        $stmtSync = $conn->prepare("UPDATE store_translations SET label_km = ? WHERE trans_key = 'home_hero_title'");
                        if ($stmtSync) {
                            $stmtSync->bind_param('s', $settings['home_hero_title']['value']);
                            $stmtSync->execute();
                            $stmtSync->close();
                        }
                    }
                    if (!empty($settings['home_hero_description']['value'])) {
                        $stmtSync = $conn->prepare("UPDATE store_translations SET label_km = ? WHERE trans_key = 'home_hero_desc'");
                        if ($stmtSync) {
                            $stmtSync->bind_param('s', $settings['home_hero_description']['value']);
                            $stmtSync->execute();
                            $stmtSync->close();
                        }
                    }
                } elseif ($operation === 'save_store') {
                    if (!empty($settings['store_name']['value'])) {
                        $newStoreName = $settings['store_name']['value'];
                        $oldNameQuery = $conn->query("SELECT setting_value FROM system_settings WHERE setting_name = 'store_name' LIMIT 1");
                        $oldStoreName = ($oldNameQuery && ($oldRow = $oldNameQuery->fetch_assoc())) ? $oldRow['setting_value'] : '4K STORE';
                        
                        $stmtSyncName = $conn->prepare("UPDATE store_translations SET label_km = REPLACE(REPLACE(label_km, '4K STORE', ?), ?, ?), label_en = REPLACE(REPLACE(label_en, '4K STORE', ?), ?, ?) WHERE label_km LIKE '%4K STORE%' OR label_en LIKE '%4K STORE%' OR label_km LIKE ? OR label_en LIKE ?");
                        if ($stmtSyncName) {
                            $oldLike = '%' . $oldStoreName . '%';
                            $stmtSyncName->bind_param('ssssssss', $newStoreName, $oldStoreName, $newStoreName, $newStoreName, $oldStoreName, $newStoreName, $oldLike, $oldLike);
                            $stmtSyncName->execute();
                            $stmtSyncName->close();
                        }
                    }
                }
                storeManagementRedirect($operation === 'save_homepage' ? 'homepage' : 'store', 'Settings saved.');
            }
            $errorMessage = 'Could not save settings and audit record.';
        }
    } elseif ($operation === 'mark_notification_read' || $operation === 'mark_all_notifications_read') {
        adminRequirePermission($currentAdmin, 'notifications');
        if ($operation === 'mark_all_notifications_read') {
            $saved = $conn->query('UPDATE admin_notifications SET read_at = NOW() WHERE read_at IS NULL');
        } else {
            $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
            $statement = $conn->prepare('UPDATE admin_notifications SET read_at = NOW() WHERE id = ? AND read_at IS NULL');
            if ($statement && $notificationId) {
                $statement->bind_param('i', $notificationId);
                $saved = $statement->execute();
                $statement->close();
            } else {
                $saved = false;
            }
        }
        if ($saved) {
            storeManagementRedirect('notifications', 'Notification status updated.');
        }
        $errorMessage = 'Could not update notification status.';
    } else {
        $errorMessage = 'Unknown store-management action.';
    }
}

$settings = [];
$settingsResult = $conn->query('SELECT setting_name, setting_value FROM system_settings');
if ($settingsResult) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}
$currencySymbol = $settings['currency_symbol'] ?? '$';
$coupons = $conn->query('SELECT id, coupon_code, discount_type, discount_value, minimum_subtotal, max_uses, uses_count, starts_at, expires_at, is_active FROM discount_coupons ORDER BY created_at DESC, id DESC');
$notifications = $conn->query('SELECT id, notification_type, title, message, target_url, created_at, read_at FROM admin_notifications ORDER BY created_at DESC, id DESC LIMIT 100');
$unreadCount = 0;
$unreadResult = $conn->query('SELECT COUNT(*) AS unread FROM admin_notifications WHERE read_at IS NULL');
if ($unreadResult) {
    $unreadCount = (int) $unreadResult->fetch_assoc()['unread'];
}
if (!headers_sent()) {
    header('Cache-Control: no-store, private');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $adminStoreSettings = function_exists('getStoreSettings') ? getStoreSettings($conn) : [];
    $adminStoreName = $adminStoreSettings['store_name'] ?? 'SN STORE';
    $adminStoreLogo = $adminStoreSettings['store_logo_url'] ?? '';
    ?>
    <title>Admin Dashboard • <?= htmlspecialchars($adminStoreName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="<?= htmlspecialchars(resolveStoreAssetUrl($adminStoreLogo, 'admin'), ENT_QUOTES, 'UTF-8') ?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(resolveStoreAssetUrl($adminStoreLogo, 'admin'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../home/page.css?v=<?= filemtime(__DIR__ . '/../home/page.css') ?>">
    <link rel="stylesheet" href="../home/fashion-theme.css?v=<?= filemtime(__DIR__ . '/../home/fashion-theme.css') ?>">
    <link rel="stylesheet" href="./backoffice-theme.css?v=<?= filemtime(__DIR__ . '/backoffice-theme.css') ?>">
    <link rel="stylesheet" href="./operations.css?v=<?= filemtime(__DIR__ . '/operations.css') ?>">
    <link rel="stylesheet" href="../css/theme-mode.css?v=<?= filemtime(__DIR__ . '/../css/theme-mode.css') ?>">
</head>
<body class="fashion-storefront admin-storefront">
<main class="admin-dashboard-container operations-container">
    <header class="admin-header-glass operations-header">
        <div><span class="section-tag"><i class="fas fa-store"></i> Store management</span><h1><?= storeManagementEscape($views[$view]['label']) ?></h1></div>
    </header>
    <?php renderAdminPrimaryNavigation($currentAdmin, $primarySection); ?>
    <?php if ($flashMessage !== ''): ?><div class="operation-notice" role="status"><?= storeManagementEscape($flashMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="operation-error" role="alert"><?= storeManagementEscape($errorMessage) ?></div><?php endif; ?>

    <?php if ($view === 'promotions'): ?>
    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Create a coupon</h2><p>Set a percent or fixed-value discount, optional minimum, usage limit, and active dates.</p></div></div>
        <form class="security-create-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= storeManagementEscape($csrfToken) ?>"><input type="hidden" name="operation" value="save_coupon">
            <label>Coupon code<input class="operation-input" name="coupon_code" minlength="3" maxlength="40" pattern="[A-Za-z0-9_-]+" required></label>
            <label>Discount type<select class="operation-input" name="discount_type"><option value="percent">Percent</option><option value="fixed">Fixed amount</option></select></label>
            <label>Discount value<input class="operation-input" name="discount_value" type="number" min="0.01" max="100000000" step="0.01" required></label>
            <label>Minimum subtotal (<?= storeManagementEscape($currencySymbol) ?>)<input class="operation-input" name="minimum_subtotal" type="number" min="0" step="0.01" value="0" required></label>
            <label>Maximum uses<input class="operation-input" name="max_uses" type="number" min="1" placeholder="Unlimited"></label>
            <label>Starts<input class="operation-input" name="starts_at" type="datetime-local"></label>
            <label>Expires<input class="operation-input" name="expires_at" type="datetime-local"></label>
            <label><span>Active</span><input name="is_active" type="checkbox" value="1" checked></label>
            <button class="operation-button" type="submit">Save coupon</button>
        </form>
    </section>
    <section class="operations-section"><div class="operations-section-heading"><div><h2>Coupons</h2><p>Uses show orders that have reserved this code.</p></div></div>
        <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Code</th><th>Discount</th><th>Minimum</th><th>Uses</th><th>Dates</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php if ($coupons): while ($coupon = $coupons->fetch_assoc()): ?><tr>
            <td><strong><?= storeManagementEscape((string) $coupon['coupon_code']) ?></strong></td>
            <td><?= $coupon['discount_type'] === 'percent' ? number_format((float) $coupon['discount_value'], 2) . '%' : storeManagementEscape($currencySymbol) . number_format((float) $coupon['discount_value'], 2) ?></td>
            <td><?= storeManagementEscape($currencySymbol) ?><?= number_format((float) $coupon['minimum_subtotal'], 2) ?></td>
            <td><?= (int) $coupon['uses_count'] ?> / <?= $coupon['max_uses'] === null ? '∞' : (int) $coupon['max_uses'] ?></td>
            <td><?= storeManagementEscape((string) ($coupon['starts_at'] ?? 'Any time')) ?><br><?= storeManagementEscape((string) ($coupon['expires_at'] ?? 'No expiry')) ?></td>
            <td><?= (int) $coupon['is_active'] === 1 ? 'Active' : 'Disabled' ?></td>
            <td><form method="post"><input type="hidden" name="csrf_token" value="<?= storeManagementEscape($csrfToken) ?>"><input type="hidden" name="operation" value="toggle_coupon"><input type="hidden" name="coupon_id" value="<?= (int) $coupon['id'] ?>"><button class="operation-button operation-button-secondary" type="submit">Toggle</button></form></td>
        </tr><?php endwhile; else: ?><tr><td colspan="7">No coupons created.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <?php elseif ($view === 'homepage' || $view === 'store'): ?>
    <?php
    $isHomepage = $view === 'homepage';
    $fields = $isHomepage
        ? [
            'home_hero_title' => ['Hero headline', 160],
            'home_hero_description' => ['Hero description', 800],
            'home_banner_url' => ['Hero banner image URL (HTTPS)', 500]
        ]
        : [
            'store_name' => ['Store name', 80],
            'store_logo_url' => ['Logo URL (HTTPS)', 500],
            'store_contact_url' => ['Telegram contact link (e.g. https://t.me/your_store)', 500],
            'telegram_username' => ['Telegram handle / username (e.g. @your_store)', 80],
            'support_phone' => ['Customer support phone number', 50],
            'support_hours' => ['Support operating hours (e.g. Mon - Sun: 8:00 AM - 10:00 PM)', 120],
            'store_location' => ['Store physical location (e.g. Phnom Penh, Cambodia)', 200],
            'google_client_id' => ['Google OAuth Client ID (for 1-click Google Sign-In)', 255],
            'currency_symbol' => ['Currency symbol or code', 12]
        ];
    ?>
    <section class="operations-section">
        <div class="operations-section-heading"><div><h2><?= $isHomepage ? 'Homepage content' : 'Store settings' ?></h2><p><?= $isHomepage ? 'Update the storefront hero copy.' : 'Set store branding, contact destination, and displayed currency.' ?></p></div></div>
        <?php if ($isHomepage): ?>
        <div style="background:#fcfbf9; border:1px solid #ded5cb; border-left:4px solid #203f3a; border-radius:8px; padding:14px 18px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <strong style="color:#203f3a; font-size:14px;"><i class="fas fa-file-pen" style="margin-right:6px;"></i> គ្រប់គ្រងខ្លឹមសារ &amp; Note តាមទំព័រ (Step-by-Step Page Content Manager)</strong>
                <p style="margin:4px 0 0; font-size:13px; color:#6c645c;">កែសម្រួលចំណងជើង Hero, ស្ថិតិ 100%, ការធានា, និងកំណត់សម្គាល់គ្រប់ទំព័រជា ២ ភាសា (ខ្មែរ &amp; English) យ៉ាងលម្អិត។</p>
            </div>
            <a href="store-management.php?view=translations&tab=step1_home" class="operation-button" style="background:#203f3a; color:#fff; padding:8px 16px; font-size:13px; text-decoration:none; border-radius:6px; display:inline-flex; align-items:center; gap:6px;">
                <i class="fas fa-arrow-right"></i> ទៅកាន់គ្រប់គ្រងខ្លឹមសារ (Page Content)
            </a>
        </div>
        <?php endif; ?>
        <form class="security-create-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= storeManagementEscape($csrfToken) ?>">
            <input type="hidden" name="operation" value="<?= $isHomepage ? 'save_homepage' : 'save_store' ?>">
            <?php foreach ($fields as $key => [$label, $maxLength]): ?>
                <?php if ($key === 'home_hero_description'): ?>
                    <label><?= storeManagementEscape($label) ?>
                        <textarea class="operation-input" name="<?= storeManagementEscape($key) ?>" maxlength="<?= $maxLength ?>" rows="4"><?= storeManagementEscape((string) ($settings[$key] ?? '')) ?></textarea>
                    </label>
                <?php elseif ($key === 'home_banner_url'): ?>
                    <label><?= storeManagementEscape($label) ?>
                        <div style="display:flex; gap:16px; align-items:center; margin:8px 0; background:#faf8f5; border:1px dashed #ded5cb; border-radius:10px; padding:12px;">
                            <img src="<?= adminResolveAssetUrl($settings['home_banner_url'] ?? '', './logo/banner.png') ?>" alt="Hero Banner Preview" style="width:68px; height:84px; border-radius:36% 36% 4px 4px; object-fit:cover; border:1.5px solid #805f4b;">
                            <div style="flex:1;">
                                <div style="font-weight:700; font-size:13px; color:#203f3a; margin-bottom:4px;"><i class="fas fa-desktop"></i> ជ្រើសរើសរូបភាពពី PC (Upload from your PC):</div>
                                <input type="file" name="home_banner_file" accept="image/*" class="operation-input" style="padding:6px; font-size:12.5px; background:#fff;">
                            </div>
                        </div>
                        <input class="operation-input" name="<?= storeManagementEscape($key) ?>" maxlength="<?= $maxLength ?>" value="<?= storeManagementEscape((string) ($settings[$key] ?? '')) ?>" placeholder="ឬបញ្ចូល File Path / HTTPS URL...">
                    </label>
                <?php elseif ($key === 'store_logo_url'): ?>
                    <label><?= storeManagementEscape($label) ?>
                        <div style="display:flex; gap:16px; align-items:center; margin:8px 0; background:#faf8f5; border:1px dashed #ded5cb; border-radius:10px; padding:12px;">
                            <img src="<?= adminResolveAssetUrl($settings['store_logo_url'] ?? '', './logo/logome.png') ?>" alt="Logo Preview" style="width:58px; height:58px; border-radius:12px; object-fit:contain; border:1.5px solid #805f4b; background:#fff; padding:4px;">
                            <div style="flex:1;">
                                <div style="font-weight:700; font-size:13px; color:#203f3a; margin-bottom:4px;"><i class="fas fa-desktop"></i> ជ្រើសរើស Logo ពី PC (Upload from your PC):</div>
                                <input type="file" name="store_logo_file" accept="image/*" class="operation-input" style="padding:6px; font-size:12.5px; background:#fff;">
                            </div>
                        </div>
                        <input class="operation-input" name="<?= storeManagementEscape($key) ?>" maxlength="<?= $maxLength ?>" value="<?= storeManagementEscape((string) ($settings[$key] ?? '')) ?>" placeholder="ឬបញ្ចូល File Path / HTTPS URL...">
                    </label>
                <?php else: ?>
                    <label><?= storeManagementEscape($label) ?>
                        <input class="operation-input" name="<?= storeManagementEscape($key) ?>" maxlength="<?= $maxLength ?>" value="<?= storeManagementEscape((string) ($settings[$key] ?? ($key === 'currency_symbol' ? '$' : ''))) ?>" <?= in_array($key, ['store_contact_url'], true) ? 'type="url"' : 'type="text"' ?>>
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
            <button class="operation-button" type="submit">Save settings</button>
        </form>
    </section>
    <?php elseif ($view === 'translations'): ?>
    <?php
    require_once __DIR__ . '/../api/i18n.php';
    require_once __DIR__ . '/../api/page-content-config.php';
    ensureTranslationsSchema($conn);
    $allTranslations = loadTranslations($conn);
    $defaultsMap = getDefaultTranslationsMap();
    $steps = getPageContentSteps();
    $activeTab = is_string($_GET['tab'] ?? null) && (isset($steps[$_GET['tab']]) || $_GET['tab'] === 'all') ? $_GET['tab'] : 'step1_home';

    // Compute item counts for each step
    $stepKeyCounts = [];
    $totalKeys = count($defaultsMap);
    foreach ($steps as $sId => $sData) {
        $cnt = 0;
        foreach ($sData['sections'] as $sec) {
            $cnt += count($sec['keys']);
        }
        $stepKeyCounts[$sId] = $cnt;
    }
    $stepKeys = array_keys($steps);
    ?>
    <style>
        /* Page Content & Note Properties Manager Styles - Fixed Full Viewport */
        .admin-storefront .admin-dashboard-container.operations-container {
            width: calc(100vw - 280px) !important;
            max-width: calc(100vw - 280px) !important;
            box-sizing: border-box !important;
            min-width: 0 !important;
            overflow-x: hidden !important;
        }
        @media (max-width: 1024px) {
            .admin-storefront .admin-dashboard-container.operations-container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 16px auto 40px !important;
            }
        }
        .page-content-manager-root {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            min-width: 0 !important;
            overflow-x: hidden !important;
        }
        .pcm-form {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            min-width: 0 !important;
        }
        .pcm-hero-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            background: linear-gradient(135deg, #ffffff 0%, #faf8f5 100%);
            border: 1px solid #ded5cb;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            width: 100%;
            box-sizing: border-box;
        }
        .pcm-badge-step {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: #e8f4f2;
            color: #1e4d45;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 6px;
        }
        .pcm-hero-heading h2 {
            font-size: 21px;
            color: #203f3a;
            margin: 0 0 6px;
            font-weight: 800;
            font-family: inherit;
        }
        .pcm-hero-heading p {
            font-size: 13px;
            color: #6c645c;
            margin: 0;
            line-height: 1.5;
        }
        .pcm-hero-stats {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pcm-stat-pill {
            background: #ffffff;
            border: 1px solid #e2dad0;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            color: #332d28;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .pcm-top-bar {
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            background: #ffffff;
            border: 1px solid #ded5cb;
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 18px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            width: 100%;
            box-sizing: border-box;
        }
        .pcm-search-box {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 280px;
            min-width: 0;
            max-width: 100%;
            background: #faf8f5;
            border: 1px solid #ded5cb;
            border-radius: 8px;
            padding: 2px 12px;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .pcm-search-box:focus-within {
            border-color: #203f3a;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(32, 63, 58, 0.1);
        }
        .pcm-search-box i {
            color: #805f4b;
            font-size: 14px;
        }
        .pcm-search-box input {
            border: none;
            background: transparent;
            width: 100%;
            padding: 9px 4px;
            font-size: 13.5px;
            outline: none;
            color: #201b18;
            font-family: inherit;
        }
        .pcm-search-box button {
            background: none;
            border: none;
            color: #9c9489;
            cursor: pointer;
            padding: 4px;
            font-size: 14px;
        }
        .pcm-search-box button:hover {
            color: #201b18;
        }
        .pcm-search-counter {
            font-size: 12.5px;
            font-weight: 700;
            color: #1e4d45;
            background: #e8f4f2;
            padding: 6px 12px;
            border-radius: 16px;
        }
        .pcm-top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        /* Step Navigation Tabs: Grid of 4 columns, fully responsive, zero overflow */
        .pcm-steps-nav {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 8px !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            margin-bottom: 20px !important;
        }
        @media (max-width: 1100px) {
            .pcm-steps-nav {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
        @media (max-width: 600px) {
            .pcm-steps-nav {
                grid-template-columns: 1fr !important;
            }
        }
        .pcm-step-tab {
            display: flex !important;
            align-items: center !important;
            gap: 9px !important;
            padding: 9px 12px !important;
            background: #ffffff !important;
            border: 1px solid #ded5cb !important;
            border-radius: 9px !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-align: left !important;
            color: #332d28 !important;
            font-family: inherit !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.02) !important;
            min-width: 0 !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .pcm-step-tab:hover {
            border-color: #805f4b !important;
            background: #faf8f5 !important;
            transform: translateY(-1px) !important;
        }
        .pcm-step-tab.is-active {
            background: #203f3a !important;
            border-color: #203f3a !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(32, 63, 58, 0.25) !important;
        }
        .pcm-step-num {
            width: 22px !important;
            height: 22px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 50% !important;
            background: #e8e2d9 !important;
            color: #332d28 !important;
            font-size: 11px !important;
            font-weight: 800 !important;
            flex-shrink: 0 !important;
        }
        .pcm-step-tab.is-active .pcm-step-num {
            background: rgba(255, 255, 255, 0.22) !important;
            color: #ffffff !important;
        }
        .pcm-step-icon {
            font-size: 13px !important;
            color: #805f4b !important;
            flex-shrink: 0 !important;
        }
        .pcm-step-tab.is-active .pcm-step-icon {
            color: #d8c3b4 !important;
        }
        .pcm-step-text {
            display: flex !important;
            flex-direction: column !important;
            gap: 1px !important;
            min-width: 0 !important;
            flex: 1 1 auto !important;
            overflow: hidden !important;
        }
        .pcm-step-text strong {
            font-size: 12px !important;
            line-height: 1.25 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        .pcm-step-text small {
            font-size: 10px !important;
            color: #786f66 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        .pcm-step-tab.is-active .pcm-step-text small {
            color: #d8c3b4 !important;
        }
        .pcm-step-badge {
            background: #f0ece6 !important;
            color: #5c544d !important;
            font-size: 10.5px !important;
            font-weight: 700 !important;
            padding: 2px 6px !important;
            border-radius: 10px !important;
            flex-shrink: 0 !important;
        }
        .pcm-step-tab.is-active .pcm-step-badge {
            background: rgba(255, 255, 255, 0.22) !important;
            color: #ffffff !important;
        }
        .pcm-step-pane {
            display: none;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        .pcm-step-pane.is-active {
            display: block;
            animation: pcmFadeIn 0.25s ease-out;
        }
        @keyframes pcmFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .pcm-step-pane.is-all-view {
            display: block;
            margin-bottom: 30px;
        }
        .pcm-pane-header {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            background: #ffffff !important;
            border: 1px solid #ded5cb !important;
            border-radius: 12px !important;
            padding: 16px 20px !important;
            margin-bottom: 20px !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03) !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .pcm-pane-badge {
            display: inline-block;
            font-size: 11.5px;
            font-weight: 700;
            color: #805f4b;
            background: #f5efe8;
            padding: 3px 10px;
            border-radius: 14px;
            margin-bottom: 4px;
        }
        .pcm-pane-info {
            width: 100% !important;
            min-width: 0 !important;
        }
        .pcm-pane-info h3 {
            margin: 0 0 4px !important;
            font-size: 18px !important;
            color: #203f3a !important;
            font-weight: 800 !important;
        }
        .pcm-en-sub {
            font-size: 13.5px;
            color: #805f4b;
            font-weight: 600;
        }
        .pcm-pane-info p {
            margin: 0;
            font-size: 12.5px;
            color: #6c645c;
            line-height: 1.4;
        }
        .pcm-pane-actions {
            display: flex !important;
            gap: 8px !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            padding-top: 10px !important;
            border-top: 1px dashed #ede5db !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .pcm-btn {
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s ease;
            text-decoration: none;
            box-sizing: border-box;
        }
        .pcm-btn-primary {
            background: #203f3a;
            color: #ffffff;
        }
        .pcm-btn-primary:hover {
            background: #162c28;
            color: #ffffff;
            box-shadow: 0 3px 10px rgba(32, 63, 58, 0.2);
        }
        .pcm-btn-secondary {
            background: #faf8f5;
            color: #332d28;
            border: 1px solid #ded5cb;
        }
        .pcm-btn-secondary:hover {
            background: #ffffff;
            border-color: #805f4b;
            color: #203f3a;
        }
        .pcm-btn-nav {
            background: #f4efe9;
            color: #332d28;
            border: 1px solid #ded5cb;
            font-size: 12.5px;
            padding: 8px 12px;
        }
        .pcm-btn-nav:hover {
            background: #203f3a;
            color: #ffffff;
            border-color: #203f3a;
        }
        .pcm-section-card {
            background: #ffffff;
            border: 1px solid #ded5cb;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .pcm-section-header {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding-bottom: 14px;
            margin-bottom: 16px;
            border-bottom: 1px solid #f0eae1;
            width: 100%;
            box-sizing: border-box;
        }
        .pcm-section-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: #e8f4f2;
            color: #203f3a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }
        .pcm-section-title-wrap h4 {
            margin: 0 0 3px;
            font-size: 15.5px;
            color: #203f3a;
            font-weight: 800;
        }
        .pcm-section-title-wrap p {
            margin: 0;
            font-size: 12px;
            color: #786f66;
        }
        .pcm-props-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .pcm-prop-card {
            background: #fdfcfb;
            border: 1px solid #e8e2d9;
            border-radius: 10px;
            padding: 14px 16px;
            transition: all 0.2s ease;
            width: 100% !important;
            box-sizing: border-box !important;
            min-width: 0 !important;
        }
        .pcm-prop-card:hover {
            border-color: #c9bea9;
            background: #ffffff;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        }
        .pcm-prop-card:focus-within {
            border-color: #203f3a;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(32, 63, 58, 0.08);
        }
        .pcm-prop-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
            width: 100%;
            box-sizing: border-box;
        }
        .pcm-prop-title-box h5 {
            margin: 0 0 2px;
            font-size: 14px;
            color: #203f3a;
            font-weight: 700;
        }
        .pcm-prop-hint {
            font-size: 11.5px;
            color: #805f4b;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .pcm-key-tag {
            font-family: monospace;
            font-size: 11px;
            font-weight: 700;
            background: #f0ece6;
            color: #5c544d;
            padding: 2px 7px;
            border-radius: 5px;
            border: 1px solid #ded5cb;
        }
        .pcm-prop-inputs {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 14px;
            width: 100%;
            box-sizing: border-box;
        }
        @media (max-width: 860px) {
            .pcm-prop-inputs {
                grid-template-columns: 1fr;
            }
        }
        .pcm-input-col {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 0;
            width: 100%;
            box-sizing: border-box;
        }
        .pcm-lang-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #4a423a;
        }
        .pcm-flag {
            font-size: 13px;
        }
        .pcm-input {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            border: 1px solid #ded5cb;
            border-radius: 7px;
            padding: 9px 12px;
            font-size: 13px;
            font-family: inherit;
            background: #ffffff;
            color: #201b18;
            line-height: 1.55;
            transition: all 0.2s ease;
        }
        .pcm-input:focus {
            border-color: #203f3a;
            outline: none;
            box-shadow: 0 0 0 3px rgba(32, 63, 58, 0.12);
        }
        .pcm-input-km {
            font-family: 'Kantumruy Pro', 'Battambang', system-ui, sans-serif;
        }
        .pcm-pane-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: #ffffff;
            border: 1px solid #ded5cb;
            border-radius: 12px;
            padding: 14px 18px;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            width: 100%;
            box-sizing: border-box;
        }
        .pcm-pane-footer-left {
            font-size: 12px;
            color: #786f66;
        }
        .pcm-pane-footer-right {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .pcm-sticky-bar {
            position: sticky;
            bottom: 16px;
            z-index: 100;
            margin-top: 22px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border: 1px solid #ded5cb;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            padding: 10px 18px;
            width: 100%;
            box-sizing: border-box;
        }
        .pcm-sticky-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-right: 140px; /* Clearance for floating theme toggle */
            box-sizing: border-box;
        }
        .pcm-sticky-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            color: #332d28;
            font-weight: 600;
        }
        .pcm-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #16a34a;
        }
        .pcm-status-dot.has-changes {
            background: #f59e0b;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
        }
        /* Visual Asset Card & Arch Preview */
        .pcm-asset-card {
            background: #ffffff !important;
            border: 1px solid #ded5cb !important;
            border-radius: 14px !important;
            padding: 20px 24px !important;
            margin-bottom: 22px !important;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04) !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        .pcm-asset-header {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding-bottom: 16px;
            margin-bottom: 18px;
            border-bottom: 1px solid #f0eae1;
            width: 100%;
            box-sizing: border-box;
        }
        .pcm-asset-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #e8f4f2;
            color: #203f3a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .pcm-asset-title-wrap h4 {
            margin: 0;
            font-size: 16.5px;
            color: #203f3a;
            font-weight: 800;
        }
        .pcm-asset-title-wrap p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #6c645c;
            line-height: 1.45;
        }
        .pcm-asset-badge-active {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 700;
            color: #16a34a;
            background: #ecfdf5;
            padding: 3px 8px;
            border-radius: 12px;
            border: 1px solid #a7f3d0;
        }
        .pcm-asset-body {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            gap: 24px;
            align-items: flex-start;
            width: 100%;
            box-sizing: border-box;
        }
        @media (max-width: 860px) {
            .pcm-asset-body {
                grid-template-columns: 1fr;
            }
        }
        .pcm-asset-preview-pane {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            background: #faf8f5;
            border: 1px dashed #ded5cb;
            border-radius: 12px;
            padding: 16px 14px;
        }
        .pcm-hero-arch-frame {
            position: relative;
            width: 180px;
            height: 220px;
            border-radius: 48% 48% 4px 4px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.6), rgba(211, 188, 166, 0.5));
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 2px solid #805f4b;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            padding-bottom: 8px;
        }
        .pcm-hero-arch-frame img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: inherit;
        }
        .pcm-hero-arch-caption {
            position: relative;
            z-index: 2;
            color: #805f4b;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-shadow: 0 1px 3px rgba(255,255,255,0.9);
        }
        .pcm-hero-arch-mark {
            position: relative;
            z-index: 2;
            font-size: 24px;
            font-weight: 900;
            color: #203f3a;
            line-height: 1;
            text-shadow: 0 1px 4px rgba(255,255,255,0.9);
        }
        .pcm-logo-frame {
            width: 100px;
            height: 100px;
            border-radius: 16px;
            border: 2px solid #805f4b;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        }
        .pcm-logo-frame img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .pcm-asset-dim-note {
            font-size: 11px;
            color: #786f66;
            text-align: center;
            line-height: 1.35;
        }
        .pcm-asset-controls-pane {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-width: 0;
        }
        .pcm-asset-picker-box {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pcm-btn-file-select {
            background: #f4efe9 !important;
            color: #332d28 !important;
            border: 1px solid #ded5cb !important;
            cursor: pointer !important;
            padding: 10px 18px !important;
            border-radius: 8px !important;
            font-size: 13.5px !important;
            font-weight: 700 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            transition: all 0.2s ease !important;
        }
        .pcm-btn-file-select:hover {
            background: #203f3a !important;
            color: #ffffff !important;
            border-color: #203f3a !important;
        }
        .pcm-asset-filename {
            font-size: 12.5px;
            color: #6c645c;
            font-style: italic;
        }
        .pcm-asset-actions-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pcm-asset-status-msg {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.4;
        }
        .pcm-asset-status-msg.is-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .pcm-asset-status-msg.is-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .pcm-asset-info-grid {
            background: #faf8f5;
            border: 1px solid #ded5cb;
            border-radius: 8px;
            padding: 10px 14px;
        }
        .pcm-asset-info-item label {
            display: block;
            font-size: 11.5px;
            color: #786f66;
            margin-bottom: 3px;
            font-weight: 600;
        }
        .pcm-asset-info-item code {
            font-size: 12px;
            color: #203f3a;
            font-weight: 700;
            background: #ffffff;
            border: 1px solid #e0d7cd;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            word-break: break-all;
        }
        /* Ensure floating theme widget stays neat */
        .theme-mode-floating {
            bottom: 18px !important;
            right: 18px !important;
            z-index: 20000 !important;
        }
    </style>

    <section class="operations-section page-content-manager-root">
        <!-- Main Heading -->
        <div class="pcm-hero-heading">
            <div class="pcm-hero-title-wrap">
                <span class="pcm-badge-step"><i class="fas fa-layer-group"></i> Step-by-Step UI Manager</span>
                <h2><i class="fas fa-file-pen" style="color:#203f3a; margin-right:8px;"></i>ការគ្រប់គ្រងខ្លឹមសារ &amp; Note តាមទំព័រ (Page Content &amp; Notes)</h2>
                <p>កែសម្រួលចំណងជើង, Note, ការណែនាំ, ផ្លាកសញ្ញា (Badges) និងគោលការណ៍នៅលើ Storefront User UI គ្រប់ទំព័រជា ២ ភាសា (🇰🇭 ខ្មែរ &amp; 🇬🇧 English) យ៉ាងងាយស្រួលជាជំហានៗ។</p>
            </div>
            <div class="pcm-hero-stats">
                <span class="pcm-stat-pill"><i class="fas fa-check-double" style="color:#16a34a;"></i> <strong><?= $totalKeys ?></strong> កំណត់សម្គាល់សរុប (Total Notes)</span>
                <span class="pcm-stat-pill"><i class="fas fa-compass" style="color:#805f4b;"></i> <strong>7</strong> ជំហានទំព័រ (7 Pages)</span>
            </div>
        </div>

        <!-- Main Editor Form -->
        <form method="post" id="pageContentForm" class="pcm-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= storeManagementEscape($csrfToken) ?>">
            <input type="hidden" name="operation" value="save_translations">
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?= storeManagementEscape($activeTab) ?>">

            <!-- Top Control Bar: Search & Actions -->
            <div class="pcm-top-bar">
                <div class="pcm-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="propSearch" placeholder="ស្វែងរកតាមពាក្យគន្លឹះ (Search note text, headline, or property key in KM / EN)..." autocomplete="off">
                    <button type="button" id="propSearchClear" style="display:none;" title="Clear search"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="pcm-top-actions">
                    <span id="searchResultCount" class="pcm-search-counter" style="display:none;"></span>
                    <button type="submit" class="pcm-btn pcm-btn-primary">
                        <i class="fas fa-floppy-disk"></i> រក្សាទុកការកែប្រែ (Save Page Notes)
                    </button>
                </div>
            </div>

            <!-- Step Navigation Tabs Strip -->
            <div class="pcm-steps-nav" id="pcmStepsNav" role="tablist">
                <?php foreach ($steps as $sId => $sData): ?>
                    <?php $isActive = $activeTab === $sId; ?>
                    <button type="button" class="pcm-step-tab <?= $isActive ? 'is-active' : '' ?>" data-step-id="<?= storeManagementEscape($sId) ?>" role="tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                        <span class="pcm-step-num"><?= $sData['step_num'] ?></span>
                        <span class="pcm-step-icon"><i class="fas <?= storeManagementEscape($sData['icon']) ?>"></i></span>
                        <span class="pcm-step-text">
                            <strong><?= storeManagementEscape($sData['title_km']) ?></strong>
                            <small><?= storeManagementEscape($sData['title_en']) ?></small>
                        </span>
                        <span class="pcm-step-badge"><?= (int)($stepKeyCounts[$sId] ?? 0) ?></span>
                    </button>
                <?php endforeach; ?>
                <button type="button" class="pcm-step-tab pcm-step-tab-all <?= $activeTab === 'all' ? 'is-active' : '' ?>" data-step-id="all" role="tab" aria-selected="<?= $activeTab === 'all' ? 'true' : 'false' ?>">
                    <span class="pcm-step-num"><i class="fas fa-list-check"></i></span>
                    <span class="pcm-step-text">
                        <strong>ទាំងអស់ (All)</strong>
                        <small>គ្រប់ទំព័រទាំង ២១៤</small>
                    </span>
                    <span class="pcm-step-badge"><?= $totalKeys ?></span>
                </button>
            </div>

            <!-- Step Panes -->
            <div class="pcm-panes-wrapper">
                <?php foreach ($steps as $sId => $sData): ?>
                    <?php
                    $stepIndex = array_search($sId, $stepKeys, true);
                    $prevStepId = $stepIndex > 0 ? $stepKeys[$stepIndex - 1] : null;
                    $nextStepId = $stepIndex < count($stepKeys) - 1 ? $stepKeys[$stepIndex + 1] : null;
                    $isPaneActive = $activeTab === $sId || $activeTab === 'all';
                    ?>
                    <div class="pcm-step-pane <?= $activeTab === $sId ? 'is-active' : ($activeTab === 'all' ? 'is-all-view' : '') ?>" id="pane-<?= storeManagementEscape($sId) ?>" data-pane-id="<?= storeManagementEscape($sId) ?>">
                        <!-- Step Header Box -->
                        <div class="pcm-pane-header">
                            <div class="pcm-pane-info">
                                <span class="pcm-pane-badge">
                                    <i class="fas <?= storeManagementEscape($sData['icon']) ?>"></i> ជំហានទី <?= $sData['step_num'] ?> នៃ ៧: <?= storeManagementEscape($sData['badge']) ?>
                                </span>
                                <h3><?= storeManagementEscape($sData['title_km']) ?> <span class="pcm-en-sub">(<?= storeManagementEscape($sData['title_en']) ?>)</span></h3>
                                <p><?= storeManagementEscape($sData['desc']) ?></p>
                            </div>
                            <div class="pcm-pane-actions">
                                <a href="<?= storeManagementEscape($sData['preview_url']) ?>" target="_blank" rel="noopener noreferrer" class="pcm-btn pcm-btn-secondary" title="បើកមើលទំព័រផ្ទាល់ក្នុង Tab ថ្មី">
                                    <i class="fas fa-arrow-up-right-from-square"></i> មើលទំព័រផ្ទាល់ (Live Preview)
                                </a>
                                <?php if ($prevStepId): ?>
                                    <button type="button" class="pcm-btn pcm-btn-nav pcm-nav-prev" data-target-step="<?= storeManagementEscape($prevStepId) ?>">
                                        <i class="fas fa-chevron-left"></i> ជំហានមុន
                                    </button>
                                <?php endif; ?>
                                <?php if ($nextStepId): ?>
                                    <button type="button" class="pcm-btn pcm-btn-nav pcm-nav-next" data-target-step="<?= storeManagementEscape($nextStepId) ?>">
                                        ជំហានបន្ទាប់ <i class="fas fa-chevron-right"></i>
                                    </button>
                                <?php endif; ?>
                                <button type="submit" class="pcm-btn pcm-btn-primary">
                                    <i class="fas fa-floppy-disk"></i> រក្សាទុកទំព័រនេះ
                                </button>
                            </div>
                        </div>

                        <!-- Step Sections -->
                        <div class="pcm-sections-list">
                            <?php if ($sId === 'step1_home'): ?>
                                <!-- Visual Asset Box: Homepage Hero Arch Banner -->
                                <div class="pcm-asset-card" id="pcmHeroBannerCard">
                                    <div class="pcm-asset-header">
                                        <div class="pcm-asset-icon"><i class="fas fa-image"></i></div>
                                        <div class="pcm-asset-title-wrap">
                                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                                <h4>រូបភាព Hero Banner (Homepage Hero Visual Banner)</h4>
                                                <span class="pcm-asset-badge-active"><i class="fas fa-circle-check"></i> បង្ហាញលើទំព័រដើម</span>
                                            </div>
                                            <p>រូបភាព Arched Banner ដ៏លេចធ្លោនៅផ្នែកខាងស្តាំនៃ Hero Section លើទំព័រដើម។ លោកអ្នកអាចជ្រើសរើសរូបភាពពីកុំព្យូទ័រ (PC) ដើម្បីផ្លាស់ប្តូរបានភ្លាមៗ។</p>
                                        </div>
                                    </div>
                                    <div class="pcm-asset-body">
                                        <div class="pcm-asset-preview-pane">
                                            <div class="pcm-hero-arch-frame">
                                                <img src="<?= adminResolveAssetUrl($settings['home_banner_url'] ?? '', './logo/banner.png') ?>" id="pcmHeroBannerImg" alt="Hero Banner Preview">
                                                <span class="pcm-hero-arch-caption"><?= storeManagementEscape((string)($settings['store_name'] ?? 'SN STORE')) ?> · CURATED GOODS</span>
                                                <span class="pcm-hero-arch-mark"><?= storeManagementEscape(mb_substr((string)($settings['store_name'] ?? 'SN'), 0, 4)) ?></span>
                                            </div>
                                            <span class="pcm-asset-dim-note">ទំហំដែលណែនាំ: 600 x 600 px (Arch Cutout / PNG, JPG, WEBP)</span>
                                        </div>
                                        <div class="pcm-asset-controls-pane">
                                            <div class="pcm-asset-picker-box">
                                                <input type="file" id="heroBannerFileInput" name="step1_hero_banner_file" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;">
                                                <label for="heroBannerFileInput" class="pcm-btn pcm-btn-file-select">
                                                    <i class="fas fa-desktop"></i> ជ្រើសរើសរូបភាពពីកុំព្យូទ័រ (Choose from PC)
                                                </label>
                                                <span id="heroBannerFileName" class="pcm-asset-filename">មិនទាន់បានជ្រើសរើសឯកសារថ្មី</span>
                                            </div>
                                            <div class="pcm-asset-actions-row">
                                                <button type="button" id="btnUploadHeroBanner" class="pcm-btn pcm-btn-primary" style="display:none;">
                                                    <i class="fas fa-cloud-arrow-up"></i> ផ្លាស់ប្តូររូបភាពភ្លាមៗ (Upload &amp; Save from PC)
                                                </button>
                                                <button type="button" id="btnResetHeroBanner" class="pcm-btn pcm-btn-secondary" title="កំណត់ត្រឡប់ទៅរូបភាពលំនាំដើម">
                                                    <i class="fas fa-rotate-left"></i> រូបភាពលំនាំដើម (Reset Default)
                                                </button>
                                            </div>
                                            <div id="heroBannerUploadStatus" class="pcm-asset-status-msg" style="display:none;"></div>
                                            <div class="pcm-asset-info-grid">
                                                <div class="pcm-asset-info-item">
                                                    <label>ទីតាំងឯកសារបច្ចុប្បន្ន (Current File Path):</label>
                                                    <code id="heroBannerCurrentPath"><?= storeManagementEscape((string)($settings['home_banner_url'] ?? './logo/banner.png')) ?></code>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($sId === 'step7_nav'): ?>
                                <!-- Visual Asset Box: Store Logo & Favicon -->
                                <div class="pcm-asset-card" id="pcmStoreLogoCard">
                                    <div class="pcm-asset-header">
                                        <div class="pcm-asset-icon"><i class="fas fa-stamp"></i></div>
                                        <div class="pcm-asset-title-wrap">
                                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                                <h4>រូបសញ្ញាហាង Logo (Store Brand Logo &amp; Favicon)</h4>
                                                <span class="pcm-asset-badge-active"><i class="fas fa-circle-check"></i> បង្ហាញលើ Navbar &amp; Footer គ្រប់ទំព័រ</span>
                                            </div>
                                            <p>រូបសញ្ញា Logo បង្ហាញនៅ Header Navbar, Mobile Drawer, Footer និង Favicon លើគ្រប់ទំព័រទាំងអស់។ លោកអ្នកអាចជ្រើសរើស Logo ពីកុំព្យូទ័រ (PC) ដើម្បីផ្លាស់ប្តូរបានភ្លាមៗ។</p>
                                        </div>
                                    </div>
                                    <div class="pcm-asset-body">
                                        <div class="pcm-asset-preview-pane">
                                            <div class="pcm-logo-frame">
                                                <img src="<?= adminResolveAssetUrl($settings['store_logo_url'] ?? '', './logo/logome.png') ?>" id="pcmStoreLogoImg" alt="Store Logo Preview">
                                            </div>
                                            <span class="pcm-asset-dim-note">ទំហំដែលណែនាំ: 200 x 200 px (Square / Transparent PNG ឬ WEBP)</span>
                                        </div>
                                        <div class="pcm-asset-controls-pane">
                                            <div class="pcm-asset-picker-box">
                                                <input type="file" id="storeLogoFileInput" name="step7_store_logo_file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" style="display:none;">
                                                <label for="storeLogoFileInput" class="pcm-btn pcm-btn-file-select">
                                                    <i class="fas fa-desktop"></i> ជ្រើសរើស Logo ពីកុំព្យូទ័រ (Choose Logo from PC)
                                                </label>
                                                <span id="storeLogoFileName" class="pcm-asset-filename">មិនទាន់បានជ្រើសរើសឯកសារថ្មី</span>
                                            </div>
                                            <div class="pcm-asset-actions-row">
                                                <button type="button" id="btnUploadStoreLogo" class="pcm-btn pcm-btn-primary" style="display:none;">
                                                    <i class="fas fa-cloud-arrow-up"></i> ផ្លាស់ប្តូរ Logo ភ្លាមៗ (Upload &amp; Save from PC)
                                                </button>
                                                <button type="button" id="btnResetStoreLogo" class="pcm-btn pcm-btn-secondary" title="កំណត់ត្រឡប់ទៅ Logo លំនាំដើម">
                                                    <i class="fas fa-rotate-left"></i> Logo លំនាំដើម (Reset Default)
                                                </button>
                                            </div>
                                            <div id="storeLogoUploadStatus" class="pcm-asset-status-msg" style="display:none;"></div>
                                            <div class="pcm-asset-info-grid">
                                                <div class="pcm-asset-info-item">
                                                    <label>ទីតាំងឯកសារបច្ចុប្បន្ន (Current File Path):</label>
                                                    <code id="storeLogoCurrentPath"><?= storeManagementEscape((string)($settings['store_logo_url'] ?? './logo/logome.png')) ?></code>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($sData['sections'] as $secIdx => $sec): ?>
                                <div class="pcm-section-card">
                                    <div class="pcm-section-header">
                                        <div class="pcm-section-icon"><i class="fas <?= storeManagementEscape($sec['icon']) ?>"></i></div>
                                        <div class="pcm-section-title-wrap">
                                            <h4><?= storeManagementEscape($sec['title_km']) ?> <span class="pcm-en-sub">(<?= storeManagementEscape($sec['title_en']) ?>)</span></h4>
                                            <p><?= storeManagementEscape($sec['desc']) ?> · <strong><?= count($sec['keys']) ?> Notes &amp; Properties</strong></p>
                                        </div>
                                    </div>
                                    <div class="pcm-props-grid">
                                        <?php foreach ($sec['keys'] as $k => $kMeta): ?>
                                            <?php
                                            $rowGroup = $allTranslations[$k]['group'] ?? ($defaultsMap[$k]['group'] ?? 'general');
                                            $rowKm = $allTranslations[$k]['km'] ?? ($defaultsMap[$k]['km'] ?? '');
                                            $rowEn = $allTranslations[$k]['en'] ?? ($defaultsMap[$k]['en'] ?? '');
                                            $rowDesc = $kMeta['hint'] ?? ($defaultsMap[$k]['desc'] ?? '');
                                            $isTextarea = ($kMeta['type'] ?? 'input') === 'textarea' || mb_strlen($rowKm) > 65 || mb_strlen($rowEn) > 65;
                                            $searchHaystack = strtolower($k . ' ' . $kMeta['title'] . ' ' . $kMeta['hint'] . ' ' . $rowKm . ' ' . $rowEn);
                                            ?>
                                            <div class="pcm-prop-card" data-key="<?= storeManagementEscape($k) ?>" data-search="<?= storeManagementEscape($searchHaystack) ?>">
                                                <div class="pcm-prop-header">
                                                    <div class="pcm-prop-title-box">
                                                        <h5><?= storeManagementEscape($kMeta['title']) ?></h5>
                                                        <span class="pcm-prop-hint"><i class="fas fa-location-dot"></i> <?= storeManagementEscape($kMeta['hint']) ?></span>
                                                    </div>
                                                    <div class="pcm-prop-badges">
                                                        <code class="pcm-key-tag" title="Technical database key"><?= storeManagementEscape($k) ?></code>
                                                    </div>
                                                </div>
                                                <div class="pcm-prop-inputs">
                                                    <!-- Khmer Column -->
                                                    <div class="pcm-input-col">
                                                        <label class="pcm-lang-label">
                                                            <span class="pcm-flag">🇰🇭</span>
                                                            <strong>ភាសាខ្មែរ (Khmer Note)</strong>
                                                        </label>
                                                        <?php if ($isTextarea): ?>
                                                            <textarea class="pcm-input pcm-input-km" name="trans[<?= storeManagementEscape($k) ?>][km]" rows="3" placeholder="បញ្ចូលអត្ថបទជាភាសាខ្មែរ..."><?= storeManagementEscape($rowKm) ?></textarea>
                                                        <?php else: ?>
                                                            <input type="text" class="pcm-input pcm-input-km" name="trans[<?= storeManagementEscape($k) ?>][km]" value="<?= storeManagementEscape($rowKm) ?>" placeholder="បញ្ចូលអត្ថបទជាភាសាខ្មែរ...">
                                                        <?php endif; ?>
                                                    </div>
                                                    <!-- English Column -->
                                                    <div class="pcm-input-col">
                                                        <label class="pcm-lang-label">
                                                            <span class="pcm-flag">🇬🇧</span>
                                                            <strong>English (ភាសាអង់គ្លេស)</strong>
                                                        </label>
                                                        <?php if ($isTextarea): ?>
                                                            <textarea class="pcm-input pcm-input-en" name="trans[<?= storeManagementEscape($k) ?>][en]" rows="3" placeholder="Enter English text..."><?= storeManagementEscape($rowEn) ?></textarea>
                                                        <?php else: ?>
                                                            <input type="text" class="pcm-input pcm-input-en" name="trans[<?= storeManagementEscape($k) ?>][en]" value="<?= storeManagementEscape($rowEn) ?>" placeholder="Enter English text...">
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="trans[<?= storeManagementEscape($k) ?>][group]" value="<?= storeManagementEscape($rowGroup) ?>">
                                                <input type="hidden" name="trans[<?= storeManagementEscape($k) ?>][desc]" value="<?= storeManagementEscape($rowDesc) ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Step Bottom Navigation Footer -->
                        <div class="pcm-pane-footer">
                            <div class="pcm-pane-footer-left">
                                <span><i class="fas fa-circle-info" style="color:#805f4b;"></i> រាល់ការកែប្រែទាំងអស់នឹងឆ្លុះបញ្ចាំងលើទំព័រផ្ទាល់ភ្លាមៗបន្ទាប់ពីចុចរក្សាទុក។</span>
                            </div>
                            <div class="pcm-pane-footer-right">
                                <?php if ($prevStepId): ?>
                                    <button type="button" class="pcm-btn pcm-btn-secondary pcm-nav-prev" data-target-step="<?= storeManagementEscape($prevStepId) ?>">
                                        <i class="fas fa-arrow-left"></i> ជំហានមុន (<?= storeManagementEscape($steps[$prevStepId]['title_km']) ?>)
                                    </button>
                                <?php endif; ?>
                                <?php if ($nextStepId): ?>
                                    <button type="button" class="pcm-btn pcm-btn-secondary pcm-nav-next" data-target-step="<?= storeManagementEscape($nextStepId) ?>">
                                        ជំហានបន្ទាប់ (<?= storeManagementEscape($steps[$nextStepId]['title_km']) ?>) <i class="fas fa-arrow-right"></i>
                                    </button>
                                <?php endif; ?>
                                <button type="submit" class="pcm-btn pcm-btn-primary">
                                    <i class="fas fa-floppy-disk"></i> រក្សាទុកខ្លឹមសារទំព័រនេះ (Save Page)
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Sticky Bottom Bar -->
            <div class="pcm-sticky-bar" id="pcmStickyBar">
                <div class="pcm-sticky-inner">
                    <div class="pcm-sticky-status">
                        <span class="pcm-status-dot" id="pcmStatusDot"></span>
                        <span id="pcmStatusText">គ្រប់គ្រងខ្លឹមសារ &amp; Note តាមទំព័រ (Step-by-Step)</span>
                    </div>
                    <div class="pcm-sticky-actions">
                        <button type="submit" class="pcm-btn pcm-btn-primary" style="padding:10px 28px; font-size:14px; box-shadow:0 4px 14px rgba(32,63,58,0.25);">
                            <i class="fas fa-floppy-disk"></i> រក្សាទុកការកែប្រែ (Save All Changes)
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('propSearch');
            const searchClear = document.getElementById('propSearchClear');
            const searchResultCount = document.getElementById('searchResultCount');
            const stepTabs = document.querySelectorAll('.pcm-step-tab');
            const stepPanes = document.querySelectorAll('.pcm-step-pane');
            const activeTabInput = document.getElementById('activeTabInput');
            const statusDot = document.getElementById('pcmStatusDot');
            const statusText = document.getElementById('pcmStatusText');
            const form = document.getElementById('pageContentForm');

            const stepTitles = {
                'step1_home': 'ជំហានទី ១: ទំព័រដើម & Hero',
                'step2_about': 'ជំហានទី ២: ទំព័រអំពីយើង (About Us)',
                'step3_support': 'ជំហានទី ៣: ទំព័រជំនួយការ (Support)',
                'step4_checkout': 'ជំហានទី ៤: ទំព័រទូទាត់ប្រាក់ (Checkout)',
                'step5_products': 'ជំហានទី ៥: ផលិតផល & កន្ត្រក (Products & Cart)',
                'step6_auth': 'ជំហានទី ៦: គណនី & ប្រវត្តិរូប (Auth & Profile)',
                'step7_nav': 'ជំហានទី ៧: រុករក & Footer',
                'all': 'ខ្លឹមសារទាំងអស់ (All Content - 214)'
            };

            // Switch Tab Function
            function switchTab(stepId) {
                stepTabs.forEach(t => {
                    const match = t.dataset.stepId === stepId;
                    t.classList.toggle('is-active', match);
                    t.setAttribute('aria-selected', match ? 'true' : 'false');
                });
                stepPanes.forEach(p => {
                    if (stepId === 'all') {
                        p.classList.remove('is-active');
                        p.classList.add('is-all-view');
                    } else {
                        p.classList.remove('is-all-view');
                        p.classList.toggle('is-active', p.dataset.paneId === stepId);
                    }
                });
                if (activeTabInput) activeTabInput.value = stepId;
                if (statusText && stepTitles[stepId]) {
                    statusText.textContent = stepTitles[stepId];
                }
                const url = new URL(window.location.href);
                url.searchParams.set('tab', stepId);
                window.history.replaceState({}, '', url.toString());
                applySearch();
            }

            // Initialize status text
            const initTab = activeTabInput?.value || 'step1_home';
            if (statusText && stepTitles[initTab]) {
                statusText.textContent = stepTitles[initTab];
            }

            // Step Tab Clicks
            stepTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    switchTab(tab.dataset.stepId);
                });
            });

            // Prev / Next Step Buttons
            document.querySelectorAll('.pcm-nav-prev, .pcm-nav-next').forEach(btn => {
                btn.addEventListener('click', () => {
                    const target = btn.dataset.targetStep;
                    if (target) {
                        switchTab(target);
                        const navEl = document.querySelector('.pcm-steps-nav');
                        if (navEl) {
                            window.scrollTo({ top: navEl.offsetTop - 20, behavior: 'smooth' });
                        }
                    }
                });
            });

            // Live Search Filter
            function applySearch() {
                const query = (searchInput?.value || '').toLowerCase().trim();
                if (searchClear) searchClear.style.display = query ? 'inline-block' : 'none';
                let matchCount = 0;

                const currentActiveTab = activeTabInput?.value || 'step1_home';
                const cards = document.querySelectorAll('.pcm-prop-card');

                cards.forEach(card => {
                    const haystack = card.dataset.search || '';
                    const matchesQuery = !query || haystack.includes(query);
                    const parentPane = card.closest('.pcm-step-pane');
                    const isInActivePane = currentActiveTab === 'all' || parentPane?.dataset.paneId === currentActiveTab;

                    if (currentActiveTab === 'all') {
                        card.style.display = matchesQuery ? '' : 'none';
                        if (matchesQuery) matchCount++;
                    } else {
                        if (isInActivePane) {
                            card.style.display = matchesQuery ? '' : 'none';
                            if (matchesQuery) matchCount++;
                        }
                    }
                });

                // Toggle section visibility if all cards inside are hidden
                document.querySelectorAll('.pcm-section-card').forEach(sec => {
                    const visibleCards = sec.querySelectorAll('.pcm-prop-card:not([style*="display: none"])');
                    sec.style.display = visibleCards.length > 0 ? '' : 'none';
                });

                if (searchResultCount) {
                    if (query) {
                        searchResultCount.style.display = 'inline-block';
                        searchResultCount.textContent = `រកឃើញ ${matchCount} កំណត់សម្គាល់ (matches)`;
                    } else {
                        searchResultCount.style.display = 'none';
                    }
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', applySearch);
            }
            if (searchClear) {
                searchClear.addEventListener('click', () => {
                    searchInput.value = '';
                    applySearch();
                    searchInput.focus();
                });
            }

            // Detect Unsaved Changes
            if (form) {
                form.addEventListener('input', () => {
                    if (statusDot) statusDot.classList.add('has-changes');
                    if (statusText) statusText.textContent = '● មានការកែប្រែមិនទាន់រក្សាទុក (Unsaved changes)';
                });
            }

            // Ctrl+S / Cmd+S to Quick Save
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    form?.submit();
                }
            });

            // Hero Banner Uploader from PC
            const heroFileInput = document.getElementById('heroBannerFileInput');
            const heroFileName = document.getElementById('heroBannerFileName');
            const btnUploadHero = document.getElementById('btnUploadHeroBanner');
            const btnResetHero = document.getElementById('btnResetHeroBanner');
            const heroImg = document.getElementById('pcmHeroBannerImg');
            const heroPath = document.getElementById('heroBannerCurrentPath');
            const heroStatus = document.getElementById('heroBannerUploadStatus');

            if (heroFileInput) {
                heroFileInput.addEventListener('change', () => {
                    const file = heroFileInput.files[0];
                    if (!file) return;
                    if (heroFileName) heroFileName.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        if (heroImg) heroImg.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    if (btnUploadHero) {
                        btnUploadHero.style.display = 'inline-flex';
                    }
                });
            }

            if (btnUploadHero) {
                btnUploadHero.addEventListener('click', async () => {
                    const file = heroFileInput?.files[0];
                    if (!file) return;
                    const fd = new FormData();
                    fd.append('asset_type', 'home_banner');
                    fd.append('action', 'upload');
                    fd.append('asset_file', file);
                    fd.append('csrf_token', '<?= $csrfToken ?>');

                    btnUploadHero.disabled = true;
                    btnUploadHero.innerHTML = '<i class="fas fa-spinner fa-spin"></i> កំពុងបញ្ចូល...';
                    if (heroStatus) heroStatus.style.display = 'none';

                    try {
                        const res = await fetch('../api/upload-store-asset.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            if (heroStatus) {
                                heroStatus.className = 'pcm-asset-status-msg is-success';
                                heroStatus.innerHTML = `<i class="fas fa-circle-check"></i> <strong>ជោគជ័យ!</strong> ${data.message} <a href="../home/index.php" target="_blank" style="margin-left:8px; color:#065f46; text-decoration:underline; font-weight:700;">[ មើលលើទំព័រដើម ↗ ]</a>`;
                                heroStatus.style.display = 'block';
                            }
                            if (heroPath) heroPath.textContent = data.db_path;
                            if (heroImg) heroImg.src = data.preview_url + '?t=' + Date.now();
                            btnUploadHero.style.display = 'none';
                        } else {
                            if (heroStatus) {
                                heroStatus.className = 'pcm-asset-status-msg is-error';
                                heroStatus.innerHTML = `<i class="fas fa-triangle-exclamation"></i> ${data.message || 'Error uploading image'}`;
                                heroStatus.style.display = 'block';
                            }
                        }
                    } catch (err) {
                        if (heroStatus) {
                            heroStatus.className = 'pcm-asset-status-msg is-error';
                            heroStatus.innerHTML = `<i class="fas fa-triangle-exclamation"></i> Error: ${err.message}`;
                            heroStatus.style.display = 'block';
                        }
                    } finally {
                        btnUploadHero.disabled = false;
                        btnUploadHero.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> ផ្លាស់ប្តូររូបភាពភ្លាមៗ (Upload &amp; Save from PC)';
                    }
                });
            }

            if (btnResetHero) {
                btnResetHero.addEventListener('click', async () => {
                    if (!confirm('តើអ្នកប្រាកដជាចង់កំណត់រូបភាព Hero Banner ទៅជាលំនាំដើមវិញមែនទេ? (Reset Hero banner to default?)')) return;
                    const fd = new FormData();
                    fd.append('asset_type', 'home_banner');
                    fd.append('action', 'reset_default');
                    fd.append('csrf_token', '<?= $csrfToken ?>');

                    try {
                        const res = await fetch('../api/upload-store-asset.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            if (heroImg) heroImg.src = data.preview_url + '?t=' + Date.now();
                            if (heroPath) heroPath.textContent = data.db_path;
                            if (heroStatus) {
                                heroStatus.className = 'pcm-asset-status-msg is-success';
                                heroStatus.innerHTML = `<i class="fas fa-circle-check"></i> ${data.message}`;
                                heroStatus.style.display = 'block';
                            }
                            if (btnUploadHero) btnUploadHero.style.display = 'none';
                            if (heroFileName) heroFileName.textContent = 'បានកំណត់ទៅលំនាំដើមវិញ';
                            if (heroFileInput) heroFileInput.value = '';
                        }
                    } catch (err) {
                        alert('Reset failed: ' + err.message);
                    }
                });
            }

            // Store Logo Uploader from PC
            const logoFileInput = document.getElementById('storeLogoFileInput');
            const logoFileName = document.getElementById('storeLogoFileName');
            const btnUploadLogo = document.getElementById('btnUploadStoreLogo');
            const btnResetLogo = document.getElementById('btnResetStoreLogo');
            const logoImg = document.getElementById('pcmStoreLogoImg');
            const logoPath = document.getElementById('storeLogoCurrentPath');
            const logoStatus = document.getElementById('storeLogoUploadStatus');

            if (logoFileInput) {
                logoFileInput.addEventListener('change', () => {
                    const file = logoFileInput.files[0];
                    if (!file) return;
                    if (logoFileName) logoFileName.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        if (logoImg) logoImg.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    if (btnUploadLogo) {
                        btnUploadLogo.style.display = 'inline-flex';
                    }
                });
            }

            if (btnUploadLogo) {
                btnUploadLogo.addEventListener('click', async () => {
                    const file = logoFileInput?.files[0];
                    if (!file) return;
                    const fd = new FormData();
                    fd.append('asset_type', 'store_logo');
                    fd.append('action', 'upload');
                    fd.append('asset_file', file);
                    fd.append('csrf_token', '<?= $csrfToken ?>');

                    btnUploadLogo.disabled = true;
                    btnUploadLogo.innerHTML = '<i class="fas fa-spinner fa-spin"></i> កំពុងបញ្ចូល...';
                    if (logoStatus) logoStatus.style.display = 'none';

                    try {
                        const res = await fetch('../api/upload-store-asset.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            if (logoStatus) {
                                logoStatus.className = 'pcm-asset-status-msg is-success';
                                logoStatus.innerHTML = `<i class="fas fa-circle-check"></i> <strong>ជោគជ័យ!</strong> ${data.message} <a href="../home/index.php" target="_blank" style="margin-left:8px; color:#065f46; text-decoration:underline; font-weight:700;">[ មើល Navbar ↗ ]</a>`;
                                logoStatus.style.display = 'block';
                            }
                            if (logoPath) logoPath.textContent = data.db_path;
                            if (logoImg) logoImg.src = data.preview_url + '?t=' + Date.now();
                            btnUploadLogo.style.display = 'none';
                        } else {
                            if (logoStatus) {
                                logoStatus.className = 'pcm-asset-status-msg is-error';
                                logoStatus.innerHTML = `<i class="fas fa-triangle-exclamation"></i> ${data.message || 'Error uploading file'}`;
                                logoStatus.style.display = 'block';
                            }
                        }
                    } catch (err) {
                        if (logoStatus) {
                            logoStatus.className = 'pcm-asset-status-msg is-error';
                            logoStatus.innerHTML = `<i class="fas fa-triangle-exclamation"></i> Error: ${err.message}`;
                            logoStatus.style.display = 'block';
                        }
                    } finally {
                        btnUploadLogo.disabled = false;
                        btnUploadLogo.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> ផ្លាស់ប្តូរ Logo ភ្លាមៗ (Upload &amp; Save from PC)';
                    }
                });
            }

            if (btnResetLogo) {
                btnResetLogo.addEventListener('click', async () => {
                    if (!confirm('តើអ្នកប្រាកដជាចង់កំណត់ Store Logo ទៅជាលំនាំដើមវិញមែនទេ? (Reset Logo to default?)')) return;
                    const fd = new FormData();
                    fd.append('asset_type', 'store_logo');
                    fd.append('action', 'reset_default');
                    fd.append('csrf_token', '<?= $csrfToken ?>');

                    try {
                        const res = await fetch('../api/upload-store-asset.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            if (logoImg) logoImg.src = data.preview_url + '?t=' + Date.now();
                            if (logoPath) logoPath.textContent = data.db_path;
                            if (logoStatus) {
                                logoStatus.className = 'pcm-asset-status-msg is-success';
                                logoStatus.innerHTML = `<i class="fas fa-circle-check"></i> ${data.message}`;
                                logoStatus.style.display = 'block';
                            }
                            if (btnUploadLogo) btnUploadLogo.style.display = 'none';
                            if (logoFileName) logoFileName.textContent = 'បានកំណត់ទៅលំនាំដើមវិញ';
                            if (logoFileInput) logoFileInput.value = '';
                        }
                    } catch (err) {
                        alert('Reset failed: ' + err.message);
                    }
                });
            }
        });
    </script>
    <?php else: ?>
    <section class="operations-section"><div class="operations-section-heading"><div><h2>Notifications</h2><p><?= $unreadCount ?> unread · New orders and low-stock alerts</p></div>
        <?php if ($unreadCount): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= storeManagementEscape($csrfToken) ?>"><input type="hidden" name="operation" value="mark_all_notifications_read"><button class="operation-button operation-button-secondary" type="submit">Mark all read</button></form><?php endif; ?>
    </div>
        <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Type</th><th>Notification</th><th>When</th><th>Status</th></tr></thead><tbody>
        <?php if ($notifications): while ($notification = $notifications->fetch_assoc()): ?><tr>
            <td><?= storeManagementEscape(ucwords(str_replace('_', ' ', (string) $notification['notification_type']))) ?></td>
            <td><strong><?= storeManagementEscape((string) $notification['title']) ?></strong><br><?= storeManagementEscape((string) $notification['message']) ?><?php if ($notification['target_url']): ?><br><a href="<?= storeManagementEscape((string) $notification['target_url']) ?>">Open</a><?php endif; ?></td>
            <td><?= storeManagementEscape((string) $notification['created_at']) ?></td>
            <td><?php if ($notification['read_at'] === null): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= storeManagementEscape($csrfToken) ?>"><input type="hidden" name="operation" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>"><button class="operation-button operation-button-secondary" type="submit">Mark read</button></form><?php else: ?>Read<?php endif; ?></td>
        </tr><?php endwhile; else: ?><tr><td colspan="4">No notifications yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <?php endif; ?>
</main>
<script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js') ?>"></script>
</body>
</html>
