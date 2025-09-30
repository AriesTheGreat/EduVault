<?php
session_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Settings</title>

  <!-- CSS -->
  <link rel="stylesheet" href="../../css/Admin-settings.css">
  <link rel="stylesheet" href="../../css/header&sideBar.css">
  <link rel="stylesheet" href="../../css/Dashboard-profile.css">

  <!-- Google Icons & FontAwesome -->
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      <li><a href="#"><span class="material-icons">settings</span><span class="text">Settings</span></a></li>
    </ul>
  </div>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <h2>Admin Settings</h2>

    <div class="settings-container">
      <!-- Profile Settings -->
      <div class="settings-card">
        <h3>Profile Settings</h3>

        <!-- Name -->
        <div class="setting-item">
          <label>Name:</label>
          <input type="text" id="profileNameInput" placeholder="Admin Name">
        </div>

        <!-- Email -->
        <div class="setting-item">
          <label>Email:</label>
          <input type="email" id="profileEmailInput" placeholder="admin@example.com">
        </div>

        <!-- Optional new password -->
        <div class="setting-item">
          <label>New Password:</label>
          <input type="password" id="profilePasswordInput" placeholder="********">
        </div>

        <!-- Current password required -->
        <div class="setting-item">
          <label>Current Password:</label>
          <input type="password" id="currentPasswordInput" placeholder="Enter current password">
        </div>

        <!-- Save Button -->
        <button class="save-btn" id="saveProfileSettingsBtn">Save Changes</button>
      </div>

      <!-- System Settings -->
      <div class="settings-card">
        <h3>System Settings</h3>
        <div class="setting-item">
          <label>Theme:</label>
          <select id="themeSelect">
            <option>Dark</option>
            <option>Light</option>
          </select>
        </div>
        <div class="setting-item">
          <label>Notifications:</label>
          <input type="checkbox" id="notificationsToggle" checked>
        </div>
        <button class="save-btn" id="saveSystemSettingsBtn">Save Changes</button>
      </div>

      <!-- Security Settings -->
      <div class="settings-card">
        <h3>Security Settings</h3>
        <div class="setting-item">
          <label>Two-Factor Authentication:</label>
          <input type="checkbox" id="twoFactorToggle">
        </div>
        <div class="setting-item">
          <label>Reset Password:</label>
          <button class="reset-btn" id="resetPasswordBtn">Reset</button>
        </div>
      </div>
    </div>
  </main>

  <!-- JS -->
  <script src="../../js/navigation.js"></script>
  <script src="../../js/Dashboard-profile.js"></script>
  <script src="../../js/Admin-settings.js"></script>
</body>
</html>