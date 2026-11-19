<?php
require_once '../../includes/config.php';
requireRole('admin');
$pageTitle = 'Audit Log';

$logs = $conn->query("
    SELECT a.*, u.username, u.full_name
    FROM audit_log a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 500
")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php'; include '../../includes/sidebar.php';
?>
<div class="pmas-page-header">
    <div><h2><i class="fas fa-clipboard-list me-2"></i> Audit Log</h2><p>System activity records</p></div>
</div>
<div class="pmas-card">
    <div class="pmas-card-header"><i class="fas fa-history"></i> Recent Activity (last 500)</div>
    <div class="pmas-card-body p-0">
        <div class="p-3">
            <input type="text" id="tableSearch" class="form-control" placeholder="Search log..." style="max-width:300px;">
        </div>
        <!-- Desktop table -->
        <div class="table-responsive desktop-only">
        <table class="pmas-table">
            <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Module</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="font-size:11px;white-space:nowrap;"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                <td><?= $log['username'] ? sanitize($log['username']) : '<em class="text-muted">System</em>' ?></td>
                <td><code style="font-size:11px;"><?= sanitize($log['action']) ?></code></td>
                <td><span class="badge bg-secondary"><?= sanitize($log['module'] ?? '') ?></span></td>
                <td style="font-size:12px;max-width:200px;white-space:normal;"><?= sanitize($log['details'] ?? '') ?></td>
                <td style="font-size:11px;"><?= sanitize($log['ip_address'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <!-- Mobile cards -->
        <div class="mobile-only p-3">
        <?php foreach ($logs as $log): ?>
        <div class="border rounded mb-2 p-3" style="font-size:12px;">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <code style="font-size:11px;color:var(--pmas-red);"><?= sanitize($log['action']) ?></code>
                <span class="badge bg-secondary ms-2 flex-shrink-0"><?= sanitize($log['module'] ?? '') ?></span>
            </div>
            <div style="color:#555;margin-bottom:3px;"><?= sanitize($log['details'] ?? '') ?></div>
            <div style="color:#999;font-size:11px;">
                <i class="fas fa-user me-1"></i>
                <?= $log['username'] ? sanitize($log['username']) : 'System' ?>
                &nbsp;|&nbsp;
                <i class="fas fa-clock me-1"></i>
                <?= date('M d, Y H:i', strtotime($log['created_at'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
