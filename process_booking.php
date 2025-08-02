<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Booking.php';


try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid form submission. Please try again.');
    }

    $auth = new Auth($conn);
    $bookingManager = new Booking($conn);
    $isAdmin = $auth->isAdmin();

    $required_fields = ['facility_id', 'start_time', 'end_time', 'purpose', 'attendees_count'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $facility_id = (int)$_POST['facility_id'];
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    $attendees_count = (int)$_POST['attendees_count'];
    $purpose = trim($_POST['purpose']);


    if (empty($facility_id) || empty($start_time) || empty($end_time) || empty($purpose) || empty($attendees_count)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required.'
        ]);
        exit();
    }

    // Validate attendees count
    if ($attendees_count <= 0 || $attendees_count > 1000) {
        echo json_encode([
            'success' => false,
            'message' => 'Attendees count must be between 1 and 1000.'
        ]);
        exit();
    }

    $start = new DateTime($start_time);
    $end = new DateTime($end_time);
    $now = new DateTime();

    if ($start < $now) {
        echo json_encode([
            'success' => false,
            'message' => 'Start time cannot be in the past.'
        ]);
        exit();
    }

    if ($end <= $start) {
        echo json_encode([
            'success' => false,
            'message' => 'End time must be after start time.'
        ]);
        exit();
    }

    // Check if booking is on Sunday
    $startDay = $start->format('N'); // 1 (Monday) through 7 (Sunday)
    if ($startDay == 7) {
        echo json_encode([
            'success' => false,
            'message' => 'Bookings are not allowed on Sundays.'
        ]);
        exit();
    }

    // Check if booking time is within allowed hours (7 AM to 8 PM)
    $startHour = (int)$start->format('H');
    $startMinute = (int)$start->format('i');
    $endHour = (int)$end->format('H');
    $endMinute = (int)$end->format('i');
    
    // Start time must be at or after 7:00 AM
    if ($startHour < 7) {
        echo json_encode([
            'success' => false,
            'message' => 'Bookings cannot start before 7:00 AM.'
        ]);
        exit();
    }
    
    // End time must be at or before 8:00 PM (20:00)
    if ($endHour > 20) {
        echo json_encode([
            'success' => false,
            'message' => 'Bookings must end by 8:00 PM.'
        ]);
        exit();
    }

    // Check if booking duration exceeds 5 hours
    $duration = $end->diff($start);
    $totalHours = $duration->h + ($duration->days * 24);
    if ($totalHours > 5) {
        echo json_encode([
            'success' => false,
            'message' => 'Bookings cannot exceed 5 hours.'
        ]);
        exit();
    }

    $stmt = $conn->prepare("SELECT capacity FROM facilities WHERE id = ?");
    $stmt->execute([$facility_id]);
    $facility = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$facility) {
        echo json_encode([
            'success' => false,
            'message' => 'Facility not found.'
        ]);
        exit();
    }

    if ($attendees_count > $facility['capacity']) {
        echo json_encode([
            'success' => false,
            'message' => "Number of attendees ($attendees_count) exceeds facility capacity ({$facility['capacity']})."
        ]);
        exit();
    }

    // Allow bookings with at least 1 day advance notice for students/faculty
    if (!$isAdmin && $start < $now->modify('+1 day')) {
        echo json_encode([
            'success' => false,
            'message' => 'Booking must be at least 1 day in advance for non-admin users'
        ]);
        exit();
    }

    $initial_status = $isAdmin ? 'approved' : 'pending';

    // Begin transaction
    $conn->beginTransaction();

    $result = $bookingManager->createBooking(
        $facility_id,
        $_SESSION['user_id'],
        $start_time,
        $end_time,
        $attendees_count,
        $purpose,
        null,
        $initial_status
    );

    if (!$result['success']) {
        $conn->rollBack();
        throw new Exception($result['message'] ?? 'Failed to create booking');
    }
    
    $booking_id = $result['booking_id'];
    

    
    // Commit transaction
    $conn->commit();

    $message = $isAdmin ? 'Booking created and automatically approved.' : 'Booking request submitted successfully! Waiting for admin approval.';

    echo json_encode([
        'success' => true,
        'message' => $message,
        'booking_id' => $booking_id
    ]);

} catch (Exception $e) {
    // Roll back the transaction if there was an error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Booking creation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 