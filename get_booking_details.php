<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'config/config.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo 'Unauthorized access';
    exit();
}

// Check for CSRF token in AJAX requests (only for non-GET requests or if explicitly required)
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // For GET requests, we'll be more lenient with CSRF since they're read-only
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            if (isset($_GET['format']) && $_GET['format'] === 'json') {
                header('Content-Type: application/json');
                $message = 'Invalid CSRF token';
                if (!isset($_GET['csrf_token'])) {
                    $message = 'CSRF token is missing';
                } elseif (!isset($_SESSION['csrf_token'])) {
                    $message = 'Session CSRF token is missing';
                } elseif ($_GET['csrf_token'] !== $_SESSION['csrf_token']) {
                    $message = 'CSRF token mismatch';
                }
                echo json_encode(['success' => false, 'message' => $message]);
            } else {
                echo 'Invalid CSRF token';
            }
            exit();
        }
    }
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo 'Missing booking ID';
    exit();
}

$booking_id = trim($_GET['id']);
$user_id = $auth->getUserId();
$is_admin = $auth->isAdmin();
$format = $_GET['format'] ?? 'html';

// Validate booking ID
if (!is_numeric($booking_id) || $booking_id <= 0) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
        exit();
    } else {
        echo '<div class="alert alert-danger">Invalid booking ID.</div>';
        exit();
    }
}

try {
    $query = "
        SELECT b.*, 
            DATE_FORMAT(b.start_time, '%Y-%m-%d %H:%i') as formatted_start,
            DATE_FORMAT(b.end_time, '%Y-%m-%d %H:%i') as formatted_end,
            f.name as facility_name,
            f.description as facility_description,
            f.capacity as facility_capacity,
            u.full_name as user_name,
            u.email as user_email,
            u.phone as user_phone,
            u.department as user_department
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$booking_id]);
    
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        if ($format === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Booking not found or access denied']);
            exit();
        } else {
            echo '<div class="alert alert-danger">Booking not found or access denied.</div>';
            exit();
        }
    }
    
    // Check if user has permission to view this booking
    if (!$is_admin && $booking['user_id'] != $user_id) {
        if ($format === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit();
        } else {
            echo '<div class="alert alert-danger">Access denied.</div>';
            exit();
        }
    }
    
    $start = new DateTime($booking['start_time']);
    $end = new DateTime($booking['end_time']);
    $duration = $start->diff($end);
    
    $duration_text = '';
    if ($duration->h > 0) {
        $duration_text .= $duration->h . ' hour' . ($duration->h > 1 ? 's' : '') . ' ';
    }
    if ($duration->i > 0) {
        $duration_text .= $duration->i . ' minute' . ($duration->i > 1 ? 's' : '');
    }
    
    $status_class = [
        'approved' => 'success',
        'pending' => 'warning',
        'rejected' => 'danger',
        'cancelled' => 'danger',
        'completed' => 'info'
    ][$booking['status']] ?? 'secondary';

    // Materials functionality removed
    $booking['materials'] = [];

    // If JSON format is requested, return JSON response
    if ($format === 'json') {
        // Add status color for easier display
        $booking['status_color'] = $status_class;
        
        header('Content-Type: application/json');
        echo json_encode($booking);
        exit();
    }
?>

<div class="booking-details">
    <div class="row">
        <div class="col-md-6">
            <h5>Facility Information</h5>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['facility_name']); ?></p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($booking['facility_description']); ?></p>
        </div>
        <div class="col-md-6">
            <h5>Booking Status</h5>
            <p>
                <span class="badge bg-<?php echo $status_class; ?>">
                    <?php echo ucfirst($booking['status']); ?>
                </span>
            </p>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-6">
            <h5>Booking Details</h5>
            <p><strong>Start Time:</strong> <?php echo date('Y-m-d h:i A', strtotime($booking['start_time'])); ?></p>
            <p><strong>End Time:</strong> <?php echo date('Y-m-d h:i A', strtotime($booking['end_time'])); ?></p>
            <p><strong>Duration:</strong> <?php echo $duration_text; ?></p>
            <p><strong>Purpose:</strong> <?php echo htmlspecialchars($booking['purpose']); ?></p>
            <?php if (!empty($booking['things_needed'])): ?>
            <p><strong>Things Needed:</strong> <?php echo nl2br(htmlspecialchars($booking['things_needed'])); ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <h5>User Information</h5>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['user_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['user_email']); ?></p>
        </div>
    </div>
    
    <?php if (!empty($booking['materials'])): ?>
    <div class="row mt-3">
        <div class="col-12">
            <h5>Materials Needed</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Material</th>
                            <th>Description</th>
                            <th class="text-center">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($booking['materials'] as $material): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($material['name']); ?></td>
                            <td><?php echo empty($material['description']) ? 'N/A' : htmlspecialchars($material['description']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($material['quantity']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row mt-3">
        <div class="col-12">
            <?php if ($booking['status'] === 'pending' && ($is_admin || $booking['user_id'] == $user_id)): ?>
            <button type="button" class="btn btn-danger" onclick="cancelBooking(<?php echo $booking_id; ?>)">
                <i class="fas fa-times"></i> Cancel Booking
            </button>
            <?php endif; ?>
            <?php if ($booking['status'] === 'approved'): ?>
            <a href="print_receipt.php?booking_id=<?php echo $booking_id; ?>" target="_blank" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Receipt
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
} catch (Exception $e) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error loading booking details']);
        exit();
    } else {
        echo '<div class="alert alert-danger">Error loading booking details.</div>';
    }
}
?>