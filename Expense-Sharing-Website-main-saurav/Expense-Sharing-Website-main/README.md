# Expense Maker

A modern web application for sharing expenses among friends and groups. Built with PHP, MySQL, HTML, CSS, and JavaScript. Features Google OAuth authentication and Razorpay payment integration.

## Features

- Google OAuth authentication
- Create and manage expense groups
- Add and track expenses
- Automatic expense splitting
- Real-time balance calculation
- Razorpay payment integration
- Email notifications
- Modern and responsive UI
- Secure password handling
- Form validation
- Interactive notifications
- Expense history and reports
- Settlement calculator

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser
- Composer for dependency management
- Google OAuth credentials
- Razorpay account and API keys
- SMTP server for email notifications

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/Ankii04/Expense-Sharing-Website.git
   cd Expense-Sharing-Website
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Set up configuration:
   - Copy `.env.example` to `.env`
   - Copy `config.example.php` to `config.php`
   - Update both files with your credentials:
     - Database settings
     - Google OAuth credentials
     - Razorpay API keys
     - SMTP configuration

4. Create a MySQL database and import the schema:
   ```bash
   mysql -u root -p < database/complete_setup.sql
   ```

5. Set up your web server:
   - Point your web server to the project directory
   - Ensure PHP has write permissions for session handling
   - Enable required PHP extensions (PDO, MySQL, curl)

6. Configure OAuth and Payment:
   - Set up a project in Google Cloud Console
   - Configure OAuth consent screen
   - Create OAuth 2.0 credentials
   - Set up Razorpay account and get API keys

7. Access the application through your web browser:
   ```
   http://localhost/Expense-Maker
   ```

## Usage

1. Register a new account
2. Log in to your account
3. Create a new group
4. Add members to your group
5. Add expenses to the group
6. View balances and settle up

## Security Features

- OAuth 2.0 authentication with Google
- Password hashing using PHP's password_hash()
- Prepared statements for all database queries
- Input validation and sanitization
- Session-based authentication
- XSS protection
- CSRF protection
- Secure payment handling with Razorpay
- Environment-based configuration
- Sensitive data protection

## Contributing

Feel free to submit issues and enhancement requests!

## License

This project is licensed under the MIT License - see the LICENSE file for details. 