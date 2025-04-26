<?php
include '../../includes/header.php';
require '../../config/db.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] !== 1) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_GET['user_id'];

// Fetch user details
$query = "SELECT Users.*, Departments.DepartmentName, UserStatus.StatusName, Roles.RoleName, Profile.FirstName, Profile.LastName, Profile.ContactInfo, Profile.DOB, Profile.CNIC, Profile.ProfileImage
          FROM Users
          JOIN Departments ON Users.DepartmentID = Departments.DepartmentID
          JOIN UserStatus ON Users.StatusID = UserStatus.StatusID
          JOIN Roles ON Users.RoleID = Roles.RoleID
          LEFT JOIN Profile ON Users.UserID = Profile.UserID
          WHERE Users.UserID = :user_id";
$stmt = $pdo->prepare($query);
$stmt->execute([":user_id" => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: dashboard.php");
    exit();
}

// Fetch user's projects and milestones
$projectsQuery = "SELECT Projects.ProjectID, Projects.Title, Projects.Description, Projects.Objectives, Projects.Status, Projects.SupervisorID, Users.Username AS SupervisorName
                  FROM Projects
                  JOIN Users ON Projects.SupervisorID = Users.UserID
                  WHERE Projects.StudentID = :user_id";
$projectsStmt = $pdo->prepare($projectsQuery);
$projectsStmt->execute([":user_id" => $user_id]);
$projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch milestones for each project
foreach ($projects as &$project) {
    $milestonesQuery = "SELECT MilestoneID, MilestoneTitle, DueDate, Status
                        FROM Milestones
                        WHERE ProjectID = :project_id";
    $milestonesStmt = $pdo->prepare($milestonesQuery);
    $milestonesStmt->execute([":project_id" => $project['ProjectID']]);
    $project['Milestones'] = $milestonesStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_status_id = isset($_POST['status_id']) ? (int)$_POST['status_id'] : null;

    if ($new_status_id === null) {
        $error = "Invalid status selection.";
    } else {
        try {
            $updateStmt = $pdo->prepare("UPDATE Users SET StatusID = :status_id WHERE UserID = :user_id");
            $updateStmt->execute([":status_id" => $new_status_id, ":user_id" => $user_id]);
            // header("Location: profile.php?user_id=" . $user_id);
            echo "<meta http-equiv='refresh' content='0;url=dashboard.php'>";

            exit();
        } catch (PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <main class="flex-grow">
            <div class="container mx-auto p-8">
                <h1 class="text-4xl font-bold mb-8 text-center text-gray-800">User Profile</h1>

                <!-- User Information Card -->
                <div class="bg-white p-8 rounded-lg shadow-md mb-8 flex flex-col md:flex-row items-center">
                    <div class="md:w-1/4 text-center md:text-left">
                        <?php 
                        $profileImagePath = !empty($user['ProfileImage']) ?  '../../uploads/' . $user['ProfileImage'] : '';
                        if (!empty($profileImagePath) && file_exists($profileImagePath)) { ?>
                            <img src="<?php echo htmlspecialchars($profileImagePath); ?>" alt="Profile Image" class="w-32 h-32 rounded-full mb-4 md:mb-0">
                        <?php } else { ?>
                            <span class="text-gray-500">No Image</span>
                        <?php } ?>
                    </div>
                    <div class="md:w-3/4 pl-0 md:pl-8">
                        <h2 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']); ?></h2>
                        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($user['Email']); ?></p>
                        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($user['DepartmentName']); ?></p>
                        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($user['RoleName']); ?></p>
                        <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($user['StatusName']); ?></p>
                        <div class="grid grid-cols-2 gap-6 mt-4">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Contact Info</label>
                                <p class="text-gray-600"><?php echo htmlspecialchars($user['ContactInfo']); ?></p>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Date of Birth</label>
                                <p class="text-gray-600"><?php echo htmlspecialchars($user['DOB']); ?></p>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">CNIC</label>
                                <p class="text-gray-600"><?php echo htmlspecialchars($user['CNIC']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Update Status Form -->
                <div class="bg-white p-8 rounded-lg shadow-md mb-8">
                    <h2 class="text-3xl font-bold mb-6 text-gray-800">Update Status</h2>
                    <form method="POST" action="profile.php?user_id=<?php echo $user['UserID']; ?>">
                        <div class="mb-4">
                            <label for="status_id" class="block text-gray-700 font-bold mb-2">Status</label>
                            <select id="status_id" name="status_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <?php
                                $statusQuery = "SELECT StatusID, StatusName FROM UserStatus";
                                $statusStmt = $pdo->prepare($statusQuery);
                                $statusStmt->execute();
                                $statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($statuses as $status) {
                                    // Skip the "Approved" status
                                    if ($status['StatusName'] === 'Approved') {
                                        continue;
                                    }
                                    $selected = ($status['StatusID'] == $user['StatusID']) ? 'selected' : '';
                                    // Allow transitions from Blocked/Rejected to Active
                                    if ($user['StatusID'] == 4 && $status['StatusID'] == 5) { // Blocked to Active
                                        echo "<option value='" . $status['StatusID'] . "' " . $selected . ">" . htmlspecialchars($status['StatusName']) . "</option>";
                                    } elseif ($user['StatusID'] == 3 && $status['StatusID'] == 5) { // Rejected to Active
                                        echo "<option value='" . $status['StatusID'] . "' " . $selected . ">" . htmlspecialchars($status['StatusName']) . "</option>";
                                    } elseif ($user['StatusID'] == 1 && $status['StatusID'] == 5) { // Pending to Active
                                        echo "<option value='" . $status['StatusID'] . "' " . $selected . ">" . htmlspecialchars($status['StatusName']) . "</option>";
                                    } elseif ($user['StatusID'] == 1 && $status['StatusID'] == 3) { // Pending to Rejected
                                        echo "<option value='" . $status['StatusID'] . "' " . $selected . ">" . htmlspecialchars($status['StatusName']) . "</option>";
                                    } elseif ($user['StatusID'] == 5 && $status['StatusID'] == 4) { // Active to Blocked
                                        echo "<option value='" . $status['StatusID'] . "' " . $selected . ">" . htmlspecialchars($status['StatusName']) . "</option>";
                                    } elseif ($user['StatusID'] == 5 && $status['StatusID'] == 3) { // Active to Rejected
                                        echo "<option value='" . $status['StatusID'] . "' " . $selected . ">" . htmlspecialchars($status['StatusName']) . "</option>";
                                    } else {
                                        echo "<option value='" . $status['StatusID'] . "' " . $selected . " disabled>" . htmlspecialchars($status['StatusName']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Update Status
                        </button>
                    </form>

                    <?php if (isset($error)) { ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-4" role="alert">
                            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php } ?>
                </div>

                <!-- Projects and Milestones -->
                <div class="bg-white p-8 rounded-lg shadow-md mb-8">
                    <h2 class="text-3xl font-bold mb-6 text-gray-800">Projects</h2>
                    <?php if (empty($projects)) { ?>
                        <p class="text-red-500 text-center">No projects found.</p>
                    <?php } else { ?>
                        <?php foreach ($projects as $project) { ?>
                            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                                <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($project['Title']); ?></h3>
                                <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($project['Description']); ?></p>
                                <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($project['Objectives']); ?></p>
                                <p class="text-gray-600 mb-2">Status: <?php echo htmlspecialchars($project['Status']); ?></p>
                                <p class="text-gray-600 mb-2">Supervisor: <?php echo htmlspecialchars($project['SupervisorName']); ?></p>
                                <div class="mt-4">
                                    <h4 class="text-lg font-bold mb-2">Milestones</h4>
                                    <?php if (empty($project['Milestones'])) { ?>
                                        <p class="text-red-500">No milestones found.</p>
                                    <?php } else { ?>
                                        <ul class="list-disc list-inside">
                                            <?php foreach ($project['Milestones'] as $milestone) { ?>
                                                <li class="mb-2">
                                                    <strong><?php echo htmlspecialchars($milestone['MilestoneTitle']); ?></strong> - Due Date: <?php echo htmlspecialchars($milestone['DueDate']); ?> - Status: <?php echo htmlspecialchars($milestone['Status']); ?>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </main>
        <?php include '../../includes/footer.php'; ?>
    </div>
</body>
</html>