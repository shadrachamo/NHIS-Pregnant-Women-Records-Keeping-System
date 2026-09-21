<?php
// ============================================================
// Edit Pregnancy Record
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$id  = (int) ($_GET['id'] ?? 0);
$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT * FROM pregnant_women WHERE id = :id AND is_deleted = 0");
$stmt->execute([':id' => $id]);
$record = $stmt->fetch();

if (!$record) {
    setFlash('danger', 'Record not found.');
    header('Location: ' . BASE_URL . '/records/index.php');
    exit;
}

$pageTitle   = 'Edit: ' . $record['record_number'];
$communities = getCommunities();
$facilities  = getHealthFacilities();
$errors      = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token.';
    } else {
        $idType   = trim($_POST['id_type'] ?? 'nhis');
        $formData = [
            'id_type'                => $idType,
            'nhis_membership_number' => preg_replace('/\D/', '', trim($_POST['nhis_membership_number'] ?? '')),
            'ghana_card_number'      => strtoupper(trim($_POST['ghana_card_number'] ?? '')),
            'first_name'             => trim($_POST['first_name']  ?? ''),
            'middle_name'            => trim($_POST['middle_name'] ?? ''),
            'last_name'              => trim($_POST['last_name']   ?? ''),
            'date_of_birth'          => normaliseDateToDb(trim($_POST['date_of_birth'] ?? '')),
            'phone'                  => trim($_POST['phone']      ?? ''),
            'alt_phone'              => trim($_POST['alt_phone']  ?? ''),
            'community_id'           => trim($_POST['community_id']    ?? ''),
            'community_other'        => trim($_POST['community_other'] ?? ''),
            'health_facility_id'     => trim($_POST['health_facility_id'] ?? ''),
            'facility_other'         => trim($_POST['facility_other'] ?? ''),
        ];

        // Validation
        if (empty($formData['first_name']))    $errors[] = 'First name is required.';
        if (empty($formData['last_name']))     $errors[] = 'Last name is required.';
        if (empty($formData['date_of_birth'])) $errors[] = 'Date of birth is required.';
        elseif (!isValidDate($formData['date_of_birth'])) $errors[] = 'Date of birth is not valid.';
        if (empty($formData['phone']))         $errors[] = 'Phone number is required.';
        elseif (!isValidGhanaPhone($formData['phone'])) $errors[] = 'Phone must be format: 0XXXXXXXXX';
        if (!empty($formData['alt_phone']) && !isValidGhanaPhone($formData['alt_phone']))
            $errors[] = 'Alternative phone must be format: 0XXXXXXXXX';

        // ID validation + duplicate check (exclude self)
        if ($idType === 'nhis') {
            if (empty($formData['nhis_membership_number'])) {
                $errors[] = 'NHIS Number is required.';
            } elseif (!preg_match('/^\d{8}$/', $formData['nhis_membership_number'])) {
                $errors[] = 'NHIS Number must be exactly 8 digits.';
            } else {
                $chk = $pdo->prepare("SELECT id, first_name, last_name FROM pregnant_women WHERE nhis_membership_number = :n AND is_deleted = 0 AND id != :eid LIMIT 1");
                $chk->execute([':n' => $formData['nhis_membership_number'], ':eid' => $id]);
                $dup = $chk->fetch();
                if ($dup) $errors[] = 'NHIS Number already used by: <strong>' . htmlspecialchars($dup['first_name'] . ' ' . $dup['last_name']) . '</strong>.';
            }
        } else {
            if (empty($formData['ghana_card_number'])) {
                $errors[] = 'Ghana Card Number is required.';
            } elseif (!preg_match('/^GHA-\d{9}-\d$/', $formData['ghana_card_number'])) {
                $errors[] = 'Ghana Card must be format: GHA-XXXXXXXXX-X';
            } else {
                $chk = $pdo->prepare("SELECT id, first_name, last_name FROM pregnant_women WHERE ghana_card_number = :n AND is_deleted = 0 AND id != :eid LIMIT 1");
                $chk->execute([':n' => $formData['ghana_card_number'], ':eid' => $id]);
                $dup = $chk->fetch();
                if ($dup) $errors[] = 'Ghana Card already used by: <strong>' . htmlspecialchars($dup['first_name'] . ' ' . $dup['last_name']) . '</strong>.';
            }
        }

        if (empty($errors)) {
            $upd = $pdo->prepare(
                "UPDATE pregnant_women SET
                    nhis_membership_number = :nhis,
                    ghana_card_number      = :gcard,
                    first_name             = :fn,
                    middle_name            = :mn,
                    last_name              = :ln,
                    date_of_birth          = :dob,
                    phone                  = :ph,
                    alt_phone              = :aph,
                    community_id           = :cid,
                    community_other        = :coth,
                    health_facility_id     = :fid,
                    facility_other         = :foth,
                    updated_by             = :updby
                 WHERE id = :id"
            );
            $upd->execute([
                ':nhis'  => ($idType === 'nhis'  && $formData['nhis_membership_number']) ? $formData['nhis_membership_number'] : null,
                ':gcard' => ($idType === 'ghana' && $formData['ghana_card_number'])      ? $formData['ghana_card_number']      : null,
                ':fn'    => $formData['first_name'],
                ':mn'    => $formData['middle_name'] ?: null,
                ':ln'    => $formData['last_name'],
                ':dob'   => $formData['date_of_birth'],
                ':ph'    => $formData['phone'],
                ':aph'   => $formData['alt_phone'] ?: null,
                ':cid'   => $formData['community_id'] !== '' ? (int)$formData['community_id'] : null,
                ':coth'  => $formData['community_other'] ?: null,
                ':fid'   => $formData['health_facility_id'] !== '' ? (int)$formData['health_facility_id'] : null,
                ':foth'  => $formData['facility_other'] ?: null,
                ':updby' => $_SESSION['user_id'],
                ':id'    => $id,
            ]);

            logAudit($_SESSION['user_id'], 'UPDATE_RECORD', 'Pregnancy', $id,
                "Record updated: {$record['record_number']} – {$formData['first_name']} {$formData['last_name']}");
            setFlash('success', 'Record updated successfully.');
            header('Location: ' . BASE_URL . '/pregnancy/view.php?id=' . $id);
            exit;
        }
        $record = array_merge($record, $formData);
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">
    <div class="page-header d-flex align-items-center justify-content-between">
        <div>
            <h1><i class="bi bi-pencil-square me-2 text-nhis"></i>Edit Record</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/records/index.php">Records</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pregnancy/view.php?id=<?= $id ?>"><?= htmlspecialchars($record['record_number']) ?></a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="<?= BASE_URL ?>/pregnancy/view.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>

    <?php renderFlash(); ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please correct:</strong>
        <ul class="mb-0 mt-2"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="alert alert-info d-flex align-items-center gap-2 py-2 no-print">
        <i class="bi bi-info-circle-fill"></i>
        <span>Editing: <strong><?= htmlspecialchars($record['record_number']) ?></strong></span>
    </div>

    <form method="POST" action="" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <!-- ===== PATIENT IDENTIFICATION ===== -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-credit-card-2-front me-2 text-nhis"></i>Patient Identification</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-500">ID Type</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="id_type" id="editIdTypeNhis"
                                       value="nhis" <?= empty($record['ghana_card_number']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="editIdTypeNhis">
                                    NHIS Number <span class="text-muted">(8 digits)</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="id_type" id="editIdTypeGhana"
                                       value="ghana" <?= !empty($record['ghana_card_number']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="editIdTypeGhana">
                                    Ghana Card <span class="text-muted">(GHA-XXXXXXXXX-X)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5" id="editNhisField">
                        <label class="form-label">NHIS Membership Number</label>
                        <input type="text" class="form-control form-control-lg" id="nhis_membership_number"
                               name="nhis_membership_number"
                               value="<?= htmlspecialchars($record['nhis_membership_number'] ?? '') ?>"
                               maxlength="8" placeholder="12345678" inputmode="numeric"
                               style="font-family:monospace;font-weight:700;letter-spacing:.2em;">
                        <div class="form-text">8 digits only.</div>
                    </div>
                    <div class="col-md-5" id="editGhanaField">
                        <label class="form-label">Ghana Card Number</label>
                        <input type="text" class="form-control form-control-lg" id="ghana_card_number"
                               name="ghana_card_number"
                               value="<?= htmlspecialchars($record['ghana_card_number'] ?? '') ?>"
                               maxlength="20" placeholder="GHA-123456789-1"
                               style="font-family:monospace;font-weight:700;letter-spacing:.08em;">
                        <div class="form-text">Format: GHA-XXXXXXXXX-X</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== PERSONAL INFORMATION ===== -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person me-2 text-nhis"></i>Personal Information</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">First Name <span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="first_name" required maxlength="80"
                               value="<?= htmlspecialchars($record['first_name'] ?? '') ?>">
                        <div class="invalid-feedback">First name is required.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Middle Name <span class="text-muted small">(optional)</span></label>
                        <input type="text" class="form-control" name="middle_name" maxlength="80"
                               value="<?= htmlspecialchars($record['middle_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last Name <span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="last_name" required maxlength="80"
                               value="<?= htmlspecialchars($record['last_name'] ?? '') ?>">
                        <div class="invalid-feedback">Last name is required.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date of Birth <span class="required-star">*</span></label>
                        <input type="text" class="form-control" id="dob_display"
                               placeholder="dd/mm/yyyy" maxlength="10" inputmode="numeric"
                               value="<?= ($record['date_of_birth'] && $record['date_of_birth'] !== '0000-00-00') ? date('d/m/Y', strtotime($record['date_of_birth'])) : '' ?>"
                               autocomplete="off">
                        <input type="hidden" name="date_of_birth" id="dob_field"
                               value="<?= htmlspecialchars($record['date_of_birth'] ?? '') ?>">
                        <div class="form-text">Format: dd/mm/yyyy &nbsp; e.g. 15/04/1998</div>
                        <div class="invalid-feedback" id="dob_error" style="display:none;">
                            Please enter a valid date (dd/mm/yyyy).
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Age</label>
                        <input type="text" class="form-control bg-light" id="age_display"
                               placeholder="Auto" readonly tabindex="-1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phone <span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="phone" data-phone required maxlength="15"
                               value="<?= htmlspecialchars($record['phone'] ?? '') ?>"
                               placeholder="0244123456">
                        <div class="invalid-feedback">Phone number is required.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Alternative Phone</label>
                        <input type="text" class="form-control" name="alt_phone" data-phone maxlength="15"
                               value="<?= htmlspecialchars($record['alt_phone'] ?? '') ?>"
                               placeholder="0201234567">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Community</label>
                        <select class="form-select" name="community_id" id="communitySelect">
                            <option value="">— Select Community —</option>
                            <?php foreach ($communities as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($record['community_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['community_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" id="communityOtherRow"
                         style="<?= empty($record['community_id']) ? '' : 'display:none;' ?>">
                        <label class="form-label">Community <span class="text-muted small">(if not listed)</span></label>
                        <input type="text" class="form-control" name="community_other" maxlength="150"
                               value="<?= htmlspecialchars($record['community_other'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Health Facility</label>
                        <select class="form-select" name="health_facility_id" id="facilitySelect">
                            <option value="">— Select Health Facility —</option>
                            <?php foreach ($facilities as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= ($record['health_facility_id'] == $f['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['facility_name']) ?>
                                <?= $f['facility_type'] ? ' (' . htmlspecialchars($f['facility_type']) . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6" id="facilityOtherRow"
                         style="<?= empty($record['health_facility_id']) ? '' : 'display:none;' ?>">
                        <label class="form-label">Facility <span class="text-muted small">(if not listed)</span></label>
                        <input type="text" class="form-control" name="facility_other" maxlength="200"
                               value="<?= htmlspecialchars($record['facility_other'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mb-4">
            <a href="<?= BASE_URL ?>/pregnancy/view.php?id=<?= $id ?>" class="btn btn-secondary">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-nhis-primary px-4">
                <i class="bi bi-save me-1"></i>Update Record
            </button>
        </div>
    </form>
</div>

<script>
var BASE_URL = '<?= BASE_URL ?>';
document.addEventListener('DOMContentLoaded', function () {

    // ID type toggle
    function toggleIdFields() {
        const isNhis = document.getElementById('editIdTypeNhis').checked;
        document.getElementById('editNhisField').style.display  = isNhis ? '' : 'none';
        document.getElementById('editGhanaField').style.display = isNhis ? 'none' : '';
        document.getElementById('nhis_membership_number').required = isNhis;
        document.getElementById('ghana_card_number').required      = !isNhis;
    }
    document.getElementById('editIdTypeNhis').addEventListener('change',  toggleIdFields);
    document.getElementById('editIdTypeGhana').addEventListener('change', toggleIdFields);
    toggleIdFields();

    // NHIS digits only
    document.getElementById('nhis_membership_number').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 8);
    });

    // Ghana Card auto-format
    document.getElementById('ghana_card_number').addEventListener('input', function () {
        let v = this.value.replace(/[^A-Z0-9]/gi, '').toUpperCase();
        if (v.startsWith('GHA')) v = v.slice(3);
        v = v.replace(/\D/g, '').slice(0, 10);
        this.value = 'GHA-' + (v.length <= 9 ? v : v.slice(0, 9) + '-' + v.slice(9));
    });

    // DOB: dd/mm/yyyy text input → hidden YYYY-MM-DD + age
    const dobDisplay = document.getElementById('dob_display');
    const dobHidden  = document.getElementById('dob_field');
    const ageDisplay = document.getElementById('age_display');
    const dobError   = document.getElementById('dob_error');

    dobDisplay.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '');
        if (v.length > 8) v = v.slice(0, 8);
        if (v.length >= 5)      this.value = v.slice(0,2) + '/' + v.slice(2,4) + '/' + v.slice(4);
        else if (v.length >= 3) this.value = v.slice(0,2) + '/' + v.slice(2);
        else                    this.value = v;
        syncDOB();
    });

    dobDisplay.addEventListener('blur', function () {
        syncDOB();
        if (this.value && !dobHidden.value) {
            this.classList.add('is-invalid');
            dobError.style.display = 'block';
        } else {
            this.classList.remove('is-invalid');
            dobError.style.display = 'none';
        }
    });

    function syncDOB() {
        const raw = dobDisplay.value.trim();
        const m   = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (m) {
            const dd = parseInt(m[1], 10);
            const mm = parseInt(m[2], 10) - 1;
            const yy = parseInt(m[3], 10);
            const d  = new Date(yy, mm, dd);
            if (d.getFullYear() === yy && d.getMonth() === mm && d.getDate() === dd && yy >= 1900 && d <= new Date()) {
                dobHidden.value = yy + '-' + String(mm+1).padStart(2,'0') + '-' + String(dd).padStart(2,'0');
                dobDisplay.classList.remove('is-invalid');
                dobError.style.display = 'none';
                const now = new Date();
                let age   = now.getFullYear() - d.getFullYear();
                const mo  = now.getMonth() - d.getMonth();
                if (mo < 0 || (mo === 0 && now.getDate() < d.getDate())) age--;
                ageDisplay.value = age >= 0 ? age + ' yrs' : '';
                return;
            }
        }
        dobHidden.value  = '';
        ageDisplay.value = '';
    }

    // Pre-fill age if DOB already set
    if (dobDisplay.value) dobDisplay.dispatchEvent(new Event('input'));

    // Block submit if DOB display filled but hidden empty
    document.querySelector('form').addEventListener('submit', function (e) {
        if (dobDisplay.value.trim() && !dobHidden.value) {
            e.preventDefault();
            dobDisplay.classList.add('is-invalid');
            dobError.style.display = 'block';
            dobDisplay.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, true);

    // Community other
    document.getElementById('communitySelect').addEventListener('change', function () {
        const txt = this.options[this.selectedIndex]?.text || '';
        document.getElementById('communityOtherRow').style.display =
            (txt.toLowerCase().includes('other') || this.value === '') ? '' : 'none';
    });

    // Facility other
    document.getElementById('facilitySelect').addEventListener('change', function () {
        const txt = this.options[this.selectedIndex]?.text || '';
        document.getElementById('facilityOtherRow').style.display =
            (txt.toLowerCase().includes('other') || this.value === '') ? '' : 'none';
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
