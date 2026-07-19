<?php
function cleanInput($value)
{
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

function escapeOutput($value)
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

function formatDatabaseDate($dateValue)
{
    if (!is_string($dateValue) || trim($dateValue) === '') {
        return '-';
    }

    $dateValue = trim($dateValue);
    $dateParts = [];

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $dateValue, $dateParts) !== 1) {
        return '-';
    }

    $year = (int) $dateParts[1];
    $month = (int) $dateParts[2];
    $day = (int) $dateParts[3];
    $hour = (int) $dateParts[4];
    $minute = (int) $dateParts[5];
    $second = (int) $dateParts[6];

    if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
        return '-';
    }

    $timestamp = strtotime($dateValue);

    if ($timestamp === false) {
        return '-';
    }

    return date('M j, Y g:i A', $timestamp);
}

function formatMoney($amount)
{
    if (!is_numeric($amount)) {
        return 'PHP -';
    }

    return 'PHP ' . number_format((float) $amount, 2, '.', ',');
}

function isKnownOrderStatus($status)
{
    return is_string($status) && in_array($status, ['Pending', 'Processing', 'Completed', 'Cancelled'], true);
}

function getOrderStatusBadgeClass($status)
{
    $badgeClasses = [
        'Pending' => 'text-bg-warning',
        'Processing' => 'text-bg-primary',
        'Completed' => 'text-bg-success',
        'Cancelled' => 'text-bg-secondary'
    ];

    return $badgeClasses[$status] ?? 'text-bg-light border text-dark';
}

function getOrderStatusLabel($status)
{
    return isKnownOrderStatus($status) ? $status : 'Status unavailable';
}

function getProductStatusBadgeClass($status)
{
    if ($status === 'Active') {
        return 'text-bg-success';
    }

    if ($status === 'Inactive') {
        return 'text-bg-secondary';
    }

    return 'text-bg-light border text-dark';
}

function getProductStatusLabel($status)
{
    return in_array($status, ['Active', 'Inactive'], true) ? $status : 'Status unavailable';
}

function getVerificationBadgeClass($isVerified)
{
    return (int) $isVerified === 1 ? 'text-bg-success' : 'text-bg-warning';
}

function getInventoryConditionBadgeClass($stock)
{
    return (int) $stock > 0 ? 'text-bg-success' : 'text-bg-danger';
}

function parsePositiveIntegerId($value, $maximumValue = 4294967295)
{
    if (
        !is_string($value) ||
        $value === '' ||
        strlen($value) > 10 ||
        !ctype_digit($value)
    ) {
        return false;
    }

    $maximumValueText = (string) $maximumValue;
    $valueWithoutLeadingZeros = ltrim($value, '0');

    if ($valueWithoutLeadingZeros === '') {
        return false;
    }

    if (
        strlen($valueWithoutLeadingZeros) > strlen($maximumValueText) ||
        (
            strlen($valueWithoutLeadingZeros) === strlen($maximumValueText) &&
            strcmp($valueWithoutLeadingZeros, $maximumValueText) > 0
        )
    ) {
        return false;
    }

    $identifier = (int) $value;

    if ($identifier <= 0 || $identifier > $maximumValue) {
        return false;
    }

    return $identifier;
}

function hasBlockedUploadExtension($originalFileName)
{
    if (!is_string($originalFileName) || $originalFileName === '') {
        return false;
    }

    $safeBaseName = strtolower(basename(str_replace('\\', '/', $originalFileName)));
    $nameParts = explode('.', $safeBaseName);
    $blockedExtensions = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'js', 'html', 'htm', 'shtml', 'svg'];

    foreach ($nameParts as $namePart) {
        if (in_array($namePart, $blockedExtensions, true)) {
            return true;
        }
    }

    return false;
}
