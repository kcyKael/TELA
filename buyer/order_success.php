<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    redirectTo(rtrim(APP_BASE_URL, '/') . '/buyer/cart.php');
}

$pageTitle = 'Order Confirmation';
$activePage = 'cart';
$buyerId = (int) $_SESSION['user_id'];
$submittedOrderId = $_GET['order_id'] ?? '';
$orderId = 0;
$orderAvailable = false;
$confirmationLoadError = '';
$order = [];
$orderItems = [];

if (
    is_string($submittedOrderId) &&
    $submittedOrderId !== '' &&
    strlen($submittedOrderId) <= 10 &&
    ctype_digit($submittedOrderId)
) {
    $orderId = (int) $submittedOrderId;
}

if ($orderId > 0) {
    require_once __DIR__ . '/../config/database.php';

    $orderSql = '
        SELECT
            orders.order_id,
            orders.order_number,
            orders.total_amount,
            orders.payment_method,
            orders.order_status,
            orders.created_at,
            users.full_name,
            users.email,
            users.address,
            users.contact_number
        FROM orders
        INNER JOIN users ON orders.user_id = users.user_id
        WHERE orders.order_id = ? AND orders.user_id = ?
        LIMIT 1
    ';
    $orderStmt = mysqli_prepare($conn, $orderSql);

    if ($orderStmt === false) {
        $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
    } elseif (!mysqli_stmt_bind_param($orderStmt, 'ii', $orderId, $buyerId)) {
        $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
        mysqli_stmt_close($orderStmt);
    } elseif (!mysqli_stmt_execute($orderStmt)) {
        $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
        mysqli_stmt_close($orderStmt);
    } elseif (!mysqli_stmt_bind_result(
        $orderStmt,
        $foundOrderId,
        $orderNumber,
        $totalAmount,
        $paymentMethod,
        $orderStatus,
        $createdAt,
        $fullName,
        $email,
        $address,
        $contactNumber
    )) {
        $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
        mysqli_stmt_close($orderStmt);
    } else {
        $orderFound = mysqli_stmt_fetch($orderStmt);
        mysqli_stmt_close($orderStmt);

        if ($orderFound === true) {
            $order = [
                'order_id' => (int) $foundOrderId,
                'order_number' => $orderNumber,
                'total_amount' => (float) $totalAmount,
                'payment_method' => $paymentMethod,
                'order_status' => $orderStatus,
                'created_at' => $createdAt,
                'full_name' => $fullName,
                'email' => $email,
                'address' => $address,
                'contact_number' => $contactNumber
            ];
            $orderAvailable = true;
        } elseif ($orderFound === false) {
            $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
        }
    }
}

if ($orderAvailable) {
    $allowedOrderStatuses = ['Pending', 'Processing', 'Completed', 'Cancelled'];

    if (
        !in_array($order['order_status'], $allowedOrderStatuses, true) ||
        $order['total_amount'] <= 0
    ) {
        $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
        $orderAvailable = false;
    }
}

if ($orderAvailable) {
    $itemsSql = '
        SELECT product_name, quantity, price, subtotal
        FROM order_items
        WHERE order_id = ?
        ORDER BY order_item_id ASC
    ';
    $itemsStmt = mysqli_prepare($conn, $itemsSql);

    if ($itemsStmt === false) {
        $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
    } elseif (!mysqli_stmt_bind_param($itemsStmt, 'i', $orderId)) {
        $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
        mysqli_stmt_close($itemsStmt);
    } elseif (!mysqli_stmt_execute($itemsStmt)) {
        $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
        mysqli_stmt_close($itemsStmt);
    } elseif (!mysqli_stmt_bind_result($itemsStmt, $productName, $quantity, $price, $subtotal)) {
        $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
        mysqli_stmt_close($itemsStmt);
    } else {
        $itemsAreConsistent = true;
        $itemSubtotalSum = 0.00;

        while (($itemFetchResult = mysqli_stmt_fetch($itemsStmt)) === true) {
            $quantityValue = (int) $quantity;
            $priceValue = (float) $price;
            $subtotalValue = (float) $subtotal;
            $calculatedSubtotal = round($priceValue * $quantityValue, 2);

            if (
                $quantityValue <= 0 ||
                $priceValue <= 0 ||
                $subtotalValue <= 0 ||
                abs($subtotalValue - $calculatedSubtotal) > 0.001
            ) {
                $itemsAreConsistent = false;
            }

            $orderItems[] = [
                'product_name' => $productName,
                'quantity' => $quantityValue,
                'price' => $priceValue,
                'subtotal' => $subtotalValue
            ];
            $itemSubtotalSum = round($itemSubtotalSum + $subtotalValue, 2);
        }

        mysqli_stmt_close($itemsStmt);

        if (
            $itemFetchResult === false ||
            empty($orderItems) ||
            !$itemsAreConsistent ||
            abs($itemSubtotalSum - $order['total_amount']) > 0.001
        ) {
            $confirmationLoadError = 'Order confirmation could not be loaded right now. Please try again later.';
        }
    }
}

if ($confirmationLoadError !== '') {
    $orderAvailable = false;
    $orderItems = [];
}

$statusBadgeClasses = [
    'Pending' => 'text-bg-warning',
    'Processing' => 'text-bg-primary',
    'Completed' => 'text-bg-success',
    'Cancelled' => 'text-bg-secondary'
];

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Order</p>
            <h1 class="h3 mb-3">Order Confirmation</h1>

            <?php if (!$orderAvailable): ?>
                <div class="alert alert-warning" role="alert">
                    <?php if ($confirmationLoadError !== ''): ?>
                        <?php echo escapeOutput($confirmationLoadError); ?>
                    <?php else: ?>
                        Order not found or unavailable.
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Return to Store</a>
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/cart.php">View Cart</a>
                </div>
            <?php else: ?>
                <div class="alert alert-success" role="status">
                    Your order was placed successfully.
                </div>

                <section class="border-bottom pb-4 mb-4" aria-labelledby="orderSummaryHeading">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1" id="orderSummaryHeading">Order <?php echo escapeOutput($order['order_number']); ?></h2>
                            <p class="text-muted mb-0">Created: <?php echo escapeOutput($order['created_at']); ?></p>
                        </div>
                        <div>
                            <span class="badge <?php echo $statusBadgeClasses[$order['order_status']]; ?>">
                                <?php echo escapeOutput($order['order_status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Simulated Payment Method</span>
                            <p class="mb-0"><?php echo escapeOutput($order['payment_method']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Order Total</span>
                            <p class="fw-semibold mb-0">PHP <?php echo escapeOutput(number_format($order['total_amount'], 2)); ?></p>
                        </div>
                    </div>
                </section>

                <section class="border-bottom pb-4 mb-4" aria-labelledby="orderItemsHeading">
                    <h2 class="h5 mb-3" id="orderItemsHeading">Ordered Items</h2>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="text-end">Price</th>
                                    <th scope="col" class="text-end">Quantity</th>
                                    <th scope="col" class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?php echo escapeOutput($item['product_name']); ?></td>
                                        <td class="text-end">PHP <?php echo escapeOutput(number_format($item['price'], 2)); ?></td>
                                        <td class="text-end"><?php echo (int) $item['quantity']; ?></td>
                                        <td class="text-end">PHP <?php echo escapeOutput(number_format($item['subtotal'], 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th scope="row" colspan="3" class="text-end">Total</th>
                                    <th class="text-end">PHP <?php echo escapeOutput(number_format($order['total_amount'], 2)); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <section aria-labelledby="buyerInformationHeading">
                    <h2 class="h5 mb-3" id="buyerInformationHeading">Buyer Information</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Complete Name</span>
                            <p class="mb-0"><?php echo escapeOutput($order['full_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Email Address</span>
                            <p class="mb-0"><?php echo escapeOutput($order['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Contact Number</span>
                            <p class="mb-0"><?php echo escapeOutput($order['contact_number']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Current Registered Address</span>
                            <p class="mb-0"><?php echo nl2br(escapeOutput($order['address'])); ?></p>
                        </div>
                    </div>
                    <p class="text-muted small mt-3 mb-0">The address shown is your current registered address. This order does not store a separate address snapshot.</p>
                </section>

                <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                    <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Continue Shopping</a>
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/cart.php">View Cart</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
