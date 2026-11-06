<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function restoreReservedStockItems(mysqli $conn, array $reservedItems, string $orderReference): void
{
    foreach ($reservedItems as $item) {
        $pId = (int)$item['id'];
        $qty = (int)$item['qty'];
        $name = (string)$item['name'];
        $conn->query("UPDATE products SET stock = stock + {$qty} WHERE id = {$pId}");
        recordInventoryAdjustment(
            $conn,
            $pId,
            $name,
            $qty,
            0,
            0,
            'Reservation released after failed order ' . $orderReference,
            'System'
        );
    }
}

function readConfigValue(array $keys): ?string
{
    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && trim($value) !== '') {
            return trim($value);
        }
    }

    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($envFile)) {
        return null;
    }

    $config = [];
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/', $line, $matches)) {
            $value = trim($matches[2]);
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            $config[$matches[1]] = $value;
        }
    }

    foreach ($keys as $key) {
        if (isset($config[$key]) && $config[$key] !== '') {
            return $config[$key];
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'Method not allowed.']);
}

if (session_status() !== PHP_SESSION_ACTIVE && !@session_start()) {
    respond(500, ['success' => false, 'message' => 'មិនអាចចាប់ផ្ដើម session បានទេ។ សូមព្យាយាមម្តងទៀត។']);
}

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['order_csrf_token'] ?? '';
if (!is_string($submittedToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    respond(403, ['success' => false, 'message' => 'សំណើមិនត្រឹមត្រូវទេ។ សូមបិទហើយបើកផ្ទាំងទិញម្ដងទៀត (Invalid CSRF token).']);
}

$customerNameInput = $_POST['customer_name'] ?? '';
$phoneNumberInput = $_POST['phone_number'] ?? '';
$locationInput = $_POST['location'] ?? '';

$customerName = trim((string)$customerNameInput);
$phoneNumber = trim((string)$phoneNumberInput);
$location = trim((string)$locationInput);

require __DIR__ . '/db.php';
require_once __DIR__ . '/user-auth.php';
$loggedInUser = getCurrentUser($conn);
$loggedInUserId = $loggedInUser ? (int)$loggedInUser['id'] : null;
if (!isset($conn) || !$conn instanceof mysqli) {
    respond(500, ['success' => false, 'message' => 'មិនអាចភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យបានទេ។']);
}
if (!ensureProductStockColumn($conn) || !ensureSalesAnalyticsSchema($conn) || !ensureAdminOperationsSchema($conn) || !ensureStoreManagementSchema($conn)) {
    respond(500, ['success' => false, 'message' => 'ប្រព័ន្ធមិនទាន់រួចរាល់ទេ។ សូមទាក់ទង Admin។']);
}

$phoneNormalized = normalizeCustomerPhone($phoneNumber);
if (
    $customerName === ''
    || strlen($customerName) > 100
    || $phoneNumber === ''
    || strlen($phoneNumber) > 32
    || preg_match('/^[+\d\s().-]+$/D', $phoneNumber) !== 1
    || strlen($phoneNormalized) < 6
    || strlen($phoneNormalized) > 20
    || $location === ''
    || strlen($location) > 300
) {
    respond(422, ['success' => false, 'message' => 'សូមបំពេញឈ្មោះ លេខទូរស័ព្ទ និងទីតាំងឱ្យបានត្រឹមត្រូវ។']);
}

// Check if customer is blocked
$customerStatement = $conn->prepare('SELECT is_blocked FROM customer_profiles WHERE phone_normalized = ? LIMIT 1');
if ($customerStatement) {
    $customerStatement->bind_param('s', $phoneNormalized);
    if ($customerStatement->execute()) {
        $customerProfile = $customerStatement->get_result()->fetch_assoc();
        if ($customerProfile && (int) $customerProfile['is_blocked'] === 1) {
            $customerStatement->close();
            respond(403, ['success' => false, 'message' => 'លេខទូរស័ព្ទនេះមិនអាចធ្វើការបញ្ជាទិញបានទេ។ សូមទាក់ទងហាង។']);
        }
    }
    $customerStatement->close();
}

// Parse Order Items: Single item OR Cart items
$orderItems = [];
$cartItemsRaw = $_POST['cart_items'] ?? null;

if (!empty($cartItemsRaw)) {
    $decodedCart = is_string($cartItemsRaw) ? json_decode($cartItemsRaw, true) : $cartItemsRaw;
    if (is_array($decodedCart) && count($decodedCart) > 0) {
        foreach ($decodedCart as $cItem) {
            $cId = filter_var($cItem['id'] ?? null, FILTER_VALIDATE_INT);
            $cQty = max(1, filter_var($cItem['quantity'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
            if ($cId && $cId > 0) {
                $orderItems[] = ['id' => $cId, 'quantity' => $cQty];
            }
        }
    }
}

if (empty($orderItems)) {
    $singleItem = $_POST['item'] ?? '';
    $singleQty = max(1, filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
    if (is_numeric($singleItem) && (int)$singleItem > 0) {
        $orderItems[] = ['id' => (int)$singleItem, 'quantity' => $singleQty];
    }
}

if (empty($orderItems)) {
    respond(422, ['success' => false, 'message' => 'សូមជ្រើសរើសផលិតផលសម្រាប់បញ្ជាទិញ (No products selected).']);
}

// Validate Receipt File
$receipt = $_FILES['receipt'] ?? null;
if (!is_array($receipt) || !isset($receipt['error']) || !is_int($receipt['error']) || $receipt['error'] === UPLOAD_ERR_NO_FILE) {
    respond(422, ['success' => false, 'message' => 'សូមភ្ជាប់វិក្កយបត្រ ឬរូបថតអេក្រង់បង់ប្រាក់។']);
}
if (
    $receipt['error'] !== UPLOAD_ERR_OK
    || !isset($receipt['tmp_name'], $receipt['size'])
    || !is_string($receipt['tmp_name'])
    || !is_int($receipt['size'])
    || !is_uploaded_file($receipt['tmp_name'])
) {
    $message = $receipt['error'] === UPLOAD_ERR_INI_SIZE || $receipt['error'] === UPLOAD_ERR_FORM_SIZE
        ? 'ឯកសារធំពេក។ សូមជ្រើសរើសឯកសារមិនលើស 5 MB។'
        : 'មិនអាចទទួលយកឯកសារបានទេ។ សូមព្យាយាមម្ដងទៀត។';
    respond(422, ['success' => false, 'message' => $message]);
}
if ($receipt['size'] < 1 || $receipt['size'] > 5 * 1024 * 1024) {
    respond(422, ['success' => false, 'message' => 'ឯកសារធំពេក។ សូមជ្រើសរើសឯកសារមិនលើស 5 MB។']);
}

$fileInfo = new finfo(FILEINFO_MIME_TYPE);
$receiptMime = $fileInfo->file($receipt['tmp_name']);
$receiptTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/heic' => 'heic',
    'image/heif' => 'heif',
    'application/pdf' => 'pdf',
];
if (!is_string($receiptMime) || !isset($receiptTypes[$receiptMime])) {
    respond(422, ['success' => false, 'message' => 'ប្រភេទឯកសារមិនត្រឹមត្រូវ។ អនុញ្ញាតតែ JPG, PNG, WEBP, GIF, HEIC ឬ PDF។']);
}

$botToken = readConfigValue(['Telegram_bot_token', 'TELEGRAM_BOT_TOKEN']);
$chatId = readConfigValue(['Telegram_chat_id', 'TELEGRAM_CHAT_ID']);
if ($botToken === null || $chatId === null) {
    error_log('Telegram order notification is missing bot token or chat ID configuration.');
    respond(503, ['success' => false, 'message' => 'ការជូនដំណឹង Telegram មិនទាន់បានកំណត់ទេ។ សូមទាក់ទង Admin។']);
}

// Generate Order Reference
$orderReference = 'ORD-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

// Save Private Receipt Copy
$receiptDirectory = privateReceiptDirectory();
if (!is_dir($receiptDirectory) && !mkdir($receiptDirectory, 0750, true) && !is_dir($receiptDirectory)) {
    error_log('Could not create private receipt storage directory.');
    respond(500, ['success' => false, 'message' => 'មិនអាចរក្សាទុកវិក្កយបត្របានទេ។ សូមទាក់ទង Admin។']);
}
$receiptDirectoryReal = realpath($receiptDirectory);
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$publicDirectory = is_string($documentRoot) && $documentRoot !== '' ? realpath($documentRoot) : false;
if (
    $receiptDirectoryReal === false
    || ($publicDirectory !== false && (
        $receiptDirectoryReal === $publicDirectory
        || strpos($receiptDirectoryReal, $publicDirectory . DIRECTORY_SEPARATOR) === 0
    ))
) {
    error_log('Private receipt storage must resolve outside the public document root.');
    respond(500, ['success' => false, 'message' => 'ទីតាំងរក្សាទុកវិក្កយបត្រមិនមានសុវត្ថិភាពទេ។ សូមទាក់ទង Admin។']);
}
$receiptFilename = 'receipt_' . bin2hex(random_bytes(16)) . '.' . $receiptTypes[$receiptMime];
$receiptPath = $receiptDirectoryReal . DIRECTORY_SEPARATOR . $receiptFilename;
if (!copy($receipt['tmp_name'], $receiptPath)) {
    error_log('Could not store a private copy of the uploaded receipt.');
    respond(500, ['success' => false, 'message' => 'មិនអាចរក្សាទុកវិក្កយបត្របានទេ។ សូមព្យាយាមម្ដងទៀត។']);
}

// Begin Stock Reservation Transaction
if (!$conn->begin_transaction()) {
    unlink($receiptPath);
    respond(500, ['success' => false, 'message' => 'មានបញ្ហាក្នុងការកក់ស្តុកផលិតផល។']);
}

$reservedItems = [];
$subtotal = 0.0;
$itemsDetails = [];

foreach ($orderItems as $item) {
    $pId = (int)$item['id'];
    $reqQty = (int)$item['quantity'];

    $stockStmt = $conn->prepare('SELECT id, name, price, cost_price, stock, low_stock_threshold FROM products WHERE id = ? FOR UPDATE');
    if (!$stockStmt) {
        $conn->rollback();
        unlink($receiptPath);
        respond(500, ['success' => false, 'message' => 'មានបញ្ហាក្នុងការពិនិត្យស្តុក។']);
    }
    $stockStmt->bind_param('i', $pId);
    $stockStmt->execute();
    $pData = $stockStmt->get_result()->fetch_assoc();
    $stockStmt->close();

    if (!$pData || (int)$pData['stock'] < $reqQty) {
        $conn->rollback();
        unlink($receiptPath);
        $pName = $pData ? $pData['name'] : "ID #$pId";
        respond(409, ['success' => false, 'message' => "ផលិតផល \"{$pName}\" មិនមានស្តុកគ្រប់គ្រាន់ទេ (នៅសល់តែ " . ($pData['stock'] ?? 0) . " គ្រឿង)។"]);
    }

    $stockBefore = (int)$pData['stock'];
    $stockAfter = $stockBefore - $reqQty;

    $updateStmt = $conn->prepare('UPDATE products SET stock = ? WHERE id = ?');
    $updateStmt->bind_param('ii', $stockAfter, $pId);
    $updateStmt->execute();
    $updateStmt->close();

    recordInventoryAdjustment(
        $conn,
        $pId,
        (string)$pData['name'],
        -$reqQty,
        $stockBefore,
        $stockAfter,
        "Reserved for order {$orderReference} (x{$reqQty})",
        'Customer checkout'
    );

    $unitPrice = (float)$pData['price'];
    $itemSubtotal = round($unitPrice * $reqQty, 2);
    $subtotal += $itemSubtotal;

    $reservedItems[] = [
        'id' => $pId,
        'qty' => $reqQty,
        'name' => (string)$pData['name'],
        'unit_price' => $unitPrice,
        'unit_cost' => $pData['cost_price'] !== null ? (float)$pData['cost_price'] : null,
        'low_threshold' => (int)$pData['low_stock_threshold'],
        'stock_before' => $stockBefore,
        'stock_after' => $stockAfter,
    ];

    $itemsDetails[] = "- {$pData['name']} x {$reqQty} = $" . number_format($itemSubtotal, 2);
}

// Process Coupon
$couponCodeInput = $_POST['coupon_code'] ?? '';
$couponCode = is_string($couponCodeInput) ? strtoupper(trim($couponCodeInput)) : '';
$coupon = null;
$discountAmount = 0.0;
$couponId = null;

if ($couponCode !== '') {
    $coupon = findAvailableCoupon($conn, $couponCode);
    $discountAmount = $coupon ? calculateCouponDiscount($subtotal, $coupon) : null;
    if (!$coupon || $discountAmount === null || $discountAmount <= 0) {
        $conn->rollback();
        unlink($receiptPath);
        respond(422, ['success' => false, 'message' => 'កូដបញ្ចុះតម្លៃមិនត្រឹមត្រូវ ឬផុតកំណត់ហើយ (Invalid or expired coupon).']);
    }
    $couponId = (int)$coupon['id'];
    $conn->query("UPDATE discount_coupons SET uses_count = uses_count + 1 WHERE id = {$couponId}");
}

$currencySymbol = storeCurrencySymbol($conn);
$subtotal = round($subtotal, 2);
$discountAmount = round((float)$discountAmount, 2);
$orderTotal = max(0, round($subtotal - $discountAmount, 2));

// Prepare Telegram Message
$couponLine = $coupon ? "\n🔖 កូដបញ្ចុះតម្លៃ: {$couponCode} (-{$currencySymbol}" . number_format($discountAmount, 2) . ")" : '';
$itemsListFormatted = implode("\n", $itemsDetails);

$tgMessage = "🛍️ **ការបញ្ជាទិញថ្មី (NEW ORDER)**\n"
    . "🧾 លេខវិក្កយបត្រ: `{$orderReference}`\n"
    . "-------------------------\n"
    . "📦 **ទំនិញដែលបានបញ្ជាទិញ:**\n"
    . "{$itemsListFormatted}\n"
    . "-------------------------\n"
    . "💵 សរុបបឋម: {$currencySymbol}" . number_format($subtotal, 2)
    . $couponLine . "\n"
    . "💰 **ទឹកប្រាក់ត្រូវបង់សរុប: {$currencySymbol}" . number_format($orderTotal, 2) . "**\n"
    . "-------------------------\n"
    . "👤 **ព័ត៌មានអតិថិជន:**\n"
    . "• ឈ្មោះ: {$customerName}\n"
    . "• លេខទូរស័ព្ទ: {$phoneNumber}\n"
    . "• ទីតាំងដឹកជញ្ជូន: {$location}\n"
    . "• កាលបរិច្ឆេទ: " . date('Y-m-d H:i:s');

// Send to Telegram
$originalName = basename((string) ($receipt['name'] ?? 'payment-receipt'));
$safeBaseName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
$safeBaseName = is_string($safeBaseName) && $safeBaseName !== '' ? substr($safeBaseName, 0, 100) : 'payment-receipt';
$safeFileName = $safeBaseName . '.' . $receiptTypes[$receiptMime];

$curl = curl_init('https://api.telegram.org/bot' . $botToken . '/sendDocument');
if ($curl === false) {
    $conn->rollback();
    unlink($receiptPath);
    respond(500, ['success' => false, 'message' => 'មិនអាចចាប់ផ្តើមការបញ្ជូន Telegram បានទេ។']);
}

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        'chat_id' => $chatId,
        'document' => new CURLFile($receipt['tmp_name'], $receiptMime, $safeFileName),
        'caption' => $tgMessage,
        'parse_mode' => 'Markdown',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$responseBody = curl_exec($curl);
$curlError = curl_error($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);

if ($responseBody === false) {
    $conn->rollback();
    unlink($receiptPath);
    error_log('Telegram request failed: ' . $curlError);
    respond(502, ['success' => false, 'message' => 'មិនអាចភ្ជាប់ទៅកាន់ Telegram បានទេ។ សូមព្យាយាមម្តងទៀត។']);
}

$telegramResponse = json_decode($responseBody, true);
if ($httpStatus < 200 || $httpStatus >= 300 || !is_array($telegramResponse) || ($telegramResponse['ok'] ?? false) !== true) {
    $conn->rollback();
    unlink($receiptPath);
    error_log('Telegram rejected order notification: ' . $responseBody);
    respond(502, ['success' => false, 'message' => 'Telegram មិនអាចទទួលព័ត៌មានបានទេ។ សូមព្យាយាមម្តងទៀត។']);
}

// Insert Sales Orders Records
$orderIds = [];
$saleInsert = $conn->prepare("INSERT INTO sales_orders (user_id, product_id, product_name, customer_name, phone_number, phone_normalized, location, quantity, unit_price, unit_cost, order_reference, payment_method, status, payment_status, receipt_filename, receipt_mime, inventory_reserved, coupon_id, coupon_code, discount_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'online', 'Pending', 'pending', ?, ?, 1, ?, ?, ?)");

foreach ($reservedItems as $item) {
    $pId = $item['id'];
    $pName = $item['name'];
    $qty = $item['qty'];
    $uPrice = $item['unit_price'];
    $uCost = $item['unit_cost'];
    $saleInsert->bind_param('iisssssiddsssisd', $loggedInUserId, $pId, $pName, $customerName, $phoneNumber, $phoneNormalized, $location, $qty, $uPrice, $uCost, $orderReference, $receiptFilename, $receiptMime, $couponId, $couponCode, $discountAmount);
    $saleInsert->execute();
    $orderIds[] = (int)$conn->insert_id;
}
$saleInsert->close();

// Update customer profile
$profileStatement = $conn->prepare('INSERT INTO customer_profiles (phone_normalized, phone_number, customer_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE phone_number = VALUES(phone_number), customer_name = VALUES(customer_name)');
if ($profileStatement) {
    $profileStatement->bind_param('sss', $phoneNormalized, $phoneNumber, $customerName);
    $profileStatement->execute();
    $profileStatement->close();
}

// Record order history
foreach ($orderIds as $oId) {
    recordSalesOrderHistory(
        $conn,
        $oId,
        'order_created',
        null,
        'Pending',
        'Receipt uploaded via online storefront; awaiting verification.',
        null,
        null,
        'Customer checkout'
    );
}

// Commit transaction
if (!$conn->commit()) {
    $conn->rollback();
    unlink($receiptPath);
    respond(500, ['success' => false, 'message' => 'ការបញ្ជាទិញត្រូវបានផ្ញើ ប៉ុន្តែមិនអាចបញ្ជាក់ការរក្សាទុកបានទេ។']);
}

// Record Admin Notifications
$firstSummary = count($reservedItems) === 1
    ? ($reservedItems[0]['name'] . ' (x' . $reservedItems[0]['qty'] . ')')
    : (count($reservedItems) . ' items in cart');

createAdminNotification(
    $conn,
    'new_order',
    'New order ' . $orderReference,
    $firstSummary . ' · ' . $customerName . ' · ' . $currencySymbol . number_format($orderTotal, 2),
    'operations.php?view=orders&search=' . urlencode($orderReference)
);

foreach ($reservedItems as $item) {
    if ($item['stock_before'] > $item['low_threshold'] && $item['stock_after'] <= $item['low_threshold']) {
        createAdminNotification(
            $conn,
            'low_stock',
            'Low stock alert: ' . $item['name'],
            'Remaining stock: ' . $item['stock_after'] . ' unit(s).',
            'operations.php?view=inventory'
        );
    }
}

unset($_SESSION['order_csrf_token']);

respond(200, [
    'success' => true,
    'order_reference' => $orderReference,
    'total' => $orderTotal,
    'currency' => $currencySymbol,
    'message' => 'ការបញ្ជាទិញ ' . $orderReference . ' ត្រូវបានបញ្ជូនទៅកាន់ Telegram Admin រួចរាល់ដោយជោគជ័យ!'
]);
