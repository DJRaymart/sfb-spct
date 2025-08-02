<?php


define('SITE_NAME', 'School Facility Reservation System');

// Replace these with your actual reCAPTCHA keys from https://www.google.com/recaptcha/admin
define('RECAPTCHA_SITE_KEY', '6LfTpAYrAAAAABiUVa3xkb2aBOVnd8l7cz0SgOWr');
define('RECAPTCHA_SECRET_KEY', '6LfTpAYrAAAAAPu9iFIoT3ot_cYg3N4Ckt5M2we1');

define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRES_SPECIAL', true);
define('PASSWORD_REQUIRES_NUMBER', true);
define('PASSWORD_REQUIRES_UPPERCASE', true);

// SMTP Configuration
// For production, these should be set as environment variables
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'schoolfacilitybooking@gmail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'wzwx fdiq yaeu hkel');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587); // Use TLS port for better reliability
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'schoolfacilitybooking@gmail.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'School Facility Reservation System');

define('DEPARTMENTS', [
    'Computer Science',
    'Mathematics',
    'Physics',
    'Chemistry',
    'Biology',
    'Engineering',
    'Business Administration',
    'Social Sciences',
    'Arts and Humanities',
    'Physical Education'
]);
?> 