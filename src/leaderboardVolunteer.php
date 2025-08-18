<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth.php");
    exit();
}

function getCurrentUserPerformance($conn, $user_id) {
    $query = "SELECT 
                u.id, u.fname, u.lname, u.picture, u.location, u.created_at as join_date,
                COUNT(a.id) as total_assignments,
                COALESCE(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END), 0) as total_completed,
                COALESCE(SUM(CASE WHEN a.status = 'completed' AND (a.completed_at IS NOT NULL AND a.completed_at <= a.deadline) THEN 1 ELSE 0 END), 0) as completed_on_time,
                COALESCE(SUM(CASE WHEN a.status IN ('assigned', 'in-progress', 'not-started') 
                          AND (a.volunteer_response IS NULL OR a.volunteer_response != 'rejected') THEN 1 ELSE 0 END), 0) as pending_tasks,
                COALESCE(SUM(CASE WHEN a.status = 'rejected' OR a.volunteer_response = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_tasks,
                COALESCE(SUM(CASE WHEN a.status = 'completed' AND (a.completed_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)) THEN 1 ELSE 0 END), 0) as recent_completed
              FROM users u
              LEFT JOIN assignments a ON u.id = a.volunteer_id
              WHERE u.id = ? AND u.user_type = 'volunteer'
              GROUP BY u.id";

    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Database error: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }
    
    $stmt->close();
    return null;
}

function calculatePerformanceScore($data) {
    if ($data['total_assignments'] == 0) return 0;
    
    $completion_rate = $data['total_completed'] / $data['total_assignments'];
    $on_time_rate = $data['completed_on_time'] / max($data['total_completed'], 1);
    $rejection_penalty = $data['rejected_tasks'] / max($data['total_assignments'], 1);
    $recent_activity = min($data['recent_completed'], 10) / 10;
    
    $score = (
        ($completion_rate * 40) +
        ($on_time_rate * 35) +
        ($recent_activity * 15) +
        (max(0, 1 - $rejection_penalty) * 10)
    );
    
    return round($score, 1);
}

function calculateConsistencyScore($data) {
    if ($data['total_assignments'] < 3) return 0;
    
    $completion_consistency = $data['total_completed'] / $data['total_assignments'];
    $on_time_consistency = $data['completed_on_time'] / max($data['total_completed'], 1);
    $quality_consistency = 1 - ($data['rejected_tasks'] / max($data['total_assignments'], 1));
    
    return round(($completion_consistency + $on_time_consistency + $quality_consistency) / 3 * 100, 1);
}

function getLeaderboard($conn, $current_user_id) {
   $query = "SELECT 
                u.id, u.fname, u.lname, u.picture, u.location, u.created_at as join_date,
                COUNT(a.id) as total_assignments,
                COALESCE(SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END), 0) as total_completed,
                COALESCE(SUM(CASE WHEN a.status = 'completed' AND (a.completed_at IS NOT NULL AND a.completed_at <= a.deadline) THEN 1 ELSE 0 END), 0) as completed_on_time,
                COALESCE(SUM(CASE WHEN a.status IN ('assigned', 'in-progress', 'not-started') 
                          AND (a.volunteer_response IS NULL OR a.volunteer_response != 'rejected') THEN 1 ELSE 0 END), 0) as pending_tasks,
                COALESCE(SUM(CASE WHEN a.status = 'rejected' OR a.volunteer_response = 'rejected' THEN 1 ELSE 0 END), 0) as rejected_tasks,
                COALESCE(SUM(CASE WHEN a.status = 'completed' AND (a.completed_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)) THEN 1 ELSE 0 END), 0) as recent_completed
              FROM users u
              LEFT JOIN assignments a ON u.id = a.volunteer_id
              WHERE u.user_type = 'volunteer' AND u.status = 'active'
              GROUP BY u.id";

    
    $result = $conn->query($query);
    $volunteers = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $performance_score = calculatePerformanceScore($row);
            $consistency_score = calculateConsistencyScore($row);
            
            $volunteers[] = [
                'id' => $row['id'],
                'name' => trim($row['fname'] . ' ' . $row['lname']),
                'picture' => $row['picture'],
                'location' => $row['location'],
                'join_date' => $row['join_date'],
                'total_assignments' => $row['total_assignments'],
                'completed_on_time' => $row['completed_on_time'],
                'total_completed' => $row['total_completed'],
                'rejected_tasks' => $row['rejected_tasks'],
                'pending_tasks' => $row['pending_tasks'],
                'recent_completed' => $row['recent_completed'],
                'performance_score' => $performance_score,
                'consistency_score' => $consistency_score,
                'completion_rate' => $row['total_assignments'] > 0 ? round(($row['total_completed'] / $row['total_assignments']) * 100, 1) : 0,
                'on_time_rate' => $row['total_completed'] > 0 ? round(($row['completed_on_time'] / $row['total_completed']) * 100, 1) : 0
            ];
        }
    } else {
        error_log("Database query error: " . $conn->error);
    }
    
    usort($volunteers, function($a, $b) {
        if ($a['performance_score'] == $b['performance_score']) {
            return $b['consistency_score'] <=> $a['consistency_score'];
        }
        return $b['performance_score'] <=> $a['performance_score'];
    });
    
    $current_user_rank = null;
    foreach ($volunteers as $index => &$volunteer) {
        $volunteer['rank'] = $index + 1;
        if ($volunteer['id'] == $current_user_id) {
            $current_user_rank = $index + 1;
        }
    }
    
    return ['leaderboard' => $volunteers, 'user_rank' => $current_user_rank];
}

function getAchievements($data) {
    $badges = [];
    
    if ($data['total_completed'] >= 50) $badges[] = ['name' => 'Task Master', 'icon' => '🏆'];
    if ($data['completed_on_time'] >= 20) $badges[] = ['name' => 'Punctual Pro', 'icon' => '⏰'];
    if ($data['rejected_tasks'] == 0 && $data['total_completed'] >= 10) $badges[] = ['name' => 'Quality Champion', 'icon' => '💎'];
    if ($data['recent_completed'] >= 10) $badges[] = ['name' => 'Active Volunteer', 'icon' => '🔥'];
    if ($data['total_assignments'] >= 100) $badges[] = ['name' => 'Veteran', 'icon' => '⭐'];
    
    return $badges;
}

$current_user_performance = getCurrentUserPerformance($conn, $_SESSION['user_id']);
$leaderboard_data = getLeaderboard($conn, $_SESSION['user_id']);
$leaderboard = $leaderboard_data['leaderboard'];
$user_rank = $leaderboard_data['user_rank'];

$user_performance_score = $current_user_performance ? calculatePerformanceScore($current_user_performance) : 0;
$user_consistency_score = $current_user_performance ? calculateConsistencyScore($current_user_performance) : 0;
$user_achievements = $current_user_performance ? getAchievements($current_user_performance) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Leaderboard</title>
    <style>
        :root {
            --primary: #374151;
            --primary-light: #4b5563;
            --secondary: #6b7280;
            --accent: #059669;
            --accent-light: #10b981;
            --background: #f9fafb;
            --surface: #ffffff;
            --border: #e5e7eb;
            --text: #111827;
            --text-muted: #6b7280;
            --success: #065f46;
            --warning: #92400e;
            --error: #991b1b;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .header h1 {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }
        .header p {
            color: var(--text-muted);
            font-size: 1.125rem;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }
        .dashboard-card {
            background: var(--surface);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .my-rank {
            text-align: center;
        }
        .my-rank h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.125rem;
            font-weight: 600;
        }
        .rank-display {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--accent);
            margin: 1rem 0;
            line-height: 1;
        }
        .rank-ordinal {
            font-size: 1.25rem;
            color: var(--text-muted);
            margin-left: 0.25rem;
        }
        .performance-overview h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.125rem;
            font-weight: 600;
        }
        .performance-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .metric-item {
            text-align: center;
            padding: 1rem;
            background: var(--background);
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .metric-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 0.25rem;
        }
        .metric-label {
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .detailed-stats {
            grid-column: 1 / -1;
            margin-top: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1.25rem;
            text-align: center;
            transition: border-color 0.2s ease;
        }
        .stat-card:hover {
            border-color: var(--accent);
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .achievements {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }
        .achievements h4 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 1rem;
            font-weight: 600;
        }
        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .achievement-badge {
            background: var(--accent);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 16px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-title {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
        }
        .podium {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .podium-item {
            background: var(--surface);
            border-radius: 12px;
            padding: 1.5rem 1rem;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            position: relative;
            transition: transform 0.2s ease;
        }
        .podium-item:hover {
            transform: translateY(-2px);
        }
        .podium-item.first {
            order: 2;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-color: #f59e0b;
            transform: translateY(-4px);
        }
        .podium-item.second {
            order: 1;
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
        }
        .podium-item.third {
            order: 3;
            background: linear-gradient(135deg, #fed7aa, #fdba74);
            border-color: #f97316;
        }
        .podium-rank {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.875rem;
            color: white;
        }
        .podium-item.first .podium-rank {
            background: #f59e0b;
        }
        .podium-item.second .podium-rank {
            background: var(--secondary);
        }
        .podium-item.third .podium-rank {
            background: #f97316;
        }
        .avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.25rem;
            color: var(--text-muted);
            border: 2px solid var(--border);
        }
        .avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .volunteer-name {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        .volunteer-location {
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .podium-score {
            font-weight: 700;
            font-size: 1rem;
            color: var(--accent);
        }
        .volunteers-list {
            background: var(--surface);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .list-header {
            background: var(--background);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .list-header h3 {
            color: var(--primary);
            font-size: 1.125rem;
            font-weight: 600;
        }
        .volunteer-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            transition: background-color 0.15s ease;
        }
        .volunteer-item:hover {
            background: var(--background);
        }
        .volunteer-item:last-child {
            border-bottom: none;
        }
        .item-rank {
            width: 40px;
            text-align: center;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 1rem;
        }
        .item-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 1rem;
            font-weight: 600;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }
        .item-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        .item-info {
            flex: 1;
        }
        .item-name {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.125rem;
        }
        .item-location {
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        .item-metrics {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            text-align: center;
        }
        .item-metric {
            min-width: 60px;
        }
        .metric-number {
            font-weight: 700;
            color: var(--accent);
            font-size: 0.875rem;
        }
        .metric-text {
            color: var(--text-muted);
            font-size: 0.75rem;
        }
        .current-user {
            background: #f0f9ff !important;
            border-left: 3px solid var(--accent);
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }
        .no-data {
            background: var(--surface);
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        .no-data h3 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        .no-data p {
            color: var(--text-muted);
        }
        /* Responsive Design Updates */
        @media (max-width: 768px) {
            .container {
                padding: 1rem 0.5rem;
            }
            .header h1 {
                font-size: 1.875rem;
            }
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .podium {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            .podium-item {
                order: unset !important;
                transform: none !important;
            }
            .performance-metrics {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .volunteer-item {
                flex-wrap: wrap;
                justify-content: space-between;
                padding: 0.75rem 1rem;
            }
            .item-metrics {
                flex: 1 1 100%;
                justify-content: space-around;
                margin-top: 0.75rem;
            }
            .item-rank, .item-avatar, .item-info {
                flex: 0 0 auto;
            }
        }
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .rank-display {
                font-size: 2.5rem;
            }
            .volunteer-item {
                flex-direction: column;
                text-align: center;
            }
            .item-avatar {
                margin: 0 auto 0.5rem;
            }
            .item-info {
                flex: none;
            }
            .item-metrics {
                flex-direction: row;
                justify-content: space-around;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Volunteer Leaderboard</h1>
            <p>Recognizing outstanding volunteer performance and dedication</p>
        </div>
        <?php if ($current_user_performance && $user_rank): ?>
        <div class="dashboard-grid">
            <div class="dashboard-card my-rank">
                <h3>Your Current Rank</h3>
                <div class="rank-display">
                    <?php echo $user_rank ?>
                    <span class="rank-ordinal">
                        <?php 
                        $ordinal = 'th';
                        if ($user_rank % 10 == 1 && $user_rank % 100 != 11) $ordinal = 'st';
                        elseif ($user_rank % 10 == 2 && $user_rank % 100 != 12) $ordinal = 'nd';
                        elseif ($user_rank % 10 == 3 && $user_rank % 100 != 13) $ordinal = 'rd';
                        echo $ordinal;
                        ?>
                    </span>
                </div>
                <?php if (!empty($user_achievements)): ?>
                <div class="achievements">
                    <h4>Achievements</h4>
                    <div class="badge-list">
                        <?php foreach ($user_achievements as $badge): ?>
                        <div class="achievement-badge">
                            <span><?php echo $badge['icon'] ?></span>
                            <?php echo $badge['name'] ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="dashboard-card performance-overview">
                <h3>Performance Overview</h3>
                <div class="performance-metrics">
                    <div class="metric-item">
                        <div class="metric-value"><?php echo $user_performance_score ?></div>
                        <div class="metric-label">Performance Score</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value"><?php echo $user_consistency_score ?>%</div>
                        <div class="metric-label">Consistency</div>
                    </div>
                </div>
            </div>
            <div class="detailed-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $current_user_performance['total_completed'] ?></div>
                        <div class="stat-label">Tasks Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $current_user_performance['completed_on_time'] ?></div>
                        <div class="stat-label">On-Time Delivery</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $current_user_performance['recent_completed'] ?></div>
                        <div class="stat-label">Recent Activity</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $current_user_performance['pending_tasks'] ?></div>
                        <div class="stat-label">Pending Tasks</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $current_user_performance['rejected_tasks'] ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo round(($current_user_performance['total_completed'] / max($current_user_performance['total_assignments'], 1)) * 100, 1) ?>%</div>
                        <div class="stat-label">Success Rate</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (count($leaderboard) >= 3): ?>
        <div class="section-title">Top Performers</div>
        <div class="podium">
            <?php for ($i = 0; $i < 3; $i++): 
                $volunteer = $leaderboard[$i];
                $class = $i == 0 ? 'first' : ($i == 1 ? 'second' : 'third');
            ?>
            <div class="podium-item <?php echo $class ?>">
                <div class="podium-rank"><?php echo $volunteer['rank'] ?></div>
                <div class="avatar">
                    <?php if ($volunteer['picture'] && $volunteer['picture'] != 'default-avatar.png'): ?>
                        <img src="<?php echo htmlspecialchars($volunteer['picture']) ?>" alt="<?php echo htmlspecialchars($volunteer['name']) ?>">
                    <?php else: ?>
                        <?php echo strtoupper(substr($volunteer['name'], 0, 2)) ?>
                    <?php endif; ?>
                </div>
                <div class="volunteer-name"><?php echo htmlspecialchars($volunteer['name']) ?></div>
                <div class="volunteer-location"><?php echo htmlspecialchars($volunteer['location']) ?></div>
                <div class="podium-score"><?php echo $volunteer['performance_score'] ?> pts</div>
            </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <div class="volunteers-list">
            <div class="list-header">
                <h3><?php echo count($leaderboard) > 3 ? 'All Rankings' : 'Volunteer Rankings' ?></h3>
            </div>
            <?php 
            if (empty($leaderboard)): ?>
                <div class="empty-state">
                    <h3>No volunteer data available</h3>
                    <p>Complete some tasks to appear on the leaderboard!</p>
                </div>
            <?php else: ?>
                <?php foreach ($leaderboard as $volunteer): ?>
                <div class="volunteer-item <?php echo $volunteer['id'] == $_SESSION['user_id'] ? 'current-user' : '' ?>">
                    <div class="item-rank"><?php echo $volunteer['rank'] ?></div>
                    <div class="item-avatar">
                        <?php if ($volunteer['picture'] && $volunteer['picture'] != 'default-avatar.png'): ?>
                            <img src="<?php echo htmlspecialchars($volunteer['picture']) ?>" alt="<?php echo htmlspecialchars($volunteer['name']) ?>">
                        <?php else: ?>
                            <?php echo strtoupper(substr($volunteer['name'], 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="item-info">
                        <div class="item-name"><?php echo htmlspecialchars($volunteer['name']) ?></div>
                        <div class="item-location"><?php echo htmlspecialchars($volunteer['location']) ?></div>
                    </div>
                    <div class="item-metrics">
                        <div class="item-metric">
                            <div class="metric-number"><?php echo $volunteer['performance_score'] ?></div>
                            <div class="metric-text">Score</div>
                        </div>
                        <div class="item-metric">
                            <div class="metric-number"><?php echo $volunteer['on_time_rate'] ?>%</div>
                            <div class="metric-text">On-Time %</div>
                        </div>
                        <div class="item-metric">
                            <div class="metric-number"><?php echo $volunteer['rejected_tasks'] ?></div>
                            <div class="metric-text">Rejected</div>
                        </div>
                        <div class="item-metric">
                            <div class="metric-number"><?php echo $volunteer['total_completed'] ?></div>
                            <div class="metric-text">Completed</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>