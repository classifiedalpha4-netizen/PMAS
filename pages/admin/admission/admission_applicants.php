<?php
require_once '../../../includes/config.php';
requireRole('admin','staff');
$pageTitle = 'Admission Applicants';

$filterBatch  = isset($_GET['batch_id']) && is_numeric($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$filterStatus = sanitize($_GET['status'] ?? '');

$batches = $conn->query("SELECT * FROM admission_batches ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Default to first active batch
if (!$filterBatch && !empty($batches)) {
    foreach ($batches as $b) {
        if ($b['is_active']) { $filterBatch = $b['id']; break; }
    }
    if (!$filterBatch) $filterBatch = $batches[0]['id'];
}

$where = "WHERE a.batch_id = $filterBatch";
if ($filterStatus) $where .= " AND a.submission_status = '" . $conn->real_escape_string($filterStatus) . "'";

$applicants = $conn->query("
    SELECT a.*,
           (SELECT COUNT(*) FROM applicant_documents ad WHERE ad.applicant_id = a.id) AS doc_count,
           TIMESTAMPDIFF(HOUR, a.bg_check_start, NOW()) AS hours_since_submission
    FROM applicants a
    $where
    ORDER BY a.submitted_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Count stats
$stats = $conn->query("
    SELECT submission_status, COUNT(*) as cnt
    FROM applicants
    WHERE batch_id = $filterBatch
    GROUP BY submission_status
")->fetch_all(MYSQLI_ASSOC);

$statusCounts = [];
foreach ($stats as $s) $statusCounts[$s['submission_status']] = $s['cnt'];

include '../../../includes/header.php'; include '../../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-file-alt me-2"></i> Admission Applicants</h2>
        <p>Review and evaluate cadetship applicants</p>
    </div>
    <a href="<?= BASE_URL ?>pages/admission/apply.php" class="btn-pmas-outline" target="_blank">
        <i class="fas fa-external-link-alt me-1"></i> Public Application Page
    </a>
</div>

<!-- BATCH + STATUS FILTER -->
<div class="pmas-card mb-4">
    <div class="pmas-card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="pmas-form-label">Admission Batch</label>
                <select name="batch_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($batches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $b['id'] == $filterBatch ? 'selected' : '' ?>>
                        <?= sanitize($b['batch_name']) ?> <?= $b['is_active'] ? '(Active)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="pmas-form-label">Status Filter</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach (['submitted','under_review','passed','failed','withdrawn'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn-pmas w-100"><i class="fas fa-filter me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- STAT BADGES -->
<div class="row g-2 mb-4">
    <?php
    $statDefs = [
        'submitted'    => ['warning', 'Submitted',     'fa-file-alt'],
        'under_review' => ['info',    'Under Review',  'fa-search'],
        'passed'       => ['success', 'Passed',        'fa-check-circle'],
        'failed'       => ['danger',  'Failed',        'fa-times-circle'],
        'withdrawn'    => ['secondary','Withdrawn',    'fa-user-minus'],
    ];
    foreach ($statDefs as $key => [$color, $label, $icon]):
    ?>
    <div class="col">
        <a href="?batch_id=<?= $filterBatch ?>&status=<?= $key ?>" class="text-decoration-none">
            <div class="stat-card <?= $key === $filterStatus ? 'red' : 'black' ?>" style="padding:14px;">
                <div class="stat-card-icon" style="font-size:24px;"><i class="fas <?= $icon ?>"></i></div>
                <div class="stat-card-info">
                    <h3 style="font-size:22px;"><?= $statusCounts[$key] ?? 0 ?></h3>
                    <p style="font-size:11px;"><?= $label ?></p>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- APPLICANTS TABLE -->
<div class="pmas-card">
    <div class="pmas-card-header">
        <i class="fas fa-users"></i>
        Applicants (<?= count($applicants) ?>)
    </div>
    <div class="pmas-card-body p-0">
        <div class="p-3">
            <input type="text" id="tableSearch" class="form-control" placeholder="Search applicants..." style="max-width:300px;">
        </div>

        <!-- Desktop table -->
        <div class="table-responsive desktop-only">
        <table class="pmas-table">
            <thead>
                <tr>
                    <th>Tracking #</th>
                    <th>Username</th>
                    <th>Discord/ID</th>
                    <th>Auto-Eval</th>
                    <th>Docs</th>
                    <th>Submitted</th>
                    <th>BG Check Due</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($applicants)): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">No applicants found.</td></tr>
            <?php else: foreach ($applicants as $ap): ?>
            <?php
            $bgDue      = $ap['bg_check_due'];
            $now        = date('Y-m-d H:i:s');
            $bgReady    = $bgDue && $now >= $bgDue;
            $hoursLeft  = $bgDue ? max(0, (strtotime($bgDue) - time()) / 3600) : null;
            ?>
            <tr>
                <td><code style="font-size:11px;"><?= sanitize($ap['tracking_number']) ?></code></td>
                <td>
                    <strong><?= sanitize($ap['last_name']) ?></strong>
                    <br><small class="text-muted"><?= sanitize($ap['email']) ?></small>
                </td>
                <td style="font-size:12px;"><?= sanitize($ap['email']) ?></td>
                <td>
                    <?php if ($ap['auto_eligible']): ?>
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Eligible</span>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark">
                        <?= ($ap['eval_age_ok']+$ap['eval_height_ok']+$ap['eval_ee_ok']+$ap['eval_gwa_ok']+$ap['eval_docs_ok']) ?>/5
                    </span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="badge bg-<?= $ap['doc_count'] > 0 ? 'primary' : 'secondary' ?>">
                        <?= $ap['doc_count'] ?>
                    </span>
                </td>
                <td style="font-size:11px;"><?= $ap['submitted_at'] ? date('M d, Y', strtotime($ap['submitted_at'])) : '—' ?></td>
                <td style="font-size:11px;">
                    <?php if ($bgDue): ?>
                        <?php if ($bgReady): ?>
                        <span class="badge bg-success">Ready</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark">~<?= round($hoursLeft) ?>h</span>
                        <?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><?= getAdmissionStatusBadge($ap['submission_status']) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>pages/admin/admission/admission_review.php?id=<?= $ap['id'] ?>"
                       class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="mobile-only p-3">
        <?php if (empty($applicants)): ?>
            <p class="text-center text-muted py-3">No applicants found.</p>
        <?php else: foreach ($applicants as $ap):
            $bgDue   = $ap['bg_check_due'];
            $now     = date('Y-m-d H:i:s');
            $bgReady = $bgDue && $now >= $bgDue;
            $hoursLeft = $bgDue ? max(0,(strtotime($bgDue)-time())/3600) : null;
        ?>
        <div class="border rounded mb-3 overflow-hidden" style="font-size:13px;">
            <div class="p-3" style="background:#f8f8f8;border-bottom:1px solid #eee;">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                    <code style="font-size:11px;color:var(--pmas-red);"><?= sanitize($ap['tracking_number']) ?></code>
                    <?= getAdmissionStatusBadge($ap['submission_status']) ?>
                </div>
                <div class="fw-bold mb-1"><?= sanitize($ap['last_name']) ?></div>
                <div style="font-size:12px;color:#666;" class="mb-1">
                    <i class="fas fa-comment me-1"></i><?= sanitize($ap['email']) ?>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php if ($ap['auto_eligible']): ?>
                    <span class="badge bg-success">Auto-Eligible</span>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark">Eval: <?= ($ap['eval_age_ok']+$ap['eval_height_ok']+$ap['eval_ee_ok']+$ap['eval_gwa_ok']+$ap['eval_docs_ok']) ?>/5</span>
                    <?php endif; ?>
                    <span class="badge bg-<?= $ap['doc_count'] > 0 ? 'primary' : 'secondary' ?>">
                        <i class="fas fa-file me-1"></i><?= $ap['doc_count'] ?> doc(s)
                    </span>
                    <?php if ($bgDue): ?>
                        <?php if ($bgReady): ?>
                        <span class="badge bg-success">Ready for Review</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark">~<?= round($hoursLeft) ?>h left</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if ($ap['submitted_at']): ?>
                <div style="font-size:11px;color:#999;margin-top:4px;">
                    Submitted: <?= date('M d, Y', strtotime($ap['submitted_at'])) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="p-2" style="background:#fff;">
                <a href="<?= BASE_URL ?>pages/admin/admission/admission_review.php?id=<?= $ap['id'] ?>"
                   class="btn-pmas w-100 justify-content-center">
                    <i class="fas fa-eye me-1"></i> Review Applicant
                </a>
            </div>
        </div>
        <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
