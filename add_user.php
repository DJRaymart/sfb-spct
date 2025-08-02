<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

$auth = new Auth($conn);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = $_POST['full_name'] ?? '';
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $phone = $_POST['phone'] ?? '';
    $id_number = $_POST['id_number'] ?? '';
    $department = 'General'; // Default department

    // Log the received data
    error_log("Received user data: " . json_encode([
        'username' => $username,
        'email' => $email,
        'full_name' => $full_name,
        'role' => $role,
        'phone' => $phone
    ]));

    if (!empty($username) && !empty($password) && !empty($email) && !empty($full_name) && !empty($role)) {
        try {
            // Validate phone number (must be exactly 11 digits)
            if (!preg_match('/^\d{11}$/', $phone)) {
                echo json_encode(['success' => false, 'message' => 'Phone number must be exactly 11 digits']);
                exit;
            }
            
            // When admin adds a faculty user, set status to active directly (no need for approval)
            // For self-registration, faculty accounts will still be set to pending in register.php
            $status = $_POST['status'] ?? 'active';
            
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, email, full_name, role, status, phone, department, id_number)
                VALUES (:username, :password, :email, :full_name, :role, :status, :phone, :department, :id_number)
            ");
            
            $stmt->execute([
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'email' => $email,
                'full_name' => $full_name,
                'role' => $role,
                'status' => $status,
                'phone' => $phone,
                'department' => $department,
                'id_number' => $id_number
            ]);
            
            // Send email notification if the user is a faculty member
            if ($role === 'faculty') {
                try {
                    $mail = new PHPMailer(true);
                    
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'schoolfacilitybooking@gmail.com';
                    $mail->Password = 'bddm uifv njah dsgm';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    
                    // Recipients
                    $mail->setFrom('schoolfacilitybooking@gmail.com', 'School Facility Reservation System');
                    $mail->addAddress($email, $full_name);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Faculty Account Created';
                    $mail->Body = "
                        <html>
                        <head>
                            <title>Faculty Account Created</title>
                        </head>
                        <body>
                            <h2>Faculty Account Created</h2>
                            <p>Dear {$full_name},</p>
                            <p>You have been added as a faculty member by the admin to the School Facility Reservation System.</p>
                            <p><strong>Your account details:</strong></p>
                            <ul>
                                <li>Username: {$username}</li>
                                <li>Email: {$email}</li>
                                <li>Role: Faculty</li>
                                <li>Status: Active</li>
                            </ul>
                            <p>You can now log in to the system and start making facility reservations.</p>
                            <p>Best regards,<br>School Facility Reservation System Team</p>
                        </body>
                        </html>
                    ";
                    
                    $mail->send();
                    error_log("Email notification sent to new faculty user: {$email}");
                } catch (Exception $e) {
                    error_log("Failed to send email notification to new faculty user: " . $e->getMessage());
                    // Continue even if email fails
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'User added successfully']);
        } catch (Exception $e) {
            error_log("Error during registration: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => "An error occurred during registration: " . $e->getMessage()]);
        }
    } else {
        error_log("Missing required fields");
        echo json_encode(['success' => false, 'message' => "Please fill in all required fields."]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
exit; 