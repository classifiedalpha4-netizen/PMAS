<?php
/**
 * PMAS — One-time database setup for Railway
 * Visit this URL once after deployment to create all tables and seed data.
 * DELETE THIS FILE after running it.
 */

// Only allow if accessed with the secret key
$secret = $_GET['key'] ?? '';
if ($secret !== 'pmas_setup_2026') {
    die('Access denied. Add ?key=pmas_setup_2026 to the URL.');
}

require_once 'includes/config.php';

$results = [];
$errors  = [];

function runSQL($conn, $sql, $label) {
    global $results, $errors;
    // Split on semicolons for multi-statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        if ($conn->query($stmt) === true) {
            $results[] = "✓ $label";
        } else {
            if (str_contains($conn->error, 'already exists') || str_contains($conn->error, 'Duplicate')) {
                $results[] = "⟳ $label (already exists)";
            } else {
                $errors[] = "✗ $label: " . $conn->error;
            }
        }
    }
}

// ---- USERS ----
runSQL($conn, "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff','officer','cadet') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    `rank` VARCHAR(60) DEFAULT NULL,
    unit VARCHAR(100) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    roblox_username VARCHAR(100) DEFAULT NULL,
    profile_photo VARCHAR(255) DEFAULT 'default.png',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)", "users table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS cadet_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cadet_id VARCHAR(20) NOT NULL UNIQUE,
    class_year VARCHAR(10) DEFAULT NULL,
    company VARCHAR(10) DEFAULT NULL,
    battalion VARCHAR(10) DEFAULT NULL,
    date_enrolled DATE DEFAULT NULL,
    status ENUM('active','graduated','dismissed','resigned') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)", "cadet_profiles table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS officer_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    officer_id VARCHAR(20) NOT NULL UNIQUE,
    class_year VARCHAR(10) DEFAULT NULL,
    branch_of_service VARCHAR(60) DEFAULT NULL,
    current_assignment VARCHAR(100) DEFAULT NULL,
    license_number VARCHAR(30) DEFAULT NULL,
    certificate_number VARCHAR(40) DEFAULT NULL,
    transition_number VARCHAR(40) DEFAULT NULL,
    license_issued DATE DEFAULT NULL,
    license_expiry DATE DEFAULT NULL,
    license_status ENUM('valid','expired','revoked','suspended') DEFAULT 'valid',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)", "officer_profiles table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(30) NOT NULL UNIQUE,
    subject_user_id INT NOT NULL,
    case_title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    offense_category ENUM('Academic Dishonesty','Conduct Unbecoming','Violation of Honor Code','Misconduct','AWOL','Drug Related','Others') DEFAULT 'Others',
    filed_date DATE NOT NULL,
    hearing_date DATE DEFAULT NULL,
    verdict ENUM('Pending','Guilty','Not Guilty','Dismissed','Under Appeal') DEFAULT 'Pending',
    sanction TEXT DEFAULT NULL,
    handled_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
)", "cases table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    full_name VARCHAR(100) NOT NULL,
    blacklist_type ENUM('PAR','PMA','Both') NOT NULL,
    reason TEXT NOT NULL,
    offense_date DATE DEFAULT NULL,
    blacklisted_date DATE NOT NULL,
    blacklisted_by INT DEFAULT NULL,
    status ENUM('Active','Lifted','Under Review') DEFAULT 'Active',
    remarks TEXT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (blacklisted_by) REFERENCES users(id) ON DELETE SET NULL
)", "blacklist table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS blacklist_evidence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blacklist_id INT NOT NULL,
    file_label VARCHAR(200) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blacklist_id) REFERENCES blacklist(id) ON DELETE CASCADE
)", "blacklist_evidence table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS case_evidence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    file_label VARCHAR(200) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
)", "case_evidence table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    module VARCHAR(60) DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)", "audit_log table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS license_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sig_order INT NOT NULL DEFAULT 1,
    signatory_username VARCHAR(100) NOT NULL,
    signatory_position VARCHAR(150) NOT NULL,
    signature_image VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "license_signatures table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS cadet_card_signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sig_order INT NOT NULL DEFAULT 1,
    signatory_username VARCHAR(100) NOT NULL,
    signatory_position VARCHAR(150) NOT NULL,
    signature_image VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "cadet_card_signatures table");

// Admission tables
runSQL($conn, "
CREATE TABLE IF NOT EXISTS admission_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(100) NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    application_open DATE NOT NULL,
    application_close DATE NOT NULL,
    ee_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "admission_batches table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    tracking_number VARCHAR(20) NOT NULL UNIQUE,
    last_name VARCHAR(60) NOT NULL,
    first_name VARCHAR(60) NOT NULL,
    middle_name VARCHAR(60) DEFAULT NULL,
    suffix VARCHAR(10) DEFAULT NULL,
    gender ENUM('Male','Female') NOT NULL,
    date_of_birth DATE NOT NULL,
    place_of_birth VARCHAR(100) DEFAULT NULL,
    civil_status ENUM('Single','Married','Widowed','Other') DEFAULT 'Single',
    nationality VARCHAR(60) DEFAULT 'Filipino',
    religion VARCHAR(60) DEFAULT NULL,
    height_cm DECIMAL(5,2) DEFAULT NULL,
    weight_kg DECIMAL(5,2) DEFAULT NULL,
    blood_type VARCHAR(5) DEFAULT NULL,
    email VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) DEFAULT NULL,
    permanent_address TEXT DEFAULT NULL,
    current_address TEXT DEFAULT NULL,
    school_name VARCHAR(150) NOT NULL,
    school_address VARCHAR(200) DEFAULT NULL,
    school_type ENUM('Public','Private') DEFAULT 'Public',
    school_year_graduated VARCHAR(10) DEFAULT NULL,
    gwa DECIMAL(4,2) DEFAULT NULL,
    ee_passed TINYINT(1) DEFAULT 0,
    ee_score DECIMAL(5,2) DEFAULT NULL,
    ee_date DATE DEFAULT NULL,
    father_name VARCHAR(100) DEFAULT NULL,
    father_occupation VARCHAR(80) DEFAULT NULL,
    mother_name VARCHAR(100) DEFAULT NULL,
    mother_occupation VARCHAR(80) DEFAULT NULL,
    guardian_name VARCHAR(100) DEFAULT NULL,
    guardian_relationship VARCHAR(60) DEFAULT NULL,
    guardian_contact VARCHAR(20) DEFAULT NULL,
    eval_age_ok TINYINT(1) DEFAULT 0,
    eval_height_ok TINYINT(1) DEFAULT 0,
    eval_ee_ok TINYINT(1) DEFAULT 0,
    eval_gwa_ok TINYINT(1) DEFAULT 0,
    eval_docs_ok TINYINT(1) DEFAULT 0,
    auto_eligible TINYINT(1) DEFAULT 0,
    submission_status ENUM('draft','submitted','under_review','passed','failed','withdrawn') DEFAULT 'draft',
    review_notes TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    submitted_at TIMESTAMP NULL DEFAULT NULL,
    result_released_at TIMESTAMP NULL DEFAULT NULL,
    bg_check_start TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES admission_batches(id) ON DELETE CASCADE
)", "applicants table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS admission_required_docs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT DEFAULT NULL,
    doc_name VARCHAR(150) NOT NULL,
    doc_description TEXT DEFAULT NULL,
    is_required TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0
)", "admission_required_docs table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS applicant_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    doc_requirement_id INT DEFAULT NULL,
    doc_label VARCHAR(150) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(60) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    upload_status ENUM('pending','verified','rejected') DEFAULT 'pending',
    rejection_reason VARCHAR(255) DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
)", "applicant_documents table");

runSQL($conn, "
CREATE TABLE IF NOT EXISTS bg_check_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    checked_by INT DEFAULT NULL,
    check_type VARCHAR(80) DEFAULT 'General Background',
    findings TEXT DEFAULT NULL,
    result ENUM('Clear','With Record','Pending') DEFAULT 'Pending',
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE CASCADE
)", "bg_check_log table");

// ---- SEED admin account (password: Admin@1234) ----
$adminPw = password_hash('Admin@1234', PASSWORD_BCRYPT);
$conn->query("INSERT IGNORE INTO users (username,password,role,full_name,rank,unit) VALUES ('admin','$adminPw','admin','System Administrator','Administrator','PMA HQ')");
$results[] = "✓ Admin account seeded (admin / Admin@1234)";

// Seed default admission batch
$conn->query("INSERT IGNORE INTO admission_batches (batch_name,academic_year,application_open,application_close,is_active,created_by) SELECT 'PMA Class 2029 — Cadetship Admission AY 2025-2026','2025-2026','2025-10-01','2026-03-31',1,1 WHERE NOT EXISTS (SELECT 1 FROM admission_batches LIMIT 1)");

// Seed default EE document requirement
$conn->query("INSERT IGNORE INTO admission_required_docs (doc_name,doc_description,is_required,sort_order) SELECT 'Entrance Examination Result','Official EE result slip showing passing score',1,5 WHERE NOT EXISTS (SELECT 1 FROM admission_required_docs LIMIT 1)");

$results[] = "✓ Seed data inserted";

?>
<!DOCTYPE html>
<html>
<head>
    <title>PMAS Database Setup</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; }
        h2 { color: #C0001A; }
        .ok  { color: green; }
        .err { color: red; }
        .warn { background: #fff3cd; padding: 14px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #ffc107; }
        .success { background: #d4edda; padding: 14px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #28a745; }
    </style>
</head>
<body>
    <h2>PMAS — Database Setup</h2>

    <?php foreach ($results as $r): ?>
    <p class="ok"><?= htmlspecialchars($r) ?></p>
    <?php endforeach; ?>

    <?php foreach ($errors as $e): ?>
    <p class="err"><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
    <div class="success">
        <strong>✅ Setup complete!</strong><br>
        All tables created. You can now <a href="/">go to the portal</a> and log in with:<br>
        <strong>Username:</strong> admin<br>
        <strong>Password:</strong> Admin@1234<br><br>
        ⚠️ <strong>Delete this file (setup_db.php) after setup!</strong>
    </div>
    <?php else: ?>
    <div class="warn">
        <strong>⚠️ Some errors occurred.</strong> Check the red messages above.
    </div>
    <?php endif; ?>
</body>
</html>
