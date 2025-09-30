<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instructor - Course Materials</title>
  <link rel="stylesheet" href="../../css/Instructor-materials.css">
  <link rel="stylesheet" href="../../css/header&sideBar.css">
  <link rel="stylesheet" href="../../css/Dashboard-profile.css">

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
          <!-- Calendar icon -->
<span class="material-icons" id="calendarIcon" style="cursor: pointer;">calendar_month</span>

<!-- Calendar popup -->
<div id="calendarPopup" class="calendar-popup">
  <div class="calendar-header">
    <button id="prevMonth">&lt;</button>
    <span id="monthYear"></span>
    <button id="nextMonth">&gt;</button>
  </div>
  <table id="calendarTable">
    <thead>
      <tr>
        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>

      <!-- Profile picture in header -->
      <img src="default-profile.png" alt="Profile" class="dashboard-profile" id="dashboardProfile">

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
      <li><a href="Instructor-dashboard.php"><span class="material-icons">dashboard</span><span class="text">Dashboard</span></a></li>
      <li><a href="Instructor-research.php"><span class="material-icons">book</span><span class="text">Research</span></a></li>
      <li><a href="#"><span class="material-icons">folder</span><span class="text">Materials</span></a></li>
      <li><a href="Instructor-settings.php"><span class="material-icons">settings</span><span class="text">Settings</span></a></li>
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
              <input type="text" id="subjectName" placeholder="Enter subject name">
            </div>

            <!-- Department -->
            <label>Department</label>
            <select id="departmentSelectForm">
              <option value="">Select Department</option>
              <option value="CEC">CEC</option>
              <option value="CBM">CBM</option>
            </select>

            <!-- Program (dynamic) -->
            <label>Program</label>
            <select id="programSelectForm">
              <option value="">Select Program</option>
            </select>

            <!-- Semester -->
            <label>Semester</label>
            <select id="semesterSelectForm">
              <option value="1st Semester">1st Semester</option>
              <option value="2nd Semester">2nd Semester</option>
            </select>

            <!-- Year Level -->
            <label>Year Level</label>
            <select id="yearSelectForm">
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
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
            <img src="default-profile.png" alt="Profile Preview" id="lessonProfilePreview" class="profile-preview">
            
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
  <script src="../../js/calendar.js"></script>
  <script src="../../js/Instructor-materials.js"></script> <!-- JS for materials functionality -->
  <script src="../../js/navigation.js"></script>    <!-- JS for sidebar and nav -->
  <script src="../../js/Dashboard-profile.js"></script> <!-- JS for profile and header -->
</body>
</html>
