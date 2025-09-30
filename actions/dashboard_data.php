<?php
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../db.php');
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

if (!in_array($_SESSION['role'], ['Dean', 'Admin'])) {
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
    exit;
}

try {
    $data = [];

    // Get active research count (all active research papers)
    $research_sql = "SELECT COUNT(*) as count FROM research_papers WHERE is_active = 1";
    $research_result = $conn->query($research_sql);
    if (!$research_result) {
        throw new Exception('Research query failed: ' . $conn->error);
    }
    $data['research_count'] = (int)$research_result->fetch_assoc()['count'];

    // Get research by department
    $research_by_dept_sql = "
        SELECT d.department_name, COUNT(*) as count
        FROM research_papers r
        JOIN programs p ON r.program_id = p.program_id
        JOIN departments d ON p.department_id = d.department_id
        WHERE r.is_active = 1
        GROUP BY d.department_id, d.department_name
    ";
    $research_by_dept_result = $conn->query($research_by_dept_sql);
    if ($research_by_dept_result) {
        $data['research_by_department'] = [];
        while ($row = $research_by_dept_result->fetch_assoc()) {
            $data['research_by_department'][$row['department_name']] = (int)$row['count'];
        }
    }

    // Get materials count (from learning_materials table - the correct table)
    $materials_sql = "SELECT COUNT(*) as count FROM learning_materials WHERE status = 'approved' AND is_active = 1";
    $materials_result = $conn->query($materials_sql);
    if (!$materials_result) {
        throw new Exception('Materials query failed: ' . $conn->error);
    }
    $data['materials_count'] = (int)$materials_result->fetch_assoc()['count'];

    // Get pending materials count (from learning_materials table)
    $pending_materials_sql = "
        SELECT COUNT(*) as count
        FROM learning_materials m
        WHERE m.status = 'pending'
        AND m.is_active = 1";
    $pending_materials_result = $conn->query($pending_materials_sql);
    if (!$pending_materials_result) {
        throw new Exception('Pending materials query failed: ' . $conn->error);
    }
    $data['pending_materials_count'] = (int)$pending_materials_result->fetch_assoc()['count'];

    // Get detailed pending materials breakdown
    $pending_materials_detail_sql = "
        SELECT m.material_type as type, COUNT(*) as count
        FROM learning_materials m
        WHERE m.status = 'pending'
        AND m.is_active = 1
        GROUP BY m.material_type";
    $pending_materials_detail_result = $conn->query($pending_materials_detail_sql);
    if ($pending_materials_detail_result) {
        $data['pending_materials_by_type'] = [];
        while ($row = $pending_materials_detail_result->fetch_assoc()) {
            $data['pending_materials_by_type'][$row['type']] = (int)$row['count'];
        }
    }

    // Get pending research count (only active research papers pending approval)
    $pending_research_sql = "
        SELECT COUNT(*) as count
        FROM research_papers r
        WHERE r.status = 'pending'
        AND r.is_active = 1";
    $pending_research_result = $conn->query($pending_research_sql);
    if (!$pending_research_result) {
        throw new Exception('Pending research query failed: ' . $conn->error);
    }
    $data['pending_research_count'] = (int)$pending_research_result->fetch_assoc()['count'];

    // Total pending requests (materials + research)
    $data['pending_requests_count'] = $data['pending_materials_count'] + $data['pending_research_count'];

    // Get active users count (check if table exists first)
    $check_sessions_table = $conn->query("SHOW TABLES LIKE 'user_sessions'");
    if ($check_sessions_table && $check_sessions_table->num_rows > 0) {
        $users_sql = "SELECT COUNT(DISTINCT user_id) as count FROM user_sessions WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $users_result = $conn->query($users_sql);
        $data['active_users_count'] = $users_result ? (int)$users_result->fetch_assoc()['count'] : 0;
    } else {
        // Fallback to total users if user_sessions table doesn't exist
        $check_users_table = $conn->query("SHOW TABLES LIKE 'users'");
        if ($check_users_table && $check_users_table->num_rows > 0) {
            $users_fallback_sql = "SELECT COUNT(*) as count FROM users";
            $users_fallback_result = $conn->query($users_fallback_sql);
            $data['active_users_count'] = $users_fallback_result ? (int)$users_fallback_result->fetch_assoc()['count'] : 0;
        } else {
            $data['active_users_count'] = 0;
        }
    }

    // Get research growth data by month (current year) - show only active research
    $current_year = date('Y');
    $research_growth_sql = "
        SELECT
            MONTH(submission_date) as month,
            COUNT(*) as count
        FROM research_papers
        WHERE YEAR(submission_date) = ? AND is_active = 1
        GROUP BY MONTH(submission_date)
        ORDER BY month ASC
    ";
    $research_growth_stmt = $conn->prepare($research_growth_sql);
    $research_growth_stmt->bind_param("i", $current_year);
    $research_growth_stmt->execute();
    $research_growth_result = $research_growth_stmt->get_result();

    // Initialize monthly data array
    $monthly_research = array_fill(0, 12, 0); // Jan=0, Feb=1, ..., Dec=11

    while ($row = $research_growth_result->fetch_assoc()) {
        $monthly_research[$row['month'] - 1] = (int)$row['count']; // Convert 1-based to 0-based
    }

    $data['monthly_research'] = $monthly_research;

    // Get research by status breakdown
    $status_sql = "
        SELECT
            status,
            COUNT(*) as count
        FROM research_papers
        WHERE is_active = 1
        GROUP BY status
    ";
    $status_result = $conn->query($status_sql);
    $status_breakdown = [];
    while ($row = $status_result->fetch_assoc()) {
        $status_breakdown[$row['status']] = (int)$row['count'];
    }
    $data['research_by_status'] = $status_breakdown;

    // Get research by department
    $dept_sql = "
        SELECT
            d.department_name,
            COUNT(rp.research_id) as count
        FROM departments d
        LEFT JOIN programs p ON d.department_id = p.department_id
        LEFT JOIN research_papers rp ON p.program_id = rp.program_id AND rp.is_active = 1
        GROUP BY d.department_id, d.department_name
    ";
    $dept_result = $conn->query($dept_sql);
    $dept_breakdown = [];
    while ($row = $dept_result->fetch_assoc()) {
        $dept_breakdown[$row['department_name']] = (int)$row['count'];
    }
    $data['research_by_department'] = $dept_breakdown;

    // Get recent activity (last 10 research submissions)
    $recent_sql = "
        SELECT
            rp.title,
            rp.authors,
            rp.submission_date,
            d.department_name
        FROM research_papers rp
        LEFT JOIN programs p ON rp.program_id = p.program_id
        LEFT JOIN departments d ON p.department_id = d.department_id
        WHERE rp.is_active = 1
        ORDER BY rp.submission_date DESC
        LIMIT 10
    ";
    $recent_result = $conn->query($recent_sql);
    $recent_activity = [];
    while ($row = $recent_result->fetch_assoc()) {
        $recent_activity[] = [
            'title' => $row['title'],
            'author' => $row['authors'],
            'date' => $row['submission_date'],
            'department' => $row['department_name'] ?? 'Unknown'
        ];
    }
    $data['recent_activity'] = $recent_activity;

    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
