<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit();
}

// Handle profile settings update (name, email, password)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_changes'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $current_password = $_POST['current_password'];

    // Validate required fields
    if (empty($name) || empty($email) || empty($current_password)) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=missing_fields');
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=invalid_email');
        exit();
    }

    // Get current user data
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    $stmt->close();

    // Verify current password
    if (!password_verify($current_password, $hashed_password)) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=incorrect_password');
        exit();
    }

    // Hash new password if provided
    $update_password = false;
    if (!empty($new_password)) {
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_password = true;
    }

    // Update user profile
    if ($update_password) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE user_id = ?");
        $stmt->bind_param("sssi", $name, $email, $new_hashed_password, $_SESSION['user_id']);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $name, $email, $_SESSION['user_id']);
    }

    if ($stmt->execute()) {
        // Update session data
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;

        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?success=profile_updated');
        exit();
    } else {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=update_failed');
        exit();
    }
}

// Handle profile picture upload
if (isset($_FILES['profile_picture'])) {
    $target_dir = "../uploads/profiles/";

    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
    $new_filename = $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    // Check if image file is a actual image
    if(getimagesize($_FILES["profile_picture"]["tmp_name"]) !== false) {
        // Check file size (5MB max)
        if ($_FILES["profile_picture"]["size"] <= 5000000) {
            // Allow certain file formats
            if(in_array($file_extension, ["jpg", "jpeg", "png", "gif"])) {
                if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                    // Update database
                    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $new_filename, $_SESSION['user_id']);

                    if ($stmt->execute()) {
                        $_SESSION['profile_picture'] = $new_filename;
                        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?success=picture_updated');
                        exit();
                    }
                }
            }
        }
    }
}

// If something went wrong
header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=profile_update_failed');
exit();
