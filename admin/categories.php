<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Category Management';
$activePage = 'admin_categories';

$categoryName = '';
$editCategoryId = 0;
$editCategoryName = '';
$errors = [];
$successMessage = '';
$categorySuccess = $_SESSION['category_success'] ?? '';
unset($_SESSION['category_success']);

if (is_string($categorySuccess)) {
    $successMessage = $categorySuccess;
}

function redirectAfterCategorySuccess($message)
{
    $_SESSION['category_success'] = $message;
    redirectTo(rtrim(APP_BASE_URL, '/') . '/admin/categories.php');
}

function insertCategoryAudit($conn, $activity, $description)
{
    $auditSql = 'INSERT INTO audit_logs (user_id, activity, description, ip_address) VALUES (?, ?, ?, ?)';
    $auditStmt = mysqli_prepare($conn, $auditSql);

    if ($auditStmt === false) {
        return false;
    }

    $adminUserId = (int) $_SESSION['user_id'];
    $ipAddressValue = $_SERVER['REMOTE_ADDR'] ?? '';
    $ipAddress = is_string($ipAddressValue) ? substr($ipAddressValue, 0, 45) : '';
    $bound = mysqli_stmt_bind_param($auditStmt, 'isss', $adminUserId, $activity, $description, $ipAddress);
    $inserted = $bound && mysqli_stmt_execute($auditStmt) && mysqli_stmt_affected_rows($auditStmt) === 1;
    mysqli_stmt_close($auditStmt);

    return $inserted;
}

function categoryNameExists($conn, $categoryName, $ignoreCategoryId = 0)
{
    $duplicateSql = 'SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(?) AND category_id <> ? LIMIT 1';
    $duplicateStmt = mysqli_prepare($conn, $duplicateSql);

    if ($duplicateStmt === false) {
        return null;
    }

    if (
        !mysqli_stmt_bind_param($duplicateStmt, 'si', $categoryName, $ignoreCategoryId) ||
        !mysqli_stmt_execute($duplicateStmt)
    ) {
        mysqli_stmt_close($duplicateStmt);
        return null;
    }

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
    if (!verifyCsrfToken()) {
        $errors[] = 'The category request could not be verified. Please try again.';
    } else {
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';

        if ($action === 'add') {
            $categoryName = cleanInput($_POST['category_name'] ?? '');
            $errors = validateCategoryName($categoryName);
            $duplicateExists = empty($errors) ? categoryNameExists($conn, $categoryName) : false;

            if ($duplicateExists === null) {
                $errors[] = 'Category could not be checked right now.';
            } elseif ($duplicateExists) {
                $errors[] = 'Category name already exists.';
            }

            if (empty($errors)) {
                $transactionStarted = mysqli_begin_transaction($conn);
                $mutationSucceeded = false;
                $duplicateInsert = false;

                if ($transactionStarted) {
                    $insertSql = 'INSERT INTO categories (category_name) VALUES (?)';
                    $insertStmt = mysqli_prepare($conn, $insertSql);

                    if ($insertStmt !== false && mysqli_stmt_bind_param($insertStmt, 's', $categoryName)) {
                        $inserted = mysqli_stmt_execute($insertStmt) && mysqli_stmt_affected_rows($insertStmt) === 1;
                        $duplicateInsert = !$inserted && mysqli_errno($conn) === 1062;

                        if ($inserted) {
                            $newCategoryId = mysqli_insert_id($conn);
                            $auditDescription = 'Added category ID ' . $newCategoryId . ': ' . $categoryName . '.';
                            $mutationSucceeded = insertCategoryAudit($conn, 'Add Category', $auditDescription);
                        }
                    }

                    if ($insertStmt !== false) {
                        mysqli_stmt_close($insertStmt);
                    }

                    if ($mutationSucceeded && mysqli_commit($conn)) {
                        redirectAfterCategorySuccess('Category added successfully.');
                    }

                    mysqli_rollback($conn);
                }

                $errors[] = $duplicateInsert
                    ? 'Category name already exists.'
                    : 'Category could not be added right now.';
            }
        } elseif ($action === 'edit') {
            $editCategoryId = parsePositiveIntegerId($_POST['category_id'] ?? '');
            $editCategoryName = cleanInput($_POST['category_name'] ?? '');
            $errors = validateCategoryName($editCategoryName);

            if ($editCategoryId === false) {
                $errors[] = 'Invalid category selected.';
            }

            if (empty($errors)) {
                $transactionStarted = mysqli_begin_transaction($conn);
                $mutationSucceeded = false;

                if (!$transactionStarted) {
                    $errors[] = 'Category could not be updated right now.';
                } else {
                    $lookupSql = 'SELECT category_name FROM categories WHERE category_id = ? LIMIT 1 FOR UPDATE';
                    $lookupStmt = mysqli_prepare($conn, $lookupSql);
                    $categoryFound = false;
                    $oldCategoryName = '';

                    if (
                        $lookupStmt !== false &&
                        mysqli_stmt_bind_param($lookupStmt, 'i', $editCategoryId) &&
                        mysqli_stmt_execute($lookupStmt)
                    ) {
                        mysqli_stmt_bind_result($lookupStmt, $oldCategoryName);
                        $categoryFound = mysqli_stmt_fetch($lookupStmt);
                    }

                    if ($lookupStmt !== false) {
                        mysqli_stmt_close($lookupStmt);
                    }

                    if (!$categoryFound) {
                        mysqli_rollback($conn);
                        $errors[] = 'Category was not found.';
                    } elseif ($oldCategoryName === $editCategoryName) {
                        mysqli_rollback($conn);
                        $successMessage = 'No changes were made.';
                    } else {
                        $duplicateExists = categoryNameExists($conn, $editCategoryName, $editCategoryId);

                        if ($duplicateExists === null) {
                            mysqli_rollback($conn);
                            $errors[] = 'Category could not be checked right now.';
                        } elseif ($duplicateExists) {
                            mysqli_rollback($conn);
                            $errors[] = 'Category name already exists.';
                        } else {
                            $updateSql = 'UPDATE categories SET category_name = ? WHERE category_id = ?';
                            $updateStmt = mysqli_prepare($conn, $updateSql);

                            if (
                                $updateStmt !== false &&
                                mysqli_stmt_bind_param($updateStmt, 'si', $editCategoryName, $editCategoryId)
                            ) {
                                $updated = mysqli_stmt_execute($updateStmt) && mysqli_stmt_affected_rows($updateStmt) === 1;

                                if ($updated) {
                                    $auditDescription = 'Updated category ID ' . $editCategoryId . ' from ' .
                                        $oldCategoryName . ' to ' . $editCategoryName . '.';
                                    $mutationSucceeded = insertCategoryAudit($conn, 'Edit Category', $auditDescription);
                                }
                            }

                            if ($updateStmt !== false) {
                                mysqli_stmt_close($updateStmt);
                            }

                            if ($mutationSucceeded && mysqli_commit($conn)) {
                                redirectAfterCategorySuccess('Category updated successfully.');
                            }

                            mysqli_rollback($conn);
                            $errors[] = 'Category could not be updated right now.';
                        }
                    }
                }
            }
        } elseif ($action === 'delete') {
            $deleteCategoryId = parsePositiveIntegerId($_POST['category_id'] ?? '');

            if ($deleteCategoryId === false) {
                $errors[] = 'Invalid category selected.';
            } else {
                $transactionStarted = mysqli_begin_transaction($conn);
                $mutationSucceeded = false;
                $categoryBlocked = false;
                $categoryFound = false;
                $deleteCategoryName = '';

                if ($transactionStarted) {
                    $lookupSql = 'SELECT category_name FROM categories WHERE category_id = ? LIMIT 1 FOR UPDATE';
                    $lookupStmt = mysqli_prepare($conn, $lookupSql);

                    if (
                        $lookupStmt !== false &&
                        mysqli_stmt_bind_param($lookupStmt, 'i', $deleteCategoryId) &&
                        mysqli_stmt_execute($lookupStmt)
                    ) {
                        mysqli_stmt_bind_result($lookupStmt, $deleteCategoryName);
                        $categoryFound = mysqli_stmt_fetch($lookupStmt);
                    }

                    if ($lookupStmt !== false) {
                        mysqli_stmt_close($lookupStmt);
                    }

                    if ($categoryFound) {
                        $productCheckSql = 'SELECT product_id FROM products WHERE category_id = ? LIMIT 1';
                        $productCheckStmt = mysqli_prepare($conn, $productCheckSql);
                        $referencesChecked = false;

                        if (
                            $productCheckStmt !== false &&
                            mysqli_stmt_bind_param($productCheckStmt, 'i', $deleteCategoryId) &&
                            mysqli_stmt_execute($productCheckStmt)
                        ) {
                            mysqli_stmt_store_result($productCheckStmt);
                            $categoryBlocked = mysqli_stmt_num_rows($productCheckStmt) > 0;
                            $referencesChecked = true;
                        }

                        if ($productCheckStmt !== false) {
                            mysqli_stmt_close($productCheckStmt);
                        }

                        if ($referencesChecked && !$categoryBlocked) {
                            $deleteSql = 'DELETE FROM categories WHERE category_id = ?';
                            $deleteStmt = mysqli_prepare($conn, $deleteSql);

                            if (
                                $deleteStmt !== false &&
                                mysqli_stmt_bind_param($deleteStmt, 'i', $deleteCategoryId)
                            ) {
                                $deleted = mysqli_stmt_execute($deleteStmt) && mysqli_stmt_affected_rows($deleteStmt) === 1;

                                if ($deleted) {
                                    $auditDescription = 'Deleted category ID ' . $deleteCategoryId . ': ' . $deleteCategoryName . '.';
                                    $mutationSucceeded = insertCategoryAudit($conn, 'Delete Category', $auditDescription);
                                }
                            }

                            if ($deleteStmt !== false) {
                                mysqli_stmt_close($deleteStmt);
                            }
                        }
                    }

                    if ($mutationSucceeded && mysqli_commit($conn)) {
                        redirectAfterCategorySuccess('Category deleted successfully.');
                    }

                    mysqli_rollback($conn);
                }

                if (!$categoryFound) {
                    $errors[] = 'Category was not found.';
                } elseif ($categoryBlocked) {
                    $errors[] = 'Category cannot be deleted because products are using it.';
                } else {
                    $errors[] = 'Category could not be deleted right now.';
                }
            }
        } else {
            $errors[] = 'Invalid category action.';
        }
    }
}

if (isset($_GET['edit'])) {
    $requestedEditId = parsePositiveIntegerId($_GET['edit']);

    if ($requestedEditId !== false) {
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
                        <?php echo csrfTokenField(); ?>
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
                                                    <?php echo csrfTokenField(); ?>
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
