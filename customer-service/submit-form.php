<?php
declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
$lang = is_string($_POST['lang'] ?? null) ? strtolower(trim($_POST['lang'])) : '';
$langParam = in_array($lang, ['km', 'en'], true) ? '&lang=' . urlencode($lang) : '';

if (
    $name === ''
    || strlen($name) > 100
    || $email === ''
    || strlen($email) > 100
    || $message === ''
    || strlen($message) > 10000
) {
    header('Location: index.php?error=1' . $langParam, true, 303);
    exit;
}

$statement = $conn->prepare('INSERT INTO messages (name, email, message) VALUES (?, ?, ?)');
if (!$statement) {
    error_log('Could not prepare customer message insert: ' . $conn->error);
    header('Location: index.php?error=1' . $langParam, true, 303);
    exit;
}
$statement->bind_param('sss', $name, $email, $message);
$saved = $statement->execute();
if (!$saved) {
    error_log('Could not save customer message: ' . $statement->error);
}
$statement->close();

header('Location: index.php?' . ($saved ? 'success=1' : 'error=1') . $langParam, true, 303);
exit;
