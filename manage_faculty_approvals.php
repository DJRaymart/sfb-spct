<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: index.php');
    exit;
}

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    if ($action && $user_id) {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'faculty' AND status = 'pending'");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new Exception('Invalid faculty user or already processed');
            }
            
            $new_status = ($action === 'approve') ? 'active' : 'inactive';
            
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $user_id]);
            
            // Send email notification
            require_once 'vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'schoolfacilitybooking@gmail.com';
                $mail->Password = 'bddm uifv njah dsgm';
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $mail->setFrom('schoolfacilitybooking@gmail.com', 'School Facility Reservation System');
                $mail->addAddress($user['email'], $user['full_name']);
                
                $mail->isHTML(true);
                $mail->Subject = 'Faculty Account Status Update';
                
                if ($action === 'approve') {
                    $mail->Body = "
                        <html>
                        <head>
                            <title>Account Approved</title>
                        </head>
                        <body>
                            <h2>Account Approved</h2>
                            <p>Dear {$user['full_name']},</p>
                            <p>Your faculty account has been approved by the administrator. You can now log in to your account and start using the School Facility Reservation System.</p>
                            <p>Best regards,<br>School Facility Reservation System Team</p>
                        </body>
                        </html>
                    ";
                } else {
                    $mail->Body = "
                        <html>
                        <head>
                            <title>Account Rejected</title>
                        </head>
                        <body>
                            <h2>Account Rejected</h2>
                            <p>Dear {$user['full_name']},</p>
                            <p>We regret to inform you that your faculty account registration has been rejected by the administrator.</p>
                            <p>If you believe this is a mistake, please contact the administrator for more information.</p>
                            <p>Best regards,<br>School Facility Reservation System Team</p>
                        </body>
                        </html>
                    ";
                }
                
                $mail->send();
            } catch (Exception $e) {
                error_log("Email notification error: " . $e->getMessage());
            }
            
            header('Location: manage_faculty_approvals.php');
            exit();
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
            header('Location: manage_faculty_approvals.php');
            exit();
        }
    }
}

// Get pending faculty accounts
$stmt = $conn->prepare("
    SELECT id, username, email, full_name, phone, department, created_at 
    FROM users 
    WHERE role = 'faculty' AND status = 'pending'
    ORDER BY created_at DESC
");
$stmt->execute();
$pending_faculty = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Faculty Approvals - School Facility Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php require_once 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <h2><i class="fas fa-user-check me-2"></i>Manage Faculty Approvals</h2>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    echo htmlspecialchars($_SESSION['success_message']);
                    unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (empty($pending_faculty)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No pending faculty approvals at this time.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Phone</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_faculty as $faculty): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($faculty['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($faculty['username']); ?></td>
                                <td><?php echo htmlspecialchars($faculty['email']); ?></td>
                                <td><?php echo htmlspecialchars($faculty['department']); ?></td>
                                <td><?php echo htmlspecialchars($faculty['phone']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($faculty['created_at'])); ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="user_id" value="<?php echo $faculty['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 