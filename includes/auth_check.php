<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

function requireAuthenticated()
{
    if (
        !isset($_SESSION['user_id']) ||
        !isset($_SESSION['role']) ||
        !isset($_SESSION['is_verified']) ||
        (int) $_SESSION['is_verified'] !== 1
    ) {
        redirectTo(rtrim(APP_BASE_URL, '/') . '/auth/login.php');
    }
}

function requireAdmin()
{
    requireAuthenticated();

    if ($_SESSION['role'] !== 'admin') {
        redirectTo(rtrim(APP_BASE_URL, '/') . '/buyer/store.php');
    }
}

function requireBuyer()
{
    requireAuthenticated();

    if ($_SESSION['role'] !== 'buyer') {
        redirectTo(rtrim(APP_BASE_URL, '/') . '/admin/dashboard.php');
    }
}

function currentUserRole()
{
    return $_SESSION['role'] ?? '';
}

function currentUserName()
{
    return $_SESSION['full_name'] ?? '';
}

if (strpos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') !== false) {
    requireAdmin();
}
