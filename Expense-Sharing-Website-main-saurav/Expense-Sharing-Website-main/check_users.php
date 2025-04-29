<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("SELECT id, username, email FROM users");
    $users = $stmt->fetchAll();
    echo "Users in database:\n";
    print_r($users);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
