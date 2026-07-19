<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Checkout';
$activePage = 'cart';
$buyerId = (int) $_SESSION['user_id'];
$buyerProfile = [
    'full_name' => '',
    'email' => '',
    'address' => '',
    'contact_number' => ''
];
$buyerInformationComplete = false;
$checkoutItems = [];
$checkoutTotal = 0;
$allItemsEligible = true;
$checkoutLoadError = '';

function getCheckoutProductImage($imagePath)
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

$profileSql = 'SELECT full_name, email, address, contact_number FROM users WHERE user_id = ? LIMIT 1';
$profileStmt = mysqli_prepare($conn, $profileSql);

if ($profileStmt === false) {
    $checkoutLoadError = 'Checkout could not be loaded right now. Please try again later.';
} else {
    mysqli_stmt_bind_param($profileStmt, 'i', $buyerId);

    if (mysqli_stmt_execute($profileStmt)) {
        mysqli_stmt_bind_result($profileStmt, $fullName, $email, $address, $contactNumber);

        if (mysqli_stmt_fetch($profileStmt)) {
            $buyerProfile = [
                'full_name' => $fullName,
                'email' => $email,
                'address' => $address,
                'contact_number' => $contactNumber
            ];
            $buyerInformationComplete =
                trim((string) $fullName) !== '' &&
                trim((string) $email) !== '' &&
                trim((string) $address) !== '' &&
                trim((string) $contactNumber) !== '';
        } else {
            $checkoutLoadError = 'Checkout could not be loaded right now. Please try again later.';
        }
    } else {
        $checkoutLoadError = 'Checkout could not be loaded right now. Please try again later.';
    }

    mysqli_stmt_close($profileStmt);
}

if ($checkoutLoadError === '') {
    $cartSql = '
        SELECT
            cart.cart_id,
            cart.product_id,
            cart.quantity,
            products.product_id,
            products.product_name,
            products.image_path,
            products.price,
            products.stock,
            products.status,
            categories.category_name
        FROM cart
        LEFT JOIN products ON cart.product_id = products.product_id
        LEFT JOIN categories ON products.category_id = categories.category_id
        WHERE cart.user_id = ?
        ORDER BY cart.created_at ASC, cart.cart_id ASC
    ';
    $cartStmt = mysqli_prepare($conn, $cartSql);

    if ($cartStmt === false) {
        $checkoutLoadError = 'Checkout could not be loaded right now. Please try again later.';
    } else {
        mysqli_stmt_bind_param($cartStmt, 'i', $buyerId);

        if (mysqli_stmt_execute($cartStmt)) {
            mysqli_stmt_bind_result(
                $cartStmt,
                $cartId,
                $cartProductId,
                $quantity,
                $foundProductId,
                $productName,
                $imagePath,
                $price,
                $stock,
                $status,
                $categoryName
            );

            while (mysqli_stmt_fetch($cartStmt)) {
                $quantityValue = (int) $quantity;
                $priceValue = $price !== null ? (float) $price : 0;
                $stockValue = $stock !== null ? (int) $stock : 0;
                $productExists = $foundProductId !== null;
                $categoryExists = $categoryName !== null;
                $warnings = [];

                if (!$productExists) {
                    $warnings[] = 'This product is no longer available.';
                } else {
                    if (!$categoryExists) {
                        $warnings[] = 'This product category is unavailable.';
                    } elseif ($categoryName !== PRODUCT_CATEGORY_NAME) {
                        $warnings[] = 'This product is not eligible for Hoodie checkout.';
                    }

                    if ($status !== 'Active') {
                        $warnings[] = 'This product is Inactive.';
                    }

                    if ($stockValue <= 0) {
                        $warnings[] = 'Out of Stock.';
                    } elseif ($quantityValue > $stockValue) {
                        $warnings[] = 'Quantity exceeds current stock. Only ' . $stockValue . ' item(s) are available.';
                    }

                    if ($priceValue <= 0) {
                        $warnings[] = 'This product has an invalid current price.';
                    }
                }

                if ($quantityValue <= 0) {
                    $warnings[] = 'This cart quantity is invalid.';
                }

                $itemEligible = empty($warnings);
                $lineSubtotal = $quantityValue > 0 ? round($priceValue * $quantityValue, 2) : 0;
                $checkoutTotal = round($checkoutTotal + $lineSubtotal, 2);

                if (!$itemEligible) {
                    $allItemsEligible = false;
                }

                $checkoutItems[] = [
                    'cart_id' => (int) $cartId,
                    'product_id' => (int) $cartProductId,
                    'product_name' => $productExists && trim((string) $productName) !== '' ? $productName : 'Unavailable product',
                    'image_path' => $productExists ? $imagePath : '',
                    'price' => $priceValue,
                    'quantity' => $quantityValue,
                    'stock' => $stockValue,
                    'status' => $productExists && $status !== null ? $status : 'Unavailable',
                    'category_name' => $categoryExists && trim((string) $categoryName) !== '' ? $categoryName : 'Unavailable',
                    'subtotal' => $lineSubtotal,
                    'eligible' => $itemEligible,
                    'warnings' => $warnings
                ];
            }
        } else {
            $checkoutLoadError = 'Checkout could not be loaded right now. Please try again later.';
        }

        mysqli_stmt_close($cartStmt);
    }
}

if (empty($checkoutItems)) {
    $allItemsEligible = false;
}

$checkoutReady =
    $checkoutLoadError === '' &&
    !empty($checkoutItems) &&
    $buyerInformationComplete &&
    $allItemsEligible &&
    $checkoutTotal > 0;

if (!$checkoutReady) {
    unset($_SESSION['checkout_token']);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Checkout</p>
            <h1 class="h3 mb-3">Checkout</h1>
            <p class="text-muted">Review your registered shipping information and current cart details.</p>

            <?php if ($checkoutLoadError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo escapeOutput($checkoutLoadError); ?>
                </div>
                <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/cart.php">Back to Cart</a>
            <?php elseif (empty($checkoutItems)): ?>
                <div class="alert alert-info" role="alert">
                    Your cart is empty. Add a Hoodie before continuing to checkout.
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/cart.php">Back to Cart</a>
                    <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Browse Hoodies</a>
                </div>
            <?php else: ?>
                <section class="border-bottom pb-4 mb-4" aria-labelledby="shippingInformationHeading">
                    <h2 class="h5 mb-3" id="shippingInformationHeading">Registered Shipping Information</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <span class="text-muted small">Complete Name</span>
                            <p class="mb-0"><?php echo escapeOutput($buyerProfile['full_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Email Address</span>
                            <p class="mb-0"><?php echo escapeOutput($buyerProfile['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Contact Number</span>
                            <p class="mb-0"><?php echo escapeOutput($buyerProfile['contact_number']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <span class="text-muted small">Shipping Address</span>
                            <p class="mb-0"><?php echo nl2br(escapeOutput($buyerProfile['address'])); ?></p>
                        </div>
                    </div>
                    <p class="text-muted small mt-3 mb-0">This is your current registered address. It is not stored as a separate order-address snapshot.</p>
                </section>

                <?php if (!$buyerInformationComplete): ?>
                    <div class="alert alert-warning" role="alert">
                        Your registered account information is incomplete. Please contact an administrator before checking out.
                    </div>
                <?php endif; ?>

                <section aria-labelledby="checkoutItemsHeading">
                    <h2 class="h5 mb-3" id="checkoutItemsHeading">Order Review</h2>

                    <?php foreach ($checkoutItems as $item): ?>
                        <?php
                        $imageSource = getCheckoutProductImage($item['image_path']);
                        $hasStock = $item['stock'] > 0;
                        $stockCondition = $hasStock ? 'In Stock' : 'Out of Stock';
                        $stockBadgeClass = $hasStock ? 'text-bg-success' : 'text-bg-danger';
                        $availabilityBadgeClass = $item['eligible'] ? 'text-bg-success' : 'text-bg-warning';
                        $availabilityLabel = $item['eligible'] ? 'Eligible' : 'Unavailable';
                        ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="row g-3 align-items-start">
                                <div class="col-sm-4 col-md-3">
                                    <img
                                        src="<?php echo escapeOutput($imageSource); ?>"
                                        alt="<?php echo escapeOutput($item['product_name']); ?> Hoodie product image"
                                        class="img-fluid rounded border checkout-item-image"
                                    >
                                </div>
                                <div class="col-sm-8 col-md-9">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                        <div>
                                            <p class="text-muted small mb-1"><?php echo escapeOutput($item['category_name']); ?></p>
                                            <h3 class="h6 mb-2"><?php echo escapeOutput($item['product_name']); ?></h3>
                                        </div>
                                        <p class="fw-semibold mb-0 flex-shrink-0 text-md-end"><?php echo escapeOutput(formatMoney($item['subtotal'])); ?></p>
                                    </div>

                                    <div class="row g-2 small mt-1">
                                        <div class="col-6 col-lg-3"><span class="text-muted">Current price</span><br><?php echo escapeOutput(formatMoney($item['price'])); ?></div>
                                        <div class="col-6 col-lg-3"><span class="text-muted">Quantity</span><br><?php echo (int) $item['quantity']; ?></div>
                                        <div class="col-6 col-lg-3"><span class="text-muted">Current stock</span><br><?php echo (int) $item['stock']; ?></div>
                                        <div class="col-6 col-lg-3"><span class="text-muted">Status</span><br><span class="badge <?php echo getProductStatusBadgeClass($item['status']); ?>"><?php echo escapeOutput(getProductStatusLabel($item['status'])); ?></span></div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <span class="badge <?php echo $stockBadgeClass; ?>"><?php echo escapeOutput($stockCondition); ?></span>
                                        <span class="badge <?php echo $availabilityBadgeClass; ?>"><?php echo escapeOutput($availabilityLabel); ?></span>
                                    </div>

                                    <?php if (!$item['eligible']): ?>
                                        <div class="alert alert-warning py-2 mt-3 mb-0" role="alert">
                                            <?php foreach ($item['warnings'] as $warning): ?>
                                                <div><?php echo escapeOutput($warning); ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <div class="border-top pt-3 d-flex justify-content-between align-items-center">
                    <span class="h5 mb-0">Checkout Total</span>
                    <strong class="h4 mb-0 text-nowrap"><?php echo escapeOutput(formatMoney($checkoutTotal)); ?></strong>
                </div>

                <div class="alert alert-secondary mt-4" role="note">
                    <p class="mb-1">Prices and stock will be revalidated during final submission.</p>
                    <p class="mb-1">Cart availability is not guaranteed until the checkout transaction succeeds.</p>
                    <p class="mb-0">The shipping address shown above is your current registered address.</p>
                </div>

                <?php if (!$allItemsEligible): ?>
                    <div class="alert alert-warning" role="alert">
                        Checkout is unavailable until every cart item is eligible. Return to your Cart to update or remove unavailable items.
                    </div>
                <?php elseif ($checkoutTotal <= 0): ?>
                    <div class="alert alert-warning" role="alert">
                        Checkout is unavailable because the current total is invalid.
                    </div>
                <?php endif; ?>

                <?php if ($checkoutReady): ?>
                    <form
                        method="post"
                        action="<?php echo BASE_URL; ?>buyer/checkout_process.php"
                        onsubmit="this.querySelector('button[type=submit]').disabled = true;"
                    >
                        <?php echo csrfTokenField(); ?>
                        <?php echo checkoutTokenField(); ?>

                        <fieldset class="mb-3">
                            <legend class="h5">Simulated Payment Method</legend>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" id="paymentCashOnDelivery" value="Cash on Delivery" required>
                                <label class="form-check-label" for="paymentCashOnDelivery">Cash on Delivery</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="paymentGcashSimulation" value="GCash Simulation" required>
                                <label class="form-check-label" for="paymentGcashSimulation">GCash Simulation</label>
                            </div>
                            <p class="text-muted small mt-2 mb-0">Payment is simulated for classroom use. No real payment information is collected or processed.</p>
                        </fieldset>

                        <button type="submit" class="btn btn-dark">
                            Place Order
                        </button>
                    </form>
                <?php else: ?>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/cart.php">Back to Cart</a>
                        <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>buyer/store.php">Continue Shopping</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
