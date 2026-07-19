<?php
require_once __DIR__ . '/config.php';

if (!function_exists('mysqli_connect')) {
    error_log('TELA database extension is unavailable.');
    http_response_code(500);
    die('A database connection error occurred. Please try again later.');
}

mysqli_report(MYSQLI_REPORT_OFF);

$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPassword = DB_PASSWORD;
$dbName = DB_NAME;

if (
    $dbHost === '' ||
    $dbUser === '' ||
    $dbName === '' ||
    (APP_ENV === 'production' && $dbPassword === '')
) {
    http_response_code(500);
    die('A database connection error occurred. Please try again later.');
}

$conn = @mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);

if (!$conn) {
    error_log('TELA database connection failed.');
    http_response_code(500);
    die('A database connection error occurred. Please try again later.');
}

if (!mysqli_set_charset($conn, 'utf8mb4')) {
    error_log('TELA database character-set configuration failed.');
    mysqli_close($conn);
    http_response_code(500);
    die('A database connection error occurred. Please try again later.');
}
