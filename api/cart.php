<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/db.php';

if (!ensureProductStockColumn($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Product inventory is unavailable.']);
    exit;
}

$action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'validate' : 'list');

if ($action === 'validate' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST['items'] ?? [];
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
    }
    
    if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
        $data = $data['items'];
    }
    
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'message' => 'Invalid cart payload.']);
        exit;
    }

    $validatedItems = [];
    $subtotal = 0.0;
    $hasStockIssues = false;

    foreach ($data as $item) {
        $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $reqQty = max(1, filter_var($item['quantity'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
        if (!$id || $id < 1) continue;

        $stmt = $conn->prepare("SELECT id, name, price, stock, image FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $availableStock = (int)$row['stock'];
            $actualQty = min($reqQty, $availableStock);
            if ($actualQty < $reqQty || $availableStock <= 0) {
                $hasStockIssues = true;
            }

            if ($availableStock > 0) {
                $unitPrice = (float)$row['price'];
                $itemTotal = round($unitPrice * $actualQty, 2);
                $subtotal += $itemTotal;

                $validatedItems[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'price' => $unitPrice,
                    'quantity' => $actualQty,
                    'stock' => $availableStock,
                    'image' => (string)($row['image'] ?? ''),
                    'item_total' => $itemTotal
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'items' => $validatedItems,
        'subtotal' => round($subtotal, 2),
        'currency' => storeCurrencySymbol($conn),
        'has_stock_issues' => $hasStockIssues
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Cart endpoint active.']);
