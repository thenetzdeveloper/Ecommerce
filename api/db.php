<?php
mysqli_report(MYSQLI_REPORT_OFF);

if (!function_exists('applicationConfigValue')) {
    function applicationConfigValue(string $key): ?string
    {
        static $fileConfig = null;
        $environmentValue = getenv($key);
        if ($environmentValue !== false) {
            return (string) $environmentValue;
        }
        if (isset($_ENV[$key])) {
            return (string) $_ENV[$key];
        }

        if ($fileConfig === null) {
            $fileConfig = [];
            $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
            if (is_readable($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/', $line, $matches)) {
                        $value = trim($matches[2]);
                        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                            $value = substr($value, 1, -1);
                        }
                        $fileConfig[$matches[1]] = $value;
                    }
                }
            }
        }

        return array_key_exists($key, $fileConfig) ? $fileConfig[$key] : null;
    }
}

$appEnvironment = strtolower(trim(applicationConfigValue('APP_ENV') ?? 'local'));
$databaseHost = applicationConfigValue('ECOMMERCE_DB_HOST');
$databaseUser = applicationConfigValue('ECOMMERCE_DB_USER');
$databasePassword = applicationConfigValue('ECOMMERCE_DB_PASSWORD');
$databaseName = applicationConfigValue('ECOMMERCE_DB_NAME');
$databasePort = applicationConfigValue('ECOMMERCE_DB_PORT');

if ($appEnvironment === 'local') {
    $databaseHost = $databaseHost ?? 'localhost';
    $databaseUser = $databaseUser ?? 'root';
    $databasePassword = $databasePassword ?? '';
    $databaseName = $databaseName ?? 'ecommerce';
} elseif (
    !$databaseHost
    || !$databaseUser
    || !$databasePassword
    || !$databaseName
    || strtolower($databaseUser) === 'root'
) {
    error_log('Production database configuration is incomplete or uses the root account.');
    http_response_code(500);
    exit('Database service is not configured.');
}

$databasePortNumber = $databasePort === null || $databasePort === '' ? 3306 : filter_var($databasePort, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
if ($databasePortNumber === false) {
    error_log('Invalid database port configuration.');
    http_response_code(500);
    exit('Database service is not configured.');
}

$conn = new mysqli($databaseHost, $databaseUser, $databasePassword, $databaseName, (int) $databasePortNumber);
if ($conn->connect_errno) {
    error_log('Database connection failed (' . $conn->connect_errno . '): ' . $conn->connect_error);
    http_response_code(500);
    exit('Database service is temporarily unavailable.');
}
if (!$conn->set_charset('utf8mb4')) {
    error_log('Could not set the database connection charset: ' . $conn->error);
    http_response_code(500);
    exit('Database service is temporarily unavailable.');
}

if (!function_exists('applicationTableExists')) {
    function applicationTableExists(mysqli $connection, string $table): bool
    {
        $statement = $connection->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        if (!$statement) {
            error_log('Could not prepare database table check: ' . $connection->error);
            return false;
        }
        $statement->bind_param('s', $table);
        $exists = $statement->execute() && $statement->get_result()->num_rows > 0;
        if ($statement->errno !== 0) {
            error_log('Could not check database table: ' . $statement->error);
        }
        $statement->close();
        return $exists;
    }
}

if (!$conn->query("SET time_zone = '+07:00'")) {
    error_log('Could not set the application database time zone: ' . $conn->error);
    die('Could not initialize the application database time zone.');
}

if (!function_exists('ensureProductStockColumn')) {
    function ensureProductStockColumn(mysqli $connection): bool
    {
        $stockColumn = $connection->query("SHOW COLUMNS FROM products LIKE 'stock'");
        if (!$stockColumn) {
            error_log('Could not check product stock schema: ' . $connection->error);
            return false;
        }

        if ($stockColumn->num_rows === 0 && !$connection->query('ALTER TABLE products ADD stock INT UNSIGNED NOT NULL DEFAULT 0')) {
            error_log('Could not add product stock column: ' . $connection->error);
            return false;
        }

        return true;
    }
}

if (!function_exists('ensureProductDiscountColumns')) {
    function ensureProductDiscountColumns(mysqli $connection): bool
    {
        $origColumn = $connection->query("SHOW COLUMNS FROM products LIKE 'original_price'");
        if (!$origColumn) {
            error_log('Could not check product original_price schema: ' . $connection->error);
            return false;
        }

        if ($origColumn->num_rows === 0 && !$connection->query('ALTER TABLE products ADD original_price DECIMAL(10,2) NULL DEFAULT NULL AFTER price')) {
            error_log('Could not add product original_price column: ' . $connection->error);
            return false;
        }

        $discColumn = $connection->query("SHOW COLUMNS FROM products LIKE 'discount_percent'");
        if (!$discColumn) {
            error_log('Could not check product discount_percent schema: ' . $connection->error);
            return false;
        }

        if ($discColumn->num_rows === 0 && !$connection->query('ALTER TABLE products ADD discount_percent INT UNSIGNED NOT NULL DEFAULT 0 AFTER original_price')) {
            error_log('Could not add product discount_percent column: ' . $connection->error);
            return false;
        }

        return true;
    }
}

if (!function_exists('ensureSalesAnalyticsSchema')) {
    function ensureSalesAnalyticsSchema(mysqli $connection): bool
    {
        $costColumn = $connection->query("SHOW COLUMNS FROM products LIKE 'cost_price'");
        if (!$costColumn) {
            error_log('Could not check product cost schema: ' . $connection->error);
            return false;
        }

        if ($costColumn->num_rows === 0 && !$connection->query('ALTER TABLE products ADD cost_price DECIMAL(10,2) NULL DEFAULT NULL')) {
            error_log('Could not add product cost column: ' . $connection->error);
            return false;
        }

        $salesTable = "CREATE TABLE IF NOT EXISTS sales_orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id INT NULL,
            product_name VARCHAR(255) NOT NULL,
            customer_name VARCHAR(100) NOT NULL,
            phone_number VARCHAR(32) NOT NULL,
            location VARCHAR(300) NOT NULL,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL,
            unit_cost DECIMAL(10,2) NULL DEFAULT NULL,
            order_reference VARCHAR(40) NULL DEFAULT NULL,
            payment_method VARCHAR(20) NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sales_created_at (created_at),
            KEY idx_sales_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!applicationTableExists($connection, 'sales_orders') && !$connection->query($salesTable)) {
            error_log('Could not create sales history table: ' . $connection->error);
            return false;
        }

        $orderReferenceColumn = $connection->query("SHOW COLUMNS FROM sales_orders LIKE 'order_reference'");
        if (!$orderReferenceColumn) {
            error_log('Could not check sales order reference schema: ' . $connection->error);
            return false;
        }
        if ($orderReferenceColumn->num_rows === 0 && !$connection->query('ALTER TABLE sales_orders ADD order_reference VARCHAR(40) NULL DEFAULT NULL')) {
            error_log('Could not add sales order reference column: ' . $connection->error);
            return false;
        }

        $paymentMethodColumn = $connection->query("SHOW COLUMNS FROM sales_orders LIKE 'payment_method'");
        if (!$paymentMethodColumn) {
            error_log('Could not check sales payment method schema: ' . $connection->error);
            return false;
        }
        if ($paymentMethodColumn->num_rows === 0 && !$connection->query('ALTER TABLE sales_orders ADD payment_method VARCHAR(20) NULL DEFAULT NULL')) {
            error_log('Could not add sales payment method column: ' . $connection->error);
            return false;
        }

        $activityTable = "CREATE TABLE IF NOT EXISTS product_activity (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id INT NULL,
            product_name VARCHAR(255) NOT NULL,
            action VARCHAR(20) NOT NULL,
            actor VARCHAR(100) NOT NULL,
            old_values TEXT NULL,
            new_values TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_product_activity_created_at (created_at),
            KEY idx_product_activity_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!applicationTableExists($connection, 'product_activity') && !$connection->query($activityTable)) {
            error_log('Could not create product activity table: ' . $connection->error);
            return false;
        }

        return true;
    }
}

if (!function_exists('getStoreSettings')) {
    /**
     * Retrieve all key-value pairs from system_settings with in-memory caching.
     */
    function getStoreSettings(?mysqli $connection = null): array
    {
        static $cachedSettings = null;
        global $conn;

        if ($cachedSettings !== null && $connection === null) {
            return $cachedSettings;
        }

        $activeConn = $connection ?? $conn ?? null;
        $settings = [
            'store_name' => 'SN STORE',
            'currency_symbol' => '$',
            'store_logo_url' => '',
            'store_contact_url' => 'https://t.me/remsonich',
            'home_hero_title' => 'គុណភាពខ្ពស់ 100% ម៉ូដទាន់សម័យ',
            'home_hero_description' => 'ទិញ-លក់សម្លៀកបំពាក់ ម៉ូដ និងទំនិញពេញនិយម ប្រកបដោយសុវត្ថិភាព មានការធានាជូន និងគាំទ្រការទូទាត់គ្រប់ធនាគារក្នុងប្រទេសកម្ពុជា។',
            'home_banner_url' => '',
            'telegram_username' => '@remsonich',
            'support_phone' => '',
            'support_hours' => '',
            'store_location' => '',
            'google_client_id' => '',
        ];

        if ($activeConn instanceof mysqli && !$activeConn->connect_error) {
            $res = $activeConn->query("SELECT `setting_name`, `setting_value` FROM `system_settings`");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    if (isset($row['setting_name']) && $row['setting_value'] !== null) {
                        $settings[$row['setting_name']] = (string)$row['setting_value'];
                    }
                }
            }
        }

        if ($connection === null) {
            $cachedSettings = $settings;
        }

        return $settings;
    }
}

if (!function_exists('resolveStoreAssetUrl')) {
    /**
     * Resolve a store asset URL (logo, banner, image) relative to the calling script.
     * Works seamlessly whether called from project root, /home/, /admin/, or /customer-service/.
     */
    function resolveStoreAssetUrl(?string $path, ?string $context = null): string
    {
        $rootDir = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $scriptDir = $scriptPath ? realpath(dirname($scriptPath)) : getcwd();

        $prefix = './';
        if ($context !== null) {
            $normalizedContext = strtolower(trim($context, '/\\'));
            if ($normalizedContext === 'root' || $normalizedContext === '' || $normalizedContext === '.') {
                $prefix = './';
            } else {
                $prefix = '../';
            }
        } elseif ($scriptDir && $rootDir) {
            $normScript = str_replace('\\', '/', $scriptDir);
            $normRoot = str_replace('\\', '/', $rootDir);
            if ($normScript === $normRoot) {
                $prefix = './';
            } else {
                $rel = trim(str_replace($normRoot, '', $normScript), '/');
                $depth = ($rel === '' || $rel === false) ? 0 : count(explode('/', $rel));
                $prefix = $depth === 0 ? './' : str_repeat('../', $depth);
            }
        }

        if ($path === null || trim($path) === '') {
            return $prefix . 'home/logo/logome.png';
        }

        $trimmed = trim($path);
        if (preg_match('~^(https?://|data:|blob:)~i', $trimmed)) {
            return $trimmed;
        }

        // Clean relative prefix and attach detected prefix
        $cleanPath = ltrim(str_replace('\\', '/', $trimmed), './');
        return $prefix . $cleanPath;
    }
}

require_once __DIR__ . '/admin-operations.php';
?>
