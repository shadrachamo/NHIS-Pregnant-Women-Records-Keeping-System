<?php
// AJAX: check for duplicate NHIS number or Ghana Card number
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$field     = trim($_GET['field']      ?? 'nhis');   // 'nhis' or 'ghana'
$nhis      = trim($_GET['nhis']       ?? '');
$ghana     = trim($_GET['ghana']      ?? '');
$excludeId = (int) ($_GET['exclude_id'] ?? 0);

$pdo = getDBConnection();

if ($field === 'nhis' && preg_match('/^\d{8}$/', $nhis)) {
    $sql    = "SELECT id, first_name, last_name FROM pregnant_women WHERE nhis_membership_number = :val AND is_deleted = 0";
    $params = [':val' => $nhis];
} elseif ($field === 'ghana' && preg_match('/^GHA-\d{9}-\d$/', $ghana)) {
    $sql    = "SELECT id, first_name, last_name FROM pregnant_women WHERE ghana_card_number = :val AND is_deleted = 0";
    $params = [':val' => strtoupper($ghana)];
} else {
    echo json_encode(['found' => false]);
    exit;
}

if ($excludeId > 0) {
    $sql   .= " AND id != :eid";
    $params[':eid'] = $excludeId;
}
$sql .= " LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$row  = $stmt->fetch();

if ($row) {
    echo json_encode([
        'found'     => true,
        'id'        => $row['id'],
        'full_name' => $row['first_name'] . ' ' . $row['last_name'],
    ]);
} else {
    echo json_encode(['found' => false]);
}
