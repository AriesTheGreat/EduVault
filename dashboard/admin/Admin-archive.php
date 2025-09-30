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

// Get archived items data manually (without view)
$archived_data = [];

// Get archived research papers
$research_sql = "SELECT 
    'research' as item_type,
    r.research_id as item_id,
    r.title,
    r.authors as uploader_name,
    r.research_type as category,
    r.year_published as year,
    COALESCE(d.department_name, 'Unknown') as department,
    r.file_path,
    r.file_size,
    r.archived_at,
    r.archived_by,
    u.name as archived_by_name,
    r.submission_date as original_date
FROM research_papers r
LEFT JOIN programs p ON r.program_id = p.program_id
LEFT JOIN departments d ON p.department_id = d.department_id
LEFT JOIN users u ON r.archived_by = u.user_id
WHERE r.is_active = 0 AND r.archived_at IS NOT NULL";

$research_result = $conn->query($research_sql);
if ($research_result) {
    while ($row = $research_result->fetch_assoc()) {
        $archived_data[] = $row;
    }
}

// Get archived materials
$materials_sql = "SELECT 
    'material' as item_type,
    m.material_id as item_id,
    m.title,
    mu.name as uploader_name,
    'Material' as category,
    YEAR(m.upload_date) as year,
    COALESCE(d.department_name, 'Unknown') as department,
    m.file_path,
    m.file_size,
    m.archived_at,
    m.archived_by,
    u.name as archived_by_name,
    m.upload_date as original_date
FROM materials m
LEFT JOIN courses c ON m.course_id = c.course_id
LEFT JOIN programs p ON c.program_id = p.program_id
LEFT JOIN departments d ON p.department_id = d.department_id
LEFT JOIN users mu ON m.uploaded_by = mu.user_id
LEFT JOIN users u ON m.archived_by = u.user_id
WHERE m.is_active = 0 AND m.archived_at IS NOT NULL";

$materials_result = $conn->query($materials_sql);
if ($materials_result) {
    while ($row = $materials_result->fetch_assoc()) {
        $archived_data[] = $row;
    }
}

// Get archived references
$references_sql = "SELECT 
    'reference' as item_type,
    ref.reference_id as item_id,
    ref.title,
    ru.name as uploader_name,
    ref.reference_type as category,
    ref.publication_year as year,
    'Reference' as department,
    ref.file_path,
    ref.file_size,
    ref.archived_at,
    ref.archived_by,
    u.name as archived_by_name,
    ref.upload_date as original_date
FROM `references` ref
LEFT JOIN users ru ON ref.uploaded_by = ru.user_id
LEFT JOIN users u ON ref.archived_by = u.user_id
WHERE ref.is_active = 0 AND ref.archived_at IS NOT NULL";

$references_result = $conn->query($references_sql);
if ($references_result) {
    while ($row = $references_result->fetch_assoc()) {
        $archived_data[] = $row;
    }
}

// Sort by archived_at DESC
usort($archived_data, function($a, $b) {
    return strtotime($b['archived_at']) - strtotime($a['archived_at']);
});

// Calculate statistics
$stats = [];
foreach ($archived_data as $item) {
    $type = $item['item_type'];
    if (!isset($stats[$type])) {
        $stats[$type] = ['count' => 0, 'total_size' => 0];
    }
    $stats[$type]['count']++;
    $stats[$type]['total_size'] += $item['file_size'] ?? 0;
}

// Get recent activity (first 5 items)
$recent_activity = array_slice($archived_data, 0, 5);

$initialData = [
    'archived_items' => $archived_data,
    'stats' => $stats,
    'recent_activity' => $recent_activity
];

$jsInitialData = json_encode($initialData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Archive Management | Admin Panel</title>

  <!-- Material Icons -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link rel="stylesheet" href="../../css/Admin-archive.css">
  <link rel="stylesheet" href="../../css/header&sideBar.css">
  <link rel="stylesheet" href="../../css/Dashboard-profile.css">

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
      <li><a href="Admin-research.php"><span class="material-icons">book</span><span class="text">Research</span></a></li>
      <li><a href="Admin-materials.php"><span class="material-icons">folder</span><span class="text">Materials</span></a></li>
      <li><a href="Admin-request.php"><span class="material-icons">hourglass_empty</span><span class="text">Requests</span></a></li>
      <li class="active"><a href="Admin-archive.php"><span class="material-icons">archive</span><span class="text">Archive History</span></a></li>
      <li><a href="Admin-usermanagement.php"><span class="material-icons">group</span><span class="text">User Management</span></a></li>
      <li><a href="Admin-settings.php"><span class="material-icons">settings</span><span class="text">Settings</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="page-header">
      <div class="page-title-section">
        <h2><span class="material-icons"></span>Archive Management</h2>
        <p class="page-subtitle">Manage deleted items, restore content, and maintain archive history</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-icon" id="refreshBtn" title="Refresh">
          <span class="material-icons">refresh</span>
        </button>
        <button class="btn btn-primary" id="exportBtn" title="Export Archive Data">
          <span class="material-icons">download</span>
          Export
        </button>
      </div>
    </div>

    <!-- FILTERS AND SEARCH SECTION -->
    <div class="filters-container">
      <div class="filter-group">
        <input type="text" id="searchInput" placeholder="Search archived items..." class="search-input">
        <button class="btn btn-primary" id="searchBtn">
          <span class="material-icons">search</span>
        </button>
      </div>
      
      <div class="filter-group">
        <select id="typeFilter" class="filter-select">
          <option value="all">All Types</option>
          <option value="research">Research</option>
          <option value="material">Materials</option>
          <option value="reference">References</option>
        </select>
        
        <select id="dateFilter" class="filter-select">
          <option value="all">All Dates</option>
          <option value="today">Today</option>
          <option value="week">This Week</option>
          <option value="month">This Month</option>
          <option value="year">This Year</option>
        </select>
        
        <select id="departmentFilter" class="filter-select">
          <option value="all">All Departments</option>
          <option value="CBM">CBM</option>
          <option value="Information Technology">Information Technology</option>
          <option value="Business Administration">Business Administration</option>
          <option value="Elementary Education">Elementary Education</option>
        </select>
      </div>
      
      <div class="filter-group">
        <button class="btn btn-secondary" id="clearFiltersBtn">Clear Filters</button>
        <button class="btn btn-warning" id="bulkActionsBtn">
          <span class="material-icons">checklist</span>
          Batch Operations
        </button>
      </div>
    </div>

    <!-- ARCHIVE STATISTICS -->
    <div class="stats-summary">
      <div class="stat-card">
        <span class="stat-number" id="researchCount">0</span>
        <span class="stat-label">Archived Research</span>
      </div>
      <div class="stat-card">
        <span class="stat-number" id="materialCount">0</span>
        <span class="stat-label">Archived Materials</span>
      </div>
      <div class="stat-card">
        <span class="stat-number" id="referenceCount">0</span>
        <span class="stat-label">Archived References</span>
      </div>
      <div class="stat-card">
        <span class="stat-number" id="totalSize">0 MB</span>
        <span class="stat-label">Total Archive Size</span>
      </div>
    </div>

    <!-- ARCHIVE ITEMS SECTION -->
    <div class="requests-container">
      <div class="table-controls">
        <div class="bulk-selector">
          <input type="checkbox" id="selectAll" class="bulk-checkbox">
          <label for="selectAll">Select All</label>
          <span id="selectedCount">(0 selected)</span>
        </div>
        <div class="table-actions">
          <button class="btn btn-success" id="bulkRestoreBtn" disabled>
            <span class="material-icons">restore</span> Restore Selected
          </button>
          <button class="btn btn-danger" id="bulkDeleteBtn" disabled>
            <span class="material-icons">delete_forever</span> Remove Selected
          </button>
        </div>
      </div>
      
      <!-- Archive Table -->
      <div class="archive-table-container">
        <table class="archive-table">
          <thead>
            <tr>
              <th><input type="checkbox" id="headerSelectAll"></th>
              <th>#</th>
              <th>Title</th>
              <th>Author/Uploader</th>
              <th>Date Deleted</th>
              <th>Document Type</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="archiveTableBody">
            <!-- Archive items will be populated here -->
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

    <!-- CONFIRM RESTORE MODAL -->
    <div class="modal" id="restoreModal">
      <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3 id="restoreActionTitle">Restore Item</h3>
        <p id="restoreMessage">Are you sure you want to restore this item?</p>
        <div class="modal-actions">
          <button id="confirmRestoreBtn" class="btn btn-success">Yes, Restore</button>
          <button id="cancelRestoreBtn" class="btn btn-secondary">Cancel</button>
        </div>
      </div>
    </div>

    <!-- CONFIRM DELETE MODAL -->
    <div class="modal" id="deleteModal">
      <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3 id="deleteActionTitle">Permanently Delete Item</h3>
        <p id="deleteMessage">Are you sure you want to permanently delete this item? This action cannot be undone.</p>
        <div class="modal-actions">
          <button id="confirmDeleteBtn" class="btn btn-danger">Yes, Delete</button>
          <button id="cancelDeleteBtn" class="btn btn-secondary">Cancel</button>
        </div>
      </div>
    </div>

    <!-- BATCH OPERATIONS MODAL -->
    <div class="modal" id="bulkActionModal">
      <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3 id="bulkActionTitle">Batch Operations</h3>
        <p id="bulkActionMessage">Select an operation for the selected archived items:</p>
        
        <div class="bulk-feedback">
          <label for="bulkReason">Reason (optional):</label>
          <textarea id="bulkReason" placeholder="Enter reason for bulk action..."></textarea>
        </div>
        
        <div class="modal-actions">
          <button id="bulkRestoreAllBtn" class="btn btn-success">Restore All</button>
          <button id="bulkDeleteAllBtn" class="btn btn-danger">Delete All</button>
          <button id="cancelBulkBtn" class="btn btn-secondary">Cancel</button>
        </div>
      </div>
    </div>
  </main>

  <!-- JS -->
  <script src="../../js/Admin-archive.js"></script>
  <script src="../../js/navigation.js"></script>
  <script src="../../js/Dashboard-profile.js"></script>
</body>
</html>
