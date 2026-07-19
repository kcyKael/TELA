<?php
require_once __DIR__ . '/config.php';

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
    die('A database connection error occurred. Please try again later.');
}

$conn = @mysqli_connect($dbHost, $dbUser, $dbPassword, $dbName);

if (!$conn) {
    error_log('TELA database connection failed.');
    die('A database connection error occurred. Please try again later.');
}

if (!mysqli_set_charset($conn, 'utf8mb4')) {
    error_log('TELA database character-set configuration failed.');
    mysqli_close($conn);
    die('A database connection error occurred. Please try again later.');
}
