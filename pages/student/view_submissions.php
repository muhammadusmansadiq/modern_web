<?php
// pages/supervisor/view_submission.php
include_once '../../includes/header.php';
include_once '../../config/db.php';

// // Check if the user is logged in and has the role of Supervisor (RoleID = 2)
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
//     header("Location: ../login.php");
//     exit();
// }

$supervisorID = $_SESSION['user_id'];
$studentID = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$projectID = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$error = "";
$success = "";

// Validate that the student and project belong to this supervisor
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.ProjectID,
            p.Title AS ProjectTitle,
            g.GroupID,
            g.GroupName
        FROM 
            Projects p
        JOIN 
            Groups g ON p.GroupID = g.GroupID
        WHERE 
            p.ProjectID = :projectID
            AND g.SupervisorID = :supervisorID
    ");
    $stmt->execute([':projectID' => $projectID, ':supervisorID' => $supervisorID]);
    $projectData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$projectData) {
        $error = "You do not have permission to view this project or the project does not exist.";
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle submission review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['accept', 'reject'])) {
    $submissionID = intval($_POST['submission_id']);
    $action = $_POST['action'];
    $feedback = trim($_POST['feedback']);
    
    if ($submissionID <= 0) {
        $error = "Invalid submission ID.";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update submission review status
            $reviewStatus = ($action === 'accept') ? 'Accepted' : 'Rejected';
            $stmt = $pdo->prepare("
                UPDATE Submissions 
                SET 
                    ReviewStatus = :reviewStatus,
                    Remarks = :remarks,
                    ReviewedBy = :reviewedBy,
                    ReviewedAt = NOW()
                WHERE 
                    SubmissionID = :submissionID
            ");
            $stmt->execute([
                ':reviewStatus' => $reviewStatus,
                ':remarks' => $feedback,
                ':reviewedBy' => $supervisorID,
                ':submissionID' => $submissionID
            ]);
            
            // Get the project ID for this submission
            $stmt = $pdo->prepare("SELECT ProjectID FROM Submissions WHERE SubmissionID = :submissionID");
            $stmt->execute([':submissionID' => $submissionID]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $submissionProjectID = $result['ProjectID'];
            
            // Add to project history
            $historyAction = "Submission " . $reviewStatus;
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
                    :userID
                )
            ");
            $stmt->execute([
                ':projectID' => $submissionProjectID,
                ':action' => $historyAction,
                ':userID' => $supervisorID
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Submission has been " . strtolower($reviewStatus) . " successfully.";
            
            // Optional: Send notification to student (could be implemented later)
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error updating submission: " . $e->getMessage();
        }
    }
}

// Fetch student's submissions
if (empty($error)) {
    try {
        // Get all submissions for this project
        $stmt = $pdo->prepare("
            SELECT 
                s.SubmissionID,
                s.SubmissionType,
                s.Version,
                s.SubmittedAt,
                s.Status,
                s.Remarks,
                s.ReviewStatus,
                s.ReviewedBy,
                s.ReviewedAt,
                m.MilestoneTitle,
                m.DueDate
            FROM 
                Submissions s
            JOIN 
                Milestones m ON s.MilestoneID = m.MilestoneID
            WHERE 
                s.ProjectID = :projectID
            ORDER BY 
                s.SubmittedAt DESC
        ");
        $stmt->execute([':projectID' => $projectID]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For each submission, get the files
        foreach ($submissions as &$submission) {
            $stmt = $pdo->prepare("
                SELECT 
                    f.FileID,
                    f.FileName,
                    f.FilePath,
                    f.FileType,
                    f.FileSize,
                    f.UploadedAt,
                    u.Username AS UploaderUsername,
                    CONCAT(p.FirstName, ' ', p.LastName) AS UploaderName
                FROM 
                    FileUploads f
                JOIN 
                    Users u ON f.UploadedBy = u.UserID
                LEFT JOIN 
                    Profile p ON u.UserID = p.UserID
                WHERE 
                    f.SubmissionID = :submissionID
                ORDER BY 
                    f.UploadedAt ASC
            ");
            $stmt->execute([':submissionID' => $submission['SubmissionID']]);
            $submission['Files'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If the submission was reviewed, get the reviewer's info
            if ($submission['ReviewedBy']) {
                $stmt = $pdo->prepare("
                    SELECT 
                        u.Username AS ReviewerUsername,
                        CONCAT(p.FirstName, ' ', p.LastName) AS ReviewerName
                    FROM 
                        Users u
                    LEFT JOIN 
                        Profile p ON u.UserID = p.UserID
                    WHERE 
                        u.UserID = :reviewerID
                ");
                $stmt->execute([':reviewerID' => $submission['ReviewedBy']]);
                $reviewer = $stmt->fetch(PDO::FETCH_ASSOC);
                $submission['ReviewerName'] = $reviewer['ReviewerName'] ?: $reviewer['ReviewerUsername'];
            }
        }
        
        // Get students in the group
        if (!empty($projectData['GroupID'])) {
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
                ORDER BY 
                    p.FirstName, p.LastName
            ");
            $stmt->execute([':groupID' => $projectData['GroupID']]);
            $groupStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $groupStudents = [];
        }
    } catch (PDOException $e) {
        $error = "Error fetching submissions: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submissions</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.10.5/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-6xl mx-auto">
            <h1 class="text-3xl font-bold mb-2 text-center text-gray-800">Project Submissions</h1>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php else: ?>
                <div class="mb-8 text-center">
                    <h2 class="text-xl font-medium"><?php echo htmlspecialchars($projectData['ProjectTitle']); ?></h2>
                    <p class="text-gray-600">Group: <?php echo htmlspecialchars($projectData['GroupName']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($error) && !empty($groupStudents)): ?>
                <div class="bg-white shadow-lg rounded-lg overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h2 class="text-xl font-bold text-white">Group Members</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($groupStudents as $student): ?>
                            <div class="flex items-center p-3 border rounded-lg">
                                <div class="flex-shrink-0 h-10 w-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr(($student['StudentName'] ?: $student['Username']), 0, 1)); ?>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($student['StudentName'] ?: $student['Username']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($student['Email']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($error) && empty($submissions)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Notice</p>
                    <p>No submissions found for this project.</p>
                </div>
            <?php elseif (empty($error)): ?>
                <div x-data="{ openTab: 0 }" class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h2 class="text-xl font-bold text-white">Submissions</h2>
                    </div>
                    
                    <!-- Tabs -->
                    <div class="border-b border-gray-200">
                        <ul class="flex -mb-px overflow-x-auto">
                            <?php foreach ($submissions as $index => $submission): ?>
                                <li class="mr-1">
                                    <button 
                                        @click="openTab = <?php echo $index; ?>" 
                                        :class="{ 'border-blue-500 text-blue-600': openTab === <?php echo $index; ?>, 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': openTab !== <?php echo $index; ?> }"
                                        class="py-4 px-6 font-medium border-b-2 focus:outline-none whitespace-nowrap"
                                    >
                                        <?php 
                                            $statusColor = '';
                                            if ($submission['ReviewStatus'] === 'Accepted') {
                                                $statusColor = 'text-green-500';
                                            } elseif ($submission['ReviewStatus'] === 'Rejected') {
                                                $statusColor = 'text-red-500';
                                            }
                                            
                                            echo htmlspecialchars($submission['SubmissionType']); 
                                            if ($statusColor) {
                                                echo " <span class=\"{$statusColor}\">&bull;</span>";
                                            }
                                        ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Tab Content -->
                    <div class="p-6">
                        <?php foreach ($submissions as $index => $submission): ?>
                            <div x-show="openTab === <?php echo $index; ?>">
                                <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Submission Type</p>
                                        <p class="text-lg font-semibold"><?php echo htmlspecialchars($submission['SubmissionType']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Milestone</p>
                                        <p class="text-lg font-semibold"><?php echo htmlspecialchars($submission['MilestoneTitle']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Submitted On</p>
                                        <p class="text-lg font-semibold"><?php echo date('M d, Y, h:i A', strtotime($submission['SubmittedAt'])); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Due Date</p>
                                        <p class="text-lg font-semibold">
                                            <?php echo date('M d, Y', strtotime($submission['DueDate'])); ?>
                                            <?php if ($submission['Status'] === 'Late'): ?>
                                                <span class="text-red-500 text-sm ml-2">Late Submission</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Version</p>
                                        <p class="text-lg font-semibold"><?php echo htmlspecialchars($submission['Version']); ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-500">Review Status</p>
                                        <p class="text-lg font-semibold 
                                            <?php 
                                                if ($submission['ReviewStatus'] === 'Accepted') echo 'text-green-600';
                                                elseif ($submission['ReviewStatus'] === 'Rejected') echo 'text-red-600';
                                                else echo 'text-yellow-600';
                                            ?>"
                                        >
                                            <?php echo htmlspecialchars($submission['ReviewStatus']); ?>
                                            <?php if (in_array($submission['ReviewStatus'], ['Accepted', 'Rejected'])): ?>
                                                <span class="text-gray-500 text-sm ml-2">
                                                    by <?php echo htmlspecialchars($submission['ReviewerName']); ?> 
                                                    on <?php echo date('M d, Y', strtotime($submission['ReviewedAt'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Student Comments -->
                                <?php if (!empty($submission['Remarks'])): ?>
                                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                                        <h3 class="font-semibold mb-2">Student Comments:</h3>
                                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($submission['Remarks'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Files -->
                                <h3 class="font-semibold text-lg mb-3">Submitted Files</h3>
                                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                                    <?php if (empty($submission['Files'])): ?>
                                        <p class="text-gray-500 italic">No files found for this submission.</p>
                                    <?php else: ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <?php foreach ($submission['Files'] as $file): ?>
                                                <?php
                                                    // Determine file icon based on file type
                                                    $fileExtension = pathinfo($file['FileName'], PATHINFO_EXTENSION);
                                                    $fileIcon = '';
                                                    
                                                    switch (strtolower($fileExtension)) {
                                                        case 'pdf':
                                                            $fileIcon = '<svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path><path d="M3 8a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path></svg>';
                                                            break;
                                                        case 'doc':
                                                        case 'docx':
                                                            $fileIcon = '<svg class="w-8 h-8 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>';
                                                            break;
                                                        case 'xls':
                                                        case 'xlsx':
                                                        case 'csv':
                                                            $fileIcon = '<svg class="w-8 h-8 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1h4a1 1 0 001-1v-1h-5v2zm0-4h5V8h-5v2zM9 8H4v2h5V8z" clip-rule="evenodd"></path></svg>';
                                                            break;
                                                        case 'zip':
                                                        case 'rar':
                                                        case '7z':
                                                            $fileIcon = '<svg class="w-8 h-8 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2h8v1H6V6zm0 3h8v1H6V9zm0 3h8v1H6v-1z" clip-rule="evenodd"></path></svg>';
                                                            break;
                                                        case 'sql':
                                                            $fileIcon = '<svg class="w-8 h-8 text-purple-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5.94 5.5c.944-.945 2.56-.276 5.074.248.41.086.82.183 1.226.298C10.868 4.251 9.022 3.911 7.6 5.333 6.9 6.033 6.5 7.633 6.5 7.633l-.74-.366C5.85 6.4 5.56 5.877 5.94 5.5zm8.25 3.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm1.138 9.992a7.998 7.998 0 01-3.333.938 8.025 8.025 0 01-6.047-2.672c-2.933-3.095-1.894-7.312-.694-9.054a.75.75 0 011.138.984c-.602.878-1.843 4.214.574 6.75a6.73 6.73 0.0 4.606 2.254 6.71 6.71 0 003.756-.752.75.75 0 01.9 1.202z" clip-rule="evenodd"></path></svg>';
                                                            break;
                                                        case 'jpg':
                                                        case 'jpeg':
                                                        case 'png':
                                                        case 'gif':
                                                            $fileIcon = '<svg class="w-8 h-8 text-pink-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path></svg>';
                                                            break;
                                                        default:
                                                            $fileIcon = '<svg class="w-8 h-8 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>';
                                                            break;
                                                    }
                                                    
                                                    // Format file size
                                                    $fileSize = $file['FileSize'];
                                                    if ($fileSize < 1024) {
                                                        $formattedSize = $fileSize . ' bytes';
                                                    } elseif ($fileSize < 1024 * 1024) {
                                                        $formattedSize = round($fileSize / 1024, 1) . ' KB';
                                                    } else {
                                                        $formattedSize = round($fileSize / (1024 * 1024), 1) . ' MB';
                                                    }
                                                ?>
                                                <div class="flex items-center p-3 border rounded-md">
                                                    <div class="flex-shrink-0">
                                                        <?php echo $fileIcon; ?>
                                                    </div>
                                                    <div class="ml-3 flex-grow">
                                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($file['FileName']); ?></p>
                                                        <p class="text-xs text-gray-500"><?php echo $formattedSize; ?> • Uploaded <?php echo date('M d, Y, h:i A', strtotime($file['UploadedAt'])); ?></p>
                                                        <p class="text-xs text-gray-500">By <?php echo htmlspecialchars($file['UploaderName'] ?: $file['UploaderUsername']); ?></p>
                                                    </div>
                                                    <a href="download_file.php?id=<?php echo $file['FileID']; ?>" class="ml-3 bg-blue-100 hover:bg-blue-200 text-blue-700 text-xs font-semibold py-1 px-3 rounded-full transition duration-200">
                                                        Download
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Review Section (Only show if not already reviewed) -->
                                <?php if ($submission['ReviewStatus'] === 'Pending'): ?>
                                    <div x-data="{ showAcceptForm: false, showRejectForm: false }">
                                        <h3 class="font-semibold text-lg mb-3">Review Submission</h3>
                                        <div class="flex space-x-4 mb-4">
                                            <button 
                                                @click="showAcceptForm = true; showRejectForm = false"
                                                class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200"
                                            >
                                                Accept Submission
                                            </button>
                                            <button 
                                                @click="showRejectForm = true; showAcceptForm = false"
                                                class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200"
                                            >
                                                Reject Submission
                                            </button>
                                        </div>
                                        
                                        <!-- Accept Form -->
                                        <div x-show="showAcceptForm" class="bg-gray-50 rounded-lg p-4 mb-4">
                                            <form method="POST" class="space-y-4">
                                                <input type="hidden" name="submission_id" value="<?php echo $submission['SubmissionID']; ?>">
                                                <input type="hidden" name="action" value="accept">
                                                
                                                <div>
                                                    <label for="accept-feedback" class="block text-sm font-medium text-gray-700 mb-1">Feedback for Students</label>
                                                    <textarea 
                                                        id="accept-feedback" 
                                                        name="feedback" 
                                                        rows="4" 
                                                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500"
                                                        placeholder="Provide feedback about this submission..."
                                                        required
                                                    ></textarea>
                                                </div>
                                                
                                                <div class="flex justify-between">
                                                    <button 
                                                        type="button" 
                                                        @click="showAcceptForm = false"
                                                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200"
                                                    >
                                                        Cancel
                                                    </button>
                                                    <button 
                                                        type="submit"
                                                        class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200"
                                                    >
                                                        Confirm Acceptance
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                        
                                        <!-- Reject Form -->
                                        <div x-show="showRejectForm" class="bg-gray-50 rounded-lg p-4 mb-4">
                                            <form method="POST" class="space-y-4">
                                                <input type="hidden" name="submission_id" value="<?php echo $submission['SubmissionID']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                
                                                <div>
                                                    <label for="reject-feedback" class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason</label>
                                                    <textarea 
                                                        id="reject-feedback" 
                                                        name="feedback" 
                                                        rows="4" 
                                                        class="w-full border-gray-300 rounded-md shadow-sm focus:ring-red-500 focus:border-red-500"
                                                        placeholder="Explain why this submission is being rejected..."
                                                        required
                                                    ></textarea>
                                                </div>
                                                
                                                <div class="flex justify-between">
                                                    <button 
                                                        type="button" 
                                                        @click="showRejectForm = false"
                                                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200"
                                                    >
                                                        Cancel
                                                    </button>
                                                    <button 
                                                        type="submit"
                                                        class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg shadow-sm transition duration-200"
                                                    >
                                                        Confirm Rejection
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Review Feedback -->
                                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                        <h3 class="font-semibold text-lg mb-3">
                                            Feedback
                                            <span class="ml-2 text-sm font-normal text-gray-500">
                                                (Reviewed on <?php echo date('M d, Y', strtotime($submission['ReviewedAt'])); ?>)
                                            </span>
                                        </h3>
                                        <div class="p-4 rounded-md <?php echo $submission['ReviewStatus'] === 'Accepted' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'; ?>">
                                            <p class="text-sm font-medium mb-2"><?php echo $submission['ReviewStatus'] === 'Accepted' ? 'Acceptance Feedback:' : 'Rejection Reason:'; ?></p>
                                            <p><?php echo nl2br(htmlspecialchars($submission['Remarks'])); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Download File Handler Script -->
    <script>
        function downloadFile(fileId, fileName) {
            const link = document.createElement('a');
            link.href = 'download_file.php?id=' + fileId;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>