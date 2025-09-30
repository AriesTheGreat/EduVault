<?php
session_start();
require_once('../db.php');

// Set JSON response header
header('Content-Type: application/json');

// Authentication check
function isAdminAuthenticated() {
    return isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['Dean', 'Admin', 'Instructor']);
}

if (!isAdminAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Handle delete material action
if (isset($_POST['action']) && $_POST['action'] === 'delete_material') {
    $material_id = isset($_POST['material_id']) ? intval($_POST['material_id']) : 0;
    if (!$material_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid material ID']);
        exit;
    }

    try {
        $conn->begin_transaction();

        // Update learning_materials to mark as archived
        $sql = "UPDATE learning_materials SET is_active = 0, archived_at = NOW(), archived_by = ? WHERE material_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $_SESSION['user_id'], $material_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to archive material: " . $stmt->error);
        }

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Material archived successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Existing upload handling code below...

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

// Log incoming request for debugging
error_log("Material upload attempt: " . json_encode([
    'POST' => $_POST,
    'FILES' => $_FILES,
    'SESSION' => $_SESSION
]));

// Get form data
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$material_type = isset($_POST['material_type']) ? $_POST['material_type'] : 'Document';

// Debug: Log parsed data
error_log("Parsed upload data: class_id=$class_id, title='$title', material_type='$material_type'");

// Validate required fields
if (!$class_id || !$title) {
    error_log("Validation failed: class_id=$class_id, title='$title'");
    echo json_encode(['success' => false, 'error' => 'Class ID and title are required']);
    exit;
}

// Validate file
$file = $_FILES['file'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileTmpName = $file['tmp_name'];
$fileType = $file['type'];

// Get file extension
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Define allowed extensions
$allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'zip'];

// Check file extension
if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)
    ]);
    exit;
}

// Check file size (100MB limit)
$maxFileSize = 100 * 1024 * 1024; // 100MB in bytes
if ($fileSize > $maxFileSize) {
    echo json_encode(['success' => false, 'error' => 'File size exceeds 100MB limit']);
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = '../uploads/materials/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$uniqueFileName = date('Y-m-d_H-i-s') . '_' . uniqid() . '_' . $fileName;
$uploadPath = $uploadDir . $uniqueFileName;
$relativePath = 'uploads/materials/' . $uniqueFileName;

// Move uploaded file
if (!move_uploaded_file($fileTmpName, $uploadPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get class details for additional data
    $classQuery = "SELECT * FROM classes WHERE id = ?";
    $classStmt = $conn->prepare($classQuery);
    $classStmt->bind_param("i", $class_id);
    $classStmt->execute();
    $classResult = $classStmt->get_result();
    
    if ($classResult->num_rows === 0) {
        throw new Exception("Class not found");
    }
    
    $classData = $classResult->fetch_assoc();
    
    // Check if learning_materials table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'learning_materials'");
    if (!$table_check || $table_check->num_rows === 0) {
        // Create learning_materials table without foreign key constraints initially
        $create_table_sql = "
            CREATE TABLE learning_materials (
                material_id int NOT NULL AUTO_INCREMENT,
                title varchar(300) NOT NULL,
                description longtext,
                material_type varchar(50) DEFAULT 'Document',
                file_path varchar(500) NOT NULL,
                file_name varchar(255) NOT NULL,
                file_size bigint,
                file_type varchar(50),
                uploaded_by int NOT NULL,
                class_id int NOT NULL,
                semester varchar(20),
                academic_year varchar(20),
                status enum('draft','pending','approved','rejected','archived') DEFAULT 'approved',
                visibility enum('public','class_only','department','restricted') DEFAULT 'class_only',
                access_level enum('open','registered','enrolled','instructor_only') DEFAULT 'enrolled',
                is_active tinyint(1) DEFAULT '1',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (material_id),
                KEY fk_material_uploaded_by (uploaded_by),
                KEY fk_material_class_id (class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if (!$conn->query($create_table_sql)) {
            throw new Exception("Failed to create learning_materials table: " . $conn->error);
        }
    }
    
    // Insert into learning_materials table
    $insertQuery = "INSERT INTO learning_materials (
        title, description, material_type, file_path, file_name, file_size, file_type,
        uploaded_by, class_id, semester, academic_year, status, visibility, access_level,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'class_only', 'enrolled', NOW())";
    
    $stmt = $conn->prepare($insertQuery);
    
    // Set values from class data
    $semester = isset($classData['semester']) ? $classData['semester'] : '1st';
    $academic_year = date('Y') . '-' . (date('Y') + 1);
    
    $stmt->bind_param("ssssssiiiss", 
        $title,
        $description,
        $material_type,
        $relativePath,
        $fileName,
        $fileSize,
        $fileType,
        $_SESSION['user_id'],
        $class_id,
        $semester,
        $academic_year
    );
    
    error_log("Executing material insert with params: title='$title', class_id=$class_id, user_id=" . $_SESSION['user_id']);
    
    if (!$stmt->execute()) {
        error_log("Database insert failed: " . $stmt->error);
        throw new Exception("Database error: " . $stmt->error);
    }
    
    $material_id = $conn->insert_id;
    error_log("Material inserted successfully with ID: $material_id");
    
    // Create notification for successful upload
    $notification_title = "Material Uploaded";
    $notification_message = "Learning material '{$title}' has been successfully uploaded to class '{$classData['subject_name']}'";
    
    $notifQuery = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')";
    $notifStmt = $conn->prepare($notifQuery);
    $notifStmt->bind_param("iss", $_SESSION['user_id'], $notification_title, $notification_message);
    $notifStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Material uploaded successfully',
        'material_id' => $material_id,
        'file_name' => $fileName,
        'file_size' => $fileSize,
        'upload_path' => $relativePath,
        'trigger_dashboard_update' => true, // Signal to update dashboard
        'class_info' => [
            'class_id' => $class_id,
            'subject_name' => $classData['subject_name'],
            'department' => $classData['department']
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Delete uploaded file if database operation failed
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    // Log detailed error for debugging
    error_log("Material upload failed: " . $e->getMessage());
    error_log("Class ID: $class_id, User ID: " . $_SESSION['user_id']);
    
    echo json_encode([
        'success' => false,
        'error' => 'Upload failed: ' . $e->getMessage(),
        'debug_info' => [
            'class_id' => $class_id,
            'user_id' => $_SESSION['user_id'],
            'file_name' => $fileName,
            'title' => $title
        ]
    ]);
}
