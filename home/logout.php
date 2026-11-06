<?php
declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/user-auth.php';

userLogout($conn);

$redirect = filter_var($_GET['redirect'] ?? 'index.php', FILTER_SANITIZE_URL);
if (empty($redirect) || preg_match('/^(?:https?:)?\/\//i', $redirect)) {
    $redirect = 'index.php';
}

if (!headers_sent()) {
    header('Location: ' . $redirect, true, 303);
} else {
    echo '<script>window.location.href="' . htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') . '";</script>';
}
exit;

