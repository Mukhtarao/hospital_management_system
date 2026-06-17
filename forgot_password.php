<?php
session_start();
include 'db.php';

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";
$status = "";

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $status = "error";
        $message = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, email FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
            $update->bind_param("sss", $token, $expires, $email);
            $update->execute();

            $reset_link = "https://web-production-2d187.up.railway.app/reset_password.php?token=" . urlencode($token);

            $mail = new PHPMailer(true);

            try {
                $smtpEmail = getenv('SMTP_EMAIL');
                $smtpPassword = getenv('SMTP_PASSWORD');

                if (empty($smtpEmail) || empty($smtpPassword)) {
                    throw new Exception("Railway SMTP variables not found.");
                }

                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $smtpEmail;
                $mail->Password = $smtpPassword;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                // Change to 2 only for testing. Keep 0 for normal use.
                $mail->SMTPDebug = 0;
                $mail->Debugoutput = 'html';

                $mail->setFrom($smtpEmail, 'HGH Medical Portal');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; color: #052e16;'>
                        <h2>HGH Medical Portal</h2>
                        <p>You requested a password reset.</p>
                        <p>
                            <a href='{$reset_link}'
                               style='background:#15803d;color:#fff;padding:12px 20px;
                               text-decoration:none;border-radius:5px;display:inline-block;'>
                               Reset Password
                            </a>
                        </p>
                        <p>This link expires in 1 hour.</p>
                        <p>If the button does not work, copy and paste this link:</p>
                        <p>{$reset_link}</p>
                    </div>
                ";

                $mail->AltBody = "Reset your password using this link: {$reset_link}";

                $mail->send();

                $status = "success";
                $message = "Recovery link sent. Please check your inbox and spam folder.";
            } catch (Exception $e) {
                $status = "error";
                $message = "Mailer Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage();
            }
        } else {
            $status = "error";
            $message = "No account found with that email address.";
        }
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

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($status) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($status !== 'success'): ?>
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
