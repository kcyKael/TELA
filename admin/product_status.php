<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

function redirectToProducts($messageCode)
{
    redirectTo('products.php?message=' . urlencode($messageCode));
}

function logProductAction($conn, $activity, $description)
{
    $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt !== false) {
        $adminUserId = $_SESSION['user_id'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        mysqli_stmt_bind_param($auditStmt, 'isss', $adminUserId, $activity, $description, $ipAddress);
        mysqli_stmt_execute($auditStmt);
        mysqli_stmt_close($auditStmt);
    }
}

function productHasOrderHistory($conn, $productId)
{
    $orderItemSql = 'SELECT order_item_id FROM order_items WHERE product_id = ? LIMIT 1';
    $orderItemStmt = mysqli_prepare($conn, $orderItemSql);

    if ($orderItemStmt === false) {
        return true;
    }

    mysqli_stmt_bind_param($orderItemStmt, 'i', $productId);
    mysqli_stmt_execute($orderItemStmt);
    mysqli_stmt_store_result($orderItemStmt);
    $hasOrderHistory = mysqli_stmt_num_rows($orderItemStmt) > 0;
    mysqli_stmt_close($orderItemStmt);

    return $hasOrderHistory;
}

function findProductForAction($conn, $productId)
{
    $productSql = 'SELECT product_id, product_name, status FROM products WHERE product_id = ? LIMIT 1';
    $productStmt = mysqli_prepare($conn, $productSql);

    if ($productStmt === false) {
        return null;
    }

    mysqli_stmt_bind_param($productStmt, 'i', $productId);
    mysqli_stmt_execute($productStmt);
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

$productId = (int) ($_POST['product_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($productId <= 0 || !in_array($action, ['activate', 'deactivate'], true)) {
    redirectToProducts('invalid_action');
}

$product = findProductForAction($conn, $productId);

if ($product === null) {
    redirectToProducts('product_not_found');
}

$newStatus = $action === 'activate' ? 'Active' : 'Inactive';
$hasOrderHistory = $newStatus === 'Inactive' ? productHasOrderHistory($conn, $productId) : false;

if ($product['status'] === $newStatus) {
    redirectToProducts($newStatus === 'Active' ? 'already_active' : 'already_inactive');
}

$updateSql = 'UPDATE products SET status = ? WHERE product_id = ?';
$updateStmt = mysqli_prepare($conn, $updateSql);

if ($updateStmt === false) {
    redirectToProducts('action_failed');
}

mysqli_stmt_bind_param($updateStmt, 'si', $newStatus, $productId);
$updated = mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

if (!$updated) {
    redirectToProducts('action_failed');
}

if ($newStatus === 'Inactive') {
    logProductAction($conn, 'Deactivate Product', 'Admin deactivated product: ' . $product['product_name']);
    redirectToProducts($hasOrderHistory ? 'product_deactivated_history' : 'product_deactivated');
}

logProductAction($conn, 'Activate Product', 'Admin activated product: ' . $product['product_name']);
redirectToProducts('product_activated');