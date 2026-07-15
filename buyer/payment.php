<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
$pageTitle = 'Payment';
$activePage = 'store';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Milestone 1 Setup</p>
            <h1 class="h3 mb-3">Payment</h1>
            <p class="mb-0">Payment simulation will be implemented in a later milestone without external payment API integration.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
