<?php
// ============================================================
// Audit Logs
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pageTitle = 'Audit Logs';
$pdo       = getDBConnection();

// Filters
$filterUser   = (int)   ($_GET['user']    ?? 0);
$filterAction = trim(   $_GET['action']   ?? '');
$filterFrom   = trim(   $_GET['date_from']?? '');
$filterTo     = trim(   $_GET['date_to']  ?? '');

$where  = [];
$params = [];

if ($filterUser)   { $where[] = 'al.user_id = :uid';    $params[':uid']  = $filterUser; }
if ($filterAction) { $where[] = 'al.action = :action';  $params[':action'] = $filterAction; }
if ($filterFrom && isValidDate($filterFrom)) { $where[] = 'DATE(al.created_at) >= :dfrom'; $params[':dfrom'] = $filterFrom; }
if ($filterTo   && isValidDate($filterTo))   { $where[] = 'DATE(al.created_at) <= :dto';   $params[':dto']   = $filterTo; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al $whereSQL");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$pg    = getPaginationParams(30);
$pgStr = http_build_query(array_filter(['user'=>$filterUser?:'','action'=>$filterAction,'date_from'=>$filterFrom,'date_to'=>$filterTo]));

$sql = "SELECT al.*, u.full_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $whereSQL
        ORDER BY al.created_at DESC
        LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $pg['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pg['offset'],   PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

// Get distinct actions for filter dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$users   = getAllUsers();

// Action badge colours
$actionColors = [
    'LOGIN'          => 'success',
    'LOGOUT'         => 'secondary',
    'FAILED_LOGIN'   => 'danger',
    'CREATE_RECORD'  => 'primary',
    'UPDATE_RECORD'  => 'info',
    'DELETE_RECORD'  => 'danger',
    'CREATE_USER'    => 'primary',
    'UPDATE_USER'    => 'info',
    'ACTIVATE_USER'  => 'success',
    'DEACTIVATE_USER'=> 'warning',
    'REPORT'         => 'secondary',
    'SETTINGS'       => 'secondary',
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1><i class="bi bi-shield-check me-2 text-nhis"></i>Audit Logs</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Audit Logs</li>
                </ol>
            </nav>
        </div>
        <span class="badge bg-secondary fs-6"><?= number_format($totalRows) ?> entries</span>
    </div>

    <?php renderFlash(); ?>

    <!-- Filters -->
    <div class="filter-card mb-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1 small fw-500">User</label>
                <select class="form-select form-select-sm" name="user">
                    <option value="">All Users</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filterUser==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1 small fw-500">Action</label>
                <select class="form-select form-select-sm" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $a): ?>
                    <option value="<?= $a ?>" <?= $filterAction===$a?'selected':'' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-500">From</label>
                <input type="date" class="form-control form-control-sm" name="date_from" value="<?= htmlspecialchars($filterFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1 small fw-500">To</label>
                <input type="date" class="form-control form-control-sm" name="date_to" value="<?= htmlspecialchars($filterTo) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-nhis-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul me-2 text-nhis"></i>Activity Log</span>
            <small class="text-muted">Most recent first</small>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
            <div class="empty-state"><i class="bi bi-journal-x"></i><p>No log entries found.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-nhis mb-0" style="font-size:0.83rem;">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date &amp; Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Record ID</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $n = $pg['offset']+1; foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted small"><?= $n++ ?></td>
                        <td style="white-space:nowrap;"><?= formatDateTime($log['created_at']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'System') ?></strong>
                        </td>
                        <td>
                            <?php $color = $actionColors[$log['action']] ?? 'secondary'; ?>
                            <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($log['action']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($log['module'] ?: '—') ?></td>
                        <td>
                            <?php if ($log['record_id']): ?>
                            <a href="<?= BASE_URL ?>/pregnancy/view.php?id=<?= $log['record_id'] ?>" class="small">#<?= $log['record_id'] ?></a>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td style="max-width:300px;"><?= htmlspecialchars($log['description'] ?: '—') ?></td>
                        <td><code style="font-size:0.75rem;"><?= htmlspecialchars($log['ip_address'] ?: '—') ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-3 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2 border-top">
                <small class="text-muted">Total: <?= number_format($totalRows) ?> entries</small>
                <?= renderPagination($totalRows, $pg['per_page'], $pg['page'], $pgStr) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
