<?php
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Audit Logs';
$activePage = 'admin_audit';
$auditRows = [];
$auditLoadError = '';

$auditSql = "
    SELECT
        audit_logs.audit_id,
        audit_logs.activity,
        audit_logs.description,
        audit_logs.ip_address,
        audit_logs.created_at,
        users.user_id AS actor_user_id,
        users.full_name AS actor_name,
        users.email AS actor_email,
        users.role AS actor_role
    FROM audit_logs
    LEFT JOIN users ON audit_logs.user_id = users.user_id
    ORDER BY audit_logs.created_at DESC, audit_logs.audit_id DESC
";

$auditStmt = mysqli_prepare($conn, $auditSql);

if ($auditStmt === false) {
    $auditLoadError = 'Audit activity could not be loaded right now.';
} else {
    if (!mysqli_stmt_execute($auditStmt)) {
        $auditLoadError = 'Audit activity could not be loaded right now.';
    } else {
        mysqli_stmt_bind_result(
            $auditStmt,
            $auditId,
            $activity,
            $description,
            $ipAddress,
            $createdAt,
            $actorUserId,
            $actorName,
            $actorEmail,
            $actorRole
        );

        while (mysqli_stmt_fetch($auditStmt)) {
            $auditRows[] = [
                'audit_id' => (int) $auditId,
                'activity' => $activity,
                'description' => $description,
                'ip_address' => $ipAddress,
                'created_at' => $createdAt,
                'actor_user_id' => $actorUserId,
                'actor_name' => $actorName,
                'actor_email' => $actorEmail,
                'actor_role' => $actorRole
            ];
        }
    }

    mysqli_stmt_close($auditStmt);
}

include __DIR__ . '/../includes/header.php';
?>

<section class="page-section">
    <div class="container">
        <div class="setup-panel report-panel">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <p class="section-label mb-2">Admin Management</p>
                    <h1 class="h3 mb-2">Audit Logs</h1>
                    <p class="text-muted mb-0">Review significant system activities and their current actor information.</p>
                </div>
                <a class="btn btn-outline-secondary" href="<?php echo BASE_URL; ?>admin/dashboard.php">Back to Dashboard</a>
            </div>

            <?php if ($auditLoadError !== ''): ?>
                <div class="alert alert-danger mb-0" role="alert">
                    <?php echo escapeOutput($auditLoadError); ?>
                </div>
            <?php elseif (empty($auditRows)): ?>
                <div class="alert alert-info mb-0" role="status">
                    No audit activity is available yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0 audit-report-table">
                        <thead>
                            <tr>
                                <th scope="col">Date and Time</th>
                                <th scope="col">Activity</th>
                                <th scope="col">Description</th>
                                <th scope="col">Actor</th>
                                <th scope="col">Actor Email</th>
                                <th scope="col">Actor Role</th>
                                <th scope="col">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditRows as $audit): ?>
                                <?php
                                $hasActor = $audit['actor_user_id'] !== null;
                                $actorName = $hasActor ? $audit['actor_name'] : 'System or unavailable user';
                                $actorEmail = $hasActor ? $audit['actor_email'] : '-';
                                $actorRole = $hasActor ? $audit['actor_role'] : '-';
                                $description = $audit['description'] !== null && $audit['description'] !== ''
                                    ? $audit['description']
                                    : '-';
                                $ipAddress = $audit['ip_address'] !== null && $audit['ip_address'] !== ''
                                    ? $audit['ip_address']
                                    : '-';
                                ?>
                                <tr>
                                    <td class="text-nowrap"><?php echo escapeOutput(formatDatabaseDate($audit['created_at'])); ?></td>
                                    <td><?php echo escapeOutput($audit['activity']); ?></td>
                                    <td class="audit-description"><?php echo escapeOutput($description); ?></td>
                                    <td class="audit-actor"><?php echo escapeOutput($actorName); ?></td>
                                    <td class="audit-email"><?php echo escapeOutput($actorEmail); ?></td>
                                    <td><?php echo escapeOutput($actorRole); ?></td>
                                    <td class="text-nowrap"><?php echo escapeOutput($ipAddress); ?></td>
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
