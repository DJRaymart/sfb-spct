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
    $id = $_POST['id'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $phone = $_POST['phone'] ?? '';
    $id_number = $_POST['id_number'] ?? '';

    if (!empty($id) && !empty($username) && !empty($email) && !empty($full_name) && !empty($role)) {
        try {
            // Validate phone number (must be exactly 11 digits)
            if (!preg_match('/^\d{11}$/', $phone)) {
                echo json_encode(['success' => false, 'message' => 'Phone number must be exactly 11 digits']);
                exit;
            }
            
            $conn->beginTransaction();

            $sql = "UPDATE users SET 
                    full_name = :full_name, 
                    username = :username, 
                    email = :email, 
                    role = :role, 
                    status = :status, 
                    phone = :phone,
                    id_number = :id_number";

            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password = :password";
                $params['password'] = $hashed_password;
            }

            $sql .= " WHERE id = :id";
            $params = [
                'full_name' => $full_name,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'phone' => $phone,
                'id_number' => $id_number,
                'id' => $id
            ];

            $stmt = $conn->prepare($sql);
            $success = $stmt->execute($params);

            if ($success) {
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            } else {
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to update user']);
            }
        } catch (PDOException $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit; 