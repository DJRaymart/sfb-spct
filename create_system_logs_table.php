<?php
require_once 'config/database.php';

try {
    // Check if the table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'system_logs'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Create the system_logs table
        $sql = "CREATE TABLE `system_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `action` varchar(50) NOT NULL,
            `details` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        
        $conn->exec($sql);
        echo "Table system_logs created successfully.";
    } else {
        echo "Table system_logs already exists.";
    }
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?> 