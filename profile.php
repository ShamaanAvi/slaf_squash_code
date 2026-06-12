<?php
require_once "config/db.php";
require_once "config/auth.php";
require_once "config/functions.php";

checkAccess();

$playerId = (int)($_SESSION['player_id'] ?? 0);
if ($_SESSION['role'] !== 'player' || $playerId <= 0) {
    header("Location: " . redirectPath('home.php'));
    exit;
}

$message = "";
$type = "success";
$profile = getPlayerProfile($conn, $playerId);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCsrfToken();
    try {
        updatePlayerProfile($conn, $playerId, $_POST, (int)$_SESSION['user_id'], false);
        header("Location: " . redirectPath('profile.php?success=1'));
        exit;
    } catch (Exception $e) {
        $message = $e->getMessage();
        $type = "danger";
    }
}

$profile = getPlayerProfile($conn, $playerId);
include "public/home_header.php";
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h4 class="mb-0"><i class="bi bi-person-gear me-2"></i>My Profile</h4>
            </div>
            <div class="card-body p-4">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">Profile updated successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo e($type); ?> alert-dismissible fade show"><?php echo e($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <form method="post">
                    <?php echo csrfField(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo e($profile['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="<?php echo e($profile['date_of_birth'] ?? $profile['dob'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select gender</option>
                                <option value="Male" <?php echo ($profile['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($profile['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address</label>
                        <textarea name="address" class="form-control" rows="3" required><?php echo e($profile['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo e($profile['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo e($profile['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">New Password</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg"><i class="bi bi-save me-2"></i>Save Profile</button>
                        <a href="<?php echo e(appUrl('home.php')); ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include "public/home_footer.php"; ?>
