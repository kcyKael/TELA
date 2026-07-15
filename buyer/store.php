<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireBuyer();
$pageTitle = 'Hoodies';
$activePage = 'store';
include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Milestone 1 Setup</p>
            <h1 class="h3 mb-3">Hoodies</h1>
            <p class="mb-0">The storefront will list active Hoodie products in a later milestone. Out-of-stock products will remain visible with disabled cart actions.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
