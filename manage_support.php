<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: index.php');
    exit();
}

$currentUser = $auth->getCurrentUser();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="img/logo.png">
    <title>Support Management - School Facility Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
        
        .dashboard-container {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            color: #333;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stats-card.warning {
            border-left: 4px solid #ffc107;
        }
        
        .stats-card.success {
            border-left: 4px solid #28a745;
        }
        
        .stats-card.info {
            border-left: 4px solid #17a2b8;
        }
        
        .stats-card.warning {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }
        
        .stats-card.success {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }
        
        .stats-card.info {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }
        
        .support-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .support-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .support-card.unread {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
        
        .support-card.urgent {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .email-preview {
            max-height: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .support-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .support-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .support-actions {
            margin-top: 20px;
        }
        
        /* Ensure SweetAlert2 inputs are properly styled and focusable */
        .swal2-html-container input.form-control,
        .swal2-html-container textarea.form-control {
            width: 100% !important;
            padding: 0.375rem 0.75rem !important;
            font-size: 1rem !important;
            line-height: 1.5 !important;
            color: #495057 !important;
            background-color: #fff !important;
            background-clip: padding-box !important;
            border: 1px solid #ced4da !important;
            border-radius: 0.25rem !important;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
            pointer-events: auto !important;
            user-select: text !important;
        }
        
        .swal2-html-container input.form-control:focus,
        .swal2-html-container textarea.form-control:focus {
            color: #495057 !important;
            background-color: #fff !important;
            border-color: #80bdff !important;
            outline: 0 !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
        }
        
        .swal2-html-container .form-label {
            margin-bottom: 0.5rem !important;
            font-weight: 600 !important;
            color: #333 !important;
        }
        
        .btn-reply {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-reply:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            background: white;
            border: 1px solid #e9ecef;
        }
        
        .table {
            margin-bottom: 0;
            background: white;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px 12px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .table tbody tr {
            transition: background-color 0.2s ease;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }
        
        .table tbody tr:hover {
            background-color: #f0f9ff;
            border-left: 3px solid #3b82f6;
        }
        
        .table tbody td {
            padding: 12px;
            border: none;
            vertical-align: middle;
        }
        
        .btn-action {
            margin: 0 2px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-action:hover {
            transform: scale(1.1);
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
                        <i class="fas fa-headset me-3"></i>
                        Support Management
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Manage and respond to support requests from students and faculty</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex justify-content-md-end">
                        <!-- Export button removed -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="text-center">
                        <i class="fas fa-envelope fa-2x mb-3"></i>
                        <h4 id="totalRequests">0</h4>
                        <p class="mb-0">Total Requests</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card warning">
                    <div class="text-center">
                        <i class="fas fa-exclamation-circle fa-2x mb-3"></i>
                        <h4 id="unreadRequests">0</h4>
                        <p class="mb-0">Unread</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card success">
                    <div class="text-center">
                        <i class="fas fa-reply fa-2x mb-3"></i>
                        <h4 id="repliedRequests">0</h4>
                        <p class="mb-0">Replied</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card info">
                    <div class="text-center">
                        <i class="fas fa-calendar-alt fa-2x mb-3"></i>
                        <h4 id="monthlyRequests">0</h4>
                        <p class="mb-0">This Month</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="dashboard-container">

                        <div class="table-responsive">
                            <table class="table table-hover" id="supportTable">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                        <th><i class="fas fa-user me-2"></i>Name</th>
                                        <th><i class="fas fa-envelope me-2"></i>Email</th>
                                        <th><i class="fas fa-tag me-2"></i>Subject</th>
                                        <th><i class="fas fa-calendar me-2"></i>Date</th>
                                        <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                        <th><i class="fas fa-cogs me-2"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Support requests will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Support Details Modal -->
    <div class="modal fade" id="supportDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-envelope me-2"></i>
                        Support Request Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="supportDetailsContent">
                    <!-- Support details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="replyToSupport()">
                        <i class="fas fa-reply me-2"></i>
                        Reply
                    </button>
                </div>
            </div>
        </div>
    </div>
<br>
<br>
<br>
<br>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        let supportTable;
        let currentSupportId;

        $(document).ready(function() {
            // Initialize DataTable
            supportTable = $('#supportTable').DataTable({
                order: [[4, 'desc']], // Sort by date descending
                pageLength: 10,
                language: {
                    search: "Search support requests:",
                    lengthMenu: "Show _MENU_ requests per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ requests",
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                responsive: true
            });

            // Load support requests
            loadSupportRequests();
            
            // Refresh data every 30 seconds
            setInterval(loadSupportRequests, 30000);
        });

        function loadSupportRequests() {
            $.ajax({
                url: 'get_support_requests.php',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        updateSupportTable(response.data);
                        updateStatistics(response.statistics);
                    } else {
                        console.error('Failed to load support requests:', response.message);
                    }
                },
                error: function() {
                    console.error('Error loading support requests');
                }
            });
        }

        function updateSupportTable(requests) {
            supportTable.clear();
            
            requests.forEach(function(request) {
                const row = [
                    request.id,
                    request.name,
                    request.email,
                    request.subject,
                    new Date(request.created_at).toLocaleString(),
                    getStatusBadge(request.status),
                    getActionButtons(request.id)
                ];
                
                supportTable.row.add(row);
            });
            
            supportTable.draw();
        }

        function getStatusBadge(status) {
            const badges = {
                // Old status values (for backward compatibility)
                'new': '<span class="badge bg-danger">New</span>',
                'read': '<span class="badge bg-warning">Read</span>',
                'replied': '<span class="badge bg-success">Replied</span>',
                'closed': '<span class="badge bg-secondary">Closed</span>',
                // New status values
                'pending': '<span class="badge bg-danger">Pending</span>',
                'in_progress': '<span class="badge bg-warning">In Progress</span>',
                'resolved': '<span class="badge bg-success">Resolved</span>'
            };
            return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
        }

        function getActionButtons(id) {
            return `
                <button class="btn btn-sm btn-primary" onclick="viewSupportDetails(${id})" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-success" onclick="replyToSupport(${id})" title="Reply">
                    <i class="fas fa-reply"></i>
                </button>
                <button class="btn btn-sm btn-warning" onclick="markAsRead(${id})" title="Mark as Read">
                    <i class="fas fa-check"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteSupportRequest(${id})" title="Delete Request">
                    <i class="fas fa-trash"></i>
                </button>
            `;
        }

        function updateStatistics(stats) {
            $('#totalRequests').text(stats.total);
            $('#unreadRequests').text(stats.unread);
            $('#repliedRequests').text(stats.replied);
            $('#monthlyRequests').text(stats.monthly);
        }

        function viewSupportDetails(id) {
            currentSupportId = id;
            
            $.ajax({
                url: 'get_support_details.php',
                method: 'GET',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const support = response.data;
                        const modalContent = `
                            <div class="support-details">
                                <div class="support-meta">
                                    <div>
                                        <h6><strong>From:</strong> ${support.name} (${support.email})</h6>
                                        <p><strong>Subject:</strong> ${support.subject}</p>
                                        <p><strong>Date:</strong> ${new Date(support.created_at).toLocaleString()}</p>
                                    </div>
                                    <div>
                                        ${getStatusBadge(support.status)}
                                    </div>
                                </div>
                                <div class="support-message">
                                    <h6><strong>Message:</strong></h6>
                                    <div class="border rounded p-3 bg-white">
                                        ${support.message.replace(/\n/g, '<br>')}
                                    </div>
                                </div>
                                <div class="support-actions">
                                    <button class="btn btn-primary" onclick="replyToSupport(${support.id})">
                                        <i class="fas fa-reply me-2"></i>
                                        Reply to ${support.name}
                                    </button>
                                    <button class="btn btn-warning" onclick="markAsRead(${support.id})">
                                        <i class="fas fa-check me-2"></i>
                                        Mark as Read
                                    </button>
                                    <button class="btn btn-secondary" onclick="closeSupport(${support.id})">
                                        <i class="fas fa-times me-2"></i>
                                        Close Request
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteSupportRequest(${support.id})">
                                        <i class="fas fa-trash me-2"></i>
                                        Delete Request
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        $('#supportDetailsContent').html(modalContent);
                        $('#supportDetailsModal').modal('show');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load support details'
                    });
                }
            });
        }

        function replyToSupport(id) {
            const supportId = id || currentSupportId;
            
            Swal.fire({
                title: 'Reply to Support Request',
                html: `
                    <div class="form-group mb-3">
                        <label for="replySubject" class="form-label text-start d-block">Subject</label>
                        <input type="text" id="replySubject" class="form-control" placeholder="Enter subject" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="replyMessage" class="form-label text-start d-block">Message</label>
                        <textarea id="replyMessage" class="form-control" rows="6" placeholder="Enter your reply" autocomplete="off"></textarea>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Send Reply',
                cancelButtonText: 'Cancel',
                width: '600px',
                focusConfirm: false,
                allowOutsideClick: false,
                didOpen: () => {
                    // Ensure inputs can receive focus
                    const subjectInput = document.getElementById('replySubject');
                    const messageTextarea = document.getElementById('replyMessage');
                    
                    // Remove any disabled attributes and ensure they're focusable
                    subjectInput.removeAttribute('disabled');
                    messageTextarea.removeAttribute('disabled');
                    subjectInput.removeAttribute('readonly');
                    messageTextarea.removeAttribute('readonly');
                    
                    // Set focus to subject field
                    setTimeout(() => {
                        subjectInput.focus();
                    }, 100);
                    
                    // Add event listeners to ensure proper behavior
                    subjectInput.addEventListener('focus', function() {
                        this.style.outline = 'none';
                        this.style.borderColor = '#007bff';
                    });
                    
                    messageTextarea.addEventListener('focus', function() {
                        this.style.outline = 'none';
                        this.style.borderColor = '#007bff';
                    });
                },
                preConfirm: () => {
                    const subject = document.getElementById('replySubject').value.trim();
                    const message = document.getElementById('replyMessage').value.trim();
                    
                    if (!subject || !message) {
                        Swal.showValidationMessage('Please fill in all fields');
                        return false;
                    }
                    
                    return { subject, message };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    sendReply(supportId, result.value.subject, result.value.message);
                }
            });
        }

        function sendReply(supportId, subject, message) {
            $.ajax({
                url: 'send_support_reply.php',
                method: 'POST',
                data: {
                    support_id: supportId,
                    subject: subject,
                    message: message
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Reply Sent!',
                            text: response.message
                        });
                        loadSupportRequests();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to Send',
                            text: response.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to send reply'
                    });
                }
            });
        }

        function markAsRead(id) {
            $.ajax({
                url: 'update_support_status.php',
                method: 'POST',
                data: {
                    support_id: id,
                    status: 'read'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: 'Support request marked as read'
                        });
                        loadSupportRequests();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: response.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to update status'
                    });
                }
            });
        }

        function closeSupport(id) {
            Swal.fire({
                title: 'Close Support Request',
                text: 'Are you sure you want to close this support request?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Close',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'update_support_status.php',
                        method: 'POST',
                        data: {
                            support_id: id,
                            status: 'closed'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Closed!',
                                    text: 'Support request has been closed'
                                });
                                loadSupportRequests();
                                $('#supportDetailsModal').modal('hide');
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Failed',
                                    text: response.message
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to close support request'
                            });
                        }
                    });
                }
            });
        }

        function deleteSupportRequest(id) {
            Swal.fire({
                title: 'Delete Support Request',
                text: 'Are you sure you want to permanently delete this support request? This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'delete_support_request.php',
                        method: 'POST',
                        data: {
                            support_id: id
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: response.message
                                });
                                loadSupportRequests();
                                $('#supportDetailsModal').modal('hide');
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Failed',
                                    text: response.message
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to delete support request'
                            });
                        }
                    });
                }
            });
        }
    </script>
    
    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 