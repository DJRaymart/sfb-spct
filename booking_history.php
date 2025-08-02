<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Booking.php';
require_once 'includes/BookingStats.php';

$auth = new Auth($conn);
$bookingManager = new Booking($conn);
$bookingStats = new BookingStats($conn);

// Auto-complete past approved bookings
$bookingManager->autoCompleteBookings();

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Get booking statistics
$stats = $bookingStats->getBookingStatistics($_SESSION['user_id'], $auth->isAdmin());

$status = $_GET['status'] ?? '';
$facility_id = $_GET['facility_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT b.*, f.name as facility_name, u.full_name as user_name 
          FROM bookings b 
          JOIN facilities f ON b.facility_id = f.id 
          JOIN users u ON b.user_id = u.id 
          WHERE 1=1";

$params = [];

if (!$auth->isAdmin()) {
    $query .= " AND b.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

if ($status) {
    $query .= " AND b.status = ?";
    $params[] = $status;
}

if ($facility_id) {
    $query .= " AND b.facility_id = ?";
    $params[] = $facility_id;
}

if ($start_date) {
    $query .= " AND b.start_time >= ?";
    $params[] = $start_date . ' 00:00:00';
}

if ($end_date) {
    $query .= " AND b.end_time <= ?";
    $params[] = $end_date . ' 23:59:59';
}

if ($search) {
    $query .= " AND (f.name LIKE ? OR u.full_name LIKE ? OR b.purpose LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY b.created_at ASC";

$facilities_query = "SELECT * FROM facilities WHERE status = 'active' ORDER BY name";
$facilities = $conn->query($facilities_query)->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

       
// Keep filtered counts for display purposes
$total_bookings = count($bookings);
$approved_bookings = array_filter($bookings, function($b) { return $b['status'] === 'approved'; });
$pending_bookings = array_filter($bookings, function($b) { return $b['status'] === 'pending'; });
$completed_bookings = array_filter($bookings, function($b) { return $b['status'] === 'completed'; });
$cancelled_bookings = array_filter($bookings, function($b) { return $b['status'] === 'cancelled'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="img/logo.png">
    <title>Booking History - School Facility Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.10/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.10/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/list@6.1.10/index.global.min.js'></script>
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
            border-radius: 10px;
            box-shadow: 0 0 25px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
            margin-bottom: 5rem;
        }

        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .stats-card {
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            margin-bottom: 1rem;
            border: none;
            overflow: hidden;
        }
        
        .stats-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
        }
        
        .stats-card .card-body {
            padding: 2rem 1.5rem;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .stats-card .card-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .stats-card .card-text {
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }
        
        .stats-card h2 {
            font-size: 2.5rem;
            margin-bottom: 0;
            font-weight: 700;
        }

        .stats-card small {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .stats-card i {
            opacity: 0.9;
        }

        /* Progress bar styling */
        .progress {
            border-radius: 10px;
            background-color: rgba(255, 255, 255, 0.2);
        }

        .progress-bar {
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        /* Status breakdown card */
        .status-breakdown-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .status-breakdown-card .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            border-radius: 15px 15px 0 0;
        }

        .status-breakdown-card .card-body {
            padding: 2rem;
        }

        .badge {
            font-size: 0.875rem;
            padding: 0.5rem 0.75rem;
        }

        .filter-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filter-section .form-group {
            margin-bottom: 1rem;
        }


        .filter-section label {
            font-weight: 500;
            color: #495057;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }
        
        .table th {
            font-weight: 600;
            color: #495057;
            border-top: none;
        }


        .table td {
            vertical-align: middle;
        }
        
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
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

        /* Modal Styling */
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
            padding: 15px 20px;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            background: #f8f9fa;
            border-radius: 0 0 10px 10px;
            padding: 15px 20px;
        }
    
        .modal-backdrop {
            z-index: 1040;
        }

        .modal {
            z-index: 1050;
        }
        
        /* Custom SweetAlert2 Styles */
        .swal2-popup {
            border-radius: 15px !important;
        }
        
        .swal2-title {
            color: #2c3e50 !important;
            font-weight: 600 !important;
        }
        
        .swal2-html-container {
            margin: 1rem 0 !important;
        }
        
        .swal2-confirm {
            border-radius: 8px !important;
            font-weight: 600 !important;
        }
        
        .swal2-cancel {
            border-radius: 8px !important;
            font-weight: 600 !important;
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
                        <i class="fas fa-history me-3"></i>
                        Booking History
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">View and manage all your facility booking history and status</p>
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
    <div class="dashboard-container">
        <!-- Overall Booking Statistics -->
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <!-- Total Bookings - Larger card -->
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card card bg-primary text-white h-100">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-center mb-2">
                            <i class="fas fa-calendar-alt fa-2x me-3"></i>
                            <h2 class="card-title mb-0"><?php echo $stats['total_bookings']; ?></h2>
                        </div>
                        <p class="card-text mb-0 fw-bold">Total Bookings</p>
                    </div>
                </div>
            </div>
            
            <!-- Active Bookings (Pending + Approved) -->
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card card bg-success text-white h-100">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-center mb-2">
                            <i class="fas fa-clock fa-2x me-3"></i>
                            <h2 class="card-title mb-0"><?php echo $stats['pending'] + $stats['approved']; ?></h2>
                        </div>
                        <p class="card-text mb-0 fw-bold">Active Bookings</p>
                        <small class="text-white-50"><?php echo $stats['pending']; ?> pending, <?php echo $stats['approved']; ?> approved</small>
                    </div>
                </div>
            </div>
            
            <!-- Completed Bookings -->
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card card bg-info text-white h-100">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-center mb-2">
                            <i class="fas fa-check-circle fa-2x me-3"></i>
                            <h2 class="card-title mb-0"><?php echo $stats['completed']; ?></h2>
                        </div>
                        <p class="card-text mb-0 fw-bold">Completed</p>
                        <small class="text-white-50">Past bookings</small>
                    </div>
                </div>
            </div>
            
            <!-- Cancelled Bookings -->
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card card bg-danger text-white h-100">
                    <div class="card-body text-center">
                        <div class="d-flex align-items-center justify-content-center mb-2">
                            <i class="fas fa-times-circle fa-2x me-3"></i>
                            <h2 class="card-title mb-0"><?php echo $stats['cancelled']; ?></h2>
                        </div>
                        <p class="card-text mb-0 fw-bold">Cancelled</p>
                        <small class="text-white-50">Cancelled bookings</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Status Breakdown -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card status-breakdown-card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Status Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    <span class="badge bg-warning fs-6 me-2"><?php echo $stats['pending']; ?></span>
                                    <span class="fw-bold">Pending</span>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $stats['total_bookings'] > 0 ? ($stats['pending'] / $stats['total_bookings'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    <span class="badge bg-success fs-6 me-2"><?php echo $stats['approved']; ?></span>
                                    <span class="fw-bold">Approved</span>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $stats['total_bookings'] > 0 ? ($stats['approved'] / $stats['total_bookings'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    <span class="badge bg-info fs-6 me-2"><?php echo $stats['completed']; ?></span>
                                    <span class="fw-bold">Completed</span>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo $stats['total_bookings'] > 0 ? ($stats['completed'] / $stats['total_bookings'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    <span class="badge bg-danger fs-6 me-2"><?php echo $stats['cancelled']; ?></span>
                                    <span class="fw-bold">Cancelled</span>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-danger" style="width: <?php echo $stats['total_bookings'] > 0 ? ($stats['cancelled'] / $stats['total_bookings'] * 100) : 0; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Facility</label>
                        <select name="facility_id" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($facilities as $facility): ?>
                                <option value="<?php echo $facility['id']; ?>" <?php echo $facility_id == $facility['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($facility['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by facility, user, or purpose" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="booking_history.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                        <button type="button" class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table class="table table-hover" id="bookingsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Facility</th>
                        <th>User</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Status</th>
                        <th>Purpose</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['facility_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($booking['start_time'])); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($booking['end_time'])); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $booking['status'] === 'approved' ? 'success' : 
                                        ($booking['status'] === 'pending' ? 'warning' : 
                                        ($booking['status'] === 'completed' ? 'info' : 
                                        ($booking['status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($booking['purpose']); ?></td>
                            <td>
                                <button type="button" class="btn btn-action btn-info" onclick="viewBooking(<?php echo $booking['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <button type="button" class="btn btn-action btn-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                                <?php if ($booking['status'] === 'approved'): ?>
                                    <a href="print_receipt.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-action btn-primary" title="Print Receipt">
                                        <i class="fas fa-print"></i>
                                    </a>

                                    <?php if ($auth->isAdmin()): ?>
                                        <?php 
                                        // Check if booking has ended
                                        $bookingEndTime = new DateTime($booking['end_time']);
                                        $now = new DateTime();
                                        if ($bookingEndTime > $now): 
                                        ?>
                                        <button type="button" class="btn btn-action btn-warning" onclick="changeDateModal(<?php echo $booking['id']; ?>, '<?php echo $booking['facility_id']; ?>')">
                                            <i class="fas fa-calendar-alt"></i>
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($auth->isAdmin()): ?>
                                    <?php 
                                    // Only show delete button for past bookings that are not pending
                                    $bookingEndTime = new DateTime($booking['end_time']);
                                    $now = new DateTime();
                                    $isPastBooking = $bookingEndTime < $now;
                                    $isNotPending = $booking['status'] !== 'pending';
                                    
                                    if ($isPastBooking && $isNotPending): 
                                    ?>
                                    <button type="button" class="btn btn-action btn-danger" onclick="deleteBooking(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['facility_name']); ?>')" title="Delete Past Booking">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="bookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bookingDetails">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changeDateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Booking Date</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="changeDateForm">
                        <input type="hidden" id="booking_id" name="booking_id">
                        <input type="hidden" id="facility_id" name="facility_id">
                        
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date and Time</label>
                            <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date and Time</label>
                            <input type="datetime-local" class="form-control" id="end_date" name="end_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_note" class="form-label">Reason for Date Change</label>
                            <textarea class="form-control" id="admin_note" name="admin_note" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="changeBookingDate()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
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
            $('#bookingsTable').DataTable({
                order: [[0, 'asc']],
                pageLength: 10,
                language: {
                    search: "Search bookings:",
                    lengthMenu: "Show _MENU_ bookings per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ bookings",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
        });

        function viewBooking(id) {
            $.get('get_booking_details.php?id=' + id, function(data) {
                $('#bookingDetails').html(data);
                const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
                bookingModal.show();
            });
        }

        function cancelBooking(id) {
            Swal.fire({
                title: 'Cancel Booking',
                html: `
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 3rem;"></i>
                        <p>Are you sure you want to cancel this booking?</p>
                        <p class="text-muted small">The booking will be marked as cancelled and the facility will be available again.</p>
                        <div class="form-group mt-3">
                            <label for="cancel-reason" class="form-label">Reason for cancellation (optional):</label>
                            <textarea id="cancel-reason" class="form-control" rows="3" placeholder="Please provide a reason for cancellation..."></textarea>
                        </div>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="fas fa-times me-2"></i>Yes, cancel it!',
                cancelButtonText: '<i class="fas fa-check me-2"></i>No, keep it',
                reverseButtons: true,
                preConfirm: () => {
                    return document.getElementById('cancel-reason').value;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Cancelling Booking',
                        html: `
                            <div class="text-center">
                                <div class="spinner-border text-warning mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p>Please wait while we cancel the booking...</p>
                            </div>
                        `,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });
                    
                    const cancelReason = result.value || 'Cancelled by user';
                    
                    $.post('cancel_booking.php', { 
                        id: id,
                        cancel_reason: cancelReason
                    }, function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Cancelled Successfully!',
                                html: `
                                    <div class="text-center">
                                        <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                                        <p>Booking has been cancelled successfully.</p>
                                        <p class="text-muted small">The facility is now available for new bookings.</p>
                                    </div>
                                `,
                                showConfirmButton: false,
                                timer: 2000
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Cancel Failed!',
                                html: `
                                    <div class="text-center">
                                        <i class="fas fa-times-circle text-danger mb-3" style="font-size: 3rem;"></i>
                                        <p>Failed to cancel booking: ${response.message}</p>
                                    </div>
                                `,
                                confirmButtonText: 'OK'
                            });
                        }
                    }).fail(function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cancel Failed!',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                                    <p>An error occurred while cancelling the booking</p>
                                    <p class="text-muted small">Please try again or contact support.</p>
                                </div>
                            `,
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
        }

        function deleteBooking(id, facilityName) {
            Swal.fire({
                title: 'Delete Booking',
                text: `Are you sure you want to permanently delete this booking for ${facilityName}?`,
                html: `
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 3rem;"></i>
                        <p class="text-danger"><strong>This action cannot be undone!</strong></p>
                        <p class="text-muted">The booking and all related data will be permanently removed.</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, delete it!',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Deleting Booking',
                        html: `
                            <div class="text-center">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p>Please wait while we delete the booking...</p>
                            </div>
                        `,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false
                    });
                    
                    $.ajax({
                        url: 'delete_booking.php',
                        method: 'POST',
                        data: { 
                            booking_id: id,
                            facility_name: facilityName
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted Successfully!',
                                    html: `
                                        <div class="text-center">
                                            <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                                            <p>${response.message}</p>
                                            <p class="text-muted small">Booking IDs have been resequenced automatically.</p>
                                        </div>
                                    `,
                                    showConfirmButton: false,
                                    timer: 2500
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Delete Failed!',
                                    html: `
                                        <div class="text-center">
                                            <i class="fas fa-times-circle text-danger mb-3" style="font-size: 3rem;"></i>
                                            <p>${response.message || 'Failed to delete booking'}</p>
                                        </div>
                                    `,
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Delete Failed!',
                                html: `
                                    <div class="text-center">
                                        <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                                        <p>An error occurred while deleting the booking</p>
                                        <p class="text-muted small">Please try again or contact support.</p>
                                    </div>
                                `,
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
        }

        function exportToExcel() {
            const table = document.getElementById('bookingsTable');
            const wb = XLSX.utils.table_to_book(table, { sheet: "Bookings" });
            const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'binary' });
            
            function s2ab(s) {
                const buf = new ArrayBuffer(s.length);
                const view = new Uint8Array(buf);
                for (let i = 0; i < s.length; i++) view[i] = s.charCodeAt(i) & 0xFF;
                return buf;
            }
            
            const blob = new Blob([s2ab(wbout)], { type: 'application/octet-stream' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'booking_history.xlsx';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function changeDateModal(bookingId, facilityId) {
            $('#booking_id').val(bookingId);
            $('#facility_id').val(facilityId);
            
            // Get current booking details to pre-fill the form
            $.get('get_booking_details.php?id=' + bookingId + '&format=json', function(response) {
                if (response.success) {
                    const booking = response.booking;
                    
                    // Check if booking has already passed
                    const now = new Date();
                    const bookingEnd = new Date(booking.end_time);
                    
                    if (bookingEnd <= now) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Cannot Modify Past Booking',
                            text: 'You cannot change the dates of bookings that have already passed.'
                        });
                        return;
                    }
                    
                    // Format dates for datetime-local input
                    const startDate = new Date(booking.start_time);
                    const endDate = new Date(booking.end_time);
                    
                    const formatDatetimeLocal = (date) => {
                        const yyyy = date.getFullYear();
                        const mm = String(date.getMonth() + 1).padStart(2, '0');
                        const dd = String(date.getDate()).padStart(2, '0');
                        const hh = String(date.getHours()).padStart(2, '0');
                        const min = String(date.getMinutes()).padStart(2, '0');
                        
                        return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
                    };
                    
                    $('#start_date').val(formatDatetimeLocal(startDate));
                    $('#end_date').val(formatDatetimeLocal(endDate));
                }
                
                const changeDateModal = new bootstrap.Modal(document.getElementById('changeDateModal'));
                changeDateModal.show();
            });
        }
        
        function changeBookingDate() {
            const bookingId = $('#booking_id').val();
            const facilityId = $('#facility_id').val();
            const startDate = $('#start_date').val();
            const endDate = $('#end_date').val();
            const adminNote = $('#admin_note').val();
            
            if (!startDate || !endDate || !adminNote) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Please fill in all fields'
                });
                return;
            }
            
            // Check if end date is after start date
            if (new Date(endDate) <= new Date(startDate)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'End time must be after start time'
                });
                return;
            }
            
            // Check if the new dates are in the past
            const now = new Date();
            const newStartDate = new Date(startDate);
            const newEndDate = new Date(endDate);
            
            if (newStartDate <= now || newEndDate <= now) {
                Swal.fire({
                    icon: 'error',
                    title: 'Cannot Schedule in the Past',
                    text: 'You cannot schedule bookings in the past.'
                });
                return;
            }
            
            // Show loading indicator
            Swal.fire({
                title: 'Checking availability...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // First check for conflicts
            $.post('check_conflicts.php', {
                facility_id: facilityId,
                start_time: startDate,
                end_time: endDate,
                exclude_booking_id: bookingId
            }, function(response) {
                if (response.conflict) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Time Slot Unavailable',
                        text: 'The selected time slot conflicts with another booking.'
                    });
                } else {
                    // No conflicts, proceed with update
                    $.post('update_booking_date.php', {
                        booking_id: bookingId,
                        start_time: startDate,
                        end_time: endDate,
                        admin_note: adminNote
                    }, function(updateResponse) {
                        if (updateResponse.success) {
                            const changeDateModal = bootstrap.Modal.getInstance(document.getElementById('changeDateModal'));
                            if (changeDateModal) {
                                changeDateModal.hide();
                            }
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: 'Booking date updated successfully',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: updateResponse.message || 'Failed to update booking date'
                            });
                        }
                    }, 'json');
                }
            }, 'json');
        }
    </script>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html>
