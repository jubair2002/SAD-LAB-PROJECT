<?php
require_once 'config.php';
session_start();

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

// Check if user is logged in
$user_logged_in = isset($_SESSION['user_id']);
$user_id = $user_logged_in ? $_SESSION['user_id'] : 0;

// Determine if anonymous option should be shown (always show if not logged in, or if logged in)
$show_anonymous_option = true; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <title>Donate to <?php echo htmlspecialchars($campaign['name']); ?></title>
    
    <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/donation.css">
    
    <style>
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .back-btn {
                position: static;
                transform: none;
                align-self: center;
            }
        }
        /* Style for the anonymous donation checkbox */
        .anonymous-option {
            margin-top: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f0f8ff; /* Light blue background */
            border-left: 5px solid var(--donation-primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            color: #333;
        }
        .anonymous-option input[type="checkbox"] {
            transform: scale(1.2);
            accent-color: var(--donation-primary);
        }
        .anonymous-option label {
            cursor: pointer;
            font-weight: 500;
        }
        /* Hide fields when anonymous is selected */
        .personal-info-fields.hidden {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease-out;
        }
        .personal-info-fields.visible {
            display: block;
            opacity: 1;
            transition: opacity 0.3s ease-in;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-heart"></i> Make a Donation</h1>
            <a href="campaign-details.php?id=<?php echo $campaign_id; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Campaign
            </a>
        </div>

        <div class="donation-container">
            <?php if (!$user_logged_in): ?>
                <div class="login-message">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Note:</strong> For a better donation experience, consider
                        <a href="login.php?redirect=donate.php?campaign_id=<?php echo $campaign_id; ?>&amount=<?php echo $amount; ?>">logging in</a> or
                        <a href="register.php?redirect=donate.php?campaign_id=<?php echo $campaign_id; ?>&amount=<?php echo $amount; ?>">creating an account</a>.
                        <br><small>This helps us track your donations and send you updates about the campaign.</small>
                    </div>
                </div>
            <?php endif; ?>

            <div class="campaign-info">
                <div class="campaign-image">
                    <img src="<?php echo htmlspecialchars($campaign['image_url']); ?>" alt="<?php echo htmlspecialchars($campaign['name']); ?>">
                </div>
                <div class="campaign-details">
                    <h2><?php echo htmlspecialchars($campaign['name']); ?></h2>
                    <p>You are donating: <span class="donation-amount"><span id="display-amount"><?php echo number_format($amount, 2); ?></span></span></p>
                    <p style="margin-top: 10px; color: #666; font-size: 14px;">
                        <i class="fas fa-shield-alt" style="color: var(--donation-primary);"></i> 
                        Your donation is secure and goes directly to this campaign.
                    </p>
                </div>
            </div>

            <form action="process-donation.php" method="post" id="donation-form" novalidate>
                <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">

                <div class="form-group">
                    <label for="amount">
                        <i class="fas fa-dollar-sign"></i> Donation Amount ($)
                    </label>
                    <input type="number" id="amount" name="amount" min="1" step="0.01" value="<?php echo $amount; ?>" required placeholder="Enter amount (minimum $1.00)">
                </div>

                <?php if ($show_anonymous_option): ?>
                    <div class="anonymous-option">
                        <input type="checkbox" id="anonymous_donation" name="is_anonymous" value="1">
                        <label for="anonymous_donation">Donate Anonymously</label>
                        <i class="fas fa-question-circle" title="Your name and email will not be saved with this donation."></i>
                    </div>
                <?php endif; ?>

                <?php if (!$user_logged_in): ?>
                    <div class="personal-info-fields" id="guest-fields">
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label for="name">
                                        <i class="fas fa-user"></i> Full Name
                                    </label>
                                    <input type="text" id="name" name="name" required placeholder="Enter your full name">
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label for="email">
                                        <i class="fas fa-envelope"></i> Email Address
                                    </label>
                                    <input type="email" id="email" name="email" required placeholder="Enter your email address">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>
                        <i class="fas fa-credit-card"></i> Payment Method
                    </label>
                    <div class="payment-methods">
                        <div class="payment-method" data-method="credit_card">
                            <input type="radio" name="payment_method" value="credit_card" id="credit_card" required>
                            <label for="credit_card">
                                <i class="fab fa-cc-visa"></i> Credit Card
                            </label>
                        </div>
                        <div class="payment-method" data-method="debit_card">
                            <input type="radio" name="payment_method" value="debit_card" id="debit_card">
                            <label for="debit_card">
                                <i class="fas fa-credit-card"></i> Debit Card
                            </label>
                        </div>
                        <div class="payment-method" data-method="mobile_banking">
                            <input type="radio" name="payment_method" value="mobile_banking" id="mobile_banking">
                            <label for="mobile_banking">
                                <i class="fas fa-mobile-alt"></i> Mobile Banking
                            </label>
                        </div>
                        <div class="payment-method" data-method="bank_transfer">
                            <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer">
                            <label for="bank_transfer">
                                <i class="fas fa-university"></i> Bank Transfer
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Credit/Debit Card Fields -->
                <div class="payment-fields" id="card-fields" style="display: none;">
                    <h4 style="margin-bottom: 20px; color: var(--donation-dark);">
                        <i class="fas fa-lock" style="color: var(--donation-primary);"></i> 
                        Card Information
                    </h4>
                    <div class="form-group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label for="expiry">Expiry Date</label>
                                <input type="text" id="expiry" name="expiry" placeholder="MM/YY" maxlength="5">
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-group">
                                <label for="cvv">CVV</label>
                                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Banking Fields -->
                <div class="payment-fields" id="mobile-fields" style="display: none;">
                    <h4 style="margin-bottom: 20px; color: var(--donation-dark);">
                        <i class="fas fa-mobile-alt" style="color: var(--donation-primary);"></i> 
                        Mobile Banking Details
                    </h4>
                    <div class="form-group">
                        <label for="mobile_provider">Mobile Banking Provider</label>
                        <select id="mobile_provider" name="mobile_provider">
                            <option value="">Select your mobile banking provider</option>
                            <option value="Bkash">bKash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Rocket">Rocket</option>
                            <option value="Upay">Upay</option>
                            <option value="SureCash">SureCash</option>
                            <option value="DBBL">DBBL Mobile Banking</option>
                            <option value="MyCash">MyCash</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="mobile_number">Mobile Number</label>
                        <input type="text" id="mobile_number" name="mobile_number" placeholder="01XXXXXXXXX">
                        <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                            Enter the mobile number registered with your mobile banking service
                        </small>
                    </div>
                </div>

                <!-- Bank Transfer Fields -->
                <div class="payment-fields" id="bank-fields" style="display: none;">
                    <h4 style="margin-bottom: 20px; color: var(--donation-dark);">
                        <i class="fas fa-university" style="color: var(--donation-primary);"></i> 
                        Bank Transfer Details
                    </h4>
                    <div class="form-group">
                        <label for="bank_name">Bank Name</label>
                        <select id="bank_name" name="bank_name">
                            <option value="">Select your bank</option>
                            <option value="aibl">Al-Arafah Islami Bank PLC</option>
                            <option value="dutch_bangla">Dutch-Bangla Bank</option>
                            <option value="brac">BRAC Bank</option>
                            <option value="city">City Bank</option>
                            <option value="eastern">Eastern Bank (EBL)</option>
                            <option value="islami">Islami Bank Bangladesh</option>
                            <option value="standard_chartered">Standard Chartered</option>
                            <option value="hsbc">HSBC Bangladesh</option>
                            <option value="sonali">Sonali Bank</option>
                            <option value="janata">Janata Bank</option>
                            <option value="agrani">Agrani Bank</option>
                            <option value="pubali">Pubali Bank</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="account_number">Account Number</label>
                        <input type="text" id="account_number" name="account_number" placeholder="Enter your account number">
                    </div>
                </div>

                <div style="margin: 30px 0; padding: 20px; background: rgba(76, 175, 80, 0.05); border-radius: 8px; border-left: 4px solid var(--donation-primary);">
                    <h4 style="margin-bottom: 15px; color: var(--donation-dark);">
                        <i class="fas fa-heart" style="color: var(--donation-primary);"></i> 
                        Your Impact
                    </h4>
                    <p style="margin: 0; color: #666; line-height: 1.6;">
                        Your generous donation will help <?php echo htmlspecialchars($campaign['name']); ?> reach its goal and make a real difference in the lives of those we serve. Every contribution, no matter the size, brings us closer to creating positive change.
                    </p>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-heart"></i> Complete Donation
                </button>
                
                <p style="text-align: center; margin-top: 20px; font-size: 12px; color: #666; line-height: 1.5;">
                    <i class="fas fa-lock" style="color: var(--donation-primary);"></i> 
                    Your payment information is encrypted and secure. We never store your financial details.
                    <br>
                    By donating, you agree to our <a href="#" style="color: var(--donation-primary);">Terms of Service</a> and <a href="#" style="color: var(--donation-primary);">Privacy Policy</a>.
                </p>
            </form>
        </div>
    </div>

    <!-- Pass PHP variables to JavaScript -->
    <script>
        // Global campaign data
        const campaignData = {
            id: <?php echo $campaign_id; ?>,
            name: '<?php echo addslashes($campaign['name']); ?>',
            imageUrl: '<?php echo addslashes($campaign['image_url']); ?>',
            userLoggedIn: <?php echo $user_logged_in ? 'true' : 'false'; ?>,
            showAnonymousOption: <?php echo $show_anonymous_option ? 'true' : 'false'; ?>
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="assets/js/donation.js"></script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
