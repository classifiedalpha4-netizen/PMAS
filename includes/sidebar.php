<?php
$role = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- SIDEBAR -->
<div id="pmas-sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-shield-halved"></i>
        <span><?= strtoupper($role) ?></span>
    </div>
    <ul class="sidebar-nav">

        <!-- COMMON: Dashboard -->
        <li class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>

        <?php if ($role === 'admin'): ?>
        <!-- ========== ADMIN MENU ========== -->
        <li class="sidebar-section">USER MANAGEMENT</li>
        <li class="<?= in_array($currentPage, ['users.php','add_user.php','edit_user.php']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/users.php">
                <i class="fas fa-users"></i> Manage Users
            </a>
        </li>

        <li class="sidebar-section">RECORDS</li>
        <li class="<?= $currentPage === 'blacklist.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/blacklist.php">
                <i class="fas fa-ban"></i> Blacklist (PAR &amp; PMA)
            </a>
        </li>
        <li class="<?= $currentPage === 'cases.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/cases.php">
                <i class="fas fa-gavel"></i> Case Verdicts
            </a>
        </li>

        <li class="sidebar-section">CARDS &amp; LICENSES</li>
        <li class="<?= $currentPage === 'officer_licenses.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/officer_licenses.php">
                <i class="fas fa-id-card"></i> Officer Licenses
            </a>
        </li>
        <li class="<?= $currentPage === 'cadet_cards.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/cadet_cards.php">
                <i class="fas fa-id-badge"></i> Cadet ID Cards
            </a>
        </li>

        <li class="sidebar-section">ADMISSION</li>
        <li class="<?= in_array($currentPage, ['admission_batches.php','admission_applicants.php','admission_review.php','admission_results.php']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/admission/admission_applicants.php">
                <i class="fas fa-file-alt"></i> Applicants
            </a>
        </li>
        <li class="<?= $currentPage === 'admission_batches.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/admission/admission_batches.php">
                <i class="fas fa-calendar-alt"></i> Admission Batches
            </a>
        </li>
        <li class="<?= $currentPage === 'admission_results.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/admission/admission_results.php">
                <i class="fas fa-scroll"></i> Results Board
            </a>
        </li>

        <li class="sidebar-section">SYSTEM</li>
        <li class="<?= $currentPage === 'audit_log.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/audit_log.php">
                <i class="fas fa-clipboard-list"></i> Audit Log
            </a>
        </li>

        <?php elseif ($role === 'staff'): ?>
        <!-- ========== STAFF MENU ========== -->
        <li class="sidebar-section">RECORDS</li>
        <li class="<?= $currentPage === 'blacklist.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/staff/blacklist.php">
                <i class="fas fa-ban"></i> Blacklist (PAR &amp; PMA)
            </a>
        </li>
        <li class="<?= $currentPage === 'cases.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/staff/cases.php">
                <i class="fas fa-gavel"></i> Case Verdicts
            </a>
        </li>

        <li class="sidebar-section">CARDS &amp; LICENSES</li>
        <li class="<?= $currentPage === 'officer_licenses.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/staff/officer_licenses.php">
                <i class="fas fa-id-card"></i> Officer Licenses
            </a>
        </li>
        <li class="<?= $currentPage === 'cadet_cards.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/staff/cadet_cards.php">
                <i class="fas fa-id-badge"></i> Cadet ID Cards
            </a>
        </li>

        <li class="sidebar-section">ADMISSION</li>
        <li class="<?= in_array($currentPage, ['admission_applicants.php','admission_review.php','admission_results.php']) ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/admission/admission_applicants.php">
                <i class="fas fa-file-alt"></i> Applicants
            </a>
        </li>
        <li class="<?= $currentPage === 'admission_results.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/admin/admission/admission_results.php">
                <i class="fas fa-scroll"></i> Results Board
            </a>
        </li>

        <?php elseif ($role === 'officer'): ?>
        <!-- ========== OFFICER MENU ========== -->
        <li class="sidebar-section">MY RECORDS</li>
        <li class="<?= $currentPage === 'my_license.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/officer/my_license.php">
                <i class="fas fa-id-card"></i> My Officer License
            </a>
        </li>
        <li class="<?= $currentPage === 'my_cases.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/officer/my_cases.php">
                <i class="fas fa-gavel"></i> My Case Verdict
            </a>
        </li>

        <li class="sidebar-section">REGISTRY</li>
        <li class="<?= $currentPage === 'blacklist.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/officer/blacklist.php">
                <i class="fas fa-ban"></i> Blacklist (PAR &amp; PMA)
            </a>
        </li>

        <?php elseif ($role === 'cadet'): ?>
        <!-- ========== CADET MENU ========== -->
        <li class="sidebar-section">MY RECORDS</li>
        <li class="<?= $currentPage === 'my_cases.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/cadet/my_cases.php">
                <i class="fas fa-gavel"></i> My Case Verdict
            </a>
        </li>
        <li class="<?= $currentPage === 'my_id.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/cadet/my_id.php">
                <i class="fas fa-id-badge"></i> My Cadet ID Card
            </a>
        </li>
        <?php endif; ?>

        <!-- COMMON -->
        <li class="sidebar-section">ACCOUNT</li>
        <li class="<?= $currentPage === 'change_password.php' ? 'active' : '' ?>">
            <a href="<?= BASE_URL ?>pages/change_password.php">
                <i class="fas fa-key"></i> Change Password
            </a>
        </li>
        <li>
            <a href="<?= BASE_URL ?>logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>

    </ul>
</div>
<!-- /SIDEBAR -->

<!-- MAIN CONTENT WRAPPER -->
<div id="pmas-content">
