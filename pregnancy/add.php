<?php
// ============================================================
// New Pregnancy Registration
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pageTitle   = 'New Pregnancy Registration';
$pdo         = getDBConnection();
$communities = getCommunities();
$facilities  = getHealthFacilities();
$errors      = [];
$formData    = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token. Please refresh and try again.';
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
        elseif (!isValidGhanaPhone($formData['phone'])) $errors[] = 'Phone number must be format: 0XXXXXXXXX';
        if (!empty($formData['alt_phone']) && !isValidGhanaPhone($formData['alt_phone']))
            $errors[] = 'Alternative phone must be format: 0XXXXXXXXX';
        if (empty($formData['community_id']) && empty($formData['community_other']))
            $errors[] = 'Community is required.';
        if (empty($formData['health_facility_id']) && empty($formData['facility_other']))
            $errors[] = 'Health facility is required.';

        // ID validation + duplicate check
        if ($idType === 'nhis') {
            if (empty($formData['nhis_membership_number'])) {
                $errors[] = 'NHIS Membership Number is required.';
            } elseif (!preg_match('/^\d{8}$/', $formData['nhis_membership_number'])) {
                $errors[] = 'NHIS Number must be exactly 8 digits.';
            } else {
                $chk = $pdo->prepare("SELECT id, first_name, last_name FROM pregnant_women WHERE nhis_membership_number = :n AND is_deleted = 0 LIMIT 1");
                $chk->execute([':n' => $formData['nhis_membership_number']]);
                $dup = $chk->fetch();
                if ($dup) {
                    $errors[] = 'A record already exists for NHIS Number <strong>' . htmlspecialchars($formData['nhis_membership_number']) .
                        '</strong> — <strong>' . htmlspecialchars($dup['first_name'] . ' ' . $dup['last_name']) .
                        '</strong>. <a href="' . BASE_URL . '/pregnancy/view.php?id=' . $dup['id'] . '" target="_blank">View existing record &rarr;</a>';
                }
            }
        } else {
            if (empty($formData['ghana_card_number'])) {
                $errors[] = 'Ghana Card Number is required.';
            } elseif (!preg_match('/^GHA-\d{9}-\d$/', $formData['ghana_card_number'])) {
                $errors[] = 'Ghana Card must be format: GHA-XXXXXXXXX-X';
            } else {
                $chk = $pdo->prepare("SELECT id, first_name, last_name FROM pregnant_women WHERE ghana_card_number = :n AND is_deleted = 0 LIMIT 1");
                $chk->execute([':n' => $formData['ghana_card_number']]);
                $dup = $chk->fetch();
                if ($dup) {
                    $errors[] = 'A record already exists for Ghana Card <strong>' . htmlspecialchars($formData['ghana_card_number']) .
                        '</strong> — <strong>' . htmlspecialchars($dup['first_name'] . ' ' . $dup['last_name']) .
                        '</strong>. <a href="' . BASE_URL . '/pregnancy/view.php?id=' . $dup['id'] . '" target="_blank">View existing record &rarr;</a>';
                }
            }
        }

        if (empty($errors)) {
            $recordNumber = generateRecordNumber();

            $stmt = $pdo->prepare(
                "INSERT INTO pregnant_women (
                    record_number,
                    nhis_membership_number, ghana_card_number,
                    first_name, middle_name, last_name,
                    sex, date_of_birth,
                    phone, alt_phone,
                    community_id, community_other, district, region,
                    health_facility_id, facility_other,
                    registration_date, created_by
                ) VALUES (
                    :rn,
                    :nhis, :gcard,
                    :fn, :mn, :ln,
                    'Female', :dob,
                    :ph, :aph,
                    :cid, :coth, :dist, :reg,
                    :fid, :foth,
                    :regdate, :createdby
                )"
            );
            $stmt->execute([
                ':rn'        => $recordNumber,
                ':nhis'      => ($idType === 'nhis' && $formData['nhis_membership_number']) ? $formData['nhis_membership_number'] : null,
                ':gcard'     => ($idType === 'ghana' && $formData['ghana_card_number'])     ? $formData['ghana_card_number']     : null,
                ':fn'        => $formData['first_name'],
                ':mn'        => $formData['middle_name'] ?: null,
                ':ln'        => $formData['last_name'],
                ':dob'       => $formData['date_of_birth'],
                ':ph'        => $formData['phone'],
                ':aph'       => $formData['alt_phone'] ?: null,
                ':cid'       => $formData['community_id'] !== '' ? (int)$formData['community_id'] : null,
                ':coth'      => $formData['community_other'] ?: null,
                ':dist'      => 'Twifo Atti-Morkwa',
                ':reg'       => 'Central Region',
                ':fid'       => $formData['health_facility_id'] !== '' ? (int)$formData['health_facility_id'] : null,
                ':foth'      => $formData['facility_other'] ?: null,
                ':regdate'   => date('Y-m-d'),
                ':createdby' => $_SESSION['user_id'],
            ]);

            $newId    = $pdo->lastInsertId();
            $fullName = trim($formData['first_name'] . ' ' . $formData['middle_name'] . ' ' . $formData['last_name']);
            logAudit($_SESSION['user_id'], 'CREATE_RECORD', 'Pregnancy', $newId,
                "New registration: {$recordNumber} – {$fullName}");

            setFlash('success', "Registration saved. Record Number: <strong>{$recordNumber}</strong>");
            header('Location: ' . BASE_URL . '/pregnancy/view.php?id=' . $newId);
            exit;
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (empty($formData)) {
    $formData = ['id_type' => 'nhis'];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="nhis-content">
    <div class="page-header">
        <h1><i class="bi bi-plus-circle me-2 text-nhis"></i>New Pregnancy Registration</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/records/index.php">Records</a></li>
                <li class="breadcrumb-item active">New Registration</li>
            </ol>
        </nav>
    </div>

    <?php renderFlash(); ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please correct the following:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <!-- ===== PATIENT IDENTIFICATION ===== -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-credit-card-2-front me-2 text-nhis"></i>Patient Identification
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-12">
                        <label class="form-label fw-500">ID Type <span class="required-star">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="id_type" id="idTypeNhis"
                                       value="nhis" <?= ($formData['id_type'] ?? 'nhis') === 'nhis' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-500" for="idTypeNhis">
                                    <i class="bi bi-card-text me-1 text-nhis"></i>NHIS Number
                                    <span class="text-muted fw-400">(8 digits)</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="id_type" id="idTypeGhana"
                                       value="ghana" <?= ($formData['id_type'] ?? '') === 'ghana' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-500" for="idTypeGhana">
                                    <i class="bi bi-person-vcard me-1 text-nhis"></i>Ghana Card
                                    <span class="text-muted fw-400">(GHA-XXXXXXXXX-X)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- NHIS Number -->
                    <div class="col-md-5" id="nhisField">
                        <label class="form-label">NHIS Membership Number <span class="required-star">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-nhis-light text-nhis fw-600"
                                  style="width:44px;justify-content:center;">
                                <i class="bi bi-hash"></i>
                            </span>
                            <input type="text" class="form-control form-control-lg"
                                   id="nhis_membership_number" name="nhis_membership_number"
                                   value="<?= htmlspecialchars($formData['nhis_membership_number'] ?? '') ?>"
                                   placeholder="12345678" maxlength="8" inputmode="numeric"
                                   style="font-size:1.3rem;letter-spacing:.2em;font-family:monospace;font-weight:700;">
                        </div>
                        <div class="form-text">Exactly 8 digits — numbers only.</div>
                        <div id="duplicateWarning" class="duplicate-alert mt-2 d-none">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                            <strong>Existing record:</strong> <span id="dupName"></span>.
                            <a href="#" id="dupRecordLink" target="_blank" class="ms-1">View &rarr;</a>
                        </div>
                    </div>

                    <!-- Ghana Card -->
                    <div class="col-md-5" id="ghanaField" style="display:none;">
                        <label class="form-label">Ghana Card Number <span class="required-star">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-nhis-light text-nhis fw-600"
                                  style="min-width:50px;justify-content:center;">GHA</span>
                            <input type="text" class="form-control form-control-lg"
                                   id="ghana_card_number" name="ghana_card_number"
                                   value="<?= htmlspecialchars($formData['ghana_card_number'] ?? '') ?>"
                                   placeholder="GHA-123456789-1" maxlength="20"
                                   style="font-size:1.1rem;letter-spacing:.08em;font-family:monospace;font-weight:700;">
                        </div>
                        <div class="form-text">Format: GHA-XXXXXXXXX-X</div>
                        <div id="duplicateWarningGhana" class="duplicate-alert mt-2 d-none">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                            <strong>Existing record:</strong> <span id="dupNameGhana"></span>.
                            <a href="#" id="dupRecordLinkGhana" target="_blank" class="ms-1">View &rarr;</a>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ===== PERSONAL INFORMATION ===== -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person me-2 text-nhis"></i>Personal Information
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">First Name <span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="first_name" required maxlength="80"
                               value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>"
                               placeholder="e.g. Akosua">
                        <div class="invalid-feedback">First name is required.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Middle Name <span class="text-muted small">(optional)</span></label>
                        <input type="text" class="form-control" name="middle_name" maxlength="80"
                               value="<?= htmlspecialchars($formData['middle_name'] ?? '') ?>"
                               placeholder="e.g. Ama">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Last Name <span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="last_name" required maxlength="80"
                               value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>"
                               placeholder="e.g. Amponsah">
                        <div class="invalid-feedback">Last name is required.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Date of Birth <span class="required-star">*</span></label>
                        <!-- Visible dd/mm/yyyy input -->
                        <input type="text" class="form-control" id="dob_display"
                               placeholder="dd/mm/yyyy" maxlength="10" inputmode="numeric"
                               value="<?= !empty($formData['date_of_birth']) ? date('d/m/Y', strtotime($formData['date_of_birth'])) : '' ?>"
                               autocomplete="off">
                        <!-- Hidden YYYY-MM-DD value sent to server -->
                        <input type="hidden" name="date_of_birth" id="dob_field"
                               value="<?= htmlspecialchars($formData['date_of_birth'] ?? '') ?>">
                        <div class="form-text">Format: dd/mm/yyyy &nbsp; e.g. 15/04/1998</div>
                        <div class="invalid-feedback" id="dob_error" style="display:none;">
                            Please enter a valid date of birth (dd/mm/yyyy).
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Age</label>
                        <input type="text" class="form-control bg-light" id="age_display"
                               placeholder="Auto" readonly tabindex="-1">
                        <div class="form-text">From DOB.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Phone Number <span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="phone" data-phone required
                               value="<?= htmlspecialchars($formData['phone'] ?? '') ?>"
                               placeholder="0244123456" maxlength="15">
                        <div class="invalid-feedback">Phone number is required.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Alternative Phone <span class="text-muted small">(optional)</span></label>
                        <input type="text" class="form-control" name="alt_phone" data-phone
                               value="<?= htmlspecialchars($formData['alt_phone'] ?? '') ?>"
                               placeholder="0201234567" maxlength="15">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Community <span class="required-star">*</span></label>
                        <select class="form-select" name="community_id" id="communitySelect" required>
                            <option value="">— Select Community —</option>
                            <?php foreach ($communities as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                <?= (($formData['community_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['community_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a community.</div>
                    </div>

                    <div class="col-md-6" id="communityOtherRow" style="display:none;">
                        <label class="form-label">Community <span class="text-muted small">(if not listed)</span></label>
                        <input type="text" class="form-control" name="community_other" maxlength="150"
                               value="<?= htmlspecialchars($formData['community_other'] ?? '') ?>"
                               placeholder="Specify community name">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Health Facility <span class="required-star">*</span></label>
                        <select class="form-select" name="health_facility_id" id="facilitySelect" required>
                            <option value="">— Select Health Facility —</option>
                            <?php foreach ($facilities as $f): ?>
                            <option value="<?= $f['id'] ?>"
                                <?= (($formData['health_facility_id'] ?? '') == $f['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['facility_name']) ?>
                                <?= $f['facility_type'] ? ' (' . htmlspecialchars($f['facility_type']) . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a health facility.</div>
                    </div>

                    <div class="col-md-6" id="facilityOtherRow" style="display:none;">
                        <label class="form-label">Facility <span class="text-muted small">(if not listed)</span></label>
                        <input type="text" class="form-control" name="facility_other" maxlength="200"
                               value="<?= htmlspecialchars($formData['facility_other'] ?? '') ?>"
                               placeholder="Specify facility name">
                    </div>

                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="d-flex gap-2 justify-content-end mb-4">
            <a href="<?= BASE_URL ?>/records/index.php" class="btn btn-secondary">
                <i class="bi bi-x-circle me-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-nhis-primary px-4">
                <i class="bi bi-save me-1"></i>Save Registration
            </button>
        </div>

    </form>
</div>

<script>
var BASE_URL = '<?= BASE_URL ?>';

document.addEventListener('DOMContentLoaded', function () {

    // ID type toggle
    function toggleIdFields() {
        const isNhis = document.getElementById('idTypeNhis').checked;
        document.getElementById('nhisField').style.display  = isNhis ? '' : 'none';
        document.getElementById('ghanaField').style.display = isNhis ? 'none' : '';
        document.getElementById('nhis_membership_number').required = isNhis;
        document.getElementById('ghana_card_number').required      = !isNhis;
    }
    document.getElementById('idTypeNhis').addEventListener('change',  toggleIdFields);
    document.getElementById('idTypeGhana').addEventListener('change', toggleIdFields);
    toggleIdFields();

    // NHIS — digits only
    document.getElementById('nhis_membership_number').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 8);
    });

    // Ghana Card — auto-format
    document.getElementById('ghana_card_number').addEventListener('input', function () {
        let v = this.value.replace(/[^A-Z0-9]/gi, '').toUpperCase();
        if (v.startsWith('GHA')) v = v.slice(3);
        v = v.replace(/\D/g, '').slice(0, 10);
        this.value = 'GHA-' + (v.length <= 9 ? v : v.slice(0, 9) + '-' + v.slice(9));
    });

    // DOB: dd/mm/yyyy text input → hidden YYYY-MM-DD + age calc
    const dobDisplay = document.getElementById('dob_display');
    const dobHidden  = document.getElementById('dob_field');
    const ageDisplay = document.getElementById('age_display');
    const dobError   = document.getElementById('dob_error');

    dobDisplay.addEventListener('input', function () {
        // Auto-insert slashes as user types
        let v = this.value.replace(/\D/g, '');
        if (v.length > 8) v = v.slice(0, 8);
        if (v.length >= 5)      this.value = v.slice(0,2) + '/' + v.slice(2,4) + '/' + v.slice(4);
        else if (v.length >= 3) this.value = v.slice(0,2) + '/' + v.slice(2);
        else                    this.value = v;
        syncDOB();
    });

    dobDisplay.addEventListener('blur', function () {
        syncDOB();
        // Show error border if invalid and non-empty
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
            // Validate date components
            if (d.getFullYear() === yy && d.getMonth() === mm && d.getDate() === dd && yy >= 1900 && d <= new Date()) {
                dobHidden.value = yy + '-' + String(mm + 1).padStart(2,'0') + '-' + String(dd).padStart(2,'0');
                dobDisplay.classList.remove('is-invalid');
                dobError.style.display = 'none';
                calcAge(d);
                return;
            }
        }
        dobHidden.value  = '';
        ageDisplay.value = '';
    }

    function calcAge(dob) {
        const now = new Date();
        let age   = now.getFullYear() - dob.getFullYear();
        const m   = now.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < dob.getDate())) age--;
        ageDisplay.value = age >= 0 ? age + ' yrs' : '';
    }

    // Trigger on load if value pre-filled (after error repopulation)
    if (dobDisplay.value) dobDisplay.dispatchEvent(new Event('input'));

    // Prevent submit if DOB display filled but hidden is empty
    document.querySelector('form').addEventListener('submit', function (e) {
        const display = document.getElementById('dob_display');
        const hidden  = document.getElementById('dob_field');
        if (display.value.trim() && !hidden.value) {
            e.preventDefault();
            display.classList.add('is-invalid');
            document.getElementById('dob_error').style.display = 'block';
            display.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, true);  // capture phase so it runs before Bootstrap's validation

    // Community "Other"
    document.getElementById('communitySelect').addEventListener('change', function () {
        const txt = this.options[this.selectedIndex]?.text || '';
        document.getElementById('communityOtherRow').style.display =
            (txt.toLowerCase().includes('other') || this.value === '') ? '' : 'none';
    });

    // Facility "Other"
    document.getElementById('facilitySelect').addEventListener('change', function () {
        const txt = this.options[this.selectedIndex]?.text || '';
        document.getElementById('facilityOtherRow').style.display =
            (txt.toLowerCase().includes('other') || this.value === '') ? '' : 'none';
    });

    // NHIS duplicate check
    let nhisTimer;
    document.getElementById('nhis_membership_number').addEventListener('input', function () {
        clearTimeout(nhisTimer);
        const val = this.value.trim();
        document.getElementById('duplicateWarning').classList.add('d-none');
        if (val.length !== 8) return;
        nhisTimer = setTimeout(function () {
            fetch(BASE_URL + '/pregnancy/check_duplicate.php?nhis=' + encodeURIComponent(val) + '&field=nhis')
                .then(r => r.json())
                .then(d => {
                    if (d.found) {
                        document.getElementById('duplicateWarning').classList.remove('d-none');
                        document.getElementById('dupName').textContent = d.full_name;
                        document.getElementById('dupRecordLink').href  = BASE_URL + '/pregnancy/view.php?id=' + d.id;
                    }
                }).catch(() => {});
        }, 500);
    });

    // Ghana Card duplicate check
    let gcTimer;
    document.getElementById('ghana_card_number').addEventListener('input', function () {
        clearTimeout(gcTimer);
        const val = this.value.trim();
        document.getElementById('duplicateWarningGhana').classList.add('d-none');
        if (!/^GHA-\d{9}-\d$/.test(val)) return;
        gcTimer = setTimeout(function () {
            fetch(BASE_URL + '/pregnancy/check_duplicate.php?ghana=' + encodeURIComponent(val) + '&field=ghana')
                .then(r => r.json())
                .then(d => {
                    if (d.found) {
                        document.getElementById('duplicateWarningGhana').classList.remove('d-none');
                        document.getElementById('dupNameGhana').textContent    = d.full_name;
                        document.getElementById('dupRecordLinkGhana').href     = BASE_URL + '/pregnancy/view.php?id=' + d.id;
                    }
                }).catch(() => {});
        }, 500);
    });

});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
