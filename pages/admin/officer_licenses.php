<?php
require_once '../../includes/config.php';
requireRole('admin','staff');
$pageTitle = 'Officer License Cards';

// ---- Auto-create tables ----
$conn->query("
    CREATE TABLE IF NOT EXISTS license_signatures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sig_order INT NOT NULL DEFAULT 1,
        signatory_username VARCHAR(100) NOT NULL,
        signatory_position VARCHAR(150) NOT NULL,
        signature_image VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
");

// Add new columns if not exist (MySQL-compatible)
$_c1 = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='officer_profiles' AND COLUMN_NAME='certificate_number'");
if ($_c1 && $_c1->num_rows === 0) $conn->query("ALTER TABLE officer_profiles ADD COLUMN certificate_number VARCHAR(40) DEFAULT NULL AFTER license_number");
$_c2 = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='officer_profiles' AND COLUMN_NAME='transition_number'");
if ($_c2 && $_c2->num_rows === 0) $conn->query("ALTER TABLE officer_profiles ADD COLUMN transition_number VARCHAR(40) DEFAULT NULL AFTER certificate_number");

// ---- Upload dir ----
define('SIG_UPLOAD_DIR', __DIR__ . '/../../uploads/signatures/');
if (!is_dir(SIG_UPLOAD_DIR)) mkdir(SIG_UPLOAD_DIR, 0755, true);

// ---- Handle signature save ----
$sigSuccess = $sigError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_signature'])) {
    $sigId       = (int)($_POST['sig_id'] ?? 0);
    $sigOrder    = (int)($_POST['sig_order'] ?? 1);
    $sigUsername = sanitize($_POST['signatory_username']);
    $sigPosition = sanitize($_POST['signatory_position']);
    $sigActive   = isset($_POST['is_active']) ? 1 : 0;
    $createdBy   = $_SESSION['user_id'];

    // Handle image upload
    $sigImage = sanitize($_POST['existing_sig_image'] ?? '');
    if (!empty($_FILES['signature_image']['name']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['signature_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','gif'])) {
            $sigError = 'Signature image must be PNG, JPG, or GIF.';
        } elseif ($_FILES['signature_image']['size'] > 2 * 1024 * 1024) {
            $sigError = 'Signature image must be under 2MB.';
        } else {
            $safeName = 'sig_' . time() . '_' . $sigOrder . '.' . $ext;
            if (move_uploaded_file($_FILES['signature_image']['tmp_name'], SIG_UPLOAD_DIR . $safeName)) {
                // Delete old image
                if ($sigImage && file_exists(SIG_UPLOAD_DIR . $sigImage)) unlink(SIG_UPLOAD_DIR . $sigImage);
                $sigImage = $safeName;
            }
        }
    }

    if (!$sigError) {
        if ($sigId > 0) {
            $st = $conn->prepare("UPDATE license_signatures SET sig_order=?,signatory_username=?,signatory_position=?,signature_image=?,is_active=? WHERE id=?");
            $st->bind_param('isssii', $sigOrder,$sigUsername,$sigPosition,$sigImage,$sigActive,$sigId);
        } else {
            $st = $conn->prepare("INSERT INTO license_signatures (sig_order,signatory_username,signatory_position,signature_image,is_active,created_by) VALUES (?,?,?,?,?,?)");
            $st->bind_param('isssii', $sigOrder,$sigUsername,$sigPosition,$sigImage,$sigActive,$createdBy);
        }
        $st->execute(); $st->close();
        logAction($conn, $_SESSION['user_id'], 'SAVE_SIGNATURE', 'Licenses', "$sigUsername");
        $sigSuccess = 'Signatory saved successfully.';
    }
}

// ---- Delete signature ----
if (isset($_GET['del_sig']) && is_numeric($_GET['del_sig'])) {
    $delSig = (int)$_GET['del_sig'];
    $sv = $conn->query("SELECT signature_image FROM license_signatures WHERE id=$delSig")->fetch_assoc();
    if ($sv && $sv['signature_image'] && file_exists(SIG_UPLOAD_DIR . $sv['signature_image'])) {
        unlink(SIG_UPLOAD_DIR . $sv['signature_image']);
    }
    $conn->query("DELETE FROM license_signatures WHERE id=$delSig");
    redirect(BASE_URL . 'pages/admin/officer_licenses.php');
}

// ---- Edit signature ----
$editSig = null;
if (isset($_GET['edit_sig']) && is_numeric($_GET['edit_sig'])) {
    $editSig = $conn->query("SELECT * FROM license_signatures WHERE id=" . (int)$_GET['edit_sig'])->fetch_assoc();
}

// ---- Fetch data ----
$officers = $conn->query("
    SELECT u.id, u.username, u.full_name, u.rank, u.unit, u.profile_photo,
           u.roblox_username,
           op.officer_id, op.class_year, op.branch_of_service,
           op.current_assignment, op.license_number,
           op.certificate_number, op.transition_number,
           op.license_issued, op.license_expiry, op.license_status
    FROM users u
    JOIN officer_profiles op ON u.id = op.user_id
    WHERE u.role = 'officer' AND u.is_active = 1
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

$signatures = $conn->query("SELECT * FROM license_signatures WHERE is_active=1 ORDER BY sig_order ASC")->fetch_all(MYSQLI_ASSOC);
$allSignatures = $conn->query("SELECT * FROM license_signatures ORDER BY sig_order ASC")->fetch_all(MYSQLI_ASSOC);

// Auto-generate license number helper
function generateLicenseNumber($conn, $officerId) {
    $year = date('Y');
    return 'PMA-LIC-' . $year . '-' . str_pad($officerId, 4, '0', STR_PAD_LEFT);
}

// View single
$viewId  = isset($_GET['view']) && is_numeric($_GET['view']) ? (int)$_GET['view'] : 0;
$officer = null;
if ($viewId > 0) {
    foreach ($officers as $o) {
        if ($o['id'] == $viewId) { $officer = $o; break; }
    }
}

include '../../includes/header.php'; include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-id-card me-2"></i> Officer License Cards</h2>
        <p>View, print, and manage officer license cards &amp; signatories</p>
    </div>
</div>

<?php if ($sigSuccess): ?>
<div class="pmas-alert pmas-alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i> <?= $sigSuccess ?></div>
<?php endif; ?>
<?php if ($sigError): ?>
<div class="pmas-alert pmas-alert-danger"><i class="fas fa-times-circle"></i> <?= $sigError ?></div>
<?php endif; ?>

<?php if ($officer): ?>
<!-- ==================== SINGLE CARD VIEW ==================== -->
<div class="row justify-content-center">
    <div class="col-auto text-center">
        <?php
        // Auto-generate license number if empty
        $licNum = $officer['license_number'] ?: generateLicenseNumber($conn, $officer['id']);
        $certNum = $officer['certificate_number'] ?: ('PMA-CERT-' . date('Y') . '-' . str_pad($officer['id'], 4, '0', STR_PAD_LEFT));
        ?>
        <div class="officer-license-card mb-3">
            <!-- PMA watermark -->
            <div class="officer-card-logo-watermark">
                <img src="<?= BASE_URL ?>assets/img/pma_logo.php" alt="">
            </div>
            <!-- Header -->
            <div class="officer-card-header">
                <div class="pma-emblem">
                    <img src="<?= BASE_URL ?>assets/img/pma_logo.php" alt="PMA">
                </div>
                <div class="header-text">
                    <div class="title">Philippine Military Academy</div>
                    <div class="sub">Official Officer License Card &mdash; Philippine Army Roleplay</div>
                </div>
            </div>

            <!-- Body -->
            <div class="officer-card-body">
                <!-- Photo -->
                <div class="officer-card-photo">
                    <?php
                    $photoDir = __DIR__ . '/../../assets/img/profiles/';
                    $photoFile = $officer['profile_photo'] ?? '';
                    if ($photoFile && $photoFile !== 'default.png' && file_exists($photoDir . $photoFile)):
                    ?>
                    <img src="<?= BASE_URL ?>assets/img/profiles/<?= urlencode($photoFile) ?>" alt="photo">
                    <?php else: ?>
                    <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <!-- Info -->
                <div class="officer-card-info">
                    <div class="c-name"><?= sanitize($officer['full_name']) ?></div>
                    <div class="c-rank"><?= sanitize($officer['rank'] ?? 'Officer') ?></div>
                    <div class="c-row"><span>Officer ID</span><span><?= sanitize($officer['officer_id']) ?></span></div>
                    <div class="c-row"><span>Roblox Username</span><span><?= sanitize($officer['roblox_username'] ?? $officer['username']) ?></span></div>
                    <div class="c-row"><span>Rank</span><span><?= sanitize($officer['rank'] ?? 'â€”') ?></span></div>
                    <div class="c-row"><span>Cert. No.</span><span><?= sanitize($certNum) ?></span></div>
                    <div class="c-row"><span>Lic. No.</span><span><?= sanitize($licNum) ?></span></div>
                    <div class="c-row"><span>Trans. No.</span><span><?= sanitize($officer['transition_number'] ?? 'â€”') ?></span></div>
                </div>
            </div>

            <!-- Signatures -->
            <?php if (!empty($signatures)): ?>
            <div class="officer-card-signatures">
                <?php foreach ($signatures as $sig): ?>
                <div class="officer-sig-block">
                    <div class="officer-sig-img">
                        <?php if ($sig['signature_image'] && file_exists(SIG_UPLOAD_DIR . $sig['signature_image'])): ?>
                        <img src="<?= BASE_URL ?>uploads/signatures/<?= urlencode($sig['signature_image']) ?>" alt="sig">
                        <?php else: ?>
                        <div style="height:28px;border-bottom:1px dashed rgba(255,255,255,0.3);width:80px;"></div>
                        <?php endif; ?>
                    </div>
                    <div class="officer-sig-line"></div>
                    <div class="officer-sig-name"><?= sanitize($sig['signatory_username']) ?></div>
                    <div class="officer-sig-pos"><?= sanitize($sig['signatory_position']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="officer-card-footer">
                <div class="lic-num">LIC: <?= sanitize($licNum) ?></div>
                <div><?= getStatusBadge($officer['license_status']) ?></div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-center no-print mb-4">
            <button class="btn-pmas print-btn"><i class="fas fa-print me-1"></i> Print Card</button>
            <a href="<?= BASE_URL ?>pages/admin/officer_licenses.php" class="btn-pmas-dark">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ==================== MANAGE SIGNATORIES ==================== -->
<div class="row mb-4">
    <div class="col-lg-4">
        <div class="pmas-card">
            <div class="pmas-card-header">
                <i class="fas fa-<?= $editSig ? 'edit' : 'plus' ?>"></i>
                <?= $editSig ? 'Edit Signatory' : 'Add Signatory' ?>
            </div>
            <div class="pmas-card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="save_signature" value="1">
                    <input type="hidden" name="sig_id" value="<?= $editSig['id'] ?? 0 ?>">
                    <input type="hidden" name="existing_sig_image" value="<?= sanitize($editSig['signature_image'] ?? '') ?>">

                    <div class="mb-3">
                        <label class="pmas-form-label">Order <small class="text-muted">(1 = highest / HICOMS first)</small></label>
                        <input type="number" name="sig_order" class="form-control" min="1" max="10"
                               value="<?= $editSig['sig_order'] ?? (count($allSignatures) + 1) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Signatory Name / Username *</label>
                        <input type="text" name="signatory_username" class="form-control"
                               placeholder="e.g. PMA ADMIN HICOMS"
                               value="<?= sanitize($editSig['signatory_username'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Position / Title *</label>
                        <input type="text" name="signatory_position" class="form-control"
                               placeholder="e.g. Commandant, Philippine Military Academy"
                               value="<?= sanitize($editSig['signatory_position'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">
                            Signature Image
                            <small class="text-muted fw-normal">(PNG/JPG, max 2MB, transparent PNG recommended)</small>
                        </label>
                        <?php if (!empty($editSig['signature_image']) && file_exists(SIG_UPLOAD_DIR . $editSig['signature_image'])): ?>
                        <div class="mb-2 p-2 rounded" style="background:#1a1a1a;">
                            <img src="<?= BASE_URL ?>uploads/signatures/<?= urlencode($editSig['signature_image']) ?>"
                                 style="max-height:50px;filter:brightness(0) invert(1);" alt="Current signature">
                            <div style="font-size:11px;color:#888;margin-top:4px;">Current signature â€” upload a new one to replace</div>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="signature_image" class="form-control"
                               accept=".png,.jpg,.jpeg,.gif">
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="sigActive"
                                   <?= ($editSig['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sigActive">Active (show on cards)</label>
                        </div>
                    </div>
                    <button type="submit" class="btn-pmas w-100">
                        <i class="fas fa-save me-1"></i> <?= $editSig ? 'Update' : 'Add Signatory' ?>
                    </button>
                    <?php if ($editSig): ?>
                    <a href="<?= BASE_URL ?>pages/admin/officer_licenses.php"
                       class="btn-pmas-dark w-100 mt-2 justify-content-center">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="pmas-card">
            <div class="pmas-card-header"><i class="fas fa-signature"></i> Signatories on License Cards</div>
            <div class="pmas-card-body p-0">
                <?php if (empty($allSignatures)): ?>
                <p class="text-center text-muted py-4">No signatories yet. Add one to appear on license cards.</p>
                <?php else: ?>
                <table class="pmas-table">
                    <thead>
                        <tr>
                            <th class="text-center">Order</th>
                            <th>Name / Username</th>
                            <th>Position</th>
                            <th>Signature</th>
                            <th class="text-center">Active</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allSignatures as $sig): ?>
                    <tr>
                        <td class="text-center"><strong><?= $sig['sig_order'] ?></strong></td>
                        <td><strong><?= sanitize($sig['signatory_username']) ?></strong></td>
                        <td style="font-size:12px;"><?= sanitize($sig['signatory_position']) ?></td>
                        <td>
                            <?php if ($sig['signature_image'] && file_exists(SIG_UPLOAD_DIR . $sig['signature_image'])): ?>
                            <img src="<?= BASE_URL ?>uploads/signatures/<?= urlencode($sig['signature_image']) ?>"
                                 style="max-height:30px;max-width:80px;background:#222;padding:2px;border-radius:3px;" alt="sig">
                            <?php else: ?>
                            <span class="text-muted" style="font-size:11px;">No image</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?= $sig['is_active']
                                ? '<span class="badge bg-success">Yes</span>'
                                : '<span class="badge bg-secondary">No</span>' ?>
                        </td>
                        <td>
                            <a href="?edit_sig=<?= $sig['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?del_sig=<?= $sig['id'] ?>" class="btn btn-sm btn-outline-danger"
                               data-confirm="Delete signatory '<?= sanitize($sig['signatory_username']) ?>'?">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== OFFICER LIST ==================== -->
<div class="pmas-card-header pmas-card mb-3" style="border-radius:8px;overflow:hidden;padding:0;">
    <div class="pmas-card-header">
        <i class="fas fa-id-card"></i> All Officer License Cards (<?= count($officers) ?>)
    </div>
</div>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
<?php foreach ($officers as $o):
    $licNum  = $o['license_number'] ?: generateLicenseNumber($conn, $o['id']);
    $certNum = $o['certificate_number'] ?: ('PMA-CERT-' . date('Y') . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT));
?>
<div class="col">
    <div class="officer-license-card" style="width:100%;">
        <!-- PMA watermark -->
        <div class="officer-card-logo-watermark">
            <img src="<?= BASE_URL ?>assets/img/pma_logo.php" alt="">
        </div>
        <!-- Header -->
        <div class="officer-card-header">
            <div class="pma-emblem">
                <img src="<?= BASE_URL ?>assets/img/pma_logo.php" alt="PMA">
            </div>
            <div class="header-text">
                <div class="title">Philippine Military Academy</div>
                <div class="sub">Officer License Card</div>
            </div>
        </div>
        <!-- Body -->
        <div class="officer-card-body">
            <div class="officer-card-photo">
                <?php
                $pDir  = __DIR__ . '/../../assets/img/profiles/';
                $pFile = $o['profile_photo'] ?? '';
                if ($pFile && $pFile !== 'default.png' && file_exists($pDir . $pFile)):
                ?>
                <img src="<?= BASE_URL ?>assets/img/profiles/<?= urlencode($pFile) ?>" alt="photo">
                <?php else: ?>
                <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="officer-card-info">
                <div class="c-name"><?= sanitize($o['full_name']) ?></div>
                <div class="c-rank"><?= sanitize($o['rank'] ?? 'Officer') ?></div>
                <div class="c-row"><span>Officer ID</span><span><?= sanitize($o['officer_id']) ?></span></div>
                <div class="c-row"><span>Roblox Username</span><span><?= sanitize($o['roblox_username'] ?? $o['username']) ?></span></div>
                <div class="c-row"><span>Rank</span><span><?= sanitize($o['rank'] ?? 'â€”') ?></span></div>
                <div class="c-row"><span>Cert. No.</span><span><?= sanitize($certNum) ?></span></div>
                <div class="c-row"><span>Lic. No.</span><span><?= sanitize($licNum) ?></span></div>
                <div class="c-row"><span>Trans. No.</span><span><?= sanitize($o['transition_number'] ?? 'â€”') ?></span></div>
            </div>
        </div>
        <!-- Signatures (mini preview) -->
        <?php if (!empty($signatures)): ?>
        <div class="officer-card-signatures">
            <?php foreach ($signatures as $sig): ?>
            <div class="officer-sig-block">
                <div class="officer-sig-img">
                    <?php if ($sig['signature_image'] && file_exists(SIG_UPLOAD_DIR . $sig['signature_image'])): ?>
                    <img src="<?= BASE_URL ?>uploads/signatures/<?= urlencode($sig['signature_image']) ?>" alt="sig">
                    <?php else: ?>
                    <div style="height:24px;border-bottom:1px dashed rgba(255,255,255,0.3);width:60px;"></div>
                    <?php endif; ?>
                </div>
                <div class="officer-sig-line"></div>
                <div class="officer-sig-name"><?= sanitize($sig['signatory_username']) ?></div>
                <div class="officer-sig-pos"><?= sanitize($sig['signatory_position']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <!-- Footer -->
        <div class="officer-card-footer">
            <div class="lic-num">LIC: <?= sanitize($licNum) ?></div>
            <div class="d-flex align-items-center gap-2">
                <?= getStatusBadge($o['license_status']) ?>
                <a href="?view=<?= $o['id'] ?>" class="btn btn-sm btn-outline-light" style="font-size:10px;">
                    <i class="fas fa-eye me-1"></i>View
                </a>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($officers)): ?>
<div class="col-12 text-center text-muted py-5">No officers found.</div>
<?php endif; ?>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
