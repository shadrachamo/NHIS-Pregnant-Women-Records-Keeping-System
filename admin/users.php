<?php
// ============================================================
// User Management
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pageTitle = 'User Management';
$pdo       = getDBConnection();

$users = $pdo->query(
    "SELECT u.*, r.role_name,
            (SELECT COUNT(*) FROM pregnant_women pw WHERE pw.created_by = u.id AND pw.is_deleted = 0) AS record_count,
            u.last_login
     FROM users u
     JOIN roles r ON u.role_id = r.id
     ORDER BY u.full_name"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">

    <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1><i class="bi bi-people me-2 text-nhis"></i>User Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Users</li>
                </ol>
            </nav>
        </div>
        <a href="<?= BASE_URL ?>/admin/add_user.php" class="btn btn-nhis-primary btn-sm">
            <i class="bi bi-person-plus me-1"></i>Add New User
        </a>
    </div>

    <?php renderFlash(); ?>

    <div class="card">
        <div class="card-header">
            <i class="bi bi-people me-2 text-nhis"></i>System Users
            <span class="badge bg-secondary ms-1"><?= count($users) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-nhis mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone</th>
                            <th>Records</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $n=1; foreach ($users as $u): ?>
                    <tr>
                        <td class="text-muted small"><?= $n++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                            <span class="badge bg-info ms-1" style="font-size:.65rem;">You</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                        <td><?= htmlspecialchars($u['email'] ?: '—') ?></td>
                        <td>
                            <span class="badge <?= $u['role_id'] == 1 ? 'bg-danger' : 'bg-primary' ?>">
                                <?= htmlspecialchars($u['role_name']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($u['phone'] ?: '—') ?></td>
                        <td>
                            <span class="badge bg-nhis-light text-nhis fw-600"><?= $u['record_count'] ?></span>
                        </td>
                        <td><?= $u['last_login'] ? formatDateTime($u['last_login']) : '<span class="text-muted small">Never</span>' ?></td>
                        <td>
                            <?php if ($u['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center" style="white-space:nowrap;">
                            <a href="<?= BASE_URL ?>/admin/edit_user.php?id=<?= $u['id'] ?>"
                               class="btn btn-sm btn-outline-secondary btn-action" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="<?= BASE_URL ?>/admin/toggle_user.php?id=<?= $u['id'] ?>&action=<?= $u['status']==='active' ? 'deactivate':'activate' ?>"
                               class="btn btn-sm <?= $u['status']==='active' ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-action"
                               title="<?= $u['status']==='active' ? 'Deactivate':'Activate' ?>"
                               data-confirm="<?= $u['status']==='active' ? 'Deactivate' : 'Activate' ?> user <?= htmlspecialchars($u['full_name']) ?>?">
                                <i class="bi bi-<?= $u['status']==='active' ? 'person-x' : 'person-check' ?>"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
