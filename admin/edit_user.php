<?php
// ============================================================
// Edit User
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$id  = (int) ($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'User not found.');
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

$pageTitle = 'Edit User: ' . $user['username'];
$roles     = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$errors    = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token.';
    } else {
        $formData = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'username'  => strtolower(trim($_POST['username'] ?? '')),
            'email'     => trim($_POST['email'] ?? ''),
            'phone'     => trim($_POST['phone'] ?? ''),
            'role_id'   => (int) ($_POST['role_id'] ?? 2),
            'status'    => $_POST['status'] ?? 'active',
            'password'  => $_POST['password'] ?? '',
            'password2' => $_POST['password2'] ?? '',
        ];

        if (empty($formData['full_name'])) $errors[] = 'Full name is required.';
        if (empty($formData['username']))  $errors[] = 'Username is required.';
        if (!preg_match('/^[a-z0-9._]+$/', $formData['username'])) $errors[] = 'Invalid username format.';
        if (!empty($formData['phone']) && !isValidGhanaPhone($formData['phone'])) $errors[] = 'Invalid phone number.';

        // Check username uniqueness (exclude self)
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1");
        $chk->execute([':u' => $formData['username'], ':id' => $id]);
        if ($chk->fetchColumn()) $errors[] = "Username '{$formData['username']}' is already taken.";

        // Password change (optional)
        if (!empty($formData['password'])) {
            if (strlen($formData['password']) < 8) $errors[] = 'Password must be at least 8 characters.';
            if ($formData['password'] !== $formData['password2']) $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            if (!empty($formData['password'])) {
                $hash = password_hash($formData['password'], PASSWORD_DEFAULT);
                $upd  = $pdo->prepare(
                    "UPDATE users SET full_name=:fn, username=:un, email=:em, phone=:ph,
                     role_id=:rid, status=:st, password=:pw, must_change_password=0 WHERE id=:id"
                );
                $upd->execute([':pw' => $hash, ':fn'=>$formData['full_name'],':un'=>$formData['username'],
                    ':em'=>$formData['email']?:null,':ph'=>$formData['phone']?:null,
                    ':rid'=>$formData['role_id'],':st'=>$formData['status'],':id'=>$id]);
            } else {
                $upd = $pdo->prepare(
                    "UPDATE users SET full_name=:fn, username=:un, email=:em, phone=:ph,
                     role_id=:rid, status=:st WHERE id=:id"
                );
                $upd->execute([':fn'=>$formData['full_name'],':un'=>$formData['username'],
                    ':em'=>$formData['email']?:null,':ph'=>$formData['phone']?:null,
                    ':rid'=>$formData['role_id'],':st'=>$formData['status'],':id'=>$id]);
            }

            logAudit($_SESSION['user_id'], 'UPDATE_USER', 'Users', $id,
                     "User account updated: {$formData['username']}");
            setFlash('success', "User '{$formData['full_name']}' updated successfully.");
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        }
        $user = array_merge($user, $formData);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">
    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h1><i class="bi bi-person-gear me-2 text-nhis"></i>Edit User</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/users.php">Users</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Please correct:</strong>
        <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="bi bi-person me-2 text-nhis"></i>Edit Account: <?= htmlspecialchars($user['username']) ?></div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Full Name <span class="required-star">*</span></label>
                                <input type="text" class="form-control" name="full_name" required maxlength="150"
                                       value="<?= htmlspecialchars($user['full_name']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="required-star">*</span></label>
                                <input type="text" class="form-control" name="username" required maxlength="80"
                                       value="<?= htmlspecialchars($user['username']) ?>">
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
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role_id">
                                    <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= ($user['role_id'] == $r['id']) ? 'selected':'' ?>>
                                        <?= htmlspecialchars($r['role_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" <?= ($id == $_SESSION['user_id']) ? 'disabled' : '' ?>>
                                    <option value="active"   <?= $user['status']==='active'   ? 'selected':'' ?>>Active</option>
                                    <option value="inactive" <?= $user['status']==='inactive' ? 'selected':'' ?>>Inactive</option>
                                </select>
                                <?php if ($id == $_SESSION['user_id']): ?>
                                <input type="hidden" name="status" value="active">
                                <div class="form-text text-warning">You cannot deactivate your own account.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <hr class="my-3">
                        <p class="fw-500 small mb-2">Change Password <span class="text-muted fw-400">(leave blank to keep current)</span></p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="ePw"
                                           placeholder="Min. 8 characters">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('ePw','ePwIcon')">
                                        <i class="bi bi-eye" id="ePwIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password2" id="ePw2"
                                           placeholder="Repeat new password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('ePw2','ePw2Icon')">
                                        <i class="bi bi-eye" id="ePw2Icon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-nhis-primary px-4">
                                <i class="bi bi-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function togglePw(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
