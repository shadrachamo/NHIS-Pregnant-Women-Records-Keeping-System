<?php
// ============================================================
// Database Configuration
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================

define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_NAME',     'nhis_pregnancy');
define('DB_CHARSET',  'utf8mb4');

// Application constants
define('APP_NAME',    'NHIS Pregnancy Exemption Registration System');
define('APP_SHORT',   'NHIS-PERS');
define('APP_OFFICE',  'NHIS Twifo Praso District Office');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/NHIS');
define('RECORDS_PER_PAGE', 20);

// Session name
define('SESSION_NAME', 'nhis_praso_sess');

/**
 * Get a PDO database connection (singleton pattern).
 * All queries throughout the system use this connection.
 */
function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Show a friendly error; never expose credentials in production
            die('<div style="font-family:sans-serif;padding:40px;background:#fff3cd;border:1px solid #ffc107;border-radius:8px;max-width:600px;margin:60px auto;">
                <h2 style="color:#856404;">Database Connection Error</h2>
                <p>Unable to connect to the database. Please check that:</p>
                <ul>
                    <li>XAMPP MySQL service is running</li>
                    <li>The database <strong>nhis_pregnancy</strong> has been imported via phpMyAdmin</li>
                    <li>Credentials in <code>config/database.php</code> are correct</li>
                </ul>
                <p style="color:#6c757d;font-size:0.85em;">Error code: ' . htmlspecialchars($e->getCode()) . '</p>
            </div>');
        }
    }
    return $pdo;
}
