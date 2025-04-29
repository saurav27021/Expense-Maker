<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['credential'])) {
        throw new Exception('No credentials provided');
    }

    // Decode the JWT token
    $jwt = $data['credential'];
    $tokenParts = explode('.', $jwt);
    $payload = json_decode(base64_decode($tokenParts[1]), true);

    // Extract user information
    $google_id = $payload['sub'];
    $email = $payload['email'];
    $name = $payload['name'];
    $picture = $payload['picture'];

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->execute([$google_id, $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user
        $stmt = $pdo->prepare("UPDATE users SET google_id = ?, name = ?, avatar = ?, last_login = NOW() WHERE id = ?");
        $stmt->execute([$google_id, $name, $picture, $user['id']]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['avatar'] = $picture;
    } else {
        // Create new user
        $username = generateUniqueUsername($pdo, $name);
        
        $stmt = $pdo->prepare("INSERT INTO users (google_id, username, name, email, avatar) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$google_id, $username, $name, $email, $picture]);
        
        $userId = $pdo->lastInsertId();
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['avatar'] = $picture;
    }

    echo json_encode([
        'success' => true,
        'redirect' => 'dashboard.php'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generateUniqueUsername($pdo, $name) {
    // Convert name to lowercase and remove special characters
    $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    
    // If username is too short, add 'user' prefix
    if (strlen($baseUsername) < 3) {
        $baseUsername = 'user' . $baseUsername;
    }
    
    $username = $baseUsername;
    $counter = 1;
    
    // Keep trying until we find a unique username
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() === 0) {
            return $username;
        }
        
        $username = $baseUsername . $counter;
        $counter++;
    }
} 