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
    $current_page = $_POST['current_page'] ?? 0;

    try {
        if (empty($id)) {
            throw new Exception("Facility ID is required.");
        }

        $stmt = $conn->prepare("SELECT id FROM facilities WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new Exception("Facility not found.");
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE facility_id = ?");
        $stmt->execute([$id]);
        $bookingCount = $stmt->fetchColumn();
        
        if ($bookingCount > 0) {
            throw new Exception("Cannot delete facility with existing bookings.");
        }

        $stmt = $conn->prepare("DELETE FROM facilities WHERE id = ?");
        $success = $stmt->execute([$id]);

        if ($success) {
            // Resequence IDs
            $conn->query("SET @counter = 0");
            $conn->query("UPDATE facilities SET id = (@counter:=@counter+1) ORDER BY id ASC");
            
            // Reset AUTO_INCREMENT value
            $getMaxIdStmt = $conn->query("SELECT MAX(id) as max_id FROM facilities");
            $maxId = $getMaxIdStmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
            $conn->query("ALTER TABLE facilities AUTO_INCREMENT = " . ($maxId + 1));
            
            echo json_encode(['success' => true, 'message' => 'Facility deleted successfully and IDs resequenced!']);
        } else {
            throw new Exception("Failed to delete facility.");
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
} 