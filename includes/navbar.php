<?php
$isLoggedIn = isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['is_verified']) && (int) $_SESSION['is_verified'] === 1;
$userRole = $isLoggedIn ? $_SESSION['role'] : '';
$brandPath = $userRole === 'admin' ? 'admin/dashboard.php' : ($userRole === 'buyer' ? 'buyer/store.php' : 'index.php');
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?php echo BASE_URL . $brandPath; ?>">
            <span><?php echo GROUP_NAME; ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'home' ? ' active' : ''; ?>" <?php echo $activePage === 'home' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'store' ? ' active' : ''; ?>" <?php echo $activePage === 'store' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>buyer/store.php">Store</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'about' ? ' active' : ''; ?>" <?php echo $activePage === 'about' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>buyer/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'login' ? ' active' : ''; ?>" <?php echo $activePage === 'login' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>auth/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'register' ? ' active' : ''; ?>" <?php echo $activePage === 'register' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>auth/register.php">Register</a>
                    </li>
                <?php elseif ($userRole === 'buyer'): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'store' ? ' active' : ''; ?>" <?php echo $activePage === 'store' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>buyer/store.php">Store</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'cart' ? ' active' : ''; ?>" <?php echo $activePage === 'cart' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>buyer/cart.php">Cart</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'orders' ? ' active' : ''; ?>" <?php echo $activePage === 'orders' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>buyer/orders.php">My Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'about' ? ' active' : ''; ?>" <?php echo $activePage === 'about' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>buyer/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="<?php echo BASE_URL; ?>auth/logout.php" class="nav-logout-form">
                            <?php echo csrfTokenField(); ?>
                            <button type="submit" class="nav-link nav-logout-button">Logout</button>
                        </form>
                    </li>
                <?php elseif ($userRole === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'admin' ? ' active' : ''; ?>" <?php echo $activePage === 'admin' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>admin/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'admin_users' ? ' active' : ''; ?>" <?php echo $activePage === 'admin_users' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>admin/users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'admin_orders' ? ' active' : ''; ?>" <?php echo $activePage === 'admin_orders' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>admin/orders.php">Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'admin_categories' ? ' active' : ''; ?>" <?php echo $activePage === 'admin_categories' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>admin/categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'admin_products' ? ' active' : ''; ?>" <?php echo $activePage === 'admin_products' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>admin/products.php">Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'admin_inventory' ? ' active' : ''; ?>" <?php echo $activePage === 'admin_inventory' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>admin/inventory.php">Inventory</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $activePage === 'admin_audit' ? ' active' : ''; ?>" <?php echo $activePage === 'admin_audit' ? 'aria-current="page"' : ''; ?> href="<?php echo BASE_URL; ?>admin/audit_logs.php">Audit Logs</a>
                    </li>
                    <li class="nav-item">
                        <form method="post" action="<?php echo BASE_URL; ?>auth/logout.php" class="nav-logout-form">
                            <?php echo csrfTokenField(); ?>
                            <button type="submit" class="nav-link nav-logout-button">Logout</button>
                        </form>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
