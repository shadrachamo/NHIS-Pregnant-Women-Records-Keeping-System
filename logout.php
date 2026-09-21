<?php
// ============================================================
// Logout
// NHIS Pregnancy Exemption Registration System – Twifo Praso
// ============================================================
require_once __DIR__ . '/includes/auth.php';

logoutUser();
header('Location: ' . BASE_URL . '/login.php');
exit;
