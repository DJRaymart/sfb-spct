<?php
session_start();
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/Auth.php';
require_once 'includes/Booking.php';
require_once 'vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

header('Content-Type: application/json');

try {
    if (!isset($_POST['id'])) {
        throw new Exception('Booking ID is required');
    }

    // Make cancel_reason optional for backward compatibility
    $booking_id = $_POST['id'];
    $cancel_reason = $_POST['cancel_reason'] ?? 'Cancelled by user';

    $auth = new Auth($conn);
    $booking = new Booking($conn);

    if (!$auth->isLoggedIn()) {
        throw new Exception('User not logged in');
    }

    $current_user = $auth->getCurrentUser();
    $user_id = $current_user['id'];
    $is_admin = $auth->isAdmin();

    $stmt = $conn->prepare("
        SELECT b.*, f.name as facility_name, u.email as user_email, u.username as user_name 
        FROM bookings b 
        JOIN facilities f ON b.facility_id = f.id 
        JOIN users u ON b.user_id = u.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $bookingDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bookingDetails) {
        throw new Exception('Booking not found');
    }

    // Check if user has permission to cancel this booking
    if (!$is_admin && $bookingDetails['user_id'] != $user_id) {
        throw new Exception('You can only cancel your own bookings');
    }

    if ($bookingDetails['status'] === 'cancelled') {
        throw new Exception('This booking is already cancelled');
    }

    $bookingStart = strtotime($bookingDetails['start_time']);
    $now = time();
    if ($bookingStart < $now) {
        throw new Exception('Cannot cancel a booking that has already passed');
    }

    $conn->beginTransaction();

    try {
        $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$booking_id]);

        // Determine who gets notified based on who cancelled
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465; // Use SSL port
            $mail->SMTPDebug = 0;
            $mail->Timeout = 60;
            
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            ); 

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->isHTML(true);
            
            $start_time = date('F j, Y g:i A', strtotime($bookingDetails['start_time']));
            $end_time = date('F j, Y g:i A', strtotime($bookingDetails['end_time']));
            
            if ($is_admin) {
                // Admin cancelled user's booking - notify the user
                $mail->addAddress($bookingDetails['user_email'], $bookingDetails['user_name']);
                $mail->Subject = "Your Booking Has Been Cancelled";
                
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background-color: #f8f9fa; }
                        .footer { text-align: center; padding: 20px; color: #6c757d; }
                        .details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Booking Cancellation Notice</h2>
                        </div>
                        <div class='content'>
                            <p>Dear {$bookingDetails['user_name']},</p>
                            <p>Your booking has been cancelled by the administrator.</p>
                            
                            <div class='details'>
                                <h3>Booking Details:</h3>
                                <p><strong>Facility:</strong> {$bookingDetails['facility_name']}</p>
                                <p><strong>Date & Time:</strong> {$start_time} to {$end_time}</p>
                                <p><strong>Purpose:</strong> {$bookingDetails['purpose']}</p>
                                <p><strong>Cancellation Reason:</strong> {$cancel_reason}</p>
                            </div>
                            
                            <p>If you have any questions, please contact the administrator.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
            } else {
                // User cancelled their own booking - notify the admin
                $mail->addAddress('admin@sfrss.info', 'System Administrator');
                $mail->Subject = "Booking Cancelled by User - {$bookingDetails['facility_name']}";
                
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #fd7e14; color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background-color: #f8f9fa; }
                        .footer { text-align: center; padding: 20px; color: #6c757d; }
                        .details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                        .user-info { background-color: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>User Booking Cancellation</h2>
                        </div>
                        <div class='content'>
                            <p>Dear Administrator,</p>
                            <p>A user has cancelled their booking in the School Facility Reservation System.</p>
                            
                            <div class='user-info'>
                                <h3>User Information:</h3>
                                <p><strong>Name:</strong> {$bookingDetails['user_name']}</p>
                                <p><strong>Email:</strong> {$bookingDetails['user_email']}</p>
                            </div>
                            
                            <div class='details'>
                                <h3>Cancelled Booking Details:</h3>
                                <p><strong>Facility:</strong> {$bookingDetails['facility_name']}</p>
                                <p><strong>Date & Time:</strong> {$start_time} to {$end_time}</p>
                                <p><strong>Purpose:</strong> {$bookingDetails['purpose']}</p>
                                <p><strong>Cancellation Reason:</strong> {$cancel_reason}</p>
                                <p><strong>Cancelled On:</strong> " . date('F j, Y g:i A') . "</p>
                            </div>
                            
                            <p>The facility is now available for new bookings during this time slot.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message from the School Facility Reservation System.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
            }

            $mail->Body = $message;

            $emailSent = $mail->send();

            $conn->commit();

            error_log("Email sending attempt to {$bookingDetails['user_email']}: " . ($emailSent ? "Success" : "Failed"));

            ob_clean();
            
            $notification_target = $is_admin ? 'user' : 'administrator';
            echo json_encode([
                'success' => true,
                'message' => 'Booking cancelled successfully' . 
                            ($emailSent ? " and notification sent to $notification_target" : " but failed to send notification to $notification_target")
            ]);

        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            throw new Exception('Failed to send email notification: ' . $e->getMessage());
        }

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 