<?php
require_once '../../../includes/config.php';
requireRole('admin','staff');
$pageTitle = 'Admission Results Board';

$batches = $conn->query("SELECT * FROM admission_batches ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$filterBatch = isset($_GET['batch_id']) && is_numeric($_GET['batch_id']) ? (int)$_GET['batch_id'] : ($batches[0]['id'] ?? 0);

// Release results action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_results'])) {
    $batchId = (int)$_POST['batch_id'];
    // Mark all passed applicants as having result_released_at = NOW()
    $conn->query("UPDATE applicants SET result_released_at = NOW() WHERE batch_id=$batchId AND submission_status='passed' AND result_released_at IS NULL");
    logAction($conn, $_SESSION['user_id'], 'RELEASE_RESULTS', 'Admission', "Batch #$batchId results released");
    $success = 'Results released successfully. They are now visible on the public Results Board.';
}

$success = $success ?? '';

$passedList = [];
$failedList = [];
$pendingList= [];

if ($filterBatch) {
    $passedList  = $conn->query("SELECT a.id, a.tracking_number, a.last_name, a.first_name,
                                        a.email, a.school_address, a.permanent_address, a.current_address,
                                        a.middle_name, a.suffix, a.result_released_at, a.review_notes
                                 FROM applicants a WHERE a.batch_id=$filterBatch AND a.submission_status='passed' ORDER BY a.last_name")->fetch_all(MYSQLI_ASSOC);
    $failedList  = $conn->query("SELECT a.id, a.tracking_number, a.last_name, a.first_name,
                                        a.email, a.review_notes
                                 FROM applicants a WHERE a.batch_id=$filterBatch AND a.submission_status='failed' ORDER BY a.last_name")->fetch_all(MYSQLI_ASSOC);
    $pendingList = $conn->query("SELECT a.*,
                                        DATE_ADD(a.bg_check_start, INTERVAL 2 DAY) AS bg_check_due
                                 FROM applicants a WHERE a.batch_id=$filterBatch AND a.submission_status IN ('submitted','under_review') ORDER BY a.submitted_at")->fetch_all(MYSQLI_ASSOC);
}

$currentBatch = null;
foreach ($batches as $b) { if ($b['id'] == $filterBatch) { $currentBatch = $b; break; } }

// Count unreleased passed
$unreleasedCount = 0;
foreach ($passedList as $p) { if (!$p['result_released_at']) $unreleasedCount++; }

include '../../../includes/header.php'; include '../../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-scroll me-2"></i> Admission Results Board</h2>
        <p>Manage and release official admission results</p>
    </div>
    <a href="<?= BASE_URL ?>pages/admission/results.php" class="btn-pmas-outline" target="_blank">
        <i class="fas fa-external-link-alt me-1"></i> Public Results Page
    </a>
</div>

<?php if ($success): ?>
<div class="pmas-alert pmas-alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<!-- BATCH SELECTOR -->
<div class="pmas-card mb-4">
    <div class="pmas-card-body py-3">
        <form method="GET" class="d-flex gap-3 align-items-center flex-wrap">
            <label class="pmas-form-label mb-0">Batch:</label>
            <select name="batch_id" class="form-select" style="max-width:420px;" onchange="this.form.submit()">
                <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id'] == $filterBatch ? 'selected' : '' ?>>
                    <?= sanitize($b['batch_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- SUMMARY STATS -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card info">
            <div class="stat-card-icon"><i class="fas fa-users"></i></div>
            <div class="stat-card-info">
                <h3><?= count($passedList) + count($failedList) + count($pendingList) ?></h3>
                <p>Total Applicants</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card black" style="background:linear-gradient(135deg,#155724,#28a745);">
            <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-card-info">
                <h3><?= count($passedList) ?></h3>
                <p>Passed</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card red">
            <div class="stat-card-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-card-info">
                <h3><?= count($failedList) ?></h3>
                <p>Failed</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card gold">
            <div class="stat-card-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-card-info">
                <h3><?= count($pendingList) ?></h3>
                <p>Pending Review</p>
            </div>
        </div>
    </div>
</div>

<!-- RELEASE RESULTS -->
<?php if ($unreleasedCount > 0 && $_SESSION['role'] === 'admin'): ?>
<div class="pmas-card mb-4" style="border:2px solid var(--pmas-gold);">
    <div class="pmas-card-header" style="background:linear-gradient(90deg,#7a5a00,#c49a00);">
        <i class="fas fa-bullhorn"></i> Release Results to Public
    </div>
    <div class="pmas-card-body">
        <p style="font-size:14px;">
            There are <strong><?= $unreleasedCount ?></strong> passed applicant(s) whose results have not yet been released to the public board.
            Once released, their names will appear on the public Results Board.
        </p>
        <form method="POST" onsubmit="return confirm('Release results to the public? This cannot be undone.')">
            <input type="hidden" name="batch_id" value="<?= $filterBatch ?>">
            <button type="submit" name="release_results" class="btn-pmas">
                <i class="fas fa-bullhorn me-2"></i> Release <?= $unreleasedCount ?> Result(s) to Public Board
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- PASSED LIST -->
<div class="pmas-card mb-4">
    <div class="pmas-card-header" style="background:linear-gradient(90deg,var(--pmas-dark),#155724);">
        <i class="fas fa-medal text-warning"></i> Passed Applicants (<?= count($passedList) ?>)
    </div>
    <div class="pmas-card-body p-0">
        <!-- Desktop -->
        <div class="table-responsive desktop-only">
        <table class="pmas-table">
            <thead><tr><th>#</th><th>Tracking #</th><th>Username</th><th>PA Rank</th><th>Discord USN &amp; ID</th><th>Grade</th><th>Unit / Dept</th><th>Results Released</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (empty($passedList)): ?>
                <tr><td colspan="9" class="text-center py-3 text-muted">No passed applicants yet.</td></tr>
            <?php else: foreach ($passedList as $i => $p): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><code style="font-size:11px;"><?= sanitize($p['tracking_number']) ?></code></td>
                <td><strong><?= sanitize($p['last_name']) ?></strong></td>
                <td><?= sanitize($p['first_name']) ?></td>
                <td><?= sanitize($p['email']) ?></td>
                <td><?= sanitize($p['permanent_address'] ?? '—') ?></td>
                <td><?= sanitize($p['current_address'] ?? '—') ?></td>
                <td>
                    <?php if ($p['result_released_at']): ?>
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Released</span>
                    <small class="d-block text-muted" style="font-size:10px;"><?= date('M d, Y', strtotime($p['result_released_at'])) ?></small>
                    <?php else: ?><span class="badge bg-warning text-dark">Not Yet Released</span><?php endif; ?>
                </td>
                <td><a href="<?= BASE_URL ?>pages/admin/admission/admission_review.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <!-- Mobile cards -->
        <div class="mobile-only p-3">
        <?php if (empty($passedList)): ?>
            <p class="text-center text-muted py-3">No passed applicants yet.</p>
        <?php else: foreach ($passedList as $i => $p): ?>
        <div class="border rounded mb-3 overflow-hidden" style="font-size:13px;">
            <div class="p-3" style="background:#f0fff4;border-left:4px solid #28a745;">
                <div class="d-flex justify-content-between mb-1">
                    <code style="font-size:11px;color:var(--pmas-red);"><?= sanitize($p['tracking_number']) ?></code>
                    <?php if ($p['result_released_at']): ?>
                    <span class="badge bg-success">Released</span>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark">Unreleased</span>
                    <?php endif; ?>
                </div>
                <div class="fw-bold"><?= sanitize($p['last_name']) ?></div>
                <div style="font-size:12px;color:#555;" class="mt-1">
                    <span class="me-2"><i class="fas fa-shield-halved me-1"></i><?= sanitize($p['first_name']) ?></span>
                    <?php if ($p['permanent_address']): ?>
                    <span><i class="fas fa-star me-1"></i><?= sanitize($p['permanent_address']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="p-2" style="background:#fff;">
                <a href="<?= BASE_URL ?>pages/admin/admission/admission_review.php?id=<?= $p['id'] ?>" class="btn-pmas w-100 justify-content-center">
                    <i class="fas fa-eye me-1"></i> View
                </a>
            </div>
        </div>
        <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- FAILED LIST -->
<div class="pmas-card mb-4">
    <div class="pmas-card-header" style="background:linear-gradient(90deg,var(--pmas-dark),var(--pmas-red-dark));">
        <i class="fas fa-times-circle"></i> Failed Applicants (<?= count($failedList) ?>)
    </div>
    <div class="pmas-card-body p-0">
        <!-- Desktop -->
        <div class="table-responsive desktop-only">
        <table class="pmas-table">
            <thead><tr><th>#</th><th>Tracking #</th><th>Username</th><th>PA Rank</th><th>Discord USN &amp; ID</th><th>Reason/Notes</th><th>Action</th></tr></thead>
            <tbody>
            <?php if (empty($failedList)): ?>
            <tr><td colspan="7" class="text-center py-3 text-muted">No failed applicants.</td></tr>
            <?php else: foreach ($failedList as $i => $f): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><code style="font-size:11px;"><?= sanitize($f['tracking_number']) ?></code></td>
                <td><strong><?= sanitize($f['last_name']) ?></strong></td>
                <td><?= sanitize($f['first_name']) ?></td>
                <td><?= sanitize($f['email']) ?></td>
                <td style="font-size:12px;"><?= sanitize($f['review_notes'] ?? '—') ?></td>
                <td><a href="<?= BASE_URL ?>pages/admin/admission/admission_review.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <!-- Mobile cards -->
        <div class="mobile-only p-3">
        <?php if (empty($failedList)): ?>
            <p class="text-center text-muted py-3">No failed applicants.</p>
        <?php else: foreach ($failedList as $i => $f): ?>
        <div class="border rounded mb-3 overflow-hidden" style="font-size:13px;">
            <div class="p-3" style="background:#fff5f5;border-left:4px solid var(--pmas-red);">
                <code style="font-size:11px;color:var(--pmas-red);"><?= sanitize($f['tracking_number']) ?></code>
                <div class="fw-bold mt-1"><?= sanitize($f['last_name']) ?></div>
                <div style="font-size:12px;color:#666;"><?= sanitize($f['first_name']) ?> &mdash; <?= sanitize($f['email']) ?></div>
                <?php if ($f['review_notes'] ?? ''): ?>
                <div style="font-size:12px;color:#721c24;margin-top:4px;"><?= sanitize($f['review_notes']) ?></div>
                <?php endif; ?>
            </div>
            <div class="p-2" style="background:#fff;">
                <a href="<?= BASE_URL ?>pages/admin/admission/admission_review.php?id=<?= $f['id'] ?>" class="btn-pmas w-100 justify-content-center">
                    <i class="fas fa-eye me-1"></i> View
                </a>
            </div>
        </div>
        <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- PENDING -->
<?php if (!empty($pendingList)): ?>
<div class="pmas-card">
    <div class="pmas-card-header">
        <i class="fas fa-hourglass-half"></i> Pending Review (<?= count($pendingList) ?>)
    </div>
    <div class="pmas-card-body p-0">
        <!-- Desktop -->
        <div class="table-responsive desktop-only">
        <table class="pmas-table">
            <thead><tr><th>Tracking #</th><th>Name</th><th>Submitted</th><th>BG Check Due</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($pendingList as $p): ?>
            <?php $bgReady = $p['bg_check_due'] && date('Y-m-d H:i:s') >= $p['bg_check_due']; ?>
            <tr>
                <td><code style="font-size:11px;"><?= sanitize($p['tracking_number']) ?></code></td>
                <td><?= sanitize($p['last_name']) ?>, <?= sanitize($p['first_name']) ?></td>
                <td style="font-size:11px;"><?= $p['submitted_at'] ? date('M d, Y', strtotime($p['submitted_at'])) : '—' ?></td>
                <td style="font-size:11px;">
                    <?= $p['bg_check_due'] ? date('M d, Y g:i A', strtotime($p['bg_check_due'])) : '—' ?>
                    <?= $bgReady ? '<span class="badge bg-success ms-1">Ready</span>' : '' ?>
                </td>
                <td><?= getAdmissionStatusBadge($p['submission_status']) ?></td>
                <td><a href="<?= BASE_URL ?>pages/admin/admission/admission_review.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <!-- Mobile cards -->
        <div class="mobile-only p-3">
        <?php foreach ($pendingList as $p):
            $bgReady = $p['bg_check_due'] && date('Y-m-d H:i:s') >= $p['bg_check_due'];
        ?>
        <div class="border rounded mb-3 overflow-hidden" style="font-size:13px;">
            <div class="p-3" style="background:#f8f8f8;border-left:4px solid #ffc107;">
                <div class="d-flex justify-content-between mb-1">
                    <code style="font-size:11px;color:var(--pmas-red);"><?= sanitize($p['tracking_number']) ?></code>
                    <?= getAdmissionStatusBadge($p['submission_status']) ?>
                </div>
                <div class="fw-bold"><?= sanitize($p['last_name']) ?>, <?= sanitize($p['first_name']) ?></div>
                <div style="font-size:12px;color:#666;margin-top:4px;">
                    <i class="fas fa-calendar me-1"></i>
                    Submitted: <?= $p['submitted_at'] ? date('M d, Y', strtotime($p['submitted_at'])) : '—' ?>
                </div>
                <div style="font-size:12px;margin-top:2px;">
                    <?php if ($bgReady): ?>
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Ready for Review</span>
                    <?php elseif ($p['bg_check_due']): ?>
                    <span class="badge bg-warning text-dark">Due: <?= date('M d, Y', strtotime($p['bg_check_due'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="p-2" style="background:#fff;">
                <a href="<?= BASE_URL ?>pages/admin/admission/admission_review.php?id=<?= $p['id'] ?>" class="btn-pmas w-100 justify-content-center">
                    <i class="fas fa-eye me-1"></i> Review
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../../../includes/footer.php'; ?>
