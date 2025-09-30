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
if (!$input || !isset($input['item_id']) || !isset($input['item_type'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$item_id = (int)$input['item_id'];
$item_type = $input['item_type'];

// Validate item type
$valid_types = ['research', 'material', 'reference'];
if (!in_array($item_type, $valid_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid item type']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Determine table and ID column based on item type
    switch ($item_type) {
        case 'research':
            $table = 'research_papers';
            $id_column = 'research_id';
            break;
        case 'material':
            $table = 'materials';
            $id_column = 'material_id';
            break;
        case 'reference':
            $table = 'references';
            $id_column = 'reference_id';
            break;
    }

    // First, check if the item exists and is archived
    $check_sql = "SELECT * FROM {$table} WHERE {$id_column} = ? AND is_active = 0 AND archived_at IS NOT NULL";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Item not found or not archived');
    }
    
    $item_data = $result->fetch_assoc();
    
    // Restore the item (set is_active = 1, clear archive info)
    $restore_sql = "UPDATE {$table} 
                    SET is_active = 1, 
                        archived_at = NULL, 
                        archived_by = NULL
                    WHERE {$id_column} = ?";
    
    $stmt = $conn->prepare($restore_sql);
    $stmt->bind_param("i", $item_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to restore item: " . $stmt->error);
    }
    
    // Log the restore action (simplified - using notifications instead)
    $restore_log_msg = ucfirst($item_type) . " '{$item_data['title']}' has been restored from archive";
    $log_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')";
    $stmt_log = $conn->prepare($log_sql);
    $stmt_log->bind_param("iss", $_SESSION['user_id'], $item_data['title'], $restore_log_msg);
    $stmt_log->execute();
    
    // Create notification for the original uploader (if different from restorer)
    $uploader_field = ($item_type === 'research') ? 'submitted_by' : 'uploaded_by';
    if (isset($item_data[$uploader_field]) && $item_data[$uploader_field] != $_SESSION['user_id']) {
        $notification_msg = "Your {$item_type} '{$item_data['title']}' has been restored from archive";
        $notification_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')";
        $stmt_notif = $conn->prepare($notification_sql);
        $stmt_notif->bind_param("iss", $item_data[$uploader_field], $item_data['title'], $notification_msg);
        $stmt_notif->execute();
    }
    
    // Create system notification for admin
    $admin_notification = ucfirst($item_type) . " '{$item_data['title']}' has been restored from archive";
    $admin_notif_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')";
    $stmt_admin = $conn->prepare($admin_notif_sql);
    $stmt_admin->bind_param("iss", $_SESSION['user_id'], $item_data['title'], $admin_notification);
    $stmt_admin->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($item_type) . ' restored successfully',
        'item_id' => $item_id,
        'item_type' => $item_type,
        'restored_title' => $item_data['title']
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
