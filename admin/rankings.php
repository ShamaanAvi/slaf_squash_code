<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$categories = getAgeCategories($conn);
$selectedCategory = (int)($_GET['category_id'] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCsrfToken();
    if (isset($_POST['run_rankings'])) {
        calculateRankings($conn, date('Y-m'));
        header("Location: rankings.php?ran=1");
        exit;
    }
    if (isset($_POST['transition_mode'])) {
        setSystemSetting($conn, 'transition_mode', isset($_POST['transition_enabled']) ? '1' : '0');
        logAudit($conn, 'transition_mode_updated', 'system_settings', null, null);
        header("Location: rankings.php?saved=1");
        exit;
    }
}

$rankings = getCurrentRankings($conn, $selectedCategory);
$lastRun = getSystemSetting($conn, 'last_ranking_run', 'Never');
$transitionMode = getSystemSetting($conn, 'transition_mode', '0');

include "../public/header.php";
?>

<div class="container mt-4">
    <?php if (isset($_GET['ran']) || isset($_GET['saved'])): ?>
        <div class="alert alert-success alert-dismissible fade show">Rankings updated successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Rankings Admin</h4>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <div class="text-muted small">Last ranking run</div>
                    <div class="fw-bold"><?php echo e($lastRun ?: 'Never'); ?></div>
                </div>
                <div class="col-md-4">
                    <form method="post" id="rankingTransitionModeForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="transition_mode" value="1">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="transition_enabled" id="transition_enabled" <?php echo $transitionMode === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="transition_enabled">Transition mode</label>
                        </div>
                        <div class="form-text">Changes save automatically when toggled.</div>
                    </form>
                </div>
                <div class="col-md-4 text-md-end">
                    <form method="post">
                        <?php echo csrfField(); ?>
                        <button name="run_rankings" class="btn btn-primary"><i class="bi bi-arrow-clockwise me-2"></i>Run Ranking Calculation Now</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <form method="get" class="card shadow-sm border-0 mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label fw-bold">Category</label>
                <select name="category_id" class="form-select">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int)$category['id']; ?>" <?php echo $selectedCategory === (int)$category['id'] ? 'selected' : ''; ?>><?php echo e($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><button class="btn btn-primary w-100">Filter</button></div>
        </div>
    </form>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Rank</th><th>Category</th><th>Player</th><th class="text-end">Average</th></tr></thead>
                <tbody>
                    <?php foreach ($rankings as $row): ?>
                        <tr><td><?php echo (int)$row['rank_position']; ?></td><td><?php echo e($row['category_name']); ?></td><td><?php echo e($row['full_name']); ?></td><td class="text-end fw-bold"><?php echo e(number_format((float)$row['ranking_average'], 4)); ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$rankings): ?><tr><td colspan="4" class="text-center py-5 text-muted">No ranking data found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const rankingTransitionToggle = document.getElementById('transition_enabled');
const rankingTransitionForm = document.getElementById('rankingTransitionModeForm');

if (rankingTransitionToggle && rankingTransitionForm) {
    rankingTransitionToggle.addEventListener('change', () => {
        rankingTransitionToggle.disabled = true;
        rankingTransitionForm.submit();
    });
}
</script>

<?php include "../public/footer.php"; ?>
