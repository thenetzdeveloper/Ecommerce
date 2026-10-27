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
    'notifications' => ['permission' => 'notifications', 'label' => 'Notifications'],
];
$view = is_string($_GET['view'] ?? null) && isset($views[$_GET['view']]) ? $_GET['view'] : 'promotions';
adminRequirePermission($currentAdmin, $views[$view]['permission']);
$primarySection = ['promotions' => 'promotions', 'homepage' => 'homepage', 'store' => 'settings', 'notifications' => 'notifications'][$view];
$errorMessage = '';
$flashMessage = is_string($_SESSION['store_management_flash'] ?? null) ? $_SESSION['store_management_flash'] : '';
unset($_SESSION['store_management_flash']);

function storeManagementEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function storeManagementRedirect(string $view, string $message): void
{
    $_SESSION['store_management_flash'] = $message;
    header('Location: store-management.php?view=' . rawurlencode($view));
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adminCsrfValid()) {
        http_response_code(403);
        exit('The form expired. Reload the page and try again.');
    }
    $operation = is_string($_POST['operation'] ?? null) ? $_POST['operation'] : '';
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
            : ['store_name' => 80, 'store_logo_url' => 500, 'store_contact_url' => 500, 'currency_symbol' => 12];
        $settings = [];
        foreach ($fields as $key => $maxLength) {
            $value = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
            if (strlen($value) > $maxLength) {
                $errorMessage = 'One or more settings exceed the allowed length.';
                break;
            }
            if (in_array($key, ['store_logo_url', 'store_contact_url', 'home_banner_url'], true) && $value !== '') {
                $scheme = parse_url($value, PHP_URL_SCHEME);
                if (!filter_var($value, FILTER_VALIDATE_URL) || strtolower((string) $scheme) !== 'https') {
                    $errorMessage = 'Logo and contact URLs must use HTTPS.';
                    break;
                }
            }
            if ($key === 'currency_symbol' && preg_match('/^[A-Za-z$€£៛]{1,12}$/u', $value) !== 1) {
                $errorMessage = 'Enter a short currency symbol or code.';
                break;
            }
            $settings[$key] = ['value' => $value, 'description' => str_replace('_', ' ', ucfirst($key))];
        }
        if ($errorMessage === '') {
            if (storeManagementSaveSettings($conn, (int) $currentAdmin['id'], $settings)) {
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
header('Cache-Control: no-store, private');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard • 4K STORE</title>
    <link rel="icon" href="../home/logo/logome.png" type="image/x-icon">
    <link rel="apple-touch-icon" href="../home/logo/logome.png">
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
    <?php $isHomepage = $view === 'homepage'; $fields = $isHomepage ? ['home_hero_title' => ['Hero headline', 160], 'home_hero_description' => ['Hero description', 800], 'home_banner_url' => ['Hero banner image URL (HTTPS)', 500]] : ['store_name' => ['Store name', 80], 'store_logo_url' => ['Logo URL (HTTPS)', 500], 'store_contact_url' => ['Contact link (HTTPS)', 500], 'currency_symbol' => ['Currency symbol or code', 12]]; ?>
    <section class="operations-section">
        <div class="operations-section-heading"><div><h2><?= $isHomepage ? 'Homepage content' : 'Store settings' ?></h2><p><?= $isHomepage ? 'Update the storefront hero copy.' : 'Set store branding, contact destination, and displayed currency.' ?></p></div></div>
        <form class="security-create-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= storeManagementEscape($csrfToken) ?>"><input type="hidden" name="operation" value="<?= $isHomepage ? 'save_homepage' : 'save_store' ?>">
            <?php foreach ($fields as $key => [$label, $maxLength]): ?><label><?= storeManagementEscape($label) ?><?php if ($key === 'home_hero_description'): ?><textarea class="operation-input" name="<?= storeManagementEscape($key) ?>" maxlength="<?= $maxLength ?>" rows="4"><?= storeManagementEscape((string) ($settings[$key] ?? '')) ?></textarea><?php else: ?><input class="operation-input" name="<?= storeManagementEscape($key) ?>" maxlength="<?= $maxLength ?>" value="<?= storeManagementEscape((string) ($settings[$key] ?? ($key === 'currency_symbol' ? '$' : ''))) ?>" <?= in_array($key, ['store_logo_url', 'store_contact_url'], true) ? 'type="url"' : 'type="text"' ?>><?php endif; ?></label><?php endforeach; ?>
            <button class="operation-button" type="submit">Save settings</button>
        </form>
    </section>
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
