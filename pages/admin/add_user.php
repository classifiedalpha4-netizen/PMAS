<?php
require_once '../../includes/config.php';
requireRole('admin');
$pageTitle = 'Add User';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = sanitize($_POST['username']);
    $password  = $_POST['password'];
    $full_name = sanitize($_POST['full_name']);
    $role      = sanitize($_POST['role']);
    $rank      = sanitize($_POST['rank']);
    $unit      = sanitize($_POST['unit']);
    $email     = sanitize($_POST['email']);
    $roblox_username = sanitize($_POST['roblox_username'] ?? '');

    // Check duplicate username
    $ck = $conn->prepare("SELECT id FROM users WHERE username=?");
    $ck->bind_param('s', $username);
    $ck->execute();
    $ck->store_result();

    if ($ck->num_rows > 0) {
        $error = 'Username already exists.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO users (username,password,role,full_name,rank,unit,email,roblox_username) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssss', $username, $hash, $role, $full_name, $rank, $unit, $email, $roblox_username);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        // Create profile depending on role
        if ($role === 'cadet') {
            $cadetId   = sanitize($_POST['cadet_id']);
            $classYear = sanitize($_POST['class_year']);
            $company   = sanitize($_POST['company']);
            $battalion = sanitize($_POST['battalion']);
            $enrolled  = sanitize($_POST['date_enrolled']);
            $ps = $conn->prepare("INSERT INTO cadet_profiles (user_id,cadet_id,class_year,company,battalion,date_enrolled) VALUES (?,?,?,?,?,?)");
            $ps->bind_param('isssss', $newId, $cadetId, $classYear, $company, $battalion, $enrolled);
            $ps->execute();
            $ps->close();
        }

        if ($role === 'officer') {
            $officerId   = sanitize($_POST['officer_id']);
            $classYear   = sanitize($_POST['class_year']);
            $branch      = sanitize($_POST['branch_of_service']);
            $assignment  = sanitize($_POST['current_assignment']);
            $licNum      = sanitize($_POST['license_number']);
            $licIssued   = sanitize($_POST['license_issued']);
            $licExpiry   = sanitize($_POST['license_expiry']);
            $licStatus   = sanitize($_POST['license_status']);
            $ps = $conn->prepare("INSERT INTO officer_profiles (user_id,officer_id,class_year,branch_of_service,current_assignment,license_number,license_issued,license_expiry,license_status) VALUES (?,?,?,?,?,?,?,?,?)");
            $ps->bind_param('issssssss', $newId, $officerId, $classYear, $branch, $assignment, $licNum, $licIssued, $licExpiry, $licStatus);
            $ps->execute();
            $ps->close();
        }

        logAction($conn, $_SESSION['user_id'], 'ADD_USER', 'Users', "Added user: $username ($role)");
        redirect(BASE_URL . 'pages/admin/users.php?saved=1');
    }
}

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-user-plus me-2"></i> Add New User</h2>
        <p>Create a new system account</p>
    </div>
    <a href="<?= BASE_URL ?>pages/admin/users.php" class="btn-pmas-dark">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>

<?php if ($error): ?>
<div class="pmas-alert pmas-alert-danger"><i class="fas fa-times-circle"></i> <?= $error ?></div>
<?php endif; ?>

<div class="pmas-card">
    <div class="pmas-card-header"><i class="fas fa-user-plus"></i> Account Information</div>
    <div class="pmas-card-body">
        <form method="POST" id="addUserForm">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="pmas-form-label">Username *</label>
                    <input type="text" name="username" class="form-control"
                           placeholder="e.g. B01-001 / STF-002"
                           value="<?= sanitize($_POST['username'] ?? '') ?>" required>
                    <div class="form-text">For cadets: use Cadet Number format (e.g. B01-001)</div>
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">Password *</label>
                    <input type="password" name="password" class="form-control" minlength="8" required>
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">Role *</label>
                    <select name="role" class="form-select" id="roleSelect" required>
                        <option value="">-- Select Role --</option>
                        <option value="admin">Admin</option>
                        <option value="staff">Staff</option>
                        <option value="officer">Officer (Graduate)</option>
                        <option value="cadet">Cadet</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="pmas-form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required value="<?= sanitize($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">Rank / Title</label>
                    <input type="text" name="rank" class="form-control" value="<?= sanitize($_POST['rank'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="pmas-form-label">Unit / Department</label>
                    <input type="text" name="unit" class="form-control" value="<?= sanitize($_POST['unit'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= sanitize($_POST['email'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="pmas-form-label">
                        <i class="fas fa-gamepad me-1 text-pmas-red"></i> Roblox Username
                    </label>
                    <input type="text" name="roblox_username" class="form-control"
                           placeholder="e.g. PlayerName123"
                           value="<?= sanitize($_POST['roblox_username'] ?? '') ?>">
                </div>
            </div>

            <!-- Cadet Fields -->
            <div id="cadetFields" style="display:none;">
                <hr class="separator-red">
                <h6 class="text-pmas-red fw-bold mb-3"><i class="fas fa-id-badge me-2"></i>Cadet Profile</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="pmas-form-label">Cadet ID *</label>
                        <input type="text" name="cadet_id" class="form-control" placeholder="e.g. B01-001">
                    </div>
                    <div class="col-md-3">
                        <label class="pmas-form-label">Class Year</label>
                        <input type="text" name="class_year" class="form-control" placeholder="e.g. 2027">
                    </div>
                    <div class="col-md-2">
                        <label class="pmas-form-label">Company</label>
                        <input type="text" name="company" class="form-control" placeholder="e.g. Alpha">
                    </div>
                    <div class="col-md-2">
                        <label class="pmas-form-label">Battalion</label>
                        <input type="text" name="battalion" class="form-control" placeholder="e.g. 1st">
                    </div>
                    <div class="col-md-2">
                        <label class="pmas-form-label">Date Enrolled</label>
                        <input type="date" name="date_enrolled" class="form-control">
                    </div>
                </div>
            </div>

            <!-- Officer Fields -->
            <div id="officerFields" style="display:none;">
                <hr class="separator-red">
                <h6 class="text-pmas-red fw-bold mb-3"><i class="fas fa-id-card me-2"></i>Officer Profile & License</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="pmas-form-label">Officer ID *</label>
                        <input type="text" name="officer_id" class="form-control" placeholder="OFF-2024-001">
                    </div>
                    <div class="col-md-2">
                        <label class="pmas-form-label">Class Year</label>
                        <input type="text" name="class_year" class="form-control" placeholder="2024">
                    </div>
                    <div class="col-md-3">
                        <label class="pmas-form-label">Branch of Service</label>
                        <input type="text" name="branch_of_service" class="form-control" placeholder="Infantry">
                    </div>
                    <div class="col-md-4">
                        <label class="pmas-form-label">Current Assignment</label>
                        <input type="text" name="current_assignment" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="pmas-form-label">License Number</label>
                        <input type="text" name="license_number" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="pmas-form-label">License Issued</label>
                        <input type="date" name="license_issued" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="pmas-form-label">License Expiry</label>
                        <input type="date" name="license_expiry" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="pmas-form-label">License Status</label>
                        <select name="license_status" class="form-select">
                            <option value="valid">Valid</option>
                            <option value="expired">Expired</option>
                            <option value="revoked">Revoked</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn-pmas">
                    <i class="fas fa-save me-1"></i> Save User
                </button>
                <a href="<?= BASE_URL ?>pages/admin/users.php" class="btn-pmas-dark ms-2">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('roleSelect').addEventListener('change', function () {
    document.getElementById('cadetFields').style.display  = this.value === 'cadet'   ? 'block' : 'none';
    document.getElementById('officerFields').style.display = this.value === 'officer' ? 'block' : 'none';
});
// Trigger on page load if POST error
(function(){ const v = document.getElementById('roleSelect').value;
    if (v) document.getElementById('roleSelect').dispatchEvent(new Event('change')); })();
</script>

<?php include '../../includes/footer.php'; ?>
