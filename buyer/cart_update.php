<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();

$cartRedirectPath = rtrim(APP_BASE_URL, '/') . '/buyer/cart.php';

function cartUpdateRedirect($message, $type = 'danger')
{
    $_SESSION['cart_flash'] = [
        'message' => $message,
        'type' => $type
    ];

    redirectTo(rtrim(APP_BASE_URL, '/') . '/buyer/cart.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cartUpdateRedirect('The cart update request was invalid.');
}

if (!verifyCsrfToken()) {
    $_SESSION['cart_flash'] = [
        'message' => 'The cart update request could not be verified.',
        'type' => 'danger'
    ];
    csrfFailure($cartRedirectPath);
}

$submittedCartId = $_POST['cart_id'] ?? '';
$submittedQuantity = $_POST['quantity'] ?? '';

if (
    !is_string($submittedCartId) ||
    !ctype_digit($submittedCartId) ||
    strlen($submittedCartId) > 10
) {
    cartUpdateRedirect('The selected cart item could not be updated.');
}

if (
    !is_string($submittedQuantity) ||
    !ctype_digit($submittedQuantity) ||
    strlen($submittedQuantity) > 10
) {
    cartUpdateRedirect('Please enter a valid quantity of at least 1.');
}

$cartId = (int) $submittedCartId;
$requestedQuantity = (int) $submittedQuantity;

if ($cartId <= 0) {
    cartUpdateRedirect('The selected cart item could not be updated.');
}

if ($requestedQuantity < 1) {
    cartUpdateRedirect('Please enter a valid quantity of at least 1.');
}

$buyerId = (int) $_SESSION['user_id'];
require_once __DIR__ . '/../config/database.php';

$cartLookupSql = '
    SELECT
        cart.cart_id,
        cart.product_id,
        cart.quantity,
        products.product_name,
        products.stock,
        products.status,
        categories.category_name
    FROM cart
    INNER JOIN products ON cart.product_id = products.product_id
    INNER JOIN categories ON products.category_id = categories.category_id
    WHERE cart.cart_id = ? AND cart.user_id = ?
    LIMIT 1
';
$cartLookupStmt = mysqli_prepare($conn, $cartLookupSql);

if ($cartLookupStmt === false) {
    cartUpdateRedirect('The cart item could not be updated right now. Please try again later.');
}

mysqli_stmt_bind_param($cartLookupStmt, 'ii', $cartId, $buyerId);
$cartLookupSucceeded = mysqli_stmt_execute($cartLookupStmt);

if (!$cartLookupSucceeded) {
    mysqli_stmt_close($cartLookupStmt);
    cartUpdateRedirect('The cart item could not be updated right now. Please try again later.');
}

mysqli_stmt_bind_result(
    $cartLookupStmt,
    $foundCartId,
    $productId,
    $currentQuantity,
    $productName,
    $productStock,
    $productStatus,
    $categoryName
);
$cartRowFound = mysqli_stmt_fetch($cartLookupStmt);
mysqli_stmt_close($cartLookupStmt);

if (!$cartRowFound) {
    cartUpdateRedirect('The selected cart item could not be updated.');
}

$cartId = (int) $foundCartId;
$productId = (int) $productId;
$currentQuantity = (int) $currentQuantity;
$productStock = (int) $productStock;

if ($productStatus !== 'Active' || $categoryName !== PRODUCT_CATEGORY_NAME || $productStock <= 0) {
    cartUpdateRedirect('This cart item is unavailable and cannot be updated.', 'warning');
}

if ($requestedQuantity > $productStock) {
    cartUpdateRedirect('The requested quantity exceeds current stock.', 'warning');
}

if ($requestedQuantity === $currentQuantity) {
    cartUpdateRedirect('The cart quantity is already set to that value.', 'info');
}

$updateSql = 'UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE cart_id = ? AND user_id = ?';
$updateStmt = mysqli_prepare($conn, $updateSql);

if ($updateStmt === false) {
    cartUpdateRedirect('The cart item could not be updated right now. Please try again later.');
}

mysqli_stmt_bind_param($updateStmt, 'iii', $requestedQuantity, $cartId, $buyerId);
$updateSucceeded = mysqli_stmt_execute($updateStmt) && mysqli_stmt_affected_rows($updateStmt) === 1;
mysqli_stmt_close($updateStmt);

if (!$updateSucceeded) {
    cartUpdateRedirect('The cart item could not be updated right now. Please try again later.');
}

$auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
$auditStmt = mysqli_prepare($conn, $auditSql);

if ($auditStmt !== false) {
    $activity = 'Update Cart';
    $description = 'Updated product ID ' . $productId . ' (' . $productName . ') cart quantity to ' . $requestedQuantity . '.';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    mysqli_stmt_bind_param($auditStmt, 'isss', $buyerId, $activity, $description, $ipAddress);
    mysqli_stmt_execute($auditStmt);
    mysqli_stmt_close($auditStmt);
}

cartUpdateRedirect('Cart quantity updated successfully.', 'success');
