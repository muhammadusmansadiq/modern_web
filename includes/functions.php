<?php
function redirect($url) {
    header("Location: $url");
    exit();
}

function alert($message, $type = 'danger') {
    echo "<div class='alert alert-$type' role='alert'>$message</div>";
}

function get_user_role($user_id) {
    // Assuming you have a database connection in db.php
    require_once '../config/db.php';

    try {
        $stmt = $pdo->prepare("SELECT RoleID FROM Users WHERE UserID = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $role_id = $user['RoleID'];
            $stmt = $pdo->prepare("SELECT RoleName FROM Roles WHERE RoleID = :role_id");
            $stmt->execute(['role_id' => $role_id]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            return $role ? $role['RoleName'] : null;
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
    return null;
}
?>