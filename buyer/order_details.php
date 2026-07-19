<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();

$pageTitle = 'Order Details';
$activePage = 'orders';
$buyerId = (int) $_SESSION['user_id'];
$submittedOrderId = $_GET['order_id'] ?? '';
$orderId = 0;
$ownedOrderFound = false;
$orderAvailable = false;
$order = [];
$orderItems = [];

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' &&
    is_string($submittedOrderId) &&
    $submittedOrderId !== '' &&
    strlen($submittedOrderId) <= 10 &&
    ctype_digit($submittedOrderId)
) {
    $validatedOrderId = (int) $submittedOrderId;

    if ($validatedOrderId > 0 && $validatedOrderId <= 4294967295) {
        $orderId = $validatedOrderId;
    }
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
            orders.created_at
        FROM orders
        WHERE orders.order_id = ? AND orders.user_id = ?
        LIMIT 1
    ';
    $orderStmt = mysqli_prepare($conn, $orderSql);

    if ($orderStmt !== false) {
        if (
            mysqli_stmt_bind_param($orderStmt, 'ii', $orderId, $buyerId) &&
            mysqli_stmt_execute($orderStmt) &&
            mysqli_stmt_bind_result(
                $orderStmt,
                $foundOrderId,
                $orderNumber,
                $totalAmount,
                $paymentMethod,
                $orderStatus,
                $createdAt
            )
        ) {
            $orderFound = mysqli_stmt_fetch($orderStmt);

            if ($orderFound === true) {
                $order = [
                    'order_id' => (int) $foundOrderId,
                    'order_number' => $orderNumber,
                    'total_amount' => (float) $totalAmount,
                    'payment_method' => $paymentMethod,
                    'order_status' => $orderStatus,
                    'created_at' => $createdAt
                ];
                $ownedOrderFound = true;
            }
        }

        mysqli_stmt_close($orderStmt);
    }
}

if ($ownedOrderFound) {
    $itemsSql = '
        SELECT
            order_items.order_item_id,
            order_items.product_id,
            order_items.product_name,
            order_items.quantity,
            order_items.price,
            order_items.subtotal
        FROM order_items
        WHERE order_items.order_id = ?
        ORDER BY order_items.order_item_id ASC
    ';
    $itemsStmt = mysqli_prepare($conn, $itemsSql);

    if ($itemsStmt !== false) {
        if (
            mysqli_stmt_bind_param($itemsStmt, 'i', $orderId) &&
            mysqli_stmt_execute($itemsStmt) &&
            mysqli_stmt_bind_result(
                $itemsStmt,
                $orderItemId,
                $productId,
                $productName,
                $quantity,
                $price,
                $subtotal
            )
        ) {
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
                    'order_item_id' => (int) $orderItemId,
                    'product_id' => (int) $productId,
                    'product_name' => $productName,
                    'quantity' => $quantityValue,
                    'price' => $priceValue,
                    'subtotal' => $subtotalValue
                ];
                $itemSubtotalSum = round($itemSubtotalSum + $subtotalValue, 2);
            }

            if (
                $itemFetchResult !== false &&
                !empty($orderItems) &&
                $itemsAreConsistent &&
                $order['total_amount'] > 0 &&
                abs($itemSubtotalSum - $order['total_amount']) <= 0.001
            ) {
                $orderAvailable = true;
            }
        }

        mysqli_stmt_close($itemsStmt);
    }
}

if (!$orderAvailable) {
    $order = [];
    $orderItems = [];
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Orders</p>
            <h1 class="h3 mb-3">Order Details</h1>

            <?php if (!$orderAvailable): ?>
                <div class="alert alert-warning" role="alert">
                    Order unavailable.
                </div>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/orders.php">Back to My Orders</a>
            <?php else: ?>
                <?php
                $statusBadgeClass = getOrderStatusBadgeClass($order['order_status']);
                $displayStatus = getOrderStatusLabel($order['order_status']);
                ?>

                <section class="border-bottom pb-4 mb-4" aria-labelledby="orderSummaryHeading">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1 long-value" id="orderSummaryHeading">Order <?php echo escapeOutput($order['order_number']); ?></h2>
                            <p class="text-muted mb-0">Created: <?php echo escapeOutput(formatDatabaseDate($order['created_at'])); ?></p>
                        </div>
                        <div>
                            <span class="badge <?php echo $statusBadgeClass; ?>">
                                <?php echo escapeOutput($displayStatus); ?>
                            </span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Simulated Payment Method</span>
                            <p class="mb-0"><?php echo escapeOutput($order['payment_method']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Stored Order Total</span>
                            <p class="fw-semibold mb-0"><?php echo escapeOutput(formatMoney($order['total_amount'])); ?></p>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="orderedItemsHeading">
                    <h2 class="h5 mb-3" id="orderedItemsHeading">Ordered Items</h2>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 order-items-table">
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="text-end">Unit Price</th>
                                    <th scope="col" class="text-end">Quantity</th>
                                    <th scope="col" class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td><?php echo escapeOutput($item['product_name']); ?></td>
                                        <td class="text-end text-nowrap"><?php echo escapeOutput(formatMoney($item['price'])); ?></td>
                                        <td class="text-end"><?php echo (int) $item['quantity']; ?></td>
                                        <td class="text-end text-nowrap"><?php echo escapeOutput(formatMoney($item['subtotal'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th scope="row" colspan="3" class="text-end">Stored Order Total</th>
                                    <th class="text-end text-nowrap"><?php echo escapeOutput(formatMoney($order['total_amount'])); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                    <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/orders.php">Back to My Orders</a>
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Store</a>
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/cart.php">Cart</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
