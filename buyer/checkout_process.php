<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();

$cartRedirectPath = rtrim(APP_BASE_URL, '/') . '/buyer/cart.php';

function checkoutProcessRedirect($message, $type = 'danger')
{
    $_SESSION['cart_flash'] = [
        'message' => $message,
        'type' => $type
    ];

    redirectTo(rtrim(APP_BASE_URL, '/') . '/buyer/cart.php');
}

function createOrderNumber()
{
    return 'TELA-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(8)));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkoutProcessRedirect('The checkout request was invalid.');
}

if (!verifyCsrfToken()) {
    $_SESSION['cart_flash'] = [
        'message' => 'The checkout request could not be verified.',
        'type' => 'danger'
    ];
    csrfFailure($cartRedirectPath);
}

$allowedPaymentMethods = ['Cash on Delivery', 'GCash Simulation'];
$paymentMethod = $_POST['payment_method'] ?? '';

if (!is_string($paymentMethod) || !in_array($paymentMethod, $allowedPaymentMethods, true)) {
    checkoutProcessRedirect('Please select a valid simulated payment method.');
}

if (!verifyCheckoutToken()) {
    checkoutProcessRedirect('This checkout request is invalid or has already been used. Please review your cart and try again.');
}

unset($_SESSION['checkout_token']);

$buyerId = (int) $_SESSION['user_id'];
require_once __DIR__ . '/../config/database.php';

$transactionStarted = false;
$failureMessage = 'Checkout could not be completed right now. Your cart was not changed.';

try {
    if (!mysqli_begin_transaction($conn)) {
        throw new RuntimeException('Transaction start failed.');
    }

    $transactionStarted = true;

    $profileSql = '
        SELECT full_name, email, address, contact_number
        FROM users
        WHERE user_id = ?
        LIMIT 1
        FOR UPDATE
    ';
    $profileStmt = mysqli_prepare($conn, $profileSql);

    if ($profileStmt === false) {
        throw new RuntimeException('Profile statement preparation failed.');
    }

    if (!mysqli_stmt_bind_param($profileStmt, 'i', $buyerId)) {
        mysqli_stmt_close($profileStmt);
        throw new RuntimeException('Profile parameter binding failed.');
    }

    if (!mysqli_stmt_execute($profileStmt)) {
        mysqli_stmt_close($profileStmt);
        throw new RuntimeException('Profile query failed.');
    }

    if (!mysqli_stmt_bind_result($profileStmt, $fullName, $email, $address, $contactNumber)) {
        mysqli_stmt_close($profileStmt);
        throw new RuntimeException('Profile result binding failed.');
    }

    $profileFetchResult = mysqli_stmt_fetch($profileStmt);
    mysqli_stmt_close($profileStmt);

    if ($profileFetchResult !== true) {
        $failureMessage = 'Your registered account information could not be verified. Please try again later.';
        throw new RuntimeException('Buyer profile was not found.');
    }

    if (
        trim((string) $fullName) === '' ||
        trim((string) $email) === '' ||
        trim((string) $address) === '' ||
        trim((string) $contactNumber) === ''
    ) {
        $failureMessage = 'Your registered account information is incomplete. Please review your checkout information.';
        throw new RuntimeException('Buyer profile is incomplete.');
    }

    $cartSql = '
        SELECT
            cart.cart_id,
            cart.product_id,
            cart.quantity,
            products.product_id AS found_product_id,
            products.product_name,
            products.price,
            products.stock,
            products.status,
            categories.category_id AS found_category_id,
            categories.category_name
        FROM cart
        LEFT JOIN products ON cart.product_id = products.product_id
        LEFT JOIN categories ON products.category_id = categories.category_id
        WHERE cart.user_id = ?
        ORDER BY cart.cart_id ASC
        FOR UPDATE
    ';
    $cartStmt = mysqli_prepare($conn, $cartSql);

    if ($cartStmt === false) {
        throw new RuntimeException('Cart statement preparation failed.');
    }

    if (!mysqli_stmt_bind_param($cartStmt, 'i', $buyerId)) {
        mysqli_stmt_close($cartStmt);
        throw new RuntimeException('Cart parameter binding failed.');
    }

    if (!mysqli_stmt_execute($cartStmt)) {
        mysqli_stmt_close($cartStmt);
        throw new RuntimeException('Cart query failed.');
    }

    if (!mysqli_stmt_bind_result(
        $cartStmt,
        $cartId,
        $cartProductId,
        $cartQuantity,
        $foundProductId,
        $productName,
        $productPrice,
        $productStock,
        $productStatus,
        $foundCategoryId,
        $categoryName
    )) {
        mysqli_stmt_close($cartStmt);
        throw new RuntimeException('Cart result binding failed.');
    }

    $lockedCartRows = [];

    while (($cartFetchResult = mysqli_stmt_fetch($cartStmt)) === true) {
        $lockedCartRows[] = [
            'cart_id' => (int) $cartId,
            'product_id' => (int) $cartProductId,
            'quantity' => (int) $cartQuantity,
            'found_product_id' => $foundProductId,
            'product_name' => $productName,
            'price' => $productPrice,
            'stock' => $productStock,
            'status' => $productStatus,
            'found_category_id' => $foundCategoryId,
            'category_name' => $categoryName
        ];
    }

    mysqli_stmt_close($cartStmt);

    if ($cartFetchResult === false) {
        throw new RuntimeException('Cart result fetch failed.');
    }

    if (empty($lockedCartRows)) {
        $failureMessage = 'Your cart is empty. Add a Hoodie before checking out.';
        throw new RuntimeException('Cart is empty.');
    }

    $orderItems = [];
    $orderTotal = 0.00;

    foreach ($lockedCartRows as $cartRow) {
        $productExists = $cartRow['found_product_id'] !== null;
        $categoryExists = $cartRow['found_category_id'] !== null;
        $quantity = (int) $cartRow['quantity'];
        $stock = $cartRow['stock'] !== null ? (int) $cartRow['stock'] : 0;
        $priceIsValid = $cartRow['price'] !== null && is_numeric($cartRow['price']);
        $price = $priceIsValid ? (float) $cartRow['price'] : 0.00;

        if (
            !$productExists ||
            !$categoryExists ||
            $cartRow['category_name'] !== PRODUCT_CATEGORY_NAME ||
            $cartRow['status'] !== 'Active' ||
            $stock <= 0 ||
            $quantity <= 0 ||
            $quantity > $stock ||
            $price <= 0
        ) {
            $failureMessage = 'Checkout could not be completed because one or more cart items are unavailable. Please review your cart.';
            throw new RuntimeException('A cart item failed eligibility validation.');
        }

        $subtotal = round($price * $quantity, 2);

        if ($subtotal <= 0) {
            $failureMessage = 'Checkout could not be completed because the cart total is invalid. Please review your cart.';
            throw new RuntimeException('An order item subtotal is invalid.');
        }

        $orderItems[] = [
            'product_id' => (int) $cartRow['found_product_id'],
            'product_name' => (string) $cartRow['product_name'],
            'quantity' => $quantity,
            'price' => round($price, 2),
            'subtotal' => $subtotal
        ];
        $orderTotal = round($orderTotal + $subtotal, 2);
    }

    $subtotalSum = round(array_sum(array_column($orderItems, 'subtotal')), 2);

    if ($orderTotal <= 0 || abs($orderTotal - $subtotalSum) > 0.001) {
        $failureMessage = 'Checkout could not be completed because the cart total is invalid. Please review your cart.';
        throw new RuntimeException('Order total validation failed.');
    }

    $orderId = 0;
    $orderNumber = '';
    $orderStatus = 'Pending';

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $candidateOrderNumber = createOrderNumber();

        if (strlen($candidateOrderNumber) > 50) {
            throw new RuntimeException('Generated order number is too long.');
        }

        $orderSql = '
            INSERT INTO orders (user_id, order_number, total_amount, payment_method, order_status)
            VALUES (?, ?, ?, ?, ?)
        ';
        $orderStmt = mysqli_prepare($conn, $orderSql);

        if ($orderStmt === false) {
            throw new RuntimeException('Order statement preparation failed.');
        }

        $candidateOrderInserted = false;
        $orderErrorCode = 0;

        if (!mysqli_stmt_bind_param(
            $orderStmt,
            'isdss',
            $buyerId,
            $candidateOrderNumber,
            $orderTotal,
            $paymentMethod,
            $orderStatus
        )) {
            mysqli_stmt_close($orderStmt);
            throw new RuntimeException('Order parameter binding failed.');
        }

        try {
            $candidateOrderInserted = mysqli_stmt_execute($orderStmt);
        } catch (mysqli_sql_exception $exception) {
            $orderErrorCode = (int) $exception->getCode();
        }

        if (!$candidateOrderInserted && $orderErrorCode === 0) {
            $orderErrorCode = mysqli_stmt_errno($orderStmt);
        }

        if ($candidateOrderInserted) {
            $affectedOrders = mysqli_stmt_affected_rows($orderStmt);
            $newOrderId = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($orderStmt);

            if ($affectedOrders !== 1 || $newOrderId <= 0) {
                throw new RuntimeException('Order insert verification failed.');
            }

            $orderId = $newOrderId;
            $orderNumber = $candidateOrderNumber;
            break;
        }

        mysqli_stmt_close($orderStmt);

        if ($orderErrorCode !== 1062) {
            throw new RuntimeException('Order insert failed.');
        }
    }

    if ($orderId <= 0 || $orderNumber === '') {
        throw new RuntimeException('Order number retries were exhausted.');
    }

    $orderItemSql = '
        INSERT INTO order_items (order_id, product_id, product_name, quantity, price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ';
    $orderItemStmt = mysqli_prepare($conn, $orderItemSql);

    if ($orderItemStmt === false) {
        throw new RuntimeException('Order item statement preparation failed.');
    }

    foreach ($orderItems as $orderItem) {
        $itemProductId = $orderItem['product_id'];
        $itemProductName = $orderItem['product_name'];
        $itemQuantity = $orderItem['quantity'];
        $itemPrice = $orderItem['price'];
        $itemSubtotal = $orderItem['subtotal'];

        if (!mysqli_stmt_bind_param(
            $orderItemStmt,
            'iisidd',
            $orderId,
            $itemProductId,
            $itemProductName,
            $itemQuantity,
            $itemPrice,
            $itemSubtotal
        )) {
            mysqli_stmt_close($orderItemStmt);
            throw new RuntimeException('Order item parameter binding failed.');
        }

        if (!mysqli_stmt_execute($orderItemStmt) || mysqli_stmt_affected_rows($orderItemStmt) !== 1) {
            mysqli_stmt_close($orderItemStmt);
            throw new RuntimeException('Order item insert failed.');
        }
    }

    mysqli_stmt_close($orderItemStmt);

    $stockSql = '
        UPDATE products
        INNER JOIN categories ON products.category_id = categories.category_id
        SET products.stock = products.stock - ?
        WHERE products.product_id = ?
          AND products.status = ?
          AND products.stock >= ?
          AND categories.category_name = ?
    ';
    $stockStmt = mysqli_prepare($conn, $stockSql);

    if ($stockStmt === false) {
        throw new RuntimeException('Stock statement preparation failed.');
    }

    $requiredStatus = 'Active';
    $requiredCategory = PRODUCT_CATEGORY_NAME;

    foreach ($orderItems as $orderItem) {
        $stockQuantity = $orderItem['quantity'];
        $stockProductId = $orderItem['product_id'];

        if (!mysqli_stmt_bind_param(
            $stockStmt,
            'iisis',
            $stockQuantity,
            $stockProductId,
            $requiredStatus,
            $stockQuantity,
            $requiredCategory
        )) {
            mysqli_stmt_close($stockStmt);
            throw new RuntimeException('Stock parameter binding failed.');
        }

        if (!mysqli_stmt_execute($stockStmt) || mysqli_stmt_affected_rows($stockStmt) !== 1) {
            mysqli_stmt_close($stockStmt);
            throw new RuntimeException('Conditional stock update failed.');
        }
    }

    mysqli_stmt_close($stockStmt);

    $cartDeleteSql = 'DELETE FROM cart WHERE user_id = ?';
    $cartDeleteStmt = mysqli_prepare($conn, $cartDeleteSql);

    if ($cartDeleteStmt === false) {
        throw new RuntimeException('Cart delete statement preparation failed.');
    }

    if (!mysqli_stmt_bind_param($cartDeleteStmt, 'i', $buyerId)) {
        mysqli_stmt_close($cartDeleteStmt);
        throw new RuntimeException('Cart delete parameter binding failed.');
    }

    $cartDeleteSucceeded = mysqli_stmt_execute($cartDeleteStmt);
    $deletedCartRows = mysqli_stmt_affected_rows($cartDeleteStmt);
    mysqli_stmt_close($cartDeleteStmt);

    if (!$cartDeleteSucceeded || $deletedCartRows !== count($lockedCartRows)) {
        throw new RuntimeException('Cart clearing verification failed.');
    }

    $auditSql = '
        INSERT INTO audit_logs (user_id, activity, description, ip_address)
        VALUES (?, ?, ?, ?)
    ';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt === false) {
        throw new RuntimeException('Audit statement preparation failed.');
    }

    $activity = 'Checkout';
    $description = 'Order ' . $orderNumber . ' (ID ' . $orderId . ') placed with ' . count($orderItems) .
        ' item(s), total PHP ' . number_format($orderTotal, 2, '.', '') . ', payment method: ' . $paymentMethod . '.';
    $ipAddressValue = $_SERVER['REMOTE_ADDR'] ?? '';
    $ipAddress = is_string($ipAddressValue) ? substr($ipAddressValue, 0, 45) : '';

    if (!mysqli_stmt_bind_param($auditStmt, 'isss', $buyerId, $activity, $description, $ipAddress)) {
        mysqli_stmt_close($auditStmt);
        throw new RuntimeException('Audit parameter binding failed.');
    }

    if (!mysqli_stmt_execute($auditStmt) || mysqli_stmt_affected_rows($auditStmt) !== 1) {
        mysqli_stmt_close($auditStmt);
        throw new RuntimeException('Audit insert failed.');
    }

    mysqli_stmt_close($auditStmt);

    if (!mysqli_commit($conn)) {
        throw new RuntimeException('Transaction commit failed.');
    }

    $transactionStarted = false;
    $_SESSION['recent_order_id'] = $orderId;
    checkoutProcessRedirect('Your order was placed successfully.', 'success');
} catch (Throwable $exception) {
    if ($transactionStarted) {
        try {
            mysqli_rollback($conn);
        } catch (Throwable $rollbackException) {
            // The buyer still receives a generic message without database details.
        }
    }

    error_log('Checkout processing failed for buyer user ID ' . $buyerId . '.');
    checkoutProcessRedirect($failureMessage);
}
