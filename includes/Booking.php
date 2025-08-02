<?php
class Booking {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    public function createBooking($facility_id, $user_id, $start_time, $end_time, $attendees_count, $purpose, $materials = null, $initial_status = 'pending') {
        try {
            // Use database-level constraint checking to prevent race conditions
            $stmt = $this->conn->prepare("
                INSERT INTO bookings (facility_id, user_id, start_time, end_time, attendees_count, purpose, status)
                SELECT :facility_id, :user_id, :start_time, :end_time, :attendees_count, :purpose, :status
                WHERE NOT EXISTS (
                    SELECT 1 FROM bookings 
                    WHERE facility_id = :facility_id 
                    AND status IN ('approved', 'pending')
                    AND (
                        (start_time BETWEEN :start_time AND :end_time)
                        OR (end_time BETWEEN :start_time AND :end_time)
                        OR (:start_time BETWEEN start_time AND end_time)
                    )
                )
            ");
            
            $result = $stmt->execute([
                'facility_id' => $facility_id,
                'user_id' => $user_id,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'attendees_count' => $attendees_count,
                'purpose' => $purpose,
                'status' => $initial_status
            ]);
            
            if ($result && $stmt->rowCount() > 0) {
                $booking_id = $this->conn->lastInsertId();
                $message = $initial_status === 'approved' ? 
                    'Your booking has been created and is approved.' : 
                    'Your booking request has been submitted and is pending approval.';
                $this->createNotification($user_id, $booking_id, $message, 'booking_confirmation');
                return ['success' => true, 'booking_id' => $booking_id];
            }
            return ['success' => false, 'message' => 'Time slot is already booked'];
        } catch(PDOException $e) {
            error_log("Booking creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    private function hasConflict($facility_id, $start_time, $end_time) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) FROM bookings 
            WHERE facility_id = :facility_id 
            AND status IN ('approved', 'pending')
            AND (
                (start_time BETWEEN :start_time AND :end_time)
                OR (end_time BETWEEN :start_time AND :end_time)
                OR (:start_time BETWEEN start_time AND end_time)
            )
        ");
        
        $stmt->execute([
            'facility_id' => $facility_id,
            'start_time' => $start_time,
            'end_time' => $end_time
        ]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    public function getBooking($booking_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT b.*, f.name as facility_name, u.full_name as user_name
                FROM bookings b
                JOIN facilities f ON b.facility_id = f.id
                JOIN users u ON b.user_id = u.id
                WHERE b.id = :booking_id
            ");
            
            $stmt->execute(['booking_id' => $booking_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get booking error: " . $e->getMessage());
            return null;
        }
    }
    
    public function updateBookingStatus($booking_id, $status, $admin_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE bookings 
                SET status = :status, updated_at = CURRENT_TIMESTAMP
                WHERE id = :booking_id
            ");
            
            $result = $stmt->execute([
                'status' => $status,
                'booking_id' => $booking_id
            ]);
            
            if ($result) {
                $booking = $this->getBooking($booking_id);
                $message = 'Your booking request has been approved.';
                
                $this->createNotification(
                    $booking['user_id'],
                    $booking_id,
                    $message,
                    'booking_confirmation'
                );
                
                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Update booking status error: " . $e->getMessage());
            return false;
        }
    }
    
    private function createNotification($user_id, $booking_id, $message, $type) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO notifications (user_id, booking_id, message, type)
                VALUES (:user_id, :booking_id, :message, :type)
            ");
            
            return $stmt->execute([
                'user_id' => $user_id,
                'booking_id' => $booking_id,
                'message' => $message,
                'type' => $type
            ]);
        } catch(PDOException $e) {
            error_log("Create notification error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getUserBookings($user_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT b.*, f.name as facility_name
                FROM bookings b
                JOIN facilities f ON b.facility_id = f.id
                WHERE b.user_id = :user_id
                ORDER BY b.start_time DESC
            ");
            
            $stmt->execute(['user_id' => $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get user bookings error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAllBookings() {
        try {
            // Check if user is admin first
            $stmt = $this->conn->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $isAdmin = $user && $user['role'] === 'admin';

            if ($isAdmin) {
                // Admin sees all bookings
                $sql = "
                    SELECT b.*, f.name as facility_name, u.full_name as user_name
                    FROM bookings b
                    JOIN facilities f ON b.facility_id = f.id
                    JOIN users u ON b.user_id = u.id
                    ORDER BY b.start_time DESC
                ";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute();
            } else {
                // Students and faculty only see approved bookings
                $sql = "
                    SELECT b.*, f.name as facility_name, u.full_name as user_name
                    FROM bookings b
                    JOIN facilities f ON b.facility_id = f.id
                    JOIN users u ON b.user_id = u.id
                    WHERE b.status = 'approved'
                    ORDER BY b.start_time DESC
                ";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute();
            }
            
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug output
            error_log("getAllBookings returned " . count($bookings) . " bookings for user " . $_SESSION['user_id'] . " (admin: " . ($isAdmin ? 'yes' : 'no') . ")");
            
            return $bookings;
        } catch(PDOException $e) {
            error_log("Get all bookings error: " . $e->getMessage());
            return [];
        }
    }
    
    public function autoCompleteBookings() {
        try {
            $totalUpdated = 0;
            
            // 1. Cancel pending bookings that are past their start time
            $stmt = $this->conn->prepare("
                UPDATE bookings 
                SET status = 'cancelled', 
                    cancellation_reason = 'Automatically cancelled due to past date',
                    updated_at = NOW() 
                WHERE status = 'pending' 
                AND start_time < NOW()
            ");
            
            $stmt->execute();
            $cancelledCount = $stmt->rowCount();
            $totalUpdated += $cancelledCount;
            
            if ($cancelledCount > 0) {
                error_log("Auto-cancelled $cancelledCount past pending bookings");
            }
            
            // 2. Complete approved bookings that have passed their end time
            $stmt = $this->conn->prepare("
                UPDATE bookings 
                SET status = 'completed', updated_at = NOW() 
                WHERE status = 'approved' 
                AND end_time < NOW()
            ");
            
            $stmt->execute();
            $completedCount = $stmt->rowCount();
            $totalUpdated += $completedCount;
            
            if ($completedCount > 0) {
                error_log("Auto-completed $completedCount past approved bookings");
            }
            
            return $totalUpdated;
        } catch(PDOException $e) {
            error_log("Auto complete bookings error: " . $e->getMessage());
            return 0;
        }
    }

    public function checkScheduleConflict($facility_id, $start_time, $end_time, $exclude_booking_id = null) {
        $sql = "SELECT COUNT(*) as conflict_count 
                FROM bookings 
                WHERE facility_id = :facility_id 
                AND status = 'approved'
                AND (
                    (:start_time BETWEEN start_time AND end_time)
                    OR (:end_time BETWEEN start_time AND end_time)
                    OR (start_time BETWEEN :start_time AND :end_time)
                )";
        
        if ($exclude_booking_id) {
            $sql .= " AND id != :exclude_booking_id";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':facility_id', $facility_id, PDO::PARAM_INT);
        $stmt->bindParam(':start_time', $start_time);
        $stmt->bindParam(':end_time', $end_time);
        
        if ($exclude_booking_id) {
            $stmt->bindParam(':exclude_booking_id', $exclude_booking_id, PDO::PARAM_INT);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['conflict_count'] > 0;
    }

    public function getConflictingBookings($facility_id, $start_time, $end_time) {
        $sql = "SELECT b.*, f.name as facility_name, u.full_name as user_name 
                FROM bookings b
                JOIN facilities f ON b.facility_id = f.id
                JOIN users u ON b.user_id = u.id
                WHERE b.facility_id = :facility_id 
                AND b.status = 'approved'
                AND (
                    (:start_time BETWEEN b.start_time AND b.end_time)
                    OR (:end_time BETWEEN b.start_time AND b.end_time)
                    OR (b.start_time BETWEEN :start_time AND :end_time)
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':facility_id', $facility_id, PDO::PARAM_INT);
        $stmt->bindParam(':start_time', $start_time);
        $stmt->bindParam(':end_time', $end_time);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthlyBookings() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    DATE_FORMAT(start_time, '%Y-%m') as month,
                    COUNT(*) as total_bookings,
                    LAG(COUNT(*)) OVER (ORDER BY DATE_FORMAT(start_time, '%Y-%m')) as prev_month_bookings
                FROM bookings
                WHERE start_time >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(start_time, '%Y-%m')
                ORDER BY month
            ");
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate percentage change
            foreach ($results as &$row) {
                if ($row['prev_month_bookings'] > 0) {
                    $row['percentage_change'] = round((($row['total_bookings'] - $row['prev_month_bookings']) / $row['prev_month_bookings']) * 100);
                } else {
                    $row['percentage_change'] = 0;
                }
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Error in getMonthlyBookings: " . $e->getMessage());
            return [];
        }
    }

    public function getFacilityUsage() {
        try {
            // Get total hours booked
            $stmt = $this->conn->query("
                SELECT 
                    SUM(TIMESTAMPDIFF(HOUR, start_time, end_time)) as total_hours,
                    COUNT(*) as total_bookings
                FROM bookings
                WHERE status = 'approved'
            ");
            $total = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get usage by facility
            $stmt = $this->conn->query("
                SELECT 
                    f.name,
                    COUNT(b.id) as usage_count,
                    SUM(TIMESTAMPDIFF(HOUR, b.start_time, b.end_time)) as total_hours
                FROM facilities f
                LEFT JOIN bookings b ON f.id = b.facility_id AND b.status = 'approved'
                GROUP BY f.id, f.name
                ORDER BY usage_count DESC
            ");
            
            $by_facility = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate average usage percentage
            $total_hours = $total['total_hours'] ?? 0;
            $total_bookings = $total['total_bookings'] ?? 0;
            $average_usage = $total_bookings > 0 ? round(($total_hours / ($total_bookings * 24)) * 100) : 0;
            
            return [
                'total_hours' => $total_hours,
                'total_bookings' => $total_bookings,
                'average_usage' => $average_usage,
                'by_facility' => $by_facility
            ];
        } catch (PDOException $e) {
            error_log("Error in getFacilityUsage: " . $e->getMessage());
            return [
                'total_hours' => 0,
                'total_bookings' => 0,
                'average_usage' => 0,
                'by_facility' => []
            ];
        }
    }

    public function getBookingStatusStats() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM bookings
                GROUP BY status
            ");
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats = [
                'total_count' => 0,
                'pending_count' => 0,
                'approved_count' => 0,
                'cancelled_count' => 0
            ];
            
            foreach ($results as $row) {
                $stats['total_count'] += $row['count'];
                $stats[$row['status'] . '_count'] = $row['count'];
            }
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Error in getBookingStatusStats: " . $e->getMessage());
            return [
                'total_count' => 0,
                'pending_count' => 0,
                'approved_count' => 0,
                'cancelled_count' => 0
            ];
        }
    }

    public function getPeakHours() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    HOUR(start_time) as hour,
                    COUNT(*) as booking_count
                FROM bookings
                WHERE status = 'approved'
                GROUP BY HOUR(start_time)
                ORDER BY hour
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getPeakHours: " . $e->getMessage());
            return [];
        }
    }

    public function getPopularFacilities() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    f.name,
                    COUNT(b.id) as booking_count
                FROM facilities f
                LEFT JOIN bookings b ON f.id = b.facility_id AND b.status = 'approved'
                GROUP BY f.id, f.name
                ORDER BY booking_count DESC
                LIMIT 5
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getPopularFacilities: " . $e->getMessage());
            return [];
        }
    }
}
?> 