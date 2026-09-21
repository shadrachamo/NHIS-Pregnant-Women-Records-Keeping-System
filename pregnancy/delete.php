<?php
// ============================================================
// Delete Pregnancy Record (soft-delete, admin only)
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();  // Admin only

$id  = (int) ($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT id, record_number, full_name FROM pregnant_women WHERE id = :id AND is_deleted = 0");
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();

if (!$record) {
    setFlash('danger', 'Record not found or already deleted.');
    header('Location: ' . BASE_URL . '/records/index.php');
    exit;
}

// Perform soft-delete
$del = $pdo->prepare("UPDATE pregnant_women SET is_deleted = 1, updated_by = :uid WHERE id = :id");
$del->execute([':uid' => $_SESSION['user_id'], ':id' => $id]);

logAudit($_SESSION['user_id'], 'DELETE_RECORD', 'Pregnancy', $id,
         "Record deleted: {$record['record_number']} – {$record['full_name']}");

setFlash('success', "Record {$record['record_number']} has been deleted.");
header('Location: ' . BASE_URL . '/records/index.php');
exit;
