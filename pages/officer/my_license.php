<?php
require_once '../../includes/config.php';
requireRole('officer');
$pageTitle = 'My Officer License';

define('SIG_UPLOAD_DIR', __DIR__ . '/../../uploads/signatures/');
if (!is_dir(SIG_UPLOAD_DIR)) mkdir(SIG_UPLOAD_DIR, 0755, true);

// Auto-create tables if needed (MySQL-compatible column checks)
$_c1 = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='officer_profiles' AND COLUMN_NAME='certificate_number'");
if ($_c1 && $_c1->num_rows === 0) $conn->query("ALTER TABLE officer_profiles ADD COLUMN certificate_number VARCHAR(40) DEFAULT NULL AFTER license_number");
$_c2 = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='officer_profiles' AND COLUMN_NAME='transition_number'");
if ($_c2 && $_c2->num_rows === 0) $conn->query("ALTER TABLE officer_profiles ADD COLUMN transition_number VARCHAR(40) DEFAULT NULL AFTER certificate_number");
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

$uid = $_SESSION['user_id'];

$officer = $conn->query("
    SELECT u.*, op.officer_id, op.class_year, op.branch_of_service,
           op.current_assignment, op.license_number,
           op.certificate_number, op.transition_number,
           op.license_issued, op.license_expiry, op.license_status
    FROM users u
    JOIN officer_profiles op ON u.id = op.user_id
    WHERE u.id = $uid
")->fetch_assoc();

$signatures = $conn->query("SELECT * FROM license_signatures WHERE is_active=1 ORDER BY sig_order ASC")->fetch_all(MYSQLI_ASSOC);

// Auto-generate numbers if not set
function generateLicenseNumber($conn, $officerId) {
    return 'PMA-LIC-' . date('Y') . '-' . str_pad($officerId, 4, '0', STR_PAD_LEFT);
}

include '../../includes/header.php'; include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-id-card me-2"></i> My Officer License Card</h2>
        <p>Your official officer identification and license</p>
    </div>
</div>

<?php if (!$officer): ?>
<div class="pmas-alert pmas-alert-warning">
    <i class="fas fa-exclamation-triangle"></i> No officer profile found. Please contact the administrator.
</div>
<?php else: ?>

<?php
$licNum  = $officer['license_number']      ?: generateLicenseNumber($conn, $uid);
$certNum = $officer['certificate_number']   ?: ('PMA-CERT-' . date('Y') . '-' . str_pad($uid, 4, '0', STR_PAD_LEFT));
$transNum= $officer['transition_number']    ?? 'â€”';
?>

<div class="row justify-content-center">
    <!-- ========== LICENSE CARD ========== -->
    <div class="col-auto text-center">
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
                    $photoDir  = __DIR__ . '/../../assets/img/profiles/';
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
                    <div class="c-row"><span>Trans. No.</span><span><?= sanitize($transNum) ?></span></div>
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
            <button class="btn-pmas print-btn">
                <i class="fas fa-print me-1"></i> Print Card
            </button>
            <a href="<?= BASE_URL ?>dashboard.php" class="btn-pmas-dark">
                <i class="fas fa-home me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ========== DETAILS TABLE ========== -->
    <div class="col-lg-5">
        <div class="pmas-card">
            <div class="pmas-card-header"><i class="fas fa-info-circle"></i> License Details</div>
            <div class="pmas-card-body">
                <table class="table table-borderless" style="font-size:13.5px;">
                    <tr>
                        <td class="fw-bold text-muted" style="width:45%;">Full Name</td>
                        <td><?= sanitize($officer['full_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Username</td>
                        <td><code><?= sanitize($officer['username']) ?></code></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Rank</td>
                        <td><?= sanitize($officer['rank'] ?? 'â€”') ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Certificate No.</td>
                        <td><code><?= sanitize($certNum) ?></code></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">License No.</td>
                        <td><code><?= sanitize($licNum) ?></code></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Transitioned No.</td>
                        <td><?= sanitize($transNum) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">License Issued</td>
                        <td><?= $officer['license_issued'] ? date('F j, Y', strtotime($officer['license_issued'])) : 'â€”' ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">License Expiry</td>
                        <td><?= $officer['license_expiry'] ? date('F j, Y', strtotime($officer['license_expiry'])) : 'â€”' ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">License Status</td>
                        <td><?= getStatusBadge($officer['license_status']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Signatories preview -->
        <?php if (!empty($signatures)): ?>
        <div class="pmas-card mt-3">
            <div class="pmas-card-header"><i class="fas fa-signature"></i> Card Signatories</div>
            <div class="pmas-card-body p-0">
                <table class="pmas-table">
                    <thead><tr><th>#</th><th>Name</th><th>Position</th></tr></thead>
                    <tbody>
                    <?php foreach ($signatures as $sig): ?>
                    <tr>
                        <td class="text-center"><?= $sig['sig_order'] ?></td>
                        <td><strong><?= sanitize($sig['signatory_username']) ?></strong></td>
                        <td style="font-size:12px;"><?= sanitize($sig['signatory_position']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
<?php include '../../includes/footer.php'; ?>
