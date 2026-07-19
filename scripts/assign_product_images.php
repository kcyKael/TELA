<?php
// Run from the command line only: php scripts/assign_product_images.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/../config/database.php';

$uploadDirectory = __DIR__ . '/../uploads/products/';
$maximumFileSize = 2 * 1024 * 1024;
$allowedImageTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp'
];
$imagePaths = [];
$validationErrors = [];

if (!is_dir($uploadDirectory) || !is_readable($uploadDirectory)) {
    fwrite(STDERR, "The product upload directory is missing or unreadable.\n");
    exit(1);
}

$uploadedFiles = scandir($uploadDirectory);

if ($uploadedFiles === false) {
    fwrite(STDERR, "The product upload directory could not be scanned.\n");
    exit(1);
}

foreach ($uploadedFiles as $fileName) {
    $fullPath = $uploadDirectory . $fileName;

    if (!is_file($fullPath)) {
        continue;
    }

    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!isset($allowedImageTypes[$extension])) {
        continue;
    }

    // Ignore existing product uploads and select only this supplied image set.
    if (!preg_match('/^hoodie- \([0-9]+\)\.(jpg|jpeg|png|webp)$/i', $fileName)) {
        continue;
    }

    $fileSize = filesize($fullPath);

    if ($fileSize === false || $fileSize <= 0 || $fileSize > $maximumFileSize) {
        $validationErrors[] = $fileName . ' has an invalid file size.';
        continue;
    }

    $imageDetails = @getimagesize($fullPath);
    $actualMimeType = is_array($imageDetails) && isset($imageDetails['mime'])
        ? strtolower((string) $imageDetails['mime'])
        : '';

    if ($imageDetails === false || $actualMimeType !== $allowedImageTypes[$extension]) {
        $validationErrors[] = $fileName . ' is not a valid supported image.';
        continue;
    }

    if (strpos($fileName, '..') !== false) {
        $validationErrors[] = $fileName . ' has an unsafe filename.';
        continue;
    }

    $imagePaths[] = PRODUCT_UPLOAD_PATH . $fileName;
}

if (!empty($validationErrors)) {
    fwrite(STDERR, "Image assignment stopped because validation failed:\n");

    foreach ($validationErrors as $validationError) {
        fwrite(STDERR, '- ' . $validationError . "\n");
    }

    exit(1);
}

$productSql = "
    SELECT p.product_id, p.product_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    WHERE c.category_name = ?
    ORDER BY p.product_id ASC
";
$productStmt = mysqli_prepare($conn, $productSql);

if ($productStmt === false) {
    fwrite(STDERR, "The Hoodie products could not be loaded.\n");
    exit(1);
}

$categoryName = PRODUCT_CATEGORY_NAME;
mysqli_stmt_bind_param($productStmt, 's', $categoryName);

if (!mysqli_stmt_execute($productStmt)) {
    mysqli_stmt_close($productStmt);
    fwrite(STDERR, "The Hoodie products could not be loaded.\n");
    exit(1);
}

mysqli_stmt_bind_result($productStmt, $productId, $productName);
$products = [];

while (mysqli_stmt_fetch($productStmt)) {
    $products[] = [
        'product_id' => (int) $productId,
        'product_name' => $productName
    ];
}

mysqli_stmt_close($productStmt);

$productCount = count($products);
$imageCount = count($imagePaths);

if ($productCount === 0) {
    fwrite(STDERR, "No Hoodie products were found.\n");
    exit(1);
}

if ($productCount !== $imageCount) {
    fwrite(
        STDERR,
        'Assignment stopped. Found ' . $productCount . ' Hoodie products and ' . $imageCount . " valid images. The counts must match.\n"
    );
    exit(1);
}

shuffle($imagePaths);

if (!mysqli_begin_transaction($conn)) {
    fwrite(STDERR, "The image assignment transaction could not be started.\n");
    exit(1);
}

$updateStmt = mysqli_prepare($conn, 'UPDATE products SET image_path = ? WHERE product_id = ?');

if ($updateStmt === false) {
    mysqli_rollback($conn);
    fwrite(STDERR, "The image update could not be prepared.\n");
    exit(1);
}

$assignmentSucceeded = true;
$assignments = [];

foreach ($products as $index => $product) {
    $imagePath = $imagePaths[$index];
    $currentProductId = $product['product_id'];
    mysqli_stmt_bind_param($updateStmt, 'si', $imagePath, $currentProductId);

    if (!mysqli_stmt_execute($updateStmt)) {
        $assignmentSucceeded = false;
        break;
    }

    $assignments[] = [
        'product_name' => $product['product_name'],
        'image_path' => $imagePath
    ];
}

mysqli_stmt_close($updateStmt);

if (!$assignmentSucceeded || !mysqli_commit($conn)) {
    mysqli_rollback($conn);
    fwrite(STDERR, "The assignment failed. No product image paths were changed.\n");
    exit(1);
}

echo "Product images assigned successfully:\n";

foreach ($assignments as $assignment) {
    echo '- ' . $assignment['product_name'] . ' -> ' . $assignment['image_path'] . "\n";
}

echo 'Assigned ' . count($assignments) . ' images to ' . count($products) . " Hoodie products.\n";
