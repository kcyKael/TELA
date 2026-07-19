<?php
$localConfigPath = __DIR__ . '/config.local.php';

if (file_exists($localConfigPath)) {
    require_once $localConfigPath;
}

function telaEnvironmentValue($name, $defaultValue = '')
{
    $value = getenv($name);

    if (!is_string($value) || $value === '') {
        return $defaultValue;
    }

    return $value;
}

function isTelaHttpsRequest()
{
    $httpsValue = $_SERVER['HTTPS'] ?? '';

    if (is_string($httpsValue) && $httpsValue !== '' && strtolower($httpsValue) !== 'off') {
        return true;
    }

    return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
}

function initializeTelaSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $currentCookieParams = session_get_cookie_params();
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params([
        'lifetime' => $currentCookieParams['lifetime'],
        'path' => $currentCookieParams['path'],
        'domain' => $currentCookieParams['domain'],
        'secure' => isTelaHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function sendTelaSecurityHeaders()
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', strtolower(telaEnvironmentValue('TELA_APP_ENV', 'development')));
}

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

if (!defined('DB_HOST')) {
    define('DB_HOST', telaEnvironmentValue('TELA_DB_HOST', APP_ENV === 'production' ? '' : 'localhost'));
}

if (!defined('DB_USER')) {
    define('DB_USER', telaEnvironmentValue('TELA_DB_USER', APP_ENV === 'production' ? '' : 'root'));
}

if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', telaEnvironmentValue('TELA_DB_PASSWORD', ''));
}

if (!defined('DB_NAME')) {
    define('DB_NAME', telaEnvironmentValue('TELA_DB_NAME', APP_ENV === 'production' ? '' : 'tela_db'));
}

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'TELA');
}

if (!defined('GROUP_NAME')) {
    define('GROUP_NAME', 'TELA Group');
}

if (!defined('BASE_URL')) {
    $defaultBaseUrl = APP_ENV === 'production' ? '/' : '/TELA/';
    define('BASE_URL', telaEnvironmentValue('TELA_BASE_URL', $defaultBaseUrl));
}

if (!defined('APP_BASE_URL')) {
    $defaultAppBaseUrl = APP_ENV === 'production' ? 'https://tela.kcykae.dev' : 'http://localhost/TELA';
    define('APP_BASE_URL', telaEnvironmentValue('TELA_APP_BASE_URL', $defaultAppBaseUrl));
}

if (!defined('PRODUCT_CATEGORY_NAME')) {
    define('PRODUCT_CATEGORY_NAME', 'Hoodies');
}

if (!defined('PRODUCT_UPLOAD_PATH')) {
    define('PRODUCT_UPLOAD_PATH', 'uploads/products/');
}

if (!defined('PROFILE_UPLOAD_PATH')) {
    define('PROFILE_UPLOAD_PATH', 'uploads/profiles/');
}

if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', '');
}

if (!defined('BREVO_SENDER_NAME')) {
    define('BREVO_SENDER_NAME', SITE_NAME);
}

if (!defined('BREVO_SENDER_EMAIL')) {
    define('BREVO_SENDER_EMAIL', '');
}

sendTelaSecurityHeaders();
