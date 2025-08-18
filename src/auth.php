<?php
session_start();
require_once 'config.php';

$districts = [
    "Bagerhat",
    "Bandarban",
    "Barguna",
    "Barishal",
    "Bhola",
    "Bogra",
    "Brahmanbaria",
    "Chandpur",
    "Chapainawabganj",
    "Chattogram",
    "Chuadanga",
    "Cox's Bazar",
    "Cumilla",
    "Dhaka",
    "Dinajpur",
    "Faridpur",
    "Feni",
    "Gaibandha",
    "Gazipur",
    "Gopalganj",
    "Habiganj",
    "Jamalpur",
    "Jashore",
    "Jhalokati",
    "Jhenaidah",
    "Joypurhat",
    "Khagrachhari",
    "Khulna",
    "Kishoreganj",
    "Kurigram",
    "Kushtia",
    "Lakshmipur",
    "Lalmonirhat",
    "Madaripur",
    "Magura",
    "Manikganj",
    "Meherpur",
    "Moulvibazar",
    "Munshiganj",
    "Mymensingh",
    "Naogaon",
    "Narail",
    "Narayanganj",
    "Narsingdi",
    "Natore",
    "Netrokona",
    "Nilphamari",
    "Noakhali",
    "Pabna",
    "Panchagarh",
    "Patuakhali",
    "Pirojpur",
    "Rajbari",
    "Rajshahi",
    "Rangamati",
    "Rangpur",
    "Satkhira",
    "Shariatpur",
    "Sherpur",
    "Sirajganj",
    "Sunamganj",
    "Sylhet",
    "Tangail",
    "Thakurgaon"
];
// Initialize variables
$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'login';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                handleLogin($conn);
                break;
            case 'register':
                handleRegistration($conn);
                break;
            case 'forgot':
                handleForgotPassword($conn);
                break;
            case 'reset':
                handleResetPassword($conn);
                break;
        }
    }
}

// Login handler
function handleLogin($conn)
{
    global $error;

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Updated SQL query to also select the 'status'
    $stmt = $conn->prepare("SELECT id, fname, user_type, password, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Check user status
            if ($user['status'] == 'active') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['fname'];
                $_SESSION['user_type'] = $user['user_type'];

                // Redirect based on user type
                if ($user['user_type'] == 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($user['user_type'] == 'volunteer') {
                    header("Location: volunteer_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit();
            } else {
                $error = "Your account is currently inactive. Please contact Admin Support.";
            }
        } else {
            $error = "Invalid email or password."; // Keep general for security
        }
    } else {
        $error = "Invalid email or password."; // Keep general for security
    }
}

// Registration handler
function handleRegistration($conn)
{
    global $error, $success;

    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $location = trim($_POST['location']);
    $user_type = $_POST['join_as'];

    // Validate password match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
        return;
    }

    // Validate email uniqueness
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error = "Email already exists";
        return;
    }

    // Set default profile picture path
    $picture_path = 'assets/images/default-profile.jpg';

    // Insert new user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (fname, lname, email,phone, password, picture, location, user_type) VALUES (?, ?, ?, ?, ?, ?, ?,?)");
    $stmt->bind_param("ssssssss", $fname, $lname, $email, $phone, $hashed_password, $picture_path, $location, $user_type);

    if ($stmt->execute()) {
        $success = "Registration successful! Please login.";
        $action = 'login';
    } else {
        $error = "Registration failed. Please try again.";
    }
}
// Forgot password handler
function handleForgotPassword($conn)
{
    global $error, $success;

    $email = trim($_POST['email']);

    // Check if the email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Update reset token and expiration in the database
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $expires, $email);

        if ($stmt->execute()) {
            $success = "Password reset instructions have been sent to your email.";
            header("Location: auth.php?action=reset&token=" . urlencode($token) . "&email=" . urlencode($email));
            exit();
        } else {
            $error = "Failed to process your request. Please try again.";
        }
    } else {
        $error = "Email not found.";
    }
}

// Reset password handler
function handleResetPassword($conn)
{
    global $error, $success;

    if (empty($_POST['password']) || empty($_POST['confirm_password'])) {
        $error = "All fields are required.";
        return;
    }

    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
        return;
    }

    if (empty($token) || empty($email)) {
        $error = "Invalid reset request.";
        return;
    }

    // Verify token and expiration
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ?");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error = "Invalid or expired reset token. Please request a new one.";
        return;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE email = ?");
    $stmt->bind_param("ss", $hashed_password, $email);

    if ($stmt->execute()) {
        $success = "Password reset successfully. Please login with your new password.";
        global $action;
        $action = 'login';
    } else {
        $error = "Error updating password. Please try again.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrisisLink - Authentication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>

<body style="background-image: url('assets/images/background_hero.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">

    <div class="auth-container">
        <!-- Background Effects -->
        <div class="background-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
        </div>

        <!-- no Navbar only button-->
        <div class="nav-content">
            <a href="index.php" class="navbar-brand">CrisisLink Network</a>
        </div>


        <div class="auth-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-content">
                    <h1>Welcome to CrisisLink</h1>
                    <p>Securely manage your account and access our services with ease. Register or log in to continue.</p>
                </div>
            </div>

            <!-- Forms Section -->
            <div class="forms-section">
                <div class="forms-container">
                    <!-- Error & Success Messages -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form id="login-form" class="auth-form" method="POST" action="auth.php" <?= $action == 'login' ? '' : 'style="display: none;"' ?>>
                        <h3 class="text-center mb-4">Login</h3>
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <input type="email" name="email" class="form-control" placeholder="Email" required>
                        </div>
                        <div class="mb-3 position-relative">
                            <input type="password" name="password" class="form-control" placeholder="Password" required>
                            <i class="fas fa-eye toggle-password"></i>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                        <div class="text-center mt-3">
                            <a href="#" class="switch-form" data-target="register-form">Create an Account</a> |
                            <a href="#" class="switch-form" data-target="forgot-form">Forgot Password?</a>
                        </div>
                    </form>

                    <!-- Registration Form -->
                    <form id="register-form" class="auth-form" method="POST" action="auth.php" <?= $action == 'register' ? '' : 'style="display: none;"' ?>>
                        <h3 class="text-center mb-4">Create Account</h3>
                        <input type="hidden" name="action" value="register">
                        <div class="row mb-3">
                            <div class="col">
                                <input type="text" name="fname" class="form-control" placeholder="First Name" required>
                            </div>
                            <div class="col">
                                <input type="text" name="lname" class="form-control" placeholder="Last Name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <input type="email" name="email" class="form-control" placeholder="Email" required>
                        </div>


                        <div class="mb-3 position-relative">
                            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                            <i class="fas fa-eye toggle-password" data-target="password"></i>
                        </div>
                        <div class="mb-3 position-relative">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password" required>
                            <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                        </div>
                        <div class="mb-3">
                            <select name="location" class="form-control" required>
                                <option value="">Select Locations</option>
                                <?php foreach ($districts as $district): ?>
                                    <option value="<?php echo htmlspecialchars($district); ?>">
                                        <?php echo htmlspecialchars($district); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <input type="text" name="phone" class="form-control" placeholder="Phone Number" required>
                        </div>
                        <!-- Join As dropdown -->
                        <div class="mb-3">
                            <select name="join_as" class="form-control" required>
                                <option value="">Join As</option>
                                <option value="volunteer">Volunteer</option>
                                <option value="regular">Regular Member</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                        <div class="text-center mt-3">
                            <a href="#" class="switch-form" data-target="login-form">Already have an account? Login</a>
                        </div>
                    </form>


                    <!-- Forgot Password Form -->
                    <form id="forgot-form" class="auth-form" method="POST" action="auth.php" <?= $action == 'forgot' ? '' : 'style="display: none;"' ?>>
                        <h3 class="text-center mb-4">Forgot Password</h3>
                        <input type="hidden" name="action" value="forgot">
                        <div class="mb-3">
                            <input type="email" name="email" class="form-control" placeholder="Email" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">Reset Password</button>
                        <div class="text-center mt-3">
                            <a href="#" class="switch-form" data-target="login-form">Back to Login</a>
                        </div>
                    </form>

                    <!-- Reset Password Form -->
                    <form id="reset-form" class="auth-form" method="POST" action="auth.php" <?= $action == 'reset' ? '' : 'style="display: none;"' ?>>
                        <h3 class="text-center mb-4">Reset Password</h3>
                        <input type="hidden" name="action" value="reset">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
                        <div class="mb-3 position-relative">
                            <input type="password" name="password" class="form-control" placeholder="New Password" required>
                            <i class="fas fa-eye toggle-password"></i>
                        </div>
                        <div class="mb-3 position-relative">
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                            <i class="fas fa-eye toggle-password"></i>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Update Password</button>
                        <div class="text-center mt-3">
                            <a href="#" class="switch-form" data-target="login-form">Back to Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
</body>

</html>