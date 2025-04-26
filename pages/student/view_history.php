<?php
// pages/student/view_history.php
// Buffer output to prevent "headers already sent" errors
ob_start();

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

// Get the student's group
try {
    $stmt = $pdo->prepare("
        SELECT 
            sg.GroupID,
            g.GroupName
        FROM 
            StudentGroups sg
        JOIN 
            Groups g ON sg.GroupID = g.GroupID
        WHERE 
            sg.StudentID = :studentID AND sg.Status = 'Active'
    ");
    $stmt->execute([':studentID' => $user_id]);
    $groupInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$groupInfo) {
        $error = "You are not assigned to any group. Please contact your supervisor to be added to a group.";
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Fetch all projects for this group
if (!empty($groupInfo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.ProjectID,
                p.Title,
                p.Description,
                p.Status
            FROM 
                Projects p
            WHERE 
                p.GroupID = :groupID
            ORDER BY 
                p.CreatedAt DESC
        ");
        $stmt->execute([':groupID' => $groupInfo['GroupID']]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each project, get history entries
        foreach ($projects as &$project) {
            $stmt = $pdo->prepare("
                SELECT 
                    ph.HistoryID,
                    ph.Action,
                    ph.ActionDate,
                    ph.Status AS HistoryStatus,
                    ph.DaysLate,
                    CONCAT(pr.FirstName, ' ', pr.LastName) AS UserName,
                    u.Username,
                    u.RoleID
                FROM 
                    ProjectHistory ph
                JOIN 
                    Users u ON ph.UserID = u.UserID
                LEFT JOIN 
                    Profile pr ON u.UserID = pr.UserID
                WHERE 
                    ph.ProjectID = :projectID
                ORDER BY 
                    ph.ActionDate DESC
            ");
            $stmt->execute([':projectID' => $project['ProjectID']]);
            $project['History'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Error fetching projects: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project History</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8 text-center text-gray-800">Project History</h1>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($groupInfo)): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                    <h2 class="text-xl font-bold text-white">Group: <?php echo htmlspecialchars($groupInfo['GroupName']); ?></h2>
                </div>
                
                <div class="p-6">
                    <?php if (empty($projects)): ?>
                        <div class="text-center py-8">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="text-xl font-semibold text-gray-500">No projects yet</h3>
                            <p class="text-gray-500 mt-2">Your group hasn't started any projects yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <div class="mb-8">
                                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                    <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($project['Title']); ?></h3>
                                    <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($project['Description']); ?></p>
                                    <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full
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
                                </div>
                                
                                <?php if (empty($project['History'])): ?>
                                    <p class="text-gray-500 italic text-center">No history entries for this project.</p>
                                <?php else: ?>
                                    <div class="relative pl-8">
                                        <div class="absolute left-4 top-0 h-full w-0.5 bg-gray-200"></div>
                                        <ul class="space-y-6">
                                            <?php foreach ($project['History'] as $history): ?>
                                                <li class="relative">
                                                    <div class="absolute -left-4 top-1.5 h-8 w-8 rounded-full 
                                                        <?php echo $history['RoleID'] == 2 ? 'bg-purple-500' : 'bg-blue-500'; ?> 
                                                        flex items-center justify-center text-white">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </div>
                                                    <div class="bg-white p-4 rounded-lg shadow-sm">
                                                        <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($history['Action']); ?></p>
                                                        <p class="text-sm text-gray-500">
                                                            <?php echo date('M d, Y, h:i A', strtotime($history['ActionDate'])); ?> 
                                                            by <?php echo htmlspecialchars($history['UserName'] ?: $history['Username']); ?>
                                                            <span class="text-xs ml-2 px-2 py-0.5 rounded-full 
                                                                <?php echo $history['RoleID'] == 2 ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                                <?php echo $history['RoleID'] == 2 ? 'Supervisor' : 'Student'; ?>
                                                            </span>
                                                        </p>
                                                        <?php if ($history['HistoryStatus'] === 'Late'): ?>
                                                            <p class="text-xs text-red-500 mt-1">Late by <?php echo $history['DaysLate']; ?> days</p>
                                                        <?php elseif ($history['HistoryStatus'] === 'On Time'): ?>
                                                            <p class="text-xs text-green-500 mt-1">Submitted on time</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <h2 class="text-2xl font-bold mb-4">You are not assigned to any group</h2>
                <p class="text-gray-600 mb-6">Please contact your supervisor to be added to a group in order to view project history.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
// Flush the output buffer
ob_end_flush();
?>