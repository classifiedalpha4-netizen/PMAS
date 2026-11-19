<?php
require_once '../../includes/config.php';
requireRole('cadet');
$pageTitle = 'My Case Verdicts';
$uid = $_SESSION['user_id'];

$cases = $conn->query("
    SELECT c.*, u.full_name AS handled_by_name
    FROM cases c
    LEFT JOIN users u ON c.handled_by = u.id
    WHERE c.subject_user_id = $uid
    ORDER BY c.filed_date DESC
")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php'; include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-gavel me-2"></i> My Case Verdicts</h2>
        <p>Disciplinary cases filed against your record</p>
    </div>
</div>

<?php if (empty($cases)): ?>
<div class="pmas-card">
    <div class="pmas-card-body text-center py-5">
        <i class="fas fa-check-circle" style="font-size:56px;color:#28a745;"></i>
        <h4 class="mt-3 text-success">No Cases on Record</h4>
        <p class="text-muted">You have a clean disciplinary record. Keep it up!</p>
    </div>
</div>
<?php else: ?>

<?php $guiltyCount = count(array_filter($cases, fn($c) => $c['verdict'] === 'Guilty')); ?>
<?php if ($guiltyCount > 0): ?>
<div class="blacklist-banner mb-4">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong>Disciplinary Notice</strong>
        <div style="font-size:13px;margin-top:4px;">
            You have <?= $guiltyCount ?> case(s) with a GUILTY verdict on record. This may affect your standing.
        </div>
    </div>
</div>
<?php endif; ?>

<?php foreach ($cases as $c): ?>
<div class="pmas-card mb-3">
    <div class="pmas-card-header">
        <i class="fas fa-gavel"></i>
        <?= sanitize($c['case_number']) ?>
        &nbsp;&nbsp;<?= getStatusBadge($c['verdict']) ?>
    </div>
    <div class="pmas-card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <h5 style="color:var(--pmas-red);"><?= sanitize($c['case_title']) ?></h5>
                <p class="text-muted" style="font-size:13px;"><?= sanitize($c['description'] ?? 'No description provided.') ?></p>
                <div class="row g-2">
                    <div class="col-6"><strong style="font-size:12px;">Offense:</strong><br><?= sanitize($c['offense_category']) ?></div>
                    <div class="col-6"><strong style="font-size:12px;">Filed:</strong><br><?= date('F j, Y', strtotime($c['filed_date'])) ?></div>
                    <div class="col-6"><strong style="font-size:12px;">Hearing:</strong><br><?= $c['hearing_date'] ? date('F j, Y', strtotime($c['hearing_date'])) : 'Not scheduled' ?></div>
                    <div class="col-6"><strong style="font-size:12px;">Officer-in-Charge:</strong><br><?= sanitize($c['handled_by_name'] ?? 'N/A') ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div style="background:linear-gradient(135deg,var(--pmas-dark),var(--pmas-red-dark));border-radius:8px;padding:18px;color:#fff;text-align:center;min-height:120px;display:flex;flex-direction:column;justify-content:center;">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;opacity:0.6;">VERDICT</div>
                    <div style="font-size:24px;font-weight:900;font-family:'Cinzel',serif;margin:8px 0;letter-spacing:1px;">
                        <?= strtoupper($c['verdict']) ?>
                    </div>
                    <?php if ($c['sanction']): ?>
                    <div style="font-size:11px;opacity:0.75;border-top:1px solid rgba(255,255,255,0.2);padding-top:8px;margin-top:4px;">
                        <strong>Sanction:</strong><br><?= sanitize($c['sanction']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
