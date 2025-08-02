<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Booking.php';

$auth = new Auth($conn);
$bookingManager = new Booking($conn);

try {
    $stmt = $conn->prepare("
        SELECT id, start_time, facility_id, user_id 
        FROM bookings 
        WHERE status = 'pending' 
        AND start_time < NOW()
    ");
    $stmt->execute();
    $pastBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pastBookings as $booking) {
        $updateStmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'cancelled', 
                cancellation_reason = 'Automatically cancelled due to past date' 
            WHERE id = ?
        ");
        $updateStmt->execute([$booking['id']]);

        $logStmt = $conn->prepare("
            INSERT INTO booking_logs (
                booking_id, 
                action, 
                action_by, 
                details
            ) VALUES (?, 'auto_cancel', 'system', ?)
        ");
        $logStmt->execute([
            $booking['id'],
            'Booking automatically cancelled due to past date'
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => count($pastBookings) . ' past bookings automatically cancelled'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error processing automatic cancellations: ' . $e->getMessage()
    ]);
} 