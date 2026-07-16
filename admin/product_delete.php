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

function findProductForAction($conn, $productId)
{
    $productSql = 'SELECT product_id, product_name, image_path, status FROM products WHERE product_id = ? LIMIT 1';
    $productStmt = mysqli_prepare($conn, $productSql);

    if ($productStmt === false) {
        return null;
    }

    mysqli_stmt_bind_param($productStmt, 'i', $productId);
    mysqli_stmt_execute($productStmt);
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
        return true;
    }

    mysqli_stmt_bind_param($orderItemStmt, 'i', $productId);
    mysqli_stmt_execute($orderItemStmt);
    mysqli_stmt_store_result($orderItemStmt);
    $hasOrderHistory = mysqli_stmt_num_rows($orderItemStmt) > 0;
    mysqli_stmt_close($orderItemStmt);

    return $hasOrderHistory;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToProducts('invalid_action');
}

$productId = (int) ($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    redirectToProducts('invalid_action');
}

$product = findProductForAction($conn, $productId);

if ($product === null) {
    redirectToProducts('product_not_found');
}

$hasOrderHistory = productHasOrderHistory($conn, $productId);

if ($product['status'] === 'Inactive') {
    redirectToProducts('already_inactive');
}

$updateSql = 'UPDATE products SET status = ? WHERE product_id = ?';
$updateStmt = mysqli_prepare($conn, $updateSql);

if ($updateStmt === false) {
    redirectToProducts('action_failed');
}

$inactiveStatus = 'Inactive';
mysqli_stmt_bind_param($updateStmt, 'si', $inactiveStatus, $productId);
$updated = mysqli_stmt_execute($updateStmt);
mysqli_stmt_close($updateStmt);

if (!$updated) {
    redirectToProducts('action_failed');
}

logProductAction($conn, 'Deactivate Product', 'Admin deactivated product: ' . $product['product_name']);

if ($hasOrderHistory) {
    redirectToProducts('product_deactivated_history');
}

redirectToProducts('product_deactivated');