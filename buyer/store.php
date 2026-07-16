<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Store';
$activePage = 'store';
$errors = [];
$products = [];

function getStoreProductImage($imagePath)
{
    $fallbackImage = BASE_URL . 'assets/images/logo.png';
    $imagePath = trim((string) $imagePath);

    if ($imagePath === '') {
        return $fallbackImage;
    }

    $cleanPath = str_replace('\\', '/', $imagePath);
    $cleanPath = ltrim($cleanPath, '/');

    if (strpos($cleanPath, PRODUCT_UPLOAD_PATH) !== 0 || strpos($cleanPath, '..') !== false) {
        return $fallbackImage;
    }

    $fullPath = __DIR__ . '/../' . $cleanPath;

    if (!is_file($fullPath)) {
        return $fallbackImage;
    }

    return BASE_URL . $cleanPath;
}

$activeStatus = 'Active';
$categoryNameFilter = PRODUCT_CATEGORY_NAME;

$productSql = "
    SELECT
        p.product_id,
        p.product_name,
        p.price,
        p.stock,
        p.image_path,
        c.category_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    WHERE p.status = ?
      AND c.category_name = ?
    ORDER BY p.product_name ASC
";

$productStmt = mysqli_prepare($conn, $productSql);

if ($productStmt === false) {
    $errors[] = 'Products could not be loaded right now.';
} else {
    mysqli_stmt_bind_param($productStmt, 'ss', $activeStatus, $categoryNameFilter);
    mysqli_stmt_execute($productStmt);
    mysqli_stmt_bind_result($productStmt, $productId, $productName, $price, $stock, $imagePath, $categoryName);

    while (mysqli_stmt_fetch($productStmt)) {
        $products[] = [
            'product_id' => (int) $productId,
            'product_name' => $productName,
            'price' => $price,
            'stock' => (int) $stock,
            'image_path' => $imagePath,
            'category_name' => $categoryName
        ];
    }

    mysqli_stmt_close($productStmt);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Store</p>
            <h1 class="h3 mb-3">Hoodie Store</h1>
            <p class="text-muted mb-4">Browse Active Hoodie products from TELA.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escapeOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (empty($products) && empty($errors)): ?>
                <div class="alert alert-info mb-0" role="alert">
                    No products are available right now.
                </div>
            <?php endif; ?>

            <?php if (!empty($products)): ?>
                <div class="row g-4">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $productId = (int) $product['product_id'];
                        $imageSource = getStoreProductImage($product['image_path']);
                        $stockCondition = $product['stock'] > 0 ? 'In Stock' : 'Out of Stock';
                        $stockClass = $product['stock'] > 0 ? 'badge text-bg-success' : 'badge text-bg-danger';
                        ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="card h-100">
                                <img src="<?php echo escapeOutput($imageSource); ?>" class="card-img-top" alt="<?php echo escapeOutput($product['product_name']); ?>" style="height: 220px; object-fit: cover;">
                                <div class="card-body d-flex flex-column">
                                    <p class="text-muted small mb-1"><?php echo escapeOutput($product['category_name']); ?></p>
                                    <h2 class="h5 card-title"><?php echo escapeOutput($product['product_name']); ?></h2>
                                    <p class="fw-semibold mb-2">PHP <?php echo escapeOutput(number_format((float) $product['price'], 2)); ?></p>
                                    <p class="mb-3">
                                        <span class="<?php echo $stockClass; ?>"><?php echo escapeOutput($stockCondition); ?></span>
                                    </p>

                                    <div class="mt-auto d-flex gap-2">
                                        <a class="btn btn-outline-dark flex-fill" href="<?php echo BASE_URL; ?>buyer/product.php?product_id=<?php echo $productId; ?>">Details</a>
                                        <?php if ($product['stock'] > 0): ?>
                                            <button type="button" class="btn btn-dark flex-fill" disabled title="Cart coming in the next milestone">Cart Coming Soon</button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-secondary flex-fill" disabled title="This product is out of stock">Out of Stock</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>