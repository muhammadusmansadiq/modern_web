<?php
// dashboard.php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch user role data
include '../config/db.php';

$user_id = $_SESSION['user_id'];

// Fetch user role data
$stmt = $pdo->prepare("SELECT RoleName FROM Roles WHERE RoleID = :role_id");
$stmt->execute([":role_id" => $_SESSION['role_id']]);
$userRole = $stmt->fetch(PDO::FETCH_ASSOC);

if ($userRole && isset($userRole['RoleName'])) {
    $roleName = $userRole['RoleName'];

    // Redirect based on user role
    switch ($roleName) {
        case 'Admin':
            header("Location: admin/dashboard.php");
            exit();
        case 'Supervisor':
            header("Location: supervisor/dashboard.php");
            exit();
        case 'Student':
            header("Location: student/dashboard.php");
            exit();
        default:
            // If the role is not recognized, log out the user and redirect to login
            session_destroy();
            header("Location: login.php?error=unknown_role");
            exit();
    }
} else {
    // If the role data is not found, log out the user and redirect to login
    session_destroy();
    header("Location: login.php?error=role_not_found");
    exit();
}
?>