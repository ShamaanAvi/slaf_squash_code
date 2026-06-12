<?php

//Scores List Page

require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

checkAccess(); 

$isPlayer = ($_SESSION['role'] === 'player');
$playerId = $_SESSION['player_id'] ?? 0;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$currentFile = $_SERVER['PHP_SELF'];

// Fetch data using the centralized function
try {
    $res = getScoresList($conn, $isPlayer ? $playerId : null, $searchTerm);
} catch (Exception $e) {
    error_log("Scores List Error: " . $e->getMessage());
    $res = null;
}

include "../public/header.php";
?>

<div class="container mt-4">
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Record removed successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h4 class="mb-0">
                <i class="bi bi-list-stars me-2"></i>
                <?php echo $isPlayer ? "My Score History" : "Scores List"; ?>
            </h4>
            
            <?php if (!$isPlayer): ?>
            <form action="" method="GET" class="d-flex" style="min-width: 280px;">
                <div class="input-group shadow-sm">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search player name..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button class="btn btn-light border" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if($searchTerm): ?>
                        <a href="<?php echo $currentFile; ?>" class="btn btn-outline-light"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr class="text-secondary small uppercase">
                            <th>Player</th>
                            <th>Tournament</th>
                            <th>Category</th>
                            <th class="text-center">Date</th>
                            <th class="text-end" style="width: 240px;">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res && $res->num_rows > 0): ?>
                            <?php while($row = $res->fetch_assoc()): ?>
                                <tr>
                                    <td><div class="fw-bold text-dark"><?php echo htmlspecialchars($row['full_name']); ?></div></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($row['tournament_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark bg-opacity-10 border border-info-subtle">
                                            <?php echo htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center text-muted"><?php echo e($row['held_on']); ?></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end align-items-center">
                                            <?php if((int)$row['is_penalty'] === 1): ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 me-3">Withdrawal / No-show</span>
                                            <?php else: ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle me-2"><?php echo e(formatFinishPosition($row['finish_position'])); ?></span>
                                            <?php endif; ?>
                                            <span class="fs-5 fw-bold text-primary me-3"><?php echo e(number_format((float)$row['points_awarded'], 2)); ?></span>

                                            <?php if (!$isPlayer): ?>
                                            <div class="btn-group">
                                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                                <form method="post" action="delete.php" class="d-inline" onsubmit="return confirm('Remove this result entry?');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan='5' class='text-center py-5 text-muted'>
                                    <i class='bi bi-info-circle fs-2 d-block mb-2'></i>
                                    <?php echo $searchTerm ? "No results found." : "No scores recorded yet."; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small">
            Total Records: <?php echo $res ? $res->num_rows : 0; ?>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
