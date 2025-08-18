<?php
session_start();
require 'config.php'; // Include your database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT fname, lname, email, phone, location, picture FROM users WHERE id = ?");
$query->bind_param("i", $user_id); // Bind the parameter
$query->execute(); // Execute the query
$result = $query->get_result(); // Get the result set
$user = $result->fetch_assoc(); // Fetch the user data

// Debug Profile Update
if (isset($_POST['debug_info'])) {
    error_log("POST data: " . print_r($_POST, true));
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Update Name, Email & Location
    if (isset($_POST['update_profile'])) {
        $fname = htmlspecialchars($_POST['fname']);
        $lname = htmlspecialchars($_POST['lname']);
        $email = htmlspecialchars($_POST['email']);
        $location = htmlspecialchars($_POST['location']);
        $phone = htmlspecialchars($_POST['phone']);

        // Debug - log data before update
        error_log("Updating profile for user ID: $user_id");
        error_log("Data: fname=$fname, lname=$lname, email=$email, location=$location, phone=$phone");

        $updateQuery = $conn->prepare("UPDATE users SET fname = ?, lname = ?, email = ?, location = ?, phone = ? WHERE id = ?");
        $updateQuery->bind_param("sssssi", $fname, $lname, $email, $location, $phone, $user_id); // Bind parameters

        if ($updateQuery->execute()) { // Execute the query and check result
            $_SESSION['success'] = "Profile updated successfully!";
            error_log("Profile update successful");
        } else {
            $_SESSION['error'] = "Failed to update profile: " . $conn->error;
            error_log("Profile update failed: " . $conn->error);
        }

        // Redirect to refresh page with updated data
        header("Location: profile.php");
        exit();
    }
}

// Update Profile Picture
if (isset($_POST['upload_picture']) && isset($_FILES['picture'])) {
    $img_name = $_FILES['picture']['name'];
    $img_tmp = $_FILES['picture']['tmp_name'];
    $img_size = $_FILES['picture']['size'];
    $img_type = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));

    // Validate file type
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($img_type, $allowed_types)) {
        $_SESSION['error'] = "Only JPG, JPEG, PNG, and GIF files are allowed!";
    } elseif ($img_size > 5 * 1024 * 1024) { // 5MB limit
        $_SESSION['error'] = "File size must be less than 5MB!";
    } else {
        // Create uploads folder if not exists
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true); // Changed to 0755 for better security
        }

        // Delete old picture if it exists and is not the default avatar
        if (
            !empty($user['picture']) &&
            file_exists($user['picture']) &&
            strpos($user['picture'], 'default-avatar.png') === false
        ) {
            unlink($user['picture']);
        }

        // Create unique file name
        $new_file_name = "user_" . $user_id . "." . $img_type;
        $img_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($img_tmp, $img_path)) {
            // Store relative path in database
            $relative_path = "uploads/" . $new_file_name;
            $updatePic = $conn->prepare("UPDATE users SET picture = ? WHERE id = ?");
            $updatePic->bind_param("si", $relative_path, $user_id);

            if ($updatePic->execute()) {
                // Update session and local user data immediately
                $_SESSION['picture'] = $relative_path;
                $user['picture'] = $relative_path;
                $_SESSION['success'] = "Profile picture updated!";

                // Add cache busting query string to force refresh
                $cache_buster = "?v=" . time();
                echo "<script>document.querySelector('.profile-img').src = '" . htmlspecialchars($relative_path) . $cache_buster . "';</script>";
            } else {
                $_SESSION['error'] = "Database update failed!";
                // Remove the uploaded file if DB update failed
                if (file_exists($img_path)) {
                    unlink($img_path);
                }
            }
        } else {
            $_SESSION['error'] = "Failed to upload image! Check folder permissions.";
        }
    }
    header("Location: profile.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <title>Profile - CrisisLink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <style>
        /* Additional inline styles to ensure full screen */
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        .profile-dashboard {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
        }
    </style>
</head>

<body>
   

        <!-- Main Content Area -->
        <div class="profile-content">
            <div class="content-header">
                <h1 class="content-title">Profile Information</h1>
                <p class="content-subtitle">Manage your personal information</p>
            </div>

            <div class="content-body">
                <!-- Success & Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['success']);
                        unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['error']);
                        unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Info Tab -->
                <div class="tab-content active" id="profile-info">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <div class="section-title">
                                <i class="fas fa-user"></i>
                                Personal Information
                            </div>
                            <span class="edit-badge">Editable</span>
                        </div>
                        <div class="form-section-body">
                            <form method="POST" id="profile-form" novalidate>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="fname" value="<?php echo htmlspecialchars($user['fname']); ?>" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="lname" value="<?php echo htmlspecialchars($user['lname']); ?>" class="form-control" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control" required>
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Location</label>
                                        <input type="text" name="location" value="<?php echo htmlspecialchars($user['location']); ?>" class="form-control" required>
                                    </div>
                                </div>

                                <input type="hidden" name="debug_info" value="1">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Profile Picture Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <div class="section-title">
                                <i class="fas fa-camera"></i>
                                Profile Picture
                            </div>
                            <span class="edit-badge">Upload</span>
                        </div>
                        <div class="form-section-body">
                            <form method="POST" enctype="multipart/form-data" id="picture-form" novalidate>
                                <div class="file-upload-area">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <div class="upload-text">Click to upload or drag and drop</div>
                                    <div class="upload-subtext">JPG, PNG, GIF up to 5MB</div>
                                </div>
                                <input type="file" id="picture-upload" name="picture" class="form-control" accept="image/*" required style="display: none;">
                                <input type="hidden" name="upload_picture" value="1">
                                <div class="form-text">Choose a clear, professional photo that represents you well.</div>
                                <button type="submit" name="upload_picture" class="btn btn-success" style="margin-top: 15px;">
                                    <i class="fas fa-upload"></i> Upload Picture
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pass PHP variables to JavaScript -->
    <script>
        // Global user data
        const userData = {
            id: <?php echo $user_id; ?>,
            name: '<?php echo addslashes($user['fname'] . ' ' . $user['lname']); ?>',
            email: '<?php echo addslashes($user['email']); ?>',
            phone: '<?php echo addslashes($user['phone']); ?>',
            location: '<?php echo addslashes($user['location']); ?>',
            picture: '<?php echo addslashes($user['picture']); ?>'
        };
    </script>

    <script src="assets/js/profile.js"></script>
</body>

</html>