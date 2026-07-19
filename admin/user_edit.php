<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Edit Admin';
$activePage = 'admin_users';
$actingAdminId = (int) $_SESSION['user_id'];
$targetUserId = 0;
$targetAvailable = false;
$targetLoadError = '';
$fullName = '';
$email = '';
$address = '';
$contactNumber = '';
$targetRole = '';
$targetIsVerified = 0;
$targetCreatedAt = '';
$errors = [];
$infoMessage = '';

function getAdminEditFormValue($fieldName)
{
    $value = $_POST[$fieldName] ?? '';

    if (!is_string($value)) {
        return '';
    }

    return cleanInput($value);
}

function getStrictPositiveUserId($value)
{
    if (
        !is_string($value) ||
        $value === '' ||
        strlen($value) > 10 ||
        !ctype_digit($value)
    ) {
        return 0;
    }

    $userId = (int) $value;

    if ($userId <= 0 || $userId > 4294967295) {
        return 0;
    }

    return $userId;
}

function insertAdminEditAudit($conn, $actingAdminId, $activity, $description, $ipAddress)
{
    $auditSql = '
        INSERT INTO audit_logs (user_id, activity, description, ip_address)
        VALUES (?, ?, ?, ?)
    ';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt === false) {
        return false;
    }

    if (!mysqli_stmt_bind_param(
        $auditStmt,
        'isss',
        $actingAdminId,
        $activity,
        $description,
        $ipAddress
    )) {
        mysqli_stmt_close($auditStmt);
        return false;
    }

    $auditCreated = mysqli_stmt_execute($auditStmt);
    mysqli_stmt_close($auditStmt);

    return $auditCreated;
}

$submittedUserId = $_GET['user_id'] ?? '';
$targetUserId = getStrictPositiveUserId($submittedUserId);

if ($targetUserId === 0) {
    $targetLoadError = 'User could not be loaded.';
} else {
    try {
        $loadSql = '
            SELECT full_name, email, address, contact_number, role, is_verified, created_at
            FROM users
            WHERE user_id = ? AND role = \'admin\'
            LIMIT 1
        ';
        $loadStmt = mysqli_prepare($conn, $loadSql);

        if ($loadStmt === false) {
            $targetLoadError = 'User could not be loaded.';
        } elseif (!mysqli_stmt_bind_param($loadStmt, 'i', $targetUserId)) {
            $targetLoadError = 'User could not be loaded.';
            mysqli_stmt_close($loadStmt);
        } elseif (!mysqli_stmt_execute($loadStmt)) {
            $targetLoadError = 'User could not be loaded.';
            mysqli_stmt_close($loadStmt);
        } elseif (!mysqli_stmt_bind_result(
            $loadStmt,
            $loadedFullName,
            $loadedEmail,
            $loadedAddress,
            $loadedContactNumber,
            $loadedRole,
            $loadedIsVerified,
            $loadedCreatedAt
        )) {
            $targetLoadError = 'User could not be loaded.';
            mysqli_stmt_close($loadStmt);
        } else {
            $loadFetchResult = mysqli_stmt_fetch($loadStmt);
            mysqli_stmt_close($loadStmt);

            if ($loadFetchResult !== true) {
                $targetLoadError = 'User could not be loaded.';
            } else {
                $targetAvailable = true;
                $fullName = $loadedFullName;
                $email = $loadedEmail;
                $address = $loadedAddress;
                $contactNumber = $loadedContactNumber;
                $targetRole = $loadedRole;
                $targetIsVerified = (int) $loadedIsVerified;
                $targetCreatedAt = $loadedCreatedAt;
            }
        }
    } catch (Throwable $exception) {
        $targetLoadError = 'User could not be loaded.';
        error_log('Admin target lookup failed for target user ID ' . $targetUserId . '.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $targetAvailable) {
    $fullName = getAdminEditFormValue('full_name');
    $email = getAdminEditFormValue('email');
    $address = getAdminEditFormValue('address');
    $contactNumber = getAdminEditFormValue('contact_number');
    $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password'])
        ? $_POST['new_password']
        : '';
    $confirmNewPassword = isset($_POST['confirm_new_password']) && is_string($_POST['confirm_new_password'])
        ? $_POST['confirm_new_password']
        : '';
    $passwordChangeRequested = $newPassword !== '' || $confirmNewPassword !== '';
    $newPasswordHash = '';

    if (!verifyCsrfToken()) {
        $errors[] = 'The request could not be verified.';
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

        if ($passwordChangeRequested) {
            if ($newPassword === '' || $confirmNewPassword === '') {
                $errors[] = 'Enter and confirm the new password.';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif ($newPassword !== $confirmNewPassword) {
                $errors[] = 'New passwords do not match.';
            }
        }
    }

    if (empty($errors) && $passwordChangeRequested) {
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        if ($newPasswordHash === false) {
            $errors[] = 'Admin account could not be updated right now.';
        }
    }

    if (empty($errors)) {
        $duplicateSql = '
            SELECT user_id
            FROM users
            WHERE email = ? AND user_id <> ?
            LIMIT 1
        ';

        try {
            $duplicateStmt = mysqli_prepare($conn, $duplicateSql);

            if ($duplicateStmt === false) {
                $errors[] = 'Admin account could not be updated right now.';
            } elseif (!mysqli_stmt_bind_param($duplicateStmt, 'si', $email, $targetUserId)) {
                $errors[] = 'Admin account could not be updated right now.';
                mysqli_stmt_close($duplicateStmt);
            } elseif (!mysqli_stmt_execute($duplicateStmt) || !mysqli_stmt_store_result($duplicateStmt)) {
                $errors[] = 'Admin account could not be updated right now.';
                mysqli_stmt_close($duplicateStmt);
            } else {
                if (mysqli_stmt_num_rows($duplicateStmt) > 0) {
                    $errors[] = 'Email address is already in use.';
                }

                mysqli_stmt_close($duplicateStmt);
            }
        } catch (Throwable $exception) {
            $errors[] = 'Admin account could not be updated right now.';
            error_log('Admin email duplicate check failed for target user ID ' . $targetUserId . '.');
        }
    }

    if (empty($errors)) {
        $transactionStarted = false;
        $duplicateEmailFailure = false;
        $targetRevalidationFailure = false;

        try {
            if (!mysqli_begin_transaction($conn)) {
                throw new RuntimeException('Transaction start failed.');
            }

            $transactionStarted = true;
            $lockSql = '
                SELECT full_name, email, password_hash, address, contact_number, role, is_verified
                FROM users
                WHERE user_id = ? AND role = \'admin\'
                LIMIT 1
                FOR UPDATE
            ';
            $lockStmt = mysqli_prepare($conn, $lockSql);

            if ($lockStmt === false) {
                throw new RuntimeException('Target lock statement preparation failed.');
            }

            if (!mysqli_stmt_bind_param($lockStmt, 'i', $targetUserId)) {
                mysqli_stmt_close($lockStmt);
                throw new RuntimeException('Target lock parameter binding failed.');
            }

            if (!mysqli_stmt_execute($lockStmt)) {
                mysqli_stmt_close($lockStmt);
                throw new RuntimeException('Target lock query failed.');
            }

            if (!mysqli_stmt_bind_result(
                $lockStmt,
                $currentFullName,
                $currentEmail,
                $currentPasswordHash,
                $currentAddress,
                $currentContactNumber,
                $currentRole,
                $currentIsVerified
            )) {
                mysqli_stmt_close($lockStmt);
                throw new RuntimeException('Target lock result binding failed.');
            }

            $lockFetchResult = mysqli_stmt_fetch($lockStmt);
            mysqli_stmt_close($lockStmt);

            if ($lockFetchResult !== true || $currentRole !== 'admin') {
                $targetRevalidationFailure = true;
                throw new RuntimeException('Admin target revalidation failed.');
            }

            $transactionDuplicateStmt = mysqli_prepare($conn, $duplicateSql);

            if ($transactionDuplicateStmt === false) {
                throw new RuntimeException('Duplicate statement preparation failed.');
            }

            if (!mysqli_stmt_bind_param($transactionDuplicateStmt, 'si', $email, $targetUserId)) {
                mysqli_stmt_close($transactionDuplicateStmt);
                throw new RuntimeException('Duplicate parameter binding failed.');
            }

            if (!mysqli_stmt_execute($transactionDuplicateStmt) || !mysqli_stmt_store_result($transactionDuplicateStmt)) {
                mysqli_stmt_close($transactionDuplicateStmt);
                throw new RuntimeException('Duplicate query failed.');
            }

            if (mysqli_stmt_num_rows($transactionDuplicateStmt) > 0) {
                $duplicateEmailFailure = true;
                mysqli_stmt_close($transactionDuplicateStmt);
                throw new RuntimeException('Duplicate email was found.');
            }

            mysqli_stmt_close($transactionDuplicateStmt);

            $changedFields = [];

            if ($fullName !== $currentFullName) {
                $changedFields[] = 'full_name';
            }

            if ($email !== $currentEmail) {
                $changedFields[] = 'email';
            }

            if ($address !== $currentAddress) {
                $changedFields[] = 'address';
            }

            if ($contactNumber !== $currentContactNumber) {
                $changedFields[] = 'contact_number';
            }

            if ($passwordChangeRequested) {
                $changedFields[] = 'password';
            }

            if (empty($changedFields)) {
                if (!mysqli_rollback($conn)) {
                    throw new RuntimeException('No-change transaction rollback failed.');
                }

                $transactionStarted = false;
                $infoMessage = 'No changes were made.';
            } else {
                if ($passwordChangeRequested) {
                    $updateSql = '
                        UPDATE users
                        SET full_name = ?, email = ?, address = ?, contact_number = ?, password_hash = ?
                        WHERE user_id = ? AND role = \'admin\'
                    ';
                    $updateStmt = mysqli_prepare($conn, $updateSql);

                    if ($updateStmt === false) {
                        throw new RuntimeException('Password update statement preparation failed.');
                    }

                    if (!mysqli_stmt_bind_param(
                        $updateStmt,
                        'sssssi',
                        $fullName,
                        $email,
                        $address,
                        $contactNumber,
                        $newPasswordHash,
                        $targetUserId
                    )) {
                        mysqli_stmt_close($updateStmt);
                        throw new RuntimeException('Password update parameter binding failed.');
                    }
                } else {
                    $updateSql = '
                        UPDATE users
                        SET full_name = ?, email = ?, address = ?, contact_number = ?
                        WHERE user_id = ? AND role = \'admin\'
                    ';
                    $updateStmt = mysqli_prepare($conn, $updateSql);

                    if ($updateStmt === false) {
                        throw new RuntimeException('Profile update statement preparation failed.');
                    }

                    if (!mysqli_stmt_bind_param(
                        $updateStmt,
                        'ssssi',
                        $fullName,
                        $email,
                        $address,
                        $contactNumber,
                        $targetUserId
                    )) {
                        mysqli_stmt_close($updateStmt);
                        throw new RuntimeException('Profile update parameter binding failed.');
                    }
                }

                if (!mysqli_stmt_execute($updateStmt)) {
                    $duplicateEmailFailure = mysqli_stmt_errno($updateStmt) === 1062;
                    mysqli_stmt_close($updateStmt);
                    throw new RuntimeException('Admin update failed.');
                }

                $updatedRows = mysqli_stmt_affected_rows($updateStmt);
                mysqli_stmt_close($updateStmt);

                if ($updatedRows !== 1) {
                    throw new RuntimeException('Admin update affected an unexpected number of rows.');
                }

                $changedFieldNames = implode(', ', $changedFields);
                $ipAddress = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
                    ? substr($_SERVER['REMOTE_ADDR'], 0, 45)
                    : '';
                $updateActivity = 'Update Admin';
                $updateDescription = 'Updated administrator account ID ' . $targetUserId .
                    ' with email ' . $email . '. Changed fields: ' . $changedFieldNames . '.';

                if (!insertAdminEditAudit(
                    $conn,
                    $actingAdminId,
                    $updateActivity,
                    $updateDescription,
                    $ipAddress
                )) {
                    throw new RuntimeException('Update Admin audit insertion failed.');
                }

                if ($passwordChangeRequested) {
                    $passwordActivity = 'Password Change';
                    $passwordDescription = 'Changed password for administrator account ID ' .
                        $targetUserId . ' with email ' . $email . '.';

                    if (!insertAdminEditAudit(
                        $conn,
                        $actingAdminId,
                        $passwordActivity,
                        $passwordDescription,
                        $ipAddress
                    )) {
                        throw new RuntimeException('Password Change audit insertion failed.');
                    }
                }

                if (!mysqli_commit($conn)) {
                    throw new RuntimeException('Transaction commit failed.');
                }

                $transactionStarted = false;

                if ($actingAdminId === $targetUserId) {
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['email'] = $email;
                }

                $_SESSION['user_management_success'] = 'Administrator account updated successfully.';
                redirectTo(rtrim(APP_BASE_URL, '/') . '/admin/users.php');
            }
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
            } elseif ($targetRevalidationFailure) {
                $targetAvailable = false;
                $targetLoadError = 'User could not be loaded.';
            } else {
                $errors[] = 'Admin account could not be updated right now.';
            }

            error_log(
                'Admin account update failed for acting admin user ID ' . $actingAdminId .
                ' and target user ID ' . $targetUserId . '.'
            );
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
                    <h1 class="h3 mb-0">Edit Admin</h1>
                </div>
                <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>admin/users.php">Back to Users</a>
            </div>

            <?php if ($targetLoadError !== ''): ?>
                <div class="alert alert-warning mb-0" role="alert">
                    <?php echo escapeOutput($targetLoadError); ?>
                </div>
            <?php elseif ($targetAvailable): ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo escapeOutput($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($infoMessage !== ''): ?>
                    <div class="alert alert-info" role="status">
                        <?php echo escapeOutput($infoMessage); ?>
                    </div>
                <?php endif; ?>

                <dl class="row small mb-4">
                    <dt class="col-sm-4">Role</dt>
                    <dd class="col-sm-8"><?php echo escapeOutput(ucfirst($targetRole)); ?></dd>
                    <dt class="col-sm-4">Verification</dt>
                    <dd class="col-sm-8">
                        <span class="badge <?php echo getVerificationBadgeClass($targetIsVerified); ?>"><?php echo escapeOutput($targetIsVerified === 1 ? 'Verified' : 'Unverified'); ?></span>
                    </dd>
                    <dt class="col-sm-4">Created Date</dt>
                    <dd class="col-sm-8"><?php echo escapeOutput(formatDatabaseDate($targetCreatedAt)); ?></dd>
                </dl>

                <form method="post" action="<?php echo BASE_URL; ?>admin/user_edit.php?user_id=<?php echo (int) $targetUserId; ?>">
                    <?php echo csrfTokenField(); ?>

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo escapeOutput($fullName); ?>" minlength="2" maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo escapeOutput($email); ?>" maxlength="150" autocomplete="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Complete Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" minlength="5" required><?php echo escapeOutput($address); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="contact_number" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo escapeOutput($contactNumber); ?>" maxlength="20" inputmode="tel" required>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="new_password" class="form-label">New Password (Optional)</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" minlength="8" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <button type="submit" class="btn btn-dark">Save Changes</button>
                        <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>admin/users.php">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
