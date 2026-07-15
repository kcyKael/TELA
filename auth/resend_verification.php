<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/brevo_email.php';

$pageTitle = 'Resend Verification Email';
$activePage = 'login';

$email = '';
$errors = [];
$successMessage = '';
$genericMessage = 'If the email address is registered and still needs verification, a new verification email will be sent when allowed.';
$resendWaitSeconds = 5 * 60;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('RESEND: POST received');
    $email = cleanInput($_POST['email'] ?? '');

    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        error_log('RESEND: email validated');
        $successMessage = $genericMessage;

        $selectSql = '
            SELECT
                user_id,
                full_name,
                is_verified,
                verification_token,
                UNIX_TIMESTAMP(created_at) AS created_at_unix,
                UNIX_TIMESTAMP(updated_at) AS updated_at_unix
            FROM users
            WHERE email = ?
            LIMIT 1
        ';
        $selectStmt = mysqli_prepare($conn, $selectSql);

        if ($selectStmt === false) {
            error_log('RESEND: account lookup prepare failed');
        } else {
            mysqli_stmt_bind_param($selectStmt, 's', $email);
            mysqli_stmt_execute($selectStmt);
            mysqli_stmt_bind_result($selectStmt, $userId, $fullName, $isVerified, $currentToken, $createdAtUnix, $updatedAtUnix);
            $accountFound = mysqli_stmt_fetch($selectStmt);
            mysqli_stmt_close($selectStmt);

            if (!$accountFound) {
                error_log('RESEND: account row not found');
            } else {
                error_log('RESEND: account row found; user_id=' . $userId . '; verified=' . (int) $isVerified);
            }

            if ($accountFound && (int) $isVerified === 0) {
                error_log('RESEND: account eligible; user_id=' . $userId);
                $effectiveTokenTime = $updatedAtUnix !== null ? (int) $updatedAtUnix : (int) $createdAtUnix;
                $requestAllowed = $effectiveTokenTime === 0 || (time() - $effectiveTokenTime) >= $resendWaitSeconds;

                if ($requestAllowed) {
                    error_log('RESEND: throttle passed; user_id=' . $userId);
                    $newVerificationToken = bin2hex(random_bytes(32));
                    $updateSql = 'UPDATE users SET verification_token = ?, updated_at = NOW() WHERE user_id = ? AND is_verified = 0';
                    $updateStmt = mysqli_prepare($conn, $updateSql);

                    if ($updateStmt === false) {
                        error_log('RESEND: token update prepare failed; user_id=' . $userId);
                    } else {
                        error_log('RESEND: token update attempted; user_id=' . $userId);
                        mysqli_stmt_bind_param($updateStmt, 'si', $newVerificationToken, $userId);
                        mysqli_stmt_execute($updateStmt);
                        $updatedRows = mysqli_stmt_affected_rows($updateStmt);
                        mysqli_stmt_close($updateStmt);
                        error_log('RESEND: token update affected rows=' . $updatedRows . '; user_id=' . $userId);

                        if ($updatedRows === 1) {
                            error_log('RESEND: Brevo helper called; user_id=' . $userId);
                            $emailSent = sendVerificationEmail($email, $fullName, $newVerificationToken);

                            if ($emailSent) {
                                error_log('RESEND: Brevo helper success; user_id=' . $userId);
                            } else {
                                error_log('RESEND: Brevo helper failure; user_id=' . $userId);
                            }
                        }
                    }
                } else {
                    error_log('RESEND: throttle blocked; user_id=' . $userId);
                }
            } elseif ($accountFound) {
                error_log('RESEND: account ineligible; already verified; user_id=' . $userId);
            }
        }

        $email = '';
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Email Verification</p>
            <h1 class="h3 mb-3">Resend Verification Email</h1>
            <p class="text-muted">Enter the email address used during registration.</p>

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

            <form id="resendVerificationForm" method="post" action="resend_verification.php" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo escapeOutput($email); ?>" maxlength="150" required>
                </div>

                <button type="submit" class="btn btn-dark w-100">Resend Verification Email</button>
            </form>

            <p class="mt-3 mb-0">
                <a href="<?php echo BASE_URL; ?>auth/login.php">Back to login</a>
            </p>
        </div>
    </div>
</section>

<script src="<?php echo BASE_URL; ?>assets/js/resend_verification.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
