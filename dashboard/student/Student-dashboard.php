<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="../../css/student-dashboard.css">
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
      <img src="default-profile.png" alt="Profile" class="dashboard-profile" id="dashboardProfile">

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
      <li><a href="#"><span class="material-icons">dashboard</span><span class="text">Dashboard</span></a></li>
      <li><a href="Student-research.php"><span class="material-icons">book</span><span class="text">Research</span></a></li>
      <li><a href="Student-materials.php"><span class="material-icons">folder</span><span class="text">Materials</span></a></li>
      <li><a href="Student-settings.php"><span class="material-icons">settings</span><span class="text">Settings</span></a></li>
    </ul>

  </div>

  <!-- MAIN CONTENT -->
   
  <main class="main-content"> 
     <div class="welcome-message">
  <img id="welcomeProfile" src="default-profile.png" alt="Profile Picture" class="welcome-profile">
  <span id="welcomeFullName">Welcome, Student!</span>
</div>

    <!-- TOP INFO BOXES -->
    <div class="stats-container">
      <!-- Research -->
      <div class="stat-box">
        <div id="research-animation" class="lottie-box"></div>
        <div>
          <h3 id="researchCount">0</h3>
          <p>Research</p>
        </div>
      </div>

      <!-- Materials -->
      <div class="stat-box">
        <div id="materials-animation" class="lottie-box"></div>
        <div>
          <h3 id="materialsCount">0</h3>
          <p>Materials</p>
        </div>
      </div>

      <!-- Clock -->
      <div class="stat-box clock-box">
        <div class="clock-icon">
          <span class="material-icons">schedule</span>
        </div>
        <div class="clock-info">
          <div id="calendarDate"></div>
          <div id="calendarTime"></div>
        </div>
      </div>
    </div>

    <!-- Research Category Chart -->
    <div class="chart-card wide">
      <h3>Research by Category</h3>
      <canvas id="barChart"></canvas>
    </div>

    <!-- 📌 RECENT RESEARCH UPLOADS -->
    <section class="recent-uploads">
    <div class="recent-uploads-container" id="recentUploadsContainer">
      <h3 class="recent-uploads-title">Recent Research Uploads</h3>
      <div class="uploads-list" id="recentResearchUploads">
        <!-- JS will populate max 5 items here -->
      </div>
    </div>
  </section>

  </main>

  <!-- SCRIPTS -->
  <script src="../../js/navigation.js"></script>
  <script src="../../js/Dashboard-profile.js"></script>
  <script src="../../js/calendar.js"></script>
  <script type="module" src="../../js/student-dashboard/student dashboard-init.js"></script>
</body>
</html>
  