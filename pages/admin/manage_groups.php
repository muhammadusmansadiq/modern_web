<?php
// pages/admin/manage_groups.php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

$error = "";
$success = "";

// Handle group status change (activate/deactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_group') {
    $groupID = intval($_POST['group_id']);
    $newStatus = $_POST['status'] === 'activate' ? 'Active' : 'Inactive';
    
    try {
        $stmt = $pdo->prepare("UPDATE Groups SET Status = :status WHERE GroupID = :groupID");
        $stmt->execute([
            ':status' => $newStatus,
            ':groupID' => $groupID
        ]);
        
        $success = "Group status updated successfully.";
        // Refresh the page to reflect changes
        header("Location: manage_groups.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating group status: " . $e->getMessage();
    }
}

// Handle student removal from group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_student') {
    $studentGroupID = intval($_POST['student_group_id']);
    
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

// Handle supervisor change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_supervisor') {
    $groupID = intval($_POST['group_id']);
    $newSupervisorID = intval($_POST['supervisor_id']);
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get current supervisor
        $stmt = $pdo->prepare("SELECT SupervisorID FROM Groups WHERE GroupID = :groupID");
        $stmt->execute([':groupID' => $groupID]);
        $currentSupervisor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($currentSupervisor) {
            // Decrease group count for previous supervisor
            $stmt = $pdo->prepare("UPDATE Users SET GroupCount = GroupCount - 1 WHERE UserID = :userID AND GroupCount > 0");
            $stmt->execute([':userID' => $currentSupervisor['SupervisorID']]);
            
            // Increase group count for new supervisor
            $stmt = $pdo->prepare("UPDATE Users SET GroupCount = GroupCount + 1 WHERE UserID = :userID");
            $stmt->execute([':userID' => $newSupervisorID]);
            
            // Update the group's supervisor
            $stmt = $pdo->prepare("UPDATE Groups SET SupervisorID = :supervisorID WHERE GroupID = :groupID");
            $stmt->execute([
                ':supervisorID' => $newSupervisorID,
                ':groupID' => $groupID
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Supervisor changed successfully.";
            // Refresh the page to reflect changes
            header("Location: manage_groups.php");
            exit();
        } else {
            $pdo->rollBack();
            $error = "Group not found.";
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error = "Error changing supervisor: " . $e->getMessage();
    }
}

// Fetch all groups with their supervisors and student counts
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
            u.Email AS SupervisorEmail,
            COUNT(sg.StudentID) AS StudentCount
        FROM 
            Groups g
        JOIN 
            Users u ON g.SupervisorID = u.UserID
        LEFT JOIN 
            Profile p ON u.UserID = p.UserID
        LEFT JOIN 
            StudentGroups sg ON g.GroupID = sg.GroupID
        GROUP BY 
            g.GroupID
        ORDER BY 
            g.CreatedAt DESC
    ");
    $stmt->execute();
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching groups: " . $e->getMessage();
}

// Fetch all available supervisors
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.UserID, 
            u.Username, 
            u.Email, 
            CONCAT(p.FirstName, ' ', p.LastName) AS SupervisorName,
            u.GroupCount
        FROM 
            Users u
        LEFT JOIN 
            Profile p ON u.UserID = p.UserID
        WHERE 
            u.RoleID = 2 AND u.StatusID = 5
        ORDER BY 
            p.FirstName, p.LastName
    ");
    $stmt->execute();
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching supervisors: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Groups</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.10.5/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4" x-data="{ showChangeSupervisorModal: false, selectedGroupId: null }">
        <h1 class="text-3xl font-bold mb-8 text-center">Admin - Manage Groups</h1>
        
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
        
        <!-- Groups List -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                <h2 class="text-xl font-bold text-white">All Groups</h2>
            </div>
            <div class="p-6">
                <?php if (empty($groups)): ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500 text-xl">No groups created yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-100 text-gray-700">
                                    <th class="py-3 px-4 text-left">Group Name</th>
                                    <th class="py-3 px-4 text-left">Supervisor</th>
                                    <th class="py-3 px-4 text-left">Students</th>
                                    <th class="py-3 px-4 text-left">Status</th>
                                    <th class="py-3 px-4 text-left">Created</th>
                                    <th class="py-3 px-4 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groups as $group): ?>
                                    <tr class="border-t hover:bg-gray-50">
                                        <td class="py-3 px-4">
                                            <div class="font-medium"><?php echo htmlspecialchars($group['GroupName']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo mb_substr(htmlspecialchars($group['Description'] ?: 'No description'), 0, 50) . (mb_strlen($group['Description']) > 50 ? '...' : ''); ?></div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="font-medium"><?php echo htmlspecialchars($group['SupervisorName'] ?: $group['SupervisorUsername']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($group['SupervisorEmail']); ?></div>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="bg-blue-100 text-blue-800 font-medium px-2.5 py-0.5 rounded-full"><?php echo $group['StudentCount']; ?></span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                                <?php echo $group['Status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?php echo htmlspecialchars($group['Status'] ?: 'Active'); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-sm">
                                            <?php echo date('M d, Y', strtotime($group['CreatedAt'])); ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex space-x-2">
                                                <a href="view_group.php?id=<?php echo $group['GroupID']; ?>" class="text-blue-500 hover:text-blue-700">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </a>
                                                <button 
                                                    @click="selectedGroupId = <?php echo $group['GroupID']; ?>; showChangeSupervisorModal = true" 
                                                    class="text-yellow-500 hover:text-yellow-700"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                    </svg>
                                                </button>
                                                <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to <?php echo $group['Status'] === 'Active' ? 'deactivate' : 'activate'; ?> this group?');">
                                                    <input type="hidden" name="action" value="toggle_group">
                                                    <input type="hidden" name="group_id" value="<?php echo $group['GroupID']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $group['Status'] === 'Active' ? 'deactivate' : 'activate'; ?>">
                                                    <button type="submit" class="<?php echo $group['Status'] === 'Active' ? 'text-red-500 hover:text-red-700' : 'text-green-500 hover:text-green-700'; ?>">
                                                        <?php if ($group['Status'] === 'Active'): ?>
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                                                            </svg>
                                                        <?php else: ?>
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                            </svg>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Supervisor Overview -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-gradient-to-r from-purple-600 to-purple-800 p-4">
                <h2 class="text-xl font-bold text-white">Supervisor Overview</h2>
            </div>
            <div class="p-6">
                <?php if (empty($supervisors)): ?>
                    <div class="text-center py-8">
                        <p class="text-gray-500 text-xl">No supervisors available.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($supervisors as $supervisor): ?>
                            <div class="border rounded-lg overflow-hidden shadow-sm">
                                <div class="bg-gradient-to-r from-gray-100 to-gray-200 p-4 border-b">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-12 w-12 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                                            <?php echo strtoupper(substr(($supervisor['SupervisorName'] ?: $supervisor['Username']), 0, 1)); ?>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($supervisor['SupervisorName'] ?: $supervisor['Username']); ?></h3>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($supervisor['Email']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Group Count:</span>
                                        <span class="px-2.5 py-0.5 rounded-full text-sm font-medium
                                            <?php 
                                                if ($supervisor['GroupCount'] >= 3) {
                                                    echo 'bg-red-100 text-red-800';
                                                } elseif ($supervisor['GroupCount'] >= 2) {
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                } else {
                                                    echo 'bg-green-100 text-green-800';
                                                }
                                            ?>">
                                            <?php echo $supervisor['GroupCount']; ?>/3
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Change Supervisor Modal -->
        <div 
            x-show="showChangeSupervisorModal" 
            @click.away="showChangeSupervisorModal = false" 
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
                <h3 class="text-2xl font-bold mb-4">Change Supervisor</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_supervisor">
                    <input type="hidden" name="group_id" x-bind:value="selectedGroupId">
                    <div class="mb-6">
                        <label for="supervisor_id" class="block text-gray-700 font-bold mb-2">Select New Supervisor</label>
                        <select id="supervisor_id" name="supervisor_id" required 
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">Select a supervisor...</option>
                            <?php foreach ($supervisors as $supervisor): ?>
                                <?php if ($supervisor['GroupCount'] < 3): ?>
                                    <option value="<?php echo $supervisor['UserID']; ?>">
                                        <?php echo htmlspecialchars($supervisor['SupervisorName'] ?: $supervisor['Username']); ?> - 
                                        (<?php echo $supervisor['GroupCount']; ?>/3 groups)
                                    </option>
                                <?php else: ?>
                                    <option value="<?php echo $supervisor['UserID']; ?>" disabled>
                                        <?php echo htmlspecialchars($supervisor['SupervisorName'] ?: $supervisor['Username']); ?> - 
                                        (Max groups reached)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" @click="showChangeSupervisorModal = false" 
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2">
                            Cancel
                        </button>
                        <button type="submit" 
                            class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">
                            Change Supervisor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>