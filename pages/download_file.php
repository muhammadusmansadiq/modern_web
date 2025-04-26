<?php
// pages/download_file.php
// This is a central file download handler that works for all users
// Buffer output to prevent "headers already sent" errors
ob_start();

include_once '../config/db.php';
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get file ID from query parameters
$fileId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($fileId <= 0) {
    http_response_code(400);
    exit('Invalid file ID');
}

try {
    // Fetch file information
    $stmt = $pdo->prepare("
        SELECT 
            f.FileID,
            f.FileName,
            f.FilePath,
            f.FileType,
            f.FileSize,
            f.UploadedBy,
            s.ProjectID,
            p.GroupID
        FROM 
            FileUploads f
        JOIN 
            Submissions s ON f.SubmissionID = s.SubmissionID
        JOIN 
            Projects p ON s.ProjectID = p.ProjectID
        WHERE 
            f.FileID = :fileId
    ");
    $stmt->execute([':fileId' => $fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        exit('File not found');
    }
    
    // Security check: Verify user has permission to download this file
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role_id'];
    
    $hasAccess = false;
    
    // Admin has access to all files
    if ($userRole == 1) {
        $hasAccess = true;
    }
    // Supervisor has access if they are the supervisor of the group
    else if ($userRole == 2) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM Groups
            WHERE GroupID = :groupId AND SupervisorID = :userId
        ");
        $stmt->execute([
            ':groupId' => $file['GroupID'],
            ':userId' => $userId
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $hasAccess = $result['count'] > 0;
    }
    // Student has access if they are the uploader or in the same group
    else if ($userRole == 3) {
        if ($file['UploadedBy'] == $userId) {
            $hasAccess = true;
        } else {
            // Check if student is in the same group
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM StudentGroups
                WHERE GroupID = :groupId AND StudentID = :studentId AND Status = 'Active'
            ");
            $stmt->execute([
                ':groupId' => $file['GroupID'],
                ':studentId' => $userId
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $hasAccess = $result['count'] > 0;
        }
    }
    
    if (!$hasAccess) {
        http_response_code(403);
        exit('Access denied');
    }
    
    // File exists but check if the file physically exists
    if (!file_exists($file['FilePath'])) {
        http_response_code(404);
        exit('File not found on server');
    }
    
    // Set headers and output file for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file['FileName']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $file['FileSize']);
    ob_clean(); // Clean the output buffer
    flush(); // Flush system output buffer
    readfile($file['FilePath']);
    exit;
    
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
}
?>