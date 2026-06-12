<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$stmt = $conn->prepare(
    'SELECT a.*, u.username
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT ? OFFSET ?'
);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$logs = fetchAll($stmt->get_result());

include "../public/header.php";
?>

<div class="container mt-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Audit Log</h4>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Date/Time</th><th>Admin User</th><th>Action</th><th>Target</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo e($log['created_at']); ?></td>
                            <td><?php echo e($log['username'] ?? 'Unknown'); ?></td>
                            <td><?php echo e($log['action']); ?></td>
                            <td><?php echo e(trim(($log['target_type'] ?? '') . ' #' . ($log['target_id'] ?? ''), ' #')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?><tr><td colspan="4" class="text-center py-5 text-muted">No audit entries found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between">
            <a class="btn btn-outline-secondary <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?page=<?php echo $page - 1; ?>">Previous</a>
            <a class="btn btn-outline-secondary <?php echo count($logs) < $limit ? 'disabled' : ''; ?>" href="?page=<?php echo $page + 1; ?>">Next</a>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
