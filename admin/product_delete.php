<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

function redirectToProducts($messageCode)
{
    redirectTo('products.php?message=' . urlencode($messageCode));
}

function insertProductDeactivationAudit($conn, $productId, $productName)
{
    $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt === false) {
        return false;
    }

    $adminUserId = (int) $_SESSION['user_id'];
    $activity = 'Deactivate Product';
    $description = 'Deactivated product ID ' . $productId . ': ' . $productName . '.';
    $ipAddressValue = $_SERVER['REMOTE_ADDR'] ?? '';
    $ipAddress = is_string($ipAddressValue) ? substr($ipAddressValue, 0, 45) : '';
    $bound = mysqli_stmt_bind_param($auditStmt, 'isss', $adminUserId, $activity, $description, $ipAddress);
    $inserted = $bound && mysqli_stmt_execute($auditStmt) && mysqli_stmt_affected_rows($auditStmt) === 1;
    mysqli_stmt_close($auditStmt);

    return $inserted;
}

function findProductForAction($conn, $productId)
{
    $productSql = 'SELECT product_id, product_name, image_path, status FROM products WHERE product_id = ? LIMIT 1 FOR UPDATE';
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

    mysqli_stmt_bind_result($productStmt, $foundProductId, $productName, $imagePath, $status);

    $product = null;

    if (mysqli_stmt_fetch($productStmt)) {
        $product = [
            'product_id' => (int) $foundProductId,
            'product_name' => $productName,
            'image_path' => $imagePath,
            'status' => $status
        ];
    }

    mysqli_stmt_close($productStmt);
    return $product;
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToProducts('invalid_action');
}

if (!verifyCsrfToken()) {
    redirectToProducts('invalid_action');
}

$productId = parsePositiveIntegerId($_POST['product_id'] ?? '');

if ($productId === false) {
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

$hasOrderHistory = productHasOrderHistory($conn, $productId);

if ($hasOrderHistory === null) {
    mysqli_rollback($conn);
    redirectToProducts('action_failed');
}

if ($product['status'] === 'Inactive') {
    mysqli_rollback($conn);
    redirectToProducts('already_inactive');
}

$updateSql = 'UPDATE products SET status = ? WHERE product_id = ?';
$updateStmt = mysqli_prepare($conn, $updateSql);

if ($updateStmt === false) {
    mysqli_rollback($conn);
    redirectToProducts('action_failed');
}

$inactiveStatus = 'Inactive';
$updated = mysqli_stmt_bind_param($updateStmt, 'si', $inactiveStatus, $productId) &&
    mysqli_stmt_execute($updateStmt) &&
    mysqli_stmt_affected_rows($updateStmt) === 1;
mysqli_stmt_close($updateStmt);

if (!$updated) {
    mysqli_rollback($conn);
    redirectToProducts('action_failed');
}

if (!insertProductDeactivationAudit($conn, $productId, $product['product_name'])) {
    mysqli_rollback($conn);
    redirectToProducts('action_failed');
}

if (!mysqli_commit($conn)) {
    mysqli_rollback($conn);
    redirectToProducts('action_failed');
}

if ($hasOrderHistory) {
    redirectToProducts('product_deactivated_history');
}

redirectToProducts('product_deactivated');