<?php
// ============================================================
// System Settings (Admin only)
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$pageTitle = 'System Settings';
$pdo       = getDBConnection();
$errors    = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token.';
    } else {
        $settingsToSave = [
            'system_name'       => trim($_POST['system_name']       ?? ''),
            'office_name'       => trim($_POST['office_name']       ?? ''),
            'district'          => trim($_POST['district']          ?? ''),
            'region'            => trim($_POST['region']            ?? ''),
            'records_per_page'  => trim($_POST['records_per_page']  ?? '20'),
            'session_timeout'   => trim($_POST['session_timeout']   ?? '60'),
            'allow_staff_delete'=> trim($_POST['allow_staff_delete']?? '0'),
            'footer_text'       => trim($_POST['footer_text']       ?? ''),
        ];

        if (empty($settingsToSave['system_name'])) $errors[] = 'System name is required.';

        if (empty($errors)) {
            foreach ($settingsToSave as $key => $value) {
                saveSetting($key, $value, $_SESSION['user_id']);
            }
            logAudit($_SESSION['user_id'], 'SETTINGS', 'Settings', null, 'System settings updated');
            setFlash('success', 'Settings saved successfully.');
            header('Location: ' . BASE_URL . '/settings.php');
            exit;
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load all settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $settingsStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

function s(string $key, string $default = '', array $settings = []): string {
    return htmlspecialchars($settings[$key] ?? $default);
}

// Get database size info
try {
    $dbSize = $pdo->query(
        "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
         FROM information_schema.tables
         WHERE table_schema = 'nhis_pregnancy'"
    )->fetchColumn();
} catch (Exception $e) { $dbSize = 'N/A'; }

$totalRecords = (int) $pdo->query("SELECT COUNT(*) FROM pregnant_women WHERE is_deleted=0")->fetchColumn();
$totalUsers   = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalLogs    = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="nhis-content">

    <div class="page-header">
        <h1><i class="bi bi-gear me-2 text-nhis"></i>System Settings</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </nav>
    </div>

    <?php renderFlash(); ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">

            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- General Settings -->
                <div class="card mb-4">
                    <div class="card-header"><i class="bi bi-info-circle me-2 text-nhis"></i>General Settings</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">System Name <span class="required-star">*</span></label>
                                <input type="text" class="form-control" name="system_name" required
                                       value="<?= s('system_name','NHIS Pregnancy Exemption Registration System',$settings) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Office Name</label>
                                <input type="text" class="form-control" name="office_name"
                                       value="<?= s('office_name','NHIS Twifo Praso District Office',$settings) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">District</label>
                                <input type="text" class="form-control" name="district"
                                       value="<?= s('district','Twifo Atti-Morkwa',$settings) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Region</label>
                                <input type="text" class="form-control" name="region"
                                       value="<?= s('region','Central Region',$settings) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Footer Text</label>
                                <input type="text" class="form-control" name="footer_text"
                                       value="<?= s('footer_text','',$settings) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Behaviour -->
                <div class="card mb-4">
                    <div class="card-header"><i class="bi bi-sliders me-2 text-nhis"></i>System Behaviour</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Records Per Page</label>
                                <select class="form-select" name="records_per_page">
                                    <?php foreach ([10,15,20,25,50,100] as $n): ?>
                                    <option value="<?= $n ?>" <?= (($settings['records_per_page'] ?? 20) == $n) ? 'selected':'' ?>><?= $n ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Session Timeout (minutes)</label>
                                <select class="form-select" name="session_timeout">
                                    <?php foreach ([15,30,60,120,240] as $n): ?>
                                    <option value="<?= $n ?>" <?= (($settings['session_timeout'] ?? 60) == $n) ? 'selected':'' ?>><?= $n ?> min</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Staff Can Delete Records</label>
                                <select class="form-select" name="allow_staff_delete">
                                    <option value="0" <?= (($settings['allow_staff_delete'] ?? '0') === '0') ? 'selected':''?>>No (Admin only)</option>
                                    <option value="1" <?= (($settings['allow_staff_delete'] ?? '0') === '1') ? 'selected':''?>>Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-nhis-primary px-4">
                    <i class="bi bi-save me-1"></i>Save Settings
                </button>
            </form>

        </div>

        <div class="col-lg-4">
            <!-- System Info -->
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-info-circle me-2 text-nhis"></i>System Information</div>
                <div class="card-body">
                    <table class="table table-sm mb-0" style="font-size:.85rem;">
                        <tr><td class="text-muted">Version</td><td><strong><?= APP_VERSION ?></strong></td></tr>
                        <tr><td class="text-muted">Total Records</td><td><strong><?= number_format($totalRecords) ?></strong></td></tr>
                        <tr><td class="text-muted">Total Users</td><td><strong><?= $totalUsers ?></strong></td></tr>
                        <tr><td class="text-muted">Audit Log Entries</td><td><strong><?= number_format($totalLogs) ?></strong></td></tr>
                        <tr><td class="text-muted">Database Size</td><td><strong><?= $dbSize ?> MB</strong></td></tr>
                        <tr><td class="text-muted">PHP Version</td><td><strong><?= PHP_VERSION ?></strong></td></tr>
                    </table>
                </div>
            </div>

            <!-- Backup Instructions -->
            <div class="card">
                <div class="card-header"><i class="bi bi-database me-2 text-nhis"></i>Database Backup</div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Regular backups protect against data loss. Follow these steps:</p>
                    <ol class="small ps-3 mb-3">
                        <li>Open <strong>phpMyAdmin</strong> at <a href="http://localhost/phpmyadmin" target="_blank">localhost/phpmyadmin</a></li>
                        <li>Select the <code>nhis_pregnancy</code> database</li>
                        <li>Click the <strong>Export</strong> tab</li>
                        <li>Choose <strong>Quick</strong> export method</li>
                        <li>Click <strong>Go</strong> to download the <code>.sql</code> file</li>
                        <li>Store backups securely — they contain sensitive patient information</li>
                    </ol>
                    <div class="alert alert-warning py-2 small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Back up regularly. Store backup files securely and restrict access.
                    </div>
                    <a href="http://localhost/phpmyadmin" target="_blank" class="btn btn-outline-secondary btn-sm mt-2 w-100">
                        <i class="bi bi-database-add me-1"></i>Open phpMyAdmin
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
