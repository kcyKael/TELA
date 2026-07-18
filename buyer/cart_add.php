<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();

$cartRedirectPath = rtrim(APP_BASE_URL, '/') . '/buyer/cart.php';

function cartAddRedirect($message, $type = 'danger')
{
    $_SESSION['cart_flash'] = [
        'message' => $message,
        'type' => $type
    ];

    redirectTo(rtrim(APP_BASE_URL, '/') . '/buyer/cart.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cartAddRedirect('The Add to Cart request was invalid.');
}

if (!verifyCsrfToken()) {
    $_SESSION['cart_flash'] = [
        'message' => 'The Add to Cart request could not be verified.',
        'type' => 'danger'
    ];
    csrfFailure($cartRedirectPath);
}

$submittedProductId = $_POST['product_id'] ?? '';

if (!is_string($submittedProductId) || !ctype_digit($submittedProductId)) {
    cartAddRedirect('The selected product could not be added to your cart.');
}

$productId = (int) $submittedProductId;

if ($productId <= 0) {
    cartAddRedirect('The selected product could not be added to your cart.');
}

$buyerId = (int) $_SESSION['user_id'];
require_once __DIR__ . '/../config/database.php';

$productSql = '
    SELECT p.product_id, p.product_name, p.stock, p.status, c.category_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    WHERE p.product_id = ?
    LIMIT 1
';
$productStmt = mysqli_prepare($conn, $productSql);

if ($productStmt === false) {
    cartAddRedirect('The product could not be added right now. Please try again later.');
}

mysqli_stmt_bind_param($productStmt, 'i', $productId);
$productQuerySucceeded = mysqli_stmt_execute($productStmt);

if (!$productQuerySucceeded) {
    mysqli_stmt_close($productStmt);
    cartAddRedirect('The product could not be added right now. Please try again later.');
}

mysqli_stmt_bind_result($productStmt, $foundProductId, $productName, $productStock, $productStatus, $categoryName);
$productFound = mysqli_stmt_fetch($productStmt);
mysqli_stmt_close($productStmt);

if (!$productFound) {
    cartAddRedirect('The selected product is unavailable.');
}

$productId = (int) $foundProductId;
$productStock = (int) $productStock;

if ($productStatus !== 'Active' || $categoryName !== PRODUCT_CATEGORY_NAME || $productStock <= 0) {
    cartAddRedirect('The selected product is unavailable.');
}

$cartLookupSql = 'SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ? LIMIT 1';
$cartLookupStmt = mysqli_prepare($conn, $cartLookupSql);

if ($cartLookupStmt === false) {
    cartAddRedirect('The product could not be added right now. Please try again later.');
}

mysqli_stmt_bind_param($cartLookupStmt, 'ii', $buyerId, $productId);
$cartLookupSucceeded = mysqli_stmt_execute($cartLookupStmt);

if (!$cartLookupSucceeded) {
    mysqli_stmt_close($cartLookupStmt);
    cartAddRedirect('The product could not be added right now. Please try again later.');
}

mysqli_stmt_bind_result($cartLookupStmt, $cartId, $currentQuantity);
$cartRowFound = mysqli_stmt_fetch($cartLookupStmt);
mysqli_stmt_close($cartLookupStmt);

$resultingQuantity = 1;
$cartChanged = false;

if ($cartRowFound) {
    $cartId = (int) $cartId;
    $resultingQuantity = (int) $currentQuantity + 1;

    if ($resultingQuantity > $productStock) {
        cartAddRedirect('The available stock limit for this product has been reached.', 'warning');
    }

    $updateSql = 'UPDATE cart SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE cart_id = ? AND user_id = ?';
    $updateStmt = mysqli_prepare($conn, $updateSql);

    if ($updateStmt !== false) {
        mysqli_stmt_bind_param($updateStmt, 'iii', $resultingQuantity, $cartId, $buyerId);
        $cartChanged = mysqli_stmt_execute($updateStmt) && mysqli_stmt_affected_rows($updateStmt) === 1;
        mysqli_stmt_close($updateStmt);
    }
} else {
    $insertSql = 'INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)';
    $insertStmt = mysqli_prepare($conn, $insertSql);

    if ($insertStmt !== false) {
        mysqli_stmt_bind_param($insertStmt, 'iii', $buyerId, $productId, $resultingQuantity);
        $cartChanged = mysqli_stmt_execute($insertStmt) && mysqli_stmt_affected_rows($insertStmt) === 1;
        mysqli_stmt_close($insertStmt);
    }
}

if (!$cartChanged) {
    cartAddRedirect('The product could not be added right now. Please try again later.');
}

$auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
$auditStmt = mysqli_prepare($conn, $auditSql);

if ($auditStmt !== false) {
    $activity = 'Add to Cart';
    $description = 'Added product ID ' . $productId . ' (' . $productName . ') to cart. Resulting quantity: ' . $resultingQuantity . '.';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    mysqli_stmt_bind_param($auditStmt, 'isss', $buyerId, $activity, $description, $ipAddress);
    mysqli_stmt_execute($auditStmt);
    mysqli_stmt_close($auditStmt);
}

cartAddRedirect('Product added to your cart.', 'success');
