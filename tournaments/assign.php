<?php
require_once "../config/db.php";
require_once "../config/functions.php";
require_once "../config/auth.php";

adminOnly();

$msg = "";
$type = "success";
$selectedTournamentId = (int)($_POST['tournament'] ?? $_GET['tournament'] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['assign'])) {
    validateCsrfToken();
    try {
        $label = assignPlayerToTournament($conn, (int)$_POST['player'], (int)$_POST['tournament']);
        header("Location: assign.php?tournament=" . (int)$_POST['tournament'] . "&assigned=" . urlencode($label));
        exit;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $type = "danger";
        $selectedTournamentId = (int)$_POST['tournament'];
    }
}

include "../public/header.php";
$tournaments = getTournamentList($conn);
$selectedTournament = $selectedTournamentId ? getTournamentDetails($conn, $selectedTournamentId) : null;
$players = $selectedTournamentId ? getEligiblePlayersForTournament($conn, $selectedTournamentId) : [];
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 text-center"><i class="bi bi-person-plus-fill me-2"></i>Player Allocation</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($_GET['assigned'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">Player assigned as <?php echo e($_GET['assigned']); ?>.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if($msg): ?>
                        <div class="alert alert-<?php echo e($type); ?> alert-dismissible fade show" role="alert">
                            <?php echo e($msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="get" class="mb-4">
                        <label class="form-label fw-bold text-secondary">Tournament</label>
                        <select name="tournament" class="form-select shadow-sm" onchange="this.form.submit()" required>
                            <option value="">-- Choose Tournament --</option>
                            <?php foreach ($tournaments as $row): ?>
                                <option value="<?php echo (int)$row['id']; ?>" <?php echo $selectedTournamentId === (int)$row['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($row['display_name']); ?> - <?php echo e($row['category_name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if ($selectedTournament): ?>
                        <div class="alert alert-info border-0">
                            <strong>Category:</strong> <?php echo e($selectedTournament['category_name']); ?>
                            <span class="text-muted ms-2"><?php echo e($selectedTournament['held_on']); ?> to <?php echo e($selectedTournament['end_on'] ?: $selectedTournament['held_on']); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="assignForm">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="tournament" value="<?php echo (int)$selectedTournamentId; ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Player</label>
                            <input type="hidden" name="player" id="playerId" required>
                            <div class="position-relative">
                                <input
                                    type="text"
                                    id="playerPicker"
                                    class="form-control"
                                    placeholder="Search and select player..."
                                    autocomplete="off"
                                    <?php echo $selectedTournament ? '' : 'disabled'; ?>
                                >
                                <div id="playerResults" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1040; max-height: 260px; overflow-y: auto;">
                                <?php foreach ($players as $row): ?>
                                    <button
                                        type="button"
                                        class="list-group-item list-group-item-action player-option"
                                        data-id="<?php echo (int)$row['id']; ?>"
                                        data-name="<?php echo e(strtolower($row['full_name'])); ?>"
                                        data-label="<?php echo e($row['full_name'] . ' - ' . ($row['calculated_category_name'] ?? '')); ?>"
                                        data-passport-status="<?php echo e($row['passport_status']); ?>"
                                        data-passport-label="<?php echo e($row['passport_status_label']); ?>"
                                        data-passport-expiry="<?php echo e($row['passport_expiry_date'] ?? ''); ?>"
                                    >
                                        <span class="fw-bold"><?php echo e($row['full_name']); ?></span>
                                        <span class="text-muted small ms-2"><?php echo e($row['calculated_category_name'] ?? ''); ?></span>
                                    </button>
                                <?php endforeach; ?>
                                    <div id="noPlayerResults" class="list-group-item text-muted d-none">No matching eligible players</div>
                                </div>
                            </div>
                            <?php if ($selectedTournament && !$players): ?>
                                <div class="form-text text-danger">No eligible players found for this tournament category.</div>
                            <?php endif; ?>
                        </div>

                        <div class="alert d-none" id="passportStatusAlert" role="alert"></div>

                        <div class="d-grid mt-4">
                            <button type="submit" name="assign" id="assignButton" class="btn btn-primary btn-lg shadow-sm" <?php echo ($selectedTournament && $players) ? '' : 'disabled'; ?>>
                                <i class="bi bi-check2-circle me-2"></i>Save Allocation
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-light text-center py-3">
                    <small class="text-muted">Only eligible same-gender players in this category or higher categories are shown.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const playerPicker = document.getElementById('playerPicker');
const playerId = document.getElementById('playerId');
const playerResults = document.getElementById('playerResults');
const playerOptions = Array.from(document.querySelectorAll('.player-option'));
const noPlayerResults = document.getElementById('noPlayerResults');
const assignForm = document.getElementById('assignForm');
const passportStatusAlert = document.getElementById('passportStatusAlert');
const assignButton = document.getElementById('assignButton');
const tournamentEnd = '<?php echo e($selectedTournament['end_on'] ?? $selectedTournament['held_on'] ?? ''); ?>';
const warningDays = 90;
let selectedPlayerOption = null;

function parseDate(value) {
    if (!value) return null;
    const date = new Date(value + 'T00:00:00');
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDate(value) {
    const date = parseDate(value);
    return date ? date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '';
}

function showPlayerResults() {
    if (!playerResults || playerOptions.length === 0) {
        return;
    }
    playerResults.classList.remove('d-none');
}

function filterPlayers() {
    const term = playerPicker.value.trim().toLowerCase();
    let visibleCount = 0;
    playerOptions.forEach((option) => {
        const visible = term === '' || (option.dataset.name || '').includes(term);
        option.classList.toggle('d-none', !visible);
        if (visible) visibleCount++;
    });
    noPlayerResults.classList.toggle('d-none', visibleCount !== 0);

    if (selectedPlayerOption && playerPicker.value !== selectedPlayerOption.dataset.label) {
        selectedPlayerOption = null;
        playerId.value = '';
    }
    showPlayerResults();
    updatePassportStatus();
}

function updatePassportStatus() {
    const option = selectedPlayerOption;
    const status = option ? option.dataset.passportStatus : '';
    const expiryValue = option ? option.dataset.passportExpiry : '';
    const label = option ? option.dataset.passportLabel : '';

    passportStatusAlert.className = 'alert d-none';
    passportStatusAlert.textContent = '';
    assignButton.disabled = !playerId.value;

    if (!status || status === 'not_passport') return;

    const expiryDate = parseDate(expiryValue);
    const tournamentDate = parseDate(tournamentEnd);
    if (expiryDate && tournamentDate && expiryDate < tournamentDate) {
        passportStatusAlert.className = 'alert alert-danger';
        passportStatusAlert.textContent = 'Passport expires on ' + formatDate(expiryValue) + ', before this tournament ends.';
        assignButton.disabled = true;
        return;
    }

    passportStatusAlert.classList.remove('d-none');
    passportStatusAlert.textContent = label;
    passportStatusAlert.classList.add(status === 'expired' ? 'alert-danger' : (status === 'expiring' ? 'alert-warning' : (status === 'unknown' ? 'alert-secondary' : 'alert-success')));
    if (status === 'expired') assignButton.disabled = true;
}

if (playerPicker && playerResults) {
    playerPicker.addEventListener('focus', () => {
        filterPlayers();
    });
    playerPicker.addEventListener('input', filterPlayers);
    playerOptions.forEach((option) => {
        option.addEventListener('click', () => {
            selectedPlayerOption = option;
            playerId.value = option.dataset.id;
            playerPicker.value = option.dataset.label;
            playerResults.classList.add('d-none');
            updatePassportStatus();
        });
    });
    document.addEventListener('click', (event) => {
        if (!playerResults.contains(event.target) && event.target !== playerPicker) {
            playerResults.classList.add('d-none');
        }
    });
    assignForm.addEventListener('submit', (event) => {
        if (!playerId.value) {
            event.preventDefault();
            playerPicker.focus();
            showPlayerResults();
        }
    });
    updatePassportStatus();
}
</script>

<?php include "../public/footer.php"; ?>
