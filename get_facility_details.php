<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'config/config.php';

header('Content-Type: application/json');

$auth = new Auth($conn);

// Check for CSRF token in AJAX requests (only for non-GET requests or if explicitly required)
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // For GET requests, we'll be more lenient with CSRF since they're read-only
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }
}

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Facility ID is required']);
    exit;
}

// Sanitize and validate the facility ID
$facility_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($facility_id === false || $facility_id === null || $facility_id <= 0) {
    echo json_encode(['error' => 'Invalid facility ID format']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM facilities WHERE id = ?");
    $stmt->execute([$facility_id]);
    $facility = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$facility) {
        echo json_encode(['error' => 'Facility not found']);
        exit;
    }

    echo json_encode($facility);

} catch (PDOException $e) {
    error_log("Database error in get_facility_details.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in get_facility_details.php: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch facility details']);
} 