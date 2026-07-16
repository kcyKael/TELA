<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
$pageTitle = 'Cart';
$activePage = 'cart';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Cart</p>
            <h1 class="h3 mb-3">Cart</h1>
            <p class="mb-0">Cart functionality will be implemented in a future milestone.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
