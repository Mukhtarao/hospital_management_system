<?php
session_start();
include("db.php");

$message = "";
$status = "";
$token = $_GET['token'] ?? '';

// 1. Verify if the token exists in the database
if (empty($token)) {
    die("Invalid access. No token provided.");
}

$check_token = mysqli_query($conn, "SELECT * FROM users WHERE reset_token='$token' LIMIT 1");
if (mysqli_num_rows($check_token) == 0) {
    die("This reset link is invalid or has already been used.");
}

// 2. Handle Password Update
if (isset($_POST['update_password'])) {
    $new_pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $status = "error";
        $message = "Passwords do not match.";
    } elseif (strlen($new_pass) < 6) {
        $status = "error";
        $message = "Password must be at least 6 characters.";
    } else {
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        
        // Update password and CLEAR the token so it can't be used again
        $update = mysqli_query($conn, "UPDATE users SET password='$hashed_pass', reset_token=NULL WHERE reset_token='$token'");

        if ($update) {
            $status = "success";
            $message = "Password updated successfully. You can now login.";
        } else {
            $status = "error";
            $message = "System error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set New Password · HGH</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root {
  --g900:#052e16; --g800:#14532d; --g600:#16a34a;
  --surface:#ffffff; --border:#e2ece6;
  --ink:#071a0e; --soft:#637a69;
  --fd:'Cormorant Garamond',serif; --fb:'Outfit',sans-serif;
}

body {
    margin: 0; padding: 0; height: 100vh;
    display: flex; align-items: center; justify-content: center;
    background: radial-gradient(circle at center, var(--g800) 0%, var(--g900) 100%);
    font-family: var(--fb);
}

.reset-card {
    width: 100%; max-width: 400px;
    background: var(--surface);
    padding: 40px;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    text-align: center;
}

.brand { font-family: var(--fd); font-size: 32px; font-weight: 700; color: var(--g900); margin-bottom: 8px; }
h2 { font-family: var(--fd); font-size: 28px; color: var(--ink); margin-bottom: 20px; }

.fg { text-align: left; margin-bottom: 15px; }
.fg label { font-size: 10px; text-transform: uppercase; color: var(--soft); font-weight: 600; display: block; margin-bottom: 5px; }

.fi {
    width: 100%; padding: 12px;
    border: 1.5px solid var(--border); border-radius: 12px;
    font-family: var(--fb); font-size: 15px;
    background: #f9fbf9; outline: none; box-sizing: border-box;
}

.fi:focus { border-color: var(--g600); background: #fff; }

.btn-p {
    width: 100%; padding: 14px; margin-top: 10px;
    border-radius: 12px; border: none;
    background: var(--g900); color: white;
    font-weight: 600; cursor: pointer; transition: .2s;
}

.alert { padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; border: 1px solid transparent; }
.alert-success { background: #dcfce7; color: #15803d; border-color: #bbf7d0; }
.alert-error { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }

.login-link { display: inline-block; margin-top: 20px; color: var(--g600); text-decoration: none; font-size: 14px; font-weight: 500; }
</style>
</head>
<body>

<div class="reset-card">
    <div class="brand">HG<span>H</span></div>
    <h2>Update <em>Credentials</em></h2>

    <?php if($message): ?>
        <div class="alert alert-<?= $status ?>"><?= $message ?></div>
    <?php endif; ?>

    <?php if($status !== 'success'): ?>
        <form method="POST">
            <div class="fg">
                <label>New Password</label>
                <input type="password" name="password" class="fi" placeholder="••••••••" required>
            </div>
            <div class="fg">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="fi" placeholder="••••••••" required>
            </div>
            <button type="submit" name="update_password" class="btn-p">Update Password</button>
        </form>
    <?php else: ?>
        <a href="index.php" class="btn-p" style="display:block; text-decoration:none;">Go to Sign In</a>
    <?php endif; ?>
</div>

</body>
</html>