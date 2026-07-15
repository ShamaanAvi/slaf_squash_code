<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$msg = "";
$selectedTournament = isset($_POST['tournament']) ? (int)$_POST['tournament'] : (int)($_GET['tournament'] ?? 0);
$tournaments = getTournamentList($conn);
$assignedPlayers = $selectedTournament ? getAssignedPlayersWithoutResult($conn, $selectedTournament) : [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save'])) {
    validateCsrfToken();
    $finishPosition = ($_POST['finish_position'] ?? '') === 'withdrawal' ? null : (int)$_POST['finish_position'];
    try {
        saveScore($conn, (int)$_POST['player'], (int)$_POST['tournament'], $finishPosition);
        header("Location: add.php?success=1");
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
                <div class="card-header bg-primary text-white py-3 text-center">
                    <h5 class="mb-0">Add Result</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">Result saved successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($msg): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><?php echo e($msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="get" class="mb-4">
                        <label class="form-label fw-bold text-secondary">Tournament</label>
                        <select name="tournament" class="form-select" onchange="this.form.submit()" required>
                            <option value="">Select Tournament</option>
                            <?php foreach ($tournaments as $tournament): ?>
                                <option value="<?php echo (int)$tournament['id']; ?>" <?php echo $selectedTournament === (int)$tournament['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($tournament['display_name']); ?> - <?php echo e($tournament['category_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <form method="post">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="tournament" value="<?php echo (int)$selectedTournament; ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Player</label>
                            <select name="player" class="form-select" required <?php echo $selectedTournament ? '' : 'disabled'; ?>>
                                <option value="">Select Player</option>
                                <?php foreach ($assignedPlayers as $player): ?>
                                    <option value="<?php echo (int)$player['id']; ?>">
                                        <?php echo e($player['full_name']); ?><?php echo !empty($player['calculated_category_name']) ? ' - ' . e($player['calculated_category_name']) : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($selectedTournament && !$assignedPlayers): ?>
                                <div class="form-text text-danger">No assigned players without results for this tournament.</div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">Finishing Position</label>
                            <select name="finish_position" class="form-select" required>
                                <option value="">Select position</option>
                                <?php for ($i = 1; $i <= 16; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo e(formatFinishPosition($i)); ?></option>
                                <?php endfor; ?>
                                <option value="withdrawal">Withdrawal / No-show</option>
                            </select>
                        </div>
                        <button type="submit" name="save" class="btn btn-primary btn-lg w-100 shadow-sm" <?php echo ($selectedTournament && $assignedPlayers) ? '' : 'disabled'; ?>>Save Result</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
