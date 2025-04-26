<?php
// header.php
session_start();

// Manually define the base URL
$base_url = 'http://localhost/dms_project/pages/';
$base_dir = 'http://localhost/dms_project/';

// Fetch user role and profile image if user is logged in
$user_role = null;
$profile_image = null;
$first_name = null;
$email = null;

if (isset($_SESSION['user_id'])) {
    include_once __DIR__ . '/../config/db.php';

    // Fetch user role and email
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT RoleID, Email FROM Users WHERE UserID = :user_id");
    $stmt->execute([":user_id" => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user_role = $user['RoleID'];
        $email = $user['Email'];

        // Fetch profile image and first name
        $profileStmt = $pdo->prepare("SELECT ProfileImage, FirstName FROM Profile WHERE UserID = :user_id");
        $profileStmt->execute([":user_id" => $user_id]);
        $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

        if ($profile) {
            $profile_image = $profile['ProfileImage'];
            $first_name = $profile['FirstName'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="http://localhost/dms_project/favicon.png">

    <title>Dissertation Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/flowbite@1.4.7/dist/flowbite.js"></script>
    <style>
        .dropdown-menu {
            display: none;
            position: absolute;
            background-color: #fff;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
        }
        .dropdown-menu a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .dropdown-menu a:hover {
            background-color: #f1f1f1;
        }
        .dropdown:hover .dropdown-menu {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php
// includes/header.php (partial update)
// This code represents the navigation section that would be added to the existing header.php file

// Inside the existing header.php file, replace the existing navigation with this updated version
?>

<nav class="bg-white border-gray-200 dark:bg-gray-900">
    <div class="max-w-screen-xl flex flex-wrap items-center justify-between mx-auto p-4">
        <a href="<?php echo $base_url; ?>dashboard.php" class="flex items-center space-x-3 rtl:space-x-reverse" style="background-color: #1a202c; color: white; padding: 10px; border-radius: 5px;">
            <img src="http://localhost/dms_project/au.png" class="h-8" alt="Workflow">
        </a>
        <div class="flex items-center md:order-2 space-x-3 md:space-x-0 rtl:space-x-reverse">
            <?php if (isset($_SESSION['user_id'])) { ?>
                <button type="button" class="flex text-sm bg-gray-800 rounded-full md:me-0 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="user-dropdown" data-dropdown-placement="bottom">
                    <span class="sr-only">Open user menu</span>
                    <img class="w-8 h-8 rounded-full" src="<?php echo $profile_image ? $base_dir . 'uploads/' . htmlspecialchars($profile_image) : 'https://via.placeholder.com/32'; ?>" alt="user photo">
                </button>
                <!-- Dropdown menu -->
                <div class="z-50 hidden my-4 text-base list-none bg-white divide-y divide-gray-100 rounded-lg shadow dark:bg-gray-700 dark:divide-gray-600" id="user-dropdown">
                    <div class="px-4 py-3">
                        <span class="block text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($first_name ?? 'User'); ?></span>
                        <span class="block text-sm text-gray-500 truncate dark:text-gray-400"><?php echo htmlspecialchars($email ?? 'user@example.com'); ?></span>
                    </div>
                    <ul class="py-2" aria-labelledby="user-menu-button">
                        <li>
                            <a href="<?php echo $base_url; ?>profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Profile</a>
                        </li>
                        <li>
                            <a href="<?php echo $base_url; ?>change_password.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Change Password</a>
                        </li>
                        <li>
                            <a href="<?php echo $base_url; ?>logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-200 dark:hover:text-white">Logout</a>
                        </li>
                    </ul>
                </div>
            <?php } ?>
            <button data-collapse-toggle="navbar-user" type="button" class="inline-flex items-center p-2 w-10 h-10 justify-center text-sm text-gray-500 rounded-lg md:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600" aria-controls="navbar-user" aria-expanded="false">
                <span class="sr-only">Open main menu</span>
                <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 17 14">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 1h15M1 7h15M1 13h15"/>
                </svg>
            </button>
        </div>
        <div class="items-center justify-between hidden w-full md:flex md:w-auto md:order-1" id="navbar-user">
            <ul class="flex flex-col font-medium p-4 md:p-0 mt-4 border border-gray-100 rounded-lg bg-gray-50 md:space-x-8 rtl:space-x-reverse md:flex-row md:mt-0 md:border-0 md:bg-white dark:bg-gray-800 md:dark:bg-gray-900 dark:border-gray-700">
                <li>
                    <a href="<?php echo $base_url; ?>dashboard.php" class="block py-2 px-3 text-blue-700 rounded md:bg-transparent md:text-blue-700 md:p-0" aria-current="page">Home</a>
                </li>
                <?php if (!isset($_SESSION['user_id'])) { ?>
                    <li>
                        <a href="<?php echo $base_url; ?>login.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Login</a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>signup.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Sign Up</a>
                    </li>
                <?php } else { ?>
                    <li>
                        <a href="<?php echo $base_url; ?>dashboard.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Dashboard</a>
                    </li>

                    <?php if ($_SESSION['role_id'] == 3){ // Student Navigation ?>
                    <li>
                        <a href="<?php echo $base_url; ?>/student/submit_project.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Submit Files</a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>/student/view_feedback.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Feedback</a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>/student/view_submissions.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Submissions</a>
                    </li>
                    
                    <?php } elseif ($_SESSION['role_id'] == 2) { // Supervisor Navigation ?>
                    <li>
                        <a href="<?php echo $base_url; ?>/supervisor/manage_groups.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Manage Groups</a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>/supervisor/view_submissions.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Review Submissions</a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>/supervisor/create_milestone.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Create Milestone</a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>/supervisor/view_feedback.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Feedback</a>
                    </li>
                    
                    <?php } elseif ($_SESSION['role_id'] == 1) { // Admin Navigation ?>
                    <li>
                        <a href="<?php echo $base_url; ?>/admin/dashboard.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Users</a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>/admin/manage_groups.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Groups</a>
                    </li>
                    <li>
                        <a href="<?php echo $base_url; ?>/admin/reports.php" class="block py-2 px-3 text-gray-900 rounded hover:bg-gray-100 md:hover:bg-transparent md:hover:text-blue-700 md:p-0 dark:text-white md:dark:hover:text-blue-500 dark:hover:bg-gray-700 dark:hover:text-white md:dark:hover:bg-transparent dark:border-gray-700">Reports</a>
                    </li>
                    <?php } ?>
                <?php } ?>
            </ul>
        </div>
    </div>
</nav>
<div class="py-10">