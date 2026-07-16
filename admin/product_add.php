<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Add Product';
$activePage = 'admin_products';
$errors = [];
$successMessage = '';
$categories = [];

$categoryId = 0;
$productName = '';
$description = '';
$price = '';
$stock = '';
$status = 'Active';

function logProductAction($conn, $activity, $description)
{
    $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt !== false) {
        $adminUserId = $_SESSION['user_id'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        mysqli_stmt_bind_param($auditStmt, 'isss', $adminUserId, $activity, $description, $ipAddress);
        mysqli_stmt_execute($auditStmt);
        mysqli_stmt_close($auditStmt);
    }
}

function loadCategories($conn)
{
    $categoryRows = [];
    $categorySql = 'SELECT category_id, category_name FROM categories ORDER BY category_name ASC';
    $categoryStmt = mysqli_prepare($conn, $categorySql);

    if ($categoryStmt === false) {
        return $categoryRows;
    }

    mysqli_stmt_execute($categoryStmt);
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
    $categorySql = 'SELECT category_id FROM categories WHERE category_id = ? LIMIT 1';
    $categoryStmt = mysqli_prepare($conn, $categorySql);

    if ($categoryStmt === false) {
        return false;
    }

    mysqli_stmt_bind_param($categoryStmt, 'i', $categoryId);
    mysqli_stmt_execute($categoryStmt);
    mysqli_stmt_store_result($categoryStmt);
    $exists = mysqli_stmt_num_rows($categoryStmt) > 0;
    mysqli_stmt_close($categoryStmt);

    return $exists;
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
function validateProductImage($imageFile, $uploadDirectory)
{
    $result = [
        'errors' => [],
        'relative_path' => '',
        'full_path' => ''
    ];

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxFileSize = 2 * 1024 * 1024;

    if (!isset($imageFile) || !isset($imageFile['error']) || $imageFile['error'] === UPLOAD_ERR_NO_FILE) {
        $result['errors'][] = 'Product image is required.';
        return $result;
    }

    if ($imageFile['error'] !== UPLOAD_ERR_OK) {
        $result['errors'][] = 'Product image could not be uploaded.';
        return $result;
    }

    if (!isset($imageFile['size']) || (int) $imageFile['size'] <= 0) {
        $result['errors'][] = 'Product image is invalid.';
        return $result;
    }

    if ((int) $imageFile['size'] > $maxFileSize) {
        $result['errors'][] = 'Product image must be 2 MB or smaller.';
        return $result;
    }

    $originalName = $imageFile['name'] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        $result['errors'][] = 'Product image must be JPG, JPEG, PNG, or WEBP.';
        return $result;
    }

    if (!isset($imageFile['tmp_name']) || !is_uploaded_file($imageFile['tmp_name'])) {
        $result['errors'][] = 'Product image is invalid.';
        return $result;
    }

    $imageInfo = getimagesize($imageFile['tmp_name']);

    if ($imageInfo === false || !isset($imageInfo['mime'])) {
        $result['errors'][] = 'Uploaded file must be a valid image.';
        return $result;
    }

    $mimeType = $imageInfo['mime'];

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        $result['errors'][] = 'Product image type is not allowed.';
        return $result;
    }

    if (
        ($mimeType === 'image/jpeg' && !in_array($extension, ['jpg', 'jpeg'], true)) ||
        ($mimeType === 'image/png' && $extension !== 'png') ||
        ($mimeType === 'image/webp' && $extension !== 'webp')
    ) {
        $result['errors'][] = 'Product image extension does not match the image type.';
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

$categories = loadCategories($conn);
$postExceedsServerLimit = $_SERVER['REQUEST_METHOD'] === 'POST' && isPostOverServerLimit();

if (empty($categories) && !$postExceedsServerLimit) {
    $errors[] = 'No categories are available. Add the Hoodies category first.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($postExceedsServerLimit) {
        $errors = ['The uploaded file exceeds the server post limit. Please select a smaller image.'];
    } else {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $productName = cleanInput($_POST['product_name'] ?? '');
        $description = cleanInput($_POST['description'] ?? '');
        $price = cleanInput($_POST['price'] ?? '');
        $stock = cleanInput($_POST['stock'] ?? '');
        $status = cleanInput($_POST['status'] ?? '');
        $allowedStatuses = ['Active', 'Inactive'];

        if ($categoryId <= 0 || !categoryExists($conn, $categoryId)) {
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
        $imageValidation = validateProductImage($_FILES['image'] ?? null, $uploadDirectory);

        if (!empty($imageValidation['errors'])) {
            $errors = array_merge($errors, $imageValidation['errors']);
        }

        if (empty($errors)) {
            $imageMoved = move_uploaded_file($_FILES['image']['tmp_name'], $imageValidation['full_path']);

            if (!$imageMoved) {
                $errors[] = 'Product image could not be saved.';
            } else {
                $priceValue = (float) $price;
                $stockValue = (int) $stock;
                $imagePath = $imageValidation['relative_path'];

                $insertSql = 'INSERT INTO products (category_id, product_name, description, price, stock, image_path, status) VALUES (?, ?, ?, ?, ?, ?, ?)';
                $insertStmt = mysqli_prepare($conn, $insertSql);

                if ($insertStmt === false) {
                    if (is_file($imageValidation['full_path'])) {
                        unlink($imageValidation['full_path']);
                    }

                    $errors[] = 'Product could not be added right now.';
                } else {
                    mysqli_stmt_bind_param($insertStmt, 'issdiss', $categoryId, $productName, $description, $priceValue, $stockValue, $imagePath, $status);

                    if (mysqli_stmt_execute($insertStmt)) {
                        logProductAction($conn, 'Add Product', 'Admin added product: ' . $productName);
                        $successMessage = 'Product added successfully.';
                        $categoryId = 0;
                        $productName = '';
                        $description = '';
                        $price = '';
                        $stock = '';
                        $status = 'Active';
                    } else {
                        if (is_file($imageValidation['full_path'])) {
                            unlink($imageValidation['full_path']);
                        }

                        $errors[] = 'Product could not be added right now.';
                    }

                    mysqli_stmt_close($insertStmt);
                }
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <p class="section-label mb-2">Admin Management</p>
                    <h1 class="h3 mb-2">Add Product</h1>
                    <p class="text-muted mb-0">Add a Hoodie product with a valid image, price, stock, and status.</p>
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

            <form method="post" action="product_add.php" enctype="multipart/form-data" novalidate>
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

                    <div class="col-12">
                        <label for="image" class="form-label">Product Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                        <div class="form-text">Accepted formats: JPG, JPEG, PNG, WEBP. Maximum size: 2 MB.</div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-dark">Add Product</button>
                    <a class="btn btn-outline-secondary" href="products.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>