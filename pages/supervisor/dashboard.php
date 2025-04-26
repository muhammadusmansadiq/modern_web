<?php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// Check if the user is logged in and has the role of Supervisor (RoleID = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit();
}

try {
    // Fetch projects assigned to the current supervisor
    $supervisorID = $_SESSION['user_id'];
    $projectsQuery = "
        SELECT 
            p.ProjectID,
            p.StudentID,
            p.Status
        FROM 
            Projects p
        WHERE 
            p.SupervisorID = :supervisorID;
    ";
    $projectsStmt = $pdo->prepare($projectsQuery);
    $projectsStmt->execute([':supervisorID' => $supervisorID]);
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Extract student IDs from the projects
    $studentIDs = array_map(function($project) {
        return $project['StudentID'];
    }, $projects);

    if (!empty($studentIDs)) {
        // Fetch students with their current status who are assigned to the current supervisor
        $placeholders = implode(',', array_fill(0, count($studentIDs), '?'));
        $studentsQuery = "
            SELECT 
                u.UserID,
                pr.FirstName,
                pr.LastName,
                u.Email,
                d.DepartmentName,
                us.StatusName
            FROM 
                Users u
            JOIN 
                Profile pr ON u.UserID = pr.UserID
            JOIN 
                Departments d ON u.DepartmentID = d.DepartmentID
            JOIN 
                UserStatus us ON u.StatusID = us.StatusID
            WHERE 
                u.RoleID = 3 
                AND u.UserID IN ($placeholders)
        ";
        $studentsStmt = $pdo->prepare($studentsQuery);
        $studentsStmt->execute($studentIDs);
        $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $students = [];
    }
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-3xl font-bold mb-4">Student Status</h1>
        <table class="w-full border-collapse bg-white text-left text-sm">
            <thead>
                <tr>
                    <th class="border-b dark:border-slate-600 p-4">Name</th>
                    <th class="border-b dark:border-slate-600 p-4">Email</th>
                    <th class="border-b dark:border-slate-600 p-4">Department</th>
                    <th class="border-b dark:border-slate-600 p-4">Status</th>
                    <th class="border-b dark:border-slate-600 p-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td class="border-b border-slate-100 dark:border-slate-700 p-4 text-center" colspan="5">No students found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="border-b border-slate-100 dark:border-slate-700 p-4"><?= htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']); ?></td>
                            <td class="border-b border-slate-100 dark:border-slate-700 p-4"><?= htmlspecialchars($student['Email']); ?></td>
                            <td class="border-b border-slate-100 dark:border-slate-700 p-4"><?= htmlspecialchars($student['DepartmentName']); ?></td>
                            <td class="border-b border-slate-100 dark:border-slate-700 p-4"><?= htmlspecialchars($student['StatusName']); ?></td>
                            <td class="border-b border-slate-100 dark:border-slate-700 p-4">
                                <?php
                                // Find the project status for the current student
                                $projectStatus = null;
                                $projectID = null;
                                foreach ($projects as $project) {
                                    if ($project['StudentID'] == $student['UserID']) {
                                        $projectStatus = $project['Status'];
                                        $projectID = $project['ProjectID'];
                                        break;
                                    }
                                }

                                if ($projectStatus == 'Proposal Submitted') {
                                    echo '<a href="view_proposal.php?student_id=' . htmlspecialchars($student['UserID']) . '&project_id=' . htmlspecialchars($projectID) . '" class="text-blue-500 hover:underline">View Proposal</a>';
                                } else {
                                    echo '<a href="create_milestone.php?student_id=' . htmlspecialchars($student['UserID']) . '&project_id=' . htmlspecialchars($projectID) . '" class="text-blue-500 hover:underline">Create Milestone</a>';
                                    echo ' | ';
                                    echo '<a href="view_history.php?student_id=' . htmlspecialchars($student['UserID']) . '&project_id=' . htmlspecialchars($projectID) . '" class="text-blue-500 hover:underline">View History</a>';
                                    echo ' | ';
                                    echo '<a href="view_feedback.php?student_id=' . htmlspecialchars($student['UserID']) . '&project_id=' . htmlspecialchars($projectID) . '" class="text-blue-500 hover:underline">View Feedback</a>';
                                    echo ' | ';
                                    echo '<a href="view_submission.php?student_id=' . htmlspecialchars($student['UserID']) . '&project_id=' . htmlspecialchars($projectID) . '" class="text-blue-500 hover:underline">View Submissions</a>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>