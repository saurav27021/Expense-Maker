<?php
// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'expense_maker');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Connection instance
$conn = null;
$pdo = null;

try {
    // Create MySQLi connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("MySQLi connection failed: " . $conn->connect_error);
    }
    $conn->set_charset(DB_CHARSET);

    // Create PDO connection
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

    // Test connections
    $conn->query("SELECT 1");
    $pdo->query("SELECT 1");

} catch (Exception $e) {
    // Log error (in production, use proper logging)
    error_log("Database connection error: " . $e->getMessage());
    
    // In production, show a user-friendly message
    if ($_SERVER['SERVER_NAME'] !== 'localhost') {
        die("A database error occurred. Please try again later.");
    } else {
        die("Database connection error: " . $e->getMessage());
    }
}

/**
 * Helper function to get a new database connection
 * @return mysqli A new database connection
 */
function getNewConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("MySQLi connection failed: " . $conn->connect_error);
    }
    $conn->set_charset(DB_CHARSET);
    return $conn;
}

/**
 * Helper function to get a new PDO connection
 * @return PDO A new PDO connection
 */
function getNewPDO() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $options);
}
