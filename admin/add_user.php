<?php
// ============================================================
// Add New User
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pageTitle = 'Add New User';
$pdo       = getDBConnection();
$errors    = [];
$formData  = [];

$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();

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
            'password'  => $_POST['password'] ?? '',
            'password2' => $_POST['password2'] ?? '',
            'status'    => $_POST['status'] ?? 'active',
        ];

        if (empty($formData['full_name'])) $errors[] = 'Full name is required.';
        if (empty($formData['username']))  $errors[] = 'Username is required.';
        if (!preg_match('/^[a-z0-9._]+$/', $formData['username'])) $errors[] = 'Username may only contain lowercase letters, numbers, dots and underscores.';
        if (empty($formData['password']))  $errors[] = 'Password is required.';
        if (strlen($formData['password']) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($formData['password'] !== $formData['password2']) $errors[] = 'Passwords do not match.';
        if (!empty($formData['phone']) && !isValidGhanaPhone($formData['phone'])) $errors[] = 'Invalid phone number format.';

        // Username uniqueness
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $chk->execute([':u' => $formData['username']]);
        if ($chk->fetchColumn()) $errors[] = "Username '{$formData['username']}' is already taken.";

        if (empty($errors)) {
            $hash = password_hash($formData['password'], PASSWORD_DEFAULT);
            $ins  = $pdo->prepare(
                "INSERT INTO users (full_name, username, email, password, role_id, phone, status, must_change_password)
                 VALUES (:fn, :un, :em, :pw, :rid, :ph, :st, 1)"
            );
            $ins->execute([
                ':fn'  => $formData['full_name'],
                ':un'  => $formData['username'],
                ':em'  => $formData['email'] ?: null,
                ':pw'  => $hash,
                ':rid' => $formData['role_id'],
                ':ph'  => $formData['phone'] ?: null,
                ':st'  => $formData['status'],
            ]);
            $newId = $pdo->lastInsertId();
            logAudit($_SESSION['user_id'], 'CREATE_USER', 'Users', $newId,
                     "New user created: {$formData['username']} ({$formData['full_name']})");
            setFlash('success', "User '{$formData['full_name']}' created successfully.");
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">
    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h1><i class="bi bi-person-plus me-2 text-nhis"></i>Add New User</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/users.php">Users</a></li>
                    <li class="breadcrumb-item active">Add User</li>
                </ol>
            </nav>
        </div>
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please correct:</strong>
        <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="bi bi-person me-2 text-nhis"></i>New Staff Account</div>
                <div class="card-body">
                    <form method="POST" action="" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Full Name <span class="required-star">*</span></label>
                                <input type="text" class="form-control" name="full_name" required maxlength="150"
                                       value="<?= htmlspecialchars($formData['full_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username <span class="required-star">*</span></label>
                                <input type="text" class="form-control" name="username" required maxlength="80"
                                       value="<?= htmlspecialchars($formData['username'] ?? '') ?>"
                                       placeholder="e.g. kofi.mensah">
                                <div class="form-text">Lowercase letters, numbers, dots, underscores only.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" maxlength="150"
                                       value="<?= htmlspecialchars($formData['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" data-phone maxlength="15"
                                       value="<?= htmlspecialchars($formData['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role <span class="required-star">*</span></label>
                                <select class="form-select" name="role_id" required>
                                    <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>" <?= (($formData['role_id'] ?? 2) == $r['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['role_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="required-star">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password" id="newPw"
                                           required minlength="8" placeholder="Min. 8 characters">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('newPw','newPwIcon')">
                                        <i class="bi bi-eye" id="newPwIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password <span class="required-star">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="password2" id="newPw2"
                                           required minlength="8" placeholder="Repeat password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw('newPw2','newPw2Icon')">
                                        <i class="bi bi-eye" id="newPw2Icon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?= (($formData['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= (($formData['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            The new user will be required to change their password on first login.
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-nhis-primary px-4">
                                <i class="bi bi-person-check me-1"></i>Create Account
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
