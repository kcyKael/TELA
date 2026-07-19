<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Edit Product';
$activePage = 'admin_products';
$errors = [];
$successMessage = '';
$categories = [];
$productFound = false;
$product = null;

$productId = parsePositiveIntegerId($_GET['id'] ?? '');
$categoryId = 0;
$productName = '';
$description = '';
$price = '';
$stock = '';
$status = 'Active';
$currentImagePath = '';

function insertProductEditAudit($conn, $productId, $changedFields)
{
    $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt === false) {
        return false;
    }

    $adminUserId = (int) $_SESSION['user_id'];
    $activity = 'Update Product';
    $description = 'Updated product ID ' . $productId . '. Changed fields: ' . implode(', ', $changedFields) . '.';
    $ipAddressValue = $_SERVER['REMOTE_ADDR'] ?? '';
    $ipAddress = is_string($ipAddressValue) ? substr($ipAddressValue, 0, 45) : '';
    $bound = mysqli_stmt_bind_param($auditStmt, 'isss', $adminUserId, $activity, $description, $ipAddress);
    $inserted = $bound && mysqli_stmt_execute($auditStmt) && mysqli_stmt_affected_rows($auditStmt) === 1;
    mysqli_stmt_close($auditStmt);

    return $inserted;
}

function loadCategories($conn)
{
    $categoryRows = [];
    $categorySql = 'SELECT category_id, category_name FROM categories WHERE category_name = ? ORDER BY category_name ASC';
    $categoryStmt = mysqli_prepare($conn, $categorySql);

    if ($categoryStmt === false) {
        return $categoryRows;
    }

    $requiredCategoryName = PRODUCT_CATEGORY_NAME;

    if (
        !mysqli_stmt_bind_param($categoryStmt, 's', $requiredCategoryName) ||
        !mysqli_stmt_execute($categoryStmt)
    ) {
        mysqli_stmt_close($categoryStmt);
        return $categoryRows;
    }

    mysqli_stmt_bind_result($categoryStmt, $foundCategoryId, $foundCategoryName);

    while (mysqli_stmt_fetch($categoryStmt)) {
        $categoryRows[] = [
            'category_id' => (int) $foundCategoryId,
            'category_name' => $foundCategoryName
        ];
    }

    mysqli_stmt_close($categoryStmt);
    return $categoryRows;
}

function categoryExists($conn, $categoryId)
{
    $categorySql = 'SELECT category_id FROM categories WHERE category_id = ? AND category_name = ? LIMIT 1';
    $categoryStmt = mysqli_prepare($conn, $categorySql);

    if ($categoryStmt === false) {
        return false;
    }

    $requiredCategoryName = PRODUCT_CATEGORY_NAME;

    if (
        !mysqli_stmt_bind_param($categoryStmt, 'is', $categoryId, $requiredCategoryName) ||
        !mysqli_stmt_execute($categoryStmt)
    ) {
        mysqli_stmt_close($categoryStmt);
        return false;
    }

    mysqli_stmt_store_result($categoryStmt);
    $exists = mysqli_stmt_num_rows($categoryStmt) > 0;
    mysqli_stmt_close($categoryStmt);

    return $exists;
}

function loadProduct($conn, $productId, $forUpdate = false)
{
    $productSql = 'SELECT product_id, category_id, product_name, description, price, stock, image_path, status FROM products WHERE product_id = ? LIMIT 1';

    if ($forUpdate) {
        $productSql .= ' FOR UPDATE';
    }

    $productStmt = mysqli_prepare($conn, $productSql);

    if ($productStmt === false) {
        return null;
    }

    if (
        !mysqli_stmt_bind_param($productStmt, 'i', $productId) ||
        !mysqli_stmt_execute($productStmt)
    ) {
        mysqli_stmt_close($productStmt);
        return null;
    }

    mysqli_stmt_bind_result($productStmt, $foundProductId, $foundCategoryId, $foundProductName, $foundDescription, $foundPrice, $foundStock, $foundImagePath, $foundStatus);

    $productRow = null;

    if (mysqli_stmt_fetch($productStmt)) {
        $productRow = [
            'product_id' => (int) $foundProductId,
            'category_id' => (int) $foundCategoryId,
            'product_name' => $foundProductName,
            'description' => $foundDescription,
            'price' => $foundPrice,
            'stock' => (int) $foundStock,
            'image_path' => $foundImagePath,
            'status' => $foundStatus
        ];
    }

    mysqli_stmt_close($productStmt);
    return $productRow;
}

function convertPhpSizeToBytes($sizeValue)
{
    $sizeValue = trim((string) $sizeValue);

    if ($sizeValue === '') {
        return 0;
    }

    $lastCharacter = strtolower($sizeValue[strlen($sizeValue) - 1]);
    $bytes = (float) $sizeValue;

    if ($lastCharacter === 'g') {
        $bytes *= 1024 * 1024 * 1024;
    } elseif ($lastCharacter === 'm') {
        $bytes *= 1024 * 1024;
    } elseif ($lastCharacter === 'k') {
        $bytes *= 1024;
    }

    return (int) $bytes;
}

function isPostOverServerLimit()
{
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxBytes = convertPhpSizeToBytes(ini_get('post_max_size'));

    return $postMaxBytes > 0 && $contentLength > $postMaxBytes;
}

function validateReplacementImage($imageFile, $uploadDirectory)
{
    $result = [
        'errors' => [],
        'relative_path' => '',
        'full_path' => '',
        'has_upload' => false
    ];

    if (!isset($imageFile) || !isset($imageFile['error']) || $imageFile['error'] === UPLOAD_ERR_NO_FILE) {
        return $result;
    }

    $result['has_upload'] = true;
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxFileSize = 2 * 1024 * 1024;

    if ($imageFile['error'] !== UPLOAD_ERR_OK) {
        $result['errors'][] = 'Replacement image could not be uploaded.';
        return $result;
    }

    if (!isset($imageFile['size']) || (int) $imageFile['size'] <= 0) {
        $result['errors'][] = 'Replacement image is invalid.';
        return $result;
    }

    if ((int) $imageFile['size'] > $maxFileSize) {
        $result['errors'][] = 'Replacement image must be 2 MB or smaller.';
        return $result;
    }

    $originalName = $imageFile['name'] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (hasBlockedUploadExtension($originalName)) {
        $result['errors'][] = 'Replacement image filename contains a blocked file type.';
        return $result;
    }

    if (!in_array($extension, $allowedExtensions, true)) {
        $result['errors'][] = 'Replacement image must be JPG, JPEG, PNG, or WEBP.';
        return $result;
    }

    if (!isset($imageFile['tmp_name']) || !is_uploaded_file($imageFile['tmp_name'])) {
        $result['errors'][] = 'Replacement image is invalid.';
        return $result;
    }

    $imageInfo = getimagesize($imageFile['tmp_name']);

    if ($imageInfo === false || !isset($imageInfo['mime'])) {
        $result['errors'][] = 'Replacement file must be a valid image.';
        return $result;
    }

    $mimeType = $imageInfo['mime'];

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        $result['errors'][] = 'Replacement image type is not allowed.';
        return $result;
    }

    if (
        ($mimeType === 'image/jpeg' && !in_array($extension, ['jpg', 'jpeg'], true)) ||
        ($mimeType === 'image/png' && $extension !== 'png') ||
        ($mimeType === 'image/webp' && $extension !== 'webp')
    ) {
        $result['errors'][] = 'Replacement image extension does not match the image type.';
        return $result;
    }

    if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {
        $result['errors'][] = 'Product image upload folder is not available.';
        return $result;
    }

    $storedExtension = $extension === 'jpeg' ? 'jpg' : $extension;
    $safeFileName = 'product_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $storedExtension;
    $result['relative_path'] = 'uploads/products/' . $safeFileName;
    $result['full_path'] = $uploadDirectory . DIRECTORY_SEPARATOR . $safeFileName;

    return $result;
}

function getSafeProductImageSource($imagePath)
{
    $imagePath = trim((string) $imagePath);

    if ($imagePath === '') {
        return '';
    }

    $cleanPath = str_replace('\\', '/', $imagePath);
    $cleanPath = ltrim($cleanPath, '/');

    if (strpos($cleanPath, 'uploads/products/') !== 0 || strpos($cleanPath, '..') !== false) {
        return '';
    }

    $fullPath = __DIR__ . '/../' . $cleanPath;

    if (!is_file($fullPath)) {
        return '';
    }

    return '../' . $cleanPath;
}

function getSafeProductImageFullPath($imagePath)
{
    $imagePath = trim((string) $imagePath);

    if ($imagePath === '') {
        return '';
    }

    $cleanPath = str_replace('\\', '/', $imagePath);
    $cleanPath = ltrim($cleanPath, '/');

    if (strpos($cleanPath, 'uploads/products/') !== 0 || strpos($cleanPath, '..') !== false) {
        return '';
    }

    if (!preg_match('/^uploads\/products\/product_[0-9]{8}_[0-9]{6}_[a-f0-9]{16}\.(?:jpg|png|webp)$/', $cleanPath)) {
        return '';
    }

    $uploadDirectory = realpath(__DIR__ . '/../uploads/products');
    $fullPath = realpath(__DIR__ . '/../' . $cleanPath);

    if ($uploadDirectory === false || $fullPath === false || !is_file($fullPath) || dirname($fullPath) !== $uploadDirectory) {
        return '';
    }

    return $fullPath;
}

if ($productId === false) {
    $errors[] = 'Invalid product selected.';
} else {
    $product = loadProduct($conn, $productId);

    if ($product === null) {
        $errors[] = 'Product was not found.';
    } else {
        $productFound = true;
        $categoryId = $product['category_id'];
        $productName = $product['product_name'];
        $description = $product['description'];
        $price = number_format((float) $product['price'], 2, '.', '');
        $stock = (string) $product['stock'];
        $status = $product['status'];
        $currentImagePath = $product['image_path'];
    }
}

$categories = loadCategories($conn);
$postExceedsServerLimit = $_SERVER['REQUEST_METHOD'] === 'POST' && isPostOverServerLimit();

if (empty($categories) && !$postExceedsServerLimit) {
    $errors[] = 'No categories are available. Add the Hoodies category first.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $productFound) {
    if ($postExceedsServerLimit) {
        $errors = ['The uploaded file exceeds the server post limit. Please select a smaller image.'];
    } elseif (!verifyCsrfToken()) {
        $errors = ['The product request could not be verified. Please try again.'];
    } else {
        $categoryId = parsePositiveIntegerId($_POST['category_id'] ?? '');
        $productName = cleanInput($_POST['product_name'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        $price = cleanInput($_POST['price'] ?? '');
        $stock = cleanInput($_POST['stock'] ?? '');
        $status = cleanInput($_POST['status'] ?? '');
        $allowedStatuses = ['Active', 'Inactive'];

        if ($categoryId === false || !categoryExists($conn, $categoryId)) {
            $errors[] = 'Please select a valid category.';
        }

        if ($productName === '') {
            $errors[] = 'Product name is required.';
        } elseif (strlen($productName) > 150) {
            $errors[] = 'Product name must be 150 characters or less.';
        }

        if ($price === '' || !is_numeric($price) || (float) $price <= 0) {
            $errors[] = 'Price must be greater than zero.';
        }

        if ($stock === '' || !ctype_digit($stock)) {
            $errors[] = 'Stock must be a whole number.';
        } elseif ((int) $stock < 0) {
            $errors[] = 'Stock cannot be negative.';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = 'Please select a valid product status.';
        }

        $uploadDirectory = __DIR__ . '/../uploads/products';
        $imageValidation = validateReplacementImage($_FILES['image'] ?? null, $uploadDirectory);

        if (!empty($imageValidation['errors'])) {
            $errors = array_merge($errors, $imageValidation['errors']);
        }

        if (empty($errors)) {
            $priceValue = (float) $price;
            $stockValue = (int) $stock;
            $newImageFullPath = '';
            $newImageMoved = false;

            if ($imageValidation['has_upload']) {
                $newImageFullPath = $imageValidation['full_path'];
                $newImageMoved = move_uploaded_file($_FILES['image']['tmp_name'], $newImageFullPath);

                if (!$newImageMoved) {
                    $errors[] = 'Replacement image could not be saved.';
                }
            }

            if (empty($errors)) {
                $transactionStarted = mysqli_begin_transaction($conn);
                $mutationSucceeded = false;
                $committed = false;
                $oldImagePath = '';

                if ($transactionStarted) {
                    $lockedProduct = loadProduct($conn, $productId, true);

                    if ($lockedProduct !== null && categoryExists($conn, $categoryId)) {
                        $changedFields = [];
                        $oldImagePath = $lockedProduct['image_path'];
                        $newImagePath = $newImageMoved ? $imageValidation['relative_path'] : $oldImagePath;

                        if ((int) $lockedProduct['category_id'] !== $categoryId) {
                            $changedFields[] = 'category';
                        }

                        if ((string) $lockedProduct['product_name'] !== $productName) {
                            $changedFields[] = 'name';
                        }

                        if ((string) $lockedProduct['description'] !== $description) {
                            $changedFields[] = 'description';
                        }

                        if (round((float) $lockedProduct['price'], 2) !== round($priceValue, 2)) {
                            $changedFields[] = 'price';
                        }

                        if ((int) $lockedProduct['stock'] !== $stockValue) {
                            $changedFields[] = 'stock';
                        }

                        if ($lockedProduct['status'] !== $status) {
                            $changedFields[] = 'status';
                        }

                        if ($newImageMoved) {
                            $changedFields[] = 'image';
                        }

                        if (empty($changedFields)) {
                            mysqli_rollback($conn);
                            $successMessage = 'No changes were made.';
                        } else {
                            $updateSql = 'UPDATE products SET category_id = ?, product_name = ?, description = ?, price = ?, stock = ?, image_path = ?, status = ? WHERE product_id = ?';
                            $updateStmt = mysqli_prepare($conn, $updateSql);

                            if (
                                $updateStmt !== false &&
                                mysqli_stmt_bind_param($updateStmt, 'issdissi', $categoryId, $productName, $description, $priceValue, $stockValue, $newImagePath, $status, $productId)
                            ) {
                                $updated = mysqli_stmt_execute($updateStmt) && mysqli_stmt_affected_rows($updateStmt) === 1;
                                $mutationSucceeded = $updated && insertProductEditAudit($conn, $productId, $changedFields);
                            }

                            if ($updateStmt !== false) {
                                mysqli_stmt_close($updateStmt);
                            }

                            if ($mutationSucceeded) {
                                $committed = mysqli_commit($conn);
                            }

                            if ($committed) {
                                if ($newImageMoved) {
                                    $oldImageFullPath = getSafeProductImageFullPath($oldImagePath);

                                    if (
                                        $oldImageFullPath !== '' &&
                                        $oldImageFullPath !== $newImageFullPath &&
                                        !unlink($oldImageFullPath)
                                    ) {
                                        error_log('TELA product upload: replaced image cleanup failed.');
                                    }
                                }

                                redirectTo(rtrim(APP_BASE_URL, '/') . '/admin/products.php?message=product_updated');
                            }
                        }
                    }

                    if (!$committed && $successMessage === '') {
                        mysqli_rollback($conn);
                    }
                }

                if (!$committed && $successMessage === '') {
                    if ($newImageMoved && is_file($newImageFullPath) && !unlink($newImageFullPath)) {
                        error_log('TELA product upload: rollback file cleanup failed.');
                    }

                    $errors[] = 'Product could not be updated right now.';
                }
            }
        }
    }
}

$currentImageSource = getSafeProductImageSource($currentImagePath);

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <p class="section-label mb-2">Admin Management</p>
                    <h1 class="h3 mb-2">Edit Product</h1>
                    <p class="text-muted mb-0">Update Hoodie product details, stock, price, image, and status.</p>
                </div>
                <a class="btn btn-outline-secondary" href="products.php">Back to Products</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escapeOutput($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo escapeOutput($successMessage); ?>
                    <a href="products.php" class="alert-link">View products</a>.
                </div>
            <?php endif; ?>

            <?php if ($productFound): ?>
                <form method="post" action="product_edit.php?id=<?php echo (int) $productId; ?>" enctype="multipart/form-data" novalidate>
                    <?php echo csrfTokenField(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo (int) $category['category_id']; ?>" <?php echo $categoryId === (int) $category['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo escapeOutput($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="product_name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" value="<?php echo escapeOutput($productName); ?>" maxlength="150" required>
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo escapeOutput($description); ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label for="price" class="form-label">Price</label>
                            <input type="number" class="form-control" id="price" name="price" value="<?php echo escapeOutput($price); ?>" min="0.01" step="0.01" required>
                        </div>

                        <div class="col-md-4">
                            <label for="stock" class="form-label">Stock</label>
                            <input type="number" class="form-control" id="stock" name="stock" value="<?php echo escapeOutput($stock); ?>" min="0" step="1" required>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Current Image</label>
                            <?php if ($currentImageSource !== ''): ?>
                                <img src="<?php echo escapeOutput($currentImageSource); ?>" alt="<?php echo escapeOutput($productName); ?>" class="img-thumbnail d-block" style="width: 140px; height: 140px; object-fit: cover;">
                            <?php else: ?>
                                <div class="border rounded bg-light text-muted d-flex align-items-center justify-content-center text-center" style="width: 140px; height: 140px;">No Image</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-8">
                            <label for="image" class="form-label">Replacement Image</label>
                            <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <div class="form-text">Optional. Leave blank to keep the current image. Accepted formats: JPG, JPEG, PNG, WEBP. Maximum size: 2 MB.</div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-dark">Update Product</button>
                        <a class="btn btn-outline-secondary" href="products.php">Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <a class="btn btn-outline-secondary" href="products.php">Back to Products</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>