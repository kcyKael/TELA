<?php
$isLoggedIn = isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['is_verified']) && (int) $_SESSION['is_verified'] === 1;
$userRole = $isLoggedIn ? $_SESSION['role'] : '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo BASE_URL; ?>index.php">
            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="TELA logo" width="32" height="32">
            <span><?php echo GROUP_NAME; ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'home' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>index.php">Home</a>
                </li>

                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'about' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>buyer/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'login' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'register' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>auth/register.php">Register</a>
                    </li>
                <?php elseif ($userRole === 'buyer'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'store' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>buyer/store.php">Hoodies</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'cart' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>buyer/cart.php">Cart</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'orders' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>buyer/orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'about' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>buyer/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>auth/logout.php">Logout</a>
                    </li>
                <?php elseif ($userRole === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'admin' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'admin_categories' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'admin_products' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/products.php">Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'admin_inventory' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/inventory.php">Inventory</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === 'admin_audit' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/audit_logs.php">Audit Logs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>auth/logout.php">Logout</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
