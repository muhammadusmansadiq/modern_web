<?php
// pages/student/dashboard.php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Check if student belongs to a group
try {
    $stmt = $pdo->prepare("
        SELECT 
            sg.GroupID,
            g.GroupName,
            g.Description,
            g.SupervisorID,
            CONCAT(p.FirstName, ' ', p.LastName) AS SupervisorName,
            u.Username AS SupervisorUsername,
            u.Email AS SupervisorEmail
        FROM 
            StudentGroups sg
        JOIN 
            Groups g ON sg.GroupID = g.GroupID
        JOIN 
            Users u ON g.SupervisorID = u.UserID
        LEFT JOIN 
            Profile p ON u.UserID = p.UserID
        WHERE 
            sg.StudentID = :studentID AND sg.Status = 'Active'
    ");
    $stmt->execute([':studentID' => $user_id]);
    $groupInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$groupInfo) {
        $error = "You are not assigned to any group. Please contact your supervisor to be added to a group.";
    } else {
        // Get other students in the same group
        $stmt = $pdo->prepare("
            SELECT 
                u.UserID,
                u.Username,
                u.Email,
                CONCAT(p.FirstName, ' ', p.LastName) AS StudentName,
                sg.JoinedAt
            FROM 
                StudentGroups sg
            JOIN 
                Users u ON sg.StudentID = u.UserID
            LEFT JOIN 
                Profile p ON u.UserID = p.UserID
            WHERE 
                sg.GroupID = :groupID AND sg.StudentID != :currentStudentID
            ORDER BY 
                p.FirstName, p.LastName
        ");
        $stmt->execute([
            ':groupID' => $groupInfo['GroupID'],
            ':currentStudentID' => $user_id
        ]);
        $groupMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Fetch projects for this group
if (!empty($groupInfo)) {
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
        $stmt->execute([':groupID' => $groupInfo['GroupID']]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each project, get the latest submission and pending milestones
        foreach ($projects as &$project) {
            // Get latest submission
            $stmt = $pdo->prepare("
                SELECT 
                    s.SubmissionID,
                    s.SubmissionType,
                    s.SubmittedAt,
                    s.Status AS SubmissionStatus,
                    s.ReviewStatus,
                    m.MilestoneTitle
                FROM 
                    Submissions s
                JOIN 
                    Milestones m ON s.MilestoneID = m.MilestoneID
                WHERE 
                    s.ProjectID = :projectID
                ORDER BY 
                    s.SubmittedAt DESC
                LIMIT 1
            ");
            $stmt->execute([':projectID' => $project['ProjectID']]);
            $project['LatestSubmission'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get pending milestones
            $stmt = $pdo->prepare("
                SELECT 
                    MilestoneID,
                    MilestoneTitle,
                    DueDate,
                    Status
                FROM 
                    Milestones
                WHERE 
                    ProjectID = :projectID AND Status = 'Pending'
                ORDER BY 
                    DueDate ASC
            ");
            $stmt->execute([':projectID' => $project['ProjectID']]);
            $project['PendingMilestones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get project history
            $stmt = $pdo->prepare("
                SELECT 
                    ph.Action,
                    ph.ActionDate,
                    ph.Status AS HistoryStatus,
                    ph.DaysLate,
                    CONCAT(p.FirstName, ' ', p.LastName) AS UserName,
                    u.Username
                FROM 
                    ProjectHistory ph
                JOIN 
                    Users u ON ph.UserID = u.UserID
                LEFT JOIN 
                    Profile p ON u.UserID = p.UserID
                WHERE 
                    ph.ProjectID = :projectID
                ORDER BY 
                    ph.ActionDate DESC
                LIMIT 5
            ");
            $stmt->execute([':projectID' => $project['ProjectID']]);
            $project['RecentHistory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Error fetching projects: " . $e->getMessage();
    }
}

// Get recent feedback
if (!empty($groupInfo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                f.FeedbackID,
                f.FeedbackText,
                f.SentAt,
                f.ProjectID,
                CONCAT(p.FirstName, ' ', p.LastName) AS SenderName,
                u.Username AS SenderUsername,
                pr.Title AS ProjectTitle
            FROM 
                Feedback f
            JOIN 
                Users u ON f.SenderID = u.UserID
            LEFT JOIN 
                Profile p ON u.UserID = p.UserID
            JOIN 
                Projects pr ON f.ProjectID = pr.ProjectID
            WHERE 
                pr.GroupID = :groupID
            ORDER BY 
                f.SentAt DESC
            LIMIT 5
        ");
        $stmt->execute([':groupID' => $groupInfo['GroupID']]);
        $recentFeedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching feedback: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center text-gray-800">Student Dashboard</h1>
        
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
        
        <?php if (empty($groupInfo)): ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <h2 class="text-2xl font-bold mb-4">You are not assigned to any group</h2>
                <p class="text-gray-600 mb-6">Please contact your supervisor to be added to a group in order to start working on projects.</p>
            </div>
        <?php else: ?>
            <!-- Group Information Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                            <h2 class="text-xl font-bold text-white">Group Information</h2>
                        </div>
                        <div class="p-6">
                            <h3 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($groupInfo['GroupName']); ?></h3>
                            <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($groupInfo['Description'])); ?></p>
                            
                            <h4 class="font-semibold text-lg mb-2">Supervisor</h4>
                            <div class="bg-blue-50 p-4 rounded-lg mb-6 flex items-center">
                                <div class="flex-shrink-0 h-12 w-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr(($groupInfo['SupervisorName'] ?: $groupInfo['SupervisorUsername']), 0, 1)); ?>
                                </div>
                                <div class="ml-4">
                                    <p class="font-semibold"><?php echo htmlspecialchars($groupInfo['SupervisorName'] ?: $groupInfo['SupervisorUsername']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($groupInfo['SupervisorEmail']); ?></p>
                                </div>
                            </div>
                            
                            <h4 class="font-semibold text-lg mb-2">Group Members</h4>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php if (empty($groupMembers)): ?>
                                    <p class="text-gray-500 italic">You are the only member in this group.</p>
                                <?php else: ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <?php foreach ($groupMembers as $member): ?>
                                            <div class="flex items-center p-3 border rounded-md">
                                                <div class="flex-shrink-0 h-10 w-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                                    <?php echo strtoupper(substr(($member['StudentName'] ?: $member['Username']), 0, 1)); ?>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($member['StudentName'] ?: $member['Username']); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($member['Email']); ?></p>
                                                    <p class="text-xs text-gray-500">Joined: <?php echo date('M d, Y', strtotime($member['JoinedAt'])); ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                            <h2 class="text-xl font-bold text-white">Quick Actions</h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <a href="submit_project.php" class="block w-full py-3 px-4 bg-green-500 hover:bg-green-600 text-white font-medium rounded-lg shadow text-center transition-colors duration-200">
                                    Submit New Files
                                </a>
                                <a href="view_feedback.php" class="block w-full py-3 px-4 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg shadow text-center transition-colors duration-200">
                                    View Feedback
                                </a>
                                <a href="view_history.php" class="block w-full py-3 px-4 bg-purple-500 hover:bg-purple-600 text-white font-medium rounded-lg shadow text-center transition-colors duration-200">
                                    View History
                                </a>
                                <a href="view_submissions.php" class="block w-full py-3 px-4 bg-yellow-500 hover:bg-yellow-600 text-white font-medium rounded-lg shadow text-center transition-colors duration-200">
                                    View All Submissions
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Project Section -->
            <div class="mb-8">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4 flex justify-between items-center">
                        <h2 class="text-xl font-bold text-white">Group Projects</h2>
                        <?php if (!empty($projects)): ?>
                            <a href="view_all_projects.php" class="text-sm text-white hover:text-blue-200 underline">View All</a>
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <?php if (empty($projects)): ?>
                            <div class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h3 class="text-xl font-semibold text-gray-500">No projects yet</h3>
                                <p class="text-gray-500 mt-2">Your group has not started any projects yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach ($projects as $project): ?>
                                    <div class="border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow duration-200">
                                        <div class="bg-gray-50 p-4 border-b">
                                            <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($project['Title']); ?></h3>
                                            <p class="text-sm text-gray-500">Created: <?php echo date('M d, Y', strtotime($project['CreatedAt'])); ?></p>
                                            <span class="inline-block mt-2 px-3 py-1 text-xs font-semibold rounded-full
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
                                                ?>
                                            ">
                                                <?php echo htmlspecialchars($project['Status']); ?>
                                            </span>
                                        </div>
                                        <div class="p-4">
                                            <?php if (!empty($project['PendingMilestones'])): ?>
                                                <h4 class="font-medium text-sm text-gray-600 mb-2">Upcoming Milestones</h4>
                                                <ul class="space-y-2 mb-4">
                                                    <?php foreach ($project['PendingMilestones'] as $milestone): ?>
                                                        <?php
                                                            $dueDate = new DateTime($milestone['DueDate']);
                                                            $today = new DateTime();
                                                            $interval = $today->diff($dueDate);
                                                            $daysLeft = $dueDate > $today ? $interval->days : -$interval->days;
                                                            
                                                            $badgeClass = 'bg-blue-100 text-blue-800';
                                                            if ($daysLeft < 0) {
                                                                $badgeClass = 'bg-red-100 text-red-800';
                                                            } elseif ($daysLeft <= 3) {
                                                                $badgeClass = 'bg-yellow-100 text-yellow-800';
                                                            }
                                                        ?>
                                                        <li class="flex justify-between items-center text-sm">
                                                            <span><?php echo htmlspecialchars($milestone['MilestoneTitle']); ?></span>
                                                            <span class="px-2 py-1 rounded <?php echo $badgeClass; ?>">
                                                                <?php 
                                                                    if ($daysLeft < 0) {
                                                                        echo 'Overdue: ' . abs($daysLeft) . ' days';
                                                                    } elseif ($daysLeft == 0) {
                                                                        echo 'Due today';
                                                                    } else {
                                                                        echo 'Due in ' . $daysLeft . ' days';
                                                                    }
                                                                ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($project['LatestSubmission'])): ?>
                                                <h4 class="font-medium text-sm text-gray-600 mb-2">Latest Submission</h4>
                                                <div class="bg-gray-50 p-3 rounded-lg text-sm">
                                                    <p><span class="font-medium">Type:</span> <?php echo htmlspecialchars($project['LatestSubmission']['SubmissionType']); ?></p>
                                                    <p><span class="font-medium">Milestone:</span> <?php echo htmlspecialchars($project['LatestSubmission']['MilestoneTitle']); ?></p>
                                                    <p><span class="font-medium">Date:</span> <?php echo date('M d, Y', strtotime($project['LatestSubmission']['SubmittedAt'])); ?></p>
                                                    <p>
                                                        <span class="font-medium">Status:</span>
                                                        <span class="
                                                            <?php
                                                                switch ($project['LatestSubmission']['ReviewStatus']) {
                                                                    case 'Accepted':
                                                                        echo 'text-green-600';
                                                                        break;
                                                                    case 'Rejected':
                                                                        echo 'text-red-600';
                                                                        break;
                                                                    default:
                                                                        echo 'text-yellow-600';
                                                                }
                                                            ?>
                                                        ">
                                                            <?php echo htmlspecialchars($project['LatestSubmission']['ReviewStatus']); ?>
                                                        </span>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-4 flex justify-end">
                                                <a href="view_project.php?id=<?php echo $project['ProjectID']; ?>" class="text-blue-500 hover:text-blue-700 text-sm font-medium">
                                                    View Details →
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                            <h2 class="text-xl font-bold text-white">Recent Activity</h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($projects) || empty($projects[0]['RecentHistory'])): ?>
                                <p class="text-gray-500 italic text-center">No recent activity.</p>
                            <?php else: ?>
                                <div class="relative">
                                    <div class="absolute left-4 top-0 h-full w-0.5 bg-gray-200"></div>
                                    <ul class="space-y-6">
                                        <?php foreach ($projects[0]['RecentHistory'] as $history): ?>
                                            <li class="relative pl-10">
                                                <div class="absolute left-0 top-1.5 h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center text-white">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($history['Action']); ?></p>
                                                    <p class="text-sm text-gray-500">
                                                        <?php echo date('M d, Y, h:i A', strtotime($history['ActionDate'])); ?> 
                                                        by <?php echo htmlspecialchars($history['UserName'] ?: $history['Username']); ?>
                                                    </p>
                                                    <?php if ($history['HistoryStatus'] === 'Late'): ?>
                                                        <p class="text-xs text-red-500">Late by <?php echo $history['DaysLate']; ?> days</p>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden h-full">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                            <h2 class="text-xl font-bold text-white">Recent Feedback</h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($recentFeedback)): ?>
                                <p class="text-gray-500 italic text-center">No recent feedback.</p>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($recentFeedback as $feedback): ?>
                                        <div class="border rounded-lg p-4">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <span class="font-medium"><?php echo htmlspecialchars($feedback['ProjectTitle']); ?></span>
                                                    <p class="text-xs text-gray-500">
                                                        By <?php echo htmlspecialchars($feedback['SenderName'] ?: $feedback['SenderUsername']); ?> 
                                                        on <?php echo date('M d, Y', strtotime($feedback['SentAt'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <p class="text-gray-700 text-sm"><?php echo nl2br(htmlspecialchars(substr($feedback['FeedbackText'], 0, 200) . (strlen($feedback['FeedbackText']) > 200 ? '...' : ''))); ?></p>
                                            <div class="mt-2 text-right">
                                                <a href="view_feedback.php?id=<?php echo $feedback['FeedbackID']; ?>" class="text-blue-500 hover:text-blue-700 text-xs font-medium">
                                                    Read More
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>