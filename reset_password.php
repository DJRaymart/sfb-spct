<?php
session_start();
require_once 'config/database.php';
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
$valid_token = false;

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (!isset($_GET['token'])) {
    header('Location: forgot_password.php');
    exit();
}

// Sanitize the token
$token = htmlspecialchars(trim($_GET['token']));

// Validate token format (should be a 64-character hexadecimal string)
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    header('Location: forgot_password.php');
    exit();
}

try {
    $valid_token = $auth->verifyResetToken($token);
} catch (Exception $e) {
    $error = 'Invalid or expired reset token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$valid_token) {
        $error = 'Invalid or expired reset token.';
    } else {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token_submitted = $_POST['csrf_token'] ?? '';
    
    if (empty($csrf_token_submitted) || $csrf_token_submitted !== $csrf_token) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!$validator->validatePassword($password)) {
        $error = $validator->getFirstError();
    } else {
        try {
            $result = $auth->resetPassword($token, $password);
            if ($result['success']) {
                $success = 'Password has been reset successfully. You can now login with your new password.';
                $valid_token = false; // Prevent multiple submissions
            } else {
                $error = $result['message'] ?? 'Failed to reset password.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again later.';
        }
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="img/logo.png">
    <title>Reset Password - School Facility Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            background: url('img/spct.jpg') center/cover;
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

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: var(--secondary-color);
        }

        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-footer a:hover {
            color: #224abe;
        }

        .school-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: url('img/logo.jpg') center/cover;
            display: block;
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-image">
            <h2>Reset Your Password</h2>
            <p>Please enter your new password to regain access to your account.</p>
            <ul class="features-list">
                <li><i class="fas fa-shield-alt"></i> Secure password reset process</li>
                <li><i class="fas fa-lock"></i> Strong password requirements</li>
                <li><i class="fas fa-user-shield"></i> Protected account access</li>
                <li><i class="fas fa-clock"></i> One-time use token</li>
            </ul>
        </div>
        <div class="login-form">
            <div class="text-center mb-4">
                <div class="school-logo"></div>
                <h2 class="school-name">School Facility Reservation System</h2>
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
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Redirect to forgot password page for invalid tokens
                                if ('<?php echo htmlspecialchars($error); ?>'.includes('Invalid or expired')) {
                                    window.location.href = 'forgot_password.php';
                                }
                            }
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($success): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Password Reset Successful!',
                            text: '<?php echo htmlspecialchars($success); ?>',
                            confirmButtonColor: '#28a745',
                            confirmButtonText: 'Go to Login',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'index.php';
                            }
                        });
                    });
                </script>
            <?php endif; ?>

            <?php if ($valid_token): ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-2" id="passwordStrength" style="display: none;">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" id="strengthBar" role="progressbar"></div>
                            </div>
                            <small class="text-muted" id="strengthText"></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                <i class="fas fa-eye" id="toggleConfirmPasswordIcon"></i>
                            </button>
                        </div>
                        <div class="password-match mt-2" id="passwordMatch" style="display: none;">
                            <small class="text-muted" id="matchText"></small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Password must be at least 8 characters long and contain uppercase letters, numbers, and special characters.
                        </small>
                    </div>
                    <button type="submit" class="btn btn-login btn-primary w-100">
                        <i class="fas fa-save me-2"></i>Reset Password
                    </button>
                </form>
            <?php else: ?>
                <?php if (!$error && !$success): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Invalid Reset Link',
                                text: 'The password reset link is invalid or has expired. Please request a new password reset.',
                                confirmButtonColor: '#ffc107',
                                confirmButtonText: 'Request New Link',
                                showCancelButton: true,
                                cancelButtonText: 'Go to Login',
                                cancelButtonColor: '#6c757d'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'forgot_password.php';
                                } else {
                                    window.location.href = 'index.php';
                                }
                            });
                        });
                    </script>
                    <div class="text-center">
                        <p class="text-muted">Redirecting...</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="login-footer">
                <a href="index.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Login
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordStrength = document.getElementById('passwordStrength');
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            const passwordMatch = document.getElementById('passwordMatch');
            const matchText = document.getElementById('matchText');
            
            // Password strength validation
            function checkPasswordStrength(password) {
                let strength = 0;
                let feedback = [];
                
                if (password.length >= 8) strength += 25;
                else feedback.push('At least 8 characters');
                
                if (/[a-z]/.test(password)) strength += 25;
                else feedback.push('Lowercase letter');
                
                if (/[A-Z]/.test(password)) strength += 25;
                else feedback.push('Uppercase letter');
                
                if (/[0-9]/.test(password)) strength += 25;
                else feedback.push('Number');
                
                if (/[^A-Za-z0-9]/.test(password)) strength += 25;
                else feedback.push('Special character');
                
                return { strength, feedback };
            }
            
            // Update password strength display
            function updatePasswordStrength() {
                const passwordValue = password.value;
                if (passwordValue.length === 0) {
                    passwordStrength.style.display = 'none';
                    return;
                }
                
                const { strength, feedback } = checkPasswordStrength(passwordValue);
                passwordStrength.style.display = 'block';
                
                // Update progress bar
                strengthBar.style.width = strength + '%';
                
                // Set color based on strength
                if (strength <= 25) {
                    strengthBar.className = 'progress-bar bg-danger';
                    strengthText.textContent = 'Very Weak';
                    strengthText.className = 'text-danger';
                } else if (strength <= 50) {
                    strengthBar.className = 'progress-bar bg-warning';
                    strengthText.textContent = 'Weak';
                    strengthText.className = 'text-warning';
                } else if (strength <= 75) {
                    strengthBar.className = 'progress-bar bg-info';
                    strengthText.textContent = 'Good';
                    strengthText.className = 'text-info';
                } else {
                    strengthBar.className = 'progress-bar bg-success';
                    strengthText.textContent = 'Strong';
                    strengthText.className = 'text-success';
                }
            }
            
            // Check password match
            function checkPasswordMatch() {
                const passwordValue = password.value;
                const confirmValue = confirmPassword.value;
                
                if (confirmValue.length === 0) {
                    passwordMatch.style.display = 'none';
                    return;
                }
                
                passwordMatch.style.display = 'block';
                
                if (passwordValue === confirmValue) {
                    matchText.textContent = '✓ Passwords match';
                    matchText.className = 'text-success';
                } else {
                    matchText.textContent = '✗ Passwords do not match';
                    matchText.className = 'text-danger';
                }
            }
            
                         // Password visibility toggle functionality
             const togglePassword = document.getElementById('togglePassword');
             const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
             const togglePasswordIcon = document.getElementById('togglePasswordIcon');
             const toggleConfirmPasswordIcon = document.getElementById('toggleConfirmPasswordIcon');
             
             // Toggle password visibility for first password field
             togglePassword.addEventListener('click', function() {
                 const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                 password.setAttribute('type', type);
                 
                 // Toggle icon
                 if (type === 'text') {
                     togglePasswordIcon.classList.remove('fa-eye');
                     togglePasswordIcon.classList.add('fa-eye-slash');
                 } else {
                     togglePasswordIcon.classList.remove('fa-eye-slash');
                     togglePasswordIcon.classList.add('fa-eye');
                 }
             });
             
             // Toggle password visibility for confirm password field
             toggleConfirmPassword.addEventListener('click', function() {
                 const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                 confirmPassword.setAttribute('type', type);
                 
                 // Toggle icon
                 if (type === 'text') {
                     toggleConfirmPasswordIcon.classList.remove('fa-eye');
                     toggleConfirmPasswordIcon.classList.add('fa-eye-slash');
                 } else {
                     toggleConfirmPasswordIcon.classList.remove('fa-eye-slash');
                     toggleConfirmPasswordIcon.classList.add('fa-eye');
                 }
             });
             
             // Add event listeners
             password.addEventListener('input', updatePasswordStrength);
             confirmPassword.addEventListener('input', checkPasswordMatch);
            
            // Handle form submission with confirmation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const passwordValue = password.value;
                    const confirmPasswordValue = confirmPassword.value;
                    
                    // Basic validation
                    if (!passwordValue || !confirmPasswordValue) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Missing Information',
                            text: 'Please fill in all fields.',
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                    
                    if (passwordValue !== confirmPasswordValue) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Passwords Do Not Match',
                            text: 'Please make sure both passwords are identical.',
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: 'OK'
                        });
                        return;
                    }
                    
                    const { strength } = checkPasswordStrength(passwordValue);
                    if (strength < 50) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Weak Password',
                            text: 'Your password is weak. Consider using a stronger password with uppercase letters, numbers, and special characters.',
                            showCancelButton: true,
                            confirmButtonColor: '#ffc107',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Continue Anyway',
                            cancelButtonText: 'Improve Password'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                showConfirmationDialog();
                            }
                        });
                        return;
                    }
                    
                    showConfirmationDialog();
                });
            }
            
            function showConfirmationDialog() {
                Swal.fire({
                    title: 'Confirm Password Reset',
                    text: 'Are you sure you want to reset your password?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Reset Password',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Submit the form
                        form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html> 