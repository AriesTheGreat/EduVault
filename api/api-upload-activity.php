<?php
/**
 * Materials API Endpoint
 * Handles CRUD operations for instructional materials
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
$mysqli = new mysqli("localhost", "root", "", "eduvault");
require_once '../db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    switch ($method) {
        case 'GET':
            handleGetMaterials();
            break;
        case 'POST':
            handlePostMaterial();
            break;
        case 'PUT':
            handlePutMaterial();
            break;
        case 'DELETE':
            handleDeleteMaterial();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function handleGetMaterials() {
    global $conn, $user_id, $user_role;
    
    // Get query parameters
    $program = $_GET['program'] ?? '';
    $semester = $_GET['semester'] ?? '';
    $fileType = $_GET['file_type'] ?? '';
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'approved';
    
    // Build base query
    if ($user_role === 'Student') {
        // Students can only see approved materials
        $query = "SELECT m.*, c.course_name, p.program_name, d.department_name, u.name as uploaded_by_name
                  FROM approved_materials_view m
                  LEFT JOIN courses c ON m.course_id = c.course_id
                  LEFT JOIN programs p ON c.program_id = p.program_id
                  LEFT JOIN departments d ON p.department_id = d.department_id
                  LEFT JOIN users u ON m.uploaded_by = u.user_id
                  WHERE 1=1";
    } else {
        // Instructors can see their own materials regardless of status
        $query = "SELECT m.*, c.course_name, p.program_name, d.department_name, u.name as uploaded_by_name
                  FROM materials m
                  LEFT JOIN courses c ON m.course_id = c.course_id
                  LEFT JOIN programs p ON c.program_id = p.program_id
                  LEFT JOIN departments d ON p.department_id = d.department_id
                  LEFT JOIN users u ON m.uploaded_by = u.user_id
                  WHERE m.uploaded_by = ? AND m.is_active = 1";
    }
    
    $params = [];
    $types = '';
    
    if ($user_role === 'Instructor') {
        $params[] = $user_id;
        $types .= 'i';
    }
    
    // Add filters
    if (!empty($program)) {
        $query .= " AND p.program_name LIKE ?";
        $params[] = "%$program%";
        $types .= 's';
    }
    
    if (!empty($semester)) {
        $query .= " AND c.semester = ?";
        $params[] = $semester;
        $types .= 'i';
    }
    
    if (!empty($fileType)) {
        $query .= " AND m.file_path LIKE ?";
        $params[] = "%.$fileType%";
        $types .= 's';
    }
    
    if (!empty($search)) {
        $query .= " AND (m.title LIKE ? OR m.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= 'ss';
    }
    
    if ($user_role === 'Instructor' && !empty($status)) {
        $query .= " AND m.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $query .= " ORDER BY m.upload_date DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }
    
    echo json_encode($materials);
}

function handlePostMaterial() {
    global $conn, $user_id, $user_role;
    
    // Only instructors can upload materials
    if ($user_role !== 'Instructor') {
        http_response_code(403);
        echo json_encode(['error' => 'Only instructors can upload materials']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $course_id = $input['course_id'] ?? null;
    $file_path = $input['file_path'] ?? '';
    $file_size = $input['file_size'] ?? 0;
    
    if (empty($title) || empty($file_path)) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and file path are required']);
        return;
    }
    
    $query = "INSERT INTO materials (title, description, file_path, uploaded_by, course_id, file_size, status, upload_date) 
              VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssiii', $title, $description, $file_path, $user_id, $course_id, $file_size);
    
    if ($stmt->execute()) {
        $material_id = $conn->insert_id;
        
        // Create approval request
        $approval_query = "INSERT INTO approval_requests (request_type, resource_id, submitted_by, status, submitted_at) 
                           VALUES ('material', ?, ?, 'pending', NOW())";
        $approval_stmt = $conn->prepare($approval_query);
        $approval_stmt->bind_param('ii', $material_id, $user_id);
        $approval_stmt->execute();
        
        echo json_encode(['success' => true, 'material_id' => $material_id, 'message' => 'Material uploaded successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload material']);
    }
}

function handlePutMaterial() {
    global $conn, $user_id, $user_role;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $material_id = $input['material_id'] ?? 0;
    
    if (!$material_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Material ID is required']);
        return;
    }
    
    // Check if user owns the material or is admin
    if ($user_role === 'Instructor') {
        $check_query = "SELECT uploaded_by FROM materials WHERE material_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('i', $material_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $material = $result->fetch_assoc();
        
        if (!$material || $material['uploaded_by'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only edit your own materials']);
            return;
        }
    }
    
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $course_id = $input['course_id'] ?? null;
    
    $query = "UPDATE materials SET title = ?, description = ?, course_id = ? WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssii', $title, $description, $course_id, $material_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Material updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update material']);
    }
}

function handleDeleteMaterial() {
    global $conn, $user_id, $user_role;
    
    $material_id = $_GET['id'] ?? 0;
    
    if (!$material_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Material ID is required']);
        return;
    }
    
    // Check if user owns the material or is admin
    if ($user_role === 'Instructor') {
        $check_query = "SELECT uploaded_by FROM materials WHERE material_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('i', $material_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $material = $result->fetch_assoc();
        
        if (!$material || $material['uploaded_by'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only delete your own materials']);
            return;
        }
    }
    
    // Soft delete by setting is_active = 0
    $query = "UPDATE materials SET is_active = 0 WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $material_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Material deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete material']);
    }
}
?>
