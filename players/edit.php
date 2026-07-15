<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$id = (int)($_GET['id'] ?? 0);
$player = getPlayerAdminById($conn, $id);
if (!$player) {
    header("Location: add.php");
    exit;
}

$message = "";
$type = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCsrfToken();
    try {
        updateAdminPlayer($conn, $id, $_POST);
        header("Location: edit.php?id=" . $id . "&saved=1");
        exit;
    } catch (Exception $e) {
        $message = $e->getMessage();
        $type = "danger";
    }
}

$player = getPlayerAdminById($conn, $id);
include "../public/header.php";
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-person-gear me-2"></i>Edit Player</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($_GET['saved'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">Player updated successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo e($type); ?> alert-dismissible fade show"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo e($player['full_name']); ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold d-block">Registration Document</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="identity_type" id="identityNic" value="NIC" <?php echo ($player['identity_type'] ?? '') === 'NIC' ? 'checked' : ''; ?> required>
                                    <label class="btn btn-outline-primary" for="identityNic">NIC</label>
                                    <input type="radio" class="btn-check" name="identity_type" id="identityPassport" value="Passport" <?php echo ($player['identity_type'] ?? '') === 'Passport' ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-primary" for="identityPassport">Passport</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold" id="identityNumberLabel">Document Number</label>
                                <input type="text" name="nic" id="identityNumber" class="form-control" value="<?php echo e($player['nic'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3" id="passportExpiryGroup">
                            <label class="form-label fw-bold">Passport Expiry Date</label>
                            <input type="date" name="passport_expiry_date" id="passportExpiryDate" class="form-control" value="<?php echo e($player['passport_expiry_date'] ?? ''); ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Date of Birth</label>
                                <input type="date" name="dob" class="form-control" value="<?php echo e($player['date_of_birth'] ?: $player['dob']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Gender</label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select gender</option>
                                    <option value="Male" <?php echo ($player['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($player['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">System Category</label>
                                <div class="form-control bg-light"><span id="assignedCategory"><?php echo e($player['calculated_category_name'] ?? ''); ?></span></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo e($player['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo e($player['email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Address</label>
                            <textarea name="address" rows="3" class="form-control"><?php echo e($player['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">PSA / WSF Ranking</label>
                            <input type="number" min="1" name="psa_wsf_ranking" class="form-control" value="<?php echo e($player['psa_wsf_ranking'] ?? ''); ?>">
                        </div>

                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg"><i class="bi bi-save me-2"></i>Save Player</button>
                            <a href="add.php" class="btn btn-outline-secondary">Back to Players</a>
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
const assignedCategory = document.getElementById('assignedCategory');
const identityTypeInputs = document.querySelectorAll('input[name="identity_type"]');
const identityNumberLabel = document.getElementById('identityNumberLabel');
const passportExpiryGroup = document.getElementById('passportExpiryGroup');
const passportExpiryDate = document.getElementById('passportExpiryDate');
const rankingYear = <?php echo (int)date('Y'); ?>;

function categoryForAge(dob, gender) {
    if (!dob || !gender) return '';
    const age = rankingYear - Number(dob.slice(0, 4));
    if (age < 0) return '';
    if (age <= 8) return gender === 'Male' ? 'Boys U9' : 'Girls U9';
    if (age <= 10) return gender === 'Male' ? 'Boys U11' : 'Girls U11';
    if (age <= 12) return gender === 'Male' ? 'Boys U13' : 'Girls U13';
    if (age <= 14) return gender === 'Male' ? 'Boys U15' : 'Girls U15';
    if (age <= 16) return gender === 'Male' ? 'Boys U17' : 'Girls U17';
    if (age <= 18) return gender === 'Male' ? 'Boys U19' : 'Girls U19';
    if (gender === 'Female') return age >= 35 ? "Women's Over 35" : "Women's Open";
    if (age >= 65) return "Men's Masters Over 65";
    if (age >= 60) return "Men's Masters Over 60";
    if (age >= 55) return "Men's Masters Over 55";
    if (age >= 50) return "Men's Masters Over 50";
    if (age >= 45) return "Men's Over 45";
    if (age >= 40) return "Men's Over 40";
    if (age >= 35) return "Men's Over 35";
    return "Men's Open";
}

function updateCategory() {
    assignedCategory.textContent = categoryForAge(dobInput.value, genderInput.value) || 'Select DOB and gender';
}

function updateIdentity() {
    const checked = document.querySelector('input[name="identity_type"]:checked');
    const isPassport = checked && checked.value === 'Passport';
    identityNumberLabel.textContent = checked ? checked.value + ' Number' : 'Document Number';
    passportExpiryGroup.classList.toggle('d-none', !isPassport);
    if (!isPassport) passportExpiryDate.value = '';
}

dobInput.addEventListener('change', updateCategory);
genderInput.addEventListener('change', updateCategory);
identityTypeInputs.forEach((input) => input.addEventListener('change', updateIdentity));
updateCategory();
updateIdentity();
</script>

<?php include "../public/footer.php"; ?>
