<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="img/logo.png">
    <title>Profile - School Facility Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
        
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .profile-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 25px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-top: 3rem;
            margin-bottom: 5rem;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: #6c757d;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,.15);
        }
        
        .password-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .btn-save {
            padding: 0.75rem 2rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13,110,253,0.2);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .password-input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #495057;
        }

        .password-toggle:focus {
            outline: none;
            color: #0d6efd;
        }

        .password-feedback {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .password-feedback.valid {
            color: #198754;
        }

        .password-feedback.invalid {
            color: #dc3545;
        }

        .password-feedback.checking {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/navbar.php'; ?>
    
    <div class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-user me-3"></i>
                        User Profile
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Manage your account information and update your profile details</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h2 id="profile-name"><?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?></h2>
                <p class="text-muted"><?php echo isset($user['role']) ? htmlspecialchars($user['role']) : 'User'; ?></p>
            </div>
            
            <div id="alert-container"></div>
            
            <form id="profile-form">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" 
                               class="form-control" 
                               id="full_name" 
                               name="full_name" 
                               value="<?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : ''; ?>" 
                               required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                               title="Please enter a valid email address"
                               value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" 
                               required>
                        <div class="form-text">Enter a valid email address</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" 
                               class="form-control" 
                               id="phone" 
                               name="phone" 
                               pattern="\d{11}"
                               title="Please enter exactly 11 digits for phone number"
                               value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>" 
                               required>
                        <div class="form-text">Enter exactly 11 digits for phone number</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="department" class="form-label">Department</label>
                        <input type="text" 
                               class="form-control" 
                               id="department" 
                               name="department" 
                               value="<?php echo isset($user['department']) ? htmlspecialchars($user['department']) : ''; ?>" 
                               required>
                    </div>
                </div>
                
                <div class="password-section">
                    <h4 class="mb-3">Change Password</h4>
                    <p class="text-muted mb-4">Leave blank if you don't want to change your password</p>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="password-input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="current_password" 
                                       name="current_password"
                                       minlength="8">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current_password')">
                                    <i class="fas fa-eye" id="current_password_icon"></i>
                                </button>
                            </div>
                            <div class="password-feedback" id="current-password-feedback"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="password-input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="new_password" 
                                       name="new_password"
                                       minlength="8">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new_password')">
                                    <i class="fas fa-eye" id="new_password_icon"></i>
                                </button>
                            </div>
                            <div class="form-text">Password must be at least 8 characters long</div>
                            <div class="invalid-feedback" id="new-password-feedback"></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="password-input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password"
                                       minlength="8">
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password_icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-save">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('profile-form');
            const loadingOverlay = document.querySelector('.loading-overlay');
            let currentPasswordCheckTimeout;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                loadingOverlay.style.display = 'flex';

                const formData = new FormData(form);

                fetch('process_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loadingOverlay.style.display = 'none';

                    if (data.success) {
                        document.getElementById('profile-name').textContent = formData.get('full_name');

                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Profile updated successfully!',
                            showConfirmButton: false,
                            timer: 1500
                        });

                        document.getElementById('current_password').value = '';
                        document.getElementById('new_password').value = '';
                        document.getElementById('confirm_password').value = '';
                        
                        // Clear password feedback
                        const currentPasswordFeedback = document.getElementById('current-password-feedback');
                        currentPasswordFeedback.textContent = '';
                        currentPasswordFeedback.className = 'password-feedback';
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: data.message || 'Failed to update profile'
                        });
                    }
                })
                .catch(error => {
                    loadingOverlay.style.display = 'none';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An error occurred while updating your profile'
                    });
                });
            });

            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const newPasswordFeedback = document.getElementById('new-password-feedback');
            const currentPassword = document.getElementById('current_password');
            const currentPasswordFeedback = document.getElementById('current-password-feedback');

            // Password visibility toggle function
            window.togglePasswordVisibility = function(fieldId) {
                const input = document.getElementById(fieldId);
                const icon = document.getElementById(fieldId + '_icon');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            };

            // Current password validation with debouncing
            function validateCurrentPassword() {
                const passwordValue = currentPassword.value.trim();
                
                if (passwordValue.length === 0) {
                    currentPasswordFeedback.textContent = '';
                    currentPasswordFeedback.className = 'password-feedback';
                    return;
                }

                if (passwordValue.length < 8) {
                    currentPasswordFeedback.textContent = 'Password must be at least 8 characters';
                    currentPasswordFeedback.className = 'password-feedback invalid';
                    return;
                }

                // Clear previous timeout
                if (currentPasswordCheckTimeout) {
                    clearTimeout(currentPasswordCheckTimeout);
                }

                // Set checking state
                currentPasswordFeedback.textContent = 'Checking password...';
                currentPasswordFeedback.className = 'password-feedback checking';

                // Debounce the API call
                currentPasswordCheckTimeout = setTimeout(() => {
                    const formData = new FormData();
                    formData.append('current_password', passwordValue);

                    fetch('check_current_password.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            currentPasswordFeedback.textContent = '✓ Current password is correct';
                            currentPasswordFeedback.className = 'password-feedback valid';
                        } else {
                            currentPasswordFeedback.textContent = '✗ ' + data.message;
                            currentPasswordFeedback.className = 'password-feedback invalid';
                        }
                    })
                    .catch(error => {
                        currentPasswordFeedback.textContent = 'Error checking password';
                        currentPasswordFeedback.className = 'password-feedback invalid';
                    });
                }, 500); // 500ms delay
            }

            // Instant password validation
            function validateNewPassword() {
                const passwordValue = newPassword.value;
                const minLength = 8;
                
                if (passwordValue && passwordValue.length < minLength) {
                    newPassword.classList.add('is-invalid');
                    newPasswordFeedback.textContent = `Password must be at least ${minLength} characters long. Current length: ${passwordValue.length}`;
                    return false;
                } else {
                    newPassword.classList.remove('is-invalid');
                    newPassword.classList.add('is-valid');
                    newPasswordFeedback.textContent = '';
                    return true;
                }
            }

            function validatePasswords() {
                if (newPassword.value && newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            // Add event listeners for password validation
            newPassword.addEventListener('input', validateNewPassword);
            newPassword.addEventListener('blur', validateNewPassword);
            newPassword.addEventListener('keyup', validateNewPassword);
            
            newPassword.addEventListener('change', validatePasswords);
            confirmPassword.addEventListener('keyup', validatePasswords);

            // Add event listeners for current password validation
            currentPassword.addEventListener('input', validateCurrentPassword);
            currentPassword.addEventListener('blur', validateCurrentPassword);
            currentPassword.addEventListener('keyup', validateCurrentPassword);
        });
    </script>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 