<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/EmailNotification.php';

header('Content-Type: application/json');

try {
    // Initialize auth first
    $auth = new Auth($conn);
    
    // Allow all authenticated users for testing (remove admin restriction)
    if (!$auth->isLoggedIn()) {
        throw new Exception('Access denied. Please log in to use email support.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate required fields
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        throw new Exception('All fields are required');
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Sanitize inputs
    $name = htmlspecialchars($name);
    $email = htmlspecialchars($email);
    $subject = htmlspecialchars($subject);
    $message = htmlspecialchars($message);

    // Get user info if logged in
    $userInfo = '';
    if ($auth->isLoggedIn()) {
        $currentUser = $auth->getCurrentUser();
        $userInfo = "User ID: " . $currentUser['id'] . "\nUsername: " . $currentUser['username'] . "\nRole: " . $currentUser['role'] . "\n\n";
    }

    // Create email content
    $emailContent = "
Dear Administrator,

A new support request has been submitted through the School Facility Reservation System.

**Request Details:**
- Name: $name
- Email: $email
- Subject: $subject
- Date: " . date('Y-m-d H:i:s') . "

**User Information:**
$userInfo

**Message:**
$message

---
This email was sent from the School Facility Reservation System Support Form.
Please respond to the user at: $email
    ";

    // Send email to admin
    $emailNotification = new EmailNotification();
    $adminEmail = 'schoolfacilitybooking@gmail.com'; // Replace with actual admin email
    
    $emailSent = $emailNotification->sendEmail(
        $adminEmail,
        'Support Request: ' . $subject,
        $emailContent,
        $name
    );

    if (!$emailSent) {
        error_log("Failed to send support email to admin. From: $name ($email), Subject: $subject");
        throw new Exception('Failed to send email to administrator. This could be due to server configuration issues. Please try again later or contact support directly.');
    }

    // Send confirmation email to user
    $confirmationContent = "
Dear $name,

Thank you for contacting the School Facility Reservation System support team.

We have received your message regarding: $subject

**Your Message:**
$message

We will review your request and respond within 24-48 hours during business days.

If this is an urgent matter, please contact the IT department directly.

Best regards,
School Facility Reservation System Support Team
    ";

    $emailNotification->sendEmail(
        $email,
        'Support Request Received - School Facility Reservation System',
        $confirmationContent,
        'School Facility Reservation System'
    );

    // Save support request to database for Support Management
    try {
        // Get current user ID if logged in
        $user_id = null;
        if ($auth->isLoggedIn()) {
            $currentUser = $auth->getCurrentUser();
            $user_id = $currentUser['id'];
        }
        
        // Insert into support_requests table
        $stmt = $conn->prepare("
            INSERT INTO support_requests (user_id, name, email, subject, message, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$user_id, $name, $email, $subject, $message]);
        
        $support_request_id = $conn->lastInsertId();
        error_log("Support request saved to database with ID: $support_request_id");
        
        // Also log to system_logs for backward compatibility
        $stmt = $conn->prepare("
            INSERT INTO system_logs (action, details, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([
            'support_request',
            "Support request #$support_request_id from $name ($email) - Subject: $subject"
        ]);
        
    } catch (Exception $e) {
        // Log error but don't fail the email sending
        error_log("Failed to save support request to database: " . $e->getMessage());
        
        // Try to create the table if it doesn't exist
        try {
            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS support_requests (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NULL,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    subject VARCHAR(500) NOT NULL,
                    message TEXT NOT NULL,
                    status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
                    admin_reply TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                )
            ";
            $conn->exec($createTableSQL);
            error_log("Created support_requests table");
            
            // Retry saving the support request
            $stmt = $conn->prepare("
                INSERT INTO support_requests (user_id, name, email, subject, message, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $name, $email, $subject, $message]);
            $support_request_id = $conn->lastInsertId();
            error_log("Support request saved to database with ID: $support_request_id (after table creation)");
            
        } catch (Exception $e2) {
            error_log("Failed to create support_requests table and save request: " . $e2->getMessage());
        }
    }

    // Prepare success response
    $response = [
        'success' => true,
        'message' => 'Your support request has been submitted successfully. You will receive a confirmation email shortly and can track its status in Support Management.'
    ];
    
    // Add support request ID if available
    if (isset($support_request_id)) {
        $response['support_request_id'] = $support_request_id;
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    // Enhanced error logging
    $error_context = [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s'),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    error_log("SUPPORT EMAIL ERROR: " . json_encode($error_context));
    
    // Provide user-friendly error messages
    $userMessage = $e->getMessage();
    if (strpos($e->getMessage(), 'SMTP') !== false || strpos($e->getMessage(), 'Failed to connect') !== false) {
        $userMessage = 'Email server connection failed. Please try again in a few minutes.';
    } elseif (strpos($e->getMessage(), 'Invalid email') !== false) {
        $userMessage = 'Please check that your email address is correct and try again.';
    } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
        $userMessage = 'Access denied. Please make sure you are logged in as a student or faculty member.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $userMessage,
        'debug' => $e->getMessage() // Temporary debug info
    ]);
}
?> 