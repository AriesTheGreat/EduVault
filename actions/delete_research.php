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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['research_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing research ID']);
    exit;
}

$research_id = (int)$input['research_id'];
$delete_reason = $input['delete_reason'] ?? 'No reason provided';

// Start transaction
$conn->begin_transaction();

try {
    // First, get the research data before deleting
    $get_research_sql = "SELECT r.*, u.name as uploader_name, p.program_name, d.department_name 
                         FROM research_papers r 
                         LEFT JOIN users u ON r.submitted_by = u.user_id
                         LEFT JOIN programs p ON r.program_id = p.program_id 
                         LEFT JOIN departments d ON p.department_id = d.department_id
                         WHERE r.research_id = ? AND r.is_active = 1";
    
    $stmt = $conn->prepare($get_research_sql);
    $stmt->bind_param("i", $research_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Research not found or already archived');
    }
    
    $research_data = $result->fetch_assoc();
    
    // Archive the research (set is_active = 0, add archive info)
    $archive_sql = "UPDATE research_papers 
                    SET is_active = 0, 
                        archived_at = NOW(), 
                        archived_by = ?
                    WHERE research_id = ?";
    
    $stmt = $conn->prepare($archive_sql);
    $stmt->bind_param("ii", $_SESSION['user_id'], $research_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to archive research: " . $stmt->error);
    }
    
    // Log the archive action (simplified - using notifications instead)
    $archive_log_msg = "Research '{$research_data['title']}' archived. Reason: {$delete_reason}";
    $log_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')";
    $stmt_log = $conn->prepare($log_sql);
    $stmt_log->bind_param("iss", $_SESSION['user_id'], $research_data['title'], $archive_log_msg);
    $stmt_log->execute();
    
    // Create notification for the uploader (if different from deleter)
    if ($research_data['submitted_by'] != $_SESSION['user_id']) {
        $notification_msg = "Your research '{$research_data['title']}' has been archived by admin. Reason: {$delete_reason}";
        $notification_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'warning')";
        $stmt_notif = $conn->prepare($notification_sql);
        $stmt_notif->bind_param("iss", $research_data['submitted_by'], $research_data['title'], $notification_msg);
        $stmt_notif->execute();
    }
    
    // Create system notification for admin
    $admin_notification = "Research '{$research_data['title']}' by {$research_data['uploader_name']} has been archived";
    $admin_notif_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')";
    $stmt_admin = $conn->prepare($admin_notif_sql);
    $stmt_admin->bind_param("iss", $_SESSION['user_id'], $research_data['title'], $admin_notification);
    $stmt_admin->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Research archived successfully',
        'research_id' => $research_id,
        'archived_title' => $research_data['title']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
