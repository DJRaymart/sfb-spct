<?php
session_start();
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/Auth.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$auth = new Auth($conn);
$isAdmin = $auth->isAdmin();

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrf_token_submitted = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token_submitted) || $csrf_token_submitted !== $csrf_token) {
        $_SESSION['error_message'] = "Invalid form submission. Please try again.";
        header('Location: register.php');
        exit();
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $department_name = trim($_POST['department_name'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');

    // Validate required fields
    if (empty($username) || empty($password) || empty($email) || empty($full_name) || empty($role) || empty($phone) || empty($department_name) || empty($id_number)) {
        $_SESSION['error_message'] = "All fields are required.";
        header('Location: register.php');
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Please enter a valid email address.";
        header('Location: register.php');
        exit();
    }

    // Validate username format (alphanumeric and underscore only, 3-20 characters)
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $_SESSION['error_message'] = "Username must be 3-20 characters and contain only letters, numbers, and underscores.";
        header('Location: register.php');
        exit();
    }

    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "username_exists";
        $_SESSION['username_error'] = $username;
        header('Location: register.php');
        exit();
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['error_message'] = "email_exists";
        $_SESSION['email_error'] = $email;
        header('Location: register.php');
        exit();
    }

    // Verify OTP
    $otp = $_POST['otp'] ?? '';
    if (empty($otp)) {
        $_SESSION['error_message'] = "Please enter the verification code sent to your email.";
        header('Location: register.php');
        exit();
    }

    // Check if OTP exists and is valid
    if (!isset($_SESSION['email_otp']) || !isset($_SESSION['email_otp_email']) || !isset($_SESSION['email_otp_time'])) {
        $_SESSION['error_message'] = "Please request a new verification code.";
        header('Location: register.php');
        exit();
    }

    // Check if OTP is for the correct email
    if ($_SESSION['email_otp_email'] !== $email) {
        $_SESSION['error_message'] = "Email verification failed. Please use the same email address.";
        header('Location: register.php');
        exit();
    }

    // Check if OTP is expired (10 minutes)
    if (time() - $_SESSION['email_otp_time'] > 600) {
        $_SESSION['error_message'] = "Verification code has expired. Please request a new one.";
        header('Location: register.php');
        exit();
    }

    // Check if OTP matches
    if ($_SESSION['email_otp'] !== $otp) {
        $_SESSION['error_message'] = "Invalid verification code. Please check and try again.";
        header('Location: register.php');
        exit();
    }

    // Validate password strength
    if (strlen($password) < 8) {
        $_SESSION['error_message'] = "Password must be at least 8 characters long.";
        header('Location: register.php');
        exit();
    }

    $allowed_roles = ['student', 'faculty', 'admin'];
    if (!in_array($role, $allowed_roles)) {
        $_SESSION['error_message'] = "Invalid role selected.";
        header('Location: register.php');
        exit();
    }

    $recaptcha_secret = RECAPTCHA_SECRET_KEY;
    $recaptcha_response = $_POST['g-recaptcha-response'];

    $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$recaptcha_secret&response=$recaptcha_response");
    $response_keys = json_decode($response, true);

    if (!$response_keys['success']) {
        $_SESSION['error_message'] = "Please complete the CAPTCHA.";
        header('Location: register.php');
        exit();
    }

    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Validate phone number (must be exactly 11 digits)
        if (!preg_match('/^\d{11}$/', $phone)) {
            $_SESSION['error_message'] = "Phone number must be exactly 11 digits.";
            header('Location: register.php');
            exit();
        }
        
        // Set status based on role - only faculty needs approval, students are auto-approved
        $status = ($role === 'faculty') ? 'pending' : 'active';
        
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, email, full_name, role, phone, department, status, id_number)
            VALUES (:username, :password, :email, :full_name, :role, :phone, :department, :status, :id_number)
        ");
        
        $stmt->execute([
            'username' => $username,
            'password' => $hashed_password,
            'email' => $email,
            'full_name' => $full_name,
            'role' => $role,
            'phone' => $phone,
            'department' => $department_name,
            'status' => $status,
            'id_number' => $id_number
        ]);
        
        // Send welcome email
        require_once 'vendor/autoload.php';
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
            $mail->addAddress($email, $full_name);

            $mail->isHTML(true);
            $mail->Subject = 'Welcome to School Facility Reservation System';
            
            if ($role === 'faculty') {
                $mail->Body = "
                    <html>
                    <head>
                        <title>Welcome to School Facility Reservation System</title>
                    </head>
                    <body>
                        <h2>Welcome to School Facility Reservation System!</h2>
                        <p>Dear {$full_name},</p>
                        <p>Thank you for registering with our School Facility Reservation System.</p>
                        <p>Your faculty account is currently pending approval from the administrator. You will receive another email once your account has been approved.</p>
                        <p>Here are your account details:</p>
                        <ul>
                            <li><strong>Username:</strong> {$username}</li>
                            <li><strong>Email:</strong> {$email}</li>
                            <li><strong>Role:</strong> {$role}</li>
                            <li><strong>Department:</strong> {$department_name}</li>
                        </ul>
                        <p>Best regards,<br>School Facility Reservation System Team</p>
                    </body>
                    </html>
                ";
            } else {
                $mail->Body = "
                    <html>
                    <head>
                        <title>Welcome to School Facility Reservation System</title>
                    </head>
                    <body>
                        <h2>Welcome to School Facility Reservation System!</h2>
                        <p>Dear {$full_name},</p>
                        <p>Thank you for registering with our School Facility Reservation System. Your account has been created successfully.</p>
                        <p>Here are your account details:</p>
                        <ul>
                            <li><strong>Username:</strong> {$username}</li>
                            <li><strong>Email:</strong> {$email}</li>
                            <li><strong>Role:</strong> {$role}</li>
                            <li><strong>Department:</strong> {$department_name}</li>
                        </ul>
                        <p>You can now log in to your account and start using our facility reservation system.</p>
                        <p>Best regards,<br>School Facility Reservation System Team</p>
                    </body>
                    </html>
                ";
            }

            $mail->send();
        } catch (Exception $e) {
            error_log("Registration email error: " . $e->getMessage());
        }
        
        // Clear OTP session data
        unset($_SESSION['email_otp']);
        unset($_SESSION['email_otp_email']);
        unset($_SESSION['email_otp_time']);
        
        $_SESSION['success_message'] = ($role === 'faculty') ? 
            "Thank you for registering! Your faculty account is pending approval. You will receive an email once approved." : 
            "Thank you for registering! Your account has been created successfully. Please check your email for account details.";
        header('Location: index.php');
        exit();
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $_SESSION['error_message'] = "Username or email already exists.";
        } else {
            $_SESSION['error_message'] = "Registration failed. Please try again.";
        }
        header('Location: register.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="img/logo.png">
    <title>Registration - School Facility Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }

        .g-recaptcha {
            margin-bottom: 20px;
        }
        
        .recaptcha-container {
            display: flex;
            justify-content: left;
            margin-bottom: 25px;
        }
        
        .recaptcha-container > div {
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 20px;
        }

        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1200px;
            display: flex;
            margin: 20px;
        }

        .register-image {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            padding: 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 45%;
            position: relative;
            overflow: hidden;
        }

        .register-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('img/spct2.jpg') center/cover;
            opacity: 0.2;
        }

        .register-image h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .register-image p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .benefits-list li {
            margin: 15px 0;
            display: flex;
            align-items: center;
            font-size: 1rem;
        }

        .benefits-list li i {
            background: rgba(255, 255, 255, 0.2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 14px;
        }

        .register-form {
            padding: 40px;
            width: 55%;
            background: white;
        }

        .form-section {
            background: var(--light-color);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-section h3 {
            color: var(--dark-color);
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .form-section h3 i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .form-control {
            border-radius: 10px;
            padding: 12px 20px;
            border: 2px solid #e3e6f0;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.15);
        }

        .form-select {
            border-radius: 10px;
            padding: 12px 20px;
            border: 2px solid #e3e6f0;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.15);
        }

        .btn-register {
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            border: none;
            transition: all 0.3s ease;
            font-size: 1rem;
            color: white;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.15);
        }

        /* Password toggle button styling */
        .input-group .btn-outline-secondary {
            border-left: none;
            border-color: #e3e6f0;
        }

        .input-group .btn-outline-secondary:hover {
            background-color: #f8f9fc;
            border-color: #e3e6f0;
            color: #6c757d;
        }

        .input-group .form-control {
            border-right: none;
        }

        .input-group .form-control:focus {
            border-right: none;
        }

        /* Real-time validation styling */
        .form-control.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.15);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
        }

        .valid-feedback {
            display: none;
            color: #28a745;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .valid-feedback.show {
            display: block;
        }

        .invalid-feedback.show {
            display: block;
        }

        /* OTP Section Styling */
        #otpSection {
            border: 2px solid #e3e6f0;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            background: #f8f9fc;
        }

        #otpSection h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        #sendOtpBtn, #resendOtpBtn {
            border-radius: 0 10px 10px 0;
        }

        #otpSection .input-group .form-control {
            border-radius: 10px 0 0 10px;
        }

        #otpTimer {
            color: #6c757d;
            font-size: 0.875rem;
        }

        #otpTimer i {
            color: #ffc107;
        }

        .register-footer {
            text-align: center;
            margin-top: 30px;
            color: var(--secondary-color);
        }

        .register-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .register-footer a:hover {
            color: #224abe;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            border-radius: 50%;
            background: url('img/logo.jpg') center/cover;
        }

        .school-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .register-image {
                display: none;
            }
            .register-form {
                width: 100%;
                padding: 30px;
            }
            .school-name {
                font-size: 1.5rem;
            }
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        @media screen and (max-width: 400px) {
            .recaptcha-container {
                transform: scale(0.85);
                transform-origin: center;
                margin-left: -22px; 
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-image">
            <h2>Join Our Community!</h2>
            <p>Create your account to access the School Facility Reservation System and enjoy these benefits:</p>
            <ul class="benefits-list">
                <li><i class="fas fa-calendar-plus"></i> Book facilities instantly</li>
                <li><i class="fas fa-clock"></i> Check real-time availability</li>
                <li><i class="fas fa-bell"></i> Get booking notifications</li>
                <li><i class="fas fa-calendar-alt"></i> Manage your bookings</li>
                <li><i class="fas fa-user-shield"></i> Secure account access</li>
                <li><i class="fas fa-headset"></i> 24/7 Support</li>
            </ul>
        </div>
        <div class="register-form">
            <div class="text-center mb-4">
                <img src="img/logo.jpg" alt="School Logo" class="school-logo">
                <div class="school-name">School Facility Reservation <br> System</div>
                <p class="text-muted">Create your account</p>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        <?php if ($_SESSION['error_message'] === 'username_exists'): ?>
                            Swal.fire({
                                icon: 'error',
                                title: 'Username Already Exists!',
                                text: 'The username "<?php echo htmlspecialchars($_SESSION['username_error']); ?>" is already taken. Please choose a different username.',
                                confirmButtonColor: '#dc3545',
                                confirmButtonText: 'OK'
                            });
                        <?php elseif ($_SESSION['error_message'] === 'email_exists'): ?>
                            Swal.fire({
                                icon: 'warning',
                                title: 'Email Already Exists!',
                                text: 'An account with the email "<?php echo htmlspecialchars($_SESSION['email_error']); ?>" already exists.',
                                showCancelButton: true,
                                confirmButtonColor: '#ffc107',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: 'Recover Account',
                                cancelButtonText: 'Use Different Email',
                                reverseButtons: true
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Redirect to password recovery page
                                    window.location.href = 'forgot_password.php?email=<?php echo urlencode($_SESSION['email_error']); ?>';
                                }
                            });
                        <?php else: ?>
                            Swal.fire({
                                icon: 'error',
                                title: 'Registration Error!',
                                text: '<?php echo htmlspecialchars($_SESSION['error_message']); ?>',
                                confirmButtonColor: '#dc3545',
                                confirmButtonText: 'OK'
                            });
                        <?php endif; ?>
                    });
                </script>
                <?php 
                    unset($_SESSION['error_message']);
                    unset($_SESSION['username_error']);
                    unset($_SESSION['email_error']);
                ?>
            <?php endif; ?>

            <form id="registerForm" action="register.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-section">
                    <h3><i class="fas fa-user-circle"></i> Account Information</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="valid-feedback" id="username-valid-feedback">Username is available</div>
                            <div class="invalid-feedback" id="username-invalid-feedback">Username already exists</div>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">Password must be at least 8 characters long</div>
                            <div class="invalid-feedback" id="password-feedback"></div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <input type="email" class="form-control" id="email" name="email" required>
                                <button class="btn btn-outline-primary" type="button" id="sendOtpBtn">
                                    <i class="fas fa-paper-plane"></i> Send OTP
                                </button>
                            </div>
                            <div class="valid-feedback" id="email-valid-feedback">Email is available</div>
                            <div class="invalid-feedback" id="email-invalid-feedback">Email already exists</div>
                        </div>
                    </div>
                </div>

                <div class="form-section" id="otpSection" style="display: none;">
                    <h3><i class="fas fa-shield-alt"></i> Email Verification</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="otp" class="form-label">Verification Code</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="otp" name="otp" placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}">
                                <button class="btn btn-outline-secondary" type="button" id="resendOtpBtn">
                                    <i class="fas fa-redo"></i> Resend
                                </button>
                            </div>
                            <div class="form-text">Enter the 6-digit code sent to your email</div>
                            <div class="invalid-feedback" id="otp-feedback"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-end h-100">
                                <div class="text-muted">
                                    <small id="otpTimer" style="display: none;">
                                        <i class="fas fa-clock"></i> Resend available in <span id="timerCount">10:00</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-id-badge"></i> Role & Contact</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="student">Student</option>
                                <option value="faculty">Faculty</option>
                                <?php if ($isAdmin): ?>
                                <option value="admin">Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                            <div class="valid-feedback" id="phone-valid-feedback">Phone number is valid</div>
                            <div class="invalid-feedback" id="phone-invalid-feedback">Phone number must be exactly 11 digits</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-building"></i> Department Information</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="department_name" class="form-label">Department Name</label>
                            <input type="text" class="form-control" id="department_name" name="department_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="id_number" class="form-label" id="id_label">ID Number</label>
                            <input type="text" class="form-control" id="id_number" name="id_number" required>
                            <div class="form-text">Enter your ID number for verification purposes</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-shield-alt"></i> Security Verification</h3>
                    <p class="text-muted mb-3">Please verify that you are not a robot</p>
                    <div class="recaptcha-container">
                        <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                </div>
            </form>

            <div class="register-footer">
                <p class="mb-2">Already have an account? <a href="index.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const loadingOverlay = document.querySelector('.loading-overlay');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Get reCAPTCHA response
                const recaptchaResponse = grecaptcha.getResponse();
                if (!recaptchaResponse) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Please complete the CAPTCHA verification'
                    });
                    return;
                }

                // Create FormData object
                const formData = new FormData(form);
                
                // Show confirmation dialog
                Swal.fire({
                    title: 'Confirm Registration',
                    html: `
                        <div class="text-start">
                            <p><strong>Username:</strong> ${formData.get('username')}</p>
                            <p><strong>Full Name:</strong> ${formData.get('full_name')}</p>
                            <p><strong>Email:</strong> ${formData.get('email')}</p>
                            <p><strong>Role:</strong> ${formData.get('role')}</p>
                            <p><strong>Phone:</strong> ${formData.get('phone')}</p>
                            <p><strong>Department:</strong> ${formData.get('department_name')}</p>
                            <p><strong>${formData.get('role') === 'student' ? 'Student ID' : 'Faculty ID'}:</strong> ${formData.get('id_number')}</p>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#4e73df',
                    cancelButtonColor: '#858796',
                    confirmButtonText: 'Yes, register!',
                    cancelButtonText: 'No, review again'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Submit the form
                        form.submit();
                    }
                });
            });

            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            const passwordFeedback = document.getElementById('password-feedback');

            // Password visibility toggle
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                
                // Toggle icon
                if (type === 'text') {
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                } else {
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                }
            });

            // Instant password validation
            function validatePassword() {
                const passwordValue = password.value;
                const minLength = 8;
                
                if (passwordValue.length < minLength) {
                    password.classList.add('is-invalid');
                    passwordFeedback.textContent = `Password must be at least ${minLength} characters long. Current length: ${passwordValue.length}`;
                    return false;
                } else {
                    password.classList.remove('is-invalid');
                    password.classList.add('is-valid');
                    passwordFeedback.textContent = '';
                    return true;
                }
            }

            // Add event listeners for instant validation
            password.addEventListener('input', validatePassword);
            password.addEventListener('blur', validatePassword);
            password.addEventListener('keyup', validatePassword);

            // Real-time username availability check
            const username = document.getElementById('username');
            let usernameTimeout;

            username.addEventListener('blur', function() {
                if (this.value.length < 3) {
                    this.setCustomValidity('Username must be at least 3 characters long');
                } else {
                    this.setCustomValidity('');
                }
            });

            // Clear feedback when username field is focused but empty
            username.addEventListener('focus', function() {
                if (this.value.trim().length === 0) {
                    this.classList.remove('is-valid', 'is-invalid');
                    const validFeedback = document.getElementById('username-valid-feedback');
                    const invalidFeedback = document.getElementById('username-invalid-feedback');
                    validFeedback.textContent = '';
                    validFeedback.classList.remove('show');
                    invalidFeedback.textContent = '';
                    invalidFeedback.classList.remove('show');
                }
            });

            username.addEventListener('input', function() {
                clearTimeout(usernameTimeout);
                const usernameValue = this.value.trim();
                
                // Hide feedback messages when field is empty
                const validFeedback = document.getElementById('username-valid-feedback');
                const invalidFeedback = document.getElementById('username-invalid-feedback');
                
                if (usernameValue.length === 0) {
                    this.classList.remove('is-valid', 'is-invalid');
                    validFeedback.textContent = '';
                    validFeedback.classList.remove('show');
                    invalidFeedback.textContent = '';
                    invalidFeedback.classList.remove('show');
                    return;
                }
                
                if (usernameValue.length >= 3) {
                    usernameTimeout = setTimeout(() => {
                        checkUsernameAvailability(usernameValue);
                    }, 500);
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                    validFeedback.textContent = '';
                    validFeedback.classList.remove('show');
                    invalidFeedback.textContent = '';
                    invalidFeedback.classList.remove('show');
                }
            });

            // Email validation
            const email = document.getElementById('email');
            let emailTimeout;

            email.addEventListener('blur', function() {
                const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                if (!emailPattern.test(this.value)) {
                    this.setCustomValidity('Please enter a valid email address');
                } else {
                    this.setCustomValidity('');
                }
            });

            // Clear feedback when email field is focused but empty
            email.addEventListener('focus', function() {
                if (this.value.trim().length === 0) {
                    this.classList.remove('is-valid', 'is-invalid');
                    const validFeedback = document.getElementById('email-valid-feedback');
                    const invalidFeedback = document.getElementById('email-invalid-feedback');
                    validFeedback.textContent = '';
                    validFeedback.classList.remove('show');
                    invalidFeedback.textContent = '';
                    invalidFeedback.classList.remove('show');
                }
            });

            email.addEventListener('input', function() {
                clearTimeout(emailTimeout);
                const emailValue = this.value.trim();
                
                // Hide feedback messages when field is empty
                const validFeedback = document.getElementById('email-valid-feedback');
                const invalidFeedback = document.getElementById('email-invalid-feedback');
                
                if (emailValue.length === 0) {
                    this.classList.remove('is-valid', 'is-invalid');
                    validFeedback.textContent = '';
                    validFeedback.classList.remove('show');
                    invalidFeedback.textContent = '';
                    invalidFeedback.classList.remove('show');
                    return;
                }
                
                if (emailValue && isValidEmail(emailValue)) {
                    emailTimeout = setTimeout(() => {
                        checkEmailAvailability(emailValue);
                    }, 500);
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                    validFeedback.textContent = '';
                    validFeedback.classList.remove('show');
                    invalidFeedback.textContent = '';
                    invalidFeedback.classList.remove('show');
                }
            });

            // Phone validation
            const phone = document.getElementById('phone');
            
            function validatePhone() {
                const phoneValue = phone.value.replace(/\D/g, ''); // Remove non-digits
                const phonePattern = /^[0-9]{11}$/;
                const validFeedback = document.getElementById('phone-valid-feedback');
                const invalidFeedback = document.getElementById('phone-invalid-feedback');
                
                if (phoneValue.length === 0) {
                    phone.classList.remove('is-valid', 'is-invalid');
                    validFeedback.classList.remove('show');
                    invalidFeedback.classList.remove('show');
                    return;
                }
                
                if (phonePattern.test(phoneValue)) {
                    phone.classList.remove('is-invalid');
                    phone.classList.add('is-valid');
                    validFeedback.classList.add('show');
                    invalidFeedback.classList.remove('show');
                } else {
                    phone.classList.remove('is-valid');
                    phone.classList.add('is-invalid');
                    invalidFeedback.classList.add('show');
                    validFeedback.classList.remove('show');
                }
            }
            
            phone.addEventListener('blur', function() {
                const phonePattern = /^[0-9]{11}$/;
                if (!phonePattern.test(this.value)) {
                    this.setCustomValidity('Please enter a valid phone number (exactly 11 digits)');
                } else {
                    this.setCustomValidity('');
                }
                validatePhone();
            });
            
            phone.addEventListener('input', function() {
                // Only allow digits
                this.value = this.value.replace(/\D/g, '');
                validatePhone();
            });

            function checkUsernameAvailability(username) {
                fetch('check_username_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'username=' + encodeURIComponent(username)
                })
                .then(response => response.json())
                .then(data => {
                    const validFeedback = document.getElementById('username-valid-feedback');
                    const invalidFeedback = document.getElementById('username-invalid-feedback');
                    
                    if (data.available) {
                        username.classList.remove('is-invalid');
                        username.classList.add('is-valid');
                        validFeedback.textContent = data.message;
                        validFeedback.classList.add('show');
                        invalidFeedback.textContent = '';
                        invalidFeedback.classList.remove('show');
                    } else {
                        username.classList.remove('is-valid');
                        username.classList.add('is-invalid');
                        invalidFeedback.textContent = data.message;
                        invalidFeedback.classList.add('show');
                        validFeedback.textContent = '';
                        validFeedback.classList.remove('show');
                    }
                })
                .catch(error => {
                    console.error('Error checking username availability:', error);
                });
            }



            function checkEmailAvailability(email) {
                fetch('check_email_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'email=' + encodeURIComponent(email)
                })
                .then(response => response.json())
                .then(data => {
                    const validFeedback = document.getElementById('email-valid-feedback');
                    const invalidFeedback = document.getElementById('email-invalid-feedback');
                    
                    if (data.available) {
                        email.classList.remove('is-invalid');
                        email.classList.add('is-valid');
                        validFeedback.textContent = data.message;
                        validFeedback.classList.add('show');
                        invalidFeedback.textContent = '';
                        invalidFeedback.classList.remove('show');
                    } else {
                        email.classList.remove('is-valid');
                        email.classList.add('is-invalid');
                        invalidFeedback.textContent = data.message;
                        invalidFeedback.classList.add('show');
                        validFeedback.textContent = '';
                        validFeedback.classList.remove('show');
                    }
                })
                .catch(error => {
                    console.error('Error checking email availability:', error);
                });
            }

            function isValidEmail(email) {
                const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                return emailPattern.test(email);
            }

            // OTP functionality
            const sendOtpBtn = document.getElementById('sendOtpBtn');
            const resendOtpBtn = document.getElementById('resendOtpBtn');
            const otpSection = document.getElementById('otpSection');
            const otpInput = document.getElementById('otp');
            const otpTimer = document.getElementById('otpTimer');
            const timerCount = document.getElementById('timerCount');
            let countdownTimer;

            // Send OTP
            sendOtpBtn.addEventListener('click', function() {
                const emailValue = email.value.trim();
                
                if (!emailValue || !isValidEmail(emailValue)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Email',
                        text: 'Please enter a valid email address first.',
                        confirmButtonColor: '#dc3545'
                    });
                    return;
                }

                // Check email availability first
                fetch('check_email_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'email=' + encodeURIComponent(emailValue)
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.available) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Email Already Exists',
                            text: 'This email is already registered. Please use a different email.',
                            confirmButtonColor: '#dc3545'
                        });
                        return;
                    }

                    // Send OTP
                    sendOtpBtn.disabled = true;
                    sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

                    fetch('send_otp.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'email=' + encodeURIComponent(emailValue)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'OTP Sent!',
                                text: 'A verification code has been sent to your email address.',
                                confirmButtonColor: '#28a745'
                            });
                            
                            // Show OTP section
                            otpSection.style.display = 'block';
                            
                            // Start countdown timer
                            startCountdown();
                            
                            // Focus on OTP input
                            otpInput.focus();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed to Send OTP',
                                text: data.message,
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error sending OTP:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to send OTP. Please try again.',
                            confirmButtonColor: '#dc3545'
                        });
                    })
                    .finally(() => {
                        sendOtpBtn.disabled = false;
                        sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP';
                    });
                });
            });

            // Resend OTP
            resendOtpBtn.addEventListener('click', function() {
                const emailValue = email.value.trim();
                
                if (!emailValue) {
                    Swal.fire({
                        icon: 'error',
                        title: 'No Email',
                        text: 'Please enter an email address first.',
                        confirmButtonColor: '#dc3545'
                    });
                    return;
                }

                resendOtpBtn.disabled = true;
                resendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

                fetch('send_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'email=' + encodeURIComponent(emailValue)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'OTP Resent!',
                            text: 'A new verification code has been sent to your email address.',
                            confirmButtonColor: '#28a745'
                        });
                        
                        // Restart countdown timer
                        startCountdown();
                        
                        // Focus on OTP input
                        otpInput.focus();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to Resend OTP',
                            text: data.message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error resending OTP:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to resend OTP. Please try again.',
                        confirmButtonColor: '#dc3545'
                    });
                })
                .finally(() => {
                    resendOtpBtn.disabled = false;
                    resendOtpBtn.innerHTML = '<i class="fas fa-redo"></i> Resend';
                });
            });

            // Countdown timer function
            function startCountdown() {
                let timeLeft = 600; // 10 minutes in seconds
                
                otpTimer.style.display = 'block';
                resendOtpBtn.disabled = true;
                
                countdownTimer = setInterval(() => {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    timerCount.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    
                    if (timeLeft <= 0) {
                        clearInterval(countdownTimer);
                        otpTimer.style.display = 'none';
                        resendOtpBtn.disabled = false;
                    }
                    
                    timeLeft--;
                }, 1000);
            }

            // OTP input validation
            otpInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });

            // Automatic capitalization for full name
            const fullName = document.getElementById('full_name');
            
            function capitalizeWords(str) {
                return str.replace(/\b\w/g, function(char) {
                    return char.toUpperCase();
                });
            }
            
            fullName.addEventListener('input', function() {
                const cursorPosition = this.selectionStart;
                const originalLength = this.value.length;
                
                // Capitalize words
                this.value = capitalizeWords(this.value);
                
                // Restore cursor position
                const newLength = this.value.length;
                if (newLength >= originalLength) {
                    this.setSelectionRange(cursorPosition, cursorPosition);
                }
            });
            
            fullName.addEventListener('blur', function() {
                // Ensure proper capitalization when leaving the field
                this.value = capitalizeWords(this.value);
            });
            
            // Update ID field label based on selected role
            const role = document.getElementById('role');
            const idLabel = document.getElementById('id_label');
            
            role.addEventListener('change', function() {
                if (this.value === 'student') {
                    idLabel.textContent = 'Student ID';
                } else if (this.value === 'faculty') {
                    idLabel.textContent = 'Faculty ID';
                } else {
                    idLabel.textContent = 'ID Number';
                }
            });
        });
    </script>
</body>
</html> 