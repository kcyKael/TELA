<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

function redirectToProducts($messageCode)
{
    redirectTo('products.php?message=' . urlencode($messageCode));
}

function insertProductStatusAudit($conn, $activity, $productId, $productName)
{
    $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt === false) {
        return false;
    }

    $adminUserId = (int) $_SESSION['user_id'];
    $description = $activity . ' for product ID ' . $productId . ': ' . $productName . '.';
    $ipAddressValue = $_SERVER['REMOTE_ADDR'] ?? '';
    $ipAddress = is_string($ipAddressValue) ? substr($ipAddressValue, 0, 45) : '';
    $bound = mysqli_stmt_bind_param($auditStmt, 'isss', $adminUserId, $activity, $description, $ipAddress);
    $inserted = $bound && mysqli_stmt_execute($auditStmt) && mysqli_stmt_affected_rows($auditStmt) === 1;
    mysqli_stmt_close($auditStmt);

    return $inserted;
}

function productHasOrderHistory($conn, $productId)
{
    $orderItemSql = 'SELECT order_item_id FROM order_items WHERE product_id = ? LIMIT 1';
    $orderItemStmt = mysqli_prepare($conn, $orderItemSql);

    if ($orderItemStmt === false) {
        return null;
    }

    if (
        !mysqli_stmt_bind_param($orderItemStmt, 'i', $productId) ||
        !mysqli_stmt_execute($orderItemStmt)
    ) {
        mysqli_stmt_close($orderItemStmt);
        return null;
    }

    mysqli_stmt_store_result($orderItemStmt);
    $hasOrderHistory = mysqli_stmt_num_rows($orderItemStmt) > 0;
    mysqli_stmt_close($orderItemStmt);

    return $hasOrderHistory;
}

function findProductForAction($conn, $productId)
{
    $productSql = 'SELECT product_id, product_name, status FROM products WHERE product_id = ? LIMIT 1 FOR UPDATE';
    $productStmt = mysqli_prepare($conn, $productSql);

    if ($productStmt === false) {
        return null;
    }

    if (
        !mysqli_stmt_bind_param($productStmt, 'i', $productId) ||
        !mysqli_stmt_execute($productStmt)
    ) {
        mysqli_stmt_close($productStmt);
        return null;
    }

    mysqli_stmt_bind_result($productStmt, $foundProductId, $productName, $status);

    $product = null;

    if (mysqli_stmt_fetch($productStmt)) {
        $product = [
            'product_id' => (int) $foundProductId,
            'product_name' => $productName,
            'status' => $status
        ];
    }

    mysqli_stmt_close($productStmt);
    return $product;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToProducts('invalid_action');
}

if (!verifyCsrfToken()) {
    redirectToProducts('invalid_action');
}

$productId = parsePositiveIntegerId($_POST['product_id'] ?? '');
$actionValue = $_POST['action'] ?? '';
$action = is_string($actionValue) ? $actionValue : '';

if ($productId === false || !in_array($action, ['activate', 'deactivate'], true)) {
    redirectToProducts('invalid_action');
}

if (!mysqli_begin_transaction($conn)) {
    redirectToProducts('action_failed');
}

$product = findProductForAction($conn, $productId);

if ($product === null) {
    mysqli_rollback($conn);
    redirectToProducts('product_not_found');
}

$newStatus = $action === 'activate' ? 'Active' : 'Inactive';

if ($product['status'] === $newStatus) {
    mysqli_rollback($conn);
    redirectToProducts($newStatus === 'Active' ? 'already_active' : 'already_inactive');
}

$hasOrderHistory = false;

if ($newStatus === 'Inactive') {
    $hasOrderHistory = productHasOrderHistory($conn, $productId);

    if ($hasOrderHistory === null) {
        mysqli_rollback($conn);
        redirectToProducts('action_failed');
    }
}

$updateSql = 'UPDATE products SET status = ? WHERE product_id = ?';
$updateStmt = mysqli_prepare($conn, $updateSql);

if ($updateStmt === false) {
    mysqli_rollback($conn);
    redirectToProducts('action_failed');
}

$updated = mysqli_stmt_bind_param($updateStmt, 'si', $newStatus, $productId) &&
    mysqli_stmt_execute($updateStmt) &&
    mysqli_stmt_affected_rows($updateStmt) === 1;
mysqli_stmt_close($updateStmt);

if (!$updated) {
    mysqli_rollback($conn);
    redirectToProducts('action_failed');
}

$activity = $newStatus === 'Active' ? 'Activate Product' : 'Deactivate Product';

if (!insertProductStatusAudit($conn, $activity, $productId, $product['product_name'])) {
    mysqli_rollback($conn);
    redirectToProducts('action_failed');
}

if (!mysqli_commit($conn)) {
    mysqli_rollback($conn);
    redirectToProducts('action_failed');
}

if ($newStatus === 'Active') {
    redirectToProducts('product_activated');
}

redirectToProducts($hasOrderHistory ? 'product_deactivated_history' : 'product_deactivated');
