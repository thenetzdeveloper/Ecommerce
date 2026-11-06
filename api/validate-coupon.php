<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function couponResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    couponResponse(405, ['success' => false, 'message' => 'Method not allowed.']);
}

$codeInput = $_POST['coupon_code'] ?? '';
$code = is_string($codeInput) ? strtoupper(trim($codeInput)) : '';
if (preg_match('/^[A-Z0-9_-]{3,40}$/D', $code) !== 1) {
    couponResponse(422, ['success' => false, 'message' => 'សូមបញ្ចូលកូដបញ្ចុះតម្លៃឱ្យបានត្រឹមត្រូវ (Enter a valid coupon code).']);
}

require_once __DIR__ . '/db.php';
if (!ensureProductStockColumn($conn) || !ensureAdminOperationsSchema($conn) || !ensureStoreManagementSchema($conn)) {
    couponResponse(503, ['success' => false, 'message' => 'ប្រព័ន្ធប្រូម៉ូសិនមិនទាន់អាចប្រើបានទេ (Promotions temporarily unavailable).']);
}

$subtotal = 0.0;
$productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = max(1, filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT) ?: 1);

if ($productId && $productId > 0) {
    $productStatement = $conn->prepare('SELECT price FROM products WHERE id = ? AND stock > 0 LIMIT 1');
    if (!$productStatement) {
        error_log('Could not prepare product lookup for coupon validation: ' . $conn->error);
        couponResponse(503, ['success' => false, 'message' => 'Promotions are temporarily unavailable.']);
    }
    $productStatement->bind_param('i', $productId);
    if (!$productStatement->execute()) {
        error_log('Could not execute product lookup for coupon validation: ' . $productStatement->error);
        $productStatement->close();
        couponResponse(503, ['success' => false, 'message' => 'Promotions are temporarily unavailable.']);
    }
    $product = $productStatement->get_result()->fetch_assoc();
    $productStatement->close();
    if (!$product) {
        couponResponse(404, ['success' => false, 'message' => 'ផលិតផលនេះមិនមានក្នុងស្តុកទេ (Product is unavailable).']);
    }
    $subtotal = round((float) $product['price'] * $quantity, 2);
} elseif (isset($_POST['subtotal']) && is_numeric($_POST['subtotal']) && (float) $_POST['subtotal'] > 0) {
    $subtotal = round((float) $_POST['subtotal'], 2);
} else {
    couponResponse(422, ['success' => false, 'message' => 'សូមបញ្ជាក់ផលិតផល ឬចំនួនទឹកប្រាក់សរុប (Specify product or subtotal).']);
}

$coupon = findAvailableCoupon($conn, $code);
$discount = $coupon ? calculateCouponDiscount($subtotal, $coupon) : null;
if (!$coupon || $discount === null || $discount <= 0) {
    couponResponse(422, ['success' => false, 'message' => 'កូដបញ្ចុះតម្លៃមិនត្រឹមត្រូវ ផុតកំណត់ ឬមិនទាន់គ្រប់លក្ខខណ្ឌបញ្ចុះតម្លៃ (Coupon is invalid or expired).']);
}

couponResponse(200, [
    'success' => true,
    'coupon_code' => (string) $coupon['coupon_code'],
    'subtotal' => round($subtotal, 2),
    'discount' => round($discount, 2),
    'total' => max(0, round($subtotal - $discount, 2)),
]);
