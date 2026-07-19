<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();

$pageTitle = 'Admin Order Details';
$activePage = 'admin_orders';
$submittedOrderId = $_GET['order_id'] ?? '';
$orderId = 0;
$orderAvailable = false;
$itemsAvailable = false;
$order = [];
$orderItems = [];
$itemsLoadMessage = '';

$adminOrderFlash = $_SESSION['admin_order_flash'] ?? null;
unset($_SESSION['admin_order_flash']);

$flashMessage = '';
$flashType = '';
$allowedFlashTypes = ['success', 'info', 'warning', 'danger'];

if (is_array($adminOrderFlash)) {
    $submittedFlashMessage = $adminOrderFlash['message'] ?? '';
    $submittedFlashType = $adminOrderFlash['type'] ?? '';

    if (
        is_string($submittedFlashMessage) &&
        $submittedFlashMessage !== '' &&
        is_string($submittedFlashType) &&
        in_array($submittedFlashType, $allowedFlashTypes, true)
    ) {
        $flashMessage = $submittedFlashMessage;
        $flashType = $submittedFlashType;
    }
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' &&
    is_string($submittedOrderId) &&
    $submittedOrderId !== '' &&
    strlen($submittedOrderId) <= 10 &&
    ctype_digit($submittedOrderId)
) {
    $isWithinUnsignedRange = strlen($submittedOrderId) < 10 ||
        strcmp($submittedOrderId, '4294967295') <= 0;

    if ($isWithinUnsignedRange) {
        $validatedOrderId = (int) $submittedOrderId;

        if ($validatedOrderId > 0) {
            $orderId = $validatedOrderId;
        }
    }
}

if ($orderId > 0) {
    require_once __DIR__ . '/../config/database.php';

    $orderSql = '
        SELECT
            orders.order_id,
            orders.user_id,
            orders.order_number,
            orders.total_amount,
            orders.payment_method,
            orders.order_status,
            orders.created_at,
            orders.updated_at,
            users.full_name,
            users.email,
            users.contact_number,
            users.address
        FROM orders
        INNER JOIN users ON orders.user_id = users.user_id
        WHERE orders.order_id = ?
        LIMIT 1
    ';

    try {
        $orderStmt = mysqli_prepare($conn, $orderSql);

        if ($orderStmt !== false &&
            mysqli_stmt_bind_param($orderStmt, 'i', $orderId) &&
            mysqli_stmt_execute($orderStmt) &&
            mysqli_stmt_bind_result(
                $orderStmt,
                $foundOrderId,
                $buyerUserId,
                $orderNumber,
                $totalAmount,
                $paymentMethod,
                $orderStatus,
                $createdAt,
                $updatedAt,
                $buyerName,
                $buyerEmail,
                $buyerContactNumber,
                $buyerAddress
            )
        ) {
            $orderFound = mysqli_stmt_fetch($orderStmt);

            if ($orderFound === true) {
                $order = [
                    'order_id' => (int) $foundOrderId,
                    'user_id' => (int) $buyerUserId,
                    'order_number' => $orderNumber,
                    'total_amount' => (float) $totalAmount,
                    'payment_method' => $paymentMethod,
                    'order_status' => $orderStatus,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                    'buyer_name' => $buyerName,
                    'buyer_email' => $buyerEmail,
                    'buyer_contact_number' => $buyerContactNumber,
                    'buyer_address' => $buyerAddress
                ];
                $orderAvailable = true;
            }
        }

        if ($orderStmt !== false) {
            mysqli_stmt_close($orderStmt);
        }
    } catch (Throwable $exception) {
        $order = [];
        $orderAvailable = false;
        error_log('Admin order details parent query failed.');
    }
}

if ($orderAvailable) {
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

    try {
        $itemsStmt = mysqli_prepare($conn, $itemsSql);

        if ($itemsStmt !== false &&
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
            while (($itemFetchResult = mysqli_stmt_fetch($itemsStmt)) === true) {
                $orderItems[] = [
                    'order_item_id' => (int) $orderItemId,
                    'product_id' => (int) $productId,
                    'product_name' => $productName,
                    'quantity' => (int) $quantity,
                    'price' => (float) $price,
                    'subtotal' => (float) $subtotal
                ];
            }

            if ($itemFetchResult !== false && !empty($orderItems)) {
                $itemsAvailable = true;
            }
        }

        if ($itemsStmt !== false) {
            mysqli_stmt_close($itemsStmt);
        }
    } catch (Throwable $exception) {
        $orderItems = [];
        error_log('Admin order details item query failed.');
    }

    if (!$itemsAvailable) {
        $orderItems = [];
        $itemsLoadMessage = 'Order item details are unavailable right now.';
    }
}

$statusBadgeClasses = [
    'Pending' => 'text-bg-warning',
    'Processing' => 'text-bg-primary',
    'Completed' => 'text-bg-success',
    'Cancelled' => 'text-bg-secondary'
];

$validNextStatuses = [
    'Pending' => ['Processing', 'Cancelled'],
    'Processing' => ['Completed', 'Cancelled'],
    'Completed' => [],
    'Cancelled' => []
];

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Admin Order Management</p>
            <h1 class="h3 mb-3">Order Details</h1>

            <?php if (!$orderAvailable): ?>
                <div class="alert alert-warning" role="alert">
                    Order unavailable.
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a class="btn btn-dark" href="<?php echo BASE_URL; ?>admin/orders.php">Back to Orders</a>
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>admin/dashboard.php">Admin Dashboard</a>
                </div>
            <?php else: ?>
                <?php
                $hasKnownStatus = isset($statusBadgeClasses[$order['order_status']]);
                $statusBadgeClass = $hasKnownStatus
                    ? $statusBadgeClasses[$order['order_status']]
                    : 'text-bg-secondary';
                $displayStatus = $hasKnownStatus
                    ? $order['order_status']
                    : 'Status unavailable';
                $nextStatusChoices = $hasKnownStatus
                    ? $validNextStatuses[$order['order_status']]
                    : [];
                ?>

                <?php if ($flashMessage !== ''): ?>
                    <div class="alert alert-<?php echo $flashType; ?>" role="status">
                        <?php echo escapeOutput($flashMessage); ?>
                    </div>
                <?php endif; ?>

                <section class="border-bottom pb-4 mb-4" aria-labelledby="orderSummaryHeading">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1" id="orderSummaryHeading">Order <?php echo escapeOutput($order['order_number']); ?></h2>
                            <p class="text-muted mb-1">Created: <?php echo escapeOutput($order['created_at']); ?></p>
                            <?php if ($order['updated_at'] !== null && $order['updated_at'] !== ''): ?>
                                <p class="text-muted mb-0">Last Updated: <?php echo escapeOutput($order['updated_at']); ?></p>
                            <?php endif; ?>
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
                            <p class="fw-semibold mb-0">PHP <?php echo escapeOutput(number_format($order['total_amount'], 2)); ?></p>
                        </div>
                    </div>
                </section>

                <section class="border-bottom pb-4 mb-4" aria-labelledby="buyerInformationHeading">
                    <h2 class="h5 mb-3" id="buyerInformationHeading">Current Buyer Account Information</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Full Name</span>
                            <p class="mb-0"><?php echo escapeOutput($order['buyer_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Email</span>
                            <p class="mb-0"><?php echo escapeOutput($order['buyer_email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Contact Number</span>
                            <p class="mb-0"><?php echo escapeOutput($order['buyer_contact_number']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Current Registered Address</span>
                            <p class="mb-0"><?php echo nl2br(escapeOutput($order['buyer_address'])); ?></p>
                        </div>
                    </div>
                    <p class="text-muted small mt-3 mb-0">The address shown is the buyer's current registered address. This order does not store a separate address snapshot from the time of checkout.</p>
                </section>

                <section class="border-bottom pb-4 mb-4" aria-labelledby="orderItemsHeading">
                    <h2 class="h5 mb-3" id="orderItemsHeading">Stored Order Items</h2>

                    <?php if (!$itemsAvailable): ?>
                        <div class="alert alert-warning mb-0" role="alert">
                            <?php echo escapeOutput($itemsLoadMessage); ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 order-items-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Product</th>
                                        <th scope="col" class="text-end text-nowrap">Unit Price</th>
                                        <th scope="col" class="text-end">Quantity</th>
                                        <th scope="col" class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                        <tr>
                                            <td><?php echo escapeOutput($item['product_name']); ?></td>
                                            <td class="text-end text-nowrap">PHP <?php echo escapeOutput(number_format($item['price'], 2)); ?></td>
                                            <td class="text-end"><?php echo (int) $item['quantity']; ?></td>
                                            <td class="text-end text-nowrap">PHP <?php echo escapeOutput(number_format($item['subtotal'], 2)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th scope="row" colspan="3" class="text-end">Stored Order Total</th>
                                        <th class="text-end text-nowrap">PHP <?php echo escapeOutput(number_format($order['total_amount'], 2)); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ($itemsAvailable && !empty($nextStatusChoices)): ?>
                    <section class="border-bottom pb-4 mb-4" aria-labelledby="statusUpdateHeading">
                        <h2 class="h5 mb-3" id="statusUpdateHeading">Update Order Status</h2>
                        <form action="<?php echo BASE_URL; ?>admin/order_update_status.php" method="post" class="row g-3 align-items-end">
                            <?php echo csrfTokenField(); ?>
                            <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                            <div class="col-md-8">
                                <label class="form-label" for="new_status">New Status</label>
                                <select class="form-select" id="new_status" name="new_status" required>
                                    <option value="">Select a status</option>
                                    <?php foreach ($nextStatusChoices as $nextStatus): ?>
                                        <option value="<?php echo escapeOutput($nextStatus); ?>">
                                            <?php echo escapeOutput($nextStatus); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-dark w-100" type="submit">Update Status</button>
                            </div>
                        </form>
                    </section>
                <?php elseif ($hasKnownStatus && empty($nextStatusChoices)): ?>
                    <div class="alert alert-secondary" role="status">
                        This order has a final status and cannot be updated.
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a class="btn btn-dark" href="<?php echo BASE_URL; ?>admin/orders.php">Back to Orders</a>
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>admin/dashboard.php">Admin Dashboard</a>
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>admin/inventory.php">Inventory</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
