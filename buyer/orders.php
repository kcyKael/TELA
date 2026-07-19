<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'My Orders';
$activePage = 'orders';
$buyerId = (int) $_SESSION['user_id'];
$orders = [];
$ordersLoadError = '';

$ordersSql = '
    SELECT
        orders.order_id,
        orders.order_number,
        orders.total_amount,
        orders.payment_method,
        orders.order_status,
        orders.created_at
    FROM orders
    WHERE orders.user_id = ?
    ORDER BY orders.created_at DESC, orders.order_id DESC
';
$ordersStmt = mysqli_prepare($conn, $ordersSql);

if ($ordersStmt === false) {
    $ordersLoadError = 'Your orders could not be loaded right now.';
} elseif (!mysqli_stmt_bind_param($ordersStmt, 'i', $buyerId)) {
    $ordersLoadError = 'Your orders could not be loaded right now.';
    mysqli_stmt_close($ordersStmt);
} elseif (!mysqli_stmt_execute($ordersStmt)) {
    $ordersLoadError = 'Your orders could not be loaded right now.';
    mysqli_stmt_close($ordersStmt);
} elseif (!mysqli_stmt_bind_result(
    $ordersStmt,
    $orderId,
    $orderNumber,
    $totalAmount,
    $paymentMethod,
    $orderStatus,
    $createdAt
)) {
    $ordersLoadError = 'Your orders could not be loaded right now.';
    mysqli_stmt_close($ordersStmt);
} else {
    while (($orderFetchResult = mysqli_stmt_fetch($ordersStmt)) === true) {
        $orders[] = [
            'order_id' => (int) $orderId,
            'order_number' => $orderNumber,
            'total_amount' => (float) $totalAmount,
            'payment_method' => $paymentMethod,
            'order_status' => $orderStatus,
            'created_at' => $createdAt
        ];
    }

    mysqli_stmt_close($ordersStmt);

    if ($orderFetchResult === false) {
        $orders = [];
        $ordersLoadError = 'Your orders could not be loaded right now.';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Orders</p>
            <h1 class="h3 mb-3">My Orders</h1>

            <?php if ($ordersLoadError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo escapeOutput($ordersLoadError); ?>
                </div>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Browse Hoodies</a>
            <?php elseif (empty($orders)): ?>
                <div class="alert alert-info" role="status">
                    Your order history is empty. You have not placed any orders yet.
                </div>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Browse Hoodies</a>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 order-history-table">
                        <thead>
                            <tr>
                                <th scope="col">Order Number</th>
                                <th scope="col">Date Created</th>
                                <th scope="col">Status</th>
                                <th scope="col">Payment Method</th>
                                <th scope="col" class="text-end">Total</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $statusBadgeClass = getOrderStatusBadgeClass($order['order_status']);
                                $displayStatus = getOrderStatusLabel($order['order_status']);
                                ?>
                                <tr>
                                    <td class="long-value"><?php echo escapeOutput($order['order_number']); ?></td>
                                    <td class="text-nowrap"><?php echo escapeOutput(formatDatabaseDate($order['created_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadgeClass; ?>">
                                            <?php echo escapeOutput($displayStatus); ?>
                                        </span>
                                    </td>
                                    <td><?php echo escapeOutput($order['payment_method']); ?></td>
                                    <td class="text-end text-nowrap"><?php echo escapeOutput(formatMoney($order['total_amount'])); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-dark text-nowrap" href="<?php echo BASE_URL; ?>buyer/order_details.php?order_id=<?php echo (int) $order['order_id']; ?>">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
