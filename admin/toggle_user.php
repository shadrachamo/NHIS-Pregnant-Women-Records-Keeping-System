<?php
// Activate / Deactivate user account
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$id     = (int) ($_GET['id'] ?? 0);
$action = trim($_GET['action'] ?? '');

if (!in_array($action, ['activate','deactivate']) || $id == $_SESSION['user_id']) {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

$pdo   = getDBConnection();
$stmt  = $pdo->prepare("SELECT id, full_name, username FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user  = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'User not found.');
} else {
    $newStatus = ($action === 'activate') ? 'active' : 'inactive';
    $upd = $pdo->prepare("UPDATE users SET status = :st WHERE id = :id");
    $upd->execute([':st' => $newStatus, ':id' => $id]);
    logAudit($_SESSION['user_id'], strtoupper($action) . '_USER', 'Users', $id,
             "User account {$action}d: {$user['username']}");
    setFlash('success', "User '{$user['full_name']}' has been {$action}d.");
}

header('Location: ' . BASE_URL . '/admin/users.php');
exit;
