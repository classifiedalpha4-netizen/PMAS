<?php
require_once '../../includes/config.php';
requireRole('staff','admin');
redirect(BASE_URL . 'pages/admin/officer_licenses.php');
