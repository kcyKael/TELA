<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();

$cartRedirectPath = rtrim(APP_BASE_URL, '/') . '/buyer/cart.php';

function cartRemoveRedirect($message, $type = 'danger')
{
    $_SESSION['cart_flash'] = [
        'message' => $message,
        'type' => $type
    ];

    redirectTo(rtrim(APP_BASE_URL, '/') . '/buyer/cart.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cartRemoveRedirect('The cart removal request was invalid.');
}

if (!verifyCsrfToken()) {
    $_SESSION['cart_flash'] = [
        'message' => 'The cart removal request could not be verified.',
        'type' => 'danger'
    ];
    csrfFailure($cartRedirectPath);
}

$submittedCartId = $_POST['cart_id'] ?? '';

if (
    !is_string($submittedCartId) ||
    !ctype_digit($submittedCartId) ||
    strlen($submittedCartId) > 10
) {
    cartRemoveRedirect('The selected cart item could not be removed.');
}

$cartId = (int) $submittedCartId;

if ($cartId <= 0) {
    cartRemoveRedirect('The selected cart item could not be removed.');
}

$buyerId = (int) $_SESSION['user_id'];
require_once __DIR__ . '/../config/database.php';

$cartLookupSql = '
    SELECT
        cart.cart_id,
        cart.product_id,
        products.product_name
    FROM cart
    INNER JOIN products ON cart.product_id = products.product_id
    WHERE cart.cart_id = ? AND cart.user_id = ?
    LIMIT 1
';
$cartLookupStmt = mysqli_prepare($conn, $cartLookupSql);

if ($cartLookupStmt === false) {
    cartRemoveRedirect('The cart item could not be removed right now. Please try again later.');
}

mysqli_stmt_bind_param($cartLookupStmt, 'ii', $cartId, $buyerId);
$cartLookupSucceeded = mysqli_stmt_execute($cartLookupStmt);

if (!$cartLookupSucceeded) {
    mysqli_stmt_close($cartLookupStmt);
    cartRemoveRedirect('The cart item could not be removed right now. Please try again later.');
}

mysqli_stmt_bind_result(
    $cartLookupStmt,
    $foundCartId,
    $productId,
    $productName
);
$cartRowFound = mysqli_stmt_fetch($cartLookupStmt);
mysqli_stmt_close($cartLookupStmt);

if (!$cartRowFound) {
    cartRemoveRedirect('The selected cart item could not be removed.');
}

$cartId = (int) $foundCartId;
$productId = (int) $productId;

$deleteSql = 'DELETE FROM cart WHERE cart_id = ? AND user_id = ?';
$deleteStmt = mysqli_prepare($conn, $deleteSql);

if ($deleteStmt === false) {
    cartRemoveRedirect('The cart item could not be removed right now. Please try again later.');
}

mysqli_stmt_bind_param($deleteStmt, 'ii', $cartId, $buyerId);
$deleteSucceeded = mysqli_stmt_execute($deleteStmt) && mysqli_stmt_affected_rows($deleteStmt) === 1;
mysqli_stmt_close($deleteStmt);

if (!$deleteSucceeded) {
    cartRemoveRedirect('The selected cart item could not be removed.');
}

$auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
$auditStmt = mysqli_prepare($conn, $auditSql);

if ($auditStmt !== false) {
    $activity = 'Remove Cart Item';
    $description = 'Removed cart item ID ' . $cartId . ' for product ID ' . $productId . ' (' . $productName . ').';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    mysqli_stmt_bind_param($auditStmt, 'isss', $buyerId, $activity, $description, $ipAddress);
    mysqli_stmt_execute($auditStmt);
    mysqli_stmt_close($auditStmt);
}

cartRemoveRedirect('Cart item removed successfully.', 'success');
