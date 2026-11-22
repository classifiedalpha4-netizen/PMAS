<?php
require_once '../../includes/config.php';
requireRole('cadet');
$pageTitle = 'My Cadet ID Card';
$uid = $_SESSION['user_id'];

define('CADET_SIG_DIR', __DIR__ . '/../../uploads/cadet_signatures/');
if (!is_dir(CADET_SIG_DIR)) mkdir(CADET_SIG_DIR, 0755, true);

// Auto-create table if needed
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

$cadet = $conn->query("
    SELECT u.*, cp.cadet_id, cp.class_year, cp.company, cp.battalion,
           cp.date_enrolled, cp.status AS cadet_status
    FROM users u
    JOIN cadet_profiles cp ON u.id = cp.user_id
    WHERE u.id = $uid
")->fetch_assoc();

$signatures = $conn->query("
    SELECT * FROM cadet_card_signatures
    WHERE is_active = 1
    ORDER BY sig_order ASC
")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php'; include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-id-badge me-2"></i> My Cadet ID Card</h2>
        <p>Your official cadet identification card</p>
    </div>
</div>

<?php if (!$cadet): ?>
<div class="pmas-alert pmas-alert-warning">
    <i class="fas fa-exclamation-triangle"></i> No cadet profile found. Please contact the administrator.
</div>
<?php else: ?>

<div class="row justify-content-center">

    <!-- ========== CADET ID CARD ========== -->
    <div class="col-auto text-center">
        <div class="cadet-id-card mb-3" style="margin:0 auto;">
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
                    <div class="c-row"><span>Status</span><span><?= getStatusBadge($cadet['cadet_status']) ?></span></div>
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
                        <div style="height:28px;border-bottom:1px dashed rgba(255,255,255,0.3);width:70px;"></div>
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
            <div class="pmas-card-header"><i class="fas fa-info-circle"></i> Cadet Information</div>
            <div class="pmas-card-body">
                <table class="table table-borderless" style="font-size:13.5px;">
                    <tr>
                        <td class="fw-bold text-muted" style="width:40%;">Full Name</td>
                        <td><?= sanitize($cadet['full_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Username</td>
                        <td><code><?= sanitize($cadet['username']) ?></code></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Cadet ID</td>
                        <td><code><?= sanitize($cadet['cadet_id']) ?></code></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Rank</td>
                        <td><?= sanitize($cadet['rank'] ?? 'Cadet') ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Company</td>
                        <td><?= sanitize($cadet['company'] ?? 'Ã¢â‚¬â€') ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Battalion</td>
                        <td><?= sanitize($cadet['battalion'] ?? 'Ã¢â‚¬â€') ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Class Year</td>
                        <td><?= sanitize($cadet['class_year'] ?? 'Ã¢â‚¬â€') ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Date Enrolled</td>
                        <td><?= $cadet['date_enrolled'] ? date('F j, Y', strtotime($cadet['date_enrolled'])) : 'Ã¢â‚¬â€' ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-muted">Status</td>
                        <td><?= getStatusBadge($cadet['cadet_status']) ?></td>
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
