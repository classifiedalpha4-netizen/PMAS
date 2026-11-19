<?php
require_once '../../includes/config.php';
requireRole('officer');
$pageTitle = 'Blacklist — PAR & PMA';

$list = $conn->query("SELECT * FROM blacklist ORDER BY status = 'Active' DESC, blacklisted_date DESC")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php'; include '../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-ban me-2"></i> Blacklist — PAR &amp; PMA</h2>
        <p>Wide blacklisted individuals in PAR and PMA (view only)</p>
    </div>
</div>

<?php $active = array_filter($list, fn($b) => $b['status'] === 'Active'); ?>
<?php if (!empty($active)): ?>
<div class="blacklist-banner">
    <i class="fas fa-exclamation-triangle"></i>
    <div>
        <strong style="font-size:16px;">CAUTION — Active Blacklist Records</strong>
        <div style="font-size:13px;opacity:0.85;margin-top:4px;">
            There are <strong><?= count($active) ?></strong> active blacklisted individual(s) in PAR and/or PMA.
            Exercise caution in dealings with the individuals listed below.
        </div>
    </div>
</div>
<?php endif; ?>

<div class="pmas-card">
    <div class="pmas-card-header"><i class="fas fa-list"></i> Blacklist Registry (<?= count($list) ?> records)</div>
    <div class="pmas-card-body p-0">
        <div class="p-3">
            <input type="text" id="tableSearch" class="form-control" placeholder="Search..." style="max-width:300px;">
        </div>
        <div class="table-responsive">
        <table class="pmas-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Blacklist Type</th>
                    <th>Reason</th>
                    <th>Offense Date</th>
                    <th>Blacklisted Date</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($list)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No blacklist records.</td></tr>
            <?php else: foreach ($list as $i => $b): ?>
            <tr <?= $b['status'] === 'Active' ? 'style="background:rgba(192,0,26,0.05);"' : '' ?>>
                <td><?= $i + 1 ?></td>
                <td>
                    <strong><?= sanitize($b['full_name']) ?></strong>
                    <?php if ($b['status'] === 'Active'): ?>
                    <i class="fas fa-ban text-danger ms-1" style="font-size:11px;" title="Active Blacklist"></i>
                    <?php endif; ?>
                </td>
                <td><span class="badge bg-dark"><?= sanitize($b['blacklist_type']) ?></span></td>
                <td style="font-size:12px;max-width:200px;white-space:normal;"><?= sanitize($b['reason']) ?></td>
                <td style="font-size:12px;"><?= $b['offense_date'] ? date('M d, Y', strtotime($b['offense_date'])) : '—' ?></td>
                <td style="font-size:12px;"><?= date('M d, Y', strtotime($b['blacklisted_date'])) ?></td>
                <td><?= getStatusBadge($b['status']) ?></td>
                <td style="font-size:12px;"><?= sanitize($b['remarks'] ?? '—') ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
