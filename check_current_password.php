<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

try {
    $auth = new Auth($conn);

    if (!$auth->isLoggedIn()) {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $userId = $_SESSION['user_id'];
    $current_password = $_POST['current_password'] ?? '';

    if (empty($current_password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Current password is required'
        ]);
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($current_password, $user_data['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Current password is incorrect'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Current password is correct'
        ]);
    }

} catch (Exception $e) {
    error_log("Current password check error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 