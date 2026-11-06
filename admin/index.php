<?php
declare(strict_types=1);

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

// Handle Login
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
            $stmt = $conn->prepare('SELECT id, username, password, role, permissions, is_active FROM admins WHERE username = ? OR (email IS NOT NULL AND email = ?) LIMIT 1');
            if (!$stmt) {
                http_response_code(500);
                exit('Admin sign-in is temporarily unavailable.');
            }
            $stmt->bind_param('ss', $username, $username);
            if (!$stmt->execute()) {
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

// Handle Logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout' && $csrfValid) {
    adminDestroySession($conn);
    header('Location: index.php');
    exit;
}

$currentAdmin = adminCurrentUser($conn);
if ($currentAdmin === null) {
    ?>
    <!DOCTYPE html>
    <html lang="km">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Sign In • 4K STORE</title>
        <link rel="stylesheet" href="../home/page.css">
        <link rel="stylesheet" href="../home/fashion-theme.css?v=<?= filemtime(__DIR__ . '/../home/fashion-theme.css'); ?>">
        <link rel="stylesheet" href="./backoffice-theme.css?v=<?= filemtime(__DIR__ . '/backoffice-theme.css'); ?>">
        <link rel="stylesheet" href="../css/theme-mode.css?v=<?= filemtime(__DIR__ . '/../css/theme-mode.css'); ?>">
        <style>
            .admin-login-wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f7f4ef; }
            .admin-login-card { width: min(100%, 420px); padding: 40px 32px; border: 1px solid #e8e1d8; border-radius: 16px; background: #fff; box-shadow: 0 10px 35px rgba(0,0,0,0.06); color: #302923; }
            .admin-login-card h1 { margin: 0 0 8px; font-size: 24px; font-weight: 700; color: #203f3a; }
            .admin-login-card p { color: #756d64; font-size: 14px; margin-bottom: 22px; }
            .admin-login-card label { display: block; margin: 16px 0 6px; font-size: 13px; font-weight: 600; color: #443e38; }
            .admin-login-card input { box-sizing: border-box; width: 100%; padding: 12px 14px; border: 1px solid #ded5cb; border-radius: 8px; background: #faf8f5; color: #302923; font: inherit; outline: none; }
            .admin-login-card input:focus { border-color: #203f3a; background: #fff; }
            .admin-login-card button { width: 100%; margin-top: 24px; padding: 13px; border: 0; border-radius: 8px; background: #203f3a; color: #fff; font: inherit; font-weight: 700; cursor: pointer; transition: background 0.2s; }
            .admin-login-card button:hover { background: #2f5650; }
            .admin-login-error { margin-bottom: 16px; padding: 10px 14px; border-radius: 8px; background: #fde8e8; border: 1px solid #f8b4b4; color: #9b1c1c; font-size: 13px; }
        </style>
    </head>
    <body class="fashion-storefront admin-storefront">
        <main class="admin-login-wrap">
            <form class="admin-login-card" method="post" action="index.php">
                <h1>4K STORE • Admin</h1>
                <p>ចូលប្រើប្រព័ន្ធគ្រប់គ្រងផលិតផល និងការបញ្ជាទិញ</p>
                <?php if ($loginError !== ''): ?><div class="admin-login-error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label for="adminUsername">ឈ្មោះអ្នកប្រើប្រាស់ (Username ឬ Email)</label>
                <input id="adminUsername" name="username" type="text" autocomplete="username" placeholder="kakadachan541@gmail.com ឬ netsmos100" required>
                <label for="adminPassword">ពាក្យសម្ងាត់ (Password)</label>
                <input id="adminPassword" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">ចូលប្រព័ន្ធ (Sign in)</button>
            </form>
        </main>
        <script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js'); ?>"></script>
    </body>
    </html>
    <?php
    exit;
}

$adminUsername = (string) $currentAdmin['username'];
if (!adminHasPermission($currentAdmin, 'products')) {
    http_response_code(403);
    exit('You do not have permission to access the product dashboard.');
}

header('Cache-Control: no-store, private');

if (!ensureProductStockColumn($conn) || !ensureProductDiscountColumns($conn) || !ensureSalesAnalyticsSchema($conn) || !ensureAdminOperationsSchema($conn) || !ensureStoreManagementSchema($conn)) {
    http_response_code(500);
    exit('Could not prepare admin schemas.');
}

// Helper functions for activity & inventory
function saveProductActivity(mysqli $connection, ?int $productId, string $productName, string $action, string $actor, ?array $oldValues, ?array $newValues): bool
{
    $statement = $connection->prepare('INSERT INTO product_activity (product_id, product_name, action, actor, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$statement) return false;
    $oldJson = $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $newJson = $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $statement->bind_param('isssss', $productId, $productName, $action, $actor, $oldJson, $newJson);
    $saved = $statement->execute();
    $statement->close();
    return $saved;
}

function productActivitySnapshot(array $product): array
{
    return [
        'name' => (string) $product['name'],
        'category' => (string) ($product['category'] ?? ''),
        'description' => hash('sha256', (string) ($product['description'] ?? '')),
        'price' => $product['price'] === null ? null : (float) $product['price'],
        'cost_price' => $product['cost_price'] === null ? null : (float) $product['cost_price'],
        'stock' => (int) $product['stock'],
        'image' => (string) ($product['image'] ?? ''),
    ];
}

// -------------------------------------------------------------
// AJAX ENDPOINT: Get Product JSON for Modal Editing
// -------------------------------------------------------------
if ($action === 'get_product_json') {
    header('Content-Type: application/json; charset=utf-8');
    $pId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$pId || $pId < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }
    $stmt = $conn->prepare("SELECT id, name, category, description, price, original_price, discount_percent, cost_price, image, stock, low_stock_threshold FROM products WHERE id = ?");
    $stmt->bind_param("i", $pId);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$prod) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    echo json_encode(['success' => true, 'product' => $prod]);
    exit;
}

// -------------------------------------------------------------
// AJAX ENDPOINT: Quick Inline Stock Adjust (+1, -1, or Custom)
// -------------------------------------------------------------
if ($action === 'quick_stock_adjust' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$csrfValid) {
        echo json_encode(['success' => false, 'message' => 'Security token expired. Please refresh.']);
        exit;
    }
    $pId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $change = filter_var($_POST['delta'] ?? null, FILTER_VALIDATE_INT);
    $exactStock = filter_var($_POST['new_stock'] ?? null, FILTER_VALIDATE_INT);
    $reason = trim((string)($_POST['reason'] ?? 'Quick stock update from admin panel'));

    if (!$pId || $pId < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }

    $conn->begin_transaction();
    $stmt = $conn->prepare("SELECT id, name, stock, low_stock_threshold FROM products WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $pId);
    $stmt->execute();
    $pRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pRow) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    $stockBefore = (int)$pRow['stock'];
    if ($exactStock !== null && $exactStock !== false) {
        $stockAfter = max(0, $exactStock);
    } elseif ($change !== null && $change !== false) {
        $stockAfter = max(0, $stockBefore + $change);
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'No stock adjustment provided']);
        exit;
    }

    $stockDelta = $stockAfter - $stockBefore;
    $upd = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
    $upd->bind_param("ii", $stockAfter, $pId);
    $upd->execute();
    $upd->close();

    recordInventoryAdjustment(
        $conn,
        $pId,
        (string)$pRow['name'],
        $stockDelta,
        $stockBefore,
        $stockAfter,
        $reason,
        $adminUsername
    );

    $conn->commit();

    $threshold = (int)$pRow['low_stock_threshold'];
    $stockStatus = $stockAfter === 0 ? 'empty' : ($stockAfter <= $threshold ? 'low' : 'ok');

    echo json_encode([
        'success' => true,
        'product_id' => $pId,
        'stock_before' => $stockBefore,
        'stock_after' => $stockAfter,
        'stock_status' => $stockStatus,
        'message' => "ស្តុកផលិតផល {$pRow['name']} ត្រូវបានប្តូរទៅជា {$stockAfter}"
    ]);
    exit;
}

// -------------------------------------------------------------
// POST HANDLER: Add or Edit Product (AJAX or Standard POST)
// -------------------------------------------------------------
$error_msg = '';
$flash_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
    $isAjax = isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1';
    
    if (!$csrfValid) {
        $error_msg = 'The form session expired. Please reload and try again.';
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit;
        }
    } else {
        $pId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string)($_POST['name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'men'));
        $description = trim((string)($_POST['description'] ?? ''));
        $priceInput = trim((string)($_POST['price'] ?? '0'));
        $originalPriceInput = trim((string)($_POST['original_price'] ?? ''));
        $discountPercentInput = trim((string)($_POST['discount_percent'] ?? '0'));
        $costPriceInput = trim((string)($_POST['cost_price'] ?? ''));
        $stockInput = trim((string)($_POST['stock'] ?? '0'));
        $thresholdInput = trim((string)($_POST['low_stock_threshold'] ?? '5'));

        $price = is_numeric($priceInput) ? max(0, (float)$priceInput) : false;
        $originalPrice = ($originalPriceInput !== '' && is_numeric($originalPriceInput)) ? max(0, (float)$originalPriceInput) : null;
        $discountPercent = is_numeric($discountPercentInput) ? max(0, min(99, (int)$discountPercentInput)) : 0;
        $costPrice = ($costPriceInput !== '' && is_numeric($costPriceInput)) ? max(0, (float)$costPriceInput) : null;
        $stock = is_numeric($stockInput) ? max(0, (int)$stockInput) : 0;
        $threshold = is_numeric($thresholdInput) ? max(1, (int)$thresholdInput) : 5;

        // Auto-calculate discount relationship
        if ($price !== false && $price > 0) {
            if ($originalPrice !== null && $originalPrice > $price) {
                $discountPercent = (int) round((($originalPrice - $price) / $originalPrice) * 100);
            } elseif ($discountPercent > 0 && ($originalPrice === null || $originalPrice <= $price)) {
                $originalPrice = round($price / (1 - ($discountPercent / 100)), 2);
            } else {
                $originalPrice = null;
                $discountPercent = 0;
            }
        } else {
            $originalPrice = null;
            $discountPercent = 0;
        }

        if ($name === '' || $price === false) {
            $error_msg = 'សូមបញ្ចូលឈ្មោះ និងតម្លៃផលិតផលឱ្យបានត្រឹមត្រូវ (Name and price required).';
        }

        // Handle Image
        $imagePath = '';
        if ($action === 'edit' && $pId) {
            $cStmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
            $cStmt->bind_param("i", $pId);
            $cStmt->execute();
            $oldP = $cStmt->get_result()->fetch_assoc();
            $cStmt->close();
            $imagePath = $oldP ? (string)$oldP['image'] : '';
        }

        $imageSource = $_POST['image_source'] ?? 'url';
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));
        $upload = $_FILES['icon'] ?? null;

        if ($error_msg === '') {
            if ($imageSource === 'url' && $imageUrl !== '') {
                $imagePath = $imageUrl;
            } elseif ($imageSource === 'upload' && $upload && $upload['error'] === UPLOAD_ERR_OK && is_uploaded_file($upload['tmp_name'])) {
                $fileType = mime_content_type($upload['tmp_name']);
                $imageTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];
                if (isset($imageTypes[$fileType])) {
                    $targetDir = __DIR__ . '/../img/products/';
                    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                    $newImgName = 'icon_' . bin2hex(random_bytes(14)) . '.' . $imageTypes[$fileType];
                    if (move_uploaded_file($upload['tmp_name'], $targetDir . $newImgName)) {
                        $imagePath = 'img/products/' . $newImgName;
                    }
                }
            }
        }

        if ($error_msg === '') {
            $conn->begin_transaction();
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO products (name, category, description, price, original_price, discount_percent, cost_price, image, stock, low_stock_threshold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssddidsii", $name, $category, $description, $price, $originalPrice, $discountPercent, $costPrice, $imagePath, $stock, $threshold);
                if ($stmt->execute()) {
                    $newId = (int)$conn->insert_id;
                    $stmt->close();
                    recordInventoryAdjustment($conn, $newId, $name, $stock, 0, $stock, 'Initial stock on creation', $adminUsername);
                    saveProductActivity($conn, $newId, $name, 'create', $adminUsername, null, [
                        'name' => $name, 'category' => $category, 'price' => $price, 'original_price' => $originalPrice, 'discount_percent' => $discountPercent, 'stock' => $stock
                    ]);
                    $conn->commit();
                    $flash_msg = "ផលិតផល \"{$name}\" ត្រូវបានបន្ថែមជោគជ័យ!";
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'message' => $flash_msg, 'id' => $newId]);
                        exit;
                    }
                    header('Location: index.php?view=products&msg=added');
                    exit;
                } else {
                    $conn->rollback();
                    $error_msg = 'Error creating product: ' . $conn->error;
                }
            } else { // edit
                $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, description = ?, price = ?, original_price = ?, discount_percent = ?, cost_price = ?, image = ?, stock = ?, low_stock_threshold = ? WHERE id = ?");
                $stmt->bind_param("sssddidsiii", $name, $category, $description, $price, $originalPrice, $discountPercent, $costPrice, $imagePath, $stock, $threshold, $pId);
                if ($stmt->execute()) {
                    $stmt->close();
                    saveProductActivity($conn, $pId, $name, 'update', $adminUsername, null, [
                        'name' => $name, 'category' => $category, 'price' => $price, 'original_price' => $originalPrice, 'discount_percent' => $discountPercent, 'stock' => $stock
                    ]);
                    $conn->commit();
                    $flash_msg = "បានកែប្រែផលិតផល \"{$name}\" ជោគជ័យ!";
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'message' => $flash_msg]);
                        exit;
                    }
                    header('Location: index.php?view=products&msg=updated');
                    exit;
                } else {
                    $conn->rollback();
                    $error_msg = 'Error updating product: ' . $conn->error;
                }
            }
        }

        if ($isAjax && $error_msg !== '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit;
        }
    }
}

// -------------------------------------------------------------
// POST HANDLER: Delete Product
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $pId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $isAjax = isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1';

    if (!$csrfValid) {
        $error_msg = 'Session expired. Please try again.';
    } elseif ($pId && $pId > 0) {
        $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->bind_param("i", $pId);
        $stmt->execute();
        $pRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($pRow) {
            $pName = (string)$pRow['name'];
            $del = $conn->prepare("DELETE FROM products WHERE id = ?");
            $del->bind_param("i", $pId);
            if ($del->execute()) {
                $del->close();
                saveProductActivity($conn, $pId, $pName, 'delete', $adminUsername, ['name' => $pName], null);
                $flash_msg = "បានលុបផលិតផល \"{$pName}\" រួចរាល់។";
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => $flash_msg]);
                    exit;
                }
                header('Location: index.php?view=products&msg=deleted');
                exit;
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $error_msg ?: 'Could not delete product']);
        exit;
    }
}

// Fetch KPI Statistics
$statsQuery = $conn->query("SELECT 
    count(*) as total_products,
    COALESCE(SUM(stock), 0) as total_units,
    COALESCE(SUM(price * stock), 0) as total_value,
    COALESCE(SUM(CASE WHEN stock <= low_stock_threshold AND stock > 0 THEN 1 ELSE 0 END), 0) as low_stock_count,
    COALESCE(SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END), 0) as out_of_stock_count
FROM products");
$kpiStats = $statsQuery ? $statsQuery->fetch_assoc() : [];

$currencySymbol = storeCurrencySymbol($conn);

// Fetch All Products for the Dashboard Matrix
$productsList = [];
$prodRes = $conn->query("SELECT id, name, category, description, price, original_price, discount_percent, cost_price, image, stock, low_stock_threshold, created_at FROM products ORDER BY id DESC");
if ($prodRes) {
    while ($r = $prodRes->fetch_assoc()) {
        $productsList[] = $r;
    }
}

// Category Distribution for filter pills
$catCounts = ['all' => count($productsList), 'men' => 0, 'women' => 0, 'accessories' => 0, 'digital' => 0];
foreach ($productsList as $p) {
    $c = $p['category'] ?: 'men';
    if (!isset($catCounts[$c])) $catCounts[$c] = 0;
    $catCounts[$c]++;
}

$adminView = $_GET['view'] ?? 'dashboard';
if (!in_array($adminView, ['dashboard', 'products'], true)) {
    $adminView = 'dashboard';
}

// Fetch Overview Stats for Dashboard
$ordersStatsQuery = $conn->query("SELECT 
    COUNT(*) as total_orders,
    COALESCE(SUM(CASE WHEN payment_status = 'verified' OR status IN ('Paid', 'Completed') THEN (quantity * unit_price) ELSE 0 END), 0) as total_revenue,
    COALESCE(SUM(CASE WHEN status = 'Pending' OR payment_status = 'pending' THEN 1 ELSE 0 END), 0) as pending_orders,
    COALESCE(SUM(CASE WHEN status IN ('Completed', 'Paid') THEN 1 ELSE 0 END), 0) as completed_orders
FROM sales_orders");
$orderStats = $ordersStatsQuery ? $ordersStatsQuery->fetch_assoc() : [];
$dashTotalRevenue = (float)($orderStats['total_revenue'] ?? 0);
$dashTotalOrders = (int)($orderStats['total_orders'] ?? 0);
$dashPendingOrders = (int)($orderStats['pending_orders'] ?? 0);
$dashCompletedOrders = (int)($orderStats['completed_orders'] ?? 0);

$custCountQuery = $conn->query("SELECT COUNT(*) as total_customers FROM users");
$dashTotalCustomers = $custCountQuery ? (int)($custCountQuery->fetch_assoc()['total_customers'] ?? 0) : 0;

$recentOrdersQuery = $conn->query("SELECT id, order_reference, customer_name, phone_number, (quantity * unit_price) as total_amount, status, payment_status, created_at FROM sales_orders ORDER BY id DESC LIMIT 6");
$recentOrdersList = [];
if ($recentOrdersQuery) {
    while ($ro = $recentOrdersQuery->fetch_assoc()) {
        $recentOrdersList[] = $ro;
    }
}

$lowStockProducts = [];
$lowStockQuery = $conn->query("SELECT id, name, category, price, stock, low_stock_threshold, image FROM products WHERE stock <= low_stock_threshold OR stock = 0 ORDER BY stock ASC, id DESC LIMIT 6");
if ($lowStockQuery) {
    while ($lp = $lowStockQuery->fetch_assoc()) {
        $lowStockProducts[] = $lp;
    }
}

$adminStoreSettings = function_exists('getStoreSettings') ? getStoreSettings($conn) : [];
$adminStoreName = $adminStoreSettings['store_name'] ?? 'SN STORE';
$adminStoreLogo = $adminStoreSettings['store_logo_url'] ?? '';
$adminResolvedLogo = function_exists('resolveStoreAssetUrl') ? resolveStoreAssetUrl($adminStoreLogo, 'admin') : '../' . ltrim($adminStoreLogo, './\\');
?>
<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $adminView === 'dashboard' ? 'ផ្ទាំងគ្រប់គ្រងទូទៅ • Store Dashboard • ' . htmlspecialchars($adminStoreName, ENT_QUOTES, 'UTF-8') : 'គ្រប់គ្រងផលិតផល • Product Catalog • ' . htmlspecialchars($adminStoreName, ENT_QUOTES, 'UTF-8'); ?></title>
    <!-- FontAwesome & Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="<?= htmlspecialchars($adminResolvedLogo, ENT_QUOTES, 'UTF-8') ?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($adminResolvedLogo, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="../home/page.css?v=<?= filemtime(__DIR__ . '/../home/page.css'); ?>">
    <link rel="stylesheet" href="../home/fashion-theme.css?v=<?= filemtime(__DIR__ . '/../home/fashion-theme.css'); ?>">
    <link rel="stylesheet" href="./backoffice-theme.css?v=<?= filemtime(__DIR__ . '/backoffice-theme.css'); ?>">
    <link rel="stylesheet" href="../css/theme-mode.css?v=<?= filemtime(__DIR__ . '/../css/theme-mode.css'); ?>">
    <script src="../js/page-navigation.js?v=<?= filemtime(__DIR__ . '/../js/page-navigation.js'); ?>" defer></script>
    <script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js'); ?>" defer></script>
    <style>
        /* Modern Clean Dashboard Styling */
        :root {
            --admin-primary: #1e3a35;
            --admin-primary-light: #2d564f;
            --admin-accent: #b17b60;
            --admin-bg: #f8f6f2;
            --admin-card-bg: #ffffff;
            --admin-border: #e6dfd5;
            --admin-text-main: #2b2520;
            --admin-text-muted: #736b63;
            --admin-green: #2e7d32;
            --admin-green-bg: #e8f5e9;
            --admin-amber: #d97706;
            --admin-amber-bg: #fef3c7;
            --admin-red: #dc2626;
            --admin-red-bg: #fee2e2;
            --admin-radius: 12px;
            --admin-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            --admin-sidebar-w: 240px;
        }

        /* Dark Theme Support */
        body.admin-storefront[data-theme="dark"],
        [data-theme="dark"] {
            --admin-primary: #34d399;
            --admin-primary-light: #10b981;
            --admin-accent: #f59e0b;
            --admin-bg: #0f141d;
            --admin-card-bg: #161c27;
            --admin-border: #293444;
            --admin-text-main: #edf2f7;
            --admin-text-muted: #94a3b8;
            --admin-green: #4ade80;
            --admin-green-bg: rgba(34, 197, 94, 0.15);
            --admin-amber: #fbbf24;
            --admin-amber-bg: rgba(245, 158, 11, 0.15);
            --admin-red: #f87171;
            --admin-red-bg: rgba(239, 68, 68, 0.15);
            --admin-shadow: 0 4px 24px rgba(0, 0, 0, 0.28);
        }

        body.admin-storefront {
            background-color: var(--admin-bg);
            color: var(--admin-text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Kantumruy Pro", sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            background-color: var(--admin-bg);
            box-sizing: border-box;
            position: relative;
        }

        /* Content Area - Offset exactly by the fixed sidebar width */
        .dashboard-main-pane {
            flex: 1;
            min-width: 0;
            margin-left: var(--admin-sidebar-w);
            width: calc(100% - var(--admin-sidebar-w));
            max-width: calc(100% - var(--admin-sidebar-w));
            padding: 28px 36px 80px;
            box-sizing: border-box;
            transition: margin-left 0.2s ease, width 0.2s ease;
        }

        @media (max-width: 760px) {
            .dashboard-wrapper {
                flex-direction: column;
            }
            .dashboard-main-pane {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
                padding: 16px 14px 60px;
            }
        }

        /* Top Bar */
        .dash-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 26px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--admin-border);
        }

        .dash-title-block h1 {
            font-size: 26px;
            font-weight: 800;
            margin: 0 0 6px;
            color: var(--admin-primary);
            letter-spacing: -0.01em;
        }

        .dash-title-block p {
            margin: 0;
            font-size: 13.5px;
            color: var(--admin-text-muted);
        }

        .dash-topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-modern {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-primary-action {
            background: var(--admin-primary);
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(30, 58, 53, 0.2);
        }

        .btn-primary-action:hover {
            background: var(--admin-primary-light);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(30, 58, 53, 0.25);
        }

        .btn-outline-action {
            background: #ffffff;
            border-color: var(--admin-border);
            color: var(--admin-text-main) !important;
        }

        .btn-outline-action:hover {
            background: #f2eee8;
            border-color: #d1c7bc;
        }

        /* KPI Cards Grid */
        .kpi-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .kpi-card {
            background: var(--admin-card-bg);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius);
            padding: 20px 22px;
            box-shadow: var(--admin-shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
        }

        .kpi-icon-circle {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .kpi-icon-products { background: #e0f2fe; color: #0284c7; }
        .kpi-icon-stock { background: #e8f5e9; color: #2e7d32; }
        .kpi-icon-low { background: #fef3c7; color: #d97706; }
        .kpi-icon-value { background: #f3e8ff; color: #7e22ce; }

        .kpi-card-info span {
            display: block;
            font-size: 12.5px;
            color: var(--admin-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .kpi-card-info strong {
            display: block;
            font-size: 24px;
            font-weight: 800;
            color: var(--admin-text-main);
            margin-top: 4px;
        }

        /* Control & Filter Toolbar */
        .dash-toolbar-card {
            background: var(--admin-card-bg);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius);
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: var(--admin-shadow);
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
        }

        .dash-search-box {
            position: relative;
            flex: 1;
            min-width: 260px;
            max-width: 420px;
        }

        .dash-search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9c9489;
            font-size: 14px;
        }

        .dash-search-box input {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 36px 10px 38px;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            background: #faf8f5;
            font: inherit;
            font-size: 13.5px;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }

        .dash-search-box input:focus {
            border-color: var(--admin-primary);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(30, 58, 53, 0.1);
        }

        .dash-search-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: 0;
            color: #9c9489;
            cursor: pointer;
            display: none;
        }

        .dash-filters-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-pill-btn {
            padding: 7px 14px;
            border-radius: 9999px;
            background: #faf8f5;
            border: 1px solid var(--admin-border);
            font-size: 12.5px;
            font-weight: 600;
            color: var(--admin-text-muted);
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .filter-pill-btn:hover {
            border-color: #c4b9ac;
            color: var(--admin-text-main);
        }

        .filter-pill-btn.active {
            background: var(--admin-primary);
            border-color: var(--admin-primary);
            color: #ffffff;
        }

        .dash-sort-select {
            padding: 8px 12px;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            background: #faf8f5;
            font: inherit;
            font-size: 13px;
            color: var(--admin-text-main);
            outline: none;
        }

        /* Products Table Card */
        .dash-table-card {
            background: var(--admin-card-bg);
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius);
            box-shadow: var(--admin-shadow);
            overflow: hidden;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .dash-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }

        .dash-table th {
            background: #f5f1eb;
            color: #554d45;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 18px;
            border-bottom: 1px solid var(--admin-border);
            white-space: nowrap;
        }

        .dash-table td {
            padding: 16px 18px;
            border-bottom: 1px solid #f0eae1;
            color: #3b342e;
            vertical-align: middle;
        }

        .dash-table tbody tr {
            transition: background 0.15s ease;
        }

        .dash-table tbody tr:hover {
            background: #faf7f2;
        }

        /* Product Cell */
        .prod-cell {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .prod-thumb-wrap {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            border: 1px solid var(--admin-border);
            overflow: hidden;
            background: #f7f4ef;
            flex-shrink: 0;
            position: relative;
        }

        .prod-thumb-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .prod-thumb-wrap:hover img {
            transform: scale(1.15);
        }

        .prod-meta-title {
            font-weight: 700;
            color: var(--admin-primary);
            font-size: 14px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .prod-cat-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #efebe4;
            color: #63574d;
        }

        .prod-meta-desc {
            font-size: 12px;
            color: var(--admin-text-muted);
            max-width: 320px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Price & Margin */
        .price-chip {
            font-weight: 700;
            color: var(--admin-text-main);
            font-size: 14px;
        }

        .cost-chip {
            font-size: 12px;
            color: var(--admin-text-muted);
            display: block;
            margin-top: 2px;
        }

        .margin-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            color: var(--admin-green);
            background: var(--admin-green-bg);
            padding: 1px 6px;
            border-radius: 4px;
            margin-top: 3px;
        }

        /* Stock Stepper in Table */
        .stock-control-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stock-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .stock-status-pill.status-ok {
            background: var(--admin-green-bg);
            color: var(--admin-green);
            border: 1px solid #bbf7d0;
        }

        .stock-status-pill.status-low {
            background: var(--admin-amber-bg);
            color: var(--admin-amber);
            border: 1px solid #fde68a;
        }

        .stock-status-pill.status-empty {
            background: var(--admin-red-bg);
            color: var(--admin-red);
            border: 1px solid #fecaca;
        }

        .stock-quick-stepper {
            display: inline-flex;
            border: 1px solid var(--admin-border);
            border-radius: 6px;
            overflow: hidden;
            background: #ffffff;
        }

        .stock-quick-stepper button {
            background: transparent;
            border: 0;
            padding: 4px 8px;
            font-size: 11px;
            color: var(--admin-text-main);
            cursor: pointer;
            transition: background 0.15s;
        }

        .stock-quick-stepper button:hover {
            background: #f0eae1;
            color: var(--admin-primary);
        }

        /* Action Buttons */
        .table-actions-cell {
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
        }

        .btn-action-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--admin-border);
            background: #ffffff;
            color: var(--admin-text-main);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            font-size: 13px;
        }

        .btn-action-icon:hover {
            border-color: #c4b9ac;
            background: #f7f4ef;
            transform: translateY(-1px);
        }

        .btn-action-icon.btn-edit:hover {
            color: #0284c7;
            border-color: #7dd3fc;
            background: #f0f9ff;
        }

        .btn-action-icon.btn-del:hover {
            color: #dc2626;
            border-color: #fca5a5;
            background: #fef2f2;
        }

        /* Modal Overlay System */
        .dash-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(30, 25, 20, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }

        .dash-modal-overlay.open {
            opacity: 1;
            visibility: visible;
        }

        .dash-modal-card {
            background: #ffffff;
            border: 1px solid var(--admin-border);
            border-radius: 16px;
            width: 100%;
            max-width: 580px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
            transform: scale(0.96) translateY(10px);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            padding: 28px;
            box-sizing: border-box;
        }

        .dash-modal-overlay.open .dash-modal-card {
            transform: scale(1) translateY(0);
        }

        .dash-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f0eae1;
        }

        .dash-modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: var(--admin-primary);
        }

        .dash-modal-close {
            background: transparent;
            border: 0;
            font-size: 18px;
            color: #9c9489;
            cursor: pointer;
            padding: 4px 8px;
        }

        .dash-modal-close:hover {
            color: #dc2626;
        }

        /* Form Controls inside Modal */
        .form-grid-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-field-group {
            margin-bottom: 16px;
        }

        .form-field-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #443d37;
        }

        .form-input-modern {
            box-sizing: border-box;
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #dcd3c8;
            border-radius: 8px;
            background: #faf8f5;
            color: #302923;
            font: inherit;
            font-size: 13.5px;
            outline: none;
            transition: border 0.15s, background 0.15s;
        }

        .form-input-modern:focus {
            border-color: var(--admin-primary);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(30, 58, 53, 0.1);
        }

        textarea.form-input-modern {
            min-height: 80px;
            resize: vertical;
        }

        .image-preview-box {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            background: #faf8f5;
            margin-top: 10px;
        }

        .image-preview-box img {
            width: 56px;
            height: 56px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #dcd3c8;
            background: #ffffff;
        }

        /* Toast Notifications */
        .admin-toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .admin-toast {
            background: #1e3a35;
            color: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: toastSlide 0.3s ease forwards;
        }

        .admin-toast.toast-error {
            background: #dc2626;
        }

        @keyframes toastSlide {
            from { transform: translateX(50px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Comprehensive Dark Mode Enhancements */
        body.admin-storefront[data-theme="dark"] .dash-table th {
            background: #18202c;
            color: #94a3b8;
            border-bottom-color: var(--admin-border);
        }

        body.admin-storefront[data-theme="dark"] .dash-table td {
            color: #edf2f7;
            border-bottom-color: #202938;
        }

        body.admin-storefront[data-theme="dark"] .dash-table tbody tr:hover {
            background: #1a2230;
        }

        body.admin-storefront[data-theme="dark"] .dash-search-box input,
        body.admin-storefront[data-theme="dark"] .dash-sort-select,
        body.admin-storefront[data-theme="dark"] .filter-pill-btn {
            background: #10151e;
            border-color: var(--admin-border);
            color: #edf2f7;
        }

        body.admin-storefront[data-theme="dark"] .filter-pill-btn.active {
            background: #203f3a;
            border-color: #34d399;
            color: #ffffff;
        }

        body.admin-storefront[data-theme="dark"] .btn-outline-action {
            background: #161c27;
            border-color: var(--admin-border);
            color: #edf2f7 !important;
        }

        body.admin-storefront[data-theme="dark"] .btn-outline-action:hover {
            background: #222c3c;
        }

        body.admin-storefront[data-theme="dark"] .dash-modal-card {
            background: #161c27;
            border-color: var(--admin-border);
            color: #edf2f7;
        }

        body.admin-storefront[data-theme="dark"] .dash-modal-header {
            border-bottom-color: var(--admin-border);
        }

        body.admin-storefront[data-theme="dark"] .dash-modal-header h2 {
            color: #34d399;
        }

        body.admin-storefront[data-theme="dark"] .form-field-group label {
            color: #cbd5e1;
        }

        body.admin-storefront[data-theme="dark"] .form-input-modern {
            background: #10151e;
            border-color: var(--admin-border);
            color: #edf2f7;
        }

        body.admin-storefront[data-theme="dark"] .form-input-modern:focus {
            border-color: #34d399;
            background: #131a26;
        }

        body.admin-storefront[data-theme="dark"] .image-preview-box {
            background: #10151e;
            border-color: var(--admin-border);
        }

        body.admin-storefront[data-theme="dark"] .image-preview-box img {
            background: #161c27;
            border-color: var(--admin-border);
        }

        body.admin-storefront[data-theme="dark"] .prod-thumb-wrap {
            background: #10151e;
            border-color: var(--admin-border);
        }

        body.admin-storefront[data-theme="dark"] .prod-cat-badge {
            background: #222b39;
            color: #94a3b8;
        }

        body.admin-storefront[data-theme="dark"] .cost-chip {
            color: #94a3b8;
        }

        body.admin-storefront[data-theme="dark"] .stock-quick-stepper {
            background: #10151e;
            border-color: var(--admin-border);
        }

        body.admin-storefront[data-theme="dark"] .stock-quick-stepper button {
            color: #edf2f7;
        }

        body.admin-storefront[data-theme="dark"] .stock-quick-stepper button:hover {
            background: #222c3c;
            color: #34d399;
        }

        body.admin-storefront[data-theme="dark"] .btn-action-icon {
            background: #1a2230;
            border-color: var(--admin-border);
            color: #94a3b8;
        }

        body.admin-storefront[data-theme="dark"] .btn-action-icon.btn-edit:hover {
            background: rgba(56, 189, 248, 0.15);
            border-color: #38bdf8;
            color: #38bdf8;
        }

        body.admin-storefront[data-theme="dark"] .btn-action-icon.btn-stock:hover {
            background: rgba(251, 191, 36, 0.15);
            border-color: #fbbf24;
            color: #fbbf24;
        }

        body.admin-storefront[data-theme="dark"] .btn-action-icon.btn-del:hover {
            background: rgba(248, 113, 113, 0.15);
            border-color: #f87171;
            color: #f87171;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .dashboard-main-pane {
                padding: 20px 16px;
            }
            .form-grid-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="fashion-storefront admin-storefront">
    <div class="dashboard-wrapper">
        <!-- Render Navigation Sidebar -->
        <?php renderAdminPrimaryNavigation($currentAdmin, $adminView === 'products' ? 'products' : 'dashboard'); ?>

        <!-- Main Content Area -->
        <main class="dashboard-main-pane" id="mainDashboardPane">
            <?php if ($adminView === 'dashboard'): ?>
                <!-- ========================================== -->
                <!-- EXECUTIVE DASHBOARD OVERVIEW VIEW          -->
                <!-- ========================================== -->
                <header class="dash-topbar">
                    <div class="dash-title-block">
                        <h1><i class="fas fa-gauge-high" style="color: var(--admin-accent);"></i> ផ្ទាំងគ្រប់គ្រងទូទៅ (Store Dashboard)</h1>
                        <p>ទិដ្ឋភាពទូទៅនៃប្រតិបត្តិការហាង ការលក់ ការបញ្ជាទិញ និងស្តុកទំនិញជាក់ស្តែង</p>
                    </div>
                    <div class="dash-topbar-actions">
                        <button type="button" class="btn-modern btn-primary-action" id="openAddProductModal">
                            <i class="fas fa-plus-circle"></i> + បន្ថែមផលិតផលថ្មី
                        </button>
                        <a href="operations.php?view=orders" class="btn-modern btn-outline-action">
                            <i class="fas fa-receipt"></i> គ្រប់គ្រងការបញ្ជាទិញ
                        </a>
                        <a href="../home/index.php" target="_blank" rel="noopener noreferrer" class="btn-modern btn-outline-action" title="បើកមើលហាងផ្ទាល់">
                            <i class="fas fa-arrow-up-right-from-square"></i> មើលទំព័រមុខហាង
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
                </header>

                <!-- KPI Metric Cards Grid -->
                <section class="kpi-cards-grid" aria-label="Quick metrics">
                    <div class="kpi-card">
                        <div class="kpi-icon-circle kpi-icon-val"><i class="fas fa-sack-dollar"></i></div>
                        <div class="kpi-card-info">
                            <span>សរុបទឹកប្រាក់លក់ (Total Revenue)</span>
                            <strong><?= $currencySymbol ?><?= number_format($dashTotalRevenue, 2) ?></strong>
                            <small style="font-size: 11px; color: var(--admin-text-muted);">តម្លៃទំនិញក្នុងស្តុក: <?= $currencySymbol ?><?= number_format((float)($kpiStats['total_valuation'] ?? 0), 2) ?></small>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon-circle kpi-icon-orders" style="background: rgba(59, 130, 246, 0.12); color: #2563eb;"><i class="fas fa-receipt"></i></div>
                        <div class="kpi-card-info">
                            <span>ការបញ្ជាទិញសរុប (Total Orders)</span>
                            <strong><?= $dashTotalOrders ?> <small style="font-size: 13px; font-weight: normal; color: var(--admin-text-muted);">Orders</small></strong>
                            <small style="font-size: 11px; color: var(--admin-text-muted);"><?= $dashPendingOrders ?> Pending / <?= $dashCompletedOrders ?> Completed</small>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon-circle kpi-icon-products"><i class="fas fa-shirt"></i></div>
                        <div class="kpi-card-info">
                            <span>មុខទំនិញក្នុងស្តុក (Catalog Items)</span>
                            <strong><?= count($productsList) ?> <small style="font-size: 13px; font-weight: normal; color: var(--admin-text-muted);">មុខទំនិញ</small></strong>
                            <small style="font-size: 11px; color: var(--admin-text-muted);"><?= number_format((int)($kpiStats['total_units'] ?? 0)) ?> គ្រឿងសរុបក្នុងឃ្លាំង</small>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon-circle kpi-icon-customers" style="background: rgba(16, 185, 129, 0.12); color: #059669;"><i class="fas fa-users"></i></div>
                        <div class="kpi-card-info">
                            <span>អតិថិជនបានចុះឈ្មោះ (Customers)</span>
                            <strong><?= $dashTotalCustomers ?> <small style="font-size: 13px; font-weight: normal; color: var(--admin-text-muted);">នាក់</small></strong>
                            <small style="font-size: 11px; color: var(--admin-text-muted);"><a href="operations.php?view=customers" style="color: inherit; text-decoration: underline;">គ្រប់គ្រងអតិថិជន &rarr;</a></small>
                        </div>
                    </div>
                </section>

                <!-- Two-Column Overview Matrix -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px; margin-top: 24px;">
                    <!-- Column 1: Recent Orders List -->
                    <div class="dash-table-card" style="margin: 0; padding: 22px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                            <h2 style="font-size: 16px; font-weight: 700; color: var(--admin-text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-clock-rotate-left" style="color: var(--admin-accent);"></i> ការបញ្ជាទិញថ្មីៗ (Recent Orders)
                            </h2>
                            <a href="operations.php?view=orders" class="btn-modern btn-outline-action" style="font-size: 12px; padding: 4px 10px;">មើលទាំងអស់ &rarr;</a>
                        </div>
                        <?php if (!empty($recentOrdersList)): ?>
                            <div class="table-responsive">
                                <table class="dash-table">
                                    <thead>
                                        <tr>
                                            <th>លេខកូដ (Order Ref)</th>
                                            <th>អតិថិជន</th>
                                            <th>តម្លៃសរុប</th>
                                            <th>ស្ថានភាព</th>
                                            <th style="text-align: right;">សកម្មភាព</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrdersList as $ro): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($ro['order_reference'] ?: '#' . $ro['id'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <div style="font-size: 11px; color: var(--admin-text-muted);"><?= date('M d, H:i', strtotime($ro['created_at'])) ?></div>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 600;"><?= htmlspecialchars($ro['customer_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div style="font-size: 11px; color: var(--admin-text-muted);"><?= htmlspecialchars($ro['phone_number'], ENT_QUOTES, 'UTF-8') ?></div>
                                                </td>
                                                <td>
                                                    <strong style="color: var(--admin-primary);"><?= $currencySymbol ?><?= number_format((float)$ro['total_amount'], 2) ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $st = strtolower((string)$ro['status']);
                                                    $badgeColor = ($st === 'completed' || $st === 'paid') ? '#059669' : ($st === 'pending' ? '#d97706' : '#dc2626');
                                                    $badgeBg = ($st === 'completed' || $st === 'paid') ? 'rgba(5, 150, 105, 0.1)' : ($st === 'pending' ? 'rgba(217, 119, 6, 0.1)' : 'rgba(220, 38, 38, 0.1)');
                                                    ?>
                                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700; color: <?= $badgeColor ?>; background: <?= $badgeBg ?>;">
                                                        <?= htmlspecialchars($ro['status'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: right;">
                                                    <a href="operations.php?view=orders&amp;details=<?= (int)$ro['id'] ?>" class="btn-action-icon btn-edit" title="ពិនិត្យការបញ្ជាទិញ">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 36px 16px; color: var(--admin-text-muted);">
                                <i class="fas fa-inbox" style="font-size: 36px; opacity: 0.35; margin-bottom: 12px; display: block;"></i>
                                <div style="font-weight: 600; font-size: 14px; margin-bottom: 6px;">មិនទាន់មានការបញ្ជាទិញថ្មីនៅឡើយទេ</div>
                                <p style="font-size: 12px; margin: 0 0 16px;">រាល់ពេលមានអតិថិជនកុម្ម៉ង់ទិញទំនិញ ព័ត៌មាននឹងបង្ហាញនៅទីនេះភ្លាមៗ។</p>
                                <a href="../home/index.php" target="_blank" class="btn-modern btn-outline-action" style="font-size: 12px; display: inline-flex;">
                                    <i class="fas fa-store"></i> ទៅកាន់ទំព័រមុខហាង
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Column 2: Inventory Health & Management Shortcuts -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <!-- Low Stock Card -->
                        <div class="dash-table-card" style="margin: 0; padding: 22px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                                <h2 style="font-size: 16px; font-weight: 700; color: var(--admin-text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-boxes-packing" style="color: #d97706;"></i> ស្ថានភាពស្តុកទំនិញ (Inventory Status)
                                </h2>
                                <a href="index.php?view=products" class="btn-modern btn-outline-action" style="font-size: 12px; padding: 4px 10px;">កាតាឡុកទំនិញ &rarr;</a>
                            </div>
                            <?php if (!empty($lowStockProducts)): ?>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <?php foreach ($lowStockProducts as $lp): ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border: 1px solid var(--admin-border); border-radius: 8px; background: rgba(0,0,0,0.015);">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <img src="<?= htmlspecialchars(!empty($lp['image']) ? '../' . ltrim($lp['image'], './\\') : '../home/logo/logome.png', ENT_QUOTES, 'UTF-8') ?>" alt="" style="width: 34px; height: 34px; border-radius: 6px; object-fit: cover;" onerror="this.src='../home/logo/logome.png'">
                                                <div>
                                                    <div style="font-size: 13px; font-weight: 600; color: var(--admin-text-main);"><?= htmlspecialchars($lp['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div style="font-size: 11px; color: var(--admin-text-muted);"><?= $currencySymbol ?><?= number_format((float)$lp['price'], 2) ?></div>
                                                </div>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <span class="stock-status-pill <?= (int)$lp['stock'] === 0 ? 'stock-out' : 'stock-low' ?>" style="font-size: 11px;">
                                                    <?= (int)$lp['stock'] === 0 ? 'អស់ស្តុក' : 'សល់ ' . (int)$lp['stock'] ?>
                                                </span>
                                                <button type="button" class="btn-action-icon btn-stock" data-adjust-id="<?= (int)$lp['id'] ?>" data-stock-val="<?= (int)$lp['stock'] ?>" data-name="<?= htmlspecialchars($lp['name'], ENT_QUOTES, 'UTF-8') ?>" title="កែប្រែចំនួនស្តុក">
                                                    <i class="fas fa-cubes-stacked"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 24px 16px; color: var(--admin-text-muted);">
                                    <i class="fas fa-circle-check" style="font-size: 32px; color: #059669; margin-bottom: 8px; display: block;"></i>
                                    <div style="font-weight: 600; color: #059669; font-size: 13.5px;">ស្តុកទំនិញទាំងអស់ស្ថិតក្នុងកម្រិតល្អ!</div>
                                    <div style="font-size: 12px; margin-top: 4px;">គ្មានទំនិញណាមួយជិតអស់ ឬដាច់ស្តុកឡើយ។</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Shortcuts Card -->
                        <div class="dash-table-card" style="margin: 0; padding: 22px;">
                            <h2 style="font-size: 16px; font-weight: 700; color: var(--admin-text-main); margin: 0 0 14px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-bolt" style="color: #b17b60;"></i> ផ្លូវកាត់ការគ្រប់គ្រងរហ័ស (Quick Actions)
                            </h2>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                                <a href="index.php?view=products" class="btn-modern btn-outline-action" style="justify-content: flex-start; padding: 10px 12px; text-decoration: none; font-size: 12.5px;">
                                    <i class="fas fa-boxes-stacked" style="color: var(--admin-accent);"></i> កាតាឡុកផលិតផល
                                </a>
                                <a href="operations.php?view=orders" class="btn-modern btn-outline-action" style="justify-content: flex-start; padding: 10px 12px; text-decoration: none; font-size: 12.5px;">
                                    <i class="fas fa-receipt" style="color: #2563eb;"></i> ផ្ទៀងផ្ទាត់ KHQR
                                </a>
                                <a href="../customer-service/dashboard.php" class="btn-modern btn-outline-action" style="justify-content: flex-start; padding: 10px 12px; text-decoration: none; font-size: 12.5px;">
                                    <i class="fas fa-headset" style="color: #059669;"></i> សារពីអតិថិជន
                                </a>
                                <a href="store-management.php?view=translations" class="btn-modern btn-outline-action" style="justify-content: flex-start; padding: 10px 12px; text-decoration: none; font-size: 12.5px;">
                                    <i class="fas fa-file-pen" style="color: #d97706;"></i> ខ្លឹមសារ &amp; Note
                                </a>
                                <a href="store-management.php?view=store" class="btn-modern btn-outline-action" style="justify-content: flex-start; padding: 10px 12px; text-decoration: none; font-size: 12.5px;">
                                    <i class="fas fa-gear" style="color: #4b5563;"></i> ការកំណត់ហាង
                                </a>
                                <a href="backup.php" class="btn-modern btn-outline-action" style="justify-content: flex-start; padding: 10px 12px; text-decoration: none; font-size: 12.5px;">
                                    <i class="fas fa-database" style="color: #6366f1;"></i> បម្រុងទុកទិន្នន័យ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- ========================================== -->
                <!-- PRODUCT CATALOG VIEW                       -->
                <!-- ========================================== -->
            <!-- Top Dashboard Bar -->
            <header class="dash-topbar">
                <div class="dash-title-block">
                    <h1><i class="fas fa-boxes-stacked" style="color: var(--admin-accent);"></i> គ្រប់គ្រងផលិតផល (Product Catalog)</h1>
                    <p>មើល បន្ថែម កែសម្រួលតម្លៃ និងតាមដានស្តុកទំនិញជាក់ស្តែងទាំងអស់</p>
                </div>
                <div class="dash-topbar-actions">
                    <button type="button" class="btn-modern btn-primary-action" id="openAddProductModal">
                        <i class="fas fa-plus-circle"></i> + បន្ថែមផលិតផលថ្មី
                    </button>
                    <a href="../home/index.php" target="_blank" rel="noopener noreferrer" class="btn-modern btn-outline-action" title="បើកមើលហាងផ្ទាល់">
                        <i class="fas fa-arrow-up-right-from-square"></i> មើលទំព័រមុខហាង
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
            </header>

            <!-- KPI Metric Cards -->
            <section class="kpi-cards-grid" aria-label="Quick metrics">
                <div class="kpi-card">
                    <div class="kpi-icon-circle kpi-icon-products"><i class="fas fa-shirt"></i></div>
                    <div class="kpi-card-info">
                        <span>ផលិតផលសរុប (Total Items)</span>
                        <strong><?= count($productsList) ?> <small style="font-size: 13px; font-weight: normal; color: var(--admin-text-muted);">មុខទំនិញ</small></strong>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-circle kpi-icon-stock"><i class="fas fa-warehouse"></i></div>
                    <div class="kpi-card-info">
                        <span>ស្តុកសរុបក្នុងឃ្លាំង (Total Units)</span>
                        <strong><?= number_format((int)($kpiStats['total_units'] ?? 0)) ?> <small style="font-size: 13px; font-weight: normal; color: var(--admin-text-muted);">គ្រឿង</small></strong>
                    </div>
                </div>
                <div class="kpi-card" id="kpiCardLowStock" style="cursor: pointer;" title="ចុចដើម្បីមើលផលិតផលស្តុកទាប">
                    <div class="kpi-icon-circle kpi-icon-low"><i class="fas fa-triangle-exclamation"></i></div>
                    <div class="kpi-card-info">
                        <span>ការជូនដំណឹងស្តុកទាប (Low Stock)</span>
                        <strong style="color: <?= ((int)($kpiStats['low_stock_count'] ?? 0)) > 0 ? '#d97706' : '#2e7d32'; ?>">
                            <?= (int)($kpiStats['low_stock_count'] ?? 0) ?> <small style="font-size: 13px; font-weight: normal;">មុខ</small>
                        </strong>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-circle kpi-icon-value"><i class="fas fa-coins"></i></div>
                    <div class="kpi-card-info">
                        <span>តម្លៃទំនិញក្នុងស្តុក (Valuation)</span>
                        <strong><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8'); ?><?= number_format((float)($kpiStats['total_value'] ?? 0), 2) ?></strong>
                    </div>
                </div>
            </section>

            <!-- Search, Category & Sorting Toolbar -->
            <section class="dash-toolbar-card">
                <div class="dash-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="adminProductSearch" placeholder="ស្វែងរកតាមឈ្មោះ, ID ឬប្រភេទផលិតផល..." autocomplete="off">
                    <button type="button" id="clearAdminSearch" class="dash-search-clear"><i class="fas fa-times"></i></button>
                </div>

                <div class="dash-filters-group">
                    <button type="button" class="filter-pill-btn active" data-filter-cat="all">ទាំងអស់ (<?= count($productsList) ?>)</button>
                    <button type="button" class="filter-pill-btn" data-filter-cat="men">បុរស (<?= $catCounts['men'] ?>)</button>
                    <button type="button" class="filter-pill-btn" data-filter-cat="women">នារី (<?= $catCounts['women'] ?>)</button>
                    <button type="button" class="filter-pill-btn" data-filter-cat="accessories">គ្រឿងតុបតែង (<?= $catCounts['accessories'] ?>)</button>
                    <button type="button" class="filter-pill-btn" data-filter-cat="digital">ឌីជីថល (<?= $catCounts['digital'] ?>)</button>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <select id="adminStockStatusFilter" class="dash-sort-select" aria-label="Stock status">
                        <option value="all">ស្តុកទាំងអស់ (All Stock)</option>
                        <option value="in_stock">មានស្តុក (In Stock)</option>
                        <option value="low_stock">ស្តុកតិច (Low Stock &le; 5)</option>
                        <option value="out_of_stock">អស់ស្តុក (Out of Stock)</option>
                    </select>

                    <select id="adminSortSelect" class="dash-sort-select" aria-label="Sort products">
                        <option value="newest">ថ្មីបំផុត (Newest ID)</option>
                        <option value="oldest">ចាស់បំផុត (Oldest ID)</option>
                        <option value="name_asc">ឈ្មោះ A &rarr; Z</option>
                        <option value="price_asc">តម្លៃ៖ ទាប &rarr; ខ្ពស់</option>
                        <option value="price_desc">តម្លៃ៖ ខ្ពស់ &rarr; ទាប</option>
                        <option value="stock_desc">ស្តុក៖ ច្រើន &rarr; តិច</option>
                    </select>
                </div>
            </section>

            <!-- Product Table Card -->
            <section class="dash-table-card">
                <div class="table-responsive">
                    <table class="dash-table" id="adminProductsTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>ផលិតផល (Product Info)</th>
                                <th style="width: 140px;">តម្លៃលក់ &amp; ដើម</th>
                                <th style="width: 180px;">ចំនួនស្តុក (Stock Level)</th>
                                <th style="width: 130px; text-align: right;">សកម្មភាព (Actions)</th>
                            </tr>
                        </thead>
                        <tbody id="adminProductsTableBody">
                            <?php foreach ($productsList as $p): ?>
                                <?php
                                $pId = (int)$p['id'];
                                $pName = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
                                $pCategory = htmlspecialchars($p['category'] ?: 'men', ENT_QUOTES, 'UTF-8');
                                $pDesc = htmlspecialchars($p['description'] ?? '', ENT_QUOTES, 'UTF-8');
                                $pPrice = (float)$p['price'];
                                $pCost = $p['cost_price'] !== null ? (float)$p['cost_price'] : null;
                                $pStock = (int)$p['stock'];
                                $pThreshold = (int)$p['low_stock_threshold'];
                                $pImg = !empty($p['image'])
                                    ? (preg_match('~^https?://~i', $p['image']) ? $p['image'] : '../' . $p['image'])
                                    : '../home/logo/logome.png';

                                $stockStatusClass = $pStock === 0 ? 'status-empty' : ($pStock <= $pThreshold ? 'status-low' : 'status-ok');
                                $stockStatusText = $pStock === 0 ? 'អស់ស្តុក (0)' : ($pStock <= $pThreshold ? "ស្តុកតិច ({$pStock})" : "មានស្តុក ({$pStock})");

                                $pOriginal = isset($p['original_price']) && is_numeric($p['original_price']) ? (float)$p['original_price'] : null;
                                $pDiscount = (int)($p['discount_percent'] ?? 0);
                                if ($pOriginal !== null && $pOriginal > $pPrice && $pDiscount === 0) {
                                    $pDiscount = (int) round((($pOriginal - $pPrice) / $pOriginal) * 100);
                                }

                                $marginHtml = '';
                                if ($pCost !== null && $pPrice > 0) {
                                    $margin = round((($pPrice - $pCost) / $pPrice) * 100, 1);
                                    $marginHtml = "<span class=\"margin-badge\">+{$margin}% ចំណេញ</span>";
                                }
                                ?>
                                <tr class="prod-table-row" 
                                    data-id="<?= $pId ?>"
                                    data-name="<?= strtolower($pName) ?>"
                                    data-category="<?= strtolower($pCategory) ?>"
                                    data-price="<?= $pPrice ?>"
                                    data-original-price="<?= $pOriginal !== null ? $pOriginal : '' ?>"
                                    data-discount-percent="<?= $pDiscount ?>"
                                    data-stock="<?= $pStock ?>"
                                    data-threshold="<?= $pThreshold ?>">
                                    <td style="font-weight: 700; color: #887d72;">#<?= $pId ?></td>
                                    <td>
                                        <div class="prod-cell">
                                            <div class="prod-thumb-wrap">
                                                <img src="<?= htmlspecialchars($pImg, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $pName ?>" onerror="this.src='../home/logo/logome.png'">
                                            </div>
                                            <div>
                                                <div class="prod-meta-title">
                                                    <span><?= $pName ?></span>
                                                    <span class="prod-cat-badge"><?= strtoupper($pCategory) ?></span>
                                                    <?php if ($pOriginal !== null && $pOriginal > $pPrice): ?>
                                                        <span style="background: #e11d48; color: #fff; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; margin-left: 4px;"><i class="fas fa-tag"></i> -<?= $pDiscount ?>%</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="prod-meta-desc"><?= $pDesc ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="price-chip">
                                            <?= $currencySymbol ?><?= number_format($pPrice, 2) ?>
                                            <?php if ($pOriginal !== null && $pOriginal > $pPrice): ?>
                                                <span style="font-size: 11px; text-decoration: line-through; opacity: 0.65; margin-left: 4px;"><?= $currencySymbol ?><?= number_format($pOriginal, 2) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="cost-chip">ដើម: <?= $pCost !== null ? $currencySymbol . number_format($pCost, 2) : 'មិនបានកំណត់' ?></span>
                                        <?= $marginHtml ?>
                                    </td>
                                    <td>
                                        <div class="stock-control-cell">
                                            <span class="stock-status-pill <?= $stockStatusClass ?>" id="stockBadge-<?= $pId ?>">
                                                <?= $stockStatusText ?>
                                            </span>
                                            <div class="stock-quick-stepper">
                                                <button type="button" class="quick-stock-btn" data-id="<?= $pId ?>" data-delta="-1" title="បន្ថយស្តុក 1">&minus;</button>
                                                <button type="button" class="quick-stock-btn" data-id="<?= $pId ?>" data-delta="1" title="បន្ថែមស្តុក 1">&plus;</button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="table-actions-cell">
                                            <button type="button" class="btn-action-icon btn-edit" data-edit-id="<?= $pId ?>" title="កែប្រែព័ត៌មាន">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <button type="button" class="btn-action-icon btn-stock" data-adjust-id="<?= $pId ?>" data-stock-val="<?= $pStock ?>" data-name="<?= $pName ?>" title="កែប្រែចំនួនស្តុក">
                                                <i class="fas fa-cubes-stacked"></i>
                                            </button>
                                            <button type="button" class="btn-action-icon btn-del" data-delete-id="<?= $pId ?>" data-name="<?= $pName ?>" title="លុបផលិតផល">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- ============================================================== -->
    <!-- ADD / EDIT PRODUCT MODAL (SMOOTH & CLEAN)                       -->
    <!-- ============================================================== -->
    <div class="dash-modal-overlay" id="productModal">
        <div class="dash-modal-card" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="dash-modal-header">
                <h2 id="modalTitle"><i class="fas fa-plus-circle" style="color: var(--admin-accent);"></i> បន្ថែមផលិតផលថ្មី</h2>
                <button type="button" class="dash-modal-close" data-close-modal aria-label="បិទ"><i class="fas fa-xmark"></i></button>
            </div>

            <form id="productForm" action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formProductId" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="is_ajax" value="1">

                <!-- Link Auto-fill Helper -->
                <div class="form-field-group" id="autoFillGroup">
                    <label><i class="fas fa-wand-magic-sparkles" style="color: var(--admin-accent);"></i> ស្វែងរកបំពេញស្វ័យប្រវត្តិតាម Link (Auto-fill)</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="url" id="linkScrapeInput" class="form-input-modern" placeholder="https://example.com/product/...">
                        <button type="button" id="btnScrapeProduct" class="btn-modern btn-outline-action" style="white-space: nowrap;">
                            <i class="fas fa-download"></i> ទាញយកទិន្នន័យ
                        </button>
                    </div>
                    <span id="scrapeStatus" style="display:none; font-size:12px; margin-top:4px;"></span>
                </div>

                <div class="form-grid-row">
                    <div class="form-field-group">
                        <label for="inputName">ឈ្មោះផលិតផល (Product Name) *</label>
                        <input type="text" id="inputName" name="name" class="form-input-modern" placeholder="ឧ. Men Classic T-Shirt" required>
                    </div>
                    <div class="form-field-group">
                        <label for="inputCategory">ប្រភេទផលិតផល (Category) *</label>
                        <select id="inputCategory" name="category" class="form-input-modern" required>
                            <option value="men">បុរស (Men's Clothing)</option>
                            <option value="women">នារី (Women's Clothing)</option>
                            <option value="accessories">គ្រឿងតុបតែង &amp; ស្បែកជើង (Accessories)</option>
                            <option value="digital">សេវាកម្ម &amp; ឌីជីថល (Digital)</option>
                            <option value="fashion">ម៉ូដទូទៅ (Fashion General)</option>
                        </select>
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="inputDescription">ព័ត៌មានលម្អិត (Description)</label>
                    <textarea id="inputDescription" name="description" class="form-input-modern" placeholder="ពិពណ៌នាពីសាច់ក្រណាត់ ទំហំ និងគុណភាពផលិតផល..."></textarea>
                </div>

                <div class="form-grid-row">
                    <div class="form-field-group">
                        <label for="inputPrice">តម្លៃលក់បច្ចុប្បន្ន $ (Sale / Current Price) *</label>
                        <input type="number" step="0.01" min="0" id="inputPrice" name="price" class="form-input-modern" placeholder="12.00" required>
                    </div>
                    <div class="form-field-group">
                        <label for="inputCostPrice">តម្លៃដើមទិញចូល $ (Cost Price)</label>
                        <input type="number" step="0.01" min="0" id="inputCostPrice" name="cost_price" class="form-input-modern" placeholder="6.00">
                    </div>
                </div>

                <!-- Discount & Promotion Configuration -->
                <div style="background: #faf8f5; border: 1px dashed #ded5cb; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-weight: 700; font-size: 13.5px; color: #203f3a;"><i class="fas fa-tags" style="color: #e11d48; margin-right: 6px;"></i> ការបញ្ចុះតម្លៃ (Discount & Promotion)</span>
                        <span id="discountLiveBadge" style="display: none; background: #e11d48; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 800;">-0% OFF</span>
                    </div>
                    <div class="form-grid-row" style="margin-bottom: 0;">
                        <div class="form-field-group" style="margin-bottom: 0;">
                            <label for="inputOriginalPrice" style="font-size: 12.5px; color: #554d45;">តម្លៃដើមមុនបញ្ចុះ $ (Original Price)</label>
                            <input type="number" step="0.01" min="0" id="inputOriginalPrice" name="original_price" class="form-input-modern" placeholder="ឧ. 20.00">
                        </div>
                        <div class="form-field-group" style="margin-bottom: 0;">
                            <label for="inputDiscountPercent" style="font-size: 12.5px; color: #554d45;">ភាគរយបញ្ចុះ % (Discount %)</label>
                            <input type="number" step="1" min="0" max="99" id="inputDiscountPercent" name="discount_percent" class="form-input-modern" placeholder="ឧ. 25">
                        </div>
                    </div>
                    <small style="display: block; margin-top: 8px; font-size: 11.5px; color: #887d72;">
                        <i class="fas fa-circle-info"></i> បញ្ចូលតម្លៃដើម ឬភាគរយបញ្ចុះ ប្រព័ន្ធនឹងគណនាបញ្ចុះតម្លៃ និងបង្ហាញលើ Card ផលិតផលដោយស្វ័យប្រវត្តិ។
                    </small>
                </div>

                <div class="form-grid-row">
                    <div class="form-field-group">
                        <label for="inputStock">ចំនួនស្តុក (Stock Quantity) *</label>
                        <input type="number" step="1" min="0" id="inputStock" name="stock" class="form-input-modern" value="50" required>
                    </div>
                    <div class="form-field-group">
                        <label for="inputThreshold">កម្រិតស្តុកតិច (Low Threshold)</label>
                        <input type="number" step="1" min="1" id="inputThreshold" name="low_stock_threshold" class="form-input-modern" value="5">
                    </div>
                </div>

                <!-- Product Image Selection -->
                <div class="form-field-group">
                    <label>រូបភាពផលិតផល (Product Image)</label>
                    <div style="display: flex; gap: 16px; margin-bottom: 8px;">
                        <label style="font-weight: normal; cursor: pointer;">
                            <input type="radio" name="image_source" value="url" checked id="radioSourceUrl"> ប្រើ Link រូបភាព (URL)
                        </label>
                        <label style="font-weight: normal; cursor: pointer;">
                            <input type="radio" name="image_source" value="upload" id="radioSourceUpload"> បង្ហោះរូបភាពពីកុំព្យូទ័រ (File Upload)
                        </label>
                    </div>

                    <div id="urlInputContainer">
                        <input type="url" id="inputImageUrl" name="image_url" class="form-input-modern" placeholder="https://images.unsplash.com/photo-...">
                    </div>

                    <div id="fileUploadContainer" style="display: none;">
                        <input type="file" id="inputFileUpload" name="icon" class="form-input-modern" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>

                    <div class="image-preview-box" id="imgPreviewWrapper">
                        <img id="imgPreviewThumb" src="../home/logo/logome.png" alt="Preview">
                        <span id="imgPreviewLabel" style="font-size: 12px; color: var(--admin-text-muted);">រូបភាព preview នឹងបង្ហាញទីនេះ</span>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px;">
                    <button type="button" class="btn-modern btn-outline-action" data-close-modal>បោះបង់ (Cancel)</button>
                    <button type="submit" id="btnSaveProduct" class="btn-modern btn-primary-action">
                        <i class="fas fa-check"></i> រក្សាទុក (Save Product)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- QUICK STOCK ADJUSTMENT MODAL                                    -->
    <!-- ============================================================== -->
    <div class="dash-modal-overlay" id="stockAdjustModal">
        <div class="dash-modal-card" style="max-width: 440px;" role="dialog" aria-modal="true">
            <div class="dash-modal-header">
                <h2><i class="fas fa-cubes-stacked" style="color: var(--admin-accent);"></i> កែប្រែចំនួនស្តុក</h2>
                <button type="button" class="dash-modal-close" data-close-modal><i class="fas fa-xmark"></i></button>
            </div>
            <form id="stockAdjustForm">
                <input type="hidden" id="adjustProdId" name="id" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div style="margin-bottom: 16px;">
                    <strong id="adjustProdName" style="font-size: 16px; color: var(--admin-primary); display:block; margin-bottom: 4px;"></strong>
                    <span style="font-size: 13px; color: var(--admin-text-muted);">ស្តុកបច្ចុប្បន្ន: <strong id="adjustCurrentStockVal" style="color:var(--admin-text-main);">0</strong> គ្រឿង</span>
                </div>

                <div class="form-field-group">
                    <label for="adjustNewStock">ចំនួនស្តុកថ្មី (New Stock Total)</label>
                    <input type="number" min="0" id="adjustNewStock" name="new_stock" class="form-input-modern" required>
                </div>

                <div style="display: flex; gap: 6px; margin-bottom: 16px;">
                    <button type="button" class="filter-pill-btn adjust-chip" data-add="5">+5</button>
                    <button type="button" class="filter-pill-btn adjust-chip" data-add="10">+10</button>
                    <button type="button" class="filter-pill-btn adjust-chip" data-add="25">+25</button>
                    <button type="button" class="filter-pill-btn adjust-chip" data-add="-5">-5</button>
                    <button type="button" class="filter-pill-btn adjust-chip" data-add="-10">-10</button>
                </div>

                <div class="form-field-group">
                    <label for="adjustReason">មូលហេតុនៃការកែប្រែ (Audit Reason)</label>
                    <input type="text" id="adjustReason" name="reason" class="form-input-modern" value="កែប្រែស្តុកដោយ Admin" placeholder="ឧ. ទំនិញចូលថ្មីពីអ្នកផ្គត់ផ្គង់">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn-modern btn-outline-action" data-close-modal>បោះបង់</button>
                    <button type="submit" class="btn-modern btn-primary-action"><i class="fas fa-save"></i> កែប្រែស្តុក</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- DELETE CONFIRMATION MODAL                                      -->
    <!-- ============================================================== -->
    <div class="dash-modal-overlay" id="deleteModal">
        <div class="dash-modal-card" style="max-width: 420px; text-align: center;" role="dialog" aria-modal="true">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: grid; place-items: center; font-size: 24px; margin: 0 auto 16px;">
                <i class="fas fa-trash-can"></i>
            </div>
            <h2 style="font-size: 20px; margin: 0 0 8px; color: var(--admin-text-main);">បញ្ជាក់ការលុបផលិតផល</h2>
            <p style="font-size: 13.5px; color: var(--admin-text-muted); margin: 0 0 16px;">
                តើអ្នកពិតជាចង់លុបផលិតផលនេះមែនទេ? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។
            </p>
            <strong id="deleteProdNameDisplay" style="display:block; font-size: 15px; color: var(--admin-primary); margin-bottom: 22px;"></strong>

            <form id="deleteProductForm">
                <input type="hidden" id="deleteTargetId" name="id" value="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="is_ajax" value="1">
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" class="btn-modern btn-outline-action" data-close-modal>បោះបង់</button>
                    <button type="submit" class="btn-modern" style="background: #dc2626; color: #fff;">
                        <i class="fas fa-trash"></i> លុបផលិតផល
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="admin-toast-container" id="toastContainer"></div>

    <!-- Interactive Client-Side Engine -->
    <script>
        (() => {
            const csrfToken = <?= json_encode($csrfToken) ?>;
            const currencySymbol = <?= json_encode($currencySymbol) ?>;

            // Toast feedback helper
            function showToast(message, isError = false) {
                const container = document.getElementById('toastContainer');
                const toast = document.createElement('div');
                toast.className = 'admin-toast' + (isError ? ' toast-error' : '');
                toast.innerHTML = `<i class="fas ${isError ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i> <span>${message}</span>`;
                container.appendChild(toast);
                setTimeout(() => {
                    toast.style.transition = 'opacity 0.3s, transform 0.3s';
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(10px)';
                    setTimeout(() => toast.remove(), 300);
                }, 3500);
            }

            // ---------------------------------------------------------
            // Instant Search, Category Filter & Sorting
            // ---------------------------------------------------------
            const searchInput = document.getElementById('adminProductSearch');
            const clearSearchBtn = document.getElementById('clearAdminSearch');
            const catFilterBtns = document.querySelectorAll('.filter-pill-btn[data-filter-cat]');
            const stockFilterSelect = document.getElementById('adminStockStatusFilter');
            const sortSelect = document.getElementById('adminSortSelect');
            const tableBody = document.getElementById('adminProductsTableBody');
            const allRows = Array.from(document.querySelectorAll('.prod-table-row'));

            function applyFilters() {
                const query = searchInput.value.trim().toLowerCase();
                clearSearchBtn.style.display = query !== '' ? 'block' : 'none';

                const activeCatBtn = document.querySelector('.filter-pill-btn.active[data-filter-cat]');
                const activeCat = activeCatBtn ? activeCatBtn.dataset.filterCat : 'all';
                const stockFilter = stockFilterSelect.value;

                allRows.forEach(row => {
                    const name = row.dataset.name || '';
                    const id = row.dataset.id || '';
                    const cat = row.dataset.category || '';
                    const stock = parseInt(row.dataset.stock, 10) || 0;
                    const threshold = parseInt(row.dataset.threshold, 10) || 5;

                    const matchSearch = query === '' || name.includes(query) || id === query || cat.includes(query);
                    const matchCat = activeCat === 'all' || cat === activeCat;

                    let matchStock = true;
                    if (stockFilter === 'in_stock') matchStock = stock > threshold;
                    else if (stockFilter === 'low_stock') matchStock = stock > 0 && stock <= threshold;
                    else if (stockFilter === 'out_of_stock') matchStock = stock === 0;

                    if (matchSearch && matchCat && matchStock) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });

                applySorting();
            }

            function applySorting() {
                const sortVal = sortSelect.value;
                const rows = Array.from(tableBody.querySelectorAll('.prod-table-row'));
                rows.sort((a, b) => {
                    const idA = parseInt(a.dataset.id, 10);
                    const idB = parseInt(b.dataset.id, 10);
                    const priceA = parseFloat(a.dataset.price);
                    const priceB = parseFloat(b.dataset.price);
                    const stockA = parseInt(a.dataset.stock, 10);
                    const stockB = parseInt(b.dataset.stock, 10);
                    const nameA = a.dataset.name;
                    const nameB = b.dataset.name;

                    if (sortVal === 'newest') return idB - idA;
                    if (sortVal === 'oldest') return idA - idB;
                    if (sortVal === 'price_asc') return priceA - priceB;
                    if (sortVal === 'price_desc') return priceB - priceA;
                    if (sortVal === 'stock_desc') return stockB - stockA;
                    if (sortVal === 'name_asc') return nameA.localeCompare(nameB);
                    return 0;
                });
                rows.forEach(r => tableBody.appendChild(r));
            }

            if (searchInput) searchInput.addEventListener('input', applyFilters);
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', () => {
                    searchInput.value = '';
                    applyFilters();
                    searchInput.focus();
                });
            }

            catFilterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    catFilterBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    applyFilters();
                });
            });

            if (stockFilterSelect) stockFilterSelect.addEventListener('change', applyFilters);
            if (sortSelect) sortSelect.addEventListener('change', applySorting);

            // KPI Low Stock Card Click to filter
            const kpiLowCard = document.getElementById('kpiCardLowStock');
            if (kpiLowCard) {
                kpiLowCard.addEventListener('click', () => {
                    stockFilterSelect.value = 'low_stock';
                    applyFilters();
                });
            }

            // ---------------------------------------------------------
            // Modal Open / Close Helpers
            // ---------------------------------------------------------
            const productModal = document.getElementById('productModal');
            const stockAdjustModal = document.getElementById('stockAdjustModal');
            const deleteModal = document.getElementById('deleteModal');

            function openModal(modalEl) {
                modalEl.classList.add('open');
                document.body.style.overflow = 'hidden';
            }

            function closeModal(modalEl) {
                modalEl.classList.remove('open');
                document.body.style.overflow = '';
            }

            document.querySelectorAll('[data-close-modal]').forEach(btn => {
                btn.addEventListener('click', () => {
                    closeModal(productModal);
                    closeModal(stockAdjustModal);
                    closeModal(deleteModal);
                });
            });

            [productModal, stockAdjustModal, deleteModal].forEach(modalEl => {
                modalEl.addEventListener('click', (e) => {
                    if (e.target === modalEl) closeModal(modalEl);
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeModal(productModal);
                    closeModal(stockAdjustModal);
                    closeModal(deleteModal);
                }
            });

            // ---------------------------------------------------------
            // Add / Edit Product Modal Handlers
            // ---------------------------------------------------------
            const openAddBtn = document.getElementById('openAddProductModal');
            const productForm = document.getElementById('productForm');
            const modalTitle = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            const formProductId = document.getElementById('formProductId');
            const inputName = document.getElementById('inputName');
            const inputCategory = document.getElementById('inputCategory');
            const inputDescription = document.getElementById('inputDescription');
            const inputPrice = document.getElementById('inputPrice');
            const inputOriginalPrice = document.getElementById('inputOriginalPrice');
            const inputDiscountPercent = document.getElementById('inputDiscountPercent');
            const discountLiveBadge = document.getElementById('discountLiveBadge');
            const inputCostPrice = document.getElementById('inputCostPrice');

            function syncDiscountCalculation(source) {
                const price = parseFloat(inputPrice.value) || 0;
                const orig = parseFloat(inputOriginalPrice.value) || 0;
                const disc = parseInt(inputDiscountPercent.value, 10) || 0;

                if (source === 'orig' || source === 'price') {
                    if (orig > price && price > 0) {
                        const calculatedPercent = Math.round(((orig - price) / orig) * 100);
                        inputDiscountPercent.value = calculatedPercent;
                        discountLiveBadge.textContent = `-${calculatedPercent}% OFF`;
                        discountLiveBadge.style.display = 'inline';
                    } else if (orig <= price || orig === 0) {
                        if (source === 'orig' && orig === 0) {
                            inputDiscountPercent.value = '';
                            discountLiveBadge.style.display = 'none';
                        }
                    }
                } else if (source === 'disc') {
                    if (disc > 0 && disc < 100) {
                        if (orig > 0) {
                            const calculatedPrice = (orig * (1 - (disc / 100))).toFixed(2);
                            inputPrice.value = calculatedPrice;
                        } else if (price > 0) {
                            const calculatedOrig = (price / (1 - (disc / 100))).toFixed(2);
                            inputOriginalPrice.value = calculatedOrig;
                        }
                        discountLiveBadge.textContent = `-${disc}% OFF`;
                        discountLiveBadge.style.display = 'inline';
                    } else {
                        discountLiveBadge.style.display = 'none';
                    }
                }
            }

            if (inputPrice) inputPrice.addEventListener('input', () => syncDiscountCalculation('price'));
            if (inputOriginalPrice) inputOriginalPrice.addEventListener('input', () => syncDiscountCalculation('orig'));
            if (inputDiscountPercent) inputDiscountPercent.addEventListener('input', () => syncDiscountCalculation('disc'));
            const inputStock = document.getElementById('inputStock');
            const inputThreshold = document.getElementById('inputThreshold');
            const inputImageUrl = document.getElementById('inputImageUrl');
            const inputFileUpload = document.getElementById('inputFileUpload');
            const radioSourceUrl = document.getElementById('radioSourceUrl');
            const radioSourceUpload = document.getElementById('radioSourceUpload');
            const urlContainer = document.getElementById('urlInputContainer');
            const fileContainer = document.getElementById('fileUploadContainer');
            const imgPreviewThumb = document.getElementById('imgPreviewThumb');
            const imgPreviewLabel = document.getElementById('imgPreviewLabel');
            const autoFillGroup = document.getElementById('autoFillGroup');

            function updateImageSourceInputs() {
                if (radioSourceUrl.checked) {
                    urlContainer.style.display = 'block';
                    fileContainer.style.display = 'none';
                    if (inputImageUrl.value.trim()) {
                        imgPreviewThumb.src = inputImageUrl.value.trim();
                        imgPreviewLabel.textContent = 'URL Image';
                    }
                } else {
                    urlContainer.style.display = 'none';
                    fileContainer.style.display = 'block';
                    imgPreviewLabel.textContent = 'ជ្រើសរើសឯកសារពីកុំព្យូទ័រ';
                }
            }

            radioSourceUrl.addEventListener('change', updateImageSourceInputs);
            radioSourceUpload.addEventListener('change', updateImageSourceInputs);
            inputImageUrl.addEventListener('input', () => {
                const url = inputImageUrl.value.trim();
                imgPreviewThumb.src = url || '../home/logo/logome.png';
            });
            inputFileUpload.addEventListener('change', () => {
                if (inputFileUpload.files && inputFileUpload.files[0]) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        imgPreviewThumb.src = e.target.result;
                        imgPreviewLabel.textContent = inputFileUpload.files[0].name;
                    };
                    reader.readAsDataURL(inputFileUpload.files[0]);
                }
            });

            // Open Add Modal
            if (openAddBtn) {
                openAddBtn.addEventListener('click', () => {
                    productForm.reset();
                    formAction.value = 'add';
                    formProductId.value = '';
                    modalTitle.innerHTML = '<i class="fas fa-plus-circle" style="color: var(--admin-accent);"></i> បន្ថែមផលិតផលថ្មី';
                    autoFillGroup.style.display = 'block';
                    radioSourceUrl.checked = true;
                    inputOriginalPrice.value = '';
                    inputDiscountPercent.value = '';
                    discountLiveBadge.style.display = 'none';
                    updateImageSourceInputs();
                    imgPreviewThumb.src = '../home/logo/logome.png';
                    openModal(productModal);
                    inputName.focus();
                });
            }

            // Open Edit Modal
            document.querySelectorAll('[data-edit-id]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.dataset.editId;
                    try {
                        const res = await fetch(`index.php?action=get_product_json&id=${id}`);
                        const data = await res.json();
                        if (data.success && data.product) {
                            const p = data.product;
                            formAction.value = 'edit';
                            formProductId.value = p.id;
                            modalTitle.innerHTML = `<i class="fas fa-pen" style="color: var(--admin-accent);"></i> កែប្រែផលិតផល #${p.id}`;
                            autoFillGroup.style.display = 'none';

                            inputName.value = p.name;
                            inputCategory.value = p.category || 'men';
                            inputDescription.value = p.description || '';
                            inputPrice.value = p.price;
                            inputOriginalPrice.value = p.original_price || '';
                            inputDiscountPercent.value = p.discount_percent || '';
                            syncDiscountCalculation('orig');
                            inputCostPrice.value = p.cost_price || '';
                            inputStock.value = p.stock;
                            inputThreshold.value = p.low_stock_threshold || 5;

                            const isUrl = p.image && /^https?:\/\//i.test(p.image);
                            if (isUrl) {
                                radioSourceUrl.checked = true;
                                inputImageUrl.value = p.image;
                                imgPreviewThumb.src = p.image;
                            } else {
                                radioSourceUrl.checked = true;
                                inputImageUrl.value = p.image ? '../' + p.image : '';
                                imgPreviewThumb.src = p.image ? '../' + p.image : '../home/logo/logome.png';
                            }
                            updateImageSourceInputs();
                            openModal(productModal);
                        } else {
                            showToast(data.message || 'Cannot load product details', true);
                        }
                    } catch (err) {
                        showToast('Network error loading product', true);
                    }
                });
            });

            // Product Form Submit (AJAX)
            productForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const saveBtn = document.getElementById('btnSaveProduct');
                const origHtml = saveBtn.innerHTML;
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> កំពុងរក្សាទុក...';

                try {
                    const formData = new FormData(productForm);
                    const res = await fetch('index.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Product saved successfully!');
                        closeModal(productModal);
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        showToast(data.message || 'Error saving product', true);
                    }
                } catch (err) {
                    showToast('Connection error while saving', true);
                } finally {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = origHtml;
                }
            });

            // ---------------------------------------------------------
            // Quick Stock Stepper Buttons in Table Rows (+1, -1)
            // ---------------------------------------------------------
            document.querySelectorAll('.quick-stock-btn').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const pId = btn.dataset.id;
                    const delta = parseInt(btn.dataset.delta, 10);
                    btn.disabled = true;

                    try {
                        const formData = new FormData();
                        formData.append('action', 'quick_stock_adjust');
                        formData.append('id', pId);
                        formData.append('delta', delta);
                        formData.append('csrf_token', csrfToken);
                        formData.append('reason', delta > 0 ? 'បន្ថែមស្តុក 1 (Quick Stepper)' : 'កាត់ស្តុក 1 (Quick Stepper)');

                        const res = await fetch('index.php', { method: 'POST', body: formData });
                        const data = await res.json();

                        if (data.success) {
                            const badge = document.getElementById(`stockBadge-${pId}`);
                            const row = btn.closest('.prod-table-row');
                            if (badge) {
                                badge.className = `stock-status-pill status-${data.stock_status}`;
                                badge.textContent = data.stock_after === 0 ? 'អស់ស្តុក (0)' : (data.stock_status === 'low' ? `ស្តុកតិច (${data.stock_after})` : `មានស្តុក (${data.stock_after})`);
                            }
                            if (row) row.dataset.stock = data.stock_after;
                            showToast(data.message);
                        } else {
                            showToast(data.message || 'Could not update stock', true);
                        }
                    } catch (err) {
                        showToast('Network error updating stock', true);
                    } finally {
                        btn.disabled = false;
                    }
                });
            });

            // ---------------------------------------------------------
            // Quick Stock Adjust Modal Form
            // ---------------------------------------------------------
            const stockAdjustForm = document.getElementById('stockAdjustForm');
            const adjustProdId = document.getElementById('adjustProdId');
            const adjustProdName = document.getElementById('adjustProdName');
            const adjustCurrentStockVal = document.getElementById('adjustCurrentStockVal');
            const adjustNewStock = document.getElementById('adjustNewStock');

            document.querySelectorAll('[data-adjust-id]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.adjustId;
                    const name = btn.dataset.name;
                    const curStock = parseInt(btn.dataset.stockVal, 10);

                    adjustProdId.value = id;
                    adjustProdName.textContent = name;
                    adjustCurrentStockVal.textContent = curStock;
                    adjustNewStock.value = curStock;
                    openModal(stockAdjustModal);
                });
            });

            document.querySelectorAll('.adjust-chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    const add = parseInt(chip.dataset.add, 10);
                    const cur = parseInt(adjustNewStock.value || '0', 10);
                    adjustNewStock.value = Math.max(0, cur + add);
                });
            });

            stockAdjustForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                try {
                    const formData = new FormData(stockAdjustForm);
                    formData.append('action', 'quick_stock_adjust');
                    const res = await fetch('index.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message);
                        closeModal(stockAdjustModal);
                        const badge = document.getElementById(`stockBadge-${data.product_id}`);
                        const row = document.querySelector(`.prod-table-row[data-id="${data.product_id}"]`);
                        if (badge) {
                            badge.className = `stock-status-pill status-${data.stock_status}`;
                            badge.textContent = data.stock_after === 0 ? 'អស់ស្តុក (0)' : (data.stock_status === 'low' ? `ស្តុកតិច (${data.stock_after})` : `មានស្តុក (${data.stock_after})`);
                        }
                        if (row) row.dataset.stock = data.stock_after;
                    } else {
                        showToast(data.message || 'Error updating stock', true);
                    }
                } catch (err) {
                    showToast('Connection error', true);
                }
            });

            // ---------------------------------------------------------
            // Delete Modal Handlers
            // ---------------------------------------------------------
            const deleteTargetId = document.getElementById('deleteTargetId');
            const deleteProdNameDisplay = document.getElementById('deleteProdNameDisplay');
            const deleteProductForm = document.getElementById('deleteProductForm');

            document.querySelectorAll('[data-delete-id]').forEach(btn => {
                btn.addEventListener('click', () => {
                    deleteTargetId.value = btn.dataset.deleteId;
                    deleteProdNameDisplay.textContent = btn.dataset.name;
                    openModal(deleteModal);
                });
            });

            deleteProductForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                try {
                    const formData = new FormData(deleteProductForm);
                    const res = await fetch('index.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message || 'Product deleted');
                        closeModal(deleteModal);
                        const row = document.querySelector(`.prod-table-row[data-id="${deleteTargetId.value}"]`);
                        if (row) row.remove();
                    } else {
                        showToast(data.message || 'Could not delete product', true);
                    }
                } catch (err) {
                    showToast('Connection error', true);
                }
            });

            // ---------------------------------------------------------
            // URL Scraper / Auto-Fill Tool
            // ---------------------------------------------------------
            const btnScrape = document.getElementById('btnScrapeProduct');
            const linkInput = document.getElementById('linkScrapeInput');
            const scrapeStatus = document.getElementById('scrapeStatus');

            if (btnScrape && linkInput) {
                btnScrape.addEventListener('click', async () => {
                    const url = linkInput.value.trim();
                    if (!url) {
                        scrapeStatus.style.display = 'block';
                        scrapeStatus.style.color = '#dc2626';
                        scrapeStatus.textContent = 'សូមបញ្ចូល Link ជាមុនសិន';
                        return;
                    }
                    btnScrape.disabled = true;
                    btnScrape.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';
                    scrapeStatus.style.display = 'block';
                    scrapeStatus.style.color = 'var(--admin-primary)';
                    scrapeStatus.textContent = 'កំពុងទាញយកទិន្នន័យពី Link...';

                    try {
                        const res = await fetch(`../api/scrape-product.php?url=${encodeURIComponent(url)}`);
                        const data = await res.json();
                        if (data.name) inputName.value = data.name;
                        if (data.description) inputDescription.value = data.description;
                        if (data.price) inputCostPrice.value = data.price;
                        if (data.image) {
                            radioSourceUrl.checked = true;
                            inputImageUrl.value = data.image;
                            imgPreviewThumb.src = data.image;
                            updateImageSourceInputs();
                        }
                        scrapeStatus.style.color = 'var(--admin-green)';
                        scrapeStatus.textContent = '✅ បានបំពេញព័ត៌មានដោយស្វ័យប្រវត្តិកំពុងរង់ចាំពិនិត្យ!';
                    } catch (err) {
                        scrapeStatus.style.color = '#dc2626';
                        scrapeStatus.textContent = '❌ មិនអាចទាញយកទិន្នន័យពី Link នេះបានទេ';
                    } finally {
                        btnScrape.disabled = false;
                        btnScrape.innerHTML = '<i class="fas fa-download"></i> ទាញយកទិន្នន័យ';
                    }
                });
            }
        })();
    </script>
    <script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js'); ?>"></script>
</body>

</html>
