<?php
session_start();
require_once '../config/database.php';

/**
 * Archive Management Handler
 * Handles archived items (classes, materials, research) with restore and permanent delete functionality
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

class ArchiveHandler {
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
     * Get all archived items with filtering and pagination
     */
    public function getArchivedItems($filters = []) {
        try {
            $sql = "SELECT 
                        'class' as item_type,
                        id as item_id,
                        subject_name as title,
                        instructor as created_by_name,
                        department,
                        created_at,
                        updated_at as archived_at,
                        'N/A' as file_path
                    FROM classes 
                    WHERE is_active = 0
                    
                    UNION ALL
                    
                    SELECT 
                        'material' as item_type,
                        material_id as item_id,
                        title,
                        (SELECT name FROM users WHERE user_id = lm.uploaded_by) as created_by_name,
                        (SELECT department FROM classes WHERE id = lm.class_id) as department,
                        created_at,
                        updated_at as archived_at,
                        file_path
                    FROM learning_materials lm
                    WHERE is_active = 0
                    
                    UNION ALL
                    
                    SELECT 
                        'research' as item_type,
                        research_id as item_id,
                        title,
                        authors as created_by_name,
                        COALESCE(
                            (SELECT d.department_name FROM programs p 
                             JOIN departments d ON p.department_id = d.department_id 
                             WHERE p.program_id = r.program_id), 
                            'Unknown'
                        ) as department,
                        submission_date as created_at,
                        approved_at as archived_at,
                        file_path
                    FROM research_papers r
                    WHERE is_active = 0";
            
            $params = [];
            
            // Apply filters
            if (!empty($filters['item_type'])) {
                $sql = "SELECT * FROM (" . $sql . ") as archived_items WHERE item_type = ?";
                $params[] = $filters['item_type'];
            }
            
            if (!empty($filters['department'])) {
                if (empty($params)) {
                    $sql = "SELECT * FROM (" . $sql . ") as archived_items WHERE department LIKE ?";
                } else {
                    $sql .= " AND department LIKE ?";
                }
                $params[] = '%' . $filters['department'] . '%';
            }
            
            if (!empty($filters['search'])) {
                if (empty($params)) {
                    $sql = "SELECT * FROM (" . $sql . ") as archived_items WHERE (title LIKE ? OR created_by_name LIKE ?)";
                } else {
                    $sql .= " AND (title LIKE ? OR created_by_name LIKE ?)";
                }
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Add ordering if we haven't wrapped in subquery yet
            if (strpos($sql, 'archived_items WHERE') === false) {
                $sql = "SELECT * FROM (" . $sql . ") as archived_items";
            }
            $sql .= " ORDER BY archived_at DESC";
            
            // Pagination
            $page = max(1, intval($filters['page'] ?? 1));
            $limit = max(1, min(100, intval($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $items = $this->db->fetchAll($sql, $params);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM (
                            SELECT id FROM classes WHERE is_active = 0
                            UNION ALL
                            SELECT material_id FROM learning_materials WHERE is_active = 0
                            UNION ALL
                            SELECT research_id FROM research_papers WHERE is_active = 0
                        ) as total_archived";
            $totalRecords = $this->db->fetch($countSql)['total'];
            
            $this->sendResponse(true, 'Archived items retrieved successfully', [
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $totalRecords,
                    'total_pages' => ceil($totalRecords / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error fetching archived items: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching archived items: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get archive statistics for dashboard
     */
    public function getArchiveStats() {
        try {
            $stats = [];
            
            // Count by type
            $classCount = $this->db->fetch("SELECT COUNT(*) as count FROM classes WHERE is_active = 0")['count'];
            $materialCount = $this->db->fetch("SELECT COUNT(*) as count FROM learning_materials WHERE is_active = 0")['count'];
            $researchCount = $this->db->fetch("SELECT COUNT(*) as count FROM research_papers WHERE is_active = 0")['count'];
            
            $stats['by_type'] = [
                'classes' => intval($classCount),
                'materials' => intval($materialCount),
                'research' => intval($researchCount)
            ];
            
            $stats['total_archived'] = intval($classCount) + intval($materialCount) + intval($researchCount);
            
            // Recent archives (last 7 days)
            $recentSql = "SELECT COUNT(*) as count FROM (
                             SELECT updated_at as archived_at FROM classes WHERE is_active = 0 AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                             UNION ALL
                             SELECT updated_at as archived_at FROM learning_materials WHERE is_active = 0 AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                             UNION ALL
                             SELECT approved_at as archived_at FROM research_papers WHERE is_active = 0 AND approved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         ) as recent_archived";
            $stats['recent_archived'] = intval($this->db->fetch($recentSql)['count']);
            
            $this->sendResponse(true, 'Archive statistics retrieved successfully', $stats);
            
        } catch (Exception $e) {
            error_log("Error fetching archive statistics: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching archive statistics: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Restore archived item
     */
    public function restoreItem($itemType, $itemId) {
        try {
            $this->db->beginTransaction();
            
            $table = '';
            $idField = '';
            
            switch ($itemType) {
                case 'class':
                    $table = 'classes';
                    $idField = 'id';
                    break;
                case 'material':
                    $table = 'learning_materials';
                    $idField = 'material_id';
                    break;
                case 'research':
                    $table = 'research_papers';
                    $idField = 'research_id';
                    break;
                default:
                    throw new Exception('Invalid item type');
            }
            
            $sql = "UPDATE {$table} SET is_active = 1, updated_at = NOW() WHERE {$idField} = ?";
            $stmt = $this->db->execute($sql, [$itemId]);
            
            if ($this->db->affectedRows() === 0) {
                throw new Exception('Item not found or already restored');
            }
            
            // If restoring a class, also restore its materials
            if ($itemType === 'class') {
                $materialsSql = "UPDATE learning_materials SET is_active = 1, updated_at = NOW() WHERE class_id = ? AND is_active = 0";
                $this->db->execute($materialsSql, [$itemId]);
            }
            
            // Log the action
            $this->logAction($_SESSION['user_id'] ?? 1, 'restore_item', "{$itemType} ID {$itemId} restored");
            
            $this->db->commit();
            
            $this->sendResponse(true, ucfirst($itemType) . ' restored successfully', [
                'item_type' => $itemType,
                'item_id' => $itemId
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error restoring item: " . $e->getMessage());
            $this->sendResponse(false, 'Error restoring item: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Permanently delete archived item
     */
    public function permanentDelete($itemType, $itemId) {
        try {
            $this->db->beginTransaction();
            
            $table = '';
            $idField = '';
            $filePath = '';
            
            switch ($itemType) {
                case 'class':
                    $table = 'classes';
                    $idField = 'id';
                    
                    // First get all materials associated with this class to delete their files
                    $materialsSql = "SELECT file_path FROM learning_materials WHERE class_id = ?";
                    $materials = $this->db->fetchAll($materialsSql, [$itemId]);
                    
                    // Delete material files
                    foreach ($materials as $material) {
                        if (!empty($material['file_path'])) {
                            $fullPath = '../' . $material['file_path'];
                            if (file_exists($fullPath)) {
                                unlink($fullPath);
                            }
                        }
                    }
                    
                    // Delete materials from database
                    $deleteMaterialsSql = "DELETE FROM learning_materials WHERE class_id = ?";
                    $this->db->execute($deleteMaterialsSql, [$itemId]);
                    
                    break;
                    
                case 'material':
                    $table = 'learning_materials';
                    $idField = 'material_id';
                    
                    // Get file path for deletion
                    $fileInfo = $this->db->fetch("SELECT file_path FROM {$table} WHERE {$idField} = ?", [$itemId]);
                    $filePath = $fileInfo['file_path'] ?? '';
                    
                    break;
                    
                case 'research':
                    $table = 'research_papers';
                    $idField = 'research_id';
                    
                    // Get file path for deletion
                    $fileInfo = $this->db->fetch("SELECT file_path FROM {$table} WHERE {$idField} = ?", [$itemId]);
                    $filePath = $fileInfo['file_path'] ?? '';
                    
                    break;
                    
                default:
                    throw new Exception('Invalid item type');
            }
            
            // Delete associated file
            if (!empty($filePath)) {
                $fullPath = '../' . $filePath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            
            // Delete from database
            $sql = "DELETE FROM {$table} WHERE {$idField} = ?";
            $stmt = $this->db->execute($sql, [$itemId]);
            
            if ($this->db->affectedRows() === 0) {
                throw new Exception('Item not found');
            }
            
            // Log the action
            $this->logAction($_SESSION['user_id'] ?? 1, 'permanent_delete', "{$itemType} ID {$itemId} permanently deleted");
            
            $this->db->commit();
            
            $this->sendResponse(true, ucfirst($itemType) . ' permanently deleted', [
                'item_type' => $itemType,
                'item_id' => $itemId
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error permanently deleting item: " . $e->getMessage());
            $this->sendResponse(false, 'Error permanently deleting item: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Bulk operations on archived items
     */
    public function bulkOperation($action, $items) {
        try {
            $this->db->beginTransaction();
            
            $successCount = 0;
            $errors = [];
            
            foreach ($items as $item) {
                try {
                    if ($action === 'restore') {
                        $this->restoreItem($item['type'], $item['id']);
                    } elseif ($action === 'delete') {
                        $this->permanentDelete($item['type'], $item['id']);
                    } else {
                        throw new Exception('Invalid action');
                    }
                    $successCount++;
                } catch (Exception $e) {
                    $errors[] = "Failed to {$action} {$item['type']} ID {$item['id']}: " . $e->getMessage();
                }
            }
            
            $this->db->commit();
            
            $this->sendResponse(true, "Bulk {$action} completed", [
                'success_count' => $successCount,
                'total_count' => count($items),
                'errors' => $errors
            ]);
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error in bulk operation: " . $e->getMessage());
            $this->sendResponse(false, 'Error in bulk operation: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Log admin actions
     */
    private function logAction($userId, $action, $details) {
        try {
            $sql = "INSERT INTO accesslog (user_id, action, session_id, ip_address, user_agent, timestamp) 
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            
            $params = [
                $userId,
                $action . ' - ' . $details,
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            
            $this->db->execute($sql, $params);
            
        } catch (Exception $e) {
            // Log errors but don't fail the main operation
            error_log("Error logging action: " . $e->getMessage());
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
    // Authentication check
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Dean', 'Admin'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'data' => [], 'timestamp' => date('Y-m-d H:i:s')]);
        exit;
    }
    
    $handler = new ArchiveHandler();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_archived_items':
            $filters = [];
            foreach (['item_type', 'department', 'search', 'page', 'limit'] as $param) {
                if (!empty($_GET[$param])) {
                    $filters[$param] = $_GET[$param];
                }
            }
            $handler->getArchivedItems($filters);
            break;
            
        case 'get_archive_stats':
            $handler->getArchiveStats();
            break;
            
        case 'restore_item':
            $itemType = $_POST['item_type'] ?? '';
            $itemId = intval($_POST['item_id'] ?? 0);
            if ($itemType && $itemId) {
                $handler->restoreItem($itemType, $itemId);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid parameters', 'data' => [], 'timestamp' => date('Y-m-d H:i:s')]);
            }
            break;
            
        case 'permanent_delete':
            $itemType = $_POST['item_type'] ?? '';
            $itemId = intval($_POST['item_id'] ?? 0);
            if ($itemType && $itemId) {
                $handler->permanentDelete($itemType, $itemId);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid parameters', 'data' => [], 'timestamp' => date('Y-m-d H:i:s')]);
            }
            break;
            
        case 'bulk_operation':
            $data = json_decode(file_get_contents('php://input'), true);
            $bulkAction = $data['bulk_action'] ?? '';
            $items = $data['items'] ?? [];
            
            if ($bulkAction && !empty($items)) {
                $handler->bulkOperation($bulkAction, $items);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid bulk operation parameters', 'data' => [], 'timestamp' => date('Y-m-d H:i:s')]);
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
    error_log("Archive handler error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'data' => [],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
