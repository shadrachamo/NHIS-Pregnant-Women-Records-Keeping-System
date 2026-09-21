<?php
// ============================================================
// View Pregnancy Record
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$id  = (int) ($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare(
    "SELECT pw.*,
            c.community_name,
            hf.facility_name, hf.facility_type,
            u1.full_name AS created_by_name,
            u2.full_name AS updated_by_name
     FROM pregnant_women pw
     LEFT JOIN communities       c  ON pw.community_id       = c.id
     LEFT JOIN health_facilities hf ON pw.health_facility_id = hf.id
     LEFT JOIN users             u1 ON pw.created_by         = u1.id
     LEFT JOIN users             u2 ON pw.updated_by         = u2.id
     WHERE pw.id = :id AND pw.is_deleted = 0"
);
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();

if (!$record) {
    setFlash('danger', 'Record not found or has been deleted.');
    header('Location: ' . BASE_URL . '/records/index.php');
    exit;
}

$pageTitle = 'Record: ' . $record['record_number'];
$community = $record['community_name'] ?: ($record['community_other'] ?: '—');
$facility  = $record['facility_name']  ?: ($record['facility_other']  ?: '—');
$fullName  = trim($record['first_name'] . ' ' . ($record['middle_name'] ? $record['middle_name'] . ' ' : '') . $record['last_name']);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1><i class="bi bi-person-lines-fill me-2 text-nhis"></i>Pregnancy Record</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/records/index.php">Records</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($record['record_number']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2 flex-wrap no-print">
            <button class="btn btn-outline-secondary btn-sm" data-print>
                <i class="bi bi-printer me-1"></i>Print
            </button>
            <a href="<?= BASE_URL ?>/pregnancy/edit.php?id=<?= $record['id'] ?>" class="btn btn-nhis-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
            <?php if (isAdmin()): ?>
            <a href="<?= BASE_URL ?>/pregnancy/delete.php?id=<?= $record['id'] ?>"
               class="btn btn-danger btn-sm"
               data-confirm="Delete this record? This cannot be undone.">
                <i class="bi bi-trash me-1"></i>Delete
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php renderFlash(); ?>

    <!-- Print header -->
    <div class="print-header mb-4">
        <h4 class="fw-700">NHIS Twifo Praso District Office</h4>
        <h5>Pregnancy Exemption Registration Record</h5>
        <hr>
    </div>

    <!-- Record number banner -->
    <div class="d-flex align-items-center justify-content-between mb-4 p-3 rounded-3 bg-nhis-light border border-2 border-nhis">
        <div>
            <div class="text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;">Record Number</div>
            <div class="fw-700 fs-5 text-nhis"><?= htmlspecialchars($record['record_number']) ?></div>
        </div>
        <div class="text-end">
            <div class="fw-600"><?= htmlspecialchars($fullName) ?></div>
            <div class="text-muted small"><?= formatDate($record['registration_date']) ?></div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Personal Information -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-person me-2 text-nhis"></i>Personal Information
                </div>
                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-6">
                            <div class="detail-label">NHIS Membership No.</div>
                            <div class="detail-value" style="font-family:monospace;">
                                <?= htmlspecialchars($record['nhis_membership_number'] ?: '—') ?>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detail-label">Ghana Card Number</div>
                            <div class="detail-value" style="font-family:monospace;">
                                <?= htmlspecialchars($record['ghana_card_number'] ?: '—') ?>
                            </div>
                        </div>

                        <div class="col-4">
                            <div class="detail-label">First Name</div>
                            <div class="detail-value"><?= htmlspecialchars($record['first_name']) ?></div>
                        </div>
                        <div class="col-4">
                            <div class="detail-label">Middle Name</div>
                            <div class="detail-value"><?= htmlspecialchars($record['middle_name'] ?: '—') ?></div>
                        </div>
                        <div class="col-4">
                            <div class="detail-label">Last Name</div>
                            <div class="detail-value"><?= htmlspecialchars($record['last_name']) ?></div>
                        </div>

                        <div class="col-4">
                            <div class="detail-label">Date of Birth</div>
                            <div class="detail-value"><?= formatDate($record['date_of_birth']) ?></div>
                        </div>
                        <div class="col-4">
                            <div class="detail-label">Age</div>
                            <div class="detail-value"><?= calculateAge($record['date_of_birth']) ?></div>
                        </div>
                        <div class="col-4">
                            <div class="detail-label">Sex</div>
                            <div class="detail-value">Female</div>
                        </div>

                        <div class="col-6">
                            <div class="detail-label">Phone Number</div>
                            <div class="detail-value"><?= htmlspecialchars($record['phone'] ?: '—') ?></div>
                        </div>
                        <div class="col-6">
                            <div class="detail-label">Alternative Phone</div>
                            <div class="detail-value"><?= htmlspecialchars($record['alt_phone'] ?: '—') ?></div>
                        </div>

                        <div class="col-6">
                            <div class="detail-label">Community</div>
                            <div class="detail-value"><?= htmlspecialchars($community) ?></div>
                        </div>
                        <div class="col-6">
                            <div class="detail-label">Health Facility</div>
                            <div class="detail-value"><?= htmlspecialchars($facility) ?></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Record Metadata -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2 text-nhis"></i>Record Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="detail-label">Record Number</div>
                            <div class="detail-value text-nhis fw-700"><?= htmlspecialchars($record['record_number']) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="detail-label">Registration Date</div>
                            <div class="detail-value"><?= formatDate($record['registration_date']) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="detail-label">Registered By</div>
                            <div class="detail-value"><?= htmlspecialchars($record['created_by_name'] ?: '—') ?></div>
                        </div>
                        <div class="col-12">
                            <div class="detail-label">Date Created</div>
                            <div class="detail-value"><?= formatDateTime($record['created_at']) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="detail-label">Last Updated</div>
                            <div class="detail-value"><?= formatDateTime($record['updated_at']) ?></div>
                        </div>
                        <?php if ($record['updated_by_name']): ?>
                        <div class="col-12">
                            <div class="detail-label">Updated By</div>
                            <div class="detail-value"><?= htmlspecialchars($record['updated_by_name']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="d-flex gap-2 mt-4 no-print">
        <a href="<?= BASE_URL ?>/records/index.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Records
        </a>
        <a href="<?= BASE_URL ?>/pregnancy/edit.php?id=<?= $record['id'] ?>" class="btn btn-nhis-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit Record
        </a>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
