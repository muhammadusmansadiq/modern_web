<?php
// pages/admin/view_group.php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

$groupID = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($groupID <= 0) {
    header("Location: manage_groups.php");
    exit();
}

$error = "";
$success = "";

// Fetch group details
try {
    $stmt = $pdo->prepare("
        SELECT 
            g.GroupID, 
            g.GroupName, 
            g.Description, 
            g.Status,
            g.CreatedAt,
            g.SupervisorID,
            CONCAT(p.FirstName, ' ', p.LastName) AS SupervisorName,
            u.Username AS SupervisorUsername,
            u.Email AS SupervisorEmail
        FROM 
            Groups g
        JOIN 
            Users u ON g.SupervisorID = u.UserID
        LEFT JOIN 
            Profile p ON u.UserID = p.UserID
        WHERE 
            g.GroupID = :groupID
    ");
    $stmt->execute([':groupID' => $groupID]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        $error = "Group not found.";
    }
} catch (PDOException $e) {
    $error = "Error fetching group details: " . $e->getMessage();
}

// Fetch students in the group
if (!$error) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                sg.StudentGroupID,
                sg.StudentID,
                sg.JoinedAt,
                sg.Status AS MembershipStatus,
                u.Username,
                u.Email,
                CONCAT(p.FirstName, ' ', p.LastName) AS StudentName
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
        $stmt->execute([':groupID' => $groupID]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching group members: " . $e->getMessage();
    }
}

// Fetch projects for this group
if (!$error) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.ProjectID,
                p.Title,
                p.Description,
                p.Status,
                p.CreatedAt,
                (SELECT COUNT(*) FROM Submissions s WHERE s.ProjectID = p.ProjectID) AS SubmissionCount
            FROM 
                Projects p
            WHERE 
                p.GroupID = :groupID
            ORDER BY 
                p.CreatedAt DESC
        ");
        $stmt->execute([':groupID' => $groupID]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching projects: " . $e->getMessage();
    }
}

// Fetch recent submissions for this group
if (!$error) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.SubmissionID,
                s.SubmissionType,
                s.SubmittedAt,
                s.Status,
                s.ReviewStatus,
                p.Title AS ProjectTitle,
                m.MilestoneTitle,
                CONCAT(pr.FirstName, ' ', pr.LastName) AS StudentName,
                u.Username AS StudentUsername
            FROM 
                Submissions s
            JOIN 
                Projects p ON s.ProjectID = p.ProjectID
            JOIN 
                Milestones m ON s.MilestoneID = m.MilestoneID
            JOIN 
                FileUploads f ON s.SubmissionID = f.SubmissionID
            JOIN 
                Users u ON f.UploadedBy = u.UserID
            LEFT JOIN 
                Profile pr ON u.UserID = pr.UserID
            WHERE 
                p.GroupID = :groupID
            GROUP BY 
                s.SubmissionID
            ORDER BY 
                s.SubmittedAt DESC
            LIMIT 10
        ");
        $stmt->execute([':groupID' => $groupID]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching submissions: " . $e->getMessage();
    }
}

// Handle adding a student to the group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_student') {
    $studentID = intval($_POST['student_id']);
    
    if ($studentID <= 0) {
        $error = "Invalid student ID.";
    } else {
        try {
            // Check if student is already in a group
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
                header("Location: view_group.php?id=" . $groupID);
                exit();
            }
        } catch (PDOException $e) {
            $error = "Error adding student to group: " . $e->getMessage();
        }
    }
}

// Handle removing a student from the group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_student') {
    $studentGroupID = intval($_POST['student_group_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM StudentGroups WHERE StudentGroupID = :studentGroupID");
        $stmt->execute([':studentGroupID' => $studentGroupID]);
        
        $success = "Student removed from group successfully.";
        // Refresh the page to reflect changes
        header("Location: view_group.php?id=" . $groupID);
        exit();
    } catch (PDOException $e) {
        $error = "Error removing student from group: " . $e->getMessage();
    }
}

// Fetch available students (not in any group)
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.UserID, 
            u.Username, 
            u.Email, 
            CONCAT(p.FirstName, ' ', p.LastName) AS StudentName
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
    <title>Admin - View Group</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.10.5/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4" x-data="{ showAddStudentModal: false }">
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
        
        <?php if (!empty($group)): ?>
            <!-- Group Header -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-6">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div>
                            <h1 class="text-3xl font-bold text-white mb-2"><?php echo htmlspecialchars($group['GroupName']); ?></h1>
                            <div class="flex items-center text-white opacity-90">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                </svg>
                                <span>Created: <?php echo date('F d, Y', strtotime($group['CreatedAt'])); ?></span>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0">
                            <span class="px-4 py-2 rounded-full font-semibold
                                <?php echo $group['Status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo htmlspecialchars($group['Status'] ?: 'Active'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold mb-2">Description</h2>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($group['Description'] ?: 'No description provided.')); ?></p>
                    </div>
                    
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold mb-2">Supervisor</h2>
                        <div class="bg-gray-50 p-4 rounded-lg flex items-center">
                            <div class="flex-shrink-0 h-12 w-12 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                                <?php echo strtoupper(substr(($group['SupervisorName'] ?: $group['SupervisorUsername']), 0, 1)); ?>
                            </div>
                            <div class="ml-4">
                                <h3 class="font-medium"><?php echo htmlspecialchars($group['SupervisorName'] ?: $group['SupervisorUsername']); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($group['SupervisorEmail']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold">Students</h2>
                                <button 
                                    @click="showAddStudentModal = true"
                                    class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium py-2 px-4 rounded-lg transition-colors duration-200">
                                    Add Student
                                </button>
                            </div>
                            
                            <?php if (empty($students)): ?>
                                <p class="text-gray-500 italic">No students in this group yet.</p>
                            <?php else: ?>
                                <div class="bg-gray-50 rounded-lg divide-y divide-gray-200">
                                    <?php foreach ($students as $student): ?>
                                        <div class="p-4 flex justify-between items-center">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                                    <?php echo strtoupper(substr(($student['StudentName'] ?: $student['Username']), 0, 1)); ?>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['StudentName'] ?: $student['Username']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($student['Email']); ?></p>
                                                    <p class="text-xs text-gray-500">Joined: <?php echo date('M d, Y', strtotime($student['JoinedAt'])); ?></p>
                                                </div>
                                            </div>
                                            <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to remove this student from the group?');">
                                                <input type="hidden" name="action" value="remove_student">
                                                <input type="hidden" name="student_group_id" value="<?php echo $student['StudentGroupID']; ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h2 class="text-xl font-semibold mb-4">Projects</h2>
                            <?php if (empty($projects)): ?>
                                <p class="text-gray-500 italic">No projects for this group yet.</p>
                            <?php else: ?>
                                <div class="bg-gray-50 rounded-lg divide-y divide-gray-200">
                                    <?php foreach ($projects as $project): ?>
                                        <div class="p-4">
                                            <h3 class="font-medium"><?php echo htmlspecialchars($project['Title']); ?></h3>
                                            <p class="text-sm text-gray-500 mb-2"><?php echo mb_substr(htmlspecialchars($project['Description']), 0, 100) . (mb_strlen($project['Description']) > 100 ? '...' : ''); ?></p>
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <span class="inline-block text-xs px-2 py-1 rounded-full 
                                                        <?php
                                                            switch ($project['Status']) {
                                                                case 'Proposal Submitted':
                                                                    echo 'bg-yellow-100 text-yellow-800';
                                                                    break;
                                                                case 'Accepted':
                                                                    echo 'bg-green-100 text-green-800';
                                                                    break;
                                                                case 'Rejected':
                                                                    echo 'bg-red-100 text-red-800';
                                                                    break;
                                                                case 'Completed':
                                                                    echo 'bg-blue-100 text-blue-800';
                                                                    break;
                                                                default:
                                                                    echo 'bg-gray-100 text-gray-800';
                                                            }
                                                        ?>">
                                                        <?php echo htmlspecialchars($project['Status']); ?>
                                                    </span>
                                                    <span class="text-xs text-gray-500 ml-2">
                                                        <?php echo date('M d, Y', strtotime($project['CreatedAt'])); ?>
                                                    </span>
                                                </div>
                                                <a href="view_project.php?id=<?php echo $project['ProjectID']; ?>" class="text-sm text-blue-500 hover:text-blue-700">View</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Submissions -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                    <h2 class="text-xl font-bold text-white">Recent Submissions</h2>
                </div>
                <div class="p-6">
                    <?php if (empty($submissions)): ?>
                        <p class="text-gray-500 italic text-center">No submissions found for this group.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead>
                                    <tr class="bg-gray-100 text-gray-700">
                                        <th class="py-3 px-4 text-left">Type</th>
                                        <th class="py-3 px-4 text-left">Project</th>
                                        <th class="py-3 px-4 text-left">Milestone</th>
                                        <th class="py-3 px-4 text-left">Submitted By</th>
                                        <th class="py-3 px-4 text-left">Date</th>
                                        <th class="py-3 px-4 text-left">Status</th>
                                        <th class="py-3 px-4 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $submission): ?>
                                        <tr class="border-t hover:bg-gray-50">
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($submission['SubmissionType']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($submission['ProjectTitle']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($submission['MilestoneTitle']); ?></td>
                                            <td class="py-3 px-4"><?php echo htmlspecialchars($submission['StudentName'] ?: $submission['StudentUsername']); ?></td>
                                            <td class="py-3 px-4 text-sm"><?php echo date('M d, Y, h:i A', strtotime($submission['SubmittedAt'])); ?></td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                                    <?php
                                                        switch ($submission['ReviewStatus']) {
                                                            case 'Accepted':
                                                                echo 'bg-green-100 text-green-800';
                                                                break;
                                                            case 'Rejected':
                                                                echo 'bg-red-100 text-red-800';
                                                                break;
                                                            default:
                                                                echo 'bg-yellow-100 text-yellow-800';
                                                        }
                                                    ?>">
                                                    <?php echo htmlspecialchars($submission['ReviewStatus']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <a href="../supervisor/view_submission.php?id=<?php echo $submission['SubmissionID']; ?>" class="text-blue-500 hover:text-blue-700 text-sm">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Back to Groups List -->
            <div class="flex justify-end mb-8">
                <a href="manage_groups.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Groups
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <h1 class="text-2xl font-bold mb-2">Group Not Found</h1>
                <p class="text-gray-600 mb-6">The group you are looking for does not exist or you do not have permission to view it.</p>
                <a href="manage_groups.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                    Return to Groups
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Add Student Modal -->
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
                        <div class="mb-6">
                            <label for="student_id" class="block text-gray-700 font-bold mb-2">Select Student</label>
                            <select id="student_id" name="student_id" required 
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Select a student...</option>
                                <?php foreach ($availableStudents as $student): ?>
                                    <?php 
                                    $studentName = $student['StudentName'] ? 
                                        $student['StudentName'] : 
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
                                class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
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