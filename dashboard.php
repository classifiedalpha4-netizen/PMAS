<?php
require_once 'includes/config.php';
requireLogin();
$role      = $_SESSION['role'];
$pageTitle = 'Dashboard';

$stats = [];

// ---- ADMIN / STAFF ----
if ($role === 'admin' || $role === 'staff') {
    $stats['total_cadets']   = $conn->query("SELECT COUNT(*) FROM users WHERE role='cadet'")->fetch_row()[0];
    $stats['total_officers'] = $conn->query("SELECT COUNT(*) FROM users WHERE role='officer'")->fetch_row()[0];
    $stats['total_cases']    = $conn->query("SELECT COUNT(*) FROM cases")->fetch_row()[0];
    $stats['blacklisted']    = $conn->query("SELECT COUNT(*) FROM blacklist WHERE status='Active'")->fetch_row()[0];
    $stats['pending_cases']  = $conn->query("SELECT COUNT(*) FROM cases WHERE verdict='Pending'")->fetch_row()[0];
    $stats['adm_pending']    = $conn->query("SELECT COUNT(*) FROM applicants WHERE submission_status IN ('submitted','under_review')")->fetch_row()[0];
    $stats['adm_passed']     = $conn->query("SELECT COUNT(*) FROM applicants WHERE submission_status='passed'")->fetch_row()[0];
    $stats['adm_bg_ready']   = $conn->query("SELECT COUNT(*) FROM applicants WHERE submission_status='submitted' AND bg_check_due <= NOW()")->fetch_row()[0];

    $recentCases = $conn->query("
        SELECT c.*, u.full_name, u.role
        FROM cases c
        JOIN users u ON c.subject_user_id = u.id
        ORDER BY c.created_at DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);

    $recentBlacklist = $conn->query("
        SELECT * FROM blacklist ORDER BY blacklisted_date DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
}

// ---- OFFICER ----
if ($role === 'officer') {
    $uid = $_SESSION['user_id'];
    $stats['my_cases'] = $conn->query("SELECT COUNT(*) FROM cases WHERE subject_user_id=$uid")->fetch_row()[0];
    $op = $conn->query("SELECT * FROM officer_profiles WHERE user_id=$uid")->fetch_assoc();
}

// ---- CADET ----
if ($role === 'cadet') {
    $uid = $_SESSION['user_id'];
    $stats['my_cases'] = $conn->query("SELECT COUNT(*) FROM cases WHERE subject_user_id=$uid")->fetch_row()[0];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h2>
        <p>Welcome back, <strong><?= sanitize($_SESSION['full_name']) ?></strong>
           &mdash; <?= date('l, F j, Y') ?></p>
    </div>
    <div>
        <span class="badge bg-danger text-uppercase" style="font-size:12px;padding:8px 14px;">
            <i class="fas fa-shield-halved me-1"></i>
            <?= strtoupper($role) ?>
        </span>
    </div>
</div>

<?php if ($role === 'admin' || $role === 'staff'): ?>
<!-- ===== ADMIN / STAFF DASHBOARD ===== -->

<!-- Main stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="stat-card-info">
                <h3><?= $stats['total_cadets'] ?></h3>
                <p>Total Cadets</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card black">
            <div class="stat-card-icon"><i class="fas fa-user-tie"></i></div>
            <div class="stat-card-info">
                <h3><?= $stats['total_officers'] ?></h3>
                <p>Officers</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card gold">
            <div class="stat-card-icon"><i class="fas fa-gavel"></i></div>
            <div class="stat-card-info">
                <h3><?= $stats['total_cases'] ?></h3>
                <p>Total Cases
                    <?php if ($stats['pending_cases'] > 0): ?>
                    <br><small style="color:#ffe66d;"><?= $stats['pending_cases'] ?> Pending</small>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="fas fa-ban"></i></div>
            <div class="stat-card-info">
                <h3><?= $stats['blacklisted'] ?></h3>
                <p>Active Blacklist</p>
            </div>
        </div>
    </div>
</div>

<!-- Admission stats -->
<div class="pmas-card mb-4">
    <div class="pmas-card-header">
        <i class="fas fa-file-alt"></i> Admission Overview
        <a href="<?= BASE_URL ?>pages/admin/admission/admission_applicants.php"
           class="btn-pmas ms-auto" style="font-size:11px;padding:4px 12px;">
            View All
        </a>
    </div>
    <div class="pmas-card-body p-0">
        <div class="row g-0">
            <div class="col-6 col-md-3 p-3 border-end">
                <div class="text-center">
                    <div style="font-size:28px;font-weight:700;color:var(--pmas-red);"><?= $stats['adm_pending'] ?></div>
                    <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Pending Review</div>
                </div>
            </div>
            <div class="col-6 col-md-3 p-3 border-end" style="<?= $stats['adm_bg_ready'] > 0 ? 'background:rgba(255,215,0,0.08)' : '' ?>">
                <div class="text-center">
                    <div style="font-size:28px;font-weight:700;color:<?= $stats['adm_bg_ready'] > 0 ? '#856404' : '#888' ?>;">
                        <?= $stats['adm_bg_ready'] ?>
                    </div>
                    <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">
                        Ready for Review<?= $stats['adm_bg_ready'] > 0 ? ' ⚠' : '' ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 p-3 border-end">
                <div class="text-center">
                    <div style="font-size:28px;font-weight:700;color:#155724;"><?= $stats['adm_passed'] ?></div>
                    <div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:0.5px;">Passed Admission</div>
                </div>
            </div>
            <div class="col-6 col-md-3 p-3">
                <div class="text-center">
                    <a href="<?= BASE_URL ?>pages/admin/admission/admission_results.php" class="btn-pmas w-100 justify-content-center">
                        <i class="fas fa-scroll me-1"></i> Results Board
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent tables -->
<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="pmas-card h-100">
            <div class="pmas-card-header">
                <i class="fas fa-gavel"></i> Recent Case Verdicts
            </div>
            <div class="pmas-card-body p-0">
                <div class="table-responsive">
                <table class="pmas-table">
                    <thead>
                        <tr>
                            <th>Case No.</th>
                            <th>Subject</th>
                            <th>Offense</th>
                            <th>Verdict</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recentCases)): ?>
                        <tr><td colspan="4" class="text-center py-3 text-muted">No cases found.</td></tr>
                    <?php else: foreach ($recentCases as $c): ?>
                        <tr>
                            <td><code><?= sanitize($c['case_number']) ?></code></td>
                            <td><?= sanitize($c['full_name']) ?></td>
                            <td style="font-size:12px;"><?= sanitize($c['offense_category']) ?></td>
                            <td><?= getStatusBadge($c['verdict']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-4">
        <div class="pmas-card h-100">
            <div class="pmas-card-header">
                <i class="fas fa-ban"></i> Recent Blacklisted
            </div>
            <div class="pmas-card-body p-0">
                <div class="table-responsive">
                <table class="pmas-table">
                    <thead>
                        <tr><th>Name</th><th>Type</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recentBlacklist)): ?>
                        <tr><td colspan="3" class="text-center py-3 text-muted">No records.</td></tr>
                    <?php else: foreach ($recentBlacklist as $b): ?>
                        <tr>
                            <td><?= sanitize($b['full_name']) ?></td>
                            <td><span class="badge bg-dark"><?= $b['blacklist_type'] ?></span></td>
                            <td><?= getStatusBadge($b['status']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($role === 'officer'): ?>
<!-- ===== OFFICER DASHBOARD ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card black">
            <div class="stat-card-icon"><i class="fas fa-gavel"></i></div>
            <div class="stat-card-info">
                <h3><?= $stats['my_cases'] ?></h3>
                <p>My Cases</p>
            </div>
        </div>
    </div>
    <?php if (!empty($op)): ?>
    <div class="col-6 col-md-4">
        <div class="stat-card <?= $op['license_status'] === 'valid' ? 'info' : 'red' ?>">
            <div class="stat-card-icon"><i class="fas fa-id-card"></i></div>
            <div class="stat-card-info">
                <h3 style="font-size:16px;"><?= strtoupper($op['license_status']) ?></h3>
                <p>License Status</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card gold">
            <div class="stat-card-icon"><i class="fas fa-calendar"></i></div>
            <div class="stat-card-info">
                <h3 style="font-size:14px;"><?= $op['license_expiry'] ? date('M d, Y', strtotime($op['license_expiry'])) : 'N/A' ?></h3>
                <p>License Expiry</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="pmas-card">
            <div class="pmas-card-header"><i class="fas fa-link"></i> Quick Links</div>
            <div class="pmas-card-body">
                <div class="d-flex flex-column gap-2">
                    <a href="<?= BASE_URL ?>pages/officer/my_license.php" class="btn-pmas">
                        <i class="fas fa-id-card me-2"></i> View My Officer License Card
                    </a>
                    <a href="<?= BASE_URL ?>pages/officer/my_cases.php" class="btn-pmas-dark">
                        <i class="fas fa-gavel me-2"></i> View My Case Verdict
                    </a>
                    <a href="<?= BASE_URL ?>pages/officer/blacklist.php" class="btn-pmas-outline">
                        <i class="fas fa-ban me-2"></i> View Blacklist (PAR &amp; PMA)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($role === 'cadet'): ?>
<!-- ===== CADET DASHBOARD ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card black">
            <div class="stat-card-icon"><i class="fas fa-gavel"></i></div>
            <div class="stat-card-info">
                <h3><?= $stats['my_cases'] ?></h3>
                <p>My Cases</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="pmas-card">
            <div class="pmas-card-header"><i class="fas fa-link"></i> Quick Links</div>
            <div class="pmas-card-body">
                <div class="d-flex flex-column gap-2">
                    <a href="<?= BASE_URL ?>pages/cadet/my_cases.php" class="btn-pmas-dark">
                        <i class="fas fa-gavel me-2"></i> View My Case Verdict
                    </a>
                    <a href="<?= BASE_URL ?>pages/cadet/my_id.php" class="btn-pmas-outline">
                        <i class="fas fa-id-badge me-2"></i> View My Cadet ID Card
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
