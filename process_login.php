<?php
session_start();
require_once 'config/database.php';
require_once 'includes/Auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid form submission. Please try again.';
        header('Location: index.php');
        exit();
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['error'] = 'Please enter both username and password.';
        $_SESSION['preserved_username'] = $username; // Preserve username even if empty
        header('Location: index.php');
        exit();
    }

    try {
        $auth = new Auth($conn);
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            // Clear preserved username on successful login
            if (isset($_SESSION['preserved_username'])) {
                unset($_SESSION['preserved_username']);
            }
            header('Location: dashboard.php');
            exit();
        } else {
            $_SESSION['error'] = $result['message'];
            $_SESSION['preserved_username'] = $username; // Preserve username on login failure
            header('Location: index.php');
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = 'An error occurred. Please try again later.';
        $_SESSION['preserved_username'] = $username; // Preserve username on error
        header('Location: index.php');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?> 