<?php
include '../../includes/header.php';
include '../../config/db.php';

// Handle search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$department_id = isset($_GET['department_id']) ? $_GET['department_id'] : '';
$role_id = isset($_GET['role_id']) ? $_GET['role_id'] : '';
$status_id = isset($_GET['status_id']) ? $_GET['status_id'] : '';

// Build the SQL query with filters and search
$query = "SELECT Users.*, Departments.DepartmentName, UserStatus.StatusName, Roles.RoleName, Profile.FirstName, Profile.LastName, Profile.ProfileImage
          FROM Users
          JOIN Departments ON Users.DepartmentID = Departments.DepartmentID
          JOIN UserStatus ON Users.StatusID = UserStatus.StatusID
          JOIN Roles ON Users.RoleID = Roles.RoleID
          LEFT JOIN Profile ON Users.UserID = Profile.UserID
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (Users.Username LIKE :search_username OR Users.Email LIKE :search_email OR Users.StudentID LIKE :search_studentid)";
    $searchTerm = '%' . $search . '%';
    $params[':search_username'] = $searchTerm;
    $params[':search_email'] = $searchTerm;
    $params[':search_studentid'] = $searchTerm;
}

if (!empty($department_id)) {
    $query .= " AND Users.DepartmentID = :department_id";
    $params[':department_id'] = $department_id;
}

if (!empty($role_id)) {
    $query .= " AND Users.RoleID = :role_id";
    $params[':role_id'] = $role_id;
}

if (!empty($status_id)) {
    $query .= " AND Users.StatusID = :status_id";
    $params[':status_id'] = $status_id;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <div class="min-h-screen flex flex-col">
        <main class="flex-grow">
            <div class="p-8">
                <h1 class="text-3xl font-bold mb-6">Admin Dashboard</h1>

                <!-- Filters and Search -->
                <form method="GET" action="dashboard.php" class="mb-6">
                    <div class="flex flex-wrap -mx-3">
                        <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0">
                            <label for="search" class="block text-gray-700 font-bold mb-2">Search by Username, Email, or Student ID</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0">
                            <label for="department_id" class="block text-gray-700 font-bold mb-2">Filter by Department</label>
                            <select id="department_id" name="department_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">All Departments</option>
                                <?php
                                $deptQuery = "SELECT DepartmentID, DepartmentName FROM Departments";
                                $deptStmt = $pdo->prepare($deptQuery);
                                $deptStmt->execute();
                                $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($departments as $department) {
                                    $selected = ($department['DepartmentID'] == $department_id) ? 'selected' : '';
                                    echo "<option value='" . $department['DepartmentID'] . "' " . $selected . ">" . htmlspecialchars($department['DepartmentName']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0">
    <label for="role_id" class="block text-gray-700 font-bold mb-2">Filter by Role</label>
    <select id="role_id" name="role_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        <option value="">All Roles</option>
        <?php
        $roleQuery = "SELECT RoleID, RoleName FROM Roles WHERE RoleName != 'Admin'";
        $roleStmt = $pdo->prepare($roleQuery);
        $roleStmt->execute();
        $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($roles as $role) {
            $selected = ($role['RoleID'] == $role_id) ? 'selected' : '';
            echo "<option value='" . $role['RoleID'] . "' " . $selected . ">" . htmlspecialchars($role['RoleName']) . "</option>";
        }
        ?>
    </select>
</div>
                        <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0">
    <label for="status_id" class="block text-gray-700 font-bold mb-2">Filter by Status</label>
    <select id="status_id" name="status_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        <option value="">All Statuses</option>
        <?php
        $statusQuery = "SELECT StatusID, StatusName FROM UserStatus WHERE StatusName != 'Approved'";
        $statusStmt = $pdo->prepare($statusQuery);
        $statusStmt->execute();
        $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($statuses as $status) {
            $selected = ($status['StatusID'] == $status_id) ? 'selected' : '';
            echo "<option value='" . $status['StatusID'] . "' " . $selected . ">" . htmlspecialchars($status['StatusName']) . "</option>";
        }
        ?>
    </select>
</div>
                        <div class="w-full md:w-1/4 px-3 mb-6 md:mb-0">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mt-6">
                                Filter & Search
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Users Table -->
                <?php if (empty($users)) { ?>
                    <p class="text-red-500 text-center">No users found.</p>
                <?php } else { ?>
                    <table class="min-w-full bg-white shadow-md rounded-lg overflow-hidden">
                        <thead>
                            <tr>
                                <th class="py-3 px-6 bg-gray-100 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Name</th>
                                <th class="py-3 px-6 bg-gray-100 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Email</th>
                                <th class="py-3 px-6 bg-gray-100 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Department</th>
                                <th class="py-3 px-6 bg-gray-100 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Role</th>
                                <th class="py-3 px-6 bg-gray-100 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                                <th class="py-3 px-6 bg-gray-100 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Profile Image</th>
                                <th class="py-3 px-6 bg-gray-100 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user) { ?>
                                <tr class="border-b">
                                    <td class="py-4 px-6 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></td>
                                    <td class="py-4 px-6 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['Email']); ?></td>
                                    <td class="py-4 px-6 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['DepartmentName']); ?></td>
                                    <td class="py-4 px-6 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['RoleName']); ?></td>
                                    <td class="py-4 px-6 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($user['StatusName']); ?></td>
                                    <td class="py-4 px-6 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        $profileImagePath = !empty($user['ProfileImage']) ? '../../uploads/' . $user['ProfileImage'] : '';
                                       ?>
                                            <img src="<?php echo htmlspecialchars($profileImagePath); ?>" alt="Profile Image" class="w-10 h-10 rounded-full">
                                        
                                    </td>
                                    <td class="py-4 px-6 whitespace-nowrap text-sm font-medium">
                                        <a href="profile.php?user_id=<?php echo $user['UserID']; ?>" class="text-blue-600 hover:text-blue-900">View Profile</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
        </main>
        <?php include '../../includes/footer.php'; ?>
    </div>
</body>
</html>