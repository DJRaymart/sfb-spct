<?php
require_once 'config/database.php';

try {
    // Add id_number column to users table if it doesn't exist
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS id_number VARCHAR(50) DEFAULT NULL");
    
    echo "ID Number column added successfully to users table";
} catch (PDOException $e) {
    echo "Error adding column: " . $e->getMessage();
}
?> 