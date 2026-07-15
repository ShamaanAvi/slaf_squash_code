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

                    <form method="post" id="tournamentForm">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Tournament Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label fw-bold text-secondary">Tier</label>
                                <select name="tier" class="form-select" required>
                                    <option value="B">Tier B</option>
                                    <option value="A">Tier A</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">Starting Date</label>
                                <input type="date" class="form-control" name="held_on" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-secondary">Ending Date</label>
                                <input type="date" class="form-control" name="end_on" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
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
                                        <?php foreach ($categories as $category): ?>
                                            <label class="dropdown-item d-flex align-items-center gap-2 rounded">
                                                <input
                                                    class="form-check-input m-0 category-checkbox"
                                                    type="checkbox"
                                                    name="category_ids[]"
                                                    value="<?php echo (int)$category['id']; ?>"
                                                >
                                                <span><?php echo e($category['name']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="form-text">Select one or more categories.</div>
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

<script>
    const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
    const categorySummary = document.getElementById('categorySummary');
    const tournamentForm = document.getElementById('tournamentForm');

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
    tournamentForm.addEventListener('submit', function (event) {
        if (!Array.from(categoryCheckboxes).some((checkbox) => checkbox.checked)) {
            event.preventDefault();
            categoryCheckboxes[0].focus();
        }
    });
    updateCategorySummary();
</script>

<?php include "../public/footer.php"; ?>
