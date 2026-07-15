<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminRedirect = rtrim(APP_BASE_URL, '/') . '/admin/dashboard.php';
$buyerRedirect = rtrim(APP_BASE_URL, '/') . '/buyer/store.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        redirectTo($adminRedirect);
    }

    if ($_SESSION['role'] === 'buyer') {
        redirectTo($buyerRedirect);
    }
}

$pageTitle = 'Login';
$activePage = 'login';

$email = '';
$errors = [];
$unverifiedMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $loginSql = 'SELECT user_id, full_name, email, password_hash, role, is_verified FROM users WHERE email = ? LIMIT 1';
        $loginStmt = mysqli_prepare($conn, $loginSql);

        if ($loginStmt === false) {
            $errors[] = 'Login is temporarily unavailable. Please try again later.';
        } else {
            mysqli_stmt_bind_param($loginStmt, 's', $email);
            mysqli_stmt_execute($loginStmt);
            mysqli_stmt_bind_result($loginStmt, $userId, $fullName, $dbEmail, $passwordHash, $role, $isVerified);
            $accountFound = mysqli_stmt_fetch($loginStmt);
            mysqli_stmt_close($loginStmt);

            if (!$accountFound || !password_verify($password, $passwordHash)) {
                $errors[] = 'Invalid email or password.';
            } elseif ((int) $isVerified !== 1) {
                $unverifiedMessage = 'Please verify your email before logging in.';
            } elseif ($role !== 'admin' && $role !== 'buyer') {
                $errors[] = 'Invalid email or password.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $dbEmail;
                $_SESSION['role'] = $role;
                $_SESSION['is_verified'] = (int) $isVerified;

                $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
                $auditStmt = mysqli_prepare($conn, $auditSql);

                if ($auditStmt !== false) {
                    $activity = 'Login';
                    $description = 'User logged in successfully.';
                    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
                    mysqli_stmt_bind_param($auditStmt, 'isss', $userId, $activity, $description, $ipAddress);
                    mysqli_stmt_execute($auditStmt);
                    mysqli_stmt_close($auditStmt);
                }

                if ($role === 'admin') {
                    redirectTo($adminRedirect);
                }

                redirectTo($buyerRedirect);
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Authentication</p>
            <h1 class="h3 mb-3">Login</h1>
            <p class="text-muted">Log in using your verified TELA account.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escapeOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($unverifiedMessage !== ''): ?>
                <div class="alert alert-warning" role="alert">
                    <?php echo escapeOutput($unverifiedMessage); ?>
                    <a href="<?php echo BASE_URL; ?>auth/resend_verification.php" class="alert-link">Resend verification email</a>.
                </div>
            <?php endif; ?>

            <form method="post" action="login.php" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo escapeOutput($email); ?>" maxlength="150" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-dark w-100">Login</button>
            </form>

            <p class="mt-3 mb-0">
                Need an account? <a href="<?php echo BASE_URL; ?>auth/register.php">Register here</a>.
            </p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
