<?php
include_once '../../includes/header.php';
include_once '../../config/db.php';



// Check if the user is logged in and has the role of Supervisor (RoleID = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit();
}

// Get student_id and project_id from GET parameters
$studentID = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$projectID = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($studentID == 0 || $projectID == 0) {
    header("Location: dashboard.php");
    exit();
}

// Create a PDO instance
try {
  
    // Fetch the proposal details
    $proposalQuery = "
        SELECT 
            p.Title,
            p.Description,
            p.Objectives,
            pr.FirstName,
            pr.LastName,
            p.CreatedAt
        FROM 
            Projects p
        JOIN 
            Users u ON p.StudentID = u.UserID
        JOIN 
            Profile pr ON u.UserID = pr.UserID
        WHERE 
            p.ProjectID = :projectID
            AND p.StudentID = :studentID;
    ";
    $proposalStmt = $pdo->prepare($proposalQuery);
    $proposalStmt->execute([':projectID' => $projectID, ':studentID' => $studentID]);
    $proposal = $proposalStmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposal) {
        header("Location: dashboard.php");
        exit();
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
    <title>View Proposal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-3xl font-bold mb-4">View Proposal</h1>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold mb-4"><?= htmlspecialchars($proposal['Title']); ?></h2>
            <p class="text-gray-700 mb-4"><strong>Description:</strong> <?= nl2br(htmlspecialchars($proposal['Description'])); ?></p>
            <p class="text-gray-700 mb-4"><strong>Objectives:</strong> <?= nl2br(htmlspecialchars($proposal['Objectives'])); ?></p>
            <p class="text-gray-700 mb-4"><strong>Submitted by:</strong> <?= htmlspecialchars($proposal['FirstName'] . ' ' . $proposal['LastName']); ?></p>
            <p class="text-gray-700 mb-4"><strong>Submitted on:</strong> <?= htmlspecialchars($proposal['CreatedAt']); ?></p>
            <form class="w-full max-w-lg" method="POST" action="">
                <div class="flex flex-wrap -mx-3 mb-6">
                    <div class="w-full px-3">
                        <button class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="action" value="accept">
                            Accept
                        </button>
                        <button class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="action" value="reject">
                            Reject
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        try {
            $pdo->beginTransaction();

            // Update the project status
            $updateStatusQuery = "
                UPDATE Projects
                SET Status = :status
                WHERE ProjectID = :projectID
            ";
            $updateStatusStmt = $pdo->prepare($updateStatusQuery);
            $updateStatusStmt->execute([
                ':status' => $action == 'accept' ? 'Accepted' : 'Rejected',
                ':projectID' => $projectID
            ]);

            // Log the action in ProjectHistory
            $supervisorID = $_SESSION['user_id'];
            $actionText = $action == 'accept' ? 'Accepted' : 'Rejected';
            $insertHistoryQuery = "
                INSERT INTO ProjectHistory (ProjectID, Action, UserID)
                VALUES (:projectID, :action, :userID)
            ";
            $insertHistoryStmt = $pdo->prepare($insertHistoryQuery);
            $insertHistoryStmt->execute([
                ':projectID' => $projectID,
                ':action' => $actionText,
                ':userID' => $supervisorID
            ]);

            $pdo->commit();
            echo "<script>alert('Proposal " . htmlspecialchars($action) . " successfully!');</script>";
            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "Error updating proposal status: " . $e->getMessage();
        }
    }
    ?>
</body>
</html>