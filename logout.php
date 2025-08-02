<?php
session_start();
require_once 'includes/Auth.php';
require_once 'config/database.php';

$auth = new Auth($conn);

// Check if this is an auto logout request
$isAutoLogout = isset($_POST['auto_logout']) || isset($_GET['auto_logout']);

if ($isAutoLogout) {
    // For auto logout, just perform the logout without redirect
    $auth->logout();
    http_response_code(200);
    exit('OK');
} else {
    // For manual logout, perform logout and redirect
    $auth->logout();
    header('Location: index.php');
    exit();
}
?> 