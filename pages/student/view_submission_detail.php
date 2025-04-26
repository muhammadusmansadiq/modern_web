<?php
// pages/student/view_submission_detail.php
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
$submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($submission_id <= 0) {
    header("Location: view_submissions.php");
    exit();
}

$error = "";
$success = "";

// Verify if the student has access to view this submission (part of the same group)
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.SubmissionID,
            s.SubmissionType,
            s.Version,
            s.SubmittedAt,
            s.Status AS SubmissionStatus,
            s.ReviewStatus,
            s.Remarks,
            s.ReviewedAt,
            s.ReviewedBy,
            p.ProjectID,
            p.Title AS ProjectTitle,
            p.GroupID,
            m.MilestoneTitle,
            m.DueDate,
            g.GroupName
        FROM 
            Submissions s
        JOIN 
            Projects p ON s.ProjectID = p.ProjectID
        JOIN 
            Milestones m ON s.MilestoneID = m.MilestoneID
        JOIN 
            Groups g ON p.GroupID = g.GroupID
        JOIN 
            StudentGroups sg ON g.GroupID = sg.GroupID
        WHERE 
            s.SubmissionID = :submissionID
            AND sg.StudentID = :studentID
            AND sg.Status = 'Active'
    ");
    $stmt->execute([
        ':submissionID' => $submission_id,
        ':studentID' => $user_id
    ]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission) {
        $error = "You do not have permission to view this submission or it doesn't exist.";
    } else {
        // Get files for this submission
        $stmt = $pdo->prepare("
            SELECT 
                f.FileID,
                f.FileName,
                f.FileType,
                f.FileSize,
                f.UploadedAt,
                f.UploadedBy,
                CONCAT(pr.FirstName, ' ', pr.LastName) AS UploaderName,
                u.Username AS UploaderUsername
            FROM 
                FileUploads f
            JOIN 
                Users u ON f.UploadedBy = u.UserID
            LEFT JOIN 
                Profile pr ON u.UserID = pr.UserID
            WHERE 
                f.SubmissionID = :submissionID
            ORDER BY 
                f.UploadedAt ASC
        ");
        $stmt->execute([':submissionID' => $submission_id]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If the submission was reviewed, get reviewer's info
        if ($submission['ReviewStatus'] !== 'Pending' && $submission['ReviewedBy']) {
            $stmt = $pdo->prepare("
                SELECT 
                    CONCAT(p.FirstName, ' ', p.LastName) AS ReviewerName,
                    u.Username AS ReviewerUsername
                FROM 
                    Users u
                LEFT JOIN 
                    Profile p ON u.UserID = p.UserID
                WHERE 
                    u.UserID = :reviewerID
            ");
            $stmt->execute([':reviewerID' => $submission['ReviewedBy']]);
            $reviewer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reviewer) {
                $submission['ReviewerName'] = $reviewer['ReviewerName'] ?: $reviewer['ReviewerUsername'];
            }
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Details</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-5xl mx-auto">
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
                
                <div class="mt-8 text-center">
                    <a href="view_submissions.php" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Back to Submissions
                    </a>
                </div>
            <?php elseif (!empty($submission)): ?>
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($submission['SubmissionType']); ?> Submission</h1>
                    <a href="view_submissions.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Submissions
                    </a>
                </div>
                
                <!-- Submission Overview -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h2 class="text-xl font-bold text-white">Submission Details</h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div>
                                <p class="text-sm text-gray-500">Project</p>
                                <p class="text-lg font-medium"><?php echo htmlspecialchars($submission['ProjectTitle']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Group</p>
                                <p class="text-lg font-medium"><?php echo htmlspecialchars($submission['GroupName']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Milestone</p>
                                <p class="text-lg font-medium"><?php echo htmlspecialchars($submission['MilestoneTitle']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Due Date</p>
                                <p class="text-lg font-medium"><?php echo date('F d, Y', strtotime($submission['DueDate'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Submission Date</p>
                                <p class="text-lg font-medium"><?php echo date('F d, Y, h:i A', strtotime($submission['SubmittedAt'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Version</p>
                                <p class="text-lg font-medium"><?php echo htmlspecialchars($submission['Version']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Status</p>
                                <p class="text-lg font-medium">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium
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
                                    <?php if ($submission['SubmissionStatus'] === 'Late'): ?>
                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                            Late
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php if ($submission['ReviewStatus'] !== 'Pending' && isset($submission['ReviewerName'])): ?>
                                <div>
                                    <p class="text-sm text-gray-500">Reviewed By</p>
                                    <p class="text-lg font-medium"><?php echo htmlspecialchars($submission['ReviewerName']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Reviewed On</p>
                                    <p class="text-lg font-medium"><?php echo date('F d, Y', strtotime($submission['ReviewedAt'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($submission['Remarks'])): ?>
                            <div class="mt-6">
                                <h3 class="text-lg font-medium mb-2">Comments</h3>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="whitespace-pre-line"><?php echo nl2br(htmlspecialchars($submission['Remarks'])); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Uploaded Files -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h2 class="text-xl font-bold text-white">Uploaded Files</h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($files)): ?>
                            <p class="text-gray-500 italic text-center">No files found for this submission.</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($files as $file): ?>
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
                                                $fileIcon = '<svg class="w-8 h-8 text-purple-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5.94 5.5c.944-.945 2.56-.276 5.074.248.41.086.82.183 1.226.298C10.868 4.251 9.022 3.911 7.6 5.333 6.9 6.033 6.5 7.633 6.5 7.633l-.74-.366C5.85 6.4 5.56 5.877 5.94 5.5zm8.25 3.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm1.138 9.992a7.998 7.998 0 01-3.333.938 8.025 8.025 0 01-6.047-2.672c-2.933-3.095-1.894-7.312-.694-9.054a.75.75 0 011.138.984c-.602.878-1.843 4.214.574 6.75a6.73 6.73 0 004.606 2.254 6.71 6.71 0 003.756-.752.75.75 0 01.9 1.202z" clip-rule="evenodd"></path></svg>';
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
                                        
                                        // Determine if current user is the uploader
                                        $isUploader = ($file['UploadedBy'] == $user_id);
                                    ?>
                                    <div class="flex items-center p-4 border rounded-lg <?php echo $isUploader ? 'bg-blue-50 border-blue-200' : 'bg-gray-50'; ?>">
                                        <div class="flex-shrink-0">
                                            <?php echo $fileIcon; ?>
                                        </div>
                                        <div class="ml-4 flex-grow">
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($file['FileName']); ?></p>
                                            <p class="text-sm text-gray-500"><?php echo $formattedSize; ?> • <?php echo date('M d, Y, h:i A', strtotime($file['UploadedAt'])); ?></p>
                                            <p class="text-sm text-gray-500">
                                                Uploaded by: <?php echo htmlspecialchars($file['UploaderName'] ?: $file['UploaderUsername']); ?>
                                                <?php if ($isUploader): ?>
                                                    <span class="ml-1 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">You</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <a href="../download_file.php?id=<?php echo $file['FileID']; ?>" class="ml-2 bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-2 px-4 rounded transition-colors duration-200">
                                            Download
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Supervisor Feedback (if reviewed) -->
                <?php if ($submission['ReviewStatus'] !== 'Pending'): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                        <div class="bg-gradient-to-r from-<?php echo $submission['ReviewStatus'] === 'Accepted' ? 'green' : 'red'; ?>-600 to-<?php echo $submission['ReviewStatus'] === 'Accepted' ? 'green' : 'red'; ?>-800 p-4">
                            <h2 class="text-xl font-bold text-white">Supervisor Feedback</h2>
                        </div>
                        <div class="p-6">
                            <?php if (!empty($submission['Remarks'])): ?>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <p class="whitespace-pre-line"><?php echo nl2br(htmlspecialchars($submission['Remarks'])); ?></p>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500 italic text-center">No feedback provided.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
// Flush the output buffer
ob_end_flush();
?>