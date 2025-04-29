<?php
session_start();
require_once 'db.php';
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// Validate group_id parameter
if (!isset($_GET['group_id']) || !is_numeric($_GET['group_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid group ID']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$group_id = (int)$_GET['group_id'];

try {
    // First verify that the user is a member of this group
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM group_members 
        WHERE group_id = ? AND user_id = ?
    ");
    $stmt->execute([$group_id, $user_id]);
    $is_member = $stmt->fetchColumn();

    if (!$is_member) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized to view this group']);
        exit();
    }

    // Get all members of the group except the current user
    $stmt = $pdo->prepare("
        SELECT u.id, u.username as name, u.email
        FROM users u
        INNER JOIN group_members gm ON u.id = gm.user_id
        WHERE gm.group_id = ? AND u.id != ?
        ORDER BY u.username ASC
    ");
    $stmt->execute([$group_id, $user_id]);
    $members = $stmt->fetchAll();

    echo json_encode($members);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
}
