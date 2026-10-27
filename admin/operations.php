<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/admin-auth.php';
require_once __DIR__ . '/primary-navigation.php';

if (!ensureAdminSecuritySchema($conn)) {
    http_response_code(500);
    exit('Could not prepare admin security. Check the server error log.');
}
if (!adminStartSession()) {
    http_response_code(500);
    exit('Could not start the admin session.');
}
$currentAdmin = adminRequireLogin($conn);

$adminUsername = (string) $currentAdmin['username'];
$csrfToken = adminCsrfToken();
header('Cache-Control: no-store, private');

if (!ensureProductStockColumn($conn) || !ensureSalesAnalyticsSchema($conn) || !ensureAdminOperationsSchema($conn) || !ensureStoreManagementSchema($conn)) {
    http_response_code(500);
    exit('Could not prepare admin operations. Check the server error log.');
}
$currencySymbol = storeCurrencySymbol($conn);

function operationsEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function operationsRedirect(string $view, string $message): void
{
    $_SESSION['operations_flash'] = $message;
    header('Location: operations.php?view=' . rawurlencode($view));
    exit;
}

function operationsValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

$view = $_GET['view'] ?? 'orders';
if (!is_string($view) || !in_array($view, ['orders', 'inventory', 'customers', 'reports'], true)) {
    $view = 'orders';
}
$viewPermissions = ['orders' => 'orders', 'inventory' => 'inventory', 'customers' => 'customers', 'reports' => 'reports'];
if (!adminHasPermission($currentAdmin, $viewPermissions[$view])) {
    $availableViews = array_keys(array_filter($viewPermissions, static fn(string $permission): bool => adminHasPermission($currentAdmin, $permission)));
    if (!$availableViews) {
        http_response_code(403);
        exit('You do not have permission to access operations.');
    }
    $view = $availableViews[0];
}
$errorMessage = '';
$flashMessage = is_string($_SESSION['operations_flash'] ?? null) ? $_SESSION['operations_flash'] : '';
unset($_SESSION['operations_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrf = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedCsrf) || !hash_equals($csrfToken, $submittedCsrf)) {
        $errorMessage = 'The form expired. Reload the page and try again.';
    } else {
        $operation = $_POST['operation'] ?? '';
        $operationPermissions = [
            'verify_payment' => 'orders',
            'reject_payment' => 'orders',
            'record_refund' => 'orders',
            'change_order_status' => 'orders',
            'adjust_stock' => 'inventory',
            'set_threshold' => 'inventory',
            'save_customer' => 'customers',
        ];
        if ($operation !== 'logout' && (!isset($operationPermissions[$operation]) || !adminHasPermission($currentAdmin, $operationPermissions[$operation]))) {
            $errorMessage = 'You do not have permission to perform this action.';
        } elseif ($operation === 'verify_payment') {
            $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
            $transactionId = is_string($_POST['transaction_id'] ?? null) ? trim($_POST['transaction_id']) : '';
            $amountInput = is_string($_POST['amount'] ?? null) ? trim($_POST['amount']) : '';
            $amountValid = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amountInput) === 1;
            if (!$orderId || $orderId < 1 || $transactionId === '' || strlen($transactionId) > 120 || !$amountValid) {
                $errorMessage = 'Enter a valid transaction ID and payment amount.';
            } elseif (!$conn->begin_transaction()) {
                $errorMessage = 'Could not start payment verification.';
            } else {
                $lookup = $conn->prepare('SELECT status, payment_status, unit_price, quantity, discount_amount FROM sales_orders WHERE id = ? FOR UPDATE');
                if (!$lookup) {
                    $conn->rollback();
                    error_log('Could not prepare payment verification lookup: ' . $conn->error);
                    $errorMessage = 'Could not load the order for verification.';
                } else {
                    $lookup->bind_param('i', $orderId);
                    if (!$lookup->execute()) {
                        $lookup->close();
                        $conn->rollback();
                        error_log('Could not execute payment verification lookup.');
                        $errorMessage = 'Could not load the order for verification.';
                    } else {
                        $order = $lookup->get_result()->fetch_assoc();
                        $lookup->close();
                        $amount = (float) $amountInput;
                        $expectedAmount = $order ? max(0, (float) $order['unit_price'] * (int) $order['quantity'] - (float) $order['discount_amount']) : 0.0;
                        if (
                            !$order
                            || $order['status'] === 'Cancelled'
                            || in_array($order['payment_status'], ['verified', 'partially_refunded', 'refunded'], true)
                            || (int) round($amount * 100) !== (int) round($expectedAmount * 100)
                        ) {
                            $conn->rollback();
                            $errorMessage = 'Payment cannot be verified: the order must be active, not already paid, and the amount must equal the order total.';
                        } else {
                            $update = $conn->prepare("UPDATE sales_orders SET status = 'Paid', payment_status = 'verified', payment_transaction_id = ?, paid_amount = ? WHERE id = ?");
                            if (!$update) {
                                $conn->rollback();
                                error_log('Could not prepare payment verification update: ' . $conn->error);
                                $errorMessage = 'Could not save payment verification.';
                            } else {
                                $update->bind_param('sdi', $transactionId, $amount, $orderId);
                                $saved = $update->execute();
                                $update->close();
                                $paymentHistorySaved = $saved && recordSalesOrderHistory(
                                    $conn,
                                    $orderId,
                                    'payment_verified',
                                    (string) $order['payment_status'],
                                    'verified',
                                    'Manually verified by an admin. Receipt upload alone is not proof of payment.',
                                    $transactionId,
                                    $amount,
                                    $adminUsername
                                );
                                $statusHistorySaved = $paymentHistorySaved && recordSalesOrderHistory(
                                    $conn,
                                    $orderId,
                                    'status_changed',
                                    (string) $order['status'],
                                    'Paid',
                                    'Payment manually verified.',
                                    $transactionId,
                                    $amount,
                                    $adminUsername
                                );
                                $historySaved = $paymentHistorySaved && $statusHistorySaved;
                                if (!$historySaved || !$conn->commit()) {
                                    $conn->rollback();
                                    $errorMessage = 'Payment state and audit history could not be saved.';
                                } else {
                                    operationsRedirect('orders', 'Payment verified and order marked Paid.');
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($operation === 'reject_payment') {
            $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
            $note = is_string($_POST['note'] ?? null) ? trim($_POST['note']) : '';
            if (!$orderId || $orderId < 1 || $note === '' || strlen($note) > 500) {
                $errorMessage = 'Provide an order and a rejection reason (up to 500 characters).';
            } elseif (!$conn->begin_transaction()) {
                $errorMessage = 'Could not start payment review.';
            } else {
                $lookup = $conn->prepare('SELECT status, payment_status FROM sales_orders WHERE id = ? FOR UPDATE');
                if (!$lookup) {
                    $conn->rollback();
                    error_log('Could not prepare payment rejection lookup: ' . $conn->error);
                    $errorMessage = 'Could not load the order for review.';
                } else {
                    $lookup->bind_param('i', $orderId);
                    if (!$lookup->execute()) {
                        $lookup->close();
                        $conn->rollback();
                        $errorMessage = 'Could not load the order for review.';
                    } else {
                        $order = $lookup->get_result()->fetch_assoc();
                        $lookup->close();
                        if (!$order || $order['status'] === 'Cancelled' || in_array($order['payment_status'], ['verified', 'partially_refunded', 'refunded'], true)) {
                            $conn->rollback();
                            $errorMessage = 'This payment cannot be rejected in its current state.';
                        } else {
                            $update = $conn->prepare("UPDATE sales_orders SET payment_status = 'rejected' WHERE id = ?");
                            if (!$update) {
                                $conn->rollback();
                                error_log('Could not prepare payment rejection update: ' . $conn->error);
                                $errorMessage = 'Could not save the payment rejection.';
                            } else {
                                $update->bind_param('i', $orderId);
                                $saved = $update->execute();
                                $update->close();
                                $historySaved = $saved && recordSalesOrderHistory($conn, $orderId, 'payment_rejected', (string) $order['payment_status'], 'rejected', $note, null, null, $adminUsername);
                                if (!$historySaved || !$conn->commit()) {
                                    $conn->rollback();
                                    $errorMessage = 'Payment rejection and audit history could not be saved.';
                                } else {
                                    operationsRedirect('orders', 'Payment rejection recorded.');
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($operation === 'record_refund') {
            $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
            $transactionId = is_string($_POST['transaction_id'] ?? null) ? trim($_POST['transaction_id']) : '';
            $amountInput = is_string($_POST['amount'] ?? null) ? trim($_POST['amount']) : '';
            $amountValid = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amountInput) === 1;
            if (!$orderId || $orderId < 1 || $transactionId === '' || strlen($transactionId) > 120 || !$amountValid || (float) $amountInput <= 0) {
                $errorMessage = 'Enter a valid refund transaction ID and a positive amount.';
            } elseif (!$conn->begin_transaction()) {
                $errorMessage = 'Could not start refund recording.';
            } else {
                $lookup = $conn->prepare("SELECT payment_status, paid_amount, (SELECT COALESCE(SUM(amount), 0) FROM sales_order_history WHERE order_id = sales_orders.id AND event_type = 'refund_recorded') AS refunded_total FROM sales_orders WHERE id = ? FOR UPDATE");
                if (!$lookup) {
                    $conn->rollback();
                    error_log('Could not prepare refund lookup: ' . $conn->error);
                    $errorMessage = 'Could not load the order for refund recording.';
                } else {
                    $lookup->bind_param('i', $orderId);
                    if (!$lookup->execute()) {
                        $lookup->close();
                        $conn->rollback();
                        $errorMessage = 'Could not load the order for refund recording.';
                    } else {
                        $order = $lookup->get_result()->fetch_assoc();
                        $lookup->close();
                        $amount = (float) $amountInput;
                        $availableToRefund = $order ? (float) $order['paid_amount'] - (float) $order['refunded_total'] : 0.0;
                        if (!$order || !in_array($order['payment_status'], ['verified', 'partially_refunded'], true) || $amount > $availableToRefund + 0.00001) {
                            $conn->rollback();
                            $errorMessage = 'Refund amount exceeds the verified amount or the order has no refundable payment.';
                        } else {
                            $fullRefund = (int) round(($amount + (float) $order['refunded_total']) * 100) >= (int) round((float) $order['paid_amount'] * 100);
                            $newPaymentStatus = $fullRefund ? 'refunded' : 'partially_refunded';
                            $update = $conn->prepare('UPDATE sales_orders SET payment_status = ? WHERE id = ?');
                            if (!$update) {
                                $conn->rollback();
                                error_log('Could not prepare refund status update: ' . $conn->error);
                                $errorMessage = 'Could not save the refund record.';
                            } else {
                                $update->bind_param('si', $newPaymentStatus, $orderId);
                                $saved = $update->execute();
                                $update->close();
                                $historySaved = $saved && recordSalesOrderHistory($conn, $orderId, 'refund_recorded', (string) $order['payment_status'], $newPaymentStatus, 'Refund recorded by admin; transfer is handled outside this app.', $transactionId, $amount, $adminUsername);
                                if (!$historySaved || !$conn->commit()) {
                                    $conn->rollback();
                                    $errorMessage = 'Refund state and audit history could not be saved.';
                                } else {
                                    operationsRedirect('orders', 'Refund recorded. The actual refund must be completed through the payment provider.');
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($operation === 'change_order_status') {
            $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
            $newStatus = $_POST['status'] ?? '';
            if (!$orderId || $orderId < 1 || !is_string($newStatus) || !in_array($newStatus, ['Processing', 'Completed', 'Cancelled'], true)) {
                $errorMessage = 'Choose a valid order status.';
            } elseif (!$conn->begin_transaction()) {
                $errorMessage = 'Could not start the order update.';
            } else {
                $lookup = $conn->prepare('SELECT status, payment_status, product_id, product_name, quantity, inventory_reserved FROM sales_orders WHERE id = ? FOR UPDATE');
                if (!$lookup) {
                    $conn->rollback();
                    error_log('Could not prepare order status lookup: ' . $conn->error);
                    $errorMessage = 'Could not load the order.';
                } else {
                    $lookup->bind_param('i', $orderId);
                    if (!$lookup->execute()) {
                        $lookup->close();
                        $conn->rollback();
                        $errorMessage = 'Could not load the order.';
                    } else {
                        $order = $lookup->get_result()->fetch_assoc();
                        $lookup->close();
                        $paid = in_array($order['payment_status'] ?? '', ['verified', 'partially_refunded', 'refunded'], true);
                        if (
                            !$order
                            || $order['status'] === 'Cancelled'
                            || $order['status'] === $newStatus
                            || (in_array($newStatus, ['Processing', 'Completed'], true) && !$paid)
                        ) {
                            $conn->rollback();
                            $errorMessage = 'Order status cannot be changed to that value. Verify payment before processing or completing an order.';
                        } else {
                            $inventorySaved = true;
                            if ($newStatus === 'Cancelled' && (int) $order['inventory_reserved'] === 1 && $order['product_id'] !== null) {
                                $productId = (int) $order['product_id'];
                                $productLookup = $conn->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
                                if (!$productLookup) {
                                    $inventorySaved = false;
                                    error_log('Could not prepare stock restoration for cancellation: ' . $conn->error);
                                } else {
                                    $productLookup->bind_param('i', $productId);
                                    if (!$productLookup->execute()) {
                                        $inventorySaved = false;
                                    } else {
                                        $stockRow = $productLookup->get_result()->fetch_assoc();
                                        if ($stockRow) {
                                            $stockBefore = (int) $stockRow['stock'];
                                            $quantity = (int) $order['quantity'];
                                            $stockAfter = $stockBefore + $quantity;
                                            if ($stockAfter > 2147483647) {
                                                $inventorySaved = false;
                                            }
                                            $stockUpdate = $conn->prepare('UPDATE products SET stock = ? WHERE id = ?');
                                            if (!$inventorySaved) {
                                                error_log('Cancelled order stock restoration exceeded the supported product stock range.');
                                            } elseif (!$stockUpdate) {
                                                $inventorySaved = false;
                                            } else {
                                                $stockUpdate->bind_param('ii', $stockAfter, $productId);
                                                $inventorySaved = $stockUpdate->execute();
                                                $stockUpdate->close();
                                                if ($inventorySaved) {
                                                    $inventorySaved = recordInventoryAdjustment($conn, $productId, (string) $order['product_name'], $quantity, $stockBefore, $stockAfter, 'Cancelled order #' . $orderId, $adminUsername);
                                                }
                                            }
                                        } else {
                                            error_log('Cancelled order product no longer exists; no stock row can be restored.');
                                        }
                                    }
                                    $productLookup->close();
                                }
                            }

                            $update = $inventorySaved ? $conn->prepare('UPDATE sales_orders SET status = ?, inventory_reserved = IF(? = \'Cancelled\', 0, inventory_reserved) WHERE id = ?') : false;
                            if (!$update) {
                                $conn->rollback();
                                if ($inventorySaved) {
                                    error_log('Could not prepare order status update: ' . $conn->error);
                                }
                                $errorMessage = 'Could not save the order status.';
                            } else {
                                $oldStatus = (string) $order['status'];
                                $update->bind_param('ssi', $newStatus, $newStatus, $orderId);
                                $saved = $update->execute();
                                $update->close();
                                $historySaved = $saved && recordSalesOrderHistory($conn, $orderId, 'status_changed', $oldStatus, $newStatus, null, null, null, $adminUsername);
                                if (!$historySaved || !$conn->commit()) {
                                    $conn->rollback();
                                    $errorMessage = 'Order status and audit history could not be saved.';
                                } else {
                                    operationsRedirect('orders', 'Order status updated.');
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($operation === 'adjust_stock') {
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            $deltaInput = is_string($_POST['change_amount'] ?? null) ? trim($_POST['change_amount']) : '';
            $reason = is_string($_POST['reason'] ?? null) ? trim($_POST['reason']) : '';
            $delta = filter_var($deltaInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => -2147483647, 'max_range' => 2147483647]]);
            if (!$productId || $productId < 1 || $delta === false || $delta === 0 || $reason === '' || strlen($reason) > 255) {
                $errorMessage = 'Enter a non-zero whole-number stock adjustment and a reason (up to 255 characters).';
            } elseif (!$conn->begin_transaction()) {
                $errorMessage = 'Could not start the inventory adjustment.';
            } else {
                $lookup = $conn->prepare('SELECT name, stock, low_stock_threshold FROM products WHERE id = ? FOR UPDATE');
                if (!$lookup) {
                    $conn->rollback();
                    error_log('Could not prepare inventory adjustment lookup: ' . $conn->error);
                    $errorMessage = 'Could not load the product.';
                } else {
                    $lookup->bind_param('i', $productId);
                    if (!$lookup->execute()) {
                        $lookup->close();
                        $conn->rollback();
                        $errorMessage = 'Could not load the product.';
                    } else {
                        $product = $lookup->get_result()->fetch_assoc();
                        $lookup->close();
                        $stockBefore = $product ? (int) $product['stock'] : 0;
                        $stockAfter = $stockBefore + (int) $delta;
                        if (!$product || $stockAfter < 0 || $stockAfter > 2147483647) {
                            $conn->rollback();
                            $errorMessage = 'Adjustment would put stock outside the allowed range.';
                        } else {
                            $update = $conn->prepare('UPDATE products SET stock = ? WHERE id = ?');
                            if (!$update) {
                                $conn->rollback();
                                error_log('Could not prepare stock adjustment update: ' . $conn->error);
                                $errorMessage = 'Could not save the stock adjustment.';
                            } else {
                                $update->bind_param('ii', $stockAfter, $productId);
                                $saved = $update->execute();
                                $update->close();
                                $logged = $saved && recordInventoryAdjustment($conn, $productId, (string) $product['name'], (int) $delta, $stockBefore, $stockAfter, $reason, $adminUsername);
                                $lowStockCrossed = $stockBefore > (int) $product['low_stock_threshold'] && $stockAfter <= (int) $product['low_stock_threshold'];
                                $notificationSaved = !$lowStockCrossed || createAdminNotification(
                                    $conn,
                                    'low_stock',
                                    'Low stock: ' . (string) $product['name'],
                                    'Stock is now ' . $stockAfter . ' unit(s), at or below its alert threshold.',
                                    'operations.php?view=inventory'
                                );
                                if (!$logged || !$notificationSaved || !$conn->commit()) {
                                    $conn->rollback();
                                    $errorMessage = 'Stock and adjustment history could not be saved.';
                                } else {
                                    operationsRedirect('inventory', 'Inventory adjusted and recorded.');
                                }
                            }
                        }
                    }
                }
            }
        } elseif ($operation === 'set_threshold') {
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            $thresholdInput = is_string($_POST['low_stock_threshold'] ?? null) ? trim($_POST['low_stock_threshold']) : '';
            $threshold = filter_var($thresholdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
            if (!$productId || $productId < 1 || $threshold === false) {
                $errorMessage = 'Enter a valid non-negative low-stock threshold.';
            } else {
                $update = $conn->prepare('UPDATE products SET low_stock_threshold = ? WHERE id = ?');
                if (!$update) {
                    error_log('Could not prepare low-stock threshold update: ' . $conn->error);
                    $errorMessage = 'Could not save the low-stock threshold.';
                } else {
                    $update->bind_param('ii', $threshold, $productId);
                    $saved = $update->execute();
                    $update->close();
                    if (!$saved) {
                        $errorMessage = 'Product was not found or the threshold could not be saved.';
                    } else {
                        $exists = $conn->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
                        if (!$exists) {
                            error_log('Could not verify product after low-stock threshold update: ' . $conn->error);
                            $errorMessage = 'Could not verify the product threshold.';
                        } else {
                            $exists->bind_param('i', $productId);
                            $productExists = $exists->execute() && $exists->get_result()->num_rows === 1;
                            $exists->close();
                            if (!$productExists) {
                                $errorMessage = 'Product was not found.';
                            } else {
                                operationsRedirect('inventory', 'Low-stock threshold saved.');
                            }
                        }
                    }
                }
            }
        } elseif ($operation === 'save_customer') {
            $phoneNumber = is_string($_POST['phone_number'] ?? null) ? trim($_POST['phone_number']) : '';
            $name = is_string($_POST['customer_name'] ?? null) ? trim($_POST['customer_name']) : '';
            $notes = is_string($_POST['notes'] ?? null) ? trim($_POST['notes']) : '';
            $isBlocked = ($_POST['is_blocked'] ?? '') === '1' ? 1 : 0;
            $phoneNormalized = normalizeCustomerPhone($phoneNumber);
            if (
                $name === ''
                || strlen($name) > 100
                || strlen($phoneNumber) > 32
                || preg_match('/^[+\d\s().-]+$/D', $phoneNumber) !== 1
                || strlen($phoneNormalized) < 6
                || strlen($phoneNormalized) > 20
                || strlen($notes) > 5000
            ) {
                $errorMessage = 'Enter a valid customer name, phone number, and notes (up to 5,000 characters).';
            } else {
                $save = $conn->prepare('INSERT INTO customer_profiles (phone_normalized, phone_number, customer_name, notes, is_blocked) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE phone_number = VALUES(phone_number), customer_name = VALUES(customer_name), notes = VALUES(notes), is_blocked = VALUES(is_blocked)');
                if (!$save) {
                    error_log('Could not prepare customer profile update: ' . $conn->error);
                    $errorMessage = 'Could not save the customer profile.';
                } else {
                    $save->bind_param('ssssi', $phoneNormalized, $phoneNumber, $name, $notes, $isBlocked);
                    $saved = $save->execute();
                    $save->close();
                    if (!$saved) {
                        $errorMessage = 'Could not save the customer profile.';
                    } else {
                        operationsRedirect('customers', $isBlocked ? 'Customer blocked from future checkout.' : 'Customer profile saved.');
                    }
                }
            }
        } elseif ($operation === 'logout') {
            adminDestroySession($conn);
            header('Location: index.php');
            exit;
        } else {
            $errorMessage = 'Unknown operation.';
        }
    }
}

$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$orderStatusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$paymentStatusFilter = is_string($_GET['payment'] ?? null) ? $_GET['payment'] : '';
$selectedOrderId = filter_input(INPUT_GET, 'details', FILTER_VALIDATE_INT);
$orders = [];
$orderCount = 0;
$ordersPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$ordersPage = $ordersPage && $ordersPage > 0 ? $ordersPage : 1;
$ordersPageSize = 50;
$ordersPageCount = 1;
$selectedOrder = null;
$orderHistory = [];
$products = [];
$inventoryHistory = [];
$customers = [];
$dailyReports = [];
$reportSummary = ['orders' => 0, 'units' => 0, 'revenue' => 0.0, 'refunds' => 0.0, 'cost' => 0.0, 'unknown_cost_units' => 0];
$previousReportSummary = ['orders_count' => 0, 'revenue' => 0.0, 'known_profit' => 0.0];
$chartReports = [];
$topProducts = [];
$dateFromDefault = (new DateTimeImmutable('first day of this month', new DateTimeZone('Asia/Phnom_Penh')))->format('Y-m-d');
$dateToDefault = (new DateTimeImmutable('today', new DateTimeZone('Asia/Phnom_Penh')))->format('Y-m-d');
$dateFrom = is_string($_GET['from'] ?? null) ? $_GET['from'] : $dateFromDefault;
$dateTo = is_string($_GET['to'] ?? null) ? $_GET['to'] : $dateToDefault;
$validReportDates = operationsValidDate($dateFrom) && operationsValidDate($dateTo) && $dateFrom <= $dateTo;
if ($validReportDates) {
    $rangeDays = (int) (new DateTimeImmutable($dateFrom))->diff(new DateTimeImmutable($dateTo))->days;
    $validReportDates = $rangeDays <= 366;
}
if (!$validReportDates) {
    $errorMessage = $errorMessage !== '' ? $errorMessage : 'Choose a valid date range no longer than 367 days.';
    $dateFrom = $dateFromDefault;
    $dateTo = $dateToDefault;
}

if ($view === 'orders') {
    $conditions = [];
    $parameters = [];
    $types = '';
    if ($search !== '') {
        $conditions[] = "(CAST(s.id AS CHAR) LIKE ? OR COALESCE(s.order_reference, '') LIKE ? OR s.customer_name LIKE ? OR s.phone_number LIKE ?)";
        $like = '%' . $search . '%';
        array_push($parameters, $like, $like, $like, $like);
        $types .= 'ssss';
    }
    if (in_array($orderStatusFilter, ['Pending', 'Paid', 'Processing', 'Completed', 'Cancelled'], true)) {
        $conditions[] = 's.status = ?';
        $parameters[] = $orderStatusFilter;
        $types .= 's';
    }
    if (in_array($paymentStatusFilter, ['pending', 'verified', 'rejected', 'partially_refunded', 'refunded'], true)) {
        $conditions[] = 's.payment_status = ?';
        $parameters[] = $paymentStatusFilter;
        $types .= 's';
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $countStatement = $conn->prepare("SELECT COUNT(*) AS total FROM sales_orders s $where");
    if (!$countStatement) {
        error_log('Could not prepare order count: ' . $conn->error);
        http_response_code(500);
        exit('Could not count orders.');
    }
    if ($types !== '') {
        $countStatement->bind_param($types, ...$parameters);
    }
    if (!$countStatement->execute()) {
        error_log('Could not execute order count: ' . $countStatement->error);
        $countStatement->close();
        http_response_code(500);
        exit('Could not count orders.');
    }
    $orderCount = (int) $countStatement->get_result()->fetch_assoc()['total'];
    $countStatement->close();
    $ordersPageCount = max(1, (int) ceil($orderCount / $ordersPageSize));
    $ordersPage = min($ordersPage, $ordersPageCount);
    $offset = ($ordersPage - 1) * $ordersPageSize;
    $sql = "SELECT s.id, s.order_reference, s.status, s.payment_status, s.product_name, s.customer_name, s.phone_number, s.location, s.quantity, s.unit_price, s.discount_amount, s.paid_amount, s.payment_transaction_id, s.receipt_filename, s.created_at FROM sales_orders s $where ORDER BY s.created_at DESC, s.id DESC LIMIT ? OFFSET ?";
    $statement = $conn->prepare($sql);
    if (!$statement) {
        error_log('Could not prepare order management list: ' . $conn->error);
        http_response_code(500);
        exit('Could not load orders.');
    }
    $listParameters = array_merge($parameters, [$ordersPageSize, $offset]);
    $statement->bind_param($types . 'ii', ...$listParameters);
    if (!$statement->execute()) {
        error_log('Could not execute order management list: ' . $statement->error);
        $statement->close();
        http_response_code(500);
        exit('Could not load orders.');
    }
    $ordersResult = $statement->get_result();
    while ($row = $ordersResult->fetch_assoc()) {
        $orders[] = $row;
    }
    $statement->close();

    if ($selectedOrderId && $selectedOrderId > 0) {
        $statement = $conn->prepare('SELECT * FROM sales_orders WHERE id = ? LIMIT 1');
        if (!$statement) {
            error_log('Could not prepare order detail lookup: ' . $conn->error);
            http_response_code(500);
            exit('Could not load order details.');
        }
        $statement->bind_param('i', $selectedOrderId);
        if (!$statement->execute()) {
            $statement->close();
            http_response_code(500);
            exit('Could not load order details.');
        }
        $selectedOrder = $statement->get_result()->fetch_assoc();
        $statement->close();
        if ($selectedOrder) {
            $historyStatement = $conn->prepare('SELECT event_type, old_value, new_value, note, transaction_id, amount, actor, created_at FROM sales_order_history WHERE order_id = ? ORDER BY created_at DESC, id DESC');
            if (!$historyStatement) {
                error_log('Could not prepare order history list: ' . $conn->error);
                http_response_code(500);
                exit('Could not load order history.');
            }
            $historyStatement->bind_param('i', $selectedOrderId);
            if (!$historyStatement->execute()) {
                $historyStatement->close();
                http_response_code(500);
                exit('Could not load order history.');
            }
            $historyResult = $historyStatement->get_result();
            while ($event = $historyResult->fetch_assoc()) {
                $orderHistory[] = $event;
            }
            $historyStatement->close();
        }
    }
} elseif ($view === 'inventory') {
    $productResult = $conn->query('SELECT id, name, stock, low_stock_threshold FROM products ORDER BY name');
    $historyResult = $conn->query('SELECT product_name, change_amount, stock_before, stock_after, reason, actor, created_at FROM inventory_adjustments ORDER BY created_at DESC, id DESC LIMIT 100');
    if (!$productResult || !$historyResult) {
        error_log('Could not load inventory management data: ' . $conn->error);
        http_response_code(500);
        exit('Could not load inventory.');
    }
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
    while ($row = $historyResult->fetch_assoc()) {
        $inventoryHistory[] = $row;
    }
} elseif ($view === 'customers') {
    $customerSearch = '%' . $search . '%';
    $statement = $conn->prepare("SELECT c.phone_normalized, c.phone_number, c.customer_name, c.notes, c.is_blocked, c.updated_at,
        COALESCE(o.order_count, 0) AS order_count,
        COALESCE(o.total_spent, 0) AS total_spent,
        o.last_order
        FROM customer_profiles c
        LEFT JOIN (
            SELECT phone_normalized, COUNT(*) AS order_count,
                SUM(CASE WHEN payment_status IN ('verified', 'partially_refunded', 'refunded') AND status <> 'Cancelled' THEN COALESCE(paid_amount, unit_price * quantity - discount_amount) - (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = sales_orders.id AND h.event_type = 'refund_recorded') ELSE 0 END) AS total_spent,
                MAX(created_at) AS last_order
            FROM sales_orders
            WHERE phone_normalized IS NOT NULL AND phone_normalized <> ''
            GROUP BY phone_normalized
        ) o ON o.phone_normalized = c.phone_normalized
        WHERE c.customer_name LIKE ? OR c.phone_number LIKE ?
        ORDER BY c.updated_at DESC LIMIT 200");
    if (!$statement) {
        error_log('Could not prepare customer management list: ' . $conn->error);
        http_response_code(500);
        exit('Could not load customers.');
    }
    $statement->bind_param('ss', $customerSearch, $customerSearch);
    if (!$statement->execute()) {
        $statement->close();
        http_response_code(500);
        exit('Could not load customers.');
    }
    $customerResult = $statement->get_result();
    while ($row = $customerResult->fetch_assoc()) {
        $customers[] = $row;
    }
    $statement->close();
} elseif ($view === 'reports') {
    $start = $dateFrom . ' 00:00:00';
    $until = (new DateTimeImmutable($dateTo, new DateTimeZone('Asia/Phnom_Penh')))->modify('+1 day')->format('Y-m-d 00:00:00');
    $statement = $conn->prepare("SELECT COUNT(*) AS orders_count, COALESCE(SUM(s.quantity), 0) AS units,
        COALESCE(SUM(COALESCE(s.paid_amount, s.unit_price * s.quantity - s.discount_amount)), 0) AS total_paid,
        COALESCE(SUM((SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = s.id AND h.event_type = 'refund_recorded')), 0) AS refunds,
        COALESCE(SUM(CASE WHEN s.unit_cost IS NOT NULL THEN COALESCE(s.paid_amount, s.unit_price * s.quantity - s.discount_amount) - (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = s.id AND h.event_type = 'refund_recorded') - s.unit_cost * s.quantity ELSE 0 END), 0) AS known_profit,
        COALESCE(SUM(CASE WHEN s.unit_cost IS NULL THEN s.quantity ELSE 0 END), 0) AS unknown_cost_units
        FROM sales_orders s
        WHERE s.payment_status IN ('verified', 'partially_refunded', 'refunded') AND s.status <> 'Cancelled' AND s.created_at >= ? AND s.created_at < ?");
    if (!$statement) {
        error_log('Could not prepare sales report summary: ' . $conn->error);
        http_response_code(500);
        exit('Could not load sales reports.');
    }
    $statement->bind_param('ss', $start, $until);
    if (!$statement->execute()) {
        $statement->close();
        http_response_code(500);
        exit('Could not load sales reports.');
    }
    $reportSummary = $statement->get_result()->fetch_assoc();
    $statement->close();
    $reportSummary['revenue'] = (float) $reportSummary['total_paid'] - (float) $reportSummary['refunds'];
    $reportSummary['known_profit'] = (float) $reportSummary['known_profit'];

    $statement = $conn->prepare("SELECT s.product_name, SUM(s.quantity) AS units_sold,
        SUM(COALESCE(s.paid_amount, s.unit_price * s.quantity - s.discount_amount) - (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = s.id AND h.event_type = 'refund_recorded')) AS net_revenue
        FROM sales_orders s
        WHERE s.payment_status IN ('verified', 'partially_refunded', 'refunded') AND s.status <> 'Cancelled' AND s.created_at >= ? AND s.created_at < ?
        GROUP BY s.product_name ORDER BY units_sold DESC, net_revenue DESC LIMIT 50");
    if (!$statement) {
        error_log('Could not prepare product sales report: ' . $conn->error);
        http_response_code(500);
        exit('Could not load product sales report.');
    }
    $statement->bind_param('ss', $start, $until);
    if (!$statement->execute()) {
        $statement->close();
        http_response_code(500);
        exit('Could not load product sales report.');
    }
    $topResult = $statement->get_result();
    while ($row = $topResult->fetch_assoc()) {
        $topProducts[] = $row;
    }
    $statement->close();

    $statement = $conn->prepare("SELECT DATE(s.created_at) AS sales_day, COUNT(*) AS order_count, SUM(s.quantity) AS units,
        SUM(COALESCE(s.paid_amount, s.unit_price * s.quantity - s.discount_amount) - (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = s.id AND h.event_type = 'refund_recorded')) AS net_revenue,
        SUM(CASE WHEN s.unit_cost IS NOT NULL THEN COALESCE(s.paid_amount, s.unit_price * s.quantity - s.discount_amount) - (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = s.id AND h.event_type = 'refund_recorded') - s.unit_cost * s.quantity ELSE 0 END) AS known_profit
        FROM sales_orders s
        WHERE s.payment_status IN ('verified', 'partially_refunded', 'refunded') AND s.status <> 'Cancelled' AND s.created_at >= ? AND s.created_at < ?
        GROUP BY DATE(s.created_at) ORDER BY sales_day DESC");
    if (!$statement) {
        error_log('Could not prepare daily sales breakdown: ' . $conn->error);
        http_response_code(500);
        exit('Could not load daily sales breakdown.');
    }
    $statement->bind_param('ss', $start, $until);
    if (!$statement->execute()) {
        $statement->close();
        http_response_code(500);
        exit('Could not load daily sales breakdown.');
    }
    $dailyResult = $statement->get_result();
    while ($row = $dailyResult->fetch_assoc()) {
        $dailyReports[] = $row;
    }
    $statement->close();

    $periodDays = $rangeDays + 1;
    $previousDateTo = (new DateTimeImmutable($dateFrom, new DateTimeZone('Asia/Phnom_Penh')))->modify('-1 day');
    $previousDateFrom = $previousDateTo->modify('-' . ($periodDays - 1) . ' days');
    $previousStart = $previousDateFrom->format('Y-m-d') . ' 00:00:00';
    $previousUntil = $previousDateTo->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $statement = $conn->prepare("SELECT COUNT(*) AS orders_count,
        COALESCE(SUM(COALESCE(s.paid_amount, s.unit_price * s.quantity - s.discount_amount) - (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = s.id AND h.event_type = 'refund_recorded')), 0) AS revenue,
        COALESCE(SUM(CASE WHEN s.unit_cost IS NOT NULL THEN COALESCE(s.paid_amount, s.unit_price * s.quantity - s.discount_amount) - (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = s.id AND h.event_type = 'refund_recorded') - s.unit_cost * s.quantity ELSE 0 END), 0) AS known_profit
        FROM sales_orders s
        WHERE s.payment_status IN ('verified', 'partially_refunded', 'refunded') AND s.status <> 'Cancelled' AND s.created_at >= ? AND s.created_at < ?");
    if (!$statement) {
        error_log('Could not prepare previous-period report comparison: ' . $conn->error);
        http_response_code(500);
        exit('Could not compare sales reports.');
    }
    $statement->bind_param('ss', $previousStart, $previousUntil);
    if (!$statement->execute()) {
        $statement->close();
        error_log('Could not execute previous-period report comparison: ' . $conn->error);
        http_response_code(500);
        exit('Could not compare sales reports.');
    }
    $previousReportSummary = $statement->get_result()->fetch_assoc();
    $statement->close();

    $dailyByDate = [];
    foreach ($dailyReports as $daily) {
        $dailyByDate[$daily['sales_day']] = $daily;
    }
    $chartDate = new DateTimeImmutable($dateFrom, new DateTimeZone('Asia/Phnom_Penh'));
    $chartLastDate = new DateTimeImmutable($dateTo, new DateTimeZone('Asia/Phnom_Penh'));
    while ($chartDate <= $chartLastDate) {
        $chartKey = $chartDate->format('Y-m-d');
        $daily = $dailyByDate[$chartKey] ?? null;
        $chartReports[] = [
            'date' => $chartKey,
            'revenue' => $daily ? (float) $daily['net_revenue'] : 0.0,
            'profit' => $daily ? (float) $daily['known_profit'] : 0.0,
        ];
        $chartDate = $chartDate->modify('+1 day');
    }
}

if ($view === 'reports' && isset($_GET['export']) && $_GET['export'] === 'csv' && $validReportDates) {
    $start = $dateFrom . ' 00:00:00';
    $until = (new DateTimeImmutable($dateTo, new DateTimeZone('Asia/Phnom_Penh')))->modify('+1 day')->format('Y-m-d 00:00:00');
    $statement = $conn->prepare("SELECT s.id, s.order_reference, s.status, s.payment_status, s.payment_transaction_id, s.created_at, s.product_name, s.customer_name, s.phone_number, s.quantity, s.unit_price, s.coupon_code, s.discount_amount, COALESCE(s.paid_amount, s.unit_price * s.quantity - s.discount_amount) AS paid_amount, s.unit_cost,
        (SELECT COALESCE(SUM(h.amount), 0) FROM sales_order_history h WHERE h.order_id = s.id AND h.event_type = 'refund_recorded') AS refunded_total
        FROM sales_orders s
        WHERE s.payment_status IN ('verified', 'partially_refunded', 'refunded') AND s.status <> 'Cancelled' AND s.created_at >= ? AND s.created_at < ?
        ORDER BY s.created_at DESC, s.id DESC");
    if (!$statement) {
        error_log('Could not prepare CSV report export: ' . $conn->error);
        http_response_code(500);
        exit('Could not export the report.');
    }
    $statement->bind_param('ss', $start, $until);
    if (!$statement->execute()) {
        $statement->close();
        http_response_code(500);
        exit('Could not export the report.');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-report-' . $dateFrom . '-to-' . $dateTo . '.csv"');
    header('Cache-Control: private, no-store');
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        $statement->close();
        http_response_code(500);
        exit('Could not stream the report.');
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Order reference', 'Status', 'Payment status', 'Transaction ID', 'Date', 'Product', 'Customer', 'Phone', 'Quantity', 'Unit price', 'Coupon code', 'Discount', 'Paid', 'Refunded', 'Net revenue', 'Unit cost', 'Known cost', 'Profit']);
    $rows = $statement->get_result();
    while ($row = $rows->fetch_assoc()) {
        $net = (float) $row['paid_amount'] - (float) $row['refunded_total'];
        $cost = $row['unit_cost'] === null ? null : (float) $row['unit_cost'] * (int) $row['quantity'];
        $values = [
            $row['order_reference'] ?: 'Order #' . $row['id'],
            $row['status'],
            $row['payment_status'],
            $row['payment_transaction_id'] ?? '',
            $row['created_at'],
            $row['product_name'],
            $row['customer_name'],
            $row['phone_number'],
            (int) $row['quantity'],
            number_format((float) $row['unit_price'], 2, '.', ''),
            $row['coupon_code'] ?? '',
            number_format((float) $row['discount_amount'], 2, '.', ''),
            number_format((float) $row['paid_amount'], 2, '.', ''),
            number_format((float) $row['refunded_total'], 2, '.', ''),
            number_format($net, 2, '.', ''),
            $row['unit_cost'] === null ? '' : number_format((float) $row['unit_cost'], 2, '.', ''),
            $cost === null ? '' : number_format($cost, 2, '.', ''),
            $cost === null ? '' : number_format($net - $cost, 2, '.', ''),
        ];
        foreach ($values as &$value) {
            if (is_string($value) && preg_match('/^[\s]*[=+\-@]/', $value) === 1) {
                $value = "'" . $value;
            }
        }
        unset($value);
        fputcsv($output, $values);
    }
    fclose($output);
    $statement->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard • 4K STORE</title>
    <link rel="icon" href="../home/logo/logome.png" type="image/x-icon">
    <link rel="apple-touch-icon" href="../home/logo/logome.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../home/page.css?v=<?= filemtime(__DIR__ . '/../home/page.css') ?>">
    <link rel="stylesheet" href="../home/fashion-theme.css?v=<?= filemtime(__DIR__ . '/../home/fashion-theme.css') ?>">
    <link rel="stylesheet" href="./backoffice-theme.css?v=<?= filemtime(__DIR__ . '/backoffice-theme.css') ?>">
    <link rel="stylesheet" href="./operations.css?v=<?= filemtime(__DIR__ . '/operations.css') ?>">
    <link rel="stylesheet" href="../css/theme-mode.css?v=<?= filemtime(__DIR__ . '/../css/theme-mode.css') ?>">
    <script src="../js/page-navigation.js?v=<?= filemtime(__DIR__ . '/../js/page-navigation.js') ?>" defer></script>
</head>
<body class="fashion-storefront admin-storefront">
<main class="admin-dashboard-container operations-container">
    <header class="admin-header-glass operations-header">
        <div>
            <span class="section-tag"><i class="fas fa-shield-halved"></i> Admin control center</span>
            <h1>ការគ្រប់គ្រងហាង · Store operations</h1>
        </div>
        <div class="operations-nav">
            <a href="../home/index.php" class="ios-glass-btn"><i class="fas fa-store"></i> Storefront</a>
            <div class="theme-mode-control theme-mode-admin">
                <label class="theme-mode-label" for="operationsThemeModeSelect">Theme</label>
                <select id="operationsThemeModeSelect" data-theme-mode aria-label="Color theme">
                    <option value="system">System</option>
                    <option value="light">Light</option>
                    <option value="dark">Dark</option>
                </select>
            </div>
        </div>
    </header>
    <?php renderAdminPrimaryNavigation($currentAdmin, ['orders' => 'orders', 'inventory' => 'products', 'customers' => 'customers', 'reports' => 'reports'][$view]); ?>

    <?php if ($flashMessage !== ''): ?><div class="operation-notice" role="status"><?= operationsEscape($flashMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== ''): ?><div class="operation-error" role="alert"><?= operationsEscape($errorMessage) ?></div><?php endif; ?>

    <?php if ($view === 'orders'): ?>
        <section class="operations-section">
            <div class="operations-section-heading">
                <div><h2>Order management</h2><p>Search orders, review uploaded receipts, verify payments, and keep an audit trail.</p></div>
            </div>
            <form class="operations-filter" method="get">
                <input type="hidden" name="view" value="orders">
                <label>Order, customer, or phone<input name="q" value="<?= operationsEscape($search) ?>" placeholder="Search by order number, name, phone"></label>
                <label>Order status<select name="status"><option value="">All statuses</option><?php foreach (['Pending', 'Paid', 'Processing', 'Completed', 'Cancelled'] as $status): ?><option value="<?= operationsEscape($status) ?>" <?= $orderStatusFilter === $status ? 'selected' : '' ?>><?= operationsEscape($status) ?></option><?php endforeach; ?></select></label>
                <label>Payment status<select name="payment"><option value="">All payment states</option><?php foreach (['pending', 'verified', 'rejected', 'partially_refunded', 'refunded'] as $status): ?><option value="<?= operationsEscape($status) ?>" <?= $paymentStatusFilter === $status ? 'selected' : '' ?>><?= operationsEscape(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></label>
                <button class="operation-button" type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
            <?php if ($selectedOrder): ?>
                <?php
                $selectedTotal = max(0, (float) $selectedOrder['unit_price'] * (int) $selectedOrder['quantity'] - (float) $selectedOrder['discount_amount']);
                $selectedRefund = 0.0;
                foreach ($orderHistory as $historyEvent) {
                    if ($historyEvent['event_type'] === 'refund_recorded') {
                        $selectedRefund += (float) $historyEvent['amount'];
                    }
                }
                ?>
                <article class="order-review-card">
                    <div class="order-review-heading">
                        <div><span class="section-tag">Order <?= operationsEscape($selectedOrder['order_reference'] ?: '#' . $selectedOrder['id']) ?></span><h3><?= operationsEscape($selectedOrder['customer_name']) ?> · <?= operationsEscape($selectedOrder['phone_number']) ?></h3><p><?= operationsEscape($selectedOrder['product_name']) ?> × <?= (int) $selectedOrder['quantity'] ?> · <?= operationsEscape($currencySymbol) ?><?= number_format($selectedTotal, 2) ?><?php if ((float) $selectedOrder['discount_amount'] > 0): ?> · Coupon <?= operationsEscape((string) $selectedOrder['coupon_code']) ?> (−<?= operationsEscape($currencySymbol) ?><?= number_format((float) $selectedOrder['discount_amount'], 2) ?>)<?php endif; ?> · <?= operationsEscape($selectedOrder['created_at']) ?></p></div>
                        <a class="operation-button operation-button-secondary" href="?view=orders"><i class="fas fa-arrow-left"></i> Close</a>
                    </div>
                    <div class="order-review-meta">
                        <span><b>Order:</b> <?= operationsEscape($selectedOrder['status']) ?></span>
                        <span><b>Payment:</b> <?= operationsEscape(ucwords(str_replace('_', ' ', $selectedOrder['payment_status']))) ?></span>
                        <span><b>Transaction:</b> <?= operationsEscape($selectedOrder['payment_transaction_id'] ?: 'Not recorded') ?></span>
                        <span><b>Delivery:</b> <?= operationsEscape($selectedOrder['location']) ?></span>
                        <span><b>Paid:</b> <?= operationsEscape($currencySymbol) ?><?= number_format((float) ($selectedOrder['paid_amount'] ?? 0), 2) ?></span>
                        <span><b>Refunded:</b> <?= operationsEscape($currencySymbol) ?><?= number_format($selectedRefund, 2) ?></span>
                        <?php if ($selectedOrder['receipt_filename']): ?><a href="order-receipt.php?id=<?= (int) $selectedOrder['id'] ?>" target="_blank" rel="noopener"><i class="fas fa-file-arrow-down"></i> View private receipt</a><?php else: ?><span>No receipt stored</span><?php endif; ?>
                    </div>
                    <div class="order-action-grid">
                        <?php if ($selectedOrder['status'] !== 'Cancelled' && !in_array($selectedOrder['payment_status'], ['verified', 'partially_refunded', 'refunded'], true)): ?>
                            <form method="post" class="operation-card-form">
                                <input type="hidden" name="csrf_token" value="<?= operationsEscape($csrfToken) ?>"><input type="hidden" name="operation" value="verify_payment"><input type="hidden" name="view" value="orders"><input type="hidden" name="order_id" value="<?= (int) $selectedOrder['id'] ?>">
                                <h4>Manually verify payment</h4>
                                <p>A receipt image never marks an order paid. Confirm the transaction independently before submitting.</p>
                                <label>Transaction ID<input name="transaction_id" maxlength="120" required></label>
                                <label>Verified amount ($)<input name="amount" inputmode="decimal" pattern="\d{1,8}(\.\d{1,2})?" value="<?= number_format($selectedTotal, 2, '.', '') ?>" required></label>
                                <button class="operation-button" type="submit"><i class="fas fa-circle-check"></i> Verify &amp; mark Paid</button>
                            </form>
                            <form method="post" class="operation-card-form">
                                <input type="hidden" name="csrf_token" value="<?= operationsEscape($csrfToken) ?>"><input type="hidden" name="operation" value="reject_payment"><input type="hidden" name="view" value="orders"><input type="hidden" name="order_id" value="<?= (int) $selectedOrder['id'] ?>">
                                <h4>Reject payment evidence</h4>
                                <p>Rejection reason is recorded in order history.</p>
                                <label>Reason<textarea name="note" maxlength="500" required></textarea></label>
                                <button class="operation-button operation-button-danger" type="submit"><i class="fas fa-circle-xmark"></i> Reject payment</button>
                            </form>
                        <?php endif; ?>
                        <?php if (in_array($selectedOrder['payment_status'], ['verified', 'partially_refunded'], true)): ?>
                            <form method="post" class="operation-card-form">
                                <input type="hidden" name="csrf_token" value="<?= operationsEscape($csrfToken) ?>"><input type="hidden" name="operation" value="record_refund"><input type="hidden" name="view" value="orders"><input type="hidden" name="order_id" value="<?= (int) $selectedOrder['id'] ?>">
                                <h4>Record refund</h4>
                                <p>This logs a refund completed outside this app; it does not transfer money.</p>
                                <label>Refund transaction ID<input name="transaction_id" maxlength="120" required></label>
                                <label>Refund amount ($)<input name="amount" inputmode="decimal" pattern="\d{1,8}(\.\d{1,2})?" max="<?= number_format(max(0, (float) $selectedOrder['paid_amount'] - $selectedRefund), 2, '.', '') ?>" required></label>
                                <button class="operation-button operation-button-secondary" type="submit"><i class="fas fa-arrow-rotate-left"></i> Record refund</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($selectedOrder['status'] !== 'Cancelled'): ?>
                            <form method="post" class="operation-card-form" onsubmit="return confirm('Apply this order status change? Cancellation releases the reserved stock.');">
                                <input type="hidden" name="csrf_token" value="<?= operationsEscape($csrfToken) ?>"><input type="hidden" name="operation" value="change_order_status"><input type="hidden" name="view" value="orders"><input type="hidden" name="order_id" value="<?= (int) $selectedOrder['id'] ?>">
                                <h4>Update order status</h4>
                                <p>Paid, Processing, or Completed requires a separately verified payment. Cancelling releases reserved stock; record any refund separately.</p>
                                <label>New status<select name="status" required><option value="">Choose status</option><option value="Processing">Processing</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select></label>
                                <button class="operation-button operation-button-secondary" type="submit">Save status</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <h4 class="history-title">Order &amp; payment history</h4>
                    <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Event</th><th>Change / details</th><th>Amount / transaction</th><th>Admin / source</th><th>When</th></tr></thead><tbody>
                        <?php foreach ($orderHistory as $event): ?><tr><td><?= operationsEscape(ucwords(str_replace('_', ' ', $event['event_type']))) ?></td><td><?= operationsEscape(trim(($event['old_value'] ? $event['old_value'] . ' → ' : '') . ($event['new_value'] ?? '') . ($event['note'] ? ' · ' . $event['note'] : ''))) ?></td><td><?= $event['amount'] === null ? '—' : '$' . number_format((float) $event['amount'], 2) ?><?= $event['transaction_id'] ? '<br>' . operationsEscape($event['transaction_id']) : '' ?></td><td><?= operationsEscape($event['actor']) ?></td><td><?= operationsEscape($event['created_at']) ?></td></tr><?php endforeach; ?>
                        <?php if (!$orderHistory): ?><tr><td colspan="5">No history events yet.</td></tr><?php endif; ?>
                    </tbody></table></div>
                </article>
            <?php endif; ?>
            <div class="operations-table-wrap"><table class="operations-table">
                <thead><tr><th>Order</th><th>Buyer / contact</th><th>Product</th><th>Total</th><th>Order status</th><th>Payment</th><th>Date</th><th></th></tr></thead><tbody>
                <?php foreach ($orders as $order): ?><tr>
                    <td><strong><?= operationsEscape($order['order_reference'] ?: '#' . $order['id']) ?></strong></td>
                    <td><?= operationsEscape($order['customer_name']) ?><small><?= operationsEscape($order['phone_number']) ?></small></td>
                    <td><?= operationsEscape($order['product_name']) ?> × <?= (int) $order['quantity'] ?></td>
                    <td><?= operationsEscape($currencySymbol) ?><?= number_format(max(0, (float) $order['unit_price'] * (int) $order['quantity'] - (float) $order['discount_amount']), 2) ?></td>
                    <td><span class="status-badge status-<?= strtolower(operationsEscape($order['status'])) ?>"><?= operationsEscape($order['status']) ?></span></td>
                    <td><span class="status-badge payment-<?= operationsEscape($order['payment_status']) ?>"><?= operationsEscape(ucwords(str_replace('_', ' ', $order['payment_status']))) ?></span></td>
                    <td><?= operationsEscape($order['created_at']) ?></td>
                    <td><a class="operation-button operation-button-secondary" href="?view=orders&amp;details=<?= (int) $order['id'] ?>"><i class="fas fa-eye"></i> Review</a></td>
                </tr><?php endforeach; ?>
                <?php if (!$orders): ?><tr><td colspan="8">No orders match this search.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <div class="operations-pagination">
                <span>Showing <?= $orderCount === 0 ? 0 : $offset + 1 ?>–<?= min($offset + $ordersPageSize, $orderCount) ?> of <?= $orderCount ?> orders</span>
                <div>
                    <?php if ($ordersPage > 1): ?><a class="operation-button operation-button-secondary" href="?<?= operationsEscape(http_build_query(['view' => 'orders', 'q' => $search, 'status' => $orderStatusFilter, 'payment' => $paymentStatusFilter, 'page' => $ordersPage - 1])) ?>">Previous</a><?php endif; ?>
                    <?php if ($ordersPage < $ordersPageCount): ?><a class="operation-button operation-button-secondary" href="?<?= operationsEscape(http_build_query(['view' => 'orders', 'q' => $search, 'status' => $orderStatusFilter, 'payment' => $paymentStatusFilter, 'page' => $ordersPage + 1])) ?>">Next</a><?php endif; ?>
                </div>
            </div>
            <p class="operations-help">Existing records remain pending verification until an admin confirms payment independently.</p>
        </section>
    <?php elseif ($view === 'inventory'): ?>
        <section class="operations-section">
            <div class="operations-section-heading"><div><h2>Inventory &amp; stock control</h2><p>Set alert thresholds and record every stock adjustment with its reason and admin.</p></div></div>
            <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Product</th><th>On hand</th><th>Low-stock alert at</th><th>Adjustment (+ / −)</th></tr></thead><tbody>
                <?php foreach ($products as $product): ?><tr>
                    <td><strong><?= operationsEscape($product['name']) ?></strong></td>
                    <td><span class="stock-badge <?= (int) $product['stock'] === 0 ? 'is-empty' : ((int) $product['stock'] <= (int) $product['low_stock_threshold'] ? 'is-low' : '') ?>"><?= (int) $product['stock'] ?></span></td>
                    <td><form method="post" class="inline-operation-form"><input type="hidden" name="csrf_token" value="<?= operationsEscape($csrfToken) ?>"><input type="hidden" name="operation" value="set_threshold"><input type="hidden" name="view" value="inventory"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input class="operation-input short-input" type="number" min="0" max="2147483647" name="low_stock_threshold" value="<?= (int) $product['low_stock_threshold'] ?>" required><button class="operation-button operation-button-secondary" type="submit">Save</button></form></td>
                    <td><form method="post" class="inline-operation-form adjustment-form"><input type="hidden" name="csrf_token" value="<?= operationsEscape($csrfToken) ?>"><input type="hidden" name="operation" value="adjust_stock"><input type="hidden" name="view" value="inventory"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input class="operation-input short-input" type="number" min="-2147483647" max="2147483647" name="change_amount" placeholder="+/- units" required><input class="operation-input reason-input" name="reason" maxlength="255" placeholder="Reason" required><button class="operation-button" type="submit">Adjust</button></form></td>
                </tr><?php endforeach; ?>
                <?php if (!$products): ?><tr><td colspan="4">No products found.</td></tr><?php endif; ?>
            </tbody></table></div>
            <h3 class="history-title">Stock adjustment history</h3>
            <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Product</th><th>Change</th><th>Before → after</th><th>Reason</th><th>Admin</th><th>When</th></tr></thead><tbody>
                <?php foreach ($inventoryHistory as $adjustment): ?><tr><td><?= operationsEscape($adjustment['product_name']) ?></td><td class="<?= (int) $adjustment['change_amount'] >= 0 ? 'stock-increase' : 'stock-decrease' ?>"><?= (int) $adjustment['change_amount'] > 0 ? '+' : '' ?><?= (int) $adjustment['change_amount'] ?></td><td><?= (int) $adjustment['stock_before'] ?> → <?= (int) $adjustment['stock_after'] ?></td><td><?= operationsEscape($adjustment['reason']) ?></td><td><?= operationsEscape($adjustment['actor']) ?></td><td><?= operationsEscape($adjustment['created_at']) ?></td></tr><?php endforeach; ?>
                <?php if (!$inventoryHistory): ?><tr><td colspan="6">No stock adjustments recorded yet.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
    <?php elseif ($view === 'customers'): ?>
        <section class="operations-section">
            <div class="operations-section-heading"><div><h2>Customer management</h2><p>Guest checkout customers are identified by normalized phone number.</p></div></div>
            <form class="operations-filter customer-search" method="get"><input type="hidden" name="view" value="customers"><label>Find customer<input name="q" value="<?= operationsEscape($search) ?>" placeholder="Name or phone"></label><button class="operation-button" type="submit"><i class="fas fa-search"></i> Search</button></form>
            <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Customer</th><th>Phone</th><th>Orders</th><th>Verified spend</th><th>Last order</th><th>Notes &amp; access</th></tr></thead><tbody>
                <?php foreach ($customers as $customer): ?><tr>
                    <td><strong><?= operationsEscape($customer['customer_name']) ?></strong><?php if ((int) $customer['is_blocked'] === 1): ?><small class="blocked-label">Blocked</small><?php endif; ?></td>
                    <td><?= operationsEscape($customer['phone_number']) ?></td>
                    <td><?= (int) $customer['order_count'] ?></td>
                    <td><?= operationsEscape($currencySymbol) ?><?= number_format((float) $customer['total_spent'], 2) ?></td>
                    <td><?= operationsEscape($customer['last_order'] ?? '—') ?></td>
                    <td><form method="post" class="customer-edit-form"><input type="hidden" name="csrf_token" value="<?= operationsEscape($csrfToken) ?>"><input type="hidden" name="operation" value="save_customer"><input type="hidden" name="view" value="customers"><input type="hidden" name="phone_number" value="<?= operationsEscape($customer['phone_number']) ?>"><input type="hidden" name="customer_name" value="<?= operationsEscape($customer['customer_name']) ?>"><textarea name="notes" maxlength="5000" placeholder="Customer service notes"><?= operationsEscape($customer['notes'] ?? '') ?></textarea><label class="block-checkbox"><input type="checkbox" name="is_blocked" value="1" <?= (int) $customer['is_blocked'] === 1 ? 'checked' : '' ?>> Block future checkout</label><button class="operation-button operation-button-secondary" type="submit">Save</button></form></td>
                </tr><?php endforeach; ?>
                <?php if (!$customers): ?><tr><td colspan="6">No customers match this search.</td></tr><?php endif; ?>
            </tbody></table></div>
            <p class="operations-help">Blocking prevents new online orders from that phone number. It does not delete past order history or stop communication outside this website.</p>
        </section>
    <?php else: ?>
        <section class="operations-section">
            <div class="operations-section-heading"><div><h2>Sales reports &amp; analytics</h2><p>Revenue counts manually verified payments only; refunds are subtracted. Costs are estimates from product cost data.</p></div></div>
            <form class="operations-filter report-filter" method="get">
                <input type="hidden" name="view" value="reports">
                <label>From<input type="date" name="from" value="<?= operationsEscape($dateFrom) ?>" required></label>
                <label>To<input type="date" name="to" value="<?= operationsEscape($dateTo) ?>" required></label>
                <button class="operation-button" type="submit"><i class="fas fa-filter"></i> Apply range</button>
                <a class="operation-button operation-button-secondary" href="?view=reports&amp;from=<?= rawurlencode($dateFrom) ?>&amp;to=<?= rawurlencode($dateTo) ?>&amp;export=csv"><i class="fas fa-file-csv"></i> Export CSV for Excel</a>
            </form>
            <div class="analytics-grid operations-metrics">
                <article class="analytics-card"><span>Verified orders</span><strong><?= (int) $reportSummary['orders_count'] ?></strong></article>
                <article class="analytics-card"><span>Units sold</span><strong><?= (int) $reportSummary['units'] ?></strong></article>
                <article class="analytics-card"><span>Net revenue</span><strong><?= operationsEscape($currencySymbol) ?><?= number_format((float) $reportSummary['revenue'], 2) ?></strong><small>After refunds of <?= operationsEscape($currencySymbol) ?><?= number_format((float) $reportSummary['refunds'], 2) ?></small></article>
                <article class="analytics-card"><span><?= (float) $reportSummary['known_profit'] < 0 ? 'Estimated loss (known costs)' : 'Estimated profit (known costs)' ?></span><strong class="<?= (float) $reportSummary['known_profit'] < 0 ? 'report-loss-value' : '' ?>"><?= operationsEscape($currencySymbol) ?><?= number_format(abs((float) $reportSummary['known_profit']), 2) ?><?= (float) $reportSummary['known_profit'] < 0 ? ' loss' : '' ?></strong><small><?= (int) $reportSummary['unknown_cost_units'] ?> units with no saved cost excluded</small></article>
            </div>
            <?php
            $reportToday = new DateTimeImmutable('today', new DateTimeZone('Asia/Phnom_Penh'));
            $weekStart = $reportToday->modify('monday this week');
            $comparisonCards = [
                [
                    'label' => 'Revenue change',
                    'current' => (float) $reportSummary['revenue'],
                    'previous' => (float) $previousReportSummary['revenue'],
                    'format' => 'money',
                ],
                [
                    'label' => 'Profit / loss change',
                    'current' => (float) $reportSummary['known_profit'],
                    'previous' => (float) $previousReportSummary['known_profit'],
                    'format' => 'money',
                ],
                [
                    'label' => 'Verified orders change',
                    'current' => (float) $reportSummary['orders_count'],
                    'previous' => (float) $previousReportSummary['orders_count'],
                    'format' => 'count',
                ],
            ];
            $chartWidth = 900;
            $chartHeight = 320;
            $chartLeft = 68;
            $chartRight = 24;
            $chartTop = 22;
            $chartBottom = 48;
            $plotWidth = $chartWidth - $chartLeft - $chartRight;
            $plotHeight = $chartHeight - $chartTop - $chartBottom;
            $chartValues = [0.0];
            foreach ($chartReports as $point) {
                $chartValues[] = $point['revenue'];
                $chartValues[] = $point['profit'];
            }
            $chartMin = min($chartValues);
            $chartMax = max($chartValues);
            if ($chartMin === $chartMax) {
                $chartMin = $chartMin === 0.0 ? -1.0 : $chartMin - abs($chartMin) * 0.1;
                $chartMax = $chartMax === 0.0 ? 1.0 : $chartMax + abs($chartMax) * 0.1;
            }
            $chartPadding = ($chartMax - $chartMin) * 0.1;
            $chartMin -= $chartPadding;
            $chartMax += $chartPadding;
            $chartY = static function (float $value) use ($chartMin, $chartMax, $chartTop, $plotHeight): float {
                return $chartTop + (($chartMax - $value) / ($chartMax - $chartMin)) * $plotHeight;
            };
            $chartX = static function (int $index) use ($chartReports, $chartLeft, $plotWidth): float {
                $lastIndex = count($chartReports) - 1;
                return $chartLeft + ($lastIndex > 0 ? ($index / $lastIndex) * $plotWidth : $plotWidth / 2);
            };
            $revenuePoints = [];
            $profitPoints = [];
            foreach ($chartReports as $index => $point) {
                $revenuePoints[] = number_format($chartX($index), 2, '.', '') . ',' . number_format($chartY($point['revenue']), 2, '.', '');
                $profitPoints[] = number_format($chartX($index), 2, '.', '') . ',' . number_format($chartY($point['profit']), 2, '.', '');
            }
            $formatAxisValue = static function (float $value): string {
                $absoluteValue = abs($value);
                $shortValue = $absoluteValue >= 1000000
                    ? number_format($value / 1000000, 1) . 'm'
                    : ($absoluteValue >= 1000 ? number_format($value / 1000, 1) . 'k' : number_format($absoluteValue, $absoluteValue < 10 ? 2 : 0));
                if ($absoluteValue < 10) {
                    $shortValue = rtrim(rtrim($shortValue, '0'), '.');
                    $shortValue = $shortValue === '' ? '0' : $shortValue;
                }
                return ($value < 0 ? '−' : '') . '$' . $shortValue;
            };
            ?>
            <div class="report-quick-ranges" aria-label="Quick report ranges">
                <a class="operation-button operation-button-secondary" href="?view=reports&amp;from=<?= $reportToday->format('Y-m-d') ?>&amp;to=<?= $reportToday->format('Y-m-d') ?>">Today</a>
                <a class="operation-button operation-button-secondary" href="?view=reports&amp;from=<?= $weekStart->format('Y-m-d') ?>&amp;to=<?= $reportToday->format('Y-m-d') ?>">This week</a>
                <a class="operation-button operation-button-secondary" href="?view=reports&amp;from=<?= $reportToday->modify('first day of this month')->format('Y-m-d') ?>&amp;to=<?= $reportToday->format('Y-m-d') ?>">This month</a>
            </div>
            <section class="report-comparison" aria-label="Comparison with previous period">
                <div class="report-comparison-heading">
                    <h3>Growth vs previous period</h3>
                    <p>Same number of days: <?= operationsEscape($previousDateFrom->format('M j, Y')) ?> – <?= operationsEscape($previousDateTo->format('M j, Y')) ?></p>
                </div>
                <div class="report-comparison-grid">
                    <?php foreach ($comparisonCards as $comparison):
                        $change = $comparison['current'] - $comparison['previous'];
                        $changeClass = $change > 0 ? 'is-positive' : ($change < 0 ? 'is-negative' : 'is-neutral');
                        $changeText = $comparison['format'] === 'money'
                            ? (($change > 0 ? '+' : ($change < 0 ? '−' : '')) . '$' . number_format(abs($change), 2))
                            : (($change > 0 ? '+' : ($change < 0 ? '−' : '')) . number_format(abs($change), 0));
                        if ($comparison['previous'] > 0) {
                            $percentText = ($change > 0 ? '+' : '') . number_format(($change / $comparison['previous']) * 100, 1) . '%';
                        } elseif ($comparison['previous'] < 0) {
                            $percentText = $change > 0 ? 'Improved from loss' : ($change < 0 ? 'Worsened from loss' : 'No change');
                        } else {
                            $percentText = $comparison['current'] > 0
                                ? 'New activity'
                                : ($comparison['current'] < 0 ? 'New loss' : 'No change');
                        }
                    ?>
                        <article class="report-comparison-card">
                            <span><?= operationsEscape($comparison['label']) ?></span>
                            <strong class="<?= $changeClass ?>"><?= operationsEscape($changeText) ?></strong>
                            <small><?= operationsEscape($percentText) ?> · Current <?= $comparison['format'] === 'money' ? '$' . number_format($comparison['current'], 2) : number_format($comparison['current'], 0) ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="report-chart-panel" aria-labelledby="salesTrendHeading">
                <div class="report-chart-heading">
                    <div><h3 id="salesTrendHeading">Revenue and profit / loss trend</h3><p>Daily net revenue after refunds and estimated profit using products with saved costs.</p></div>
                    <div class="report-chart-legend"><span><i class="legend-revenue"></i> Net revenue</span><span><i class="legend-profit"></i> Profit / loss</span></div>
                </div>
                <div class="report-chart-scroll">
                    <svg class="report-trend-chart" viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" role="img" aria-label="Daily net revenue and estimated profit or loss from <?= operationsEscape($dateFrom) ?> to <?= operationsEscape($dateTo) ?>">
                        <?php for ($tick = 0; $tick <= 4; $tick++):
                            $tickValue = $chartMax - (($chartMax - $chartMin) * $tick / 4);
                            $tickY = $chartY($tickValue);
                        ?>
                            <line class="chart-grid-line" x1="<?= $chartLeft ?>" y1="<?= number_format($tickY, 2, '.', '') ?>" x2="<?= $chartWidth - $chartRight ?>" y2="<?= number_format($tickY, 2, '.', '') ?>"></line>
                            <text class="chart-axis-label" x="<?= $chartLeft - 10 ?>" y="<?= number_format($tickY + 4, 2, '.', '') ?>" text-anchor="end"><?= operationsEscape($formatAxisValue($tickValue)) ?></text>
                        <?php endfor; ?>
                        <?php if ($chartMin < 0 && $chartMax > 0): ?><line class="chart-zero-line" x1="<?= $chartLeft ?>" y1="<?= number_format($chartY(0), 2, '.', '') ?>" x2="<?= $chartWidth - $chartRight ?>" y2="<?= number_format($chartY(0), 2, '.', '') ?>"></line><?php endif; ?>
                        <?php if ($chartReports): ?>
                            <polyline class="chart-revenue-line" points="<?= operationsEscape(implode(' ', $revenuePoints)) ?>"></polyline>
                            <polyline class="chart-profit-line" points="<?= operationsEscape(implode(' ', $profitPoints)) ?>"></polyline>
                            <?php foreach ($chartReports as $index => $point): ?>
                                <circle class="chart-revenue-point" cx="<?= number_format($chartX($index), 2, '.', '') ?>" cy="<?= number_format($chartY($point['revenue']), 2, '.', '') ?>" r="3"><title><?= operationsEscape($point['date']) ?> · Revenue <?= operationsEscape($currencySymbol) ?><?= number_format($point['revenue'], 2) ?></title></circle>
                                <circle class="chart-profit-point" cx="<?= number_format($chartX($index), 2, '.', '') ?>" cy="<?= number_format($chartY($point['profit']), 2, '.', '') ?>" r="3"><title><?= operationsEscape($point['date']) ?> · Profit / loss <?= operationsEscape($currencySymbol) ?><?= number_format($point['profit'], 2) ?></title></circle>
                            <?php endforeach; ?>
                            <?php
                            $labelCount = min(6, count($chartReports));
                            $labelIndexes = $labelCount > 1
                                ? array_unique(array_map(static fn (int $step): int => (int) round($step * (count($chartReports) - 1) / ($labelCount - 1)), range(0, $labelCount - 1)))
                                : [0];
                            ?>
                            <?php foreach ($labelIndexes as $labelIndex): ?>
                                <text class="chart-axis-label chart-date-label" x="<?= number_format($chartX($labelIndex), 2, '.', '') ?>" y="<?= $chartHeight - 14 ?>" text-anchor="middle"><?= operationsEscape(substr($chartReports[$labelIndex]['date'], 5)) ?></text>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </svg>
                </div>
                <?php if (!$chartReports || (int) $reportSummary['orders_count'] === 0): ?><p class="report-chart-empty">No verified sales in this date range. The chart will show trends when payments are verified.</p><?php endif; ?>
            </section>
            <h3 class="history-title">Daily sales breakdown</h3>
            <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Date</th><th>Orders</th><th>Units</th><th>Net revenue</th><th>Estimated profit (known costs)</th></tr></thead><tbody>
                <?php foreach ($dailyReports as $daily): ?><tr><td><?= operationsEscape($daily['sales_day']) ?></td><td><?= (int) $daily['order_count'] ?></td><td><?= (int) $daily['units'] ?></td><td><?= operationsEscape($currencySymbol) ?><?= number_format((float) $daily['net_revenue'], 2) ?></td><td><?= operationsEscape($currencySymbol) ?><?= number_format((float) $daily['known_profit'], 2) ?></td></tr><?php endforeach; ?>
                <?php if (!$dailyReports): ?><tr><td colspan="5">No verified sales in this date range.</td></tr><?php endif; ?>
            </tbody></table></div>
            <h3 class="history-title">Best-selling products in period</h3>
            <div class="operations-table-wrap"><table class="operations-table"><thead><tr><th>Product</th><th>Units sold</th><th>Net revenue</th></tr></thead><tbody>
                <?php foreach ($topProducts as $product): ?><tr><td><?= operationsEscape($product['product_name']) ?></td><td><?= (int) $product['units_sold'] ?></td><td><?= operationsEscape($currencySymbol) ?><?= number_format((float) $product['net_revenue'], 2) ?></td></tr><?php endforeach; ?>
                <?php if (!$topProducts): ?><tr><td colspan="3">No verified sales in this date range.</td></tr><?php endif; ?>
            </tbody></table></div>
            <p class="operations-help">To view daily, weekly, or monthly results, select the corresponding date range. CSV export includes order, customer, payment, refund, cost, and profit fields.</p>
        </section>
    <?php endif; ?>
</main>
<script src="../js/theme-mode.js?v=<?= filemtime(__DIR__ . '/../js/theme-mode.js') ?>"></script>
</body>
</html>
