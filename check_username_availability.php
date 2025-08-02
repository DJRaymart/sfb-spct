<?php
require_once 'config/database.php';

// Set header to JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        
        if (empty($username)) {
            echo json_encode(['available' => false, 'message' => 'Username is required']);
            exit;
        }

        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            echo json_encode(['available' => false, 'message' => 'Invalid username format']);
            exit;
        }

        // Check if username exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            echo json_encode(['available' => false, 'message' => 'Username already exists']);
        } else {
            echo json_encode(['available' => true, 'message' => 'Username is available']);
        }
    } else {
        echo json_encode(['available' => false, 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    echo json_encode(['available' => false, 'message' => 'Database error']);
}
?> 