<?php
// pages/supervisor/manage_groups.php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// Check if the user is logged in and has the role of Supervisor (RoleID = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

$supervisorID = $_SESSION['user_id'];
$error = "";
$success = "";

// Check if the supervisor has reached the maximum number of groups (3)
try {
    $stmt = $pdo->prepare("SELECT GroupCount FROM Users WHERE UserID = :supervisorID");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $groupCount = $result['GroupCount'] ?? 0;
    $canCreateMoreGroups = $groupCount < 3;
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
    if (!$canCreateMoreGroups) {
        $error = "You have reached the maximum limit of 3 groups.";
    } else {
        $groupName = trim($_POST['group_name']);
        $description = trim($_POST['description']);
        
        if (empty($groupName)) {
            $error = "Group name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO Groups (GroupName, SupervisorID, Description) VALUES (:groupName, :supervisorID, :description)");
                $stmt->execute([
                    ':groupName' => $groupName,
                    ':supervisorID' => $supervisorID,
                    ':description' => $description
                ]);
                
                $success = "Group created successfully.";
                // Refresh the page to reflect changes
                header("Location: manage_groups.php");
                exit();
            } catch (PDOException $e) {
                $error = "Error creating group: " . $e->getMessage();
            }
        }
    }
}

// Handle adding students to a group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $groupID = intval($_POST['group_id']);
    $studentID = intval($_POST['student_id']);
    
    if ($groupID <= 0 || $studentID <= 0) {
        $error = "Invalid group or student ID.";
    } else {
        try {
            // Check if student is already in another group
            $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM StudentGroups WHERE StudentID = :studentID");
            $stmt->execute([':studentID' => $studentID]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                $error = "This student is already in a group.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO StudentGroups (GroupID, StudentID) VALUES (:groupID, :studentID)");
                $stmt->execute([
                    ':groupID' => $groupID,
                    ':studentID' => $studentID
                ]);
                
                $success = "Student added to group successfully.";
                // Refresh the page to reflect changes
                header("Location: manage_groups.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Error adding student to group: " . $e->getMessage();
        }
    }
}

// Handle removing students from a group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_student') {
    $studentGroupID = intval($_POST['student_group_id']);
    
    if ($studentGroupID <= 0) {
        $error = "Invalid student group ID.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM StudentGroups WHERE StudentGroupID = :studentGroupID");
            $stmt->execute([':studentGroupID' => $studentGroupID]);
            
            $success = "Student removed from group successfully.";
            // Refresh the page to reflect changes
            header("Location: manage_groups.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error removing student from group: " . $e->getMessage();
        }
    }
}

// Handle deleting a group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_group') {
    $groupID = intval($_POST['group_id']);
    
    if ($groupID <= 0) {
        $error = "Invalid group ID.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // First delete all student-group associations
            $stmt = $pdo->prepare("DELETE FROM StudentGroups WHERE GroupID = :groupID");
            $stmt->execute([':groupID' => $groupID]);
            
            // Then delete the group itself
            $stmt = $pdo->prepare("DELETE FROM Groups WHERE GroupID = :groupID AND SupervisorID = :supervisorID");
            $stmt->execute([
                ':groupID' => $groupID,
                ':supervisorID' => $supervisorID
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Group deleted successfully.";
            // Refresh the page to reflect changes
            header("Location: manage_groups.php");
            exit();
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error = "Error deleting group: " . $e->getMessage();
        }
    }
}

// Fetch supervisor's groups
try {
    $stmt = $pdo->prepare("
        SELECT 
            g.GroupID, 
            g.GroupName, 
            g.Description, 
            g.CreatedAt,
            COUNT(sg.StudentID) AS StudentCount
        FROM 
            Groups g
        LEFT JOIN 
            StudentGroups sg ON g.GroupID = sg.GroupID
        WHERE 
            g.SupervisorID = :supervisorID
        GROUP BY 
            g.GroupID
        ORDER BY 
            g.CreatedAt DESC
    ");
    $stmt->execute([':supervisorID' => $supervisorID]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching groups: " . $e->getMessage();
}

// Fetch available students (students that don't belong to any group yet)
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.UserID, 
            u.Username, 
            u.Email, 
            p.FirstName, 
            p.LastName
        FROM 
            Users u
        LEFT JOIN 
            Profile p ON u.UserID = p.UserID
        WHERE 
            u.RoleID = 3
            AND u.StatusID = 5
            AND u.UserID NOT IN (SELECT StudentID FROM StudentGroups)
        ORDER BY 
            p.FirstName, p.LastName
    ");
    $stmt->execute();
    $availableStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching available students: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Groups</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.10.5/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4" x-data="{ showCreateGroupModal: false, showAddStudentModal: false, selectedGroupId: null }">
        <h1 class="text-3xl font-bold mb-8 text-center">Manage Student Groups</h1>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Group Count Information -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-semibold mb-4">Group Summary</h2>
            <div class="flex flex-wrap justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <span class="text-lg">Total Groups: <span class="font-bold"><?php echo count($groups); ?>/3</span></span>
                </div>
                <?php if ($canCreateMoreGroups): ?>
                    <button 
                        @click="showCreateGroupModal = true" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200 ease-in-out transform hover:scale-105"
                    >
                        Create New Group
                    </button>
                <?php else: ?>
                    <span class="text-red-500 font-semibold">Maximum group limit reached (3/3)</span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Groups List -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <?php if (empty($groups)): ?>
                <div class="col-span-3 text-center py-10 bg-white rounded-lg shadow-md">
                    <p class="text-gray-500 text-xl">No groups created yet.</p>
                    <?php if ($canCreateMoreGroups): ?>
                        <button 
                            @click="showCreateGroupModal = true" 
                            class="mt-4 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200 ease-in-out"
                        >
                            Create Your First Group
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4">
                            <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($group['GroupName']); ?></h3>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($group['Description'] ?: 'No description provided.'); ?></p>
                            <div class="flex justify-between items-center mb-4">
                                <div class="text-sm text-gray-500">
                                    <p>Created: <?php echo date('M d, Y', strtotime($group['CreatedAt'])); ?></p>
                                    <p>Students: <?php echo $group['StudentCount']; ?></p>
                                </div>
                                <div>
                                    <button 
                                        @click="selectedGroupId = <?php echo $group['GroupID']; ?>; showAddStudentModal = true" 
                                        class="bg-green-500 hover:bg-green-600 text-white text-sm font-bold py-1 px-3 rounded mr-1"
                                    >
                                        Add Student
                                    </button>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this group?');">
                                        <input type="hidden" name="action" value="delete_group">
                                        <input type="hidden" name="group_id" value="<?php echo $group['GroupID']; ?>">
                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-sm font-bold py-1 px-3 rounded">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <h4 class="font-semibold mb-2 text-gray-700">Group Members</h4>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <?php
                                // Fetch students in this group
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT 
                                            sg.StudentGroupID,
                                            u.UserID,
                                            u.Username,
                                            p.FirstName,
                                            p.LastName,
                                            sg.JoinedAt
                                        FROM 
                                            StudentGroups sg
                                        JOIN 
                                            Users u ON sg.StudentID = u.UserID
                                        LEFT JOIN 
                                            Profile p ON u.UserID = p.UserID
                                        WHERE 
                                            sg.GroupID = :groupID
                                        ORDER BY 
                                            p.FirstName, p.LastName
                                    ");
                                    $stmt->execute([':groupID' => $group['GroupID']]);
                                    $groupStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) {
                                    echo "<p class='text-red-500'>Error fetching group members: " . htmlspecialchars($e->getMessage()) . "</p>";
                                    $groupStudents = [];
                                }
                                
                                if (empty($groupStudents)) {
                                    echo "<p class='text-gray-500 text-sm italic'>No students in this group yet.</p>";
                                } else {
                                    foreach ($groupStudents as $student) {
                                        $studentName = $student['FirstName'] && $student['LastName'] ? 
                                            htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']) : 
                                            htmlspecialchars($student['Username']);
                                        ?>
                                        <div class="flex justify-between items-center mb-2 pb-2 border-b border-gray-200">
                                            <div>
                                                <span class="font-medium"><?php echo $studentName; ?></span>
                                                <span class="text-xs text-gray-500 ml-2">Joined: <?php echo date('M d, Y', strtotime($student['JoinedAt'])); ?></span>
                                            </div>
                                            <form method="POST" class="inline-block" onsubmit="return confirm('Remove this student from the group?');">
                                                <input type="hidden" name="action" value="remove_student">
                                                <input type="hidden" name="student_group_id" value="<?php echo $student['StudentGroupID']; ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                        <?php
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Create Group Modal -->
        <div 
            x-show="showCreateGroupModal" 
            @click.away="showCreateGroupModal = false" 
            class="fixed inset-0 flex items-center justify-center z-50"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-90"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-90"
            style="background-color: rgba(0, 0, 0, 0.5);"
        >
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-3">
                <h3 class="text-2xl font-bold mb-4">Create New Group</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_group">
                    <div class="mb-4">
                        <label for="group_name" class="block text-gray-700 font-bold mb-2">Group Name</label>
                        <input type="text" id="group_name" name="group_name" required 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <div class="mb-6">
                        <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                        <textarea id="description" name="description" rows="3" 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" @click="showCreateGroupModal = false" 
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                            Cancel
                        </button>
                        <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Create Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Add Student to Group Modal -->
        <div 
            x-show="showAddStudentModal" 
            @click.away="showAddStudentModal = false" 
            class="fixed inset-0 flex items-center justify-center z-50"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-90"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-90"
            style="background-color: rgba(0, 0, 0, 0.5);"
        >
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-3">
                <h3 class="text-2xl font-bold mb-4">Add Student to Group</h3>
                <?php if (empty($availableStudents)): ?>
                    <p class="text-gray-500 mb-4">No available students found. All students are already assigned to groups.</p>
                    <div class="flex justify-end">
                        <button type="button" @click="showAddStudentModal = false" 
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                            Close
                        </button>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_student">
                        <input type="hidden" name="group_id" x-bind:value="selectedGroupId">
                        <div class="mb-6">
                            <label for="student_id" class="block text-gray-700 font-bold mb-2">Select Student</label>
                            <select id="student_id" name="student_id" required 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Select a student...</option>
                                <?php foreach ($availableStudents as $student): ?>
                                    <?php 
                                    $studentName = $student['FirstName'] && $student['LastName'] ? 
                                        $student['FirstName'] . ' ' . $student['LastName'] : 
                                        $student['Username']; 
                                    ?>
                                    <option value="<?php echo $student['UserID']; ?>">
                                        <?php echo htmlspecialchars($studentName); ?> - <?php echo htmlspecialchars($student['Email']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" @click="showAddStudentModal = false" 
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                                Cancel
                            </button>
                            <button type="submit" 
                                class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                                Add Student
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>