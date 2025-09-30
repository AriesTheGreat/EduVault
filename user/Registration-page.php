<?php
session_start();

// Include database connection
require_once '../db.php';

// Check if database connection exists
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please try again later.");
}

// Handle registration form submission
// Pre-fill form if coming from Google
$google_data = $_SESSION['google_data'] ?? null;
if ((isset($_GET['google']) || (isset($_GET['source']) && $_GET['source'] === 'google')) && $google_data) {
    list($firstName, $lastName) = explode(' ', $google_data['name'] . ' ', 2);
    $email = $google_data['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Get user input
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $department = $_POST['department'] ?? '';
    $password = $_POST['password'] ?? '';
    $userId = trim($_POST['userId'] ?? '');

    // Validate userId format
    if (!preg_match('/^[0-9]{5,10}$/', $userId)) {
        echo '<script>alert("Please enter a valid User ID (5-10 digits)."); window.history.back();</script>';
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo '<script>alert("Please enter a valid email address."); window.history.back();</script>';
        exit;
    }

    // Check email domain - allow all parsu.edu.ph emails to register as any role
    $email_domain = substr(strrchr($email, "@"), 1);

    // Additional domain validation
    if ($email_domain !== 'parsu.edu.ph') {
        echo '<script>alert("Please use your ParSU email address (@parsu.edu.ph)"); window.history.back();</script>';
        exit;
    }

    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $check_stmt->bind_param('s', $email);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        echo '<script>alert("This email is already registered. Please use a different email or login."); window.history.back();</script>';
        exit;
    }
    $check_stmt->close();

    // Check if userId already exists
    $check_userid_stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $check_userid_stmt->bind_param('i', $userId);
    $check_userid_stmt->execute();
    $check_userid_stmt->store_result();

    if ($check_userid_stmt->num_rows > 0) {
        echo '<script>alert("This User ID is already registered. Please use a different ID."); window.history.back();</script>';
        exit;
    }
    $check_userid_stmt->close();

    // Create user directly with verified status
    $name = $firstName . ' ' . $lastName;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $email_verified = 1; // Auto verify the email

    // Ensure role is properly capitalized for consistency
    $role = ucfirst(strtolower($role));

    try {
        // Start transaction
        $conn->begin_transaction();

        // Insert the user into database
        $stmt = $conn->prepare("INSERT INTO users (user_id, name, email, password, role, email_verified, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssis', $userId, $name, $email, $hashedPassword, $role, $email_verified, $department);

        if ($stmt->execute()) {
            // Commit transaction
            $conn->commit();
            
            // Set success message and redirect
            echo '<script>
                alert("Registration successful! Please login to continue.");
                window.location.href = "login.php";
            </script>';
            exit;
        } else {
            // Rollback on error
            $conn->rollback();
            throw new Exception("Failed to insert user data");
        }
    } catch (Exception $e) {
        // Rollback on any error
        $conn->rollback();
        echo '<script>alert("Registration failed: ' . $e->getMessage() . '"); window.history.back();</script>';
    } finally {
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registration</title>
  <link rel="stylesheet" href="../css/Registration-page.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
  <div class="register-container">

    <!-- Logo -->
    <img src="../img/partido-state-university-logo.png" alt="Logo" class="logo">

    <h2 class="register-title">Register</h2>

    <!-- Registration Form -->
    <form class="register-form" method="post" action="">
      <!-- Role + Department -->
      <div class="role-dept-row">
        <select name="role" id="role" required onchange="toggleIdFields()">
          <option value="" disabled selected>Select Role</option>
          <option value="Student">Student</option>
          <option value="Instructor">Instructor</option>
          <!-- <option value="Dean">Dean</option> -->
        </select>

        <select name="department" id="department" required>
          <option value="" disabled selected>Select Department</option>
          <option value="Computer Science">Computer Science</option>
          <option value="CBM">CBM</option>
          <option value="Engineering">Engineering</option>
          <option value="Education">Education</option>
        </select>
      </div>

      <!-- Name -->
      <div class="name-row">
        <input type="text" name="firstName" id="firstName" placeholder="First Name" required>
        <input type="text" name="lastName" id="lastName" placeholder="Last Name" required>
      </div>

      <!-- User ID field -->
      <div class="user-id-field">
        <input type="text" name="userId" id="userId" placeholder="User ID" required pattern="[0-9]+" minlength="5" maxlength="10" title="Please enter a valid PSU ID number (5-10 digits)">
      </div>

      <!-- Email -->
      <input type="email" name="email" id="email" placeholder="Enter your Gmail account(@parsu.edu.ph)" required>
      <!-- Password -->
      <input type="password" name="password" id="password" placeholder="Password" required>

      <input type="password" id="confirmPassword" placeholder="Confirm password:" required>

      <!-- Info text -->
      <p class="info-text">
        By clicking Register, you agree to our Terms, Privacy Policy and Cookies Policy.
      </p>

      <!-- Submit -->
      <button type="submit" name="register" class="btn">Register</button>
    </form>

    <p class="login-text">
      Already have an account? <a href="login.php" class="login-link">Log in</a>
    </p>
  </div>

  <!-- JS -->
  <script>
    function validateEmail(email) {
        return email.toLowerCase().endsWith('@parsu.edu.ph');
    }

    // Role change event - no restrictions for parsu.edu.ph emails
    document.getElementById('role').addEventListener('change', function() {
        // No restrictions needed
    });

    document.getElementById('email').addEventListener('input', function() {
        const email = this.value.toLowerCase();

        if (!validateEmail(email)) {
            this.setCustomValidity('Please use your ParSU email address (@parsu.edu.ph)');
        } else {
            this.setCustomValidity('');
        }
    });

    document.querySelector('.register-form').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.toLowerCase();

        if (!validateEmail(email)) {
            e.preventDefault();
            alert('Please use your ParSU email address (@parsu.edu.ph)');
            return false;
        }
    });
  </script>
</body>
</html>