<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;

if ($userId !== null) {
    $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt !== false) {
        $activity = 'Logout';
        $description = 'User logged out.';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        mysqli_stmt_bind_param($auditStmt, 'isss', $userId, $activity, $description, $ipAddress);
        mysqli_stmt_execute($auditStmt);
        mysqli_stmt_close($auditStmt);
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookieParams = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $cookieParams['path'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
}

session_destroy();
redirectTo(rtrim(APP_BASE_URL, '/') . '/auth/login.php');
