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
    $supportId = $_GET['id'] ?? '';
    
    if (empty($supportId)) {
        throw new Exception('Support ID is required');
    }

    // Get support request details from support_requests table
    $stmt = $conn->prepare("
        SELECT 
            sr.id,
            sr.name,
            sr.email,
            sr.subject,
            sr.message,
            sr.status,
            sr.admin_reply,
            sr.created_at,
            sr.updated_at,
            u.username,
            u.role
        FROM support_requests sr
        LEFT JOIN users u ON sr.user_id = u.id
        WHERE sr.id = ?
    ");
    $stmt->execute([$supportId]);
    $supportRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supportRequest) {
        throw new Exception('Support request not found');
    }

    // Extract information directly from the database
    $name = $supportRequest['name'];
    $email = $supportRequest['email'];
    $subject = $supportRequest['subject'];
    $message = $supportRequest['message'];
    $status = $supportRequest['status'];
    $admin_reply = $supportRequest['admin_reply'];
    $created_at = $supportRequest['created_at'];
    $updated_at = $supportRequest['updated_at'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $supportRequest['id'],
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'admin_reply' => $admin_reply,
            'created_at' => $created_at,
            'updated_at' => $updated_at,
            'username' => $supportRequest['username'],
            'user_role' => $supportRequest['role']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error getting support details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 