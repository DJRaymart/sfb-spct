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

    // 1. Check user permissions - only admin can update booking dates
    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        throw new Exception('Unauthorized access. Only administrators can change booking dates');
    }

    // 2. Validate request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // 3. Validate required parameters
    if (!isset($_POST['booking_id']) || !isset($_POST['start_time']) || !isset($_POST['end_time']) || !isset($_POST['admin_note'])) {
        throw new Exception('Missing required parameters');
    }

    $booking_id = $_POST['booking_id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $admin_note = $_POST['admin_note'];

    // 4. Fetch original booking information
    $bookingDetails = $bookingManager->getBooking($booking_id);
    if (!$bookingDetails) {
        throw new Exception('Booking not found');
    }

    // 5. Check if booking is in a state that can be modified
    if ($bookingDetails['status'] !== 'approved') {
        throw new Exception('Only approved bookings can have their dates changed');
    }

    // 6. Validate time parameters
    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $now = new DateTime();

    // Check if the new dates are in the past
    if ($start <= $now) {
        throw new Exception('Start time cannot be in the past or present');
    }

    if ($end <= $now) {
        throw new Exception('End time cannot be in the past or present');
    }

    if ($end <= $start) {
        throw new Exception('End time must be after start time');
    }

    // Check if booking time is within allowed hours (7 AM to 8 PM)
    $startHour = (int)$start->format('H');
    $startMinute = (int)$start->format('i');
    $endHour = (int)$end->format('H');
    $endMinute = (int)$end->format('i');
    
    if ($startHour < 7 || ($startHour === 7 && $startMinute < 0) || 
        $endHour > 20 || ($endHour === 20 && $endMinute > 0)) {
        throw new Exception('Bookings are only allowed between 7:00 AM and 8:00 PM.');
    }

    // 7. Check for conflicts
    if ($bookingManager->checkScheduleConflict($bookingDetails['facility_id'], $start_time, $end_time, $booking_id)) {
        throw new Exception('Selected time slot conflicts with another booking');
    }

    // 8. All validations passed, update the booking
    $conn->beginTransaction();

    // Update booking
    $updateStmt = $conn->prepare("
        UPDATE bookings 
        SET start_time = :start_time, 
            end_time = :end_time,
            updated_at = NOW()
        WHERE id = :booking_id
    ");
    
    $updateStmt->execute([
        'start_time' => $start_time,
        'end_time' => $end_time,
        'booking_id' => $booking_id
    ]);

    // Create notification
    $notification = "Your booking #{$booking_id} for {$bookingDetails['facility_name']} has been rescheduled by an administrator. New schedule: " . 
                    date('M d, Y h:i A', strtotime($start_time)) . " to " . 
                    date('M d, Y h:i A', strtotime($end_time)) . ". Reason: {$admin_note}";

    $notificationStmt = $conn->prepare("
        INSERT INTO notifications (user_id, booking_id, message, type) 
        VALUES (:user_id, :booking_id, :message, 'system')
    ");
    
    $notificationStmt->execute([
        'user_id' => $bookingDetails['user_id'],
        'booking_id' => $booking_id,
        'message' => $notification
    ]);

    $conn->commit();

    // 9. Send email notification to user
    try {
        // Get user email
        $userStmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $userStmt->execute([$bookingDetails['user_id']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Send email directly but safely to avoid JSON corruption
            $emailNotification = new EmailNotification();
            
            // Use output buffering to capture any unexpected output
            ob_start();
            $emailSent = @$emailNotification->sendBookingDateChangeNotification(
                $user['email'],
                $user['full_name'],
                $bookingDetails['facility_name'],
                $bookingDetails['start_time'],
                $bookingDetails['end_time'],
                $start_time,
                $end_time,
                $admin_note
            );
            $emailOutput = ob_get_contents();
            ob_end_clean();
            
            // Discard any output from email sending
            if (!empty($emailOutput)) {
                error_log("Email output captured and discarded: " . $emailOutput);
            }
            
            if ($emailSent) {
                error_log("✅ Date change email sent successfully for booking ID: $booking_id");
            } else {
                error_log("❌ Date change email failed for booking ID: $booking_id");
            }
        }
    } catch (Exception $emailError) {
        // Clean any output from failed email attempt
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Log email error but continue with the response
        error_log("Email notification error: " . $emailError->getMessage());
    }

    // 10. Return success
    // Clean any unexpected output before sending JSON
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking date updated successfully',
        'booking' => [
            'id' => $booking_id,
            'start_time' => $start_time,
            'end_time' => $end_time
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Update booking date error: " . $e->getMessage());
    
    // Clean any unexpected output before sending JSON
    ob_clean();
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 