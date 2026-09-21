<?php
// ============================================================
// User Profile & Password Change
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle    = 'My Profile';
$pdo          = getDBConnection();
$forceChange  = isset($_GET['force_change']);
$errors       = [];
$profileErrors = [];

$stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=:id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $profileErrors[] = 'Invalid form token.';
    } elseif ($_POST['action'] === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if (empty($fullName)) $profileErrors[] = 'Full name is required.';
        if (!empty($phone) && !isValidGhanaPhone($phone)) $profileErrors[] = 'Invalid phone number format.';

        if (empty($profileErrors)) {
            $upd = $pdo->prepare("UPDATE users SET full_name=:fn, email=:em, phone=:ph WHERE id=:id");
            $upd->execute([':fn'=>$fullName,':em'=>$email?:null,':ph'=>$phone?:null,':id'=>$_SESSION['user_id']]);
            $_SESSION['full_name'] = $fullName;
            logAudit($_SESSION['user_id'], 'UPDATE_PROFILE', 'Profile', $_SESSION['user_id'], 'Profile updated');
            setFlash('success', 'Profile updated successfully.');
            header('Location: ' . BASE_URL . '/profile.php');
            exit;
        }
    } elseif ($_POST['action'] === 'change_password') {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password']     ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPw, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($newPw) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($newPw !== $confirmPw)      $errors[] = 'New passwords do not match.';
        if ($newPw === $currentPw)      $errors[] = 'New password must be different from the current password.';

        if (empty($errors)) {
            $hash = password_hash($newPw, PASSWORD_DEFAULT);
            $upd  = $pdo->prepare("UPDATE users SET password=:pw, must_change_password=0 WHERE id=:id");
            $upd->execute([':pw' => $hash, ':id' => $_SESSION['user_id']]);
            logAudit($_SESSION['user_id'], 'CHANGE_PASSWORD', 'Profile', $_SESSION['user_id'], 'Password changed');
            setFlash('success', 'Password changed successfully.');
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Count records created by this user
$recCount = (int) $pdo->prepare("SELECT COUNT(*) FROM pregnant_women WHERE created_by=:id AND is_deleted=0")
                      ->execute([':id'=>$_SESSION['user_id']]) ? $pdo->query("SELECT COUNT(*) FROM pregnant_women WHERE created_by={$_SESSION['user_id']} AND is_deleted=0")->fetchColumn() : 0;

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="nhis-content">

    <div class="page-header">
        <h1><i class="bi bi-person-circle me-2 text-nhis"></i>My Profile</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Profile</li>
            </ol>
        </nav>
    </div>

    <?php renderFlash(); ?>

    <?php if ($forceChange): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-shield-exclamation fs-5"></i>
        <strong>Action Required:</strong> You must change your password before using the system.
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Profile info card -->
        <div class="col-lg-4">
            <div class="card text-center">
                <div class="card-body py-4">
                    <div class="nhis-avatar mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <h5 class="fw-700 mb-0"><?= htmlspecialchars($user['full_name']) ?></h5>
                    <p class="text-muted mb-2"><?= htmlspecialchars($user['username']) ?></p>
                    <span class="badge <?= $user['role_id']==1 ? 'bg-danger' : 'bg-primary' ?> mb-3">
                        <?= htmlspecialchars($user['role_name']) ?>
                    </span>
                    <hr>
                    <div class="row text-center">
                        <div class="col">
                            <div class="fw-700 fs-4 text-nhis"><?= $recCount ?></div>
                            <div class="text-muted small">Records Created</div>
                        </div>
                        <div class="col">
                            <div class="fw-700 fs-4 text-nhis"><?= $user['status']==='active' ? '<span class="text-success">✓</span>' : '<span class="text-danger">✗</span>' ?></div>
                            <div class="text-muted small">Account Status</div>
                        </div>
                    </div>
                    <?php if ($user['last_login']): ?>
                    <div class="mt-3 text-muted small">Last login: <?= formatDateTime($user['last_login']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Edit profile -->
            <?php if (!$forceChange): ?>
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-person me-2 text-nhis"></i>Edit Profile</div>
                <div class="card-body">
                    <?php if (!empty($profileErrors)): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($profileErrors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Full Name <span class="required-star">*</span></label>
                                <input type="text" class="form-control" name="full_name" required maxlength="150"
                                       value="<?= htmlspecialchars($user['full_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <div class="form-text">Username cannot be changed.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" maxlength="150"
                                       value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" data-phone maxlength="15"
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-nhis-primary mt-3">
                            <i class="bi bi-save me-1"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Change password -->
            <div class="card">
                <div class="card-header"><i class="bi bi-key me-2 text-nhis"></i>
                    <?= $forceChange ? 'Set New Password (Required)' : 'Change Password' ?>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Current Password <span class="required-star">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="current_password" id="curPw" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('curPw','curPwI')"><i class="bi bi-eye" id="curPwI"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Password <span class="required-star">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="new_password" id="newPw" required minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('newPw','newPwI')"><i class="bi bi-eye" id="newPwI"></i></button>
                                </div>
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password <span class="required-star">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="confirm_password" id="confPw" required minlength="8">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('confPw','confPwI')"><i class="bi bi-eye" id="confPwI"></i></button>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-nhis-primary mt-3">
                            <i class="bi bi-shield-lock me-1"></i>
                            <?= $forceChange ? 'Set Password & Continue' : 'Change Password' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePw(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ic  = document.getElementById(iconId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ic.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
