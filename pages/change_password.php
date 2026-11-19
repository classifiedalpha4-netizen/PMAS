<?php
require_once '../includes/config.php';
requireLogin();
$pageTitle = 'Change Password';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $user = getUser($conn, $_SESSION['user_id']);

    if (!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $hash, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        logAction($conn, $_SESSION['user_id'], 'CHANGE_PASSWORD', 'Auth', 'Password changed');
        $success = 'Password changed successfully.';
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-key me-2"></i> Change Password</h2>
        <p>Update your account password</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="pmas-card">
            <div class="pmas-card-header"><i class="fas fa-lock"></i> Change Password</div>
            <div class="pmas-card-body">
                <?php if ($success): ?>
                <div class="pmas-alert pmas-alert-success" data-auto-dismiss>
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
                <?php endif; ?>
                <?php if ($error): ?>
                <div class="pmas-alert pmas-alert-danger" data-auto-dismiss>
                    <i class="fas fa-times-circle"></i> <?= $error ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="pmas-form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control"
                               minlength="8" required>
                        <div class="form-text">Minimum 8 characters.</div>
                    </div>
                    <div class="mb-4">
                        <label class="pmas-form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn-pmas">
                        <i class="fas fa-save me-1"></i> Update Password
                    </button>
                    <a href="<?= BASE_URL ?>dashboard.php" class="btn-pmas-dark ms-2">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
