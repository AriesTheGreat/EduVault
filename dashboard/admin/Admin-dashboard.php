
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../../css/Admin-dashboard.css">
  <link rel="stylesheet" href="../../css/header&sideBar.css">
  <link rel="stylesheet" href="../../css/Dashboard-profile.css">
  
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <!-- FontAwesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.10.0/lottie.min.js"></script>
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
      <img src="../../default-profile.png" alt="Profile" class="dashboard-profile" id="dashboardProfile">

      <!-- Profile dropdown -->
      <div onclick="toggleProfileDropdown()" class="profile-dropdown" hidden id="profileDropdown">
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
    <h2>Dashboard Overview</h2>

    <!-- TOP INFO BOXES -->
    <div class="stats-container">
      <!-- Research -->
      <a href="Admin-research.php"><div class="stat-box">
        <div id="research-animation" class="lottie-box"></div>
        <div>
          <h3 id="researchCount">0</h3>
          <p>Research</p>
        </div>
      </div></a>

      <!-- Materials -->
      <a href="Admin-materials.php"><div class="stat-box">
        <div id="materials-animation" class="lottie-box"></div>
        <div>
          <h3 id="materialsCount">0</h3>
          <p>Materials</p>
        </div>
      </div></a>

      <!-- Pending Requests -->
      <a href="Admin-request.php"><div class="stat-box">
        <div id="requests-animation" class="lottie-box"></div>
        <div>
          <h3 id="requestsCount">0</h3>
          <p>Pending Requests</p>
        </div>
      </div></a>
    </div>

    <div class="charts-container">
      <!-- Pending Requests Pie -->
      <div class="chart-card">
        <h3>Pending Request</h3>
        <canvas id="pieChart"></canvas>
        <div class="pie-counts">
          <p>Pending: <span id="pendingCount">0</span></p>
        </div>
      </div>

      <!-- Active Users -->
      <div class="chart-card access-card">
        <div id="active-users-illustration" class="lottie-box"></div>
        <h3>Active Users</h3>
        <p id="userCount">0</p>
        <small>Currently accessing the system</small>

        <!-- Mini Weekly Users Chart -->
        <canvas id="weeklyUsersChart"></canvas>
      </div>
    </div>

    <!-- Pending Research Section -->
    <div class="chart-card wide pending-research-card">
      <div class="card-header-row">
        <h3>⏳ Pending Research Uploads</h3>
        <a href="Admin-research.php" class="view-all-link">View All →</a>
      </div>
      <div id="pendingResearchContainer" class="pending-research-list">
        <div class="loading-state">
          <i class="fas fa-spinner fa-spin"></i> Loading pending research...
        </div>
      </div>
    </div>

    <!-- Line Chart -->
    <div class="chart-card wide">
      <h3>Research Growth</h3>
      <canvas id="lineChart"></canvas>
    </div>
  </main>

  <!-- SCRIPTS -->
  <script src="../../js/navigation.js"></script>
  <script src="../../js/Dashboard-profile.js"></script>
  <script type="module" src="../../js/dashboard/dashboard-init.js"></script>
  <script src="../../js/dashboard/dashboard-enhanced.js"></script>
  <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
  
  <!-- Pending Research Script -->
  <script>
    // Load pending research on page load
    async function loadPendingResearch() {
      const container = document.getElementById('pendingResearchContainer');
      try {
        const response = await fetch('../../api/pending-research.php');
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
          container.innerHTML = data.data.map(research => `
            <div class="pending-research-item">
              <div class="research-icon">
                <i class="fas fa-file-pdf"></i>
              </div>
              <div class="research-info">
                <h4>${research.title}</h4>
                <p class="research-meta">
                  <span><i class="fas fa-user"></i> ${research.uploaded_by_name || 'Unknown'}</span>
                  <span><i class="fas fa-calendar"></i> ${new Date(research.submission_date).toLocaleDateString()}</span>
                  <span class="research-type">${research.research_type || 'Research Paper'}</span>
                </p>
                <p class="research-abstract">${research.abstract ? research.abstract.substring(0, 100) + '...' : 'No description available'}</p>
              </div>
              <div class="research-actions">
                <button class="action-btn approve-btn" onclick="handleResearch(${research.research_id}, 'approve')" title="Approve">
                  <i class="fas fa-check"></i> Approve
                </button>
                <button class="action-btn reject-btn" onclick="handleResearch(${research.research_id}, 'reject')" title="Reject">
                  <i class="fas fa-times"></i> Reject
                </button>
                <a href="../../${research.file_path}" target="_blank" class="action-btn view-btn" title="View PDF">
                  <i class="fas fa-eye"></i> View
                </a>
              </div>
            </div>
          `).join('');
        } else if (data.success && data.data.length === 0) {
          container.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i> No pending research at the moment</div>';
        } else {
          container.innerHTML = '<div class="error-state"><i class="fas fa-exclamation-triangle"></i> Error loading pending research</div>';
        }
      } catch (error) {
        console.error('Error loading pending research:', error);
        container.innerHTML = '<div class="error-state"><i class="fas fa-exclamation-triangle"></i> Failed to load pending research</div>';
      }
    }
    
    // Handle approve/reject actions
    async function handleResearch(researchId, action) {
      const reason = action === 'reject' ? prompt('Please provide a reason for rejection:') : null;
      if (action === 'reject' && !reason) return;
      
      try {
        const response = await fetch('../../actions/approve_research.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ research_id: researchId, action, reason })
        });
        const data = await response.json();
        
        if (data.success) {
          alert(`Research ${action}d successfully!`);
          loadPendingResearch(); // Reload list
        } else {
          alert('Error: ' + data.error);
        }
      } catch (error) {
        console.error('Error handling research:', error);
        alert('Failed to ' + action + ' research');
      }
    }
    
    // Load on page ready
    document.addEventListener('DOMContentLoaded', loadPendingResearch);
  </script>
  
  <!-- Debug script to test API -->
  <script>
    console.log('🔍 Dashboard Debug: Testing API connection...');
    
    // Test API directly
    setTimeout(async () => {
      try {
        const testResponse = await fetch('../../actions/dashboard_data.php');
        const testData = await testResponse.json();
        console.log('📊 Direct API Test Result:', testData);
        
        if (testData.success && testData.data.research_count !== undefined) {
          console.log('✅ Research count from API:', testData.data.research_count);
          console.log('✅ Total research count from API:', testData.data.total_research_count);
        } else {
          console.error('❌ API test failed:', testData);
        }
      } catch (error) {
        console.error('❌ API connection error:', error);
      }
    }, 2000);
  </script>

</body>
</html>