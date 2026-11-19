<?php
require_once '../../includes/config.php';
requireRole('staff','admin');
redirect(BASE_URL . 'pages/admin/cadet_cards.php');
