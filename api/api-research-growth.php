<?php
/**
 * Research Papers API Endpoint
 * Handles research paper operations for students and instructors
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
            handleGetResearch();
            break;
        case 'POST':
            handlePostResearch();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function handleGetResearch() {
    global $conn, $user_id, $user_role;
    
    // Get query parameters
    $year = $_GET['year'] ?? '';
    $research_type = $_GET['research_type'] ?? '';
    $keywords = $_GET['keywords'] ?? '';
    $search = $_GET['search'] ?? '';
    $program = $_GET['program'] ?? '';
    
    // Students can only see approved research
    if ($user_role === 'Student') {
        $query = "SELECT r.*, p.program_name, d.department_name, u.name as submitted_by_name
                  FROM approved_research_view r
                  LEFT JOIN programs p ON r.program_id = p.program_id
                  LEFT JOIN departments d ON p.department_id = d.department_id
                  LEFT JOIN users u ON r.submitted_by = u.user_id
                  WHERE 1=1";
    } else {
        // Instructors can see their own research regardless of status
        $query = "SELECT r.*, p.program_name, d.department_name, u.name as submitted_by_name
                  FROM research_papers r
                  LEFT JOIN programs p ON r.program_id = p.program_id
                  LEFT JOIN departments d ON p.department_id = d.department_id
                  LEFT JOIN users u ON r.submitted_by = u.user_id
                  WHERE r.submitted_by = ? AND r.is_active = 1";
    }
    
    $params = [];
    $types = '';
    
    if ($user_role === 'Instructor') {
        $params[] = $user_id;
        $types .= 'i';
    }
    
    // Add filters
    if (!empty($year)) {
        $query .= " AND r.year_published = ?";
        $params[] = $year;
        $types .= 'i';
    }
    
    if (!empty($research_type)) {
        $query .= " AND r.research_type = ?";
        $params[] = $research_type;
        $types .= 's';
    }
    
    if (!empty($keywords)) {
        $query .= " AND r.keywords LIKE ?";
        $params[] = "%$keywords%";
        $types .= 's';
    }
    
    if (!empty($search)) {
        $query .= " AND (r.title LIKE ? OR r.abstract LIKE ? OR r.authors LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= 'sss';
    }
    
    if (!empty($program)) {
        $query .= " AND p.program_name LIKE ?";
        $params[] = "%$program%";
        $types .= 's';
    }
    
    $query .= " ORDER BY r.submission_date DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $research = [];
    while ($row = $result->fetch_assoc()) {
        $research[] = $row;
    }
    
    echo json_encode($research);
}

function handlePostResearch() {
    global $conn, $user_id, $user_role;
    
    // Only students can submit research papers
    if ($user_role !== 'Student') {
        http_response_code(403);
        echo json_encode(['error' => 'Only students can submit research papers']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = $input['title'] ?? '';
    $abstract = $input['abstract'] ?? '';
    $authors = $input['authors'] ?? '';
    $program_id = $input['program_id'] ?? null;
    $research_type = $input['research_type'] ?? 'Research Paper';
    $keywords = $input['keywords'] ?? '';
    $year_published = $input['year_published'] ?? date('Y');
    $file_path = $input['file_path'] ?? '';
    $file_size = $input['file_size'] ?? 0;
    
    if (empty($title) || empty($authors) || empty($file_path)) {
        http_response_code(400);
        echo json_encode(['error' => 'Title, authors, and file are required']);
        return;
    }
    
    $query = "INSERT INTO research_papers (title, abstract, authors, file_path, submitted_by, program_id, research_type, keywords, year_published, file_size, status, submission_date) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssiissii', $title, $abstract, $authors, $file_path, $user_id, $program_id, $research_type, $keywords, $year_published, $file_size);
    
    if ($stmt->execute()) {
        $research_id = $conn->insert_id;
        
        // Create approval request
        $approval_query = "INSERT INTO approval_requests (request_type, resource_id, submitted_by, status, submitted_at) 
                           VALUES ('research', ?, ?, 'pending', NOW())";
        $approval_stmt = $conn->prepare($approval_query);
        $approval_stmt->bind_param('ii', $research_id, $user_id);
        $approval_stmt->execute();
        
        echo json_encode(['success' => true, 'research_id' => $research_id, 'message' => 'Research paper submitted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit research paper']);
    }
}
?>
