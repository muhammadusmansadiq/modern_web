<?php
include_once '../../includes/header.php';
include_once '../../config/db.php';


$error = "";
$success = "";

// Fetch user's projects and their latest history
$student_id  = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id <= 0) {
    die('User ID is required!');
}
try {
    // Fetch projects and their latest action
    $stmt = $pdo->prepare("
        SELECT 
            P.ProjectID,
            P.Title,
            P.Status,
            PH.Action AS LatestAction,
            PH.ActionDate AS LatestActionDate,
            PH.Status AS LatestHistoryStatus,
            PH.DaysLate AS LatestDaysLate
        FROM 
            Projects P
        LEFT JOIN 
            (SELECT 
                 ProjectID, 
                 Action, 
                 ActionDate, 
                 Status, 
                 DaysLate
             FROM 
                 (SELECT 
                      ProjectID, 
                      Action, 
                      ActionDate, 
                      Status, 
                      DaysLate,
                      ROW_NUMBER() OVER (PARTITION BY ProjectID ORDER BY ActionDate DESC) AS rn
                  FROM 
                      ProjectHistory) AS sub
             WHERE 
                 sub.rn = 1) PH ON P.ProjectID = PH.ProjectID
        WHERE 
            P.StudentID = :student_id
        ORDER BY 
            P.ProjectID
    ");
    $stmt->execute([':student_id' => $student_id]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if there are any projects fetched
    if (empty($projects)) {
        $error = "No projects found for this student.";
    }

    // Fetch all actions for each project
    $projectActions = [];
    foreach ($projects as $project) {
        $projectID = $project['ProjectID'];
        try {
            $stmtActions = $pdo->prepare("
                SELECT 
                    Action,
                    ActionDate,
                    Status AS HistoryStatus,
                    DaysLate
                FROM 
                    ProjectHistory
                WHERE 
                    ProjectID = :project_id
                ORDER BY 
                    ActionDate DESC
            ");
            $stmtActions->execute([':project_id' => $projectID]);
            $actions = $stmtActions->fetchAll(PDO::FETCH_ASSOC);
            $projectActions[$projectID] = $actions;
        } catch (PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }

    // Fetch all milestones for each project
    $projectMilestones = [];
    foreach ($projects as $project) {
        $projectID = $project['ProjectID'];
        try {
            $stmtMilestones = $pdo->prepare("
                SELECT 
                    DueDate
                FROM 
                    Milestones
                WHERE 
                    ProjectID = :project_id
                ORDER BY 
                    DueDate
            ");
            $stmtMilestones->execute([':project_id' => $projectID]);
            $milestones = $stmtMilestones->fetchAll(PDO::FETCH_ASSOC);
            $projectMilestones[$projectID] = $milestones;
        } catch (PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
} catch (PDOException $e) {
    $error = "An error occurred: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <main class="flex-grow">
            <div class="flex items-center justify-center mt-10">
                <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-4xl">
                    <h2 class="text-2xl font-bold mb-6">Project History Timeline</h2>
                    <?php if (!empty($error)) { ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php } ?>
                    <?php if (!empty($success)) { ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    <?php } ?>
                    <?php if (empty($projects)) { ?>
                        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">No project history found.</span>
                        </div>
                    <?php } else { ?>
                        <?php foreach ($projects as $project): ?>
                            <div class="mb-10">
                                <div class="bg-white p-4 rounded-lg shadow-md mb-4">
                                    <h3 class="text-lg font-bold mb-2"><?php echo htmlspecialchars($project['Title']); ?></h3>
                                    <p class="text-gray-700"><strong>Project ID:</strong> <?php echo htmlspecialchars($project['ProjectID']); ?></p>
                                    <p class="text-gray-700"><strong>Status:</strong> <?php echo htmlspecialchars($project['Status']); ?></p>
                              
                                </div>
                                <div class="relative">
                                    <div class="absolute left-1/2 transform -translate-x-1/2 w-0.5 h-full bg-gray-300"></div>
                                    <?php if (isset($projectActions[$project['ProjectID']])): ?>
                                        <?php foreach ($projectActions[$project['ProjectID']] as $action): ?>
                                            <div class="mb-8 flex items-center">
                                                <div class="w-full flex items-center justify-center">
                                                    <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div class="w-full pl-16">
                                                    <div class="bg-white p-4 rounded-lg shadow-md">
                                                        <p class="text-gray-700"><strong>Action:</strong> <?php echo htmlspecialchars($action['Action']); ?></p>
                                                        <p class="text-gray-700"><strong>Action Date:</strong> <?php echo htmlspecialchars($action['ActionDate']); ?></p>
                                                        <?php if ($action['HistoryStatus'] == 'Late'): ?>
                                                            <p class="text-red-500"><strong>Late by:</strong> <?php echo htmlspecialchars($action['DaysLate']); ?> days</p>
                                                        <?php elseif ($action['HistoryStatus'] == 'On Time'): ?>
                                                            <p class="text-green-500"><strong>Status:</strong> On Time</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php } ?>
                </div>
            </div>
        </main>
        <?php include '../../includes/footer.php'; ?>
    </div>
</body>
</html>