<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Booking.php';

try {
    $auth = new Auth($conn);
    $booking = new Booking($conn);

    if (!$auth->isLoggedIn()) {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_POST['facility_id']) || !isset($_POST['start_time']) || !isset($_POST['end_time'])) {
        throw new Exception('Missing required parameters');
    }

    $facility_id = $_POST['facility_id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $now = new DateTime();

    if ($start < $now) {
        throw new Exception('Start time cannot be in the past');
    }

    if ($end <= $start) {
        throw new Exception('End time must be after start time');
    }

    $hasConflict = $booking->checkScheduleConflict($facility_id, $start_time, $end_time);
    
    if ($hasConflict) {
        $conflictingBookings = $booking->getConflictingBookings($facility_id, $start_time, $end_time);
        $conflictDetails = array_map(function($conflict) {
            return sprintf(
                "%s - %s (Booked by %s)",
                date('M d, Y h:i A', strtotime($conflict['start_time'])),
                date('M d, Y h:i A', strtotime($conflict['end_time'])),
                $conflict['user_name']
            );
        }, $conflictingBookings);
        
        echo json_encode([
            'success' => true,
            'hasConflict' => true,
            'conflictDetails' => $conflictDetails
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'hasConflict' => false
        ]);
    }

} catch (Exception $e) {
    error_log("Conflict check error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 