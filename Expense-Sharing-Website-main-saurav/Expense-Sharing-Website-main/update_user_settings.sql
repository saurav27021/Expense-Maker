-- Update user_settings table
ALTER TABLE user_settings
ADD COLUMN email_notifications BOOLEAN DEFAULT TRUE,
ADD COLUMN expense_reminders BOOLEAN DEFAULT TRUE,
ADD COLUMN settlement_reminders BOOLEAN DEFAULT TRUE;

-- Update users table if needed
ALTER TABLE users
MODIFY COLUMN timezone VARCHAR(100) DEFAULT 'Asia/Kolkata',
MODIFY COLUMN currency VARCHAR(3) DEFAULT 'INR';

-- Add indexes for better performance
CREATE INDEX idx_user_settings_user_id ON user_settings(user_id);
CREATE INDEX idx_users_email ON users(email);
