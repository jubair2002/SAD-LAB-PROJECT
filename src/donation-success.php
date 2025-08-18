<?php
require_once 'config.php';

if (!isset($_GET['campaign_id']) || empty($_GET['campaign_id'])) {
    header('Location: campaign.php');
    exit;
}

$campaign_id = intval($_GET['campaign_id']);
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

// Fetch campaign details
$stmt = $conn->prepare("SELECT * FROM campaigns WHERE id = ?");
$stmt->bind_param("i", $campaign_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: campaign.php');
    exit;
}

$campaign = $result->fetch_assoc();

// Generate transaction ID
$transaction_id = strtoupper(substr(md5(time() . $campaign_id), 0, 12));
$current_date = date('F j, Y');
$current_time = date('g:i A');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <title>Donation Successful - <?php echo htmlspecialchars($campaign['name']); ?></title>

    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/donation.css">

    <style>
        /* Additional success page optimizations */
        @media (max-width: 768px) {
            .success-container {
                padding: 30px 20px;
            }

            .success-title {
                font-size: 28px;
            }

            .social-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="success-container donation-success">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>

            <h1 class="success-title">Thank You for Your Generous Donation!</h1>
            <p style="font-size: 18px; color: #666; margin-bottom: 30px;">
                Your contribution has been successfully processed and will make a real difference.
            </p>

            <div class="donation-details">
                <div class="donation-amount">$<?php echo number_format($amount, 2); ?></div>
                <p class="campaign-name">donated to <?php echo htmlspecialchars($campaign['name']); ?></p>
                <div style="margin-top: 15px; font-size: 14px; color: #666;">
                    <i class="fas fa-calendar-alt"></i> <?php echo $current_date; ?> at <?php echo $current_time; ?>
                </div>
            </div>

            <div class="impact-message">
                <i class="fas fa-heart" style="color: var(--donation-primary); margin-right: 10px;"></i>
                Your donation will help us continue our mission and create meaningful change. Together, we can achieve our goals and make a lasting impact on the lives of those we serve.
                <br><br>
                <strong>Thank you for being part of our community of changemakers!</strong>
            </div>

            <div class="receipt-info">
                <h4 style="margin-bottom: 15px; color: var(--donation-dark);">
                    <i class="fas fa-receipt" style="color: var(--donation-primary);"></i>
                    Transaction Details
                </h4>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: left; display: inline-block; min-width: 300px;">
                    <p><strong>Transaction ID:</strong> <?php echo $transaction_id; ?></p>
                    <p><strong>Date:</strong> <?php echo $current_date; ?></p>
                    <p><strong>Time:</strong> <?php echo $current_time; ?></p>
                    <p><strong>Amount:</strong> $<?php echo number_format($amount, 2); ?></p>
                    <p><strong>Campaign:</strong> <?php echo htmlspecialchars($campaign['name']); ?></p>
                    <p><strong>Status:</strong> <span style="color: var(--success-color); font-weight: bold;">✓ Completed</span></p>
                </div>
                <p style="margin-top: 15px; font-size: 14px; color: #666;">
                    <i class="fas fa-envelope"></i> A detailed receipt has been sent to your email address.
                </p>
            </div>

            <!-- Donation Card for Sharing (Hidden) -->
            <div id="donation-card" class="donation-card">
                <div class="card-header">
                    <div class="logo-area">
                        <i class="fas fa-heart"></i> CrisisLink - Donation Certificate
                    </div>
                </div>
                <div class="card-content">
                    <div class="donation-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <h2 class="card-title">Thank You for Your Donation!</h2>
                    <div class="card-amount">$<?php echo number_format($amount, 2); ?></div>
                    <p class="card-campaign">donated to <?php echo htmlspecialchars($campaign['name']); ?></p>
                    <p class="card-date"><?php echo $current_date; ?></p>
                    <div class="card-message">
                        "Your generosity creates ripples of positive change that extend far beyond what you can see. Thank you for making a difference."
                    </div>
                    <div style="margin-top: 20px; font-size: 12px; color: #666;">
                        Transaction ID: <?php echo $transaction_id; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <p class="website">🌟 crisislink.org - Making a Difference Together</p>
                </div>
            </div>

            <!-- Share Section -->
            <div class="share-section">
                <h3 class="share-title">
                    <i class="fas fa-share-alt"></i> Spread the Word & Inspire Others
                </h3>
                <p style="margin-bottom: 25px; color: #666;">
                    Share your donation and encourage others to join this important cause
                </p>
                <div class="social-buttons">
                    <a href="javascript:void(0);" onclick="shareOnFacebook()" class="social-btn facebook" title="Share on Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="javascript:void(0);" onclick="shareOnTwitter()" class="social-btn twitter" title="Share on Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="javascript:void(0);" onclick="shareOnWhatsApp()" class="social-btn whatsapp" title="Share on WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="javascript:void(0);" onclick="shareViaEmail()" class="social-btn email" title="Share via Email">
                        <i class="fas fa-envelope"></i>
                    </a>
                    <a href="javascript:void(0);" onclick="downloadPDF()" class="social-btn pdf-download" title="Download Certificate">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="campaign-details.php?id=<?php echo $campaign_id; ?>" class="btn primary-btn">
                    <i class="fas fa-arrow-left"></i> Return to Campaign
                </a>
                <a href="campaign.php" class="btn secondary-btn">
                    <i class="fas fa-list"></i> View All Campaigns
                </a>
            </div>
        </div>
    </div>

    <!-- External Libraries for PDF Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <!-- Pass PHP variables to JavaScript -->
    <script>
        // Global donation data for sharing
        window.donationData = {
            campaignName: '<?php echo addslashes($campaign['name']); ?>',
            donationAmount: '<?php echo number_format($amount, 2); ?>',
            transactionId: '<?php echo $transaction_id; ?>',
            date: '<?php echo $current_date; ?>',
            time: '<?php echo $current_time; ?>'
        };

        // Auto-scroll to top on page load
        window.scrollTo(0, 0);
    </script>

    <!-- External JavaScript -->
    <script src="assets/js/donation.js"></script>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>