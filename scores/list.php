<?php

//Scores List Page

require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

checkAccess(); 

$isPlayer = ($_SESSION['role'] === 'player');
$playerId = $_SESSION['player_id'] ?? 0;
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'tournament_id' => $_GET['tournament_id'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'tier' => $_GET['tier'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'finish_position' => $_GET['finish_position'] ?? '',
    'penalty' => $_GET['penalty'] ?? '',
    'points_min' => $_GET['points_min'] ?? '',
    'points_max' => $_GET['points_max'] ?? '',
];
$searchTerm = $filters['search'];
$currentFile = $_SERVER['PHP_SELF'];

// Fetch data using the centralized function
try {
    $res = getScoresList($conn, $isPlayer ? $playerId : null, $filters);
} catch (Exception $e) {
    error_log("Scores List Error: " . $e->getMessage());
    $res = null;
}

include "../public/header.php";
$tournaments = getTournamentList($conn);
$categories = getAgeCategories($conn);
$players = !$isPlayer ? getPlayerList($conn) : [];
?>

<style>
    .score-filter-panel {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
    }

    .score-filter-panel .form-label {
        color: #64748b;
        font-size: 0.72rem;
        letter-spacing: 0.02em;
        margin-bottom: 0.25rem;
    }

    .score-filter-panel .form-control,
    .score-filter-panel .form-select,
    .score-filter-panel .input-group-text {
        font-size: 0.875rem;
    }

    .player-filter-results {
        z-index: 1040;
        max-height: 240px;
        overflow-y: auto;
    }

    @media (max-width: 575.98px) {
        .score-filter-panel {
            padding: 10px;
        }

        .score-filter-panel .input-group {
            flex-wrap: nowrap;
        }

        .score-filter-panel .input-group-text {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }

        .score-filter-panel input[type="date"] {
            font-size: 0.78rem;
            padding-left: 0.4rem;
            padding-right: 0.4rem;
        }

        .score-filter-panel .col-lg-3.col-md-6.d-flex {
            width: 100%;
        }

        .table td.text-end > .d-flex {
            justify-content: flex-start !important;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .table td.text-end .me-3,
        .table td.text-end .me-2 {
            margin-right: 0 !important;
        }
    }
</style>

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
            
        </div>
        
        <div class="card-body">
            <?php if (!$isPlayer): ?>
            <form method="get" class="score-filter-panel mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-uppercase">Player</label>
                        <div class="position-relative">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input
                                    type="text"
                                    name="search"
                                    id="scorePlayerFilter"
                                    class="form-control"
                                    value="<?php echo e($filters['search']); ?>"
                                    placeholder="Player name"
                                    autocomplete="off"
                                    aria-expanded="false"
                                    aria-controls="scorePlayerResults"
                                >
                            </div>
                            <div id="scorePlayerResults" class="list-group position-absolute w-100 shadow-sm d-none player-filter-results">
                                <?php foreach ($players as $player): ?>
                                    <button
                                        type="button"
                                        class="list-group-item list-group-item-action py-2 score-player-option"
                                        data-name="<?php echo e(strtolower($player['full_name'])); ?>"
                                        data-label="<?php echo e($player['full_name']); ?>"
                                    >
                                        <span class="fw-bold"><?php echo e($player['full_name']); ?></span>
                                        <?php if (!empty($player['calculated_category_name'])): ?>
                                            <span class="text-muted small ms-2"><?php echo e($player['calculated_category_name']); ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                                <div id="scoreNoPlayerResults" class="list-group-item text-muted small d-none">No matching players</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-uppercase">Tournament</label>
                        <select name="tournament_id" class="form-select form-select-sm">
                            <option value="">All tournaments</option>
                            <?php foreach ($tournaments as $tournament): ?>
                                <option value="<?php echo (int)$tournament['id']; ?>" <?php echo (string)$filters['tournament_id'] === (string)$tournament['id'] ? 'selected' : ''; ?>><?php echo e($tournament['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-uppercase">Category</label>
                        <select name="category_id" class="form-select form-select-sm">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo (int)$category['id']; ?>" <?php echo (string)$filters['category_id'] === (string)$category['id'] ? 'selected' : ''; ?>><?php echo e($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-uppercase">Date Range</label>
                        <div class="input-group input-group-sm">
                            <input type="date" name="date_from" class="form-control" value="<?php echo e($filters['date_from']); ?>" aria-label="Date from">
                            <span class="input-group-text">to</span>
                            <input type="date" name="date_to" class="form-control" value="<?php echo e($filters['date_to']); ?>" aria-label="Date to">
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-bold text-uppercase">Tier</label>
                        <select name="tier" class="form-select form-select-sm">
                            <option value="">All tiers</option>
                            <option value="A" <?php echo $filters['tier'] === 'A' ? 'selected' : ''; ?>>Tier A</option>
                            <option value="B" <?php echo $filters['tier'] === 'B' ? 'selected' : ''; ?>>Tier B</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-bold text-uppercase">Position</label>
                        <select name="finish_position" class="form-select form-select-sm">
                            <option value="">Any</option>
                            <?php for ($i = 1; $i <= 16; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo (string)$filters['finish_position'] === (string)$i ? 'selected' : ''; ?>><?php echo e(formatFinishPosition($i)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label fw-bold text-uppercase">Status</label>
                        <select name="penalty" class="form-select form-select-sm">
                            <option value="">Any</option>
                            <option value="0" <?php echo $filters['penalty'] === '0' ? 'selected' : ''; ?>>Normal</option>
                            <option value="1" <?php echo $filters['penalty'] === '1' ? 'selected' : ''; ?>>Withdrawal</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-uppercase">Points</label>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" name="points_min" class="form-control" value="<?php echo e($filters['points_min']); ?>" placeholder="Min" aria-label="Minimum points">
                            <span class="input-group-text">to</span>
                            <input type="number" step="0.01" name="points_max" class="form-control" value="<?php echo e($filters['points_max']); ?>" placeholder="Max" aria-label="Maximum points">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="<?php echo e($currentFile); ?>" class="btn btn-outline-secondary btn-sm" aria-label="Clear filters"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
            </form>
            <?php endif; ?>
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

<?php if (!$isPlayer): ?>
<script>
const scorePlayerFilter = document.getElementById('scorePlayerFilter');
const scorePlayerResults = document.getElementById('scorePlayerResults');
const scorePlayerOptions = Array.from(document.querySelectorAll('.score-player-option'));
const scoreNoPlayerResults = document.getElementById('scoreNoPlayerResults');

function updateScorePlayerSuggestions() {
    if (!scorePlayerFilter || !scorePlayerResults) {
        return;
    }

    const term = scorePlayerFilter.value.trim().toLowerCase();
    let visibleCount = 0;

    scorePlayerOptions.forEach((option) => {
        const visible = term !== '' && (option.dataset.name || '').includes(term);
        option.classList.toggle('d-none', !visible);
        if (visible) visibleCount++;
    });

    if (scoreNoPlayerResults) {
        scoreNoPlayerResults.classList.toggle('d-none', term === '' || visibleCount > 0);
    }

    const shouldShow = term !== '' && (visibleCount > 0 || scoreNoPlayerResults);
    scorePlayerResults.classList.toggle('d-none', !shouldShow);
    scorePlayerFilter.setAttribute('aria-expanded', shouldShow ? 'true' : 'false');
}

if (scorePlayerFilter && scorePlayerResults) {
    scorePlayerFilter.addEventListener('input', updateScorePlayerSuggestions);
    scorePlayerFilter.addEventListener('focus', updateScorePlayerSuggestions);

    scorePlayerOptions.forEach((option) => {
        option.addEventListener('click', () => {
            scorePlayerFilter.value = option.dataset.label || '';
            scorePlayerResults.classList.add('d-none');
            scorePlayerFilter.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('click', (event) => {
        if (!scorePlayerResults.contains(event.target) && event.target !== scorePlayerFilter) {
            scorePlayerResults.classList.add('d-none');
            scorePlayerFilter.setAttribute('aria-expanded', 'false');
        }
    });
}
</script>
<?php endif; ?>

<?php include "../public/footer.php"; ?>
