<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

// Debug info in development
if ($_SERVER['SERVER_NAME'] === 'localhost') {
    error_log("Callback Debug - Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Callback Debug - Code present: " . (isset($_GET['code']) ? 'yes' : 'no'));
    error_log("Callback Debug - Raw GET params: " . print_r($_GET, true));
    if (isset($_GET['error'])) {
        error_log("Callback Debug - Error: " . $_GET['error']);
        error_log("Callback Debug - Error Description: " . ($_GET['error_description'] ?? 'none'));
    }
}

try {
    // Create Google Client
    $client = new Google\Client();
    
    // Set OAuth 2.0 Client ID credentials
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);
    
    // Set application name
    $client->setApplicationName('Expense Maker');
    
    // Set access type to offline to get refresh token
    $client->setAccessType('offline');
    
    // Force to select account
    $client->setPrompt('select_account consent');
    
    // Request email and profile scopes
    $client->addScope('https://www.googleapis.com/auth/userinfo.email');
    $client->addScope('https://www.googleapis.com/auth/userinfo.profile');

    // Debug OAuth settings in development
    if ($_SERVER['SERVER_NAME'] === 'localhost') {
        error_log("Callback OAuth Debug - Client ID: " . GOOGLE_CLIENT_ID);
        error_log("Callback OAuth Debug - Redirect URI: " . GOOGLE_REDIRECT_URI);
        error_log("Callback OAuth Debug - Current URL: " . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    }

    if (isset($_GET['code'])) {
        try {
            // Get token
            $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
            
            if (isset($token['error'])) {
                throw new Exception('Token Error: ' . ($token['error_description'] ?? $token['error']));
            }
            
            $client->setAccessToken($token);

            // Get user info
            $oauth2 = new Google\Service\Oauth2($client);
            $google_account_info = $oauth2->userinfo->get();
            
            $email = $google_account_info->email;
            $name = $google_account_info->name;
            $google_id = $google_account_info->id;
            $picture = $google_account_info->picture;

            // Debug user info in development
            if ($_SERVER['SERVER_NAME'] === 'localhost') {
                error_log("User Info - Email: " . $email);
                error_log("User Info - Name: " . $name);
                error_log("User Info - Google ID: " . $google_id);
            }

            // Start transaction
            $pdo->beginTransaction();

            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
                $stmt->execute([$google_id, $email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Update existing user
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET google_id = ?, 
                            name = ?, 
                            avatar = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$google_id, $name, $picture, $user['id']]);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['name'] = $name;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['success'] = 'Welcome back, ' . $name . '!';
                } else {
                    // Generate unique username
                    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
                    $username = $base_username;
                    $counter = 1;
                    
                    while (true) {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $stmt->execute([$username]);
                        if (!$stmt->fetch()) break;
                        $username = $base_username . $counter;
                        $counter++;
                    }

                    // Create new user
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, name, email, google_id, avatar, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$username, $name, $email, $google_id, $picture]);
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // Initialize user settings
                    $stmt = $pdo->prepare("
                        INSERT INTO user_settings (user_id, notification_preferences)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$user_id, json_encode(['email' => true, 'web' => true])]);
                    
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['email'] = $email;
                    $_SESSION['name'] = $name;
                    $_SESSION['username'] = $username;
                    $_SESSION['success'] = 'Welcome to Expense Maker, ' . $name . '!';
                }

                $pdo->commit();

                // Check for pending invite
                if (isset($_SESSION['pending_invite_token'])) {
                    $token = $_SESSION['pending_invite_token'];
                    unset($_SESSION['pending_invite_token']);
                    header('Location: join-group.php?token=' . urlencode($token));
                    exit();
                }

                header('Location: dashboard.php');
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Google OAuth Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $_SESSION['error'] = 'Failed to authenticate with Google. Please try again. Error: ' . $e->getMessage();
            header('Location: login.php');
            exit();
        }
    } else {
        $_SESSION['error'] = 'No authorization code received from Google';
        header('Location: login.php');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Google OAuth Error in setup: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $_SESSION['error'] = 'An error occurred during authentication. Please try again.';
    header('Location: login.php');
    exit();
}