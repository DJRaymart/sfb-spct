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
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $status = $_POST['status'] ?? '';
    $type = $_POST['type'] ?? '';

    try {
        if (empty($id) || empty($name) || empty($description) || empty($capacity) || empty($status)) {
            throw new Exception("Please fill in all required fields.");
        }

        if (!is_numeric($capacity) || $capacity <= 0) {
            throw new Exception("Capacity must be a positive number.");
        }

        $stmt = $conn->prepare("
            UPDATE facilities 
            SET name = ?, 
                description = ?, 
                capacity = ?, 
                status = ?,
                type = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $success = $stmt->execute([
            $name,
            $description,
            $capacity,
            $status,
            $type,
            $id
        ]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Facility updated successfully!']);
        } else {
            throw new Exception("Failed to update facility.");
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
} 