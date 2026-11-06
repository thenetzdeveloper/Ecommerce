<?php
declare(strict_types=1);

if (!function_exists('ensureAdminOperationsSchema')) {
    function ensureAdminOperationsSchema(mysqli $connection): bool
    {
        $columns = [
            'products' => [
                'low_stock_threshold' => 'INT UNSIGNED NOT NULL DEFAULT 5',
            ],
            'sales_orders' => [
                'status' => "VARCHAR(20) NOT NULL DEFAULT 'Pending'",
                'payment_status' => "VARCHAR(20) NOT NULL DEFAULT 'pending'",
                'payment_transaction_id' => 'VARCHAR(120) NULL DEFAULT NULL',
                'paid_amount' => 'DECIMAL(10,2) NULL DEFAULT NULL',
                'receipt_filename' => 'VARCHAR(100) NULL DEFAULT NULL',
                'receipt_mime' => 'VARCHAR(100) NULL DEFAULT NULL',
                'inventory_reserved' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'phone_normalized' => 'VARCHAR(32) NULL DEFAULT NULL',
                'coupon_id' => 'BIGINT UNSIGNED NULL DEFAULT NULL',
                'coupon_code' => 'VARCHAR(40) NULL DEFAULT NULL',
                'discount_amount' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
            ],
        ];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column => $definition) {
                $columnResult = $connection->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
                if (!$columnResult) {
                    error_log("Could not check $table.$column schema: " . $connection->error);
                    return false;
                }
                if ($columnResult->num_rows === 0 && !$connection->query("ALTER TABLE `$table` ADD `$column` $definition")) {
                    error_log("Could not add $table.$column schema: " . $connection->error);
                    return false;
                }
            }
        }

        $tables = [
            'customer_profiles' => "CREATE TABLE customer_profiles (
                phone_normalized VARCHAR(32) NOT NULL,
                phone_number VARCHAR(32) NOT NULL,
                customer_name VARCHAR(100) NOT NULL,
                notes TEXT NULL,
                is_blocked TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (phone_normalized),
                KEY idx_customer_name (customer_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'sales_order_history' => "CREATE TABLE sales_order_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(32) NOT NULL,
                old_value VARCHAR(120) NULL DEFAULT NULL,
                new_value VARCHAR(120) NULL DEFAULT NULL,
                note VARCHAR(500) NULL DEFAULT NULL,
                transaction_id VARCHAR(120) NULL DEFAULT NULL,
                amount DECIMAL(10,2) NULL DEFAULT NULL,
                actor VARCHAR(100) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_order_history_order_id (order_id),
                KEY idx_order_history_type (event_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'inventory_adjustments' => "CREATE TABLE inventory_adjustments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id INT NULL DEFAULT NULL,
                product_name VARCHAR(255) NOT NULL,
                change_amount INT NOT NULL,
                stock_before INT UNSIGNED NOT NULL,
                stock_after INT UNSIGNED NOT NULL,
                reason VARCHAR(255) NOT NULL,
                actor VARCHAR(100) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_inventory_adjustments_product (product_id),
                KEY idx_inventory_adjustments_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tables as $table => $sql) {
            if (!applicationTableExists($connection, $table) && !$connection->query($sql)) {
                error_log('Could not create an admin operations table: ' . $connection->error);
                return false;
            }
        }

        $phoneIndex = $connection->query("SHOW INDEX FROM sales_orders WHERE Key_name = 'idx_sales_phone_normalized'");
        if (!$phoneIndex) {
            error_log('Could not check customer phone index: ' . $connection->error);
            return false;
        }
        if ($phoneIndex->num_rows === 0 && !$connection->query('ALTER TABLE sales_orders ADD INDEX idx_sales_phone_normalized (phone_normalized)')) {
            error_log('Could not add customer phone index: ' . $connection->error);
            return false;
        }

        $statusIndex = $connection->query("SHOW INDEX FROM sales_orders WHERE Key_name = 'idx_sales_status_created'");
        if (!$statusIndex) {
            error_log('Could not check order status index: ' . $connection->error);
            return false;
        }
        if ($statusIndex->num_rows === 0 && !$connection->query('ALTER TABLE sales_orders ADD INDEX idx_sales_status_created (status, created_at)')) {
            error_log('Could not add order status index: ' . $connection->error);
            return false;
        }

        $legacyOrders = $connection->query('SELECT COUNT(*) AS remaining FROM sales_orders WHERE phone_normalized IS NULL');
        if (!$legacyOrders) {
            error_log('Could not check legacy customer orders: ' . $connection->error);
            return false;
        }
        $legacyCount = (int) $legacyOrders->fetch_assoc()['remaining'];
        $legacyOrders->free();
        if ($legacyCount > 0) {
            if (!$connection->query("UPDATE sales_orders SET phone_normalized = REGEXP_REPLACE(phone_number, '[^0-9]', '') WHERE phone_normalized IS NULL")) {
                error_log('Could not normalize legacy customer phone numbers: ' . $connection->error);
                return false;
            }
            if (!$connection->query("INSERT IGNORE INTO customer_profiles (phone_normalized, phone_number, customer_name)
                SELECT phone_normalized, MAX(phone_number), MAX(customer_name)
                FROM sales_orders
                WHERE phone_normalized IS NOT NULL AND phone_normalized <> ''
                GROUP BY phone_normalized")) {
                error_log('Could not migrate legacy customer profiles: ' . $connection->error);
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('ensureAdminSecuritySchema')) {
    function ensureAdminSecuritySchema(mysqli $connection): bool
    {
        $columns = [
            'email' => 'VARCHAR(100) NULL DEFAULT NULL',
            'role' => "VARCHAR(20) NOT NULL DEFAULT 'admin'",
            'permissions' => 'TEXT NULL',
            'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
        ];

        foreach ($columns as $column => $definition) {
            $columnResult = $connection->query("SHOW COLUMNS FROM admins LIKE '$column'");
            if (!$columnResult) {
                error_log('Could not check admin security schema: ' . $connection->error);
                return false;
            }
            if ($columnResult->num_rows === 0 && !$connection->query("ALTER TABLE admins ADD `$column` $definition")) {
                error_log('Could not add admin security column: ' . $connection->error);
                return false;
            }
        }

        $tables = [
            'admin_login_attempts' => "CREATE TABLE admin_login_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                identity_hash CHAR(64) NOT NULL,
                attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_admin_login_ip_time (ip_address, attempted_at),
                KEY idx_admin_login_identity_time (identity_hash, attempted_at),
                KEY idx_admin_login_attempted (attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'admin_sessions' => "CREATE TABLE admin_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                admin_id INT NOT NULL,
                session_hash CHAR(64) NOT NULL,
                ip_address VARCHAR(45) NULL DEFAULT NULL,
                user_agent VARCHAR(255) NOT NULL DEFAULT '',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_admin_session_hash (session_hash),
                KEY idx_admin_session_admin (admin_id),
                KEY idx_admin_session_expiry (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'admin_audit_log' => "CREATE TABLE admin_audit_log (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_id INT NULL DEFAULT NULL,
                actor_username VARCHAR(100) NOT NULL,
                action VARCHAR(50) NOT NULL,
                target_admin_id INT NULL DEFAULT NULL,
                old_values TEXT NULL,
                new_values TEXT NULL,
                ip_address VARCHAR(45) NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_admin_audit_created (created_at),
                KEY idx_admin_audit_target (target_admin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tables as $table => $sql) {
            if (!applicationTableExists($connection, $table) && !$connection->query($sql)) {
                error_log('Could not create an admin security table: ' . $connection->error);
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('normalizeCustomerPhone')) {
    function normalizeCustomerPhone(string $phoneNumber): string
    {
        return preg_replace('/\D+/', '', $phoneNumber) ?? '';
    }
}

if (!function_exists('privateReceiptDirectory')) {
    function privateReceiptDirectory(): string
    {
        $configuredDirectory = getenv('ECOMMERCE_PRIVATE_RECEIPT_DIR');
        if ($configuredDirectory !== false && trim($configuredDirectory) !== '') {
            return trim($configuredDirectory);
        }

        $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_readable($envFile)) {
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    if (preg_match('/^\s*ECOMMERCE_PRIVATE_RECEIPT_DIR\s*=\s*(.*?)\s*$/', $line, $m)) {
                        $val = trim($m[1], " \t\n\r\0\x0B\"'");
                        if ($val !== '') {
                            return $val;
                        }
                    }
                }
            }
        }

        if (is_dir('C:\\xampp\\tmp\\ecommerce-private-receipts')) {
            return 'C:\\xampp\\tmp\\ecommerce-private-receipts';
        }

        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $publicDirectory = is_string($documentRoot) && $documentRoot !== '' ? realpath($documentRoot) : false;
        $parentDirectory = $publicDirectory !== false ? dirname($publicDirectory) : sys_get_temp_dir();
        return $parentDirectory . DIRECTORY_SEPARATOR . 'ecommerce-private-receipts';
    }
}

if (!function_exists('recordSalesOrderHistory')) {
    function recordSalesOrderHistory(
        mysqli $connection,
        int $orderId,
        string $eventType,
        ?string $oldValue,
        ?string $newValue,
        ?string $note,
        ?string $transactionId,
        ?float $amount,
        string $actor
    ): bool {
        $statement = $connection->prepare('INSERT INTO sales_order_history (order_id, event_type, old_value, new_value, note, transaction_id, amount, actor) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$statement) {
            error_log('Could not prepare order history entry: ' . $connection->error);
            return false;
        }

        $statement->bind_param('isssssds', $orderId, $eventType, $oldValue, $newValue, $note, $transactionId, $amount, $actor);
        $saved = $statement->execute();
        if (!$saved) {
            error_log('Could not save order history entry: ' . $statement->error);
        }
        $statement->close();
        return $saved;
    }
}

if (!function_exists('recordInventoryAdjustment')) {
    function recordInventoryAdjustment(
        mysqli $connection,
        ?int $productId,
        string $productName,
        int $changeAmount,
        int $stockBefore,
        int $stockAfter,
        string $reason,
        string $actor
    ): bool {
        $statement = $connection->prepare('INSERT INTO inventory_adjustments (product_id, product_name, change_amount, stock_before, stock_after, reason, actor) VALUES (?, ?, ?, ?, ?, ?, ?)');
        if (!$statement) {
            error_log('Could not prepare inventory adjustment: ' . $connection->error);
            return false;
        }

        $statement->bind_param('isiiiss', $productId, $productName, $changeAmount, $stockBefore, $stockAfter, $reason, $actor);
        $saved = $statement->execute();
        if (!$saved) {
            error_log('Could not save inventory adjustment: ' . $statement->error);
        }
        $statement->close();
        return $saved;
    }
}

if (!function_exists('ensureStoreManagementSchema')) {
    function ensureStoreManagementSchema(mysqli $connection): bool
    {
        $tables = [
            'discount_coupons' => "CREATE TABLE discount_coupons (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                coupon_code VARCHAR(40) NOT NULL,
                discount_type VARCHAR(12) NOT NULL,
                discount_value DECIMAL(10,2) NOT NULL,
                minimum_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                max_uses INT UNSIGNED NULL DEFAULT NULL,
                uses_count INT UNSIGNED NOT NULL DEFAULT 0,
                starts_at DATETIME NULL DEFAULT NULL,
                expires_at DATETIME NULL DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by INT NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_discount_coupon_code (coupon_code),
                KEY idx_discount_coupon_active_expiry (is_active, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'admin_notifications' => "CREATE TABLE admin_notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                notification_type VARCHAR(32) NOT NULL,
                title VARCHAR(160) NOT NULL,
                message VARCHAR(500) NOT NULL,
                target_url VARCHAR(255) NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_admin_notifications_unread (read_at, created_at),
                KEY idx_admin_notifications_type (notification_type, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tables as $table => $sql) {
            if (!applicationTableExists($connection, $table) && !$connection->query($sql)) {
                error_log("Could not create store-management table $table: " . $connection->error);
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('createAdminNotification')) {
    function createAdminNotification(mysqli $connection, string $type, string $title, string $message, ?string $targetUrl = null): bool
    {
        $statement = $connection->prepare('INSERT INTO admin_notifications (notification_type, title, message, target_url) VALUES (?, ?, ?, ?)');
        if (!$statement) {
            error_log('Could not prepare admin notification: ' . $connection->error);
            return false;
        }
        $statement->bind_param('ssss', $type, $title, $message, $targetUrl);
        $saved = $statement->execute();
        if (!$saved) {
            error_log('Could not save admin notification: ' . $statement->error);
        }
        $statement->close();
        return $saved;
    }
}

if (!function_exists('privateDatabaseBackupDirectory')) {
    function privateDatabaseBackupDirectory(): string
    {
        $configuredDirectory = getenv('ECOMMERCE_BACKUP_DIR');
        if ($configuredDirectory !== false && trim($configuredDirectory) !== '') {
            return trim($configuredDirectory);
        }

        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $publicDirectory = is_string($documentRoot) && $documentRoot !== '' ? realpath($documentRoot) : false;
        $backupRoot = $publicDirectory !== false ? dirname($publicDirectory) : sys_get_temp_dir();
        return $backupRoot . DIRECTORY_SEPARATOR . 'ecommerce-private-backups';
    }
}

if (!function_exists('findAvailableCoupon')) {
    function findAvailableCoupon(mysqli $connection, string $code): ?array
    {
        $statement = $connection->prepare("SELECT id, coupon_code, discount_type, discount_value, minimum_subtotal, max_uses, uses_count
            FROM discount_coupons
            WHERE coupon_code = ? AND is_active = 1
                AND (starts_at IS NULL OR starts_at <= NOW())
                AND (expires_at IS NULL OR expires_at > NOW())
                AND (max_uses IS NULL OR uses_count < max_uses)
            LIMIT 1");
        if (!$statement) {
            error_log('Could not prepare coupon lookup: ' . $connection->error);
            return null;
        }
        $statement->bind_param('s', $code);
        if (!$statement->execute()) {
            error_log('Could not execute coupon lookup: ' . $statement->error);
            $statement->close();
            return null;
        }
        $coupon = $statement->get_result()->fetch_assoc();
        $statement->close();
        return $coupon ?: null;
    }
}

if (!function_exists('calculateCouponDiscount')) {
    function calculateCouponDiscount(float $subtotal, array $coupon): ?float
    {
        if ($subtotal < (float) $coupon['minimum_subtotal']) {
            return null;
        }
        if ($coupon['discount_type'] === 'percent') {
            return min($subtotal, round($subtotal * (float) $coupon['discount_value'] / 100, 2));
        }
        if ($coupon['discount_type'] === 'fixed') {
            return min($subtotal, round((float) $coupon['discount_value'], 2));
        }
        return null;
    }
}

if (!function_exists('storeCurrencySymbol')) {
    function storeCurrencySymbol(mysqli $connection): string
    {
        $statement = $connection->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'currency_symbol' LIMIT 1");
        if (!$statement || !$statement->execute()) {
            if ($statement) {
                $statement->close();
            }
            return '$';
        }
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();
        $symbol = is_string($row['setting_value'] ?? null) ? trim($row['setting_value']) : '';
        return $symbol !== '' ? $symbol : '$';
    }
}
