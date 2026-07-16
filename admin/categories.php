<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Category Management';
$activePage = 'admin_categories';

$categoryName = '';
$editCategoryId = 0;
$editCategoryName = '';
$errors = [];
$successMessage = '';

function logCategoryAction($conn, $activity, $description)
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

function categoryNameExists($conn, $categoryName, $ignoreCategoryId = 0)
{
    $duplicateSql = 'SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(?) AND category_id <> ? LIMIT 1';
    $duplicateStmt = mysqli_prepare($conn, $duplicateSql);

    if ($duplicateStmt === false) {
        return true;
    }

    mysqli_stmt_bind_param($duplicateStmt, 'si', $categoryName, $ignoreCategoryId);
    mysqli_stmt_execute($duplicateStmt);
    mysqli_stmt_store_result($duplicateStmt);
    $exists = mysqli_stmt_num_rows($duplicateStmt) > 0;
    mysqli_stmt_close($duplicateStmt);

    return $exists;
}

function validateCategoryName($categoryName)
{
    $validationErrors = [];

    if ($categoryName === '') {
        $validationErrors[] = 'Category name is required.';
    } elseif (strlen($categoryName) > 100) {
        $validationErrors[] = 'Category name must be 100 characters or less.';
    }

    return $validationErrors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $categoryName = cleanInput($_POST['category_name'] ?? '');
        $errors = validateCategoryName($categoryName);

        if (empty($errors) && categoryNameExists($conn, $categoryName)) {
            $errors[] = 'Category name already exists.';
        }

        if (empty($errors)) {
            $insertSql = 'INSERT INTO categories (category_name) VALUES (?)';
            $insertStmt = mysqli_prepare($conn, $insertSql);

            if ($insertStmt === false) {
                $errors[] = 'Category could not be added right now.';
            } else {
                mysqli_stmt_bind_param($insertStmt, 's', $categoryName);

                if (mysqli_stmt_execute($insertStmt)) {
                    logCategoryAction($conn, 'Add Category', 'Admin added category: ' . $categoryName);
                    $successMessage = 'Category added successfully.';
                    $categoryName = '';
                } else {
                    $errors[] = 'Category could not be added right now.';
                }

                mysqli_stmt_close($insertStmt);
            }
        }
    } elseif ($action === 'edit') {
        $editCategoryId = (int) ($_POST['category_id'] ?? 0);
        $editCategoryName = cleanInput($_POST['category_name'] ?? '');
        $errors = validateCategoryName($editCategoryName);

        if ($editCategoryId <= 0) {
            $errors[] = 'Invalid category selected.';
        }

        if (empty($errors)) {
            $lookupSql = 'SELECT category_name FROM categories WHERE category_id = ? LIMIT 1';
            $lookupStmt = mysqli_prepare($conn, $lookupSql);

            if ($lookupStmt === false) {
                $errors[] = 'Category could not be checked right now.';
            } else {
                mysqli_stmt_bind_param($lookupStmt, 'i', $editCategoryId);
                mysqli_stmt_execute($lookupStmt);
                mysqli_stmt_bind_result($lookupStmt, $oldCategoryName);
                $categoryFound = mysqli_stmt_fetch($lookupStmt);
                mysqli_stmt_close($lookupStmt);

                if (!$categoryFound) {
                    $errors[] = 'Category was not found.';
                } elseif (categoryNameExists($conn, $editCategoryName, $editCategoryId)) {
                    $errors[] = 'Category name already exists.';
                } else {
                    $updateSql = 'UPDATE categories SET category_name = ? WHERE category_id = ?';
                    $updateStmt = mysqli_prepare($conn, $updateSql);

                    if ($updateStmt === false) {
                        $errors[] = 'Category could not be updated right now.';
                    } else {
                        mysqli_stmt_bind_param($updateStmt, 'si', $editCategoryName, $editCategoryId);

                        if (mysqli_stmt_execute($updateStmt)) {
                            logCategoryAction($conn, 'Edit Category', 'Admin edited category from ' . $oldCategoryName . ' to ' . $editCategoryName);
                            $successMessage = 'Category updated successfully.';
                            $editCategoryId = 0;
                            $editCategoryName = '';
                        } else {
                            $errors[] = 'Category could not be updated right now.';
                        }

                        mysqli_stmt_close($updateStmt);
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        $deleteCategoryId = (int) ($_POST['category_id'] ?? 0);

        if ($deleteCategoryId <= 0) {
            $errors[] = 'Invalid category selected.';
        } else {
            $lookupSql = 'SELECT category_name FROM categories WHERE category_id = ? LIMIT 1';
            $lookupStmt = mysqli_prepare($conn, $lookupSql);

            if ($lookupStmt === false) {
                $errors[] = 'Category could not be checked right now.';
            } else {
                mysqli_stmt_bind_param($lookupStmt, 'i', $deleteCategoryId);
                mysqli_stmt_execute($lookupStmt);
                mysqli_stmt_bind_result($lookupStmt, $deleteCategoryName);
                $categoryFound = mysqli_stmt_fetch($lookupStmt);
                mysqli_stmt_close($lookupStmt);

                if (!$categoryFound) {
                    $errors[] = 'Category was not found.';
                } else {
                    $productCheckSql = 'SELECT product_id FROM products WHERE category_id = ? LIMIT 1';
                    $productCheckStmt = mysqli_prepare($conn, $productCheckSql);

                    if ($productCheckStmt === false) {
                        $errors[] = 'Category references could not be checked right now.';
                    } else {
                        mysqli_stmt_bind_param($productCheckStmt, 'i', $deleteCategoryId);
                        mysqli_stmt_execute($productCheckStmt);
                        mysqli_stmt_store_result($productCheckStmt);
                        $hasProducts = mysqli_stmt_num_rows($productCheckStmt) > 0;
                        mysqli_stmt_close($productCheckStmt);

                        if ($hasProducts) {
                            $errors[] = 'Category cannot be deleted because products are using it.';
                        } else {
                            $deleteSql = 'DELETE FROM categories WHERE category_id = ?';
                            $deleteStmt = mysqli_prepare($conn, $deleteSql);

                            if ($deleteStmt === false) {
                                $errors[] = 'Category could not be deleted right now.';
                            } else {
                                mysqli_stmt_bind_param($deleteStmt, 'i', $deleteCategoryId);

                                if (mysqli_stmt_execute($deleteStmt)) {
                                    logCategoryAction($conn, 'Delete Category', 'Admin deleted category: ' . $deleteCategoryName);
                                    $successMessage = 'Category deleted successfully.';
                                } else {
                                    $errors[] = 'Category could not be deleted right now.';
                                }

                                mysqli_stmt_close($deleteStmt);
                            }
                        }
                    }
                }
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $requestedEditId = (int) $_GET['edit'];

    if ($requestedEditId > 0) {
        $editSql = 'SELECT category_id, category_name FROM categories WHERE category_id = ? LIMIT 1';
        $editStmt = mysqli_prepare($conn, $editSql);

        if ($editStmt !== false) {
            mysqli_stmt_bind_param($editStmt, 'i', $requestedEditId);
            mysqli_stmt_execute($editStmt);
            mysqli_stmt_bind_result($editStmt, $foundCategoryId, $foundCategoryName);

            if (mysqli_stmt_fetch($editStmt)) {
                $editCategoryId = $foundCategoryId;
                $editCategoryName = $foundCategoryName;
            }

            mysqli_stmt_close($editStmt);
        }
    }
}

$categories = [];
$categorySql = 'SELECT category_id, category_name, created_at, updated_at FROM categories ORDER BY category_name ASC';
$categoryStmt = mysqli_prepare($conn, $categorySql);

if ($categoryStmt === false) {
    $errors[] = 'Categories could not be loaded right now.';
} else {
    mysqli_stmt_execute($categoryStmt);
    mysqli_stmt_bind_result($categoryStmt, $categoryId, $listCategoryName, $createdAt, $updatedAt);

    while (mysqli_stmt_fetch($categoryStmt)) {
        $categories[] = [
            'category_id' => $categoryId,
            'category_name' => $listCategoryName,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt
        ];
    }

    mysqli_stmt_close($categoryStmt);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <p class="section-label mb-2">Admin Management</p>
            <h1 class="h3 mb-3">Category Management</h1>
            <p class="text-muted">Manage product categories for the TELA Hoodie store.</p>

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
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-4">
                    <h2 class="h5 mb-3"><?php echo $editCategoryId > 0 ? 'Edit Category' : 'Add Category'; ?></h2>
                    <form method="post" action="categories.php" novalidate>
                        <input type="hidden" name="action" value="<?php echo $editCategoryId > 0 ? 'edit' : 'add'; ?>">
                        <?php if ($editCategoryId > 0): ?>
                            <input type="hidden" name="category_id" value="<?php echo (int) $editCategoryId; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" value="<?php echo escapeOutput($editCategoryId > 0 ? $editCategoryName : $categoryName); ?>" maxlength="100" required>
                        </div>

                        <button type="submit" class="btn btn-dark"><?php echo $editCategoryId > 0 ? 'Update Category' : 'Add Category'; ?></button>
                        <?php if ($editCategoryId > 0): ?>
                            <a class="btn btn-outline-secondary" href="categories.php">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="col-lg-8">
                    <h2 class="h5 mb-3">Categories</h2>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Category Name</th>
                                    <th>Created Date</th>
                                    <th>Updated Date</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No categories found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?php echo escapeOutput($category['category_name']); ?></td>
                                            <td><?php echo escapeOutput($category['created_at']); ?></td>
                                            <td><?php echo $category['updated_at'] !== null ? escapeOutput($category['updated_at']) : 'Not updated'; ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-dark" href="categories.php?edit=<?php echo (int) $category['category_id']; ?>">Edit</a>
                                                <form method="post" action="categories.php" class="d-inline" onsubmit="return confirm('Delete this category?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="category_id" value="<?php echo (int) $category['category_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
