<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instructor - Research Dashboard</title>

  <!-- Material Icons -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <!-- Flag Icons (optional) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/atisawd/flag-icons@1.0.0/css/fi.css">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">


  <!-- CSS -->
  <link rel="stylesheet" href="../../css/Instructor-research.css">
  <link rel="stylesheet" href="../../css/Header&Body-research.css">
  <link rel="stylesheet" href="../../css/Dashboard-profile.css">
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
      <li><a href="Instructor-dashboard.php"><span class="material-icons">dashboard</span><span class="text">Dashboard</span></a></li>
      <li class="has-submenu">
        <a href="#"><span class="material-icons">book</span><span class="text">Research</span><span class="material-icons arrow">expand_more</span></a>
        <ul class="submenu">
          <li><a href="#" id="allResearchLink">All Research</a></li>
          <li><a href="#" id="cbmLink">CBM</a></li>
          <li><a href="#" id="bsitLink">BSIT</a></li>
          <li><a href="#" id="bsbaLink">BSBA</a></li>
          <li><a href="#" id="beedLink">BEED</a></li>
        </ul>
      </li>
      <li><a href="Instructor-materials.php"><span class="material-icons">folder</span><span class="text">Materials</span></a></li>
      <li><a href="Instructor-settings.php"><span class="material-icons">settings</span><span class="text">Settings</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">

    <!-- Filters + Upload -->
    <div class="filters-top">
      <div class="filter-left">
        <div class="search-wrapper">
          <span class="material-icons search-icon">search</span>
          <input type="text" id="titleSearch" placeholder="Search by title...">
        </div>
      </div>
      <div class="filter-right">
        <select id="categoryFilter">
          <option value="all">All Categories</option>
          <option value="technology">Technology</option>
          <option value="education">Education</option>
          <option value="health">Health</option>
          <option value="science">Science</option>
          <option value="Economics">Economics</option>
        </select>

        <select id="yearFilter">
          <option value="all">All Years</option>
          <option value="2025">2025</option>
          <option value="2024">2024</option>
          <option value="2023">2023</option>
          <option value="2022">2022</option>
        </select>

        <button class="upload-btn"><span class="material-icons">add</span></button>
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
          <select id="researchDepartment" required>
            <option value="">Select Department</option>
            <option value="CBM">CBM</option>
            <option value="BSIT">BSIT</option>
            <option value="BSBA">BSBA</option>
            <option value="BEED">BEED</option>
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

    <!-- Research Files Container -->
    <div class="research-container" id="researchContainer"></div>
  </div>

  <!-- JS -->
  <script src="../../js/calendar.js"></script>
  <script src="../../js/Instructor-research.js"></script>
  <script src="../../js/upload.js"></script>
  <script src="../../js/navigation.js"></script>
   <script src="../../js/Dashboard-profile.js"></script>
</body>
</html>
