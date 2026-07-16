<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Inventory Report';
$activePage = 'admin_inventory';
$errors = [];
$inventoryItems = [];
$summary = [
    'total_products' => 0,
    'active_products' => 0,
    'inactive_products' => 0,
    'total_stock' => 0,
    'out_of_stock_products' => 0
];

$inventorySql = "
    SELECT
        p.product_name,
        p.price,
        p.stock,
        p.status,
        p.created_at,
        p.updated_at,
        c.category_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_name ASC
";

$inventoryStmt = mysqli_prepare($conn, $inventorySql);

if ($inventoryStmt === false) {
    $errors[] = 'Inventory could not be loaded right now.';
} else {
    mysqli_stmt_execute($inventoryStmt);
    mysqli_stmt_bind_result(
        $inventoryStmt,
        $productName,
        $price,
        $stock,
        $status,
        $createdAt,
        $updatedAt,
        $categoryName
    );

    while (mysqli_stmt_fetch($inventoryStmt)) {
        $stockValue = (int) $stock;

        $inventoryItems[] = [
            'product_name' => $productName,
            'category_name' => $categoryName,
            'price' => $price,
            'stock' => $stockValue,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt
        ];

        $summary['total_products']++;
        $summary['total_stock'] += $stockValue;

        if ($status === 'Active') {
            $summary['active_products']++;
        } elseif ($status === 'Inactive') {
            $summary['inactive_products']++;
        }

        if ($stockValue === 0) {
            $summary['out_of_stock_products']++;
        }
    }

    mysqli_stmt_close($inventoryStmt);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <p class="section-label mb-2">Admin Management</p>
                    <h1 class="h3 mb-2">Inventory Report</h1>
                    <p class="text-muted mb-0">View remaining Hoodie inventory for Active and Inactive products.</p>
                </div>
                <a class="btn btn-outline-secondary" href="products.php">Back to Products</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escapeOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted small mb-1">Total Products</p>
                        <p class="h4 mb-0"><?php echo (int) $summary['total_products']; ?></p>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted small mb-1">Active Products</p>
                        <p class="h4 mb-0"><?php echo (int) $summary['active_products']; ?></p>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted small mb-1">Inactive Products</p>
                        <p class="h4 mb-0"><?php echo (int) $summary['inactive_products']; ?></p>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted small mb-1">Total Stock</p>
                        <p class="h4 mb-0"><?php echo (int) $summary['total_stock']; ?></p>
                    </div>
                </div>
                <div class="col-6 col-lg">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted small mb-1">Out of Stock</p>
                        <p class="h4 mb-0"><?php echo (int) $summary['out_of_stock_products']; ?></p>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Current Stock</th>
                            <th>Product Status</th>
                            <th>Inventory Condition</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventoryItems)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No products found in inventory.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inventoryItems as $item): ?>
                                <?php
                                $condition = $item['stock'] > 0 ? 'In Stock' : 'Out of Stock';
                                $conditionClass = $item['stock'] > 0 ? 'badge text-bg-success' : 'badge text-bg-danger';
                                $statusClass = $item['status'] === 'Active' ? 'badge text-bg-success' : 'badge text-bg-secondary';
                                $displayDate = $item['updated_at'] !== null ? $item['updated_at'] : $item['created_at'];
                                ?>
                                <tr>
                                    <td><?php echo escapeOutput($item['product_name']); ?></td>
                                    <td><?php echo escapeOutput($item['category_name']); ?></td>
                                    <td>PHP <?php echo escapeOutput(number_format((float) $item['price'], 2)); ?></td>
                                    <td><?php echo (int) $item['stock']; ?></td>
                                    <td><span class="<?php echo $statusClass; ?>"><?php echo escapeOutput($item['status']); ?></span></td>
                                    <td><span class="<?php echo $conditionClass; ?>"><?php echo escapeOutput($condition); ?></span></td>
                                    <td><?php echo escapeOutput($displayDate); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>