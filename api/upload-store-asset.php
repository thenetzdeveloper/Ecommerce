<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin-auth.php';

if (!ensureAdminSecuritySchema($conn) || !adminStartSession()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not start admin session.']);
    exit;
}

$currentAdmin = adminCurrentUser($conn);
if (!$currentAdmin || !adminHasPermission($currentAdmin, 'store_settings')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin permission required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

if (!adminCsrfValid()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Security token (CSRF) expired. Please reload the page.']);
    exit;
}

$assetType = trim((string)($_POST['asset_type'] ?? ''));
$action = trim((string)($_POST['action'] ?? 'upload'));

$allowedTypes = [
    'home_banner' => [
        'setting' => 'home_banner_url',
        'dir' => __DIR__ . '/../img/banners/',
        'db_prefix' => 'img/banners/',
        'default' => './logo/banner.png',
        'label' => 'Hero Banner Image'
    ],
    'store_logo' => [
        'setting' => 'store_logo_url',
        'dir' => __DIR__ . '/../img/store/',
        'db_prefix' => 'img/store/',
        'default' => './logo/logome.png',
        'label' => 'Store Logo'
    ],
    'about_banner' => [
        'setting' => 'about_banner_url',
        'dir' => __DIR__ . '/../img/banners/',
        'db_prefix' => 'img/banners/',
        'default' => '',
        'label' => 'About Us Banner Image'
    ]
];

if (!isset($allowedTypes[$assetType])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid asset type specified.']);
    exit;
}

$config = $allowedTypes[$assetType];

// Handle Reset to Default
if ($action === 'reset_default') {
    $defaultVal = $config['default'];
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if ($stmt) {
        $stmt->bind_param('ss', $config['setting'], $defaultVal);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode([
        'success' => true,
        'action' => 'reset_default',
        'asset_type' => $assetType,
        'setting_name' => $config['setting'],
        'db_path' => $defaultVal,
        'preview_url' => (str_starts_with($defaultVal, './') ? '../home/' . substr($defaultVal, 2) : '../' . $defaultVal),
        'message' => 'កំណត់រូបភាពទៅជាលំនាំដើមវិញដោយជោគជ័យ (Reset to default successfully)'
    ]);
    exit;
}

// Handle File Upload from PC
if (!isset($_FILES['asset_file']) || $_FILES['asset_file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['asset_file']['error'] ?? -1;
    $msg = 'សូមជ្រើសរើសឯកសាររូបភាពពីកុំព្យូទ័រ (Please select an image file to upload).';
    if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) {
        $msg = 'ទំហំរូបភាពធំពេក (Image file size exceeds maximum limit).';
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$file = $_FILES['asset_file'];
$tmpPath = $file['tmp_name'];
$fileSize = (int)$file['size'];

if ($fileSize > 10 * 1024 * 1024) { // 10MB max
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ទំហំរូបភាពមិនត្រូវលើសពី 10MB ទេ (Maximum file size is 10MB).']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmpPath);

$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/svg+xml' => 'svg'
];

if (!isset($allowedMimes[$mimeType])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ប្រភេទឯកសារមិនត្រឹមត្រូវទេ។ អនុញ្ញាតតែ JPG, PNG, WEBP, GIF, SVG ប៉ុណ្ណោះ (Invalid format. Only JPG, PNG, WEBP, GIF, SVG supported).'
    ]);
    exit;
}

$ext = $allowedMimes[$mimeType];
$targetDir = $config['dir'];
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$filename = $assetType . '_' . date('Ymd_His_') . bin2hex(random_bytes(6)) . '.' . $ext;
$targetPath = $targetDir . $filename;

if (!move_uploaded_file($tmpPath, $targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'មិនអាចរក្សាទុករូបភាពលើ Server បានទេ (Failed to save uploaded image).']);
    exit;
}

// Store relative path in DB: e.g. img/banners/home_banner_20261106_abc.jpg
$dbPath = $config['db_prefix'] . $filename;

$stmt = $conn->prepare("INSERT INTO system_settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
if ($stmt) {
    $stmt->bind_param('ss', $config['setting'], $dbPath);
    $stmt->execute();
    $stmt->close();
}

$adminPreviewUrl = '../' . $dbPath;
$homePreviewUrl = '../' . $dbPath;

echo json_encode([
    'success' => true,
    'action' => 'upload',
    'asset_type' => $assetType,
    'setting_name' => $config['setting'],
    'db_path' => $dbPath,
    'preview_url' => $adminPreviewUrl,
    'home_preview_url' => $homePreviewUrl,
    'filename' => $filename,
    'message' => 'រូបភាពត្រូវបានផ្លាស់ប្តូរដោយជោគជ័យ! (Image uploaded and updated successfully from your PC)'
]);

