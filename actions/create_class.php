<?php
session_start();
require_once('../config/config.php');
require_once('../db.php');

// Authentication check
function isAdminAuthenticated() {
    return isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['Dean', 'Admin']);
}

// Check authentication
if (!isAdminAuthenticated()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = ['subject_name', 'department', 'program', 'semester', 'year_level', 'instructor'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Missing required fields: ' . implode(', ', $missing_fields)
        ]);
        exit;
    }

    // Sanitize input
    $subject_name = $conn->real_escape_string($_POST['subject_name']);
    $department = $conn->real_escape_string($_POST['department']);
    $program = $conn->real_escape_string($_POST['program']);
    $semester = $conn->real_escape_string($_POST['semester']);
    $year_level = $conn->real_escape_string($_POST['year_level']);
    $instructor = $conn->real_escape_string($_POST['instructor']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Log the received data
        error_log("Creating new class: " . json_encode([
            'subject' => $subject_name,
            'department' => $department,
            'program' => $program,
            'semester' => $semester,
            'year' => $year_level,
            'instructor' => $instructor,
            'user_id' => $_SESSION['user_id']
        ]));

        // Insert into classes table
        $sql = "INSERT INTO classes (subject_name, department, program, semester, year_level, instructor, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssi", $subject_name, $department, $program, $semester, $year_level, $instructor, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create class: " . $stmt->error);
        }
        $class_id = $conn->insert_id;

        // Create notification message
        $notification_msg = "New class '{$subject_name}' has been created for {$department} - {$program}, {$year_level} ({$semester})";
        
        // Insert notification
        $sql_notif = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')";
        $stmt_notif = $conn->prepare($sql_notif);
        $notification_title = 'New Class Created';
        $stmt_notif->bind_param("iss", $_SESSION['user_id'], $notification_title, $notification_msg);
        $stmt_notif->execute();

        // Commit transaction
        $conn->commit();
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'id' => $class_id,
            'message' => 'Class created successfully!',
            'notification' => $notification_msg
        ]);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    // Invalid request method
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>