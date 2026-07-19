<?php
function cleanInput($value)
{
    return trim($value);
}

function escapeOutput($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirectTo($path)
{
    header('Location: ' . $path);
    exit;
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function generateCsrfToken()
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        $_SESSION['csrf_token'] === ''
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfTokenField()
{
    $csrfToken = generateCsrfToken();
    $escapedToken = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

    return '<input type="hidden" name="csrf_token" value="' . $escapedToken . '">';
}

function verifyCsrfToken()
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    $storedToken = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !is_string($storedToken) ||
        $submittedToken === '' ||
        $storedToken === ''
    ) {
        return false;
    }

    return hash_equals($storedToken, $submittedToken);
}

function csrfFailure($trustedRedirectPath)
{
    redirectTo($trustedRedirectPath);
}

function generateCheckoutToken()
{
    if (
        !isset($_SESSION['checkout_token']) ||
        !is_string($_SESSION['checkout_token']) ||
        $_SESSION['checkout_token'] === ''
    ) {
        $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['checkout_token'];
}

function checkoutTokenField()
{
    $checkoutToken = generateCheckoutToken();
    $escapedToken = htmlspecialchars($checkoutToken, ENT_QUOTES, 'UTF-8');

    return '<input type="hidden" name="checkout_token" value="' . $escapedToken . '">';
}

function verifyCheckoutToken()
{
    $submittedToken = $_POST['checkout_token'] ?? '';
    $storedToken = $_SESSION['checkout_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !is_string($storedToken) ||
        $submittedToken === '' ||
        $storedToken === ''
    ) {
        return false;
    }

    return hash_equals($storedToken, $submittedToken);
}
