<?php
// ============================================================
// Dashboard
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Dashboard';
$stats     = getDashboardStats();

// Recent 8 registrations
$pdo    = getDBConnection();
$recent = $pdo->query(
    "SELECT pw.id, pw.record_number, pw.first_name, pw.last_name, pw.registration_date,
            pw.pregnancy_status, pw.exemption_status,
            COALESCE(c.community_name, pw.community_other,'—') AS community,
            u.full_name AS registered_by
     FROM pregnant_women pw
     LEFT JOIN communities c ON pw.community_id = c.id
     LEFT JOIN users u ON pw.created_by = u.id
     WHERE pw.is_deleted = 0
     ORDER BY pw.created_at DESC LIMIT 8"
)->fetchAll();

// Build chart data JSON
$monthLabels  = array_column($stats['monthly_trend'], 'lbl');
$monthTotals  = array_column($stats['monthly_trend'], 'total');
$commLabels   = array_column($stats['by_community'], 'community');
$commTotals   = array_column($stats['by_community'], 'total');
$facLabels    = array_column($stats['by_facility'],  'facility');
$facTotals    = array_column($stats['by_facility'],  'total');
$staffLabels  = array_column($stats['by_staff'],     'staff');
$staffTotals  = array_column($stats['by_staff'],     'total');

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="nhis-content">

    <!-- Page header -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1><i class="bi bi-speedometer2 me-2 text-nhis"></i>Dashboard</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/pregnancy/add.php" class="btn btn-nhis-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i>New Registration
            </a>
            <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-bar-chart-line me-1"></i>Reports
            </a>
        </div>
    </div>

    <?php renderFlash(); ?>

    <!-- ===== STAT CARDS ROW 1 ===== -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card stat-green h-100">
                <div class="stat-label">Total Records</div>
                <div class="stat-value"><?= number_format($stats['total']) ?></div>
                <div class="stat-sub">All-time registrations</div>
                <i class="bi bi-journal-medical stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-blue h-100">
                <div class="stat-label">This Year</div>
                <div class="stat-value"><?= number_format($stats['this_year']) ?></div>
                <div class="stat-sub"><?= date('Y') ?></div>
                <i class="bi bi-calendar3 stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-teal h-100">
                <div class="stat-label">This Month</div>
                <div class="stat-value"><?= number_format($stats['this_month']) ?></div>
                <div class="stat-sub"><?= date('F Y') ?></div>
                <i class="bi bi-calendar-month stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-gold h-100">
                <div class="stat-label">Today</div>
                <div class="stat-value"><?= number_format($stats['today']) ?></div>
                <div class="stat-sub"><?= date('d M Y') ?></div>
                <i class="bi bi-calendar-day stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- ===== STAT CARDS ROW 2 ===== -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card stat-sky h-100">
                <div class="stat-label">Active Pregnancies</div>
                <div class="stat-value"><?= number_format($stats['active']) ?></div>
                <div class="stat-sub">Currently active</div>
                <i class="bi bi-heart-pulse stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-purple h-100">
                <div class="stat-label">Delivered</div>
                <div class="stat-value"><?= number_format($stats['delivered']) ?></div>
                <div class="stat-sub">Completed pregnancies</div>
                <i class="bi bi-check2-circle stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-green h-100">
                <div class="stat-label">Exemptions Approved</div>
                <div class="stat-value"><?= number_format($stats['approved']) ?></div>
                <div class="stat-sub">NHIS approved</div>
                <i class="bi bi-shield-check stat-icon"></i>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-rose h-100">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= number_format($stats['pending']) ?></div>
                <div class="stat-sub">Awaiting processing</div>
                <i class="bi bi-hourglass-split stat-icon"></i>
            </div>
        </div>
    </div>

    <!-- ===== CHARTS ROW 1 ===== -->
    <div class="row g-3 mb-4">
        <!-- Monthly Registration Trend -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-graph-up me-2 text-nhis"></i>Monthly Registration Trend (Last 12 Months)</span>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- Exemption Status Distribution -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-pie-chart me-2 text-nhis"></i>Exemption Status
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div class="chart-wrapper w-100">
                        <canvas id="statusDonutChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== CHARTS ROW 2 ===== -->
    <div class="row g-3 mb-4">
        <!-- By Community -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-geo-alt me-2 text-nhis"></i>Registrations by Community
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="communityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- By Health Facility -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-hospital me-2 text-nhis"></i>Registrations by Health Facility
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="facilityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== BOTTOM ROW: Staff Stats + Recent Records ===== -->
    <div class="row g-3">
        <!-- Staff Processing Stats -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-person-lines-fill me-2 text-nhis"></i>Records by Staff Member
                </div>
                <div class="card-body p-0">
                    <?php if (empty($stats['by_staff'])): ?>
                    <div class="empty-state py-4"><i class="bi bi-people"></i><p>No data</p></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-nhis mb-0">
                            <thead><tr><th>Staff Member</th><th class="text-end">Records</th></tr></thead>
                            <tbody>
                            <?php foreach ($stats['by_staff'] as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['staff']) ?></td>
                                <td class="text-end">
                                    <span class="badge bg-nhis-light text-nhis fw-600"><?= $row['total'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Registrations -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-clock-history me-2 text-nhis"></i>Recent Registrations</span>
                    <a href="<?= BASE_URL ?>/records/index.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent)): ?>
                    <div class="empty-state"><i class="bi bi-inbox"></i><p>No records yet</p></div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-nhis mb-0">
                            <thead>
                                <tr>
                                    <th>Record No.</th>
                                    <th>Full Name</th>
                                    <th>Community</th>
                                    <th>Reg. Date</th>
                                    <th>Preg. Status</th>
                                    <th>Exemption</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><span class="record-num"><?= htmlspecialchars($r['record_number']) ?></span></td>
                                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                <td><?= htmlspecialchars($r['community']) ?></td>
                                <td><?= formatDate($r['registration_date']) ?></td>
                                <td><?= pregnancyStatusBadge($r['pregnancy_status']) ?></td>
                                <td><?= exemptionStatusBadge($r['exemption_status']) ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/pregnancy/view.php?id=<?= $r['id'] ?>"
                                       class="btn btn-sm btn-outline-secondary btn-action"
                                       title="View Record">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.nhis-content -->

<?php
// Pass chart data to JS
$extraJs = "
const monthLabels  = " . json_encode($monthLabels)  . ";
const monthTotals  = " . json_encode(array_map('intval', $monthTotals))  . ";
const commLabels   = " . json_encode($commLabels)   . ";
const commTotals   = " . json_encode(array_map('intval', $commTotals))   . ";
const facLabels    = " . json_encode($facLabels)    . ";
const facTotals    = " . json_encode(array_map('intval', $facTotals))    . ";
const staffLabels  = " . json_encode($staffLabels)  . ";
const staffTotals  = " . json_encode(array_map('intval', $staffTotals))  . ";
const statusData   = [" . $stats['approved'] . ", " . $stats['pending'] . ", " . ($stats['total'] - $stats['approved'] - $stats['pending']) . "];

document.addEventListener('DOMContentLoaded', function() {

    // Monthly trend line chart
    nhisChart(
        document.getElementById('monthlyTrendChart').getContext('2d'),
        'line', monthLabels,
        [{
            label: 'Registrations',
            data: monthTotals,
            borderColor: '#1a7a4c',
            backgroundColor: 'rgba(26,122,76,0.12)',
            borderWidth: 2.5,
            pointBackgroundColor: '#1a7a4c',
            pointRadius: 4,
            tension: 0.35,
            fill: true
        }],
        { scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: { family:'Inter', size:10 } }, grid: { color:'#f0f4f8' } },
                    x: { ticks: { font: { family:'Inter', size:10 } }, grid: { display: false } } } }
    );

    // Status donut chart
    nhisChart(
        document.getElementById('statusDonutChart').getContext('2d'),
        'doughnut',
        ['Approved', 'Pending', 'Other'],
        [{
            data: statusData,
            backgroundColor: ['#1a7a4c','#d97706','#9ca3af'],
            borderWidth: 2,
            borderColor: '#fff'
        }],
        { cutout: '65%', plugins: { legend: { position: 'bottom' } } }
    );

    // Community bar chart
    nhisChart(
        document.getElementById('communityChart').getContext('2d'),
        'bar', commLabels,
        [{
            label: 'Registrations',
            data: commTotals,
            backgroundColor: NHIS_COLORS,
            borderRadius: 5,
            borderSkipped: false
        }],
        { indexAxis: 'y',
          scales: { x: { beginAtZero: true, ticks: { stepSize:1, font:{family:'Inter',size:10} }, grid:{color:'#f0f4f8'} },
                    y: { ticks: { font:{family:'Inter',size:10} }, grid:{display:false} } },
          plugins: { legend: { display: false } } }
    );

    // Facility bar chart
    nhisChart(
        document.getElementById('facilityChart').getContext('2d'),
        'bar', facLabels,
        [{
            label: 'Registrations',
            data: facTotals,
            backgroundColor: NHIS_COLORS.slice(2),
            borderRadius: 5,
            borderSkipped: false
        }],
        { indexAxis: 'y',
          scales: { x: { beginAtZero: true, ticks: { stepSize:1, font:{family:'Inter',size:10} }, grid:{color:'#f0f4f8'} },
                    y: { ticks: { font:{family:'Inter',size:10} }, grid:{display:false} } },
          plugins: { legend: { display: false } } }
    );
});
";

include __DIR__ . '/includes/footer.php';
?>
