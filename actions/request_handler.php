<?php
session_start();
require_once('../db.php');

// Set JSON response header
header('Content-Type: application/json');

// Authentication check
function isAdminAuthenticated() {
    return isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['Dean', 'Admin']);
}

if (!isAdminAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get request action
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'get_requests':
            getRequests();
            break;
        case 'get_stats':
            getRequestStats();
            break;
        case 'get_departments':
            getDepartments();
            break;
        case 'get_request':
            getRequestDetails();
            break;
        case 'update_status':
            updateRequestStatus();
            break;
        case 'bulk_update':
            bulkUpdateRequests();
            break;
        case 'export':
            exportRequests();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('Request handler error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getRequests() {
    global $conn;

    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit;

    // Build filters for search
    $search_param = '';
    $search_sql = '';
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_param = "%" . $_GET['search'] . "%";
        $search_sql = "AND (title LIKE ? OR requester_name LIKE ?)";
    }

    // Status filter
    $status_sql = '';
    $status_param = '';
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $status_sql = "AND status = ?";
        $status_param = $_GET['status'];
    }

    // Priority filter (only applicable to approval_requests)
    $priority_sql = '';
    $priority_param = '';
    if (isset($_GET['priority']) && !empty($_GET['priority'])) {
        $priority_sql = "AND priority = ?";
        $priority_param = $_GET['priority'];
    }

    // Department filter
    $dept_sql = '';
    $dept_param = '';
    if (isset($_GET['department']) && !empty($_GET['department'])) {
        $dept_sql = "AND department_name = ?";
        $dept_param = $_GET['department'];
    }

    // Date filters
    $date_from_sql = '';
    $date_from_param = '';
    if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
        $date_from_sql = "AND DATE(created_at) >= ?";
        $date_from_param = $_GET['date_from'];
    }

    $date_to_sql = '';
    $date_to_param = '';
    if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
        $date_to_sql = "AND DATE(created_at) <= ?";
        $date_to_param = $_GET['date_to'];
    }

    // Union query to get all pending requests from different tables
    $sql = "
        SELECT * FROM (
            -- Research papers
            SELECT 
                r.research_id as request_id,
                'research' as request_type,
                r.research_id as resource_id,
                r.title,
                SUBSTRING(r.abstract, 1, 200) as description,
                r.submitted_by,
                u1.name as requester_name,
                u1.department as department_name,
                r.status,
                'medium' as priority,
                r.research_type as category,
                r.submission_date as created_at,
                r.approved_at as reviewed_at,
                r.approved_by as reviewed_by,
                r.rejection_reason as admin_feedback
            FROM research_papers r
            LEFT JOIN users u1 ON r.submitted_by = u1.user_id
            WHERE r.status IN ('pending', 'under_review', 'approved', 'rejected')
            
            UNION ALL
            
            -- Learning materials
            SELECT 
                m.material_id as request_id,
                'learning_material' as request_type,
                m.material_id as resource_id,
                m.title,
                SUBSTRING(m.description, 1, 200) as description,
                m.uploaded_by as submitted_by,
                u2.name as requester_name,
                u2.department as department_name,
                m.status,
                'medium' as priority,
                m.material_type as category,
                m.created_at,
                NULL as reviewed_at,
                NULL as reviewed_by,
                NULL as admin_feedback
            FROM learning_materials m
            LEFT JOIN users u2 ON m.uploaded_by = u2.user_id
            WHERE m.status IN ('pending', 'approved', 'rejected')
            
            UNION ALL
            
            -- General materials
            SELECT 
                mat.material_id as request_id,
                'material' as request_type,
                mat.material_id as resource_id,
                mat.title,
                SUBSTRING(mat.description, 1, 200) as description,
                mat.uploaded_by as submitted_by,
                u3.name as requester_name,
                u3.department as department_name,
                mat.status,
                'medium' as priority,
                'Material' as category,
                mat.upload_date as created_at,
                mat.approved_at as reviewed_at,
                mat.approved_by as reviewed_by,
                mat.rejection_reason as admin_feedback
            FROM materials mat
            LEFT JOIN users u3 ON mat.uploaded_by = u3.user_id
            WHERE mat.status IN ('pending', 'approved', 'rejected')
            
            UNION ALL
            
            -- Approval requests
            SELECT 
                ar.request_id,
                ar.request_type,
                ar.resource_id,
                CONCAT('Request: ', ar.request_type) as title,
                ar.review_comments as description,
                ar.submitted_by,
                u4.name as requester_name,
                u4.department as department_name,
                ar.status,
                ar.priority,
                ar.request_type as category,
                ar.submitted_at as created_at,
                ar.reviewed_at,
                ar.reviewed_by,
                ar.review_comments as admin_feedback
            FROM approval_requests ar
            LEFT JOIN users u4 ON ar.submitted_by = u4.user_id
            WHERE ar.status IN ('pending', 'under_review', 'approved', 'rejected')
        ) AS all_requests
        WHERE 1=1
        $search_sql
        $status_sql
        $priority_sql
        $dept_sql
        $date_from_sql
        $date_to_sql
        ORDER BY created_at DESC
    ";

    // Count total
    $count_sql = str_replace('SELECT * FROM', 'SELECT COUNT(*) as total FROM', $sql);
    $count_sql = preg_replace('/ORDER BY.*/', '', $count_sql);
    
    $count_stmt = $conn->prepare($count_sql);
    
    // Bind parameters for count
    $bind_types = '';
    $bind_params = [];
    
    if ($search_param) {
        $bind_types .= 'ss';
        $bind_params[] = $search_param;
        $bind_params[] = $search_param;
    }
    if ($status_param) {
        $bind_types .= 's';
        $bind_params[] = $status_param;
    }
    if ($priority_param) {
        $bind_types .= 's';
        $bind_params[] = $priority_param;
    }
    if ($dept_param) {
        $bind_types .= 's';
        $bind_params[] = $dept_param;
    }
    if ($date_from_param) {
        $bind_types .= 's';
        $bind_params[] = $date_from_param;
    }
    if ($date_to_param) {
        $bind_types .= 's';
        $bind_params[] = $date_to_param;
    }
    
    if (!empty($bind_params)) {
        $count_stmt->bind_param($bind_types, ...$bind_params);
    }
    $count_stmt->execute();
    $total_result = $count_stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total = $total_row['total'];

    // Get paginated requests
    $sql .= " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    
    // Bind parameters for main query
    $bind_types_main = $bind_types . 'ii';
    $bind_params_main = array_merge($bind_params, [$limit, $offset]);
    
    if (!empty($bind_params_main)) {
        $stmt->bind_param($bind_types_main, ...$bind_params_main);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    $total_pages = ceil($total / $limit);

    echo json_encode([
        'success' => true,
        'data' => [
            'requests' => $requests,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total' => $total,
                'per_page' => $limit
            ]
        ]
    ]);
}

function getRequestStats() {
    global $conn;

    $stats = [
        'by_status' => [
            'pending' => 0,
            'under_review' => 0,
            'approved' => 0,
            'rejected' => 0
        ],
        'by_priority' => [
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'urgent' => 0
        ],
        'today_approved' => 0
    ];

    // Get status counts from all tables
    $status_sql = "
        SELECT status, COUNT(*) as count FROM (
            SELECT status FROM research_papers WHERE status IN ('pending', 'under_review', 'approved', 'rejected')
            UNION ALL
            SELECT status FROM learning_materials WHERE status IN ('pending', 'approved', 'rejected')
            UNION ALL
            SELECT status FROM materials WHERE status IN ('pending', 'approved', 'rejected')
            UNION ALL
            SELECT status FROM approval_requests WHERE status IN ('pending', 'under_review', 'approved', 'rejected')
        ) AS all_statuses
        GROUP BY status
    ";
    $status_result = $conn->query($status_sql);
    if ($status_result) {
        while ($row = $status_result->fetch_assoc()) {
            $stats['by_status'][$row['status']] = intval($row['count']);
        }
    }

    // Get priority counts (mainly from approval_requests, others default to medium)
    $priority_sql = "
        SELECT priority, COUNT(*) as count FROM (
            SELECT 'medium' as priority FROM research_papers WHERE status IN ('pending', 'under_review')
            UNION ALL
            SELECT 'medium' as priority FROM learning_materials WHERE status = 'pending'
            UNION ALL
            SELECT 'medium' as priority FROM materials WHERE status = 'pending'
            UNION ALL
            SELECT priority FROM approval_requests WHERE status IN ('pending', 'under_review')
        ) AS all_priorities
        GROUP BY priority
    ";
    $priority_result = $conn->query($priority_sql);
    if ($priority_result) {
        while ($row = $priority_result->fetch_assoc()) {
            $stats['by_priority'][$row['priority']] = intval($row['count']);
        }
    }

    // Get today's approved count from all tables
    $today_sql = "
        SELECT COUNT(*) as count FROM (
            SELECT approved_at FROM research_papers WHERE status = 'approved' AND DATE(approved_at) = CURDATE()
            UNION ALL
            SELECT approved_at FROM materials WHERE status = 'approved' AND DATE(approved_at) = CURDATE()
            UNION ALL
            SELECT reviewed_at as approved_at FROM approval_requests WHERE status = 'approved' AND DATE(reviewed_at) = CURDATE()
        ) AS today_approved
    ";
    $today_result = $conn->query($today_sql);
    if ($today_result && $today_row = $today_result->fetch_assoc()) {
        $stats['today_approved'] = intval($today_row['count']);
    }

    echo json_encode(['success' => true, 'data' => $stats]);
}

function getDepartments() {
    global $conn;

    $sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
    $result = $conn->query($sql);

    $departments = [];
    while ($row = $result->fetch_assoc()) {
        $departments[] = ['department_name' => $row['department']];
    }

    echo json_encode(['success' => true, 'data' => $departments]);
}

function getRequestDetails() {
    global $conn;

    $request_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $request_type = isset($_GET['type']) ? $_GET['type'] : '';

    if (!$request_id) {
        throw new Exception('Invalid request ID');
    }

    // First try to find the request in the unified view
    $sql = "
        SELECT * FROM (
            -- Research papers
            SELECT 
                r.research_id as request_id,
                'research' as request_type,
                r.research_id as resource_id,
                r.title,
                r.abstract as description,
                r.submitted_by,
                u1.name as requester_name,
                u1.department as department_name,
                r.status,
                'medium' as priority,
                r.research_type as category,
                r.submission_date as created_at,
                r.approved_at as reviewed_at,
                r.approved_by as reviewed_by,
                r.rejection_reason as admin_feedback,
                r.file_path as file_path
            FROM research_papers r
            LEFT JOIN users u1 ON r.submitted_by = u1.user_id
            WHERE r.research_id = ?
            
            UNION ALL
            
            -- Learning materials
            SELECT 
                m.material_id as request_id,
                'learning_material' as request_type,
                m.material_id as resource_id,
                m.title,
                m.description,
                m.uploaded_by as submitted_by,
                u2.name as requester_name,
                u2.department as department_name,
                m.status,
                'medium' as priority,
                m.material_type as category,
                m.created_at,
                NULL as reviewed_at,
                NULL as reviewed_by,
                NULL as admin_feedback,
                m.file_path as file_path
            FROM learning_materials m
            LEFT JOIN users u2 ON m.uploaded_by = u2.user_id
            WHERE m.material_id = ?
            
            UNION ALL
            
            -- General materials
            SELECT 
                mat.material_id as request_id,
                'material' as request_type,
                mat.material_id as resource_id,
                mat.title,
                mat.description,
                mat.uploaded_by as submitted_by,
                u3.name as requester_name,
                u3.department as department_name,
                mat.status,
                'medium' as priority,
                'Material' as category,
                mat.upload_date as created_at,
                mat.approved_at as reviewed_at,
                mat.approved_by as reviewed_by,
                mat.rejection_reason as admin_feedback,
                mat.file_path as file_path
            FROM materials mat
            LEFT JOIN users u3 ON mat.uploaded_by = u3.user_id
            WHERE mat.material_id = ?
            
            UNION ALL
            
            -- Approval requests
            SELECT 
                ar.request_id,
                ar.request_type,
                ar.resource_id,
                CONCAT('Request: ', ar.request_type) as title,
                ar.review_comments as description,
                ar.submitted_by,
                u4.name as requester_name,
                u4.department as department_name,
                ar.status,
                ar.priority,
                ar.request_type as category,
                ar.submitted_at as created_at,
                ar.reviewed_at,
                ar.reviewed_by,
                ar.review_comments as admin_feedback,
                NULL as file_path
            FROM approval_requests ar
            LEFT JOIN users u4 ON ar.submitted_by = u4.user_id
            WHERE ar.request_id = ?
        ) AS all_requests
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $request_id, $request_id, $request_id, $request_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Handle attachments if file_path exists
        if (!empty($row['file_path'])) {
            $row['attachments'] = [
                [
                    'name' => basename($row['file_path']),
                    'path' => $row['file_path']
                ]
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        throw new Exception('Request not found');
    }
}

function updateRequestStatus() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    $request_id = isset($input['request_id']) ? intval($input['request_id']) : 0;
    $request_type = isset($input['request_type']) ? $input['request_type'] : '';
    $status = isset($input['status']) ? $input['status'] : '';
    $feedback = isset($input['feedback']) ? trim($input['feedback']) : '';

    if (!$request_id || !$status) {
        throw new Exception('Request ID and status are required');
    }

    $valid_statuses = ['pending', 'under_review', 'approved', 'rejected'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid status');
    }

    $conn->begin_transaction();

    try {
        // Determine which table to update based on request type
        $table = '';
        $id_field = '';
        $feedback_field = '';
        $approved_by_field = 'approved_by';
        $approved_at_field = 'approved_at';
        
        switch($request_type) {
            case 'research':
                $table = 'research_papers';
                $id_field = 'research_id';
                $feedback_field = 'rejection_reason';
                break;
            case 'learning_material':
            case 'learning material':
                $table = 'learning_materials';
                $id_field = 'material_id';
                $feedback_field = 'rejection_reason';
                break;
            case 'material':
                $table = 'materials';
                $id_field = 'material_id';
                $feedback_field = 'rejection_reason';
                break;
            case 'approval_request':
            default:
                $table = 'approval_requests';
                $id_field = 'request_id';
                $feedback_field = 'admin_feedback';
                break;
        }
        
        // Build update SQL based on table
        if ($table === 'approval_requests') {
            $sql = "UPDATE $table SET status = ?, $feedback_field = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE $id_field = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssii', $status, $feedback, $_SESSION['user_id'], $request_id);
        } else {
            // For research_papers, learning_materials, and materials
            $sql = "UPDATE $table SET status = ?";
            $params = [$status];
            $types = 's';
            
            if ($feedback) {
                $sql .= ", $feedback_field = ?";
                $params[] = $feedback;
                $types .= 's';
            }
            
            if ($status === 'approved') {
                $sql .= ", $approved_by_field = ?, $approved_at_field = NOW()";
                $params[] = $_SESSION['user_id'];
                $types .= 'i';
            }
            
            $sql .= " WHERE $id_field = ?";
            $params[] = $request_id;
            $types .= 'i';
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception('Failed to update request status');
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Request status updated successfully']);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function bulkUpdateRequests() {
    global $conn;

    $input = json_decode(file_get_contents('php://input'), true);
    $request_ids = isset($input['request_ids']) ? $input['request_ids'] : [];
    $action = isset($input['action']) ? $input['action'] : '';
    $feedback = isset($input['feedback']) ? trim($input['feedback']) : '';

    if (empty($request_ids) || !$action) {
        throw new Exception('Request IDs and action are required');
    }

    $valid_actions = ['approve', 'reject', 'delete'];
    if (!in_array($action, $valid_actions)) {
        throw new Exception('Invalid action');
    }

    $status_map = [
        'approve' => 'approved',
        'reject' => 'rejected',
        'delete' => 'rejected'
    ];

    $status = $status_map[$action];
    $placeholders = str_repeat('?,', count($request_ids) - 1) . '?';

    $conn->begin_transaction();

    try {
        // Update all selected requests
        $sql = "UPDATE approval_requests SET status = ?, admin_feedback = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE request_id IN ($placeholders)";
        $stmt = $conn->prepare($sql);

        $params = [$status, $feedback, $_SESSION['user_id']];
        $params = array_merge($params, $request_ids);
        $types = 'ssi' . str_repeat('i', count($request_ids));
        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception('Failed to update requests');
        }

        $updated_count = $stmt->affected_rows;

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Bulk update completed successfully',
            'data' => [
                'updated_count' => $updated_count,
                'total_requested' => count($request_ids)
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function exportRequests() {
    global $conn;

    // Build filters (same as getRequests)
    $where_conditions = ["ar.status IN ('pending', 'under_review', 'approved', 'rejected')"];
    $params = [];
    $types = '';

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = "%" . $_GET['search'] . "%";
        $where_conditions[] = "(ar.title LIKE ? OR ar.description LIKE ? OR u.name LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= 'sss';
    }

    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $where_conditions[] = "ar.status = ?";
        $params[] = $_GET['status'];
        $types .= 's';
    }

    if (isset($_GET['priority']) && !empty($_GET['priority'])) {
        $where_conditions[] = "ar.priority = ?";
        $params[] = $_GET['priority'];
        $types .= 's';
    }

    if (isset($_GET['department']) && !empty($_GET['department'])) {
        $where_conditions[] = "u.department = ?";
        $params[] = $_GET['department'];
        $types .= 's';
    }

    $where_clause = implode(' AND ', $where_conditions);

    $sql = "SELECT ar.*, u.name as requester_name, u.department as department_name
            FROM approval_requests ar
            LEFT JOIN users u ON ar.submitted_by = u.user_id
            WHERE $where_clause
            ORDER BY ar.submitted_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="requests_export_' . date('Y-m-d_H-i-s') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, ['Request ID', 'Title', 'Type', 'Requester', 'Department', 'Status', 'Priority', 'Submitted Date', 'Reviewed Date', 'Feedback']);

    // CSV data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['request_id'],
            $row['title'],
            $row['request_type'],
            $row['requester_name'],
            $row['department_name'],
            $row['status'],
            $row['priority'],
            $row['submitted_at'],
            $row['reviewed_at'],
            $row['admin_feedback']
        ]);
    }

    fclose($output);
    exit;
}
?>
