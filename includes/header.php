<?php
require_once __DIR__ . '/../config/config.php';
initializeTelaSession();
require_once __DIR__ . '/functions.php';

if (!isset($pageTitle)) {
    $pageTitle = SITE_NAME;
}

if (!isset($activePage)) {
    $activePage = '';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo escapeOutput($pageTitle); ?> | <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
</head>
<body>
<a class="visually-hidden-focusable skip-link" href="#main-content">Skip to main content</a>
<?php include __DIR__ . '/navbar.php'; ?>
<main id="main-content" class="flex-fill py-4" tabindex="-1">
