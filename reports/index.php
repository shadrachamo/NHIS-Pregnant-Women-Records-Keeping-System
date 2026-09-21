<?php
// ============================================================
// Reports
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'Reports';
$pdo         = getDBConnection();
$communities = getCommunities();
$facilities  = getHealthFacilities();
$staffList   = getAllUsers(true);

// --- Report parameters ---
$reportType  = trim($_GET['report_type'] ?? 'date_range');
$dateFrom    = trim($_GET['date_from']   ?? date('Y-m-01'));
$dateTo      = trim($_GET['date_to']     ?? date('Y-m-d'));
$groupBy     = trim($_GET['group_by']    ?? 'registration_date');
$filterComm  = (int)  ($_GET['community']  ?? 0);
$filterFac   = (int)  ($_GET['facility']   ?? 0);
$filterStaff = (int)  ($_GET['staff']      ?? 0);
$filterExemp = trim(  $_GET['exemption']   ?? '');
$filterPreg  = trim(  $_GET['preg_status'] ?? '');
$generated   = isset($_GET['generate']);

$records     = [];
$summary     = [];

if ($generated) {
    $where  = ['pw.is_deleted = 0'];
    $params = [];

    if (!empty($dateFrom) && isValidDate($dateFrom)) { $where[] = 'pw.registration_date >= :dfrom'; $params[':dfrom'] = $dateFrom; }
    if (!empty($dateTo)   && isValidDate($dateTo))   { $where[] = 'pw.registration_date <= :dto';   $params[':dto']   = $dateTo; }
    if ($filterComm)  { $where[] = 'pw.community_id = :cid';          $params[':cid']  = $filterComm; }
    if ($filterFac)   { $where[] = 'pw.health_facility_id = :fid';    $params[':fid']  = $filterFac; }
    if ($filterStaff) { $where[] = 'pw.created_by = :staff';          $params[':staff']= $filterStaff; }
    if ($filterExemp) { $where[] = 'pw.exemption_status = :estat';    $params[':estat']= $filterExemp; }
    if ($filterPreg)  { $where[] = 'pw.pregnancy_status = :pstat';    $params[':pstat']= $filterPreg; }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    // Summary statistics
    $sumStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN pw.exemption_status='Approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN pw.exemption_status='Pending'  THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN pw.exemption_status='Rejected' THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN pw.pregnancy_status='Active'   THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN pw.pregnancy_status='Delivered' THEN 1 ELSE 0 END) AS delivered
         FROM pregnant_women pw $whereSQL"
    );
    $sumStmt->execute($params);
    $summary = $sumStmt->fetch();

    // Detail records
    $pg    = getPaginationParams(50);
    $pgStr = http_build_query(array_filter([
        'report_type'=>$reportType,'date_from'=>$dateFrom,'date_to'=>$dateTo,
        'community'=>$filterComm?:'','facility'=>$filterFac?:'','staff'=>$filterStaff?:'',
        'exemption'=>$filterExemp,'preg_status'=>$filterPreg,'generate'=>1
    ]));

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM pregnant_women pw $whereSQL");
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();

    $dataStmt = $pdo->prepare(
        "SELECT pw.id, pw.record_number, pw.nhis_membership_number, pw.ghana_card_number,
                pw.first_name, pw.last_name,
                pw.phone, pw.registration_date, pw.processing_date,
                pw.pregnancy_status, pw.exemption_status, pw.reference_number,
                pw.gestational_age_weeks, pw.expected_delivery_date,
                COALESCE(c.community_name,  pw.community_other,  '—') AS community,
                COALESCE(hf.facility_name,  pw.facility_other,   '—') AS facility,
                u.full_name AS registered_by
         FROM pregnant_women pw
         LEFT JOIN communities      c  ON pw.community_id       = c.id
         LEFT JOIN health_facilities hf ON pw.health_facility_id = hf.id
         LEFT JOIN users             u  ON pw.created_by         = u.id
         $whereSQL
         ORDER BY pw.registration_date ASC, pw.id ASC
         LIMIT :lim OFFSET :off"
    );
    foreach ($params as $k => $v) $dataStmt->bindValue($k, $v);
    $dataStmt->bindValue(':lim', $pg['per_page'], PDO::PARAM_INT);
    $dataStmt->bindValue(':off', $pg['offset'],   PDO::PARAM_INT);
    $dataStmt->execute();
    $records = $dataStmt->fetchAll();

    // Log report generation
    logAudit($_SESSION['user_id'], 'REPORT', 'Reports', null,
             "Report generated: {$dateFrom} to {$dateTo}. Total: " . ($summary['total'] ?? 0));
} else {
    $pg = getPaginationParams(); $pgStr = ''; $totalRows = 0;
}

$exportParams = http_build_query(array_filter([
    'date_from'=>$dateFrom,'date_to'=>$dateTo,
    'community'=>$filterComm?:'','facility'=>$filterFac?:'','staff'=>$filterStaff?:'',
    'exemption'=>$filterExemp,'preg_status'=>$filterPreg
]));

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1><i class="bi bi-bar-chart-line me-2 text-nhis"></i>Reports</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>
            </nav>
        </div>
        <?php if ($generated && $summary['total'] > 0): ?>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/reports/export.php?format=csv&<?= htmlspecialchars($exportParams) ?>"
               class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a>
            <a href="<?= BASE_URL ?>/reports/export.php?format=excel&<?= htmlspecialchars($exportParams) ?>"
               class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
            <button class="btn btn-outline-secondary btn-sm" data-print>
                <i class="bi bi-printer me-1"></i>Print
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php renderFlash(); ?>

    <!-- Report Criteria Form -->
    <div class="card mb-4 no-print">
        <div class="card-header"><i class="bi bi-sliders me-2 text-nhis"></i>Report Criteria</div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="generate" value="1">

                <div class="col-md-3">
                    <label class="form-label fw-500">Date From <span class="required-star">*</span></label>
                    <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-500">Date To <span class="required-star">*</span></label>
                    <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-500">Community</label>
                    <select class="form-select" name="community">
                        <option value="">All Communities</option>
                        <?php foreach ($communities as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterComm==$c['id']?'selected':''?>><?= htmlspecialchars($c['community_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-500">Health Facility</label>
                    <select class="form-select" name="facility">
                        <option value="">All Facilities</option>
                        <?php foreach ($facilities as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $filterFac==$f['id']?'selected':''?>><?= htmlspecialchars($f['facility_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-500">Staff Member</label>
                    <select class="form-select" name="staff">
                        <option value="">All Staff</option>
                        <?php foreach ($staffList as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filterStaff==$u['id']?'selected':''?>><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-500">Exemption Status</label>
                    <select class="form-select" name="exemption">
                        <option value="">All</option>
                        <?php foreach (['Approved','Pending','Under Review','Rejected'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterExemp===$s?'selected':''?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-500">Pregnancy Status</label>
                    <select class="form-select" name="preg_status">
                        <option value="">All</option>
                        <?php foreach (['Active','Delivered','Lost to Follow-up','Referred','Deceased','Other'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterPreg===$s?'selected':''?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-nhis-primary flex-fill">
                        <i class="bi bi-bar-chart me-1"></i>Generate Report
                    </button>
                    <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x"></i>
                    </a>
                </div>

                <!-- Quick date presets -->
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <span class="text-muted small me-1 align-self-center">Quick:</span>
                    <?php
                    $presets = [
                        'Today'       => [date('Y-m-d'),         date('Y-m-d')],
                        'This Week'   => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
                        'This Month'  => [date('Y-m-01'),         date('Y-m-d')],
                        'Last Month'  => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))],
                        'This Year'   => [date('Y-01-01'),        date('Y-m-d')],
                    ];
                    foreach ($presets as $label => [$from, $to]): ?>
                    <a href="?generate=1&date_from=<?= $from ?>&date_to=<?= $to ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($generated): ?>

    <!-- Print header -->
    <div class="print-header mb-3">
        <h4>NHIS Twifo Praso District Office</h4>
        <h5>Pregnancy Exemption Registration Report</h5>
        <p>Period: <?= htmlspecialchars($dateFrom) ?> to <?= htmlspecialchars($dateTo) ?> | Generated: <?= date('d M Y, H:i') ?> by <?= htmlspecialchars($_SESSION['full_name']) ?></p>
        <hr>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="stat-card stat-green p-3 text-center">
                <div class="stat-value"><?= $summary['total'] ?></div>
                <div class="stat-label" style="font-size:.7rem;">Total</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card stat-sky p-3 text-center">
                <div class="stat-value"><?= $summary['approved'] ?></div>
                <div class="stat-label" style="font-size:.7rem;">Approved</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card stat-gold p-3 text-center">
                <div class="stat-value"><?= $summary['pending'] ?></div>
                <div class="stat-label" style="font-size:.7rem;">Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card stat-rose p-3 text-center">
                <div class="stat-value"><?= $summary['rejected'] ?></div>
                <div class="stat-label" style="font-size:.7rem;">Rejected</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card stat-teal p-3 text-center">
                <div class="stat-value"><?= $summary['active'] ?></div>
                <div class="stat-label" style="font-size:.7rem;">Active Preg.</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card stat-purple p-3 text-center">
                <div class="stat-value"><?= $summary['delivered'] ?></div>
                <div class="stat-label" style="font-size:.7rem;">Delivered</div>
            </div>
        </div>
    </div>

    <!-- Records table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <i class="bi bi-table me-2 text-nhis"></i>Detailed Records
                <span class="badge bg-secondary ms-1"><?= number_format($totalRows) ?></span>
            </span>
            <small class="text-muted">
                <?= htmlspecialchars($dateFrom) ?> – <?= htmlspecialchars($dateTo) ?>
            </small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($records)): ?>
            <div class="empty-state"><i class="bi bi-inbox"></i><p>No records for selected criteria.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-nhis mb-0" style="font-size:0.82rem;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Record No.</th>
                            <th>NHIS No.</th>
                            <th>Full Name</th>
                            <th>Community</th>
                            <th>Facility</th>
                            <th>Reg. Date</th>
                            <th>GA (wks)</th>
                            <th>EDD</th>
                            <th>Preg. Status</th>
                            <th>Exemption</th>
                            <th>Ref No.</th>
                            <th class="no-print">Registered By</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $n = $pg['offset']+1; foreach ($records as $r): ?>
                    <tr>
                        <td class="text-muted small"><?= $n++ ?></td>
                        <td><span class="record-num"><?= htmlspecialchars($r['record_number']) ?></span></td>
                        <td>
                            <?php if ($r['nhis_membership_number']): ?>
                                <span style="font-family:monospace;"><?= htmlspecialchars($r['nhis_membership_number']) ?></span>
                            <?php elseif ($r['ghana_card_number']): ?>
                                <span style="font-family:monospace;font-size:.8rem;"><?= htmlspecialchars($r['ghana_card_number']) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['community']) ?></td>
                        <td><?= htmlspecialchars($r['facility']) ?></td>
                        <td><?= formatDate($r['registration_date']) ?></td>
                        <td><?= $r['gestational_age_weeks'] ?? '—' ?></td>
                        <td><?= formatDate($r['expected_delivery_date']) ?></td>
                        <td><?= pregnancyStatusBadge($r['pregnancy_status']) ?></td>
                        <td><?= exemptionStatusBadge($r['exemption_status']) ?></td>
                        <td><?= htmlspecialchars($r['reference_number'] ?: '—') ?></td>
                        <td class="no-print"><?= htmlspecialchars($r['registered_by'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light fw-600">
                            <td colspan="3">Total: <?= number_format($totalRows) ?> records</td>
                            <td colspan="10"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="px-3 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2 border-top">
                <small class="text-muted"><?= number_format($totalRows) ?> record(s)</small>
                <?= renderPagination($totalRows, $pg['per_page'], $pg['page'], $pgStr) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>

</div><!-- /.nhis-content -->
<?php include __DIR__ . '/../includes/footer.php'; ?>
