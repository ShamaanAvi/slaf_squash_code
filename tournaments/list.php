<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$addMsg = "";
$addType = "danger";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['archive_id'])) {
        validateCsrfToken();
        archiveTournament($conn, (int)$_POST['archive_id']);
        header("Location: list.php?archived=1");
        exit;
    }

    if (isset($_POST['create_tournament'])) {
        validateCsrfToken();
        try {
            addTournament($conn, $_POST);
            header("Location: list.php?created=1");
            exit;
        } catch (Exception $e) {
            $addMsg = $e->getMessage();
        }
    }
}

$tournaments = getTournamentList($conn);
$categories = getAgeCategories($conn);
$showAddOverlay = isset($_GET['add']) || $addMsg !== "";
$overlayTournamentId = (int)($_GET['players'] ?? 0);
$overlayTournament = $overlayTournamentId > 0 ? getTournamentDetails($conn, $overlayTournamentId) : null;
$overlayPlayers = $overlayTournament ? getTournamentAssignedPlayers($conn, $overlayTournamentId) : [];
include "../public/header.php";
?>

<?php if ($overlayTournament || $showAddOverlay): ?>
<script>document.body.classList.add('tournament-overlay-open');</script>
<style>
    body.tournament-overlay-open .navbar,
    body.tournament-overlay-open .tournament-list-bg {
        filter: blur(5px);
        pointer-events: none;
        user-select: none;
    }
    .tournament-overlay-backdrop {
        position: fixed;
        inset: 0;
        z-index: 2000;
        background: rgba(15, 23, 42, 0.45);
        padding: 24px;
        overflow-y: auto;
    }
    .tournament-overlay-dialog {
        max-width: 1180px;
        margin: 32px auto;
    }
    .tournament-add-dialog {
        max-width: 760px;
    }
    @media (max-width: 575.98px) {
        .tournament-overlay-backdrop {
            padding: 10px;
        }
        .tournament-overlay-dialog {
            margin: 10px auto;
            max-width: 100%;
        }
        .tournament-overlay-dialog .card-header {
            align-items: flex-start !important;
            gap: 12px;
        }
        .tournament-overlay-dialog .card-header .d-flex {
            width: 100%;
        }
        .tournament-overlay-dialog .card-header .btn {
            flex: 1 1 auto;
        }
    }
</style>
<?php endif; ?>

<div class="container mt-4 tournament-list-bg">
    <?php if (isset($_GET['created']) || isset($_GET['updated']) || isset($_GET['archived'])): ?>
        <div class="alert alert-success alert-dismissible fade show">Tournament saved successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-trophy me-2"></i>Tournaments</h4>
            <a class="btn btn-light btn-sm" href="list.php?add=1"><i class="bi bi-plus-lg me-1"></i>Add</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Tier</th>
                        <th>Dates</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tournaments as $tournament): ?>
                        <tr role="button" onclick="window.location.href='list.php?players=<?php echo (int)$tournament['id']; ?>'">
                            <td class="fw-bold"><?php echo e($tournament['display_name']); ?></td>
                            <td><?php echo e($tournament['category_name'] ?? ''); ?></td>
                            <td><span class="badge bg-secondary">Tier <?php echo e($tournament['tier']); ?></span></td>
                            <td>
                                <?php echo e($tournament['held_on']); ?>
                                <?php if (!empty($tournament['end_on'])): ?>
                                    <span class="text-muted">to</span> <?php echo e($tournament['end_on']); ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?php echo (int)$tournament['id']; ?>" onclick="event.stopPropagation();"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline" onclick="event.stopPropagation();" onsubmit="return confirm('Archive this tournament?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="archive_id" value="<?php echo (int)$tournament['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tournaments): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No active tournaments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($showAddOverlay): ?>
<div class="tournament-overlay-backdrop">
    <div class="tournament-overlay-dialog tournament-add-dialog">
        <div class="card shadow border-0">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-trophy-fill me-2"></i>Add Tournament</h5>
                <a class="btn btn-outline-light btn-sm" href="list.php"><i class="bi bi-x-lg me-1"></i>Close</a>
            </div>
            <div class="card-body p-4">
                <?php if ($addMsg): ?>
                    <div class="alert alert-<?php echo e($addType); ?> alert-dismissible fade show"><?php echo e($addMsg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <form method="post" id="tournamentAddForm">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="create_tournament" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Tournament Name</label>
                        <input type="text" class="form-control" name="name" required value="<?php echo e($_POST['name'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Tier</label>
                        <select name="tier" class="form-select" required>
                            <option value="B" <?php echo (($_POST['tier'] ?? 'B') === 'B') ? 'selected' : ''; ?>>Tier B</option>
                            <option value="A" <?php echo (($_POST['tier'] ?? 'B') === 'A') ? 'selected' : ''; ?>>Tier A</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">Starting Date</label>
                            <input type="date" class="form-control" name="held_on" required value="<?php echo e($_POST['held_on'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">Ending Date</label>
                            <input type="date" class="form-control" name="end_on" required value="<?php echo e($_POST['end_on'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">Age Categories</label>
                        <div class="dropdown" id="categoryDropdown">
                            <button
                                class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center"
                                type="button"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                aria-expanded="false"
                            >
                                <span id="categorySummary">Select age categories</span>
                            </button>
                            <div class="dropdown-menu w-100 p-2 shadow-sm" style="max-height: 280px; overflow-y: auto;">
                                <?php $selectedCategoryIds = array_map('intval', $_POST['category_ids'] ?? []); ?>
                                <?php foreach ($categories as $category): ?>
                                    <label class="dropdown-item d-flex align-items-center gap-2 rounded">
                                        <input
                                            class="form-check-input m-0 category-checkbox"
                                            type="checkbox"
                                            name="category_ids[]"
                                            value="<?php echo (int)$category['id']; ?>"
                                            <?php echo in_array((int)$category['id'], $selectedCategoryIds, true) ? 'checked' : ''; ?>
                                        >
                                        <span><?php echo e($category['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-text">Select one or more categories.</div>
                    </div>
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg shadow-sm">Save Tournament</button>
                        <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($overlayTournament): ?>
<div class="tournament-overlay-backdrop">
    <div class="tournament-overlay-dialog">
        <div class="card shadow border-0 mb-4">
            <div class="card-header bg-primary text-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h4 class="mb-0"><i class="bi bi-people me-2"></i><?php echo e($overlayTournament['display_name']); ?></h4>
                <div class="d-flex gap-2">
                    <a class="btn btn-light btn-sm" href="assign.php?tournament=<?php echo (int)$overlayTournamentId; ?>"><i class="bi bi-person-plus me-1"></i>Assign</a>
                    <a class="btn btn-outline-light btn-sm" href="list.php"><i class="bi bi-x-lg me-1"></i>Close</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><div class="text-muted small">Category</div><div class="fw-bold"><?php echo e($overlayTournament['category_name']); ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Tier</div><div class="fw-bold">Tier <?php echo e($overlayTournament['tier']); ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Dates</div><div class="fw-bold"><?php echo e($overlayTournament['held_on']); ?> to <?php echo e($overlayTournament['end_on'] ?: $overlayTournament['held_on']); ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Players</div><div class="fw-bold"><?php echo count($overlayPlayers); ?></div></div>
                </div>
            </div>
        </div>

        <div class="card shadow border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Player</th>
                            <th>Gender</th>
                            <th>DOB</th>
                            <th>Category</th>
                            <th>Document</th>
                            <th>Passport</th>
                            <th>Assigned</th>
                            <th class="text-end">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overlayPlayers as $player): ?>
                            <tr>
                                <td class="fw-bold"><?php echo e($player['full_name']); ?></td>
                                <td><?php echo e($player['gender']); ?></td>
                                <td><?php echo e($player['dob']); ?></td>
                                <td><?php echo e($player['calculated_category_name']); ?></td>
                                <td><?php echo e($player['identity_type'] . (!empty($player['nic']) ? ': ' . $player['nic'] : '')); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($player['passport_status_label']); ?></span></td>
                                <td><?php echo e($player['assigned_at']); ?></td>
                                <td class="text-end">
                                    <?php if ($player['finish_position'] !== null || (int)$player['is_penalty'] === 1): ?>
                                        <?php echo e(formatFinishPosition($player['finish_position'], $player['is_penalty'])); ?>
                                        <span class="fw-bold text-primary ms-2"><?php echo e(number_format((float)$player['points_awarded'], 2)); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">No result</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$overlayPlayers): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No players assigned to this tournament yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($showAddOverlay): ?>
<script>
    const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
    const categorySummary = document.getElementById('categorySummary');
    const tournamentAddForm = document.getElementById('tournamentAddForm');

    function updateCategorySummary() {
        const selected = Array.from(categoryCheckboxes)
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.closest('label').querySelector('span').textContent.trim());

        if (selected.length === 0) {
            categorySummary.textContent = 'Select age categories';
            return;
        }

        categorySummary.textContent = selected.length === 1 ? selected[0] : selected.length + ' categories selected';
    }

    categoryCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCategorySummary));
    tournamentAddForm.addEventListener('submit', function (event) {
        if (!Array.from(categoryCheckboxes).some((checkbox) => checkbox.checked)) {
            event.preventDefault();
            if (categoryCheckboxes[0]) {
                categoryCheckboxes[0].focus();
            }
        }
    });
    updateCategorySummary();
</script>
<?php endif; ?>

<?php include "../public/footer.php"; ?>
