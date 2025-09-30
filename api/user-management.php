<?php
session_start();
require_once('../db.php');

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

// Authentication check
function isAuthenticated() {
    return isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['Dean', 'Admin']);
}

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

class UserManagementAPI {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    /**
     * Get all users with enhanced details for tables
     */
    public function getUsers() {
        try {
            $sql = "SELECT 
                        u.user_id,
                        u.name,
                        u.email,
                        u.role,
                        u.student_id_number,
                        u.department,
                        u.program_id,
                        p.program_name,
                        u.year_level,
                        u.is_active,
                        u.is_approved,
                        u.email_verified,
                        u.created_at,
                        u.updated_at,
                        CASE 
                            WHEN u.updated_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 'online'
                            WHEN u.updated_at > DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'recently_active'
                            ELSE 'offline'
                        END as status,
                        COALESCE(download_stats.download_count, 0) as downloads,
                        COALESCE(upload_stats.upload_count, 0) as uploads
                    FROM users u
                    LEFT JOIN programs p ON u.program_id = p.program_id
                    LEFT JOIN (
                        SELECT user_id, COUNT(*) as download_count 
                        FROM downloads 
                        GROUP BY user_id
                    ) download_stats ON u.user_id = download_stats.user_id
                    LEFT JOIN (
                        SELECT uploaded_by as user_id, COUNT(*) as upload_count 
                        FROM learning_materials 
                        WHERE is_active = 1 
                        GROUP BY uploaded_by
                        UNION ALL
                        SELECT submitted_by as user_id, COUNT(*) as upload_count 
                        FROM research_papers 
                        WHERE is_active = 1 
                        GROUP BY submitted_by
                    ) upload_stats ON u.user_id = upload_stats.user_id
                    WHERE u.is_active = 1
                    ORDER BY u.created_at DESC";
            
            $result = $this->conn->query($sql);
            $users = [];
            
            while ($row = $result->fetch_assoc()) {
                $users[] = [
                    'user_id' => $row['user_id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'role' => $row['role'],
                    'student_id' => $row['student_id_number'],
                    'program' => $row['program_name'] ?? 'N/A',
                    'department' => $row['department'],
                    'year_level' => $row['year_level'],
                    'status' => $row['is_active'] ? 'active' : 'inactive',
                    'activity_status' => $row['status'],
                    'last_activity' => $row['updated_at'],
                    'downloads' => (int)$row['downloads'],
                    'uploads' => (int)$row['uploads'],
                    'created_at' => $row['created_at'],
                    'email_verified' => (bool)$row['email_verified'],
                    'is_approved' => (bool)$row['is_approved']
                ];
            }
            
            $this->sendResponse(true, 'Users retrieved successfully', $users);
            
        } catch (Exception $e) {
            error_log("Error fetching users: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching users', [], 500);
        }
    }
    
    /**
     * Get comprehensive user statistics for charts and metrics
     */
    public function getUserStats() {
        try {
            $stats = [];
            
            // Total users by role
            $role_sql = "SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role";
            $role_result = $this->conn->query($role_sql);
            $role_stats = [];
            $total_users = 0;
            
            while ($row = $role_result->fetch_assoc()) {
                $role_stats[$row['role']] = (int)$row['count'];
                $total_users += (int)$row['count'];
            }
            
            // Online users (simulated - users active in last 15 minutes)
            $online_sql = "SELECT COUNT(*) as count FROM users 
                          WHERE updated_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) 
                          AND is_active = 1";
            $online_result = $this->conn->query($online_sql);
            $online_users = (int)$online_result->fetch_assoc()['count'];
            
            // New users today
            $today_sql = "SELECT COUNT(*) as count FROM users 
                         WHERE DATE(created_at) = CURDATE() AND is_active = 1";
            $today_result = $this->conn->query($today_sql);
            $new_users_today = (int)$today_result->fetch_assoc()['count'];
            
            // Active users today (users who logged in or were active)
            $active_today_sql = "SELECT COUNT(*) as count FROM users 
                                WHERE DATE(updated_at) = CURDATE() AND is_active = 1";
            $active_today_result = $this->conn->query($active_today_sql);
            $active_users_today = (int)$active_today_result->fetch_assoc()['count'];
            
            // Registration trend (last 30 days)
            $trend_sql = "SELECT DATE(created_at) as date, COUNT(*) as registrations 
                         FROM users 
                         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                         GROUP BY DATE(created_at) 
                         ORDER BY date ASC";
            $trend_result = $this->conn->query($trend_sql);
            $registration_trend = [];
            
            while ($row = $trend_result->fetch_assoc()) {
                $registration_trend[] = [
                    'date' => $row['date'],
                    'registrations' => (int)$row['registrations']
                ];
            }
            
            // Department distribution
            $dept_sql = "SELECT 
                            COALESCE(u.department, 'Unknown') as department, 
                            COUNT(*) as count 
                         FROM users u 
                         WHERE u.is_active = 1 
                         GROUP BY u.department";
            $dept_result = $this->conn->query($dept_sql);
            $department_distribution = [];
            
            while ($row = $dept_result->fetch_assoc()) {
                $department_distribution[$row['department']] = (int)$row['count'];
            }
            
            // User activity breakdown
            $activity_sql = "SELECT 
                               CASE 
                                 WHEN updated_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 'online'
                                 WHEN updated_at > DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 'recently_active'
                                 ELSE 'offline'
                               END as activity_status,
                               COUNT(*) as count
                             FROM users 
                             WHERE is_active = 1 
                             GROUP BY activity_status";
            $activity_result = $this->conn->query($activity_sql);
            $activity_breakdown = [];
            
            while ($row = $activity_result->fetch_assoc()) {
                $activity_breakdown[$row['activity_status']] = (int)$row['count'];
            }
            
            $stats = [
                'total_users' => $total_users,
                'online_users' => $online_users,
                'new_users_today' => $new_users_today,
                'active_users_today' => $active_users_today,
                'students_count' => $role_stats['Student'] ?? 0,
                'instructors_count' => $role_stats['Instructor'] ?? 0,
                'registration_trend' => $registration_trend,
                'role_distribution' => $role_stats,
                'department_distribution' => $department_distribution,
                'activity_breakdown' => $activity_breakdown
            ];
            
            $this->sendResponse(true, 'User statistics retrieved successfully', $stats);
            
        } catch (Exception $e) {
            error_log("Error fetching user stats: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching statistics', [], 500);
        }
    }
    
    /**
     * Get user activity data for charts and timeline
     */
    public function getUserActivity() {
        try {
            // Get recent user activities (simulated from user table and other activities)
            $activities = [];
            
            // Recent registrations
            $reg_sql = "SELECT 
                          u.user_id, u.name, u.role, u.department, 
                          'registration' as action, 
                          u.created_at as timestamp,
                          'Registration completed' as description
                        FROM users u 
                        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        ORDER BY u.created_at DESC 
                        LIMIT 20";
            $reg_result = $this->conn->query($reg_sql);
            
            while ($row = $reg_result->fetch_assoc()) {
                $activities[] = [
                    'user_id' => $row['user_id'],
                    'user_name' => $row['name'],
                    'user_role' => $row['role'],
                    'user_department' => $row['department'],
                    'action' => $row['action'],
                    'description' => $row['description'],
                    'timestamp' => $row['timestamp'],
                    'time_ago' => $this->timeAgo($row['timestamp']),
                    'ip_address' => 'N/A',
                    'status' => 'completed'
                ];
            }
            
            // Recent material uploads
            $material_sql = "SELECT 
                               u.user_id, u.name, u.role, u.department,
                               'material_upload' as action,
                               lm.created_at as timestamp,
                               CONCAT('Uploaded material: ', lm.title) as description
                             FROM learning_materials lm
                             JOIN users u ON lm.uploaded_by = u.user_id
                             WHERE lm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                             ORDER BY lm.created_at DESC
                             LIMIT 15";
            $material_result = $this->conn->query($material_sql);
            
            while ($row = $material_result->fetch_assoc()) {
                $activities[] = [
                    'user_id' => $row['user_id'],
                    'user_name' => $row['name'],
                    'user_role' => $row['role'],
                    'user_department' => $row['department'],
                    'action' => $row['action'],
                    'description' => $row['description'],
                    'timestamp' => $row['timestamp'],
                    'time_ago' => $this->timeAgo($row['timestamp']),
                    'ip_address' => 'N/A',
                    'status' => 'completed'
                ];
            }
            
            // Recent research submissions
            $research_sql = "SELECT 
                               u.user_id, u.name, u.role, u.department,
                               'research_upload' as action,
                               rp.submission_date as timestamp,
                               CONCAT('Submitted research: ', rp.title) as description
                             FROM research_papers rp
                             JOIN users u ON rp.submitted_by = u.user_id
                             WHERE rp.submission_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                             ORDER BY rp.submission_date DESC
                             LIMIT 15";
            $research_result = $this->conn->query($research_sql);
            
            while ($row = $research_result->fetch_assoc()) {
                $activities[] = [
                    'user_id' => $row['user_id'],
                    'user_name' => $row['name'],
                    'user_role' => $row['role'],
                    'user_department' => $row['department'],
                    'action' => $row['action'],
                    'description' => $row['description'],
                    'timestamp' => $row['timestamp'],
                    'time_ago' => $this->timeAgo($row['timestamp']),
                    'ip_address' => 'N/A',
                    'status' => 'completed'
                ];
            }
            
            // Sort activities by timestamp (most recent first)
            usort($activities, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            // Limit to 50 most recent activities
            $activities = array_slice($activities, 0, 50);
            
            $this->sendResponse(true, 'User activity retrieved successfully', $activities);
            
        } catch (Exception $e) {
            error_log("Error fetching user activity: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching user activity', [], 500);
        }
    }
    
    /**
     * Get login history data
     */
    public function getLoginHistory() {
        try {
            // Since we don't have a login_history table, we'll use user data
            $sql = "SELECT 
                      u.user_id, u.name, u.email, u.role, u.department,
                      u.updated_at as login_time,
                      'login' as action,
                      'N/A' as ip_address,
                      'Web Browser' as device,
                      'Success' as status
                    FROM users u 
                    WHERE u.updated_at IS NOT NULL 
                    AND u.is_active = 1
                    ORDER BY u.updated_at DESC 
                    LIMIT 100";
            
            $result = $this->conn->query($sql);
            $logins = [];
            
            while ($row = $result->fetch_assoc()) {
                $logins[] = [
                    'user_name' => $row['name'],
                    'user_email' => $row['email'],
                    'user_role' => $row['role'],
                    'action' => $row['action'],
                    'ip_address' => $row['ip_address'],
                    'device' => $row['device'],
                    'timestamp' => $row['login_time'],
                    'time_ago' => $this->timeAgo($row['login_time'])
                ];
            }
            
            $this->sendResponse(true, 'Login history retrieved successfully', $logins);
            
        } catch (Exception $e) {
            error_log("Error fetching login history: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching login history', [], 500);
        }
    }
    
    /**
     * Helper function to calculate time ago
     */
    private function timeAgo($timestamp) {
        $time = strtotime($timestamp);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
        
        return date('M j, Y', $time);
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($success, $message, $data = [], $httpCode = 200) {
        http_response_code($httpCode);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
}

// Handle requests
try {
    $api = new UserManagementAPI();
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_users':
            $api->getUsers();
            break;
            
        case 'get_stats':
            $api->getUserStats();
            break;
            
        case 'get_activity':
            $api->getUserActivity();
            break;
            
        case 'get_login_history':
            $api->getLoginHistory();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("User management API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
