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
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'store' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>buyer/store.php">Hoodies</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'about' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>buyer/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'login' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>auth/login.php">Login</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $activePage === 'register' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>auth/register.php">Register</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
