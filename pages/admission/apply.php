<?php
/**
 * PMAS Admission Portal — Applicant Application Form
 * Public-facing page, no login required.
 * Applicants who have PASSED the Entrance Examination submit here.
 */
require_once '../../includes/config.php';

// Get active batch
$batch = $conn->query("SELECT * FROM admission_batches WHERE is_active=1 ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

// Get document requirements
$docRequirements = $conn->query("
    SELECT * FROM admission_required_docs
    WHERE batch_id IS NULL OR batch_id = " . ($batch['id'] ?? 0) . "
    ORDER BY sort_order
")->fetch_all(MYSQLI_ASSOC);

$error   = '';
$success = '';
$trackingNumber = '';

// Upload directory
$uploadDir = __DIR__ . '/../../uploads/admission/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {

    // ---- Gather POST data ----
    $batchId        = (int)($_POST['batch_id'] ?? $batch['id']);
    $admUsername    = sanitize($_POST['adm_username'] ?? '');
    $discordUsnId   = sanitize($_POST['discord_usn_id'] ?? '');
    $paRank         = sanitize($_POST['pa_rank'] ?? '');
    $profileLink    = sanitize($_POST['profile_link'] ?? '');
    $admissionGrade = sanitize($_POST['admission_grade'] ?? '');
    $unitDept       = sanitize($_POST['unit_department'] ?? '');
    $robloxAge      = sanitize($_POST['roblox_age'] ?? '');
    $discordAge     = sanitize($_POST['discord_age'] ?? '');

    // Map new fields onto existing DB columns (reuse nullable columns)
    // last_name  → admUsername   first_name → paRank    email → discordUsnId (stored separately via permanent_address)
    // We store all new fields in the notes columns available:
    //   last_name        = admUsername
    //   first_name       = paRank
    //   middle_name      = robloxAge
    //   suffix           = discordAge
    //   email            = discordUsnId  (used as contact identifier)
    //   contact_number   = profileLink
    //   permanent_address= admissionGrade
    //   current_address  = unitDept
    // school_name required by DB NOT NULL — set to admUsername as placeholder
    $lastName       = $admUsername;
    $firstName      = $paRank;
    $middleName     = $robloxAge;
    $suffix         = $discordAge;
    $gender         = 'Male';        // default, not collected
    $dob            = '2000-01-01';  // default placeholder
    $pob            = '';
    $civilStatus    = 'Single';
    $nationality    = 'Filipino';
    $religion       = '';
    $heightCm       = 0;
    $weightKg       = 0;
    $bloodType      = '';
    $email          = filter_var($discordUsnId, FILTER_SANITIZE_EMAIL) ?: $discordUsnId;
    $contact        = $profileLink;
    $permAddress    = $admissionGrade;
    $currAddress    = $unitDept;
    $schoolName     = $admUsername;   // required DB column
    $schoolAddress  = '';
    $schoolType     = 'Public';
    $schoolYear     = '';
    $gwa            = 0;
    $eePassed       = 1;              // applicant already passed EE
    $eeScore        = 0;
    $eeDate         = '';
    $fatherName     = ''; $fatherOcc  = '';
    $motherName     = ''; $motherOcc  = '';
    $guardianName   = ''; $guardianRel= ''; $guardianCon = '';

    // Basic validation
    if (empty($admUsername) || empty($discordUsnId) || empty($paRank) || empty($profileLink) || empty($admissionGrade) || empty($unitDept) || empty($robloxAge) || empty($discordAge)) {
        $error = 'Please fill in all required fields marked with *.';
    } elseif (!$batchId) {
        $error = 'No active admission batch found. Please try again later.';
    } else {

        // ---- Auto-evaluate (EE always passes since applicant confirmed) ----
        $applicantData = [
            'date_of_birth' => $dob,
            'gender'        => $gender,
            'height_cm'     => $heightCm,
            'ee_passed'     => $eePassed,
            'gwa'           => $gwa,
            'eval_docs_ok'  => 0,
        ];
        $evalFlags = evaluateApplicant($applicantData);
        // Override: age/height/gwa not applicable for this form — set to 1 to not block
        $evalFlags['eval_age_ok']    = 1;
        $evalFlags['eval_height_ok'] = 1;
        $evalFlags['eval_gwa_ok']    = 1;
        $evalFlags['eval_ee_ok']     = 1;

        // ---- Generate tracking number ----
        $trackNum = generateTrackingNumber($conn);

        // Assign eval flags to plain variables (bind_param requires references)
        $evalAgeOk    = 1;
        $evalHeightOk = 1;
        $evalEeOk     = 1;
        $evalGwaOk    = 1;
        $evalDocsOk   = 0;  // updated after upload
        $autoEligible = 0;  // updated after upload

        // ---- Insert applicant ----
        $stmt = $conn->prepare("
            INSERT INTO applicants
            (batch_id, tracking_number, last_name, first_name, middle_name, suffix,
             gender, date_of_birth, place_of_birth, civil_status, nationality, religion,
             height_cm, weight_kg, blood_type, email, contact_number,
             permanent_address, current_address, school_name, school_address,
             school_type, school_year_graduated, gwa, ee_passed, ee_score, ee_date,
             father_name, father_occupation, mother_name, mother_occupation,
             guardian_name, guardian_relationship, guardian_contact,
             eval_age_ok, eval_height_ok, eval_ee_ok, eval_gwa_ok, eval_docs_ok, auto_eligible,
             submission_status, submitted_at, bg_check_start)
            VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,
             'submitted', NOW(), NOW())
        ");
        $stmt->bind_param(
            'issssssssssssssssssssssdidssssssssiiiiii',
            $batchId, $trackNum, $lastName, $firstName, $middleName, $suffix,
            $gender, $dob, $pob, $civilStatus, $nationality, $religion,
            $heightCm, $weightKg, $bloodType, $email, $contact,
            $permAddress, $currAddress, $schoolName, $schoolAddress,
            $schoolType, $schoolYear, $gwa, $eePassed, $eeScore, $eeDate,
            $fatherName, $fatherOcc, $motherName, $motherOcc,
            $guardianName, $guardianRel, $guardianCon,
            $evalAgeOk, $evalHeightOk, $evalEeOk, $evalGwaOk, $evalDocsOk, $autoEligible
        );
        $stmt->execute();
        $newApplicantId = $stmt->insert_id;
        $stmt->close();

        // ---- Process document uploads ----
        $docCount = 0;
        if (!empty($_FILES['documents']['name'])) {
            foreach ($_FILES['documents']['name'] as $reqId => $fileName) {
                if (!empty($fileName) && $_FILES['documents']['error'][$reqId] === UPLOAD_ERR_OK) {
                    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $allowed  = ['pdf','jpg','jpeg','png'];
                    if (!in_array($ext, $allowed)) continue;

                    $safeName = $newApplicantId . '_' . $reqId . '_' . time() . '.' . $ext;
                    $destPath = $uploadDir . $safeName;

                    if (move_uploaded_file($_FILES['documents']['tmp_name'][$reqId], $destPath)) {
                        // Find doc label
                        $docLabel = 'Document';
                        foreach ($docRequirements as $dr) {
                            if ($dr['id'] == $reqId) { $docLabel = $dr['doc_name']; break; }
                        }
                        $fSize = $_FILES['documents']['size'][$reqId];
                        $fType = mime_content_type($destPath);
                        $ds = $conn->prepare("INSERT INTO applicant_documents (applicant_id, doc_requirement_id, doc_label, file_name, file_path, file_type, file_size) VALUES (?,?,?,?,?,?,?)");
                        $ds->bind_param('iissssi', $newApplicantId, $reqId, $docLabel, $fileName, $safeName, $fType, $fSize);
                        $ds->execute();
                        $ds->close();
                        $docCount++;
                    }
                }
            }
        }

        // ---- Update doc flag + re-evaluate auto_eligible ----
        $docsOk = $docCount >= count(array_filter($docRequirements, fn($d) => $d['is_required'])) ? 1 : 0;
        $autoElig = ($docsOk) ? 1 : 0;

        $conn->query("UPDATE applicants SET
            eval_docs_ok = $docsOk,
            auto_eligible = $autoElig
            WHERE id = $newApplicantId");

        $trackingNumber = $trackNum;
        $success = "Your application has been submitted successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadetship Admission Application | PMAS Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admission.css">
</head>
<body>

<!-- ADMISSION HEADER -->
<nav class="pmas-navbar navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>index.php">
            <div class="pmas-logo-circle">
                <img src="<?= BASE_URL ?>assets/img/pma_logo.png" alt="PMA Logo">
            </div>
            <div>
                <div class="pmas-brand-title">PMAS Portal</div>
                <div class="pmas-brand-sub">Cadetship Admission — Philippine Army Roleplay</div>
            </div>
        </a>
        <div class="ms-auto d-flex gap-1 flex-shrink-0">
            <a href="<?= BASE_URL ?>pages/admission/track.php"
               class="btn btn-outline-warning btn-sm px-2 px-md-3">
                <i class="fas fa-search me-1"></i>
                <span class="d-none d-sm-inline">Track Application</span>
                <span class="d-sm-none">Track</span>
            </a>
            <a href="<?= BASE_URL ?>index.php"
               class="btn btn-outline-light btn-sm px-2 px-md-3">
                <i class="fas fa-sign-in-alt me-1"></i>
                <span class="d-none d-sm-inline">Staff Login</span>
                <span class="d-sm-none">Login</span>
            </a>
        </div>
    </div>
</nav>

<div class="admission-hero">
    <div class="container text-center">
        <div class="adm-emblem">
            <img src="<?= BASE_URL ?>assets/img/pma_logo.png" alt="PMA Logo">
        </div>
        <h1 class="adm-title">Cadetship Admission Application</h1>
        <p class="adm-subtitle">Philippine Military Academy &mdash; Philippine Army Roleplay</p>
        <?php if ($batch): ?>
        <div style="margin-top:8px;display:flex;justify-content:center;padding:0 12px;">
            <span class="adm-batch-badge">
                <i class="fas fa-calendar me-1"></i>
                <?= sanitize($batch['batch_name']) ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container admission-container">

    <?php if (!$batch): ?>
    <div class="adm-notice warning">
        <i class="fas fa-exclamation-triangle fa-2x"></i>
        <div>
            <strong>No Active Admission Batch</strong><br>
            There is currently no open admission period. Please check back later or contact PMA Administration.
        </div>
    </div>
    <?php elseif ($success): ?>
    <!-- SUCCESS SCREEN -->
    <div class="adm-success-box">
        <div class="adm-success-icon"><i class="fas fa-check-circle"></i></div>
        <h2>Application Submitted!</h2>
        <p>Your application has been received and is now under evaluation.</p>
        <div class="adm-tracking-box">
            <div class="adm-tracking-label">YOUR TRACKING NUMBER</div>
            <div class="adm-tracking-num"><?= sanitize($trackingNumber) ?></div>
            <div class="adm-tracking-note">Save this number to track your application status.</div>
        </div>
        <div class="adm-timeline">
            <div class="adm-timeline-item done">
                <div class="adm-tl-dot"><i class="fas fa-check"></i></div>
                <div><strong>Application Submitted</strong><br><small>Your documents are now being processed.</small></div>
            </div>
            <div class="adm-timeline-item">
                <div class="adm-tl-dot"><i class="fas fa-hourglass-half"></i></div>
                <div><strong>2-Day Evaluation Period</strong><br><small>Your requirements will be reviewed and a background check will be conducted within 2 days.</small></div>
            </div>
            <div class="adm-timeline-item">
                <div class="adm-tl-dot"><i class="fas fa-trophy"></i></div>
                <div><strong>Results Release</strong><br><small>Admission results will be posted on the Results Board after the evaluation period.</small></div>
            </div>
        </div>
        <div class="mt-4 d-flex gap-3 justify-content-center flex-wrap">
            <a href="<?= BASE_URL ?>pages/admission/track.php?tn=<?= urlencode($trackingNumber) ?>" class="btn-pmas">
                <i class="fas fa-search me-2"></i> Track My Application
            </a>
            <a href="<?= BASE_URL ?>pages/admission/results.php" class="btn-pmas-outline">
                <i class="fas fa-trophy me-2"></i> View Results Board
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- APPLICATION FORM -->

    <?php if ($error): ?>
    <div class="pmas-alert pmas-alert-danger mb-4">
        <i class="fas fa-times-circle"></i> <?= $error ?>
    </div>
    <?php endif; ?>

    <!-- ELIGIBILITY NOTICE -->
    <div class="adm-notice info mb-4">
        <i class="fas fa-info-circle fa-2x"></i>
        <div>
            <strong>Important:</strong> This application is exclusively for applicants who have <strong>passed the PMA Entrance Examination (EE)</strong>.
            Submitting false information is a ground for immediate disqualification.
        </div>
    </div>

    <!-- ADMISSION STEPS -->
    <div class="adm-steps mb-4">
        <div class="adm-step active"><span>1</span> Admission Info</div>
        <div class="adm-step-line"></div>
        <div class="adm-step"><span>2</span> Review &amp; Submit</div>
    </div>

    <form method="POST" enctype="multipart/form-data" id="admissionForm">
        <input type="hidden" name="submit_application" value="1">
        <input type="hidden" name="batch_id" value="<?= $batch['id'] ?>">

        <!-- ==================== SECTION 1: INFORMATION ==================== -->
        <div class="adm-section" id="sec-personal">
            <div class="adm-section-header">
                <i class="fas fa-user"></i> Section 1: Information
            </div>
            <div class="adm-section-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="pmas-form-label">Username *</label>
                        <input type="text" name="adm_username" class="form-control"
                               placeholder="Your in-game / platform username"
                               required value="<?= sanitize($_POST['adm_username'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="pmas-form-label">Discord Username &amp; ID *</label>
                        <input type="text" name="discord_usn_id" class="form-control"
                               placeholder="e.g. username#0000 or username / 123456789"
                               required value="<?= sanitize($_POST['discord_usn_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="pmas-form-label">PA Rank *</label>
                        <input type="text" name="pa_rank" class="form-control"
                               placeholder="Your Philippine Army rank"
                               required value="<?= sanitize($_POST['pa_rank'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="pmas-form-label">Profile Link <small class="text-muted fw-normal">(Roblox Profile)</small> *</label>
                        <input type="url" name="profile_link" class="form-control"
                               placeholder="https://www.roblox.com/users/..."
                               required value="<?= sanitize($_POST['profile_link'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="pmas-form-label">Grade in Admission Result *</label>
                        <input type="text" name="admission_grade" class="form-control"
                               placeholder="Your grade/score in the admission result"
                               required value="<?= sanitize($_POST['admission_grade'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="pmas-form-label">Unit / Department *</label>
                        <input type="text" name="unit_department" class="form-control"
                               placeholder="Your unit or department"
                               required value="<?= sanitize($_POST['unit_department'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="pmas-form-label">Roblox Age *</label>
                        <input type="text" name="roblox_age" class="form-control"
                               placeholder="Your Roblox account age (e.g. 2 years)"
                               required value="<?= sanitize($_POST['roblox_age'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="pmas-form-label">Discord Age *</label>
                        <input type="text" name="discord_age" class="form-control"
                               placeholder="Your Discord account age (e.g. 1 year)"
                               required value="<?= sanitize($_POST['discord_age'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== SECTION 2: ENTRANCE EXAM RESULT ==================== -->
        <div class="adm-section" id="sec-documents">
            <div class="adm-section-header">
                <i class="fas fa-file-upload"></i> Section 2: Entrance Examination Result
            </div>
            <div class="adm-section-body">
                <div class="adm-notice info mb-3">
                    <i class="fas fa-info-circle"></i>
                    <div>Upload your official Entrance Examination result slip. Accepted formats: <strong>PDF, JPG, PNG</strong>. Maximum file size: <strong>5 MB</strong>.</div>
                </div>
                <div class="row g-3">
                    <?php
                    // Find the EE result requirement from the loaded list
                    $eeDoc = null;
                    foreach ($docRequirements as $doc) {
                        if (stripos($doc['doc_name'], 'Entrance Examination') !== false) {
                            $eeDoc = $doc;
                            break;
                        }
                    }
                    ?>
                    <div class="col-md-6">
                        <div class="adm-doc-item">
                            <label class="pmas-form-label">
                                Entrance Examination Result <span class="text-danger">*</span>
                            </label>
                            <small class="text-muted d-block mb-1">Official EE result slip showing your passing score.</small>
                            <input type="file"
                                   name="documents[<?= $eeDoc['id'] ?? 5 ?>]"
                                   class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== SECTION 3: CONFIRMATION ==================== -->
        <div class="adm-section" id="sec-confirm">
            <div class="adm-section-header">
                <i class="fas fa-check-double"></i> Section 3: Certification &amp; Submission
            </div>
            <div class="adm-section-body">
                <div class="adm-oath-box">
                    <p><strong>I hereby certify</strong> that the information I have provided in this application form is true, correct, and complete to the best of my knowledge. I understand that any false statement or misrepresentation shall be cause for disqualification from the admission process, and may subject me to appropriate legal action.</p>
                    <p>I further certify that I have <strong>passed the PMA Entrance Examination</strong> and that I am submitting all required documents as truthfully declared above.</p>
                </div>
                <div class="form-check mt-3 mb-4">
                    <input class="form-check-input" type="checkbox" id="certifyOath" required>
                    <label class="form-check-label fw-bold" for="certifyOath">
                        I have read and understood the above certification, and I agree to its terms. <span class="text-danger">*</span>
                    </label>
                </div>
                <div class="d-flex gap-3 flex-wrap">
                    <button type="submit" class="btn-pmas" style="font-size:15px;padding:12px 32px;">
                        <i class="fas fa-paper-plane me-2"></i> Submit Application
                    </button>
                    <button type="reset" class="btn-pmas-dark">
                        <i class="fas fa-undo me-1"></i> Clear Form
                    </button>
                </div>
            </div>
        </div>

    </form>
    <?php endif; ?>
</div>

<!-- FOOTER -->
<footer class="pmas-footer mt-5">
    <div class="container text-center">
        &copy; <?= date('Y') ?> <strong>PMAS Portal</strong> &mdash; Philippine Military Academy System &nbsp;|&nbsp;
        <span class="text-warning">Philippine Army Roleplay</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
<script>
// File size validation
document.querySelectorAll('input[type="file"]').forEach(function(input) {
    input.addEventListener('change', function() {
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (this.files[0] && this.files[0].size > maxSize) {
            alert('File "' + this.files[0].name + '" exceeds the 5MB limit. Please choose a smaller file.');
            this.value = '';
        }
    });
});
</script>
</body>
</html>
