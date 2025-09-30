<?php
session_start();
require_once('../../config/config.php');
require_once('../../db.php');

// Authentication check
function isAdminAuthenticated() {
    return isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['Dean', 'Admin']);
}

// Check authentication
if (!isAdminAuthenticated()) {
    header('Location: /user/login.php');
    exit;
}

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Create new class - Updated to match new database schema
        if (isset($_POST['action']) && $_POST['action'] === 'create_class') {
            $subject_name = $conn->real_escape_string($_POST['subject_name']);
            $department = $conn->real_escape_string($_POST['department']);
            $program = $conn->real_escape_string($_POST['program']);
            $semester = $conn->real_escape_string($_POST['semester']);
            $year_level = $conn->real_escape_string($_POST['year_level']);
            $instructor_name = $conn->real_escape_string($_POST['instructor']);

            // Start transaction
            $conn->begin_transaction();

            try {
                // Insert into classes table with existing schema
                $sql = "INSERT INTO classes (subject_name, department, program, semester, year_level, instructor, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $subject_name, $department, $program, $semester, $year_level, $instructor_name, $_SESSION['user_id']);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to create class: " . $stmt->error);
                }
                $class_id = $conn->insert_id;

                // Create notification message
                $notification_msg = "New class '{$subject_name}' has been created for {$department} - {$program}, {$year_level} ({$semester})";

                // Insert notification with proper title
                $sql_notif = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')";
                $stmt_notif = $conn->prepare($sql_notif);
                $notification_title = 'New Class Created';
                $stmt_notif->bind_param("iss", $_SESSION['user_id'], $notification_title, $notification_msg);
                $stmt_notif->execute();

                // Commit transaction
                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'id' => $class_id,
                    'subject_name' => $subject_name,
                    'instructor' => $instructor_name,
                    'department' => $department,
                    'program' => $program,
                    'semester' => $semester,
                    'year_level' => $year_level,
                    'message' => 'Class created successfully!',
                    'notification' => $notification_msg
                ]);
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // Get classes based on filters
        if (isset($_POST['action']) && $_POST['action'] === 'get_classes') {
            $conditions = ["c.is_active = 1"];
            $params = [];
            $types = "";

            if (!empty($_POST['department']) && $_POST['department'] !== 'all') {
                $conditions[] = "c.department = ?";
                $params[] = $_POST['department'];
                $types .= "s";
            }

            if (!empty($_POST['semester']) && $_POST['semester'] !== 'all') {
                $conditions[] = "c.semester = ?";
                $params[] = $_POST['semester'];
                $types .= "s";
            }
            
            $sql = "SELECT c.id, c.subject_name, c.department, c.program, c.semester, c.year_level, 
                           c.instructor, c.created_at, u.name as created_by_name
                    FROM classes c 
                    LEFT JOIN users u ON c.created_by = u.user_id
                    WHERE " . implode(' AND ', $conditions) . " 
                    ORDER BY c.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $classes = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'classes' => $classes]);
            exit;
        }

        // Delete/Archive class
        if (isset($_POST['action']) && $_POST['action'] === 'delete_class') {
            $class_id = intval($_POST['class_id']);
            
            $conn->begin_transaction();
            try {
                // Mark class as inactive instead of deleting
                $sql = "UPDATE classes SET is_active = 0, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $class_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to archive class: " . $stmt->error);
                }
                
                // Also mark associated materials as inactive
                $sql_materials = "UPDATE learning_materials SET is_active = 0, updated_at = NOW() WHERE class_id = ?";
                $stmt_materials = $conn->prepare($sql_materials);
                $stmt_materials->bind_param("i", $class_id);
                $stmt_materials->execute();
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Class archived successfully']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        
        // Get materials for a specific class (if learning_materials table exists)
        if (isset($_POST['action']) && $_POST['action'] === 'get_materials') {
            $class_id = intval($_POST['class_id']);
            
            // Check if learning_materials table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'learning_materials'");
            if ($table_check && $table_check->num_rows > 0) {
                $sql = "SELECT lm.*, u.name as uploaded_by_name 
                        FROM learning_materials lm 
                        LEFT JOIN users u ON lm.uploaded_by = u.user_id 
                        WHERE lm.class_id = ? AND lm.is_active = 1 
                        ORDER BY lm.created_at DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $materials = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                // Table doesn't exist yet, return empty array
                $materials = [];
            }
            
            echo json_encode(['success' => true, 'materials' => $materials]);
            exit;
        }
    }
    
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Get initial data for the page using existing schema (only active classes)
$sql = "SELECT c.id, c.subject_name, c.department, c.program, c.semester, c.year_level, 
               c.instructor, c.created_at, u.name as created_by_name
        FROM classes c 
        LEFT JOIN users u ON c.created_by = u.user_id
        WHERE c.is_active = 1
        ORDER BY c.created_at DESC";
$result = $conn->query($sql);
$initialData = [
    'classes' => $result ? $result->fetch_all(MYSQLI_ASSOC) : []
];

$jsInitialData = json_encode($initialData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Course Materials Dashboard</title>
  <link rel="stylesheet" href="../../css/Admin-Materials.css">
  <link rel="stylesheet" href="../../css/header&sideBar.css">
  <link rel="stylesheet" href="../../css/Dashboard-profile.css">
  
  <!-- Additional styles for material uploads -->
  <style>
    .uploading-indicator {
      padding: 10px;
      background: #e3f2fd;
      border: 1px solid #2196f3;
      border-radius: 4px;
      margin: 5px 0;
      color: #1976d2;
      font-size: 14px;
    }
    
    .file-description {
      padding: 8px;
      background: #f8f9fa;
      border-left: 4px solid #007bff;
      margin: 5px 0;
      font-size: 13px;
    }
    
    .file-container {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px;
      border: 1px solid #e9ecef;
      border-radius: 4px;
      margin: 3px 0;
      background: white;
    }
    
    .file-item {
      flex: 1;
    }
    
    .file-row {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .file-name.clickable {
      cursor: pointer;
      color: #007bff;
      text-decoration: none;
    }
    
    .file-name.clickable:hover {
      text-decoration: underline;
    }
    
    .file-remove {
      margin-left: 10px;
    }
    
    .remove-btn {
      background: #dc3545;
      color: white;
      border: none;
      padding: 5px 8px;
      border-radius: 3px;
      cursor: pointer;
      font-size: 12px;
    }
    
    .remove-btn:hover {
      background: #c82333;
    }
    
    .file-list {
      max-height: 300px;
      overflow-y: auto;
    }
  </style>

  <!-- Material Icons -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <!-- FontAwesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <!-- ================= HEADER ================= -->
  <header class="header">
    <div class="header-left">
      <!-- Logo and System Name -->
      <img src="../../img/EduVault Official Logo (2).png" alt="Logo" class="logo">
      <h1 class="system-name">EduVault</h1>
    </div>

    <div class="header-right" style="position: relative;">
      <!-- Notifications icon -->
      <span class="material-icons">notifications</span>
      <!-- Profile picture in header -->
      <img src="../../img/default-profile.png" alt="Profile" class="dashboard-profile" id="dashboardProfile">

      <!-- ================= PROFILE DROPDOWN ================= -->
      <div class="profile-dropdown" id="profileDropdown">
        <p><strong id="profileName">Full Name</strong></p>
        <p>ID: <span id="profileID">-</span></p>
        <p>Email: <span id="profileEmail">-</span></p>
        <!-- Upload new profile picture -->
        <input type="file" id="profileUpload">
        <button id="saveProfileBtn">Save Profile</button>
        <button id="logoutBtn">Logout</button>
      </div>
    </div>
  </header>

  <!-- ================= SIDEBAR ================= -->
  <div class="sidebar hover-enabled">
    <ul>
      <li><a href="Admin-dashboard.php"><span class="material-icons">dashboard</span><span class="text">Dashboard</span></a></li>
      <li><a href="Admin-research.php"><span class="material-icons">book</span><span class="text">Research</span></a></li>
      <li><a href="#"><span class="material-icons">folder</span><span class="text">Materials</span></a></li>
      <li><a href="Admin-request.php"><span class="material-icons">hourglass_empty</span><span class="text">Requests</span></a></li>
      <li><a href="Admin-archive.php"><span class="material-icons">archive</span><span class="text">Archive History</span></a></li>
      <li><a href="Admin-usermanagement.php"><span class="material-icons">group</span><span class="text">User Management</span></a></li>
      <li><a href="Admin-settings.php"><span class="material-icons">settings</span><span class="text">Settings</span></a></li>
    </ul>
  </div>

  <!-- ================= MAIN CONTENT: MATERIALS ================= -->
  <div class="main-content">
    <!-- Header for materials section -->
    <div class="materials-header">
      <h2>Uploaded Course Materials</h2>
    </div>

    <!-- ================= DROPDOWN FILTERS ================= -->
    <div class="filters">
      <!-- Department Filter -->
      <select id="departmentFilter">
        <option value="all">All Departments</option>
        <option value="CEC">CEC</option>
        <option value="CBM">CBM</option>
      </select>

      <!-- Program Filter (changes based on department selection) -->
      <select id="programFilter">
        <option value="all">All Programs</option>
      </select>

      <!-- Semester Filter -->
      <select id="semesterSelect">
        <option value="all">All Semesters</option>
        <option value="1st">1st Semester</option>
        <option value="2nd">2nd Semester</option>
      </select>
      <!-- Year Level Filter -->
      <select id="yearSelect">
        <option value="all">All Year Levels</option>
        <option value="1st">1st Year</option>
        <option value="2nd">2nd Year</option>
        <option value="3rd">3rd Year</option>
        <option value="4th">4th Year</option>
      </select>
      <!-- Button to open 'Create Class' modal -->
      <button class="upload-btn" id="createClassBtn">
        <span class="material-icons">add</span>
      </button>
    </div>

    <!-- ================= MATERIAL CARDS CONTAINER ================= -->
    <div class="materials-container" id="lessonsContainer">
      <!-- Material cards will be dynamically appended here via JS -->
    </div>

    <!-- ================= MODAL: CREATE MATERIAL ================== -->
    <div class="material-modal" id="materialModal">
      <div class="material-box">
        <!-- Close button -->
        <span class="close-material-modal" id="closeMaterialModal">&times;</span>
        <div class="material-inner">
          <h2>Create New Class</h2>
          <form id="addMaterialForm">
            <!-- Subject Name -->
            <div class="form-group">
              <label for="subjectName">Subject Name</label>
              <input type="text" id="subjectName" name="subject_name" required placeholder="Enter subject name">
            </div>

            <!-- Department -->
            <label>Department</label>
            <select id="departmentSelectForm" name="department" required>
              <option value="">Select Department</option>
              <option value="CEC">CEC</option>
              <option value="CBM">CBM</option>
            </select>

            <!-- Program (dynamic) -->
            <label>Program</label>
            <select id="programSelectForm" name="program" required>
              <option value="">Select Program</option>
            </select>

            <!-- Semester -->
            <label>Semester</label>
            <select id="semesterSelectForm" name="semester" required>
              <option value="1st">1st Semester</option>
              <option value="2nd">2nd Semester</option>
            </select>

            <!-- Year Level -->
            <label>Year Level</label>
            <select id="yearSelectForm" name="year_level" required>
              <option value="1st">1st Year</option>
              <option value="2nd">2nd Year</option>
              <option value="3rd">3rd Year</option>
              <option value="4th">4th Year</option>
            </select>

            <!-- Instructor -->
            <div class="form-group">
              <label for="createdBy">Instructor</label>
              <input type="text" id="createdBy" placeholder="Instructor name">
            </div>
            <!-- Submit button -->
            <button type="submit" class="btn-submit">Create Class</button>
          </form>
        </div>
      </div>
    </div>

    <!-- ================= MODAL: LESSON SETTINGS ================= -->
    <div class="modal" id="lessonSettingsModal" style="display:none;">
      <div class="settings-box">
        <!-- Close button -->
        <span class="close-modal" id="closeLessonSettings">&times;</span>
        <h3>Lesson Settings</h3>
        <form id="lessonSettingsForm">
          <!-- Profile picture -->
          <div class="settings-row profile-row">
            <div class="profile-container">
              <label for="lessonProfilePic">Profile Picture</label>
              <input type="file" id="lessonProfilePic" class="profile-upload">
              <img src="../../img/default-profile.png" alt="Profile Preview" id="lessonProfilePreview" class="profile-preview">
            </div>
          </div>

          <!-- Course title -->
          <div class="settings-row">
            <label for="editLessonTitle">Course Title:</label>
            <input type="text" id="editLessonTitle" value="">
          </div>

          <!-- Instructor name -->
          <div class="settings-row">
            <label for="editLessonInstructor">Instructor Name:</label>
            <input type="text" id="editLessonInstructor" value="">
          </div>

          <!-- Background selection -->
          <div class="settings-row">
            <label>Box Background:</label>
            <div class="bg-options">
              <img src="../../img/bkd1.jpg" alt="Background 1" class="bg-choice" data-bg="../../img/bkd1.jpg">
              <img src="../../img/bkd2.jpg" alt="Background 2" class="bg-choice" data-bg="../../img/bkd2.jpg">
              <img src="../../img/bkd3.jpg" alt="Background 3" class="bg-choice" data-bg="../../img/bkd3.jpg">
            </div>
          </div>
          <input type="hidden" id="editLessonBg" value="">

          <!-- Save + Remove buttons -->
          <div class="settings-actions">
            <button type="submit" class="btn-submit">Save Settings</button>
            <button type="button" id="removeLessonBtn" class="btn-remove">Remove Class</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ================= MODAL: LESSON DETAILS ================= -->
    <div class="modal" id="lessonBoxModal">
      <div class="lesson-box" id="lessonBox">
        <!-- Close button -->
        <span class="close-modal" id="closeLessonBox">&times;</span>

        <!-- Lesson Header -->
        <div class="lesson-header">
          <div class="lesson-header-left">
            <h2 id="lessonBoxTitle"></h2>
            <p id="lessonBoxInstructor"></p>
          </div>
          <!-- Settings button -->
          <button id="openLessonSettings" class="settings-btn" title="Lesson Settings">
            <span class="material-icons">settings</span>
          </button>
        </div>

        <!-- Upload Button -->
        <div class="upload-area">
          <button id="uploadFileBtn"><i class="fas fa-upload"></i> Upload File</button>
          <input type="file" id="announcementFileInput" style="display:none;">
        </div>

        <!-- File List Section -->
        <div class="file-list-section">
          <h3>Uploaded Materials</h3>
          <div id="fileList" class="file-list"></div>
        </div>
      </div>
    </div>

    <!-- ================= FILE PREVIEW MODAL ================= -->
    <div id="filePreviewModal" class="file-preview-modal">
      <div class="file-preview-content">
        <!-- Close button -->
        <span class="close-btn" id="closeFilePreview">&times;</span>
        <h3 id="filePreviewName"></h3>
        <iframe id="filePreviewFrame"></iframe>
      </div>
    </div>
  </div>

  <!-- ================= SCRIPTS ================= -->
  <script>
    // Initialize data from server
    window.initialData = <?php echo $jsInitialData; ?>;
  </script>
  <script src="../../js/materials/Admin-main.js"></script> <!-- JS for materials functionality -->
  <script src="../../js/navigation.js"></script>    <!-- JS for sidebar and nav -->
  <script src="../../js/Dashboard-profile.js"></script> <!-- JS for profile and header -->
</body>
</html>
