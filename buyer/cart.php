<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Cart';
$activePage = 'cart';
$buyerId = (int) $_SESSION['user_id'];
$cartRowCount = 0;
$cartLoadError = '';

$cartSql = 'SELECT COUNT(*) FROM cart WHERE user_id = ?';
$cartStmt = mysqli_prepare($conn, $cartSql);

if ($cartStmt === false) {
    $cartLoadError = 'Your cart could not be loaded right now.';
} else {
    mysqli_stmt_bind_param($cartStmt, 'i', $buyerId);

    if (mysqli_stmt_execute($cartStmt)) {
        mysqli_stmt_bind_result($cartStmt, $cartRowCountResult);

        if (mysqli_stmt_fetch($cartStmt)) {
            $cartRowCount = (int) $cartRowCountResult;
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

            <?php if ($cartLoadError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo escapeOutput($cartLoadError); ?>
                </div>
            <?php elseif ($cartRowCount === 0): ?>
                <div class="alert alert-info" role="alert">
                    Your cart is empty.
                </div>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Browse Hoodies</a>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    Your cart contains <?php echo $cartRowCount; ?> product <?php echo $cartRowCount === 1 ? 'line' : 'lines'; ?>.
                </div>
                <p class="text-muted">Cart item details and totals will be added in a later part of this milestone.</p>
                <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Continue Shopping</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
