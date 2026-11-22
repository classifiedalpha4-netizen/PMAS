<?php
require_once 'includes/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['full_name']= $user['full_name'];
            $_SESSION['rank']     = $user['rank'];

            logAction($conn, $user['id'], 'LOGIN', 'Auth', 'User logged in');
            redirect(BASE_URL . 'dashboard.php');
        } else {
            $error = 'Invalid username or password.';
            logAction($conn, null, 'FAILED LOGIN', 'Auth', "Attempted: $username");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<div class="pmas-login-wrapper">
    <div class="pmas-login-box">

        <!-- Logo -->
        <div class="pmas-login-logo">
            <div class="emblem">
                <img src="<?= BASE_URL ?>assets/img/pma_logo.php" alt="PMA Logo">
            </div>
            <h1>PMAS Portal</h1>
            <p>Philippine Military Academy System</p>
            <p style="margin-top:4px;color:#C0001A;font-weight:700;">Philippine Army Roleplay</p>
        </div>

        <hr class="pmas-login-divider">

        <?php if ($error): ?>
        <div class="pmas-alert pmas-alert-danger" data-auto-dismiss>
            <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="pmas-form-label"><i class="fas fa-user me-1"></i> Username / Cadet Number</label>
                <input type="text"
                       name="username"
                       class="form-control"
                       placeholder="e.g. B01-001 / admin / STF-001"
                       value="<?= sanitize($_POST['username'] ?? '') ?>"
                       required autofocus>
                <div class="form-text text-muted" style="font-size:11px;">
                    Cadets: use your Cadet Number (e.g. B01-001)
                </div>
            </div>
            <div class="mb-4">
                <label class="pmas-form-label"><i class="fas fa-lock me-1"></i> Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Enter password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePass" tabindex="-1">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn-pmas w-100 justify-content-center py-2" style="font-size:15px;">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>

        <hr class="pmas-login-divider">
        <div class="text-center" style="font-size:11px;color:#888;">
            <i class="fas fa-shield-halved me-1 text-pmas-red"></i>
            For authorized personnel only. Unauthorized access is prohibited.<br>
            All activities are logged and monitored.
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
<script>
document.getElementById('togglePass').addEventListener('click', function () {
    const pw   = document.getElementById('loginPassword');
    const icon = document.getElementById('eyeIcon');
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.classList.replace('fa-eye','fa-eye-slash');
    } else {
        pw.type = 'password';
        icon.classList.replace('fa-eye-slash','fa-eye');
    }
});
</script>
</body>
</html>
