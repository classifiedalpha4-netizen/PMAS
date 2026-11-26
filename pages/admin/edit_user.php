<?php
require_once '../../includes/config.php';
requireRole('admin');
$pageTitle = 'Edit User';

$userId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$userId) redirect(BASE_URL . 'pages/admin/users.php');

$user = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();
if (!$user) redirect(BASE_URL . 'pages/admin/users.php');

$cadetProfile   = $conn->query("SELECT * FROM cadet_profiles  WHERE user_id=$userId")->fetch_assoc();
$officerProfile = $conn->query("SELECT * FROM officer_profiles WHERE user_id=$userId")->fetch_assoc();

// Helper: escape string for direct SQL, returns NULL literal for empty values
function esc($conn, $val) {
    return (!empty($val)) ? "'" . $conn->real_escape_string($val) . "'" : 'NULL';
}
// Always escape (never NULL) — for required string fields
function escStr($conn, $val) {
    return "'" . $conn->real_escape_string($val) . "'";
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Profile photo upload ----
    $profilePhoto   = $user['profile_photo'] ?? 'default.png';
    $photoUploadDir = __DIR__ . '/../../assets/img/profiles/';
    if (!is_dir($photoUploadDir)) mkdir($photoUploadDir, 0755, true);

    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) {
            $error = 'Profile photo must be JPG, PNG, GIF, or WEBP.';
        } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
            $error = 'Profile photo must be under 5MB.';
        } else {
            $safeName = 'user_' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $photoUploadDir . $safeName)) {
                if ($profilePhoto && $profilePhoto !== 'default.png' && file_exists($photoUploadDir . $profilePhoto)) {
                    unlink($photoUploadDir . $profilePhoto);
                }
                $conn->query("UPDATE users SET profile_photo='" . $conn->real_escape_string($safeName) . "' WHERE id=$userId");
            }
        }
    }

    // ---- Core user fields ----
    $full_name       = sanitize($_POST['full_name']);
    $rank            = sanitize($_POST['rank']);
    $unit            = sanitize($_POST['unit']);
    $email           = sanitize($_POST['email']);
    $roblox_username = sanitize($_POST['roblox_username'] ?? '');
    $is_active       = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE users SET full_name=?, `rank`=?, unit=?, email=?, roblox_username=?, is_active=? WHERE id=?");
    $stmt->bind_param('sssssis', $full_name, $rank, $unit, $email, $roblox_username, $is_active, $userId);
    $stmt->execute(); $stmt->close();

    // ---- Cadet profile (null-safe SQL for date columns) ----
    if ($user['role'] === 'cadet') {
        $cadetId   = sanitize($_POST['cadet_id']      ?? '');
        $classYear = sanitize($_POST['class_year']    ?? '');
        $company   = sanitize($_POST['company']       ?? '');
        $battalion = sanitize($_POST['battalion']     ?? '');
        $enrolled  = sanitize($_POST['date_enrolled'] ?? '');
        $cStatus   = sanitize($_POST['cadet_status']  ?? 'active');

        $cadetIdSql  = escStr($conn, $cadetId);
        $classYrSql  = esc($conn, $classYear);
        $companySql  = esc($conn, $company);
        $battnSql    = esc($conn, $battalion);
        $enrolledSql = esc($conn, $enrolled);
        $statusSql   = escStr($conn, $cStatus);

        if ($cadetProfile) {
            $conn->query("UPDATE cadet_profiles SET cadet_id=$cadetIdSql, class_year=$classYrSql, company=$companySql, battalion=$battnSql, date_enrolled=$enrolledSql, status=$statusSql WHERE user_id=$userId");
        } else {
            $conn->query("INSERT INTO cadet_profiles (user_id,cadet_id,class_year,company,battalion,date_enrolled,status) VALUES ($userId,$cadetIdSql,$classYrSql,$companySql,$battnSql,$enrolledSql,$statusSql)");
        }
    }

    // ---- Officer profile (null-safe SQL for date columns) ----
    if ($user['role'] === 'officer') {
        $officerId  = sanitize($_POST['officer_id']          ?? '');
        $classYear  = sanitize($_POST['class_year']          ?? '');
        $branch     = sanitize($_POST['branch_of_service']   ?? '');
        $assignment = sanitize($_POST['current_assignment']  ?? '');
        $licNum     = sanitize($_POST['license_number']      ?? '');
        $certNum    = sanitize($_POST['certificate_number']  ?? '');
        $transNum   = sanitize($_POST['transition_number']   ?? '');
        $licIssued  = sanitize($_POST['license_issued']      ?? '');
        $licExpiry  = sanitize($_POST['license_expiry']      ?? '');
        $licStatus  = sanitize($_POST['license_status']      ?? 'valid');

        $officerIdSql = escStr($conn, $officerId);
        $classYrSql   = esc($conn, $classYear);
        $branchSql    = esc($conn, $branch);
        $assignSql    = esc($conn, $assignment);
        $licNumSql    = esc($conn, $licNum);
        $certNumSql   = esc($conn, $certNum);
        $transNumSql  = esc($conn, $transNum);
        $licIssuedSql = esc($conn, $licIssued);
        $licExpirySql = esc($conn, $licExpiry);
        $licStatusSql = escStr($conn, $licStatus);

        if ($officerProfile) {
            $conn->query("UPDATE officer_profiles SET officer_id=$officerIdSql, class_year=$classYrSql, branch_of_service=$branchSql, current_assignment=$assignSql, license_number=$licNumSql, certificate_number=$certNumSql, transition_number=$transNumSql, license_issued=$licIssuedSql, license_expiry=$licExpirySql, license_status=$licStatusSql WHERE user_id=$userId");
        } else {
            $conn->query("INSERT INTO officer_profiles (user_id,officer_id,class_year,branch_of_service,current_assignment,license_number,certificate_number,transition_number,license_issued,license_expiry,license_status) VALUES ($userId,$officerIdSql,$classYrSql,$branchSql,$assignSql,$licNumSql,$certNumSql,$transNumSql,$licIssuedSql,$licExpirySql,$licStatusSql)");
        }
    }

    // ---- Optional password change ----
    if (!empty($_POST['new_password'])) {
        if (strlen($_POST['new_password']) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            $hash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
            $conn->query("UPDATE users SET password='" . $conn->real_escape_string($hash) . "' WHERE id=$userId");
        }
    }

    if (!$error) {
        logAction($conn, $_SESSION['user_id'], 'EDIT_USER', 'Users', "Edited user ID $userId");
        redirect(BASE_URL . 'pages/admin/users.php?saved=1');
    }

    // Refresh
    $user           = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();
    $cadetProfile   = $conn->query("SELECT * FROM cadet_profiles  WHERE user_id=$userId")->fetch_assoc();
    $officerProfile = $conn->query("SELECT * FROM officer_profiles WHERE user_id=$userId")->fetch_assoc();
}

include '../../includes/header.php'; include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-user-edit me-2"></i> Edit User</h2>
        <p>Editing: <strong><?= sanitize($user['full_name']) ?></strong>
           &mdash; <code><?= sanitize($user['username']) ?></code></p>
    </div>
    <a href="<?= BASE_URL ?>pages/admin/users.php" class="btn-pmas-dark">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<?php if ($error): ?>
<div class="pmas-alert pmas-alert-danger"><i class="fas fa-times-circle"></i> <?= $error ?></div>
<?php endif; ?>

<div class="pmas-card">
    <div class="pmas-card-header"><i class="fas fa-user-edit"></i> Account Information</div>
    <div class="pmas-card-body">
        <form method="POST" enctype="multipart/form-data">
            <!-- ===== PROFILE PHOTO ===== -->
            <div class="row g-3 mb-3">
                <div class="col-12">
                    <div class="pmas-card-header" style="background:linear-gradient(90deg,var(--pmas-dark),var(--pmas-red-dark));border-radius:8px;margin-bottom:12px;">
                        <i class="fas fa-camera"></i> Profile Photo
                        <small class="ms-2 fw-normal" style="font-size:11px;opacity:0.7;">(shown on Officer License Card &amp; Cadet ID Card)</small>
                    </div>
                </div>
                <div class="col-auto">
                    <?php
                    $photoDir = __DIR__ . '/../../assets/img/profiles/';
                    $photoSrc = (!empty($user['profile_photo']) && $user['profile_photo'] !== 'default.png' && file_exists($photoDir . $user['profile_photo']))
                        ? BASE_URL . 'assets/img/profiles/' . urlencode($user['profile_photo'])
                        : null;
                    ?>
                    <div id="photoPreviewBox" style="width:80px;height:100px;border-radius:6px;border:2px solid var(--pmas-gold);background:var(--pmas-dark);overflow:hidden;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,0.4);font-size:30px;">
                        <?php if ($photoSrc): ?>
                        <img id="photoPreview" src="<?= $photoSrc ?>" style="width:100%;height:100%;object-fit:cover;object-position:center top;" alt="photo">
                        <?php else: ?>
                        <i class="fas fa-user" id="photoIcon"></i>
                        <img id="photoPreview" src="" style="width:100%;height:100%;object-fit:cover;object-position:center top;display:none;" alt="photo">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col">
                    <label class="pmas-form-label">Upload New Photo
                        <small class="text-muted fw-normal">(JPG, PNG, WEBP — max 5MB)</small>
                    </label>
                    <input type="file" name="profile_photo" id="profilePhotoInput"
                           class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp">
                    <div class="form-text">
                        Photo will be displayed on the
                        <?= $user['role'] === 'officer' ? 'Officer License Card' : ($user['role'] === 'cadet' ? 'Cadet ID Card' : 'ID/License Card') ?>.
                    </div>
                </div>
            </div>
            <hr class="separator-red">

            <!-- ===== CORE FIELDS ===== -->
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="pmas-form-label">Username (System Login)</label>
                    <input type="text" class="form-control" value="<?= sanitize($user['username']) ?>" disabled>
                    <div class="form-text">Username cannot be changed.</div>
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">Role</label>
                    <input type="text" class="form-control" value="<?= strtoupper($user['role']) ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                               <?= $user['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="pmas-form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control"
                           value="<?= sanitize($user['full_name']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">Rank / Title</label>
                    <input type="text" name="rank" class="form-control"
                           value="<?= sanitize($user['rank'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">Unit / Department</label>
                    <input type="text" name="unit" class="form-control"
                           value="<?= sanitize($user['unit'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= sanitize($user['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">
                        <i class="fas fa-gamepad me-1 text-pmas-red"></i> Roblox Username
                        <small class="text-muted fw-normal">(shown on ID/License cards)</small>
                    </label>
                    <input type="text" name="roblox_username" class="form-control"
                           placeholder="e.g. PlayerName123"
                           value="<?= sanitize($user['roblox_username'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">New Password <small class="text-muted fw-normal">(leave blank to keep)</small></label>
                    <input type="password" name="new_password" class="form-control" minlength="8">
                </div>
            </div>

            <!-- ===== CADET PROFILE ===== -->
            <?php if ($user['role'] === 'cadet'): ?>
            <hr class="separator-red mt-4">
            <h6 class="text-pmas-red fw-bold mb-3">
                <i class="fas fa-id-badge me-2"></i>Cadet Profile
                <small class="text-muted fw-normal ms-2" style="font-size:11px;">— changes reflect on the Cadet ID Card immediately</small>
            </h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="pmas-form-label">Cadet ID</label>
                    <input type="text" name="cadet_id" class="form-control"
                           placeholder="e.g. B01-001"
                           value="<?= sanitize($cadetProfile['cadet_id'] ?? $user['username']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">Class Year</label>
                    <input type="text" name="class_year" class="form-control"
                           value="<?= sanitize($cadetProfile['class_year'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="pmas-form-label">Company</label>
                    <input type="text" name="company" class="form-control"
                           value="<?= sanitize($cadetProfile['company'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="pmas-form-label">Battalion</label>
                    <input type="text" name="battalion" class="form-control"
                           value="<?= sanitize($cadetProfile['battalion'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="pmas-form-label">Date Enrolled</label>
                    <input type="date" name="date_enrolled" class="form-control"
                           value="<?= $cadetProfile['date_enrolled'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">Cadet Status</label>
                    <select name="cadet_status" class="form-select">
                        <?php foreach (['active','graduated','dismissed','resigned'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($cadetProfile['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== OFFICER PROFILE ===== -->
            <?php if ($user['role'] === 'officer'): ?>
            <hr class="separator-red mt-4">
            <h6 class="text-pmas-red fw-bold mb-3">
                <i class="fas fa-id-card me-2"></i>Officer Profile &amp; License
                <small class="text-muted fw-normal ms-2" style="font-size:11px;">— changes reflect on the Officer License Card immediately</small>
            </h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="pmas-form-label">Officer ID</label>
                    <input type="text" name="officer_id" class="form-control"
                           value="<?= sanitize($officerProfile['officer_id'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">Class Year</label>
                    <input type="text" name="class_year" class="form-control"
                           value="<?= sanitize($officerProfile['class_year'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">Branch of Service</label>
                    <input type="text" name="branch_of_service" class="form-control"
                           value="<?= sanitize($officerProfile['branch_of_service'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">Current Assignment</label>
                    <input type="text" name="current_assignment" class="form-control"
                           value="<?= sanitize($officerProfile['current_assignment'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">License Number <small class="text-muted fw-normal">(auto-generated if blank)</small></label>
                    <input type="text" name="license_number" class="form-control"
                           value="<?= sanitize($officerProfile['license_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">Certificate Number <small class="text-muted fw-normal">(auto-generated if blank)</small></label>
                    <input type="text" name="certificate_number" class="form-control"
                           value="<?= sanitize($officerProfile['certificate_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">Transitioned Number</label>
                    <input type="text" name="transition_number" class="form-control"
                           value="<?= sanitize($officerProfile['transition_number'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">License Issued</label>
                    <input type="date" name="license_issued" class="form-control"
                           value="<?= $officerProfile['license_issued'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">License Expiry</label>
                    <input type="date" name="license_expiry" class="form-control"
                           value="<?= $officerProfile['license_expiry'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">License Status</label>
                    <select name="license_status" class="form-select">
                        <?php foreach (['valid','expired','revoked','suspended'] as $ls): ?>
                        <option value="<?= $ls ?>" <?= ($officerProfile['license_status'] ?? 'valid') === $ls ? 'selected' : '' ?>><?= ucfirst($ls) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-4">
                <button type="submit" class="btn-pmas">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
                <a href="<?= BASE_URL ?>pages/admin/users.php" class="btn-pmas-dark ms-2">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.getElementById('profilePhotoInput').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        alert('Photo exceeds 5MB limit.');
        this.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function (e) {
        const preview = document.getElementById('photoPreview');
        const icon    = document.getElementById('photoIcon');
        preview.src = e.target.result;
        preview.style.display = 'block';
        if (icon) icon.style.display = 'none';
    };
    reader.readAsDataURL(file);
});
</script>
