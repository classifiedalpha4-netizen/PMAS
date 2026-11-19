<?php
require_once '../../../includes/config.php';
requireRole('admin','staff');
$pageTitle = 'Admission Batches';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $conn->query("DELETE FROM admission_batches WHERE id=" . (int)$_GET['delete']);
    redirect(BASE_URL . 'pages/admin/admission/admission_batches.php?deleted=1');
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    $conn->query("UPDATE admission_batches SET is_active = NOT is_active WHERE id=$tid");
    redirect(BASE_URL . 'pages/admin/admission/admission_batches.php');
}

$editEntry = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editEntry = $conn->query("SELECT * FROM admission_batches WHERE id=" . (int)$_GET['edit'])->fetch_assoc();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = sanitize($_POST['batch_name']);
    $ay       = sanitize($_POST['academic_year']);
    $open     = sanitize($_POST['application_open']);
    $close    = sanitize($_POST['application_close']);
    $eeDate   = sanitize($_POST['ee_date']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $createdBy= $_SESSION['user_id'];

    if (empty($name) || empty($ay) || empty($open) || empty($close)) {
        $error = 'Please fill in all required fields.';
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE admission_batches SET batch_name=?,academic_year=?,application_open=?,application_close=?,ee_date=?,is_active=? WHERE id=?");
            $stmt->bind_param('sssssii', $name,$ay,$open,$close,$eeDate,$isActive,$id);
        } else {
            $stmt = $conn->prepare("INSERT INTO admission_batches (batch_name,academic_year,application_open,application_close,ee_date,is_active,created_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('sssssii', $name,$ay,$open,$close,$eeDate,$isActive,$createdBy);
        }
        $stmt->execute(); $stmt->close();
        logAction($conn, $_SESSION['user_id'], $id > 0 ? 'EDIT_ADM_BATCH' : 'ADD_ADM_BATCH', 'Admission', $name);
        redirect(BASE_URL . 'pages/admin/admission/admission_batches.php?saved=1');
    }
}

$batches = $conn->query("SELECT ab.*, COUNT(ap.id) as applicant_count FROM admission_batches ab LEFT JOIN applicants ap ON ab.id=ap.batch_id GROUP BY ab.id ORDER BY ab.created_at DESC")->fetch_all(MYSQLI_ASSOC);

include '../../../includes/header.php'; include '../../../includes/sidebar.php';
?>

<div class="pmas-page-header">
    <div>
        <h2><i class="fas fa-calendar-alt me-2"></i> Admission Batches</h2>
        <p>Manage admission intake periods and batches</p>
    </div>
    <a href="<?= BASE_URL ?>pages/admission/apply.php" class="btn-pmas-outline" target="_blank">
        <i class="fas fa-external-link-alt me-1"></i> Public Admission Page
    </a>
</div>

<?php if (isset($_GET['saved'])): ?><div class="pmas-alert pmas-alert-success" data-auto-dismiss><i class="fas fa-check-circle"></i> Saved.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="pmas-alert pmas-alert-warning" data-auto-dismiss><i class="fas fa-trash"></i> Deleted.</div><?php endif; ?>
<?php if ($error): ?><div class="pmas-alert pmas-alert-danger"><i class="fas fa-times-circle"></i> <?= $error ?></div><?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="pmas-card">
            <div class="pmas-card-header"><i class="fas fa-<?= $editEntry ? 'edit' : 'plus' ?>"></i> <?= $editEntry ? 'Edit Batch' : 'New Batch' ?></div>
            <div class="pmas-card-body">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $editEntry['id'] ?? 0 ?>">
                    <div class="mb-3">
                        <label class="pmas-form-label">Batch Name *</label>
                        <input type="text" name="batch_name" class="form-control"
                               placeholder="PMA Class 2029 — AY 2025-2026"
                               value="<?= sanitize($editEntry['batch_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Academic Year *</label>
                        <input type="text" name="academic_year" class="form-control"
                               placeholder="2025-2026"
                               value="<?= sanitize($editEntry['academic_year'] ?? '') ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="pmas-form-label">Application Open *</label>
                            <input type="date" name="application_open" class="form-control"
                                   value="<?= $editEntry['application_open'] ?? '' ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="pmas-form-label">Application Close *</label>
                            <input type="date" name="application_close" class="form-control"
                                   value="<?= $editEntry['application_close'] ?? '' ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="pmas-form-label">Entrance Exam Date</label>
                        <input type="date" name="ee_date" class="form-control" value="<?= $editEntry['ee_date'] ?? '' ?>">
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="batchActive"
                                   <?= ($editEntry['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="batchActive">Active (Open for Applications)</label>
                        </div>
                    </div>
                    <button type="submit" class="btn-pmas w-100"><i class="fas fa-save me-1"></i> Save</button>
                    <?php if ($editEntry): ?>
                    <a href="<?= BASE_URL ?>pages/admin/admission/admission_batches.php" class="btn-pmas-dark w-100 mt-2 justify-content-center">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="pmas-card">
            <div class="pmas-card-header"><i class="fas fa-list"></i> Batches (<?= count($batches) ?>)</div>
            <div class="pmas-card-body p-0">

                <!-- Desktop table -->
                <div class="table-responsive desktop-only">
                <table class="pmas-table">
                    <thead><tr><th>Batch Name</th><th>AY</th><th>Open</th><th>Close</th><th>Applicants</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($batches as $b): ?>
                    <tr>
                        <td><strong><?= sanitize($b['batch_name']) ?></strong></td>
                        <td><?= $b['academic_year'] ?></td>
                        <td style="font-size:12px;"><?= date('M d, Y', strtotime($b['application_open'])) ?></td>
                        <td style="font-size:12px;"><?= date('M d, Y', strtotime($b['application_close'])) ?></td>
                        <td class="text-center">
                            <a href="<?= BASE_URL ?>pages/admin/admission/admission_applicants.php?batch_id=<?= $b['id'] ?>" class="badge bg-primary text-decoration-none">
                                <?= $b['applicant_count'] ?>
                            </a>
                        </td>
                        <td><?= $b['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td>
                            <a href="?edit=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="fas fa-edit"></i></a>
                            <a href="?toggle=<?= $b['id'] ?>" class="btn btn-sm btn-outline-warning me-1"><i class="fas fa-toggle-<?= $b['is_active'] ? 'on' : 'off' ?>"></i></a>
                            <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this batch? All applicants will also be deleted."><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <!-- Mobile cards -->
                <div class="mobile-only p-3">
                <?php foreach ($batches as $b): ?>
                <div class="border rounded mb-3 overflow-hidden">
                    <div class="p-3" style="background:#f8f8f8;border-bottom:1px solid #eee;">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <strong style="font-size:13px;line-height:1.3;"><?= sanitize($b['batch_name']) ?></strong>
                            <?= $b['is_active'] ? '<span class="badge bg-success flex-shrink-0">Active</span>' : '<span class="badge bg-secondary flex-shrink-0">Inactive</span>' ?>
                        </div>
                        <div class="mt-2" style="font-size:12px;color:#666;">
                            <span class="me-3"><i class="fas fa-graduation-cap me-1"></i><?= $b['academic_year'] ?></span>
                            <span><i class="fas fa-users me-1"></i>
                                <a href="<?= BASE_URL ?>pages/admin/admission/admission_applicants.php?batch_id=<?= $b['id'] ?>" class="text-primary text-decoration-none">
                                    <?= $b['applicant_count'] ?> applicant(s)
                                </a>
                            </span>
                        </div>
                        <div class="mt-1" style="font-size:11px;color:#888;">
                            <i class="fas fa-calendar me-1"></i>
                            <?= date('M d, Y', strtotime($b['application_open'])) ?> —
                            <?= date('M d, Y', strtotime($b['application_close'])) ?>
                        </div>
                    </div>
                    <div class="p-2 d-flex gap-2" style="background:#fff;">
                        <a href="?edit=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary flex-fill text-center">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                        <a href="?toggle=<?= $b['id'] ?>" class="btn btn-sm btn-outline-warning flex-fill text-center">
                            <i class="fas fa-toggle-<?= $b['is_active'] ? 'on' : 'off' ?> me-1"></i>
                            <?= $b['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </a>
                        <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger flex-fill text-center"
                           data-confirm="Delete this batch? All applicants will also be deleted.">
                            <i class="fas fa-trash me-1"></i> Delete
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../../../includes/footer.php'; ?>
