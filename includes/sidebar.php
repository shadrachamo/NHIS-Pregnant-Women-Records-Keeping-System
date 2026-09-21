<?php
// ============================================================
// Sidebar Navigation
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function sidebarActive(array $files, array $dirs = []): string {
    global $currentFile, $currentDir;
    if (in_array($currentFile, $files) || in_array($currentDir, $dirs)) {
        return ' active';
    }
    return '';
}
?>

<!-- ===== SIDEBAR ===== -->
<nav class="nhis-sidebar" id="nhis-sidebar">
    <div class="sidebar-inner">

        <!-- MAIN NAVIGATION -->
        <div class="sidebar-section-label">Main</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-link<?= sidebarActive(['dashboard.php']) ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <!-- PREGNANCY RECORDS -->
        <div class="sidebar-section-label">Pregnancy Records</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= BASE_URL ?>/pregnancy/add.php" class="sidebar-link<?= sidebarActive(['add.php'], ['pregnancy']) ?>">
                    <i class="bi bi-plus-circle"></i>
                    <span>New Registration</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/records/index.php" class="sidebar-link<?= sidebarActive(['index.php'], ['records']) ?>">
                    <i class="bi bi-table"></i>
                    <span>All Records</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/records/search.php" class="sidebar-link<?= sidebarActive(['search.php']) ?>">
                    <i class="bi bi-search"></i>
                    <span>Search Records</span>
                </a>
            </li>
        </ul>

        <!-- REPORTS -->
        <div class="sidebar-section-label">Reports</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= BASE_URL ?>/reports/index.php" class="sidebar-link<?= sidebarActive(['index.php', 'monthly.php'], ['reports']) ?>">
                    <i class="bi bi-bar-chart-line"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>

        <!-- ADMINISTRATION -->
        <?php if (isAdmin()): ?>
        <div class="sidebar-section-label">Administration</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= BASE_URL ?>/admin/users.php" class="sidebar-link<?= sidebarActive(['users.php', 'add_user.php', 'edit_user.php'], ['admin']) ?>">
                    <i class="bi bi-people"></i>
                    <span>User Management</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="sidebar-link<?= sidebarActive(['audit_logs.php']) ?>">
                    <i class="bi bi-shield-check"></i>
                    <span>Audit Logs</span>
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>/settings.php" class="sidebar-link<?= sidebarActive(['settings.php']) ?>">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <!-- ACCOUNT -->
        <div class="sidebar-section-label">Account</div>
        <ul class="sidebar-nav">
            <li>
                <a href="<?= BASE_URL ?>/profile.php" class="sidebar-link<?= sidebarActive(['profile.php']) ?>">
                    <i class="bi bi-person-circle"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="#" class="sidebar-link text-danger-soft" onclick="document.getElementById('logoutForm').submit();">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>

    </div><!-- /sidebar-inner -->

    <!-- Sidebar footer -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2">
            <div class="nhis-avatar nhis-avatar-sm"><?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)) ?></div>
            <div style="min-width:0;">
                <div class="fw-500 text-truncate" style="font-size:0.78rem;max-width:130px;"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></div>
                <div class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($_SESSION['role_name'] ?? '') ?></div>
            </div>
        </div>
        <div class="mt-2 text-muted" style="font-size:0.68rem;">NHIS-PERS v1.0.0</div>
    </div>
</nav>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
