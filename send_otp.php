<?php
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'vendor/autoload.php';

// Set header to JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Email is required']);
            exit;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit;
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }

        // Generate 6-digit OTP
        $otp = sprintf('%06d', mt_rand(0, 999999));
        
        // Store OTP in session with timestamp
        session_start();
        $_SESSION['email_otp'] = $otp;
        $_SESSION['email_otp_email'] = $email;
        $_SESSION['email_otp_time'] = time();
        
        // Send OTP email
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Email Verification - School Facility Reservation System';
            
            $mail->Body = "
                <html>
                <head>
                    <title>Email Verification</title>
                </head>
                <body>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px; font-family: Arial, sans-serif;'>
                        <div style='text-align: center; margin-bottom: 30px;'>
                            <h2 style='color: #4e73df;'>Email Verification</h2>
                        </div>
                        
                        <div style='background: #f8f9fc; padding: 20px; border-radius: 10px; margin-bottom: 20px;'>
                            <p>Hello,</p>
                            <p>Thank you for registering with the School Facility Reservation System. To complete your registration, please use the verification code below:</p>
                            
                            <div style='background: #4e73df; color: white; padding: 15px; border-radius: 8px; text-align: center; margin: 20px 0;'>
                                <h1 style='margin: 0; font-size: 32px; letter-spacing: 5px;'>$otp</h1>
                            </div>
                            
                            <p><strong>Important:</strong></p>
                            <ul>
                                <li>This code will expire in 10 minutes</li>
                                <li>If you didn't request this verification, please ignore this email</li>
                                <li>Do not share this code with anyone</li>
                            </ul>
                        </div>
                        
                        <div style='text-align: center; color: #6c757d; font-size: 14px;'>
                            <p>This is an automated message, please do not reply to this email.</p>
                            <p>School Facility Reservation System</p>
                        </div>
                    </div>
                </body>
                </html>
            ";

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
            
        } catch (Exception $e) {
            error_log("OTP email error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 