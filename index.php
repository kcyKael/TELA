<?php
$pageTitle = 'TELA Hoodie Store';
$activePage = 'home';
include __DIR__ . '/includes/header.php';

$homeUserRole = '';

if (isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['is_verified']) && (int) $_SESSION['is_verified'] === 1) {
    $homeUserRole = $_SESSION['role'];
}
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel home-panel home-hero">
            <div class="home-hero-content">
                <p class="section-label mb-3">Technology Enhanced Lifestyle Apparel</p>
                <h1 class="home-hero-title mb-3">Engineered<br>for every layer.</h1>
                <p class="lead mb-3">A focused online store for Hoodies.</p>
                <p class="text-muted mb-4 home-intro">
                    Refined everyday essentials, designed with a modern technical edge.
                </p>

                <div class="d-flex flex-column flex-sm-row flex-wrap gap-2 home-actions">
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>buyer/store.php">Browse Hoodies</a>
                <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>buyer/about.php">About TELA</a>

                <?php if ($homeUserRole === 'admin'): ?>
                    <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>admin/dashboard.php">Admin Dashboard</a>
                <?php elseif ($homeUserRole === 'buyer'): ?>
                    <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>buyer/orders.php">My Orders</a>
                <?php else: ?>
                    <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>auth/login.php">Login</a>
                    <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>auth/register.php">Register</a>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
