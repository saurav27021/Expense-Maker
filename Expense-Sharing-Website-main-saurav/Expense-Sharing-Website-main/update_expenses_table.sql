-- Add user_id column if it doesn't exist
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS user_id INT;

-- Rename user_id to paid_by if needed
ALTER TABLE expenses CHANGE COLUMN user_id paid_by INT;

-- Add foreign key constraint
ALTER TABLE expenses ADD CONSTRAINT fk_expenses_paid_by
    FOREIGN KEY (paid_by) REFERENCES users(id); 