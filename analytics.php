<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Booking.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: index.php');
    exit();
}

$booking = new Booking($conn);

// Get analytics data
$monthlyBookings = $booking->getMonthlyBookings();
$facilityUsage = $booking->getFacilityUsage();
$bookingStatus = $booking->getBookingStatusStats();
$peakHours = $booking->getPeakHours();
$popularFacilities = $booking->getPopularFacilities();

// Get current month's data
$currentMonth = date('Y-m');
$currentMonthData = array_filter($monthlyBookings, function($item) use ($currentMonth) {
    return $item['month'] === $currentMonth;
});
$currentMonthData = reset($currentMonthData);
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
    <title>Analytics - School Facility Reservation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .analytics-container {
            padding: 20px;
            margin-top: 20px;
        }

        .analytics-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .analytics-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        .analytics-card:nth-child(1):hover {
            box-shadow: 0 15px 35px rgba(73, 80, 87, 0.15);
        }
        
        .analytics-card:nth-child(2):hover {
            box-shadow: 0 15px 35px rgba(60, 64, 67, 0.15);
        }
        
        .analytics-card:nth-child(3):hover {
            box-shadow: 0 15px 35px rgba(68, 64, 60, 0.15);
        }
        
        .analytics-card:nth-child(4):hover {
            box-shadow: 0 15px 35px rgba(62, 62, 62, 0.15);
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .stat-label {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .trend-up {
            color: #16a34a;
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(22, 163, 74, 0.15) 100%);
            padding: 10px 15px;
            border-radius: 25px;
            border: 2px solid rgba(34, 197, 94, 0.3);
            text-shadow: 0 1px 2px rgba(22, 163, 74, 0.2);
            box-shadow: 0 2px 8px rgba(34, 197, 94, 0.15);
        }

        .trend-down {
            color: #dc2626;
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%);
            padding: 10px 15px;
            border-radius: 25px;
            border: 2px solid rgba(239, 68, 68, 0.3);
            text-shadow: 0 1px 2px rgba(220, 38, 38, 0.2);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.15);
        }

        .chart-container {
            position: relative;
            height: 350px;
            margin-bottom: 25px;
            padding: 15px;
            background: rgba(255,255,255,0.8);
            border-radius: 10px;
        }

        .analytics-card h4 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        /* Stats Cards with Natural Colors */
        .analytics-card:nth-child(1) {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #495057;
            border: 1px solid #dee2e6;
        }
        .analytics-card:nth-child(1) .stat-label {
            color: #1e40af;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(30, 64, 175, 0.1);
        }
        .analytics-card:nth-child(1) .stat-number {
            color: #3b82f6;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .analytics-card:nth-child(2) {
            background: linear-gradient(135deg, #f1f3f4 0%, #e8eaed 100%);
            color: #3c4043;
            border: 1px solid #dadce0;
        }
        .analytics-card:nth-child(2) .stat-label {
            color: #047857;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(4, 120, 87, 0.1);
        }
        .analytics-card:nth-child(2) .stat-number {
            color: #059669;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(5, 150, 105, 0.3);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .analytics-card:nth-child(3) {
            background: linear-gradient(135deg, #faf9f8 0%, #f5f4f2 100%);
            color: #44403c;
            border: 1px solid #e7e5e4;
        }
        .analytics-card:nth-child(3) .stat-label {
            color: #b91c1c;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(185, 28, 28, 0.1);
        }
        .analytics-card:nth-child(3) .stat-number {
            color: #dc2626;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(220, 38, 38, 0.3);
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .analytics-card:nth-child(4) {
            background: linear-gradient(135deg, #f9f8f7 0%, #f4f3f1 100%);
            color: #3e3e3e;
            border: 1px solid #e5e4e2;
        }
        .analytics-card:nth-child(4) .stat-label {
            color: #d97706;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(217, 119, 6, 0.1);
        }
        .analytics-card:nth-child(4) .stat-number {
            color: #f59e0b;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
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
                font-size: 16px;
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

            /* Stats cards for mobile */
            .stats-card {
                margin-bottom: 1rem;
                padding: 1rem;
            }

            .stats-card .card-body {
                padding: 0.5rem;
            }

            .stats-card h3 {
                font-size: 1.5rem;
            }

            .stats-card p {
                font-size: 0.9rem;
            }

            /* Filter section for mobile */
            .filter-section {
                flex-direction: column;
                gap: 1rem;
            }

            .filter-section .form-group {
                width: 100%;
            }

            .filter-section .btn {
                width: 100%;
                margin-top: 1rem;
            }

            /* Calendar for mobile */
            .fc {
                font-size: 0.9rem;
            }

            .fc-toolbar {
                flex-direction: column;
                gap: 1rem;
            }

            .fc-toolbar-title {
                font-size: 1.2rem !important;
            }

            .fc-button {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            .fc-event {
                padding: 0.25rem;
                font-size: 0.8rem;
            }

            .analytics-container {
                padding: 10px;
                margin-top: 10px;
            }

            .analytics-card {
                padding: 15px;
                margin-bottom: 15px;
            }

            .stat-number {
                font-size: 2.2rem;
            }

            .stat-label {
                font-size: 1rem;
            }

            .chart-container {
                height: 250px;
            }

            .row {
                margin-left: -5px;
                margin-right: -5px;
            }

            .col-md-3, .col-md-6 {
                padding-left: 5px;
                padding-right: 5px;
            }

            /* Improve touch targets */
            .btn {
                padding: 12px 20px;
                margin: 5px 0;
            }

            /* Make cards more touch-friendly */
            .analytics-card {
                min-height: 120px;
            }

            /* Adjust chart responsiveness */
            canvas {
                max-width: 100% !important;
                height: auto !important;
            }

            /* Improve readability on small screens */
            h4 {
                font-size: 1.2rem;
                margin-bottom: 15px;
            }

            /* Add spacing between elements */
            .mb-3 {
                margin-bottom: 1rem !important;
            }

            /* Make stats cards more compact */
            .stats-card {
                padding: 15px;
            }

            .stats-card h2 {
                font-size: 1.8rem;
            }

            .stats-card h5 {
                font-size: 1rem;
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

        /* Improve chart tooltips for touch devices */
        @media (hover: none) {
            .chartjs-tooltip {
                background: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 14px;
            }
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
                        <i class="fas fa-chart-line me-3"></i>
                        Analytics Dashboard
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Comprehensive analytics and insights for the School Facility Reservation System</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex justify-content-md-end">
                        <!-- Export button removed -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container analytics-container">

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="analytics-card">
                    <div class="stat-number"><?php echo $currentMonthData['total_bookings'] ?? 0; ?></div>
                    <div class="stat-label">Total Bookings This Month</div>
                    <div class="<?php echo ($currentMonthData['percentage_change'] ?? 0) >= 0 ? 'trend-up' : 'trend-down'; ?>">
                        <i class="fas fa-arrow-<?php echo ($currentMonthData['percentage_change'] ?? 0) >= 0 ? 'up' : 'down'; ?>"></i> 
                        <?php echo abs($currentMonthData['percentage_change'] ?? 0); ?>% from last month
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card">
                    <div class="stat-number"><?php echo $bookingStatus['approved_count']; ?></div>
                    <div class="stat-label">Approved Bookings</div>
                    <div class="trend-up">
                        <i class="fas fa-check-circle"></i> 
                        <?php echo $bookingStatus['total_count'] > 0 ? round(($bookingStatus['approved_count'] / $bookingStatus['total_count']) * 100) : 0; ?>% approval rate
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card">
                    <div class="stat-number"><?php echo $bookingStatus['cancelled_count']; ?></div>
                    <div class="stat-label">Cancelled Bookings</div>
                    <div class="trend-down">
                        <i class="fas fa-times-circle"></i> 
                        <?php echo $bookingStatus['total_count'] > 0 ? round(($bookingStatus['cancelled_count'] / $bookingStatus['total_count']) * 100) : 0; ?>% cancellation rate
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card">
                    <div class="stat-number"><?php echo $facilityUsage['average_usage']; ?>%</div>
                    <div class="stat-label">Average Facility Usage</div>
                    <div class="trend-up">
                        <i class="fas fa-chart-bar"></i> 
                        <?php echo $facilityUsage['total_hours']; ?> total booking hours
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-6">
                <div class="analytics-card">
                    <h4>Monthly Booking Trends</h4>
                    <div class="chart-container">
                        <canvas id="monthlyBookingsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="analytics-card">
                    <h4>Facility Usage Distribution</h4>
                    <div class="chart-container">
                        <canvas id="facilityUsageChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="analytics-card">
                    <h4>Peak Booking Hours</h4>
                    <div class="chart-container">
                        <canvas id="peakHoursChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="analytics-card">
                    <h4>Popular Facilities</h4>
                    <div class="chart-container">
                        <canvas id="popularFacilitiesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js configuration for better mobile display
        Chart.defaults.font.size = 12;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.legend.labels.boxWidth = 12;
        Chart.defaults.plugins.legend.labels.padding = 10;

        // Monthly Bookings Chart
        new Chart(document.getElementById('monthlyBookingsChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthlyBookings, 'month')); ?>,
                datasets: [{
                    label: 'Total Bookings',
                    data: <?php echo json_encode(array_column($monthlyBookings, 'total_bookings')); ?>,
                    borderColor: '#0d6efd',
                    tension: 0.1,
                    fill: true,
                    backgroundColor: 'rgba(13, 110, 253, 0.1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Facility Usage Chart
        new Chart(document.getElementById('facilityUsageChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($facilityUsage['by_facility'], 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($facilityUsage['by_facility'], 'usage_count')); ?>,
                    backgroundColor: [
                        '#0d6efd',
                        '#28a745',
                        '#ffc107',
                        '#b33b72',
                        '#6c757d'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });

        // Peak Hours Chart
        new Chart(document.getElementById('peakHoursChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($peakHours, 'hour')); ?>,
                datasets: [{
                    label: 'Number of Bookings',
                    data: <?php echo json_encode(array_column($peakHours, 'booking_count')); ?>,
                    backgroundColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Popular Facilities Chart
        new Chart(document.getElementById('popularFacilitiesChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($popularFacilities, 'name')); ?>,
                datasets: [{
                    label: 'Booking Count',
                    data: <?php echo json_encode(array_column($popularFacilities, 'booking_count')); ?>,
                    backgroundColor: '#28a745'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Handle window resize for better chart responsiveness
        window.addEventListener('resize', function() {
            const charts = document.querySelectorAll('canvas');
            charts.forEach(canvas => {
                const chart = Chart.getChart(canvas);
                if (chart) {
                    chart.resize();
                }
            });
        });
    </script>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 