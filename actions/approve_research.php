<?php
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../db.php');
session_start();

header('Content-Type: application/json');

// Only allow admins/deans
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Dean', 'Admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['research_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$research_id = (int)$data['research_id'];
$action = $data['action']; // 'approve' or 'reject'
$rejection_reason = isset($data['reason']) ? $conn->real_escape_string($data['reason']) : null;

if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

$conn->begin_transaction();

try {
    // Get research details
    $sql = "SELECT title, submitted_by FROM research_papers WHERE research_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $research_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Research paper not found');
    }
    
    $research = $result->fetch_assoc();
    
    // Update research status
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    if ($action === 'approve') {
        $sql = "UPDATE research_papers SET status = ?, approved_by = ?, approved_at = NOW() WHERE research_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $new_status, $_SESSION['user_id'], $research_id);
    } else {
        $sql = "UPDATE research_papers SET status = ?, rejection_reason = ? WHERE research_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $new_status, $rejection_reason, $research_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update research status');
    }
    
    // Create notification for uploader
    $notification_title = ($action === 'approve') ? 'Research Approved' : 'Research Rejected';
    $notification_msg = ($action === 'approve') 
        ? "Your research '{$research['title']}' has been approved and is now published."
        : "Your research '{$research['title']}' has been rejected. " . ($rejection_reason ? "Reason: {$rejection_reason}" : "");
    
    $sql_notif = "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt_notif = $conn->prepare($sql_notif);
    $notif_type = ($action === 'approve') ? 'success' : 'warning';
    $stmt_notif->bind_param("isss", $research['submitted_by'], $notification_title, $notification_msg, $notif_type);
    $stmt_notif->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Research successfully {$new_status}",
        'status' => $new_status
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
