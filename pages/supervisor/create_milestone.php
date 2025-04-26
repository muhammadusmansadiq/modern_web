<?php
// pages/supervisor/create_milestone.php
// Completely separate implementation from manage_groups.php
// Buffer output to prevent "headers already sent" errors
ob_start();

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

// Get project_id and group_id from GET parameters
$projectID = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$groupID = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

// Verify this supervisor owns this project/group
try {
    if ($projectID > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                p.ProjectID,
                p.Title,
                p.Description,
                p.GroupID,
                g.GroupName,
                g.SupervisorID
            FROM 
                Projects p
            JOIN 
                Groups g ON p.GroupID = g.GroupID
            WHERE 
                p.ProjectID = :projectID
                AND g.SupervisorID = :supervisorID
                AND g.Status = 'Active'
        ");
        $stmt->execute([
            ':projectID' => $projectID,
            ':supervisorID' => $supervisorID
        ]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            $error = "You do not have permission to create milestones for this project or the project does not exist.";
        } else {
            $groupID = $project['GroupID'];
        }
    } elseif ($groupID > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                g.GroupID,
                g.GroupName,
                g.Description,
                g.SupervisorID
            FROM 
                Groups g
            WHERE 
                g.GroupID = :groupID
                AND g.SupervisorID = :supervisorID
                AND g.Status = 'Active'
        ");
        $stmt->execute([
            ':groupID' => $groupID,
            ':supervisorID' => $supervisorID
        ]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$group) {
            $error = "You do not have permission to create milestones for this group or the group does not exist.";
        }
    } else {
        $error = "No project or group specified.";
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Fetch projects for this group if group is valid and no specific project was selected
if (empty($error) && $groupID > 0 && $projectID <= 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.ProjectID,
                p.Title,
                p.Description,
                p.Status,
                p.CreatedAt
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

// Handle milestone creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_milestone') {
    $milestoneProjectID = intval($_POST['project_id']);
    $milestoneTitle = trim($_POST['milestone_title']);
    $dueDate = trim($_POST['due_date']);
    $description = trim($_POST['description']);
    
    if ($milestoneProjectID <= 0 || empty($milestoneTitle) || empty($dueDate)) {
        $error = "Project, milestone title, and due date are required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Verify the project belongs to this supervisor
            $stmt = $pdo->prepare("
                SELECT 
                    p.ProjectID,
                    p.GroupID,
                    g.SupervisorID
                FROM 
                    Projects p
                JOIN 
                    Groups g ON p.GroupID = g.GroupID
                WHERE 
                    p.ProjectID = :projectID
                    AND g.SupervisorID = :supervisorID
            ");
            $stmt->execute([
                ':projectID' => $milestoneProjectID,
                ':supervisorID' => $supervisorID
            ]);
            $projectVerification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$projectVerification) {
                throw new Exception("You do not have permission to create milestones for this project.");
            }
            
            // Create milestone
            $stmt = $pdo->prepare("
                INSERT INTO Milestones (
                    ProjectID, 
                    MilestoneTitle, 
                    Description, 
                    DueDate, 
                    Status
                ) VALUES (
                    :projectID, 
                    :milestoneTitle, 
                    :description, 
                    :dueDate, 
                    'Pending'
                )
            ");
            $stmt->execute([
                ':projectID' => $milestoneProjectID,
                ':milestoneTitle' => $milestoneTitle,
                ':description' => $description,
                ':dueDate' => $dueDate
            ]);
            
            // Add to project history
            $stmt = $pdo->prepare("
                INSERT INTO ProjectHistory (
                    ProjectID, 
                    Action, 
                    ActionDate, 
                    UserID
                ) VALUES (
                    :projectID, 
                    :action, 
                    NOW(), 
                    :supervisorID
                )
            ");
            $stmt->execute([
                ':projectID' => $milestoneProjectID,
                ':action' => "Milestone Created: " . $milestoneTitle,
                ':supervisorID' => $supervisorID
            ]);
            
            $pdo->commit();
            
            $success = "Milestone created successfully.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error creating milestone: " . $e->getMessage();
        }
    }
}

// Fetch existing milestones for this project or group
if (empty($error)) {
    try {
        if ($projectID > 0) {
            $stmt = $pdo->prepare("
                SELECT 
                    m.MilestoneID,
                    m.MilestoneTitle,
                    m.Description,
                    m.DueDate,
                    m.Status,
                    p.ProjectID,
                    p.Title AS ProjectTitle
                FROM 
                    Milestones m
                JOIN 
                    Projects p ON m.ProjectID = p.ProjectID
                WHERE 
                    p.ProjectID = :projectID
                ORDER BY 
                    m.DueDate ASC
            ");
            $stmt->execute([':projectID' => $projectID]);
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    m.MilestoneID,
                    m.MilestoneTitle,
                    m.Description,
                    m.DueDate,
                    m.Status,
                    p.ProjectID,
                    p.Title AS ProjectTitle
                FROM 
                    Milestones m
                JOIN 
                    Projects p ON m.ProjectID = p.ProjectID
                WHERE 
                    p.GroupID = :groupID
                ORDER BY 
                    m.DueDate ASC
            ");
            $stmt->execute([':groupID' => $groupID]);
        }
        $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching milestones: " . $e->getMessage();
    }
}

// Fetch group members if we have a valid group
if (empty($error) && $groupID > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.UserID,
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
                AND sg.Status = 'Active'
            ORDER BY 
                p.FirstName, p.LastName
        ");
        $stmt->execute([':groupID' => $groupID]);
        $groupMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching group members: " . $e->getMessage();
    }
}

// Handle creating new project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $objectives = trim($_POST['objectives'] ?? '');
    
    if (empty($title) || empty($description)) {
        $error = "Project title and description are required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert the new project
            $stmt = $pdo->prepare("
                INSERT INTO Projects (
                    Title, 
                    Description, 
                    Objectives, 
                    GroupID, 
                    SupervisorID, 
                    Status, 
                    CreatedAt, 
                    UpdatedAt
                ) VALUES (
                    :title, 
                    :description, 
                    :objectives, 
                    :groupID, 
                    :supervisorID, 
                    'Initiated', 
                    NOW(), 
                    NOW()
                )
            ");
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':objectives' => $objectives,
                ':groupID' => $groupID,
                ':supervisorID' => $supervisorID
            ]);
            
            $projectID = $pdo->lastInsertId();
            
            // Add to project history
            $stmt = $pdo->prepare("
                INSERT INTO ProjectHistory (
                    ProjectID, 
                    Action, 
                    ActionDate, 
                    UserID
                ) VALUES (
                    :projectID, 
                    'Project Initiated', 
                    NOW(), 
                    :supervisorID
                )
            ");
            $stmt->execute([
                ':projectID' => $projectID,
                ':supervisorID' => $supervisorID
            ]);
            
            $pdo->commit();
            
            $success = "Project created successfully.";
            
            // Redirect to the same page to refresh the project list
            header("Location: create_milestone.php?group_id=" . $groupID);
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error creating project: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Milestone</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.10.5/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8" x-data="{ showCreateProjectModal: false }">
        <h1 class="text-3xl font-bold mb-8 text-center">Create Milestone</h1>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">Success</p>
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Project/Group Info -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                <h2 class="text-xl font-bold text-white">
                    <?php if (!empty($project)): ?>
                        Project: <?php echo htmlspecialchars($project['Title']); ?>
                    <?php elseif (!empty($group)): ?>
                        Group: <?php echo htmlspecialchars($group['GroupName']); ?>
                    <?php else: ?>
                        Project/Group Information
                    <?php endif; ?>
                </h2>
            </div>
            
            <div class="p-6">
                <?php if (!empty($project)): ?>
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-2">Project Details</h3>
                        <p class="text-gray-700 mb-2"><?php echo nl2br(htmlspecialchars($project['Description'])); ?></p>
                        <p class="text-gray-600">Group: <?php echo htmlspecialchars($project['GroupName']); ?></p>
                    </div>
                <?php elseif (!empty($group)): ?>
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold mb-2">Group Details</h3>
                        <p class="text-gray-700 mb-2"><?php echo nl2br(htmlspecialchars($group['Description'] ?: 'No description provided.')); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($groupMembers)): ?>
                    <div class="mt-4">
                        <h3 class="text-lg font-semibold mb-2">Group Members</h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($groupMembers as $member): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($member['StudentName'] ?: $member['Username']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Project Selection (only shown when coming from a group) -->
            <?php if (empty($project) && !empty($projects)): ?>
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4 flex justify-between items-center">
                            <h2 class="text-xl font-bold text-white">Projects</h2>
                            <button 
                                @click="showCreateProjectModal = true"
                                class="bg-white text-blue-700 hover:bg-blue-100 text-sm font-bold py-1 px-3 rounded transition-colors duration-200">
                                Add New
                            </button>
                        </div>
                        
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($projects as $proj): ?>
                                    <a href="create_milestone.php?project_id=<?php echo $proj['ProjectID']; ?>" 
                                       class="block border rounded-lg p-4 hover:bg-blue-50 transition-colors duration-200">
                                        <h3 class="font-semibold"><?php echo htmlspecialchars($proj['Title']); ?></h3>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <?php echo mb_substr(htmlspecialchars($proj['Description']), 0, 100) . (mb_strlen($proj['Description']) > 100 ? '...' : ''); ?>
                                        </p>
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs px-2 py-1 rounded-full font-medium
                                                <?php
                                                    switch ($proj['Status']) {
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
                                                <?php echo htmlspecialchars($proj['Status']); ?>
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                <?php echo date('M d, Y', strtotime($proj['CreatedAt'])); ?>
                                            </span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="lg:col-span-2">
            <?php else: ?>
                <div class="lg:col-span-3">
            <?php endif; ?>
                
                <!-- Create Milestone Form -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h2 class="text-xl font-bold text-white">Create Milestone</h2>
                    </div>
                    
                    <div class="p-6">
                        <?php if ((empty($project) && empty($projects)) || (!empty($error) && strpos($error, "permission") !== false)): ?>
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <h3 class="text-xl font-semibold text-gray-700 mb-2">No Projects Available</h3>
                                <?php if (!empty($group)): ?>
                                    <p class="text-gray-600 mb-4">You need to create a project for this group first.</p>
                                    <button 
                                        @click="showCreateProjectModal = true"
                                        class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                        Create First Project
                                    </button>
                                <?php else: ?>
                                    <p class="text-gray-600 mb-4">Please select a valid group or project first.</p>
                                    <a href="manage_groups.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                        View My Groups
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="action" value="create_milestone">
                                
                                <?php if (!empty($projects) && empty($project)): ?>
                                    <div>
                                        <label for="project_id" class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                                        <select 
                                            id="project_id" 
                                            name="project_id" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                            required
                                        >
                                            <option value="">Select a project...</option>
                                            <?php foreach ($projects as $proj): ?>
                                                <option value="<?php echo $proj['ProjectID']; ?>">
                                                    <?php echo htmlspecialchars($proj['Title']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="project_id" value="<?php echo $project['ProjectID']; ?>">
                                <?php endif; ?>
                                
                                <div>
                                    <label for="milestone_title" class="block text-sm font-medium text-gray-700 mb-1">Milestone Title</label>
                                    <input 
                                        type="text" 
                                        id="milestone_title" 
                                        name="milestone_title" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="e.g., Project Proposal, First Draft, Final Submission"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea 
                                        id="description" 
                                        name="description" 
                                        rows="3" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Detailed description of what this milestone requires"
                                    ></textarea>
                                </div>
                                
                                <div>
                                    <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                                    <input 
                                        type="date" 
                                        id="due_date" 
                                        name="due_date" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        required
                                    >
                                </div>
                                
                                <div>
                                    <button 
                                        type="submit"
                                        class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors duration-200"
                                    >
                                        Create Milestone
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Existing Milestones -->
                <?php if (!empty($milestones)): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
                        <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                            <h2 class="text-xl font-bold text-white">Existing Milestones</h2>
                        </div>
                        
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white">
                                    <thead>
                                        <tr class="bg-gray-100 text-gray-700">
                                            <th class="py-3 px-4 text-left">Milestone</th>
                                            <?php if (empty($project)): ?>
                                                <th class="py-3 px-4 text-left">Project</th>
                                            <?php endif; ?>
                                            <th class="py-3 px-4 text-left">Due Date</th>
                                            <th class="py-3 px-4 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($milestones as $milestone): ?>
                                            <?php
                                                $dueDate = new DateTime($milestone['DueDate']);
                                                $today = new DateTime();
                                                $interval = $today->diff($dueDate);
                                                $daysLeft = $dueDate > $today ? $interval->days : -$interval->days;
                                                
                                                $statusClass = '';
                                                $statusText = '';
                                                
                                                if ($milestone['Status'] === 'Completed') {
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    $statusText = 'Completed';
                                                } elseif ($daysLeft < 0) {
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    $statusText = 'Overdue by ' . abs($daysLeft) . ' days';
                                                } elseif ($daysLeft === 0) {
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    $statusText = 'Due today';
                                                } elseif ($daysLeft <= 3) {
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    $statusText = 'Due in ' . $daysLeft . ' days';
                                                } else {
                                                    $statusClass = 'bg-blue-100 text-blue-800';
                                                    $statusText = 'Due in ' . $daysLeft . ' days';
                                                }
                                            ?>
                                            <tr class="border-t hover:bg-gray-50">
                                                <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($milestone['MilestoneTitle']); ?></td>
                                                <?php if (empty($project)): ?>
                                                    <td class="py-3 px-4"><?php echo htmlspecialchars($milestone['ProjectTitle']); ?></td>
                                                <?php endif; ?>
                                                <td class="py-3 px-4"><?php echo date('M d, Y', strtotime($milestone['DueDate'])); ?></td>
                                                <td class="py-3 px-4">
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
