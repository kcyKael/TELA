<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Product Management';
$activePage = 'admin_products';
$errors = [];
$products = [];
$messageValue = $_GET['message'] ?? '';
$messageCode = is_string($messageValue) ? $messageValue : '';
$successMessages = [
    'product_added' => 'Product was added successfully.',
    'product_updated' => 'Product was updated successfully.',
    'product_deactivated' => 'Product was deactivated successfully.',
    'product_deactivated_history' => 'Product has order history, so it was safely deactivated.',
    'product_activated' => 'Product was activated successfully.'
];
$errorMessages = [
    'invalid_action' => 'Product action could not be completed.',
    'product_not_found' => 'Product could not be found.',
    'already_inactive' => 'Product is already inactive.',
    'already_active' => 'Product is already active.',
    'action_failed' => 'Product action could not be completed right now.'
];

function getSafeProductImage($imagePath)
{
    $imagePath = trim((string) $imagePath);

    if ($imagePath === '') {
        return '';
    }

    $cleanPath = str_replace('\\', '/', $imagePath);
    $cleanPath = ltrim($cleanPath, '/');

    if (strpos($cleanPath, 'uploads/products/') !== 0) {
        return '';
    }

    $fullPath = __DIR__ . '/../' . $cleanPath;

    if (!is_file($fullPath)) {
        return '';
    }

    return '../' . $cleanPath;
}

$productSql = "
    SELECT
        p.product_id,
        p.product_name,
        p.price,
        p.stock,
        p.image_path,
        p.status,
        p.created_at,
        p.updated_at,
        c.category_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.created_at DESC, p.product_id DESC
";

$productStmt = mysqli_prepare($conn, $productSql);

if ($productStmt === false) {
    $errors[] = 'Products could not be loaded right now.';
} else {
    mysqli_stmt_execute($productStmt);
    mysqli_stmt_bind_result(
        $productStmt,
        $productId,
        $productName,
        $price,
        $stock,
        $imagePath,
        $status,
        $createdAt,
        $updatedAt,
        $categoryName
    );

    while (mysqli_stmt_fetch($productStmt)) {
        $products[] = [
            'product_id' => (int) $productId,
            'product_name' => $productName,
            'price' => $price,
            'stock' => (int) $stock,
            'image_path' => $imagePath,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'category_name' => $categoryName
        ];
    }

    mysqli_stmt_close($productStmt);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel management-panel">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <p class="section-label mb-2">Admin Management</p>
                    <h1 class="h3 mb-2">Product Management</h1>
                    <p class="text-muted mb-0">View Hoodie products, stock, prices, and status.</p>
                </div>
                <a class="btn btn-dark" href="product_add.php">Add Product</a>
            </div>

            <?php if ($messageCode !== '' && isset($successMessages[$messageCode])): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo escapeOutput($successMessages[$messageCode]); ?>
                </div>
            <?php endif; ?>

            <?php if ($messageCode !== '' && isset($errorMessages[$messageCode])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo escapeOutput($errorMessages[$messageCode]); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escapeOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-striped align-middle product-management-table">
                    <thead>
                        <tr>
                            <th scope="col">Image</th>
                            <th scope="col">Product Name</th>
                            <th scope="col">Category</th>
                            <th scope="col">Price</th>
                            <th scope="col">Stock</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last Updated</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No products found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $safeProductId = (int) $product['product_id'];
                                $safeImageSource = getSafeProductImage($product['image_path']);
                                $stockLabel = $product['stock'] === 0 ? 'Out of Stock' : (string) $product['stock'];
                                $stockClass = $product['stock'] === 0 ? 'badge text-bg-danger' : 'badge text-bg-success';
                                $hasKnownStatus = in_array($product['status'], ['Active', 'Inactive'], true);
                                $statusClass = 'badge ' . getProductStatusBadgeClass($product['status']);
                                $displayDate = $product['updated_at'] !== null ? $product['updated_at'] : $product['created_at'];
                                $statusActionLabel = $product['status'] === 'Active' ? 'Deactivate' : 'Activate';
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($safeImageSource !== ''): ?>
                                            <img src="<?php echo escapeOutput($safeImageSource); ?>" alt="<?php echo escapeOutput($product['product_name']); ?> Hoodie product thumbnail" class="img-thumbnail admin-product-image">
                                        <?php else: ?>
                                            <div class="border rounded bg-light text-muted d-flex align-items-center justify-content-center text-center small" style="width: 72px; height: 72px;">No Image</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo escapeOutput($product['product_name']); ?></td>
                                    <td><?php echo escapeOutput($product['category_name']); ?></td>
                                    <td class="text-nowrap"><?php echo escapeOutput(formatMoney($product['price'])); ?></td>
                                    <td><span class="<?php echo $stockClass; ?>"><?php echo escapeOutput($stockLabel); ?></span></td>
                                    <td><span class="<?php echo $statusClass; ?>"><?php echo escapeOutput(getProductStatusLabel($product['status'])); ?></span></td>
                                    <td class="text-nowrap"><?php echo escapeOutput(formatDatabaseDate($displayDate)); ?></td>
                                    <td class="text-end">
                                        <div class="table-actions" aria-label="Product actions">
                                            <a class="btn btn-sm btn-outline-dark" href="product_edit.php?id=<?php echo $safeProductId; ?>">Edit</a>
                                            <?php if ($hasKnownStatus): ?>
                                                <form method="post" action="product_status.php">
                                                    <?php echo csrfTokenField(); ?>
                                                    <input type="hidden" name="product_id" value="<?php echo $safeProductId; ?>">
                                                    <input type="hidden" name="action" value="<?php echo $product['status'] === 'Active' ? 'deactivate' : 'activate'; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo escapeOutput($statusActionLabel); ?></button>
                                                </form>
                                                <?php if ($product['status'] === 'Active'): ?>
                                                    <form method="post" action="product_delete.php" onsubmit="return confirm('Deactivate this product?');">
                                                        <?php echo csrfTokenField(); ?>
                                                        <input type="hidden" name="product_id" value="<?php echo $safeProductId; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Safe Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" disabled>Safe Delete</button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Status Unavailable</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" disabled>Safe Delete</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
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
