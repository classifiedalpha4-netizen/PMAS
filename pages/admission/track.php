<?php
/**
 * PMAS Admission Portal Ã¢â‚¬â€ Application Tracking
 * Public-facing. Applicant enters their tracking number to check status.
 */
require_once '../../includes/config.php';

$applicant = null;
$documents = [];
$error     = '';
$tn        = sanitize($_GET['tn'] ?? $_POST['tracking_number'] ?? '');

if (!empty($tn)) {
    $stmt = $conn->prepare("
        SELECT a.*, ab.batch_name
        FROM applicants a
        JOIN admission_batches ab ON a.batch_id = ab.id
        WHERE a.tracking_number = ?
    ");
    $stmt->bind_param('s', $tn);
    $stmt->execute();
    $applicant = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$applicant) {
        $error = 'No application found with that tracking number. Please double-check and try again.';
    } else {
        $documents = $conn->query("SELECT * FROM applicant_documents WHERE applicant_id={$applicant['id']}")->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Application | PMAS Admission</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admission.css">
</head>
<body>

<nav class="pmas-navbar navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>index.php">
            <div class="pmas-logo-circle">
                <img src="<?= BASE_URL ?>assets/img/pma_logo.png" alt="PMA Logo">
            </div>
            <div>
                <div class="pmas-brand-title">PMAS Portal</div>
                <div class="pmas-brand-sub">Application Tracker</div>
            </div>
        </a>
        <div class="ms-auto d-flex gap-1 flex-shrink-0">
            <a href="<?= BASE_URL ?>pages/admission/apply.php"
               class="btn btn-outline-warning btn-sm px-2 px-md-3">
                <i class="fas fa-file-alt me-1"></i>
                <span class="d-none d-sm-inline">Apply</span>
                <span class="d-sm-none">Apply</span>
            </a>
            <a href="<?= BASE_URL ?>pages/admission/results.php"
               class="btn btn-outline-light btn-sm px-2 px-md-3">
                <i class="fas fa-trophy me-1"></i>
                <span class="d-none d-sm-inline">Results</span>
                <span class="d-sm-none">Results</span>
            </a>
        </div>
    </div>
</nav>

<div class="admission-hero" style="min-height:140px;">
    <div class="container text-center">
        <h1 class="adm-title" style="font-size:28px;">Application Status Tracker</h1>
        <p class="adm-subtitle" style="font-size:14px;">Enter your tracking number to check your admission status</p>
    </div>
</div>

<div class="container admission-container" style="max-width:700px;">

    <!-- SEARCH FORM -->
    <div class="adm-section">
        <div class="adm-section-header"><i class="fas fa-search"></i> Track Your Application</div>
        <div class="adm-section-body">
            <form method="GET">
                <div class="input-group">
                    <input type="text" name="tn" class="form-control form-control-lg"
                           placeholder="e.g. PMA-APP-2026-00001"
                           value="<?= sanitize($tn) ?>">
                    <button type="submit" class="btn-pmas" style="border-radius:0 6px 6px 0;">
                        <i class="fas fa-search me-1"></i> Track
                    </button>
                </div>
                <div class="form-text">Your tracking number was provided upon successful submission of your application.</div>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="pmas-alert pmas-alert-danger"><i class="fas fa-times-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <?php if ($applicant): ?>

    <?php
    // Determine current stage
    $status = $applicant['submission_status'];
    ?>

    <!-- STATUS CARD -->
    <div class="adm-section">
        <div class="adm-section-header">
            <i class="fas fa-id-card"></i>
            <?= sanitize($applicant['last_name']) ?>, <?= sanitize($applicant['first_name']) ?>
            &nbsp;Ã¢â‚¬â€&nbsp; <?= sanitize($applicant['tracking_number']) ?>
        </div>
        <div class="adm-section-body">

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <table class="table table-borderless table-sm mb-0" style="font-size:13.5px;">
                        <tr><td class="text-muted" style="width:45%;">Username</td>
                            <td><strong><?= sanitize($applicant['last_name']) ?></strong></td></tr>
                        <tr><td class="text-muted">Batch</td><td><?= sanitize($applicant['batch_name']) ?></td></tr>
                        <tr><td class="text-muted">Submitted</td><td><?= $applicant['submitted_at'] ? date('M d, Y g:i A', strtotime($applicant['submitted_at'])) : 'Ã¢â‚¬â€' ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6 d-flex align-items-center justify-content-center">
                    <div class="text-center">
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#888;mb-2;">Application Status</div>
                        <div style="font-size:22px;"><?= getAdmissionStatusBadge($status) ?></div>
                    </div>
                </div>
            </div>

            <!-- PROGRESS TIMELINE -->
            <div class="adm-track-timeline">
                <?php
                $stages = [
                    ['submitted',    'Application Submitted', 'fa-file-alt',    in_array($status, ['submitted','under_review','passed','failed'])],
                    ['under_review', 'Under Review',          'fa-search',      in_array($status, ['under_review','passed','failed'])],
                    ['result',       'Results Released',      'fa-scroll',      in_array($status, ['passed','failed'])],
                ];
                foreach ($stages as $s):
                    $done = $s[3];
                ?>
                <div class="adm-track-item <?= $done ? 'done' : '' ?>">
                    <div class="adm-track-dot"><i class="fas <?= $s[2] ?>"></i></div>
                    <div class="adm-track-label"><?= $s[1] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ELIGIBILITY FLAGS -->
            <?php if (in_array($status, ['submitted','under_review','passed','failed'])): ?>
            <h6 class="mt-4 mb-2 fw-bold" style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#666;">
                <i class="fas fa-clipboard-check me-1"></i> Auto-Evaluation Checklist
            </h6>
            <div class="row g-2 mb-3">
                <?php
                $checks = [
                    ['eval_ee_ok',   'Entrance Exam Passed',          'EE pass confirmed'],
                    ['eval_docs_ok', 'Required Documents Uploaded',   'All required documents submitted'],
                ];
                foreach ($checks as [$key, $label, $hint]):
                    $val = $applicant[$key];
                ?>
                <div class="col-md-6">
                    <div class="adm-eval-item <?= $val ? 'pass' : 'fail' ?>">
                        <i class="fas fa-<?= $val ? 'check-circle' : 'times-circle' ?>"></i>
                        <div>
                            <strong><?= $label ?></strong>
                            <small><?= $hint ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- RESULT -->
            <?php if ($status === 'passed' || $status === 'failed'): ?>
            <div class="adm-result-banner <?= $status === 'passed' ? 'pass' : 'fail' ?>">
                <div class="adm-result-icon">
                    <i class="fas fa-<?= $status === 'passed' ? 'scroll' : 'times-circle' ?>"></i>
                </div>
                <div>
                    <strong style="font-size:18px;">
                        <?= $status === 'passed' ? 'CONGRATULATIONS! You PASSED.' : 'We regret to inform you that you did NOT PASS.' ?>
                    </strong>
                    <?php if ($applicant['review_notes']): ?>
                    <div style="margin-top:6px;font-size:13px;opacity:0.9;"><?= sanitize($applicant['review_notes']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($status === 'submitted'): ?>
            <div class="adm-notice info">
                <i class="fas fa-clock"></i>
                <div>Your application has been <strong>submitted</strong> and is awaiting review by PMA Administration. You will be notified once a decision has been made.</div>
            </div>
            <?php elseif ($status === 'under_review'): ?>
            <div class="adm-notice warning">
                <i class="fas fa-search"></i>
                <div>Your application is <strong>actively under review</strong> by PMA Administration. Results will be posted soon.</div>
            </div>
            <?php endif; ?>

            <!-- DOCUMENTS STATUS -->
            <?php if (!empty($documents)): ?>
            <h6 class="mt-4 mb-2 fw-bold" style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#666;">
                <i class="fas fa-file-alt me-1"></i> Uploaded Documents (<?= count($documents) ?>)
            </h6>
            <div class="row g-2">
                <?php foreach ($documents as $doc): ?>
                <div class="col-md-6">
                    <div class="adm-doc-status-item <?= $doc['upload_status'] ?>">
                        <i class="fas fa-file-<?= str_contains($doc['file_type'] ?? '', 'pdf') ? 'pdf' : 'image' ?>"></i>
                        <div>
                            <div class="fw-bold" style="font-size:12px;"><?= sanitize($doc['doc_label']) ?></div>
                            <div style="font-size:11px;opacity:0.75;">
                                <?= sanitize($doc['file_name']) ?>
                                &nbsp;|&nbsp; <?= ucfirst($doc['upload_status']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php endif; ?>

    <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>pages/admission/apply.php" class="btn-pmas-outline me-2">
            <i class="fas fa-file-alt me-1"></i> Apply
        </a>
        <a href="<?= BASE_URL ?>pages/admission/results.php" class="btn-pmas-outline">
            <i class="fas fa-trophy me-1"></i> View Results Board
        </a>
    </div>

</div>

<footer class="pmas-footer mt-5">
    <div class="container text-center">
        &copy; <?= date('Y') ?> <strong>PMAS Portal</strong> &mdash; Philippine Army Roleplay
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
