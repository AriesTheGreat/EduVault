<?php
session_start();
require_once('../db.php');

/**
 * Comprehensive User Management Handler
 * Handles all user management operations with database integration
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

class UserManagementHandler {
    private $conn;
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        
        if (!$this->conn) {
            $this->sendResponse(false, 'Database connection failed', [], 500);
        }
    }
    
    /**
     * Get all users with pagination and filtering
     */
    public function getUsers($filters = []) {
        try {
            $sql = "SELECT
                        u.user_id,
                        u.name,
                        u.first_name,
                        u.last_name,
                        u.email,
                        u.role,
                        u.student_id_number,
                        u.instructor_id_number,
                        u.department_id,
                        d.department_name,
                        u.program_id,
                        p.program_name,
                        u.year_level,
                        u.section,
                        u.phone_number,
                        u.is_active,
                        u.is_approved,
                        u.email_verified,
                        u.last_login,
                        u.login_count,
                        u.profile_picture,
                        u.created_at,
                        u.updated_at,
                        approver.name as approved_by_name
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.department_id
                    LEFT JOIN programs p ON u.program_id = p.program_id
                    LEFT JOIN users approver ON u.approved_by = approver.user_id
                    WHERE 1=1";

            $params = [];
            $types = '';

            // Apply filters
            if (!empty($filters['role'])) {
                $sql .= " AND u.role = ?";
                $params[] = $filters['role'];
                $types .= 's';
            }

            if (!empty($filters['department'])) {
                $sql .= " AND u.department_id = ?";
                $params[] = $filters['department'];
                $types .= 'i';
            }

            if (!empty($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $sql .= " AND u.is_active = 1";
                } elseif ($filters['status'] === 'inactive') {
                    $sql .= " AND u.is_active = 0";
                } elseif ($filters['status'] === 'pending') {
                    $sql .= " AND u.is_approved = 0";
                } elseif ($filters['status'] === 'approved') {
                    $sql .= " AND u.is_approved = 1";
                }
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.student_id_number LIKE ? OR u.instructor_id_number LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $types .= 'ssss';
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND u.created_at >= ?";
                $params[] = $filters['date_from'];
                $types .= 's';
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND u.created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
                $types .= 's';
            }

            // Pagination
            $page = max(1, intval($filters['page'] ?? 1));
            $limit = max(1, min(100, intval($filters['limit'] ?? 10)));
            $offset = ($page - 1) * $limit;

            // Get total count
            $countSql = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) as total FROM', $sql);
            $countSql = preg_replace('/LEFT JOIN.*?ON[^W]*/', '', $countSql);
            $countSql = preg_replace('/ORDER BY.*/', '', $countSql);

            $countStmt = mysqli_prepare($this->conn, $countSql);
            if (!empty($params)) {
                mysqli_stmt_bind_param($countStmt, $types, ...$params);
            }
            mysqli_stmt_execute($countStmt);
            $countResult = mysqli_stmt_get_result($countStmt);
            $totalRecords = mysqli_fetch_assoc($countResult)['total'];
            mysqli_stmt_close($countStmt);

            // Add sorting and pagination
            $sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';

            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);

            $this->sendResponse(true, 'Users retrieved successfully', [
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => (int)$totalRecords,
                    'total_pages' => ceil($totalRecords / $limit)
                ]
            ]);

        } catch (Exception $e) {
            error_log("Error fetching users: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching users: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get user statistics for dashboard
     */
    public function getUserStats() {
        try {
            $stats = [];
            
            // Overall counts by role
            $sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
            $result = $this->conn->query($sql);
            $roleStats = [];
            while ($row = $result->fetch_assoc()) {
                $roleStats[] = $row;
            }
            
            foreach ($roleStats as $stat) {
                $stats['by_role'][$stat['role']] = intval($stat['count']);
            }

            // Active vs inactive users
            $sql = "SELECT is_active, COUNT(*) as count FROM users GROUP BY is_active";
            $result = $this->conn->query($sql);
            $activeStats = [];
            while ($row = $result->fetch_assoc()) {
                $activeStats[] = $row;
            }

            foreach ($activeStats as $stat) {
                $status = $stat['is_active'] ? 'active' : 'inactive';
                $stats['by_status'][$status] = intval($stat['count']);
            }

            // Department distribution
            $sql = "SELECT d.department_name, COUNT(u.user_id) as count
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.department_id
                    GROUP BY d.department_name";
            $result = $this->conn->query($sql);
            $deptStats = [];
            while ($row = $result->fetch_assoc()) {
                $deptStats[] = $row;
            }

            foreach ($deptStats as $stat) {
                $stats['by_department'][$stat['department_name'] ?? 'Unknown'] = intval($stat['count']);
            }

            // Recent registrations (last 30 days)
            $sql = "SELECT DATE(created_at) as date, COUNT(*) as count
                    FROM users
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date";
            $result = $this->conn->query($sql);
            $recentActivity = [];
            while ($row = $result->fetch_assoc()) {
                $recentActivity[] = $row;
            }

            $stats['recent_registrations'] = $recentActivity;

            // Pending approvals
            $sql = "SELECT COUNT(*) as count FROM users WHERE is_approved = 0";
            $result = $this->conn->query($sql);
            $pendingRow = $result->fetch_assoc();
            $stats['pending_approvals'] = intval($pendingRow['count']);

            // Update with simplified structure expected by frontend
            $frontendStats = [
                'total_users' => array_sum($stats['by_role'] ?? []),
                'online_users' => 0, // Will be updated by getOnlineUsers method
                'active_users_today' => $stats['recent_registrations'][0]['count'] ?? 0,
                'new_users_today' => $stats['recent_registrations'][0]['count'] ?? 0,
                'students_count' => $stats['by_role']['Student'] ?? 0,
                'instructors_count' => $stats['by_role']['Instructor'] ?? 0,
                'registration_trend' => $stats['recent_registrations'] ?? []
            ];

            $this->sendResponse(true, 'User statistics retrieved successfully', $frontendStats);

        } catch (Exception $e) {
            error_log("Error fetching user statistics: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching user statistics: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get online users (simplified - users active in last 15 minutes)
     */
    public function getOnlineUsers() {
        try {
            // For now, simulate online users based on recent login activity
            $sql = "SELECT u.user_id, u.name, u.email, u.role, u.last_login
                    FROM users u
                    WHERE u.last_login > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    AND u.is_active = 1
                    ORDER BY u.last_login DESC
                    LIMIT 20";
            
            $result = $this->conn->query($sql);
            $onlineUsers = [];
            
            while ($row = $result->fetch_assoc()) {
                $onlineUsers[] = [
                    'user_id' => $row['user_id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'role' => $row['role'],
                    'last_activity' => $row['last_login'],
                    'ip_address' => 'N/A' // Placeholder
                ];
            }
            
            $this->sendResponse(true, 'Online users retrieved successfully', $onlineUsers);
            
        } catch (Exception $e) {
            error_log("Error getting online users: " . $e->getMessage());
            $this->sendResponse(false, 'Error retrieving online users: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get user activity logs (placeholder until tables are created)
     */
    public function getUserActivity($page = 1, $limit = 20) {
        try {
            // Check if activity_logs table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'activity_logs'");
            if ($tableCheck->num_rows == 0) {
                // Return sample data for now
                $sampleActivities = [
                    [
                        'log_id' => 1,
                        'user_name' => 'John Doe',
                        'user_email' => 'john@example.com',
                        'user_role' => 'Student',
                        'action_type' => 'login',
                        'action_description' => 'User logged in',
                        'ip_address' => '127.0.0.1',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'status' => 'success'
                    ]
                ];
                
                $this->sendResponse(true, 'User activity retrieved (sample data)', [
                    'activities' => $sampleActivities,
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => $limit,
                        'total' => 1,
                        'total_pages' => 1
                    ]
                ]);
                return;
            }
            
            // If table exists, query real data
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT al.*, u.name, u.email, u.role
                    FROM activity_logs al
                    LEFT JOIN users u ON al.user_id = u.user_id
                    ORDER BY al.timestamp DESC
                    LIMIT $limit OFFSET $offset";
            
            $result = $this->conn->query($sql);
            $activities = [];
            
            while ($row = $result->fetch_assoc()) {
                $activities[] = [
                    'log_id' => $row['log_id'],
                    'user_name' => $row['name'],
                    'user_email' => $row['email'],
                    'user_role' => $row['role'],
                    'action_type' => $row['action_type'],
                    'action_description' => $row['action_description'],
                    'ip_address' => $row['ip_address'],
                    'timestamp' => $row['timestamp'],
                    'status' => $row['status']
                ];
            }
            
            $countResult = $this->conn->query("SELECT COUNT(*) as total FROM activity_logs");
            $totalCount = $countResult->fetch_assoc()['total'];
            
            $this->sendResponse(true, 'User activity retrieved successfully', [
                'activities' => $activities,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => (int)$totalCount,
                    'total_pages' => ceil($totalCount / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error getting user activity: " . $e->getMessage());
            $this->sendResponse(false, 'Error retrieving user activity: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get login history (placeholder until tables are created)
     */
    public function getLoginHistory($page = 1, $limit = 20) {
        try {
            // Check if login_history table exists
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'login_history'");
            if ($tableCheck->num_rows == 0) {
                // Return sample data based on user last_login
                $sql = "SELECT u.user_id, u.name, u.email, u.role, u.last_login, u.login_count
                        FROM users u
                        WHERE u.last_login IS NOT NULL
                        ORDER BY u.last_login DESC
                        LIMIT $limit";
                
                $result = $this->conn->query($sql);
                $logins = [];
                
                while ($row = $result->fetch_assoc()) {
                    $logins[] = [
                        'login_id' => $row['user_id'],
                        'user_name' => $row['name'],
                        'user_email' => $row['email'],
                        'user_role' => $row['role'],
                        'login_time' => $row['last_login'],
                        'logout_time' => null,
                        'ip_address' => 'N/A',
                        'login_method' => 'password',
                        'login_status' => 'success',
                        'failure_reason' => null,
                        'session_duration' => null
                    ];
                }
                
                $countResult = $this->conn->query("SELECT COUNT(*) as total FROM users WHERE last_login IS NOT NULL");
                $totalCount = $countResult->fetch_assoc()['total'];
                
                $this->sendResponse(true, 'Login history retrieved (from user data)', [
                    'logins' => $logins,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => (int)$totalCount,
                        'total_pages' => ceil($totalCount / $limit)
                    ]
                ]);
                return;
            }
            
            // If table exists, query real data
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT lh.*, u.name, u.email, u.role
                    FROM login_history lh
                    LEFT JOIN users u ON lh.user_id = u.user_id
                    ORDER BY lh.login_time DESC
                    LIMIT $limit OFFSET $offset";
            
            $result = $this->conn->query($sql);
            $logins = [];
            
            while ($row = $result->fetch_assoc()) {
                $logins[] = [
                    'login_id' => $row['login_id'],
                    'user_name' => $row['name'],
                    'user_email' => $row['email'],
                    'user_role' => $row['role'],
                    'login_time' => $row['login_time'],
                    'logout_time' => $row['logout_time'],
                    'ip_address' => $row['ip_address'],
                    'login_method' => $row['login_method'],
                    'login_status' => $row['login_status'],
                    'failure_reason' => $row['failure_reason'],
                    'session_duration' => $row['session_duration']
                ];
            }
            
            $countResult = $this->conn->query("SELECT COUNT(*) as total FROM login_history");
            $totalCount = $countResult->fetch_assoc()['total'];
            
            $this->sendResponse(true, 'Login history retrieved successfully', [
                'logins' => $logins,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => (int)$totalCount,
                    'total_pages' => ceil($totalCount / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error getting login history: " . $e->getMessage());
            $this->sendResponse(false, 'Error retrieving login history: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Export users to CSV format
     */
    public function exportUsers($role = null) {
        try {
            $whereClause = $role ? "WHERE role = '$role'" : "";
            
            $sql = "SELECT user_id, name, email, role, 
                           CASE WHEN is_active = 1 THEN 'Active' ELSE 'Inactive' END as status,
                           created_at, last_login
                    FROM users $whereClause
                    ORDER BY created_at DESC";
            
            $result = $this->conn->query($sql);
            $users = [];
            
            // Add CSV headers
            $users[] = ['User ID', 'Name', 'Email', 'Role', 'Status', 'Created At', 'Last Login'];
            
            while ($row = $result->fetch_assoc()) {
                $users[] = [
                    $row['user_id'],
                    $row['name'],
                    $row['email'],
                    $row['role'],
                    $row['status'],
                    $row['created_at'],
                    $row['last_login'] ?? 'Never'
                ];
            }
            
            $this->sendResponse(true, 'Users exported successfully', ['users' => $users]);
            
        } catch (Exception $e) {
            error_log("Error exporting users: " . $e->getMessage());
            $this->sendResponse(false, 'Error exporting users: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Create new user
     */
    public function createUser() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $required = ['first_name', 'last_name', 'email', 'password', 'role'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->sendResponse(false, "Field '{$field}' is required");
                    return;
                }
            }

            // Check if email already exists
            $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 's', $input['email']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            $exists = intval($row['count']) > 0;
            mysqli_stmt_close($stmt);

            if ($exists) {
                $this->sendResponse(false, 'Email already exists');
                return;
            }

            mysqli_autocommit($this->conn, false);

            // Generate unique user ID
            $userId = $this->generateUserId();

            // Generate student/instructor ID if applicable
            $studentId = null;
            $instructorId = null;

            if ($input['role'] === 'Student') {
                $studentId = $this->generateStudentId($input['department_id'] ?? null);
            } elseif ($input['role'] === 'Instructor') {
                $instructorId = $this->generateInstructorId($input['department_id'] ?? null);
            }

            // Insert user
            $sql = "INSERT INTO users (
                        user_id, name, first_name, last_name, email, password, role,
                        department_id, program_id, year_level, section, student_type,
                        student_id_number, instructor_id_number, phone_number, address,
                        date_of_birth, gender, is_active, is_approved, approved_by,
                        approved_at, created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        NOW(), NOW()
                    )";

            $stmt = mysqli_prepare($this->conn, $sql);

            $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
            $fullName = trim($input['first_name'] . ' ' . $input['last_name']);
            $adminId = intval($_SESSION['user_id'] ?? 0);

            $deptId = $input['department_id'] ?? null;
            $progId = $input['program_id'] ?? null;
            $yearLevel = $input['year_level'] ?? null;
            $section = $input['section'] ?? null;
            $studentType = $input['student_type'] ?? null;
            $phone = $input['phone_number'] ?? null;
            $address = $input['address'] ?? null;
            $dob = $input['date_of_birth'] ?? null;
            $gender = $input['gender'] ?? null;
            $isActive = intval($input['is_active'] ?? 1);
            $isApproved = intval($input['is_approved'] ?? 1);

            mysqli_stmt_bind_param($stmt, 'issssssiiissssssssiis', $userId, $fullName, $input['first_name'], $input['last_name'], $input['email'], $hashedPassword, $input['role'], $deptId, $progId, $yearLevel, $section, $studentType, $studentId, $instructorId, $phone, $address, $dob, $gender, $isActive, $isApproved, $adminId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Log activity
            $this->logActivity($adminId, 'create', 'users', $userId,
                "Created user: {$fullName} ({$input['email']})");

            mysqli_commit($this->conn);
            mysqli_autocommit($this->conn, true);
            $this->sendResponse(true, 'User created successfully', ['user_id' => $userId]);

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            mysqli_autocommit($this->conn, true);
            error_log("Error creating user: " . $e->getMessage());
            $this->sendResponse(false, 'Error creating user: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Update user information
     */
    public function updateUser() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $userId = intval($input['user_id'] ?? 0);
            if (!$userId) {
                $this->sendResponse(false, 'User ID is required');
                return;
            }

            // Check if user exists
            $sql = "SELECT * FROM users WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$user) {
                $this->sendResponse(false, 'User not found');
                return;
            }

            mysqli_autocommit($this->conn, false);

            // Build update query dynamically
            $updateFields = [];
            $params = [];
            $types = '';

            $allowedFields = [
                'first_name', 'last_name', 'email', 'role', 'department_id',
                'program_id', 'year_level', 'section', 'student_type', 'phone_number',
                'address', 'date_of_birth', 'gender', 'is_active', 'is_approved'
            ];

            foreach ($allowedFields as $field) {
                if (isset($input[$field]) && $input[$field] !== $user[$field]) {
                    $updateFields[] = "{$field} = ?";
                    $params[] = $input[$field];
                    $types .= 's';
                }
            }

            // Update name if first_name or last_name changed
            if (isset($input['first_name']) || isset($input['last_name'])) {
                $firstName = $input['first_name'] ?? $user['first_name'];
                $lastName = $input['last_name'] ?? $user['last_name'];
                $fullName = trim($firstName . ' ' . $lastName);
                $updateFields[] = "name = ?";
                $params[] = $fullName;
                $types .= 's';
            }

            // Handle password update
            if (!empty($input['password'])) {
                $updateFields[] = "password = ?";
                $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
                $types .= 's';
            }

            if (empty($updateFields)) {
                $this->sendResponse(false, 'No changes detected');
                return;
            }

            // Add updated timestamp
            $updateFields[] = "updated_at = NOW()";

            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?";
            $params[] = $userId;
            $types .= 'i';

            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$params);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update user');
            }
            mysqli_stmt_close($stmt);

            // Log activity
            $adminId = intval($_SESSION['user_id'] ?? 0);
            $this->logActivity($adminId, 'edit', 'users', $userId,
                "Updated user: {$user['name']} ({$user['email']})");

            mysqli_commit($this->conn);
            mysqli_autocommit($this->conn, true);
            $this->sendResponse(true, 'User updated successfully');

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            mysqli_autocommit($this->conn, true);
            error_log("Error updating user: " . $e->getMessage());
            $this->sendResponse(false, 'Error updating user: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Delete user (soft delete - deactivate)
     */
    public function deleteUser() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $userId = intval($input['user_id'] ?? 0);
            if (!$userId) {
                $this->sendResponse(false, 'User ID is required');
                return;
            }

            // Get user details for logging
            $sql = "SELECT name, email FROM users WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$user) {
                $this->sendResponse(false, 'User not found');
                return;
            }

            mysqli_autocommit($this->conn, false);

            // Soft delete - deactivate user
            $sql = "UPDATE users SET is_active = 0, updated_at = NOW() WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $userId);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to delete user');
            }
            mysqli_stmt_close($stmt);

            // Log activity
            $adminId = intval($_SESSION['user_id'] ?? 0);
            $this->logActivity($adminId, 'delete', 'users', $userId,
                "Deactivated user: {$user['name']} ({$user['email']})");

            mysqli_commit($this->conn);
            mysqli_autocommit($this->conn, true);
            $this->sendResponse(true, 'User deleted successfully');

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            mysqli_autocommit($this->conn, true);
            error_log("Error deleting user: " . $e->getMessage());
            $this->sendResponse(false, 'Error deleting user: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Bulk operations on users
     */
    public function bulkUpdateUsers() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $userIds = $input['user_ids'] ?? [];
            $action = $input['action'] ?? '';

            if (empty($userIds) || !in_array($action, ['activate', 'deactivate', 'approve', 'delete'])) {
                $this->sendResponse(false, 'Invalid user IDs or action');
                return;
            }

            mysqli_autocommit($this->conn, false);

            $processed = 0;
            $adminId = intval($_SESSION['user_id'] ?? 0);

            foreach ($userIds as $userId) {
                $userId = intval($userId);

                // Get user details for logging
                $sql = "SELECT name, email FROM users WHERE user_id = ?";
                $stmt = mysqli_prepare($this->conn, $sql);
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if (!$user) continue;

                switch ($action) {
                    case 'activate':
                        $sql = "UPDATE users SET is_active = 1, updated_at = NOW() WHERE user_id = ?";
                        $stmt = mysqli_prepare($this->conn, $sql);
                        mysqli_stmt_bind_param($stmt, 'i', $userId);
                        break;
                    case 'deactivate':
                        $sql = "UPDATE users SET is_active = 0, updated_at = NOW() WHERE user_id = ?";
                        $stmt = mysqli_prepare($this->conn, $sql);
                        mysqli_stmt_bind_param($stmt, 'i', $userId);
                        break;
                    case 'approve':
                        $sql = "UPDATE users SET is_approved = 1, approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE user_id = ?";
                        $stmt = mysqli_prepare($this->conn, $sql);
                        mysqli_stmt_bind_param($stmt, 'ii', $adminId, $userId);
                        break;
                    case 'delete':
                        $sql = "UPDATE users SET is_active = 0, updated_at = NOW() WHERE user_id = ?";
                        $stmt = mysqli_prepare($this->conn, $sql);
                        mysqli_stmt_bind_param($stmt, 'i', $userId);
                        break;
                }

                if (mysqli_stmt_execute($stmt)) {
                    $processed++;
                    mysqli_stmt_close($stmt);

                    // Log activity
                    $this->logActivity($adminId, $action, 'users', $userId,
                        "Bulk {$action}: {$user['name']} ({$user['email']})");
                } else {
                    mysqli_stmt_close($stmt);
                }
            }

            mysqli_commit($this->conn);
            mysqli_autocommit($this->conn, true);
            $this->sendResponse(true, "Successfully processed {$processed} users");

        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            mysqli_autocommit($this->conn, true);
            error_log("Error in bulk update: " . $e->getMessage());
            $this->sendResponse(false, 'Error processing bulk update: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get departments for dropdown
     */
    public function getDepartments() {
        try {
            $sql = "SELECT department_id, department_name, department_code FROM departments WHERE is_active = 1 ORDER BY department_name";
            $result = $this->conn->query($sql);
            $departments = [];
            while ($row = $result->fetch_assoc()) {
                $departments[] = $row;
            }

            $this->sendResponse(true, 'Departments retrieved successfully', $departments);

        } catch (Exception $e) {
            error_log("Error fetching departments: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching departments: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get programs for dropdown
     */
    public function getPrograms() {
        try {
            $departmentId = intval($_GET['department_id'] ?? 0);

            $sql = "SELECT p.program_id, p.program_name, p.program_code, p.degree_type
                    FROM programs p
                    WHERE p.is_active = 1";

            if ($departmentId) {
                $sql .= " AND p.department_id = ?";
            }

            $sql .= " ORDER BY p.program_name";

            $stmt = mysqli_prepare($this->conn, $sql);
            if ($departmentId) {
                mysqli_stmt_bind_param($stmt, 'i', $departmentId);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $programs = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $programs[] = $row;
            }
            mysqli_stmt_close($stmt);

            $this->sendResponse(true, 'Programs retrieved successfully', $programs);

        } catch (Exception $e) {
            error_log("Error fetching programs: " . $e->getMessage());
            $this->sendResponse(false, 'Error fetching programs: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Generate unique user ID
     */
    private function generateUserId() {
        do {
            $userId = rand(100000000, 999999999);
            $sql = "SELECT COUNT(*) FROM users WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_row($result);
            $count = $row[0];
            mysqli_stmt_close($stmt);
        } while ($count > 0);

        return $userId;
    }
    
    /**
     * Generate student ID
     */
    private function generateStudentId($departmentId = null) {
        $year = date('Y');
        $prefix = $departmentId ? str_pad($departmentId, 2, '0', STR_PAD_LEFT) : '99';

        do {
            $sequence = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $studentId = $year . $prefix . $sequence;

            $sql = "SELECT COUNT(*) FROM users WHERE student_id_number = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 's', $studentId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_row($result);
            $count = $row[0];
            mysqli_stmt_close($stmt);
        } while ($count > 0);

        return $studentId;
    }
    
    /**
     * Generate instructor ID
     */
    private function generateInstructorId($departmentId = null) {
        $prefix = $departmentId ? str_pad($departmentId, 2, '0', STR_PAD_LEFT) : '99';
        
        do {
            $sequence = str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $instructorId = 'INST' . $prefix . $sequence;
            
            $sql = "SELECT COUNT(*) FROM users WHERE instructor_id_number = ?";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 's', $instructorId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_row($result);
            $count = $row[0];
            mysqli_stmt_close($stmt);
        } while ($count > 0);
        
        return $instructorId;
    }
    
    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $table, $recordId, $description) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $sql = "INSERT INTO activity_logs (user_id, activity_type, table_affected, record_id, description, ip_address, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";

            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ississ', $userId, $action, $table, $recordId, $description, $ip);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
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

// Handle different actions
try {
    $handler = new UserManagementHandler();
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_users':
            $filters = $_GET ?? [];
            $handler->getUsers($filters);
            break;
            
        case 'get_stats':
            $handler->getUserStats();
            break;
            
        case 'create_user':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $handler->createUser();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'update_user':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $handler->updateUser();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'delete_user':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $handler->deleteUser();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'bulk_update':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $handler->bulkUpdateUsers();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
            break;
            
        case 'get_departments':
            $handler->getDepartments();
            break;
            
        case 'get_programs':
            $handler->getPrograms();
            break;
            
        case 'get_online_users':
            $handler->getOnlineUsers();
            break;
            
        case 'get_activity':
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $handler->getUserActivity($page, $limit);
            break;
            
        case 'get_login_history':
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $handler->getLoginHistory($page, $limit);
            break;
            
        case 'export_users':
            $role = $_GET['role'] ?? null;
            $handler->exportUsers($role);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("User management handler error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>
