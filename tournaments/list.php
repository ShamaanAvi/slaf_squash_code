<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['archive_id'])) {
    validateCsrfToken();
    archiveTournament($conn, (int)$_POST['archive_id']);
    header("Location: list.php?archived=1");
    exit;
}

$tournaments = getTournamentList($conn);
include "../public/header.php";
?>

<div class="container mt-4">
    <?php if (isset($_GET['created']) || isset($_GET['updated']) || isset($_GET['archived'])): ?>
        <div class="alert alert-success alert-dismissible fade show">Tournament saved successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-trophy me-2"></i>Tournaments</h4>
            <a class="btn btn-light btn-sm" href="add.php"><i class="bi bi-plus-lg me-1"></i>Add</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Tier</th>
                        <th>Draw Size</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tournaments as $tournament): ?>
                        <tr>
                            <td class="fw-bold"><?php echo e($tournament['display_name']); ?></td>
                            <td><?php echo e($tournament['category_name'] ?? ''); ?></td>
                            <td><span class="badge bg-secondary">Tier <?php echo e($tournament['tier']); ?></span></td>
                            <td><?php echo (int)$tournament['draw_size']; ?></td>
                            <td><?php echo e($tournament['held_on']); ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?php echo (int)$tournament['id']; ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Archive this tournament?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="archive_id" value="<?php echo (int)$tournament['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tournaments): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No active tournaments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
