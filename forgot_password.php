<?php
session_start();
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/Auth.php';
require_once 'includes/Validator.php';

$auth = new Auth($conn);
$validator = new Validator();

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Get email from URL parameter if available
$prefilled_email = $_GET['email'] ?? '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $csrf_token_submitted = $_POST['csrf_token'] ?? '';
    
    if (empty($csrf_token_submitted) || $csrf_token_submitted !== $csrf_token) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!$validator->validateRecaptcha($recaptcha_response)) {
        $error = $validator->getFirstError();
    } else {
        try {
            // Check if there's a recent password reset request (within 5 minutes)
            $stmt = $conn->prepare("
                SELECT created_at 
                FROM password_resets 
                WHERE user_id = (SELECT id FROM users WHERE email = ?) 
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $recentRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($recentRequest) {
                $timeDiff = time() - strtotime($recentRequest['created_at']);
                $remainingMinutes = ceil((300 - $timeDiff) / 60); // 300 seconds = 5 minutes
                $error = "Please wait {$remainingMinutes} minute(s) before requesting another password reset.";
            } else {
                $result = $auth->sendPasswordResetEmail($email);
                if ($result['success']) {
                    $success = 'Password reset instructions have been sent to your email.';
                } else {
                    $error = $result['message'] ?? 'Failed to send reset instructions.';
                }
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again later.';
        }
    }
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="img/logo.png">
    <title>Forgot Password - School Facility Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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

        body {
            background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            display: flex;
            margin: 20px;
        }

        .login-image {
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

        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('img/spct3.jpg') center/cover;
            opacity: 0.2;
        }

        .login-image h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .login-image p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .features-list li {
            margin: 15px 0;
            display: flex;
            align-items: center;
            font-size: 1rem;
        }

        .features-list li i {
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

        .login-form {
            padding: 40px;
            width: 55%;
            background: white;
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

        .btn-primary {
            width: 100%;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            border: none;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
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

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 768px) {
            .login-image {
                display: none;
            }
            .login-form {
                width: 100%;
                padding: 30px;
            }
            .school-name {
                font-size: 1.5rem;
            }
        }

        .form-floating {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-floating label {
            padding: 1rem 0.75rem;
            color: var(--secondary-color);
        }

        .form-floating input:focus ~ label,
        .form-floating input:not(:placeholder-shown) ~ label {
            color: var(--primary-color);
        }

        .recaptcha-container {
            display: flex;
            justify-content: center;
            margin: 0px 0;
            padding: 5px 0;
        }

        .g-recaptcha {
            transform: scale(0.9);
            transform-origin: center;
        }

        .form-container {
            display: flex;
            flex-direction: column;
            gap: 0px;
            width: 100%;
        }

        .submit-button-container {
            width: 100%;
            margin-top: 0px;
        }

        @media (max-width: 768px) {
            .g-recaptcha {
                transform: scale(0.85);
                transform-origin: center;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-image">
            <h2>Reset Your Password</h2>
            <p>Don't worry! It happens to the best of us. Follow these steps to regain access to your account.</p>
            <ul class="features-list">
                <li><i class="fas fa-envelope"></i> Enter your email address</li>
                <li><i class="fas fa-paper-plane"></i> Receive reset instructions</li>
                <li><i class="fas fa-link"></i> Click the reset link</li>
                <li><i class="fas fa-key"></i> Set your new password</li>
                <li><i class="fas fa-sign-in-alt"></i> Login with new credentials</li>
                <li><i class="fas fa-shield-alt"></i> Secure password reset</li>
            </ul>
        </div>
        <div class="login-form">
            <div class="text-center mb-4">
                <img src="img/logo.jpg" alt="School Logo" class="school-logo">
                <div class="school-name">School Facility Reservation <br>System</div>
                <p class="text-muted">Enter your email to reset your password</p>
            </div>

            <?php if ($error): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: '<?php echo htmlspecialchars($error); ?>',
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: 'OK',
                            allowOutsideClick: false
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($success): ?>
                <script data-success="true">
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Password Reset Email Sent!',
                            text: '<?php echo htmlspecialchars($success); ?>',
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'OK',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Start 5-minute cooldown after successful reset request
                                const submitBtn = document.getElementById('submitBtn');
                                const cooldownMessage = document.getElementById('cooldownMessage');
                                const countdownTimer = document.getElementById('countdownTimer');
                                
                                submitBtn.disabled = true;
                                submitBtn.innerHTML = '<i class="fas fa-clock me-2"></i>Please Wait...';
                                cooldownMessage.style.display = 'block';
                                
                                const endTime = Date.now() + (300000); // 5 minutes (300,000 milliseconds)
                                sessionStorage.setItem('passwordResetCooldown', endTime.toString());
                                
                                const countdownInterval = setInterval(() => {
                                    const remaining = endTime - Date.now();
                                    
                                    if (remaining <= 0) {
                                        clearInterval(countdownInterval);
                                        submitBtn.disabled = false;
                                        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Reset Instructions';
                                        cooldownMessage.style.display = 'none';
                                        sessionStorage.removeItem('passwordResetCooldown');
                                    } else {
                                        const minutes = Math.floor(remaining / 60000);
                                        const seconds = Math.floor((remaining % 60000) / 1000);
                                        countdownTimer.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                                    }
                                }, 1000);
                            }
                        });
                    });
                </script>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-container">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-floating">
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($prefilled_email); ?>" required>
                        <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                    </div>
                    <div class="recaptcha-container">
                        <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
                    </div>
                    <div class="submit-button-container">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                        </button>
                        <div id="cooldownMessage" class="text-center mt-3" style="display: none;">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Please wait <span id="countdownTimer">5:00</span> before requesting another reset
                            </small>
                        </div>
                    </div>
                </div>
            </form>

            <div class="text-center mt-4">
                <a href="index.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.getElementById('submitBtn');
            const cooldownMessage = document.getElementById('cooldownMessage');
            const countdownTimer = document.getElementById('countdownTimer');
            let countdownInterval;
            
            // Check if there's a cooldown in session storage
            const cooldownEndTime = sessionStorage.getItem('passwordResetCooldown');
            if (cooldownEndTime) {
                const now = Date.now();
                const endTime = parseInt(cooldownEndTime);
                
                if (now < endTime) {
                    // Still in cooldown period
                    startCooldownCountdown(endTime - now);
                } else {
                    // Cooldown expired, clear storage
                    sessionStorage.removeItem('passwordResetCooldown');
                }
            }
            
            function startCooldownCountdown(duration) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-clock me-2"></i>Please Wait...';
                cooldownMessage.style.display = 'block';
                
                const endTime = Date.now() + duration;
                sessionStorage.setItem('passwordResetCooldown', endTime.toString());
                
                countdownInterval = setInterval(() => {
                    const remaining = endTime - Date.now();
                    
                    if (remaining <= 0) {
                        // Cooldown finished
                        clearInterval(countdownInterval);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Reset Instructions';
                        cooldownMessage.style.display = 'none';
                        sessionStorage.removeItem('passwordResetCooldown');
                    } else {
                        // Update countdown display
                        const minutes = Math.floor(remaining / 60000);
                        const seconds = Math.floor((remaining % 60000) / 1000);
                        countdownTimer.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    }
                }, 1000);
            }
            
            // Handle form submission
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                // Check if in cooldown
                const cooldownEndTime = sessionStorage.getItem('passwordResetCooldown');
                if (cooldownEndTime) {
                    const now = Date.now();
                    const endTime = parseInt(cooldownEndTime);
                    
                    if (now < endTime) {
                        e.preventDefault();
                        const remaining = endTime - now;
                        const minutes = Math.floor(remaining / 60000);
                        const seconds = Math.floor((remaining % 60000) / 1000);
                        
                        Swal.fire({
                            icon: 'warning',
                            title: 'Please Wait',
                            text: `You can request another password reset in ${minutes}:${seconds.toString().padStart(2, '0')}`,
                            confirmButtonColor: '#ffc107',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                }
                
                // If not in cooldown, allow submission
                // The server will handle the actual cooldown check
            });
            
            // Listen for success message to start cooldown
            const successScript = document.querySelector('script[data-success]');
            if (successScript) {
                // Success message was shown, start 5-minute cooldown
                startCooldownCountdown(5 * 60 * 1000); // 5 minutes in milliseconds
            }
        });
    </script>
</body>
</html> 