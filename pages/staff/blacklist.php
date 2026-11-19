<?php
// Staff uses the same module as admin — just include it with an adjusted base path
define('STAFF_MODE', true);
require_once '../../includes/config.php';
requireRole('staff','admin');
// Redirect to admin module (staff can access the same page)
redirect(BASE_URL . 'pages/admin/blacklist.php');
