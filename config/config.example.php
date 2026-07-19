<?php
// Copy this file to config.local.php and replace the placeholder values.
// Never commit config.local.php because it contains private secrets.

define('APP_ENV', 'development');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'tela_db');

define('BREVO_API_KEY', 'your-brevo-api-key-here');
define('BREVO_SENDER_NAME', 'TELA');
define('BREVO_SENDER_EMAIL', 'verified-sender@example.com');
define('APP_BASE_URL', 'http://localhost/TELA');
define('BASE_URL', '/TELA/');

// Production may instead provide TELA_APP_ENV, TELA_DB_HOST, TELA_DB_USER,
// TELA_DB_PASSWORD, TELA_DB_NAME, TELA_APP_BASE_URL, and TELA_BASE_URL
// through server environment variables.
// For https://tela.kcykae.dev use:
// TELA_APP_BASE_URL=https://tela.kcykae.dev
// TELA_BASE_URL=/
