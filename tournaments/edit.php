<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$id = (int)($_GET['id'] ?? 0);
$tournament = getTournamentById($conn, $id);
if (!$tournament) {
    header("Location: list.php");
    exit;
}

$msg = "";
$categories = getAgeCategories($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCsrfToken();
    try {
        updateTournament($conn, $id, $_POST);
        header("Location: list.php?updated=1");
        exit;
    } catch (Exception $e) {
        $msg = $e->getMessage();
    }
}

include "../public/header.php";
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Tournament</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($msg): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?php echo e($msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirmTierChange();">
                        <?php echo csrfField(); ?>
                        <input type="hidden" id="originalTier" value="<?php echo e($tournament['tier']); ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tournament Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo e($tournament['name'] ?? $tournament['tournament_name']); ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Tier</label>
                                <select name="tier" id="tier" class="form-select" required>
                                    <option value="B" <?php echo $tournament['tier'] === 'B' ? 'selected' : ''; ?>>Tier B</option>
                                    <option value="A" <?php echo $tournament['tier'] === 'A' ? 'selected' : ''; ?>>Tier A</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Draw Size</label>
                                <input type="number" min="0" name="draw_size" class="form-control" value="<?php echo (int)$tournament['draw_size']; ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Date</label>
                                <input type="date" name="held_on" class="form-control" value="<?php echo e($tournament['held_on']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Age Category</label>
                                <select name="category_id" class="form-select" required>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo (int)$category['id']; ?>" <?php echo (int)$tournament['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>><?php echo e($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg"><i class="bi bi-save me-2"></i>Save Changes</button>
                            <a class="btn btn-outline-secondary" href="list.php">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmTierChange() {
    var originalTier = document.getElementById('originalTier').value;
    var tier = document.getElementById('tier').value;
    return originalTier === tier || confirm('Tier changes affect ranking points. Continue?');
}
</script>

<?php include "../public/footer.php"; ?>
