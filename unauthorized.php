<?php
require_once 'includes/config.php';
requireLogin();
$pageTitle = 'Unauthorized';
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-ban me-2"></i> Access Denied</h2>
        <p>You do not have permission to view this page.</p>
    </div>
</div>
<div class="pmas-card">
    <div class="pmas-card-body text-center py-5">
        <i class="fas fa-lock" style="font-size:64px;color:var(--pmas-red);"></i>
        <h3 class="mt-3 mb-2">Unauthorized Access</h3>
        <p class="text-muted">Your account does not have the required permissions to access this resource.</p>
        <a href="<?= BASE_URL ?>dashboard.php" class="btn-pmas mt-3">
            <i class="fas fa-home me-1"></i> Return to Dashboard
        </a>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
