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

    if (empty($supportId)) {
        throw new Exception('Support ID is required');
    }

    // Get support request details before deletion for logging
    $stmt = $conn->prepare("SELECT name, email, subject FROM support_requests WHERE id = ?");
    $stmt->execute([$supportId]);
    $supportRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supportRequest) {
        throw new Exception('Support request not found');
    }

    // Start transaction after validation
    $conn->beginTransaction();

    // Delete the support request
    $stmt = $conn->prepare("DELETE FROM support_requests WHERE id = ?");
    $stmt->execute([$supportId]);

    if ($stmt->rowCount() === 0) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw new Exception('Failed to delete support request');
    }

    // Commit the deletion first
    $conn->commit();

    // Now handle ID renumbering outside of transaction (it's safe and simpler)
    try {
        // Renumber the IDs to maintain sequential order
        $conn->exec("SET @count = 0");
        $conn->exec("UPDATE support_requests SET id = @count:= @count + 1 ORDER BY created_at ASC");
        
        // Reset auto increment to next sequential number
        $stmt = $conn->prepare("SELECT MAX(id) as max_id FROM support_requests");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = ($result['max_id'] ?? 0) + 1;
        
        $conn->exec("ALTER TABLE support_requests AUTO_INCREMENT = $nextId");
    } catch (Exception $renumberError) {
        error_log("ID renumbering error (non-critical): " . $renumberError->getMessage());
        // Continue anyway - deletion was successful
    }

    // Log the deletion (also outside transaction)
    try {
        $stmt = $conn->prepare("
            INSERT INTO system_logs (action, details, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([
            'support_request_deleted',
            "Support request deleted - From: {$supportRequest['name']} ({$supportRequest['email']}) - Subject: {$supportRequest['subject']}"
        ]);
    } catch (Exception $logError) {
        error_log("Logging error (non-critical): " . $logError->getMessage());
        // Continue anyway - deletion was successful
    }

    echo json_encode([
        'success' => true,
        'message' => 'Support request deleted successfully and IDs have been renumbered'
    ]);

} catch (Exception $e) {
    // Only rollback if we actually have an active transaction
    if (isset($conn) && $conn->inTransaction()) {
        try {
            $conn->rollBack();
        } catch (Exception $rollbackError) {
            error_log("Rollback error: " . $rollbackError->getMessage());
        }
    }
    
    error_log("Error deleting support request: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>