<?php 
require_once "config/auth.php"; 
checkAccess(); // Ensure user is logged in
include "public/home_header.php"; 

$role = $_SESSION['role'];
$dashboardData = ($role === 'player' && !empty($_SESSION['player_id'])) ? getPlayerDashboardData($conn, (int)$_SESSION['player_id']) : null;
?>

<div class="text-center mb-5">
    <h2 class="fw-bold">Sri Lanka Air Force Squash</h2>
    <p class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo ucfirst($role); ?>)</p>
</div>

<div class="row g-4">

    <?php if ($role === 'admin'): ?>
    <div class="col-md-4">
        <a href="players/add.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 dashboard-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-person-plus fs-1 text-primary mb-3 d-block"></i>
                    <h5>Register Player</h5>
                    <p class="text-muted small">Add new players to the system</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="tournaments/list.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 dashboard-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-trophy fs-1 text-primary mb-3 d-block"></i>
                    <h5>Tournaments</h5>
                    <p class="text-muted small">Create and manage tournaments</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="tournaments/assign.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 dashboard-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-people fs-1 text-primary mb-3 d-block"></i>
                    <h5>Assign Players</h5>
                    <p class="text-muted small">Attach players to categories</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="scores/add.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 dashboard-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-plus-circle fs-1 text-primary mb-3 d-block"></i>
                    <h5>Enter Scores</h5>
                    <p class="text-muted small">Record match scores</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="admin/rankings.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 dashboard-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-bar-chart-line fs-1 text-primary mb-3 d-block"></i>
                    <h5>Rankings</h5>
                    <p class="text-muted small">Run calculations and review standings</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="admin/audit.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 dashboard-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-clipboard-data fs-1 text-primary mb-3 d-block"></i>
                    <h5>Audit Log</h5>
                    <p class="text-muted small">Review administrative activity</p>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <div class="col-md-4">
        <a href="scores/list.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 dashboard-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-list-check fs-1 text-primary mb-3 d-block"></i>
                    <h5><?php echo ($role === 'admin') ? 'View Scores' : 'My Scores'; ?></h5>
                    <p class="text-muted small">Check tournament history</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-4">
        <a href="scores/view.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 dashboard-card border-0">
                <div class="card-body text-center">
                    <i class="bi bi-star-fill fs-1 text-primary mb-3 d-block"></i>
                    <h5>Rankings</h5>
                    <p class="text-muted small">Live category rankings</p>
                </div>
            </div>
        </a>
    </div>

</div>

<?php if ($role === 'player' && $dashboardData): ?>
<div class="row g-4 mt-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">Current Ranking</div>
            <div class="card-body">
                <?php foreach ($dashboardData['rankings'] as $ranking): ?>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span><?php echo e($ranking['category_name']); ?></span>
                        <strong>#<?php echo (int)$ranking['rank_position']; ?> / <?php echo e(number_format((float)$ranking['ranking_average'], 4)); ?></strong>
                    </div>
                <?php endforeach; ?>
                <?php if (!$dashboardData['rankings']): ?>
                    <p class="text-muted mb-0">No current ranking has been calculated yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold">My Results</div>
            <div class="card-body">
                <?php foreach ($dashboardData['results'] as $result): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <div class="fw-bold"><?php echo e($result['tournament_name']); ?></div>
                            <div class="text-muted small"><?php echo e($result['held_on']); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold"><?php echo e(number_format((float)$result['points_awarded'], 2)); ?> pts</div>
                            <span class="badge <?php echo isset($dashboardData['counting_ids'][(int)$result['id']]) ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo isset($dashboardData['counting_ids'][(int)$result['id']]) ? 'Counting' : 'Not counted'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$dashboardData['results']): ?>
                    <p class="text-muted mb-0">No results recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.dashboard-card { transition: .2s; cursor: pointer; }
.dashboard-card:hover { transform: translateY(-6px); box-shadow: 0 10px 20px rgba(0,0,0,.15) !important; }
</style>

<?php include "public/home_footer.php"; ?>
