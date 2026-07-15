<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
$pageTitle = 'My Orders';
$activePage = 'store';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Milestone 1 Setup</p>
            <h1 class="h3 mb-3">My Orders</h1>
            <p class="mb-0">Buyer order history will be implemented after checkout is complete.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
