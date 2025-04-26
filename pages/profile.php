<?php
// profile.php
include '../includes/header.php';
// include '../config/db.php';

// session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Fetch user profile data
$stmt = $pdo->prepare("SELECT * FROM Profile WHERE UserID = :user_id");
$stmt->execute([":user_id" => $user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $contact_info = $_POST['contact_info'];
    $dob = $_POST['dob'];
    $cnic = $_POST['cnic'];
    $profile_image = $_FILES['profile_image'];

    // Validate input
    if (empty($first_name) || empty($last_name) || empty($contact_info) || empty($dob) || empty($cnic)) {
        $error = "All fields are required.";
    } else {
        try {
            if ($profile) {
                // Update existing profile
                $updateStmt = $pdo->prepare("UPDATE Profile SET FirstName = :first_name, LastName = :last_name, ContactInfo = :contact_info, DOB = :dob WHERE UserID = :user_id");
                $updateStmt->execute([
                    ":first_name" => $first_name,
                    ":last_name" => $last_name,
                    ":contact_info" => $contact_info,
                    ":dob" => $dob,
                    ":user_id" => $user_id
                ]);

                // Handle profile image upload
                if ($profile_image['error'] == UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    $fileType = $profile_image['type'];

                    if (in_array($fileType, $allowedTypes)) {
                        $targetDir = "../uploads/";
                        $targetFile = $targetDir . basename($profile_image['name']);
                        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

                        // Generate a unique filename to avoid overwriting
                        $uniqueFileName = uniqid() . '.' . $imageFileType;
                        $targetFile = $targetDir . $uniqueFileName;

                        if (move_uploaded_file($profile_image['tmp_name'], $targetFile)) {
                            // Update the profile image path in the database
                            $updateImageStmt = $pdo->prepare("UPDATE Profile SET ProfileImage = :profile_image WHERE UserID = :user_id");
                            $updateImageStmt->execute([
                                ":profile_image" => $uniqueFileName,
                                ":user_id" => $user_id
                            ]);
                            $success = "Profile updated successfully.";
                        } else {
                            $error = "Error uploading profile image.";
                        }
                    } else {
                        $error = "Invalid file type. Only JPEG, PNG, and GIF are allowed.";
                    }
                } else {
                    $success = "Profile updated successfully.";
                }
            } else {
                // Create new profile
                $insertStmt = $pdo->prepare("INSERT INTO Profile (UserID, FirstName, LastName, ContactInfo, DOB, CNIC, ProfileImage) VALUES (:user_id, :first_name, :last_name, :contact_info, :dob, :cnic, :profile_image)");
                
                // Handle profile image upload
                if ($profile_image['error'] == UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
                    $fileType = $profile_image['type'];

                    if (in_array($fileType, $allowedTypes)) {
                        $targetDir = "../uploads/";
                        $targetFile = $targetDir . basename($profile_image['name']);
                        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

                        // Generate a unique filename to avoid overwriting
                        $uniqueFileName = uniqid() . '.' . $imageFileType;
                        $targetFile = $targetDir . $uniqueFileName;

                        if (move_uploaded_file($profile_image['tmp_name'], $targetFile)) {
                            // Insert the new profile with the image path
                            $insertStmt->execute([
                                ":user_id" => $user_id,
                                ":first_name" => $first_name,
                                ":last_name" => $last_name,
                                ":contact_info" => $contact_info,
                                ":dob" => $dob,
                                ":cnic" => $cnic,
                                ":profile_image" => $uniqueFileName
                            ]);
                            $success = "Profile created successfully.";
                        } else {
                            $error = "Error uploading profile image.";
                        }
                    } else {
                        $error = "Invalid file type. Only JPEG, PNG, and WEBP are allowed.";
                    }
                } else {
                    // Insert the new profile without an image
                    $insertStmt->execute([
                        ":user_id" => $user_id,
                        ":first_name" => $first_name,
                        ":last_name" => $last_name,
                        ":contact_info" => $contact_info,
                        ":dob" => $dob,
                        ":cnic" => $cnic,
                        ":profile_image" => null
                    ]);
                    $success = "Profile created successfully.";
                }
            }
        } catch (PDOException $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<div class="min-h-screen flex flex-col">
    <main class="flex-grow">
        <div class="flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-2xl">
                <h2 class="text-2xl font-bold mb-6">Profile</h2>
                <?php if (!empty($error)) { ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $error; ?></span>
                    </div>
                <?php } ?>
                <?php if (!empty($success)) { ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $success; ?></span>
                    </div>
                <?php } ?>
                <?php if (!$profile) { ?>
                    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline">Please create your profile first.</span>
                    </div>
                <?php } ?>
                <form method="POST" action="profile.php" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="first_name" class="block text-gray-700 font-bold mb-2">First Name</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="first_name" name="first_name" value="<?php echo htmlspecialchars($profile['FirstName'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="last_name" class="block text-gray-700 font-bold mb-2">Last Name</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="last_name" name="last_name" value="<?php echo htmlspecialchars($profile['LastName'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="contact_info" class="block text-gray-700 font-bold mb-2">Contact Info</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="contact_info" name="contact_info" value="<?php echo htmlspecialchars($profile['ContactInfo'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="dob" class="block text-gray-700 font-bold mb-2">Date of Birth</label>
                        <input type="date" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="dob" name="dob" value="<?php echo htmlspecialchars($profile['DOB'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="cnic" class="block text-gray-700 font-bold mb-2">CNIC</label>
                        <input type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="cnic" name="cnic" value="<?php echo htmlspecialchars($profile['CNIC'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label for="profile_image" class="block text-gray-700 font-bold mb-2">Profile Image</label>
                        <input type="file" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="profile_image" name="profile_image">
                    </div>
                    <?php if ($profile && $profile['ProfileImage']) { ?>
                        <div class="mb-4">
                            <label class="block text-gray-700 font-bold mb-2">Current Profile Image</label>
                            <img src="../uploads/<?php echo htmlspecialchars($profile['ProfileImage']); ?>" alt="Profile Image" class="w-32 h-32 rounded-full">
                        </div>
                    <?php } ?>
                    <div class="flex items-center justify-between">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            <?php echo $profile ? 'Update Profile' : 'Create Profile'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <?php include '../includes/footer.php'; ?>
</div>