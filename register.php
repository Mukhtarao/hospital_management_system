<?php
session_start();
include("db.php");

$error = "";

if (isset($_POST['register'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? '');
    $username  = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $email     = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $role      = $_POST['role'] ?? '';

    if (empty($full_name) || empty($username) || empty($password) || empty($confirm) || empty($role)) {
        $error = "All fields are required";
    }
    elseif ($password !== $confirm) {
        $error = "Passwords do not match";
    }
    elseif ($role === 'admin') {
        $allowed_domain = "@hargeisagrouphospital.org";
        if (empty($email) || !str_ends_with(strtolower($email), $allowed_domain)) {
            $error = "Admin accounts require an official hospital email";
        }
    }

    if (empty($error)) {
        $check = mysqli_query($conn, "SELECT user_id FROM users WHERE username='$username'");
        if (mysqli_num_rows($check) > 0) {
            $error = "Username is already taken";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = mysqli_query($conn, "INSERT INTO users (full_name, username, password, email, role) VALUES ('$full_name', '$username', '$hashed_password', '$email', '$role')");

            if ($insert) {
                header("Location: index.php?registered=success");
                exit();
            } else {
                $error = "System error: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment | HGH Institutional Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #052e16;
            --emerald: #16a34a;
            --glass: rgba(255, 255, 255, 0.92);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background: linear-gradient(rgba(5, 46, 22, 0.85), rgba(2, 6, 23, 0.95)),
                        url('https://images.unsplash.com/photo-1516549655169-df83a0774514?auto=format&fit=crop&q=80&w=2070');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .register-container {
            width: 580px;
            background: var(--glass);
            backdrop-filter: blur(15px);
            border-radius: 40px;
            padding: 45px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.3);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        header { text-align: center; margin-bottom: 30px; }
        header h2 { font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary); }
        header p { font-size: 14px; color: #64748b; margin-top: 5px; }

        .error-msg {
            background: #fff1f2; color: #e11d48; padding: 12px; border-radius: 12px;
            font-size: 13px; font-weight: 600; margin-bottom: 20px; border-left: 4px solid #e11d48;
            display: flex; align-items: center; gap: 10px;
        }

        form { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width { grid-column: span 2; }

        .input-group { margin-bottom: 5px; }
        .input-group label { display: block; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px; }
        
        .input-box { position: relative; }
        .input-box i { position: absolute; left: 14px; top: 14px; color: var(--emerald); font-size: 14px; }
        
        input, select {
            width: 100%;
            padding: 12px 12px 12px 38px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            font-size: 14px;
            color: var(--primary);
            transition: 0.3s;
        }

        input:focus, select:focus {
            outline: none; border-color: var(--emerald);
            background: white; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1);
        }

        .submit-btn {
            grid-column: span 2;
            padding: 16px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 15px;
        }

        .submit-btn:hover {
            background: var(--emerald);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(22, 163, 74, 0.2);
        }

        .footer { grid-column: span 2; text-align: center; margin-top: 20px; font-size: 13px; color: #64748b; }
        .footer a { color: var(--emerald); text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>

<div class="register-container">
    <header>
        <h2>Staff Enrollment</h2>
        <p>Request access to the Hospital Group network</p>
    </header>

    <?php if (!empty($error)): ?>
        <div class="error-msg">
            <i class="fa-solid fa-circle-exclamation"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group full-width">
            <label>Legal Full Name</label>
            <div class="input-box">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" name="full_name" placeholder="Dr. Jane Doe" required>
            </div>
        </div>

        <div class="input-group">
            <label>Username</label>
            <div class="input-box">
                <i class="fa-solid fa-user-tag"></i>
                <input type="text" name="username" placeholder="jdoe_hgh" required>
            </div>
        </div>

        <div class="input-group">
            <label>Official Email</label>
            <div class="input-box">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="name@hargeisagrouphospital.org">
            </div>
        </div>

        <div class="input-group">
            <label>Security Password</label>
            <div class="input-box">
                <i class="fa-solid fa-key"></i>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
        </div>

        <div class="input-group">
            <label>Repeat Password</label>
            <div class="input-box">
                <i class="fa-solid fa-shield-check"></i>
                <input type="password" name="confirm_password" placeholder="••••••••" required>
            </div>
        </div>

        <div class="input-group full-width">
            <label>Institutional Role</label>
            <div class="input-box">
                <i class="fa-solid fa-briefcase-medical"></i>
                <select name="role" required>
                    <option value="">Select your department...</option>
                    <option value="admin">Institutional Admin</option>
                    <option value="doctor">Medical Practitioner</option>
                    <option value="nurse">Clinical Nursing</option>
                    <option value="receptionist">Operations / Reception</option>
                    <option value="lab">Pathology & Lab</option>
                    <option value="pharmacy">Pharmaceutical Staff</option>
                    <option value="patient">Patient Portal</option>
                </select>
            </div>
        </div>

        <button type="submit" name="register" class="submit-btn">
            Submit Registration Request
        </button>

        <div class="footer">
            Already have an authorized account? <a href="index.php">Sign In Here</a>
        </div>
    </form>
</div>

</body>
</html>