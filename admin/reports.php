<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$tournaments = getTournamentList($conn);
include "../public/header.php";
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Reports</h4>
                </div>
                <div class="card-body p-4">
                    <form method="get" action="report_view.php" target="_blank">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tournament</label>
                            <select name="tournament_id" class="form-select" required>
                                <option value="">Select tournament</option>
                                <?php foreach ($tournaments as $tournament): ?>
                                    <option value="<?php echo (int)$tournament['id']; ?>">
                                        <?php echo e($tournament['display_name']); ?> - <?php echo e($tournament['category_name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-lg w-100"><i class="bi bi-printer me-2"></i>Generate Printable Report</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
