<?php
// Redirect root checkout requests to the home checkout page preserving parameters
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: home/checkout.php' . $queryString);
exit;

