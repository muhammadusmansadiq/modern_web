<?php
// login.php
include '../includes/header.php';
include '../config/db.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = $_POST['identifier'];
    $password = $_POST['password'];
    $error = "";

    try {
        // First, check by email
        $stmt = $pdo->prepare("SELECT UserID, Email, Username, PasswordHash, StatusID, RoleID FROM Users WHERE Email = :identifier");
        $stmt->execute([":identifier" => $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // If no result, check by username
            $stmt = $pdo->prepare("SELECT UserID, Email, Username, PasswordHash, StatusID, RoleID FROM Users WHERE Username = :identifier");
            $stmt->execute([":identifier" => $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($user) {
            // Check if the user's status is Active (StatusID = 5)
            if ($user['StatusID'] == 5) {
                // Verify the password
                if (password_verify($password, $user['PasswordHash'])) {
                   
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['username'] = $user['Username'];
                    $_SESSION['role_id'] = $user['RoleID'];

                    // Fetch roles for the user
                    $roleStmt = $pdo->prepare("SELECT RoleName FROM Roles WHERE RoleID = :role_id");
                    $roleStmt->execute([":role_id" => $user['RoleID']]);
                    $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Save roles or an empty array
                    if ($roles) {
                        $_SESSION['roles'] = $roles;
                    } else {
                        $_SESSION['roles'] = [];
                    }

                    echo "<meta http-equiv='refresh' content='0;url=dashboard.php'>";
                    exit();
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "Your account is not active.";
            }
        } else {
            $error = "Invalid email or username.";
        }
    } catch (PDOException $e) {
        $error = "An error occurred: " . $e->getMessage();
    }
}
?>

<div class="min-h-screen flex flex-col">
    <main class="flex-grow">
        <div class="flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
                <h2 class="text-2xl font-bold mb-6">Login</h2>
                <?php if (isset($error)) { ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $error; ?></span>
                    </div>
                <?php } ?>
                <form method="POST" action="login.php">
                    <div class="mb-4">
                        <label for="identifier" class="block text-gray-700 font-bold mb-2">Email or Username</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="identifier" name="identifier" required>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="block text-gray-700 font-bold mb-2">Password</label>
                        <input type="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" id="password" name="password" required>
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Login
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</div>