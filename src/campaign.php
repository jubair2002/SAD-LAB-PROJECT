<?php
// Include the database configuration file
require_once 'config.php';

//  fetch all campaigns
$sql = "SELECT * FROM campaigns ORDER BY created_at DESC LIMIT 6";
$result = $conn->query($sql);

//calculate percentage
function calculatePercentage($raised, $goal)
{
    return min(100, round(($raised / $goal) * 100));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0, minimum-scale=1.0">
    <title>Global Impact Initiatives</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/campaign.css">
    <style>
        html,
        body {
            height: auto;
            width: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .container {
            margin: 0 auto;
            padding: 20px;
            height: auto;
            overflow-y: visible;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        body {
            background-color: #f9f9f9;
            color: #333;
            line-height: 1.6;
            position: relative;
            width: 100%;
            max-width: 100%;
        }

        .cause-card {
            position: relative;
        }

        .campaign-status {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 10;
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.95);
            padding: 6px 12px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            font-size: 12px;
            font-weight: 600;
        }

        .status-icon {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-left: 6px;
            animation: pulse 2s infinite;
        }

        .status-active {
            background-color: #2ecc71;
            /* Green for active */
        }

        .status-end {
            background-color: #e74c3c;
            /* Red for completed */
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-heart"></i> Latest Campaigns</h1>
            <a href="allCampaign.php" class="all-causes-btn">
                <i class="fas fa-list"></i> All Campaigns
            </a>
        </div>

        <div class="causes-grid">
            <?php
            // Check if there are campaigns in the database
            if ($result->num_rows > 0) {
                // Loop through each campaign
                while ($row = $result->fetch_assoc()) {
                    // Calculate progress percentage
                    $percentage = calculatePercentage($row['raised'], $row['goal']);

                    // Determine status and color
                    $status = 'Ongoing';
                    $statusClass = 'status-active';

                    if ($row['progress'] == 'end') {
                        $status = 'Ended';
                        $statusClass = 'status-end';
                    }
            ?>
                    <div class="cause-card">
                        <div class="campaign-status">
                            <span><?php echo $status; ?></span>
                            <div class="status-icon <?php echo $statusClass; ?>"></div>
                        </div>
                        <div class="cause-image">
                            <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width:100%; height:auto;">
                        </div>
                        <div class="cause-content">
                            <h3 class="cause-title"><?php echo htmlspecialchars($row['name']); ?></h3>
                            <p class="cause-description"><?php echo htmlspecialchars(substr($row['description'], 0, 120)) . '...'; ?></p>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $percentage; ?>%; background: <?php echo isset($row['progress_color']) ? $row['progress_color'] : 'linear-gradient(90deg, #4CAF50, #45a049)'; ?>"></div>
                            </div>
                            <div class="cause-stats">
                                <div class="stats-left">
                                    <div class="goal">Goal: $<?php echo number_format($row['goal'], 0); ?></div>
                                    <div class="raised">Raised: $<?php echo number_format($row['raised'], 0); ?></div>
                                </div>
                                <div class="stats-right">
                                    <div class="donors"><?php echo $row['donation_count']; ?> donations</div>
                                </div>
                            </div>
                            <div class="spacer"></div>
                            <a href="campaign-details.php?id=<?php echo $row['id']; ?>" class="view-details-btn">
                                <i class="fas fa-eye"></i> VIEW DETAILS
                            </a>
                        </div>
                    </div>
            <?php
                }
            } else {
                echo "<div class='no-campaigns'>";
                echo "<i class='fas fa-exclamation-circle' style='font-size: 48px; margin-bottom: 20px; color: #95a5a6;'></i>";
                echo "<h3>No campaigns found</h3>";
                echo "<p>Check back later for new campaigns and opportunities to make a difference.</p>";
                echo "</div>";
            }
            ?>
        </div>
    </div>

    <!-- Pass PHP variables to JavaScript -->
    <script>
        // Global variables from PHP
        const campaignsData = {
            total: <?php echo $result->num_rows; ?>,
            displayed: 6
        };

        // Full screen handling
        document.addEventListener('DOMContentLoaded', function() {
            setupFullScreenHandling();
            initializeAnimations();
        });

        function setupFullScreenHandling() {
            // Prevent zoom and ensure full screen
            document.addEventListener('touchstart', function(event) {
                if (event.touches.length > 1) {
                    event.preventDefault();
                }
            });

            let lastTouchEnd = 0;
            document.addEventListener('touchend', function(event) {
                const now = (new Date()).getTime();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);



            // Handle orientation change
            window.addEventListener('orientationchange', function() {});
        }



        function initializeAnimations() {
            // Add intersection observer for card animations
            const cards = document.querySelectorAll('.cause-card');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, {
                threshold: 0.1
            });

            cards.forEach(card => {
                observer.observe(card);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeAnimations();
        });

        // Add loading state to view details buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.view-details-btn')) {
                const btn = e.target.closest('.view-details-btn');
                const originalContent = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                btn.style.pointerEvents = 'none';

                // Reset if navigation fails
                setTimeout(() => {
                    btn.innerHTML = originalContent;
                    btn.style.pointerEvents = 'auto';
                }, 3000);
            }
        });
    </script>
</body>

</html>
<?php
// Close the database connection
$conn->close();
?>