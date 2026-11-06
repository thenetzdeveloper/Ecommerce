<?php
if (!function_exists('renderAdminPrimaryNavigation')) {
    function renderAdminPrimaryNavigation(array $admin, string $activeSection): void
    {
        global $conn;
        $adminStoreSettings = function_exists('getStoreSettings') ? getStoreSettings($conn ?? null) : [];
        $adminStoreName = !empty($adminStoreSettings['store_name']) ? (string)$adminStoreSettings['store_name'] : 'SN STORE';
        $adminStoreLogo = $adminStoreSettings['store_logo_url'] ?? '';
        $adminResolvedLogo = function_exists('resolveStoreAssetUrl') ? resolveStoreAssetUrl($adminStoreLogo, 'admin') : '../' . ltrim($adminStoreLogo, './\\');
        $hasCustomLogo = !empty($adminStoreLogo);

        $inAdminDir = (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'admin');
        $adminPrefix = $inAdminDir ? '' : '../admin/';
        $csPrefix = $inAdminDir ? '../customer-service/' : '';

        $can = static fn(string $permission): bool => adminHasPermission($admin, $permission);
        $settingsPermissions = ['store_settings', 'promotions', 'notifications', 'security', 'backup'];
        $canOpenSettings = false;
        foreach ($settingsPermissions as $permission) {
            $canOpenSettings = $canOpenSettings || $can($permission);
        }
        $items = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'url' => $adminPrefix . 'index.php?view=dashboard', 'visible' => true],
            ['key' => 'products', 'label' => 'Products', 'icon' => 'fa-boxes-stacked', 'url' => $can('products') ? ($adminPrefix . 'index.php?view=products') : ($adminPrefix . 'operations.php?view=inventory'), 'visible' => $can('products') || $can('inventory')],
            ['key' => 'orders', 'label' => 'Orders', 'icon' => 'fa-receipt', 'url' => $adminPrefix . 'operations.php?view=orders', 'visible' => $can('orders')],
            ['key' => 'customers', 'label' => 'Customers', 'icon' => 'fa-users', 'url' => $can('customers') ? ($adminPrefix . 'operations.php?view=customers') : ($csPrefix . 'dashboard.php'), 'visible' => $can('customers') || $can('support')],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'fa-chart-line', 'url' => $adminPrefix . 'operations.php?view=reports', 'visible' => $can('reports')],
        ];
        echo '<nav class="admin-primary-nav" aria-label="Admin sections">';
        echo '<a class="admin-primary-brand" href="' . htmlspecialchars($adminPrefix . 'index.php?view=dashboard', ENT_QUOTES, 'UTF-8') . '">';
        if ($hasCustomLogo) {
            echo '<span class="admin-brand-logo-frame"><img src="' . htmlspecialchars($adminResolvedLogo, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($adminStoreName, ENT_QUOTES, 'UTF-8') . '" class="admin-brand-logo-img" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'grid\';"><i class="fas fa-store admin-brand-logo-fallback" style="display:none;"></i></span>';
        } else {
            echo '<i class="fas fa-store"></i>';
        }
        echo '<span>' . htmlspecialchars($adminStoreName, ENT_QUOTES, 'UTF-8') . '<small>Admin</small></span></a>';
        echo '<span class="admin-primary-label">Workspace</span>';
        foreach ($items as $item) {
            if (!$item['visible']) {
                continue;
            }
            $active = $activeSection === $item['key'] ? ' is-active' : '';
            echo '<a class="admin-primary-nav-link' . $active . '" href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '"><i class="fas ' . $item['icon'] . '" aria-hidden="true"></i><span>' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span></a>';
        }
        if ($canOpenSettings) {
            $settingsActive = in_array($activeSection, ['settings', 'promotions', 'homepage', 'notifications', 'security', 'backup', 'translations'], true) ? ' is-active' : '';
            echo '<a class="admin-primary-nav-link' . $settingsActive . '" href="' . htmlspecialchars($adminPrefix . 'store-management.php?view=store', ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-gear" aria-hidden="true"></i><span>Settings</span></a>';
            echo '<div class="admin-settings-subnav">';
            if ($can('promotions')) {
                $active = $activeSection === 'promotions' ? ' is-active' : '';
                echo '<a class="admin-settings-link' . $active . '" href="' . htmlspecialchars($adminPrefix . 'store-management.php?view=promotions', ENT_QUOTES, 'UTF-8') . '">Promotions</a>';
            }
            if ($can('store_settings')) {
                $active = $activeSection === 'homepage' ? ' is-active' : '';
                echo '<a class="admin-settings-link' . $active . '" href="' . htmlspecialchars($adminPrefix . 'store-management.php?view=homepage', ENT_QUOTES, 'UTF-8') . '">Homepage</a>';
            }
            if ($can('store_settings')) {
                $active = $activeSection === 'translations' ? ' is-active' : '';
                echo '<a class="admin-settings-link' . $active . '" href="' . htmlspecialchars($adminPrefix . 'store-management.php?view=translations', ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-file-pen" style="margin-right: 4px;"></i>Page Notes &amp; Text (ខ្លឹមសារ &amp; Note)</a>';
            }
            if ($can('notifications')) {
                $active = $activeSection === 'notifications' ? ' is-active' : '';
                echo '<a class="admin-settings-link' . $active . '" href="' . htmlspecialchars($adminPrefix . 'store-management.php?view=notifications', ENT_QUOTES, 'UTF-8') . '">Notifications</a>';
            }
            if ($can('security')) {
                $active = $activeSection === 'security' ? ' is-active' : '';
                echo '<a class="admin-settings-link' . $active . '" href="' . htmlspecialchars($adminPrefix . 'security.php', ENT_QUOTES, 'UTF-8') . '">Staff &amp; Security</a>';
            } elseif (isset($admin['id'])) {
                echo '<a class="admin-settings-link" href="' . htmlspecialchars($adminPrefix . 'security.php', ENT_QUOTES, 'UTF-8') . '">Account Security</a>';
            }
            if ($can('backup')) {
                $active = $activeSection === 'backup' ? ' is-active' : '';
                echo '<a class="admin-settings-link' . $active . '" href="' . htmlspecialchars($adminPrefix . 'backup.php', ENT_QUOTES, 'UTF-8') . '">Database Backup</a>';
            }
            if ($can('store_settings')) {
                $active = $activeSection === 'settings' ? ' is-active' : '';
                echo '<a class="admin-settings-link' . $active . '" href="' . htmlspecialchars($adminPrefix . 'store-management.php?view=store', ENT_QUOTES, 'UTF-8') . '">Store Settings</a>';
            }
            echo '</div>';
        }
        echo '<form class="admin-sidebar-logout" action="' . htmlspecialchars($adminPrefix . 'logout.php', ENT_QUOTES, 'UTF-8') . '" method="post"><input type="hidden" name="csrf_token" value="' . htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8') . '"><button type="submit"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Log out</span></button></form>';
        echo '</nav>';
    }
}
