<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/admin-auth.php';
require_once __DIR__ . '/primary-navigation.php';

if (!ensureAdminSecuritySchema($conn) || !ensureSalesAnalyticsSchema($conn) || !adminStartSession()) {
    http_response_code(500);
    exit('Could not prepare admin security. Check the server error log.');
}
$currentAdmin = adminRequireLogin($conn);
$canManageAccounts = ($currentAdmin['role'] ?? '') === 'admin';
$canViewAudit = adminHasPermission($currentAdmin, 'security');
$csrfToken = adminCsrfToken();
$allowedPermissions = [
    'products' => 'Products',
    'orders' => 'Orders & payments',
    'inventory' => 'Inventory',
    'customers' => 'Customers',
    'reports' => 'Reports',
    'security' => 'Security',
    'support' => 'Customer messages',
    'promotions' => 'Promotions',
    'store_settings' => 'Homepage & store settings',
    'notifications' => 'Notifications',
    'backup' => 'Database backup',
];
$errorMessage = '';
$flashMessage = is_string($_SESSION['admin_security_flash'] ?? null) ? $_SESSION['admin_security_flash'] : '';
unset($_SESSION['admin_security_flash']);

function securityEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function securityRedirect(string $message): void
{
    $_SESSION['admin_security_flash'] = $message;
    header('Location: security.php');
    exit;
}

function securityPermissionsFromPost(array $allowedPermissions): array
{
    $submitted = $_POST['permissions'] ?? [];
    if (!is_array($submitted)) {
        return [];
    }

    $selected = [];
    foreach ($submitted as $permission) {
        if (is_string($permission) && array_key_exists($permission, $allowedPermissions)) {
            $selected[] = $permission;
        }
    }
    return array_values(array_unique($selected));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adminCsrfValid()) {
        http_response_code(403);
        exit('The form expired. Reload the page and try again.');
    }

    $operation = is_string($_POST['operation'] ?? null) ? $_POST['operation'] : '';
    if (!$canManageAccounts && in_array($operation, ['create_admin', 'update_admin', 'revoke_session'], true)) {
        http_response_code(403);
        exit('Only an admin can manage accounts and sessions.');
    }
    if ($operation === 'logout') {
        adminWriteAudit($conn, 'logout');
        adminDestroySession($conn);
        header('Location: index.php');
        exit;
    } elseif ($operation === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $lookup = $conn->prepare('SELECT password FROM admins WHERE id = ? LIMIT 1');
        if (!$lookup) {
            $errorMessage = 'Could not verify the current password.';
        } else {
            $adminId = (int) $currentAdmin['id'];
            $lookup->bind_param('i', $adminId);
            $lookup->execute();
            $passwordRow = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            if (
                !is_string($currentPassword)
                || !$passwordRow
                || !password_verify($currentPassword, (string) $passwordRow['password'])
                || !is_string($newPassword)
                || strlen($newPassword) < 12
                || strlen($newPassword) > 255
                || !is_string($confirmPassword)
                || !hash_equals($newPassword, $confirmPassword)
            ) {
                $errorMessage = 'Verify your current password and enter a matching new password of at least 12 characters.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $save = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?');
                if (!$save || !$conn->begin_transaction()) {
                    if ($save) {
                        $save->close();
                    }
                    $errorMessage = 'Could not update the password.';
                } else {
                    $save->bind_param('si', $newHash, $adminId);
                    $saved = $save->execute();
                    $save->close();
                    $deleteOthers = $conn->prepare('DELETE FROM admin_sessions WHERE admin_id = ?');
                    $sessionsDeleted = $deleteOthers !== false;
                    if ($deleteOthers) {
                        $deleteOthers->bind_param('i', $adminId);
                        $sessionsDeleted = $deleteOthers->execute();
                        $deleteOthers->close();
                    }
                    $auditSaved = $saved && $sessionsDeleted && adminWriteAudit($conn, 'password_changed', $adminId);
                    if ($auditSaved && $conn->commit()) {
                        session_regenerate_id(true);
                        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
                        if (adminRegisterSession($conn, $adminId)) {
                            securityRedirect('Password updated. Other sessions have been signed out.');
                        }
                        $errorMessage = 'Password was updated, but this session could not be renewed. Sign in again.';
                        adminDestroySession($conn);
                    } else {
                        $conn->rollback();
                        $errorMessage = 'Could not update the password and security audit record.';
                    }
                }
            }
        }
    } elseif ($operation === 'create_admin') {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = $_POST['new_password'] ?? '';
        $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : 'staff';
        $permissions = securityPermissionsFromPost($allowedPermissions);
        if (
            preg_match('/^[A-Za-z0-9._-]{3,100}$/D', $username) !== 1
            || !is_string($password)
            || strlen($password) < 12
            || strlen($password) > 255
            || !in_array($role, ['admin', 'staff'], true)
            || ($role === 'staff' && !$permissions)
        ) {
            $errorMessage = 'Enter a valid username, a password of at least 12 characters, and permissions for Staff.';
        } else {
            $permissionJson = $role === 'admin' ? null : json_encode($permissions);
            $save = $conn->prepare('INSERT INTO admins (username, password, role, permissions, is_active) VALUES (?, ?, ?, ?, 1)');
            if (!$save || !$conn->begin_transaction()) {
                if ($save) {
                    $save->close();
                }
                $errorMessage = 'Could not create the admin account.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $save->bind_param('ssss', $username, $passwordHash, $role, $permissionJson);
                $saved = $save->execute();
                $newAdminId = $saved ? (int) $conn->insert_id : null;
                $saveError = $save->errno;
                $save->close();
                $auditSaved = $saved && adminWriteAudit($conn, 'admin_created', $newAdminId, null, ['username' => $username, 'role' => $role, 'permissions' => $permissions]);
                if ($auditSaved && $conn->commit()) {
                    securityRedirect('Admin account created.');
                }
                $conn->rollback();
                $errorMessage = $saveError === 1062 ? 'That username is already in use.' : 'Could not create the account and security audit record.';
            }
        }
    } elseif ($operation === 'update_admin') {
        $targetId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);
        $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $permissions = securityPermissionsFromPost($allowedPermissions);
        if (!$targetId || $targetId < 1 || !in_array($role, ['admin', 'staff'], true) || $targetId === (int) $currentAdmin['id']) {
            $errorMessage = 'Choose a different account and valid role settings.';
        } else {
            $lookup = $conn->prepare('SELECT id, username, role, permissions, is_active FROM admins WHERE id = ? LIMIT 1');
            if (!$lookup) {
                $errorMessage = 'Could not load the account.';
            } else {
                $lookup->bind_param('i', $targetId);
                $lookup->execute();
                $target = $lookup->get_result()->fetch_assoc();
                $lookup->close();
                if (!$target) {
                    $errorMessage = 'Admin account not found.';
                } elseif ($role === 'staff' && !$permissions) {
                    $errorMessage = 'Staff must have at least one permission.';
                } elseif ($target['role'] === 'admin' && ($role !== 'admin' || !$isActive)) {
                    $count = $conn->query("SELECT COUNT(*) AS active_admins FROM admins WHERE role = 'admin' AND is_active = 1 AND id <> " . (int) $targetId);
                    if (!$count || (int) $count->fetch_assoc()['active_admins'] < 1) {
                        $errorMessage = 'The last active admin cannot be demoted or disabled.';
                    }
                }

                if ($errorMessage === '') {
                    $permissionJson = $role === 'admin' ? null : json_encode($permissions);
                    $save = $conn->prepare('UPDATE admins SET role = ?, permissions = ?, is_active = ? WHERE id = ?');
                    if (!$save || !$conn->begin_transaction()) {
                        if ($save) {
                            $save->close();
                        }
                        $errorMessage = 'Could not update account permissions.';
                    } else {
                        $save->bind_param('ssii', $role, $permissionJson, $isActive, $targetId);
                        $saved = $save->execute();
                        $save->close();
                        $oldValues = ['role' => $target['role'], 'permissions' => $target['permissions'], 'is_active' => (int) $target['is_active']];
                        $newValues = ['role' => $role, 'permissions' => $permissions, 'is_active' => $isActive];
                        $auditSaved = $saved && adminWriteAudit($conn, 'admin_updated', (int) $targetId, $oldValues, $newValues);
                        if ($auditSaved && $conn->commit()) {
                            if (!$isActive || $role !== $target['role'] || $permissionJson !== $target['permissions']) {
                                $revoke = $conn->prepare('DELETE FROM admin_sessions WHERE admin_id = ?');
                                if ($revoke) {
                                    $revoke->bind_param('i', $targetId);
                                    $revoke->execute();
                                    $revoke->close();
                                }
                            }
                            securityRedirect('Admin account updated. Changes take effect on the next sign-in.');
                        }
                        $conn->rollback();
                        $errorMessage = 'Could not update the account and security audit record.';
                    }
                }
            }
        }
    } elseif ($operation === 'revoke_session') {
        $sessionId = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$sessionId || $sessionId < 1) {
            $errorMessage = 'Choose a valid session.';
        } else {
            $lookup = $conn->prepare('SELECT admin_id, session_hash FROM admin_sessions WHERE id = ? LIMIT 1');
            if (!$lookup) {
                $errorMessage = 'Could not load the session.';
            } else {
                $lookup->bind_param('i', $sessionId);
                $lookup->execute();
                $targetSession = $lookup->get_result()->fetch_assoc();
                $lookup->close();
                if (!$targetSession) {
                    securityRedirect('That session is already closed.');
                }
                $ownerId = (int) $targetSession['admin_id'];
                $sessionHash = (string) $targetSession['session_hash'];
                $delete = $conn->prepare('DELETE FROM admin_sessions WHERE id = ?');
                if (!$delete || !$conn->begin_transaction()) {
                    if ($delete) {
                        $delete->close();
                    }
                    $errorMessage = 'Could not revoke the session.';
                } else {
                    $delete->bind_param('i', $sessionId);
                    $deleted = $delete->execute();
                    $delete->close();
                    $auditSaved = $deleted && adminWriteAudit($conn, 'session_revoked', $ownerId, null, ['session_hash' => $sessionHash]);
                    if ($auditSaved && $conn->commit()) {
                        if (hash_equals(hash('sha256', session_id()), $sessionHash)) {
                            adminDestroySession($conn);
                            header('Location: index.php');
                            exit;
                        }
                        securityRedirect('Session revoked.');
                    }
                    $conn->rollback();
                    $errorMessage = 'Could not revoke the session and save its audit record.';
                }
            }
        }
    } else {
        $errorMessage = 'Unknown security action.';
    }
}

$conn->query('DELETE FROM admin_sessions WHERE expires_at <= NOW() OR last_seen_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)');
$conn->query('DELETE FROM admin_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
$admins = [];
$adminResult = $conn->query('SELECT id, username, role, permissions, is_active FROM admins ORDER BY username ASC');
if ($adminResult) {
    while ($row = $adminResult->fetch_assoc()) {
        $admins[] = $row;
    }
}
$sessions = [];
$sessionResult = $conn->query('SELECT s.id, s.admin_id, a.username, s.session_hash, s.ip_address, s.user_agent, s.created_at, s.last_seen_at FROM admin_sessions s INNER JOIN admins a ON a.id = s.admin_id WHERE s.expires_at > NOW() AND s.last_seen_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY s.last_seen_at DESC');
if ($sessionResult) {
    while ($row = $sessionResult->fetch_assoc()) {
        $sessions[] = $row;
    }
}
$securityAudit = $conn->query('SELECT actor_username, action, target_admin_id, old_values, new_values, ip_address, created_at FROM admin_audit_log ORDER BY created_at DESC, id DESC LIMIT 50');
$productAudit = $conn->query('SELECT product_name, action, actor, created_at FROM product_activity ORDER BY created_at DESC, id DESC LIMIT 50');
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
    <link rel="stylesheet" href="../home/page.css">
    <link rel="stylesheet" href="../home/fashion-theme.css?v=<?= filemtime(__DIR__ . '/../home/fashion-theme.css') ?>">
    <link rel="stylesheet" href="./backoffice-theme.css?v=<?= filemtime(__DIR__ . '/backoffice-theme.css') ?>">
    <link rel="stylesheet" href="./operations.css?v=<?= filemtime(__DIR__ . '/operations.css') ?>">
    <link rel="stylesheet" href="../css/theme-mode.css?v=<?= filemtime(__DIR__ . '/../css/theme-mode.css') ?>">
    <script src="../js/page-navigation.js?v=<?= filemtime(__DIR__ . '/../js/page-navigation.js') ?>" defer></script>
</head>
<body class="fashion-storefront admin-storefront">
<main class="admin-dashboard-container operations-container">
    <header class="admin-header-glass operations-header">
        <div><span class="section-tag"><i class="fas fa-shield-halved"></i> Access control</span><h1>Security &amp; audit</h1></div>
        <nav class="operations-nav" aria-label="Admin navigation">
            <div class="theme-mode-control theme-mode-admin theme-mode-nav">
                <label class="theme-mode-label" for="securityThemeModeSelect">Theme</label>
                <select id="securityThemeModeSelect" data-theme-mode aria-label="Color theme">
                    <option value="system">System</option>
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
            </div>
        </nav>
    </header>
    <?php renderAdminPrimaryNavigation($currentAdmin, 'security'); ?>

    <?php if ($flashMessage !== ''): ?><div class="operation-notice" role="status"><?= securityEscape($flashMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="operation-error" role="alert"><?= securityEscape($errorMessage) ?></div><?php endif; ?>

    <?php if ($canManageAccounts): ?>
    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Admin accounts</h2><p>Assign access by section. Deactivated accounts lose access immediately.</p></div></div>
        <div class="operations-table-wrap"><table class="operations-table">
            <thead><tr><th>Username</th><th>Role</th><th>Permissions</th><th>Status</th><th>Save</th></tr></thead>
            <tbody>
            <?php foreach ($admins as $admin): ?>
                <?php $accountPermissions = json_decode((string) ($admin['permissions'] ?? ''), true); $accountPermissions = is_array($accountPermissions) ? $accountPermissions : []; ?>
                <?php $updateFormId = 'update-admin-' . (int) $admin['id']; $isCurrentAdmin = (int) $admin['id'] === (int) $currentAdmin['id']; ?>
                <tr>
                    <td><form id="<?= securityEscape($updateFormId) ?>" method="post"><input type="hidden" name="csrf_token" value="<?= securityEscape($csrfToken) ?>"><input type="hidden" name="operation" value="update_admin"><input type="hidden" name="admin_id" value="<?= (int) $admin['id'] ?>"></form><?= securityEscape((string) $admin['username']) ?><?= $isCurrentAdmin ? ' · You' : '' ?></td>
                    <td><select class="operation-input" name="role" form="<?= securityEscape($updateFormId) ?>" <?= $isCurrentAdmin ? 'disabled' : '' ?>><option value="admin" <?= $admin['role'] === 'admin' ? 'selected' : '' ?>>Admin</option><option value="staff" <?= $admin['role'] === 'staff' ? 'selected' : '' ?>>Staff</option></select><?php if ($isCurrentAdmin): ?><input type="hidden" name="role" value="<?= securityEscape((string) $admin['role']) ?>" form="<?= securityEscape($updateFormId) ?>"><?php endif; ?></td>
                    <td><div class="security-permission-list"><?php foreach ($allowedPermissions as $permission => $label): ?><label><input type="checkbox" name="permissions[]" value="<?= securityEscape($permission) ?>" form="<?= securityEscape($updateFormId) ?>" <?= in_array($permission, $accountPermissions, true) ? 'checked' : '' ?> <?= $isCurrentAdmin ? 'disabled' : '' ?>><?= securityEscape($label) ?></label><?php endforeach; ?></div></td>
                    <td><label><input type="checkbox" name="is_active" value="1" form="<?= securityEscape($updateFormId) ?>" <?= (int) $admin['is_active'] === 1 ? 'checked' : '' ?> <?= $isCurrentAdmin ? 'disabled' : '' ?>> Active</label><?php if ($isCurrentAdmin): ?><input type="hidden" name="is_active" value="1" form="<?= securityEscape($updateFormId) ?>"><?php endif; ?></td>
                    <td><?php if (!$isCurrentAdmin): ?><button class="operation-button" type="submit" form="<?= securityEscape($updateFormId) ?>">Save</button><?php else: ?>Current account<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$admins): ?><tr><td colspan="5">No admin accounts.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>

    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Add account</h2><p>Use a unique username and a password with at least 12 characters.</p></div></div>
        <form class="security-create-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= securityEscape($csrfToken) ?>"><input type="hidden" name="operation" value="create_admin">
            <label>Username<input class="operation-input" name="username" minlength="3" maxlength="100" pattern="[A-Za-z0-9._-]+" autocomplete="off" required></label>
            <label>Temporary password<input class="operation-input" type="password" name="new_password" minlength="12" maxlength="255" autocomplete="new-password" required></label>
            <label>Role<select class="operation-input" name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></label>
            <fieldset><legend>Staff permissions</legend><?php foreach ($allowedPermissions as $permission => $label): ?><label><input type="checkbox" name="permissions[]" value="<?= securityEscape($permission) ?>"><?= securityEscape($label) ?></label><?php endforeach; ?></fieldset>
            <button class="operation-button" type="submit">Create account</button>
        </form>
    </section>
    <?php endif; ?>

    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Change your password</h2><p>All other sessions will be signed out after the change.</p></div></div>
        <form class="security-create-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= securityEscape($csrfToken) ?>"><input type="hidden" name="operation" value="change_password">
            <label>Current password<input class="operation-input" type="password" name="current_password" autocomplete="current-password" required></label>
            <label>New password<input class="operation-input" type="password" name="new_password" minlength="12" maxlength="255" autocomplete="new-password" required></label>
            <label>Confirm new password<input class="operation-input" type="password" name="confirm_password" minlength="12" maxlength="255" autocomplete="new-password" required></label>
            <button class="operation-button" type="submit">Update password</button>
        </form>
    </section>

    <?php if ($canManageAccounts): ?>
    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Active sessions</h2><p>Sessions expire after 30 minutes idle or 12 hours total.</p></div></div>
        <div class="operations-table-wrap"><table class="operations-table">
            <thead><tr><th>Account</th><th>Device</th><th>IP address</th><th>Started</th><th>Last seen</th><th>Action</th></tr></thead>
            <tbody><?php foreach ($sessions as $session): ?><tr>
                <td><?= securityEscape((string) $session['username']) ?><?= hash_equals(hash('sha256', session_id()), (string) $session['session_hash']) ? ' · This session' : '' ?></td>
                <td><?= securityEscape((string) $session['user_agent']) ?></td><td><?= securityEscape((string) ($session['ip_address'] ?? '')) ?></td>
                <td><?= securityEscape((string) $session['created_at']) ?></td><td><?= securityEscape((string) $session['last_seen_at']) ?></td>
                <td><form method="post"><input type="hidden" name="csrf_token" value="<?= securityEscape($csrfToken) ?>"><input type="hidden" name="operation" value="revoke_session"><input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>"><button class="operation-button operation-button-danger" type="submit">Revoke</button></form></td>
            </tr><?php endforeach; ?><?php if (!$sessions): ?><tr><td colspan="6">No active sessions.</td></tr><?php endif; ?></tbody>
        </table></div>
    </section>
    <?php endif; ?>

    <?php if ($canViewAudit): ?>
    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Security events</h2><p>Recent sign-ins, password changes, account updates, and session revocations.</p></div></div>
        <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Action</th><th>By</th><th>Account</th><th>IP</th><th>Time</th></tr></thead><tbody>
            <?php if ($securityAudit): while ($event = $securityAudit->fetch_assoc()): ?><tr><td><?= securityEscape(str_replace('_', ' ', (string) $event['action'])) ?></td><td><?= securityEscape((string) $event['actor_username']) ?></td><td><?= (int) ($event['target_admin_id'] ?? 0) ?: '—' ?></td><td><?= securityEscape((string) ($event['ip_address'] ?? '')) ?></td><td><?= securityEscape((string) $event['created_at']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="5">No security events.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <section class="operations-section">
        <div class="operations-section-heading"><div><h2>Product activity</h2><p>Recent product additions, edits, deletions, and the account responsible.</p></div></div>
        <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Action</th><th>Product</th><th>By</th><th>Time</th></tr></thead><tbody>
            <?php if ($productAudit): while ($event = $productAudit->fetch_assoc()): ?><tr><td><?= securityEscape(ucfirst((string) $event['action'])) ?></td><td><?= securityEscape((string) $event['product_name']) ?></td><td><?= securityEscape((string) $event['actor']) ?></td><td><?= securityEscape((string) $event['created_at']) ?></td></tr><?php endwhile; else: ?><tr><td colspan="4">No product activity.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <?php endif; ?>
</main>
<script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js') ?>"></script>
</body>
</html>
