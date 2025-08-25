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

    <style>
        :root {
            --primary-color: #4a6cf7;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --dark-color: #212529;
            --light-color: #f8f9fa;
            --border-color: #dee2e6;
            --card-bg: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
            --radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
            padding: 0;
            margin: 0;
            -ms-overflow-style: none;
            /* for Internet Explorer, Edge */
            scrollbar-width: none;
            /* for Firefox */
        }

        body::-webkit-scrollbar {
            display: none;
            /* for Chrome, Safari, and Opera */
        }

        .payment-container {
            max-width: 1000px;
            margin: 20px auto;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 24px;
            padding: 20px;
        }

        @media (max-width: 900px) {
            .payment-container {
                grid-template-columns: 1fr;
            }
        }

        .payment-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 20px;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .payment-header h1 {
            font-size: 22px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .secure-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--success-color);
            font-weight: 500;
        }

        .campaign-summary {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }

        .campaign-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .campaign-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .campaign-details h3 {
            font-size: 16px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .donation-amount {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 10px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 16px 0;
        }

        .payment-method {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
        }

        .payment-method:hover {
            border-color: var(--primary-color);
        }

        .payment-method.selected {
            border-color: var(--primary-color);
            background-color: rgba(74, 108, 247, 0.05);
        }

        .payment-method i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }

        .payment-method label {
            cursor: pointer;
            font-weight: 500;
        }

        .payment-fields {
            display: none;
            margin-top: 20px;
            animation: fadeIn 0.3s ease;
        }

        .payment-fields.active {
            display: block;
        }

        .card-preview {
            background: linear-gradient(135deg, #434343, #000000);
            border-radius: 12px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
            position: relative;
            height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-type {
            align-self: flex-end;
            font-size: 24px;
        }

        .card-number {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            letter-spacing: 2px;
        }

        .card-details {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 576px) {
            .row {
                grid-template-columns: 1fr;
            }
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: #3a5ad9;
            transform: translateY(-2px);
        }

        .anonymous-option {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .login-prompt {
            background: #e7f3ff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .login-prompt a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
        }

        .impact-section {
            background: rgba(25, 135, 84, 0.05);
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
            border-left: 4px solid var(--success-color);
        }

        .impact-section h4 {
            color: var(--success-color);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-total {
            font-weight: 700;
            font-size: 18px;
            padding-top: 15px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hidden {
            display: none;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="payment-container">
        <div class="left-column">
            <a href="campaign-details.php?id=<?php echo $campaign_id; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Campaign
            </a>

            <div class="payment-card">
                <div class="payment-header">
                    <h1>Payment Details</h1>
                    <div class="secure-badge">
                        <i class="fas fa-lock"></i>
                        <span>Secure Payment</span>
                    </div>
                </div>

                <?php if (!$user_logged_in): ?>
                    <div class="login-prompt">
                        <i class="fas fa-info-circle"></i>
                        For a better experience,
                        <a href="login.php?redirect=donate.php?campaign_id=<?php echo $campaign_id; ?>&amount=<?php echo $amount; ?>">login</a> or
                        <a href="register.php?redirect=donate.php?campaign_id=<?php echo $campaign_id; ?>&amount=<?php echo $amount; ?>">create an account</a>.
                    </div>
                <?php endif; ?>

                <form action="process-donation.php" method="post" id="donation-form" novalidate>
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">

                    <div class="form-group">
                        <label for="amount">Donation Amount (USD)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="1" step="0.01"
                            value="<?php echo $amount; ?>" required placeholder="0.00">
                    </div>

                    <?php if ($show_anonymous_option): ?>
                        <div class="anonymous-option">
                            <input type="checkbox" id="anonymous_donation" name="is_anonymous" value="1">
                            <label for="anonymous_donation">Make this donation anonymous</label>
                            <i class="fas fa-question-circle" title="Your name will not be displayed publicly"></i>
                        </div>
                    <?php endif; ?>

                    <?php if (!$user_logged_in): ?>
                        <div class="personal-info-fields" id="guest-fields">
                            <div class="row">
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required placeholder="John Doe">
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" required placeholder="john@example.com">
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Payment Method</label>
                        <div class="payment-methods">
                            <div class="payment-method" data-method="credit_card">
                                <input type="radio" name="payment_method" value="credit_card" id="credit_card" required hidden>
                                <label for="credit_card">
                                    <i class="fab fa-cc-visa"></i>
                                    <span>Credit Card</span>
                                </label>
                            </div>
                            <div class="payment-method" data-method="debit_card">
                                <input type="radio" name="payment_method" value="debit_card" id="debit_card" hidden>
                                <label for="debit_card">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Debit Card</span>
                                </label>
                            </div>
                            <div class="payment-method" data-method="mobile_banking">
                                <input type="radio" name="payment_method" value="mobile_banking" id="mobile_banking" hidden>
                                <label for="mobile_banking">
                                    <i class="fas fa-mobile-alt"></i>
                                    <span>Mobile Banking</span>
                                </label>
                            </div>
                            <div class="payment-method" data-method="bank_transfer">
                                <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer" hidden>
                                <label for="bank_transfer">
                                    <i class="fas fa-university"></i>
                                    <span>Bank Transfer</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Credit/Debit Card Fields -->
                    <div class="payment-fields" id="card-fields">
                        <div class="card-preview">
                            <div class="card-type">
                                <i class="fab fa-cc-visa"></i>
                            </div>
                            <div class="card-number">
                                **** **** **** 1234
                            </div>
                            <div class="card-details">
                                <div class="card-holder">JOHN DOE</div>
                                <div class="card-expiry">12/25</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="card_number">Card Number</label>
                            <input type="text" class="form-control" id="card_number" name="card_number"
                                placeholder="1234 5678 9012 3456" maxlength="19">
                        </div>

                        <div class="row">
                            <div class="form-group">
                                <label for="expiry">Expiry Date</label>
                                <input type="text" class="form-control" id="expiry" name="expiry"
                                    placeholder="MM/YY" maxlength="5">
                            </div>
                            <div class="form-group">
                                <label for="cvv">CVV</label>
                                <input type="text" class="form-control" id="cvv" name="cvv"
                                    placeholder="123" maxlength="4">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="card_holder">Cardholder Name</label>
                            <input type="text" class="form-control" id="card_holder" name="card_holder"
                                placeholder="As shown on card">
                        </div>
                    </div>

                    <!-- Mobile Banking Fields -->
                    <div class="payment-fields" id="mobile-fields">
                        <div class="form-group">
                            <label for="mobile_provider">Mobile Banking Provider</label>
                            <select class="form-control" id="mobile_provider" name="mobile_provider">
                                <option value="">Select your provider</option>
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
                            <input type="text" class="form-control" id="mobile_number" name="mobile_number"
                                placeholder="01XXXXXXXXX">
                        </div>
                    </div>

                    <!-- Bank Transfer Fields -->
                    <div class="payment-fields" id="bank-fields">
                        <div class="form-group">
                            <label for="bank_name">Bank Name</label>
                            <select class="form-control" id="bank_name" name="bank_name">
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
                            <input type="text" class="form-control" id="account_number" name="account_number"
                                placeholder="Enter your account number">
                        </div>
                        <div class="form-group">
                            <label for="routing_number">Routing Number</label>
                            <input type="text" class="form-control" id="routing_number" name="routing_number"
                                placeholder="Enter routing number">
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="fas fa-lock"></i> Pay Now
                    </button>

                    <p style="text-align: center; margin-top: 15px; font-size: 12px; color: var(--secondary-color);">
                        <i class="fas fa-lock"></i> Your payment is secure and encrypted
                    </p>
                </form>
            </div>
        </div>

        <div class="right-column">
            <div class="payment-card">
                <h2 style="margin-bottom: 20px;">Order Summary</h2>

                <div class="campaign-summary">
                    <div class="campaign-image">
                        <img src="<?php echo htmlspecialchars($campaign['image_url']); ?>" alt="<?php echo htmlspecialchars($campaign['name']); ?>">
                    </div>
                    <div class="campaign-details">
                        <h3><?php echo htmlspecialchars($campaign['name']); ?></h3>
                        <div class="donation-amount">$<span id="display-amount"><?php echo number_format($amount, 2); ?></span></div>
                    </div>
                </div>

                <div class="summary-item">
                    <span>Subtotal</span>
                    <span>$<span id="summary-amount"><?php echo number_format($amount, 2); ?></span></span>
                </div>
                <div class="summary-item">
                    <span>Processing fee</span>
                    <span>$<span id="processing-fee"><?php echo number_format($amount * 0.029 + 0.30, 2); ?></span></span>
                </div>
                <div class="summary-item summary-total">
                    <span>Total</span>
                    <span>$<span id="total-amount"><?php echo number_format($amount + ($amount * 0.029 + 0.30), 2); ?></span></span>
                </div>
            </div>

            <div class="impact-section">
                <h4><i class="fas fa-heart"></i> Your Impact</h4>
                <p>Your donation will help <?php echo htmlspecialchars($campaign['name']); ?> reach its goal. Every contribution brings us closer to creating positive change.</p>
            </div>
        </div>
    </div>

    <script>
        // Global campaign data
        const campaignData = {
            id: <?php echo $campaign_id; ?>,
            name: '<?php echo addslashes($campaign['name']); ?>',
            imageUrl: '<?php echo addslashes($campaign['image_url']); ?>',
            userLoggedIn: <?php echo $user_logged_in ? 'true' : 'false'; ?>,
            showAnonymousOption: <?php echo $show_anonymous_option ? 'true' : 'false'; ?>
        };

        // DOM elements
        const amountInput = document.getElementById('amount');
        const displayAmount = document.getElementById('display-amount');
        const summaryAmount = document.getElementById('summary-amount');
        const processingFee = document.getElementById('processing-fee');
        const totalAmount = document.getElementById('total-amount');
        const paymentMethods = document.querySelectorAll('.payment-method');
        const paymentFields = document.querySelectorAll('.payment-fields');

        // Update amounts when donation amount changes
        amountInput.addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const fee = amount * 0.029 + 0.30;
            const total = amount + fee;

            displayAmount.textContent = amount.toFixed(2);
            summaryAmount.textContent = amount.toFixed(2);
            processingFee.textContent = fee.toFixed(2);
            totalAmount.textContent = total.toFixed(2);
        });

        // Payment method selection
        paymentMethods.forEach(method => {
            method.addEventListener('click', function() {
                // Remove selected class from all methods
                paymentMethods.forEach(m => m.classList.remove('selected'));

                // Add selected class to clicked method
                this.classList.add('selected');

                // Check the radio input
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;

                // Show corresponding payment fields
                const methodType = this.getAttribute('data-method');
                paymentFields.forEach(field => field.classList.remove('active'));

                if (methodType === 'credit_card' || methodType === 'debit_card') {
                    document.getElementById('card-fields').classList.add('active');
                } else if (methodType === 'mobile_banking') {
                    document.getElementById('mobile-fields').classList.add('active');
                } else if (methodType === 'bank_transfer') {
                    document.getElementById('bank-fields').classList.add('active');
                }
            });
        });

        // Format card number input
        const cardNumberInput = document.getElementById('card_number');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                let matches = value.match(/\d{4,16}/g);
                let match = matches && matches[0] || '';
                let parts = [];

                for (let i = 0, len = match.length; i < len; i += 4) {
                    parts.push(match.substring(i, i + 4));
                }

                if (parts.length) {
                    e.target.value = parts.join(' ');
                } else {
                    e.target.value = value;
                }
            });
        }

        // Format expiry date input
        const expiryInput = document.getElementById('expiry');
        if (expiryInput) {
            expiryInput.addEventListener('input', function(e) {
                let value = e.target.value;
                if (value.length === 2 && !value.includes('/')) {
                    e.target.value = value + '/';
                }
            });
        }
    </script>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>