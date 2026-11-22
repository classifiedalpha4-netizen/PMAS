<?php
require_once '../../includes/config.php';
requireRole('admin','staff');
$pageTitle = 'Cadet ID Cards';

// ---- Auto-create signature table ----
$conn->query("
    CREATE TABLE IF NOT EXISTS cadet_card_signatures (
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

define('CADET_SIG_DIR', __DIR__ . '/../../uploads/cadet_signatures/');
if (!is_dir(CADET_SIG_DIR)) mkdir(CADET_SIG_DIR, 0755, true);
if (!is_dir(CADET_SIG_DIR)) mkdir(CADET_SIG_DIR, 0755, true);

// ---- Handle signature save ----
$sigSuccess = $sigError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_signature'])) {
    $sigId       = (int)($_POST['sig_id'] ?? 0);
    $sigOrder    = (int)($_POST['sig_order'] ?? 1);
    $sigUsername = sanitize($_POST['signatory_username']);
    $sigPosition = sanitize($_POST['signatory_position']);
    $sigActive   = isset($_POST['is_active']) ? 1 : 0;
    $createdBy   = $_SESSION['user_id'];

    $sigImage = sanitize($_POST['existing_sig_image'] ?? '');
    if (!empty($_FILES['signature_image']['name']) && $_FILES['signature_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['signature_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png','jpg','jpeg','gif'])) {
            $sigError = 'Signature image must be PNG, JPG, or GIF.';
        } elseif ($_FILES['signature_image']['size'] > 2 * 1024 * 1024) {
            $sigError = 'Signature image must be under 2MB.';
        } else {
            $safeName = 'csig_' . time() . '_' . $sigOrder . '.' . $ext;
            if (move_uploaded_file($_FILES['signature_image']['tmp_name'], CADET_SIG_DIR . $safeName)) {
                if ($sigImage && file_exists(CADET_SIG_DIR . $sigImage)) unlink(CADET_SIG_DIR . $sigImage);
                $sigImage = $safeName;
            }
        }
    }

    if (!$sigError) {
        if ($sigId > 0) {
            $st = $conn->prepare("UPDATE cadet_card_signatures SET sig_order=?,signatory_username=?,signatory_position=?,signature_image=?,is_active=? WHERE id=?");
            $st->bind_param('isssii', $sigOrder,$sigUsername,$sigPosition,$sigImage,$sigActive,$sigId);
        } else {
            $st = $conn->prepare("INSERT INTO cadet_card_signatures (sig_order,signatory_username,signatory_position,signature_image,is_active,created_by) VALUES (?,?,?,?,?,?)");
            $st->bind_param('isssii', $sigOrder,$sigUsername,$sigPosition,$sigImage,$sigActive,$createdBy);
        }
        $st->execute(); $st->close();
        logAction($conn, $_SESSION['user_id'], 'SAVE_CADET_SIG', 'CadetCards', $sigUsername);
        $sigSuccess = 'Signatory saved successfully.';
    }
}

// ---- Delete signature ----
if (isset($_GET['del_sig']) && is_numeric($_GET['del_sig'])) {
    $delSig = (int)$_GET['del_sig'];
    $sv = $conn->query("SELECT signature_image FROM cadet_card_signatures WHERE id=$delSig")->fetch_assoc();
    if ($sv && $sv['signature_image'] && file_exists(CADET_SIG_DIR . $sv['signature_image'])) {
        unlink(CADET_SIG_DIR . $sv['signature_image']);
    }
    $conn->query("DELETE FROM cadet_card_signatures WHERE id=$delSig");
    redirect(BASE_URL . 'pages/admin/cadet_cards.php');
}

// ---- Edit signature ----
$editSig = null;
if (isset($_GET['edit_sig']) && is_numeric($_GET['edit_sig'])) {
    $editSig = $conn->query("SELECT * FROM cadet_card_signatures WHERE id=" . (int)$_GET['edit_sig'])->fetch_assoc();
}

// ---- Fetch data ----
$cadets = $conn->query("
    SELECT u.id, u.username, u.full_name, u.rank, u.profile_photo,
           u.roblox_username,
           cp.cadet_id, cp.class_year, cp.company, cp.battalion, cp.date_enrolled, cp.status
    FROM users u
    JOIN cadet_profiles cp ON u.id = cp.user_id
    WHERE u.role = 'cadet' AND u.is_active = 1
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

$signatures    = $conn->query("SELECT * FROM cadet_card_signatures WHERE is_active=1 ORDER BY sig_order ASC")->fetch_all(MYSQLI_ASSOC);
$allSignatures = $conn->query("SELECT * FROM cadet_card_signatures ORDER BY sig_order ASC")->fetch_all(MYSQLI_ASSOC);

$viewId = isset($_GET['view']) && is_numeric($_GET['view']) ? (int)$_GET['view'] : 0;
$cadet  = null;
if ($viewId > 0) {
    foreach ($cadets as $c) {
        if ($c['id'] == $viewId) { $cadet = $c; break; }
    }
}

include '../../includes/header.php'; include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-id-badge me-2"></i> Cadet ID Cards</h2>
        <p>View, print, and manage cadet ID cards &amp; signatories</p>
    </div>
</div>

<?php if ($sigSuccess): ?>
<div class="pmas-alert pmas-alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i> <?= $sigSuccess ?></div>
<?php endif; ?>
<?php if ($sigError): ?>
<div class="pmas-alert pmas-alert-danger"><i class="fas fa-times-circle"></i> <?= $sigError ?></div>
<?php endif; ?>

<?php if ($cadet): ?>
<!-- ==================== SINGLE CARD VIEW ==================== -->
<div class="row justify-content-center">
    <div class="col-auto text-center">
        <div class="cadet-id-card mb-3">
            <!-- Watermark -->
            <div class="cadet-card-logo-watermark">
                <img src="<?= BASE_URL ?>assets/img/pma_logo.png" alt="">
            </div>
            <div class="cadet-card-stripe"></div>
            <div class="cadet-card-header">
                <img src="<?= BASE_URL ?>assets/img/pma_logo.png" alt="PMA"
                     style="width:30px;height:30px;border-radius:50%;border:1px solid var(--pmas-gold);object-fit:cover;flex-shrink:0;">
                <div>
                    <div class="title">Philippine Military Academy</div>
                    <div class="sub">Cadet Identification Card &mdash; Philippine Army Roleplay</div>
                </div>
            </div>
            <div class="cadet-card-body" style="position:relative;z-index:1;">
                <div class="cadet-card-photo">
                    <?php
                    $photoDir  = __DIR__ . '/../../assets/img/profiles/';
                    $photoFile = $cadet['profile_photo'] ?? '';
                    if ($photoFile && $photoFile !== 'default.png' && file_exists($photoDir . $photoFile)):
                    ?>
                    <img src="<?= BASE_URL ?>assets/img/profiles/<?= urlencode($photoFile) ?>" alt="photo">
                    <?php else: ?>
                    <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="cadet-card-info">
                    <div class="c-name"><?= sanitize($cadet['full_name']) ?></div>
                    <div class="c-num"><?= sanitize($cadet['rank'] ?? 'Cadet') ?></div>
                    <div class="c-row"><span>Cadet ID</span><span><?= sanitize($cadet['cadet_id']) ?></span></div>
                    <div class="c-row"><span>Roblox Username</span><span><?= sanitize($cadet['roblox_username'] ?? $cadet['username']) ?></span></div>
                    <div class="c-row"><span>Company</span><span><?= sanitize($cadet['company'] ?? 'Ã¢â‚¬â€') ?></span></div>
                    <div class="c-row"><span>Battalion</span><span><?= sanitize($cadet['battalion'] ?? 'Ã¢â‚¬â€') ?></span></div>
                    <div class="c-row"><span>Class</span><span><?= sanitize($cadet['class_year'] ?? 'Ã¢â‚¬â€') ?></span></div>
                    <div class="c-row"><span>Status</span><span><?= getStatusBadge($cadet['status']) ?></span></div>
                </div>
            </div>
            <!-- Signatures -->
            <?php if (!empty($signatures)): ?>
            <div class="cadet-card-signatures">
                <?php foreach ($signatures as $sig): ?>
                <div class="cadet-sig-block">
                    <div class="cadet-sig-img">
                        <?php if ($sig['signature_image'] && file_exists(CADET_SIG_DIR . $sig['signature_image'])): ?>
                        <img src="<?= BASE_URL ?>uploads/cadet_signatures/<?= urlencode($sig['signature_image']) ?>" alt="sig">
                        <?php else: ?>
                        <div style="height:24px;border-bottom:1px dashed rgba(255,255,255,0.3);width:70px;"></div>
                        <?php endif; ?>
                    </div>
                    <div class="cadet-sig-line"></div>
                    <div class="cadet-sig-name"><?= sanitize($sig['signatory_username']) ?></div>
                    <div class="cadet-sig-pos"><?= sanitize($sig['signatory_position']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2 justify-content-center no-print">
            <button class="btn-pmas print-btn"><i class="fas fa-print me-1"></i> Print Card</button>
            <a href="<?= BASE_URL ?>pages/admin/cadet_cards.php" class="btn-pmas-dark">
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
                        <?php if (!empty($editSig['signature_image']) && file_exists(CADET_SIG_DIR . $editSig['signature_image'])): ?>
                        <div class="mb-2 p-2 rounded" style="background:#1a1a1a;">
                            <img src="<?= BASE_URL ?>uploads/cadet_signatures/<?= urlencode($editSig['signature_image']) ?>"
                                 style="max-height:50px;filter:brightness(0) invert(1);" alt="Current">
                            <div style="font-size:11px;color:#888;margin-top:4px;">Current Ã¢â‚¬â€ upload new to replace</div>
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
                    <a href="<?= BASE_URL ?>pages/admin/cadet_cards.php"
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
            <div class="pmas-card-header"><i class="fas fa-signature"></i> Signatories on Cadet ID Cards</div>
            <div class="pmas-card-body p-0">
                <?php if (empty($allSignatures)): ?>
                <p class="text-center text-muted py-4">No signatories yet. Add one to appear on cadet ID cards.</p>
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
                            <?php if ($sig['signature_image'] && file_exists(CADET_SIG_DIR . $sig['signature_image'])): ?>
                            <img src="<?= BASE_URL ?>uploads/cadet_signatures/<?= urlencode($sig['signature_image']) ?>"
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

<!-- ==================== CADET LIST ==================== -->
<div class="pmas-card-header pmas-card mb-3" style="border-radius:8px;overflow:hidden;padding:0;">
    <div class="pmas-card-header">
        <i class="fas fa-id-badge"></i> All Cadet ID Cards (<?= count($cadets) ?>)
    </div>
</div>
<div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
<?php foreach ($cadets as $c): ?>
<div class="col">
    <div class="cadet-id-card" style="width:100%;">
        <!-- Watermark -->
        <div class="cadet-card-logo-watermark">
            <img src="<?= BASE_URL ?>assets/img/pma_logo.png" alt="">
        </div>
        <div class="cadet-card-stripe"></div>
        <div class="cadet-card-header">
            <img src="<?= BASE_URL ?>assets/img/pma_logo.png" alt="PMA"
                 style="width:26px;height:26px;border-radius:50%;border:1px solid var(--pmas-gold);object-fit:cover;flex-shrink:0;">
            <div>
                <div class="title">Philippine Military Academy</div>
                <div class="sub">Cadet ID Card</div>
            </div>
        </div>
        <div class="cadet-card-body" style="position:relative;z-index:1;">
            <div class="cadet-card-photo">
                <?php
                $pDir  = __DIR__ . '/../../assets/img/profiles/';
                $pFile = $c['profile_photo'] ?? '';
                if ($pFile && $pFile !== 'default.png' && file_exists($pDir . $pFile)):
                ?>
                <img src="<?= BASE_URL ?>assets/img/profiles/<?= urlencode($pFile) ?>" alt="photo">
                <?php else: ?>
                <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="cadet-card-info">
                <div class="c-name"><?= sanitize($c['full_name']) ?></div>
                <div class="c-num"><?= sanitize($c['rank'] ?? 'Cadet') ?></div>
                <div class="c-row"><span>Cadet ID</span><span><?= sanitize($c['cadet_id']) ?></span></div>
                <div class="c-row"><span>Roblox Username</span><span><?= sanitize($c['roblox_username'] ?? $c['username']) ?></span></div>
                <div class="c-row"><span>Company</span><span><?= sanitize($c['company'] ?? 'Ã¢â‚¬â€') ?></span></div>
                <div class="c-row"><span>Class</span><span><?= sanitize($c['class_year'] ?? 'Ã¢â‚¬â€') ?></span></div>
                <div class="c-row"><span>Status</span><span><?= getStatusBadge($c['status']) ?></span></div>
            </div>
        </div>
        <!-- Signatures mini preview -->
        <?php if (!empty($signatures)): ?>
        <div class="cadet-card-signatures">
            <?php foreach ($signatures as $sig): ?>
            <div class="cadet-sig-block">
                <div class="cadet-sig-img">
                    <?php if ($sig['signature_image'] && file_exists(CADET_SIG_DIR . $sig['signature_image'])): ?>
                    <img src="<?= BASE_URL ?>uploads/cadet_signatures/<?= urlencode($sig['signature_image']) ?>" alt="sig">
                    <?php else: ?>
                    <div style="height:20px;border-bottom:1px dashed rgba(255,255,255,0.3);width:50px;"></div>
                    <?php endif; ?>
                </div>
                <div class="cadet-sig-line"></div>
                <div class="cadet-sig-name"><?= sanitize($sig['signatory_username']) ?></div>
                <div class="cadet-sig-pos"><?= sanitize($sig['signatory_position']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div style="padding:6px 14px;text-align:right;position:relative;z-index:1;">
            <a href="?view=<?= $c['id'] ?>" class="btn btn-sm btn-outline-light" style="font-size:10px;">
                <i class="fas fa-eye me-1"></i>View / Print
            </a>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($cadets)): ?>
<div class="col-12 text-center text-muted py-5">No cadets found.</div>
<?php endif; ?>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
