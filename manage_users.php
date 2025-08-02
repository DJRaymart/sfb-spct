<?php
session_start();
require_once 'config/database.php';
require_once 'config/config.php';
require_once 'includes/Auth.php';

try {
    $auth = new Auth($conn);

    if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
        header('Location: index.php');
        exit;
    }

    $stmt = $conn->query("SELECT * FROM users ORDER BY full_name ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['error_message'] = "Database connection error: " . $e->getMessage();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_POST['user_id'] ?? '';
    
    if ($action && $user_id) {
        try {
            if ($action === 'approve' || $action === 'reject') {
                // Handle faculty approval/rejection (students are auto-approved)
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'faculty' AND status = 'pending'");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    throw new Exception('Invalid user or already processed');
                }
                
                $new_status = ($action === 'approve') ? 'active' : 'inactive';
                
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                
                // Send email notification
                require_once 'vendor/autoload.php';
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                try {
                    error_log("Attempting to send approval/rejection email to: " . $user['email']);
                    
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USERNAME;
                    $mail->Password = SMTP_PASSWORD;
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = SMTP_PORT;
                    
                    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                    $mail->addAddress($user['email'], $user['full_name']);
                    
                    $mail->isHTML(true);
                    $mail->Subject = $user['role'] . ' Account Status Update';
                    
                    $role_title = ucfirst($user['role']);
                    
                    if ($action === 'approve') {
                        $mail->Body = "
                            <html>
                            <head>
                                <title>Account Approved</title>
                            </head>
                            <body>
                                <h2>Account Approved</h2>
                                <p>Dear {$user['full_name']},</p>
                                <p>Your {$user['role']} account has been approved by the administrator. You can now log in to your account and start using the School Facility Reservation System.</p>
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
                                <p>We regret to inform you that your {$user['role']} account registration has been rejected by the administrator.</p>
                                <p>If you believe this is a mistake, please contact the administrator for more information.</p>
                                <p>Best regards,<br>School Facility Reservation System Team</p>
                            </body>
                            </html>
                        ";
                    }
                    
                    $mail->send();
                    error_log("Approval/rejection email sent successfully to: " . $user['email']);
                } catch (Exception $e) {
                    error_log("Email notification error: " . $e->getMessage());
                    error_log("Email error details: " . print_r($e, true));
                }
                
                $_SESSION['success_message'] = ucfirst($user['role']) . " account has been " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";
                
                // Return JSON response for AJAX requests
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    echo json_encode(['success' => true, 'message' => ucfirst($user['role']) . " account has been " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully."]);
                    exit();
                }
                
                header('Location: manage_users.php');
                exit();
            }
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
            
            // Return JSON response for AJAX requests
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                echo json_encode(['success' => false, 'message' => "Error processing request: " . $e->getMessage()]);
                exit();
            }
            
            header('Location: manage_users.php');
            exit();
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
    <title>Manage Users - School Facility Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
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
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0px 25px rgba(0,0,0,0.1);
            margin-bottom: 5rem;
        }
        
        .table th {
            font-weight: 600;
            color: #495057;
            border-top: none;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            margin: 0 0.25rem;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
        }
        
        .dataTables_wrapper .dataTables_length select {
            border-radius: 20px;
            padding: 0.25rem 2rem 0.25rem 1rem;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 5px;
            margin: 0 2px;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #0d6efd !important;
            border-color: #0d6efd !important;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .page-header {
                padding: 1rem 0;
                margin-bottom: 1rem;
            }

            .dashboard-container {
                padding: 15px;
                margin-top: 15px;
                margin-bottom: 2rem;
            }

            .card {
                margin-bottom: 2rem;
            }

            .table-responsive {
                margin: 0 -15px;
                padding: 0 15px;
            }

            .table td, .table th {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }

            .btn-action {
                padding: 0.25rem;
                margin: 0 0.15rem;
                min-width: 32px;
                height: 32px;
            }

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                margin-bottom: 1rem;
            }

            .dataTables_wrapper .dataTables_length select {
                padding: 0.25rem 1.5rem 0.25rem 0.75rem;
            }

            .dataTables_wrapper .dataTables_filter input {
                padding: 0.25rem 0.75rem;
            }

            .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }

            .modal-body {
                padding: 15px;
            }

            .modal-footer {
                flex-direction: column;
                gap: 10px;
            }

            .modal-footer .btn {
                width: 100%;
            }

            /* Form controls for mobile */
            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 0.75rem 1rem;
            }

            /* Button groups for mobile */
            .btn-group {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .btn-group .btn {
                flex: 1;
                min-width: 44px;
                height: 44px;
            }
        }

        /* Improve touch targets */
        @media (hover: none) {
            .btn, .form-control, .form-select {
                min-height: 44px;
            }

            .table td, .table th {
                padding: 1rem 0.75rem;
            }

            .btn-action {
                min-width: 44px;
                height: 44px;
            }
        }

        /* Add smooth transitions */
        .table-container, .btn-action, .modal-content {
            transition: all 0.3s ease-in-out;
        }

        /* Prevent body scroll when modal is open */
        body.modal-open {
            overflow: hidden;
        }

        .modal-backdrop {
            z-index: 1040;
        }

        .modal {
            z-index: 1050;
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
                        <i class="fas fa-users me-3"></i>
                        Manage Users
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Manage user accounts, roles, and permissions for the School Facility Reservation System</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex justify-content-md-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="dashboard-container">

        <div class="card">
            <div class="card-body">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ID Number</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['id_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'faculty' && $user['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-<?php echo ($user['status'] ?? 'active') === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($user['role'] === 'faculty' && $user['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-success btn-sm approve-user" data-id="<?php echo $user['id']; ?>" data-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm reject-user" data-id="<?php echo $user['id']; ?>" data-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-action btn-info" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit this user">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-action btn-danger delete-user" data-id="<?php echo $user['id']; ?>" title="Delete this user">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

                <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                <div class="modal-body">
                    <form id="addUserForm" action="add_user.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" title="Enter user's full name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" title="Enter a unique username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" title="Enter user's email address" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" id="addUserPassword" title="Enter a strong password" required>
                            <div class="form-text">Password must be at least 8 characters long</div>
                            <div class="invalid-feedback" id="addUserPassword-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" title="Select user's role in the system" required>
                                <option value="student">Student</option>
                                <option value="faculty">Faculty</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" title="Select user's account status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   name="phone" 
                                   pattern="\d{11}" 
                                   oninvalid="this.setCustomValidity('Please enter exactly 11 digits for phone number')"
                                   oninput="this.setCustomValidity('')"
                                   title="Enter exactly 11 digits for phone number" 
                                   required>
                            <div class="form-text">Format: 11 digits only (e.g., 09123456789)</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" title="Cancel adding user">Cancel</button>
                    <button type="submit" form="addUserForm" class="btn btn-primary" title="Add new user to the system">Add User</button>
                </div>
            </div>
        </div>
    </div>

                <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                <div class="modal-body">
                    <form id="editUserForm" action="edit_user.php" method="POST">
                        <input type="hidden" name="id" id="editUserId">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" id="editUserFullName" title="Edit user's full name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="editUserUsername" title="Edit username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editUserEmail" title="Edit user's email address" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" name="password" id="editUserPassword" title="Enter new password or leave blank to keep current">
                            <div class="form-text">Password must be at least 8 characters long (if provided)</div>
                            <div class="invalid-feedback" id="editUserPassword-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="editUserRole" title="Change user's role" required>
                                <option value="student">Student</option>
                                <option value="faculty">Faculty</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editUserStatus" title="Change user's account status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" 
                                   class="form-control" 
                                   name="phone" 
                                   id="editUserPhone" 
                                   pattern="\d{11}" 
                                   oninvalid="this.setCustomValidity('Please enter exactly 11 digits for phone number')"
                                   oninput="this.setCustomValidity('')"
                                   title="Enter exactly 11 digits for phone number" 
                                   required>
                            <div class="form-text">Format: 11 digits only (e.g., 09123456789)</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" id="editIdLabel">ID Number</label>
                            <input type="text" class="form-control" name="id_number" id="editUserIdNumber">
                            <div class="form-text">Student or Faculty ID number for verification</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" title="Cancel editing user">Cancel</button>
                    <button type="submit" form="editUserForm" class="btn btn-primary" title="Save changes to user">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

                <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                    <div class="mb-3">
                        <label class="form-label">Reason for Deletion</label>
                        <textarea class="form-control" id="deleteReason" rows="3" title="Enter reason for deleting this user" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" title="Cancel deletion">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete" title="Permanently delete this user">Delete</button>
                </div>
            </div>
        </div>
    </div>
    </div>  
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize all modals
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                const bsModal = new bootstrap.Modal(modal);
                
                // Add event listeners for modal events
                modal.addEventListener('hidden.bs.modal', function () {
                    // Reset form when modal is hidden
                    const form = this.querySelector('form');
                    if (form) {
                        form.reset();
                    }
                });
                
                modal.addEventListener('shown.bs.modal', function () {
                    // Focus on first input when modal is shown
                    const firstInput = this.querySelector('input, select, textarea');
                    if (firstInput) {
                        firstInput.focus();
                    }
                });
            });
            // Store current page in variable
            let currentPage = 0;
            
            const usersTable = $('#usersTable').DataTable({
                order: [[0, 'asc']],
                pageLength: 10,
                language: {
                    search: "Search users:",
                    lengthMenu: "Show _MENU_ users per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ users",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                // Track page changes and store current page
                drawCallback: function() {
                    currentPage = this.api().page();
                }
            });
            
            // Check for page parameter in URL and go to that page
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page');
            if (page !== null) {
                usersTable.page(parseInt(page)).draw('page');
            }
            
            $(document).off('submit', '#editUserForm');

            // Delete user
            let userIdToDelete = null;
            
            $(document).on('click', '.delete-user', function() {
                userIdToDelete = $(this).data('id');
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
                deleteModal.show();
            });
            
            $('#confirmDelete').click(function() {
                if (!userIdToDelete) return;
                
                const reason = $('#deleteReason').val();
                if (!reason) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Please provide a reason for deletion.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                // Show loading state
                Swal.fire({
                    title: 'Deleting User',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'delete_user_simple.php',
                    type: 'POST',
                    data: {
                        id: userIdToDelete,
                        reason: reason,
                        current_page: currentPage
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Delete response:', response);
                        if (response.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: response.message,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.location.href = 'manage_users.php?page=' + currentPage;
                                }
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: response.message || 'An error occurred while deleting the user.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Delete user error:', error);
                        console.error('Status:', status);
                        console.error('Response text:', xhr.responseText);
                        
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred while deleting the user. Please try again.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
                
                const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteUserModal'));
                if (deleteModal) {
                    deleteModal.hide();
                }
                $('#deleteReason').val('');
            });

            $('#addUserForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate phone number before submission
                const phoneInput = $('input[name="phone"]', this);
                const phoneValue = phoneInput.val();
                const phonePattern = /^\d{11}$/;
                
                if (!phonePattern.test(phoneValue)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Phone number must be exactly 11 digits.',
                        confirmButtonText: 'OK'
                    });
                    phoneInput.focus();
                    return false;
                }
                
                // Show loading state
                Swal.fire({
                    title: 'Adding User',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: 'add_user.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message,
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while adding the user. Please try again.',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            });

            // Modified event handler for edit user form
            $(document).on('click', 'button[form="editUserForm"]', function(e) {
                e.preventDefault();
                
                // Validate phone number before submission
                const phoneInput = $('#editUserPhone');
                const phoneValue = phoneInput.val();
                const phonePattern = /^\d{11}$/;
                
                if (!phonePattern.test(phoneValue)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Phone number must be exactly 11 digits.',
                        confirmButtonText: 'OK'
                    });
                    phoneInput.focus();
                    return false;
                }
                
                // Get form data manually
                const formData = {
                    id: $('#editUserId').val(),
                    full_name: $('#editUserFullName').val(),
                    username: $('#editUserUsername').val(),
                    email: $('#editUserEmail').val(),
                    password: $('input[name="password"]').val(),
                    role: $('#editUserRole').val(),
                    status: $('#editUserStatus').val(),
                    phone: $('#editUserPhone').val(),
                    id_number: $('#editUserIdNumber').val()
                };
                
                // Show loading state
                Swal.fire({
                    title: 'Updating User',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit form via AJAX
                $.ajax({
                    url: 'edit_user.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const editModal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                            if (editModal) {
                                editModal.hide();
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message,
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Update error:', error);
                        console.error('Response:', xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while updating the user. Please try again.',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            });
            
            // Add input validation for phone number in add user form
            $('input[name="phone"]').on('input', function() {
                const phoneValue = $(this).val();
                const phonePattern = /^\d{11}$/;
                
                if (phoneValue && !phonePattern.test(phoneValue)) {
                    $(this).addClass('is-invalid');
                    if (!$(this).next('.invalid-feedback').length) {
                        $(this).after('<div class="invalid-feedback">Phone number must be exactly 11 digits.</div>');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).next('.invalid-feedback').remove();
                }
            });
            
            // Add password validation for add user form
            $('#addUserPassword').on('input', function() {
                const passwordValue = $(this).val();
                const minLength = 8;
                
                if (passwordValue.length < minLength) {
                    $(this).addClass('is-invalid');
                    $('#addUserPassword-feedback').text(`Password must be at least ${minLength} characters long. Current length: ${passwordValue.length}`);
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).addClass('is-valid');
                    $('#addUserPassword-feedback').text('');
                }
            });
            
            // Handle user approval with SweetAlert
            $(document).on('click', '.approve-user', function() {
                const userId = $(this).data('id');
                const userName = $(this).data('name');
                
                Swal.fire({
                    title: 'Approve User?',
                    html: `Are you sure you want to approve <strong>${userName}</strong>?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, approve!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Processing...',
                            text: 'Please wait while we process your request.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            willOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit form via AJAX
                        $.ajax({
                            url: 'manage_users.php',
                            type: 'POST',
                            data: {
                                user_id: userId,
                                action: 'approve'
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: response.message || 'User has been approved successfully.',
                                        icon: 'success',
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: response.message || 'Failed to approve user.',
                                        icon: 'error',
                                        confirmButtonColor: '#3085d6'
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', xhr.responseText);
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Something went wrong while approving the user.',
                                    icon: 'error',
                                    confirmButtonColor: '#3085d6'
                                });
                            }
                        });
                    }
                });
            });
            
            // Handle user rejection with SweetAlert
            $(document).on('click', '.reject-user', function() {
                const userId = $(this).data('id');
                const userName = $(this).data('name');
                
                Swal.fire({
                    title: 'Reject User?',
                    html: `Are you sure you want to reject <strong>${userName}</strong>?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, reject!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Processing...',
                            text: 'Please wait while we process your request.',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            willOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        // Submit form via AJAX
                        $.ajax({
                            url: 'manage_users.php',
                            type: 'POST',
                            data: {
                                user_id: userId,
                                action: 'reject'
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: response.message || 'User has been rejected successfully.',
                                        icon: 'success',
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: response.message || 'Failed to reject user.',
                                        icon: 'error',
                                        confirmButtonColor: '#3085d6'
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', xhr.responseText);
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Something went wrong while rejecting the user.',
                                    icon: 'error',
                                    confirmButtonColor: '#3085d6'
                                });
                            }
                        });
                    }
                });
            });
        });

        function editUser(id) {
                                $('#editUserModal .modal-body').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
                    const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                    editModal.show();

            $.ajax({
                url: 'get_user_details.php?id=' + id,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('User details response:', response);
                    if (response.error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.error
                        });
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                        if (editModal) {
                            editModal.hide();
                        }
                        return;
                    }

                    if (!response.id || !response.username) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Invalid server response'
                        });
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                        if (editModal) {
                            editModal.hide();
                        }
                        return;
                    }

                    $('#editUserModal .modal-body').html(`
                        <form id="editUserForm" action="edit_user.php" method="POST">
                            <input type="hidden" name="id" id="editUserId">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" id="editUserFullName" title="Edit user's full name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" id="editUserUsername" title="Edit username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="editUserEmail" title="Edit user's email address" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role" id="editUserRole" title="Change user's role" required>
                                    <option value="student">Student</option>
                                    <option value="faculty">Faculty</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="editUserStatus" title="Change user's account status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" 
                                       class="form-control" 
                                       name="phone" 
                                       id="editUserPhone" 
                                       pattern="\d{11}" 
                                       oninvalid="this.setCustomValidity('Please enter exactly 11 digits for phone number')"
                                       oninput="this.setCustomValidity('')"
                                       title="Enter exactly 11 digits for phone number" 
                                       required>
                                <div class="form-text">Format: 11 digits only (e.g., 09123456789)</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" id="editIdLabel">ID Number</label>
                                <input type="text" class="form-control" name="id_number" id="editUserIdNumber">
                                <div class="form-text">Student or Faculty ID number for verification</div>
                            </div>
                        </form>
                    `);

                    $('#editUserId').val(response.id);
                    $('#editUserFullName').val(response.full_name);
                    $('#editUserUsername').val(response.username);
                    $('#editUserEmail').val(response.email);
                    $('#editUserRole').val(response.role);
                    $('#editUserStatus').val(response.status);
                    $('#editUserPhone').val(response.phone);
                    $('#editUserIdNumber').val(response.id_number);
                    
                    // Update ID field label based on role
                    const editIdLabel = document.getElementById('editIdLabel');
                    if (response.role === 'student') {
                        editIdLabel.textContent = 'Student ID';
                    } else if (response.role === 'faculty') {
                        editIdLabel.textContent = 'Faculty ID';
                    } else {
                        editIdLabel.textContent = 'ID Number';
                    }
                    
                    // Add role change event listener to update ID field label
                    $('#editUserRole').on('change', function() {
                        const selectedRole = $(this).val();
                        if (selectedRole === 'student') {
                            editIdLabel.textContent = 'Student ID';
                        } else if (selectedRole === 'faculty') {
                            editIdLabel.textContent = 'Faculty ID';
                        } else {
                            editIdLabel.textContent = 'ID Number';
                        }
                    });
                    
                    // Add input event listener for real-time phone validation
                    $('#editUserPhone').on('input', function() {
                        const phoneValue = $(this).val();
                        const phonePattern = /^\d{11}$/;
                        
                        if (phoneValue && !phonePattern.test(phoneValue)) {
                            $(this).addClass('is-invalid');
                            if (!$(this).next('.invalid-feedback').length) {
                                $(this).after('<div class="invalid-feedback">Phone number must be exactly 11 digits.</div>');
                            }
                        } else {
                            $(this).removeClass('is-invalid');
                            $(this).next('.invalid-feedback').remove();
                        }
                    });
                    
                    // Add password validation for edit form
                    $('#editUserPassword').on('input', function() {
                        const passwordValue = $(this).val();
                        const minLength = 8;
                        
                        if (passwordValue && passwordValue.length < minLength) {
                            $(this).addClass('is-invalid');
                            $('#editUserPassword-feedback').text(`Password must be at least ${minLength} characters long. Current length: ${passwordValue.length}`);
                        } else {
                            $(this).removeClass('is-invalid');
                            $(this).addClass('is-valid');
                            $('#editUserPassword-feedback').text('');
                        }
                    });
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    console.error('Response Text:', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Failed to load user details'
                    });
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                    if (editModal) {
                        editModal.hide();
                    }
                }
            });
        }
    </script>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 
