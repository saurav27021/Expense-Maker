<?php
// Example configuration file - Copy this to config.php and update with your values
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'expense_maker');

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', 'your_google_client_id');
define('GOOGLE_CLIENT_SECRET', 'your_google_client_secret');
define('GOOGLE_REDIRECT_URI', 'http://your-domain.com/google-callback.php');

// Razorpay Configuration
define('RAZORPAY_KEY_ID', 'your_razorpay_key_id');
define('RAZORPAY_KEY_SECRET', 'your_razorpay_key_secret');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_app_specific_password');
define('SMTP_PORT', 587);
define('SMTP_FROM', 'your_email@gmail.com');
define('SMTP_FROM_NAME', 'Expense Maker');
?>
