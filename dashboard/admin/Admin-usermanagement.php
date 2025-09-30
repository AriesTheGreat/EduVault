<?php require_once '../../db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - User Management Dashboard</title>

  <!-- CSS -->
  <link rel="stylesheet" href="../../css/Admin-usermanagement.css">
  <link rel="stylesheet" href="../../css/header&sideBar.css">
  <link rel="stylesheet" href="../../css/Dashboard-profile.css">

  <!-- Google Icons & FontAwesome -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<!-- HEADER -->
  <header class="header">
    <div class="header-left">

      <img src="../../img/EduVault Official Logo (2).png" alt="Logo" class="logo">
      <h1 class="system-name">EduVault</h1>
    </div>

    <div class="header-right" style="position: relative;">
      <span class="material-icons">notifications</span>
      <img src="../../img/default-profile.png" alt="Profile" class="dashboard-profile" id="dashboardProfile">

      <!-- Profile dropdown -->
      <div class="profile-dropdown" id="profileDropdown">
        <p><strong id="profileName">Full Name</strong></p>
        <p>ID: <span id="profileID">-</span></p>
        <p>Email: <span id="profileEmail">-</span></p>
        <input type="file" id="profileUpload">
        <button id="saveProfileBtn">Save Profile</button>
        <a href="../../actions/logout.php" class="logout-btn" id="logoutBtn">Logout</a>
      </div>
    </div>
  </header>
  <!-- SIDEBAR -->
  <div class="sidebar hover-enabled">
    <ul>
      <li><a href="Admin-dashboard.php"><span class="material-icons">dashboard</span><span class="text">Dashboard</span></a></li>
      <li><a href="Admin-research.php"><span class="material-icons">book</span><span class="text">Research</span></a></li>
      <li><a href="Admin-materials.php"><span class="material-icons">folder</span><span class="text">Materials</span></a></li>
      <li><a href="Admin-request.php"><span class="material-icons">hourglass_empty</span><span class="text">Requests</span></a></li>
      <li><a href="Admin-archive.php"><span class="material-icons">archive</span><span class="text">Archive History</span></a></li>
      <li><a href="Admin-usermanagement.php" class="active"><span class="material-icons">group</span><span class="text">User Management</span></a></li>
      <li><a href="Admin-settings.php"><span class="material-icons">settings</span><span class="text">Settings</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <!-- Enhanced Header Section -->
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">User Management</h1>
        <p class="page-subtitle">Monitor and manage system users with real-time activity tracking</p>
        <div class="header-actions">
          <button class="action-btn secondary" onclick="refreshUserData()">
            <i class="fas fa-sync-alt"></i>
            Refresh
          </button>
          <button class="action-btn primary" onclick="exportUserData()">
            <i class="fas fa-download"></i>
            Export Data
          </button>
        </div>
      </div>
      
      <!-- Real-time Stats -->
      <div class="header-metrics">
        <div class="metric-item">
          <span class="metric-value" id="totalUsers">0</span>
          <span class="metric-label">Total Users</span>
        </div>
        <div class="metric-item online">
          <span class="metric-value" id="onlineUsers">0</span>
          <span class="metric-label">Online Now</span>
        </div>
        <div class="metric-item">
          <span class="metric-value" id="newUsersToday">0</span>
          <span class="metric-label">New Today</span>
        </div>
        <div class="metric-item">
          <span class="metric-value" id="activeUsersToday">0</span>
          <span class="metric-label">Active Today</span>
        </div>
      </div>
    </div>

    <!-- Enhanced Tab Navigation -->
    <div class="enhanced-tabs">
      <div class="tab-nav">
        <button class="tab-btn active" data-tab="overview">
          <i class="fas fa-chart-pie"></i>
          Overview
        </button>
        <button class="tab-btn" data-tab="students">
          <i class="fas fa-graduation-cap"></i>
          Students <span class="tab-count" id="studentsCount">0</span>
        </button>
        <button class="tab-btn" data-tab="instructors">
          <i class="fas fa-chalkboard-teacher"></i>
          Instructors <span class="tab-count" id="instructorsCount">0</span>
        </button>
        <button class="tab-btn" data-tab="activity">
          <i class="fas fa-history"></i>
          Activity Log
        </button>
        <button class="tab-btn" data-tab="login-history">
          <i class="fas fa-sign-in-alt"></i>
          Login History
        </button>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
      <!-- Overview Panel -->
      <div class="tab-panel active" id="overview-panel">
        <div class="overview-grid">
          <!-- User Statistics Chart -->
          <div class="chart-card">
            <div class="card-header">
              <h3>User Statistics</h3>
              <div class="card-controls">
                <button class="toggle-btn" onclick="toggleChart('userStatsChart')">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
            <div class="chart-content">
              <canvas id="userStatsChart"></canvas>
            </div>
          </div>

          <!-- Registration Trend -->
          <div class="chart-card">
            <div class="card-header">
              <h3>Registration Trend (30 Days)</h3>
              <div class="card-controls">
                <button class="toggle-btn" onclick="toggleChart('registrationChart')">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
            <div class="chart-content">
              <canvas id="registrationChart"></canvas>
            </div>
          </div>

          <!-- Online Users List -->
          <div class="stats-card online-users">
            <div class="card-header">
              <h3>Currently Online</h3>
              <div class="online-indicator"></div>
            </div>
            <div class="card-content">
              <div id="onlineUsersList" class="user-list">
                <!-- Populated by JS -->
              </div>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="stats-card recent-activity">
            <div class="card-header">
              <h3>Recent User Activity</h3>
              <button class="refresh-btn" onclick="loadUserActivity()">
                <i class="fas fa-sync-alt"></i>
              </button>
            </div>
            <div class="card-content">
              <div id="recentActivityList" class="activity-list">
                <!-- Populated by JS -->
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Students Table Panel -->
      <div class="tab-panel" id="students-panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>Student Users</h3>
            <span class="user-count" id="studentsTotal">0 students</span>
          </div>
          <div class="panel-controls">
            <input type="text" id="studentSearch" placeholder="Search students..." class="search-input">
            <select id="studentFilter" class="filter-select">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="online">Online</option>
            </select>
          </div>
        </div>
        <div class="enhanced-table-container">
          <table class="enhanced-table" id="studentsTable">
            <thead>
              <tr>
                <th>User Info</th>
                <th>Student ID</th>
                <th>Program</th>
                <th>Status</th>
                <th>Last Activity</th>
                <th>Downloads</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <!-- Populated via JS -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Instructors Table Panel -->
      <div class="tab-panel" id="instructors-panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>Instructor Users</h3>
            <span class="user-count" id="instructorsTotal">0 instructors</span>
          </div>
          <div class="panel-controls">
            <input type="text" id="instructorSearch" placeholder="Search instructors..." class="search-input">
            <select id="instructorFilter" class="filter-select">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="online">Online</option>
            </select>
          </div>
        </div>
        <div class="enhanced-table-container">
          <table class="enhanced-table" id="instructorsTable">
            <thead>
              <tr>
                <th>User Info</th>
                <th>Instructor ID</th>
                <th>Department</th>
                <th>Status</th>
                <th>Last Activity</th>
                <th>Uploads</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <!-- Populated via JS -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Activity Log Panel -->
      <div class="tab-panel" id="activity-panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>User Activity Monitor</h3>
            <span class="activity-status">Live monitoring enabled</span>
          </div>
          <div class="panel-controls">
            <select id="activityFilter" class="filter-select">
              <option value="">All Activities</option>
              <option value="download">Downloads</option>
              <option value="upload">Uploads</option>
              <option value="login">Logins</option>
            </select>
            <button class="action-btn secondary" onclick="loadUserActivity()">
              <i class="fas fa-sync-alt"></i>
              Refresh
            </button>
          </div>
        </div>
        <div class="activity-container">
          <div id="activityTimeline" class="activity-timeline">
            <!-- Populated by JS -->
          </div>
        </div>
      </div>

      <!-- Login History Panel -->
      <div class="tab-panel" id="login-history-panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>Login History</h3>
            <span class="history-info">Last 100 login events</span>
          </div>
          <div class="panel-controls">
            <input type="date" id="historyDate" class="date-input">
            <select id="historyFilter" class="filter-select">
              <option value="">All Events</option>
              <option value="login">Logins</option>
              <option value="logout">Logouts</option>
              <option value="failed">Failed Attempts</option>
            </select>
          </div>
        </div>
        <div class="history-container">
          <table class="enhanced-table" id="loginHistoryTable">
            <thead>
              <tr>
                <th>User</th>
                <th>Action</th>
                <th>IP Address</th>
                <th>Device</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <!-- Populated by JS -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <!-- User Management Modal -->
  <div class="modal-overlay" id="userModal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle">Add New User</h3>
        <button class="modal-close" onclick="closeUserModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <form id="userForm" class="modal-form">
        <input type="hidden" id="userId" name="user_id">
        
        <div class="form-row">
          <div class="form-group">
            <label for="firstName">First Name *</label>
            <input type="text" id="firstName" name="first_name" required>
          </div>
          <div class="form-group">
            <label for="lastName">Last Name *</label>
            <input type="text" id="lastName" name="last_name" required>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" required>
          </div>
          <div class="form-group">
            <label for="role">Role *</label>
            <select id="role" name="role" required onchange="handleRoleChange()">
              <option value="">Select Role</option>
              <option value="Student">Student</option>
              <option value="Instructor">Instructor</option>
              <option value="Dean">Dean</option>
            </select>
          </div>
        </div>
        
        <div class="form-row" id="passwordRow">
          <div class="form-group">
            <label for="password">Password *</label>
            <input type="password" id="password" name="password">
          </div>
          <div class="form-group">
            <label for="confirmPassword">Confirm Password *</label>
            <input type="password" id="confirmPassword" name="confirm_password">
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="department">Department</label>
            <select id="department" name="department">
              <option value="">Select Department</option>
              <option value="CBM">College of Business Management</option>
              <option value="CEC">College of Engineering and Computing</option>
              <option value="CTE">College of Teacher Education</option>
              <option value="CALS">College of Agriculture and Life Sciences</option>
            </select>
          </div>
          <div class="form-group">
            <label for="program">Program</label>
            <select id="program" name="program">
              <option value="">Select Program</option>
            </select>
          </div>
        </div>
        
        <div class="form-row student-fields" style="display: none;">
          <div class="form-group">
            <label for="studentId">Student ID</label>
            <input type="text" id="studentId" name="student_id">
          </div>
          <div class="form-group">
            <label for="yearLevel">Year Level</label>
            <select id="yearLevel" name="year_level">
              <option value="">Select Year</option>
              <option value="1">1st Year</option>
              <option value="2">2nd Year</option>
              <option value="3">3rd Year</option>
              <option value="4">4th Year</option>
            </select>
          </div>
        </div>
        
        <div class="form-row instructor-fields" style="display: none;">
          <div class="form-group">
            <label for="instructorId">Instructor ID</label>
            <input type="text" id="instructorId" name="instructor_id">
          </div>
          <div class="form-group">
            <label for="phoneNumber">Phone Number</label>
            <input type="tel" id="phoneNumber" name="phone_number">
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group checkbox-group">
            <label class="checkbox-label">
              <input type="checkbox" id="isActive" name="is_active" checked>
              <span class="checkmark"></span>
              Active User
            </label>
          </div>
          <div class="form-group checkbox-group">
            <label class="checkbox-label">
              <input type="checkbox" id="isApproved" name="is_approved" checked>
              <span class="checkmark"></span>
              Approved
            </label>
          </div>
        </div>
        
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" onclick="closeUserModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Save User</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Floating Action Button for Adding Users -->
  <div class="fab" onclick="openUserModal()" title="Add New User">
    <i class="fas fa-plus"></i>
  </div>

  <!-- JS -->
  <script src="../../js/Admin-usermanagement.js"></script>
  <script src="../../js/navigation.js"></script>
  <script src="../../js/Dashboard-profile.js"></script>

</body>
</html>
