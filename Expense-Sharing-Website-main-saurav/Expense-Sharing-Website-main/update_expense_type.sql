ALTER TABLE expenses ADD COLUMN expense_type ENUM('food', 'clothes', 'travel', 'other') NOT NULL DEFAULT 'other';
