<?php
session_start();
require_once 'config.php';

try {
    $pdo->beginTransaction();

    // Ensure we have a valid user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute(['test@example.com']);
    $user = $stmt->fetch();

    if (!$user) {
        // Create test user if it doesn't exist
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            'testuser',
            'test@example.com',
            password_hash('test123', PASSWORD_DEFAULT)
        ]);
        $user_id = $pdo->lastInsertId();
    } else {
        $user_id = $user['id'];
    }

    // Create a test group
    $stmt = $pdo->prepare("
        INSERT INTO groups (name, description, created_by) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        'Test Group ' . date('Y-m-d H:i:s'),
        'This is a test group',
        $user_id
    ]);
    $group_id = $pdo->lastInsertId();
    
    // Add the creator as a group member with admin role
    $stmt = $pdo->prepare("
        INSERT INTO group_members (group_id, user_id, role) 
        VALUES (?, ?, 'admin')
    ");
    $stmt->execute([$group_id, $user_id]);

    $pdo->commit();
    echo "Success!\n";
    echo "User ID: " . $user_id . "\n";
    echo "Group created with ID: " . $group_id . "\n";
    
    // Verify the group was created
    $stmt = $pdo->prepare("
        SELECT g.*, u.username as creator_name, COUNT(gm.user_id) as member_count
        FROM groups g
        JOIN users u ON g.created_by = u.id
        LEFT JOIN group_members gm ON g.id = gm.group_id
        WHERE g.id = ?
        GROUP BY g.id
    ");
    $stmt->execute([$group_id]);
    echo "\nGroup details:\n";
    print_r($stmt->fetch());

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error creating group: " . $e->getMessage() . "\n";
    
    // Debug information
    echo "\nDebug Info:\n";
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
    $stmt->execute(['test@example.com']);
    $user = $stmt->fetch();
    echo "Test user details:\n";
    print_r($user);
}
?>
