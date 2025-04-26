<?php
session_start();

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    // Get the user role from the session
    $role = $_SESSION['role'];

    // Redirect based on the user role
    switch ($role) {
        case 'Admin':
            header("Location: pages/admin/dashboard.php");
            break;
        case 'Supervisor':
            header("Location: pages/supervisor/dashboard.php");
            break;
        case 'Student':
            header("Location: pages/student/dashboard.php");
            break;
        default:
            header("Location: pages/login.php");
            break;
    }
} else {
    // If not logged in, redirect to the login page
    header("Location: pages/login.php");
}

// Ensure no further code is executed after the redirect
exit();
?>