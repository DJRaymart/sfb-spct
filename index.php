<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$auth = new Auth($conn);

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="img/logo.png">
    <title>Login - School Facility Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            width: 120px;
            height: 120px;
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

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--secondary-color);
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--primary-color);
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

        .btn-register {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.2);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-image">
            <h2>Welcome Back!</h2>
            <p>Access the School Facility Reservation System to manage your facility reservations efficiently.</p>
            <ul class="features-list">
                <li><i class="fas fa-calendar-check"></i> Easy facility booking</li>
                <li><i class="fas fa-clock"></i> Real-time availability</li>
                <li><i class="fas fa-bell"></i> Instant notifications</li>
                <li><i class="fas fa-history"></i> Booking history</li>
                <li><i class="fas fa-chart-bar"></i> Usage analytics</li>
                <li><i class="fas fa-shield-alt"></i> Secure access</li>
            </ul>
        </div>
        <div class="login-form">
            <div class="text-center mb-4">
                <img src="img/logo.jpg" alt="School Logo" class="school-logo">
                <div class="school-name">School Facility Reservation <br>System</div>
                <p class="text-muted">Please login to your account</p>
            </div>

            <form action="process_login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?php echo isset($_SESSION['preserved_username']) ? htmlspecialchars($_SESSION['preserved_username']) : ''; ?>" required>
                    <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                </div>
                <div class="form-floating position-relative">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="text-decoration-none">Forgot Password?</a>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </div>
            </form>

            <div class="login-footer">
                <div class="text-center mb-3">
                    <p class="mb-2">Don't have an account?</p>
                    <a href="register.php" class="btn btn-outline-primary btn-register">
                        <i class="fas fa-user-plus me-2"></i>Register Now
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });



        // Show error message if exists
        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Login Failed',
                text: <?php echo json_encode($_SESSION['error']); ?>,
                confirmButtonColor: '#4e73df'
            });
            <?php 
                unset($_SESSION['error']); 
                // Don't unset preserved_username here, let it stay for the form
            ?>
        <?php endif; ?>

        // Clear preserved username if no error (successful page load)
        <?php if (!isset($_SESSION['error']) && isset($_SESSION['preserved_username'])): ?>
            <?php unset($_SESSION['preserved_username']); ?>
        <?php endif; ?>

        // Show success message if exists
        <?php if (isset($_SESSION['success_message'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: <?php echo json_encode($_SESSION['success_message']); ?>,
                showConfirmButton: false,
                timer: 3000
            });
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
    </script>
</body>
</html> 