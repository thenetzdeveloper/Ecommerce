<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/admin-auth.php';
require_once __DIR__ . '/primary-navigation.php';

if (!ensureAdminSecuritySchema($conn) || !ensureStoreManagementSchema($conn) || !adminStartSession()) {
    http_response_code(500);
    exit('Could not prepare database backup. Check the server error log.');
}
$currentAdmin = adminRequireLogin($conn);
adminRequirePermission($currentAdmin, 'backup');
$csrfToken = adminCsrfToken();
$backupDirectory = privateDatabaseBackupDirectory();
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
$resolvedParent = realpath(dirname($backupDirectory));
if (
    $documentRoot !== false
    && $resolvedParent !== false
    && ($resolvedParent === $documentRoot || str_starts_with($resolvedParent . DIRECTORY_SEPARATOR, $documentRoot . DIRECTORY_SEPARATOR))
) {
    error_log('Database backup directory must be outside the document root.');
    http_response_code(500);
    exit('Backup storage is not configured privately.');
}
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    error_log('Could not create private database backup directory.');
    http_response_code(500);
    exit('Backup storage is temporarily unavailable.');
}
$backupDirectoryReal = realpath($backupDirectory);
if ($backupDirectoryReal === false || ($documentRoot !== false && ($backupDirectoryReal === $documentRoot || str_starts_with($backupDirectoryReal . DIRECTORY_SEPARATOR, $documentRoot . DIRECTORY_SEPARATOR)))) {
    http_response_code(500);
    exit('Backup storage is not configured privately.');
}
$backupKeyValue = trim(applicationConfigValue('ECOMMERCE_BACKUP_KEY') ?? '');
if (preg_match('/^[a-f0-9]{64}$/iD', $backupKeyValue) === 1) {
    $backupKey = hex2bin($backupKeyValue);
} else {
    $backupKey = base64_decode($backupKeyValue, true);
}
if (!is_string($backupKey) || strlen($backupKey) !== 32) {
    $backupKey = null;
}
if ($backupKey === null && strtolower(trim(applicationConfigValue('APP_ENV') ?? 'local')) === 'local') {
    $keyPath = $backupDirectoryReal . DIRECTORY_SEPARATOR . '.backup-key';
    $keyHandle = @fopen($keyPath, 'x');
    if ($keyHandle !== false) {
        $generatedKey = random_bytes(32);
        $keyWritten = fwrite($keyHandle, $generatedKey) === 32;
        fflush($keyHandle);
        fclose($keyHandle);
        chmod($keyPath, 0600);
        if ($keyWritten) {
            $backupKey = $generatedKey;
        } else {
            unlink($keyPath);
        }
    }
    if ($backupKey === null && is_file($keyPath)) {
        $storedKey = file_get_contents($keyPath);
        if (is_string($storedKey) && strlen($storedKey) === 32) {
            $backupKey = $storedKey;
        }
    }
}

function backupEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function backupRedirect(string $message): void
{
    $_SESSION['backup_flash'] = $message;
    header('Location: backup.php');
    exit;
}

function backupTableNames(mysqli $connection): array
{
    $tables = [];
    $result = $connection->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
    if (!$result) {
        return [];
    }
    while ($row = $result->fetch_row()) {
        $table = (string) $row[0];
        if (preg_match('/^[A-Za-z0-9_]+$/D', $table) === 1) {
            $tables[] = $table;
        }
    }
    return $tables;
}

function backupFilePath(string $directory, string $filename): ?string
{
    if (preg_match('/^ecommerce_backup_[0-9]{8}_[0-9]{6}_[a-f0-9]{8}\\.enc$/D', $filename) !== 1) {
        return null;
    }
    $path = realpath($directory . DIRECTORY_SEPARATOR . $filename);
    if ($path === false || dirname($path) !== realpath($directory) || !is_file($path)) {
        return null;
    }
    return $path;
}

$flashMessage = is_string($_SESSION['backup_flash'] ?? null) ? $_SESSION['backup_flash'] : '';
unset($_SESSION['backup_flash']);
$errorMessage = '';

if (isset($_GET['download']) && is_string($_GET['download'])) {
    $path = backupFilePath($backupDirectoryReal, $_GET['download']);
    if ($path === null) {
        http_response_code(404);
        exit('Backup not found.');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adminCsrfValid()) {
        http_response_code(403);
        exit('The form expired. Reload the page and try again.');
    }
    $operation = is_string($_POST['operation'] ?? null) ? $_POST['operation'] : '';
    if ($operation === 'create_backup') {
        if ($backupKey === null) {
            $errorMessage = 'Set ECOMMERCE_BACKUP_KEY to a 32-byte key (64 hex characters or base64) before creating/restoring backups.';
        }
        $tableNames = backupTableNames($conn);
        if ($errorMessage !== '') {
        } elseif (!$tableNames) {
            $errorMessage = 'Could not list database tables for backup.';
        } else {
            $tableData = [];
            $backupFailed = false;
            foreach ($tableNames as $tableName) {
                $result = $conn->query('SELECT * FROM `' . $conn->real_escape_string($tableName) . '`');
                if (!$result) {
                    error_log('Could not read a table while creating database backup: ' . $tableName);
                    $backupFailed = true;
                    break;
                }
                $tableData[$tableName] = [];
                while ($row = $result->fetch_assoc()) {
                    $tableData[$tableName][] = $row;
                }
                $result->free();
            }
            $databaseResult = $conn->query('SELECT DATABASE() AS database_name');
            $databaseName = $databaseResult ? (string) $databaseResult->fetch_assoc()['database_name'] : '';
            if (!$backupFailed && $databaseName !== '') {
                try {
                    $payload = ['format' => 'ecommerce-db-backup-v1', 'database' => $databaseName, 'created_at' => date(DATE_ATOM), 'tables' => $tableData];
                    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    $backup = $payload + ['checksum' => hash('sha256', $payloadJson)];
                    $backupJson = json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    $nonce = random_bytes(12);
                    $tag = '';
                    $ciphertext = openssl_encrypt($backupJson, 'aes-256-gcm', $backupKey, OPENSSL_RAW_DATA, $nonce, $tag, 'ecommerce-db-backup-v1');
                    if ($ciphertext === false) {
                        $errorMessage = 'Could not encrypt the database backup.';
                    } elseif (strlen($ciphertext) + 35 > 100 * 1024 * 1024) {
                        $errorMessage = 'Backup exceeds the 100 MB size limit.';
                    } else {
                        $encryptedBackup = 'ECOMDB1' . $nonce . $tag . $ciphertext;
                        $filename = 'ecommerce_backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.enc';
                        $path = $backupDirectoryReal . DIRECTORY_SEPARATOR . $filename;
                        if (file_put_contents($path, $encryptedBackup, LOCK_EX) === false) {
                            error_log('Could not write private database backup.');
                            $errorMessage = 'Could not write the backup file.';
                        } elseif (!adminWriteAudit($conn, 'database_backup_created', null, null, ['filename' => $filename, 'bytes' => strlen($encryptedBackup)])) {
                            unlink($path);
                            $errorMessage = 'Could not write the backup audit record.';
                        } else {
                            backupRedirect('Database backup created.');
                        }
                    }
                } catch (JsonException $exception) {
                    error_log('Could not serialize database backup: ' . $exception->getMessage());
                    $errorMessage = 'Database contains data that cannot be safely exported to JSON.';
                }
            } elseif ($errorMessage === '') {
                $errorMessage = 'Could not create the backup. Check the server log.';
            }
        }
    } elseif ($operation === 'restore_backup') {
        if ($backupKey === null) {
            $errorMessage = 'Set ECOMMERCE_BACKUP_KEY to the original 32-byte key before restoring.';
        } elseif (!is_string($_POST['confirmation'] ?? null) || $_POST['confirmation'] !== 'RESTORE') {
            $errorMessage = 'Type RESTORE exactly to confirm replacing backed-up table data.';
        } elseif (!is_string($_POST['current_password'] ?? null)) {
            $errorMessage = 'Enter your current admin password.';
        } else {
            $passwordStatement = $conn->prepare('SELECT password FROM admins WHERE id = ? LIMIT 1');
            if (!$passwordStatement) {
                $errorMessage = 'Could not verify the current admin password.';
            } else {
                $adminId = (int) $currentAdmin['id'];
                $passwordStatement->bind_param('i', $adminId);
                $passwordStatement->execute();
                $passwordRow = $passwordStatement->get_result()->fetch_assoc();
                $passwordStatement->close();
                if (!$passwordRow || !password_verify((string) $_POST['current_password'], (string) $passwordRow['password'])) {
                    $errorMessage = 'Current admin password is incorrect.';
                } elseif (
                    !isset($_FILES['backup_file'])
                    || !is_array($_FILES['backup_file'])
                    || ($_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                    || !is_string($_FILES['backup_file']['tmp_name'] ?? null)
                    || !is_uploaded_file($_FILES['backup_file']['tmp_name'])
                    || (int) ($_FILES['backup_file']['size'] ?? 0) > 100 * 1024 * 1024
                ) {
                    $errorMessage = 'Choose a valid backup file no larger than 100 MB.';
                } else {
                    $contents = file_get_contents((string) $_FILES['backup_file']['tmp_name']);
                    $decryptedContents = false;
                    if (is_string($contents) && strlen($contents) > 35 && substr($contents, 0, 7) === 'ECOMDB1') {
                        $nonce = substr($contents, 7, 12);
                        $tag = substr($contents, 19, 16);
                        $decryptedContents = openssl_decrypt(substr($contents, 35), 'aes-256-gcm', $backupKey, OPENSSL_RAW_DATA, $nonce, $tag, 'ecommerce-db-backup-v1');
                    }
                    try {
                        $backup = is_string($decryptedContents) ? json_decode($decryptedContents, true, 512, JSON_THROW_ON_ERROR) : null;
                    } catch (JsonException) {
                        $backup = null;
                    }
                    $checksum = is_array($backup) && is_string($backup['checksum'] ?? null) ? $backup['checksum'] : '';
                    if (is_array($backup)) {
                        unset($backup['checksum']);
                    }
                    $databaseResult = $conn->query('SELECT DATABASE() AS database_name');
                    $databaseName = $databaseResult ? (string) $databaseResult->fetch_assoc()['database_name'] : '';
                    $tables = is_array($backup) ? ($backup['tables'] ?? null) : null;
                    $validBackup = is_array($backup)
                        && ($backup['format'] ?? null) === 'ecommerce-db-backup-v1'
                        && ($backup['database'] ?? null) === $databaseName
                        && is_array($tables)
                        && $checksum !== ''
                        && hash_equals($checksum, hash('sha256', json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''));
                    if (!$validBackup) {
                        $errorMessage = 'Backup format, checksum, or database name is invalid.';
                    } else {
                        $availableTables = array_flip(backupTableNames($conn));
                        $restoreFailed = false;
                        if (!$conn->begin_transaction()) {
                            $errorMessage = 'Could not start database restore.';
                        } else {
                            $conn->query('SET FOREIGN_KEY_CHECKS = 0');
                            foreach ($tables as $tableName => $rows) {
                                if (!is_string($tableName) || !isset($availableTables[$tableName]) || preg_match('/^[A-Za-z0-9_]+$/D', $tableName) !== 1 || !is_array($rows)) {
                                    $restoreFailed = true;
                                    break;
                                }
                                if (!$conn->query('DELETE FROM `' . $tableName . '`')) {
                                    $restoreFailed = true;
                                    break;
                                }
                                foreach ($rows as $row) {
                                    if (!is_array($row) || !$row) {
                                        $restoreFailed = true;
                                        break 2;
                                    }
                                    foreach (array_keys($row) as $column) {
                                        if (!is_string($column) || preg_match('/^[A-Za-z0-9_]+$/D', $column) !== 1) {
                                            $restoreFailed = true;
                                            break 2;
                                        }
                                    }
                                    $columnSql = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', array_keys($row)));
                                    $placeholders = implode(', ', array_fill(0, count($row), '?'));
                                    $insert = $conn->prepare('INSERT INTO `' . $tableName . '` (' . $columnSql . ') VALUES (' . $placeholders . ')');
                                    if (!$insert) {
                                        $restoreFailed = true;
                                        break 2;
                                    }
                                    $values = array_values($row);
                                    $types = str_repeat('s', count($values));
                                    $parameters = [$types];
                                    foreach ($values as $index => &$value) {
                                        $parameters[] = &$value;
                                    }
                                    unset($value);
                                    $bound = call_user_func_array([$insert, 'bind_param'], $parameters);
                                    $inserted = $bound && $insert->execute();
                                    $insert->close();
                                    if (!$inserted) {
                                        $restoreFailed = true;
                                        break 2;
                                    }
                                }
                            }
                            $conn->query('SET FOREIGN_KEY_CHECKS = 1');
                            if (!$restoreFailed) {
                                $conn->query('DELETE FROM admin_sessions');
                                $auditSaved = adminWriteAudit($conn, 'database_restored', null, null, ['created_at' => (string) $backup['created_at']]);
                            } else {
                                $auditSaved = false;
                            }
                            if ($restoreFailed || !$auditSaved || !$conn->commit()) {
                                $conn->rollback();
                                $errorMessage = 'Restore failed. Database changes were rolled back; check the server log.';
                            } else {
                                session_regenerate_id(true);
                                $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
                                if (!adminRegisterSession($conn, $adminId)) {
                                    adminDestroySession($conn);
                                    header('Location: index.php');
                                    exit;
                                }
                                backupRedirect('Backup restored. Other admin sessions were revoked.');
                            }
                        }
                    }
                }
            }
        }
    } else {
        $errorMessage = 'Unknown database backup action.';
    }
}

$backups = [];
foreach (glob($backupDirectoryReal . DIRECTORY_SEPARATOR . 'ecommerce_backup_*.enc') ?: [] as $backupPath) {
    $filename = basename($backupPath);
    if (backupFilePath($backupDirectoryReal, $filename) !== null) {
        $backups[] = ['filename' => $filename, 'size' => filesize($backupPath), 'modified' => filemtime($backupPath)];
    }
}
usort($backups, static fn(array $left, array $right): int => $right['modified'] <=> $left['modified']);
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
        <div><span class="section-tag"><i class="fas fa-database"></i> Store administration</span><h1>Database backup</h1></div>
    </header>
    <?php renderAdminPrimaryNavigation($currentAdmin, 'backup'); ?>
    <?php if ($flashMessage !== ''): ?><div class="operation-notice" role="status"><?= backupEscape($flashMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="operation-error" role="alert"><?= backupEscape($errorMessage) ?></div><?php endif; ?>
    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Create a private backup</h2><p>Snapshots are stored outside the web root and include customer and admin data.</p></div></div>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= backupEscape($csrfToken) ?>"><input type="hidden" name="operation" value="create_backup"><button class="operation-button" type="submit"><i class="fas fa-download"></i> Create backup</button></form>
    </section>
    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Restore a backup</h2><p>Replaces rows in matching tables only. Requires your current admin password and the exact word RESTORE. Revoke other admin sessions after restore.</p></div></div>
        <form class="security-create-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= backupEscape($csrfToken) ?>"><input type="hidden" name="operation" value="restore_backup">
            <label>Encrypted backup<input class="operation-input" type="file" name="backup_file" accept="application/octet-stream,.enc" required></label>
            <label>Current admin password<input class="operation-input" type="password" name="current_password" autocomplete="current-password" required></label>
            <label>Type RESTORE to confirm<input class="operation-input" name="confirmation" autocomplete="off" required></label>
            <button class="operation-button operation-button-danger" type="submit">Restore database</button>
        </form>
    </section>
    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Available backups</h2><p><?= count($backups) ?> file(s)</p></div></div>
        <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Backup</th><th>Created</th><th>Size</th><th>Download</th></tr></thead><tbody>
            <?php foreach ($backups as $backup): ?><tr><td><?= backupEscape($backup['filename']) ?></td><td><?= backupEscape(date('Y-m-d H:i:s', $backup['modified'])) ?></td><td><?= number_format($backup['size'] / 1024 / 1024, 2) ?> MB</td><td><a class="operation-button operation-button-secondary" href="?download=<?= rawurlencode($backup['filename']) ?>">Download</a></td></tr><?php endforeach; ?>
            <?php if (!$backups): ?><tr><td colspan="4">No backups have been created.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</main>
<script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js') ?>"></script>
</body>
</html>
