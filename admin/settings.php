<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCsrfToken();
    $divisor = max(1, (float)($_POST['ranking_divisor'] ?? 4));
    setSystemSetting($conn, 'ranking_divisor', (string)$divisor);
    setSystemSetting($conn, 'transition_mode', isset($_POST['transition_mode']) ? '1' : '0');
    logAudit($conn, 'settings_updated', 'system_settings', null, 'Ranking settings updated');
    header("Location: settings.php?saved=1");
    exit;
}

$divisor = getSystemSetting($conn, 'ranking_divisor', '4');
$transitionMode = getSystemSetting($conn, 'transition_mode', '0');
include "../public/header.php";
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h4 class="mb-0"><i class="bi bi-gear me-2"></i>System Settings</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($_GET['saved'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">Settings saved.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Ranking Divisor</label>
                            <input type="number" step="0.01" min="1" name="ranking_divisor" class="form-control" value="<?php echo e($divisor); ?>" required>
                        </div>
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="transition_mode" id="transition_mode" <?php echo $transitionMode === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="transition_mode">Transition mode</label>
                        </div>
                        <button class="btn btn-primary btn-lg w-100"><i class="bi bi-save me-2"></i>Save Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
