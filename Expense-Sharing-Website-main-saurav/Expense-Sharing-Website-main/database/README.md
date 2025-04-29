# Expense Sharing Application

A modern web application for sharing expenses among friends and groups. Built with PHP, MySQL, HTML, CSS, and JavaScript.

## Features

- User registration and authentication
- Create and manage expense groups
- Add and track expenses
- Automatic expense splitting
- Real-time balance calculation
- Modern and responsive UI
- Secure password handling
- Form validation
- Interactive notifications

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## Installation

1. Clone or download this repository to your web server directory.

2. Create a MySQL database and import the schema:
   ```bash
   mysql -u root -p < database.sql
   ```

3. Configure the database connection:
   - Open `config.php`
   - Update the database credentials (host, username, password) if needed

4. Set up your web server:
   - Point your web server to the project directory
   - Ensure PHP has write permissions for session handling
   - Enable PHP PDO and MySQL extensions

5. Access the application through your web browser:
   ```
   http://localhost/expense-sharing
   ```

## Usage

1. Register a new account
2. Log in to your account
3. Create a new group
4. Add members to your group
5. Add expenses to the group
6. View balances and settle up

## Security Features

- Password hashing using PHP's password_hash()
- Prepared statements for all database queries
- Input validation and sanitization
- Session-based authentication
- XSS protection
- CSRF protection

## Contributing

Feel free to submit issues and enhancement requests!

## License

This project is licensed under the MIT License - see the LICENSE file for details. 