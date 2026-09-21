<?php
// ============================================================
// Login Page
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Go to dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error   = '';
$reason  = $_GET['reason'] ?? '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $result = attemptLogin($username, $password);
            if ($result['success']) {
                if ($result['must_change_password']) {
                    setFlash('warning', 'You must change your password before continuing.');
                    header('Location: ' . BASE_URL . '/profile.php?force_change=1');
                } else {
                    setFlash('success', 'Welcome back, ' . htmlspecialchars($_SESSION['full_name']) . '!');
                    header('Location: ' . BASE_URL . '/dashboard.php');
                }
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
    // Regenerate CSRF after failed attempt
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | NHIS Pregnancy Exemption System – Twifo Praso</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="w-100" style="max-width:440px;">

        <!-- System notice banner -->
        <div class="text-center mb-3 text-white-50 small">
            <i class="bi bi-shield-lock me-1"></i>
            Authorized NHIS Staff Access Only
        </div>

        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="login-logo">
                    <i class="bi bi-heart-pulse-fill"></i>
                </div>
                <h4 class="fw-700 mb-0" style="font-size:1.1rem;">NHIS Twifo Praso</h4>
                <p class="mb-0 mt-1" style="font-size:0.78rem;opacity:0.85;">Pregnancy Exemption Registration &amp; Records System</p>
            </div>
            <div class="login-divider"></div>

            <!-- Body -->
            <div class="login-body">
                <h5 class="fw-600 mb-4 text-center" style="color:#1a2535;">Sign In to Your Account</h5>

                <?php if ($reason === 'timeout'): ?>
                <div class="alert alert-warning alert-sm d-flex align-items-center gap-2 py-2">
                    <i class="bi bi-clock-history"></i>
                    <span>Your session expired due to inactivity. Please log in again.</span>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   placeholder="Enter your username" required autofocus autocomplete="username">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Enter your password" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                                    tabindex="-1" title="Show/hide password">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-nhis-primary w-100 py-2">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                </form>

                <!-- Demo credentials hint -->
                <div class="mt-4 p-3 rounded" style="background:#f8f9fa;border:1px dashed #ced4da;">
                    <p class="mb-1 fw-600" style="font-size:0.78rem;color:#495057;">
                        <i class="bi bi-info-circle me-1"></i>Demo Credentials
                    </p>
                    <p class="mb-0 text-muted" style="font-size:0.78rem;">
                        Admin: <code>admin</code> / <code>Admin@1234</code><br>
                        Staff: <code>abena.mensah</code> / <code>Admin@1234</code>
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer note -->
        <div class="text-center mt-3 text-white-50" style="font-size:0.72rem;">
            <em>Prototype &ndash; Internal Records Management Solution | NHIS Twifo Praso v1.0.0</em>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function () {
        const input = document.getElementById('password');
        const icon  = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
</script>
</body>
</html>
