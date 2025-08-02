<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';
require_once 'includes/Receipt.php';

$auth = new Auth($conn);

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if (!isset($_GET['booking_id'])) {
    $_SESSION['error_message'] = "Booking ID is required.";
    header('Location: dashboard.php');
    exit();
}

$booking_id = $_GET['booking_id'];

try {
    $receipt = new Receipt($conn);
    
    $html = $receipt->generateBookingReceipt($booking_id, false);
    
    echo $html;
    
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: dashboard.php');
    exit();
} 