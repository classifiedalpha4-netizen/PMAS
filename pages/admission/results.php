<?php
/**
 * PMAS Admission Portal Ã¢â‚¬â€ Public Results Board
 * Shows applicants who PASSED the admission process (released by admin).
 */
require_once '../../includes/config.php';

// Only show passed/released applicants
$batches = $conn->query("SELECT * FROM admission_batches ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$filterBatch = isset($_GET['batch_id']) && is_numeric($_GET['batch_id']) ? (int)$_GET['batch_id'] : ($batches[0]['id'] ?? 0);

$passedList = [];
if ($filterBatch) {
    $passedList = $conn->query("
        SELECT a.last_name, a.first_name, a.middle_name, a.suffix,
               a.email, a.school_address, a.permanent_address, a.current_address,
               a.tracking_number, a.result_released_at, a.review_notes
        FROM applicants a
        WHERE a.batch_id = $filterBatch
          AND a.submission_status = 'passed'
          AND a.result_released_at IS NOT NULL
        ORDER BY a.last_name, a.first_name
    ")->fetch_all(MYSQLI_ASSOC);
}

$currentBatch = null;
foreach ($batches as $b) {
    if ($b['id'] == $filterBatch) { $currentBatch = $b; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Results | PMAS Portal</title>
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
                <div class="pmas-brand-sub">Admission Results Board</div>
            </div>
        </a>
        <div class="ms-auto d-flex gap-1 flex-shrink-0">
            <a href="<?= BASE_URL ?>pages/admission/apply.php"
               class="btn btn-outline-warning btn-sm px-2 px-md-3">
                <i class="fas fa-file-alt me-1"></i>
                <span class="d-none d-sm-inline">Apply</span>
                <span class="d-sm-none">Apply</span>
            </a>
            <a href="<?= BASE_URL ?>pages/admission/track.php"
               class="btn btn-outline-light btn-sm px-2 px-md-3">
                <i class="fas fa-search me-1"></i>
                <span class="d-none d-sm-inline">Track</span>
                <span class="d-sm-none">Track</span>
            </a>
        </div>
    </div>
</nav>

<div class="admission-hero">
    <div class="container text-center">
        <!-- Admission results icon Ã¢â‚¬â€ scroll/proclamation style -->
        <div class="adm-results-emblem">
            <i class="fas fa-scroll"></i>
        </div>
        <h1 class="adm-title">Cadetship Admission Results</h1>
        <p class="adm-subtitle">Official list of applicants who passed the admission process</p>
        <?php if ($currentBatch): ?>
        <div style="margin-top:8px;display:flex;justify-content:center;padding:0 12px;">
            <span class="adm-batch-badge">
                <i class="fas fa-calendar me-1"></i><?= sanitize($currentBatch['batch_name']) ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container admission-container">

    <!-- Batch Filter -->
    <?php if (count($batches) > 1): ?>
    <form method="GET" class="mb-4 d-flex gap-2 align-items-center">
        <label class="pmas-form-label mb-0">Select Batch:</label>
        <select name="batch_id" class="form-select" style="max-width:400px;" onchange="this.form.submit()">
            <?php foreach ($batches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $b['id'] == $filterBatch ? 'selected' : '' ?>>
                <?= sanitize($b['batch_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>

    <?php if (empty($passedList)): ?>
    <div class="adm-section">
        <div class="adm-section-body text-center py-5">
            <i class="fas fa-clock" style="font-size:56px;opacity:0.2;"></i>
            <h4 class="mt-3" style="color:#888;">Results Not Yet Released</h4>
            <p class="text-muted">The admission results for this batch have not been released yet.<br>Please check back after the evaluation period.</p>
            <a href="<?= BASE_URL ?>pages/admission/track.php" class="btn-pmas-outline mt-2">
                <i class="fas fa-search me-1"></i> Track Your Application
            </a>
        </div>
    </div>
    <?php else: ?>

    <div class="adm-results-header">
        <i class="fas fa-scroll"></i>
        <div>
            <strong style="font-size:18px;">OFFICIAL RESULTS Ã¢â‚¬â€ <?= strtoupper(sanitize($currentBatch['batch_name'] ?? '')) ?></strong>
            <div style="font-size:12px;opacity:0.75;margin-top:4px;">
                <i class="fas fa-calendar me-1"></i>
                Results released: <?= $passedList[0]['result_released_at'] ? date('F j, Y', strtotime($passedList[0]['result_released_at'])) : 'Ã¢â‚¬â€' ?>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <i class="fas fa-users me-1"></i>
                <?= count($passedList) ?> applicant(s) passed
            </div>
        </div>
    </div>

    <div class="adm-section mt-4">
        <div class="adm-section-header"><i class="fas fa-list-ol"></i> List of Passers</div>
        <div class="adm-section-body p-0">
            <div class="p-3">
                <input type="text" id="tableSearch" class="form-control" placeholder="Search by username or rank..." style="max-width:300px;">
            </div>
            <div class="table-responsive">
            <table class="pmas-table" id="resultsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>PA Rank</th>
                        <th>Discord USN &amp; ID</th>
                        <th>Profile Link</th>
                        <th>Grade</th>
                        <th>Unit / Dept</th>
                        <th>Roblox Age</th>
                        <th>Discord Age</th>
                        <th>Tracking No.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passedList as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= sanitize($p['last_name']) ?></strong></td>
                        <td><?= sanitize($p['first_name']) ?></td>
                        <td><?= sanitize($p['email']) ?></td>
                        <td>
                            <?php if ($p['school_address']): ?>
                            <a href="<?= sanitize($p['school_address']) ?>" target="_blank" rel="noopener" style="font-size:12px;">
                                <i class="fas fa-external-link-alt me-1"></i>View Profile
                            </a>
                            <?php else: ?>Ã¢â‚¬â€<?php endif; ?>
                        </td>
                        <td><?= sanitize($p['permanent_address']) ?></td>
                        <td><?= sanitize($p['current_address']) ?></td>
                        <td><?= sanitize($p['middle_name']) ?></td>
                        <td><?= sanitize($p['suffix']) ?></td>
                        <td><code><?= sanitize($p['tracking_number']) ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="adm-notice info mt-4">
        <i class="fas fa-info-circle"></i>
        <div>Passers are advised to report to the PMA Administration Office for further instructions and onboarding procedures.</div>
    </div>
    <?php endif; ?>

</div>

<footer class="pmas-footer mt-5">
    <div class="container text-center">
        &copy; <?= date('Y') ?> <strong>PMAS Portal</strong> &mdash; Philippine Army Roleplay
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
