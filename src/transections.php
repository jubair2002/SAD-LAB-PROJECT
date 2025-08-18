<?php
// Database connection
require_once 'config.php';

// Function to generate realistic transaction ID
function generateTransactionId($id) {
    $prefix = 'TRX-';
    $random = str_pad($id, 12, '0', STR_PAD_LEFT);
    return $prefix . $random;
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'all';

// Base query
// Changed INNER JOIN to LEFT JOIN to include donations with NULL user_id (anonymous donations)
// Added d.is_anonymous to the SELECT clause
$query = "SELECT d.id, d.amount, d.donation_type, d.donation_date, d.is_anonymous,
                 CONCAT(u.fname, ' ', u.lname) as user_name, u.email, u.phone, u.picture,
                 c.name as campaign_name
          FROM donations d
          LEFT JOIN users u ON d.user_id = u.id
          JOIN campaigns c ON d.campaign_id = c.id";

// Apply filter
if ($filter !== 'all') {
    switch ($filter) {
        case 'today':
            $query .= " WHERE DATE(d.donation_date) = CURDATE()";
            break;
        case 'week':
            $query .= " WHERE YEARWEEK(d.donation_date, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $query .= " WHERE MONTH(d.donation_date) = MONTH(CURDATE()) AND YEAR(d.donation_date) = YEAR(CURDATE())";
            break;
        case 'mobile_banking':
        case 'credit_card':
        case 'debit_card':
        case 'bank_transfer':
            $query .= " WHERE d.donation_type = '$filter'";
            break;
        case 'anonymous': // New filter for anonymous donations
            $query .= " WHERE d.is_anonymous = TRUE";
            break;
        case 'registered': // New filter for registered user donations
            $query .= " WHERE d.is_anonymous = FALSE";
            break;
    }
}

$query .= " ORDER BY d.donation_date DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
    <style>
        * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
    color: #333;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.app-container {
    display: flex;
    flex: 1;
    min-height: 0; /* Crucial for proper flexbox scrolling */
}

.main-content {
    flex: 1;
    padding: 20px;
    display: flex;
    flex-direction: column;
    min-height: 0; /* Crucial for proper flexbox scrolling */
    overflow: hidden;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    margin-bottom: 20px;
    border-bottom: 1px solid #e0e0e0;
    flex-shrink: 0; /* Prevent header from shrinking */
}

.header h1 {
    font-size: 24px;
    color: #2c3e50;
}

.transaction-table-container {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 0; /* Crucial for proper flexbox scrolling */
}

.transaction-table {
    width: 100%;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

table {
    width: 100%;
    border-collapse: collapse;
    flex: 1;
    display: flex;
    flex-direction: column;
}

thead {
    display: table;
    width: 100%;
    table-layout: fixed;
}

tbody {
    flex: 1;
    display: block;
    overflow-y: auto;
    width: 100%;
}

tr {
    display: table;
    width: 100%;
    table-layout: fixed;
}

th {
    background-color: #f1f5f9;
    padding: 15px 20px;
    text-align: left;
    font-weight: 600;
    color: #475569;
    border-bottom: 2px solid #e2e8f0;
}

td {
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

tr:last-child td {
    border-bottom: none;
}

tr:hover {
    background-color: #f8fafc;
}

.transaction-id {
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: #1e40af;
}

.user-info {
    display: flex;
    align-items: center;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    overflow: hidden;
    flex-shrink: 0;
    color: #64748b; /* For initials */
    font-weight: bold;
    font-size: 14px;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-details {
    line-height: 1.4;
    min-width: 0; /* Prevent text overflow */
}

.user-name {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-email {
    font-size: 13px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.campaign-name {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.amount {
    font-weight: 600;
    color: #16a34a;
}

.donation-type {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: capitalize;
    white-space: nowrap;
}

.mobile_banking { background-color: #dcfce7; color: #166534; }
.credit_card { background-color: #dbeafe; color: #1e40af; }
.debit_card { background-color: #fef3c7; color: #92400e; }
.bank_transfer { background-color: #f3e8ff; color: #6b21a8; }

.transaction-date {
    color: #64748b;
    font-size: 14px;
    white-space: nowrap;
}

.filter-dropdown {
    padding: 10px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background-color: white;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-dropdown:hover {
    border-color: #cbd5e1;
}

.no-results {
    padding: 40px;
    text-align: center;
    color: #64748b;
    display: table-cell;
    vertical-align: middle;
}

/* Hide scrollbar but keep functionality */
tbody::-webkit-scrollbar {
    display: none;
    width: 0;
}

tbody {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .header h1 {
        font-size: 20px;
    }
    
    th, td {
        padding: 12px 15px;
    }
    
    .user-avatar {
        width: 32px;
        height: 32px;
        margin-right: 8px;
    }
    
    .user-name {
        font-size: 14px;
    }
    
    .user-email {
        font-size: 12px;
    }
    
    .donation-type {
        font-size: 11px;
        padding: 4px 8px;
    }
    
    .transaction-date {
        font-size: 13px;
    }
}
    </style>
</head>
<body>
    <div class="app-container">
        <div class="main-content">
            <div class="header">
                <h1>Transaction History</h1>
                <form method="get">
                    <select class="filter-dropdown" name="filter" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Transactions</option>
                        <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="mobile_banking" <?php echo $filter === 'mobile_banking' ? 'selected' : ''; ?>>Mobile Banking</option>
                        <option value="credit_card" <?php echo $filter === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="debit_card" <?php echo $filter === 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                        <option value="bank_transfer" <?php echo $filter === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="anonymous" <?php echo $filter === 'anonymous' ? 'selected' : ''; ?>>Anonymous Donations</option>
                        <option value="registered" <?php echo $filter === 'registered' ? 'selected' : ''; ?>>Registered User Donations</option>
                    </select>
                </form>
            </div>
            
            <div class="transaction-table-container">
                <div class="transaction-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Donor</th>
                                <th>Campaign</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): 
                                    $transactionId = generateTransactionId($row['id']);
                                    // Determine donor name and email based on is_anonymous flag
                                    $donor_name_display = $row['is_anonymous'] == 1 ? 'Anonymous Donor' : htmlspecialchars($row['user_name']);
                                    $donor_email_display = $row['is_anonymous'] == 1 ? 'N/A' : htmlspecialchars($row['email']);
                                    $donor_picture_display = $row['is_anonymous'] == 1 ? '' : htmlspecialchars($row['picture']);
                                ?>
                                <tr>
                                    <td class="transaction-id"><?php echo $transactionId; ?></td>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php if (!empty($donor_picture_display)): ?>
                                                    <img src="<?php echo $donor_picture_display; ?>" alt="User Avatar">
                                                <?php else: ?>
                                                    <?php 
                                                        // Display initials or a default icon for anonymous/no picture
                                                        if ($row['is_anonymous'] == 1) {
                                                            echo '<i class="fas fa-mask"></i>'; // Icon for anonymous
                                                        } else {
                                                            $initials = '';
                                                            if (!empty($row['user_name'])) {
                                                                $parts = explode(' ', $row['user_name']);
                                                                foreach ($parts as $part) {
                                                                    if (!empty($part)) {
                                                                        $initials .= strtoupper(substr($part, 0, 1));
                                                                    }
                                                                }
                                                                $initials = substr($initials, 0, 2); // Limit to two initials
                                                            } else {
                                                                $initials = '?'; // Fallback if no name
                                                            }
                                                            echo $initials;
                                                        }
                                                    ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-details">
                                                <div class="user-name"><?php echo $donor_name_display; ?></div>
                                                <div class="user-email"><?php echo $donor_email_display; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="campaign-name"><?php echo htmlspecialchars($row['campaign_name']); ?></td>
                                    <td class="amount">$<?php echo number_format($row['amount'], 2); ?></td>
                                    <td>
                                        <span class="donation-type <?php echo $row['donation_type']; ?>">
                                            <?php echo str_replace('_', ' ', ucfirst($row['donation_type'])); ?>
                                        </span>
                                    </td>
                                    <td class="transaction-date"><?php echo date('M j, Y h:i A', strtotime($row['donation_date'])); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="no-results">
                                        No transactions found matching your criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
