<?php
// Start output buffering immediately to prevent "headers already sent" errors
if (!ob_get_level()) ob_start();

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
    // On Railway: show a friendly setup page instead of crashing
    if (!empty(getenv('RAILWAY_ENVIRONMENT'))) {
        die('<!DOCTYPE html><html><head><title>PMAS — Setup Required</title>
        <style>body{font-family:sans-serif;max-width:600px;margin:60px auto;padding:20px;background:#1a1a1a;color:#fff;}
        h2{color:#C0001A;}a{color:#FFD700;}code{background:#333;padding:3px 8px;border-radius:4px;}</style></head>
        <body><h2>⚙️ PMAS — Database Not Connected</h2>
        <p>MySQL environment variables are not set yet.</p>
        <p>In Railway: go to your <strong>PMAS service → Variables</strong> and add references to your MySQL service:</p>
        <ul><li><code>MYSQLHOST</code></li><li><code>MYSQLUSER</code></li>
        <li><code>MYSQLPASSWORD</code></li><li><code>MYSQLDATABASE</code></li><li><code>MYSQLPORT</code></li></ul>
        <p>Then redeploy. Once connected, visit 
        <a href="/setup_db.php?key=pmas_setup_2026">/setup_db.php?key=pmas_setup_2026</a> to create the tables.</p>
        </body></html>');
    }
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


// Auto-add columns only if users table already exists (MySQL-compatible)
$_tbl = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'");
if ($_tbl && $_tbl->num_rows > 0) {
    $col1 = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='roblox_username'");
    if ($col1 && $col1->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN roblox_username VARCHAR(100) DEFAULT NULL AFTER email");
    }
    $col2 = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='officer_profiles' AND COLUMN_NAME='certificate_number'");
    if ($col2 && $col2->num_rows === 0) {
        $conn->query("ALTER TABLE officer_profiles ADD COLUMN certificate_number VARCHAR(40) DEFAULT NULL AFTER license_number");
    }
    $col3 = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='officer_profiles' AND COLUMN_NAME='transition_number'");
    if ($col3 && $col3->num_rows === 0) {
        $conn->query("ALTER TABLE officer_profiles ADD COLUMN transition_number VARCHAR(40) DEFAULT NULL AFTER certificate_number");
    }
    // Widen cadet_id to VARCHAR(100) if it is still VARCHAR(20)
    $col4 = $conn->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cadet_profiles' AND COLUMN_NAME='cadet_id'");
    if ($col4) {
        $len = (int)($col4->fetch_row()[0] ?? 0);
        if ($len < 100) {
            $conn->query("ALTER TABLE cadet_profiles MODIFY COLUMN cadet_id VARCHAR(100) NOT NULL");
        }
    }
}


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
