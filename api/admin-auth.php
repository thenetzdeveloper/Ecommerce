<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const ADMIN_SESSION_IDLE_SECONDS = 1800;
const ADMIN_SESSION_MAX_SECONDS = 43200;
const ADMIN_LOGIN_WINDOW_MINUTES = 15;
const ADMIN_LOGIN_IDENTITY_LIMIT = 5;
const ADMIN_LOGIN_IP_LIMIT = 20;

function adminStartSession(): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    ini_set('session.use_strict_mode', '1');
    session_name('ecommerce_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    if (headers_sent()) {
        return false;
    }
    return @session_start();
}

function adminRequestIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function adminCsrfToken(): string
{
    if (empty($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function adminCsrfValid(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['admin_csrf_token'] ?? '';
    return is_string($submitted) && is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $submitted);
}

function adminLoginIdentityHash(string $username): string
{
    return hash('sha256', strtolower(trim($username)));
}

function adminAcquireLoginLocks(mysqli $connection, string $username): ?array
{
    $lockNames = [
        'adm_ip_' . substr(hash('sha256', adminRequestIp()), 0, 56),
        'adm_id_' . substr(adminLoginIdentityHash($username), 0, 56),
    ];
    sort($lockNames, SORT_STRING);
    $acquired = [];

    foreach ($lockNames as $lockName) {
        $statement = $connection->prepare('SELECT GET_LOCK(?, 5) AS acquired');
        if (!$statement) {
            error_log('Could not prepare admin login lock: ' . $connection->error);
            adminReleaseLoginLocks($connection, $acquired);
            return null;
        }
        $statement->bind_param('s', $lockName);
        $locked = $statement->execute() && (int) ($statement->get_result()->fetch_assoc()['acquired'] ?? 0) === 1;
        $statement->close();
        if (!$locked) {
            adminReleaseLoginLocks($connection, $acquired);
            return null;
        }
        $acquired[] = $lockName;
    }

    return $acquired;
}

function adminReleaseLoginLocks(mysqli $connection, array $lockNames): void
{
    foreach (array_reverse($lockNames) as $lockName) {
        $statement = $connection->prepare('SELECT RELEASE_LOCK(?)');
        if (!$statement) {
            error_log('Could not prepare admin login lock release: ' . $connection->error);
            continue;
        }
        $statement->bind_param('s', $lockName);
        $statement->execute();
        $statement->close();
    }
}

function adminLoginIsLimited(mysqli $connection, string $username): bool
{
    if (!$connection->query('DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)')) {
        error_log('Could not prune old admin login attempts: ' . $connection->error);
    }
    $ip = adminRequestIp();
    $identityHash = adminLoginIdentityHash($username);
    $statement = $connection->prepare("SELECT
        SUM(ip_address = ?) AS ip_attempts,
        SUM(identity_hash = ?) AS identity_attempts
        FROM admin_login_attempts
        WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    if (!$statement) {
        error_log('Could not prepare admin login rate check: ' . $connection->error);
        return true;
    }

    $statement->bind_param('ss', $ip, $identityHash);
    if (!$statement->execute()) {
        error_log('Could not execute admin login rate check: ' . $statement->error);
        $statement->close();
        return true;
    }

    $counts = $statement->get_result()->fetch_assoc();
    $statement->close();
    return (int) ($counts['ip_attempts'] ?? 0) >= ADMIN_LOGIN_IP_LIMIT
        || (int) ($counts['identity_attempts'] ?? 0) >= ADMIN_LOGIN_IDENTITY_LIMIT;
}

function adminRecordFailedLogin(mysqli $connection, string $username): bool
{
    $ip = adminRequestIp();
    $identityHash = adminLoginIdentityHash($username);
    $statement = $connection->prepare('INSERT INTO admin_login_attempts (ip_address, identity_hash) VALUES (?, ?)');
    if (!$statement) {
        error_log('Could not prepare failed admin login record: ' . $connection->error);
        return false;
    }

    $statement->bind_param('ss', $ip, $identityHash);
    $saved = $statement->execute();
    if (!$saved) {
        error_log('Could not record failed admin login: ' . $statement->error);
    }
    $statement->close();

    return $saved;
}

function adminClearFailedLogins(mysqli $connection, string $username): void
{
    $identityHash = adminLoginIdentityHash($username);
    $statement = $connection->prepare('DELETE FROM admin_login_attempts WHERE identity_hash = ?');
    if (!$statement) {
        return;
    }
    $statement->bind_param('s', $identityHash);
    $statement->execute();
    $statement->close();
}

function adminRegisterSession(mysqli $connection, int $adminId): bool
{
    $sessionHash = hash('sha256', session_id());
    $ip = adminRequestIp();
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $statement = $connection->prepare('INSERT INTO admin_sessions (admin_id, session_hash, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 12 HOUR))');
    if (!$statement) {
        error_log('Could not prepare admin session registration: ' . $connection->error);
        return false;
    }

    $statement->bind_param('isss', $adminId, $sessionHash, $ip, $userAgent);
    $registered = $statement->execute();
    if (!$registered) {
        error_log('Could not register admin session: ' . $statement->error);
    }
    $statement->close();
    $_SESSION['admin_session_started_at'] = time();
    $_SESSION['admin_session_seen_at'] = time();

    return $registered;
}

function adminDestroySession(mysqli $connection): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionHash = hash('sha256', session_id());
    $statement = $connection->prepare('DELETE FROM admin_sessions WHERE session_hash = ?');
    if ($statement) {
        $statement->bind_param('s', $sessionHash);
        $statement->execute();
        $statement->close();
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $params = session_get_cookie_params();
        @setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);
    }
    session_destroy();
}

function adminCurrentUser(mysqli $connection): ?array
{
    if (!isset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_session_started_at'], $_SESSION['admin_session_seen_at'])) {
        return null;
    }

    $now = time();
    if (
        $now - (int) $_SESSION['admin_session_seen_at'] > ADMIN_SESSION_IDLE_SECONDS
        || $now - (int) $_SESSION['admin_session_started_at'] > ADMIN_SESSION_MAX_SECONDS
    ) {
        adminDestroySession($connection);
        return null;
    }

    $sessionHash = hash('sha256', session_id());
    $connection->query('DELETE FROM admin_sessions WHERE expires_at <= NOW()');
    $adminId = (int) $_SESSION['admin_id'];
    $statement = $connection->prepare('SELECT id, username, role, permissions, is_active FROM admins WHERE id = ? LIMIT 1');
    if (!$statement) {
        error_log('Could not prepare admin session lookup: ' . $connection->error);
        return null;
    }
    $statement->bind_param('i', $adminId);
    if (!$statement->execute()) {
        error_log('Could not execute admin session lookup: ' . $statement->error);
        $statement->close();
        return null;
    }
    $admin = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$admin || !(int) $admin['is_active']) {
        adminDestroySession($connection);
        return null;
    }

    $sessionStatement = $connection->prepare('SELECT id FROM admin_sessions WHERE admin_id = ? AND session_hash = ? AND expires_at > NOW() AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1');
    if (!$sessionStatement) {
        error_log('Could not prepare admin session validation: ' . $connection->error);
        return null;
    }
    $sessionStatement->bind_param('is', $adminId, $sessionHash);
    $valid = $sessionStatement->execute() && $sessionStatement->get_result()->num_rows === 1;
    $sessionStatement->close();
    if (!$valid) {
        adminDestroySession($connection);
        return null;
    }

    $touchStatement = $connection->prepare('UPDATE admin_sessions SET last_seen_at = CURRENT_TIMESTAMP WHERE admin_id = ? AND session_hash = ?');
    if ($touchStatement) {
        $touchStatement->bind_param('is', $adminId, $sessionHash);
        $touchStatement->execute();
        $touchStatement->close();
    }

    $_SESSION['admin_username'] = (string) $admin['username'];
    $_SESSION['admin_session_seen_at'] = $now;
    return $admin;
}

function adminRequireLogin(mysqli $connection, string $loginPath = 'index.php'): array
{
    $admin = adminCurrentUser($connection);
    if ($admin !== null) {
        return $admin;
    }

    header('Location: ' . $loginPath);
    exit;
}

function adminHasPermission(array $admin, string $permission): bool
{
    if (($admin['role'] ?? 'admin') === 'admin') {
        return true;
    }

    $permissions = json_decode((string) ($admin['permissions'] ?? ''), true);
    return is_array($permissions) && in_array($permission, $permissions, true);
}

function adminRequirePermission(array $admin, string $permission): void
{
    if (adminHasPermission($admin, $permission)) {
        return;
    }

    http_response_code(403);
    exit('You do not have permission to access this area.');
}

function adminWriteAudit(mysqli $connection, string $action, ?int $targetAdminId = null, ?array $oldValues = null, ?array $newValues = null): bool
{
    $actorId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    $actorUsername = (string) ($_SESSION['admin_username'] ?? 'system');
    $oldJson = $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $newJson = $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ip = adminRequestIp();
    $statement = $connection->prepare('INSERT INTO admin_audit_log (actor_id, actor_username, action, target_admin_id, old_values, new_values, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$statement) {
        error_log('Could not prepare admin security audit record: ' . $connection->error);
        return false;
    }

    $statement->bind_param('ississs', $actorId, $actorUsername, $action, $targetAdminId, $oldJson, $newJson, $ip);
    $saved = $statement->execute();
    if (!$saved) {
        error_log('Could not save admin security audit record: ' . $statement->error);
    }
    $statement->close();
    return $saved;
}
