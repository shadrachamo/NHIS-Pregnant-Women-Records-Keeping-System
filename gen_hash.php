<?php
// ============================================================
// Password Hash Generator & Direct Database Fixer
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
// ACCESS: http://localhost/NHIS/gen_hash.php
// DELETE THIS FILE after fixing passwords.
// ============================================================

require_once __DIR__ . '/config/database.php';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_password'])) {
    $newPassword = $_POST['new_password'];
    if (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        try {
            $pdo  = getDBConnection();
            $stmt = $pdo->prepare("UPDATE users SET password = :hash, must_change_password = 0 WHERE username IN ('admin','abena.mensah','kofi.agyemang')");
            $stmt->execute([':hash' => $hash]);
            $affected = $stmt->rowCount();
            $message  = "SUCCESS: Updated password for {$affected} user(s). New password: <strong>" . htmlspecialchars($newPassword) . "</strong><br>Hash: <code>" . htmlspecialchars($hash) . "</code>";
        } catch (Exception $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Fix | NHIS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width:500px;">
    <div class="card shadow-sm">
        <div class="card-header bg-danger text-white fw-bold">
            <i class="bi bi-key me-2"></i>Password Reset Tool
        </div>
        <div class="card-body">
            <div class="alert alert-warning py-2 small mb-3">
                <strong>⚠ Security Warning:</strong> Delete this file after use.<br>
                This tool resets passwords for all demo accounts.
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success small"><?= $message ?></div>
            <p><a href="<?= BASE_URL ?>/login.php" class="btn btn-success btn-sm">Go to Login</a></p>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger small"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-500">Set new password for all demo accounts:</label>
                    <input type="text" class="form-control" name="new_password"
                           value="Admin@1234" placeholder="Enter new password">
                    <div class="form-text">This will update: admin, abena.mensah, kofi.agyemang</div>
                </div>
                <button type="submit" class="btn btn-danger w-100">Reset Passwords</button>
            </form>

            <hr>
            <p class="small text-muted mb-0">Or run this SQL directly in phpMyAdmin:</p>
            <pre class="bg-light p-2 rounded small mt-1" style="font-size:.75rem;">UPDATE `users` SET `password` = '<?= htmlspecialchars(password_hash('Admin@1234', PASSWORD_DEFAULT)) ?>'
WHERE `username` IN ('admin','abena.mensah','kofi.agyemang');</pre>
        </div>
    </div>
</div>
</body>
</html>
