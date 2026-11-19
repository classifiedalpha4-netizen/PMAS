<?php
// ============================================================
// Database Configuration
// Railway provides env vars: MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE, MYSQLPORT
// Falls back to localhost (XAMPP) when env vars are not set
// ============================================================
define('DB_HOST', getenv('MYSQLHOST')     ?: getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER')     ?: getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'pmas_db');
define('DB_PORT', (int)(getenv('MYSQLPORT') ?: 3306));

define('SITE_NAME', 'PMAS Portal');
define('SITE_FULL_NAME', 'Philippine Military Academy System');

// BASE_URL — auto-detected, works on localhost, ngrok, and Railway
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $_protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'];
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $_protocol = 'https';
} else {
    $_protocol = 'http';
}
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// On Railway the app runs at root (/), on XAMPP it runs at /PMAS/
$_isRailway = !empty(getenv('RAILWAY_ENVIRONMENT'));
define('BASE_URL', $_protocol . '://' . $_host . ($_isRailway ? '/' : '/PMAS/'));

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// Database Connection (MySQLi)
// ============================================================
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;color:red;padding:20px;">
        <strong>Database Connection Failed:</strong> ' . $conn->connect_error . '
        <br><small>Please ensure MySQL is running and the database <em>' . DB_NAME . '</em> exists.</small>
    </div>');
}
$conn->set_charset('utf8mb4');

// ============================================================
// Helper Functions
// ============================================================

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . 'index.php');
    }
}

function requireRole(...$roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        redirect(BASE_URL . 'unauthorized.php');
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function logAction($conn, $userId, $action, $module = '', $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO audit_log (user_id, action, module, details, ip_address) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issss', $userId, $action, $module, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

function getUser($conn, $userId) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}


// Auto-add roblox_username column if not exists
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS roblox_username VARCHAR(100) DEFAULT NULL AFTER email");


/** Minimum requirements for auto-evaluation */
define('ADMISSION_MIN_AGE',    17);
define('ADMISSION_MAX_AGE',    22);
define('ADMISSION_MIN_HEIGHT_M', 162); // cm for male
define('ADMISSION_MIN_HEIGHT_F', 157); // cm for female
define('ADMISSION_MAX_GWA',    85);    // GWA must be >= 85 (Philippine grading: higher = better)
define('ADMISSION_EE_REQUIRED', true);

function evaluateApplicant($applicant) {
    $flags = [
        'eval_age_ok'    => 0,
        'eval_height_ok' => 0,
        'eval_ee_ok'     => 0,
        'eval_gwa_ok'    => 0,
        'eval_docs_ok'   => 0,
    ];

    // Age check (must be 17-22 as of application)
    if (!empty($applicant['date_of_birth'])) {
        $dob  = new DateTime($applicant['date_of_birth']);
        $now  = new DateTime();
        $age  = (int)$dob->diff($now)->y;
        $flags['eval_age_ok'] = ($age >= ADMISSION_MIN_AGE && $age <= ADMISSION_MAX_AGE) ? 1 : 0;
    }

    // Height check
    if (!empty($applicant['height_cm']) && !empty($applicant['gender'])) {
        $minH = $applicant['gender'] === 'Male' ? ADMISSION_MIN_HEIGHT_M : ADMISSION_MIN_HEIGHT_F;
        $flags['eval_height_ok'] = $applicant['height_cm'] >= $minH ? 1 : 0;
    }

    // EE passed
    $flags['eval_ee_ok'] = !empty($applicant['ee_passed']) && $applicant['ee_passed'] == 1 ? 1 : 0;

    // GWA (Philippine system: 100 is highest, 75 is passing; we require >= 85)
    if (!empty($applicant['gwa'])) {
        $flags['eval_gwa_ok'] = $applicant['gwa'] >= ADMISSION_MAX_GWA ? 1 : 0;
    }

    // Documents — set to 1 if at least 1 doc uploaded (full doc check is manual)
    // This is set separately after upload count is known
    $flags['eval_docs_ok'] = $applicant['eval_docs_ok'] ?? 0;

    // Auto-eligible only if ALL flags pass
    $flags['auto_eligible'] = (
        $flags['eval_age_ok']    &&
        $flags['eval_height_ok'] &&
        $flags['eval_ee_ok']     &&
        $flags['eval_gwa_ok']    &&
        $flags['eval_docs_ok']
    ) ? 1 : 0;

    return $flags;
}

function getAdmissionStatusBadge($status) {
    $map = [
        'draft'        => ['secondary', 'Draft'],
        'submitted'    => ['warning',   'Submitted — Under Review'],
        'under_review' => ['info',      'Under Review'],
        'passed'       => ['success',   'PASSED'],
        'failed'       => ['danger',    'FAILED'],
        'withdrawn'    => ['secondary', 'Withdrawn'],
    ];
    [$color, $label] = $map[$status] ?? ['secondary', $status];
    return "<span class='badge bg-$color'>$label</span>";
}

function generateTrackingNumber($conn) {
    $year = date('Y');
    $res  = $conn->query("SELECT COUNT(*) FROM applicants WHERE YEAR(created_at)=$year");
    $count = (int)$res->fetch_row()[0] + 1;
    return sprintf('PMA-APP-%s-%05d', $year, $count);
}

function getStatusBadge($status) {
    $map = [
        'active'       => 'success',
        'valid'        => 'success',
        'Pending'      => 'warning',
        'Guilty'       => 'danger',
        'Not Guilty'   => 'success',
        'Dismissed'    => 'secondary',
        'Under Appeal' => 'info',
        'Active'       => 'danger',
        'Lifted'       => 'success',
        'Under Review' => 'warning',
        'graduated'    => 'primary',
        'dismissed'    => 'danger',
        'resigned'     => 'secondary',
        'expired'      => 'warning',
        'revoked'      => 'danger',
        'suspended'    => 'warning',
        'PASSED'       => 'success',
        'FAILED'       => 'danger',
    ];
    $color = $map[$status] ?? 'secondary';
    return "<span class='badge bg-$color'>$status</span>";
}
?>
