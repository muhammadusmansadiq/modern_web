<?php
// change_password.php
include '../includes/header.php';
include '../config/db.php';



// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirm password do not match.";
    } else {
        try {
            // Fetch the user's current password hash
            $stmt = $pdo->prepare("SELECT PasswordHash FROM Users WHERE UserID = :user_id");
            $stmt->execute([":user_id" => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($current_password, $user['PasswordHash'])) {
                // Hash the new password
                $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);

                // Update the password in the database
                $updateStmt = $pdo->prepare("UPDATE Users SET PasswordHash = :new_password_hash, UpdatedAt = CURRENT_TIMESTAMP WHERE UserID = :user_id");
                $updateStmt->execute([
                    ":new_password_hash" => $new_password_hash,
                    ":user_id" => $user_id
                ]);

                $success = "Password changed successfully.";
                // Optionally, clear the form fields
                $current_password = "";
                $new_password = "";
                $confirm_password = "";
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<div class="min-h-screen flex flex-col">
    <main class="flex-grow">
        <div class="flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
                <h2 class="text-2xl font-bold mb-6">Change Password</h2>
                <?php if (!empty($error)) { ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $error; ?></span>
                    </div>
                <?php } ?>
                <?php if (!empty($success)) { ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $success; ?></span>
                    </div>
                <?php } ?>
                <form method="POST" action="change_password.php">
                    <div class="mb-4">
                        <label for="current_password" class="block text-gray-700 font-bold mb-2">Current Password</label>
                        <input type="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-4">
                        <label for="new_password" class="block text-gray-700 font-bold mb-2">New Password</label>
                        <input type="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="new_password" name="new_password" required>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="block text-gray-700 font-bold mb-2">Confirm New Password</label>
                        <input type="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</div>