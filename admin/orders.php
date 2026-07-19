<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Order Management';
$activePage = 'admin_orders';
$orders = [];
$ordersLoadError = '';

$ordersSql = '
    SELECT
        orders.order_id,
        orders.order_number,
        orders.created_at,
        orders.order_status,
        orders.payment_method,
        orders.total_amount,
        users.full_name,
        users.email
    FROM orders
    INNER JOIN users ON orders.user_id = users.user_id
    ORDER BY orders.created_at DESC, orders.order_id DESC
';

try {
    $ordersStmt = mysqli_prepare($conn, $ordersSql);

    if ($ordersStmt === false) {
        $ordersLoadError = 'Orders could not be loaded right now.';
    } elseif (!mysqli_stmt_execute($ordersStmt)) {
        $ordersLoadError = 'Orders could not be loaded right now.';
        mysqli_stmt_close($ordersStmt);
    } elseif (!mysqli_stmt_bind_result(
        $ordersStmt,
        $orderId,
        $orderNumber,
        $createdAt,
        $orderStatus,
        $paymentMethod,
        $totalAmount,
        $buyerName,
        $buyerEmail
    )) {
        $ordersLoadError = 'Orders could not be loaded right now.';
        mysqli_stmt_close($ordersStmt);
    } else {
        while (($orderFetchResult = mysqli_stmt_fetch($ordersStmt)) === true) {
            $orders[] = [
                'order_id' => (int) $orderId,
                'order_number' => $orderNumber,
                'created_at' => $createdAt,
                'order_status' => $orderStatus,
                'payment_method' => $paymentMethod,
                'total_amount' => (float) $totalAmount,
                'buyer_name' => $buyerName,
                'buyer_email' => $buyerEmail
            ];
        }

        mysqli_stmt_close($ordersStmt);

        if ($orderFetchResult === false) {
            $orders = [];
            $ordersLoadError = 'Orders could not be loaded right now.';
        }
    }
} catch (Throwable $exception) {
    $orders = [];
    $ordersLoadError = 'Orders could not be loaded right now.';
    error_log('Admin order listing could not be loaded.');
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel order-management-panel">
            <p class="section-label mb-2">Admin Management</p>
            <h1 class="h3 mb-2">Order Management</h1>
            <p class="text-muted mb-4">View all buyer orders and their current status.</p>

            <?php if ($ordersLoadError !== ''): ?>
                <div class="alert alert-danger mb-0" role="alert">
                    <?php echo escapeOutput($ordersLoadError); ?>
                </div>
            <?php elseif (empty($orders)): ?>
                <div class="alert alert-info" role="status">
                    No orders are available.
                </div>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>admin/dashboard.php">Back to Dashboard</a>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 admin-orders-table">
                        <thead>
                            <tr>
                                <th scope="col" class="text-nowrap">Order Number</th>
                                <th scope="col" class="text-nowrap">Buyer</th>
                                <th scope="col" class="text-nowrap">Buyer Email</th>
                                <th scope="col" class="text-nowrap">Date Created</th>
                                <th scope="col" class="text-nowrap">Status</th>
                                <th scope="col" class="text-nowrap">Payment Method</th>
                                <th scope="col" class="text-end text-nowrap">Total</th>
                                <th scope="col" class="text-end text-nowrap">Action</th>
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
                                    <td class="long-value"><?php echo escapeOutput($order['buyer_name']); ?></td>
                                    <td class="long-value"><?php echo escapeOutput($order['buyer_email']); ?></td>
                                    <td class="text-nowrap"><?php echo escapeOutput(formatDatabaseDate($order['created_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusBadgeClass; ?>">
                                            <?php echo escapeOutput($displayStatus); ?>
                                        </span>
                                    </td>
                                    <td><?php echo escapeOutput($order['payment_method']); ?></td>
                                    <td class="text-end text-nowrap"><?php echo escapeOutput(formatMoney($order['total_amount'])); ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-dark text-nowrap" href="<?php echo BASE_URL; ?>admin/order_details.php?order_id=<?php echo (int) $order['order_id']; ?>">View Details</a>
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
