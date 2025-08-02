<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Booking.php';
require_once 'includes/BookingStats.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$auth = new Auth($conn);
$bookingManager = new Booking($conn);
$bookingStats = new BookingStats($conn);

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = $auth->getCurrentUser();
if (!$user) {
    header('Location: index.php');
    exit();
}

$userBookings = $bookingManager->getUserBookings($_SESSION['user_id']);

// Get booking statistics
$stats = $bookingStats->getBookingStatistics($_SESSION['user_id'], $auth->isAdmin());

try {
    $stmt = $conn->query("SELECT * FROM facilities WHERE status = 'available'");
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching facilities: " . $e->getMessage());
    $facilities = [];
}

try {
    // Auto-complete past approved bookings before fetching
    $bookingManager->autoCompleteBookings();
    
    $allBookings = $bookingManager->getAllBookings();
} catch (Exception $e) {
    error_log("Error fetching all bookings: " . $e->getMessage());
    $allBookings = [];
}

$events = array_map(function($bookingItem) {
    $color = '';
    switch($bookingItem['status'] ?? '') {
        case 'pending': $color = '#FFA726'; break;
        case 'approved': $color = '#66BB6A'; break;
        case 'completed': $color = '#17A2B8'; break;
        case 'cancelled': $color = '#EF5350'; break;
        case 'rejected': $color = '#6C757D'; break;
        default: $color = '#9E9E9E'; break;
    }
    
    // Ensure proper date formatting for JavaScript
    $startTime = new DateTime($bookingItem['start_time']);
    $endTime = new DateTime($bookingItem['end_time']);
    
    return [
        'id' => $bookingItem['id'] ?? '',
        'title' => ($bookingItem['facility_name'] ?? 'Unknown Facility') . ' - ' . ($bookingItem['user_name'] ?? 'Unknown User'),
        'start' => $startTime->format('Y-m-d\TH:i:s'),
        'end' => $endTime->format('Y-m-d\TH:i:s'),
        'color' => $color,
        'extendedProps' => [
            'status' => $bookingItem['status'] ?? '',
            'purpose' => $bookingItem['purpose'] ?? '',
            'attendees' => $bookingItem['attendees_count'] ?? 0,
            'user_id' => $bookingItem['user_id'] ?? 0,
            'facilityId' => $bookingItem['facility_id'] ?? 0
        ]
    ];
}, array_filter($allBookings, function($booking) {
    return ($booking['status'] ?? '') !== 'completed' && ($booking['status'] ?? '') !== 'rejected';
}));

// Debug information
error_log("Calendar events generated: " . count($events));
if (count($events) > 0) {
    error_log("First event: " . json_encode($events[0]));
}

$isAdmin = $auth->isAdmin();
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
    <title>Dashboard - School Facility Reservation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.10/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.10/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/list@6.1.10/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --fc-border-color: #e5e5e5;
            --fc-button-bg-color: #0d6efd;
            --fc-button-border-color: #0d6efd;
            --fc-button-hover-bg-color: #0b5ed7;
            --fc-button-hover-border-color: #0a58ca;
            --fc-button-text-color: #ffffff;
            --fc-button-active-bg-color: #0a58ca;
            --fc-button-active-border-color: #0a53be;
            --fc-today-bg-color: #f8f9fa;
            --fc-event-text-color: #ffffff;
            --fc-list-event-hover-bg-color: #f5f5f5;
            --fc-list-event-dot-width: 10px;
            --fc-list-event-dot-height: 10px;
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
        
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .dashboard-container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 25px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 30px;
            margin-bottom: 5rem;
        }

        #calendar {
            margin: 20px auto;
            padding: 0;
            min-height: 200px;
            background: white;
        }

        .fc {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .fc .fc-toolbar.fc-header-toolbar {
            margin-bottom: 2em;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .fc .fc-button {
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.2s;
            color: #ffffff;
        }

        .fc .fc-button:focus {
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
        }

        .fc .fc-daygrid-day.fc-day-today {
            background-color: rgba(13,110,253,.1);
        }

        .fc-event {
            cursor: pointer;
            border-radius: 4px;
            padding: 2px;
            font-size: 0.85em;
            transition: transform 0.2s;
        }

        .fc-event:hover {
            transform: scale(1.02);
        }

        .fc-event-title {
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            color: #ffffff !important;
        }
        
        .fc-event-time {
            color: #ffffff !important;
        }

        .fc-header-toolbar .fc-button {
            color: white !important;
        }

        .fc-button-primary {
            color: white !important;
        }

        .fc-daygrid-day-number {
            color: initial;
        }

        .fc-today-button {
            color: white !important;
        }

        .fc-toolbar-title {
            color: initial;
        }

        /* Statistics Cards */
        .stats-card {
            transition: transform 0.2s ease-in-out;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        .stats-card .card-body {
            padding: 1rem;
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: bold;
        }

        .stats-card p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .legend {
            background: white;
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            margin-right: 20px;
            padding: 5px 10px;
            border-radius: 4px;
            background: #f8f9fa;
        }

        .legend-color {
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-right: 8px;
            border-radius: 2px;
        }

        .action-buttons {
            margin: 20px 0;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }


        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-backdrop {
            z-index: 1040;
        }

        .modal {
            z-index: 1050;
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

        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            padding: 8px 12px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);
        }

        .event-pending { background-color: #FFA726; border-color: #FFA726; }
        .event-approved { background-color: #66BB6A; border-color: #66BB6A; }
        .event-cancelled { background-color: #EF5350; border-color: #EF5350; }
        
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

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 10px;
                margin: 15px 0;
            }

            #calendar {
                padding: 0;
                margin: 10px 0;
            }

            .fc .fc-toolbar.fc-header-toolbar {
                flex-direction: column;
                gap: 10px;
                padding: 10px;
            }

            .fc .fc-toolbar-title {
                font-size: 1.2em;
            }

            .fc .fc-button {
                padding: 6px 12px;
                font-size: 0.9em;
            }

            .legend {
                flex-direction: column;
                gap: 10px;
            }

            .legend-item {
                width: 100%;
                justify-content: center;
            }

            .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .btn {
                width: 100%;
                margin-bottom: 5px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .event-details {
                padding: 10px;
            }

            .detail-item {
                margin-bottom: 10px;
            }
        }

        /* Add touch-friendly styles */
        @media (hover: none) {
            .fc-event {
                padding: 4px;
            }

            .btn {
                padding: 12px 20px;
            }

            .nav-link {
                padding: 12px 15px !important;
            }
        }

        /* Improve form elements for mobile */
        @media (max-width: 768px) {
            .form-floating {
                margin-bottom: 1rem;
            }

            .form-floating label {
                padding: 1rem 0.75rem;
            }

            .form-floating input,
            .form-floating select,
            .form-floating textarea {
                padding: 1rem 0.75rem;
                height: calc(3.5rem + 2px);
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
        }

        /* Materials checkbox styling */
        #need_materials {
            transform: scale(1.2);
            margin-right: 8px;
        }

        #need_materials:checked + label {
            color: #0d6efd;
            font-weight: 600;
        }

        #materials_container {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            background-color: #f8f9fa;
        }

        .form-check-label {
            cursor: pointer;
            user-select: none;
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
                        <i class="fas fa-calendar-alt me-3"></i>
                        Facility Booking Calendar
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">View and manage your facility bookings with our interactive calendar</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex justify-content-md-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal">
                            <i class="fas fa-plus me-2"></i>Create Booking Request
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <?php 
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">


                <div class="dashboard-container">
                    
                    <div class="calendar-container">
                        <div class="legend">
                            <div class="legend-item">
                                <span class="legend-color" style="background: #FFA726"></span>
                                Pending
                            </div>
                            <div class="legend-item">
                                <span class="legend-color" style="background: #66BB6A"></span>
                                Approved
                            </div>
                            <div class="legend-item">
                                <span class="legend-color" style="background: #EF5350"></span>
                                Cancelled
                            </div>
                        </div>

                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

                <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="bookingModalLabel">Create Booking</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                <div class="modal-body">
                    <form id="bookingForm" action="process_booking.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="mb-3">
                            <label for="facility" class="form-label">Facility</label>
                            <select class="form-select" id="facility" name="facility_id" required>
                                <?php foreach ($facilities as $index => $facility): ?>
                                    <option value="<?php echo $facility['id']; ?>" data-capacity="<?php echo $facility['capacity']; ?>" <?php echo $index === 0 ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($facility['name']); ?> (Capacity: <?php echo $facility['capacity']; ?> people)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="mt-2">
                                <div class="alert alert-info py-2 px-3" id="capacityInfo">
                                    <i class="fas fa-users"></i> 
                                    <strong>Room Capacity:</strong> <span id="selectedCapacity"><?php echo $facilities[0]['capacity'] ?? 0; ?></span> people maximum
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="datetime-local" class="form-control" id="end_time" name="end_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="purpose" class="form-label">Purpose</label>
                            <textarea class="form-control" id="purpose" name="purpose" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="attendees" class="form-label">Number of Attendees</label>
                            <input type="number" class="form-control" id="attendees" name="attendees_count" min="1" required>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Maximum capacity will be enforced based on selected facility
                            </div>
                            <div id="capacityWarning" class="alert alert-warning mt-2" style="display: none;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Warning:</strong> Number of attendees exceeds room capacity!
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="bookingForm" class="btn btn-primary">Create Booking</button>
                </div>
            </div>
        </div>
    </div>

                <div class="modal fade" id="viewBookingModal" tabindex="-1" aria-labelledby="viewBookingModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="viewBookingModalLabel">Booking Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                <div class="modal-body">
                    <div id="viewEventDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if ($isAdmin): ?>
                    <button type="button" class="btn btn-success" id="approveBooking">Approve</button>
                    <button type="button" class="btn btn-warning" id="changeDateBtn" style="display: none;">Change Date</button>
                    <button type="button" class="btn btn-danger" id="cancelBooking" style="display: none;">Cancel Booking</button>
                    <button type="button" class="btn btn-danger" id="deleteBooking" style="display: none;">Delete Booking</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

                <div class="modal fade" id="cancelReasonModal" tabindex="-1" aria-labelledby="cancelReasonModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="cancelReasonModalLabel">Cancel Booking</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                <div class="modal-body">
                    <form id="cancelReasonForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">Reason for Cancellation</label>
                            <textarea class="form-control" id="cancelReason" name="cancel_reason" required 
                                    placeholder="Please provide a reason for cancelling this booking"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancel">Confirm Cancellation</button>
                </div>
            </div>
        </div>
    </div>

                <div class="modal fade" id="changeDateModal" tabindex="-1" aria-labelledby="changeDateModalLabel">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="changeDateModalLabel">Change Booking Date</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

    <script>
        // Global function to change booking date
        function changeBookingDate() {
            const bookingId = document.getElementById('booking_id').value;
            const facilityId = document.getElementById('facility_id').value;
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const adminNote = document.getElementById('admin_note').value;
            const csrfToken = '<?php echo htmlspecialchars($csrf_token); ?>';
            
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
            
            // Validate booking hours (7 AM to 8 PM)
            const start = new Date(startDate);
            const end = new Date(endDate);
            const startHour = start.getHours();
            const startMinute = start.getMinutes();
            const endHour = end.getHours();
            const endMinute = end.getMinutes();
            
            if (startHour < 7 || (startHour === 7 && startMinute < 0) || 
                endHour > 20 || (endHour === 20 && endMinute > 0)) {
                alert('Bookings are only allowed between 7:00 AM and 8:00 PM.');
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
                        admin_note: adminNote,
                        csrf_token: csrfToken
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
            var events = <?php echo json_encode($events); ?>;
            
            console.log('Calendar initializing with', events.length, 'events');
            console.log('Sample events:', events.slice(0, 3));
            console.log('Calendar element:', calendarEl);
            console.log('Is admin:', isAdmin);
            
            // Check if calendar element exists
            if (!calendarEl) {
                console.error('Calendar element not found!');
                return;
            }

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                themeSystem: 'bootstrap5',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                nowIndicator: true,
                allDaySlot: false,
                slotMinTime: '07:00:00',
                slotMaxTime: '20:00:00',
                slotDuration: '00:30:00',
                height: 'auto',
                expandRows: true,
                stickyHeaderDates: true,
                navLinks: true,
                weekNumbers: true,
                dayMaxEvents: true,
                businessHours: {
                    daysOfWeek: [1, 2, 3, 4, 5, 6], // Monday to Saturday (no Sunday)
                    startTime: '07:00',
                    endTime: '20:00', // 8 PM
                },
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                },
                selectable: true,
                selectConstraint: 'businessHours',
                selectOverlap: false,
                events: events,
                eventClassNames: function(arg) {
                    return ['event-' + arg.event.extendedProps.status];
                },
                eventDidMount: function(info) {
                    console.log('Event mounted:', info.event.title, 'Start:', info.event.start, 'Status:', info.event.extendedProps.status);
                    info.el.title = `${info.event.title}\nStatus: ${info.event.extendedProps.status}\nPurpose: ${info.event.extendedProps.purpose}`;
                },
                select: function(info) {
                    document.getElementById('start_time').value = info.startStr;
                    document.getElementById('end_time').value = info.endStr;
                    checkAvailability(info.startStr, info.endStr);
                },
                eventClick: function(info) {
                    var event = info.event;
                    window.currentEventId = event.id;
                    window.currentFacilityId = event.extendedProps.facilityId;
                    
                    updateBookingDetails(event.id);
                }
            });

            try {
                calendar.render();
                console.log('Calendar rendered successfully');
                
                // Calendar will automatically show current month by default
                console.log('Calendar showing current month:', new Date().toLocaleDateString());
            } catch (error) {
                console.error('Error rendering calendar:', error);
            }

            // Update capacity display when facility changes
            document.getElementById('facility').addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const capacity = selectedOption.dataset.capacity;
                document.getElementById('selectedCapacity').textContent = capacity;
                validateAttendeeCount(); // Check capacity when facility changes
            });

            // Validate attendee count against capacity
            function validateAttendeeCount() {
                const selectedFacility = document.getElementById('facility');
                const capacity = parseInt(selectedFacility.options[selectedFacility.selectedIndex].dataset.capacity);
                const attendeesInput = document.getElementById('attendees');
                const attendeeCount = parseInt(attendeesInput.value);
                const warningDiv = document.getElementById('capacityWarning');
                
                if (attendeeCount && attendeeCount > capacity) {
                    warningDiv.style.display = 'block';
                    attendeesInput.classList.add('is-invalid');
                } else {
                    warningDiv.style.display = 'none';
                    attendeesInput.classList.remove('is-invalid');
                }
            }

            // Add event listener to attendees input for real-time validation
            document.getElementById('attendees').addEventListener('input', validateAttendeeCount);

            async function checkAvailability(startTime, endTime) {
                try {
                    const facilityId = document.getElementById('facility').value;
                    const formData = new URLSearchParams({
                        facility_id: facilityId,
                        start_time: startTime,
                        end_time: endTime
                    });

                    const response = await fetch('check_conflicts.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    
                    if (data.hasConflict) {
                        alert('Selected time slot is not available:\n\n' + data.conflictDetails.join('\n'));
                    } else {
                        const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
                        bookingModal.show();
                    }
                } catch (error) {
                    console.error('Error checking availability:', error);
                    alert('Error checking availability. Please try again.');
                }
            }

            async function updateBookingStatus(bookingId, status) {
                try {
                    const event = calendar.getEventById(bookingId);
                    if (!event || event.extendedProps.status !== 'pending') {
                        throw new Error('Only pending bookings can be updated');
                    }

                    // Use SweetAlert2 for confirmation
                    const result = await Swal.fire({
                        title: `${status.charAt(0).toUpperCase() + status.slice(1)} Booking?`,
                        text: `Are you sure you want to ${status} this booking?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: status === 'approved' ? '#28a745' : '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: `Yes, ${status} it!`,
                        cancelButtonText: 'Cancel'
                    });

                    if (!result.isConfirmed) {
                        return;
                    }

                    const formData = new URLSearchParams({
                        booking_id: bookingId,
                        status: status
                    });

                    const response = await fetch('process_booking_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || `HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (!data.success) {
                        throw new Error(data.error || 'Failed to update booking status');
                    }

                    if (event) {
                        const color = status === 'approved' ? '#28a745' : '#dc3545';
                        event.setProp('color', color);
                        event.setExtendedProp('status', status);
                    }

                    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewBookingModal'));
                    if (viewModal) {
                        viewModal.hide();
                    }
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Booking is approved',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        alert('Booking is approved');
                        window.location.reload();
                    }

                } catch (error) {
                    console.error('Error updating booking:', error);
                    alert('Error: ' + error.message);
                }
            }

            async function cancelBooking(bookingId) {
                const cancelReasonModal = new bootstrap.Modal(document.getElementById('cancelReasonModal'));
                cancelReasonModal.show();

                window.pendingCancelBookingId = bookingId;

                document.getElementById('confirmCancel').onclick = async function() {
                    const cancelReason = document.getElementById('cancelReason').value;
                    
                    if (!cancelReason.trim()) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Please provide a reason for cancellation'
                        });
                        return;
                    }

                    try {
                        const formData = new URLSearchParams({
                            id: bookingId,
                            cancel_reason: cancelReason,
                            csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
                        });

                        const response = await fetch('cancel_booking.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: formData
                        });

                        let data;
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            data = await response.json();
                        } else {
                            throw new Error('Server returned non-JSON response');
                        }

                        if (!response.ok) {
                            throw new Error(data.message || 'Failed to cancel booking');
                        }

                        if (!data.success) {
                            throw new Error(data.message || 'Failed to cancel booking');
                        }

                        const event = calendar.getEventById(bookingId);
                        if (event) {
                            event.setProp('color', '#6c757d');
                            event.setExtendedProp('status', 'cancelled');
                        }

                        if (cancelReasonModal) {
                            cancelReasonModal.hide();
                        }
                        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewBookingModal'));
                        if (viewModal) {
                            viewModal.hide();
                        }
                        
                        Swal.fire({
                            title: 'Success!',
                            text: data.message || 'Booking has been cancelled and notification sent to the booker',
                            icon: 'success',
                            timer: 5000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });

                    } catch (error) {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: error.message || 'An error occurred while cancelling the booking'
                        });
                    }
                };
            }

            async function deleteBookingFromDashboard(bookingId) {
                // Get booking details to show facility name
                const event = calendar.getEventById(bookingId);
                const facilityName = event ? event.extendedProps.facilityName || 'Unknown Facility' : 'Unknown Facility';
                
                Swal.fire({
                    title: 'Delete Booking',
                    text: `Are you sure you want to permanently delete this booking for ${facilityName}? This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        Swal.fire({
                            title: 'Deleting Booking',
                            text: 'Please wait while we delete the booking...',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            willOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        try {
                            const formData = new URLSearchParams({
                                booking_id: bookingId,
                                facility_name: facilityName
                            });

                            const response = await fetch('delete_booking.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: formData
                            });

                            const data = await response.json();

                            if (data.success) {
                                // Remove event from calendar
                                const event = calendar.getEventById(bookingId);
                                if (event) {
                                    event.remove();
                                }
                                
                                // Close the view modal
                                const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewBookingModal'));
                                if (viewModal) {
                                    viewModal.hide();
                                }
                                
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted Successfully!',
                                    text: data.message,
                                    showConfirmButton: false,
                                    timer: 2000
                                });
                            } else {
                                throw new Error(data.message || 'Failed to delete booking');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Delete Failed!',
                                text: error.message || 'An error occurred while deleting the booking',
                                confirmButtonText: 'OK'
                            });
                        }
                    }
                });
            }

            async function handleBookingSubmission(e) {
                e.preventDefault();
                
                // Validate form before submission
                const form = e.target;
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                

                
                try {
                    const form = e.target;
                    const facilityId = document.getElementById('facility').value;
                    const startTime = document.getElementById('start_time').value;
                    const endTime = document.getElementById('end_time').value;
                    const purpose = document.getElementById('purpose').value;
                    const attendees = document.getElementById('attendees').value;

                    if (!facilityId || !startTime || !endTime || !purpose || !attendees) {
                        alert('Please fill in all required fields.');
                        return;
                    }

                    const start = new Date(startTime);
                    const end = new Date(endTime);
                    const now = new Date();

                    if (start < now) {
                        alert('Start time cannot be in the past.');
                        return;
                    }

                    if (end <= start) {
                        alert('End time must be after start time.');
                        return;
                    }

                    // Check if booking is on Sunday
                    const startDay = start.getDay();
                    if (startDay === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Day',
                            text: 'Bookings are not allowed on Sundays.'
                        });
                        return;
                    }

                    // Check if booking time is within allowed hours (7 AM to 8 PM)
                    const startHour = start.getHours();
                    const endHour = end.getHours();
                    
                    // Start time must be at or after 7:00 AM
                    if (startHour < 7) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid Start Time',
                            text: 'Bookings cannot start before 7:00 AM.'
                        });
                        return;
                    }
                    
                    // End time must be at or before 8:00 PM (20:00)
                    if (endHour > 20) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid End Time',
                            text: 'Bookings must end by 8:00 PM.'
                        });
                        return;
                    }

                    const duration = end - start;
                    const fiveHoursInMs = 5 * 60 * 60 * 1000; // 5 hours in milliseconds
                    if (duration > fiveHoursInMs) {
                        alert('Bookings cannot exceed 5 hours.');
                        return;
                    }

                    const selectedFacility = document.getElementById('facility');
                    const facilityCapacity = parseInt(selectedFacility.options[selectedFacility.selectedIndex].dataset.capacity);
                    const numAttendees = parseInt(attendees);

                    if (numAttendees > facilityCapacity) {
                        alert(`Number of attendees (${numAttendees}) exceeds facility capacity (${facilityCapacity}).`);
                        return;
                    }

                    if (!isAdmin) {
                        const oneDayFromNow = new Date(now);
                        oneDayFromNow.setDate(now.getDate() + 1);
                        
                        if (start < oneDayFromNow) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Advance Notice Required',
                                text: 'Bookings must be made at least 1 day in advance.',
                                confirmButtonText: 'OK'
                            });
                            return;
                        }
                    }

                    const conflictCheckData = new URLSearchParams({
                        facility_id: facilityId,
                        start_time: startTime,
                        end_time: endTime,
                        csrf_token: '<?php echo htmlspecialchars($csrf_token); ?>'
                    });

                    const conflictResponse = await fetch('check_conflicts.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: conflictCheckData
                    });

                    if (!conflictResponse.ok) {
                        throw new Error(`HTTP error! status: ${conflictResponse.status}`);
                    }

                    const conflictData = await conflictResponse.json();

                    if (conflictData.hasConflict) {
                        alert('This time slot is already booked:\n\n' + conflictData.conflictDetails.join('\n'));
                        return;
                    }

                    const formData = new FormData(form);
                    
                    // Only include materials if checkbox is checked
                    if (!$('#need_materials').is(':checked')) {
                        // Remove any material-related form data
                        const materialsToRemove = [];
                        for (let [key, value] of formData.entries()) {
                            if (key.startsWith('materials') || key.startsWith('material_quantity')) {
                                materialsToRemove.push(key);
                            }
                        }
                        materialsToRemove.forEach(key => formData.delete(key));
                    }
                    const bookingResponse = await fetch('process_booking.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!bookingResponse.ok) {
                        throw new Error('Failed to submit booking request');
                    }

                    const bookingData = await bookingResponse.json();
                    
                    if (!bookingData.success) {
                        throw new Error(bookingData.message || 'Failed to create booking');
                    }

                    const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Success!',
                            text: isAdmin ? 'Booking created and automatically approved.' : 'Thanks for booking. Please wait for the approval.',
                            icon: 'success',
                            timer: 5000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        alert(isAdmin ? 'Booking created and automatically approved.' : 'Thanks for booking. Please wait for the approval.');
                        window.location.reload();
                    }

                } catch (error) {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                }
            }

            if (isAdmin) {
                document.getElementById('approveBooking')?.addEventListener('click', () => {
                    if (window.currentEventId) {
                        updateBookingStatus(window.currentEventId, 'approved');
                    }
                });
                
                document.getElementById('cancelBooking')?.addEventListener('click', () => {
                    if (window.currentEventId) {
                        cancelBooking(window.currentEventId);
                    }
                });
                
                document.getElementById('deleteBooking')?.addEventListener('click', () => {
                    if (window.currentEventId) {
                        deleteBookingFromDashboard(window.currentEventId);
                    }
                });
            }

            document.getElementById('bookingForm').addEventListener('submit', handleBookingSubmission);

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

            function getStatusColor(status) {
                switch(status) {
                    case 'pending': return 'warning';
                    case 'approved': return 'success';
                    case 'cancelled': return 'danger';
                    default: return 'primary';
                }
            }

            $('.approve-booking').on('click', function() {
                const bookingId = $(this).data('id');
                
                // Get the booking event from the calendar
                const event = calendar.getEventById(bookingId);
                if (!event) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Booking not found'
                    });
                    return;
                }
                
                // Check if booking is on Sunday
                const bookingStart = new Date(event.start);
                const bookingDay = bookingStart.getDay();
                
                if (bookingDay === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Cannot Approve',
                        text: 'Bookings cannot be scheduled on Sundays'
                    });
                    return;
                }
                
                // Check if booking time is within allowed hours (7 AM to 8 PM)
                const startHour = bookingStart.getHours();
                const startMinute = bookingStart.getMinutes();
                const endHour = new Date(event.end).getHours();
                const endMinute = new Date(event.end).getMinutes();
                
                if (startHour < 7) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Cannot Approve',
                        text: 'Bookings cannot start before 7:00 AM'
                    });
                    return;
                }
                
                if (endHour > 20) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Cannot Approve',
                        text: 'Bookings must end by 8:00 PM'
                    });
                    return;
                }
                
                Swal.fire({
                    title: 'Approve Booking?',
                    text: "Are you sure you want to approve this booking?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, approve it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'process_booking_status.php',
                            method: 'POST',
                            dataType: 'json',
                            data: {
                                booking_id: bookingId,
                                status: 'approved'
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Approved!',
                                        text: 'Booking has been approved successfully.',
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error!',
                                        text: response.error || 'Failed to approve booking'
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', status, error, xhr.responseText);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: 'An error occurred while approving the booking: ' + error
                                });
                            }
                        });
                    }
                });
            });

            // Set up change date button click handler
            document.getElementById('changeDateBtn').addEventListener('click', function() {
                const bookingId = window.currentEventId;
                const event = calendar.getEventById(bookingId);
                
                if (!event || event.extendedProps.status !== 'approved') {
                    alert('Only approved bookings can have their dates changed');
                    return;
                }
                
                // Check if booking has already passed
                const now = new Date();
                const bookingEnd = new Date(event.end);
                
                if (bookingEnd <= now) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Cannot Modify Past Booking',
                        text: 'You cannot change the dates of bookings that have already passed.'
                    });
                    return;
                }
                
                // Get facility ID from event
                const facilityId = event.extendedProps.facilityId || event.id.split('-')[0];
                
                // Pre-fill the form with current booking dates
                const formatDatetimeLocal = (date) => {
                    const yyyy = date.getFullYear();
                    const mm = String(date.getMonth() + 1).padStart(2, '0');
                    const dd = String(date.getDate()).padStart(2, '0');
                    const hh = String(date.getHours()).padStart(2, '0');
                    const min = String(date.getMinutes()).padStart(2, '0');
                    
                    return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
                };
                
                document.getElementById('booking_id').value = bookingId;
                document.getElementById('facility_id').value = facilityId;
                document.getElementById('start_date').value = formatDatetimeLocal(event.start);
                document.getElementById('end_date').value = formatDatetimeLocal(event.end);
                
                                    // Close the view modal and show the change date modal
                    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewBookingModal'));
                    if (viewModal) {
                        viewModal.hide();
                    }
                    
                    const changeDateModal = new bootstrap.Modal(document.getElementById('changeDateModal'));
                    if (changeDateModal) {
                        changeDateModal.show();
                    }
            });





            function updateBookingDetails(bookingId) {
                $('#viewEventDetails').html(`
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading booking details...</p>
                    </div>
                `);
                
                                    // Show the modal first so user sees the loading indicator
                    const viewModal = new bootstrap.Modal(document.getElementById('viewBookingModal'));
                    if (viewModal) {
                        viewModal.show();
                    }
                
                $.ajax({
                    url: 'get_booking_details.php',
                    method: 'GET',
                    data: { 
                        id: bookingId, 
                        format: 'json',
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        console.log("Booking details response:", response);
                        
                        if (!response || response.error) {
                            $('#viewEventDetails').html(`<div class="alert alert-danger">${response?.error || 'Failed to load booking details'}</div>`);
                            return;
                        }
                        
                        try {
                            const booking = response;
                            
                            // Safety checks for missing data
                            if (!booking) {
                                throw new Error('No booking data returned from server');
                            }
                            
                            // Set default values for missing properties
                            const statusColor = booking.status_color || getStatusColorFromStatus(booking.status) || 'secondary';
                            const facilityName = booking.facility_name || 'Unknown Facility';
                            const status = booking.status || 'unknown';
                            const userName = booking.user_name || 'Unknown User';
                            const purpose = booking.purpose || 'No purpose specified';
                            const attendeesCount = booking.attendees_count || '0';
                            
                            // Format dates safely
                            let startFormatted = 'Date not specified';
                            let endFormatted = 'Date not specified';
                            
                            try {
                                if (booking.start_time) {
                                    const startTime = new Date(booking.start_time);
                                    startFormatted = startTime.toLocaleDateString('en-US', { 
                                        weekday: 'long', 
                                        year: 'numeric', 
                                        month: 'long', 
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                }
                                
                                if (booking.end_time) {
                                    const endTime = new Date(booking.end_time);
                                    endFormatted = endTime.toLocaleDateString('en-US', {
                                        weekday: 'long', 
                                        year: 'numeric', 
                                        month: 'long', 
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                }
                            } catch (dateError) {
                                console.error("Error formatting dates:", dateError);
                            }
                            

                            
                            // Render the booking details
                            $('#viewEventDetails').html(`
                                <div class="card mb-3">
                                    <div class="card-header bg-${statusColor}">
                                        <h5 class="card-title text-white mb-0">
                                            <i class="fas fa-bookmark me-2"></i>
                                            ${facilityName}
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <h6 class="fw-bold">Status:</h6>
                                            <span class="badge bg-${statusColor}">${status.toUpperCase()}</span>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="fw-bold">Booked By:</h6>
                                            <p>${userName}</p>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="fw-bold">Time:</h6>
                                            <p>
                                                <i class="fas fa-calendar-alt me-2"></i> ${startFormatted} <br>
                                                <i class="fas fa-clock me-2"></i> to ${endFormatted}
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="fw-bold">Purpose:</h6>
                                            <p>${purpose}</p>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="fw-bold">Attendees:</h6>
                                            <p>${attendeesCount} person(s)</p>
                                        </div>

                                    </div>
                                </div>
                            `);
                            
                            // Helper function to get color from status
                            function getStatusColorFromStatus(status) {
                                switch(status) {
                                    case 'pending': return 'warning';
                                    case 'approved': return 'success';
                                    case 'cancelled': return 'danger';
                                    default: return 'secondary';
                                }
                            }
                            
                            // Set up buttons based on booking status and user role
                            const cancelBtn = document.getElementById('cancelBooking');
                            const approveBtn = document.getElementById('approveBooking');
                            const changeDateBtn = document.getElementById('changeDateBtn');
                            const deleteBtn = document.getElementById('deleteBooking');
                            const currentDate = new Date();
                            const startTime = booking.start_time ? new Date(booking.start_time) : new Date();
                            
                            if (cancelBtn) {
                                if ((status === 'pending' || status === 'approved') && startTime > currentDate) {
                                    cancelBtn.style.display = 'block';
                                    cancelBtn.setAttribute('data-booking-id', booking.id);
                                } else {
                                    cancelBtn.style.display = 'none';
                                }
                            }
                            
                            if (approveBtn) {
                                if (status === 'pending') {
                                    approveBtn.style.display = 'block';
                                    approveBtn.setAttribute('data-booking-id', booking.id);
                                } else {
                                    approveBtn.style.display = 'none';
                                }
                            }
                            
                            if (changeDateBtn) {
                                // Only show change date for future bookings that haven't ended yet
                                const endTime = booking.end_time ? new Date(booking.end_time) : new Date();
                                if ((status === 'approved' || status === 'pending') && endTime > currentDate) {
                                    changeDateBtn.style.display = 'block';
                                    
                                    // Set the booking and facility IDs for the change date modal
                                    $('#booking_id').val(booking.id);
                                    $('#facility_id').val(booking.facility_id);
                                    
                                    // Pre-populate the date fields safely
                                    if (booking.start_time && booking.end_time) {
                                        try {
                                            const startISOString = booking.start_time.replace(' ', 'T').slice(0, 16);
                                            const endISOString = booking.end_time.replace(' ', 'T').slice(0, 16);
                                            
                                            $('#start_date').val(startISOString);
                                            $('#end_date').val(endISOString);
                                        } catch (error) {
                                            console.error("Error setting date fields:", error);
                                        }
                                    }
                                } else {
                                    changeDateBtn.style.display = 'none';
                                }
                            }
                            
                            if (deleteBtn) {
                                // Only show delete button for past bookings that are not pending
                                const endTime = booking.end_time ? new Date(booking.end_time) : new Date();
                                const isPastBooking = endTime < currentDate;
                                const isNotPending = status !== 'pending';
                                
                                if (isPastBooking && isNotPending) {
                                    deleteBtn.style.display = 'block';
                                    deleteBtn.setAttribute('data-booking-id', booking.id);
                                } else {
                                    deleteBtn.style.display = 'none';
                                }
                            }
                        } catch (error) {
                            console.error("Error processing booking details:", error);
                            $('#viewEventDetails').html(`
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Error processing booking details: ${error.message}
                                </div>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", status, error);
                        console.log("Response text:", xhr.responseText);
                        
                        let errorMessage = 'Failed to load booking details. Please try again.';
                        
                        try {
                            if (xhr.responseText) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.message) {
                                    errorMessage = response.message;
                                }
                            }
                        } catch (e) {
                            console.error("Error parsing response:", e);
                        }
                        
                        $('#viewEventDetails').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${errorMessage}
                            </div>
                        `);
                    }
                });
            }
        });
    </script>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 