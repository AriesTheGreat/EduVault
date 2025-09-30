<?php
session_start();
require_once '../config/database.php';

/**
 * Student Content Access Handler
 * Provides content based on student's department, program, and course enrollments
 */

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class StudentContentHandler {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = getDatabase();
        $this->conn = $this->db->getConnection();
        
        if (!$this->db->isConnected()) {
            $this->sendResponse(false, 'Database connection failed', [], 500);
        }
    }
    
    /**
     * Get approved research papers (public to all users)
     */
    public function getPublicResearch($filters = []) {
        try {
            $sql = "SELECT 
                        r.research_id,
                        r.title,
                        r.abstract as description,
                        r.authors,
                        r.research_type as category,
                        r.keywords,
                        r.year_published,
                        r.file_path,
                        r.file_size,
                        r.submission_date,
                        r.download_count,
                        COALESCE(d.department_name, r.keywords, 'Unknown') as department_name,
                        submitter.name as submitted_by_name
                    FROM research_papers r
                    LEFT JOIN programs p ON r.program_id = p.program_id
                    LEFT JOIN departments d ON p.department_id = d.department_id
                    LEFT JOIN users submitter ON r.submitted_by = submitter.user_id
                    WHERE r.status = 'approved' AND r.is_active = 1";
            
            $params = [];
            
            // Apply filters
            if (!empty($filters['search'])) {
                $sql .= " AND (r.title LIKE ? OR r.abstract LIKE ? OR r.authors LIKE ? OR r.keywords LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($filters['category'])) {
                $sql .= " AND r.research_type = ?";
                $params[] = $filters['category'];
            }
            
            if (!empty($filters['department'])) {
                $sql .= " AND d.department_name = ?";
                $params[] = $filters['department'];
            }
            
            if (!empty($filters['year'])) {
                $sql .= " AND r.year_published = ?";
                $params[] = intval($filters['year']);
            }
            
            $sql .= " ORDER BY r.submission_date DESC";
            
            // Pagination
            $page = max(1, intval($filters['page'] ?? 1));
            $limit = max(1, min(50, intval($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $research = $this->db->fetchAll($sql, $params);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM research_papers r 
                         LEFT JOIN programs p ON r.program_id = p.program_id
                         LEFT JOIN departments d ON p.department_id = d.department_id
                         WHERE r.status = 'approved' AND r.is_active = 1";
            $totalRecords = $this->db->fetch($countSql)['total'];
            
            $this->sendResponse(true, 'Research retrieved successfully', [
                'research' => $research,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalRecords,
                    'total_pages' => ceil($totalRecords / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error fetching public research: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching research: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get materials specific to student's department, program, and enrolled classes
     */
    public function getStudentMaterials($studentId, $filters = []) {
        try {
            // Get student's department and enrolled classes
            $studentSql = "SELECT u.department, u.program_id FROM users u WHERE u.user_id = ?";
            $student = $this->db->fetch($studentSql, [$studentId]);
            
            if (!$student) {
                $this->sendResponse(false, 'Student not found', [], 404);
                return;
            }
            
            $sql = "SELECT 
                        lm.material_id,
                        lm.title,
                        lm.description,
                        lm.material_type as category,
                        lm.file_path,
                        lm.file_name,
                        lm.file_size,
                        lm.created_at,
                        lm.semester,
                        lm.academic_year,
                        c.subject_name,
                        c.department,
                        c.program,
                        c.year_level,
                        c.instructor,
                        uploader.name as uploaded_by_name
                    FROM learning_materials lm
                    JOIN classes c ON lm.class_id = c.id
                    LEFT JOIN users uploader ON lm.uploaded_by = uploader.user_id
                    WHERE lm.status = 'approved' 
                        AND lm.is_active = 1
                        AND (
                            c.department = ? OR 
                            lm.visibility = 'public' OR
                            (lm.visibility = 'department' AND c.department = ?)
                        )";
            
            $params = [$student['department'], $student['department']];
            
            // Apply additional filters
            if (!empty($filters['search'])) {
                $sql .= " AND (lm.title LIKE ? OR lm.description LIKE ? OR c.subject_name LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($filters['material_type'])) {
                $sql .= " AND lm.material_type = ?";
                $params[] = $filters['material_type'];
            }
            
            if (!empty($filters['subject'])) {
                $sql .= " AND c.subject_name LIKE ?";
                $params[] = '%' . $filters['subject'] . '%';
            }
            
            if (!empty($filters['year_level'])) {
                $sql .= " AND c.year_level = ?";
                $params[] = $filters['year_level'];
            }
            
            $sql .= " ORDER BY lm.created_at DESC";
            
            // Pagination
            $page = max(1, intval($filters['page'] ?? 1));
            $limit = max(1, min(50, intval($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $materials = $this->db->fetchAll($sql, $params);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM learning_materials lm
                         JOIN classes c ON lm.class_id = c.id
                         WHERE lm.status = 'approved' AND lm.is_active = 1
                         AND (c.department = ? OR lm.visibility = 'public' OR (lm.visibility = 'department' AND c.department = ?))";
            $totalRecords = $this->db->fetch($countSql, [$student['department'], $student['department']])['total'];
            
            $this->sendResponse(true, 'Materials retrieved successfully', [
                'materials' => $materials,
                'student_info' => [
                    'department' => $student['department'],
                    'program_id' => $student['program_id']
                ],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalRecords,
                    'total_pages' => ceil($totalRecords / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error fetching student materials: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching materials: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get dashboard statistics for student
     */
    public function getStudentStats($studentId) {
        try {
            $studentSql = "SELECT u.department, u.program_id FROM users u WHERE u.user_id = ?";
            $student = $this->db->fetch($studentSql, [$studentId]);
            
            if (!$student) {
                $this->sendResponse(false, 'Student not found', [], 404);
                return;
            }
            
            $stats = [];
            
            // Total approved research count
            $researchCount = $this->db->fetch("SELECT COUNT(*) as count FROM research_papers WHERE status = 'approved' AND is_active = 1")['count'];
            
            // Available materials for student's department
            $materialsSql = "SELECT COUNT(*) as count FROM learning_materials lm
                             JOIN classes c ON lm.class_id = c.id
                             WHERE lm.status = 'approved' AND lm.is_active = 1
                             AND (c.department = ? OR lm.visibility = 'public' OR (lm.visibility = 'department' AND c.department = ?))";
            $materialsCount = $this->db->fetch($materialsSql, [$student['department'], $student['department']])['count'];
            
            // Recent uploads count (last 30 days)
            $recentSql = "SELECT COUNT(*) as count FROM (
                             SELECT submission_date as upload_date FROM research_papers WHERE status = 'approved' AND is_active = 1 AND submission_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                             UNION ALL
                             SELECT lm.created_at as upload_date FROM learning_materials lm
                             JOIN classes c ON lm.class_id = c.id
                             WHERE lm.status = 'approved' AND lm.is_active = 1 AND lm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                             AND (c.department = ? OR lm.visibility = 'public')
                         ) as recent_uploads";
            $recentCount = $this->db->fetch($recentSql, [$student['department']])['count'];
            
            $stats = [
                'total_research' => intval($researchCount),
                'available_materials' => intval($materialsCount),
                'recent_uploads' => intval($recentCount),
                'student_department' => $student['department']
            ];
            
            $this->sendResponse(true, 'Statistics retrieved successfully', $stats);
            
        } catch (Exception $e) {
            error_log("Error fetching student stats: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching statistics: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Track download activity
     */
    public function trackDownload($userId, $resourceType, $resourceId) {
        try {
            $sql = "INSERT INTO downloads (user_id, resource_type, resource_id, download_date, ip_address) 
                    VALUES (?, ?, ?, NOW(), ?)";
            $params = [$userId, $resourceType, $resourceId, $_SERVER['REMOTE_ADDR'] ?? 'unknown'];
            
            $this->db->execute($sql, $params);
            
            // Update download count in the respective table
            if ($resourceType === 'research') {
                $this->db->execute("UPDATE research_papers SET download_count = download_count + 1 WHERE research_id = ?", [$resourceId]);
            }
            
            $this->sendResponse(true, 'Download tracked successfully');
            
        } catch (Exception $e) {
            error_log("Error tracking download: " . $e->getMessage());
            $this->sendResponse(false, 'Error tracking download: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($success, $message, $data = [], $statusCode = 200) {
        http_response_code($statusCode);
        
        $response = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Handle the request
try {
    // Basic authentication check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required', 'data' => [], 'timestamp' => date('Y-m-d H:i:s')]);
        exit;
    }
    
    $handler = new StudentContentHandler();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_public_research':
            $filters = [];
            foreach (['search', 'category', 'department', 'year', 'page', 'limit'] as $param) {
                if (!empty($_GET[$param])) {
                    $filters[$param] = $_GET[$param];
                }
            }
            $handler->getPublicResearch($filters);
            break;
            
        case 'get_student_materials':
            $studentId = $_SESSION['user_id'];
            $filters = [];
            foreach (['search', 'material_type', 'subject', 'year_level', 'page', 'limit'] as $param) {
                if (!empty($_GET[$param])) {
                    $filters[$param] = $_GET[$param];
                }
            }
            $handler->getStudentMaterials($studentId, $filters);
            break;
            
        case 'get_student_stats':
            $studentId = $_SESSION['user_id'];
            $handler->getStudentStats($studentId);
            break;
            
        case 'track_download':
            $resourceType = $_POST['resource_type'] ?? '';
            $resourceId = intval($_POST['resource_id'] ?? 0);
            if ($resourceType && $resourceId) {
                $handler->trackDownload($_SESSION['user_id'], $resourceType, $resourceId);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid parameters', 'data' => [], 'timestamp' => date('Y-m-d H:i:s')]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified',
                'data' => [],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Student content handler error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'data' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
