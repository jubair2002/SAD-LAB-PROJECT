<?php
require_once 'config.php';

// Check if the user is logged in and fetch user role
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_type'];

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the submitted form data
    $name = $_POST['name'];
    $description = $_POST['description'];
    $goal = $_POST['goal'];
    $category = $_POST['category'];
    $location = $_POST['location'];


    // Handle the uploaded image
    if (isset($_FILES['image_url']) && $_FILES['image_url']['error'] == 0) {
        $image_tmp_name = $_FILES['image_url']['tmp_name'];
        $image_name = $_FILES['image_url']['name'];
        $image_size = $_FILES['image_url']['size'];
        $image_ext = pathinfo($image_name, PATHINFO_EXTENSION);

        $allowed_ext = ['jpg', 'jpeg', 'png'];
        if (!in_array(strtolower($image_ext), $allowed_ext)) {
            echo "Invalid image format. Please upload JPG, JPEG, or PNG files.";
            exit;
        }

        $sanitized_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($name));
        $image_new_name = $sanitized_name . '.' . $image_ext;
        $upload_dir = 'uploads/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $image_path = $upload_dir . $image_new_name;
        move_uploaded_file($image_tmp_name, $image_path);
    }

    $status = ($user_role == 'admin') ? 'approved' : 'pending';

    $stmt = $conn->prepare("INSERT INTO campaigns (name, description, goal, category, location, image_url, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissss", $name, $description, $goal, $category, $location, $image_path, $status);

    if ($stmt->execute()) {
        header("Location: campaign.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    // After successfully creating a campaign
    $chatSystem = new ChatSystem($conn);
    $chatId = $chatSystem->createCampaignChat($newCampaignId, $_SESSION['user_id']);

    // Add all assigned volunteers to the chat
    foreach ($assignedVolunteers as $volunteerId) {
        $chatSystem->addParticipant($chatId, $volunteerId);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Campaign</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/createCampaign.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-plus-circle"></i> Create New Campaign</h1>
            <a href="campaignSummary.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Campaigns
            </a>
        </div>

        <?php if ($user_role != 'admin'): ?>
            <div class="status-notice">
                <i class="fas fa-info-circle"></i> Your campaign will be reviewed by an admin before approval.
            </div>
        <?php endif; ?>

        <div class="create-campaign-form">
            <form method="POST" action="createCampaign.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Campaign Name</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required></textarea>
                </div>

                <div class="form-group">
                    <label for="goal">Goal Amount</label>
                    <input type="number" id="goal" name="goal" min="1" required>
                </div>

                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" required>
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" required>
                </div>

                <div class="form-group">
                    <label for="image_url">Campaign Image</label>
                    <input type="file" id="image_url" name="image_url" accept="image/jpeg,image/png,image/jpg" required>
                    <small class="file-help">Upload JPG, JPEG, or PNG files (Max 5MB)</small>
                </div>

                <button type="submit">
                    <i class="fas fa-rocket"></i> Create Campaign
                </button>
            </form>
        </div>
    </div>
</body>

</html>