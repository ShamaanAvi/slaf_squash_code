<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

checkAccess();

$categories = getAgeCategories($conn);
$selectedCategory = (int)($_GET['category_id'] ?? 0);
$rankings = getCurrentRankings($conn, $selectedCategory);
$period = getLatestPeriodLabel($conn);

include "../public/header.php";
?>

<div class="container mt-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0"><i class="bi bi-trophy me-2"></i>Rankings</h4>
        </div>
        <div class="card-body p-4">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label fw-bold text-secondary">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int)$category['id']; ?>" <?php echo $selectedCategory === (int)$category['id'] ? 'selected' : ''; ?>><?php echo e($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary w-100"><i class="bi bi-funnel-fill me-2"></i>Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold">Current Rankings</span>
            <span class="text-muted small">Period: <?php echo e($period ?? 'Not calculated'); ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Rank</th>
                        <th>Category</th>
                        <th>Player Name</th>
                        <th class="text-end">Ranking Average</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankings as $row): ?>
                        <tr class="<?php echo (int)($_SESSION['player_id'] ?? 0) === (int)$row['player_id'] ? 'table-info' : ''; ?>">
                            <td class="fw-bold"><?php echo (int)$row['rank_position']; ?></td>
                            <td><?php echo e($row['category_name']); ?></td>
                            <td><?php echo e($row['full_name']); ?></td>
                            <td class="text-end fw-bold"><?php echo e(number_format((float)$row['ranking_average'], 4)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rankings): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No ranking data found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
