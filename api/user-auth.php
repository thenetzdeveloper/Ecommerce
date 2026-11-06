<?php
declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        @session_start();
    }
}

require_once __DIR__ . '/db.php';

/**
 * Get currently authenticated user data, or null if not logged in.
 */
function getCurrentUser(mysqli $conn): ?array
{
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    $userId = $_SESSION['user_id'] ?? null;

    // Check remember-me cookie if session is not set
    if (!$userId && !empty($_COOKIE['user_remember'])) {
        $token = (string)$_COOKIE['user_remember'];
        $tokenHash = hash('sha256', $token);
        $stmt = $conn->prepare("SELECT user_id FROM `account_sessions` WHERE `session_token` = ? AND `expires_at` > NOW() LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $tokenHash);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $userId = (int)$row['user_id'];
                $_SESSION['user_id'] = $userId;
            }
            $stmt->close();
        }
    }

    if (!$userId) {
        return null;
    }

    $stmt = $conn->prepare("SELECT `id`, `username`, `email`, `name`, `phone`, `address`, `city`, `profile_picture`, `bio`, `auth_provider`, `google_id`, `created_at` FROM `users` WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Fallback display name
        if (empty($user['name'])) {
            $user['name'] = !empty($user['email']) ? explode('@', $user['email'])[0] : ($user['username'] ?: 'Customer');
        }
        $cachedUser = $user;
        return $user;
    }

    // Invalid session
    unset($_SESSION['user_id']);
    return null;
}

/**
 * Check if customer is logged in
 */
function userIsLoggedIn(mysqli $conn): bool
{
    return getCurrentUser($conn) !== null;
}

/**
 * Register a new user with Email and Password
 */
function userRegister(mysqli $conn, string $email, string $password, string $name, string $phone = ''): array
{
    $email = strtolower(trim($email));
    $name = trim($name);
    $phone = trim($phone);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'invalid_email', 'message' => 'Please enter a valid email address.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'password_too_short', 'message' => 'Password must be at least 6 characters.'];
    }

    if ($name === '') {
        $name = explode('@', $email)[0];
    }

    // Check if email already registered
    $checkStmt = $conn->prepare("SELECT id FROM `users` WHERE `email` = ? OR `username` = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('ss', $email, $email);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        if ($exists) {
            return ['success' => false, 'error' => 'email_exists', 'message' => 'This email is already registered. Please log in instead.'];
        }
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $authProvider = 'email';

    $insertStmt = $conn->prepare("INSERT INTO `users` (`username`, `email`, `password`, `name`, `phone`, `auth_provider`) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$insertStmt) {
        return ['success' => false, 'error' => 'db_error', 'message' => 'Database error. Please try again.'];
    }

    $insertStmt->bind_param('ssssss', $email, $email, $passwordHash, $name, $phone, $authProvider);
    $saved = $insertStmt->execute();
    $newUserId = $insertStmt->insert_id;
    $insertStmt->close();

    if (!$saved || !$newUserId) {
        return ['success' => false, 'error' => 'save_failed', 'message' => 'Could not create account. Please try again.'];
    }

    $_SESSION['user_id'] = $newUserId;

    return [
        'success' => true,
        'user_id' => $newUserId,
        'message' => 'Registration successful!'
    ];
}

/**
 * Login with Email (or username) and Password
 */
function userLogin(mysqli $conn, string $identifier, string $password, bool $remember = true): array
{
    $identifier = trim($identifier);
    if ($identifier === '' || $password === '') {
        return ['success' => false, 'error' => 'empty_fields', 'message' => 'Please enter both email and password.'];
    }

    $stmt = $conn->prepare("SELECT `id`, `username`, `email`, `password`, `name` FROM `users` WHERE `email` = ? OR `username` = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'error' => 'db_error', 'message' => 'Database error. Please try again.'];
    }

    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['success' => false, 'error' => 'invalid_credentials', 'message' => 'Invalid email or password.'];
    }

    if (empty($user['password']) || !password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'invalid_credentials', 'message' => 'Invalid email or password.'];
    }

    $_SESSION['user_id'] = (int)$user['id'];

    if ($remember) {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 days

        $remStmt = $conn->prepare("INSERT INTO `account_sessions` (`user_id`, `session_token`, `expires_at`) VALUES (?, ?, ?)");
        if ($remStmt) {
            $remStmt->bind_param('iss', $user['id'], $tokenHash, $expiresAt);
            $remStmt->execute();
            $remStmt->close();
            if (!headers_sent()) {
                @setcookie('user_remember', $rawToken, time() + (86400 * 30), '/', '', false, true);
            }
        }
    }

    return [
        'success' => true,
        'user_id' => (int)$user['id'],
        'message' => 'Login successful!'
    ];
}

/**
 * Google Sign-In via Google Identity Services JWT credential
 */
function userGoogleLogin(mysqli $conn, string $credentialJwt): array
{
    $parts = explode('.', $credentialJwt);
    if (count($parts) !== 3) {
        return ['success' => false, 'error' => 'invalid_token', 'message' => 'Malformed Google credential.'];
    }

    // Decode payload
    $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1]));
    if (!$payloadJson) {
        return ['success' => false, 'error' => 'decode_failed', 'message' => 'Could not decode Google token.'];
    }

    $data = json_decode($payloadJson, true);
    if (!is_array($data) || empty($data['email']) || empty($data['sub'])) {
        return ['success' => false, 'error' => 'invalid_payload', 'message' => 'Missing Google user attributes.'];
    }

    $googleId = (string)$data['sub'];
    $email = strtolower(trim((string)$data['email']));
    $name = trim((string)($data['name'] ?? ''));
    $picture = trim((string)($data['picture'] ?? ''));

    // Check if user exists by google_id or email
    $stmt = $conn->prepare("SELECT `id`, `google_id`, `profile_picture`, `name` FROM `users` WHERE `google_id` = ? OR `email` = ? OR `username` = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'error' => 'db_error', 'message' => 'Database error.'];
    }

    $stmt->bind_param('sss', $googleId, $email, $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $userId = (int)$existing['id'];
        // Update google_id and picture if not set
        $upStmt = $conn->prepare("UPDATE `users` SET `google_id` = IF(`google_id` = '' OR `google_id` IS NULL, ?, `google_id`), `profile_picture` = IF(`profile_picture` = '' OR `profile_picture` IS NULL, ?, `profile_picture`), `name` = IF(`name` = '' OR `name` IS NULL, ?, `name`) WHERE `id` = ?");
        if ($upStmt) {
            $upStmt->bind_param('sssi', $googleId, $picture, $name, $userId);
            $upStmt->execute();
            $upStmt->close();
        }
    } else {
        // Create new user
        $authProvider = 'google';
        $inStmt = $conn->prepare("INSERT INTO `users` (`username`, `email`, `google_id`, `name`, `profile_picture`, `auth_provider`) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$inStmt) {
            return ['success' => false, 'error' => 'db_error', 'message' => 'Could not save Google user.'];
        }
        $inStmt->bind_param('ssssss', $email, $email, $googleId, $name, $picture, $authProvider);
        $inStmt->execute();
        $userId = $inStmt->insert_id;
        $inStmt->close();
    }

    $_SESSION['user_id'] = $userId;

    // Set remember token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + (86400 * 30));
    $remStmt = $conn->prepare("INSERT INTO `account_sessions` (`user_id`, `session_token`, `expires_at`) VALUES (?, ?, ?)");
    if ($remStmt) {
        $remStmt->bind_param('iss', $userId, $tokenHash, $expiresAt);
        $remStmt->execute();
        $remStmt->close();
        if (!headers_sent()) {
            @setcookie('user_remember', $rawToken, time() + (86400 * 30), '/', '', false, true);
        }
    }

    return [
        'success' => true,
        'user_id' => $userId,
        'message' => 'Signed in with Google successfully!'
    ];
}

/**
 * Logout current user
 */
function userLogout(mysqli $conn): void
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!empty($_COOKIE['user_remember'])) {
        $token = (string)$_COOKIE['user_remember'];
        $tokenHash = hash('sha256', $token);
        $del = $conn->prepare("DELETE FROM `account_sessions` WHERE `session_token` = ?");
        if ($del) {
            $del->bind_param('s', $tokenHash);
            $del->execute();
            $del->close();
        }
        if (!headers_sent()) {
            @setcookie('user_remember', '', time() - 3600, '/');
        }
    }

    unset($_SESSION['user_id']);
}

/**
 * Update user profile details
 */
function userUpdateProfile(mysqli $conn, int $userId, array $data): array
{
    $name = trim((string)($data['name'] ?? ''));
    $phone = trim((string)($data['phone'] ?? ''));
    $address = trim((string)($data['address'] ?? ''));
    $city = trim((string)($data['city'] ?? ''));
    $bio = trim((string)($data['bio'] ?? ''));
    $profilePic = trim((string)($data['profile_picture'] ?? ''));

    if ($name === '') {
        return ['success' => false, 'message' => 'Full name cannot be empty.'];
    }

    $sql = "UPDATE `users` SET `name` = ?, `phone` = ?, `address` = ?, `city` = ?, `bio` = ?";
    $params = [$name, $phone, $address, $city, $bio];
    $types = 'sssss';

    if ($profilePic !== '') {
        $sql .= ", `profile_picture` = ?";
        $params[] = $profilePic;
        $types .= 's';
    }

    $sql .= " WHERE `id` = ?";
    $params[] = $userId;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();

    return ['success' => $ok, 'message' => $ok ? 'Profile updated successfully!' : 'Failed to update profile.'];
}

/**
 * Change user password
 */
function userChangePassword(mysqli $conn, int $userId, string $oldPassword, string $newPassword): array
{
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'New password must be at least 6 characters.'];
    }

    $stmt = $conn->prepare("SELECT `password`, `auth_provider` FROM `users` WHERE `id` = ? LIMIT 1");
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }

    // If user has an existing password, verify old password
    if (!empty($user['password'])) {
        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $up = $conn->prepare("UPDATE `users` SET `password` = ? WHERE `id` = ?");
    if (!$up) {
        return ['success' => false, 'message' => 'Database error.'];
    }
    $up->bind_param('si', $newHash, $userId);
    $ok = $up->execute();
    $up->close();

    return ['success' => $ok, 'message' => $ok ? 'Password changed successfully!' : 'Failed to change password.'];
}

/**
 * Retrieve all orders associated with a user
 */
function getUserOrders(mysqli $conn, int $userId, ?string $phone = null): array
{
    $orders = [];
    $sql = "SELECT id, product_id, product_name, customer_name, phone_number, location, quantity, unit_price, order_reference, payment_method, status, payment_status, paid_amount, receipt_filename, discount_amount, created_at 
            FROM `sales_orders` 
            WHERE `user_id` = ?";
    $params = [$userId];
    $types = 'i';

    if (!empty($phone)) {
        $sql .= " OR (`phone_number` = ? AND `phone_number` != '')";
        $params[] = $phone;
        $types .= 's';
    }

    $sql .= " ORDER BY `id` DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();
    }

    return $orders;
}

