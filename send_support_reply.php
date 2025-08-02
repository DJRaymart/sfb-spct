<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/EmailNotification.php';

header('Content-Type: application/json');

$auth = new Auth($conn);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $supportId = $_POST['support_id'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($supportId) || empty($subject) || empty($message)) {
        throw new Exception('All fields are required');
    }

    // Get support request details from support_requests table
    $stmt = $conn->prepare("
        SELECT name, email, subject, message, created_at
        FROM support_requests  
        WHERE id = ?
    ");
    $stmt->execute([$supportId]);
    $supportRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supportRequest) {
        throw new Exception('Support request not found');
    }

    // Extract user information directly from the database
    $userName = $supportRequest['name'];
    $userEmail = $supportRequest['email'];
    $originalSubject = $supportRequest['subject'];
    $originalMessage = $supportRequest['message'];
        
    // Create reply email content
    $replyContent = "
Dear $userName,

Thank you for contacting the School Facility Reservation System support team.

**Your Original Request:**
Subject: $originalSubject
Date: " . date('Y-m-d H:i:s', strtotime($supportRequest['created_at'])) . "
Message: $originalMessage

**Our Response:**
$message

If you have any further questions or need additional assistance, please don't hesitate to contact us again.

Best regards,
School Facility Reservation System Support Team
    ";

    // Send reply email
    $emailNotification = new EmailNotification();
    $emailSent = $emailNotification->sendEmail(
        $userEmail,
        'Re: ' . $subject,
        $replyContent,
        'School Facility Reservation System Support Team'
    );

    if (!$emailSent) {
        throw new Exception('Failed to send reply email');
    }

    // Update the support request with admin reply and change status to resolved
    $stmt = $conn->prepare("
        UPDATE support_requests 
        SET admin_reply = ?, status = 'resolved', updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$message, $supportId]);

    // Log the reply in system_logs for audit trail
    $stmt = $conn->prepare("
        INSERT INTO system_logs (action, details, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([
        'support_reply',
        "Reply sent to $userName ($userEmail) for support request ID: $supportId - Subject: $subject"
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Reply sent successfully to ' . $userName
    ]);

} catch (Exception $e) {
    error_log("Error sending support reply: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 