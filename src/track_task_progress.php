<?php
require_once 'config.php';
session_start();

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: auth.php');
    exit();
}

// Function to send auto notification
function sendAutoNotification($conn, $volunteer_id, $assignment_id, $task, $notification_type) {
    try {
        $titles = [
            '2_day' => "Task Deadline Reminder: {$task['task_name']} (2 days left)",
            '1_day' => "URGENT: Task Due Tomorrow - {$task['task_name']}",
            'overdue' => "OVERDUE: Task {$task['task_name']} is past deadline"
        ];
        
        $messages = [
            '2_day' => "Reminder: Your task '{$task['task_name']}' is due in 2 days on {$task['deadline']}. Please start working on it if you haven't already.",
            '1_day' => "URGENT: Your task '{$task['task_name']}' is due tomorrow ({$task['deadline']}). Please complete it as soon as possible.",
            'overdue' => "Your task '{$task['task_name']}' was due on {$task['deadline']} and is now overdue. Please complete it immediately."
        ];
        
        // Insert notification
        $stmt = $conn->prepare("INSERT INTO notifications 
                              (recipient_id, sender_id, title, message, entity_type, entity_id, created_at) 
                              VALUES (?, 1, ?, ?, 'assignment', ?, NOW())");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare notification statement: " . $conn->error);
        }
        
        $stmt->bind_param("issi", $volunteer_id, $titles[$notification_type], $messages[$notification_type], $assignment_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create notification: " . $stmt->error);
        }
        
        // Update last_notified in assignments table
        $update_stmt = $conn->prepare("UPDATE assignments SET last_notified=NOW() WHERE id=?");
        
        if (!$update_stmt) {
            throw new Exception("Failed to prepare update statement: " . $conn->error);
        }
        
        $update_stmt->bind_param("i", $assignment_id);
        $update_stmt->execute();
        
        return true;
        
    } catch (Exception $e) {
        error_log("Auto-notification error: " . $e->getMessage());
        return false;
    }
}

// Function to check notification frequency
function wasNotificationSentRecently($conn, $volunteer_id, $assignment_id, $notification_type) {
    try {
        // Different time thresholds for different notification types
        $time_thresholds = [
            '2_day' => '24 HOUR',    // 2-day reminders once per day
            '1_day' => '12 HOUR',    // 1-day reminders twice per day
            'overdue' => '24 HOUR'   // Overdue reminders once per day
        ];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications 
                               WHERE recipient_id = ? AND entity_id = ? 
                               AND entity_type = 'assignment' 
                               AND created_at > (NOW() - INTERVAL {$time_thresholds[$notification_type]})");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare notification check statement: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $volunteer_id, $assignment_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result['count'] > 0;
        
    } catch (Exception $e) {
        error_log("Notification check error: " . $e->getMessage());
        return false;
    }
}

// ------------------------------------------------------------------------------------------------
// AUTO NOTIFICATION SYSTEM
// ------------------------------------------------------------------------------------------------

$notifications_sent = 0;
$debug_info = [];

try {
    // Start transaction for auto notifications
    $conn->begin_transaction();

    // Get tasks that need notifications
    $auto_notify_sql = "SELECT 
                        a.id AS assignment_id,
                        a.task_name,
                        a.deadline,
                        a.volunteer_id,
                        u.fname,
                        u.lname,
                        DATEDIFF(a.deadline, NOW()) as days_until_deadline
                    FROM assignments a
                    INNER JOIN users u ON a.volunteer_id = u.id
                    WHERE a.status != 'completed'
                      AND a.volunteer_response != 'rejected'
                      AND (DATEDIFF(a.deadline, NOW()) <= 2 OR a.deadline < NOW())
                    ORDER BY a.deadline ASC";

    $auto_notify_result = $conn->query($auto_notify_sql);
    
    if ($auto_notify_result && $auto_notify_result->num_rows > 0) {
        while ($task = $auto_notify_result->fetch_assoc()) {
            $assignment_id = $task['assignment_id'];
            $volunteer_id = $task['volunteer_id'];
            $days_until_deadline = $task['days_until_deadline'];
            
            // Determine notification type
            if ($days_until_deadline == 2) {
                $notification_type = '2_day';
                $reason = "2-day reminder needed";
            } elseif ($days_until_deadline == 1) {
                $notification_type = '1_day';
                $reason = "1-day reminder needed";
            } elseif ($days_until_deadline <= 0) {
                $notification_type = 'overdue';
                $reason = "Task is " . abs($days_until_deadline) . " days overdue";
            } else {
                continue;
            }
            
            // Check if notification was already sent recently
            if (!wasNotificationSentRecently($conn, $volunteer_id, $assignment_id, $notification_type)) {
                if (sendAutoNotification($conn, $volunteer_id, $assignment_id, $task, $notification_type)) {
                    $notifications_sent++;
                    $debug_info[] = " Sent {$notification_type} notification for task '{$task['task_name']}' to {$task['fname']} {$task['lname']}";
                } else {
                    $debug_info[] = " Failed to send {$notification_type} notification for task '{$task['task_name']}'";
                }
            } else {
                $debug_info[] = " Skipped {$notification_type} notification for '{$task['task_name']}' (already sent recently)";
            }
        }
    } else {
        $debug_info[] = "No tasks currently require auto-notifications";
    }

    // Commit auto notification transaction
    $conn->commit();
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $debug_info[] = " Error in auto-notification system: " . $e->getMessage();
}

// ------------------------------------------------------------------------------------------------
// DISPLAY ALL TASKS
// ------------------------------------------------------------------------------------------------

$sql = "SELECT a.id AS assignment_id, a.task_name, a.status AS assignment_status, 
               a.deadline, a.volunteer_response,
               u.fname, u.lname, u.id AS volunteer_id,
               DATEDIFF(a.deadline, NOW()) as days_until_deadline,
               (SELECT MAX(n.created_at) FROM notifications n 
                WHERE n.recipient_id = u.id AND n.entity_id = a.id AND n.entity_type = 'assignment') as last_notified
        FROM assignments a
        INNER JOIN users u ON a.volunteer_id = u.id
        ORDER BY a.deadline ASC";

$result = $conn->query($sql);

if ($result === false) {
    die("Error executing query: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Task Progress</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header h1 i {
            color: #3b82f6;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .refresh-btn {
            background: #3b82f6;
            color: white;
        }

        .refresh-btn:hover {
            background: #2563eb;
        }

        .back-btn {
            background: #64748b;
            color: white;
        }

        .back-btn:hover {
            background: #475569;
        }

        .task-table {
            width: 100%;
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .task-table th,
        .task-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .task-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .task-table tbody tr:hover {
            background: #f8fafc;
        }

        .task-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status badges */
        .status, .response {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-in-progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .response-accepted {
            background: #d1fae5;
            color: #065f46;
        }

        .response-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .response-rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Priority indicators */
        .overdue {
            background: #fef2f2;
        }

        .deadline-approaching {
            background: #fffbeb;
        }

        .deadline-overdue {
            color: #dc2626;
            font-weight: 600;
        }

        .deadline-overdue small {
            color: #dc2626;
        }

        .last-notified {
            font-size: 0.8rem;
            color: #64748b;
        }

        .btn-notify {
            background: #f59e0b;
            color: white;
            padding: 0.375rem 0.75rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .btn-notify:hover {
            background: #d97706;
        }

        .btn-notify:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .no-tasks {
            text-align: center;
            color: #64748b;
            font-style: italic;
            padding: 3rem;
        }

        .task-completed {
            color: #059669;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .header-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .task-table {
                font-size: 0.875rem;
            }

            .task-table th,
            .task-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        @media (max-width: 640px) {
            .task-table th:nth-child(6),
            .task-table td:nth-child(6) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Task Progress</h1>
            <div class="header-actions">
                <button class="btn refresh-btn" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <a href="campaignSummary.php" class="btn back-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <table class="task-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Volunteer</th>
                    <th>Response</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th>Last Notified</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($assignment = $result->fetch_assoc()): ?>
                        <?php
                        $statusClass = 'status-' . str_replace(' ', '-', strtolower($assignment['assignment_status']));
                        $responseClass = 'response-' . strtolower($assignment['volunteer_response']);
                        $days_until_deadline = $assignment['days_until_deadline'];
                        $isOverdue = $days_until_deadline < 0 && $assignment['assignment_status'] != 'completed';
                        $deadlineApproaching = $days_until_deadline <= 2 && $days_until_deadline >= 0 && $assignment['assignment_status'] != 'completed';
                        $rowClass = $isOverdue ? 'overdue' : ($deadlineApproaching ? 'deadline-approaching' : '');
                        $deadlineClass = $isOverdue ? 'deadline-overdue' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($assignment['task_name']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($assignment['fname'] . " " . $assignment['lname']); ?></td>
                            <td>
                                <span class="response <?php echo $responseClass; ?>">
                                    <?php echo htmlspecialchars(ucfirst($assignment['volunteer_response'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars(ucfirst($assignment['assignment_status'])); ?>
                                </span>
                            </td>
                            <td class="<?php echo $deadlineClass; ?>">
                                <div><?php echo date('M j, Y', strtotime($assignment['deadline'])); ?></div>
                                <?php if ($isOverdue): ?>
                                    <small><strong>OVERDUE (<?php echo abs($days_until_deadline); ?> days)</strong></small>
                                <?php elseif ($deadlineApproaching): ?>
                                    <small><strong><?php echo $days_until_deadline; ?> days left</strong></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($assignment['last_notified']): ?>
                                    <span class="last-notified">
                                        <?php echo date('M j, H:i', strtotime($assignment['last_notified'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="last-notified">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($assignment['assignment_status'] != 'completed' && $assignment['volunteer_response'] != 'rejected'): ?>
                                    <button class="btn-notify"
                                        onclick="notifyVolunteer(<?php echo $assignment['volunteer_id']; ?>, <?php echo $assignment['assignment_id']; ?>, '<?php echo htmlspecialchars($assignment['fname'] . ' ' . $assignment['lname'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-bell"></i> Notify
                                    </button>
                                <?php else: ?>
                                    <span class="task-completed">
                                        <i class="fas fa-check-circle"></i> Complete
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="no-tasks">No tasks to track at this time.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        async function notifyVolunteer(volunteerId, taskId, volunteerName) {
            const btn = event.target;
            const originalText = btn.innerHTML;

            try {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;

                const response = await fetch('send_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        volunteer_id: volunteerId,
                        task_id: taskId,
                        notification_type: 'manual'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Sent';
                    btn.style.background = '#059669';
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || "Operation failed");
                }
            } catch (error) {
                btn.innerHTML = '<i class="fas fa-times"></i> Sending...';
                btn.style.background = '#dc2626';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    btn.style.background = '#f59e0b';
                }, 2000);
            }
        }

        // Auto-refresh every 2 hours (7200000 ms)
        setTimeout(() => {
            location.reload();
        }, 7200000);
    </script>
</body>
</html>

<?php
$conn->close();
?>