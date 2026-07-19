<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Checkout';
$activePage = 'cart';
$buyerId = (int) $_SESSION['user_id'];
$cartHasItems = false;
$checkoutLoadError = '';

$cartCheckSql = 'SELECT cart_id FROM cart WHERE user_id = ? LIMIT 1';
$cartCheckStmt = mysqli_prepare($conn, $cartCheckSql);

if ($cartCheckStmt === false) {
    $checkoutLoadError = 'Checkout could not be loaded right now. Please try again later.';
} else {
    mysqli_stmt_bind_param($cartCheckStmt, 'i', $buyerId);

    if (mysqli_stmt_execute($cartCheckStmt)) {
        mysqli_stmt_bind_result($cartCheckStmt, $cartId);
        $cartHasItems = mysqli_stmt_fetch($cartCheckStmt);
    } else {
        $checkoutLoadError = 'Checkout could not be loaded right now. Please try again later.';
    }

    mysqli_stmt_close($cartCheckStmt);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Checkout</p>
            <h1 class="h3 mb-3">Checkout</h1>
            <p class="text-muted">Review your cart before placing an order.</p>

            <?php if ($checkoutLoadError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo escapeOutput($checkoutLoadError); ?>
                </div>
                <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/cart.php">Back to Cart</a>
            <?php elseif (!$cartHasItems): ?>
                <div class="alert alert-info" role="alert">
                    Your cart is empty. Add a Hoodie before continuing to checkout.
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/cart.php">Back to Cart</a>
                    <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Browse Hoodies</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    Your cart contents will be reviewed here in Milestone 7 Part 2.
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/cart.php">Back to Cart</a>
                    <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>buyer/store.php">Continue Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
