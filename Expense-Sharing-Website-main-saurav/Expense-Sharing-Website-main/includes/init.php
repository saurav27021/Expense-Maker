<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting based on environment
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Include required files
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';

// Initialize CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    generateCSRFToken();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /login.php');
        exit();
    }
}

// Function to set flash message
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Function to get and clear flash message
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Set some global variables
$GLOBALS['user'] = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $GLOBALS['user'] = $stmt->fetch();
}

// Set site configuration
$GLOBALS['site_config'] = [
    'name' => 'Expense Maker',
    'description' => 'Smart Expense Sharing App',
    'version' => '1.0.0',
    'currency' => 'INR',
    'currency_symbol' => '₹',
    'date_format' => 'd M Y',
    'time_format' => 'h:i A',
    'items_per_page' => 10,
    'upload_max_size' => 5 * 1024 * 1024, // 5MB
    'allowed_image_types' => ['image/jpeg', 'image/png', 'image/gif'],
    'admin_email' => 'admin@expensemaker.com'
];
