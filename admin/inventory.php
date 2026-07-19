<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Inventory Report';
$activePage = 'admin_inventory';
$inventoryLoadError = '';
$inventoryItems = [];
$summary = [
    'total_products' => 0,
    'total_stock' => 0,
    'in_stock_products' => 0,
    'out_of_stock_products' => 0
];

$inventorySql = "
    SELECT
        products.product_name,
        categories.category_name,
        products.status,
        products.price,
        products.stock,
        products.created_at,
        products.updated_at
    FROM products
    INNER JOIN categories ON products.category_id = categories.category_id
    ORDER BY products.stock ASC, products.product_name ASC
";

$inventoryStmt = mysqli_prepare($conn, $inventorySql);

if ($inventoryStmt === false) {
    $inventoryLoadError = 'Inventory could not be loaded right now.';
} else {
    if (!mysqli_stmt_execute($inventoryStmt)) {
        $inventoryLoadError = 'Inventory could not be loaded right now.';
    } else {
        mysqli_stmt_bind_result(
            $inventoryStmt,
            $productName,
            $categoryName,
            $productStatus,
            $productPrice,
            $productStock,
            $productCreatedAt,
            $productUpdatedAt
        );

        while (mysqli_stmt_fetch($inventoryStmt)) {
            $stockValue = (int) $productStock;

            $inventoryItems[] = [
                'product_name' => $productName,
                'category_name' => $categoryName,
                'status' => $productStatus,
                'price' => $productPrice,
                'stock' => $stockValue,
                'created_at' => $productCreatedAt,
                'updated_at' => $productUpdatedAt
            ];

            $summary['total_products']++;
            $summary['total_stock'] += $stockValue;

            if ($stockValue > 0) {
                $summary['in_stock_products']++;
            } else {
                $summary['out_of_stock_products']++;
            }
        }
    }

    mysqli_stmt_close($inventoryStmt);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel report-panel">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <p class="section-label mb-2">Admin Management</p>
                    <h1 class="h3 mb-2">Inventory Report</h1>
                    <p class="text-muted mb-0">View remaining Hoodie inventory for Active and Inactive products.</p>
                </div>
                <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>admin/products.php">Back to Products</a>
            </div>

            <?php if ($inventoryLoadError !== ''): ?>
                <div class="alert alert-danger mb-0" role="alert">
                    <?php echo escapeOutput($inventoryLoadError); ?>
                </div>
            <?php elseif (empty($inventoryItems)): ?>
                <div class="alert alert-info mb-0" role="status">
                    <p class="mb-2">No products found in inventory.</p>
                    <a class="alert-link" href="<?php echo BASE_URL; ?>admin/products.php">View Products</a>
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100 bg-light">
                            <p class="text-muted small mb-1">Total Listed Products</p>
                            <p class="h4 mb-0"><?php echo (int) $summary['total_products']; ?></p>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100 bg-light">
                            <p class="text-muted small mb-1">Total Remaining Units</p>
                            <p class="h4 mb-0"><?php echo (int) $summary['total_stock']; ?></p>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100 bg-light">
                            <p class="text-muted small mb-1">In Stock Products</p>
                            <p class="h4 mb-0"><?php echo (int) $summary['in_stock_products']; ?></p>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="border rounded p-3 h-100 bg-light">
                            <p class="text-muted small mb-1">Out of Stock Products</p>
                            <p class="h4 mb-0"><?php echo (int) $summary['out_of_stock_products']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0 inventory-report-table">
                        <thead>
                            <tr>
                                <th scope="col">Product Name</th>
                                <th scope="col">Category</th>
                                <th scope="col">Product Status</th>
                                <th scope="col">Current Price</th>
                                <th scope="col">Remaining Stock</th>
                                <th scope="col">Inventory Condition</th>
                                <th scope="col">Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventoryItems as $item): ?>
                                <?php
                                $condition = $item['stock'] > 0 ? 'In Stock' : 'Out of Stock';
                                $conditionClass = getInventoryConditionBadgeClass($item['stock']);
                                $statusClass = getProductStatusBadgeClass($item['status']);
                                $displayDate = $item['updated_at'] !== null ? $item['updated_at'] : $item['created_at'];
                                ?>
                                <tr>
                                    <td><?php echo escapeOutput($item['product_name']); ?></td>
                                    <td><?php echo escapeOutput($item['category_name']); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo escapeOutput(getProductStatusLabel($item['status'])); ?></span></td>
                                    <td class="text-nowrap"><?php echo escapeOutput(formatMoney($item['price'])); ?></td>
                                    <td><?php echo (int) $item['stock']; ?></td>
                                    <td><span class="badge <?php echo $conditionClass; ?>"><?php echo escapeOutput($condition); ?></span></td>
                                    <td class="text-nowrap"><?php echo escapeOutput(formatDatabaseDate($displayDate)); ?></td>
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