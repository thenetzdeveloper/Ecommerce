<?php
if (ob_get_level() === 0) {
    ob_start();
}
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
require_once __DIR__ . '/../api/i18n.php';
require_once __DIR__ . '/../api/user-auth.php';
require_once __DIR__ . '/../api/db.php';
$storeLang = getStoreLanguage();
$navUser = isset($conn) && $conn instanceof mysqli ? getCurrentUser($conn) : null;

$navbarStoreSettings = function_exists('getStoreSettings') ? getStoreSettings($conn ?? null) : [];
$nav_home_url = $nav_home_url ?? 'index.php';
$nav_about_url = $nav_about_url ?? 'index.php?page=about';
$nav_support_url = $nav_support_url ?? '../customer-service/index.php';
$nav_payments_url = $nav_payments_url ?? 'checkout.php';
$nav_brand_logo = !empty($nav_brand_logo) ? $nav_brand_logo : ($navbarStoreSettings['store_logo_url'] ?? '');
$nav_store_name = (!empty($nav_store_name) && $nav_store_name !== '4K STORE') ? $nav_store_name : ($navbarStoreSettings['store_name'] ?? 'SN STORE');
$nav_contact_url = (!empty($nav_contact_url) && $nav_contact_url !== 'https://t.me/netstillloser') ? $nav_contact_url : ($navbarStoreSettings['store_contact_url'] ?? 'https://t.me/remsonich');
$nav_active = $nav_active ?? 'home';

$nav_auth_dir = dirname($nav_home_url) === '.' ? '' : dirname($nav_home_url) . '/';
$nav_login_url = $nav_auth_dir . 'login.php';
$nav_profile_url = $nav_auth_dir . 'profile.php';
$nav_logout_url = $nav_auth_dir . 'logout.php';

$nav_link = static function ($page, $label, $icon, $url) use ($nav_active) {
    $active_class = $nav_active === $page ? ' active' : '';
    echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="nav-tab-btn' . $active_class . '"><i class="fas ' . $icon . '"></i> ' . $label . '</a>';
};
?>
<header class="cyber-nav-container">
    <nav class="cyber-island-nav">
        <a href="<?php echo htmlspecialchars($nav_home_url, ENT_QUOTES, 'UTF-8'); ?>" class="brand-emblem">
<?php
$resolvedNavLogo = function_exists('resolveStoreAssetUrl') ? resolveStoreAssetUrl($nav_brand_logo) : $nav_brand_logo;
$resolvedFallbackLogo = function_exists('resolveStoreAssetUrl') ? resolveStoreAssetUrl('') : './logo/logome.png';
?>
            <div class="brand-hologram-cube">
                <img src="<?php echo htmlspecialchars($resolvedNavLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nav_store_name, ENT_QUOTES, 'UTF-8'); ?>" onerror="this.src='<?php echo htmlspecialchars($resolvedFallbackLogo, ENT_QUOTES, 'UTF-8'); ?>'">
            </div>
            <div class="brand-titles">
                <h1><?php echo htmlspecialchars($nav_store_name, ENT_QUOTES, 'UTF-8'); ?> <span class="dna-badge">V.1.2</span></h1>
                <span><?php echo htmlspecialchars(t('nav_brand_tagline', 'ហាងសម្លៀកបំពាក់ និងម៉ូដ', 'Fashion & Apparel'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </a>

        <div class="nav-hologram-links">
            <?php
            $nav_link('home', t('nav_home', 'ទំព័រដើម', 'Home'), 'fa-home', $nav_home_url);
            $nav_link('about', t('nav_about', 'អំពីយើង', 'About Us'), 'fa-circle-info', $nav_about_url);
            $nav_link('support', t('nav_support', 'ជំនួយការ', 'Support'), 'fa-headset', $nav_support_url);
            $nav_link('payments', t('nav_payments', 'ការទូទាត់', 'Payments'), 'fa-credit-card', $nav_payments_url);
            ?>
            <div class="theme-mode-control theme-mode-nav">
                <label class="theme-mode-label" for="themeModeSelect">Theme</label>
                <select id="themeModeSelect" data-theme-mode aria-label="Color theme">
                    <option value="system">System</option>
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
            </div>
            <?php echo renderLanguageSwitcher($storeLang, 'theme-mode-nav'); ?>
        </div>

        <div class="nav-actions">
            <!-- Shopping Cart Trigger Button -->
            <button type="button" class="fashion-nav-btn nav-cart-trigger" id="navbarCartBtn" aria-label="<?php echo t('nav_cart', 'កន្ត្រក', 'Cart'); ?>" style="position: relative;">
                <i class="fas fa-shopping-bag"></i>
                <span class="nav-action-label"><?php echo t('nav_cart', 'កន្ត្រក', 'Cart'); ?></span>
                <span class="nav-cart-badge" id="navCartCount">0</span>
            </button>

            <!-- User Auth Profile Dropdown / Login Button -->
            <?php if ($navUser): ?>
                <div class="user-menu-dropdown-wrap">
                    <button type="button" class="fashion-nav-btn user-nav-btn" id="userMenuToggleBtn" aria-label="User Account" aria-expanded="false" aria-haspopup="true">
                        <?php if (!empty($navUser['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($navUser['profile_picture'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="nav-user-avatar-img" referrerpolicy="no-referrer">
                        <?php else: ?>
                            <i class="fas fa-circle-user nav-user-avatar-icon"></i>
                        <?php endif; ?>
                        <span class="nav-user-name"><?= htmlspecialchars($navUser['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <i class="fas fa-chevron-down nav-user-caret"></i>
                    </button>
                    <div class="user-dropdown-menu" id="userDropdownMenu" role="menu" aria-label="User Account Menu">
                        <div class="user-dropdown-header">
                            <div class="user-dropdown-name"><?= htmlspecialchars($navUser['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="user-dropdown-email"><?= htmlspecialchars($navUser['email'] ?: $navUser['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="user-dropdown-body">
                            <a href="<?= htmlspecialchars($nav_profile_url . '?tab=info', ENT_QUOTES, 'UTF-8'); ?>" class="user-dropdown-link" role="menuitem">
                                <i class="fas fa-user-pen"></i>
                                <span><?= t('nav_my_profile', 'គណនីរបស់ខ្ញុំ', 'My Profile'); ?></span>
                            </a>
                            <a href="<?= htmlspecialchars($nav_profile_url . '?tab=orders', ENT_QUOTES, 'UTF-8'); ?>" class="user-dropdown-link" role="menuitem">
                                <i class="fas fa-box-archive"></i>
                                <span><?= t('nav_my_orders', 'ប្រវត្តិបញ្ជាទិញ', 'My Orders'); ?></span>
                            </a>
                            <div class="user-dropdown-divider"></div>
                            <a href="<?= htmlspecialchars($nav_logout_url, ENT_QUOTES, 'UTF-8'); ?>" class="user-dropdown-link user-dropdown-logout" role="menuitem">
                                <i class="fas fa-right-from-bracket"></i>
                                <span><?= t('nav_logout', 'ចាកចេញ', 'Logout'); ?></span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($nav_login_url, ENT_QUOTES, 'UTF-8'); ?>" class="fashion-nav-btn nav-signin-btn" aria-label="<?php echo t('nav_login', 'ចូលគណនី', 'Sign In'); ?>">
                    <i class="fas fa-circle-user nav-signin-icon"></i>
                    <span class="nav-action-label"><?php echo t('nav_login', 'ចូលគណនី', 'Sign In'); ?></span>
                </a>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($nav_contact_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="fashion-nav-btn nav-telegram-btn" aria-label="Telegram">
                <i class="fab fa-telegram-plane"></i>
                <span class="nav-action-label">Telegram</span>
            </a>
            <button class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
</header>

<div id="mobileMenuDrawer" class="mobile-menu-drawer" hidden>
    <div class="mobile-menu-links">
        <?php if ($navUser): ?>
            <div style="padding: 12px 14px; background: #faf8f5; border: 1px solid #ded5cb; border-radius: 8px; margin-bottom: 8px;">
                <div style="font-weight: 700; font-size: 13.5px; color: #203f3a;"><?= htmlspecialchars($navUser['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div style="font-size: 11.5px; color: #7d756d;"><?= htmlspecialchars($navUser['email'] ?: $navUser['username'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <a href="<?= htmlspecialchars($nav_profile_url . '?tab=info', ENT_QUOTES, 'UTF-8'); ?>" class="nav-tab-btn"><i class="fas fa-user-pen"></i> <?= t('nav_my_profile', 'គណនីរបស់ខ្ញុំ', 'My Profile'); ?></a>
            <a href="<?= htmlspecialchars($nav_profile_url . '?tab=orders', ENT_QUOTES, 'UTF-8'); ?>" class="nav-tab-btn"><i class="fas fa-box-archive"></i> <?= t('nav_my_orders', 'ប្រវត្តិបញ្ជាទិញ', 'My Orders'); ?></a>
        <?php else: ?>
            <a href="<?= htmlspecialchars($nav_login_url, ENT_QUOTES, 'UTF-8'); ?>" class="nav-tab-btn"><i class="fas fa-circle-user"></i> <?= t('nav_login', 'ចូលគណនី', 'Sign In'); ?> / <?= t('nav_register', 'ចុះឈ្មោះ', 'Register'); ?></a>
        <?php endif; ?>

        <?php
        $nav_link('home', t('nav_home', 'ទំព័រដើម', 'Home'), 'fa-home', $nav_home_url);
        $nav_link('about', t('nav_about', 'អំពីយើង', 'About Us'), 'fa-circle-info', $nav_about_url);
        $nav_link('support', t('nav_support', 'ជំនួយការ', 'Support'), 'fa-headset', $nav_support_url);
        $nav_link('payments', t('nav_payments', 'ការទូទាត់', 'Payments'), 'fa-credit-card', $nav_payments_url);
        ?>
        <button type="button" class="nav-tab-btn mobile-cart-trigger" id="mobileCartBtn" style="width: 100%; text-align: left; justify-content: flex-start;">
            <i class="fas fa-shopping-bag"></i> <?php echo t('nav_cart', 'កន្ត្រក', 'Cart'); ?> (<span id="mobileCartCount">0</span>)
        </button>

        <?php if ($navUser): ?>
            <a href="<?= htmlspecialchars($nav_logout_url, ENT_QUOTES, 'UTF-8'); ?>" class="nav-tab-btn" style="color: #b91c1c;"><i class="fas fa-right-from-bracket"></i> <?= t('nav_logout', 'ចាកចេញ', 'Logout'); ?></a>
        <?php endif; ?>

        <div class="theme-mode-control theme-mode-mobile">
            <label class="theme-mode-label" for="mobileThemeModeSelect">Theme</label>
            <select id="mobileThemeModeSelect" data-theme-mode aria-label="Color theme">
                <option value="system">System</option>
                <option value="light">Light</option>
                <option value="dark">Dark</option>
            </select>
        </div>
        <div style="padding: 6px 0;">
            <?php echo renderLanguageSwitcher($storeLang, 'lang-switcher-mobile'); ?>
        </div>
    </div>
</div>

<script>
    const mobileToggle = document.getElementById("mobileNavToggle");
    const mobileDrawer = document.getElementById("mobileMenuDrawer");
    if (mobileToggle && mobileDrawer) {
        mobileToggle.addEventListener("click", () => {
            const isOpen = mobileToggle.getAttribute("aria-expanded") === "true";
            mobileDrawer.hidden = isOpen;
            mobileToggle.setAttribute("aria-expanded", String(!isOpen));
        });
    }

    // User Profile Dropdown Toggle
    const userMenuToggle = document.getElementById("userMenuToggleBtn");
    const userDropdownMenu = document.getElementById("userDropdownMenu");
    if (userMenuToggle && userDropdownMenu) {
        const toggleDropdown = (open) => {
            const shouldOpen = open !== undefined ? open : !userDropdownMenu.classList.contains("is-open");
            userDropdownMenu.classList.toggle("is-open", shouldOpen);
            userMenuToggle.classList.toggle("is-active", shouldOpen);
            userMenuToggle.setAttribute("aria-expanded", String(shouldOpen));
        };

        userMenuToggle.addEventListener("click", (e) => {
            e.stopPropagation();
            toggleDropdown();
        });

        document.addEventListener("click", (e) => {
            if (!userDropdownMenu.contains(e.target) && !userMenuToggle.contains(e.target)) {
                toggleDropdown(false);
            }
        });

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && userDropdownMenu.classList.contains("is-open")) {
                toggleDropdown(false);
                userMenuToggle.focus();
            }
        });
    }
</script>
