<?php
session_start();
include 'db.php'; // Using 'db.php' to match your previous files

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";
$status = "";

if(isset($_POST['submit'])){
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' LIMIT 1");

    if(mysqli_num_rows($check) > 0){
        $token = md5(rand());
        mysqli_query($conn, "UPDATE users SET reset_token='$token' WHERE email='$email'");

        // Update this URL when you move to a live server
        $reset_link = "http://localhost/hospital_management_system/reset_password.php?token=$token";

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'mukhtaraden6171@gmail.com'; // Your Gmail
            $mail->Password   = 'obst irsx nypg xoia';  // Your Google App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('mukhtaraden6171@gmail.com', 'HGH Medical Portal');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body    = "
                <div style='font-family: sans-serif; color: #052e16;'>
                    <h3>HGH Medical Portal</h3>
                    <p>You requested a password reset. Click the link below to continue:</p>
                    <a href='$reset_link' style='background: #15803d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a>
                    <br><br>
                    <p>If you did not request this, please ignore this email.</p>
                </div>";

            $mail->send();
            $status = "success";
            $message = "Recovery link sent. Please check your inbox.";
        } catch (Exception $e) {
            $status = "error";
            $message = "Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $status = "error";
        $message = "No account found with that email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Access · HGH</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=DM+Mono&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root {
  --g900:#052e16; --g800:#14532d; --g700:#15803d; --g600:#16a34a; --g400:#4ade80;
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
    width: 100%; max-width: 420px;
    background: var(--surface);
    padding: 40px;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    text-align: center;
}

.brand { font-family: var(--fd); font-size: 32px; font-weight: 700; color: var(--g900); margin-bottom: 8px; }
.brand span { color: var(--g600); }

h2 { font-family: var(--fd); font-size: 28px; margin-bottom: 12px; color: var(--ink); }
p { color: var(--soft); font-size: 14px; line-height: 1.6; margin-bottom: 30px; }

.fi {
    width: 100%; padding: 12px 16px;
    border: 1.5px solid var(--border); border-radius: 12px;
    font-family: var(--fb); font-size: 15px;
    background: #f9fbf9; outline: none; transition: .2s;
    box-sizing: border-box;
}

.fi:focus { border-color: var(--g600); background: #fff; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1); }

.btn-p {
    width: 100%; padding: 14px; margin-top: 20px;
    border-radius: 12px; border: none;
    background: var(--g900); color: white;
    font-weight: 600; cursor: pointer; transition: .2s;
}

.btn-p:hover { transform: translateY(-2px); background: #000; }

.alert { padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; }
.alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

.back-link {
    display: inline-block; margin-top: 25px;
    font-size: 13px; color: var(--g700);
    text-decoration: none; font-weight: 500;
}
</style>
</head>
<body>

<div class="reset-card">
    <div class="brand">HG<span>H</span></div>
    <h2>Account <em>Recovery</em></h2>
    <p>Please enter your email address. We will send you a secure link to reset your credentials.</p>

    <?php if($message): ?>
        <div class="alert alert-<?= $status ?>"><?= $message ?></div>
    <?php endif; ?>

    <?php if($status !== 'success'): ?>
        <form method="POST">
            <div style="text-align: left; margin-bottom: 5px;">
                <label style="font-size: 10px; text-transform: uppercase; color: var(--soft); font-weight: 600; letter-spacing: 1px;">Institutional Email</label>
            </div>
            <input type="email" name="email" class="fi" placeholder="name@example.com" required autofocus>
            <button type="submit" name="submit" class="btn-p">Send Reset Link</button>
        </form>
    <?php endif; ?>

    <a href="index.php" class="back-link">← Return to Login</a>
</div>

</body>
</html>