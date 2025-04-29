<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to continue']);
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// Check if action and group_id are provided
if (!isset($_POST['action']) || !isset($_POST['group_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$action = $_POST['action'];
$group_id = (int)$_POST['group_id'];

try {
    // Verify user has access to the group and is the creator
    $stmt = $pdo->prepare("
        SELECT g.* 
        FROM groups g 
        JOIN group_members gm ON g.id = gm.group_id 
        WHERE g.id = ? AND g.created_by = ?
    ");
    $stmt->execute([$group_id, $_SESSION['user_id']]);
    $group = $stmt->fetch();

    if (!$group) {
        throw new Exception('Group not found or you do not have permission to delete it');
    }

    $pdo->beginTransaction();

    if ($action === 'delete') {
        // 1. First get all expense IDs for this group
        $stmt = $pdo->prepare("SELECT id FROM expenses WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $expense_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Delete expense splits if there are any expenses
        if (!empty($expense_ids)) {
            $placeholders = str_repeat('?,', count($expense_ids) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM expense_splits WHERE expense_id IN ($placeholders)");
            $stmt->execute($expense_ids);
        }

        // 3. Delete settlements
        $stmt = $pdo->prepare("DELETE FROM settlements WHERE group_id = ?");
        $stmt->execute([$group_id]);

        // 4. Delete expenses
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE group_id = ?");
        $stmt->execute([$group_id]);

        // 5. Delete group members
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
        $stmt->execute([$group_id]);

        // 6. Finally delete the group
        $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->execute([$group_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Group deleted successfully']);
    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Group deletion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 