<?php
require_once 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: campaign.php');
    exit;
}

$campaign_id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM campaigns WHERE id = ?");
$stmt->bind_param("i", $campaign_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: campaign.php');
    exit;
}

$campaign = $result->fetch_assoc();

function calculatePercentage($raised, $goal)
{
    return min(100, round(($raised / $goal) * 100));
}
$percentage = calculatePercentage($campaign['raised'], $campaign['goal']);

$end_date = new DateTime($campaign['end_date']);
$today = new DateTime();
$days_remaining = $today <= $end_date ? $today->diff($end_date)->days : 0;

// Get relief allocations for this campaign
$allocations_stmt = $conn->prepare("
    SELECT rc.category_name, SUM(ca.allocated_amount) as total_amount
    FROM campaign_allocations ca
    JOIN relief_categories rc ON ca.category_id = rc.id
    WHERE ca.campaign_id = ?
    GROUP BY rc.category_name
    ORDER BY total_amount DESC
");
$allocations_stmt->bind_param("i", $campaign_id);
$allocations_stmt->execute();
$allocations_result = $allocations_stmt->get_result();
$category_allocations = [];

while ($row = $allocations_result->fetch_assoc()) {
    $category_allocations[] = $row;
}

// Calculate remaining funds and allocation percentage
$remaining_funds = $campaign['raised'] - $campaign['allocated'];
$allocation_percentage = $campaign['raised'] > 0 ? min(100, round(($campaign['allocated'] / $campaign['raised']) * 100)) : 0;

// Get volunteers assigned to this campaign
$volunteers_stmt = $conn->prepare("
    SELECT a.id, a.task_name, a.priority, a.status, a.deadline, 
           u.id as user_id, u.fname, u.lname, u.email, u.picture,
           COUNT(t.id) as task_count
    FROM assignments a
    JOIN users u ON a.volunteer_id = u.id
    LEFT JOIN tasks t ON a.id = t.assignment_id
    WHERE a.campaign_id = ?
    GROUP BY a.id
    ORDER BY a.created_at DESC
");
$volunteers_stmt->bind_param("i", $campaign_id);
$volunteers_stmt->execute();
$volunteers_result = $volunteers_stmt->get_result();
$assigned_volunteers = [];

while ($row = $volunteers_result->fetch_assoc()) {
    $assigned_volunteers[] = $row;
}

// Get total counts for volunteer stats
$volunteer_counts = [
    'total' => count($assigned_volunteers),
    'completed' => 0,
    'in_progress' => 0,
    'not_started' => 0
];

foreach ($assigned_volunteers as $volunteer) {
    if ($volunteer['status'] === 'completed') {
        $volunteer_counts['completed']++;
    } elseif ($volunteer['status'] === 'in-progress') {
        $volunteer_counts['in_progress']++;
    } else {
        $volunteer_counts['not_started']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <title><?php echo htmlspecialchars($campaign['name']); ?> - Campaign Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/campaign-details.css">
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-hands-helping"></i> Campaign Details</h1>
            <a href="campaign.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Campaigns
            </a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Campaign Image Section -->
            <section class="image-section">
                <div class="campaign-image-container">
                    <img src="<?php echo htmlspecialchars($campaign['image_url']); ?>" alt="<?php echo htmlspecialchars($campaign['name']); ?>" class="campaign-image">
                    <div class="image-overlay">
                        <div class="campaign-title">
                            <h1><?php echo htmlspecialchars($campaign['name']); ?></h1>
                            <span class="campaign-category"><?php echo htmlspecialchars($campaign['category']); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Campaign Story Section -->
            <section class="story-section">
                <div class="section-container">
                    <h2 class="section-title"><i class="fas fa-book-open"></i> Campaign Story</h2>
                    <div class="story-content">
                        <div class="campaign-stats-row">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $percentage; ?>%</div>
                                <div class="stat-label">Funded</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">$<?php echo number_format($campaign['raised'], 0); ?></div>
                                <div class="stat-label">Raised</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">$<?php echo number_format($campaign['goal'], 0); ?></div>
                                <div class="stat-label">Goal</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $campaign['donation_count']; ?></div>
                                <div class="stat-label">Donors</div>
                            </div>
                        </div>

                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>

                        <div class="story-text">
                            <h3>About This Campaign</h3>
                            <p><?php echo nl2br(htmlspecialchars($campaign['description'])); ?></p>

                            <h4>Our Goals</h4>
                            <ul>
                                <li>Provide immediate relief to those affected by the crisis</li>
                                <li>Establish sustainable support systems for long-term recovery</li>
                                <li>Build community resilience and preparedness for future challenges</li>
                                <li>Coordinate with local authorities and organizations for maximum impact</li>
                            </ul>

                            <h4>How Your Donation Helps</h4>
                            <p>Every dollar you contribute goes directly toward:</p>
                            <ul>
                                <li><strong>$25</strong> - Provides essential supplies for one family for a day</li>
                                <li><strong>$50</strong> - Covers emergency shelter for one person for a week</li>
                                <li><strong>$100</strong> - Supplies medical aid and first-aid kits for emergency response</li>
                                <li><strong>$250</strong> - Funds comprehensive support package for one family</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Relief Fund Allocation Section -->
            <section class="allocation-section">
                <div class="section-container">
                    <h2 class="section-title"><i class="fas fa-chart-pie"></i> Relief Fund Allocations</h2>
                    <div class="allocation-content">
                        <div class="allocation-header">
                            <div class="allocation-summary">
                                <span class="allocation-percentage"><?php echo $allocation_percentage; ?>% of raised funds allocated</span>
                            </div>
                        </div>

                        <div class="allocation-stats">
                            <div class="allocation-stat">
                                <div class="allocation-stat-value">$<?php echo number_format($campaign['allocated'], 0); ?></div>
                                <div class="allocation-stat-label">Total Allocated</div>
                            </div>
                            <div class="allocation-stat">
                                <div class="allocation-stat-value">$<?php echo number_format($remaining_funds, 0); ?></div>
                                <div class="allocation-stat-label">Funds Available</div>
                            </div>
                            <div class="allocation-stat">
                                <div class="allocation-stat-value"><?php echo count($category_allocations); ?></div>
                                <div class="allocation-stat-label">Categories</div>
                            </div>
                        </div>

                        <div class="allocation-progress">
                            <div class="allocation-progress-fill" style="width: <?php echo $allocation_percentage; ?>%"></div>
                        </div>

                        <?php if (count($category_allocations) > 0): ?>
                            <div class="allocation-categories">
                                <?php foreach ($category_allocations as $allocation):
                                    $percentage = ($allocation['total_amount'] / $campaign['allocated']) * 100;
                                ?>
                                    <div class="allocation-category">
                                        <div class="allocation-category-header">
                                            <span class="category-name"><?php echo htmlspecialchars($allocation['category_name']); ?></span>
                                            <span class="category-amount">$<?php echo number_format($allocation['total_amount'], 0); ?></span>
                                        </div>
                                        <div class="allocation-category-bar">
                                            <div class="allocation-category-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-allocations">
                                <i class="fas fa-info-circle"></i>
                                <p>No allocations have been made yet.</p>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_type'] === 'admin' || $_SESSION['user_role'] === 'admin')): ?>
                            <div class="admin-actions">
                                <a href="relief.php?campaign_id=<?php echo $campaign_id; ?>" class="admin-btn">
                                    <i class="fas fa-cog"></i> Manage Allocations
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Make Donation Section -->
            <section class="donation-section">
                <div class="section-container">
                    <h2 class="section-title"><i class="fas fa-heart"></i> Make a Donation</h2>
                    <div class="donation-content">
                        <p class="donation-description">Your support makes a real difference. Choose an amount below or enter your own.</p>
                        
                        <div class="donation-amounts">
                            <button type="button" class="amount-btn" data-amount="25">$25</button>
                            <button type="button" class="amount-btn" data-amount="50">$50</button>
                            <button type="button" class="amount-btn" data-amount="100">$100</button>
                            <button type="button" class="amount-btn" data-amount="250">$250</button>
                            <button type="button" class="amount-btn custom-btn">Custom</button>
                        </div>
                        
                        <div class="custom-amount-container" style="display: none;">
                            <input type="number" class="custom-amount-input" placeholder="Enter amount" min="1">
                        </div>
                        
                        <a href="donate.php?campaign_id=<?php echo $campaign['id']; ?>&amount=50" class="donate-btn" id="donateBtn">
                            <i class="fas fa-heart"></i> DONATE NOW  $50
                        </a>
                    </div>
                </div>
            </section>

            <!-- Volunteers Section -->
            <section class="volunteers-section">
                <div class="section-container">
                    <h2 class="section-title"><i class="fas fa-users"></i> Volunteers</h2>
                    <div class="volunteers-content">
                        <?php if (count($assigned_volunteers) > 0): ?>
                            <div class="volunteer-stats-summary">
                                <div class="volunteer-summary-card">
                                    <span class="summary-number"><?php echo $volunteer_counts['total']; ?></span>
                                    <span class="summary-label">Total Volunteers</span>
                                </div>
                                <div class="volunteer-summary-card">
                                    <span class="summary-number"><?php echo $volunteer_counts['completed']; ?></span>
                                    <span class="summary-label">Completed</span>
                                </div>
                                <div class="volunteer-summary-card">
                                    <span class="summary-number"><?php echo $volunteer_counts['in_progress']; ?></span>
                                    <span class="summary-label">In Progress</span>
                                </div>
                            </div>

                            <div class="volunteers-list">
                                <?php foreach ($assigned_volunteers as $volunteer): ?>
                                    <div class="volunteer-card">
                                        <div class="volunteer-avatar">
                                            <img src="<?php echo !empty($volunteer['picture']) ? htmlspecialchars($volunteer['picture']) : 'assets/images/default-avatar.png'; ?>"
                                                alt="<?php echo htmlspecialchars($volunteer['fname'] . ' ' . $volunteer['lname']); ?>">
                                        </div>
                                        <div class="volunteer-info">
                                            <div class="volunteer-name"><?php echo htmlspecialchars($volunteer['fname'] . ' ' . $volunteer['lname']); ?></div>
                                            <div class="volunteer-task"><?php echo htmlspecialchars($volunteer['task_name']); ?></div>
                                            <div class="volunteer-meta">
                                                <span class="volunteer-deadline">
                                                    <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($volunteer['deadline'])); ?>
                                                </span>
                                                <?php if ($volunteer['task_count'] > 0): ?>
                                                    <span class="task-count"><?php echo $volunteer['task_count']; ?> subtasks</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="volunteer-badges">
                                            <span class="priority-badge priority-<?php echo strtolower($volunteer['priority']); ?>">
                                                <?php echo ucfirst($volunteer['priority']); ?>
                                            </span>
                                            <span class="status-badge status-<?php echo str_replace(' ', '-', strtolower($volunteer['status'])); ?>">
                                                <?php echo ucfirst(str_replace('-', ' ', $volunteer['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-volunteers">
                                <i class="fas fa-user-friends"></i>
                                <h3>No Volunteers Assigned Yet</h3>
                                <p>This campaign is looking for volunteers to help with various tasks.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Updates Section -->
            <section class="updates-section">
                <div class="section-container">
                    <h2 class="section-title"><i class="fas fa-newspaper"></i> Campaign Updates</h2>
                    <div class="updates-content">
                        <div class="update-item">
                            <div class="update-date">March 15, 2025</div>
                            <div class="update-content">
                                <h3>Major Milestone Reached</h3>
                                <p>We're excited to announce that we've reached <?php echo $percentage; ?>% of our fundraising goal! Thanks to the incredible generosity of our donors, we've been able to provide immediate assistance to hundreds of families affected by this crisis.</p>
                            </div>
                        </div>
                        <div class="update-item">
                            <div class="update-date">March 10, 2025</div>
                            <div class="update-content">
                                <h3>Distribution Center Established</h3>
                                <p>Our team has successfully established a distribution center in the affected area. We're now able to provide direct assistance and coordinate relief efforts more effectively.</p>
                            </div>
                        </div>
                        <div class="update-item">
                            <div class="update-date">March 5, 2025</div>
                            <div class="update-content">
                                <h3>Campaign Launch</h3>
                                <p>We've officially launched this emergency relief campaign. Our initial assessment shows urgent need for shelter, food, medical supplies, and long-term support for affected communities.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Pass PHP variables to JavaScript -->
    <script>
        // Global variables from PHP
        const campaignData = {
            id: <?php echo $campaign['id']; ?>,
            name: '<?php echo addslashes($campaign['name']); ?>',
            raised: <?php echo $campaign['raised']; ?>,
            goal: <?php echo $campaign['goal']; ?>,
            allocated: <?php echo $campaign['allocated']; ?>,
            percentage: <?php echo $percentage; ?>,
            allocationPercentage: <?php echo $allocation_percentage; ?>,
            donationCount: <?php echo $campaign['donation_count']; ?>
        };

        // Donation amount selection
        document.addEventListener('DOMContentLoaded', function() {
            const amountBtns = document.querySelectorAll('.amount-btn');
            const customBtn = document.querySelector('.custom-btn');
            const customContainer = document.querySelector('.custom-amount-container');
            const customInput = document.querySelector('.custom-amount-input');
            const donateBtn = document.getElementById('donateBtn');
            let selectedAmount = 50;

            amountBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    amountBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    if (this.classList.contains('custom-btn')) {
                        customContainer.style.display = 'block';
                        customInput.focus();
                    } else {
                        customContainer.style.display = 'none';
                        selectedAmount = parseInt(this.dataset.amount);
                        updateDonateButton();
                    }
                });
            });

            customInput.addEventListener('input', function() {
                selectedAmount = parseInt(this.value) || 0;
                updateDonateButton();
            });

            function updateDonateButton() {
                donateBtn.innerHTML = `<i class="fas fa-heart"></i> DONATE NOW - $${selectedAmount}`;
                donateBtn.href = `donate.php?campaign_id=${campaignData.id}&amount=${selectedAmount}`;
            }

            // Set default active button
            document.querySelector('[data-amount="50"]').classList.add('active');
        });
    </script>
</body>

</html>

<?php
$stmt->close();
$allocations_stmt->close();
$volunteers_stmt->close();
$conn->close();
?>