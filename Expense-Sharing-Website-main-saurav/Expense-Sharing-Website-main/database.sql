-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS expense_maker
DEFAULT CHARACTER SET utf8mb4
DEFAULT COLLATE utf8mb4_unicode_ci;

USE expense_maker;

-- Drop tables if they exist (in correct order due to foreign key constraints)
DROP TABLE IF EXISTS settlements;
DROP TABLE IF EXISTS expense_splits;
DROP TABLE IF EXISTS expense_attachments;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS group_invites;
DROP TABLE IF EXISTS group_members;
DROP TABLE IF EXISTS group_settings;
DROP TABLE IF EXISTS groups;
DROP TABLE IF EXISTS user_settings;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS users;

-- Create users table with enhanced fields
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    currency VARCHAR(3) DEFAULT 'INR',
    timezone VARCHAR(50) DEFAULT 'Asia/Kolkata',
    google_id VARCHAR(255) DEFAULT NULL,
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    INDEX idx_email (email),
    INDEX idx_google_id (google_id)
) ENGINE=InnoDB;

-- Create user settings table
CREATE TABLE user_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    notification_email BOOLEAN DEFAULT TRUE,
    notification_push BOOLEAN DEFAULT TRUE,
    default_split_type ENUM('equal', 'percentage', 'custom') DEFAULT 'equal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id)
) ENGINE=InnoDB;

-- Create password resets table
CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- Create groups table with enhanced fields
CREATE TABLE groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    currency VARCHAR(3) DEFAULT 'INR',
    created_by INT UNSIGNED NOT NULL,
    category ENUM('home', 'trip', 'couple', 'other') DEFAULT 'other',
    status ENUM('active', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB;

-- Create group settings table
CREATE TABLE group_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    default_split_type ENUM('equal', 'percentage', 'custom') DEFAULT 'equal',
    allow_non_group_expenses BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_settings (group_id)
) ENGINE=InnoDB;

-- Create group members table with enhanced fields
CREATE TABLE group_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_member (group_id, user_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- Create group invites table
CREATE TABLE group_invites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    invited_by INT UNSIGNED NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_invite (group_id, email),
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- Create expenses table with enhanced fields
CREATE TABLE expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    paid_by INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'other',
    date DATE NOT NULL,
    notes TEXT,
    status ENUM('active', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE RESTRICT,
    FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_group_id (group_id),
    INDEX idx_paid_by (paid_by),
    INDEX idx_date (date)
) ENGINE=InnoDB;

-- Create expense attachments table
CREATE TABLE expense_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create expense splits table with enhanced fields
CREATE TABLE expense_splits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    percentage DECIMAL(5,2) DEFAULT NULL,
    is_settled BOOLEAN DEFAULT FALSE,
    settled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_expense_split (expense_id, user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_is_settled (is_settled)
) ENGINE=InnoDB;

-- Create settlements table
CREATE TABLE settlements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NOT NULL,
    from_user_id INT UNSIGNED NOT NULL,
    to_user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    payment_method VARCHAR(50) DEFAULT NULL,
    payment_reference VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE RESTRICT,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (to_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_group_id (group_id),
    INDEX idx_from_user (from_user_id),
    INDEX idx_to_user (to_user_id)
) ENGINE=InnoDB;

-- Insert test data
INSERT INTO users (name, email, password, currency, timezone, email_verified_at) VALUES 
('Test User', 'test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'INR', 'Asia/Kolkata', NOW());

INSERT INTO user_settings (user_id) VALUES (1);

INSERT INTO groups (name, description, created_by, category) VALUES 
('Home Expenses', 'Monthly household expenses', 1, 'home');

INSERT INTO group_settings (group_id) VALUES (1);

INSERT INTO group_members (group_id, user_id, role) VALUES (1, 1, 'admin');