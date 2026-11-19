<?php
require_once '../../includes/config.php';
requireRole('admin','staff');
$pageTitle = 'Blacklist — PAR & PMA';

// Auto-create evidence table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS blacklist_evidence (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blacklist_id INT NOT NULL,
        file_label VARCHAR(200) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(100) DEFAULT NULL,
        file_size INT DEFAULT NULL,
        uploaded_by INT DEFAULT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (blacklist_id) REFERENCES blacklist(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
");

define('BL_UPLOAD_DIR', __DIR__ . '/../../uploads/blacklist_evidence/');
define('BL_MAX_SIZE',   25 * 1024 * 1024); // 25 MB
$allowedExt = ['pdf','jpg','jpeg','png','gif','doc','docx','mp4','mp3','txt'];

// ---- Delete blacklist entry ----
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    // Delete evidence files from disk
    $evFiles = $conn->query("SELECT file_path FROM blacklist_evidence WHERE blacklist_id=$delId")->fetch_all(MYSQLI_ASSOC);
    foreach ($evFiles as $ef) {
        $fp = BL_UPLOAD_DIR . $ef['file_path'];
        if (file_exists($fp)) unlink($fp);
    }
    $conn->query("DELETE FROM blacklist WHERE id=$delId");
    logAction($conn, $_SESSION['user_id'], 'DELETE_BLACKLIST', 'Blacklist', "Deleted ID $delId");
    redirect(BASE_URL . 'pages/admin/blacklist.php?deleted=1');
}

// ---- Delete single evidence file ----
if (isset($_GET['del_evidence']) && is_numeric($_GET['del_evidence'])) {
    $evId = (int)$_GET['del_evidence'];
    $ev   = $conn->query("SELECT * FROM blacklist_evidence WHERE id=$evId")->fetch_assoc();
    if ($ev) {
        $fp = BL_UPLOAD_DIR . $ev['file_path'];
        if (file_exists($fp)) unlink($fp);
        $conn->query("DELETE FROM blacklist_evidence WHERE id=$evId");
        logAction($conn, $_SESSION['user_id'], 'DELETE_BL_EVIDENCE', 'Blacklist', "Evidence ID $evId");
    }
    redirect(BASE_URL . 'pages/admin/blacklist.php?edit=' . ($ev['blacklist_id'] ?? '') . '&saved=1');
}

// ---- Edit fetch ----
$editEntry = null;
$editEvidence = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId      = (int)$_GET['edit'];
    $editEntry   = $conn->query("SELECT * FROM blacklist WHERE id=$editId")->fetch_assoc();
    $editEvidence= $conn->query("SELECT * FROM blacklist_evidence WHERE blacklist_id=$editId ORDER BY uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id               = (int)($_POST['id'] ?? 0);
    $user_id          = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $full_name        = sanitize($_POST['full_name']);
    $blacklist_type   = sanitize($_POST['blacklist_type']);
    $reason           = sanitize($_POST['reason']);
    $offense_date     = sanitize($_POST['offense_date']);
    $blacklisted_date = sanitize($_POST['blacklisted_date']);
    $status           = sanitize($_POST['status']);
    $remarks          = sanitize($_POST['remarks']);
    $blBy             = $_SESSION['user_id'];

    if (empty($full_name) || empty($reason) || empty($blacklisted_date)) {
        $error = 'Please fill in all required fields.';
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE blacklist SET user_id=?,full_name=?,blacklist_type=?,reason=?,offense_date=?,blacklisted_date=?,status=?,remarks=? WHERE id=?");
            $stmt->bind_param('isssssssi', $user_id,$full_name,$blacklist_type,$reason,$offense_date,$blacklisted_date,$status,$remarks,$id);
            $stmt->execute(); $stmt->close();
            $recordId = $id;
        } else {
            $stmt = $conn->prepare("INSERT INTO blacklist (user_id,full_name,blacklist_type,reason,offense_date,blacklisted_date,blacklisted_by,status,remarks) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('issssssss', $user_id,$full_name,$blacklist_type,$reason,$offense_date,$blacklisted_date,$blBy,$status,$remarks);
            $stmt->execute();
            $recordId = $stmt->insert_id;
            $stmt->close();
        }

        // ---- Handle evidence uploads ----
        $uploadErrors = [];
        if (!empty($_FILES['evidence_files']['name'][0])) {
            foreach ($_FILES['evidence_files']['name'] as $i => $origName) {
                if (empty($origName) || $_FILES['evidence_files']['error'][$i] !== UPLOAD_ERR_OK) continue;

                $fileSize = $_FILES['evidence_files']['size'][$i];
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                if ($fileSize > BL_MAX_SIZE) {
                    $uploadErrors[] = "$origName exceeds 25MB limit.";
                    continue;
                }
                if (!in_array($ext, $allowedExt)) {
                    $uploadErrors[] = "$origName — unsupported file type.";
                    continue;
                }

                $safeName  = 'bl_' . $recordId . '_' . time() . '_' . $i . '.' . $ext;
                $destPath  = BL_UPLOAD_DIR . $safeName;
                $fileLabel = sanitize($_POST['evidence_labels'][$i] ?? $origName);
                $fileType  = $_FILES['evidence_files']['type'][$i];

                if (move_uploaded_file($_FILES['evidence_files']['tmp_name'][$i], $destPath)) {
                    $us = $conn->prepare("INSERT INTO blacklist_evidence (blacklist_id,file_label,file_name,file_path,file_type,file_size,uploaded_by) VALUES (?,?,?,?,?,?,?)");
                    $us->bind_param('issssii', $recordId,$fileLabel,$origName,$safeName,$fileType,$fileSize,$blBy);
                    $us->execute(); $us->close();
                }
            }
        }

        logAction($conn, $_SESSION['user_id'], $id > 0 ? 'EDIT_BLACKLIST' : 'ADD_BLACKLIST', 'Blacklist', $full_name);

        if (!empty($uploadErrors)) {
            $error = 'Record saved, but some files had errors: ' . implode(' | ', $uploadErrors);
            $editEntry   = $conn->query("SELECT * FROM blacklist WHERE id=$recordId")->fetch_assoc();
            $editEvidence= $conn->query("SELECT * FROM blacklist_evidence WHERE blacklist_id=$recordId ORDER BY uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);
        } else {
            redirect(BASE_URL . 'pages/admin/blacklist.php?saved=1');
        }
    }
}

$list     = $conn->query("SELECT b.*, u.username, (SELECT COUNT(*) FROM blacklist_evidence WHERE blacklist_id=b.id) AS ev_count FROM blacklist b LEFT JOIN users u ON b.user_id=u.id ORDER BY b.blacklisted_date DESC")->fetch_all(MYSQLI_ASSOC);
$allUsers = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY role, full_name")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-ban me-2"></i> Blacklist — PAR &amp; PMA</h2>
        <p>Wide blacklisted individuals with evidence files</p>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="pmas-alert pmas-alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i> Record saved.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="pmas-alert pmas-alert-warning" data-auto-dismiss><i class="fas fa-trash"></i> Record deleted.</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="pmas-alert pmas-alert-danger"><i class="fas fa-times-circle"></i> <?= $error ?></div>
<?php endif; ?>

<div class="row">
    <!-- ==================== FORM ==================== -->
    <div class="col-lg-4">
        <div class="pmas-card">
            <div class="pmas-card-header">
                <i class="fas fa-<?= $editEntry ? 'edit' : 'plus' ?>"></i>
                <?= $editEntry ? 'Edit Entry' : 'Add to Blacklist' ?>
            </div>
            <div class="pmas-card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $editEntry['id'] ?? 0 ?>">

                    <div class="mb-3">
                        <label class="pmas-form-label">Link to System User (Optional)</label>
                        <select name="user_id" class="form-select">
                            <option value="">-- External / Not in System --</option>
                            <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (isset($editEntry) && $editEntry['user_id'] == $u['id']) ? 'selected' : '' ?>>
                                <?= sanitize($u['username']) ?> — <?= sanitize($u['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control"
                               value="<?= sanitize($editEntry['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Blacklist Type *</label>
                        <select name="blacklist_type" class="form-select" required>
                            <option value="PAR"  <?= ($editEntry['blacklist_type'] ?? '') === 'PAR'  ? 'selected' : '' ?>>PAR</option>
                            <option value="PMA"  <?= ($editEntry['blacklist_type'] ?? '') === 'PMA'  ? 'selected' : '' ?>>PMA</option>
                            <option value="Both" <?= ($editEntry['blacklist_type'] ?? '') === 'Both' ? 'selected' : '' ?>>Both (PAR &amp; PMA)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Reason *</label>
                        <textarea name="reason" class="form-control" rows="3" required><?= sanitize($editEntry['reason'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="pmas-form-label">Offense Date</label>
                            <input type="date" name="offense_date" class="form-control"
                                   value="<?= $editEntry['offense_date'] ?? '' ?>">
                        </div>
                        <div class="col-6">
                            <label class="pmas-form-label">Blacklisted Date *</label>
                            <input type="date" name="blacklisted_date" class="form-control"
                                   value="<?= $editEntry['blacklisted_date'] ?? date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active"       <?= ($editEntry['status'] ?? 'Active') === 'Active'       ? 'selected' : '' ?>>Active</option>
                            <option value="Lifted"       <?= ($editEntry['status'] ?? '') === 'Lifted'             ? 'selected' : '' ?>>Lifted</option>
                            <option value="Under Review" <?= ($editEntry['status'] ?? '') === 'Under Review'       ? 'selected' : '' ?>>Under Review</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"><?= sanitize($editEntry['remarks'] ?? '') ?></textarea>
                    </div>

                    <!-- ===== EVIDENCE UPLOAD ===== -->
                    <hr class="separator-red">
                    <div class="mb-2">
                        <label class="pmas-form-label">
                            <i class="fas fa-paperclip me-1 text-pmas-red"></i>
                            Evidence Files
                            <small class="text-muted fw-normal">(Max 25MB each)</small>
                        </label>
                        <div class="p-2 rounded mb-2" style="background:#f8f8f8;font-size:11px;color:#888;">
                            Accepted: PDF, JPG, PNG, GIF, DOC, DOCX, MP4, MP3, TXT
                        </div>
                        <div id="blEvidenceContainer">
                            <div class="evidence-row mb-2">
                                <input type="text" name="evidence_labels[]"
                                       class="form-control form-control-sm mb-1"
                                       placeholder="File label / description (optional)">
                                <input type="file" name="evidence_files[]"
                                       class="form-control form-control-sm"
                                       accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.mp4,.mp3,.txt">
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="addBlEvidence">
                            <i class="fas fa-plus me-1"></i> Add Another File
                        </button>
                    </div>

                    <!-- Existing evidence (edit mode) -->
                    <?php if (!empty($editEvidence)): ?>
                    <div class="mt-3">
                        <label class="pmas-form-label"><i class="fas fa-folder-open me-1"></i> Existing Evidence (<?= count($editEvidence) ?>)</label>
                        <?php foreach ($editEvidence as $ev): ?>
                        <div class="d-flex align-items-center gap-2 p-2 mb-1 rounded" style="background:#f8f8f8;border-left:3px solid var(--pmas-red);">
                            <i class="fas fa-<?= getEvidenceIcon($ev['file_type'] ?? '') ?> text-pmas-red"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= sanitize($ev['file_label']) ?>
                                </div>
                                <div style="font-size:10px;color:#888;">
                                    <?= sanitize($ev['file_name']) ?>
                                    &nbsp;|&nbsp; <?= round($ev['file_size'] / 1024, 1) ?> KB
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>uploads/blacklist_evidence/<?= urlencode($ev['file_path']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="?del_evidence=<?= $ev['id'] ?>"
                               class="btn btn-sm btn-outline-danger" title="Delete"
                               data-confirm="Delete evidence file '<?= sanitize($ev['file_label']) ?>'?">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <button type="submit" class="btn-pmas w-100">
                            <i class="fas fa-save me-1"></i> <?= $editEntry ? 'Update' : 'Add to Blacklist' ?>
                        </button>
                        <?php if ($editEntry): ?>
                        <a href="<?= BASE_URL ?>pages/admin/blacklist.php" class="btn-pmas-dark w-100 mt-2 justify-content-center">
                            <i class="fas fa-times me-1"></i> Cancel Edit
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== LIST ==================== -->
    <div class="col-lg-8">
        <div class="pmas-card">
            <div class="pmas-card-header"><i class="fas fa-list"></i> Blacklist Records (<?= count($list) ?>)</div>
            <div class="pmas-card-body p-0">
                <div class="p-3">
                    <input type="text" id="tableSearch" class="form-control" placeholder="Search..." style="max-width:280px;">
                </div>

                <!-- Desktop table -->
                <div class="table-responsive desktop-only">
                <table class="pmas-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-center">Evidence</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($list)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No blacklist records.</td></tr>
                    <?php else: foreach ($list as $b): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($b['full_name']) ?></strong>
                                <?php if ($b['username']): ?>
                                <br><small class="text-muted"><code><?= sanitize($b['username']) ?></code></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-dark"><?= sanitize($b['blacklist_type']) ?></span></td>
                            <td style="max-width:180px;white-space:normal;font-size:12px;"><?= sanitize($b['reason']) ?></td>
                            <td style="font-size:12px;"><?= date('M d, Y', strtotime($b['blacklisted_date'])) ?></td>
                            <td><?= getStatusBadge($b['status']) ?></td>
                            <td class="text-center">
                                <?php if ($b['ev_count'] > 0): ?>
                                <span class="badge bg-primary"><i class="fas fa-paperclip me-1"></i><?= $b['ev_count'] ?></span>
                                <?php else: ?><span class="text-muted" style="font-size:11px;">—</span><?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit / View Evidence">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger"
                                   data-confirm="Remove '<?= sanitize($b['full_name']) ?>' and all evidence files?">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>

                <!-- Mobile cards -->
                <div class="mobile-only p-3">
                <?php if (empty($list)): ?>
                    <p class="text-center text-muted py-3">No blacklist records.</p>
                <?php else: foreach ($list as $b): ?>
                <div class="border rounded mb-3 overflow-hidden" style="font-size:13px;">
                    <div class="p-3" style="background:#f8f8f8;border-bottom:1px solid #eee;border-left:4px solid var(--pmas-red);">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                            <strong><?= sanitize($b['full_name']) ?></strong>
                            <?= getStatusBadge($b['status']) ?>
                        </div>
                        <?php if ($b['username']): ?>
                        <div style="font-size:11px;color:#888;margin-bottom:4px;"><code><?= sanitize($b['username']) ?></code></div>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge bg-dark"><?= sanitize($b['blacklist_type']) ?></span>
                            <span style="font-size:11px;color:#888;"><i class="fas fa-calendar me-1"></i><?= date('M d, Y', strtotime($b['blacklisted_date'])) ?></span>
                        </div>
                        <div style="font-size:12px;color:#555;"><?= sanitize($b['reason']) ?></div>
                        <?php if ($b['ev_count'] > 0): ?>
                        <div class="mt-2">
                            <span class="badge bg-primary"><i class="fas fa-paperclip me-1"></i><?= $b['ev_count'] ?> evidence file(s)</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-2 d-flex gap-2" style="background:#fff;">
                        <a href="?edit=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill text-center">
                            <i class="fas fa-edit me-1"></i> Edit / Evidence
                        </a>
                        <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger flex-fill text-center"
                           data-confirm="Remove '<?= sanitize($b['full_name']) ?>' and all evidence files?">
                            <i class="fas fa-trash me-1"></i> Delete
                        </a>
                    </div>
                </div>
                <?php endforeach; endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Add more evidence file rows
document.getElementById('addBlEvidence').addEventListener('click', function () {
    const container = document.getElementById('blEvidenceContainer');
    const row = document.createElement('div');
    row.className = 'evidence-row mb-2';
    row.innerHTML = `
        <div class="d-flex gap-1 align-items-center">
            <div style="flex:1;">
                <input type="text" name="evidence_labels[]" class="form-control form-control-sm mb-1" placeholder="File label / description (optional)">
                <input type="file" name="evidence_files[]" class="form-control form-control-sm"
                       accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.mp4,.mp3,.txt">
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger remove-ev-row" style="height:32px;">
                <i class="fas fa-times"></i>
            </button>
        </div>`;
    container.appendChild(row);
    row.querySelector('.remove-ev-row').addEventListener('click', function () { row.remove(); });
});

// File size validation
document.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'evidence_files[]') {
        const maxSize = 25 * 1024 * 1024;
        if (e.target.files[0] && e.target.files[0].size > maxSize) {
            alert('"' + e.target.files[0].name + '" exceeds the 25MB limit.');
            e.target.value = '';
        }
    }
});
</script>

<?php
// Helper: icon by mime type
function getEvidenceIcon($mimeType) {
    if (str_contains($mimeType, 'pdf'))   return 'file-pdf';
    if (str_contains($mimeType, 'image')) return 'file-image';
    if (str_contains($mimeType, 'video')) return 'file-video';
    if (str_contains($mimeType, 'audio')) return 'file-audio';
    if (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) return 'file-word';
    return 'file-alt';
}
?>
