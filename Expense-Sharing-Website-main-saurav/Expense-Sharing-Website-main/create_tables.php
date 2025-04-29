<?php
require_once 'db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS `group_invitations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `group_id` int(11) NOT NULL,
        `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `invited_by` int(11) NOT NULL,
        `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        `status` enum('pending','accepted','declined') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `group_id` (`group_id`),
        KEY `invited_by` (`invited_by`),
        CONSTRAINT `group_invitations_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
        CONSTRAINT `group_invitations_ibfk_2` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "Group invitations table created successfully!\n";
} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage() . "\n");
} 