<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT fname FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $userName = htmlspecialchars($row['fname']);
    }
}

// Ongoing Campaigns
$result_campaigns = mysqli_query($conn, "SELECT COUNT(*) AS ongoing_campaigns FROM campaigns WHERE progress = 'ongoing'");
$row_campaigns = mysqli_fetch_assoc($result_campaigns);
$ongoingCampaigns = $row_campaigns['ongoing_campaigns'];

// Total Donation Amount
$result_donations = mysqli_query($conn, "SELECT SUM(amount) AS total_donation_amount FROM donations");
$row_donations = mysqli_fetch_assoc($result_donations);
$totalDonationAmount = number_format($row_donations['total_donation_amount'], 0); // Format as currency
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Metrics</title>
    <style>
        :root {
            --primary: #000000;
            --success: #2ecc71;
            --info: #3498db;
            --warning: #f39c12;
            --danger: #e74c3c;
            --secondary: #7f8c8d;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fc;
        }

        .main-content {
            padding: 20px;
        }

        /* Welcome section styles */
        .welcome-section {
            margin-bottom: 30px;
        }

        .welcome-message {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .welcome-subtext {
            font-size: 16px;
            color: var(--secondary);
        }

        /* Cards Section */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0.15rem 0.35rem rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            border-left: 4px solid var(--success);
        }

        .card.primary {
            border-left-color: var(--primary);
        }

        .card.success {
            border-left-color: var(--success);
        }

        .card.info {
            border-left-color: var(--info);
        }

        .card.warning {
            border-left-color: var(--warning);
        }

        .card-title {
            color: var(--secondary);
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .card-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--primary);
        }

        /* Charts and Tables Section */
        .data-container {
            display: grid;
            grid-template-columns: 1.8fr 1.2fr;
            gap: 20px;
            align-items: stretch;
        }

        .chart-container {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0.15rem 0.35rem rgba(0, 0, 0, 0.1);
            border: 1px solid #e3e6f0;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .time-period {
            display: flex;
            gap: 10px;
        }

        .time-period button {
            background: none;
            border: 1px solid #ddd;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .time-period button:hover {
            background-color: var(--light);
        }

        .time-period button.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .chart-placeholder {
            height: 300px;
            background-color: #f8f9fc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            border-radius: 5px;
            border: 1px dashed #ddd;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1 class="welcome-message">Welcome back, <?php echo $userName; ?>!</h1>
            <p class="welcome-subtext">Here's what's happening with your campaigns today.</p>
        </div>

        <!-- Dashboard Cards -->
            <div class="card success">
                <div class="card-title">Ongoing Campaigns</div>
                <div class="card-value"><?php echo $ongoingCampaigns; ?></div>
            </div>

            <div class="card warning">
                <div class="card-title">Donation Amount</div>
                <div class="card-value">$<?php echo $totalDonationAmount; ?></div>
            </div>
        </div>
    </div>
</body>
</html>