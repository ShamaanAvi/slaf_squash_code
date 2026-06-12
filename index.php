<?php

//Login page

require_once "config/db.php";
require_once "config/functions.php";
safeSessionStart();

// If already logged in, skip login page
if (isset($_SESSION['user_id'])) {
    header("Location: " . redirectPath('home.php'));
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validateCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password, role, player_id, is_first_login FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $verified = $user && password_verify($password, $user['password']);
    $legacyVerified = $user && !$verified && hash_equals((string)$user['password'], $password);

    if ($verified || $legacyVerified) {
        if ($legacyVerified) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->bind_param("si", $hash, $user['id']);
            $update->execute();
        }
        
        // Security: Regenerate session ID on successful login
        session_regenerate_id(true);

        // Set Session Variables
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['player_id'] = $user['player_id'];
        $_SESSION['is_first_login'] = (int)$user['is_first_login'];
        $_SESSION['LAST_ACTIVITY'] = time();

        $target = ($user['role'] === 'player' && (int)$user['is_first_login'] === 1) ? 'player/setup.php' : 'home.php';
        header("Location: " . redirectPath($target));
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SLAF Squash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; height: 100vh; display: flex; align-items: center; }
        .login-card { max-width: 400px; width: 100%; border: none; border-radius: 15px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card login-card shadow mx-auto">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
                <h3 class="fw-bold mt-2">SLAF Squash</h3>
                <p class="text-muted">Login to your account</p>
            </div>

            <?php if(isset($_GET['timeout'])): ?>
                <div class="alert alert-warning py-2 small text-center">
                    <i class="bi bi-clock-history me-2"></i>Session expired. Please login again.
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger py-2 small text-center"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Login</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
