<?php

include_once '../../includes/header.php';
include_once '../../config/db.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<div class='mt-10 text-center text-red-500'>You are not logged in</div>";
    exit();
}

$senderID = intval($_SESSION['user_id']);
$receiverID = isset($_GET['supervisor_id']) ? intval($_GET['supervisor_id']) : 0;
$projectID = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;


try {
  
    if ($receiverID == 0 || $projectID == 0) {
        // Query to get the latest project for the student
        $stmt = $pdo->prepare("
            SELECT ProjectID, SupervisorID
            FROM Projects
            WHERE StudentID = :studentID
            ORDER BY UpdatedAt DESC
            LIMIT 1
        ");
        $stmt->execute(['studentID' => $senderID]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($project) {
            $receiverID = $project['SupervisorID'];
            $projectID = $project['ProjectID'];
        } else {
            // Handle the case where no project is found for the student
            echo "No project found for the student.";
            exit;
        }
    }



} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}


// if ($senderID <= 0 || $receiverID <= 0 || $projectID <= 0) {
//     echo "<div class='mt-10 text-center text-red-500'>Invalid sender ID, receiver ID, or project ID</div>";
//     exit();
// }

// Fetch feedback data
try {
    $stmt = $pdo->prepare("
        SELECT 
            FeedbackID, SenderID, ReceiverID, FeedbackText, FeedbackFilePath, SentAt
        FROM 
            Feedback
        WHERE 
            ProjectID = :projectID
           
        ORDER BY 
            SentAt ASC
    ");

    if ($stmt) {
        $stmt->execute([
            'projectID' => $projectID
            
        ]);

        $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
    } else {
        echo "<div class='mt-10 text-center text-red-500'>Error fetching feedback</div>";
        exit();
    }
} catch (PDOException $e) {
    echo "<div class='mt-10 text-center text-red-500'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit();
}

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $feedbackText = $_POST['feedback_text'];
    $feedbackFilePath = '';

    if (!empty($_FILES['feedback_file']['name'])) {
        $targetDir = "../../uploads/{$senderID}/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $targetFile = $targetDir . basename($_FILES["feedback_file"]["name"]);
        if (move_uploaded_file($_FILES["feedback_file"]["tmp_name"], $targetFile)) {
            $feedbackFilePath = $targetFile;
        } else {
            echo "<div class='mt-10 text-center text-red-500'>Error uploading file</div>";
            exit();
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO Feedback (ProjectID, SenderID, ReceiverID, FeedbackText, FeedbackFilePath, SentAt)
            VALUES (:projectID, :senderID, :receiverID, :feedbackText, :feedbackFilePath, NOW())
        ");
        if ($stmt) {
            $stmt->execute([
                'projectID' => $projectID,
                'senderID' => $senderID,
                'receiverID' => $receiverID,
                'feedbackText' => $feedbackText,
                'feedbackFilePath' => $feedbackFilePath
            ]);
            $stmt->closeCursor();
            // header("Location: view_feedback.php?supervisor_id=$receiverID&project_id=$projectID");
            echo "<meta http-equiv='refresh' content='0;url=view_feedback.php?student_id=$receiverID&project_id=$projectID'>";

            exit();
        } else {
            echo "<div class='mt-10 text-center text-red-500'>Error submitting feedback</div>";
            exit();
        }
    } catch (PDOException $e) {
        echo "<div class='mt-10 text-center text-red-500'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-3xl font-bold mb-4">Feedback for Project #<?php echo htmlspecialchars($projectID); ?></h1>
        
        <!-- Feedback Chat List -->
        <div class="mb-8">
            <?php if (empty($feedbacks)): ?>
                <div class="text-center text-gray-500">No feedback yet.</div>
            <?php else: ?>
                <div class="flex flex-col space-y-4">
                    <?php foreach ($feedbacks as $feedback): ?>
                        <?php
                        $isSender = $feedback['SenderID'] == $senderID;
                        ?>
                        <div class="flex <?php echo $isSender ? 'justify-end' : 'justify-start'; ?>">
                            <div class="max-w-md p-4 rounded-lg <?php echo $isSender ? 'bg-blue-100' : 'bg-white'; ?> shadow-md">
                                <div class="flex justify-between items-center mb-2">
                                    <div>
                                        <span class="text-gray-600">
                                            <?php echo $isSender ? 'You' : 'Recipient'; ?>
                                        </span>
                                        <span class="text-gray-400 ml-2">Sent at: <?php echo htmlspecialchars($feedback['SentAt']); ?></span>
                                    </div>
                                    <div>
                                        <?php if ($feedback['FeedbackFilePath']): ?>
                                            <a href="<?php echo htmlspecialchars($feedback['FeedbackFilePath']);  ?>" target="_blank"  class="text-blue-500 ml-2">Download File</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-gray-800">
                                    <?php echo nl2br(htmlspecialchars($feedback['FeedbackText'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Feedback Form -->
        <h2 class="text-2xl font-bold mb-4">Submit Feedback</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="feedback_text" class="block text-gray-700 font-bold mb-2">Feedback Text</label>
                <textarea id="feedback_text" name="feedback_text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" rows="5" required></textarea>
            </div>
            <div class="mb-4">
                <label for="feedback_file" class="block text-gray-700 font-bold mb-2">Attach File (optional)</label>
                <input type="file" id="feedback_file" name="feedback_file" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" name="submit_feedback" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Submit Feedback
                </button>
            </div>
        </form>
    </div>
</body>
</html>