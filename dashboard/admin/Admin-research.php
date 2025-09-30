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

// Get initial research data for the page
$sql = "SELECT r.*, u.name as uploaded_by_name, r.research_type as category, r.year_published as research_year, r.abstract as description
        FROM research_papers r
        LEFT JOIN users u ON r.submitted_by = u.user_id
        WHERE r.is_active = 1
        ORDER BY r.submission_date DESC";
$result = $conn->query($sql);
$research_data = [];
if ($result) {
    $research_data = $result->fetch_all(MYSQLI_ASSOC);
}
$initialData = [
    'research' => $research_data
];

$jsInitialData = json_encode($initialData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Research Dashboard | Admin Panel</title>

  <!-- Material Icons -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <!-- Flag Icons (optional) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/atisawd/flag-icons@1.0.0/css/fi.css">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link rel="stylesheet" href="../../css/Admin-research.css">
  <link rel="stylesheet" href="../../css/header&sideBar.css">
  <link rel="stylesheet" href="../../css/Dashboard-profile.css">
  
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Initial Data -->
  <script>
    window.initialData = <?php echo $jsInitialData; ?>;
  </script>
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
        <button id="logoutBtn">Logout</button>
      </div>
    </div>
  </header>
      
  <!-- SIDEBAR -->
  <div class="sidebar hover-enabled">
    <ul>
      <li><a href="Admin-dashboard.php"><span class="material-icons">dashboard</span><span class="text">Dashboard</span></a></li>
      <li class="has-submenu">
        <a href="#"><span class="material-icons">book</span><span class="text">Research</span><span class="material-icons arrow">expand_more</span></a>
        <ul class="submenu">
          <li><a href="#" id="allResearchLink">All Research</a></li>
          <li><a href="#" id="cbmLink">CBM</a></li>
          <li><a href="#" id="informationTechnologyLink">Information Technology</a></li>
          <li><a href="#" id="businessAdministrationLink">Business Administration</a></li>
          <li><a href="#" id="elementaryEducationLink">Elementary Education</a></li>
        </ul>
      </li>
      <li><a href="Admin-materials.php"><span class="material-icons">folder</span><span class="text">Materials</span></a></li>
      <li><a href="Admin-request.php"><span class="material-icons">hourglass_empty</span><span class="text">Requests</span></a></li>
      <li><a href="Admin-archive.php"><span class="material-icons">archive</span><span class="text">Archive History</span></a></li>
      <li><a href="Admin-usermanagement.php"><span class="material-icons">group</span><span class="text">User Management</span></a></li>
      <li><a href="Admin-settings.php"><span class="material-icons">settings</span><span class="text">Settings</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <div class="page-header">
      <div class="header-content">
        <h1 class="page-title">Research Management</h1>
        <p class="page-subtitle">Monitor and manage research papers with real-time analytics</p>
        <div class="header-actions">
          <button class="action-btn secondary" onclick="refreshResearchData()">
            <i class="fas fa-sync-alt"></i>
            Refresh
          </button>
          <button class="action-btn primary upload-btn">
            <i class="fas fa-plus"></i>
            Add Research
          </button>
        </div>
      </div>
      
      <!-- Real-time Stats -->
      <div class="header-metrics">
        <div class="metric-item">
          <span class="metric-value" id="totalResearch"><?php echo count($research_data); ?></span>
          <span class="metric-label">Total Research</span>
        </div>
        <div class="metric-item pending">
          <span class="metric-value" id="pendingResearch"><?php echo count(array_filter($research_data, function($r) { return $r['status'] === 'pending'; })); ?></span>
          <span class="metric-label">Pending</span>
        </div>
        <div class="metric-item approved">
          <span class="metric-value" id="approvedResearch"><?php echo count(array_filter($research_data, function($r) { return $r['status'] === 'approved'; })); ?></span>
          <span class="metric-label">Approved</span>
        </div>
        <div class="metric-item rejected">
          <span class="metric-value" id="rejectedResearch"><?php echo count(array_filter($research_data, function($r) { return $r['status'] === 'rejected'; })); ?></span>
          <span class="metric-label">Rejected</span>
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
        <button class="tab-btn" data-tab="research-list">
          <i class="fas fa-list"></i>
          Research Papers
        </button>
        <button class="tab-btn" data-tab="analytics">
          <i class="fas fa-analytics"></i>
          Analytics
        </button>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
      <!-- Overview Panel -->
      <div class="tab-panel active" id="overview-panel">
        <div class="overview-grid">
          <!-- Research Status Chart -->
          <div class="chart-card">
            <div class="card-header">
              <h3>Research Status Distribution</h3>
            </div>
            <div class="chart-content">
              <canvas id="statusChart"></canvas>
            </div>
          </div>

          <!-- Monthly Research Trend -->
          <div class="chart-card">
            <div class="card-header">
              <h3>Monthly Research Submissions</h3>
            </div>
            <div class="chart-content">
              <canvas id="monthlyChart"></canvas>
            </div>
          </div>

          <!-- Department Distribution -->
          <div class="chart-card">
            <div class="card-header">
              <h3>Department Distribution</h3>
            </div>
            <div class="chart-content">
              <canvas id="departmentChart"></canvas>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="stats-card recent-activity">
            <div class="card-header">
              <h3>Recent Submissions</h3>
              <button class="refresh-btn" onclick="loadRecentResearch()">
                <i class="fas fa-sync-alt"></i>
              </button>
            </div>
            <div class="card-content">
              <div id="recentResearchList" class="activity-list">
                <!-- Populated by JS -->
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Research List Panel -->
      <div class="tab-panel" id="research-list-panel">
        <div class="panel-header">
          <div class="panel-title">
            <h3>Research Papers</h3>
            <span class="research-count" id="researchTotal"><?php echo count($research_data); ?> papers</span>
          </div>
          <div class="panel-controls">
            <div class="search-wrapper">
              <span class="material-icons search-icon">search</span>
              <input type="text" id="titleSearch" placeholder="Search by title, author, or keywords..." class="search-input">
            </div>
            <select id="statusFilter" class="filter-select">
              <option value="all">All Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
            <select id="categoryFilter" class="filter-select">
              <option value="all">All Categories</option>
              <option value="Thesis">Thesis</option>
              <option value="Dissertation">Dissertation</option>
              <option value="Capstone">Capstone</option>
              <option value="Research Paper">Research Paper</option>
            </select>
            <select id="yearFilter" class="filter-select">
              <option value="all">All Years</option>
              <option value="2025">2025</option>
              <option value="2024">2024</option>
              <option value="2023">2023</option>
              <option value="2022">2022</option>
            </select>
          </div>
        </div>
        <!-- Research Container -->
        <div class="research-container" id="researchContainer"></div>
      </div>

      <!-- Analytics Panel -->
      <div class="tab-panel" id="analytics-panel">
        <div class="analytics-grid">
          <!-- Research Type Distribution -->
          <div class="chart-card">
            <div class="card-header">
              <h3>Research Type Distribution</h3>
            </div>
            <div class="chart-content">
              <canvas id="typeChart"></canvas>
            </div>
          </div>

          <!-- Yearly Trend -->
          <div class="chart-card">
            <div class="card-header">
              <h3>Yearly Research Trend</h3>
            </div>
            <div class="chart-content">
              <canvas id="yearlyChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Modal -->
    <div class="upload-modal" id="uploadModal">
      <div class="upload-modal-content">
        <span class="close-modal">&times;</span>
        <h3>Upload New Research</h3>
        <form id="uploadForm">
          <label for="researchTitle">Title</label>
          <input type="text" id="researchTitle" name="researchTitle" placeholder="Enter research title" required>

          <label for="researchUploader">Uploader Name</label>
          <input type="text" id="researchUploader" name="researchUploader" placeholder="Your name" required>

          <div class="field-row">
            <label for="researchDepartment"><strong>Department:</strong></label>
            <select id="researchDepartment" name="department" required>
              <option value="">Select Department</option>
              <option value="CBM">CBM</option>
              <option value="Information Technology">Information Technology</option>
              <option value="Business Administration">Business Administration</option>
              <option value="Elementary Education">Elementary Education</option>
            </select>
          </div>

          <div class="field-row">
            <label for="researchCategory">Category:</label>
            <select id="researchCategory" name="researchCategory" required>
              <option value="">Select Category</option>
              <option value="Education">Education</option>
              <option value="Technology">Technology</option>
              <option value="Health">Health</option>
              <option value="Economics">Economics</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="field-row">
            <label for="researchYear">Research Year:</label>
            <input type="number" id="researchYear" name="researchYear" min="2000" max="2100" value="2025" required>
          </div>

          <label for="researchInfo">Short Description</label>
          <textarea id="researchInfo" name="researchInfo" placeholder="Enter a brief description" required></textarea>

          <label for="researchFile">Upload File (PDF only)</label>
          <input type="file" id="researchFile" name="researchFile" accept="application/pdf" required>

          <button type="submit" class="submit-btn">Submit</button>
        </form>
      </div>
    </div>

  </div>

  <!-- JS -->
  <script src="../../js/Admin-research.js"></script>
  <script src="../../js/upload.js"></script>
  <script src="../../js/navigation.js"></script>
  <script src="../../js/Dashboard-profile.js"></script>
</body>
</html>