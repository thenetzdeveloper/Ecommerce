<?php
require_once __DIR__ . '/../api/admin-auth.php';
require_once __DIR__ . '/primary-navigation.php';

if (!ensureAdminSecuritySchema($conn)) {
    http_response_code(500);
    exit('Could not prepare admin security. Check the server error log.');
}
if (!adminStartSession()) {
    http_response_code(500);
    exit('Could not start the admin session.');
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$loginError = '';
$csrfValid = adminCsrfValid();
$csrfToken = adminCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    if (!$csrfValid) {
        $loginError = 'The sign-in form expired. Please try again.';
    } else {
        $usernameInput = $_POST['username'] ?? '';
        $username = is_string($usernameInput) ? trim($usernameInput) : '';
        $password = $_POST['password'] ?? '';
        $loginLocks = adminAcquireLoginLocks($conn, $username);
        if ($loginLocks === null) {
            $loginError = 'Admin sign-in is temporarily unavailable. Please try again.';
        } elseif (adminLoginIsLimited($conn, $username)) {
            adminReleaseLoginLocks($conn, $loginLocks);
            $loginError = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
        } else {
            $stmt = $conn->prepare('SELECT id, username, password, role, permissions, is_active FROM admins WHERE username = ? LIMIT 1');
            if (!$stmt) {
                error_log('Could not prepare admin login lookup: ' . $conn->error);
                http_response_code(500);
                exit('Admin sign-in is temporarily unavailable.');
            }
            $stmt->bind_param('s', $username);
            if (!$stmt->execute()) {
                error_log('Could not execute admin login lookup: ' . $stmt->error);
                $stmt->close();
                http_response_code(500);
                exit('Admin sign-in is temporarily unavailable.');
            }
            $admin = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $storedHash = is_array($admin) ? (string) $admin['password'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $passwordValid = is_string($password) && password_verify($password, $storedHash);
            if ($admin && (int) $admin['is_active'] === 1 && $passwordValid) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = (string) $admin['username'];
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
                if (!adminRegisterSession($conn, (int) $admin['id'])) {
                    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_csrf_token']);
                    adminReleaseLoginLocks($conn, $loginLocks);
                    $loginError = 'Admin sign-in is temporarily unavailable.';
                } else {
                    adminClearFailedLogins($conn, $username);
                    adminWriteAudit($conn, 'login');
                    adminReleaseLoginLocks($conn, $loginLocks);
                    header('Location: index.php');
                    exit;
                }
            } else {
                adminRecordFailedLogin($conn, $username);
                adminReleaseLoginLocks($conn, $loginLocks);
                $loginError = 'Incorrect username or password.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout' && $csrfValid) {
    adminDestroySession($conn);
    header('Location: index.php');
    exit;
}

$currentAdmin = adminCurrentUser($conn);
if ($currentAdmin === null) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Sign In</title>
        <link rel="stylesheet" href="../home/page.css">
        <link rel="stylesheet" href="../home/fashion-theme.css?v=<?php echo filemtime(__DIR__ . '/../home/fashion-theme.css'); ?>">
        <script src="../js/page-navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/page-navigation.js'); ?>" defer></script>
        <style>
            .admin-login-wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
            .admin-login-card { width: min(100%, 420px); padding: 34px; border: 1px solid rgba(0,242,254,.35); border-radius: 24px; background: rgba(10,16,32,.92); color: #fff; }
            .admin-login-card h1 { margin: 0 0 8px; }
            .admin-login-card p { color: rgba(255,255,255,.7); }
            .admin-login-card label { display: block; margin: 18px 0 7px; }
            .admin-login-card input { box-sizing: border-box; width: 100%; padding: 12px; border: 1px solid rgba(0,242,254,.3); border-radius: 10px; background: rgba(255,255,255,.08); color: #fff; font: inherit; }
            .admin-login-card button { width: 100%; margin-top: 22px; padding: 12px; border: 0; border-radius: 10px; background: #00d9ee; color: #07111e; font: inherit; font-weight: 700; cursor: pointer; }
            .admin-login-error { margin-top: 16px; color: #ff8b9b; }
        </style>
        <link rel="stylesheet" href="./backoffice-theme.css?v=<?php echo filemtime(__DIR__ . '/backoffice-theme.css'); ?>">
        <link rel="stylesheet" href="../css/theme-mode.css?v=<?php echo filemtime(__DIR__ . '/../css/theme-mode.css'); ?>">
    </head>
    <body class="fashion-storefront admin-storefront">
        <main class="admin-login-wrap">
            <form class="admin-login-card" method="post" action="index.php">
                <h1>Admin sign in</h1>
                <p>Sign in to view private customer orders and manage products.</p>
                <?php if ($loginError !== ''): ?><div class="admin-login-error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label for="adminUsername">Username</label>
                <input id="adminUsername" name="username" type="text" autocomplete="username" required>
                <label for="adminPassword">Password</label>
                <input id="adminPassword" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Sign in</button>
            </form>
        </main>
        <script src="../js/theme-mode.js?v=<?php echo filemtime(__DIR__ . '/../js/theme-mode.js'); ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

$adminUsername = (string) $currentAdmin['username'];
if (!adminHasPermission($currentAdmin, 'products')) {
    if (adminHasPermission($currentAdmin, 'security')) {
        header('Location: security.php');
        exit;
    }
    foreach (['orders', 'inventory', 'customers', 'reports'] as $permission) {
        if (adminHasPermission($currentAdmin, $permission)) {
            header('Location: operations.php?view=' . rawurlencode($permission));
            exit;
        }
    }
    if (adminHasPermission($currentAdmin, 'support')) {
        header('Location: ../customer-service/dashboard.php');
        exit;
    }
    http_response_code(403);
    exit('You do not have permission to access the admin dashboard.');
}
header('Cache-Control: no-store, private');
if (!in_array($action, ['list', 'add', 'edit', 'delete'], true)) {
    $action = 'list';
}

if (!ensureProductStockColumn($conn)) {
    http_response_code(500);
    exit('Could not prepare the product stock schema. Check the server error log.');
}
if (!ensureSalesAnalyticsSchema($conn)) {
    http_response_code(500);
    exit('Could not prepare the sales analytics schema. Check the server error log.');
}
if (!ensureAdminOperationsSchema($conn) || !ensureStoreManagementSchema($conn)) {
    http_response_code(500);
    exit('Could not prepare admin operations. Check the server error log.');
}
$currencySymbol = storeCurrencySymbol($conn);

$error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit', 'delete'], true) && !$csrfValid) {
    $error_msg = 'The form expired. Please reload and try again.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout' && !$csrfValid) {
    $error_msg = 'The sign-out form expired. Please reload and try again.';
    $action = 'list';
}
$product = null;
$id = filter_var($_POST['id'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT);

function saveProductActivity(mysqli $connection, ?int $productId, string $productName, string $action, string $actor, ?array $oldValues, ?array $newValues): bool
{
    $statement = $connection->prepare('INSERT INTO product_activity (product_id, product_name, action, actor, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$statement) {
        error_log('Could not prepare product activity log: ' . $connection->error);
        return false;
    }

    $oldJson = $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $newJson = $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (($oldValues !== null && $oldJson === false) || ($newValues !== null && $newJson === false)) {
        $statement->close();
        error_log('Could not encode product activity details.');
        return false;
    }

    $statement->bind_param('isssss', $productId, $productName, $action, $actor, $oldJson, $newJson);
    $saved = $statement->execute();
    if (!$saved) {
        error_log('Could not save product activity log: ' . $statement->error);
    }
    $statement->close();
    return $saved;
}

function productActivitySnapshot(array $product): array
{
    return [
        'name' => (string) $product['name'],
        'description' => hash('sha256', (string) ($product['description'] ?? '')),
        'price' => $product['price'] === null ? null : (float) $product['price'],
        'cost_price' => $product['cost_price'] === null ? null : (float) $product['cost_price'],
        'stock' => (int) $product['stock'],
        'image' => (string) ($product['image'] ?? ''),
    ];
}

if ($action === 'edit' || $action === 'delete') {
    if (!$id || $id < 1) {
        header('Location: index.php');
        exit;
    }

    $stmt = $conn->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        header('Location: index.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $csrfValid) {
    $conn->begin_transaction();
    $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
    if (!$stmt) {
        $conn->rollback();
        http_response_code(500);
        exit('Could not prepare product deletion: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
    }
    $stmt->bind_param('i', $id);

    $deleteSucceeded = $stmt->execute();
    $activitySucceeded = $deleteSucceeded && saveProductActivity($conn, (int) $id, (string) $product['name'], 'delete', $adminUsername, productActivitySnapshot($product), null);
    $transactionCommitted = $activitySucceeded && $conn->commit();
    if ($transactionCommitted) {
        $stmt->close();
        header('Location: index.php');
        exit;
    }

    $error_msg = $deleteSucceeded
        ? 'The product could not be deleted because its activity record could not be saved.'
        : 'Error deleting product: ' . $stmt->error;
    $conn->rollback();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit') && $csrfValid) {
    $nameInput = $_POST['name'] ?? '';
    $descriptionInput = $_POST['description'] ?? '';
    $name = is_string($nameInput) ? trim($nameInput) : '';
    $description = is_string($descriptionInput) ? trim($descriptionInput) : '';
    $priceInput = is_string($_POST['price'] ?? null) ? $_POST['price'] : '';
    $costPriceInput = is_string($_POST['cost_price'] ?? null) ? $_POST['cost_price'] : '';
    $stockInput = is_string($_POST['stock'] ?? null) ? $_POST['stock'] : '';
    $stockDigits = is_string($stockInput) && preg_match('/^\d+$/D', $stockInput) ? ltrim($stockInput, '0') : false;
    $stock = $stockDigits === false
        ? false
        : filter_var($stockDigits === '' ? '0' : $stockDigits, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);

    $moneyPattern = '/^\d{1,8}(?:\.\d{1,2})?$/D';
    $priceValid = preg_match($moneyPattern, $priceInput) === 1 && is_finite((float) $priceInput) && (float) $priceInput <= 99999999.99;
    $costPrice = null;
    $costPriceValid = is_string($costPriceInput) && trim($costPriceInput) === '';
    if (trim($costPriceInput) !== '' && preg_match($moneyPattern, $costPriceInput) === 1 && is_finite((float) $costPriceInput) && (float) $costPriceInput <= 99999999.99) {
        $costPrice = (float) $costPriceInput;
        $costPriceValid = true;
    }

    if ($name === '' || $description === '' || !$priceValid || !$costPriceValid || $stock === false) {
        $error_msg = 'Please provide a name, description, valid non-negative price and cost, and whole-number stock amount.';
    }

    $imagePath = $action === 'edit' ? $product['image'] : '';
    $uploadedImagePath = '';
    $imageChanged = false;
    $imageSource = $_POST['image_source'] ?? 'upload';
    $imageUrlInput = $_POST['image_url'] ?? '';
    $imageUrl = is_string($imageUrlInput) ? trim($imageUrlInput) : '';
    $upload = $_FILES['icon'] ?? null;

    if (!in_array($imageSource, ['upload', 'url'], true)) {
        $error_msg = 'Please choose an image upload method.';
    }

    if ($error_msg === '' && $imageSource === 'url' && !is_string($imageUrlInput)) {
        $error_msg = 'Please provide a valid HTTP(S) image URL no longer than 255 characters.';
    }

    if ($error_msg === '' && $imageSource === 'url' && $imageUrl !== '') {
        if (strlen($imageUrl) > 255 || filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
            $error_msg = 'Please provide a valid HTTP(S) image URL no longer than 255 characters.';
        } else {
            $urlParts = parse_url($imageUrl);
            $urlScheme = is_array($urlParts) ? strtolower($urlParts['scheme'] ?? '') : '';
            if (
                !in_array($urlScheme, ['http', 'https'], true)
                || empty($urlParts['host'])
                || isset($urlParts['user'])
                || isset($urlParts['pass'])
            ) {
                $error_msg = 'Please provide a valid HTTP(S) image URL no longer than 255 characters.';
            } else {
                $imagePath = $imageUrl;
                $imageChanged = true;
            }
        }
    } elseif ($error_msg === '' && $imageSource === 'url' && $action === 'add') {
        $error_msg = 'Please provide an image URL.';
    } elseif ($error_msg === '' && $imageSource === 'upload' && $upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            $error_msg = 'Failed to upload icon.';
        } else {
            $imageTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];
            $fileType = mime_content_type($upload['tmp_name']);

            if (!isset($imageTypes[$fileType])) {
                $error_msg = 'Invalid file type. Only JPG, PNG, GIF, or WEBP allowed.';
            } else {
                $targetDirectory = __DIR__ . '/../img/products/';
                if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
                    $error_msg = 'Failed to create the product image directory.';
                } else {
                    $imageName = 'icon_' . bin2hex(random_bytes(16)) . '.' . $imageTypes[$fileType];
                    $targetPath = $targetDirectory . $imageName;

                    if (!move_uploaded_file($upload['tmp_name'], $targetPath)) {
                        $error_msg = 'Failed to upload icon.';
                    } else {
                        $uploadedImagePath = 'img/products/' . $imageName;
                        $imagePath = $uploadedImagePath;
                        $imageChanged = true;
                    }
                }
            }
        }
    } elseif ($error_msg === '' && $imageSource === 'upload' && $action === 'add') {
        $error_msg = 'Please select a product icon.';
    }

    if ($error_msg === '') {
        $price = (float) $priceInput;
        $conn->begin_transaction();
        if ($action === 'add') {
            $stmt = $conn->prepare('INSERT INTO products (name, description, price, cost_price, image, stock) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('ssddsi', $name, $description, $price, $costPrice, $imagePath, $stock);
            }
        } else {
            $stmt = $conn->prepare('UPDATE products SET name = ?, description = ?, price = ?, cost_price = ?, image = ?, stock = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ssddsii', $name, $description, $price, $costPrice, $imagePath, $stock, $id);
            }
        }

        if ($stmt && $stmt->execute()) {
            $savedId = $action === 'add' ? (int) $conn->insert_id : (int) $id;
            $newValues = [
                'name' => $name,
                'description' => hash('sha256', $description),
                'price' => $price,
                'cost_price' => $costPrice,
                'stock' => $stock,
                'image' => $imagePath,
            ];
            $oldValues = $action === 'edit' ? productActivitySnapshot($product) : null;
            $activitySaved = saveProductActivity(
                $conn,
                $savedId,
                $name,
                $action,
                $adminUsername,
                $oldValues,
                $newValues
            );
            $stockBefore = $action === 'edit' ? (int) $product['stock'] : 0;
            $stockChange = $stock - $stockBefore;
            $stockLogged = $stockChange === 0 || recordInventoryAdjustment(
                $conn,
                $savedId,
                $name,
                $stockChange,
                $stockBefore,
                $stock,
                $action === 'add' ? 'Initial stock from product form' : 'Stock changed in product form',
                $adminUsername
            );
            $lowStockThreshold = $action === 'edit' ? (int) $product['low_stock_threshold'] : 5;
            $lowStockCrossed = ($action === 'add' && $stock <= $lowStockThreshold)
                || ($action === 'edit' && $stockBefore > $lowStockThreshold && $stock <= $lowStockThreshold);
            $notificationSaved = !$lowStockCrossed || createAdminNotification(
                $conn,
                'low_stock',
                'Low stock: ' . $name,
                'Stock is now ' . $stock . ' unit(s), at or below its alert threshold.',
                'operations.php?view=inventory'
            );
            if (!$activitySaved || !$stockLogged || !$notificationSaved || !$conn->commit()) {
                $conn->rollback();
                $error_msg = 'Product and its activity record could not be saved.';
                if ($stmt) {
                    $stmt->close();
                }
            } else {
                $stmt->close();

                if ($action === 'edit' && $imageChanged && !empty($product['image'])) {
                    $oldImage = $product['image'];
                    if ($oldImage === 'img/products/' . basename($oldImage)) {
                        $oldImagePath = __DIR__ . '/../' . $oldImage;
                        if (is_file($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                }

                header('Location: index.php');
                exit;
            }
        }

        if ($error_msg === '') {
            $error_msg = 'Error saving product: ' . ($stmt ? $stmt->error : $conn->error);
            if ($stmt) {
                $stmt->close();
            }
            $conn->rollback();
        }
        if ($uploadedImagePath !== '') {
            unlink(__DIR__ . '/../' . $uploadedImagePath);
        }
    }

    if ($action === 'edit') {
        $product['name'] = $name;
        $product['description'] = $description;
        $product['price'] = $priceInput;
        $product['cost_price'] = $costPriceInput;
        $product['stock'] = $stockInput;
        $product['image'] = $uploadedImagePath !== '' ? ($product['image'] ?? '') : $imagePath;
    }
}

$formName = is_string($_POST['name'] ?? null) ? $_POST['name'] : (string) ($product['name'] ?? '');
$formDescription = is_string($_POST['description'] ?? null) ? $_POST['description'] : (string) ($product['description'] ?? '');
$formPrice = is_string($_POST['price'] ?? null) ? $_POST['price'] : (string) ($product['price'] ?? '');
$formCostPrice = is_string($_POST['cost_price'] ?? null) ? $_POST['cost_price'] : (string) ($product['cost_price'] ?? '');
$formStock = is_string($_POST['stock'] ?? null) ? $_POST['stock'] : (string) ($product['stock'] ?? '0');
$formImageUrl = is_string($_POST['image_url'] ?? null) ? $_POST['image_url'] : '';

$todayStart = new DateTimeImmutable('today', new DateTimeZone('Asia/Phnom_Penh'));
$tomorrowStart = $todayStart->modify('+1 day');
$yesterdayStart = $todayStart->modify('-1 day');
$salesSummaryStatement = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS units,
    COALESCE(SUM(COALESCE(paid_amount, unit_price * quantity - discount_amount) - (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = sales_orders.id AND h.event_type = 'refund_recorded')), 0) AS revenue,
    COALESCE(SUM(CASE WHEN unit_cost IS NOT NULL THEN COALESCE(paid_amount, unit_price * quantity - discount_amount) - (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = sales_orders.id AND h.event_type = 'refund_recorded') - unit_cost * quantity ELSE 0 END), 0) AS known_profit,
    COALESCE(SUM(CASE WHEN unit_cost IS NULL THEN quantity ELSE 0 END), 0) AS unknown_cost_units
    FROM sales_orders WHERE payment_status IN ('verified', 'partially_refunded', 'refunded') AND status <> 'Cancelled' AND created_at >= ? AND created_at < ?");
if (!$salesSummaryStatement) {
    http_response_code(500);
    exit('Could not prepare the daily sales summary: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}
$todayFrom = $todayStart->format('Y-m-d H:i:s');
$todayUntil = $tomorrowStart->format('Y-m-d H:i:s');
$salesSummaryStatement->bind_param('ss', $todayFrom, $todayUntil);
if (!$salesSummaryStatement->execute()) {
    http_response_code(500);
    exit('Could not load today\'s sales summary: ' . htmlspecialchars($salesSummaryStatement->error, ENT_QUOTES, 'UTF-8'));
}
$todaySales = $salesSummaryStatement->get_result()->fetch_assoc();
$yesterdayFrom = $yesterdayStart->format('Y-m-d H:i:s');
$yesterdayUntil = $todayStart->format('Y-m-d H:i:s');
$salesSummaryStatement->bind_param('ss', $yesterdayFrom, $yesterdayUntil);
if (!$salesSummaryStatement->execute()) {
    http_response_code(500);
    exit('Could not load yesterday\'s sales summary: ' . htmlspecialchars($salesSummaryStatement->error, ENT_QUOTES, 'UTF-8'));
}
$yesterdaySales = $salesSummaryStatement->get_result()->fetch_assoc();
$salesSummaryStatement->close();

$salesStatement = $conn->prepare('SELECT order_reference, payment_method, status, payment_status, customer_name, product_name, unit_price, quantity, coupon_code, discount_amount, location, phone_number, created_at FROM sales_orders ORDER BY created_at DESC, id DESC LIMIT 50');
$activityStatement = $conn->prepare('SELECT product_id, product_name, action, actor, old_values, new_values, created_at FROM product_activity ORDER BY created_at DESC, id DESC LIMIT 50');
if (!$salesStatement) {
    error_log('Could not prepare sales history query: ' . $conn->error);
    http_response_code(500);
    exit('Could not prepare sales history: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}
if (!$activityStatement) {
    error_log('Could not prepare product activity query: ' . $conn->error);
    $salesStatement->close();
    http_response_code(500);
    exit('Could not prepare product activity: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}
if (!$salesStatement->execute()) {
    $queryError = $salesStatement->error;
    error_log('Could not execute sales history query: ' . $queryError);
    $salesStatement->close();
    $activityStatement->close();
    http_response_code(500);
    exit('Could not load sales history: ' . htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8'));
}
$salesHistory = $salesStatement->get_result();
if (!$salesHistory) {
    error_log('Could not retrieve sales history result: ' . $salesStatement->error);
    $salesStatement->close();
    $activityStatement->close();
    http_response_code(500);
    exit('Could not retrieve sales history.');
}
if (!$activityStatement->execute()) {
    $queryError = $activityStatement->error;
    error_log('Could not execute product activity query: ' . $queryError);
    $salesStatement->close();
    $activityStatement->close();
    http_response_code(500);
    exit('Could not load product activity: ' . htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8'));
}
$activityHistory = $activityStatement->get_result();
$salesStatement->close();
$activityStatement->close();

$result = $conn->query('SELECT * FROM products ORDER BY id DESC');
$lowStockResult = $conn->query('SELECT name, stock, low_stock_threshold FROM products WHERE stock <= low_stock_threshold ORDER BY stock ASC, name ASC');
$inventoryResult = $conn->query('SELECT COALESCE(SUM(stock), 0) AS units FROM products');
if (!$result || !$lowStockResult || !$inventoryResult) {
    http_response_code(500);
    exit('Could not load product inventory: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}
$inventoryUnits = (int) $inventoryResult->fetch_assoc()['units'];
?>
<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard • 4K STORE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="../home/logo/logome.png" type="image/x-icon">
    <link rel="stylesheet" href="../home/page.css?v=<?php echo filemtime(__DIR__ . '/../home/page.css'); ?>">
    <link rel="stylesheet" href="../home/fashion-theme.css?v=<?php echo filemtime(__DIR__ . '/../home/fashion-theme.css'); ?>">
    <script src="../js/page-navigation.js?v=<?php echo filemtime(__DIR__ . '/../js/page-navigation.js'); ?>" defer></script>
    <style>
        .admin-dashboard-container {
            max-width: 1140px;
            margin: 40px auto 60px;
            padding: 0 20px;
            position: relative;
            z-index: 10;
        }

        .admin-header-glass {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.88) 0%, rgba(30, 27, 75, 0.8) 50%, rgba(15, 23, 42, 0.92) 100%);
            backdrop-filter: blur(28px) saturate(180%);
            -webkit-backdrop-filter: blur(28px) saturate(180%);
            border: 1px solid rgba(99, 102, 241, 0.35);
            border-radius: 28px;
            padding: 24px 30px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.3), 0 20px 40px rgba(0, 0, 0, 0.6);
        }

        .admin-table-glass {
            width: 100%;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85) 0%, rgba(20, 25, 55, 0.8) 100%);
            backdrop-filter: blur(28px) saturate(180%);
            -webkit-backdrop-filter: blur(28px) saturate(180%);
            border: 1px solid rgba(99, 102, 241, 0.28);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.3), 0 30px 60px -10px rgba(0, 0, 0, 0.65);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        thead {
            background: rgba(255, 255, 255, 0.08);
            border-bottom: 1px solid rgba(255, 255, 255, 0.16);
        }

        th {
            padding: 16px 20px;
            font-size: 13.5px;
            font-weight: 700;
            color: #00f2fe;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.88);
            font-size: 14px;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .app-icon-img {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.08);
        }

        .btn-table {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .btn-edit {
            background: rgba(0, 242, 254, 0.15);
            border-color: rgba(0, 242, 254, 0.4);
            color: #00f2fe;
        }

        .btn-edit:hover {
            background: rgba(0, 242, 254, 0.3);
            transform: translateY(-2px);
        }

        .btn-delete {
            background: rgba(255, 68, 68, 0.15);
            border-color: rgba(255, 68, 68, 0.4);
            color: #ff5555;
            margin-left: 6px;
        }

        .btn-delete:hover {
            background: rgba(255, 68, 68, 0.3);
            transform: translateY(-2px);
        }

        .delete-confirmation-backdrop {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(3, 6, 17, 0.76);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .delete-confirmation-backdrop[hidden] {
            display: none;
        }

        .delete-confirmation-dialog {
            width: min(100%, 420px);
            padding: 30px;
            border: 1px solid rgba(255, 85, 85, 0.55);
            border-radius: 24px;
            background: linear-gradient(145deg, rgba(19, 25, 45, 0.99), rgba(10, 15, 30, 0.99));
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.6), 0 0 28px rgba(255, 68, 68, 0.16);
            color: #fff;
            text-align: center;
        }

        .delete-warning-icon {
            display: grid;
            width: 64px;
            height: 64px;
            place-items: center;
            margin: 0 auto 18px;
            border: 1px solid rgba(255, 85, 85, 0.4);
            border-radius: 50%;
            background: rgba(255, 68, 68, 0.13);
            color: #ff6262;
            font-size: 26px;
        }

        .delete-confirmation-dialog h2 {
            margin-bottom: 10px;
            color: #fff;
            font-size: 21px;
        }

        .delete-confirmation-dialog p {
            color: rgba(226, 232, 240, 0.78);
            font-size: 14px;
            line-height: 1.65;
        }

        .delete-product-name {
            display: block;
            margin: 12px 0 22px;
            overflow-wrap: anywhere;
            color: #fff;
            font-weight: 700;
        }

        .delete-confirmation-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .delete-confirmation-actions button,
        .delete-confirmation-actions a {
            min-height: 42px;
            padding: 0 18px;
            border: 1px solid transparent;
            border-radius: 9999px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .delete-cancel-button {
            border-color: rgba(148, 163, 184, 0.35) !important;
            background: rgba(148, 163, 184, 0.12);
            color: #e2e8f0;
        }

        .delete-confirm-button {
            background: #dc3d4b;
            color: #fff;
        }

        .delete-cancel-button:hover {
            background: rgba(148, 163, 184, 0.22);
        }

        .delete-confirm-button:hover {
            background: #f04452;
        }

        .admin-form-layer {
            position: fixed;
            inset: 0;
            z-index: 3000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: max(24px, env(safe-area-inset-top)) 16px max(24px, env(safe-area-inset-bottom));
            background: rgba(3, 6, 17, 0.38);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            animation: overlay-fade-in 220ms ease-out both;
            overscroll-behavior: contain;
        }

        .admin-form-dashboard-background {
            position: fixed;
            inset: 0;
            z-index: 0;
            width: 100%;
            height: 100%;
            border: 0;
            pointer-events: none;
            animation: dashboard-reveal 500ms ease-out both;
        }

        .admin-form-card {
            box-sizing: border-box;
            width: 100%;
            max-width: 520px;
            max-height: calc(100vh - 48px);
            max-height: calc(100dvh - 48px);
            overflow-y: auto;
            padding: 38px 32px;
            border: 1px solid rgba(99, 102, 241, 0.38);
            border-radius: 32px;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 27, 75, 0.94), rgba(15, 23, 42, 0.97));
            box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.9), 0 0 35px rgba(99, 102, 241, 0.25);
            animation: form-card-enter 300ms cubic-bezier(0.2, 0.75, 0.25, 1) both;
            overscroll-behavior: contain;
            scrollbar-width: thin;
        }

        .admin-form-card h2 {
            margin-bottom: 4px;
            color: #fff;
            font-size: 26px;
            text-align: center;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .admin-form-card .form-description {
            margin-bottom: 24px;
            color: var(--text-muted);
            font-size: 13.5px;
            text-align: center;
        }

        .admin-form-card .form-group {
            margin-bottom: 20px;
        }

        .admin-form-card .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 13.5px;
            font-weight: 700;
        }

        .admin-form-card .cyber-input {
            box-sizing: border-box;
            width: 100%;
            padding: 13px 18px;
            border: 1px solid rgba(0, 240, 255, 0.25);
            border-radius: 16px;
            outline: none;
            background: rgba(0, 240, 255, 0.06);
            color: #fff;
            font: inherit;
            font-size: 16px;
        }

        .admin-form-card textarea.cyber-input {
            min-height: 95px;
            resize: vertical;
        }

        .admin-form-card .file-upload-box {
            position: relative;
            padding: 22px;
            border: 2px dashed rgba(0, 240, 255, 0.35);
            border-radius: 20px;
            background: rgba(0, 240, 255, 0.04);
            text-align: center;
        }

        .image-source-options {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }

        .image-source-option {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.035);
            color: rgba(255, 255, 255, 0.85);
            cursor: pointer;
            text-align: center;
            transition: border-color 160ms ease, background-color 160ms ease;
        }

        .image-source-option:has(input:checked) {
            border-color: var(--cyber-cyan);
            background: rgba(0, 240, 255, 0.1);
        }

        .image-source-option input {
            margin-right: 6px;
            accent-color: var(--cyber-cyan);
        }

        .image-source-panel[hidden] {
            display: none;
        }

        .image-url-hint {
            margin-top: 6px;
            color: var(--text-dim);
            font-size: 12px;
        }

        .admin-form-card .file-upload-box input[type="file"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .admin-form-card .current-app-preview {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
            padding: 12px 16px;
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.03);
            color: #fff;
        }

        .admin-form-card .current-app-preview img {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            object-fit: cover;
        }

        .admin-form-card .file-preview-wrap {
            display: none;
            margin-top: 10px;
            color: var(--cyber-emerald);
        }

        .admin-error {
            margin-bottom: 18px;
            padding: 12px 18px;
            border: 1px solid var(--cyber-pink);
            border-radius: 16px;
            background: rgba(255, 42, 133, 0.18);
            color: #ff66b2;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
        }

        .inventory-alert {
            margin-bottom: 20px;
            padding: 16px 20px;
            border: 1px solid rgba(245, 158, 11, 0.55);
            border-radius: 18px;
            background: rgba(245, 158, 11, 0.12);
            color: #fbbf24;
        }

        .inventory-alert h3 {
            margin-bottom: 8px;
            color: #fcd34d;
            font-size: 16px;
        }

        .inventory-alert ul {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 18px;
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }

        .analytics-card {
            padding: 20px;
            border: 1px solid rgba(0, 242, 254, 0.2);
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.88), rgba(20, 25, 55, 0.82));
        }

        .analytics-card span {
            display: block;
            color: var(--text-muted);
            font-size: 13px;
        }

        .analytics-card strong {
            display: block;
            margin-top: 8px;
            color: #fff;
            font-size: 25px;
        }

        .analytics-card small {
            display: block;
            margin-top: 6px;
            color: var(--text-dim);
        }

        .analytics-section {
            margin: 26px 0;
        }

        .analytics-section h3 {
            margin: 0 0 12px;
            color: #fff;
            font-size: 19px;
        }

        .analytics-table-wrap {
            overflow-x: auto;
        }

        .analytics-table-wrap table {
            min-width: 760px;
        }

        .analytics-table-wrap th,
        .analytics-table-wrap td {
            white-space: nowrap;
        }

        .trend-up { color: #34d399 !important; }
        .trend-down { color: #fca5a5 !important; }

        .stock-badge {
            display: inline-block;
            padding: 5px 10px;
            border: 1px solid rgba(16, 185, 129, 0.4);
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            font-weight: 700;
            white-space: nowrap;
        }

        .stock-badge.is-low,
        .stock-badge.is-empty {
            border-color: rgba(245, 158, 11, 0.45);
            background: rgba(245, 158, 11, 0.12);
            color: #fbbf24;
        }

        .stock-badge.is-empty {
            border-color: rgba(248, 113, 113, 0.45);
            background: rgba(248, 113, 113, 0.12);
            color: #fca5a5;
        }

        @keyframes overlay-fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes dashboard-reveal {
            from { opacity: 0.7; }
            to { opacity: 1; }
        }

        @keyframes form-card-enter {
            from { opacity: 0; transform: translateY(14px) scale(0.985); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        @media (max-width: 600px) {
            .admin-form-layer {
                align-items: flex-start;
                padding: max(12px, env(safe-area-inset-top)) 12px max(12px, env(safe-area-inset-bottom));
            }

            .admin-form-card {
                max-height: calc(100vh - 24px);
                max-height: calc(100dvh - 24px);
                padding: 24px 20px;
                border-radius: 24px;
            }

            .admin-form-card h2 {
                font-size: 23px;
            }

            .admin-form-card .form-group {
                margin-bottom: 16px;
            }

            .admin-form-card .cyber-input {
                padding: 12px 14px;
            }

            .admin-form-card .file-upload-box {
                padding: 18px 14px;
            }
        }

        @media (max-width: 360px) {
            .admin-form-layer {
                padding-right: 8px;
                padding-left: 8px;
            }

            .admin-form-card {
                padding: 20px 14px;
                border-radius: 20px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .admin-form-layer,
            .admin-form-dashboard-background,
            .admin-form-card {
                animation: none;
            }
        }

        @media (max-width: 480px) {
            .delete-confirmation-dialog {
                padding: 24px 18px;
            }

            .delete-confirmation-actions {
                flex-direction: column-reverse;
            }
        }
    </style>
    <link rel="stylesheet" href="./backoffice-theme.css?v=<?php echo filemtime(__DIR__ . '/backoffice-theme.css'); ?>">
    <link rel="stylesheet" href="../css/theme-mode.css?v=<?php echo filemtime(__DIR__ . '/../css/theme-mode.css'); ?>">
</head>

<body class="fashion-storefront admin-storefront">
    <div class="cyber-grid-lines"></div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <?php $isEditing = $action === 'edit'; ?>
        <iframe class="admin-form-dashboard-background" src="index.php?action=list" title="Product dashboard background" aria-hidden="true" tabindex="-1"></iframe>
        <div class="admin-form-layer">
            <div class="admin-form-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <a href="index.php" class="cyber-glow-btn" style="padding: 6px 16px; font-size: 13px;">
                        <i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ
                    </a>
                    <span class="dna-badge" style="font-size: 11px; padding: 4px 10px;">
                        <i class="fas fa-<?= $isEditing ? 'pen' : 'plus-circle' ?>"></i> <?= $isEditing ? 'Edit Product' : 'New Product' ?>
                    </span>
                </div>
                <h2><?= $isEditing ? 'កែសម្រួលផលិតផល' : 'បន្ថែមផលិតផលថ្មី' ?></h2>
                <p class="form-description"><?= $isEditing ? 'ផ្លាស់ប្តូរឈ្មោះ ព័ត៌មានលម្អិត តម្លៃ ឬរូបភាពផលិតផល' : 'សូមបំពេញព័ត៌មានលម្អិតនៃផលិតផលដែលអ្នកចង់ដាក់លក់' ?></p>

                <?php if ($error_msg !== ''): ?>
                    <div class="admin-error"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form action="index.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?= $isEditing ? 'edit' : 'add' ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <?php endif; ?>

                    <?php if (!$isEditing): ?>
                    <div class="form-group" id="productLinkGroup">
                        <label><i class="fas fa-link" style="color: var(--cyber-cyan); margin-right: 6px;"></i> Product Link (Auto-fill)</label>
                        <div style="display: flex; gap: 8px; align-items: stretch;">
                            <input type="url" id="productLinkInput" class="cyber-input" placeholder="https://shopee.com/product/... or any product page link" style="flex: 1; margin: 0;">
                            <button type="button" id="fetchProductBtn" class="cyber-buy-action" style="padding: 10px 18px; font-size: 13px; white-space: nowrap; flex-shrink: 0;">
                                <i class="fas fa-wand-magic-sparkles"></i> Auto Fill
                            </button>
                        </div>
                        <div id="fetchStatus" style="margin-top: 6px; font-size: 12px; color: var(--cyber-cyan); display: none;"></div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label><i class="fas fa-tag" style="color: var(--cyber-cyan); margin-right: 6px;"></i> ឈ្មោះផលិតផល (Product Name)</label>
                        <input type="text" name="name" id="fieldName" class="cyber-input" value="<?= htmlspecialchars($formName, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-align-left" style="color: var(--cyber-purple); margin-right: 6px;"></i> ព័ត៌មានលម្អិត (Description)</label>
                        <textarea name="description" id="fieldDescription" class="cyber-input" required><?= htmlspecialchars($formDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-coins" style="color: var(--cyber-emerald); margin-right: 6px;"></i> Cost price (your purchase cost)</label>
                        <input type="number" step="0.01" min="0" max="99999999.99" name="cost_price" id="fieldCostPrice" class="cyber-input" value="<?= htmlspecialchars($formCostPrice, ENT_QUOTES, 'UTF-8') ?>" placeholder="Leave empty if unknown">
                        <div class="image-url-hint">Used to calculate profit or loss. Existing products need their cost entered before profit can be calculated.</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-dollar-sign" style="color: var(--cyber-emerald); margin-right: 6px;"></i> តម្លៃជាដុល្លារ (Price in USD)</label>
                        <input type="number" step="0.01" min="0" max="99999999.99" name="price" class="cyber-input" value="<?= htmlspecialchars($formPrice, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-boxes-stacked" style="color: var(--cyber-amber); margin-right: 6px;"></i> ចំនួនស្តុក (Stock amount)</label>
                        <input type="number" step="1" min="0" max="2147483647" name="stock" class="cyber-input" value="<?= htmlspecialchars($formStock, ENT_QUOTES, 'UTF-8') ?>" required>
                        <div class="image-url-hint">Set to 0 to hide this product from the storefront. Configure its low-stock alert in Inventory.</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-image" style="color: var(--cyber-pink); margin-right: 6px;"></i> រូបភាពផលិតផល (Product Image)</label>
                        <?php if ($isEditing && !empty($product['image'])): ?>
                            <div class="current-app-preview">
                                <img src="<?= htmlspecialchars(preg_match('~^https?://~i', $product['image']) ? $product['image'] : '../' . $product['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Current product image">
                                <span>រូបភាពបច្ចុប្បន្ន — ទុកនៅទទេបើមិនចង់ប្តូរ</span>
                            </div>
                        <?php endif; ?>
                        <?php
                        $currentImageIsUrl = !empty($product['image']) && preg_match('~^https?://~i', $product['image']);
                        $postedImageSource = $_POST['image_source'] ?? null;
                        $selectedImageSource = is_string($postedImageSource) ? $postedImageSource : ($currentImageIsUrl ? 'url' : 'upload');
                        ?>
                        <div class="image-source-options" role="radiogroup" aria-label="Image source">
                            <label class="image-source-option">
                                <input type="radio" name="image_source" value="upload" <?= $selectedImageSource === 'upload' ? 'checked' : '' ?>>
                                Upload file
                            </label>
                            <label class="image-source-option">
                                <input type="radio" name="image_source" value="url" <?= $selectedImageSource === 'url' ? 'checked' : '' ?>>
                                Image link (URL)
                            </label>
                        </div>
                        <div class="image-source-panel" id="imageUploadPanel">
                            <div class="file-upload-box">
                                <input type="file" name="icon" id="iconFileInput" accept="image/jpeg,image/png,image/gif,image/webp">
                                <i class="fas fa-cloud-arrow-up" style="font-size: 32px; color: var(--cyber-cyan); margin-bottom: 8px;"></i>
                                <div style="font-size: 14px; font-weight: 700; color: #fff;">ចុចទីនេះដើម្បីជ្រើសរើសរូបភាពផលិតផល</div>
                                <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">គាំទ្រ: PNG, JPG, WEBP ឬ GIF</div>
                                <div class="file-preview-wrap" id="filePreviewWrap"><i class="fas fa-check"></i> <span id="fileNameLabel"></span></div>
                            </div>
                        </div>
                        <div class="image-source-panel" id="imageUrlPanel" hidden>
                            <input type="url" name="image_url" id="imageUrlInput" class="cyber-input" maxlength="255" placeholder="https://example.com/product-image.jpg" value="<?= htmlspecialchars($formImageUrl !== '' ? $formImageUrl : ($currentImageIsUrl ? $product['image'] : ''), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="image-url-hint">Use a direct image link starting with http:// or https://.</div>
                        </div>
                    </div>
                    <button type="submit" class="cyber-buy-action" style="width: 100%; padding: 14px; margin-top: 12px; font-size: 15px;">
                        <i class="fas fa-<?= $isEditing ? 'save' : 'check-circle' ?>"></i>
                        <?= $isEditing ? 'រក្សាទុកការកែប្រែ (Save Changes)' : 'រក្សាទុក និងដាក់លក់ (Add Product)' ?>
                    </button>
                </form>
            </div>
        </div>
    <?php elseif ($action === 'delete'): ?>
        <div class="admin-form-layer">
            <section class="delete-confirmation-dialog" role="alertdialog" aria-modal="true">
                <div class="delete-warning-icon"><i class="fas fa-trash-can"></i></div>
                <h2>បញ្ជាក់ការលុបផលិតផល</h2>
                <p>តើអ្នកពិតជាចង់លុបផលិតផលនេះមែនទេ? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។</p>
                <span class="delete-product-name"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($error_msg !== ''): ?><div class="admin-error"><?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <form action="index.php" method="POST" class="delete-confirmation-actions">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <a href="index.php" class="delete-cancel-button" style="display: inline-flex; align-items: center; text-decoration: none;">បោះបង់</a>
                    <button type="submit" class="delete-confirm-button"><i class="fas fa-trash"></i> លុបផលិតផល</button>
                </form>
            </section>
        </div>
    <?php else: ?>
    <div class="admin-dashboard-container">
        <!-- Top bar -->
        <div class="admin-header-glass">
            <div>
                <span class="section-tag"><i class="fas fa-shield-alt"></i> Control Center</span>
                <h2 style="font-size: 24px; font-weight: 800; color: #fff; margin-top: 4px;">គ្រប់គ្រងផលិតផល (Products)</h2>
            </div>
            <div class="admin-header-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="index.php?action=add" class="ios-glass-btn btn-aurora">
                    <i class="fas fa-plus"></i> + បន្ថែមផលិតផលថ្មី
                </a>
                <a href="../home/index.php" class="ios-glass-btn">
                    <i class="fas fa-external-link-alt"></i> មើលទំព័រមុខ
                </a>
                <div class="theme-mode-control theme-mode-admin">
                    <label class="theme-mode-label" for="adminThemeModeSelect">Theme</label>
                    <select id="adminThemeModeSelect" data-theme-mode aria-label="Color theme">
                        <option value="system">System</option>
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                    </select>
                </div>
            </div>
        </div>
        <?php renderAdminPrimaryNavigation($currentAdmin, 'products'); ?>
        <?php if ($error_msg !== ''): ?>
            <div class="admin-error"><?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (adminHasPermission($currentAdmin, 'orders')): ?>
        <?php
        $todayUnits = (int) $todaySales['units'];
        $yesterdayUnits = (int) $yesterdaySales['units'];
        $unitTrendClass = $todayUnits > $yesterdayUnits ? 'trend-up' : ($todayUnits < $yesterdayUnits ? 'trend-down' : '');
        if ($yesterdayUnits > 0) {
            $unitTrend = sprintf('%s%.1f%% vs yesterday', $todayUnits > $yesterdayUnits ? '+' : '', (($todayUnits - $yesterdayUnits) / $yesterdayUnits) * 100);
        } elseif ($todayUnits > 0) {
            $unitTrend = 'Sales started today · 0 yesterday';
        } else {
            $unitTrend = 'No sales today or yesterday';
        }
        $knownProfit = (float) $todaySales['known_profit'];
        ?>
        <section class="analytics-grid" aria-label="Sales and inventory summary">
            <article class="analytics-card">
                <span>Units sold today</span>
                <strong><?= $todayUnits ?></strong>
                <small class="<?= $unitTrendClass ?>"><?= htmlspecialchars($unitTrend, ENT_QUOTES, 'UTF-8') ?> · yesterday: <?= $yesterdayUnits ?></small>
            </article>
            <article class="analytics-card">
                <span>Net verified revenue today</span>
                <strong><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float) $todaySales['revenue'], 2) ?></strong>
                <small>Customer online orders</small>
            </article>
            <article class="analytics-card">
                <span>Profit / loss today (known costs)</span>
                <strong class="<?= $knownProfit >= 0 ? 'trend-up' : 'trend-down' ?>"><?= $knownProfit < 0 ? '-' : '' ?><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format(abs($knownProfit), 2) ?></strong>
                <small><?= (int) $todaySales['unknown_cost_units'] ?> sale(s) excluded · cost not set</small>
            </article>
        </section>
        <?php endif; ?>
        <section class="analytics-grid" aria-label="Inventory summary">
            <article class="analytics-card">
                <span>Current units in stock</span>
                <strong><?= $inventoryUnits ?></strong>
                <small>Across all listed products</small>
            </article>
        </section>

        <?php if ($lowStockResult->num_rows > 0): ?>
            <section class="inventory-alert" role="status">
                <h3><i class="fas fa-triangle-exclamation"></i> Low stock alert — <?= $lowStockResult->num_rows ?> product(s) need attention</h3>
                <ul>
                    <?php while ($lowStockProduct = $lowStockResult->fetch_assoc()): ?>
                        <li>
                            <?= htmlspecialchars($lowStockProduct['name'], ENT_QUOTES, 'UTF-8') ?>:
                            <?= (int) $lowStockProduct['stock'] === 0 ? 'OUT OF STOCK' : (int) $lowStockProduct['stock'] . ' left (threshold ' . (int) $lowStockProduct['low_stock_threshold'] . ')' ?>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (adminHasPermission($currentAdmin, 'orders')): ?>
        <section class="analytics-section">
            <h3><i class="fas fa-receipt" style="color:var(--cyber-cyan);"></i> Recent online orders (latest 50)</h3>
            <div class="admin-table-glass analytics-table-wrap">
                <table>
                    <thead><tr><th>Order</th><th>Payment</th><th>Buyer</th><th>Product</th><th>Price</th><th>Qty</th><th>Location</th><th>Phone</th><th>Order status</th><th>Payment status</th><th>Created</th></tr></thead>
                    <tbody>
                        <?php if ($salesHistory->num_rows > 0): ?>
                            <?php while ($sale = $salesHistory->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sale['order_reference'] ?: 'Online order', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($sale['payment_method'] ? strtoupper($sale['payment_method']) : 'ONLINE', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($sale['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($sale['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float) $sale['unit_price'], 2) ?><?php if ((float) $sale['discount_amount'] > 0): ?><small><?= htmlspecialchars((string) $sale['coupon_code'], ENT_QUOTES, 'UTF-8') ?> · −<?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format((float) $sale['discount_amount'], 2) ?></small><?php endif; ?></td>
                                    <td><?= (int) $sale['quantity'] ?></td>
                                    <td><?= htmlspecialchars($sale['location'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($sale['phone_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($sale['status'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $sale['payment_status'])), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($sale['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="11">No accepted orders have been recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <section class="analytics-section">
            <h3><i class="fas fa-clock-rotate-left" style="color:var(--cyber-purple);"></i> Product activity (latest 50)</h3>
            <div class="admin-table-glass analytics-table-wrap">
                <table>
                    <thead><tr><th>Action</th><th>Product</th><th>Changed fields</th><th>Who</th><th>When</th></tr></thead>
                    <tbody>
                        <?php if ($activityHistory->num_rows > 0): ?>
                            <?php while ($activity = $activityHistory->fetch_assoc()): ?>
                                <?php
                                $oldActivityValues = $activity['old_values'] === null ? null : json_decode($activity['old_values'], true);
                                $newActivityValues = $activity['new_values'] === null ? null : json_decode($activity['new_values'], true);
                                $changedFields = [];
                                if (is_array($oldActivityValues) && is_array($newActivityValues)) {
                                    foreach (['name', 'description', 'price', 'cost_price', 'stock', 'image'] as $field) {
                                        if (($oldActivityValues[$field] ?? null) !== ($newActivityValues[$field] ?? null)) {
                                            $changedFields[] = $field;
                                        }
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars(ucfirst($activity['action']), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($activity['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $changedFields ? htmlspecialchars(implode(', ', $changedFields), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                    <td><?= htmlspecialchars($activity['actor'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($activity['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No product activity has been recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Table -->
        <div class="admin-table-glass">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">ល.រ</th>
                        <th style="width: 80px;">រូបភាព</th>
                        <th>ឈ្មោះផលិតផល</th>
                        <th>ព័ត៌មានលម្អិត</th>
                        <th>តម្លៃ</th>
                        <th>Cost</th>
                        <th>ស្តុក (Stock)</th>
                        <th style="width: 180px; text-align: center;">សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight: 700; color: #00f2fe;"><?= $row['id'] ?></td>
                                <td>
                                    <?php if (!empty($row['image'])): ?>
                                        <img src="<?= htmlspecialchars(preg_match('~^https?://~i', $row['image']) ? $row['image'] : '../' . $row['image'], ENT_QUOTES, 'UTF-8') ?>" class="app-icon-img" alt="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php else: ?>
                                        <div class="app-icon-img" style="display:flex;align-items:center;justify-content:center;color:#666;">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 700; color: #fff;"><?= htmlspecialchars($row['name']) ?></td>
                                <td style="color: var(--text-muted); font-size: 13px;"><?= htmlspecialchars($row['description'] ?? '') ?></td>
                                <td>
                                    <span style="display:inline-block; padding: 4px 10px; border-radius: 9999px; background: rgba(0,245,160,0.15); border: 1px solid rgba(0,245,160,0.3); color: #00f5a0; font-weight: 700;">
                                        <?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?><?= number_format($row['price'], 2) ?>
                                    </span>
                                </td>
                                <td><?= $row['cost_price'] === null ? 'Not set' : htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') . number_format((float) $row['cost_price'], 2) ?></td>
                                <td>
                                    <?php
                                    $stockAmount = (int) $row['stock'];
                                    $stockClass = $stockAmount === 0 ? 'is-empty' : ($stockAmount <= (int) $row['low_stock_threshold'] ? 'is-low' : '');
                                    ?>
                                    <span class="stock-badge <?= $stockClass ?>">
                                        <?= $stockAmount === 0 ? 'OUT OF STOCK' : $stockAmount ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <a class="btn-table btn-edit" href="index.php?action=edit&amp;id=<?= (int) $row['id'] ?>">
                                        <i class="fas fa-pen"></i> កែប្រែ
                                    </a>
                                    <a class="btn-table btn-delete" href="index.php?action=delete&amp;id=<?= (int) $row['id'] ?>" data-delete-product="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>" data-product-id="<?= (int) $row['id'] ?>">
                                        <i class="fas fa-trash"></i> លុប
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                មិនទាន់មានផលិតផលនៅឡើយទេ (No products found)
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="delete-confirmation-backdrop" id="deleteConfirmation" hidden>
        <section class="delete-confirmation-dialog" role="alertdialog" aria-modal="true" aria-labelledby="deleteConfirmationTitle" aria-describedby="deleteConfirmationMessage" tabindex="-1">
            <div class="delete-warning-icon"><i class="fas fa-trash-can"></i></div>
            <h2 id="deleteConfirmationTitle">បញ្ជាក់ការលុបផលិតផល</h2>
            <p id="deleteConfirmationMessage">តើអ្នកពិតជាចង់លុបផលិតផលនេះមែនទេ? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។</p>
            <span class="delete-product-name" id="deleteProductName"></span>
            <div class="delete-confirmation-actions">
                <button type="button" class="delete-cancel-button" id="cancelProductDelete">បោះបង់</button>
                <button type="button" class="delete-confirm-button" id="confirmProductDelete"><i class="fas fa-trash"></i> លុបផលិតផល</button>
            </div>
        </section>
    </div>
    <form action="index.php" method="POST" id="deleteProductForm" hidden>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteProductId">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    </form>
    <script>
        (() => {
            const modal = document.getElementById('deleteConfirmation');
            const dialog = modal.querySelector('[role="alertdialog"]');
            const productName = document.getElementById('deleteProductName');
            const cancelButton = document.getElementById('cancelProductDelete');
            const confirmButton = document.getElementById('confirmProductDelete');
            const deleteForm = document.getElementById('deleteProductForm');
            const deleteProductId = document.getElementById('deleteProductId');
            let previouslyFocusedElement = null;

            const closeModal = () => {
                modal.hidden = true;
                if (previouslyFocusedElement) previouslyFocusedElement.focus();
            };

            document.querySelectorAll('[data-delete-product]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    deleteProductId.value = link.dataset.productId;
                    previouslyFocusedElement = link;
                    productName.textContent = link.dataset.deleteProduct;
                    modal.hidden = false;
                    cancelButton.focus();
                });
            });

            cancelButton.addEventListener('click', closeModal);
            confirmButton.addEventListener('click', () => {
                deleteForm.submit();
            });
            modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.hidden) closeModal();
            });
        })();
    </script>
    <?php endif; ?>
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <script>
            const fileInput = document.getElementById("iconFileInput");
            const previewWrap = document.getElementById("filePreviewWrap");
            const nameLabel = document.getElementById("fileNameLabel");
            const uploadPanel = document.getElementById("imageUploadPanel");
            const urlPanel = document.getElementById("imageUrlPanel");
            const urlInput = document.getElementById("imageUrlInput");
            const imageSourceOptions = document.querySelectorAll('input[name="image_source"]');

            const updateImageSource = () => {
                const selectedSource = document.querySelector('input[name="image_source"]:checked').value;
                const useUrl = selectedSource === 'url';
                uploadPanel.hidden = useUrl;
                urlPanel.hidden = !useUrl;
                fileInput.required = !useUrl && <?= $isEditing ? 'false' : 'true' ?>;
                urlInput.required = useUrl && <?= $isEditing ? 'false' : 'true' ?>;
            };

            imageSourceOptions.forEach((option) => option.addEventListener('change', updateImageSource));
            updateImageSource();

            fileInput.addEventListener("change", function() {
                if (this.files && this.files[0]) {
                    nameLabel.textContent = this.files[0].name;
                    previewWrap.style.display = "inline-flex";
                }
            });

            // ── Product link auto-fill ─────────────────────────────────────
            const fetchBtn    = document.getElementById('fetchProductBtn');
            const linkInput   = document.getElementById('productLinkInput');
            const fetchStatus = document.getElementById('fetchStatus');

            if (fetchBtn && linkInput) {
                fetchBtn.addEventListener('click', async () => {
                    const url = linkInput.value.trim();
                    if (!url) {
                        showStatus('សូមបញ្ចូល link ផលិតផលជាមុនសិន', 'error');
                        return;
                    }
                    if (!/^https?:\/\//i.test(url)) {
                        showStatus('Link ត្រូវចាប់ផ្តើមដោយ http:// ឬ https://', 'error');
                        return;
                    }

                    fetchBtn.disabled = true;
                    fetchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    showStatus('កំពុង fetch ទិន្នន័យ...', 'info');

                    try {
                        const resp = await fetch('../api/scrape-product.php?url=' + encodeURIComponent(url));
                        if (!resp.ok) throw new Error('Server error ' + resp.status);
                        const data = await resp.json();

                        if (data.error) {
                            showStatus('❌ ' + data.error, 'error');
                            return;
                        }

                        let filled = 0;

                        if (data.name) {
                            document.getElementById('fieldName').value = data.name;
                            filled++;
                        }
                        if (data.description) {
                            document.getElementById('fieldDescription').value = data.description;
                            filled++;
                        }
                        if (data.price && parseFloat(data.price) > 0) {
                            const costField = document.getElementById('fieldCostPrice');
                            if (costField && costField.value === '') {
                                costField.value = data.price;
                                filled++;
                            }
                        }
                        if (data.image) {
                            // Switch to URL mode and fill image URL
                            const urlRadio = document.querySelector('input[name="image_source"][value="url"]');
                            if (urlRadio) {
                                urlRadio.checked = true;
                                updateImageSource();
                            }
                            const imgUrlInput = document.getElementById('imageUrlInput');
                            if (imgUrlInput) {
                                imgUrlInput.value = data.image;
                                filled++;
                            }
                        }

                        showStatus('✅ Auto-fill រួចរាល់! (' + filled + ' fields filled)', 'success');
                    } catch (e) {
                        showStatus('❌ មិនអាច fetch បាន: ' + e.message, 'error');
                    } finally {
                        fetchBtn.disabled = false;
                        fetchBtn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Auto Fill';
                    }
                });

                // Also trigger on Enter key in link field
                linkInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        fetchBtn.click();
                    }
                });
            }

            function showStatus(msg, type) {
                if (!fetchStatus) return;
                fetchStatus.textContent = msg;
                fetchStatus.style.display = 'block';
                fetchStatus.style.color = type === 'error' ? '#ff8b9b' : type === 'success' ? '#4ade80' : 'var(--cyber-cyan)';
            }
        </script>
    <?php endif; ?>
    <script src="../js/theme-mode.js?v=<?php echo filemtime(__DIR__ . '/../js/theme-mode.js'); ?>"></script>
</body>

</html>