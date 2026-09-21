<?php
// ============================================================
// Export Records (CSV / Excel-compatible)
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$format    = trim($_GET['format'] ?? 'csv'); // csv or excel
$dateFrom  = trim($_GET['date_from']   ?? '');
$dateTo    = trim($_GET['date_to']     ?? '');
$filterComm  = (int)($_GET['community']  ?? 0);
$filterFac   = (int)($_GET['facility']   ?? 0);
$filterStaff = (int)($_GET['staff']      ?? 0);
$filterExemp = trim($_GET['exemption']   ?? '');
$filterPreg  = trim($_GET['preg_status'] ?? '');
$filterStatus = trim($_GET['status']     ?? '');

$pdo    = getDBConnection();
$where  = ['pw.is_deleted = 0'];
$params = [];

if ($dateFrom && isValidDate($dateFrom))  { $where[] = 'pw.registration_date >= :dfrom'; $params[':dfrom'] = $dateFrom; }
if ($dateTo   && isValidDate($dateTo))    { $where[] = 'pw.registration_date <= :dto';   $params[':dto']   = $dateTo; }
if ($filterComm)   { $where[] = 'pw.community_id = :cid';         $params[':cid']   = $filterComm; }
if ($filterFac)    { $where[] = 'pw.health_facility_id = :fid';   $params[':fid']   = $filterFac; }
if ($filterStaff)  { $where[] = 'pw.created_by = :staff';         $params[':staff'] = $filterStaff; }
if ($filterExemp)  { $where[] = 'pw.exemption_status = :estat';   $params[':estat'] = $filterExemp; }
if ($filterPreg)   { $where[] = 'pw.pregnancy_status = :pstat';   $params[':pstat'] = $filterPreg; }
if ($filterStatus) { $where[] = 'pw.pregnancy_status = :pstat2';  $params[':pstat2']= $filterStatus; }

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $pdo->prepare(
    "SELECT pw.record_number, pw.nhis_membership_number, pw.ghana_card_number,
            pw.first_name, pw.middle_name, pw.last_name,
            pw.date_of_birth, pw.phone, pw.alt_phone,
            COALESCE(c.community_name, pw.community_other, '')  AS community,
            pw.district, pw.region,
            pw.registration_date, pw.gravida, pw.parity,
            pw.gestational_age_weeks, pw.expected_delivery_date,
            pw.antenatal_start_date,
            COALESCE(hf.facility_name, pw.facility_other, '') AS health_facility,
            pw.pregnancy_status, pw.delivery_date, pw.delivery_outcome,
            pw.processing_date, pw.exemption_status, pw.reference_number,
            u_proc.full_name AS processed_by,
            pw.nhis_remarks, pw.general_remarks,
            u_creat.full_name AS created_by,
            pw.created_at, pw.updated_at
     FROM pregnant_women pw
     LEFT JOIN communities       c      ON pw.community_id       = c.id
     LEFT JOIN health_facilities hf     ON pw.health_facility_id = hf.id
     LEFT JOIN users             u_proc ON pw.processed_by       = u_proc.id
     LEFT JOIN users             u_creat ON pw.created_by        = u_creat.id
     $whereSQL
     ORDER BY pw.registration_date ASC, pw.id ASC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

logAudit($_SESSION['user_id'], 'EXPORT', 'Reports', null,
         "Exported " . count($rows) . " records as {$format}");

$filename = 'NHIS_Pregnancy_Records_' . date('Ymd_His') . '.csv';

// Both CSV and Excel use the same CSV format with BOM for Excel compatibility
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM for Excel UTF-8 recognition
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// Header row
fputcsv($out, [
    'Record Number', 'NHIS Membership No.', 'Ghana Card Number',
    'First Name', 'Middle Name', 'Last Name',
    'Date of Birth', 'Phone', 'Alt Phone',
    'Community', 'District', 'Region',
    'Registration Date', 'Gravida', 'Parity',
    'Gestational Age (Wks)', 'Expected Delivery Date',
    'ANC Start Date', 'Health Facility',
    'Pregnancy Status', 'Delivery Date', 'Delivery Outcome',
    'Processing Date', 'Exemption Status', 'Reference Number',
    'Processed By', 'NHIS Remarks', 'General Remarks',
    'Created By', 'Date Created', 'Last Updated'
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['record_number'],
        $r['nhis_membership_number'] ?? '',
        $r['ghana_card_number'] ?? '',
        $r['first_name'],
        $r['middle_name'] ?? '',
        $r['last_name'],
        $r['date_of_birth'] ?? '',
        $r['phone'] ?? '',
        $r['alt_phone'] ?? '',
        $r['community'],
        $r['district'],
        $r['region'],
        $r['registration_date'],
        $r['gravida'] ?? '',
        $r['parity'] ?? '',
        $r['gestational_age_weeks'] ?? '',
        $r['expected_delivery_date'] ?? '',
        $r['antenatal_start_date'] ?? '',
        $r['health_facility'],
        $r['pregnancy_status'],
        $r['delivery_date'] ?? '',
        $r['delivery_outcome'] ?? '',
        $r['processing_date'] ?? '',
        $r['exemption_status'],
        $r['reference_number'] ?? '',
        $r['processed_by'] ?? '',
        $r['nhis_remarks'] ?? '',
        $r['general_remarks'] ?? '',
        $r['created_by'] ?? '',
        $r['created_at'],
        $r['updated_at'],
    ]);
}

fclose($out);
exit;
