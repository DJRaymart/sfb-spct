<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'config/config.php';

try {
    $auth = new Auth($conn);

    // Check for CSRF token in AJAX requests (only for non-GET requests or if explicitly required)
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        // For GET requests, we'll be more lenient with CSRF since they're read-only
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid CSRF token']);
                exit;
            }
        }
    }

    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized access']);
        exit;
    }

    if (isset($_GET['id'])) {
        $id = trim($_GET['id']);
        
        try {
            if (!is_numeric($id) || $id <= 0) {
                error_log("Invalid user ID provided: $id");
                throw new Exception('Invalid user ID');
            }

            // Check if id_number column exists and handle gracefully
            try {
                $check_column = $conn->prepare("SHOW COLUMNS FROM users LIKE 'id_number'");
                $check_column->execute();
                $has_id_number = $check_column->rowCount() > 0;
                
                if ($has_id_number) {
                    $stmt = $conn->prepare("
                        SELECT id, username, email, full_name, role, status, phone, department, id_number 
                        FROM users 
                        WHERE id = ?
                    ");
                } else {
                    $stmt = $conn->prepare("
                        SELECT id, username, email, full_name, role, status, phone, department
                        FROM users 
                        WHERE id = ?
                    ");
                }
            } catch (PDOException $e) {
                // Fallback to basic query if column check fails
                $stmt = $conn->prepare("
                    SELECT id, username, email, full_name, role, status, phone, department
                    FROM users 
                    WHERE id = ?
                ");
                $has_id_number = false;
            }
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                error_log("User not found for ID: $id");
                throw new Exception('User not found');
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Debug: Log the user data
            error_log("User data retrieved: " . json_encode($user));
            
            // Ensure password is not included in response
            unset($user['password']);
            
            // Validate that required fields exist
            if (!isset($user['id']) || !isset($user['username'])) {
                error_log("Missing required fields in user data: " . json_encode($user));
                throw new Exception('Invalid user data retrieved - missing required fields');
            }
            
            header('Content-Type: application/json');
            $response = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'status' => $user['status'],
                'phone' => $user['phone'],
                'department' => $user['department']
            ];
            
            // Only include id_number if it exists in the result
            if (isset($user['id_number'])) {
                $response['id_number'] = $user['id_number'];
            }
            
            // Debug: Log the final response
            error_log("Final response: " . json_encode($response));
            
            echo json_encode($response);
        } catch (PDOException $e) {
            error_log("Database error in get_user_details.php: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database error occurred']);
        } catch (Exception $e) {
            error_log("Error in get_user_details.php: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => 'An error occurred while retrieving user details']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User ID is required']);
    }
} catch (Exception $e) {
    error_log("System error in get_user_details.php: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'System error occurred']);
} 