<?php
declare(strict_types=1);

// Direct access route for admin
if (isset($_GET['admin']) || (isset($_GET['page']) && $_GET['page'] === 'admin')) {
    header('Location: admin/index.php');
    exit;
}

// Redirect root requests to the storefront home page
header('Location: home/index.php');
exit;
