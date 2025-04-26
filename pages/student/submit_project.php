<?php
// pages/student/submit_project.php
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
            g.SupervisorID,
            u.Username AS SupervisorName
        FROM 
            StudentGroups sg
        JOIN 
            Groups g ON sg.GroupID = g.GroupID
        JOIN 
            Users u ON g.SupervisorID = u.UserID
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

// Fetch active milestones for the student's group using PDO
if (!empty($groupInfo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                M.MilestoneID, 
                M.MilestoneTitle, 
                M.DueDate, 
                M.Status, 
                P.ProjectID, 
                P.Title 
            FROM 
                Milestones M 
            JOIN 
                Projects P ON M.ProjectID = P.ProjectID 
            WHERE 
                P.GroupID = :groupID AND M.Status = 'Pending'
        ");
        $stmt->execute([':groupID' => $groupInfo['GroupID']]);
        $milestones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching milestones: " . $e->getMessage();
    }
}

// Process the submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $milestone_id = $_POST['milestone'];
    $submission_type = $_POST['submission_type'];
    $comment = $_POST['comment'] ?? '';
    $files = $_FILES['files'];

    // Validate required fields
    if (empty($milestone_id) || empty($submission_type) || empty($files['name'][0])) {
        $error = "All fields are required and at least one file must be uploaded.";
    } else {
        try {
            // Get project ID and milestone title from the milestone
            $stmt = $pdo->prepare("
                SELECT M.ProjectID, M.MilestoneTitle, M.DueDate 
                FROM Milestones M 
                WHERE M.MilestoneID = :milestone_id
            ");
            $stmt->execute([':milestone_id' => $milestone_id]);
            $milestoneData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$milestoneData) {
                throw new Exception("Milestone not found");
            }
            
            $project_id = $milestoneData['ProjectID'];
            $milestone_title = $milestoneData['MilestoneTitle'];
            $due_date = $milestoneData['DueDate'];
            
            // Determine if the submission is on time or late
            $current_date = new DateTime();
            $due_date_obj = new DateTime($due_date);
            $interval = $current_date->diff($due_date_obj);
            $days_late = $current_date > $due_date_obj ? $interval->days : 0;
            $status = $days_late > 0 ? 'Late' : 'On Time';
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert submission into the database
            $stmt = $pdo->prepare("
                INSERT INTO Submissions (
                    ProjectID, 
                    SubmissionType, 
                    Version, 
                    SubmittedAt, 
                    MilestoneID, 
                    Remarks, 
                    Status, 
                    ReviewStatus
                ) VALUES (
                    :project_id, 
                    :submission_type, 
                    1, 
                    NOW(), 
                    :milestone_id, 
                    :remarks, 
                    :status,
                    'Pending'
                )
            ");
            
            $stmt->execute([
                ':project_id' => $project_id,
                ':submission_type' => $submission_type,
                ':milestone_id' => $milestone_id,
                ':remarks' => $comment,
                ':status' => $status
            ]);
            
            $submission_id = $pdo->lastInsertId();
            
            // Process each uploaded file
            $uploadDir = '../../uploads/submissions/' . $user_id . '/' . $submission_id . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileCount = count($files['name']);
            $uploadedFiles = [];
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $fileName = $files['name'][$i];
                    $fileSize = $files['size'][$i];
                    $fileTmpName = $files['tmp_name'][$i];
                    $fileType = $files['type'][$i];
                    
                    // Generate a unique filename to prevent overwriting
                    $uniqueName = uniqid() . '_' . $fileName;
                    $filePath = $uploadDir . $uniqueName;
                    
                    // Move the uploaded file
                    if (move_uploaded_file($fileTmpName, $filePath)) {
                        // Insert file information into FileUploads table
                        $stmt = $pdo->prepare("
                            INSERT INTO FileUploads (
                                FileName, 
                                FilePath, 
                                FileType, 
                                FileSize, 
                                UploadedBy, 
                                SubmissionID, 
                                UploadedAt
                            ) VALUES (
                                :file_name, 
                                :file_path, 
                                :file_type, 
                                :file_size, 
                                :uploaded_by, 
                                :submission_id, 
                                NOW()
                            )
                        ");
                        
                        $stmt->execute([
                            ':file_name' => $fileName,
                            ':file_path' => $filePath,
                            ':file_type' => $fileType,
                            ':file_size' => $fileSize,
                            ':uploaded_by' => $user_id,
                            ':submission_id' => $submission_id
                        ]);
                        
                        $uploadedFiles[] = $fileName;
                    } else {
                        throw new Exception("Failed to upload file: " . $fileName);
                    }
                } else {
                    throw new Exception("Error uploading file: " . $files['name'][$i] . " (Error code: " . $files['error'][$i] . ")");
                }
            }
            
            // Update Milestones table to mark status as 'Completed'
            $stmt = $pdo->prepare("UPDATE Milestones SET Status = 'Completed' WHERE MilestoneID = :milestone_id");
            $stmt->execute([':milestone_id' => $milestone_id]);
            
            // Insert into ProjectHistory table
            $action = "Submitted " . $submission_type . " for " . $milestone_title;
            $stmt = $pdo->prepare("
                INSERT INTO ProjectHistory (
                    ProjectID, 
                    Action, 
                    ActionDate, 
                    UserID, 
                    Status, 
                    DaysLate
                ) VALUES (
                    :project_id, 
                    :action, 
                    NOW(), 
                    :user_id, 
                    :status, 
                    :days_late
                )
            ");
            
            $stmt->execute([
                ':project_id' => $project_id,
                ':action' => $action,
                ':user_id' => $user_id,
                ':status' => $status,
                ':days_late' => $days_late
            ]);
            
            // Commit the transaction
            $pdo->commit();
            
            $success = "Submission successful! Uploaded " . count($uploadedFiles) . " file(s).";
            
            // Optionally redirect
            // header("Location: view_submission.php?id=" . $submission_id);
            // exit();
        } catch (Exception $e) {
            // Roll back the transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error processing submission: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Project</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold mb-8 text-center text-gray-800">Submit Project Files</h1>
            
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
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Notice</p>
                    <p>You are not assigned to any group. Please contact your supervisor to be added to a group.</p>
                </div>
            <?php elseif (empty($milestones)): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Notice</p>
                    <p>No active milestones found for your group. Please contact your supervisor.</p>
                </div>
            <?php else: ?>
                <div class="bg-white shadow-lg rounded-lg overflow-hidden mb-6">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h2 class="text-xl font-bold text-white">Group Information</h2>
                    </div>
                    <div class="p-6">
                        <p class="mb-2"><span class="font-semibold">Group Name:</span> <?php echo htmlspecialchars($groupInfo['GroupName']); ?></p>
                        <p><span class="font-semibold">Supervisor:</span> <?php echo htmlspecialchars($groupInfo['SupervisorName']); ?></p>
                    </div>
                </div>
                
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-800 p-4">
                        <h2 class="text-xl font-bold text-white">Submit Files</h2>
                    </div>
                    <div class="p-6">
                        <form action="submit_project.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                            <div>
                                <label for="milestone" class="block text-sm font-medium text-gray-700 mb-1">Select Milestone</label>
                                <select name="milestone" id="milestone" required class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select a milestone...</option>
                                    <?php foreach ($milestones as $milestone): ?>
                                        <option value="<?php echo $milestone['MilestoneID']; ?>">
                                            <?php echo htmlspecialchars($milestone['MilestoneTitle']); ?> - 
                                            <?php echo htmlspecialchars($milestone['Title']); ?> 
                                            (Due: <?php echo date('M d, Y', strtotime($milestone['DueDate'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="submission_type" class="block text-sm font-medium text-gray-700 mb-1">Submission Type</label>
                                <select name="submission_type" id="submission_type" required class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select a type...</option>
                                    <option value="Proposal">Proposal</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Final Report">Final Report</option>
                                    <option value="Code">Code</option>
                                    <option value="Database">Database</option>
                                    <option value="Presentation">Presentation</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">Comments/Notes (optional)</label>
                                <textarea name="comment" id="comment" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Add any notes or comments about this submission..."></textarea>
                            </div>
                            
                            <div class="file-upload-container">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Upload Files</label>
                                <div class="mt-1 border-2 border-dashed border-gray-300 rounded-md p-6 text-center relative" id="file-dropzone">
                                    <input type="file" name="files[]" id="files" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="updateFileList()" required />
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600 justify-center">
                                            <span class="relative rounded-md font-medium text-blue-600 hover:text-blue-500 cursor-pointer">
                                                Upload files
                                            </span>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            Any file type accepted (PDF, DOC, ZIP, SQL, etc.)
                                        </p>
                                    </div>
                                </div>
                                <div id="file-list" class="mt-2"></div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition-colors duration-200">
                                    Submit Files
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function updateFileList() {
            const input = document.getElementById('files');
            const fileList = document.getElementById('file-list');
            fileList.innerHTML = '';
            
            if (input.files.length > 0) {
                const list = document.createElement('ul');
                list.className = 'mt-3 divide-y divide-gray-200';
                
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    const item = document.createElement('li');
                    item.className = 'py-3 flex items-center justify-between';
                    
                    // Format file size
                    let fileSize;
                    if (file.size < 1024) {
                        fileSize = file.size + ' bytes';
                    } else if (file.size < 1024 * 1024) {
                        fileSize = (file.size / 1024).toFixed(1) + ' KB';
                    } else {
                        fileSize = (file.size / (1024 * 1024)).toFixed(1) + ' MB';
                    }
                    
                    // Get icon based on file type
                    let fileIcon = '';
                    const fileType = file.name.split('.').pop().toLowerCase();
                    if (['pdf'].includes(fileType)) {
                        fileIcon = '<svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"></path><path d="M3 8a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path></svg>';
                    } else if (['doc', 'docx'].includes(fileType)) {
                        fileIcon = '<svg class="w-8 h-8 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>';
                    } else if (['xls', 'xlsx', 'csv'].includes(fileType)) {
                        fileIcon = '<svg class="w-8 h-8 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1h4a1 1 0 001-1v-1h-5v2zm0-4h5V8h-5v2zM9 8H4v2h5V8z" clip-rule="evenodd"></path></svg>';
                    } else if (['zip', 'rar', '7z'].includes(fileType)) {
                        fileIcon = '<svg class="w-8 h-8 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2h8v1H6V6zm0 3h8v1H6V9zm0 3h8v1H6v-1z" clip-rule="evenodd"></path></svg>';
                    } else if (['sql'].includes(fileType)) {
                        fileIcon = '<svg class="w-8 h-8 text-purple-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16zM5.94 5.5c.944-.945 2.56-.276 5.074.248.41.086.82.183 1.226.298C10.868 4.251 9.022 3.911 7.6 5.333 6.9 6.033 6.5 7.633 6.5 7.633l-.74-.366C5.85 6.4 5.56 5.877 5.94 5.5zm8.25 3.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm1.138 9.992a7.998 7.998 0 01-3.333.938 8.025 8.025 0 01-6.047-2.672c-2.933-3.095-1.894-7.312-.694-9.054a.75.75 0 011.138.984c-.602.878-1.843 4.214.574 6.75a6.73 6.73 0.0 4.606 2.254 6.71 6.71 0 003.756-.752.75.75 0 01.9 1.202z" clip-rule="evenodd"></path></svg>';
                    } else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileType)) {
                        fileIcon = '<svg class="w-8 h-8 text-pink-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path></svg>';
                    } else {
                        fileIcon = '<svg class="w-8 h-8 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path></svg>';
                    }
                    
                    item.innerHTML = `
                        <div class="flex items-center">
                            <div class="flex-shrink-0">${fileIcon}</div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">${file.name}</p>
                                <p class="text-sm text-gray-500">${fileSize}</p>
                            </div>
                        </div>
                    `;
                    
                    list.appendChild(item);
                }
                
                fileList.appendChild(list);
                
                // Add styling to the dropzone to indicate files are selected
                document.getElementById('file-dropzone').classList.add('border-blue-500');
            } else {
                document.getElementById('file-dropzone').classList.remove('border-blue-500');
            }
        }
        
        // Add drag and drop functionality
        const dropzone = document.getElementById('file-dropzone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropzone.classList.add('border-blue-500', 'bg-blue-50');
        }
        
        function unhighlight() {
            dropzone.classList.remove('border-blue-500', 'bg-blue-50');
        }
        
        dropzone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('files').files = files;
            updateFileList();
        }
    </script>
</body>
</html>