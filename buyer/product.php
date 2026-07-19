<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Product Details';
$activePage = 'store';
$product = null;
$productUnavailable = true;
$productId = 0;

function getProductDetailImage($imagePath)
{
    $fallbackImage = BASE_URL . 'assets/images/Whole_logo-tela_icon-and-text.png';
    $imagePath = trim((string) $imagePath);

    if ($imagePath === '') {
        return $fallbackImage;
    }

    $cleanPath = str_replace('\\', '/', $imagePath);
    $cleanPath = ltrim($cleanPath, '/');

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $fileExtension = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
    $hasExpectedPath = strpos($cleanPath, PRODUCT_UPLOAD_PATH) === 0 && strpos($cleanPath, '..') === false;
    $hasAllowedExtension = in_array($fileExtension, $allowedExtensions, true);

    if (!$hasExpectedPath || !$hasAllowedExtension) {
        return $fallbackImage;
    }

    $fullPath = __DIR__ . '/../' . $cleanPath;

    if (!is_file($fullPath)) {
        return $fallbackImage;
    }

    return BASE_URL . $cleanPath;
}

if (isset($_GET['product_id']) && is_string($_GET['product_id']) && ctype_digit($_GET['product_id'])) {
    $productId = (int) $_GET['product_id'];
}

if ($productId > 0) {
    $activeStatus = 'Active';
    $categoryNameFilter = PRODUCT_CATEGORY_NAME;

    $productSql = "
        SELECT
            p.product_id,
            p.product_name,
            p.description,
            p.price,
            p.stock,
            p.image_path,
            c.category_name
        FROM products p
        INNER JOIN categories c ON p.category_id = c.category_id
        WHERE p.product_id = ?
          AND p.status = ?
          AND c.category_name = ?
        LIMIT 1
    ";

    $productStmt = mysqli_prepare($conn, $productSql);

    if ($productStmt !== false) {
        mysqli_stmt_bind_param($productStmt, 'iss', $productId, $activeStatus, $categoryNameFilter);
        mysqli_stmt_execute($productStmt);
        mysqli_stmt_bind_result($productStmt, $foundProductId, $productName, $description, $price, $stock, $imagePath, $categoryName);

        if (mysqli_stmt_fetch($productStmt)) {
            $product = [
                'product_id' => (int) $foundProductId,
                'product_name' => $productName,
                'description' => $description,
                'price' => $price,
                'stock' => (int) $stock,
                'image_path' => $imagePath,
                'category_name' => $categoryName
            ];
            $productUnavailable = false;
            $pageTitle = $productName;
        }

        mysqli_stmt_close($productStmt);
    }
}

include __DIR__ . '/../includes/header.php';
$isAdminBrowsing = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <?php if ($productUnavailable): ?>
                <p class="section-label mb-2">Buyer Store</p>
                <h1 class="h3 mb-3">Product Not Available</h1>
                <div class="alert alert-info" role="alert">
                    Product not found or unavailable.
                </div>
                <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>buyer/store.php">Back to Store</a>
            <?php else: ?>
                <?php
                $imageSource = getProductDetailImage($product['image_path']);
                $stockCondition = $product['stock'] > 0 ? 'In Stock' : 'Out of Stock';
                $stockClass = $product['stock'] > 0 ? 'badge text-bg-success' : 'badge text-bg-danger';
                ?>
                <div class="row g-4 align-items-start">
                    <div class="col-lg-5">
                        <img src="<?php echo escapeOutput($imageSource); ?>" alt="<?php echo escapeOutput($product['product_name']); ?> Hoodie product image" class="img-fluid rounded border product-detail-image">
                    </div>
                    <div class="col-lg-7">
                        <p class="section-label mb-2"><?php echo escapeOutput($product['category_name']); ?></p>
                        <h1 class="h3 mb-3"><?php echo escapeOutput($product['product_name']); ?></h1>
                        <p class="h5 mb-3"><?php echo escapeOutput(formatMoney($product['price'])); ?></p>
                        <p class="mb-2">
                            <span class="<?php echo $stockClass; ?>"><?php echo escapeOutput($stockCondition); ?></span>
                        </p>

                        <?php if ($product['stock'] > 0): ?>
                            <p class="text-muted">Available stock: <?php echo (int) $product['stock']; ?></p>
                        <?php else: ?>
                            <p class="text-muted">This Hoodie is currently out of stock.</p>
                        <?php endif; ?>

                        <h2 class="h5 mt-4">Description</h2>
                        <p><?php echo nl2br(escapeOutput($product['description'] !== null && $product['description'] !== '' ? $product['description'] : 'No description available.')); ?></p>

                        <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                            <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>buyer/store.php">Back to Store</a>
                            <?php if ($product['stock'] > 0): ?>
                                <?php if ($isAdminBrowsing): ?>
                                    <button type="button" class="btn btn-secondary" disabled title="The cart is available to buyers only">Buyer Cart Only</button>
                                <?php else: ?>
                                    <form method="post" action="<?php echo BASE_URL; ?>buyer/cart_add.php">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['product_id']; ?>">
                                        <button type="submit" class="btn btn-dark w-100">Add to Cart</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary" disabled title="This product is out of stock">Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>