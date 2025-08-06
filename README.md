# School Facility Booking System (SFB)

A comprehensive web-based facility reservation system designed for educational institutions to manage and streamline the booking of school facilities by faculty, staff, and administrators.

## 🏫 Overview

The School Facility Booking System (SFB) is a PHP-based web application that enables educational institutions to efficiently manage facility reservations. The system provides role-based access control, automated email notifications, booking conflict detection, and comprehensive administrative tools.

## ✨ Features

### 🔐 Authentication & Authorization
- **Multi-role System**: Admin, Faculty, and Staff roles with different permissions
- **Secure Login**: Session-based authentication with CSRF protection
- **Password Management**: Forgot password functionality with email verification
- **Account Approval**: Admin approval system for faculty registrations

### 📅 Booking Management
- **Facility Reservation**: Book various school facilities (classrooms, auditoriums, gyms, etc.)
- **Conflict Detection**: Automatic detection of booking conflicts
- **Booking Status**: Pending, Approved, Rejected, Cancelled, Completed
- **Flexible Scheduling**: Date and time-based booking system
- **Attendee Management**: Track number of attendees for each booking

### 🏢 Facility Management
- **Facility Types**: Support for different facility categories
- **Capacity Management**: Track facility capacity and availability
- **Location Tracking**: Facility location information
- **Status Monitoring**: Available, Maintenance, Reserved statuses

### 📧 Communication
- **Email Notifications**: Automated email alerts for booking status changes
- **SMTP Integration**: PHPMailer integration for reliable email delivery
- **Support System**: Built-in support request management

### 📊 Analytics & Reporting
- **Booking Analytics**: Comprehensive booking statistics and reports
- **User Management**: Admin tools for user account management
- **Booking History**: Complete booking history and audit trail
- **Receipt Generation**: Printable booking receipts

### 🤖 Additional Features
- **Chatbot Integration**: AI-powered support chatbot
- **Responsive Design**: Mobile-friendly Bootstrap-based interface
- **Real-time Updates**: Dynamic booking status updates
- **Export Capabilities**: Data export functionality

## 🛠️ Technology Stack

- **Backend**: PHP 8.2+
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Email**: PHPMailer 6.8+
- **Server**: Apache/Nginx with XAMPP support
- **Security**: CSRF protection, SQL injection prevention, XSS protection

## 📋 Prerequisites

Before installing the SFB system, ensure you have:

- **Web Server**: XAMPP, WAMP, or similar local server environment
- **PHP**: Version 8.2 or higher
- **MySQL**: Version 5.7 or higher
- **Composer**: For dependency management
- **SMTP Server**: For email notifications (Gmail recommended)

## 🚀 Installation

### Step 1: Clone/Download the Project
```bash
# If using Git
git clone [repository-url]
cd sfb

# Or download and extract the ZIP file
```

### Step 2: Database Setup
1. **Create Database**:
   ```sql
   CREATE DATABASE sfb_db;
   ```

2. **Import Database Schema**:
   - Open phpMyAdmin or your MySQL client
   - Select the `sfb_db` database
   - Import the `database/sfb_db.sql` file

### Step 3: Configure Database Connection
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'sfb_db');
```

### Step 4: Install Dependencies
```bash
composer install
```

### Step 5: Email Configuration
Update email settings in relevant files (e.g., `manage_faculty_approvals.php`):
```php
$mail->Host = 'smtp.gmail.com';
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';
```

### Step 6: File Permissions
Ensure the following directories are writable:
- `assets/`
- `img/`
- `error.log`

### Step 7: Access the Application
1. Start your web server (XAMPP/WAMP)
2. Navigate to `http://localhost/sfb/`
3. Use the default admin credentials or register a new account

## 👥 User Roles & Permissions

### 🔧 Administrator
- **Full System Access**: Manage all aspects of the system
- **User Management**: Create, edit, delete user accounts
- **Facility Management**: Add, edit, remove facilities
- **Booking Approval**: Approve/reject booking requests
- **Faculty Approval**: Approve faculty registrations
- **System Analytics**: View comprehensive reports and statistics
- **Support Management**: Handle support requests

### 👨‍🏫 Faculty
- **Booking Creation**: Create facility booking requests
- **Booking Management**: View, edit, cancel own bookings
- **Profile Management**: Update personal information
- **Booking History**: View past and current bookings

### 👨‍💼 Staff
- **Limited Booking Access**: Create bookings with restrictions
- **Profile Management**: Update personal information
- **Booking History**: View own booking history

## 📱 Usage Guide

### For Administrators

#### Managing Users
1. Navigate to **Manage Users** from the admin dashboard
2. View all registered users with their roles and status
3. Edit user information or change account status
4. Approve pending faculty registrations

#### Managing Facilities
1. Go to **Manage Facilities** in the admin panel
2. Add new facilities with capacity and location details
3. Edit existing facility information
4. Set facility status (available/maintenance/reserved)

#### Approving Bookings
1. Access **Booking Management** from the dashboard
2. Review pending booking requests
3. Check for conflicts and facility availability
4. Approve or reject bookings with comments

### For Faculty/Staff

#### Creating a Booking
1. Log in to your account
2. Navigate to **Book Facility**
3. Select facility, date, and time
4. Enter purpose and attendee count
5. Submit booking request

#### Managing Bookings
1. View **My Bookings** to see all your reservations
2. Edit booking details (if still pending)
3. Cancel bookings (if allowed)
4. View booking status and approval status

## 🔧 Configuration

### Email Settings
Update SMTP settings in files that send emails:
- `manage_faculty_approvals.php`
- `send_support_email.php`
- `forgot_password.php`

### Database Configuration
Modify `config/database.php` for your database settings:
```php
define('DB_HOST', 'your_host');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'sfb_db');
```

### Security Settings
- Update CSRF token generation in relevant files
- Configure session timeout settings
- Set up proper file permissions

## 📊 Database Schema

### Core Tables
- **users**: User accounts and authentication
- **facilities**: Facility information and availability
- **bookings**: Booking records and status
- **support_requests**: Support ticket management
- **booking_logs**: Audit trail for bookings
- **system_logs**: System activity logging

### Key Relationships
- Users can have multiple bookings
- Facilities can have multiple bookings
- Bookings are linked to users and facilities
- Support requests are linked to users

## 🚨 Security Features

- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: Input sanitization and output escaping
- **CSRF Protection**: Token-based request validation
- **Session Security**: Secure session management
- **Password Hashing**: Secure password storage
- **Input Validation**: Comprehensive input validation

## 📝 API Endpoints

The system includes various AJAX endpoints for dynamic functionality:
- `check_conflicts.php`: Booking conflict detection
- `get_booking_details.php`: Retrieve booking information
- `process_booking.php`: Handle booking submissions
- `send_support_email.php`: Support request processing

## 🔄 Maintenance

### Regular Tasks
- **Database Backup**: Regular database backups
- **Log Rotation**: Manage error and system logs
- **Email Queue**: Monitor email delivery status
- **Past Bookings**: Clean up completed bookings

### Cron Jobs
Set up automated tasks:
```bash
# Check and cancel past bookings
0 * * * * php /path/to/sfb/cron_check_past_bookings.php

# Process email queue
*/5 * * * * php /path/to/sfb/process_email_queue.php
```

## 🐛 Troubleshooting

### Common Issues

#### Database Connection Error
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check database exists and is accessible

#### Email Not Sending
- Verify SMTP settings in email-related files
- Check Gmail app password configuration
- Ensure SMTP port 587 is open

#### Booking Conflicts
- Check facility availability settings
- Verify booking time calculations
- Review conflict detection logic

#### Session Issues
- Check PHP session configuration
- Verify session storage permissions
- Clear browser cookies if needed

### Error Logs
Check the following files for error information:
- `error.log`: Application errors
- Apache/Nginx error logs
- PHP error logs

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support and questions:
- Create an issue in the repository
- Contact the development team
- Check the documentation

## 🔄 Version History

- **v1.0.0**: Initial release with core booking functionality
- **v1.1.0**: Added email notifications and support system
- **v1.2.0**: Enhanced security features and user management
- **v1.3.0**: Added analytics and reporting features

---

**Developed by Raymart Dave Silvosa❤️ for educational institutions** 
