<?php
// Clean any output buffers completely
while (ob_get_level()) {
    ob_end_clean();
}

// Start fresh output buffering
ob_start();

// Suppress all error output to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Booking.php';
require_once 'includes/EmailNotification.php';

try {
    $auth = new Auth($conn);
    $bookingManager = new Booking($conn);

    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        throw new Exception('Unauthorized access');
    }

    if (!isset($_POST['booking_id']) || !isset($_POST['status'])) {
        throw new Exception('Missing required parameters');
    }

    $booking_id = $_POST['booking_id'];
    $status = $_POST['status'];

    if ($status !== 'approved') {
        throw new Exception('Invalid status - only approved status is allowed');
    }

    if ($status === 'approved') {
        // Get the booking details first to validate the booking time
        $stmt = $conn->prepare("
            SELECT b.*, f.name as facility_name, u.email, u.full_name as user_name 
            FROM bookings b 
            JOIN facilities f ON b.facility_id = f.id 
            JOIN users u ON b.user_id = u.id 
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        $bookingData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bookingData) {
            throw new Exception('Booking not found');
        }

        // Check if booking is on Sunday
        $bookingDay = date('N', strtotime($bookingData['start_time']));
        if ($bookingDay == 7) {
            throw new Exception('Bookings cannot be scheduled on Sundays');
        }
        
        // Check if booking time is within allowed hours (7 AM to 8 PM)
        $startHour = (int)date('H', strtotime($bookingData['start_time']));
        $startMinute = (int)date('i', strtotime($bookingData['start_time']));
        $endHour = (int)date('H', strtotime($bookingData['end_time']));
        $endMinute = (int)date('i', strtotime($bookingData['end_time']));
        
        // Start time must be at or after 7:00 AM
        if ($startHour < 7) {
            throw new Exception('Bookings cannot start before 7:00 AM');
        }
        
        // End time must be at or before 8:00 PM (20:00)
        if ($endHour > 20) {
            throw new Exception('Bookings must end by 8:00 PM');
        }
    }

    if ($bookingData['status'] !== 'pending') {
        throw new Exception('Only pending bookings can be updated');
    }

    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ? AND status = 'pending'");
    $success = $stmt->execute([$status, $booking_id]);

    if (!$success) {
        throw new Exception('Failed to update booking status in database');
    }

    if ($status === 'approved') {
        try {
            // Send email directly but safely to avoid JSON corruption
            $emailNotification = new EmailNotification();
            
            // Use output buffering to capture any unexpected output
            ob_start();
            $emailSent = @$emailNotification->sendBookingConfirmation($bookingData['email'], $bookingData);
            $emailOutput = ob_get_contents();
            ob_end_clean();
            
            // Discard any output from email sending
            if (!empty($emailOutput)) {
                error_log("Email output captured and discarded: " . $emailOutput);
            }
            
            if ($emailSent) {
                error_log("✅ Email sent successfully for booking ID: $booking_id");
                $message = 'Booking status updated and notification email sent successfully';
            } else {
                error_log("❌ Email failed for booking ID: $booking_id");
                $message = 'Booking status updated but failed to send notification email';
            }
            
        } catch (Exception $e) {
            // Clean any output from failed email attempt
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            error_log("Email error for booking ID $booking_id: " . $e->getMessage());
            $message = 'Booking status updated but email notification failed';
        }
    } else {
        $message = 'Booking status updated successfully';
    }

    $stmt = $conn->prepare("
        SELECT b.*, f.name as facility_name, u.full_name as user_name 
        FROM bookings b 
        JOIN facilities f ON b.facility_id = f.id 
        JOIN users u ON b.user_id = u.id 
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    // Clean any unexpected output before sending JSON
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'booking' => $booking
    ]);
    exit();

} catch (Exception $e) {
    error_log("Booking status update error: " . $e->getMessage());
    
    // Clean any unexpected output before sending JSON
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
} 