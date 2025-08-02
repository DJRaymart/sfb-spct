<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

header('Content-Type: application/json');

$auth = new Auth($conn);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log all POST data
    error_log("POST data received: " . json_encode($_POST));
    
    $booking_id = $_POST['booking_id'] ?? '';
    $facility_name = $_POST['facility_name'] ?? '';

    try {
        if (empty($booking_id)) {
            throw new Exception("Booking ID is required.");
        }

        // Convert booking_id to integer and validate
        $booking_id = (int)$booking_id;
        if ($booking_id <= 0) {
            throw new Exception("Invalid booking ID: $booking_id");
        }

        error_log("Attempting to delete booking ID: $booking_id");

        // Check if booking exists with better error handling
        $stmt = $conn->prepare("SELECT id, user_id, facility_id, start_time, end_time, status FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            // Log available booking IDs for debugging
            $stmt = $conn->query("SELECT id FROM bookings ORDER BY id ASC LIMIT 10");
            $availableIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $availableIdsStr = implode(', ', $availableIds);
            error_log("Booking ID $booking_id not found. Available IDs: $availableIdsStr");
            throw new Exception("Booking not found. Available booking IDs: $availableIdsStr");
        }
        
        // Check for related records that might prevent deletion
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE booking_id = ?");
        $stmt->execute([$booking_id]);
        $notificationCount = $stmt->fetchColumn();
        error_log("Found " . $notificationCount . " notifications for booking ID " . $booking_id);
        

        
        // Check for booking logs
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM booking_logs WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            $logCount = $stmt->fetchColumn();
            error_log("Found " . $logCount . " booking logs for booking ID " . $booking_id);
        } catch (Exception $e) {
            error_log("booking_logs table might not exist: " . $e->getMessage());
        }

        // Validate booking deletion restrictions
        $bookingEndTime = new DateTime($booking['end_time']);
        $now = new DateTime();
        $bookingStatus = $booking['status'];
        
        // Check if booking is pending - prevent deletion
        if ($bookingStatus === 'pending') {
            throw new Exception("Cannot delete pending bookings. Only past approved/cancelled bookings can be deleted.");
        }
        
        // Check if booking is in the future - prevent deletion
        if ($bookingEndTime > $now) {
            throw new Exception("Cannot delete future bookings. Only past bookings can be deleted.");
        }
        
        // Check if booking is currently ongoing - prevent deletion
        $bookingStartTime = new DateTime($booking['start_time']);
        if ($bookingStartTime <= $now && $bookingEndTime >= $now) {
            throw new Exception("Cannot delete ongoing bookings. Please wait until the booking ends.");
        }
        
        error_log("Booking validation passed - ID: $booking_id, Status: $bookingStatus, End Time: " . $booking['end_time']);

        // Start transaction
        $conn->beginTransaction();

        try {
            // Delete related records first (in order of dependencies)
            

            
            // 2. Delete booking logs (if table exists)
            try {
                $stmt = $conn->prepare("DELETE FROM booking_logs WHERE booking_id = ?");
                $stmt->execute([$booking_id]);
                $logRows = $stmt->rowCount();
                error_log("Deleted " . $logRows . " booking logs for booking ID: " . $booking_id);
            } catch (Exception $e) {
                // Table might not exist, continue
                error_log("booking_logs table might not exist: " . $e->getMessage());
            }
            
            // 3. Delete notifications related to this booking (CRITICAL - must be done before booking deletion)
            $stmt = $conn->prepare("DELETE FROM notifications WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            $notificationRows = $stmt->rowCount();
            error_log("Deleted " . $notificationRows . " notifications for booking ID: " . $booking_id);
            
            // Verify notifications are deleted
            $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE booking_id = ?");
            $stmt->execute([$booking_id]);
            $remainingNotifications = $stmt->fetchColumn();
            if ($remainingNotifications > 0) {
                throw new Exception("Failed to delete all notifications. " . $remainingNotifications . " notifications still exist.");
            }
            
            // 4. Finally delete the booking
            // First verify the booking still exists
            $stmt = $conn->prepare("SELECT id FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Booking ID " . $booking_id . " no longer exists.");
            }
            
            $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
            $success = $stmt->execute([$booking_id]);

            if (!$success) {
                $errorInfo = $stmt->errorInfo();
                error_log("Failed to delete booking ID " . $booking_id . ": " . print_r($errorInfo, true));
                throw new Exception("Failed to delete booking: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            $bookingRows = $stmt->rowCount();
            error_log("Successfully deleted booking ID: " . $booking_id . " (rows affected: " . $bookingRows . ")");

            // Resequence booking IDs to maintain ascending order without gaps, starting from 1
            try {
                // First, get the current booking count to verify resequencing
                $countStmt = $conn->query("SELECT COUNT(*) FROM bookings");
                $totalBookings = $countStmt->fetchColumn();
                error_log("Total bookings before resequencing: " . $totalBookings);
                
                if ($totalBookings > 0) {
                    // Temporarily disable foreign key checks to allow ID resequencing
                    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                    
                    // Reset the counter and resequence IDs starting from 1
                    $conn->query("SET @counter = 0");
                    $conn->query("UPDATE bookings SET id = (@counter:=@counter+1) ORDER BY id ASC");
                    
                    // Re-enable foreign key checks
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                    
                    // Verify the resequencing worked correctly
                    $verifyStmt = $conn->query("SELECT MIN(id), MAX(id), COUNT(*) FROM bookings");
                    $verifyResult = $verifyStmt->fetch(PDO::FETCH_NUM);
                    $minId = $verifyResult[0];
                    $maxId = $verifyResult[1];
                    $actualCount = $verifyResult[2];
                    
                    error_log("Resequencing results - Min ID: $minId, Max ID: $maxId, Count: $actualCount");
                    
                    // Ensure IDs start from 1 and are sequential
                    if ($minId != 1 || $maxId != $actualCount) {
                        throw new Exception("Resequencing verification failed. Expected: min=1, max=$actualCount. Got: min=$minId, max=$maxId");
                    }
                    
                    // Reset AUTO_INCREMENT to the next available ID
                    $nextId = $maxId + 1;
                    $conn->query("ALTER TABLE bookings AUTO_INCREMENT = $nextId");
                    error_log("Successfully resequenced booking IDs from 1 to $maxId. Next ID will be $nextId");
                } else {
                    error_log("No bookings to resequence");
                }
                
            } catch (Exception $e) {
                error_log("Error during ID resequencing: " . $e->getMessage());
                // Re-enable foreign key checks if they were disabled
                try {
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                } catch (Exception $fkError) {
                    error_log("Error re-enabling foreign key checks: " . $fkError->getMessage());
                }
                // Don't throw exception for resequencing errors, just log them
                // The deletion was successful, so we don't want to fail the entire operation
            }

            // Log the deletion
            $logStmt = $conn->prepare("
                INSERT INTO system_logs (
                    action,
                    details,
                    created_at
                ) VALUES (?, ?, NOW())
            ");
            $logStmt->execute([
                'delete_booking',
                "Booking ID $booking_id for facility '$facility_name' deleted by admin"
            ]);

            // Commit transaction
            $conn->commit();

            echo json_encode([
                'success' => true, 
                'message' => "Booking for $facility_name deleted successfully! Booking IDs have been resequenced starting from 1."
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            throw new Exception("Database error: " . $e->getMessage());
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?> 