<?php
require_once '../db.php';
session_start();

// Handle login form submission
if (isset($_POST['login'])) {
  if (empty($_POST['username']) || empty($_POST['password'])) {
    echo '<script>alert("Both email and password are required.");</script>';
    exit;
  }

  $email = trim($_POST['username']);
  $password = $_POST['password'];

  // Validate email format
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '<script>alert("Please enter a valid email address.");</script>';
    exit;
  }

  $stmt = $conn->prepare("SELECT user_id, name, password, role, email_verified FROM users WHERE email = ?");
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) {
    $stmt->bind_result($user_id, $name, $hashedPassword, $role, $email_verified);
    $stmt->fetch();

    // Check if email is verified
    if ($email_verified != 1) {
      echo '<script>alert("Please verify your email before logging in.");</script>';
      exit;
    }

    if (password_verify($password, $hashedPassword)) {
      $_SESSION['user_id'] = $user_id;
      $_SESSION['name'] = $name;
      $_SESSION['role'] = $role;
      $_SESSION['email'] = $email;
      $_SESSION['logged_in'] = true;

      // Redirect based on role
      $role = ucfirst(strtolower($role)); // Normalize role case
      if ($role === 'Student') {
        header('Location: ../dashboard/student/Student-dashboard.php');
        exit();
      } elseif ($role === 'Instructor') {
        header('Location: ../dashboard/instructor/Instructor-dashboard.php');
        exit();
      } elseif ($role === 'Dean') {
        header('Location: ../dashboard/admin/Admin-dashboard.php');
        exit();
      } else {
        // Default fallback
        header('Location: ../dashboard/student/Student-dashboard.php');
        exit();
      }
    } else {
      echo '<script>alert("Invalid password.");</script>';
    }
  } else {
    echo '<script>alert("No account found for this email.");</script>';
  }
  $stmt->close();
}

// Handle forgot password
if (isset($_POST['forgotPassword'])) {
    // TODO: Implement forgot password functionality
    echo '<script>alert("Forgot password functionality will be implemented soon.");</script>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduVault | Login Page</title>

<!-- CSS -->
<link rel="stylesheet" href="../css/Login-page.css">
</head>
<body>

<!-- Back Button -->
<a href="../index.php" class="back-btn"><</a>

<!-- Login Container -->
<div class="login-container">
  <!-- Logo -->
  <img src="../img/partido-state-university-logo.png" alt="Logo" class="login-logo">

  <!-- University Title -->
  <h1 class="psu-title">Partido State University</h1>

  <!-- Animated Login Text -->
  <p class="admin-sub wave">
    <span>L</span><span>o</span><span>g</span><span>i</span><span>n</span>
  </p>

  <!-- Login Form -->
  <form class="login-form" method="post" onsubmit="return validateForm()">
    <input type="text" id="username" name="username" placeholder="Email" required>
    <input type="password" id="password" name="password" placeholder="Password" required>

    <!-- Remember Me -->
    <div class="remember-me">
      <input type="checkbox" id="remember" name="remember">
      <label for="remember">Remember Me</label>
    </div>

    <button type="submit" name="login" class="btn">Sign In</button>
  </form>

  <!-- Google Sign-In -->
  <?php
    require_once '../config/google_config.php';
    $google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
      'client_id' => GOOGLE_CLIENT_ID,
      'redirect_uri' => GOOGLE_REDIRECT_URI,
      'response_type' => 'code',
      'scope' => 'email profile',
      'access_type' => 'online',
      'prompt' => 'select_account'
    ]);
  ?>
  <a href="https://accounts.google.com/o/oauth2/v2/auth?client_id=18457313587-e2iq0jtc0qr7kousep09aj60kugk2lfe.apps.googleusercontent.com&redirect_uri=http://localhost:3000/auth/google/callback&response_type=code&scope=email%20profile&access_type=online" class="google-btn">
    <img src="../img/google.png" alt="Google Logo" style="vertical-align:middle;margin-right:8px;">
    Sign in with Google
  </a>

  <!-- Register Redirect -->
  <p class="signup-text">
    Don't have an account? 
    <a href="Registration-page.php" class="signup-link">Register</a>
  </p>

</div>

<!-- JS -->
<script>
function validateForm() {
  const email = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;

  if (!email || !password) {
    alert('Both email and password are required.');
    return false;
  }

  // Email format validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email)) {
    alert('Please enter a valid email address.');
    return false;
  }

  return true;
}
</script>
</body>
</html>
