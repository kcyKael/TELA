<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();

function setAdminOrderFlashMessage($type, $message)
{
    $_SESSION['admin_order_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function parseAdminOrderId($value)
{
    if (
        !is_string($value) ||
        $value === '' ||
        strlen($value) > 10 ||
        !ctype_digit($value)
    ) {
        return 0;
    }

    $isWithinUnsignedRange = strlen($value) < 10 || strcmp($value, '4294967295') <= 0;

    if (!$isWithinUnsignedRange) {
        return 0;
    }

    $orderId = (int) $value;

    return $orderId > 0 ? $orderId : 0;
}

function redirectToAdminOrderDetails($orderId)
{
    $detailsPath = rtrim(APP_BASE_URL, '/') . '/admin/order_details.php?order_id=' .
        rawurlencode((string) $orderId);
    redirectTo($detailsPath);
}

$ordersPath = rtrim(APP_BASE_URL, '/') . '/admin/orders.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirectTo($ordersPath);
}

if (!verifyCsrfToken()) {
    $csrfOrderId = parseAdminOrderId($_POST['order_id'] ?? '');

    if ($csrfOrderId > 0) {
        setAdminOrderFlashMessage('danger', 'The request could not be verified.');
        redirectToAdminOrderDetails($csrfOrderId);
    }

    csrfFailure($ordersPath);
}

$orderId = parseAdminOrderId($_POST['order_id'] ?? '');

if ($orderId === 0) {
    redirectTo($ordersPath);
}

$submittedStatus = $_POST['new_status'] ?? '';
$allowedStatuses = ['Pending', 'Processing', 'Completed', 'Cancelled'];

if (!is_string($submittedStatus) || !in_array($submittedStatus, $allowedStatuses, true)) {
    setAdminOrderFlashMessage('danger', 'The status update request was invalid.');
    redirectToAdminOrderDetails($orderId);
}

$allowedTransitions = [
    'Pending' => ['Processing', 'Cancelled'],
    'Processing' => ['Completed', 'Cancelled'],
    'Completed' => [],
    'Cancelled' => []
];

require_once __DIR__ . '/../config/database.php';

$actingAdminId = (int) $_SESSION['user_id'];
$transactionStarted = false;
$currentStatus = '';
$orderNumber = '';
$stockWasRestored = false;
$restoredItemCount = 0;
$totalRestoredQuantity = 0;

try {
    if (!mysqli_begin_transaction($conn)) {
        throw new RuntimeException('Order status transaction could not start.');
    }

    $transactionStarted = true;

    $orderSql = '
        SELECT order_id, order_number, order_status
        FROM orders
        WHERE order_id = ?
        LIMIT 1
        FOR UPDATE
    ';
    $orderStmt = mysqli_prepare($conn, $orderSql);

    if ($orderStmt === false) {
        throw new RuntimeException('Order lock statement preparation failed.');
    }

    if (!mysqli_stmt_bind_param($orderStmt, 'i', $orderId)) {
        mysqli_stmt_close($orderStmt);
        throw new RuntimeException('Order lock parameter binding failed.');
    }

    if (!mysqli_stmt_execute($orderStmt)) {
        mysqli_stmt_close($orderStmt);
        throw new RuntimeException('Order lock query failed.');
    }

    if (!mysqli_stmt_bind_result($orderStmt, $lockedOrderId, $lockedOrderNumber, $lockedOrderStatus)) {
        mysqli_stmt_close($orderStmt);
        throw new RuntimeException('Order lock result binding failed.');
    }

    $orderFound = mysqli_stmt_fetch($orderStmt);
    mysqli_stmt_close($orderStmt);

    if ($orderFound !== true) {
        if (!mysqli_rollback($conn)) {
            throw new RuntimeException('Unavailable order rollback failed.');
        }

        $transactionStarted = false;
        redirectToAdminOrderDetails($orderId);
    }

    $orderNumber = $lockedOrderNumber;
    $currentStatus = $lockedOrderStatus;

    if ($submittedStatus === $currentStatus) {
        if (!mysqli_rollback($conn)) {
            throw new RuntimeException('Unchanged order rollback failed.');
        }

        $transactionStarted = false;
        setAdminOrderFlashMessage(
            'info',
            'No changes were made because the order already has that status.'
        );
        redirectToAdminOrderDetails($orderId);
    }

    if (
        !isset($allowedTransitions[$currentStatus]) ||
        !in_array($submittedStatus, $allowedTransitions[$currentStatus], true)
    ) {
        if (!mysqli_rollback($conn)) {
            throw new RuntimeException('Invalid transition rollback failed.');
        }

        $transactionStarted = false;
        setAdminOrderFlashMessage('warning', 'The requested status change is not allowed.');
        redirectToAdminOrderDetails($orderId);
    }

    if ($submittedStatus === 'Cancelled') {
        $itemsSql = '
            SELECT order_item_id, product_id, quantity
            FROM order_items
            WHERE order_id = ?
            ORDER BY product_id ASC, order_item_id ASC
        ';
        $itemsStmt = mysqli_prepare($conn, $itemsSql);

        if ($itemsStmt === false) {
            throw new RuntimeException('Cancellation item statement preparation failed.');
        }

        if (!mysqli_stmt_bind_param($itemsStmt, 'i', $orderId)) {
            mysqli_stmt_close($itemsStmt);
            throw new RuntimeException('Cancellation item parameter binding failed.');
        }

        if (!mysqli_stmt_execute($itemsStmt)) {
            mysqli_stmt_close($itemsStmt);
            throw new RuntimeException('Cancellation item query failed.');
        }

        if (!mysqli_stmt_bind_result($itemsStmt, $orderItemId, $productId, $quantity)) {
            mysqli_stmt_close($itemsStmt);
            throw new RuntimeException('Cancellation item result binding failed.');
        }

        $cancellationItems = [];

        while (($itemFetchResult = mysqli_stmt_fetch($itemsStmt)) === true) {
            $itemProductId = (int) $productId;
            $itemQuantity = (int) $quantity;

            if ($itemProductId <= 0 || $itemQuantity <= 0) {
                mysqli_stmt_close($itemsStmt);
                throw new RuntimeException('Cancellation item data was invalid.');
            }

            $cancellationItems[] = [
                'order_item_id' => (int) $orderItemId,
                'product_id' => $itemProductId,
                'quantity' => $itemQuantity
            ];
        }

        mysqli_stmt_close($itemsStmt);

        if ($itemFetchResult === false || empty($cancellationItems)) {
            throw new RuntimeException('Cancellation items were unavailable.');
        }

        $stockSql = '
            UPDATE products
            SET stock = stock + ?
            WHERE product_id = ?
        ';
        $stockStmt = mysqli_prepare($conn, $stockSql);

        if ($stockStmt === false) {
            throw new RuntimeException('Stock restoration statement preparation failed.');
        }

        foreach ($cancellationItems as $cancellationItem) {
            $restoreQuantity = $cancellationItem['quantity'];
            $restoreProductId = $cancellationItem['product_id'];

            if (!mysqli_stmt_bind_param($stockStmt, 'ii', $restoreQuantity, $restoreProductId)) {
                mysqli_stmt_close($stockStmt);
                throw new RuntimeException('Stock restoration parameter binding failed.');
            }

            if (!mysqli_stmt_execute($stockStmt) || mysqli_stmt_affected_rows($stockStmt) !== 1) {
                mysqli_stmt_close($stockStmt);
                throw new RuntimeException('Stock restoration failed.');
            }

            $restoredItemCount++;
            $totalRestoredQuantity += $restoreQuantity;
        }

        mysqli_stmt_close($stockStmt);
        $stockWasRestored = true;
    }

    $statusSql = '
        UPDATE orders
        SET order_status = ?
        WHERE order_id = ? AND order_status = ?
    ';
    $statusStmt = mysqli_prepare($conn, $statusSql);

    if ($statusStmt === false) {
        throw new RuntimeException('Order status statement preparation failed.');
    }

    if (!mysqli_stmt_bind_param($statusStmt, 'sis', $submittedStatus, $orderId, $currentStatus)) {
        mysqli_stmt_close($statusStmt);
        throw new RuntimeException('Order status parameter binding failed.');
    }

    if (!mysqli_stmt_execute($statusStmt) || mysqli_stmt_affected_rows($statusStmt) !== 1) {
        mysqli_stmt_close($statusStmt);
        throw new RuntimeException('Order status update failed.');
    }

    mysqli_stmt_close($statusStmt);

    $auditSql = '
        INSERT INTO audit_logs (user_id, activity, description, ip_address)
        VALUES (?, ?, ?, ?)
    ';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt === false) {
        throw new RuntimeException('Order status audit statement preparation failed.');
    }

    $activity = 'Update Order Status';

    if ($stockWasRestored) {
        $description = 'Changed order ID ' . $orderId . ' (' . $orderNumber . ') from ' .
            $currentStatus . ' to ' . $submittedStatus . '. Stock restored: yes; item rows: ' .
            $restoredItemCount . '; total quantity: ' . $totalRestoredQuantity . '.';
    } else {
        $description = 'Changed order ID ' . $orderId . ' (' . $orderNumber . ') from ' .
            $currentStatus . ' to ' . $submittedStatus . '. Stock restored: no.';
    }

    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $ipAddress = is_string($remoteAddress) ? substr($remoteAddress, 0, 45) : '';

    if (!mysqli_stmt_bind_param(
        $auditStmt,
        'isss',
        $actingAdminId,
        $activity,
        $description,
        $ipAddress
    )) {
        mysqli_stmt_close($auditStmt);
        throw new RuntimeException('Order status audit parameter binding failed.');
    }

    if (!mysqli_stmt_execute($auditStmt) || mysqli_stmt_affected_rows($auditStmt) !== 1) {
        mysqli_stmt_close($auditStmt);
        throw new RuntimeException('Order status audit insertion failed.');
    }

    mysqli_stmt_close($auditStmt);

    if (!mysqli_commit($conn)) {
        throw new RuntimeException('Order status transaction commit failed.');
    }

    $transactionStarted = false;

    $successMessage = $stockWasRestored
        ? 'Order status updated successfully. Product stock was restored.'
        : 'Order status updated successfully.';
    setAdminOrderFlashMessage('success', $successMessage);
    redirectToAdminOrderDetails($orderId);
} catch (Throwable $exception) {
    if ($transactionStarted) {
        try {
            mysqli_rollback($conn);
        } catch (Throwable $rollbackException) {
            // The administrator still receives a generic error message.
        }
    }

    error_log(
        'Admin order status update failed for acting admin user ID ' . $actingAdminId .
        ' and order ID ' . $orderId . '.'
    );

    setAdminOrderFlashMessage('danger', 'Order status could not be updated right now.');
    redirectToAdminOrderDetails($orderId);
}
