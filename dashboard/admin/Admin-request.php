<?php
session_start();
require_once('../../db.php');

// Authentication check
function isAdminAuthenticated() {
    return isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['Dean', 'Admin']);
}

// Redirect if not authenticated
if (!isAdminAuthenticated()) {
    header('Location: ../../user/login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Request Dashboard</title>

  <!-- CSS -->
  <link rel="stylesheet" href="../../css/Admin-request.css">
  <link rel="stylesheet" href="../../css/header&sideBar.css">
  <link rel="stylesheet" href="../../css/Dashboard-profile.css">

  <!-- Google Icons & FontAwesome -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Lottie & Chart.js -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.10.0/lottie.min.js"></script>
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
        <button id="logoutBtn">Logout</button>
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
      <li><a href="Admin-usermanagement.php"><span class="material-icons">group</span><span class="text">User Management</span></a></li>
      <li><a href="Admin-settings.php"><span class="material-icons">settings</span><span class="text">Settings</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="page-header">
      <div class="page-title-section">
        <h2><span class="material-icons">pending_actions</span>Requests Dashboard</h2>
        <p class="page-subtitle">Manage and review all pending requests</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-icon" id="refreshBtn" title="Refresh">
          <span class="material-icons">refresh</span>
        </button>
        <button class="btn btn-primary" id="exportBtn" title="Export Data">
          <span class="material-icons">download</span>
          Export
        </button>
      </div>
    </div>

    <!-- FILTERS AND SEARCH SECTION -->
    <div class="filters-container">
      <div class="filter-group">
        <input type="text" id="searchInput" placeholder="Search requests..." class="search-input">
        <button class="btn btn-primary" id="searchBtn">
          <span class="material-icons">search</span>
        </button>
      </div>
      
      <div class="filter-group">
        <select id="statusFilter" class="filter-select">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="under_review">Under Review</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        
        <select id="priorityFilter" class="filter-select">
          <option value="">All Priorities</option>
          <option value="urgent">Urgent</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
        </select>
        
        <select id="departmentFilter" class="filter-select">
          <option value="">All Departments</option>
          <!-- Populated dynamically -->
        </select>
      </div>
      
      <div class="filter-group">
        <input type="date" id="dateFrom" class="date-input">
        <span>to</span>
        <input type="date" id="dateTo" class="date-input">
        <button class="btn btn-secondary" id="clearFiltersBtn">Clear</button>
      </div>
    </div>

    <!-- REQUESTS STATISTICS -->
    <div class="stats-summary">
      <div class="stat-card">
        <span class="stat-number" id="totalRequests">0</span>
        <span class="stat-label">Total Requests</span>
      </div>
      <div class="stat-card">
        <span class="stat-number" id="pendingRequests">0</span>
        <span class="stat-label">Pending</span>
      </div>
      <div class="stat-card">
        <span class="stat-number" id="urgentRequests">0</span>
        <span class="stat-label">Urgent</span>
      </div>
      <div class="stat-card">
        <span class="stat-number" id="approvedToday">0</span>
        <span class="stat-label">Approved Today</span>
      </div>
    </div>

    <!-- REQUESTS SECTION -->
    <div class="requests-container">
      <div class="table-controls">
        <div class="bulk-selector">
          <input type="checkbox" id="selectAll" class="bulk-checkbox">
          <label for="selectAll">Select All</label>
          <span id="selectedCount">(0 selected)</span>
        </div>
        <div class="table-actions">
          <button class="btn btn-success" id="bulkApproveBtn" disabled>Approve Selected</button>
          <button class="btn btn-danger" id="bulkRejectBtn" disabled>Reject Selected</button>
          <button class="btn btn-warning" id="bulkDeleteBtn" disabled>Delete Selected</button>
        </div>
      </div>
      
      <div class="table-wrapper">
        <table class="requests-table">
          <thead>
            <tr>
              <th><input type="checkbox" id="headerSelectAll"></th>
              <th>Priority</th>
              <th>Name</th>
              <th>Title</th>
              <th>Department</th>
              <th>Category</th>
              <th>Type</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="requestsTableBody">
            <!-- Requests will be populated here -->
          </tbody>
        </table>
      </div>
      
      <!-- PAGINATION -->
      <div class="pagination-container">
        <div class="pagination-info">
          <span id="paginationInfo">Showing 0 to 0 of 0 entries</span>
        </div>
        <div class="pagination-controls">
          <button class="btn btn-secondary" id="prevPageBtn" disabled>Previous</button>
          <div class="page-numbers" id="pageNumbers"></div>
          <button class="btn btn-secondary" id="nextPageBtn" disabled>Next</button>
        </div>
      </div>
    </div>

    <!-- CONFIRM APPROVE/DECLINE MODAL -->
    <div class="modal" id="confirmActionModal">
      <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3 id="confirmActionTitle">Confirm Action</h3>
        <p id="confirmActionMessage">Are you sure?</p>
        <div class="modal-actions">
          <button id="confirmActionBtn" class="approve-btn">Yes</button>
          <button id="cancelActionBtn" class="decline-btn">No</button>
        </div>
      </div>
    </div>

    <!-- BULK ACTIONS MODAL -->
    <div class="modal" id="bulkActionModal">
      <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3 id="bulkActionTitle">Bulk Action</h3>
        <p id="bulkActionMessage">Select an action for the selected requests:</p>
        
        <div class="bulk-feedback">
          <label for="bulkFeedback">Feedback (optional):</label>
          <textarea id="bulkFeedback" placeholder="Enter feedback for selected requests..."></textarea>
        </div>
        
        <div class="modal-actions">
          <button id="bulkApproveAllBtn" class="btn btn-success">Approve All</button>
          <button id="bulkRejectAllBtn" class="btn btn-danger">Reject All</button>
          <button id="bulkDeleteAllBtn" class="btn btn-warning">Delete All</button>
          <button id="cancelBulkBtn" class="btn btn-secondary">Cancel</button>
        </div>
      </div>
    </div>
  </main>

  <!-- REQUEST MODAL -->
  <div class="modal" id="requestModal">
    <div class="modal-content">
      <span class="close-btn">&times;</span>
      <h3>Request Details</h3>

      <div class="modal-field">
        <strong>Name:</strong> <span id="modalName">N/A</span>
      </div>

      <div class="modal-field">
        <strong>Title:</strong> <span id="modalTitle">N/A</span>
      </div>

      <div class="modal-field horizontal-group">
        <strong>Details:</strong>
        <span id="modalDepartment">N/A</span>
        <span id="modalCategory">N/A</span>
        <span id="modalYear">N/A</span>
      </div>

      <div class="modal-field">
        <strong>Date:</strong> <span id="modalDate">N/A</span>
      </div>

      <div class="modal-field">
        <strong>Info:</strong> <p id="modalInfo">No additional info</p>
      </div>

      <div class="modal-field">
        <strong>Files:</strong>
        <div id="modalFiles"><p class="no-files">No files uploaded.</p></div>
      </div>

      <div class="modal-field">
        <label for="feedback"><strong>Feedback:</strong></label>
        <input type="text" id="feedback" placeholder="Enter feedback">
      </div>

      <div class="modal-actions">
        <button id="approveBtn" class="action-btn approve-btn">Approve</button>
        <button id="declineBtn" class="action-btn decline-btn">Decline</button>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="../../js/Admin-request.js"></script>
  <script src="../../js/navigation.js"></script>
  <script src="../../js/Dashboard-profile.js"></script>
</body>
</html>