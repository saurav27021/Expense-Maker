-- Create group_history table
CREATE TABLE IF NOT EXISTS group_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    performed_by INT NOT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_group_history_group (group_id),
    INDEX idx_group_history_performer (performed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
