<?php

//Player Allocation Page

require_once "../config/db.php";
require_once "../config/functions.php";
require_once "../config/auth.php";

adminOnly();

$msg = "";
$type = "success";

if (isset($_POST['assign'])) {
    validateCsrfToken();
    try {
        $label = assignPlayerToTournament($conn, $_POST['player'], $_POST['tournament']);
        $msg = "<strong>Success!</strong> Player assigned as $label.";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $type = "danger";
    }
}

include "../public/header.php";
$players = getPlayers($conn);
$tournaments = getTournamentList($conn);
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 text-center"><i class="bi bi-person-plus-fill me-2"></i>Player Allocation</h5>
                </div>
                <div class="card-body p-4">

                    <?php if($msg): ?>
                        <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show" role="alert">
                            <?php echo e(strip_tags($msg)); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small uppercase">Select Player</label>
                            <select name="player" class="form-select shadow-sm" required>
                                <option value="">-- Choose Player --</option>
                                <?php foreach ($players as $row): ?>
                                    <option value="<?php echo (int)$row['id']; ?>"><?php echo e($row['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small uppercase">Select Tournament</label>
                            <select name="tournament" class="form-select shadow-sm" required>
                                <option value="">-- Choose Tournament --</option>
                                <?php foreach ($tournaments as $row): ?>
                                    <option value="<?php echo (int)$row['id']; ?>"><?php echo e($row['display_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" name="assign" class="btn btn-primary btn-lg shadow-sm">
                                <i class="bi bi-check2-circle me-2"></i>Save Allocation
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-light text-center py-3">
                    <small class="text-muted">Note: Player category is linked from the selected tournament.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
