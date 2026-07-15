<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$message = "";
$toastType = "success";
$generatedUser = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCsrfToken();
    $data = [
        'name'   => trim($_POST['name']),
        'identity_type' => $_POST['identity_type'] ?? '',
        'nic'    => trim($_POST['nic']),
        'passport_expiry_date' => trim($_POST['passport_expiry_date'] ?? ''),
        'dob'    => $_POST['dob'],
        'gender' => $_POST['gender'] ?? '',
        'address' => trim($_POST['address'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? '')
    ];

    if (strtotime($data['dob']) > time()) {
        $message = "Date of birth cannot be in the future.";
        $toastType = "danger";
    } else {
        try {
            // Function now returns the username on success
            $generatedUser = registerPlayer($conn, $data);
            if ($generatedUser) {
                header("Location: add.php?success=1&user=" . urlencode($generatedUser));
                exit;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $toastType = "danger";
        }
    }
}

include "../public/header.php"; 
$playerSearch = trim($_GET['player_search'] ?? '');
$playerRows = $playerSearch !== '' ? getPlayerList($conn, $playerSearch) : [];
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <form method="get" class="card shadow-sm border-0 p-3">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="text" name="player_search" class="form-control" aria-label="Find existing player" placeholder="Find existing player by name, document, phone, or email" value="<?php echo e($playerSearch); ?>">
                    <button class="btn btn-primary" type="submit">Find</button>
                    <?php if ($playerSearch !== ''): ?>
                        <a href="add.php" class="btn btn-outline-secondary" aria-label="Clear player search"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="playerSearchModal" tabindex="-1" aria-labelledby="playerSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="playerSearchModalLabel">Player matches</h5>
                <a href="add.php" class="btn-close btn-close-white" aria-label="Close"></a>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Results for <strong><?php echo e($playerSearch); ?></strong></p>
                <?php if ($playerRows): ?>
                    <div class="list-group">
                        <?php foreach ($playerRows as $player): ?>
                            <a href="edit.php?id=<?php echo (int)$player['id']; ?>" class="list-group-item list-group-item-action py-3">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <div>
                                        <div class="fw-bold"><?php echo e($player['full_name']); ?></div>
                                        <div class="small text-muted">
                                            <?php echo e($player['gender'] ?? ''); ?>
                                            <?php if (!empty($player['date_of_birth']) || !empty($player['dob'])): ?>
                                                <span class="mx-1">|</span><?php echo e($player['date_of_birth'] ?: $player['dob']); ?>
                                            <?php endif; ?>
                                            <?php if (!empty($player['calculated_category_name'])): ?>
                                                <span class="mx-1">|</span><?php echo e($player['calculated_category_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-dark border align-self-start"><?php echo e($player['passport_status_label']); ?></span>
                                </div>
                                <div class="row small text-secondary mt-2 g-2">
                                    <div class="col-md-4">
                                        <i class="bi bi-person-vcard me-1"></i>
                                        <?php echo e(($player['identity_type'] ?? '') . (!empty($player['nic']) ? ': ' . $player['nic'] : '')); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <i class="bi bi-telephone me-1"></i>
                                        <?php echo e($player['phone'] ?? ''); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <i class="bi bi-envelope me-1"></i>
                                        <?php echo e($player['email'] ?? ''); ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-search display-6 d-block mb-2"></i>
                        No players found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3 text-center">
                    <h5 class="mb-0">Register Player</h5>
                    <p class="small mb-0 opacity-75">Creates a login account automatically</p>
                </div>
                <div class="card-body p-4">

                    <?php if(isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Success!</strong> Player registered.<br>
                            Username: <strong><?php echo htmlspecialchars($_GET['user']); ?></strong><br>
                            Initial password: <strong><?php echo e($_GET['user']); ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if($message): ?>
                        <div class="alert alert-<?php echo $toastType; ?> alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo e($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Full Name</label>
                            <input type="text" class="form-control" name="name" required placeholder="Enter player's full name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary d-block">Registration Document</label>
                            <div class="btn-group w-100" role="group" aria-label="Registration document type">
                                <input type="radio" class="btn-check" name="identity_type" id="identityNic" value="NIC" required>
                                <label class="btn btn-outline-primary" for="identityNic">
                                    <i class="bi bi-person-vcard me-1"></i>NIC
                                </label>

                                <input type="radio" class="btn-check" name="identity_type" id="identityPassport" value="Passport">
                                <label class="btn btn-outline-primary" for="identityPassport">
                                    <i class="bi bi-book me-1"></i>Passport
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary" id="identityNumberLabel">Document Number</label>
                            <input type="text" class="form-control" name="nic" id="identityNumber" placeholder="Choose NIC or Passport first">
                        </div>

                        <div class="mb-3 d-none" id="passportExpiryGroup">
                            <label class="form-label fw-bold text-secondary">Passport Expiry Date</label>
                            <input type="date" class="form-control" name="passport_expiry_date" id="passportExpiryDate">
                            <div class="form-text">Used to warn admins before tournament allocation.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">Date of Birth</label>
                                <input type="date" class="form-control" name="dob" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">System-Calculated Category</label>
                                <div class="form-control bg-light d-flex align-items-center justify-content-between" aria-live="polite">
                                    <span id="assignedCategory" class="text-muted">Select DOB and gender</span>
                                    <i class="bi bi-calculator text-secondary ms-2" aria-hidden="true"></i>
                                </div>
                                <div class="form-text">Calculated automatically from date of birth and gender.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                <i class="bi bi-person-plus-fill me-2"></i>Save Player
                            </button>
                            <a href="../home.php" class="btn btn-link text-muted mt-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const dobInput = document.querySelector('input[name="dob"]');
    const genderInput = document.querySelector('select[name="gender"]');
    const identityTypeInputs = document.querySelectorAll('input[name="identity_type"]');
    const identityNumberLabel = document.getElementById('identityNumberLabel');
    const identityNumber = document.getElementById('identityNumber');
    const passportExpiryGroup = document.getElementById('passportExpiryGroup');
    const passportExpiryDate = document.getElementById('passportExpiryDate');
    const assignedCategory = document.getElementById('assignedCategory');
    const rankingYear = <?php echo (int)date('Y'); ?>;

    function categoryForAge(dob, gender) {
        if (!dob || !gender) {
            return '';
        }

        const birthYear = Number(dob.slice(0, 4));
        if (!birthYear) {
            return '';
        }

        const age = rankingYear - birthYear;
        if (age < 0) {
            return '';
        }

        if (age <= 8) return gender === 'Male' ? 'Boys U9' : 'Girls U9';
        if (age <= 10) return gender === 'Male' ? 'Boys U11' : 'Girls U11';
        if (age <= 12) return gender === 'Male' ? 'Boys U13' : 'Girls U13';
        if (age <= 14) return gender === 'Male' ? 'Boys U15' : 'Girls U15';
        if (age <= 16) return gender === 'Male' ? 'Boys U17' : 'Girls U17';
        if (age <= 18) return gender === 'Male' ? 'Boys U19' : 'Girls U19';

        if (gender === 'Female') {
            return age >= 35 ? "Women's Over 35" : "Women's Open";
        }

        if (age >= 65) return "Men's Masters Over 65";
        if (age >= 60) return "Men's Masters Over 60";
        if (age >= 55) return "Men's Masters Over 55";
        if (age >= 50) return "Men's Masters Over 50";
        if (age >= 45) return "Men's Over 45";
        if (age >= 40) return "Men's Over 40";
        if (age >= 35) return "Men's Over 35";
        return "Men's Open";
    }

    function updateAssignedCategory() {
        const category = categoryForAge(dobInput.value, genderInput.value);
        assignedCategory.textContent = category || 'Select DOB and gender';
        assignedCategory.classList.toggle('text-muted', category === '');
        assignedCategory.classList.toggle('fw-bold', category !== '');

    }

    function updateIdentityLabel() {
        const checked = document.querySelector('input[name="identity_type"]:checked');
        if (!checked) {
            identityNumberLabel.textContent = 'Document Number';
            identityNumber.placeholder = 'Choose NIC or Passport first';
            passportExpiryGroup.classList.add('d-none');
            passportExpiryDate.value = '';
            return;
        }
        const selected = checked.value;
        identityNumberLabel.textContent = selected + ' Number';
        identityNumber.placeholder = 'Enter ' + selected.toLowerCase() + ' number, if available';
        const isPassport = selected === 'Passport';
        passportExpiryGroup.classList.toggle('d-none', !isPassport);
        if (!isPassport) {
            passportExpiryDate.value = '';
        }
    }

    dobInput.addEventListener('change', updateAssignedCategory);
    genderInput.addEventListener('change', updateAssignedCategory);
    identityTypeInputs.forEach((input) => input.addEventListener('change', updateIdentityLabel));
    updateAssignedCategory();
    updateIdentityLabel();

    <?php if ($playerSearch !== ''): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const playerSearchModal = new bootstrap.Modal(document.getElementById('playerSearchModal'));
        playerSearchModal.show();
    });
    <?php endif; ?>
</script>

<?php include "../public/footer.php"; ?>
