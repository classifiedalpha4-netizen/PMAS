<?php
require_once '../../includes/config.php';
requireRole('admin','staff');
$pageTitle = 'Case Verdicts';

// Auto-create evidence table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS case_evidence (
        id INT AUTO_INCREMENT PRIMARY KEY,
        case_id INT NOT NULL,
        file_label VARCHAR(200) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(100) DEFAULT NULL,
        file_size INT DEFAULT NULL,
        uploaded_by INT DEFAULT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
");

define('CASE_UPLOAD_DIR', __DIR__ . '/../../uploads/case_evidence/');
define('CASE_MAX_SIZE',   25 * 1024 * 1024); // 25 MB
$allowedExt = ['pdf','jpg','jpeg','png','gif','doc','docx','mp4','mp3','txt'];

// Helper: icon by mime type
function getEvidenceIcon($mimeType) {
    if (str_contains($mimeType, 'pdf'))   return 'file-pdf';
    if (str_contains($mimeType, 'image')) return 'file-image';
    if (str_contains($mimeType, 'video')) return 'file-video';
    if (str_contains($mimeType, 'audio')) return 'file-audio';
    if (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) return 'file-word';
    return 'file-alt';
}

// ---- Delete case ----
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $evFiles = $conn->query("SELECT file_path FROM case_evidence WHERE case_id=$delId")->fetch_all(MYSQLI_ASSOC);
    foreach ($evFiles as $ef) {
        $fp = CASE_UPLOAD_DIR . $ef['file_path'];
        if (file_exists($fp)) unlink($fp);
    }
    $conn->query("DELETE FROM cases WHERE id=$delId");
    logAction($conn, $_SESSION['user_id'], 'DELETE_CASE', 'Cases', "Deleted case ID $delId");
    redirect(BASE_URL . 'pages/admin/cases.php?deleted=1');
}

// ---- Delete single evidence file ----
if (isset($_GET['del_evidence']) && is_numeric($_GET['del_evidence'])) {
    $evId = (int)$_GET['del_evidence'];
    $ev   = $conn->query("SELECT * FROM case_evidence WHERE id=$evId")->fetch_assoc();
    if ($ev) {
        $fp = CASE_UPLOAD_DIR . $ev['file_path'];
        if (file_exists($fp)) unlink($fp);
        $conn->query("DELETE FROM case_evidence WHERE id=$evId");
        logAction($conn, $_SESSION['user_id'], 'DELETE_CASE_EVIDENCE', 'Cases', "Evidence ID $evId");
    }
    redirect(BASE_URL . 'pages/admin/cases.php?edit=' . ($ev['case_id'] ?? '') . '&saved=1');
}

// ---- Edit fetch ----
$editEntry  = null;
$editEvidence = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId       = (int)$_GET['edit'];
    $editEntry    = $conn->query("SELECT * FROM cases WHERE id=$editId")->fetch_assoc();
    $editEvidence = $conn->query("SELECT * FROM case_evidence WHERE case_id=$editId ORDER BY uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id               = (int)($_POST['id'] ?? 0);
    $case_number      = sanitize($_POST['case_number']);
    $subject_user_id  = (int)$_POST['subject_user_id'];
    $case_title       = sanitize($_POST['case_title']);
    $description      = sanitize($_POST['description']);
    $offense_category = sanitize($_POST['offense_category']);
    $filed_date       = sanitize($_POST['filed_date']);
    $hearing_date     = sanitize($_POST['hearing_date']);
    $verdict          = sanitize($_POST['verdict']);
    $sanction         = sanitize($_POST['sanction']);
    $handled_by       = $_SESSION['user_id'];

    if (empty($case_number) || empty($case_title) || empty($filed_date) || $subject_user_id < 1) {
        $error = 'Please fill in all required fields.';
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE cases SET case_number=?,subject_user_id=?,case_title=?,description=?,offense_category=?,filed_date=?,hearing_date=?,verdict=?,sanction=?,handled_by=? WHERE id=?");
            $stmt->bind_param('sisssssssii', $case_number,$subject_user_id,$case_title,$description,$offense_category,$filed_date,$hearing_date,$verdict,$sanction,$handled_by,$id);
            $stmt->execute(); $stmt->close();
            $recordId = $id;
        } else {
            $stmt = $conn->prepare("INSERT INTO cases (case_number,subject_user_id,case_title,description,offense_category,filed_date,hearing_date,verdict,sanction,handled_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sisssssssi', $case_number,$subject_user_id,$case_title,$description,$offense_category,$filed_date,$hearing_date,$verdict,$sanction,$handled_by);
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

                if ($fileSize > CASE_MAX_SIZE) {
                    $uploadErrors[] = "$origName exceeds 25MB limit.";
                    continue;
                }
                if (!in_array($ext, $allowedExt)) {
                    $uploadErrors[] = "$origName — unsupported file type.";
                    continue;
                }

                $safeName  = 'case_' . $recordId . '_' . time() . '_' . $i . '.' . $ext;
                $destPath  = CASE_UPLOAD_DIR . $safeName;
                $fileLabel = sanitize($_POST['evidence_labels'][$i] ?? $origName);
                $fileType  = $_FILES['evidence_files']['type'][$i];

                if (move_uploaded_file($_FILES['evidence_files']['tmp_name'][$i], $destPath)) {
                    $us = $conn->prepare("INSERT INTO case_evidence (case_id,file_label,file_name,file_path,file_type,file_size,uploaded_by) VALUES (?,?,?,?,?,?,?)");
                    $us->bind_param('issssii', $recordId,$fileLabel,$origName,$safeName,$fileType,$fileSize,$handled_by);
                    $us->execute(); $us->close();
                }
            }
        }

        logAction($conn, $_SESSION['user_id'], $id > 0 ? 'EDIT_CASE' : 'ADD_CASE', 'Cases', $case_number);

        if (!empty($uploadErrors)) {
            $error = 'Case saved, but some files had errors: ' . implode(' | ', $uploadErrors);
            $editEntry    = $conn->query("SELECT * FROM cases WHERE id=$recordId")->fetch_assoc();
            $editEvidence = $conn->query("SELECT * FROM case_evidence WHERE case_id=$recordId ORDER BY uploaded_at DESC")->fetch_all(MYSQLI_ASSOC);
        } else {
            redirect(BASE_URL . 'pages/admin/cases.php?saved=1');
        }
    }
}

$cases    = $conn->query("SELECT c.*, u.full_name, u.role, (SELECT COUNT(*) FROM case_evidence WHERE case_id=c.id) AS ev_count FROM cases c JOIN users u ON c.subject_user_id=u.id ORDER BY c.filed_date DESC")->fetch_all(MYSQLI_ASSOC);
$subjects = $conn->query("SELECT id, username, full_name, role FROM users WHERE role IN ('cadet','officer') ORDER BY role, full_name")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-gavel me-2"></i> Case Verdicts</h2>
        <p>Manage military disciplinary cases with evidence files</p>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="pmas-alert pmas-alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i> Case saved.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="pmas-alert pmas-alert-warning" data-auto-dismiss><i class="fas fa-trash"></i> Case deleted.</div>
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
                <?= $editEntry ? 'Edit Case' : 'File New Case' ?>
            </div>
            <div class="pmas-card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $editEntry['id'] ?? 0 ?>">

                    <div class="mb-3">
                        <label class="pmas-form-label">Case Number *</label>
                        <input type="text" name="case_number" class="form-control"
                               value="<?= sanitize($editEntry['case_number'] ?? 'PMA-CASE-' . date('Y') . '-') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Subject (Cadet/Officer) *</label>
                        <select name="subject_user_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($editEntry['subject_user_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>>
                                [<?= strtoupper($s['role']) ?>] <?= sanitize($s['username']) ?> — <?= sanitize($s['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Case Title *</label>
                        <input type="text" name="case_title" class="form-control"
                               value="<?= sanitize($editEntry['case_title'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Offense Category</label>
                        <select name="offense_category" class="form-select">
                            <?php $cats = ['Academic Dishonesty','Conduct Unbecoming','Violation of Honor Code','Misconduct','AWOL','Drug Related','Others'];
                            foreach ($cats as $cat): ?>
                            <option value="<?= $cat ?>" <?= ($editEntry['offense_category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= sanitize($editEntry['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="pmas-form-label">Filed Date *</label>
                            <input type="date" name="filed_date" class="form-control"
                                   value="<?= $editEntry['filed_date'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="pmas-form-label">Hearing Date</label>
                            <input type="date" name="hearing_date" class="form-control"
                                   value="<?= $editEntry['hearing_date'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Verdict</label>
                        <select name="verdict" class="form-select">
                            <?php foreach (['Pending','Guilty','Not Guilty','Dismissed','Under Appeal'] as $v): ?>
                            <option value="<?= $v ?>" <?= ($editEntry['verdict'] ?? 'Pending') === $v ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Sanction / Penalty</label>
                        <textarea name="sanction" class="form-control" rows="2"><?= sanitize($editEntry['sanction'] ?? '') ?></textarea>
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
                        <div id="caseEvidenceContainer">
                            <div class="evidence-row mb-2">
                                <input type="text" name="evidence_labels[]"
                                       class="form-control form-control-sm mb-1"
                                       placeholder="File label / description (optional)">
                                <input type="file" name="evidence_files[]"
                                       class="form-control form-control-sm"
                                       accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.mp4,.mp3,.txt">
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="addCaseEvidence">
                            <i class="fas fa-plus me-1"></i> Add Another File
                        </button>
                    </div>

                    <!-- Existing evidence (edit mode) -->
                    <?php if (!empty($editEvidence)): ?>
                    <div class="mt-3">
                        <label class="pmas-form-label">
                            <i class="fas fa-folder-open me-1"></i>
                            Existing Evidence (<?= count($editEvidence) ?>)
                        </label>
                        <?php foreach ($editEvidence as $ev): ?>
                        <div class="d-flex align-items-center gap-2 p-2 mb-1 rounded"
                             style="background:#f8f8f8;border-left:3px solid var(--pmas-red);">
                            <i class="fas fa-<?= getEvidenceIcon($ev['file_type'] ?? '') ?> text-pmas-red"></i>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:12px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= sanitize($ev['file_label']) ?>
                                </div>
                                <div style="font-size:10px;color:#888;">
                                    <?= sanitize($ev['file_name']) ?>
                                    &nbsp;|&nbsp; <?= round($ev['file_size'] / 1024, 1) ?> KB
                                    &nbsp;|&nbsp; <?= date('M d, Y', strtotime($ev['uploaded_at'])) ?>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>uploads/case_evidence/<?= urlencode($ev['file_path']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-primary" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="?del_evidence=<?= $ev['id'] ?>"
                               class="btn btn-sm btn-outline-danger" title="Delete"
                               data-confirm="Delete evidence '<?= sanitize($ev['file_label']) ?>'?">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <button type="submit" class="btn-pmas w-100">
                            <i class="fas fa-save me-1"></i> <?= $editEntry ? 'Update Case' : 'File Case' ?>
                        </button>
                        <?php if ($editEntry): ?>
                        <a href="<?= BASE_URL ?>pages/admin/cases.php" class="btn-pmas-dark w-100 mt-2 justify-content-center">
                            <i class="fas fa-times me-1"></i> Cancel
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
            <div class="pmas-card-header"><i class="fas fa-list"></i> Case Records (<?= count($cases) ?>)</div>
            <div class="pmas-card-body p-0">
                <div class="p-3">
                    <input type="text" id="tableSearch" class="form-control" placeholder="Search cases..." style="max-width:300px;">
                </div>

                <!-- Desktop table -->
                <div class="table-responsive desktop-only">
                <table class="pmas-table">
                    <thead>
                        <tr>
                            <th>Case No.</th>
                            <th>Subject</th>
                            <th>Offense</th>
                            <th>Filed</th>
                            <th>Verdict</th>
                            <th class="text-center">Evidence</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($cases)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No cases on file.</td></tr>
                    <?php else: foreach ($cases as $c): ?>
                        <tr>
                            <td><code><?= sanitize($c['case_number']) ?></code></td>
                            <td>
                                <?= sanitize($c['full_name']) ?>
                                <br><small class="text-muted"><?= strtoupper($c['role']) ?></small>
                            </td>
                            <td style="font-size:12px;"><?= sanitize($c['offense_category']) ?></td>
                            <td style="font-size:12px;"><?= date('M d, Y', strtotime($c['filed_date'])) ?></td>
                            <td><?= getStatusBadge($c['verdict']) ?></td>
                            <td class="text-center">
                                <?php if ($c['ev_count'] > 0): ?>
                                <span class="badge bg-primary"><i class="fas fa-paperclip me-1"></i><?= $c['ev_count'] ?></span>
                                <?php else: ?><span class="text-muted" style="font-size:11px;">—</span><?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger"
                                   data-confirm="Delete case <?= sanitize($c['case_number']) ?> and all evidence?">
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
                <?php if (empty($cases)): ?>
                    <p class="text-center text-muted py-3">No cases on file.</p>
                <?php else: foreach ($cases as $c): ?>
                <div class="border rounded mb-3 overflow-hidden" style="font-size:13px;">
                    <div class="p-3" style="background:#f8f8f8;border-bottom:1px solid #eee;">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                            <code style="font-size:11px;color:var(--pmas-red);"><?= sanitize($c['case_number']) ?></code>
                            <?= getStatusBadge($c['verdict']) ?>
                        </div>
                        <div class="fw-bold mb-1"><?= sanitize($c['full_name']) ?>
                            <small class="text-muted fw-normal">(<?= strtoupper($c['role']) ?>)</small>
                        </div>
                        <div style="font-size:12px;color:#666;">
                            <span class="me-2"><i class="fas fa-tag me-1"></i><?= sanitize($c['offense_category']) ?></span>
                            <span><i class="fas fa-calendar me-1"></i><?= date('M d, Y', strtotime($c['filed_date'])) ?></span>
                        </div>
                        <?php if ($c['ev_count'] > 0): ?>
                        <div class="mt-1">
                            <span class="badge bg-primary"><i class="fas fa-paperclip me-1"></i><?= $c['ev_count'] ?> evidence file(s)</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-2 d-flex gap-2" style="background:#fff;">
                        <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill text-center">
                            <i class="fas fa-edit me-1"></i> Edit / Evidence
                        </a>
                        <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger flex-fill text-center"
                           data-confirm="Delete case <?= sanitize($c['case_number']) ?> and all evidence?">
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
// Add more evidence file rows — Case
document.getElementById('addCaseEvidence').addEventListener('click', function () {
    const container = document.getElementById('caseEvidenceContainer');
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
