<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: index.php');
    exit;
}

$stmt = $conn->query("SELECT * FROM facilities ORDER BY name ASC");
$facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $capacity = $_POST['capacity'] ?? '';
        $location = $_POST['location'] ?? '';
        $type = $_POST['type'] ?? '';
        
        if (!empty($name) && !empty($capacity) && !empty($location) && !empty($type)) {
            $stmt = $conn->prepare("
                INSERT INTO facilities (name, description, capacity, location, type)
                VALUES (:name, :description, :capacity, :location, :type)
            ");
            
            $stmt->execute([
                'name' => $name,
                'description' => $description,
                'capacity' => $capacity,
                'location' => $location,
                'type' => $type
            ]);
            
            $_SESSION['success_message'] = "Facility added successfully";
            header('Location: manage_facilities.php');
            exit();
        }
    } elseif ($action === 'update') {
        $id = $_POST['facility_id'] ?? '';
        $status = $_POST['status'] ?? '';
        
        if (!empty($id) && !empty($status)) {
            $stmt = $conn->prepare("
                UPDATE facilities 
                SET status = :status
                WHERE id = :id
            ");
            
            $stmt->execute([
                'status' => $status,
                'id' => $id
            ]);
            
            $_SESSION['success_message'] = "Facility status updated successfully";
            header('Location: manage_facilities.php');
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
    <title>Manage Facilities - School Facility Reservation System</title>
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
                        <i class="fas fa-building me-3"></i>
                        Manage Facilities
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Manage and configure all facilities available for booking in the system</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex justify-content-md-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacilityModal">
                            <i class="fas fa-plus me-2"></i>Add New Facility
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
                <table class="table table-hover" id="facilitiesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($facilities as $facility): ?>
                            <tr>
                                <td><?php echo $facility['id']; ?></td>
                                <td><?php echo htmlspecialchars($facility['name']); ?></td>
                                <td><?php echo htmlspecialchars($facility['description']); ?></td>
                                <td><?php echo $facility['capacity']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $facility['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($facility['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-action btn-info" onclick="editFacility(<?php echo $facility['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-action btn-danger" onclick="deleteFacility(<?php echo $facility['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>

    <div class="modal fade" id="addFacilityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Facility</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addFacilityForm" action="add_facility.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" class="form-control" name="capacity" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="classroom">Classroom</option>
                                <option value="laboratory">Laboratory</option>
                                <option value="auditorium">Auditorium</option>
                                <option value="gymnasium">Gymnasium</option>
                                <option value="conference">Conference Room</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="available">Available</option>
                                <option value="maintenance">Under Maintenance</option>
                                <option value="reserved">Reserved</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addFacilityForm" class="btn btn-primary">Add Facility</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editFacilityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Facility</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editFacilityForm" action="edit_facility.php" method="POST">
                        <input type="hidden" name="id" id="editFacilityId">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="editFacilityName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editFacilityDescription" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" class="form-control" name="capacity" id="editFacilityCapacity" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" id="editFacilityType" required>
                                <option value="classroom">Classroom</option>
                                <option value="laboratory">Laboratory</option>
                                <option value="auditorium">Auditorium</option>
                                <option value="gymnasium">Gymnasium</option>
                                <option value="conference">Conference Room</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editFacilityStatus" required>
                                <option value="available">Available</option>
                                <option value="maintenance">Under Maintenance</option>
                                <option value="reserved">Reserved</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editFacilityForm" class="btn btn-primary">Save Changes</button>
                </div>
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
            
            const facilitiesTable = $('#facilitiesTable').DataTable({
                order: [[0, 'asc']],
                pageLength: 10,
                language: {
                    search: "Search facilities:",
                    lengthMenu: "Show _MENU_ facilities per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ facilities",
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
                facilitiesTable.page(parseInt(page)).draw('page');
            }

            $('#addFacilityForm').on('submit', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'add_facility.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const addModal = bootstrap.Modal.getInstance(document.getElementById('addFacilityModal'));
                            if (addModal) {
                                addModal.hide();
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Facility added successfully!',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message || 'Failed to add facility'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while adding the facility'
                        });
                    }
                });
            });

            $(document).on('submit', '#editFacilityForm', function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: 'edit_facility.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const editModal = bootstrap.Modal.getInstance(document.getElementById('editFacilityModal'));
                            if (editModal) {
                                editModal.hide();
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Facility updated successfully!',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message || 'Failed to update facility'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while updating the facility'
                        });
                    }
                });
            });
        });

        function editFacility(id) {
            $('#editFacilityModal .modal-body').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
            const editModal = new bootstrap.Modal(document.getElementById('editFacilityModal'));
            editModal.show();

            $.ajax({
                url: 'get_facility_details.php?id=' + id,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.error
                        });
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editFacilityModal'));
                        if (editModal) {
                            editModal.hide();
                        }
                        return;
                    }

                    if (!response.id || !response.name) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Invalid server response'
                        });
                        const editModal = bootstrap.Modal.getInstance(document.getElementById('editFacilityModal'));
                        if (editModal) {
                            editModal.hide();
                        }
                        return;
                    }

                    $('#editFacilityModal .modal-body').html(`
                        <form id="editFacilityForm" action="edit_facility.php" method="POST">
                            <input type="hidden" name="id" id="editFacilityId">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" id="editFacilityName" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="editFacilityDescription" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Capacity</label>
                                <input type="number" class="form-control" name="capacity" id="editFacilityCapacity" min="1" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" id="editFacilityType" required>
                                    <option value="classroom">Classroom</option>
                                    <option value="laboratory">Laboratory</option>
                                    <option value="auditorium">Auditorium</option>
                                    <option value="gymnasium">Gymnasium</option>
                                    <option value="conference">Conference Room</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="editFacilityStatus" required>
                                    <option value="available">Available</option>
                                    <option value="maintenance">Under Maintenance</option>
                                    <option value="reserved">Reserved</option>
                                </select>
                            </div>
                        </form>
                    `);

                    $('#editFacilityId').val(response.id);
                    $('#editFacilityName').val(response.name);
                    $('#editFacilityDescription').val(response.description);
                    $('#editFacilityCapacity').val(response.capacity);
                    $('#editFacilityType').val(response.type || 'classroom');
                    $('#editFacilityStatus').val((response.status || '').toLowerCase());
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Failed to load facility details'
                    });
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editFacilityModal'));
                    if (editModal) {
                        editModal.hide();
                    }
                }
            });
        }

        function deleteFacility(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'delete_facility.php',
                        method: 'POST',
                        data: { 
                            id: id,
                            current_page: currentPage 
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: response.message,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    window.location.href = 'manage_facilities.php?page=' + currentPage;
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: response.message || 'Failed to delete facility'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'An error occurred while deleting the facility'
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