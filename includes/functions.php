<?php
// ============================================================
// Core Helper Functions
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================

require_once __DIR__ . '/../config/database.php';

// ------------------------------------------------------------------
// RECORD NUMBER GENERATION
// ------------------------------------------------------------------
/**
 * Generate the next unique record number in format NHIS-TP-YYYY-NNNNN
 */
function generateRecordNumber(): string {
    $pdo  = getDBConnection();
    $year = date('Y');
    $stmt = $pdo->prepare(
        "SELECT record_number FROM pregnant_women
         WHERE record_number LIKE :pattern
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':pattern' => "NHIS-TP-{$year}-%"]);
    $last = $stmt->fetchColumn();
    if ($last) {
        $parts  = explode('-', $last);
        $seq    = (int) end($parts);
        $next   = $seq + 1;
    } else {
        $next = 1;
    }
    return 'NHIS-TP-' . $year . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

// ------------------------------------------------------------------
// AUDIT LOGGING
// ------------------------------------------------------------------
/**
 * Write an entry to audit_logs.
 */
function logAudit(
    ?int   $userId,
    string $action,
    string $module     = '',
    ?int   $recordId   = null,
    string $description = ''
): void {
    $pdo      = getDBConnection();
    $username = $_SESSION['username'] ?? 'system';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);

    $stmt = $pdo->prepare(
        "INSERT INTO audit_logs (user_id, username, action, module, record_id, description, ip_address, user_agent)
         VALUES (:uid, :uname, :action, :module, :rid, :desc, :ip, :ua)"
    );
    $stmt->execute([
        ':uid'    => $userId,
        ':uname'  => $username,
        ':action' => $action,
        ':module' => $module,
        ':rid'    => $recordId,
        ':desc'   => $description,
        ':ip'     => $ip,
        ':ua'     => $ua,
    ]);
}

// ------------------------------------------------------------------
// SYSTEM SETTINGS
// ------------------------------------------------------------------
function getSetting(string $key, string $default = ''): string {
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $val  = $stmt->fetchColumn();
    return ($val !== false) ? $val : $default;
}

function saveSetting(string $key, string $value, int $updatedBy = 0): void {
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare(
        "INSERT INTO system_settings (setting_key, setting_value, updated_by)
         VALUES (:key, :val, :uid)
         ON DUPLICATE KEY UPDATE setting_value = :val2, updated_by = :uid2"
    );
    $stmt->execute([':key' => $key, ':val' => $value, ':uid' => $updatedBy,
                    ':val2' => $value, ':uid2' => $updatedBy]);
}

// ------------------------------------------------------------------
// COMMUNITIES & FACILITIES (dropdown helpers)
// ------------------------------------------------------------------
function getCommunities(): array {
    $pdo  = getDBConnection();
    $stmt = $pdo->query("SELECT id, community_name FROM communities WHERE status='active' ORDER BY community_name");
    return $stmt->fetchAll();
}

function getHealthFacilities(): array {
    $pdo  = getDBConnection();
    $stmt = $pdo->query("SELECT id, facility_name, facility_type FROM health_facilities WHERE status='active' ORDER BY facility_name");
    return $stmt->fetchAll();
}

function getAllUsers(bool $activeOnly = false): array {
    $pdo  = getDBConnection();
    $sql  = "SELECT u.id, u.full_name, u.username, u.role_id, r.role_name, u.status
             FROM users u JOIN roles r ON u.role_id = r.id";
    if ($activeOnly) $sql .= " WHERE u.status = 'active'";
    $sql .= " ORDER BY u.full_name";
    return $pdo->query($sql)->fetchAll();
}

// ------------------------------------------------------------------
// PAGINATION
// ------------------------------------------------------------------
/**
 * Returns ['offset'=>int, 'page'=>int, 'per_page'=>int]
 */
function getPaginationParams(int $perPage = RECORDS_PER_PAGE): array {
    $page   = max(1, (int) ($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'per_page' => $perPage, 'offset' => $offset];
}

/**
 * Render Bootstrap 5 pagination links.
 */
function renderPagination(int $totalRows, int $perPage, int $currentPage, string $queryString = ''): string {
    if ($totalRows <= $perPage) return '';
    $totalPages = (int) ceil($totalRows / $perPage);
    $sep        = $queryString ? '&' : '?';
    $html       = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';

    // Prev
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?' . $queryString . $sep . 'page=' . ($currentPage - 1) . '">&laquo; Prev</a></li>';
    }
    // Pages
    $start = max(1, $currentPage - 2);
    $end   = min($totalPages, $currentPage + 2);
    if ($start > 1)          $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i === $currentPage) ? ' active' : '';
        $html  .= '<li class="page-item' . $active . '"><a class="page-link" href="?' . $queryString . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }
    if ($end < $totalPages)  $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    // Next
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="?' . $queryString . $sep . 'page=' . ($currentPage + 1) . '">Next &raquo;</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

// ------------------------------------------------------------------
// FLASH MESSAGES
// ------------------------------------------------------------------
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): void {
    $flash = getFlash();
    if (!$flash) return;
    $icons = ['success'=>'check-circle','danger'=>'x-circle','warning'=>'exclamation-triangle','info'=>'info-circle'];
    $icon  = $icons[$flash['type']] ?? 'info-circle';
    echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-' . $icon . '-fill"></i>
            <span>' . htmlspecialchars($flash['message']) . '</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
}

// ------------------------------------------------------------------
// SANITIZATION / VALIDATION
// ------------------------------------------------------------------
function clean(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function isValidGhanaPhone(string $phone): bool {
    // Accepts 0XXXXXXXXX or +233XXXXXXXXX or 233XXXXXXXXX
    return (bool) preg_match('/^(\+?233|0)[235][0-9]{8}$/', preg_replace('/\s+/', '', $phone));
}

function isValidDate(string $date): bool {
    if (empty($date)) return false;
    // Accept YYYY-MM-DD
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if ($d && $d->format('Y-m-d') === $date) return true;
    // Accept dd/mm/yyyy (fallback)
    $d = DateTime::createFromFormat('d/m/Y', $date);
    return (bool) ($d && $d->format('d/m/Y') === $date);
}

/**
 * Normalise any supported date input to YYYY-MM-DD.
 * Returns empty string if unparseable.
 */
function normaliseDateToDb(string $date): string {
    $date = trim($date);
    if (empty($date)) return '';
    // Already YYYY-MM-DD
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if ($d && $d->format('Y-m-d') === $date) return $date;
    // dd/mm/yyyy
    $d = DateTime::createFromFormat('d/m/Y', $date);
    if ($d && $d->format('d/m/Y') === $date) return $d->format('Y-m-d');
    return '';
}

// ------------------------------------------------------------------
// STATUS BADGES
// ------------------------------------------------------------------
function pregnancyStatusBadge(string $status): string {
    $map = [
        'Active'              => 'success',
        'Delivered'           => 'primary',
        'Lost to Follow-up'   => 'warning',
        'Referred'            => 'info',
        'Deceased'            => 'dark',
        'Other'               => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . htmlspecialchars($status) . '</span>';
}

function exemptionStatusBadge(string $status): string {
    $map = [
        'Approved'      => 'success',
        'Pending'        => 'warning text-dark',
        'Rejected'       => 'danger',
        'Under Review'   => 'info',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . htmlspecialchars($status) . '</span>';
}

// ------------------------------------------------------------------
// DATE HELPERS
// ------------------------------------------------------------------
function formatDate(?string $date, string $format = 'd M Y'): string {
    if (!$date || $date === '0000-00-00') return '—';
    try {
        return (new DateTime($date))->format($format);
    } catch (Exception $e) {
        return '—';
    }
}

function formatDateTime(?string $dt): string {
    return formatDate($dt, 'd M Y, H:i');
}

function calculateAge(?string $dob): string {
    if (!$dob || $dob === '0000-00-00') return '—';
    try {
        $d    = new DateTime($dob);
        $now  = new DateTime();
        $diff = $now->diff($d);
        return $diff->y . ' yrs';
    } catch (Exception $e) {
        return '—';
    }
}

// ------------------------------------------------------------------
// DASHBOARD STATISTICS
// ------------------------------------------------------------------
function getDashboardStats(): array {
    $pdo  = getDBConnection();

    $today = date('Y-m-d');
    $month = date('Y-m');
    $year  = date('Y');

    $stats = [];

    $q = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();

    $stats['total']         = $q("SELECT COUNT(*) FROM pregnant_women WHERE is_deleted=0");
    $stats['today']         = $q("SELECT COUNT(*) FROM pregnant_women WHERE is_deleted=0 AND registration_date='$today'");
    $stats['this_month']    = $q("SELECT COUNT(*) FROM pregnant_women WHERE is_deleted=0 AND DATE_FORMAT(registration_date,'%Y-%m')='$month'");
    $stats['this_year']     = $q("SELECT COUNT(*) FROM pregnant_women WHERE is_deleted=0 AND YEAR(registration_date)='$year'");
    $stats['active']        = $q("SELECT COUNT(*) FROM pregnant_women WHERE is_deleted=0 AND pregnancy_status='Active'");
    $stats['delivered']     = $q("SELECT COUNT(*) FROM pregnant_women WHERE is_deleted=0 AND pregnancy_status='Delivered'");
    $stats['approved']      = $q("SELECT COUNT(*) FROM pregnant_women WHERE is_deleted=0 AND exemption_status='Approved'");
    $stats['pending']       = $q("SELECT COUNT(*) FROM pregnant_women WHERE is_deleted=0 AND exemption_status='Pending'");

    // Monthly trend (last 12 months)
    $stmt = $pdo->query(
        "SELECT DATE_FORMAT(registration_date,'%b %Y') AS lbl,
                DATE_FORMAT(registration_date,'%Y-%m') AS ym,
                COUNT(*) AS total
         FROM pregnant_women
         WHERE is_deleted=0 AND registration_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
         GROUP BY ym ORDER BY ym"
    );
    $stats['monthly_trend'] = $stmt->fetchAll();

    // By community (top 8)
    $stmt = $pdo->query(
        "SELECT COALESCE(c.community_name, pw.community_other, 'Unknown') AS community,
                COUNT(*) AS total
         FROM pregnant_women pw
         LEFT JOIN communities c ON pw.community_id = c.id
         WHERE pw.is_deleted=0
         GROUP BY community ORDER BY total DESC LIMIT 8"
    );
    $stats['by_community'] = $stmt->fetchAll();

    // By facility (top 8)
    $stmt = $pdo->query(
        "SELECT COALESCE(hf.facility_name, pw.facility_other, 'Unknown') AS facility,
                COUNT(*) AS total
         FROM pregnant_women pw
         LEFT JOIN health_facilities hf ON pw.health_facility_id = hf.id
         WHERE pw.is_deleted=0
         GROUP BY facility ORDER BY total DESC LIMIT 8"
    );
    $stats['by_facility'] = $stmt->fetchAll();

    // By staff (top 10)
    $stmt = $pdo->query(
        "SELECT u.full_name AS staff, COUNT(*) AS total
         FROM pregnant_women pw
         JOIN users u ON pw.created_by = u.id
         WHERE pw.is_deleted=0
         GROUP BY pw.created_by ORDER BY total DESC LIMIT 10"
    );
    $stats['by_staff'] = $stmt->fetchAll();

    return $stats;
}
