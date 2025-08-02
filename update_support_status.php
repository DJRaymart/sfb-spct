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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $supportId = $_POST['support_id'] ?? '';
    $status = $_POST['status'] ?? '';

    if (empty($supportId) || empty($status)) {
        throw new Exception('Support ID and status are required');
    }

    // Map old status values to new ones for backward compatibility
    $statusMap = [
        'new' => 'pending',
        'read' => 'in_progress', 
        'replied' => 'resolved',
        'closed' => 'closed',
        // Also accept new status values directly
        'pending' => 'pending',
        'in_progress' => 'in_progress',
        'resolved' => 'resolved'
    ];
    
    if (!isset($statusMap[$status])) {
        throw new Exception('Invalid status');
    }
    
    $mappedStatus = $statusMap[$status];

    // Get support request details
    $stmt = $conn->prepare("
        SELECT id, name, email, subject
        FROM support_requests 
        WHERE id = ?
    ");
    $stmt->execute([$supportId]);
    $supportRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supportRequest) {
        throw new Exception('Support request not found');
    }

    // Update the status in support_requests table
    $stmt = $conn->prepare("
        UPDATE support_requests 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$mappedStatus, $supportId]);

    // Log the status update in system_logs for audit trail
    $stmt = $conn->prepare("
        INSERT INTO system_logs (action, details, created_at) 
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([
        'support_status_update',
        "Support request ID {$supportId} ({$supportRequest['subject']}) status updated from '{$status}' to '{$mappedStatus}'"
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Support request status updated successfully'
    ]);

} catch (Exception $e) {
    error_log("Error updating support status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 