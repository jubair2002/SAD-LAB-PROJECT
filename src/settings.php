<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match. Please try again.";
    } else {
        $passQuery = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $passQuery->bind_param("i", $_SESSION['user_id']);
        $passQuery->execute();
        $passQuery->store_result();
        $passQuery->bind_result($stored_password);
        $passQuery->fetch();

        if ($passQuery->num_rows == 1 && password_verify($current_password, $stored_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updatePass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updatePass->bind_param("si", $hashed_password, $_SESSION['user_id']);
            $updatePass->execute();
            $_SESSION['success'] = "Your password has been successfully updated.";
        } else {
            $_SESSION['error'] = "The current password you entered is incorrect.";
        }
        $passQuery->close(); // Close the statement
        if (isset($updatePass)) $updatePass->close(); 
    }
    header("Location: settings.php");
    exit();
}

// Handle account deactivation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['deactivate_account'])) {
    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['success'] = "Your account has been deactivated. You can reactivate it by logging in again.";
        session_destroy();
        header("Location: auth.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to deactivate account. Please try again.";
    }
    $stmt->close();
    header("Location: settings.php");
    exit();
}

// Handle account deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_account'])) {
    // First verify password
    $password = $_POST['delete_password'];
    
    $passQuery = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $passQuery->bind_param("i", $_SESSION['user_id']);
    $passQuery->execute();
    $passQuery->store_result();
    $passQuery->bind_result($stored_password);
    $passQuery->fetch();

    if ($passQuery->num_rows == 1 && password_verify($password, $stored_password)) {
        // Delete user from database
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $deleteStmt->bind_param("i", $_SESSION['user_id']);
        $deleteStmt->execute();
        
        if ($deleteStmt->affected_rows > 0) {
            session_destroy();
            header("Location: auth.php?account_deleted=1");
            exit();
        } else {
            $_SESSION['error'] = "Failed to delete account. Please try again.";
        }
        $deleteStmt->close();
    } else {
        $_SESSION['error'] = "Incorrect password. Please try again.";
    }
    $passQuery->close();
    header("Location: settings.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .settings-container {
            flex: 1;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background-color: #fff;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        .settings-content {
            display: grid;
            gap: 2rem;
        }

        .section {
            background-color: #fff;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e0e0e0;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #2e7d32;
        }

        .section-description {
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #444;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
            background-color: #f9f9f9;
        }

        .form-control:focus {
            outline: none;
            border-color: #2e7d32;
            background-color: #fff;
        }

        .form-text-muted {
            display: block;
            margin-top: 0.25rem;
            color: #777;
            font-size: 0.85rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #1b5e20;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: rgba(46, 125, 50, 0.1);
            border-left: 4px solid #2e7d32;
            color: #2e7d32;
        }

        .alert-error {
            background-color: rgba(211, 47, 47, 0.1);
            border-left: 4px solid #d32f2f;
            color: #d32f2f;
        }

        .danger-zone {
            border: 1px solid #d32f2f;
            background-color: rgba(211, 47, 47, 0.03);
        }
        
        .danger-zone .section-title {
            color: #d32f2f;
        }
        
        .btn-danger {
            background-color: #d32f2f;
        }
        
        .btn-danger:hover {
            background-color: #b71c1c;
        }
        
        .confirmation-text {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background-color: #f9f9f9;
            border-radius: 4px;
            line-height: 1.5;
        }
        
        .account-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-active {
            background-color: rgba(46, 125, 50, 0.15);
            color: #2e7d32;
        }
        
        .status-inactive {
            background-color: rgba(211, 47, 47, 0.15);
            color: #d32f2f;
        }

        @media (max-width: 768px) {
            .settings-container {
                padding: 1rem;
            }
            
            .section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="settings-content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-lock"></i>
                    Change Your Password
                </h2>
                <p class="section-description">Secure your account by regularly updating your password. Choose a strong, unique password.</p>

                <form method="POST">
                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required aria-label="Current Password">
                    </div>
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="4" aria-label="New Password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="4" aria-label="Confirm New Password">
                    </div>
                    <button type="submit" name="change_password" class="btn">
                        <i class="fas fa-save"></i> Save New Password
                    </button>
                </form>
            </div>

            <div class="section danger-zone">
                <h2 class="section-title">
                    <i class="fas fa-user-cog"></i>
                    Account Management
                </h2>                
                <?php
                // Get current account status
                $statusStmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
                $statusStmt->bind_param("i", $_SESSION['user_id']);
                $statusStmt->execute();
                $statusStmt->bind_result($account_status);
                $statusStmt->fetch();
                $statusStmt->close();
                ?>
                
                <div class="form-group">
                    <label class="form-label">Account Status</label>
                    <div class="account-status status-<?= $account_status ?>">
                        <?= ucfirst($account_status) ?>
                    </div>
                </div>
                
                <h3 style="margin: 1.5rem 0 1rem; font-size: 1.1rem; color: #d32f2f;">
                    <i class="fas fa-exclamation-triangle"></i> Danger Zone
                </h3>
                
                <div class="form-group">
                    <h4>Deactivate Account</h4>
                    <p class="confirmation-text">
                        Deactivating your account will disable access but preserve your data. You can reactivate by logging in again.
                    </p>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to deactivate your account?');">
                        <button type="submit" name="deactivate_account" class="btn btn-danger">
                            <i class="fas fa-power-off"></i> Deactivate Account
                        </button>
                    </form>
                </div>
                
                <div class="form-group">
                    <h4>Delete Account Permanently</h4>
                    <p class="confirmation-text">
                        This will permanently delete your account and all associated data. This action cannot be undone.
                    </p>
                    <form method="POST" onsubmit="return confirm('Are you absolutely sure? This will permanently delete your account and all data.');">
                        <div class="form-group">
                            <label for="delete_password" class="form-label">Enter your password to confirm</label>
                            <input type="password" id="delete_password" name="delete_password" class="form-control" required aria-label="Confirm Password for Deletion">
                        </div>
                        <button type="submit" name="delete_account" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Delete Account Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>