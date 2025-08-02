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

    error_log("POST data received: " . print_r($_POST, true));

    $userId = $_SESSION['user_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    error_log("Processed data: " . json_encode([
        'userId' => $userId,
        'full_name' => $full_name,
        'email' => $email,
        'phone' => $phone,
        'department' => $department,
        'has_current_password' => !empty($current_password),
        'has_new_password' => !empty($new_password)
    ]));

    if (empty($full_name)) {
        throw new Exception('Full name is required');
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email is required');
    }
    if (empty($phone)) {
        throw new Exception('Phone number is required');
    }
    if (!preg_match('/^\d{11}$/', $phone)) {
        throw new Exception('Phone number must be exactly 11 digits');
    }
    if (empty($department)) {
        throw new Exception('Department is required');
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
    $stmt->execute([
        'email' => $email,
        'id' => $userId
    ]);
    if ($stmt->rowCount() > 0) {
        throw new Exception('Email already exists');
    }

    if (!empty($current_password)) {
        if (empty($new_password)) {
            throw new Exception('New password is required when changing password');
        }
        if ($new_password !== $confirm_password) {
            throw new Exception('New passwords do not match');
        }
        if (strlen($new_password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($current_password, $user_data['password'])) {
            throw new Exception('Current password is incorrect');
        }
    }

    if (!empty($current_password) && !empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users 
            SET full_name = :full_name, 
                email = :email, 
                phone = :phone, 
                department = :department, 
                password = :password 
            WHERE id = :id
        ");
        $stmt->execute([
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'department' => $department,
            'password' => $hashed_password,
            'id' => $userId
        ]);
    } else {
        $stmt = $conn->prepare("
            UPDATE users 
            SET full_name = :full_name, 
                email = :email, 
                phone = :phone, 
                department = :department 
            WHERE id = :id
        ");
        $stmt->execute([
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'department' => $department,
            'id' => $userId
        ]);
    }

    $_SESSION['full_name'] = $full_name;

    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $updated_user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => [
            'full_name' => $updated_user['full_name'],
            'email' => $updated_user['email'],
            'phone' => $updated_user['phone'],
            'department' => $updated_user['department'],
            'role' => $updated_user['role']
        ]
    ]);

} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 