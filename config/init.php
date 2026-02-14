<?php
/**
 * Initialization - Parking Management System
 * Load config, start session, define constants
 */

if (!defined('PARKING_ACCESS')) {
    define('PARKING_ACCESS', true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base path for includes and redirects
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/PARKING_MANAGEMENT_SYSTEM');

// Load database
require_once BASE_PATH . '/config/database.php';

// Optional: load email configuration (create config/email_config.php to override defaults)
if (file_exists(BASE_PATH . '/config/email_config.php')) {
    require_once BASE_PATH . '/config/email_config.php';
}

// Application settings
// Toggle whether email verification is required for new accounts.
// You can override this by defining REQUIRE_EMAIL_VERIFICATION in config/email_config.php
if (!defined('REQUIRE_EMAIL_VERIFICATION')) {
    define('REQUIRE_EMAIL_VERIFICATION', true);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    // Support both legacy string `role` and numeric `role_id` set during login.
    if (!isLoggedIn()) return false;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    if (isset($_SESSION['role_id']) && intval($_SESSION['role_id']) === 1) return true;
    return false;
}

/**
 * Require login - redirect to login if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Require admin - redirect if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Get current user ID
 */
function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function currentUserRole() {
    // Prefer explicit role string if present; otherwise map role_id to 'admin'/'user'.
    if (isset($_SESSION['role'])) return $_SESSION['role'];
    if (isset($_SESSION['role_id'])) return (intval($_SESSION['role_id']) === 1) ? 'admin' : 'user';
    return null;
}
/**
 * Get current user's display name (full name or username fallback)
 */
function currentUserName() {
    if (!isLoggedIn()) return '';
    // prefer cached full name in session
    if (!empty($_SESSION['full_name'])) return $_SESSION['full_name'];
    // otherwise fetch from database
    $uid = currentUserId();
    if (!$uid) return '';
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT full_name, username FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $name = $row['full_name'] ?: $row['username'] ?: '';
            // cache into session for subsequent requests
            if ($name) $_SESSION['full_name'] = $name;
            return $name;
        }
    } catch (Exception $e) {
        error_log('currentUserName error: ' . $e->getMessage());
    }
    return '';
}
/**
 * Store alert to display as modal on next page load
 */
function setAlert($message, $type = 'success') {
    $_SESSION['alert_message'] = $message;
    $_SESSION['alert_type'] = $type;
}

/**
 * Get and clear alert from session
 */
function getAlert() {
    if (isset($_SESSION['alert_message'])) {
        $message = $_SESSION['alert_message'];
        $type = $_SESSION['alert_type'] ?? 'success';
        unset($_SESSION['alert_message']);
        unset($_SESSION['alert_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Echo alert modal script if alert exists
 */
function displayAlert() {
    $alert = getAlert();
    if ($alert) {
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            showAlert("' . addslashes(htmlspecialchars($alert['message'])) . '", "' . htmlspecialchars($alert['type']) . '");
        });
        </script>';
    }
}