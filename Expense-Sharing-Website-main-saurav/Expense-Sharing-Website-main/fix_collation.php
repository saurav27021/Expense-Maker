<?php
require_once 'db.php';

try {
    // First, set the database collation
    $pdo->exec("ALTER DATABASE `expense_maker` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Convert each table to utf8mb4_unicode_ci
        $pdo->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Get all columns in the table
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $column) {
            if (strpos(strtolower($column['Type']), 'char') !== false || 
                strpos(strtolower($column['Type']), 'text') !== false) {
                // Modify string columns to use utf8mb4_unicode_ci
                $pdo->exec("ALTER TABLE `$table` MODIFY `{$column['Field']}` {$column['Type']} 
                           CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
    }
    
    echo "Database collation standardized successfully!\n";
    
} catch (PDOException $e) {
    die("Error updating collation: " . $e->getMessage() . "\n");
} 