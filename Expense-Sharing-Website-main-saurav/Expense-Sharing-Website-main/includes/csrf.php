<?php
/**
 * CSRF Protection Helper Functions
 */

/**
 * Generate a new CSRF token and store it in the session
 * @return string The generated CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify if the provided CSRF token matches the one in session
 * @param string $token The token to verify
 * @return bool True if token is valid, false otherwise
 */
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output a hidden input field with the CSRF token
 * @return void
 */
function outputCSRFTokenField() {
    $token = generateCSRFToken();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Verify CSRF token from POST request and die if invalid
 * @return void
 */
function validateCSRFToken() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
    }
}
