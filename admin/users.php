<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'User Management';
$activePage = 'admin_users';
$users = [];
$usersLoadError = '';

$usersSql = '
    SELECT
        users.user_id,
        users.full_name,
        users.email,
        users.contact_number,
        users.role,
        users.is_verified,
        users.created_at
    FROM users
    ORDER BY
        CASE WHEN users.role = \'admin\' THEN 0 ELSE 1 END ASC,
        users.created_at DESC,
        users.user_id DESC
';
$usersStmt = mysqli_prepare($conn, $usersSql);

if ($usersStmt === false) {
    $usersLoadError = 'User accounts could not be loaded right now.';
} elseif (!mysqli_stmt_execute($usersStmt)) {
    $usersLoadError = 'User accounts could not be loaded right now.';
    mysqli_stmt_close($usersStmt);
} elseif (!mysqli_stmt_bind_result(
    $usersStmt,
    $userId,
    $fullName,
    $email,
    $contactNumber,
    $role,
    $isVerified,
    $createdAt
)) {
    $usersLoadError = 'User accounts could not be loaded right now.';
    mysqli_stmt_close($usersStmt);
} else {
    while (($userFetchResult = mysqli_stmt_fetch($usersStmt)) === true) {
        $users[] = [
            'user_id' => (int) $userId,
            'full_name' => $fullName,
            'email' => $email,
            'contact_number' => $contactNumber,
            'role' => $role,
            'is_verified' => (int) $isVerified,
            'created_at' => $createdAt
        ];
    }

    mysqli_stmt_close($usersStmt);

    if ($userFetchResult === false) {
        $users = [];
        $usersLoadError = 'User accounts could not be loaded right now.';
    }
}

$roleBadgeClasses = [
    'admin' => 'text-bg-dark',
    'buyer' => 'text-bg-primary'
];

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <p class="section-label mb-2">Admin Management</p>
                    <h1 class="h3 mb-0">User Management</h1>
                </div>
                <a class="btn btn-dark" href="<?php echo BASE_URL; ?>admin/user_add.php">Add Admin</a>
            </div>

            <?php if ($usersLoadError !== ''): ?>
                <div class="alert alert-warning mb-0" role="alert">
                    <?php echo escapeOutput($usersLoadError); ?>
                </div>
            <?php elseif (empty($users)): ?>
                <div class="alert alert-info mb-0" role="status">
                    No user accounts are available.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 user-management-table">
                        <thead>
                            <tr>
                                <th scope="col">Full Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Verification</th>
                                <th scope="col">Contact Number</th>
                                <th scope="col">Created Date</th>
                                <th scope="col" class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php
                                $hasKnownRole = isset($roleBadgeClasses[$user['role']]);
                                $roleBadgeClass = $hasKnownRole ? $roleBadgeClasses[$user['role']] : 'text-bg-secondary';
                                $roleLabel = $hasKnownRole ? ucfirst($user['role']) : 'Role unavailable';
                                $verificationLabel = $user['is_verified'] === 1 ? 'Verified' : 'Unverified';
                                $verificationBadgeClass = $user['is_verified'] === 1 ? 'text-bg-success' : 'text-bg-secondary';
                                ?>
                                <tr>
                                    <td><?php echo escapeOutput($user['full_name']); ?></td>
                                    <td><?php echo escapeOutput($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $roleBadgeClass; ?>">
                                            <?php echo escapeOutput($roleLabel); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $verificationBadgeClass; ?>">
                                            <?php echo escapeOutput($verificationLabel); ?>
                                        </span>
                                    </td>
                                    <td><?php echo escapeOutput($user['contact_number']); ?></td>
                                    <td><?php echo escapeOutput($user['created_at']); ?></td>
                                    <td class="text-end">
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <a class="btn btn-sm btn-outline-dark text-nowrap" href="<?php echo BASE_URL; ?>admin/user_edit.php?user_id=<?php echo (int) $user['user_id']; ?>">Edit</a>
                                        <?php else: ?>
                                            <span class="text-muted text-nowrap">Read only</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
