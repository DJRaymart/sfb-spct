<?php
require_once __DIR__ . '/../config/config.php';

class Auth {
    private $conn;
    private $allowed_roles = ['student', 'faculty', 'admin'];
    
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }
    
    public function login($username, $password) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                if (!in_array($user['role'], $this->allowed_roles)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid user role'
                    ];
                }
                
                // Check if faculty or student account is approved
                if ($user['role'] === 'faculty' || $user['role'] === 'student') {
                    $status = $user['status'] ?? '';
                    if ($status !== 'active') {
                        return [
                            'success' => false,
                            'message' => 'Your account is not yet approved by admin. Please wait for approval.'
                        ];
                    }
                }

                // Regenerate session ID to prevent session fixation attacks
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                return [
                    'success' => true,
                    'message' => 'Login successful'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Invalid username or password'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Login failed'
            ];
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public function isFaculty() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'faculty';
    }
    
    public function isStudent() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->conn->prepare("SELECT id, username, email, full_name, role, phone FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting current user: " . $e->getMessage());
            return null;
        }
    }
    
    public function logout() {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        // Clear all session variables
        session_unset();
        
        // Destroy the session
        session_destroy();
        
        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
    
    public function register($username, $password, $email, $full_name, $role, $phone) {
        if ($role === 'admin' && !$this->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Only administrators can create admin accounts.'
            ];
        }

        $allowed_roles = $this->isAdmin() ? ['student', 'faculty', 'admin'] : ['student', 'faculty'];
        if (!in_array($role, $allowed_roles)) {
            return [
                'success' => false,
                'message' => 'Invalid role. ' . ($this->isAdmin() ? 'Must be student, faculty, or admin.' : 'Must be student or faculty.')
            ];
        }

        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Set status based on role - only faculty needs approval, students are auto-approved
            $status = 'active';
            if ($role === 'faculty' && !$this->isAdmin()) {
                $status = 'pending'; // Only faculty registrations need approval
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO users (username, password, email, full_name, role, phone, status)
                VALUES (:username, :password, :email, :full_name, :role, :phone, :status)
            ");
            
            $stmt->execute([
                'username' => $username,
                'password' => $hashed_password,
                'email' => $email,
                'full_name' => $full_name,
                'role' => $role,
                'phone' => $phone,
                'status' => $status
            ]);
            
            $successMessage = 'Registration successful';
            if ($role === 'faculty' && !$this->isAdmin()) {
                $successMessage .= '. Your faculty account will be reviewed by an administrator before you can log in.';
            }
            
            return [
                'success' => true,
                'message' => $successMessage
            ];
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return [
                    'success' => false,
                    'message' => 'Username or email already exists'
                ];
            }
            return [
                'success' => false,
                'message' => 'Registration failed'
            ];
        }
    }

    public function getUserRole() {
        return $_SESSION['role'] ?? null;
    }

    public function sendPasswordResetEmail($email) {
        try {
            $stmt = $this->conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Email address not found.'
                ];
            }

            $token = bin2hex(random_bytes(32));
            $stmt = $this->conn->prepare("
                INSERT INTO password_resets (user_id, token, expires_at) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
            ");
            $stmt->execute([$user['id'], $token]);

            // Determine if using HTTPS
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
            
            // Get the directory path (in case the app is not in the server root)
            $dir_path = dirname($_SERVER['SCRIPT_NAME']);
            $base_path = $dir_path === '/' ? '' : $dir_path;
            
            $resetLink = $protocol . $_SERVER['HTTP_HOST'] . $base_path . "/reset_password.php?token=" . $token;

            require_once __DIR__ . '/../vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($email, $user['full_name']);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = "
                <html>
                <head>
                    <title>Password Reset Request</title>
                </head>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>Hello {$user['full_name']},</p>
                    <p>We received a request to reset your password. Click the link below to reset it:</p>
                    <p><a href='{$resetLink}'>{$resetLink}</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <p>Best regards,<br>School Facility Reservation System</p>
                </body>
                </html>
            ";

            if ($mail->send()) {
                return [
                    'success' => true,
                    'message' => 'Reset instructions sent successfully.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send reset email.'
                ];
            }
        } catch (Exception $e) {
            error_log("Password reset email error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while processing your request.'
            ];
        }
    }

    public function verifyResetToken($token) {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count
                FROM password_resets pr 
                WHERE pr.token = ? AND pr.expires_at > NOW() 
                AND pr.used = 0
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Token verification error: " . $e->getMessage());
            return false;
        }
    }

    public function resetPassword($token, $newPassword) {
        try {
            // Begin transaction to prevent race conditions
            $this->conn->beginTransaction();
            
            // Atomically mark token as used and get user_id
            $stmt = $this->conn->prepare("
                UPDATE password_resets 
                SET used = 1 
                WHERE token = ? AND expires_at > NOW() AND used = 0
            ");
            $stmt->execute([$token]);
            
            if ($stmt->rowCount() === 0) {
                $this->conn->rollBack();
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token.'
                ];
            }
            
            // Get user_id after marking token as used
            $stmt = $this->conn->prepare("
                SELECT user_id 
                FROM password_resets 
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare("
                UPDATE users 
                SET password = ? 
                WHERE id = ?
            ");
            $stmt->execute([$hashedPassword, $reset['user_id']]);

            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Password reset successfully.'
            ];
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while resetting your password.'
            ];
        }
    }
}
?> 