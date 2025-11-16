# HRMS - Human Resource Management System

A comprehensive Human Resource Management System built with PHP and MySQL for managing employees, attendance, and organizational operations.

## Features

### Current Features (v1.0)

#### Authentication & Authorization
- User registration and login
- Role-based access control (Admin & Employee)
- Secure password hashing
- Session management

#### Admin Features
- Dashboard with key metrics
- Create and manage users
- View all employees
- Attendance reports with filters
- View attendance by date and employee

#### Employee Features
- Punch In/Punch Out system
- View attendance history
- Real-time work hours calculation
- Personal dashboard

## Technology Stack

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Server:** Apache (XAMPP)

## Project Structure

```
hrms/
├── admin/
│   ├── dashboard.php
│   ├── create-user.php
│   ├── manage-users.php
│   └── attendance-report.php
├── employee/
│   └── dashboard.php
├── config/
│   └── database.php
├── database/
│   └── schema.sql
├── includes/
│   └── auth.php
├── index.php
├── login.php
├── register.php
├── logout.php
├── README.md
└── FUTURE_IMPLEMENTATIONS.md
```

## Installation & Setup

### Prerequisites

- XAMPP (or any other Apache + MySQL + PHP stack)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser (Chrome, Firefox, Safari, Edge)

### Step 1: Install XAMPP

1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Install XAMPP in your system
3. Start Apache and MySQL services from XAMPP Control Panel

### Step 2: Setup Project

1. Copy the `hrms` folder to `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac)
2. The final path should be: `C:\xampp\htdocs\hrms\`

### Step 3: Create Database

1. Open phpMyAdmin in your browser: `http://localhost/phpmyadmin`
2. Create a new database named `hrms_db`
3. Import the database schema:
   - Click on the `hrms_db` database
   - Go to the "Import" tab
   - Click "Choose File" and select `database/schema.sql`
   - Click "Go" to import

Alternatively, you can run the SQL file directly:
```bash
mysql -u root -p < database/schema.sql
```

### Step 4: Configure Database Connection

1. Open `config/database.php`
2. Update the database credentials if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Your MySQL password
   define('DB_NAME', 'hrms_db');
   ```

### Step 5: Access the Application

1. Open your web browser
2. Navigate to: `http://localhost/hrms`
3. You will be redirected to the login page

## Default Credentials

### Admin Account
- **Email:** admin@hrms.com
- **Password:** admin123

### Test Employee Account
You can register a new employee account or create one from the admin panel.

## Usage Guide

### For Admin Users

1. **Login** with admin credentials
2. **Dashboard:** View key metrics (total employees, present today, absent today)
3. **Create User:**
   - Click on "Create User" card
   - Fill in user details (name, username, email, password, role)
   - Submit to create a new user
4. **Manage Users:**
   - View all users in the system
   - See user details, roles, and status
5. **Attendance Report:**
   - Filter by date and employee
   - View punch in/out times and work hours
   - Export data (future feature)

### For Employee Users

1. **Login** with employee credentials
2. **Dashboard:** View personalized greeting and current date
3. **Punch In:**
   - Click "Punch In" button at the start of your workday
   - System records the time automatically
4. **Punch Out:**
   - Click "Punch Out" button at the end of your workday
   - System calculates total work hours
5. **View History:**
   - Scroll down to see your recent attendance history
   - View punch times and work hours for past days

## Database Schema

### Tables

1. **roles**
   - id (Primary Key)
   - role_name (admin, employee)
   - created_at

2. **users**
   - id (Primary Key)
   - username
   - email
   - password (hashed)
   - full_name
   - role_id (Foreign Key -> roles.id)
   - status (active, inactive)
   - created_at
   - updated_at

3. **attendance**
   - id (Primary Key)
   - user_id (Foreign Key -> users.id)
   - punch_in (datetime)
   - punch_out (datetime, nullable)
   - work_hours (decimal, nullable)
   - date
   - status (present, absent, half-day)
   - created_at
   - updated_at

## Security Features

- Password hashing using PHP's `password_hash()` function
- SQL injection prevention using prepared statements
- XSS protection with input sanitization
- Session-based authentication
- Role-based access control
- CSRF protection (to be implemented)

## Browser Compatibility

- Chrome (recommended)
- Firefox
- Safari
- Microsoft Edge
- Opera

## Troubleshooting

### Cannot connect to database
- Ensure MySQL is running in XAMPP
- Check database credentials in `config/database.php`
- Verify database `hrms_db` exists

### Login not working
- Clear browser cookies and cache
- Check if users table has records
- Verify password is being hashed correctly

### Attendance not recording
- Check if attendance table exists
- Verify user is logged in
- Check browser console for JavaScript errors

### Pages not loading
- Ensure Apache is running
- Check file paths are correct
- Verify PHP version is 7.4 or higher

## Future Enhancements

For a comprehensive list of planned features and improvements, please refer to [FUTURE_IMPLEMENTATIONS.md](FUTURE_IMPLEMENTATIONS.md)

### High Priority Features
- Leave Management System
- GPS-based Attendance
- Payroll Management
- Department Management
- Advanced Reporting and Analytics
- Mobile Application

## Contributing

This is a project in active development. Contributions are welcome!

### How to Contribute
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open-source and available for educational and commercial use.

## Support

For issues, questions, or suggestions:
- Create an issue in the project repository
- Contact the development team
- Check the documentation

## Changelog

### Version 1.0.0 (Current)
- Initial release
- User authentication system
- Role-based access (Admin & Employee)
- Basic attendance tracking (Punch In/Out)
- Admin dashboard with statistics
- User creation by admin
- Attendance reports with filters
- Employee dashboard with attendance history

## Credits

Developed as a comprehensive HRMS solution for small to medium-sized businesses.

## Screenshots

### Login Page
- Clean and modern login interface
- Demo credentials displayed for easy access

### Admin Dashboard
- Statistics cards showing key metrics
- Quick access to main features
- User-friendly navigation

### Employee Dashboard
- Punch In/Out buttons
- Real-time attendance status
- Recent attendance history table

### Attendance Report
- Date and employee filters
- Detailed attendance records
- Work hours calculation

---

**Current Version:** 1.0.0
**Last Updated:** 2025
**Status:** Active Development
