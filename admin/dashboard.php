<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Admin Dashboard';
$activePage = 'admin';
$dashboardWarning = '';
$dashboardMetrics = [
    'total_users' => null,
    'total_products' => null,
    'total_stock' => null,
    'pending_orders' => null,
    'processing_orders' => null
];

$metricQueries = [
    'total_users' => 'SELECT COUNT(*) AS metric_value FROM users',
    'total_products' => 'SELECT COUNT(*) AS metric_value FROM products',
    'total_stock' => 'SELECT COALESCE(SUM(stock), 0) AS metric_value FROM products',
    'pending_orders' => "SELECT COUNT(*) AS metric_value FROM orders WHERE order_status = 'Pending'",
    'processing_orders' => "SELECT COUNT(*) AS metric_value FROM orders WHERE order_status = 'Processing'"
];

foreach ($metricQueries as $metricName => $metricSql) {
    $metricStmt = mysqli_prepare($conn, $metricSql);

    if ($metricStmt === false) {
        $dashboardWarning = 'Some dashboard summaries are temporarily unavailable.';
        continue;
    }

    if (!mysqli_stmt_execute($metricStmt)) {
        $dashboardWarning = 'Some dashboard summaries are temporarily unavailable.';
        mysqli_stmt_close($metricStmt);
        continue;
    }

    if (!mysqli_stmt_bind_result($metricStmt, $metricValue)) {
        $dashboardWarning = 'Some dashboard summaries are temporarily unavailable.';
        mysqli_stmt_close($metricStmt);
        continue;
    }

    if (!mysqli_stmt_fetch($metricStmt)) {
        $dashboardWarning = 'Some dashboard summaries are temporarily unavailable.';
        mysqli_stmt_close($metricStmt);
        continue;
    }

    $dashboardMetrics[$metricName] = (int) $metricValue;
    mysqli_stmt_close($metricStmt);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel dashboard-panel">
            <p class="section-label mb-2">Admin Management</p>
            <h1 class="h3 mb-2">Admin Dashboard</h1>
            <p class="text-muted mb-4">View current operational summaries and open management pages.</p>

            <?php if ($dashboardWarning !== ''): ?>
                <div class="alert alert-warning" role="alert">
                    <?php echo escapeOutput($dashboardWarning); ?>
                </div>
            <?php endif; ?>

            <?php
            $summaryCards = [
                ['label' => 'Total Users', 'value' => $dashboardMetrics['total_users']],
                ['label' => 'Total Products', 'value' => $dashboardMetrics['total_products']],
                ['label' => 'Total Remaining Stock Units', 'value' => $dashboardMetrics['total_stock']],
                ['label' => 'Pending Orders', 'value' => $dashboardMetrics['pending_orders']],
                ['label' => 'Processing Orders', 'value' => $dashboardMetrics['processing_orders']]
            ];
            ?>

            <div class="row g-3 mb-4">
                <?php foreach ($summaryCards as $card): ?>
                    <div class="col-12 col-sm-6 col-xl">
                        <div class="border rounded p-3 h-100 bg-light">
                            <p class="text-muted small mb-1"><?php echo escapeOutput($card['label']); ?></p>
                            <p class="h4 mb-0">
                                <?php echo $card['value'] === null ? '-' : (int) $card['value']; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2 class="h5 mb-3">Quick Links</h2>
            <div class="d-flex flex-wrap gap-2 dashboard-quick-links">
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>admin/users.php">Users</a>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>admin/orders.php">Orders</a>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>admin/products.php">Products</a>
                <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>admin/inventory.php">Inventory</a>
                <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>admin/audit_logs.php">Audit Logs</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
