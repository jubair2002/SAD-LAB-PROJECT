<?php
// Turn off all error output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

require_once 'config.php';
session_start();

// Only return JSON
header('Content-Type: application/json');

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $volunteer_id = (int)($input['volunteer_id'] ?? 0);
    $task_id = (int)($input['task_id'] ?? 0);
    $notification_type = $input['notification_type'] ?? 'manual'; // manual, 2_day, 1_day, overdue
    
    // Basic validation
    if ($volunteer_id <= 0 || $task_id <= 0) {
        throw new Exception("Invalid volunteer or task ID");
    }

    // Start transaction
    $transaction_started = $conn->begin_transaction();

    // 1. Get task details
    $stmt = $conn->prepare("SELECT task_name, deadline, DATEDIFF(deadline, NOW()) as days_left FROM assignments WHERE id=? AND volunteer_id=?");
    $stmt->bind_param("ii", $task_id, $volunteer_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    
    if (!$task) {
        throw new Exception("Task not found for this volunteer");
    }

    // 2. Create notification based on type
    $titles = [
        'manual' => "Task Reminder: {$task['task_name']}",
        '2_day' => "Task Deadline Reminder: {$task['task_name']} (2 days left)",
        '1_day' => "URGENT: Task Due Tomorrow - {$task['task_name']}",
        'overdue' => "OVERDUE: Task {$task['task_name']} is past deadline"
    ];
    
    $messages = [
        'manual' => "Manual reminder: {$task['task_name']} (Deadline: {$task['deadline']}). Please check your task status and complete it on time.",
        '2_day' => "Reminder: Your task '{$task['task_name']}' is due in 2 days on {$task['deadline']}. Please start working on it if you haven't already.",
        '1_day' => "URGENT: Your task '{$task['task_name']}' is due tomorrow ({$task['deadline']}). Please complete it as soon as possible.",
        'overdue' => "Your task '{$task['task_name']}' was due on {$task['deadline']} and is now overdue. Please complete it immediately or contact the administrator."
    ];
    
    $title = $titles[$notification_type] ?? $titles['manual'];
    $message = $messages[$notification_type] ?? $messages['manual'];
    
    // Get sender ID (use 1 for system auto-notifications, or session user_id for manual)
    $sender_id = ($notification_type === 'manual' && isset($_SESSION['user_id'])) ? $_SESSION['user_id'] : 1;
    
    // 3. Insert notification into database
    $stmt = $conn->prepare("INSERT INTO notifications (recipient_id, sender_id, title, message, entity_type, entity_id, created_at) VALUES (?, ?, ?, ?, 'assignment', ?, NOW())");
    $stmt->bind_param("iissi", $volunteer_id, $sender_id, $title, $message, $task_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create notification: " . $stmt->error);
    }

    // 4. Update assignment last_notified timestamp
    $stmt = $conn->prepare("UPDATE assignments SET last_notified=NOW() WHERE id=?");
    $stmt->bind_param("i", $task_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update assignment: " . $stmt->error);
    }

    // 5. Commit transaction
    $conn->commit();
    
    // Clear any output and send success response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Notification sent successfully',
        'notification_type' => $notification_type,
        'volunteer_id' => $volunteer_id,
        'task_id' => $task_id,
        'title' => $title
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($transaction_started) && $transaction_started === true) {
        $conn->rollback();
    }
    
    // Clear any output and send error response
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'notification_type' => $notification_type ?? 'unknown'
    ]);
}

// Close database connection
if ($conn instanceof mysqli) {
    $conn->close();
}
?>