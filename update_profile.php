<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    
    $username = mysqli_real_escape_string($conn, $_POST['name']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Update Text Fields
    $sql = "UPDATE users SET username = '$username', email = '$email' WHERE id = '$user_id'";
    mysqli_query($conn, $sql);

    // Update Password if provided
    if (!empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE id = '$user_id'");
    }

    // Update Photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $dir = 'uploads/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        
        $fileName = time() . "_" . $_FILES['photo']['name'];
        if(move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $fileName)) {
            mysqli_query($conn, "UPDATE users SET profile_photo = '$fileName' WHERE id = '$user_id'");
            $_SESSION['profile_photo'] = $fileName;
        }
    }

    // Sync Session
    $_SESSION['username'] = $username;
    $_SESSION['email']    = $email;

    // Send success response (AJAX expects this)
    echo "Success";
}
?>