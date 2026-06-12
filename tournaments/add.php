<?php
require_once "../config/db.php";
require_once "../config/auth.php";
require_once "../config/functions.php";

adminOnly();

$msg = "";
$type = "success";
$categories = getAgeCategories($conn);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCsrfToken();
    try {
        addTournament($conn, $_POST);
        header("Location: list.php?created=1");
        exit;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $type = "danger";
    }
}

include "../public/header.php";
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 text-center"><i class="bi bi-trophy-fill me-2"></i>Add Tournament</h5>
                </div>
                <div class="card-body p-4">
                    <?php if ($msg): ?>
                        <div class="alert alert-<?php echo e($type); ?> alert-dismissible fade show"><?php echo e($msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                    <?php endif; ?>

                    <form method="post">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Tournament Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">Tier</label>
                                <select name="tier" class="form-select" required>
                                    <option value="B">Tier B</option>
                                    <option value="A">Tier A</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">Draw Size</label>
                                <input type="number" min="0" class="form-control" name="draw_size" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">Date</label>
                                <input type="date" class="form-control" name="held_on" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">Age Category</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo (int)$category['id']; ?>"><?php echo e($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm">Save Tournament</button>
                            <a href="list.php" class="btn btn-outline-secondary">Tournament List</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../public/footer.php"; ?>
