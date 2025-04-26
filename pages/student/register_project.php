<?php
include_once '../../includes/header.php';
include_once '../../config/db.php';


// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

// Fetch supervisors
try {
    $stmt = $pdo->prepare("
        SELECT UserID, Username FROM Users WHERE RoleID = 2
    ");
    $stmt->execute();
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "An error occurred: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $objectives = $_POST['objectives'];
    $supervisor_id = $_POST['supervisor_id'];

    // Validate inputs
    if (empty($title) || empty($description) || empty($objectives) || empty($supervisor_id)) {
        $error = "All fields are required.";
    } else {
        try {
            // Start a transaction
            $pdo->beginTransaction();

            // Insert the project into the Projects table
            $stmt = $pdo->prepare("
                INSERT INTO Projects (Title, Description, Objectives, StudentID, SupervisorID, Status, CreatedAt, UpdatedAt)
                VALUES (:title, :description, :objectives, :student_id, :supervisor_id, 'Proposal Submitted', NOW(), NOW())
            ");
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':objectives' => $objectives,
                ':student_id' => $_SESSION['user_id'],
                ':supervisor_id' => $supervisor_id
            ]);

            // Get the last inserted project ID
            $project_id = $pdo->lastInsertId();

            // Insert the action into the ProjectHistory table
            $historyStmt = $pdo->prepare("
                INSERT INTO ProjectHistory (ProjectID, Action, ActionDate, UserID)
                VALUES (:project_id, 'Proposal Submitted', NOW(), :student_id)
            ");
            $historyStmt->execute([
                ':project_id' => $project_id,
                ':student_id' => $_SESSION['user_id']
            ]);

            // Commit the transaction
            $pdo->commit();

            $success = "Project registered successfully.";
        } catch (PDOException $e) {
            // Rollback the transaction
            $pdo->rollBack();
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Project</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <main class="flex-grow">
            <div class="flex items-center justify-center mt-10">
                <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
                    <h2 class="text-2xl font-bold mb-6">Register Project</h2>
                    <?php if (isset($error) && !empty($error)) { ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php } ?>
                    <?php if (isset($success) && !empty($success)) { ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    <?php } ?>
                    <form method="POST" action="register_project.php">
                        <div class="mb-4">
                            <label for="title" class="block text-gray-700 font-bold mb-2">Title</label>
                            <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="title" name="title" required>
                        </div>
                        <div class="mb-4">
                            <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                            <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="description" name="description" rows="4" required></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="objectives" class="block text-gray-700 font-bold mb-2">Objectives</label>
                            <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="objectives" name="objectives" rows="4" required></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="supervisor" class="block text-gray-700 font-bold mb-2">Supervisor</label>
                            <input type="text" list="supervisors" id="supervisor" name="supervisor" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            <input type="hidden" id="supervisor_id" name="supervisor_id">
                            <datalist id="supervisors">
                                <?php foreach ($supervisors as $supervisor): ?>
                                    <option value="<?php echo htmlspecialchars($supervisor['Username']); ?>" data-id="<?php echo htmlspecialchars($supervisor['UserID']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Register Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const supervisorInput = document.getElementById('supervisor');
            const supervisorIdInput = document.getElementById('supervisor_id');
            const supervisorsList = document.getElementById('supervisors');

            supervisorInput.addEventListener('input', function() {
                const selectedOption = Array.from(supervisorsList.options).find(option => option.value === supervisorInput.value);
                if (selectedOption) {
                    supervisorIdInput.value = selectedOption.getAttribute('data-id');
                } else {
                    supervisorIdInput.value = '';
                }
            });

            supervisorInput.addEventListener('change', function() {
                const selectedOption = Array.from(supervisorsList.options).find(option => option.value === supervisorInput.value);
                if (selectedOption) {
                    supervisorIdInput.value = selectedOption.getAttribute('data-id');
                } else {
                    supervisorIdInput.value = '';
                }
            });
        });
    </script>
</body>
</html>