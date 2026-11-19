<?php
require_once '../../includes/config.php';
requireRole('admin');
$pageTitle = 'Manage Users';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    if ($delId !== $_SESSION['user_id']) {
        $conn->query("DELETE FROM users WHERE id=$delId");
        logAction($conn, $_SESSION['user_id'], 'DELETE_USER', 'Users', "Deleted user ID $delId");
    }
    redirect(BASE_URL . 'pages/admin/users.php?deleted=1');
}

// Handle toggle active
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $togId = (int)$_GET['toggle'];
    $conn->query("UPDATE users SET is_active = NOT is_active WHERE id=$togId AND id != {$_SESSION['user_id']}");
    redirect(BASE_URL . 'pages/admin/users.php');
}

$users = $conn->query("SELECT * FROM users ORDER BY role, full_name")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<style>
/* ---- USERS PAGE — Mobile Card Layout ---- */
.user-card-list     { display: none; }
.user-desktop-table { display: block; }

@media (max-width: 767px) {
    .user-desktop-table { display: none; }
    .user-card-list     { display: block; }

    .user-card {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e8e8e8;
        margin-bottom: 12px;
        overflow: hidden;
        box-shadow: 0 1px 6px rgba(0,0,0,0.07);
    }

    .user-card-top {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-bottom: 1px solid #f0f0f0;
    }

    .user-card-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }

    .user-card-name {
        flex: 1;
        min-width: 0;
    }

    .user-card-name strong {
        display: block;
        font-size: 14px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-card-name code {
        font-size: 12px;
        color: var(--pmas-red);
    }

    .user-card-body {
        padding: 10px 14px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 12px;
        font-size: 12px;
    }

    .user-card-field span:first-child {
        display: block;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #999;
        margin-bottom: 1px;
    }

    .user-card-field span:last-child {
        font-weight: 600;
        color: #333;
    }

    .user-card-actions {
        padding: 10px 14px;
        display: flex;
        gap: 8px;
        border-top: 1px solid #f0f0f0;
        background: #fafafa;
    }

    .user-card-actions a {
        flex: 1;
        text-align: center;
        padding: 7px 4px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .uca-edit   { background: #e8f0fe; color: #1a56db; border: 1px solid #c3d5fb; }
    .uca-toggle { background: #fef9e7; color: #856404; border: 1px solid #fde68a; }
    .uca-delete { background: #fde8e8; color: #c81e1e; border: 1px solid #fca5a5; }
    .uca-disabled { opacity: 0.35; pointer-events: none; }
}
</style>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-users me-2"></i> User Management</h2>
        <p>Manage all system users</p>
    </div>
    <a href="<?= BASE_URL ?>pages/admin/add_user.php" class="btn-pmas">
        <i class="fas fa-plus me-1"></i> Add User
    </a>
</div>

<?php if (isset($_GET['deleted'])): ?>
<div class="pmas-alert pmas-alert-success" data-auto-dismiss>
    <i class="fas fa-check-circle"></i> User deleted successfully.
</div>
<?php endif; ?>
<?php if (isset($_GET['saved'])): ?>
<div class="pmas-alert pmas-alert-success" data-auto-dismiss>
    <i class="fas fa-check-circle"></i> User saved successfully.
</div>
<?php endif; ?>

<!-- Search (shared) -->
<div class="mb-3">
    <input type="text" id="tableSearch" class="form-control"
           placeholder="Search users..." style="max-width:340px;">
</div>

<!-- ==================== DESKTOP TABLE ==================== -->
<div class="pmas-card user-desktop-table">
    <div class="pmas-card-header"><i class="fas fa-users"></i> All Users (<?= count($users) ?>)</div>
    <div class="pmas-card-body p-0">
        <div class="table-responsive">
        <table class="pmas-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Roblox Username</th>
                    <th>Role</th>
                    <th>Rank</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
            <?php
            $roleColor = ['admin'=>'danger','staff'=>'warning','officer'=>'primary','cadet'=>'success'];
            $rc = $roleColor[$u['role']] ?? 'secondary';
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><code><?= sanitize($u['username']) ?></code></td>
                <td><?= sanitize($u['full_name']) ?></td>
                <td><?= sanitize($u['roblox_username'] ?? '') ?: '<span class="text-muted" style="font-size:11px;">—</span>' ?></td>
                <td><span class="badge bg-<?= $rc ?>"><?= strtoupper($u['role']) ?></span></td>
                <td><?= sanitize($u['rank'] ?? '') ?></td>
                <td><?= sanitize($u['unit'] ?? '') ?></td>
                <td><?= $u['is_active']
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                <td>
                    <a href="<?= BASE_URL ?>pages/admin/edit_user.php?id=<?= $u['id'] ?>"
                       class="btn btn-sm btn-outline-primary me-1" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <a href="?toggle=<?= $u['id'] ?>"
                       class="btn btn-sm btn-outline-warning me-1" title="Toggle Active"
                       <?= $u['id'] === $_SESSION['user_id'] ? 'style="pointer-events:none;opacity:0.4"' : '' ?>>
                        <i class="fas fa-toggle-<?= $u['is_active'] ? 'on' : 'off' ?>"></i>
                    </a>
                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                    <a href="?delete=<?= $u['id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       data-confirm="Delete user '<?= sanitize($u['full_name']) ?>'? This cannot be undone.">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ==================== MOBILE CARD LIST ==================== -->
<div class="user-card-list">
    <?php
    $roleColors = [
        'admin'   => ['bg'=>'#C0001A', 'label'=>'ADMIN'],
        'staff'   => ['bg'=>'#856404', 'label'=>'STAFF'],
        'officer' => ['bg'=>'#1a56db', 'label'=>'OFFICER'],
        'cadet'   => ['bg'=>'#155724', 'label'=>'CADET'],
    ];
    foreach ($users as $i => $u):
        $rc    = $roleColors[$u['role']] ?? ['bg'=>'#555','label'=>strtoupper($u['role'])];
        $initials = strtoupper(substr($u['full_name'], 0, 1));
    ?>
    <div class="user-card" data-search="<?= strtolower(sanitize($u['username']) . ' ' . sanitize($u['full_name']) . ' ' . $u['role'] . ' ' . sanitize($u['roblox_username'] ?? '')) ?>">

        <!-- Top row: avatar + name + role badge -->
        <div class="user-card-top">
            <div class="user-card-avatar" style="background:<?= $rc['bg'] ?>;">
                <?= $initials ?>
            </div>
            <div class="user-card-name">
                <strong><?= sanitize($u['full_name']) ?></strong>
                <code><?= sanitize($u['username']) ?></code>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge" style="background:<?= $rc['bg'] ?>;font-size:10px;">
                    <?= $rc['label'] ?>
                </span>
                <?= $u['is_active']
                    ? '<span class="badge bg-success" style="font-size:10px;">Active</span>'
                    : '<span class="badge bg-secondary" style="font-size:10px;">Inactive</span>' ?>
            </div>
        </div>

        <!-- Details grid -->
        <div class="user-card-body">
            <div class="user-card-field">
                <span>Rank</span>
                <span><?= sanitize($u['rank'] ?? '—') ?></span>
            </div>
            <div class="user-card-field">
                <span>Unit / Dept</span>
                <span><?= sanitize($u['unit'] ?? '—') ?></span>
            </div>
            <div class="user-card-field">
                <span>Roblox Username</span>
                <span><?= sanitize($u['roblox_username'] ?? '—') ?></span>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="user-card-actions">
            <a href="<?= BASE_URL ?>pages/admin/edit_user.php?id=<?= $u['id'] ?>"
               class="uca-edit">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="?toggle=<?= $u['id'] ?>"
               class="uca-toggle <?= $u['id'] === $_SESSION['user_id'] ? 'uca-disabled' : '' ?>">
                <i class="fas fa-toggle-<?= $u['is_active'] ? 'on' : 'off' ?>"></i>
                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
            </a>
            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
            <a href="?delete=<?= $u['id'] ?>"
               class="uca-delete"
               data-confirm="Delete '<?= sanitize($u['full_name']) ?>'? This cannot be undone.">
                <i class="fas fa-trash"></i> Delete
            </a>
            <?php else: ?>
            <span class="uca-delete uca-disabled">
                <i class="fas fa-trash"></i> Delete
            </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
// Search works for both table and cards
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase();

        // Desktop table rows
        document.querySelectorAll('.pmas-table tbody tr').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });

        // Mobile cards
        document.querySelectorAll('.user-card').forEach(function (card) {
            const text = (card.getAttribute('data-search') || '') + card.textContent.toLowerCase();
            card.style.display = text.includes(q) ? '' : 'none';
        });
    });
}
</script>
