<?php
// ============================================================
// Authentication & Session Management
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Start session with a custom name
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ------------------------------------------------------------------
// requireLogin – redirect to login if not authenticated
// ------------------------------------------------------------------
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    // Session timeout check (60 minutes by default)
    $timeout = (int) getSetting('session_timeout', '60') * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?reason=timeout');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ------------------------------------------------------------------
// requireAdmin – additionally require administrator role
// ------------------------------------------------------------------
function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['role_id'] ?? 0) != 1) {
        setFlash('danger', 'Access denied. Administrator privileges required.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

// ------------------------------------------------------------------
// isAdmin – boolean check
// ------------------------------------------------------------------
function isAdmin(): bool {
    return (($_SESSION['role_id'] ?? 0) == 1);
}

// ------------------------------------------------------------------
// isLoggedIn – boolean check
// ------------------------------------------------------------------
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

// ------------------------------------------------------------------
// getCurrentUser – returns current user array or null
// ------------------------------------------------------------------
function getCurrentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT u.*, r.role_name FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE u.id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

// ------------------------------------------------------------------
// attemptLogin – validate credentials and start session
// ------------------------------------------------------------------
function attemptLogin(string $username, string $password): array {
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT u.*, r.role_name FROM users u
         JOIN roles r ON u.role_id = r.id
         WHERE u.username = :username LIMIT 1"
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }
    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Your account has been deactivated. Contact the administrator.'];
    }
    if (!password_verify($password, $user['password'])) {
        logAudit(null, 'FAILED_LOGIN', 'Auth', null, "Failed login attempt for username: {$username}");
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['role_id']       = $user['role_id'];
    $_SESSION['role_name']     = $user['role_name'];
    $_SESSION['last_activity'] = time();

    // Update last_login timestamp
    $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
    $upd->execute([':id' => $user['id']]);

    logAudit($user['id'], 'LOGIN', 'Auth', null, 'User logged in successfully');

    return [
        'success'             => true,
        'must_change_password' => (bool) $user['must_change_password'],
    ];
}

// ------------------------------------------------------------------
// logoutUser – destroy session
// ------------------------------------------------------------------
function logoutUser(): void {
    if (!empty($_SESSION['user_id'])) {
        logAudit($_SESSION['user_id'], 'LOGOUT', 'Auth', null, 'User logged out');
    }
    session_unset();
    session_destroy();
}
