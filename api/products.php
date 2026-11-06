<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

require_once __DIR__ . '/db.php';

if (!ensureProductStockColumn($conn) || !ensureProductDiscountColumns($conn)) {
    http_response_code(500);
    echo json_encode(['error' => 'Product inventory is temporarily unavailable.']);
    exit;
}

function resolveProductCategory(string $name): string {
    $n = strtolower($name);
    if (strpos($n, 'boot') !== false || strpos($n, 'belt') !== false || strpos($n, 'bag') !== false || strpos($n, 'shoe') !== false) {
        return 'accessories';
    }
    if (strpos($n, 'women') !== false || strpos($n, 'dress') !== false || strpos($n, 'skirt') !== false || strpos($n, 'blouse') !== false || strpos($n, 'camisole') !== false) {
        return 'women';
    }
    if (preg_match('/\bmen\b/i', $name) || strpos($n, 'men ') !== false || strpos($n, 't-shirt') !== false || strpos($n, 'chino') !== false) {
        return 'men';
    }
    if (strpos($n, 'capcut') !== false || strpos($n, 'gpt') !== false || strpos($n, 'pic') !== false || 
        strpos($n, 'sportify') !== false || strpos($n, 'spotify') !== false || strpos($n, 'tiktok') !== false || 
        strpos($n, 'motion') !== false) {
        return 'digital';
    }
    return 'fashion';
}

$idParam = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($idParam && $idParam > 0) {
    $stmt = $conn->prepare("SELECT id, name, category, description, price, original_price, discount_percent, cost_price, image, stock, low_stock_threshold FROM products WHERE id = ?");
    $stmt->bind_param("i", $idParam);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    if (empty($row['category'])) {
        $row['category'] = resolveProductCategory($row['name']);
    }
    echo json_encode($row);
    exit;
}

$sql = "SELECT id, name, category, description, price, original_price, discount_percent, cost_price, image, stock, low_stock_threshold, created_at FROM products WHERE stock > 0";
$categoryFilter = is_string($_GET['category'] ?? null) ? strtolower(trim($_GET['category'])) : '';
$searchFilter = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$sort = is_string($_GET['sort'] ?? null) ? strtolower(trim($_GET['sort'])) : 'featured';

switch ($sort) {
    case 'discount-desc':
        $sql .= " ORDER BY discount_percent DESC, id DESC";
        break;
    case 'price-asc':
        $sql .= " ORDER BY price ASC, id DESC";
        break;
    case 'price-desc':
        $sql .= " ORDER BY price DESC, id DESC";
        break;
    case 'name-asc':
        $sql .= " ORDER BY name ASC";
        break;
    case 'featured':
    default:
        $sql .= " ORDER BY id DESC";
        break;
}

$result = $conn->query($sql);
$products = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cat = !empty($row['category']) ? $row['category'] : resolveProductCategory($row['name']);
        if ($categoryFilter !== '' && $categoryFilter !== 'all' && $cat !== $categoryFilter) {
            continue;
        }
        if ($searchFilter !== '') {
            $searchLower = strtolower($searchFilter);
            if (strpos(strtolower($row['name']), $searchLower) === false && strpos(strtolower($row['description'] ?? ''), $searchLower) === false) {
                continue;
            }
        }
        $row['category'] = $cat;
        $products[] = $row;
    }
}

echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
