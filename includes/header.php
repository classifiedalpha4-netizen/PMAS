<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' | ' : '' ?><?= SITE_NAME ?></title>
    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>

<!-- TOP NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark pmas-navbar">
    <div class="container-fluid">
        <!-- Mobile sidebar toggle -->
        <button id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>

        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>dashboard.php">
            <div class="pmas-logo-circle">
                <img src="<?= BASE_URL ?>assets/img/pma_logo.php" alt="PMA Logo">
            </div>
            <div>
                <div class="pmas-brand-title">PMAS Portal</div>
                <div class="pmas-brand-sub">Philippine Military Academy System</div>
            </div>
        </a>
        <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <?php if (isset($_SESSION['role'])): ?>
                <li class="nav-item">
                    <span class="nav-link text-warning" style="white-space:nowrap;">
                        <i class="fas fa-user-shield me-1"></i>
                        <?= strtoupper(sanitize($_SESSION['role'])) ?> &mdash; <?= sanitize($_SESSION['username']) ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>logout.php"
                       class="btn btn-outline-light btn-sm px-3"
                       style="font-size:12px;white-space:nowrap;">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Sidebar overlay (mobile) -->
<div id="sidebarOverlay"></div>

<div class="d-flex" id="pmas-wrapper">
