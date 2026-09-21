<?php
// ============================================================
// Page Header – Bootstrap 5 + Bootstrap Icons + Chart.js
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
if (!isset($pageTitle)) $pageTitle = APP_NAME;
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NHIS Pregnancy Exemption Registration and Records Management System – Twifo Praso">
    <title><?= htmlspecialchars($pageTitle) ?> | NHIS Twifo Praso</title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- ===== TOP NAVIGATION BAR ===== -->
<nav class="navbar navbar-expand-lg navbar-dark nhis-topbar fixed-top">
    <div class="container-fluid px-3">

        <!-- Sidebar toggle button (mobile) -->
        <button class="btn btn-sm btn-link text-white me-2 d-lg-none" id="sidebarToggleMobile" aria-label="Toggle sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_URL ?>/dashboard.php">
            <div class="nhis-brand-icon">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <div class="d-none d-md-block">
                <div class="fw-700 lh-1" style="font-size:0.95rem;">NHIS Twifo Praso</div>
                <div style="font-size:0.7rem;opacity:0.8;font-weight:400;">Pregnancy Exemption System</div>
            </div>
        </a>

        <div class="ms-auto d-flex align-items-center gap-2">
            <!-- Current date/time -->
            <span class="d-none d-lg-block text-white-50 small" id="topDateTime"></span>

            <!-- Quick actions -->
            <a href="<?= BASE_URL ?>/pregnancy/add.php" class="btn btn-sm btn-success d-none d-md-flex align-items-center gap-1">
                <i class="bi bi-plus-circle"></i> New Registration
            </a>

            <!-- User dropdown -->
            <div class="dropdown">
                <button class="btn btn-sm btn-link text-white d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="nhis-avatar"><?= strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)) ?></div>
                    <span class="d-none d-lg-block">
                        <span class="fw-500" style="font-size:0.85rem;"><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></span>
                        <br><span class="text-white-50" style="font-size:0.72rem;"><?= htmlspecialchars($currentUser['role_name'] ?? '') ?></span>
                    </span>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><h6 class="dropdown-header"><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></h6></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                    <?php if (isAdmin()): ?>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/settings.php"><i class="bi bi-gear me-2"></i>System Settings</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="#" onclick="document.getElementById('logoutForm').submit();">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Hidden logout form -->
<form id="logoutForm" method="POST" action="<?= BASE_URL ?>/logout.php" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
</form>

<!-- ===== WRAPPER ===== -->
<div class="nhis-wrapper">
