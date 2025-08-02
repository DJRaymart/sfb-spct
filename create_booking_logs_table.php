<?php
require_once 'config/database.php';

try {
    // Check if the table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'booking_logs'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Create the booking_logs table
        $sql = "CREATE TABLE `booking_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `booking_id` int(11) NOT NULL,
            `action` enum('create','update','cancel','approve','reject','auto_cancel') NOT NULL,
            `action_by` varchar(50) NOT NULL,
            `details` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `booking_id` (`booking_id`),
            CONSTRAINT `booking_logs_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        $conn->exec($sql);
        echo "Table booking_logs created successfully.";
    } else {
        echo "Table booking_logs already exists.";
    }
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 