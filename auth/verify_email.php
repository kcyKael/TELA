<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Email Verification';
$activePage = 'register';

$messageType = 'danger';
$messageTitle = 'Email Verification Failed';
$messageText = 'Verification link is invalid or already used.';
$showLoginLink = false;
$token = cleanInput($_GET['token'] ?? '');
$tokenLifetimeSeconds = 24 * 60 * 60;

if ($token === '') {
    $messageText = 'Verification token is missing. Please use the link from your verification email.';
} else {
    $selectSql = 'SELECT user_id, full_name, is_verified, created_at, updated_at FROM users WHERE verification_token = ? LIMIT 1';
    $selectStmt = mysqli_prepare($conn, $selectSql);

    if ($selectStmt === false) {
        $messageText = 'We could not verify your email right now. Please try again later.';
    } else {
        mysqli_stmt_bind_param($selectStmt, 's', $token);
        mysqli_stmt_execute($selectStmt);
        mysqli_stmt_bind_result($selectStmt, $userId, $fullName, $isVerified, $createdAt, $updatedAt);
        $accountFound = mysqli_stmt_fetch($selectStmt);
        mysqli_stmt_close($selectStmt);

        if (!$accountFound) {
            $messageText = 'Verification link is invalid or already used.';
        } elseif ((int) $isVerified === 1) {
            $messageText = 'This account is already verified. You may log in.';
            $showLoginLink = true;
        } else {
            $tokenIssueTime = $updatedAt !== null ? strtotime($updatedAt) : strtotime($createdAt);
            $tokenExpired = $tokenIssueTime === false || (time() - $tokenIssueTime) > $tokenLifetimeSeconds;

            if ($tokenExpired) {
                $expireSql = 'UPDATE users SET verification_token = NULL, updated_at = NOW() WHERE user_id = ? AND verification_token = ? AND is_verified = 0';
                $expireStmt = mysqli_prepare($conn, $expireSql);

                if ($expireStmt !== false) {
                    mysqli_stmt_bind_param($expireStmt, 'is', $userId, $token);
                    mysqli_stmt_execute($expireStmt);
                    mysqli_stmt_close($expireStmt);
                }

                $messageText = 'Verification link has expired. Please request a new verification email later.';
            } else {
                $updateSql = 'UPDATE users SET is_verified = 1, verification_token = NULL, updated_at = NOW() WHERE user_id = ? AND verification_token = ? AND is_verified = 0';
                $updateStmt = mysqli_prepare($conn, $updateSql);

                if ($updateStmt === false) {
                    $messageText = 'We could not verify your email right now. Please try again later.';
                } else {
                    mysqli_stmt_bind_param($updateStmt, 'is', $userId, $token);
                    mysqli_stmt_execute($updateStmt);
                    $updatedRows = mysqli_stmt_affected_rows($updateStmt);
                    mysqli_stmt_close($updateStmt);

                    if ($updatedRows === 1) {
                        $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
                        $auditStmt = mysqli_prepare($conn, $auditSql);

                        if ($auditStmt !== false) {
                            $activity = 'Email Verification';
                            $description = 'User verified email address.';
                            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
                            mysqli_stmt_bind_param($auditStmt, 'isss', $userId, $activity, $description, $ipAddress);
                            mysqli_stmt_execute($auditStmt);
                            mysqli_stmt_close($auditStmt);
                        }

                        $messageType = 'success';
                        $messageTitle = 'Email Verified';
                        $messageText = 'Thank you, ' . $fullName . '. Your email has been verified successfully.';
                        $showLoginLink = true;
                    } else {
                        $messageText = 'Verification link is invalid or already used.';
                    }
                }
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Email Verification</p>
            <h1 class="h3 mb-3"><?php echo escapeOutput($messageTitle); ?></h1>

            <div class="alert alert-<?php echo escapeOutput($messageType); ?> mb-4" role="alert">
                <?php echo escapeOutput($messageText); ?>
            </div>

            <?php if ($showLoginLink): ?>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>auth/login.php">Go to Login</a>
            <?php else: ?>
                <a class="btn btn-outline-dark" href="<?php echo BASE_URL; ?>auth/register.php">Back to Registration</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
