<?php
require_once 'config/database.php';

// Set header to JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            echo json_encode(['available' => false, 'message' => 'Email is required']);
            exit;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['available' => false, 'message' => 'Invalid email format']);
            exit;
        }

        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            echo json_encode(['available' => false, 'message' => 'Email already exists']);
        } else {
            echo json_encode(['available' => true, 'message' => 'Email is available']);
        }
    } else {
        echo json_encode(['available' => false, 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    echo json_encode(['available' => false, 'message' => 'Database error']);
}
?> 