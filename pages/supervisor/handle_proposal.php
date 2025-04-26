<?php
include_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = $_POST['project_id'];
    $action = $_POST['action'];
    $supervisor_id = $_SESSION['user_id']; // Assuming the supervisor's user ID is stored in session

    if ($action === 'accept') {
        $stmt = $conn->prepare("UPDATE Projects SET Status = 'Accepted' WHERE ProjectID = ?");
        $stmt->execute([$project_id]);

        // Insert into ProjectHistory
        $stmt = $conn->prepare("INSERT INTO ProjectHistory (ProjectID, Action, ActionDate, UserID) VALUES (?, 'Proposal Accepted', NOW(), ?)");
        $stmt->execute([$project_id, $supervisor_id]);
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE Projects SET Status = 'Rejected' WHERE ProjectID = ?");
        $stmt->execute([$project_id]);

        // Insert into ProjectHistory
        $stmt = $conn->prepare("INSERT INTO ProjectHistory (ProjectID, Action, ActionDate, UserID) VALUES (?, 'Proposal Rejected', NOW(), ?)");
        $stmt->execute([$project_id, $supervisor_id]);
    }

    header('Location: supervisor_dashboard.php');
    exit;
}