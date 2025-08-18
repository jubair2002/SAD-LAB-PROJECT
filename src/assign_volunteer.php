<?php
require_once 'config.php';
require_once 'chat_functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: auth.php');
    exit();
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_volunteers':
            getVolunteersForCampaign();
            break;
        case 'check_conflicts':
            checkLocationConflicts();
            break;
        case 'get_volunteer_status':
            getVolunteerStatus();
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit();
}

function getVolunteersForCampaign() {
    global $conn;
    
    try {
        $campaign_id = $_GET['campaign_id'] ?? 0;
        
        if (!$campaign_id) {
            echo json_encode(['error' => 'Campaign ID required']);
            return;
        }
        
        // Get campaign location
        $campaignQuery = "SELECT location FROM campaigns WHERE id = ?";
        $stmt = $conn->prepare($campaignQuery);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        $stmt->bind_param("i", $campaign_id);
        $stmt->execute();
        $campaignResult = $stmt->get_result();
        $campaign = $campaignResult->fetch_assoc();
        
        if (!$campaign) {
            echo json_encode(['error' => 'Campaign not found']);
            return;
        }
        
        $campaign_location = $campaign['location'];
        
        // Simplified query to get all volunteers first
        $volunteersQuery = "SELECT 
            u.id, 
            u.fname,
            u.lname,
            u.email, 
            u.location,
            u.phone
            FROM users u
            WHERE u.user_type = 'volunteer' 
                AND u.status = 'active'
            ORDER BY u.fname, u.lname";
        
        $stmt = $conn->prepare($volunteersQuery);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $volunteers = [];
        while ($row = $result->fetch_assoc()) {
            $volunteer_id = $row['id'];
            
            // Get active assignments for this volunteer
            $assignmentQuery = "SELECT 
                a.id,
                a.status,
                c.name as campaign_name,
                c.location as campaign_location
                FROM assignments a
                JOIN campaigns c ON a.campaign_id = c.id
                WHERE a.volunteer_id = ?
                AND c.progress = 'ongoing' 
                AND a.status NOT IN ('completed', 'rejected')";
            
            $assignmentStmt = $conn->prepare($assignmentQuery);
            if (!$assignmentStmt) {
                throw new Exception('Database prepare error: ' . $conn->error);
            }
            $assignmentStmt->bind_param("i", $volunteer_id);
            $assignmentStmt->execute();
            $assignmentResult = $assignmentStmt->get_result();
            
            $active_assignments = 0;
            $current_campaign_assignments = 0;
            $assigned_campaigns = [];
            $assigned_locations = [];
            $has_conflict = false;
            
            while ($assignment = $assignmentResult->fetch_assoc()) {
                $active_assignments++;
                
                if ($assignment['campaign_location'] == $campaign_location) {
                    $current_campaign_assignments++;
                } else {
                    $has_conflict = true;
                }
                
                if (!in_array($assignment['campaign_name'], $assigned_campaigns)) {
                    $assigned_campaigns[] = $assignment['campaign_name'];
                }
                
                if (!in_array($assignment['campaign_location'], $assigned_locations)) {
                    $assigned_locations[] = $assignment['campaign_location'];
                }
            }
            
            $volunteer = [
                'id' => $volunteer_id,
                'name' => $row['fname'] . ' ' . $row['lname'],
                'email' => $row['email'],
                'location' => $row['location'],
                'phone' => $row['phone'],
                'active_assignments' => $active_assignments,
                'current_campaign_assignments' => $current_campaign_assignments,
                'assigned_campaigns' => $assigned_campaigns,
                'assigned_locations' => $assigned_locations,
                'has_conflict' => $has_conflict,
                'can_assign' => !$has_conflict,
                'availability_status' => getAvailabilityStatus($active_assignments, $has_conflict, $row['location'], $campaign_location)
            ];
            
            $volunteers[] = $volunteer;
        }
        
        echo json_encode([
            'volunteers' => $volunteers,
            'campaign_location' => $campaign_location
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getAvailabilityStatus($active_assignments, $has_conflict, $volunteer_location, $campaign_location) {
    if ($has_conflict) {
        return 'busy_different_location';
    }
    
    if ($active_assignments == 0) {
        return 'free';
    }
    
    if ($volunteer_location === $campaign_location) {
        return 'busy_same_location';
    }
    
    return 'available';
}

function getVolunteerStatus() {
    global $conn;
    
    $volunteer_id = $_GET['volunteer_id'] ?? 0;
    
    if (!$volunteer_id) {
        echo json_encode(['error' => 'Volunteer ID required']);
        return;
    }
    
    // Get detailed volunteer status
    $statusQuery = "SELECT 
        u.id,
        CONCAT(u.fname, ' ', u.lname) as name,
        u.location,
        COUNT(DISTINCT CASE WHEN a.status NOT IN ('completed', 'rejected') THEN a.id END) as active_assignments,
        GROUP_CONCAT(DISTINCT CASE WHEN a.status NOT IN ('completed', 'rejected') THEN c.name END SEPARATOR '|') as active_campaigns,
        GROUP_CONCAT(DISTINCT CASE WHEN a.status NOT IN ('completed', 'rejected') THEN c.location END SEPARATOR '|') as active_locations
        FROM users u
        LEFT JOIN assignments a ON u.id = a.volunteer_id
        LEFT JOIN campaigns c ON a.campaign_id = c.id AND c.progress = 'ongoing'
        WHERE u.id = ?
        GROUP BY u.id";
    
    $stmt = $conn->prepare($statusQuery);
    if (!$stmt) {
        echo json_encode(['error' => 'Database prepare error']);
        return;
    }
    $stmt->bind_param("i", $volunteer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $status = $result->fetch_assoc();
    
    echo json_encode([
        'volunteer' => $status,
        'is_free' => $status['active_assignments'] == 0,
        'active_locations' => $status['active_locations'] ? array_unique(explode('|', $status['active_locations'])) : []
    ]);
}

function checkLocationConflicts() {
    global $conn;
    
    $volunteer_id = $_GET['volunteer_id'] ?? 0;
    $campaign_location = $_GET['campaign_location'] ?? '';
    
    if (!$volunteer_id || !$campaign_location) {
        echo json_encode(['error' => 'Volunteer ID and campaign location required']);
        return;
    }
    
    // Check for conflicts - enhanced conflict detection
    $conflictQuery = "SELECT DISTINCT c.location, c.name, a.status, a.task_name
                      FROM assignments a
                      JOIN campaigns c ON a.campaign_id = c.id
                      WHERE a.volunteer_id = ?
                      AND c.progress = 'ongoing' 
                      AND a.status NOT IN ('completed', 'rejected')
                      AND c.location != ?";
    
    $stmt = $conn->prepare($conflictQuery);
    if (!$stmt) {
        echo json_encode(['error' => 'Database prepare error']);
        return;
    }
    $stmt->bind_param("is", $volunteer_id, $campaign_location);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conflicts = [];
    while ($row = $result->fetch_assoc()) {
        $conflicts[] = $row;
    }
    
    echo json_encode([
        'has_conflicts' => !empty($conflicts),
        'conflicts' => $conflicts,
        'can_assign' => empty($conflicts)
    ]);
}

// Fetch all ongoing campaigns with enhanced statistics
$campaignsQuery = "SELECT c.id, c.name, c.location,
    COUNT(DISTINCT CASE WHEN a.status NOT IN ('completed', 'rejected') THEN a.volunteer_id END) as active_volunteers,
    COUNT(DISTINCT CASE WHEN a.status NOT IN ('completed', 'rejected') THEN a.id END) as incomplete_tasks
    FROM campaigns c
    LEFT JOIN assignments a ON c.id = a.campaign_id 
    WHERE c.status = 'approved' AND c.progress = 'ongoing'
    GROUP BY c.id, c.name, c.location
    ORDER BY c.created_at DESC";

$campaignsResult = $conn->query($campaignsQuery);

if (!$campaignsResult) {
    die("Error fetching campaigns: " . $conn->error);
}

// Handle form submission with enhanced validation and chat integration
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_tasks'])) {
    $campaign_id = $_POST['campaign_id'];
    $volunteer_ids = is_array($_POST['volunteer_ids']) ? $_POST['volunteer_ids'] : [$_POST['volunteer_ids']];
    $task_name = $_POST['task_name'] ?? '';
    $task_description = $_POST['task_description'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $deadline = $_POST['deadline'] ?? '';

    // Get campaign details
    $campaignQuery = "SELECT name, location FROM campaigns WHERE id = ?";
    $stmt = $conn->prepare($campaignQuery);
    if (!$stmt) {
        $error_message = "Error preparing campaign query: " . $conn->error;
    } else {
        $stmt->bind_param("i", $campaign_id);
        $stmt->execute();
        $campaign = $stmt->get_result()->fetch_assoc();
        
        if (!$campaign) {
            $error_message = "Campaign not found";
        } else {
            $campaign_location = $campaign['location'];
            $campaign_name = $campaign['name'];

            $errors = [];
            $successful_assignments = 0;
            
            try {
                $conn->begin_transaction();
                
                // Initialize chat system
                $chatSystem = new ChatSystem($conn);

                foreach ($volunteer_ids as $volunteer_id) {
                    // Enhanced conflict checking - ensure volunteer can work at this location
                    $conflictQuery = "SELECT COUNT(*) as conflict_count, GROUP_CONCAT(DISTINCT c.location) as conflict_locations
                                      FROM assignments a
                                      JOIN campaigns c ON a.campaign_id = c.id
                                      WHERE a.volunteer_id = ?
                                      AND c.progress = 'ongoing' 
                                      AND a.status NOT IN ('completed', 'rejected')
                                      AND c.location != ?";
                    $stmtConflict = $conn->prepare($conflictQuery);
                    if (!$stmtConflict) {
                        throw new Exception('Database prepare error: ' . $conn->error);
                    }
                    $stmtConflict->bind_param("is", $volunteer_id, $campaign_location);
                    $stmtConflict->execute();
                    $conflictResult = $stmtConflict->get_result()->fetch_assoc();

                    if ($conflictResult['conflict_count'] > 0) {
                        // Get volunteer name for error message
                        $volunteerQuery = "SELECT CONCAT(fname, ' ', lname) as name FROM users WHERE id = ?";
                        $stmtVol = $conn->prepare($volunteerQuery);
                        if ($stmtVol) {
                            $stmtVol->bind_param("i", $volunteer_id);
                            $stmtVol->execute();
                            $volunteer_name = $stmtVol->get_result()->fetch_row()[0];
                        } else {
                            $volunteer_name = "Volunteer ID " . $volunteer_id;
                        }
                        
                        $errors[] = "Cannot assign $volunteer_name - currently working in: " . $conflictResult['conflict_locations'];
                        continue;
                    }

                    // Create assignment for this volunteer
                    $assignmentStmt = $conn->prepare("INSERT INTO assignments
                        (campaign_id, volunteer_id, task_name, description, priority, deadline, status, volunteer_response)
                        VALUES (?, ?, ?, ?, ?, ?, 'assigned', 'pending')");

                    if (!$assignmentStmt) {
                        throw new Exception('Database prepare error: ' . $conn->error);
                    }

                    $assignmentStmt->bind_param(
                        "iissss",
                        $campaign_id,
                        $volunteer_id,
                        $task_name,
                        $task_description,
                        $priority,
                        $deadline
                    );
                    
                    if ($assignmentStmt->execute()) {
                        $successful_assignments++;
                        
                        // Auto-add volunteer to campaign chat group
                        try {
                            $chatSystem->addVolunteerToCampaignChat($campaign_id, $volunteer_id, $_SESSION['user_id']);
                        } catch (Exception $e) {
                            // Don't fail assignment if chat fails, just log it
                            error_log("Failed to add volunteer to campaign chat: " . $e->getMessage());
                        }
                    }
                }

                $conn->commit();
                
                if ($successful_assignments > 0) {
                    $success_message = "Successfully assigned tasks to $successful_assignments volunteer(s) and added them to the campaign group chat!";
                }
                
                if (!empty($errors)) {
                    $error_message = implode('<br>', $errors);
                }

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Error assigning tasks: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Assignment Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f8f9fa;
            color: #212529;
            line-height: 1.5;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }

        .nav-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.15s ease;
        }

        .back-btn:hover {
            background: #f8f9fa;
            color: #495057;
        }

        .nav-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #212529;
            margin: 0;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            border: 1px solid;
            font-weight: 500;
        }

        .alert-success {
            background: #d1e7dd;
            color: #0f5132;
            border-color: #badbcc;
        }

        .alert-danger {
            background: #f8d7da;
            color: #842029;
            border-color: #f5c2c7;
        }

        .campaign-list {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .list-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #212529;
            margin: 0;
        }

        .campaign-count {
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .campaign-table {
            width: 100%;
        }

        .table-header {
            display: grid;
            grid-template-columns: 3fr 2fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            font-size: 0.875rem;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .table-row {
            display: grid;
            grid-template-columns: 3fr 2fr 1fr 1fr 1fr;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f3f4;
            align-items: center;
            transition: background-color 0.15s ease;
        }

        .table-row:hover {
            background: #f8f9fa;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .campaign-name {
            font-weight: 600;
            color: #212529;
            font-size: 0.95rem;
        }

        .location-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2rem;
            height: 1.75rem;
            padding: 0 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stat-badge.volunteers {
            background: #e7f3ff;
            color: #0066cc;
        }

        .stat-badge.tasks {
            background: #fff0e6;
            color: #cc6600;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.875rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            text-transform: none;
        }

        .btn-assign {
            background: #0d6efd;
            color: #ffffff;
            border-color: #0d6efd;
        }

        .btn-assign:hover {
            background: #0b5ed7;
            border-color: #0a58ca;
            color: #ffffff;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: #ffffff;
            margin: 3% auto;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: #343a40;
            color: #ffffff;
            padding: 1.5rem;
            text-align: center;
            position: relative;
        }

        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
            max-height: calc(90vh - 120px);
            overflow-y: auto;
        }

        .close {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 300;
            cursor: pointer;
            opacity: 0.8;
        }

        .close:hover {
            opacity: 1;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: #ffffff;
            font-size: 0.875rem;
            transition: border-color 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #343a40;
            box-shadow: 0 0 0 2px rgba(52, 58, 64, 0.1);
        }

        .volunteer-list {
            height: 450px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 0.75rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            align-content: start;
        }

        .volunteer-item {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0;
            background: #ffffff;
            border: 2px solid #dee2e6;
            transition: all 0.15s ease;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            height: fit-content;
        }

        .volunteer-item:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }

        .volunteer-item.available {
            border-color: #28a745;
            background: #f8fff9;
        }

        .volunteer-item.busy {
            border-color: #ffc107;
            background: #fffef7;
        }

        .volunteer-item.conflict {
            border-color: #dc3545;
            background: #fff5f5;
            opacity: 0.7;
        }

        .volunteer-checkbox {
            margin-top: 0.25rem;
            transform: scale(1.2);
        }

        .volunteer-info {
            flex: 1;
        }

        .volunteer-name {
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .volunteer-details {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .volunteer-status {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
        }

        .status-free {
            background: #d4edda;
            color: #155724;
        }

        .status-busy_same_location {
            background: #fff3cd;
            color: #856404;
        }

        .status-busy_different_location {
            background: #f8d7da;
            color: #721c24;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
            grid-column: 1 / -1;
        }

        .btn-submit {
            background: #28a745;
            color: #ffffff;
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            border: none;
        }

        .btn-submit:hover {
            background: #218838;
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .table-header,
            .table-row {
                grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0.5rem;
            }

            .nav-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .nav-header h1 {
                font-size: 1.25rem;
            }

            .list-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .table-header {
                display: none;
            }

            .table-row {
                display: block;
                padding: 1rem;
                border-bottom: 2px solid #f1f3f4;
            }

            .col-name {
                margin-bottom: 0.75rem;
            }

            .campaign-name {
                font-size: 1.1rem;
                font-weight: 700;
            }

            .col-location,
            .col-volunteers,
            .col-tasks {
                display: inline-block;
                margin-right: 1rem;
                margin-bottom: 0.5rem;
            }

            .col-action {
                margin-top: 1rem;
            }

            .btn-assign {
                width: 100%;
                justify-content: center;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .volunteer-list {
                grid-template-columns: 1fr;
                height: 350px;
            }

            .volunteer-item {
                flex-direction: column;
                gap: 0.5rem;
            }

            .volunteer-checkbox {
                margin-top: 0;
            }
        }

        @media (max-width: 480px) {
            .col-location,
            .col-volunteers,
            .col-tasks {
                display: block;
                margin-right: 0;
                margin-bottom: 0.75rem;
            }

            .location-badge,
            .stat-badge {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation Header -->
        <div class="nav-header">
            <a href="campaignSummary.php" class="back-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="m12 19-7-7 7-7"/>
                    <path d="m19 12H5"/>
                </svg>
                Back to Dashboard
            </a>
            <h1>Volunteer Assignment</h1>
        </div>

        <!-- Alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 12l2 2 4-4"/>
                    <circle cx="12" cy="12" r="10"/>
                </svg>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <!-- Campaign List -->
        <div class="campaign-list">
            <div class="list-header">
                <h2>Active Campaigns</h2>
                <span class="campaign-count"><?= $campaignsResult->num_rows ?> campaigns</span>
            </div>
            
            <div class="campaign-table">
                <div class="table-header">
                    <div class="col-name">Campaign Name</div>
                    <div class="col-location">Location</div>
                    <div class="col-volunteers">Active Volunteers</div>
                    <div class="col-tasks">Pending Tasks</div>
                    <div class="col-action">Action</div>
                </div>
                
                <?php while ($campaign = $campaignsResult->fetch_assoc()): ?>
                    <div class="table-row">
                        <div class="col-name">
                            <div class="campaign-name"><?= htmlspecialchars($campaign['name']) ?></div>
                        </div>
                        <div class="col-location">
                            <span class="location-badge">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                    <circle cx="12" cy="10" r="3"/>
                                </svg>
                                <?= htmlspecialchars($campaign['location']) ?>
                            </span>
                        </div>
                        <div class="col-volunteers">
                            <span class="stat-badge volunteers">
                                <?= $campaign['active_volunteers'] ?>
                            </span>
                        </div>
                        <div class="col-tasks">
                            <span class="stat-badge tasks">
                                <?= $campaign['incomplete_tasks'] ?>
                            </span>
                        </div>
                        <div class="col-action">
                            <button class="btn btn-assign" onclick="openAssignmentModal(<?= $campaign['id'] ?>, '<?= htmlspecialchars($campaign['name']) ?>', '<?= htmlspecialchars($campaign['location']) ?>')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="m19 8 2 2-2 2"/>
                                    <path d="m21 10H11"/>
                                </svg>
                                Assign
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Assignment Modal -->
    <div id="assignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close">&times;</span>
                <h2 id="modalTitle">Assign Volunteers</h2>
            </div>
            <div class="modal-body">
                <form method="POST" action="assign_volunteer.php">
                    <input type="hidden" id="campaign_id" name="campaign_id" value="">
                    <input type="hidden" name="assign_tasks" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Task Name</label>
                        <input type="text" name="task_name" class="form-input" required placeholder="Enter task name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Task Description</label>
                        <textarea name="task_description" class="form-input" rows="4" required placeholder="Enter task description"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-input" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deadline</label>
                        <input type="datetime-local" name="deadline" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Select Volunteers (Side-by-Side View)</label>
                        <div id="volunteersList" class="volunteer-list">
                            <div class="loading">Loading volunteers...</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-submit">Assign Tasks & Add to Chat Group</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentCampaignId = null;
        let currentCampaignLocation = null;
        
        // Modal handling
        const modal = document.getElementById('assignmentModal');
        const closeBtn = document.querySelector('.close');
        
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        function openAssignmentModal(campaignId, campaignName, campaignLocation) {
            currentCampaignId = campaignId;
            currentCampaignLocation = campaignLocation;
            
            document.getElementById('campaign_id').value = campaignId;
            document.getElementById('modalTitle').textContent = `Assign Volunteers - ${campaignName}`;
            
            modal.style.display = 'block';
            loadVolunteers(campaignId);
        }
        
        function loadVolunteers(campaignId) {
            const volunteersList = document.getElementById('volunteersList');
            volunteersList.innerHTML = '<div class="loading">Loading volunteers...</div>';
            
            fetch(`assign_volunteer.php?action=get_volunteers&campaign_id=${campaignId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        volunteersList.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        return;
                    }
                    
                    displayVolunteers(data.volunteers, data.campaign_location);
                })
                .catch(error => {
                    console.error('Error loading volunteers:', error);
                    volunteersList.innerHTML = '<div class="alert alert-danger">Error loading volunteers. Please try again.</div>';
                });
        }
        
        function displayVolunteers(volunteers, campaignLocation) {
            const volunteersList = document.getElementById('volunteersList');
            
            if (volunteers.length === 0) {
                volunteersList.innerHTML = '<div class="loading">No volunteers available</div>';
                return;
            }
            
            let html = '';
            
            volunteers.forEach(volunteer => {
                let statusClass = '';
                let statusText = '';
                let canSelect = volunteer.can_assign;
                
                switch (volunteer.availability_status) {
                    case 'free':
                        statusClass = 'available';
                        statusText = 'Available';
                        break;
                    case 'busy_same_location':
                        statusClass = 'busy';
                        statusText = `Working in ${campaignLocation} (can work parallel)`;
                        break;
                    case 'busy_different_location':
                        statusClass = 'conflict';
                        statusText = `Busy in different location`;
                        canSelect = false;
                        break;
                    default:
                        statusClass = 'available';
                        statusText = 'Available';
                }
                
                html += `
                    <div class="volunteer-item ${statusClass}">
                        <input type="checkbox" name="volunteer_ids[]" value="${volunteer.id}" 
                               class="volunteer-checkbox" ${canSelect ? '' : 'disabled'}>
                        <div class="volunteer-info">
                            <div class="volunteer-name">${volunteer.name}</div>
                            <div class="volunteer-details">
                                📍 ${volunteer.location}<br>
                                📞 ${volunteer.phone}<br>
                                📋 ${volunteer.active_assignments} active tasks
                            </div>
                            ${volunteer.assigned_campaigns.length > 0 ? `
                                <div class="volunteer-details">
                                    <strong>Current:</strong> ${volunteer.assigned_campaigns.join(', ')}
                                </div>
                            ` : ''}
                            <span class="volunteer-status status-${volunteer.availability_status}">
                                ${statusText}
                            </span>
                        </div>
                    </div>
                `;
            });
            
            volunteersList.innerHTML = html;
        }
        
        // Set minimum deadline to current datetime
        document.addEventListener('DOMContentLoaded', function() {
            const deadlineInput = document.querySelector('input[name="deadline"]');
            const now = new Date();
            const minDateTime = now.toISOString().slice(0, 16);
            deadlineInput.min = minDateTime;
        });
    </script>
</body>
</html>