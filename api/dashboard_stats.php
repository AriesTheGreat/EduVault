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

try {
    // Initialize stats
    $stats = [
        'research' => ['total' => 0, 'approved' => 0, 'pending' => 0, 'today' => 0],
        'materials' => ['total' => 0, 'approved' => 0, 'pending' => 0, 'today' => 0],
        'users' => ['total' => 0, 'approved' => 0, 'pending' => 0, 'today' => 0],
        'requests' => ['total' => 0, 'approved' => 0, 'pending' => 0, 'today' => 0],
        'classes' => ['total' => 0, 'today' => 0]
    ];

    // Get research papers stats
    $research_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN DATE(submission_date) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM research_papers 
        WHERE is_active = 1";
    
    $research_result = $conn->query($research_query);
    if ($research_result) {
        $research_data = $research_result->fetch_assoc();
        $stats['research'] = [
            'total' => (int)$research_data['total'],
            'approved' => (int)$research_data['approved'],
            'pending' => (int)$research_data['pending'],
            'today' => (int)$research_data['today']
        ];
    }

    // Get learning materials stats (check if table exists first)
    $materials_table_check = $conn->query("SHOW TABLES LIKE 'learning_materials'");
    if ($materials_table_check && $materials_table_check->num_rows > 0) {
        $materials_query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
            FROM learning_materials 
            WHERE is_active = 1";
        
        $materials_result = $conn->query($materials_query);
        if ($materials_result) {
            $materials_data = $materials_result->fetch_assoc();
            $stats['materials'] = [
                'total' => (int)$materials_data['total'],
                'approved' => (int)$materials_data['approved'],
                'pending' => (int)$materials_data['pending'],
                'today' => (int)$materials_data['today']
            ];
        }
    }

    // Get users stats
    $users_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM users 
        WHERE is_active = 1";
    
    $users_result = $conn->query($users_query);
    if ($users_result) {
        $users_data = $users_result->fetch_assoc();
        $stats['users'] = [
            'total' => (int)$users_data['total'],
            'approved' => (int)$users_data['approved'],
            'pending' => (int)$users_data['pending'],
            'today' => (int)$users_data['today']
        ];
    }

    // Get requests stats (if table exists)
    $requests_table_check = $conn->query("SHOW TABLES LIKE 'approval_requests'");
    if ($requests_table_check && $requests_table_check->num_rows > 0) {
        $requests_query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
            FROM approval_requests";
        
        $requests_result = $conn->query($requests_query);
        if ($requests_result) {
            $requests_data = $requests_result->fetch_assoc();
            $stats['requests'] = [
                'total' => (int)$requests_data['total'],
                'approved' => (int)$requests_data['approved'],
                'pending' => (int)$requests_data['pending'],
                'today' => (int)$requests_data['today']
            ];
        }
    }

    // Get classes stats
    $classes_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM classes";
    
    $classes_result = $conn->query($classes_query);
    if ($classes_result) {
        $classes_data = $classes_result->fetch_assoc();
        $stats['classes'] = [
            'total' => (int)$classes_data['total'],
            'today' => (int)$classes_data['today']
        ];
    }

    // Get recent activity for charts
    $recent_research = [];
    $research_recent_query = "SELECT DATE(submission_date) as date, COUNT(*) as count 
                              FROM research_papers 
                              WHERE submission_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                              GROUP BY DATE(submission_date) 
                              ORDER BY date DESC";
    
    $research_recent_result = $conn->query($research_recent_query);
    if ($research_recent_result) {
        while ($row = $research_recent_result->fetch_assoc()) {
            $recent_research[] = $row;
        }
    }

    // Get recent materials activity
    $recent_materials = [];
    if ($materials_table_check && $materials_table_check->num_rows > 0) {
        $materials_recent_query = "SELECT DATE(created_at) as date, COUNT(*) as count 
                                   FROM learning_materials 
                                   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                                   GROUP BY DATE(created_at) 
                                   ORDER BY date DESC";
        
        $materials_recent_result = $conn->query($materials_recent_query);
        if ($materials_recent_result) {
            while ($row = $materials_recent_result->fetch_assoc()) {
                $recent_materials[] = $row;
            }
        }
    }

    // Return comprehensive stats
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'charts' => [
            'recent_research' => $recent_research,
            'recent_materials' => $recent_materials
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch dashboard statistics',
        'debug' => $e->getMessage()
    ]);
}
?>
