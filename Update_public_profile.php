<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: index.php"); exit();
}

$doctor_id     = (int)$_SESSION['user_id'];
$specialization = mysqli_real_escape_string($conn, trim($_POST['specialization'] ?? ''));
$department     = mysqli_real_escape_string($conn, trim($_POST['department']     ?? ''));
$availability   = mysqli_real_escape_string($conn, trim($_POST['availability']   ?? ''));
$phone          = mysqli_real_escape_string($conn, trim($_POST['phone']          ?? ''));
$email          = mysqli_real_escape_string($conn, trim($_POST['email']          ?? ''));
$full_name      = mysqli_real_escape_string($conn, trim($_SESSION['username']    ?? ''));

// Handle profile photo upload
$photo_sql = '';
if (!empty($_FILES['photo']['name'])) {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext        = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allowed    = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size   = 5 * 1024 * 1024; // 5MB

    if (!in_array($ext, $allowed)) {
        header("Location: doctor_dashboard.php?page=home&error=invalid_photo_type"); exit();
    }
    if ($_FILES['photo']['size'] > $max_size) {
        header("Location: doctor_dashboard.php?page=home&error=photo_too_large"); exit();
    }

    $filename   = 'doctor_' . $doctor_id . '_' . time() . '.' . $ext;
    $dest       = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        $photo_sql = ", profile_photo = '$dest'";
        $_SESSION['profile_photo'] = $filename;
    }
}

// Check if doctor row already exists for this user
$exists = mysqli_query($conn, "SELECT doctor_id FROM doctors WHERE user_id = $doctor_id LIMIT 1");

if ($exists && mysqli_num_rows($exists) > 0) {
    // UPDATE existing row
    $sql = "UPDATE doctors SET
                specialization = '$specialization',
                department     = '$department',
                availability   = '$availability',
                phone          = '$phone',
                email          = '$email'
                $photo_sql
            WHERE user_id = $doctor_id";
} else {
    // INSERT new row
    $photo_val = '';
    if (!empty($_FILES['photo']['name']) && $photo_sql) {
        $photo_val = ", '$dest'";
        $photo_col = ", profile_photo";
    } else {
        $photo_val = '';
        $photo_col = '';
    }
    $sql = "INSERT INTO doctors (user_id, full_name, specialization, department, availability, phone, email $photo_col)
            VALUES ($doctor_id, '$full_name', '$specialization', '$department', '$availability', '$phone', '$email' $photo_val)";
}

if (mysqli_query($conn, $sql)) {
    header("Location: doctor_dashboard.php?page=home&saved=public");
} else {
    // Log error and redirect with error flag
    error_log("update_public_profile error: " . mysqli_error($conn));
    header("Location: doctor_dashboard.php?page=home&error=db");
}
exit();