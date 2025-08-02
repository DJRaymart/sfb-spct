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
    // Check if support_requests table exists
    $stmt = $conn->prepare("SHOW TABLES LIKE 'support_requests'");
    $stmt->execute();
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Create the table if it doesn't exist
        $createTableSQL = "
            CREATE TABLE support_requests (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                message TEXT NOT NULL,
                status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
                admin_reply TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ";
        $conn->exec($createTableSQL);
    }
    
    // Get support requests from support_requests table
    $stmt = $conn->prepare("
        SELECT 
            sr.id,
            sr.user_id,
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
        ORDER BY sr.created_at DESC
    ");
    $stmt->execute();
    $supportRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the data for the frontend
    $parsedRequests = [];
    foreach ($supportRequests as $request) {
        $parsedRequests[] = [
            'id' => $request['id'],
            'name' => $request['name'],
            'email' => $request['email'],
            'subject' => $request['subject'],
            'message' => $request['message'],
            'status' => $request['status'],
            'admin_reply' => $request['admin_reply'],
            'created_at' => $request['created_at'],
            'updated_at' => $request['updated_at'],
            'user_id' => $request['user_id'],
            'username' => $request['username'],
            'user_role' => $request['role']
        ];
    }

    // Calculate statistics
    $total = count($parsedRequests);
    $pending = count(array_filter($parsedRequests, function($req) {
        return $req['status'] === 'pending';
    }));
    $in_progress = count(array_filter($parsedRequests, function($req) {
        return $req['status'] === 'in_progress';
    }));
    $resolved = count(array_filter($parsedRequests, function($req) {
        return $req['status'] === 'resolved';
    }));
    $closed = count(array_filter($parsedRequests, function($req) {
        return $req['status'] === 'closed';
    }));
    
    // Count this month's requests
    $thisMonth = date('Y-m');
    $monthly = count(array_filter($parsedRequests, function($req) use ($thisMonth) {
        return date('Y-m', strtotime($req['created_at'])) === $thisMonth;
    }));

    echo json_encode([
        'success' => true,
        'data' => $parsedRequests,
        'statistics' => [
            'total' => $total,
            'unread' => $pending, // Map pending to unread for frontend compatibility
            'replied' => $resolved, // Map resolved to replied for frontend compatibility
            'pending' => $pending,
            'in_progress' => $in_progress,
            'resolved' => $resolved,
            'closed' => $closed,
            'monthly' => $monthly
        ]
    ]);

} catch (Exception $e) {
    error_log("Error getting support requests: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load support requests'
    ]);
}
?> 