<?php
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Booking.php';

$auth = new Auth($conn);
$bookingManager = new Booking($conn);

try {
    // 1. Cancel pending bookings that are past their start time
    $stmt = $conn->prepare("
        SELECT id, start_time, facility_id, user_id 
        FROM bookings 
        WHERE status = 'pending' 
        AND start_time < NOW()
    ");
    $stmt->execute();
    $pastPendingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cancelledCount = 0;
    foreach ($pastPendingBookings as $booking) {
        $updateStmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'cancelled', 
                cancellation_reason = 'Automatically cancelled due to past date' 
            WHERE id = ?
        ");
        $updateStmt->execute([$booking['id']]);
        $cancelledCount++;

        // Log to error log instead of database
        error_log("Auto-cancelled booking ID {$booking['id']} due to past date");
    }

    // 2. Complete approved bookings that are past their end time
    $stmt = $conn->prepare("
        SELECT id, start_time, end_time, facility_id, user_id 
        FROM bookings 
        WHERE status = 'approved' 
        AND end_time < NOW()
    ");
    $stmt->execute();
    $pastApprovedBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $completedCount = 0;
    foreach ($pastApprovedBookings as $booking) {
        $updateStmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'completed', 
                updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$booking['id']]);
        $completedCount++;

        // Log to error log instead of database
        error_log("Auto-completed booking ID {$booking['id']} due to past end time");
    }

    // Log to error log instead of database
    error_log("Cron job completed: Cancelled $cancelledCount pending bookings, completed $completedCount approved bookings");

} catch (Exception $e) {
    error_log("Cron job error: " . $e->getMessage());
} 