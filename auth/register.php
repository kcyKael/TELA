<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/brevo_email.php';

$pageTitle = 'Register';
$activePage = 'register';

$fullName = '';
$email = '';
$address = '';
$contactNumber = '';
$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = cleanInput($_POST['full_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $address = cleanInput($_POST['address'] ?? '');
    $contactNumber = cleanInput($_POST['contact_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '') {
        $errors[] = 'Complete name is required.';
    } elseif (strlen($fullName) < 2 || strlen($fullName) > 100) {
        $errors[] = 'Complete name must be 2 to 100 characters.';
    } elseif (!preg_match("/^[A-Za-z .'-]+$/", $fullName) || preg_match('/^[0-9 ]+$/', $fullName)) {
        $errors[] = 'Complete name may contain letters, spaces, periods, hyphens, and apostrophes only.';
    }

    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Confirm password is required.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if ($address === '') {
        $errors[] = 'Complete address is required.';
    } elseif (strlen($address) < 5) {
        $errors[] = 'Complete address must be at least 5 characters.';
    }

    if ($contactNumber === '') {
        $errors[] = 'Contact number is required.';
    } else {
        $contactDigits = preg_replace('/\D/', '', $contactNumber);

        if (strlen($contactNumber) > 20 || !preg_match('/^[0-9+\-\s]+$/', $contactNumber)) {
            $errors[] = 'Contact number may contain numbers, spaces, plus sign, and hyphen only.';
        } elseif (strlen($contactDigits) < 10 || strlen($contactDigits) > 13) {
            $errors[] = 'Contact number must have 10 to 13 digits.';
        }
    }

    if (empty($errors)) {
        $checkSql = 'SELECT user_id FROM users WHERE email = ? LIMIT 1';
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, 's', $email);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errors[] = 'Email is already registered.';
        }

        mysqli_stmt_close($checkStmt);
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $verificationToken = bin2hex(random_bytes(32));
        $role = 'buyer';
        $isVerified = 0;

        $insertSql = 'INSERT INTO users (full_name, email, password_hash, address, contact_number, role, is_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $insertStmt = mysqli_prepare($conn, $insertSql);
        mysqli_stmt_bind_param($insertStmt, 'ssssssis', $fullName, $email, $passwordHash, $address, $contactNumber, $role, $isVerified, $verificationToken);

        if (mysqli_stmt_execute($insertStmt)) {
            $newUserId = mysqli_insert_id($conn);

            $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
            $auditStmt = mysqli_prepare($conn, $auditSql);
            $activity = 'Register';
            $description = 'New buyer account registered with email address.';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            mysqli_stmt_bind_param($auditStmt, 'isss', $newUserId, $activity, $description, $ipAddress);
            mysqli_stmt_execute($auditStmt);
            mysqli_stmt_close($auditStmt);

            if (sendVerificationEmail($email, $fullName, $verificationToken)) {
                $successMessage = 'Registration successful. Please check your email to verify your account.';
            } else {
                $successMessage = 'Registration successful, but the verification email could not be sent right now. Your account is still unverified.';
            }

            $fullName = '';
            $email = '';
            $address = '';
            $contactNumber = '';
        } else {
            if (mysqli_errno($conn) === 1062) {
                $errors[] = 'Email is already registered.';
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }

        mysqli_stmt_close($insertStmt);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Buyer Registration</p>
            <h1 class="h3 mb-3">Create Buyer Account</h1>
            <p class="text-muted">Register for a TELA account. A verification email will be sent after successful registration.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escapeOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo escapeOutput($successMessage); ?>
                </div>
            <?php endif; ?>

            <form id="registrationForm" method="post" action="register.php" novalidate>
                <div class="mb-3">
                    <label for="full_name" class="form-label">Complete Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo escapeOutput($fullName); ?>" maxlength="100" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo escapeOutput($email); ?>" maxlength="150" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Complete Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3" required><?php echo escapeOutput($address); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="contact_number" class="form-label">Contact Number</label>
                    <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo escapeOutput($contactNumber); ?>" maxlength="20" required>
                </div>

                <button type="submit" class="btn btn-dark w-100">Register</button>
            </form>
        </div>
    </div>
</section>

<script src="<?php echo BASE_URL; ?>assets/js/register.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
