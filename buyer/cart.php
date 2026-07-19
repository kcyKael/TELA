<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Cart';
$activePage = 'cart';
$buyerId = (int) $_SESSION['user_id'];
$cartItems = [];
$cartTotal = 0;
$cartLoadError = '';
$cartFlashMessage = '';
$cartFlashType = 'info';
$allowedFlashTypes = ['success', 'danger', 'warning', 'info'];
$cartFlash = $_SESSION['cart_flash'] ?? null;
unset($_SESSION['cart_flash']);

if (is_array($cartFlash)) {
    $flashMessage = $cartFlash['message'] ?? '';
    $flashType = $cartFlash['type'] ?? '';

    if (is_string($flashMessage) && $flashMessage !== '' && in_array($flashType, $allowedFlashTypes, true)) {
        $cartFlashMessage = $flashMessage;
        $cartFlashType = $flashType;
    }
}

function getCartProductImage($imagePath)
{
    $fallbackImage = BASE_URL . 'assets/images/logo.png';
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

$cartSql = '
    SELECT
        cart.cart_id,
        cart.product_id,
        cart.quantity,
        products.product_name,
        products.price,
        products.stock,
        products.image_path,
        products.status,
        categories.category_name
    FROM cart
    INNER JOIN products ON cart.product_id = products.product_id
    INNER JOIN categories ON products.category_id = categories.category_id
    WHERE cart.user_id = ?
    ORDER BY cart.created_at DESC, cart.cart_id DESC
';
$cartStmt = mysqli_prepare($conn, $cartSql);

if ($cartStmt === false) {
    $cartLoadError = 'Your cart could not be loaded right now.';
} else {
    mysqli_stmt_bind_param($cartStmt, 'i', $buyerId);

    if (mysqli_stmt_execute($cartStmt)) {
        mysqli_stmt_bind_result(
            $cartStmt,
            $cartId,
            $productId,
            $quantity,
            $productName,
            $price,
            $stock,
            $imagePath,
            $status,
            $categoryName
        );

        while (mysqli_stmt_fetch($cartStmt)) {
            $itemSubtotal = (float) $price * (int) $quantity;
            $cartTotal += $itemSubtotal;
            $cartItems[] = [
                'cart_id' => (int) $cartId,
                'product_id' => (int) $productId,
                'quantity' => (int) $quantity,
                'product_name' => $productName,
                'price' => (float) $price,
                'stock' => (int) $stock,
                'image_path' => $imagePath,
                'status' => $status,
                'category_name' => $categoryName,
                'subtotal' => $itemSubtotal
            ];
        }
    } else {
        $cartLoadError = 'Your cart could not be loaded right now.';
    }

    mysqli_stmt_close($cartStmt);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Cart</p>
            <h1 class="h3 mb-3">Cart</h1>

            <?php if ($cartFlashMessage !== ''): ?>
                <div class="alert alert-<?php echo $cartFlashType; ?>" role="alert">
                    <?php echo escapeOutput($cartFlashMessage); ?>
                </div>
            <?php endif; ?>

            <?php if ($cartLoadError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo escapeOutput($cartLoadError); ?>
                </div>
            <?php elseif (empty($cartItems)): ?>
                <div class="alert alert-info" role="alert">
                    Your cart is empty.
                </div>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Browse Hoodies</a>
            <?php else: ?>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
                    <p class="text-muted mb-0">
                        <?php echo count($cartItems); ?> product <?php echo count($cartItems) === 1 ? 'line' : 'lines'; ?> in your cart
                    </p>
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Continue Shopping</a>
                </div>

                <?php foreach ($cartItems as $item): ?>
                    <?php
                    $imageSource = getCartProductImage($item['image_path']);
                    $isActive = $item['status'] === 'Active';
                    $isHoodie = $item['category_name'] === PRODUCT_CATEGORY_NAME;
                    $hasStock = $item['stock'] > 0;
                    $quantityWithinStock = $item['quantity'] <= $item['stock'];
                    $canUpdateQuantity = $isActive && $isHoodie && $hasStock;
                    $isEligible = $isActive && $isHoodie && $hasStock && $quantityWithinStock;
                    $stockCondition = $hasStock ? 'In Stock' : 'Out of Stock';
                    $stockBadgeClass = $hasStock ? 'text-bg-success' : 'text-bg-danger';
                    $availabilityBadgeClass = $isEligible ? 'text-bg-success' : 'text-bg-warning';
                    $availabilityLabel = $isEligible ? 'Eligible' : 'Unavailable';
                    $availabilityWarnings = [];

                    if (!$isActive || !$isHoodie) {
                        $availabilityWarnings[] = 'Product unavailable.';
                    }

                    if (!$hasStock) {
                        $availabilityWarnings[] = 'Out of Stock.';
                    } elseif (!$quantityWithinStock) {
                        $availabilityWarnings[] = 'Only ' . $item['stock'] . ' item(s) currently available.';
                        $availabilityWarnings[] = 'Quantity exceeds current stock.';
                    }
                    ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="row g-3 align-items-start">
                            <div class="col-sm-4 col-md-3">
                                <img
                                    src="<?php echo escapeOutput($imageSource); ?>"
                                    alt="<?php echo escapeOutput($item['product_name']); ?>"
                                    class="img-fluid rounded border w-100"
                                    style="height: 180px; object-fit: cover;"
                                >
                            </div>
                            <div class="col-sm-8 col-md-9">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                    <div>
                                        <p class="text-muted small mb-1"><?php echo escapeOutput($item['category_name']); ?></p>
                                        <h2 class="h5 mb-2"><?php echo escapeOutput($item['product_name']); ?></h2>
                                    </div>
                                    <p class="fw-semibold mb-0 flex-shrink-0 text-md-end">PHP <?php echo escapeOutput(number_format($item['subtotal'], 2)); ?></p>
                                </div>

                                <div class="row g-2 small mt-1">
                                    <div class="col-6 col-lg-3"><span class="text-muted">Current price</span><br>PHP <?php echo escapeOutput(number_format($item['price'], 2)); ?></div>
                                    <div class="col-6 col-lg-3"><span class="text-muted">Quantity</span><br><?php echo $item['quantity']; ?></div>
                                    <div class="col-6 col-lg-3"><span class="text-muted">Current stock</span><br><?php echo $item['stock']; ?></div>
                                    <div class="col-6 col-lg-3"><span class="text-muted">Status</span><br><?php echo escapeOutput($item['status']); ?></div>
                                </div>

                                <div class="d-flex flex-wrap gap-2 my-3">
                                    <span class="badge <?php echo $stockBadgeClass; ?>"><?php echo escapeOutput($stockCondition); ?></span>
                                    <span class="badge <?php echo $availabilityBadgeClass; ?>"><?php echo escapeOutput($availabilityLabel); ?></span>
                                </div>

                                <?php if (!$isEligible): ?>
                                    <div class="alert alert-warning py-2" role="alert">
                                        <?php foreach ($availabilityWarnings as $warning): ?>
                                            <div><?php echo escapeOutput($warning); ?></div>
                                        <?php endforeach; ?>
                                        <strong>Not eligible for checkout.</strong>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex flex-column flex-md-row gap-2 align-items-md-end" aria-label="Cart item actions">
                                    <?php if ($canUpdateQuantity): ?>
                                        <form method="post" action="<?php echo BASE_URL; ?>buyer/cart_update.php" class="d-flex flex-column flex-sm-row gap-2 align-items-sm-end">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                            <div>
                                                <label for="quantity-<?php echo $item['cart_id']; ?>" class="form-label small mb-1">Quantity</label>
                                                <input
                                                    type="number"
                                                    class="form-control form-control-sm"
                                                    id="quantity-<?php echo $item['cart_id']; ?>"
                                                    name="quantity"
                                                    value="<?php echo $item['quantity']; ?>"
                                                    min="1"
                                                    max="<?php echo $item['stock']; ?>"
                                                    required
                                                    style="width: 100px;"
                                                >
                                            </div>
                                            <button type="submit" class="btn btn-outline-dark btn-sm">Update Quantity</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="This cart item is unavailable">Update Unavailable</button>
                                    <?php endif; ?>
                                    <form method="post" action="<?php echo BASE_URL; ?>buyer/cart_remove.php" onsubmit="return confirm('Remove this item from your cart?');">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            Remove Item
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="border-top pt-3 d-flex justify-content-between align-items-center">
                    <span class="h5 mb-0">Cart Total</span>
                    <strong class="h4 mb-0">PHP <?php echo escapeOutput(number_format($cartTotal, 2)); ?></strong>
                </div>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mt-3">
                    <p class="text-muted small mb-0">Prices and availability are based on current product information.</p>
                    <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/checkout.php">
                        Proceed to Checkout
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
