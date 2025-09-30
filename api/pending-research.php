<?php
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../db.php');
session_start();

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Dean', 'Admin', 'Instructor', 'Student'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

try {
    // Get pending research papers (limit to 2 most recent)
    $sql = "SELECT 
                r.research_id,
                r.title,
                r.abstract,
                r.authors,
                r.file_path,
                r.research_type,
                r.year_published,
                r.submission_date,
                r.status,
                u.name as uploaded_by_name,
                u.user_id as uploaded_by_id
            FROM research_papers r
            LEFT JOIN users u ON r.submitted_by = u.user_id
            WHERE r.status = 'pending' AND r.is_active = 1
            ORDER BY r.submission_date DESC
            LIMIT 2";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $pending_research = [];
    while ($row = $result->fetch_assoc()) {
        $pending_research[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $pending_research,
        'count' => count($pending_research)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
