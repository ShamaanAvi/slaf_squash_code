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
        'nic'    => trim($_POST['nic']),
        'dob'    => $_POST['dob'],
        'gender' => $_POST['gender'] ?? '',
        'address' => trim($_POST['address'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'other_category_id' => $_POST['other_category_id'] ?? 0
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
$categories = getAgeCategories($conn);
?>

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
                            <label class="form-label fw-bold text-secondary">NIC / Passport Number</label>
                            <input type="text" class="form-control" name="nic" required>
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
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Optional Additional Category</label>
                            <select class="form-select" name="other_category_id" id="otherCategory">
                                <option value="">No additional category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo (int)$category['id']; ?>"><?php echo e($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Only use this if the player must also be enrolled in a second eligible category.</div>
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
    const assignedCategory = document.getElementById('assignedCategory');
    const otherCategory = document.getElementById('otherCategory');
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

        Array.from(otherCategory.options).forEach((option) => {
            option.disabled = category !== '' && option.text === category;
            if (option.disabled && option.selected) {
                otherCategory.value = '';
            }
        });
    }

    dobInput.addEventListener('change', updateAssignedCategory);
    genderInput.addEventListener('change', updateAssignedCategory);
    updateAssignedCategory();
</script>

<?php include "../public/footer.php"; ?>
