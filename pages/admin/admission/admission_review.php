<?php
require_once '../../../includes/config.php';
requireRole('admin','staff');
$pageTitle = 'Review Applicant';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) redirect(BASE_URL . 'pages/admin/admission/admission_applicants.php');

$applicant = $conn->query("
    SELECT a.*, ab.batch_name
    FROM applicants a
    JOIN admission_batches ab ON a.batch_id = ab.id
    WHERE a.id = $id
")->fetch_assoc();

if (!$applicant) redirect(BASE_URL . 'pages/admin/admission/admission_applicants.php');

$documents = $conn->query("SELECT * FROM applicant_documents WHERE applicant_id=$id ORDER BY uploaded_at")->fetch_all(MYSQLI_ASSOC);
$bgLogs    = $conn->query("SELECT bc.*, u.full_name AS checker_name FROM bg_check_log bc LEFT JOIN users u ON bc.checked_by=u.id WHERE bc.applicant_id=$id ORDER BY bc.checked_at DESC")->fetch_all(MYSQLI_ASSOC);

$error   = '';
$success = '';

// 2-day check — compute from bg_check_start (bg_check_due is not a stored column in MySQL)
$bgDue   = !empty($applicant['bg_check_start'])
           ? date('Y-m-d H:i:s', strtotime($applicant['bg_check_start'] . ' +2 days'))
           : null;
$now     = date('Y-m-d H:i:s');
$bgReady = $bgDue && $now >= $bgDue;

// ---- HANDLE ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    // Set status to under_review
    if ($action === 'start_review') {
        if (!$bgReady) {
            $error = 'The 2-day evaluation period has not yet elapsed. You cannot start the review yet.';
        } else {
            $conn->query("UPDATE applicants SET submission_status='under_review' WHERE id=$id");
            logAction($conn, $_SESSION['user_id'], 'ADM_START_REVIEW', 'Admission', "Applicant #$id");
            $success = 'Review started.';
            $applicant['submission_status'] = 'under_review';
        }
    }

    // Update individual document status
    if ($action === 'update_doc') {
        $docId     = (int)$_POST['doc_id'];
        $docStatus = sanitize($_POST['doc_status']);
        $docReason = sanitize($_POST['rejection_reason'] ?? '');
        $reviewedBy= $_SESSION['user_id'];
        $stmt = $conn->prepare("UPDATE applicant_documents SET upload_status=?,rejection_reason=?,reviewed_by=?,reviewed_at=NOW() WHERE id=? AND applicant_id=?");
        $stmt->bind_param('sssii', $docStatus, $docReason, $reviewedBy, $docId, $id);
        $stmt->execute(); $stmt->close();

        // Re-check docs_ok
        $pendingDocs = $conn->query("SELECT COUNT(*) FROM applicant_documents WHERE applicant_id=$id AND upload_status='rejected'")->fetch_row()[0];
        $docsOk = $pendingDocs == 0 ? 1 : 0;
        $conn->query("UPDATE applicants SET eval_docs_ok=$docsOk WHERE id=$id");
        $success = 'Document status updated.';
        $applicant = $conn->query("SELECT a.*, ab.batch_name FROM applicants a JOIN admission_batches ab ON a.batch_id=ab.id WHERE a.id=$id")->fetch_assoc();
        $documents = $conn->query("SELECT * FROM applicant_documents WHERE applicant_id=$id ORDER BY uploaded_at")->fetch_all(MYSQLI_ASSOC);
    }

    // Add BG check log
    if ($action === 'add_bg_log') {
        $checkType = sanitize($_POST['check_type']);
        $findings  = sanitize($_POST['findings']);
        $bgResult  = sanitize($_POST['bg_result']);
        $checkedBy = $_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO bg_check_log (applicant_id, checked_by, check_type, findings, result) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iisss', $id, $checkedBy, $checkType, $findings, $bgResult);
        $stmt->execute(); $stmt->close();
        $success = 'Background check log added.';
        $bgLogs  = $conn->query("SELECT bc.*, u.full_name AS checker_name FROM bg_check_log bc LEFT JOIN users u ON bc.checked_by=u.id WHERE bc.applicant_id=$id ORDER BY bc.checked_at DESC")->fetch_all(MYSQLI_ASSOC);
    }

    // Final verdict
    if ($action === 'set_verdict') {
        $verdict = sanitize($_POST['verdict']);
        $notes   = sanitize($_POST['review_notes']);
        $reviewedBy = $_SESSION['user_id'];
        if (!in_array($verdict, ['passed','failed'])) {
            $error = 'Invalid verdict.';
        } else {
            $stmt = $conn->prepare("UPDATE applicants SET submission_status=?,review_notes=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?");
            $stmt->bind_param('ssii', $verdict, $notes, $reviewedBy, $id);
            $stmt->execute(); $stmt->close();
            logAction($conn, $_SESSION['user_id'], 'ADM_VERDICT', 'Admission', "Applicant #$id — $verdict");
            $success = "Applicant has been marked as <strong>" . strtoupper($verdict) . "</strong>.";
            $applicant['submission_status'] = $verdict;
            $applicant['review_notes']      = $notes;
        }
    }
}

// Re-evaluate
$evalFlags = evaluateApplicant($applicant);

include '../../../includes/header.php'; include '../../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-user-check me-2"></i> Review Applicant</h2>
        <p>
            <?= sanitize($applicant['last_name']) ?>, <?= sanitize($applicant['first_name']) ?>
            &nbsp;&mdash;&nbsp; <code><?= sanitize($applicant['tracking_number']) ?></code>
        </p>
    </div>
    <a href="<?= BASE_URL ?>pages/admin/admission/admission_applicants.php?batch_id=<?= $applicant['batch_id'] ?>" class="btn-pmas-dark">
        <i class="fas fa-arrow-left me-1"></i> Back to List
    </a>
</div>

<?php if ($error): ?><div class="pmas-alert pmas-alert-danger"><i class="fas fa-times-circle"></i> <?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="pmas-alert pmas-alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i> <?= $success ?></div><?php endif; ?>

<!-- 2-DAY NOTICE -->
<?php if ($applicant['submission_status'] === 'submitted'): ?>
<div class="pmas-alert <?= $bgReady ? 'pmas-alert-success' : 'pmas-alert-warning' ?> mb-3">
    <i class="fas fa-<?= $bgReady ? 'check-circle' : 'hourglass-half' ?> fa-lg me-2"></i>
    <div>
        <?php if ($bgReady): ?>
            <strong>2-Day evaluation period has elapsed.</strong> You may now begin the review.
            <form method="POST" class="d-inline ms-3">
                <input type="hidden" name="action" value="start_review">
                <button type="submit" class="btn-pmas btn-sm">
                    <i class="fas fa-play me-1"></i> Start Review
                </button>
            </form>
        <?php else: ?>
            <strong>2-Day Evaluation Period in Progress.</strong>
            Background check due: <strong><?= date('M d, Y g:i A', strtotime($bgDue)) ?></strong>
            &nbsp;(<?= round(max(0, (strtotime($bgDue) - time()) / 3600)) ?> hours remaining)
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- STATUS BADGE -->
<div class="d-flex gap-3 mb-4 align-items-center flex-wrap">
    <div><?= getAdmissionStatusBadge($applicant['submission_status']) ?></div>
    <?php if ($applicant['auto_eligible']): ?>
    <span class="badge bg-success"><i class="fas fa-robot me-1"></i> Auto-Evaluated: ELIGIBLE</span>
    <?php else: ?>
    <span class="badge bg-warning text-dark"><i class="fas fa-robot me-1"></i> Auto-Evaluated: Criteria not fully met</span>
    <?php endif; ?>
</div>

<div class="row">

    <!-- LEFT: APPLICANT INFO + EVAL -->
    <div class="col-lg-7">

        <!-- PERSONAL INFO -->
        <div class="pmas-card mb-4">
            <div class="pmas-card-header"><i class="fas fa-user"></i> Information</div>
            <div class="pmas-card-body">
                <div class="row g-2" style="font-size:13.5px;">
                    <?php
                    $fields = [
                        ['Username',              $applicant['last_name']],
                        ['PA Rank',               $applicant['first_name']],
                        ['Discord Username & ID', $applicant['email']],
                        ['Profile Link (Roblox)', $applicant['contact_number']],
                        ['Grade in Admission Result', $applicant['permanent_address']],
                        ['Unit / Department',     $applicant['current_address']],
                        ['Roblox Age',            $applicant['middle_name']],
                        ['Discord Age',           $applicant['suffix']],
                    ];
                    foreach ($fields as [$label, $value]):
                    ?>
                    <div class="col-md-6">
                        <span class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;"><?= $label ?></span><br>
                        <strong><?= sanitize($value) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- AUTO-EVALUATION CHECKLIST -->
        <div class="pmas-card mb-4">
            <div class="pmas-card-header"><i class="fas fa-clipboard-check"></i> Auto-Evaluation Results</div>
            <div class="pmas-card-body">
                <?php
                $checks = [
                    ['eval_age_ok',    'Age Requirement',      'Must be 17–22 years old',                $applicant['eval_age_ok']],
                    ['eval_height_ok', 'Height Requirement',   'Male ≥162cm / Female ≥157cm',            $applicant['eval_height_ok']],
                    ['eval_ee_ok',     'Entrance Exam Passed', 'Applicant confirmed EE pass',            $applicant['eval_ee_ok']],
                    ['eval_gwa_ok',    'GWA Requirement',      'General Weighted Average ≥ 85',          $applicant['eval_gwa_ok']],
                    ['eval_docs_ok',   'Documents Uploaded',   'All required documents submitted',       $applicant['eval_docs_ok']],
                ];
                foreach ($checks as [$key, $label, $hint, $val]):
                ?>
                <div class="adm-eval-item <?= $val ? 'pass' : 'fail' ?> mb-2">
                    <i class="fas fa-<?= $val ? 'check-circle' : 'times-circle' ?>"></i>
                    <div>
                        <strong><?= $label ?></strong>
                        <small class="d-block text-muted"><?= $hint ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="mt-3 p-3 rounded" style="background:<?= $applicant['auto_eligible'] ? 'rgba(40,167,69,0.1)' : 'rgba(192,0,26,0.07)' ?>">
                    <strong>Overall Auto-Evaluation:</strong>
                    <?= $applicant['auto_eligible']
                        ? '<span class="badge bg-success ms-2">ELIGIBLE</span>'
                        : '<span class="badge bg-warning text-dark ms-2">NOT FULLY ELIGIBLE</span>' ?>
                    <small class="d-block text-muted mt-1">This is a system-generated pre-check. Final decision rests with the reviewer.</small>
                </div>
            </div>
        </div>

        <!-- BACKGROUND CHECK LOG -->
        <div class="pmas-card mb-4">
            <div class="pmas-card-header"><i class="fas fa-search"></i> Background Check Log</div>
            <div class="pmas-card-body">
                <?php if (in_array($applicant['submission_status'], ['under_review','passed','failed'])): ?>
                <form method="POST" class="mb-3">
                    <input type="hidden" name="action" value="add_bg_log">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="pmas-form-label">Check Type</label>
                            <input type="text" name="check_type" class="form-control" value="General Background">
                        </div>
                        <div class="col-md-4">
                            <label class="pmas-form-label">Result</label>
                            <select name="bg_result" class="form-select">
                                <option value="Clear">Clear</option>
                                <option value="With Record">With Record</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="pmas-form-label">Findings</label>
                            <textarea name="findings" class="form-control" rows="2" placeholder="Findings or notes from background check..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn-pmas btn-sm"><i class="fas fa-plus me-1"></i> Add Log</button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>

                <?php if (empty($bgLogs)): ?>
                <p class="text-muted text-center py-3">No background check logs yet.</p>
                <?php else: ?>
                <?php foreach ($bgLogs as $log): ?>
                <div class="d-flex gap-3 p-3 rounded mb-2" style="background:#f8f8f8;border-left:3px solid <?= $log['result']==='Clear' ? 'var(--pmas-red)' : ($log['result']==='With Record' ? '#dc3545' : '#ffc107') ?>;">
                    <div>
                        <span class="badge bg-<?= $log['result']==='Clear' ? 'success' : ($log['result']==='With Record' ? 'danger' : 'warning text-dark') ?>">
                            <?= $log['result'] ?>
                        </span>
                    </div>
                    <div style="flex:1;">
                        <strong style="font-size:12px;"><?= sanitize($log['check_type']) ?></strong>
                        <div style="font-size:12px;"><?= sanitize($log['findings'] ?? 'No findings noted.') ?></div>
                        <div style="font-size:11px;color:#888;">
                            By: <?= sanitize($log['checker_name'] ?? 'System') ?>
                            &nbsp;|&nbsp; <?= date('M d, Y g:i A', strtotime($log['checked_at'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: DOCUMENTS + VERDICT -->
    <div class="col-lg-5">

        <!-- DOCUMENTS REVIEW -->
        <div class="pmas-card mb-4">
            <div class="pmas-card-header"><i class="fas fa-file-alt"></i> Uploaded Documents (<?= count($documents) ?>)</div>
            <div class="pmas-card-body">
                <?php if (empty($documents)): ?>
                <p class="text-muted text-center py-3">No documents uploaded.</p>
                <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                <div class="adm-doc-review-item mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <strong style="font-size:12px;"><?= sanitize($doc['doc_label']) ?></strong>
                        <span class="badge bg-<?= ['pending'=>'warning text-dark','verified'=>'success','rejected'=>'danger'][$doc['upload_status']] ?>">
                            <?= ucfirst($doc['upload_status']) ?>
                        </span>
                    </div>
                    <div style="font-size:11px;color:#888;" class="mb-2">
                        <?= sanitize($doc['file_name']) ?>
                        <?php if ($doc['file_size']): ?>
                        (<?= round($doc['file_size'] / 1024, 1) ?> KB)
                        <?php endif; ?>
                        &nbsp;|&nbsp;
                        <a href="<?= BASE_URL ?>uploads/admission/<?= urlencode($doc['file_path']) ?>" target="_blank" class="text-pmas-red">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                    </div>
                    <?php if ($doc['rejection_reason']): ?>
                    <div style="font-size:11px;color:#dc3545;" class="mb-2">
                        Rejection reason: <?= sanitize($doc['rejection_reason']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (in_array($applicant['submission_status'], ['under_review','passed','failed'])): ?>
                    <form method="POST" class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="hidden" name="action" value="update_doc">
                        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                        <select name="doc_status" class="form-select form-select-sm" style="max-width:120px;">
                            <option value="pending"  <?= $doc['upload_status']==='pending'   ? 'selected' : '' ?>>Pending</option>
                            <option value="verified" <?= $doc['upload_status']==='verified'  ? 'selected' : '' ?>>Verified</option>
                            <option value="rejected" <?= $doc['upload_status']==='rejected'  ? 'selected' : '' ?>>Rejected</option>
                        </select>
                        <input type="text" name="rejection_reason" class="form-control form-control-sm" style="max-width:150px;" placeholder="Rejection reason">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-save"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- FINAL VERDICT -->
        <?php if (in_array($applicant['submission_status'], ['under_review','passed','failed'])): ?>
        <div class="pmas-card">
            <div class="pmas-card-header"><i class="fas fa-gavel"></i> Final Admission Verdict</div>
            <div class="pmas-card-body">
                <?php if (in_array($applicant['submission_status'], ['passed','failed'])): ?>
                <div class="adm-result-banner <?= $applicant['submission_status'] ?> mb-3" style="padding:14px 16px;">
                    <i class="fas fa-<?= $applicant['submission_status'] === 'passed' ? 'trophy' : 'times-circle' ?> fa-2x"></i>
                    <div>
                        <strong style="font-size:16px;"><?= strtoupper($applicant['submission_status']) ?></strong>
                        <?php if ($applicant['review_notes']): ?>
                        <div style="font-size:12px;opacity:0.85;margin-top:4px;"><?= sanitize($applicant['review_notes']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted" style="font-size:12px;">
                    Reviewed by: <?php
                        $reviewerName = 'N/A';
                        if (!empty($applicant['reviewed_by'])) {
                            $rRow = $conn->query("SELECT full_name FROM users WHERE id=" . (int)$applicant['reviewed_by']);
                            if ($rRow) $reviewerName = $rRow->fetch_row()[0] ?? 'N/A';
                        }
                        echo sanitize($reviewerName);
                    ?>
                    &nbsp;|&nbsp; <?= $applicant['reviewed_at'] ? date('M d, Y g:i A', strtotime($applicant['reviewed_at'])) : '—' ?>
                </p>
                <!-- Allow re-verdict -->
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="set_verdict">
                    <div class="mb-3">
                        <label class="pmas-form-label">Set Final Verdict *</label>
                        <select name="verdict" class="form-select" required>
                            <option value="">-- Select --</option>
                            <option value="passed" <?= $applicant['submission_status']==='passed' ? 'selected' : '' ?>>✓ PASSED — Admitted</option>
                            <option value="failed" <?= $applicant['submission_status']==='failed' ? 'selected' : '' ?>>✗ FAILED — Not Admitted</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Review Notes / Remarks</label>
                        <textarea name="review_notes" class="form-control" rows="3"><?= sanitize($applicant['review_notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn-pmas w-100" onclick="return confirm('Set this verdict? This will be visible to the applicant.')">
                        <i class="fas fa-gavel me-1"></i> Submit Verdict
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
