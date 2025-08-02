<?php
require_once 'includes/EmailNotification.php';

try {
    $emailer = new EmailNotification();
    $result = $emailer->sendBookingReceipt(
        'admin@school.com',
        '<h1>Test Email</h1><p>This is a test email to verify the SMTP connection.</p>'
    );
    
    if ($result) {
        echo "Email sent successfully!";
    } else {
        echo "Failed to send email. Check the error log for details.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 