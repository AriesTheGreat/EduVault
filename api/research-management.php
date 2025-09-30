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

$action = $_GET['action'] ?? 'stats';

try {
    switch ($action) {
        case 'stats':
            getResearchStats();
            break;
        case 'charts':
            getResearchCharts();
            break;
        case 'recent':
            getRecentActivity();
            break;
        case 'monthly':
            getMonthlyTrends();
            break;
        case 'departments':
            getDepartmentDistribution();
            break;
        case 'types':
            getResearchTypes();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Research management API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch research data',
        'debug' => $e->getMessage()
    ]);
}

function getResearchStats() {
    global $conn;
    
    $stats = [
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'today' => 0,
        'this_week' => 0,
        'this_month' => 0
    ];
    
    // Get comprehensive research statistics
    $query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN DATE(submission_date) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN submission_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as this_week,
        SUM(CASE WHEN MONTH(submission_date) = MONTH(CURDATE()) AND YEAR(submission_date) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_month
        FROM research_papers 
        WHERE is_active = 1";
    
    $result = $conn->query($query);
    if ($result) {
        $data = $result->fetch_assoc();
        $stats = [
            'total' => (int)$data['total'],
            'pending' => (int)$data['pending'],
            'approved' => (int)$data['approved'],
            'rejected' => (int)$data['rejected'],
            'today' => (int)$data['today'],
            'this_week' => (int)$data['this_week'],
            'this_month' => (int)$data['this_month']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getResearchCharts() {
    global $conn;
    
    // Status distribution for pie chart
    $status_query = "SELECT status, COUNT(*) as count 
                     FROM research_papers 
                     WHERE is_active = 1 
                     GROUP BY status";
    
    $status_result = $conn->query($status_query);
    $status_data = [];
    
    if ($status_result) {
        while ($row = $status_result->fetch_assoc()) {
            $status_data[] = [
                'label' => ucfirst($row['status']),
                'count' => (int)$row['count']
            ];
        }
    }
    
    // Monthly submissions for line chart (last 12 months)
    $monthly_query = "SELECT 
        DATE_FORMAT(submission_date, '%Y-%m') as month,
        COUNT(*) as count
        FROM research_papers 
        WHERE submission_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        AND is_active = 1
        GROUP BY DATE_FORMAT(submission_date, '%Y-%m')
        ORDER BY month ASC";
    
    $monthly_result = $conn->query($monthly_query);
    $monthly_data = [];
    
    if ($monthly_result) {
        while ($row = $monthly_result->fetch_assoc()) {
            $monthly_data[] = [
                'month' => $row['month'],
                'count' => (int)$row['count']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'charts' => [
            'status_distribution' => $status_data,
            'monthly_submissions' => $monthly_data
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getRecentActivity() {
    global $conn;
    
    $query = "SELECT 
        r.research_id,
        r.title,
        r.submission_date,
        r.status,
        r.research_type,
        u.name as submitted_by_name,
        p.program_name,
        d.department_name
        FROM research_papers r
        LEFT JOIN users u ON r.submitted_by = u.user_id
        LEFT JOIN programs p ON r.program_id = p.program_id
        LEFT JOIN departments d ON p.department_id = d.department_id
        WHERE r.is_active = 1
        ORDER BY r.submission_date DESC
        LIMIT 10";
    
    $result = $conn->query($query);
    $activities = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'id' => $row['research_id'],
                'title' => $row['title'],
                'author' => $row['submitted_by_name'],
                'department' => $row['department_name'],
                'program' => $row['program_name'],
                'type' => $row['research_type'],
                'status' => $row['status'],
                'date' => $row['submission_date'],
                'time_ago' => timeAgo($row['submission_date'])
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getDepartmentDistribution() {
    global $conn;
    
    $query = "SELECT 
        COALESCE(d.department_name, 'Unassigned') as department,
        COUNT(*) as count
        FROM research_papers r
        LEFT JOIN programs p ON r.program_id = p.program_id
        LEFT JOIN departments d ON p.department_id = d.department_id
        WHERE r.is_active = 1
        GROUP BY d.department_name
        ORDER BY count DESC";
    
    $result = $conn->query($query);
    $departments = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = [
                'label' => $row['department'],
                'count' => (int)$row['count']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'departments' => $departments,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getResearchTypes() {
    global $conn;
    
    $query = "SELECT 
        research_type,
        COUNT(*) as count
        FROM research_papers 
        WHERE is_active = 1
        GROUP BY research_type
        ORDER BY count DESC";
    
    $result = $conn->query($query);
    $types = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $types[] = [
                'label' => $row['research_type'],
                'count' => (int)$row['count']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'types' => $types,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getMonthlyTrends() {
    global $conn;
    
    // Get last 6 months of data with status breakdown
    $query = "SELECT 
        DATE_FORMAT(submission_date, '%Y-%m') as month,
        status,
        COUNT(*) as count
        FROM research_papers 
        WHERE submission_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        AND is_active = 1
        GROUP BY DATE_FORMAT(submission_date, '%Y-%m'), status
        ORDER BY month ASC, status ASC";
    
    $result = $conn->query($query);
    $trends = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $month = $row['month'];
            if (!isset($trends[$month])) {
                $trends[$month] = [
                    'month' => $month,
                    'pending' => 0,
                    'approved' => 0,
                    'rejected' => 0
                ];
            }
            $trends[$month][$row['status']] = (int)$row['count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'trends' => array_values($trends),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}
?>
