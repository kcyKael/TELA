<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
$pageTitle = 'Payment';
$activePage = 'cart';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Checkout</p>
            <h1 class="h3 mb-3">Payment Simulation</h1>
            <p>Payment selection is handled securely during Checkout. No real payment information is collected or processed.</p>
            <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/checkout.php">Go to Checkout</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
