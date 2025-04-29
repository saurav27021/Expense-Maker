<?php
session_start();
require_once 'db.php';
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

try {
    // Get all groups where the user is a member
    $stmt = $pdo->prepare('
        SELECT g.id, g.name 
        FROM groups g
        INNER JOIN group_members gm ON g.id = gm.group_id
        WHERE gm.user_id = ? AND g.status = "active"
        ORDER BY g.name ASC
    ');
    
    $stmt->execute([$user_id]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($groups);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch groups']);
}
