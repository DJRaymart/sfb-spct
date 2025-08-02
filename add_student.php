<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

$auth = new Auth($conn);

// Check if user is logged in and is faculty
if (!$auth->isLoggedIn() || !$auth->isFaculty()) {
            header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $department_name = $_POST['department_name'] ?? '';
    $id_number = $_POST['id_number'] ?? '';

    if (!empty($username) && !empty($password) && !empty($email) && !empty($full_name) && !empty($phone) && !empty($department_name)) {
        try {
            // Validate phone number (must be exactly 11 digits)
            if (!preg_match('/^\d{11}$/', $phone)) {
                $_SESSION['error_message'] = "Phone number must be exactly 11 digits.";
                header('Location: add_student.php');
                exit();
            }
            
            // Debug log the input data
            error_log("Attempting to add student with data: " . json_encode([
                'username' => $username,
                'email' => $email,
                'full_name' => $full_name,
                'phone' => $phone,
                'department' => $department_name
            ]));

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // First check if username or email already exists
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $check_stmt->execute([$username, $email]);
            $exists = $check_stmt->fetchColumn();
            
            if ($exists > 0) {
                $_SESSION['error_message'] = "Username or email already exists.";
                header('Location: add_student.php');
                exit();
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Insert user
                $stmt = $conn->prepare("
                    INSERT INTO users (username, password, email, full_name, role, phone, department, status, id_number, created_at)
                    VALUES (:username, :password, :email, :full_name, 'student', :phone, :department, 'active', :id_number, NOW())
                ");
                
                $result = $stmt->execute([
                    'username' => $username,
                    'password' => $hashed_password,
                    'email' => $email,
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'department' => $department_name,
                    'id_number' => $id_number
                ]);
                
                if (!$result) {
                    error_log("Database error: " . print_r($stmt->errorInfo(), true));
                    throw new PDOException("Failed to insert user");
                }
                
                $user_id = $conn->lastInsertId();
                error_log("Student added successfully with ID: " . $user_id);
                
                // Commit transaction
                $conn->commit();
                
                // Send welcome email
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
                    $mail->addAddress($email, $full_name);

                    $mail->isHTML(true);
                    $mail->Subject = 'Welcome to School Facility Reservation System';
                    $mail->Body = "
                        <html>
                        <head>
                            <title>Welcome to School Facility Reservation System</title>
                        </head>
                        <body>
                            <h2>Welcome to School Facility Reservation System!</h2>
                            <p>Dear {$full_name},</p>
                            <p>Your account has been created by a faculty member. You can now log in to your account and start using our facility reservation system.</p>
                            <p>Here are your account details:</p>
                            <ul>
                                <li><strong>Username:</strong> {$username}</li>
                                <li><strong>Email:</strong> {$email}</li>
                                <li><strong>Role:</strong> Student</li>
                                <li><strong>Department:</strong> {$department_name}</li>
                            </ul>
                            <p>Best regards,<br>School Facility Reservation System Team</p>
                        </body>
                        </html>
                    ";

                    $mail->send();
                    error_log("Welcome email sent successfully to: " . $email);
                } catch (Exception $e) {
                    error_log("Registration email error: " . $e->getMessage());
                    // Don't stop the process if email fails
                }
                
                $_SESSION['success_message'] = "Student added successfully!";
                header('Location: dashboard.php');
                exit();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                throw $e;
            }
            
        } catch (PDOException $e) {
            error_log("Database error in add_student.php: " . $e->getMessage());
            error_log("Error code: " . $e->getCode());
            error_log("Stack trace: " . $e->getTraceAsString());
            error_log("SQL State: " . $e->getCode());
            error_log("Error Info: " . print_r($e->errorInfo, true));
            
            if ($e->getCode() == 23000) {
                $_SESSION['error_message'] = "Username or email already exists.";
            } else {
                $_SESSION['error_message'] = "Failed to add student. Please try again.";
            }
            header('Location: add_student.php');
            exit();
        } catch (Exception $e) {
            error_log("General error in add_student.php: " . $e->getMessage());
            $_SESSION['error_message'] = "An unexpected error occurred. Please try again.";
            header('Location: add_student.php');
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Please fill in all required fields.";
        header('Location: add_student.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" type="image/x-icon" href="img/logo.png">
    <title>Add Student - School Facility Reservation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .page-header {
            background: white;
            color: #333;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
        }

        .dashboard-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 25px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 30px;
            margin-bottom: 5rem;
        }

        .form-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        
        .form-section h3 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .form-section h3 i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        
        .form-control.is-valid {
            border-color: var(--success-color);
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }
        
        .form-control.is-invalid {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .btn-add-student {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0a58ca 100%);
            border: none;
            padding: 0.8rem 2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: white;
        }
        
        .btn-add-student:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
            color: white;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background-color: #fff5f5;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        .alert-success {
            background-color: #f0fff4;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .form-text {
            color: var(--secondary-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .invalid-feedback {
            color: var(--danger-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .valid-feedback {
            color: var(--success-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .btn-export {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5a6268 100%);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-export:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(108, 117, 125, 0.3);
            color: white;
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.25rem;
        }

        .navbar-brand i {
            margin-right: 0.5rem;
        }

        .nav-link {
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            transform: translateY(-1px);
        }

        .nav-link i {
            margin-right: 0.5rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-name {
            font-weight: 500;
        }

        .dropdown-menu {
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: none;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background-color: var(--light-color);
            transform: translateX(5px);
        }

        .dropdown-item i {
            margin-right: 0.5rem;
            width: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-user-plus me-3"></i>
                        Add New Student
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Create new student accounts for the School Facility Reservation System</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-container">
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                        echo htmlspecialchars($_SESSION['success_message']);
                        unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form id="addStudentForm" action="add_student.php" method="POST">
                <div class="form-section">
                    <h3><i class="fas fa-user-circle"></i>Account Information</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="form-text">Username must be at least 3 characters long</div>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="form-text">Password must be at least 8 characters long</div>
                            <div class="invalid-feedback" id="password-feedback"></div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-user"></i>Personal Information</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-id-badge"></i>Contact Information</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   pattern="\d{11}"
                                   title="Please enter exactly 11 digits for phone number"
                                   required>
                            <div class="form-text">Enter exactly 11 digits for phone number</div>
                        </div>
                        <div class="col-md-6">
                            <label for="department_name" class="form-label">Department</label>
                            <input type="text" class="form-control" id="department_name" name="department_name" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-id-card"></i>Student ID</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="id_number" class="form-label">Student ID Number</label>
                            <input type="text" class="form-control" id="id_number" name="id_number" required>
                            <div class="form-text">Enter the student's ID number for verification</div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-add-student">
                        <i class="fas fa-user-plus me-2"></i>Add Student
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('addStudentForm');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Create FormData object
                const formData = new FormData(form);
                
                // Show confirmation dialog with custom styling
                Swal.fire({
                    title: 'Confirm Student Registration',
                    html: `
                        <div class="text-start">
                            <div class="mb-3">
                                <i class="fas fa-user-circle text-primary me-2"></i>
                                <strong>Account Information</strong>
                                <p class="mb-1">Username: ${formData.get('username')}</p>
                            </div>
                            <div class="mb-3">
                                <i class="fas fa-user text-success me-2"></i>
                                <strong>Personal Information</strong>
                                <p class="mb-1">Full Name: ${formData.get('full_name')}</p>
                                <p class="mb-1">Email: ${formData.get('email')}</p>
                            </div>
                            <div class="mb-3">
                                <i class="fas fa-id-badge text-info me-2"></i>
                                <strong>Contact Information</strong>
                                <p class="mb-1">Phone: ${formData.get('phone')}</p>
                                <p class="mb-1">Department: ${formData.get('department_name')}</p>
                            </div>
                            <div class="mb-3">
                                <i class="fas fa-id-card text-success me-2"></i>
                                <strong>Student ID</strong>
                                <p class="mb-1">ID Number: ${formData.get('id_number')}</p>
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-check me-2"></i>Yes, add student!',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>No, review again',
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-secondary',
                        popup: 'swal2-popup-custom'
                    },
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Adding Student...',
                            html: '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            willOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit the form
                        form.submit();
                    }
                });
            });

            // Add custom styles for SweetAlert2
            const style = document.createElement('style');
            style.textContent = `
                .swal2-popup-custom {
                    border-radius: 15px;
                    padding: 2rem;
                }
                .swal2-popup-custom .swal2-title {
                    color: #0d6efd;
                    font-weight: 600;
                    margin-bottom: 1.5rem;
                }
                .swal2-popup-custom .swal2-html-container {
                    text-align: left;
                }
                .swal2-popup-custom .swal2-html-container strong {
                    color: #0d6efd;
                }
                .swal2-popup-custom .swal2-html-container p {
                    margin-bottom: 0.5rem;
                    color: #495057;
                }
                .swal2-popup-custom .swal2-html-container i {
                    width: 20px;
                    text-align: center;
                }
                .swal2-popup-custom .swal2-confirm,
                .swal2-popup-custom .swal2-cancel {
                    padding: 0.75rem 1.5rem;
                    font-weight: 500;
                    border-radius: 8px;
                }
            `;
            document.head.appendChild(style);

            // Form validation
            const username = document.getElementById('username');
            username.addEventListener('blur', function() {
                if (this.value.length < 3) {
                    this.setCustomValidity('Username must be at least 3 characters long');
                } else {
                    this.setCustomValidity('');
                }
            });

            const email = document.getElementById('email');
            email.addEventListener('blur', function() {
                const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
                if (!emailPattern.test(this.value)) {
                    this.setCustomValidity('Please enter a valid email address');
                } else {
                    this.setCustomValidity('');
                }
            });

            const phone = document.getElementById('phone');
            phone.addEventListener('blur', function() {
                const phonePattern = /^\d{11}$/;
                if (!phonePattern.test(this.value.replace(/[^0-9]/g, ''))) {
                    this.setCustomValidity('Please enter exactly 11 digits for phone number');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Password validation
            const password = document.getElementById('password');
            const passwordFeedback = document.getElementById('password-feedback');
            
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
            
            // Add event listeners for instant password validation
            password.addEventListener('input', validatePassword);
            password.addEventListener('blur', validatePassword);
            password.addEventListener('keyup', validatePassword);
        });

        function exportStudents() {
            // Placeholder function for exporting students
            Swal.fire({
                title: 'Export Students',
                text: 'This feature will be implemented soon.',
                icon: 'info',
                confirmButtonColor: '#0d6efd'
            });
        }
    </script>
    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 