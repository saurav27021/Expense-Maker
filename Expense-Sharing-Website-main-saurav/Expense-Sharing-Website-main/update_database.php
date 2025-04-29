<?php
require_once 'db.php';

try {
    // Drop and recreate group_invites table with new structure
    $sql = "
    DROP TABLE IF EXISTS group_invites;

    CREATE TABLE group_invites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        email VARCHAR(255),
        token VARCHAR(64) NOT NULL UNIQUE,
        invite_code VARCHAR(8) NOT NULL UNIQUE,
        type ENUM('link', 'email', 'code') NOT NULL,
        status ENUM('pending', 'active', 'accepted', 'cancelled', 'expired') DEFAULT 'active',
        created_by INT NOT NULL,
        accepted_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        accepted_at TIMESTAMP NULL DEFAULT NULL,
        expires_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id),
        FOREIGN KEY (accepted_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Database updated successfully!";
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
