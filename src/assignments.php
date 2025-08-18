<?php  
require_once 'config.php';  
session_start();  

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'volunteer') {
    header('Location: auth.php');
    exit();
}

$volunteer_id = $_SESSION['user_id'];  

// Handle accept/reject actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assignment_id = intval($_POST['assignment_id']);
    $action = $_POST['action'];
    
    if (in_array($action, ['accept', 'reject'])) {
        $status = ($action == 'accept') ? 'accepted' : 'rejected';
        $stmt = $conn->prepare("UPDATE assignments 
                              SET volunteer_response = ?, response_date = NOW() 
                              WHERE id = ? AND volunteer_id = ?");
        $stmt->bind_param("sii", $status, $assignment_id, $volunteer_id);
        $stmt->execute();
        
        // If accepted, set status to 'assigned'
        if ($action == 'accept') {
            $stmt = $conn->prepare("UPDATE assignments 
                                  SET status = 'assigned' 
                                  WHERE id = ? AND volunteer_id = ?");
            $stmt->bind_param("ii", $assignment_id, $volunteer_id);
            $stmt->execute();
        }
        
        header("Location: assignments.php");
        exit();
    }
}

// Fetch assignments (only accepted ones or pending for response)
$sql = "SELECT a.id AS assignment_id, a.task_name, a.description, a.priority, 
               a.deadline, a.status, a.volunteer_response,
               c.name AS campaign_name, c.image_url AS campaign_image
        FROM assignments a
        JOIN campaigns c ON a.campaign_id = c.id
        WHERE a.volunteer_id = ? 
        AND (a.volunteer_response = 'accepted' OR a.volunteer_response = 'pending')
        ORDER BY 
            CASE WHEN a.volunteer_response = 'pending' THEN 0 ELSE 1 END,
            a.deadline ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $volunteer_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - Volunteer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/assignments.css">
    <style>
        /* Additional styles for pending assignments */
        .pending-assignment {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
        }
        
        .response-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-accept {
            background-color: #28a745;
            color: white;
        }
        
        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tasks"></i> My Assignments</h1>
        </div>
        
        <table class="task-table">
            <thead>
                <tr>
                    <th>Task Name</th>
                    <th>Campaign</th>
                    <th>Description</th>
                    <th>Priority</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if ($result->num_rows > 0) {
                    while ($assignment = $result->fetch_assoc()) {
                        $priorityClass = 'priority-' . strtolower($assignment['priority']);
                        $statusClass = 'status-' . str_replace(' ', '-', strtolower($assignment['status']));
                        $isPending = $assignment['volunteer_response'] == 'pending';
                        $isCompleted = $assignment['status'] == 'completed';
                ?>
                    <tr class="<?php echo $isPending ? 'pending-assignment' : ''; ?>">
                        <td data-label="Task Name"><?php echo htmlspecialchars($assignment['task_name']); ?></td>
                        <td data-label="Campaign">
                            <img src="<?php echo htmlspecialchars($assignment['campaign_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($assignment['campaign_name']); ?>"
                                 style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
                            <?php echo htmlspecialchars($assignment['campaign_name']); ?>
                        </td>
                        <td data-label="Description"><?php echo htmlspecialchars($assignment['description']); ?></td>
                        <td data-label="Priority" class="<?php echo $priorityClass; ?>">
                            <?php echo htmlspecialchars($assignment['priority']); ?>
                        </td>
                        <td data-label="Deadline"><?php echo htmlspecialchars($assignment['deadline']); ?></td>
                        <td data-label="Status">
                            <span class="status <?php echo $statusClass; ?>">
                                <?php 
                                if ($isPending) {
                                    echo 'Pending Response';
                                } else {
                                    echo htmlspecialchars($assignment['status']);
                                }
                                ?>
                            </span>
                        </td>
                        <td data-label="Actions">
                            <?php if ($isPending): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                                    <div class="response-buttons">
                                        <button type="submit" name="action" value="accept" class="btn btn-accept">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-reject">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="action-buttons">
                                    <?php if (!$isCompleted): ?>
                                        <?php if ($assignment['status'] == 'assigned'): ?>
                                            <button class="btn btn-update" 
                                                    onclick="startTask(<?php echo $assignment['assignment_id']; ?>, this)">
                                                <i class="fas fa-play"></i> Start Task
                                            </button>
                                        <?php elseif ($assignment['status'] == 'in-progress'): ?>
                                            <span class="btn btn-update" style="background-color: #17a2b8; cursor: default;">
                                                <i class="fas fa-sync-alt fa-spin"></i> In Progress
                                            </span>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-complete" 
                                                onclick="completeTask(<?php echo $assignment['assignment_id']; ?>, this)">
                                            <i class="fas fa-check"></i> Complete
                                        </button>
                                    <?php else: ?>
                                        <button class="btn" disabled>
                                            <i class="fas fa-check-circle"></i> Completed
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php 
                    }
                } else {
                    echo '<tr><td colspan="7" class="no-tasks">No assignments found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <script src="assets/js/assignments.js"></script>
    <script>
        function startTask(assignmentId, button) {
            fetch('update_assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `assignment_id=${assignmentId}&status=in-progress`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.outerHTML = `
                        <span class="btn btn-update" style="background-color: #17a2b8; cursor: default;">
                            <i class="fas fa-sync-alt fa-spin"></i> In Progress
                        </span>
                    `;
                }
            });
        }

        function completeTask(assignmentId, button) {
            fetch('update_assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `assignment_id=${assignmentId}&status=completed`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.outerHTML = `
                        <button class="btn" disabled>
                            <i class="fas fa-check-circle"></i> Completed
                        </button>
                    `;
                }
            });
        }
    </script>
</body>
</html>

<?php  
$conn->close();  
?>