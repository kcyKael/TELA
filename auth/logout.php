<?php
require_once __DIR__ . '/../config/config.php';
initializeTelaSession();
require_once __DIR__ . '/../includes/functions.php';

$publicRedirect = rtrim(APP_BASE_URL, '/') . '/index.php';
$loginRedirect = rtrim(APP_BASE_URL, '/') . '/auth/login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirectTo($publicRedirect);
}

if (!verifyCsrfToken()) {
    csrfFailure($publicRedirect);
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if ($userId > 0) {
    require_once __DIR__ . '/../config/database.php';
    $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt !== false) {
        $activity = 'Logout';
        $description = 'User logged out.';
        $ipAddressValue = $_SERVER['REMOTE_ADDR'] ?? '';
        $ipAddress = is_string($ipAddressValue) ? substr($ipAddressValue, 0, 45) : '';

        if (mysqli_stmt_bind_param($auditStmt, 'isss', $userId, $activity, $description, $ipAddress)) {
            mysqli_stmt_execute($auditStmt);
        }

        mysqli_stmt_close($auditStmt);
    }
}

$_SESSION = [];

if ((bool) ini_get('session.use_cookies')) {
    $cookieParams = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'] ?? 'Lax'
        ]
    );
}

session_destroy();
redirectTo($loginRedirect);
