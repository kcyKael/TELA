<?php
$localConfigPath = __DIR__ . '/config.local.php';

if (file_exists($localConfigPath)) {
    require_once $localConfigPath;
}

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'TELA');
}

if (!defined('GROUP_NAME')) {
    define('GROUP_NAME', 'TELA Group');
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/TELA/');
}

if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', 'http://localhost/TELA');
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
