<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$id = (int)($_GET['id'] ?? 0);
$score = getScoreById($conn, $id);
if (!$score) {
    header("Location: list.php");
    exit;
}

$msg = "";
$type = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCsrfToken();
    $finishPosition = ($_POST['finish_position'] ?? '') === 'withdrawal' ? null : (int)$_POST['finish_position'];
    try {
        updateScore($conn, $id, $finishPosition);
        header("Location: list.php?updated=1");
        exit;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $type = "danger";
    }
}

include "../public/header.php";
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Result</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($msg): ?>
                        <div class="alert alert-<?php echo e($type); ?> alert-dismissible fade show"><?php echo e($msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Player</label>
                            <input type="text" class="form-control bg-light border-0" value="<?php echo e($score['full_name']); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Tournament</label>
                            <input type="text" class="form-control bg-light border-0" value="<?php echo e($score['tournament_name']); ?>" readonly>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Finishing Position</label>
                            <select name="finish_position" class="form-select" required>
                                <?php for ($i = 1; $i <= 16; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (int)$score['finish_position'] === $i ? 'selected' : ''; ?>><?php echo e(formatFinishPosition($i)); ?></option>
                                <?php endfor; ?>
                                <option value="withdrawal" <?php echo (int)$score['is_penalty'] === 1 ? 'selected' : ''; ?>>Withdrawal / No-show</option>
                            </select>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg shadow-sm"><i class="bi bi-save me-2"></i>Save Changes</button>
                            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
