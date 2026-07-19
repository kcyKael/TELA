<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Add Admin';
$activePage = 'admin_users';
$actingAdminId = (int) $_SESSION['user_id'];
$fullName = '';
$email = '';
$address = '';
$contactNumber = '';
$errors = [];

function getAdminFormValue($fieldName)
{
    $value = $_POST[$fieldName] ?? '';

    if (!is_string($value)) {
        return '';
    }

    return cleanInput($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = getAdminFormValue('full_name');
    $email = getAdminFormValue('email');
    $address = getAdminFormValue('address');
    $contactNumber = getAdminFormValue('contact_number');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (!verifyCsrfToken()) {
        $errors[] = 'The request could not be completed. Please try again.';
    }

    if (empty($errors)) {
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
    }

    if (empty($errors)) {
        $checkSql = 'SELECT user_id FROM users WHERE email = ? LIMIT 1';

        try {
            $checkStmt = mysqli_prepare($conn, $checkSql);

            if ($checkStmt === false) {
                $errors[] = 'Admin account could not be created right now.';
            } elseif (!mysqli_stmt_bind_param($checkStmt, 's', $email)) {
                $errors[] = 'Admin account could not be created right now.';
                mysqli_stmt_close($checkStmt);
            } elseif (!mysqli_stmt_execute($checkStmt) || !mysqli_stmt_store_result($checkStmt)) {
                $errors[] = 'Admin account could not be created right now.';
                mysqli_stmt_close($checkStmt);
            } else {
                if (mysqli_stmt_num_rows($checkStmt) > 0) {
                    $errors[] = 'Email address is already in use.';
                }

                mysqli_stmt_close($checkStmt);
            }
        } catch (Throwable $exception) {
            $errors[] = 'Admin account could not be created right now.';
            error_log('Admin email duplicate check failed for acting admin user ID ' . $actingAdminId . '.');
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($passwordHash === false) {
            $errors[] = 'Admin account could not be created right now.';
        }
    }

    if (empty($errors)) {
        $transactionStarted = false;
        $duplicateEmailFailure = false;

        try {
            if (!mysqli_begin_transaction($conn)) {
                throw new RuntimeException('Transaction start failed.');
            }

            $transactionStarted = true;
            $role = 'admin';
            $isVerified = 1;
            $insertSql = '
                INSERT INTO users
                    (full_name, email, password_hash, address, contact_number, role, is_verified, verification_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
            ';
            $insertStmt = mysqli_prepare($conn, $insertSql);

            if ($insertStmt === false) {
                throw new RuntimeException('User statement preparation failed.');
            }

            if (!mysqli_stmt_bind_param(
                $insertStmt,
                'ssssssi',
                $fullName,
                $email,
                $passwordHash,
                $address,
                $contactNumber,
                $role,
                $isVerified
            )) {
                mysqli_stmt_close($insertStmt);
                throw new RuntimeException('User parameter binding failed.');
            }

            if (!mysqli_stmt_execute($insertStmt)) {
                $duplicateEmailFailure = mysqli_stmt_errno($insertStmt) === 1062;
                mysqli_stmt_close($insertStmt);
                throw new RuntimeException('User insertion failed.');
            }

            $newUserId = mysqli_insert_id($conn);
            mysqli_stmt_close($insertStmt);

            if ($newUserId <= 0) {
                throw new RuntimeException('New user ID was unavailable.');
            }

            $auditSql = '
                INSERT INTO audit_logs (user_id, activity, description, ip_address)
                VALUES (?, ?, ?, ?)
            ';
            $auditStmt = mysqli_prepare($conn, $auditSql);

            if ($auditStmt === false) {
                throw new RuntimeException('Audit statement preparation failed.');
            }

            $activity = 'Add Admin';
            $description = 'Created verified administrator account ID ' . $newUserId . ' with email ' . $email . '.';
            $ipAddress = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
                ? substr($_SERVER['REMOTE_ADDR'], 0, 45)
                : '';

            if (!mysqli_stmt_bind_param($auditStmt, 'isss', $actingAdminId, $activity, $description, $ipAddress)) {
                mysqli_stmt_close($auditStmt);
                throw new RuntimeException('Audit parameter binding failed.');
            }

            if (!mysqli_stmt_execute($auditStmt)) {
                mysqli_stmt_close($auditStmt);
                throw new RuntimeException('Audit insertion failed.');
            }

            mysqli_stmt_close($auditStmt);

            if (!mysqli_commit($conn)) {
                throw new RuntimeException('Transaction commit failed.');
            }

            $transactionStarted = false;
            $_SESSION['user_management_success'] = 'Administrator account created successfully.';
            redirectTo(rtrim(APP_BASE_URL, '/') . '/admin/users.php');
        } catch (Throwable $exception) {
            if ($exception instanceof mysqli_sql_exception && (int) $exception->getCode() === 1062) {
                $duplicateEmailFailure = true;
            }

            if ($transactionStarted) {
                try {
                    mysqli_rollback($conn);
                } catch (Throwable $rollbackException) {
                    // The original failure is reported with a safe message below.
                }
            }

            if ($duplicateEmailFailure) {
                $errors[] = 'Email address is already in use.';
            } else {
                $errors[] = 'Admin account could not be created right now.';
            }

            error_log('Admin account creation failed for acting admin user ID ' . $actingAdminId . '.');
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <p class="section-label mb-2">Admin Management</p>
                    <h1 class="h3 mb-0">Add Admin</h1>
                </div>
                <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>admin/users.php">Back to Users</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escapeOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo BASE_URL; ?>admin/user_add.php">
                <?php echo csrfTokenField(); ?>

                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo escapeOutput($fullName); ?>" minlength="2" maxlength="100" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo escapeOutput($email); ?>" maxlength="150" autocomplete="email" required>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="mt-3 mb-3">
                    <label for="address" class="form-label">Complete Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3" minlength="5" required><?php echo escapeOutput($address); ?></textarea>
                </div>

                <div class="mb-4">
                    <label for="contact_number" class="form-label">Contact Number</label>
                    <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo escapeOutput($contactNumber); ?>" maxlength="20" inputmode="tel" required>
                </div>

                <div class="d-flex flex-column flex-sm-row gap-2">
                    <button type="submit" class="btn btn-dark">Create Admin</button>
                    <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>admin/users.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
