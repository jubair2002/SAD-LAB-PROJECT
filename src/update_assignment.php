<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'volunteer') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assignment_id = intval($_POST['assignment_id']);
    $status = $_POST['status'];
    $volunteer_id = $_SESSION['user_id'];

    // Validate status
    $allowed_statuses = ['in-progress', 'completed'];
    if (!in_array($status, $allowed_statuses)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }

    // Update assignment
    $stmt = $conn->prepare("UPDATE assignments 
                           SET status = ? 
                           WHERE id = ? AND volunteer_id = ? AND volunteer_response = 'accepted'");
    $stmt->bind_param("sii", $status, $assignment_id, $volunteer_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }

    $stmt->close();
    $conn->close();
    exit();
}

header('HTTP/1.1 400 Bad Request');
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>