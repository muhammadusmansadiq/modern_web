<?php
// signup.php
include '../includes/header.php';
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Hash the password
    $student_id = $_POST['student_id']; // Keep the column name as StudentID
    $department_id = intval($_POST['department_id']); // Cast DepartmentID to integer
    $role_id = intval($_POST['role_id']); // Cast RoleID to integer
    $status_id = 1; // Default status 'Pending'

    // Validate RoleID to prevent Admin signup
    $check_role_query = "SELECT RoleName FROM Roles WHERE RoleID = :role_id";
    $stmt = $pdo->prepare($check_role_query);
    $stmt->execute(['role_id' => $role_id]);
    $role = $stmt->fetch();

    if (!$role) {
        $error = "Invalid Role ID.";
    } elseif ($role['RoleName'] === 'Admin') {
        $error = "You cannot sign up as an Admin.";
    } else {
        // Validate DepartmentID
        $check_department_query = "SELECT * FROM Departments WHERE DepartmentID = :department_id";
        $stmt = $pdo->prepare($check_department_query);
        $stmt->execute(['department_id' => $department_id]);
        $department = $stmt->fetch();

        if (!$department) {
            $error = "Invalid Department ID.";
        } else {
            // Check if the email or username already exists
            $check_user_query = "SELECT * FROM Users WHERE Email = :email OR Username = :username";
            $stmt = $pdo->prepare($check_user_query);
            $stmt->execute(['email' => $email, 'username' => $username]);
            $result = $stmt->fetchAll();

            if (count($result) > 0) {
                $error = "Email or Username already exists.";
            } else {
                $sql = "INSERT INTO Users (Username, Email, PasswordHash, StudentID, DepartmentID, RoleID, StatusID)
                        VALUES (:username, :email, :password, :student_id, :department_id, :role_id, :status_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'student_id' => $student_id,
                    'department_id' => $department_id,
                    'role_id' => $role_id,
                    'status_id' => $status_id
                ]);

                if ($stmt->rowCount() > 0) {
                    $success = "Registration successful. You can now login.";

                } else {
                    $error = "Error: " . $stmt->errorInfo()[2];
                }
            }
        }
    }
}
?>

<div class="min-h-screen flex flex-col">
    <main class="flex-grow">
        <div class="flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
                <h2 class="text-2xl font-bold mb-6">Sign Up</h2>
                <?php if (isset($error)) { ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $error; ?></span>
                    </div>
                <?php } ?>
                <?php if (isset($success)) { ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $success; ?></span>
                    </div>
                <?php } ?>
                <form method="POST" action="signup.php">
                    <div class="mb-4">
                        <label for="role_id" class="block text-gray-700 font-bold mb-2">Select a Role</label>
                        <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="role_id" name="role_id" required>
                            <option value="" disabled selected>Select a role</option>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM Roles WHERE RoleName != 'Admin'");
                            while ($role = $stmt->fetch()) {
                                echo "<option value='" . $role['RoleID'] . "'>" . htmlspecialchars($role['RoleName']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-4" id="student_id_container" style="display: none;">
                        <label for="student_id" class="block text-gray-700 font-bold mb-2" id="student_id_label">Student ID</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="student_id" name="student_id" required>
                        <span id="student_id_help" class="text-gray-600 text-sm">Enter your Student ID.</span>
                    </div>
                    <div class="mb-4">
                        <label for="username" class="block text-gray-700 font-bold mb-2">Username</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="username" name="username" required>
                    </div>
                    <div class="mb-4">
                        <label for="email" class="block text-gray-700 font-bold mb-2">Email</label>
                        <input type="email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="email" name="email" required>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="block text-gray-700 font-bold mb-2">Password</label>
                        <input type="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline" id="password" name="password" required>
                    </div>
                    <div class="mb-4">
                        <label for="department_id" class="block text-gray-700 font-bold mb-2">Department</label>
                        <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="department_id" name="department_id" required>
                            <option value="" disabled selected>Select a department</option>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM Departments");
                            while ($department = $stmt->fetch()) {
                                echo "<option value='" . $department['DepartmentID'] . "'>" . htmlspecialchars($department['DepartmentName']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="flex items-center justify-between">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Sign Up
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</div>

<script>
    document.getElementById('role_id').addEventListener('change', function() {
        var selectedRole = this.value;
        var studentIdContainer = document.getElementById('student_id_container');
        var studentIdLabel = document.getElementById('student_id_label');
        var studentIdHelp = document.getElementById('student_id_help');

        if (selectedRole) {
            studentIdContainer.style.display = 'block';

            if (selectedRole === '2') { // Assuming RoleID for 'Supervisor' is 2
                studentIdLabel.textContent = 'Staff ID';
                studentIdHelp.textContent = 'Enter your Staff ID.';
            } else {
                studentIdLabel.textContent = 'Student ID';
                studentIdHelp.textContent = 'Enter your Student ID.';
            }
        } else {
            studentIdContainer.style.display = 'none';
        }
    });
</script>