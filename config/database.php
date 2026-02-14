<?php
/**
 * Database Configuration - Parking Management System
 * Connects to MySQL database (XAMPP)
 */

// Prevent direct access
if (!defined('PARKING_ACCESS')) {
    define('PARKING_ACCESS', true);
}

// Database credentials - adjust for your XAMPP setup
define('DB_HOST', 'localhost');
define('DB_NAME', 'parking_management_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // Default XAMPP has no password
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection
 * @return PDO
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}
