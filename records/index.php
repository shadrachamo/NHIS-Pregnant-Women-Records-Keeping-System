<?php
// ============================================================
// All Records – paginated, filterable list
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'All Records';
$pdo         = getDBConnection();
$communities = getCommunities();
$facilities  = getHealthFacilities();
$staffList   = getAllUsers(true);

// --- Filters from GET ---
$filterStatus     = trim($_GET['status']      ?? '');
$filterExemption  = trim($_GET['exemption']   ?? '');
$filterCommunity  = (int) ($_GET['community'] ?? 0);
$filterFacility   = (int) ($_GET['facility']  ?? 0);
$filterStaff      = (int) ($_GET['staff']     ?? 0);
$filterDateFrom   = trim($_GET['date_from']   ?? '');
$filterDateTo     = trim($_GET['date_to']     ?? '');

// Build WHERE clause
$where  = ['pw.is_deleted = 0'];
$params = [];

if ($filterStatus)    { $where[] = 'pw.pregnancy_status = :pstat';  $params[':pstat']  = $filterStatus; }
if ($filterExemption) { $where[] = 'pw.exemption_status = :estat';  $params[':estat']  = $filterExemption; }
if ($filterCommunity) { $where[] = 'pw.community_id = :cid';        $params[':cid']    = $filterCommunity; }
if ($filterFacility)  { $where[] = 'pw.health_facility_id = :fid';  $params[':fid']    = $filterFacility; }
if ($filterStaff)     { $where[] = 'pw.created_by = :staff';        $params[':staff']  = $filterStaff; }
if ($filterDateFrom && isValidDate($filterDateFrom)) {
    $where[] = 'pw.registration_date >= :dfrom'; $params[':dfrom'] = $filterDateFrom;
}
if ($filterDateTo && isValidDate($filterDateTo)) {
    $where[] = 'pw.registration_date <= :dto';   $params[':dto']   = $filterDateTo;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM pregnant_women pw $whereSQL");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$pg     = getPaginationParams();
$pgStr  = http_build_query(array_filter([
    'status' => $filterStatus, 'exemption' => $filterExemption,
    'community' => $filterCommunity ?: '', 'facility' => $filterFacility ?: '',
    'staff' => $filterStaff ?: '', 'date_from' => $filterDateFrom, 'date_to' => $filterDateTo,
]));

// Fetch page
$sql = "SELECT pw.id, pw.record_number, pw.nhis_membership_number, pw.ghana_card_number,
               pw.first_name, pw.last_name,
               pw.phone, pw.registration_date, pw.pregnancy_status, pw.exemption_status,
               pw.processing_date,
               COALESCE(c.community_name,  pw.community_other,  '—') AS community,
               COALESCE(hf.facility_name,  pw.facility_other,   '—') AS facility,
               u.full_name AS created_by_name
        FROM pregnant_women pw
        LEFT JOIN communities      c  ON pw.community_id       = c.id
        LEFT JOIN health_facilities hf ON pw.health_facility_id = hf.id
        LEFT JOIN users             u  ON pw.created_by         = u.id
        $whereSQL
        ORDER BY pw.registration_date DESC, pw.id DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $pg['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pg['offset'],   PDO::PARAM_INT);
$stmt->execute();
$records = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1><i class="bi bi-table me-2 text-nhis"></i>All Records</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">All Records</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/pregnancy/add.php" class="btn btn-nhis-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>New Registration
            </a>
            <a href="<?= BASE_URL ?>/reports/export.php?format=csv&<?= htmlspecialchars($pgStr) ?>"
               class="btn btn-outline-success btn-sm">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>
        </div>
    </div>

    <?php renderFlash(); ?>

    <!-- ===== FILTERS ===== -->
    <div class="filter-card mb-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-500">Preg. Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (['Active','Delivered','Lost to Follow-up','Referred','Deceased','Other'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-500">Exemption</label>
                <select class="form-select form-select-sm" name="exemption">
                    <option value="">All</option>
                    <?php foreach (['Approved','Pending','Under Review','Rejected'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filterExemption === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-500">Community</label>
                <select class="form-select form-select-sm" name="community">
                    <option value="">All</option>
                    <?php foreach ($communities as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterCommunity == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['community_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-500">Facility</label>
                <select class="form-select form-select-sm" name="facility">
                    <option value="">All</option>
                    <?php foreach ($facilities as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= $filterFacility == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['facility_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small fw-500">Date From</label>
                <input type="date" class="form-control form-control-sm" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label mb-1 small fw-500">Date To</label>
                <input type="date" class="form-control form-control-sm" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-nhis-primary btn-sm flex-fill">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="<?= BASE_URL ?>/records/index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Records table -->
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span>
                <i class="bi bi-list-ul me-2 text-nhis"></i>
                Records
                <span class="badge bg-secondary ms-1"><?= number_format($totalRows) ?></span>
            </span>
            <span class="text-muted small">
                Showing <?= number_format(min($pg['offset'] + 1, $totalRows)) ?>–<?= number_format(min($pg['offset'] + $pg['per_page'], $totalRows)) ?>
                of <?= number_format($totalRows) ?>
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($records)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>No records found<?= $pgStr ? ' matching the current filters' : '' ?>.</p>
                <a href="<?= BASE_URL ?>/pregnancy/add.php" class="btn btn-nhis-primary btn-sm">Add First Record</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-nhis mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Record No.</th>
                            <th>NHIS / Ghana Card</th>
                            <th>Full Name</th>
                            <th>Community</th>
                            <th>Reg. Date</th>
                            <th>Preg. Status</th>
                            <th>Exemption</th>
                            <th>Registered By</th>
                            <th class="text-center no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $n = $pg['offset'] + 1; foreach ($records as $r): ?>
                    <tr>
                        <td class="text-muted small"><?= $n++ ?></td>
                        <td><span class="record-num"><?= htmlspecialchars($r['record_number']) ?></span></td>
                        <td>
                            <?php if ($r['nhis_membership_number']): ?>
                                <span class="badge bg-nhis-light text-nhis" style="font-family:monospace;"><?= htmlspecialchars($r['nhis_membership_number']) ?></span>
                            <?php elseif ($r['ghana_card_number']): ?>
                                <span class="badge bg-light text-dark border" style="font-family:monospace;font-size:.7rem;"><?= htmlspecialchars($r['ghana_card_number']) ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['community']) ?></td>
                        <td><?= formatDate($r['registration_date']) ?></td>
                        <td><?= pregnancyStatusBadge($r['pregnancy_status']) ?></td>
                        <td><?= exemptionStatusBadge($r['exemption_status']) ?></td>
                        <td><?= htmlspecialchars($r['created_by_name'] ?? '—') ?></td>
                        <td class="text-center no-print" style="white-space:nowrap;">
                            <a href="<?= BASE_URL ?>/pregnancy/view.php?id=<?= $r['id'] ?>"
                               class="btn btn-sm btn-outline-primary btn-action" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/pregnancy/edit.php?id=<?= $r['id'] ?>"
                               class="btn btn-sm btn-outline-secondary btn-action" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if (isAdmin()): ?>
                            <a href="<?= BASE_URL ?>/pregnancy/delete.php?id=<?= $r['id'] ?>"
                               class="btn btn-sm btn-outline-danger btn-action" title="Delete"
                               data-confirm="Delete record <?= htmlspecialchars($r['record_number']) ?>? This cannot be undone.">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 border-top">
                <small class="text-muted">Total: <?= number_format($totalRows) ?> record(s)</small>
                <?= renderPagination($totalRows, $pg['per_page'], $pg['page'], $pgStr) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /.nhis-content -->
<?php include __DIR__ . '/../includes/footer.php'; ?>
