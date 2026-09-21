<?php
// ============================================================
// Search Records
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle = 'Search Records';
$pdo       = getDBConnection();
$results   = [];
$totalRows = 0;
$searched  = false;

$q            = trim($_GET['q']          ?? '');
$searchField  = trim($_GET['field']      ?? 'any');
$filterStatus = trim($_GET['status']     ?? '');
$filterFrom   = trim($_GET['date_from']  ?? '');
$filterTo     = trim($_GET['date_to']    ?? '');

$communities  = getCommunities();
$facilities   = getHealthFacilities();

if (!empty($q) || !empty($filterStatus) || !empty($filterFrom)) {
    $searched = true;

    $where      = ['pw.is_deleted = 0'];
    $allParams  = [];   // all positional values in WHERE clause order

    if (!empty($q)) {
        $like = '%' . $q . '%';
        switch ($searchField) {
            case 'nhis':
                $where[]    = 'pw.nhis_membership_number LIKE ?';
                $allParams[] = $like;
                break;
            case 'name':
                $where[]    = '(pw.first_name LIKE ? OR pw.last_name LIKE ? OR pw.middle_name LIKE ?)';
                $allParams  = array_merge($allParams, [$like, $like, $like]);
                break;
            case 'phone':
                $where[]    = '(pw.phone LIKE ? OR pw.alt_phone LIKE ?)';
                $allParams  = array_merge($allParams, [$like, $like]);
                break;
            case 'record_number':
                $where[]    = 'pw.record_number LIKE ?';
                $allParams[] = $like;
                break;
            case 'community':
                $where[]    = '(c.community_name LIKE ? OR pw.community_other LIKE ?)';
                $allParams  = array_merge($allParams, [$like, $like]);
                break;
            case 'reference':
                $where[]    = 'pw.reference_number LIKE ?';
                $allParams[] = $like;
                break;
            default: // 'any'
                $where[]   = '(pw.nhis_membership_number LIKE ? OR pw.ghana_card_number LIKE ?
                               OR pw.first_name LIKE ? OR pw.last_name LIKE ? OR pw.middle_name LIKE ?
                               OR pw.phone LIKE ? OR pw.record_number LIKE ?
                               OR c.community_name LIKE ? OR pw.reference_number LIKE ?)';
                $allParams = array_merge($allParams, array_fill(0, 9, $like));
        }
    }

    if ($filterStatus) {
        $where[]     = 'pw.pregnancy_status = ?';
        $allParams[] = $filterStatus;
    }
    if ($filterFrom && isValidDate($filterFrom)) {
        $where[]     = 'pw.registration_date >= ?';
        $allParams[] = $filterFrom;
    }
    if ($filterTo && isValidDate($filterTo)) {
        $where[]     = 'pw.registration_date <= ?';
        $allParams[] = $filterTo;
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    // --- Count ---
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM pregnant_women pw
         LEFT JOIN communities c ON pw.community_id = c.id $whereSQL"
    );
    $countStmt->execute($allParams);
    $totalRows = (int) $countStmt->fetchColumn();

    $pg    = getPaginationParams();
    $pgStr = http_build_query(array_filter([
        'q'         => $q,
        'field'     => $searchField,
        'status'    => $filterStatus,
        'date_from' => $filterFrom,
        'date_to'   => $filterTo,
    ]));

    // --- Data ---
    $sql = "SELECT pw.id, pw.record_number, pw.nhis_membership_number, pw.ghana_card_number,
                   pw.first_name, pw.last_name,
                   pw.phone, pw.registration_date, pw.pregnancy_status, pw.exemption_status,
                   COALESCE(c.community_name, pw.community_other, '—') AS community,
                   COALESCE(hf.facility_name, pw.facility_other, '—') AS facility
            FROM pregnant_women pw
            LEFT JOIN communities c        ON pw.community_id       = c.id
            LEFT JOIN health_facilities hf ON pw.health_facility_id = hf.id
            $whereSQL
            ORDER BY pw.registration_date DESC, pw.id DESC
            LIMIT ? OFFSET ?";

    $dataParams   = $allParams;
    $dataParams[] = $pg['per_page'];
    $dataParams[] = $pg['offset'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($dataParams);
    $results = $stmt->fetchAll();
} else {
    $pg    = getPaginationParams();
    $pgStr = '';
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">

    <div class="page-header">
        <h1><i class="bi bi-search me-2 text-nhis"></i>Search Records</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Search</li>
            </ol>
        </nav>
    </div>

    <?php renderFlash(); ?>

    <!-- Search form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <!-- Main search -->
                <div class="col-md-5">
                    <label class="form-label fw-500">Search Query</label>
                    <div class="search-box">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" class="form-control" name="q"
                               value="<?= htmlspecialchars($q) ?>"
                               placeholder="Enter name, NHIS number, phone, record number..." autofocus>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-500">Search In</label>
                    <select class="form-select" name="field">
                        <option value="any"           <?= $searchField==='any'           ?'selected':''?>>Any Field</option>
                        <option value="name"          <?= $searchField==='name'          ?'selected':''?>>Full Name</option>
                        <option value="nhis"          <?= $searchField==='nhis'          ?'selected':''?>>NHIS Membership No.</option>
                        <option value="phone"         <?= $searchField==='phone'         ?'selected':''?>>Phone Number</option>
                        <option value="record_number" <?= $searchField==='record_number' ?'selected':''?>>Record Number</option>
                        <option value="community"     <?= $searchField==='community'     ?'selected':''?>>Community</option>
                        <option value="reference"     <?= $searchField==='reference'     ?'selected':''?>>Reference Number</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-500">Preg. Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <?php foreach (['Active','Delivered','Lost to Follow-up','Referred','Deceased','Other'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':''?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-500">From</label>
                    <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($filterFrom) ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-500">To</label>
                    <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($filterTo) ?>">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-nhis-primary">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                    <a href="<?= BASE_URL ?>/records/search.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                    <?php if ($searched && $totalRows > 0): ?>
                    <a href="<?= BASE_URL ?>/reports/export.php?format=csv&<?= htmlspecialchars($pgStr) ?>"
                       class="btn btn-outline-success ms-auto">
                        <i class="bi bi-download me-1"></i>Export Results
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <?php if (!$searched): ?>
    <div class="empty-state" style="padding:4rem 1rem;">
        <i class="bi bi-search" style="color:#d1d5db;"></i>
        <p class="text-muted mt-2">Enter a search term and click <strong>Search</strong> to find records.</p>
        <div class="mt-3">
            <span class="badge bg-light text-dark border me-1">NHIS Membership Number</span>
            <span class="badge bg-light text-dark border me-1">Full Name</span>
            <span class="badge bg-light text-dark border me-1">Phone Number</span>
            <span class="badge bg-light text-dark border me-1">Record Number</span>
            <span class="badge bg-light text-dark border">Community</span>
        </div>
    </div>
    <?php elseif (empty($results)): ?>
    <div class="empty-state">
        <i class="bi bi-emoji-frown" style="color:#d1d5db;"></i>
        <p class="text-muted mt-2">No records found for <strong><?= htmlspecialchars($q) ?></strong>.</p>
        <a href="<?= BASE_URL ?>/pregnancy/add.php" class="btn btn-nhis-primary btn-sm mt-2">Register New Patient</a>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <i class="bi bi-list-ul me-2 text-nhis"></i>
                Search Results
                <span class="badge bg-secondary ms-1"><?= number_format($totalRows) ?></span>
            </span>
            <small class="text-muted">
                <?php if (!empty($q)): ?>
                Results for: <strong>"<?= htmlspecialchars($q) ?>"</strong>
                <?php endif; ?>
            </small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-nhis mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Record No.</th>
                            <th>NHIS / Ghana Card</th>
                            <th>Full Name</th>
                            <th>Phone</th>
                            <th>Community</th>
                            <th>Reg. Date</th>
                            <th>Status</th>
                            <th>Exemption</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $n = $pg['offset'] + 1; foreach ($results as $r): ?>
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
                        <td><?= htmlspecialchars($r['phone'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($r['community']) ?></td>
                        <td><?= formatDate($r['registration_date']) ?></td>
                        <td><?= pregnancyStatusBadge($r['pregnancy_status']) ?></td>
                        <td><?= exemptionStatusBadge($r['exemption_status']) ?></td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a href="<?= BASE_URL ?>/pregnancy/view.php?id=<?= $r['id'] ?>"
                               class="btn btn-sm btn-outline-primary btn-action"><i class="bi bi-eye"></i></a>
                            <a href="<?= BASE_URL ?>/pregnancy/edit.php?id=<?= $r['id'] ?>"
                               class="btn btn-sm btn-outline-secondary btn-action"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2 border-top">
                <small class="text-muted"><?= number_format($totalRows) ?> result(s)</small>
                <?= renderPagination($totalRows, $pg['per_page'], $pg['page'], $pgStr) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.nhis-content -->
<?php include __DIR__ . '/../includes/footer.php'; ?>
