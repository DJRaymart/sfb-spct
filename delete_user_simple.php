<?php
// Prevent any output before headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/Auth.php';

// Set header to JSON
header('Content-Type: application/json');

try {
    $auth = new Auth($conn);

    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        error_log("Unauthorized access attempt in delete_user_simple.php");
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? '';
        $reason = $_POST['reason'] ?? 'No reason provided';
        $current_page = $_POST['current_page'] ?? 0;
        
        error_log("Delete user request received for ID: " . $id);
        
        if (empty($id)) {
            error_log("Delete user request received with empty ID");
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit;
        }

        // Start transaction
        $conn->beginTransaction();

        try {
            // Get user details before deletion
            $stmt = $conn->prepare("SELECT email, full_name, role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                error_log("User not found with ID: " . $id);
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }

            error_log("Found user: " . $user['full_name'] . " (" . $user['email'] . ")");

            // Check for existing bookings
            $stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
            $stmt->execute([$id]);
            $bookingCount = $stmt->fetchColumn();

            if ($bookingCount > 0) {
                error_log("Cannot delete user " . $id . " - has " . $bookingCount . " existing bookings");
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Cannot delete user with existing bookings. Please cancel all bookings first.']);
                exit;
            }

            // Check for existing support requests
            $stmt = $conn->prepare("SELECT COUNT(*) FROM support_requests WHERE user_id = ?");
            $stmt->execute([$id]);
            $supportCount = $stmt->fetchColumn();

            if ($supportCount > 0) {
                error_log("Cannot delete user " . $id . " - has " . $supportCount . " existing support requests");
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Cannot delete user with existing support requests. Please resolve all support requests first.']);
                exit;
            }

            // Delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $success = $stmt->execute([$id]);

            if (!$success) {
                error_log("Failed to delete user with ID: " . $id);
                error_log("PDO error info: " . print_r($stmt->errorInfo(), true));
                $conn->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
                exit;
            }

            error_log("User " . $id . " deleted successfully");

            // Send email notification
            require_once 'vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            try {
                error_log("Attempting to send deletion notification email to: " . $user['email']);
                
                // Server settings
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;

                // Recipients
                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($user['email'], $user['full_name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Account Deletion Notification';
                $mail->Body = "
                    <html>
                    <head>
                        <title>Account Deletion Notification</title>
                    </head>
                    <body>
                        <h2>Account Deletion Notification</h2>
                        <p>Dear {$user['full_name']},</p>
                        <p>Your account has been deleted from the School Facility Reservation System.</p>
                        <p><strong>Reason for deletion:</strong> {$reason}</p>
                        <p>If you believe this is a mistake or have any questions, please contact the system administrator.</p>
                        <p>Best regards,<br>School Facility Reservation System Team</p>
                    </body>
                    </html>
                ";

                $mail->send();
                error_log("Deletion notification email sent successfully to: " . $user['email']);
            } catch (Exception $e) {
                error_log("Failed to send deletion notification email: " . $e->getMessage());
                error_log("Email error details: " . print_r($e, true));
                // Continue with deletion even if email fails
            }

            // Commit transaction
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'User deleted successfully',
                'current_page' => $current_page
            ]);

        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Database error while deleting user: " . $e->getMessage());
            error_log("PDO error details: " . print_r($e, true));
            echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("General error while deleting user: " . $e->getMessage());
            error_log("Error details: " . print_r($e, true));
            echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the user']);
        }
    } else {
        error_log("Invalid request method in delete_user_simple.php: " . $_SERVER['REQUEST_METHOD']);
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    error_log("Critical error in delete_user_simple.php: " . $e->getMessage());
    error_log("Critical error details: " . print_r($e, true));
    echo json_encode(['success' => false, 'message' => 'A critical error occurred']);
}
?> 