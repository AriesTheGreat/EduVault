<?php
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../db.php');
session_start();

header('Content-Type: application/json');

// Only allow admins/deans
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Dean', 'Admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Support both old and new field names
$title = $_POST['title'] ?? $_POST['researchTitle'] ?? '';
$uploader_name = $_POST['uploader_name'] ?? $_POST['researchUploader'] ?? '';
$department = $_POST['department'] ?? '';
$category = $_POST['research_type'] ?? $_POST['researchCategory'] ?? '';
$year = $_POST['year_published'] ?? $_POST['researchYear'] ?? '';
$abstract = $_POST['abstract'] ?? $_POST['researchInfo'] ?? '';
$file_field = $_FILES['research_file'] ?? $_FILES['researchFile'] ?? null;

// Validate required fields
$missing_fields = [];
if (empty($title)) $missing_fields[] = 'title';
if (empty($uploader_name)) $missing_fields[] = 'uploader_name';
if (empty($department)) $missing_fields[] = 'department';
if (empty($category)) $missing_fields[] = 'category';
if (empty($year)) $missing_fields[] = 'year';
if (empty($abstract)) $missing_fields[] = 'abstract';
if (!$file_field || $file_field['error'] !== UPLOAD_ERR_OK) {
    $missing_fields[] = 'research_file';
}
if (!empty($missing_fields)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
    exit;
}

// Validate file type
$allowed_types = ['application/pdf'];
$file_info = finfo_open(FILEINFO_MIME_TYPE);
$uploaded_type = finfo_file($file_info, $file_field['tmp_name']);
finfo_close($file_info);
if (!in_array($uploaded_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF files are allowed.']);
    exit;
}

// Create upload directory
$upload_dir = __DIR__ . '/../uploads/research/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
$file_extension = pathinfo($file_field['name'], PATHINFO_EXTENSION);
$file_name = uniqid('research_') . '.' . $file_extension;
$file_path = $upload_dir . $file_name;

// Start transaction
$conn->begin_transaction();
try {
    // Move uploaded file
    if (!move_uploaded_file($file_field['tmp_name'], $file_path)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Sanitize input
    $title_clean = $conn->real_escape_string($title);
    $uploader_clean = $conn->real_escape_string($uploader_name);
    $department_clean = $conn->real_escape_string($department);
    $category_clean = $conn->real_escape_string($category);
    $research_year = (int)$year;
    $description_clean = $conn->real_escape_string($abstract);
    $file_size = $file_field['size'];

    // Map department name to program_id (for now, we'll use a simple mapping)
    $program_id = null;
    $department_map = [
        'CBM' => 1,
        'Information Technology' => 2, 
        'Business Administration' => 3,
        'Elementary Education' => 4
    ];
    
    if (isset($department_map[$department])) {
        $program_id = $department_map[$department];
    }

    // Create relative path for database storage
    $relative_file_path = 'uploads/research/' . $file_name;

    // Map category to research_type enum values
    $research_type_map = [
        'Education' => 'Research Paper',
        'Technology' => 'Capstone',
        'Health' => 'Thesis',
        'Economics' => 'Dissertation',
        'Other' => 'Research Paper'
    ];

    $research_type = isset($research_type_map[$category]) ? $research_type_map[$category] : 'Research Paper';

    // Insert research record with proper field mapping (pending status for admin approval)
    $sql = "INSERT INTO research_papers (title, abstract, authors, file_path, file_size, submitted_by, program_id, research_type, keywords, year_published, status, submission_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssiisssi", $title_clean, $description_clean, $uploader_clean, $relative_file_path, $file_size, $_SESSION['user_id'], $program_id, $research_type, $department_clean, $research_year);
    if (!$stmt->execute()) {
        throw new Exception("Failed to save research record: " . $stmt->error);
    }
    $research_id = $conn->insert_id;

    // Create notification
    $notification_msg = "New research '{$title_clean}' has been uploaded by {$uploader_clean}";
    $sql_notif = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')";
    $stmt_notif = $conn->prepare($sql_notif);
    $stmt_notif->bind_param("iss", $_SESSION['user_id'], $title_clean, $notification_msg);
    $stmt_notif->execute();

    // Commit transaction
    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Research uploaded successfully',
        'research_id' => $research_id,
        'notification' => $notification_msg
    ]);
} catch (Exception $e) {
    $conn->rollback();
    // Delete uploaded file if it exists
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
