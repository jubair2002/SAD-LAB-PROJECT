<?php
// volunteer_dashboard.php
require_once 'config.php';
require_once 'dashboard_base.php';

checkAccess('volunteer');

// Get user details
$user = getUserDetails($conn, $_SESSION['user_id']);

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboardSummaryVR';
$valid_pages = ['dashboardSummaryVR', 'createCampaign1','assignments', 'message', 'campaign', 'reports', 'emergency', 'settings', 'profile'];

// Validate the page parameter
if (!in_array($current_page, $valid_pages)) {
    $current_page = 'dashboardSummaryVR';
}

// Set the page file to load
$page_file = $current_page . '.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <!-- My CSS -->
    <link rel="stylesheet" href="assets/css/volunteerDashboard.css">

    <style>
        /* Iframe Specific Styles */
        #content-iframe {
            width: 100%;
            border: none;
            min-height: calc(100vh - 100px);
            /* Adjust based on your navbar height */
        }

        .side-menu a {
            cursor: pointer;
        }

        /* Override colors to match design */
        :root {
            --light: #F9F9F9;
            --green: #4CAF50;
            --light-green: #e8f5e9;
            --grey: #eee;
            --dark-grey: #AAAAAA;
            --dark: #342E37;
            --red: #DB504A;
        }

        /* Override blue with green */
        #sidebar .brand {
            color: var(--green);
        }

        #sidebar .side-menu.top li.active a {
            color: var(--green);
        }

        #sidebar .side-menu.top li a:hover {
            color: var(--green);
        }

        #content nav .nav-link:hover {
            color: var(--green);
        }

        #content main .head-title .left .breadcrumb li a.active {
            color: var(--green);
        }

        #content main .head-title .btn-download {
            background: var(--green);
        }

        #content main .box-info li:nth-child(1) .bx {
            background: var(--light-green);
            color: var(--green);
        }

        #content main .table-data .order table tr td .status.completed {
            background: var(--green);
        }

        #content main .table-data .todo .todo-list li.completed {
            border-left: 10px solid var(--green);
        }
    </style>

    <title>CrisisLink Volunteer</title>
</head>

<body>
    <!-- SIDEBAR -->
    <section id="sidebar">
        <a href="#" class="brand">
            <i class='bx bxs-happy'></i>
            <span class="text">CrisisLink Network</span>
        </a>
        <ul class="side-menu top">
            <li <?php echo ($current_page == 'dashboardSummaryVR') ? 'class="active"' : ''; ?>>
                <a href="?page=dashboardSummaryVR">
                    <i class='bx bxs-home'></i>
                    <span class="text">Dashboard Summary</span>
                </a>
            </li>
            <li <?php echo ($current_page == 'assignments') ? 'class="active"' : ''; ?>>
                <a href="?page=assignments">
                    <i class='bx bxs-stopwatch'></i>
                    <span class="text">Assignment</span>
                </a>
            </li>
            <li <?php echo ($current_page == 'message') ? 'class="active"' : ''; ?>>
                <a href="?page=message">
                    <i class='bx bxs-message-dots'></i>
                    <span class="text">Message</span>
                </a>
            </li>
            <li <?php echo ($current_page == 'campaign') ? 'class="active"' : ''; ?>>
                <a href="?page=campaign">
                    <i class='bx bxs-megaphone'></i>
                    <span class="text">Campaign</span>
                </a>
            </li>
            <li <?php echo ($current_page == 'createCampaign1') ? 'class="active"' : ''; ?>>
                <a href="?page=createCampaign1">
                    <i class='bx bxs-megaphone'></i>
                    <span class="text">Create Campaign</span>
                </a>
            </li>
            <li <?php echo ($current_page == 'reports') ? 'class="active"' : ''; ?>>
                <a href="?page=reports">
                    <i class='bx bxs-file'></i>
                    <span class="text">Report</span>
                </a>
            </li>
            <li <?php echo ($current_page == 'emergency') ? 'class="active"' : ''; ?>>
                <a href="?page=emergency">
                    <i class='bx bxs-first-aid'></i>
                    <span class="text">Emergency Help</span>
                </a>
            </li>
        </ul>
        <ul class="side-menu">
            <li <?php echo ($current_page == 'settings') ? 'class="active"' : ''; ?>>
                <a href="?page=settings">
                    <i class='bx bxs-cog'></i>
                    <span class="text">Settings</span>
                </a>
            </li>
            <li <?php echo ($current_page == 'profile') ? 'class="active"' : ''; ?>>
                <a href="?page=profile">
                    <i class='bx bxs-user'></i>
                    <span class="text">Profile</span>
                </a>
            </li>
            <li>
                <a href="logout.php" class="logout">
                    <i class='bx bxs-exit'></i>
                    <span class="text">Logout</span>
                </a>
            </li>
        </ul>
    </section>
    <!-- SIDEBAR -->

    <!-- CONTENT -->
    <section id="content">
        <!-- NAVBAR -->
         <nav>
            <i class='bx bx-menu'></i>
            <div style="flex-grow: 1;"></div>
            <a href="#" class="notification" id="notification-bell">
                <i class='bx bxs-bell'></i>
                <span class="notification-badge" id="notification-badge" style="display: none;">0</span>
                <!-- Add notification popup -->
                <div class="notification-popup" id="notification-popup">
                    <div class="notification-header">
                        Notifications
                    </div>
                    <div class="notification-list" id="notification-content">
                        <!-- Notifications will be loaded here -->
                    </div>
                </div>
            </a>
            <a class="profile">
                <?php if (!empty($user['picture'])): ?>
                    <img src="<?php echo htmlspecialchars($user['picture']); ?>" alt="Profile" class="profile-pic">
                <?php else: ?>
                    <i class='bx bxs-user-circle'></i>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($user['fname']); ?> (Volunteer)</span>
            </a>
        </nav>
        <!-- NAVBAR -->

        <!-- MAIN - Replace with iframe -->
        <main>
            <iframe id="content-iframe" src="<?php echo htmlspecialchars($page_file); ?>" frameborder="0"></iframe>
        </main>
    </section>
    <!-- CONTENT -->

    <script>
        // TOGGLE SIDEBAR
        const menuBar = document.querySelector('#content nav .bx.bx-menu');
        const sidebar = document.getElementById('sidebar');

        menuBar.addEventListener('click', function() {
            sidebar.classList.toggle('hide');
        });

        // RESPONSIVE BEHAVIOR
        if (window.innerWidth < 768) {
            sidebar.classList.add('hide');
        }

        window.addEventListener('resize', function() {
            if (this.innerWidth < 768) {
                sidebar.classList.add('hide');
            }
        });

        // Optional: Resize iframe to content
        document.getElementById('content-iframe').addEventListener('load', function() {
            try {
                this.style.height = this.contentWindow.document.body.scrollHeight + 'px';
            } catch (e) {
                console.log('Could not resize iframe');
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.getElementById('notification-bell');
    const notificationPopup = document.getElementById('notification-popup');
    const notificationContent = document.getElementById('notification-content');
    const notificationBadge = document.getElementById('notification-badge');
    
    // Load initial notification count
    loadNotificationCount();
    
    // Toggle notification popup
    notificationBell.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (notificationPopup.classList.contains('show')) {
            notificationPopup.classList.remove('show');
        } else {
            loadNotifications();
            notificationPopup.classList.add('show');
        }
    });
    
    // Close popup when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationBell.contains(e.target)) {
            notificationPopup.classList.remove('show');
        }
    });
    
    // Load notifications via AJAX
    function loadNotifications() {
        fetch('get_notifications.php')
            .then(response => response.text())
            .then(data => {
                notificationContent.innerHTML = data;
                // Update count after loading notifications
                loadNotificationCount();
            })
            .catch(error => {
                notificationContent.innerHTML = '<div style="padding: 20px; text-align: center; color: #777;">Error loading notifications</div>';
            });
    }
    
    // Load notification count
    function loadNotificationCount() {
        fetch('get_notification_count.php')
            .then(response => response.json())
            .then(data => {
                updateNotificationBadge(data.unread_count);
            })
            .catch(error => {
                console.error('Error loading notification count:', error);
            });
    }
    
    // Update notification badge
    function updateNotificationBadge(count) {
        if (count > 0) {
            notificationBadge.textContent = count > 99 ? '99+' : count;
            notificationBadge.style.display = 'flex';
        } else {
            notificationBadge.style.display = 'none';
        }
    }
    
    // Mark notification as read via AJAX
    function markAsRead(notificationId, element) {
        fetch('mark_notifications_read.php?id=' + notificationId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the unread styling
                    const notificationItem = element.closest('.notification-item');
                    notificationItem.classList.remove('unread');
                    
                    // Hide the "Mark as read" link
                    element.style.display = 'none';
                    
                    // Update the notification count badge
                    loadNotificationCount();
                } else {
                    alert('Error marking notification as read');
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
                alert('Error marking notification as read');
            });
    }
    
    // Make markAsRead function global so onclick can access it
    window.markAsRead = markAsRead;
    
    // Refresh notification count every 30 seconds
    setInterval(loadNotificationCount, 30000);
});
    </script>
</body>

</html>