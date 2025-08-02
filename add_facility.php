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
    // Sanitize input data
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
    $description = trim(filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING));
    $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
    $location = trim(filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING)) ?: 'St. Peter\'s College of Toril';
    $type = trim(filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING)) ?: 'classroom';
    $status = trim(filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING)) ?: 'available';

    try {
        // Validate required fields
        if (empty($name) || empty($description) || $capacity === false || $capacity === null) {
            throw new Exception("Please fill in all required fields with valid values.");
        }

        if ($capacity <= 0) {
            throw new Exception("Capacity must be a positive number.");
        }

        // Validate facility type
        $allowed_types = ['classroom', 'laboratory', 'auditorium', 'gymnasium', 'conference_room', 'other'];
        if (!in_array($type, $allowed_types)) {
            throw new Exception("Invalid facility type.");
        }

        // Validate facility status
        $allowed_statuses = ['available', 'maintenance', 'reserved'];
        if (!in_array($status, $allowed_statuses)) {
            throw new Exception("Invalid facility status.");
        }

        $stmt = $conn->prepare("
            INSERT INTO facilities (name, description, capacity, location, type, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $success = $stmt->execute([
            $name,
            $description,
            $capacity,
            $location,
            $type,
            $status
        ]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Facility added successfully!']);
        } else {
            throw new Exception("Failed to add facility.");
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
} 