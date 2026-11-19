<?php
require_once 'includes/config.php';
if (isLoggedIn()) {
    logAction($conn, $_SESSION['user_id'], 'LOGOUT', 'Auth', 'User logged out');
}
session_destroy();
redirect(BASE_URL . 'index.php');
?>
