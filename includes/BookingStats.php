<?php
class BookingStats {
    private $conn;
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    public function getBookingStatistics($user_id = null, $is_admin = false) {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM bookings
            ";
            
            $params = [];
            
            // If not admin, only show user's own bookings
            if (!$is_admin && $user_id) {
                $query .= " WHERE user_id = ?";
                $params[] = $user_id;
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Convert to integers
            return [
                'total_bookings' => (int)$stats['total_bookings'],
                'pending' => (int)$stats['pending'],
                'approved' => (int)$stats['approved'],
                'cancelled' => (int)$stats['cancelled'],
                'completed' => (int)$stats['completed']
            ];
            
        } catch (PDOException $e) {
            error_log("Error getting booking statistics: " . $e->getMessage());
            return [
                'total_bookings' => 0,
                'pending' => 0,
                'approved' => 0,
                'cancelled' => 0,
                'completed' => 0
            ];
        }
    }
    
    public function getMonthlyBookingStats($user_id = null, $is_admin = false) {
        try {
            $query = "
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM bookings
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            ";
            
            $params = [];
            
            if (!$is_admin && $user_id) {
                $query .= " AND user_id = ?";
                $params[] = $user_id;
            }
            
            $query .= " GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month DESC LIMIT 12";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting monthly booking statistics: " . $e->getMessage());
            return [];
        }
    }
    
    public function getFacilityBookingStats($user_id = null, $is_admin = false) {
        try {
            $query = "
                SELECT 
                    f.name as facility_name,
                    COUNT(b.id) as total_bookings,
                    SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM facilities f
                LEFT JOIN bookings b ON f.id = b.facility_id
            ";
            
            $params = [];
            
            if (!$is_admin && $user_id) {
                $query .= " AND (b.user_id = ? OR b.user_id IS NULL)";
                $params[] = $user_id;
            }
            
            $query .= " GROUP BY f.id, f.name ORDER BY total_bookings DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting facility booking statistics: " . $e->getMessage());
            return [];
        }
    }
}
?> 