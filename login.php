<?php

session_start();
include("db.php");

$error   = "";
$success = "";

function h(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// ── Login Handler ────────────────────────────────────────────
if (isset($_POST['login_action'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role     = strtolower(mysqli_real_escape_string($conn, $_POST['role'] ?? 'patient'));

    if ($username && $password) {
        $q = mysqli_query($conn, "SELECT * FROM users WHERE (username='$username' OR email='$username') AND LOWER(role)='$role' AND status='Active' LIMIT 1");
        if ($q && mysqli_num_rows($q) === 1) {
            $user = mysqli_fetch_assoc($q);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = strtolower($user['role']);
                $map = [
                    'admin'        => 'admin_dashboard.php',
                    'doctor'       => 'doctor_dashboard.php',
                    'nurse'        => 'nurse_dashboard.php',
                    'receptionist' => 'receptionist_dashboard.php',
                    'lab'          => 'lab_dashboard.php',
                    'patient'      => 'patient_dashboard.php',
                    'pharmacy'     => 'pharmacy_dashboard.php',
                ];
                $redirect_to = $map[strtolower($user['role'])] ?? 'index.php?error=no_map';
                header("Location: " . $redirect_to);
                exit();
            } else {
                $error = "Invalid credentials.";
            }
        } else {
            $error = "No account found for this department.";
        }
    } else {
        $error = "All fields are required.";
    }
}

// ── Patient Register Handler ──────────────────────────────────
if (isset($_POST['patient_register'])) {
    $email    = mysqli_real_escape_string($conn, trim($_POST['reg_email'] ?? ''));
    $pass_raw = $_POST['reg_password'] ?? '';
    $allowed_domains = ['gmail.com', 'hotmail.com', 'hargeisagrouphospital.org', 'yahoo.com'];
    $parts  = explode('@', $email);
    $domain = strtolower(array_pop($parts));
    $password_regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";

    if (!in_array($domain, $allowed_domains)) {
        $error = "Authorized domains: Gmail, Hotmail, Yahoo, or HGH Hospital only.";
    } elseif (!preg_match($password_regex, $pass_raw)) {
        $error = "Password must be 8+ chars with uppercase, lowercase, number, and symbol.";
    } else {
        $first   = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
        $last    = mysqli_real_escape_string($conn, trim($_POST['last_name'] ?? ''));
        $uname   = mysqli_real_escape_string($conn, trim($_POST['reg_username'] ?? ''));
        $pass    = password_hash($pass_raw, PASSWORD_DEFAULT);
        $dob     = mysqli_real_escape_string($conn, $_POST['dob'] ?? '');
        $gender  = mysqli_real_escape_string($conn, $_POST['gender'] ?? 'Other');
        $blood   = mysqli_real_escape_string($conn, $_POST['blood_group'] ?? 'N/A');
        $phone   = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
        $addr    = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
        $e_name  = mysqli_real_escape_string($conn, trim($_POST['emergency_name'] ?? ''));
        $e_phone = mysqli_real_escape_string($conn, trim($_POST['emergency_phone'] ?? ''));

        // Check duplicate username/email
        $chk = mysqli_query($conn, "SELECT user_id FROM users WHERE username='$uname' OR email='$email' LIMIT 1");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $error = "Username or email already exists. Please try different ones.";
        } else {
            mysqli_begin_transaction($conn);
            try {
                mysqli_query($conn, "INSERT INTO users (full_name,username,email,password,role,status) VALUES ('$first $last','$uname','$email','$pass','patient','Active')");
                $uid = mysqli_insert_id($conn);
                $age = 0;
                if ($dob && $dob !== '0000-00-00') {
                    $age = date_diff(date_create($dob), date_create('today'))->y;
                }
                mysqli_query($conn, "INSERT INTO patients (first_name,last_name,full_name,date_of_birth,age,gender,blood_group,phone,email,address,emergency_contact_name,emergency_contact_phone,user_id)
                                     VALUES ('$first','$last','$first $last','$dob','$age','$gender','$blood','$phone','$email','$addr','$e_name','$e_phone','$uid')");
                mysqli_commit($conn);
                $success = "Account created successfully! You can now sign in.";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Registration failed. Please try again.";
            }
        }
    }
}

// ── Staff Register Handler ────────────────────────────────────
if (isset($_POST['staff_register'])) {
    $reg_id   = mysqli_real_escape_string($conn, trim($_POST['staff_reg_id'] ?? ''));
    $email    = mysqli_real_escape_string($conn, trim($_POST['staff_email'] ?? ''));
    $pass_raw = $_POST['staff_password'] ?? '';
    $first    = mysqli_real_escape_string($conn, trim($_POST['staff_first_name'] ?? ''));
    $last     = mysqli_real_escape_string($conn, trim($_POST['staff_last_name'] ?? ''));
    $uname    = mysqli_real_escape_string($conn, trim($_POST['staff_username'] ?? ''));
    $role_raw = $_POST['staff_role'] ?? '';
    $role     = mysqli_real_escape_string($conn, strtolower($role_raw));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['staff_phone'] ?? ''));

    $password_regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";

    if (!$reg_id || !$email || !$pass_raw || !$first || !$last || !$uname || !$role) {
        $error = "All required fields must be filled.";
    } elseif (!preg_match($password_regex, $pass_raw)) {
        $error = "Password must be 8+ chars with uppercase, lowercase, number, and symbol.";
    } else {
        // Find the unclaimed slot in users table matching registration_id + role
        $chk_reg = mysqli_query($conn, "SELECT user_id FROM users 
                                        WHERE registration_id='$reg_id' 
                                        AND LOWER(role)='$role' 
                                        AND status='Pending' 
                                        AND username IS NULL
                                        LIMIT 1");
        if (!$chk_reg || mysqli_num_rows($chk_reg) === 0) {
            $error = "Invalid or already used Registration ID for the selected department. Please contact administration.";
        } else {
            $slot = mysqli_fetch_assoc($chk_reg);
            $slot_id = (int)$slot['user_id'];

            // Check duplicate username/email (exclude this slot row)
            $chk_dup = mysqli_query($conn, "SELECT user_id FROM users 
                                            WHERE (username='$uname' OR email='$email') 
                                            AND user_id != $slot_id 
                                            LIMIT 1");
            if ($chk_dup && mysqli_num_rows($chk_dup) > 0) {
                $error = "Username or email already exists. Please choose different ones.";
            } else {
                $pass = password_hash($pass_raw, PASSWORD_DEFAULT);
                mysqli_begin_transaction($conn);
                try {
                    // UPDATE the existing slot row — claim it
                    mysqli_query($conn, "UPDATE users 
                                         SET full_name='$first $last',
                                             username='$uname',
                                             email='$email',
                                             password='$pass',
                                             status='Active'
                                         WHERE user_id=$slot_id");

                    // If doctor, insert into doctors table
                    if ($role === 'doctor') {
                        mysqli_query($conn, "INSERT INTO doctors (user_id, full_name, email, phone) 
                                             VALUES ($slot_id, '$first $last', '$email', '$phone')");
                    }

                    mysqli_commit($conn);
                    $success = "Staff account created successfully! You can now sign in.";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}

// ── Forgot Password Handler ───────────────────────────────────
if (isset($_POST['forgot_action'])) {
    $f_email = mysqli_real_escape_string($conn, trim($_POST['forgot_email'] ?? ''));
    $f_role  = strtolower(mysqli_real_escape_string($conn, $_POST['forgot_role'] ?? ''));

    $query = "SELECT user_id FROM users WHERE email='$f_email'" . ($f_role ? " AND LOWER(role)='$f_role'" : "") . " LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) === 1) {
        $user_row = mysqli_fetch_assoc($result);
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        mysqli_query($conn, "UPDATE users SET reset_token='$token' WHERE user_id=" . $user_row['user_id']);
    }
    // Always show success to prevent email enumeration
    $success = "FORGOT_SENT";
}

$active_side = (isset($_POST['role']) && $_POST['role'] !== 'patient') ? 'staff' : 'patient';
if (isset($_POST['staff_register']) || isset($_POST['staff_username'])) $active_side = 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HGH · Medical Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,500;1,600&family=DM+Mono:wght@400;500&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --g900:#052e16; --g800:#14532d; --g700:#15803d; --g600:#16a34a; --g500:#22c55e; --g400:#4ade80; --g100:#dcfce7;
            --surface:#ffffff; --surface-2:#f7faf9; --border:#e2ece6;
            --ink:#071a0e; --soft:#637a69; --faint:#aabfb0;
            --fd:'Cormorant Garamond',serif; --fm:'DM Mono',monospace; --fb:'Outfit',sans-serif;
            --ease:.85s cubic-bezier(.77,0,.175,1);
        }
        *,*::after,*::before{box-sizing:border-box;margin:0;padding:0;}
        body{min-height:100vh;font-family:var(--fb);background:var(--g900);display:flex;align-items:center;justify-content:center;overflow:hidden;}

        .scene{position:fixed;inset:0;background:radial-gradient(ellipse at 25% 55%,rgba(22,163,74,.18) 0%,transparent 55%),var(--g900);z-index:0;}
        .scene::after{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(74,222,128,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(74,222,128,.03) 1px,transparent 1px);background-size:40px 40px;}

        .portal{position:relative;z-index:10;width:100%;max-width:1160px;height:min(900px,96vh);background:white;border-radius:28px;display:flex;overflow:hidden;box-shadow:0 40px 120px rgba(0,0,0,.65);border:1px solid rgba(74,222,128,.1);}

        /* GLIDER */
        .glider{position:absolute;top:0;bottom:0;width:50%;z-index:30;background:linear-gradient(155deg,var(--g800) 0%,var(--g900) 65%,#020c05 100%);display:flex;flex-direction:column;justify-content:space-between;padding:52px 46px;transition:left var(--ease);left:0;overflow:hidden;}
        .portal.staff-active .glider{left:50%;}
        .glider::before{content:'';position:absolute;width:320px;height:320px;border-radius:50%;border:1px solid rgba(74,222,128,.07);top:-80px;right:-80px;}
        .glider::after{content:'';position:absolute;width:200px;height:200px;border-radius:50%;border:1px solid rgba(74,222,128,.05);bottom:60px;left:-60px;}
        .g-wm-text{font-family:var(--fd);font-size:66px;font-weight:700;color:#fff;line-height:1;}
        .g-wm-accent{color:var(--g400);}
        .g-tagline{font-family:var(--fd);font-size:26px;font-style:italic;color:rgba(255,255,255,.88);margin-bottom:12px;line-height:1.3;}
        .g-desc{font-size:13px;color:rgba(255,255,255,.42);line-height:1.8;max-width:260px;}
        .g-cta{margin-top:22px;display:inline-flex;align-items:center;gap:9px;padding:11px 22px;border-radius:11px;border:1.5px solid rgba(74,222,128,.25);background:rgba(74,222,128,.06);color:var(--g400);cursor:pointer;font-weight:600;font-size:13px;transition:.2s;font-family:var(--fb);}
        .g-cta:hover{background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.45);}
        .g-status{font-family:var(--fm);font-size:11px;color:var(--g400);display:flex;align-items:center;gap:6px;}
        .dot{width:6px;height:6px;border-radius:50%;background:var(--g400);animation:pulse 2s infinite;}
        @keyframes pulse{0%,100%{opacity:1;}50%{opacity:.3;}}

        /* HALVES */
        .half{flex:1;display:flex;flex-direction:column;position:relative;}
        .half__scroll{flex:1;overflow-y:auto;padding:44px 44px;background:var(--surface);scrollbar-width:thin;scrollbar-color:var(--border) transparent;}
        .half__scroll::-webkit-scrollbar{width:4px;}
        .half__scroll::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px;}
        .half-sep{width:1px;background:linear-gradient(to bottom,transparent,var(--border) 20%,var(--border) 80%,transparent);}

        /* TYPOGRAPHY */
        .f-title{font-family:var(--fd);font-size:38px;font-weight:700;color:var(--ink);margin-bottom:4px;line-height:1.1;}
        .f-title em{font-style:italic;color:var(--g700);}
        .f-sub{font-size:13px;color:var(--soft);margin-bottom:22px;}

        /* TABS */
        .sub-tabs{display:flex;gap:4px;background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:4px;margin-bottom:22px;}
        .sub-tab{flex:1;padding:9px;border-radius:9px;border:none;background:transparent;font-size:13px;font-weight:500;color:var(--soft);cursor:pointer;transition:.18s;font-family:var(--fb);}
        .sub-tab.active{background:white;color:var(--ink);box-shadow:0 2px 8px rgba(0,0,0,.08);font-weight:600;}

        /* FORM */
        .fg{margin-bottom:14px;}
        .fg label{display:block;font-family:var(--fm);font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--soft);margin-bottom:5px;}
        .fg-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;}
        .fi{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:10px;font-family:var(--fb);font-size:14px;background:var(--surface-2);outline:none;transition:.2s;color:var(--ink);}
        .fi:focus{border-color:var(--g600);background:white;box-shadow:0 0 0 3px rgba(22,163,74,.1);}
        .fi::placeholder{color:var(--faint);}
        select.fi{cursor:pointer;}

        .pw-wrap{position:relative;}
        .pw-wrap .fi{padding-right:42px;}
        .pw-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);border:none;background:none;color:var(--faint);cursor:pointer;padding:4px;font-size:13px;transition:.15s;}
        .pw-eye:hover{color:var(--soft);}

        /* BUTTONS */
        .btn-p{width:100%;padding:13px;border-radius:11px;border:none;color:white;background:linear-gradient(135deg,var(--g700),var(--g900));font-weight:600;font-size:14px;cursor:pointer;transition:.2s;font-family:var(--fb);margin-top:8px;}
        .btn-p:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(22,163,74,.3);}
        .btn-p:disabled{opacity:0.5;cursor:not-allowed;transform:none !important;box-shadow:none !important;}
        .btn-secondary{width:100%;padding:11px;border-radius:11px;border:1.5px solid var(--border);color:var(--soft);background:transparent;font-weight:500;font-size:14px;cursor:pointer;transition:.2s;font-family:var(--fb);margin-top:8px;}
        .btn-secondary:hover{border-color:var(--g600);color:var(--g700);}
        .btn-row{display:flex;gap:10px;margin-top:4px;}
        .btn-row .btn-p,.btn-row .btn-secondary{margin-top:0;}

        /* FORGOT LINK */
        .forgot-wrap{text-align:right;margin-top:-6px;margin-bottom:12px;}
        .forgot-link{font-size:12px;color:var(--soft);font-family:var(--fm);transition:.15s;cursor:pointer;background:none;border:none;padding:0;letter-spacing:.02em;}
        .forgot-link:hover{color:var(--g600);}

        /* ALERTS */
        .alert{padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;font-weight:500;display:flex;align-items:center;gap:9px;}
        .alert-error{background:#fee2e2;color:#b91c1c;border-left:4px solid #ef4444;}
        .alert-success{background:var(--g100);color:var(--g800);border-left:4px solid var(--g500);}

        /* STEP PROGRESS */
        .step-progress{display:flex;align-items:center;margin-bottom:24px;}
        .step-item{display:flex;align-items:center;gap:8px;flex:1;}
        .step-item:last-child{flex:0;}
        .step-circle{width:27px;height:27px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:11px;font-family:var(--fm);font-weight:500;color:var(--faint);transition:.3s;flex-shrink:0;background:white;}
        .step-circle.active{border-color:var(--g600);color:var(--g600);background:rgba(22,163,74,.05);}
        .step-circle.done{border-color:var(--g400);background:var(--g400);color:white;}
        .step-label{font-size:10px;font-family:var(--fm);color:var(--faint);transition:.3s;text-transform:uppercase;letter-spacing:.04em;}
        .step-label.active{color:var(--g700);}
        .step-label.done{color:var(--g600);}
        .step-line{flex:1;height:1px;background:var(--border);margin:0 8px;transition:.3s;}
        .step-line.done{background:var(--g400);}

        /* PANELS */
        .step-panel{display:none;animation:fadeIn .22s ease;}
        .step-panel.active{display:block;}
        @keyframes fadeIn{from{opacity:0;transform:translateX(10px);}to{opacity:1;transform:translateX(0);}}

        /* SECTION LABELS */
        .section-label{font-family:var(--fm);font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--faint);margin-bottom:12px;margin-top:2px;display:flex;align-items:center;gap:8px;}
        .section-label::after{content:'';flex:1;height:1px;background:var(--border);}

        /* REG ID BADGE */
        .reg-id-hint{font-size:11px;color:var(--soft);margin-top:5px;display:flex;align-items:center;gap:5px;}
        .reg-id-hint i{color:var(--g600);}

        /* MODAL */
        .modal-overlay{position:fixed;inset:0;background:rgba(5,46,22,.65);backdrop-filter:blur(5px);z-index:100;display:none;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:white;border-radius:22px;padding:40px;width:100%;max-width:420px;box-shadow:0 24px 80px rgba(0,0,0,.4);border:1px solid var(--border);position:relative;animation:modalIn .28s cubic-bezier(.34,1.56,.64,1);}
        @keyframes modalIn{from{opacity:0;transform:scale(.93);}to{opacity:1;transform:scale(1);}}
        .modal-title{font-family:var(--fd);font-size:32px;font-weight:700;color:var(--ink);margin-bottom:6px;}
        .modal-title em{font-style:italic;color:var(--g700);}
        .modal-desc{font-size:13px;color:var(--soft);margin-bottom:22px;line-height:1.6;}
        .modal-close{position:absolute;top:16px;right:16px;border:none;background:var(--surface-2);border-radius:8px;width:32px;height:32px;cursor:pointer;color:var(--soft);font-size:14px;transition:.15s;display:flex;align-items:center;justify-content:center;}
        .modal-close:hover{background:var(--border);color:var(--ink);}
    </style>
</head>
<body>
<div class="scene"></div>

<div class="portal <?= $active_side === 'staff' ? 'staff-active' : '' ?>" id="portalFrame">

    <div class="glider" id="glider">
        <div>
            <div class="g-wm-text">HG<span class="g-wm-accent">H</span></div>
            <p style="font-family:var(--fm);font-size:9px;color:rgba(255,255,255,0.35);margin-top:4px;letter-spacing:.1em;">ESTABLISHED 1953</p>
        </div>
        <div id="glider-copy" style="padding: 20px 0;">
            <div id="copy-patient">
                <h3 class="g-tagline">Comprehensive patient care pathways.</h3>
                <p class="g-desc">Access secure diagnostic dashboards, clinical summaries, history files, and ledger metrics effortlessly.</p>
                <button type="button" class="g-cta" onclick="togglePortalSide('staff')">Staff Gateway Access <i class="fa fa-arrow-right"></i></button>
            </div>
            <div id="copy-staff" style="display:none;">
                <h3 class="g-tagline">Clinical Operations & Infrastructure.</h3>
                <p class="g-desc">Authorized medical personnel access node endpoints, emergency registrations, billing queues, and treatment charts.</p>
                <button type="button" class="g-cta" onclick="togglePortalSide('patient')"><i class="fa fa-arrow-left"></i> Patient Access Desk</button>
            </div>
        </div>
        <div class="g-status"><span class="dot"></span> SYSTEM ONLINE</div>
    </div>

    <div class="half">
        <div class="half__scroll">

            <?php if ($active_side === 'patient' && $error): ?>
                <div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success && $success !== 'FORGOT_SENT' && $active_side === 'patient'): ?>
                <div class="alert alert-success"><i class="fa fa-circle-check"></i><?= h($success) ?></div>
            <?php endif; ?>

            <h2 class="f-title">Patient <em>Portal</em></h2>
            <p class="f-sub">Your health records, at your fingertips.</p>

            <div class="sub-tabs">
                <button class="sub-tab active" id="p-tab-login" onclick="showPPanel('login')">Sign In</button>
                <button class="sub-tab" id="p-tab-reg" onclick="showPPanel('reg')">Register</button>
            </div>

            <div id="p-login">
                <form method="POST" action="">
                    <input type="hidden" name="role" value="patient">
                    <input type="hidden" name="login_action" value="1">
                    <div class="fg">
                        <label>Username / Email</label>
                        <input type="text" name="username" class="fi" placeholder="Enter username or email" required>
                    </div>
                    <div class="fg">
                        <label>Password</label>
                        <div class="pw-wrap">
                            <input type="password" name="password" id="p-pw" class="fi" placeholder="••••••••" required>
                            <button type="button" class="pw-eye" onclick="togglePw('p-pw',this)"><i class="fa fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="forgot-wrap">
                        <button type="button" class="forgot-link" onclick="openForgot('patient')">
                            <i class="fa fa-key" style="font-size:10px;margin-right:3px;"></i>Forgot password?
                        </button>
                    </div>
                    <button type="submit" class="btn-p">
                        <i class="fa fa-right-to-bracket" style="margin-right:8px;"></i>Secure Sign In
                    </button>
                </form>
            </div>

            <div id="p-reg" style="display:none;">
                <div class="step-progress" id="p-steps">
                    <div class="step-item">
                        <div class="step-circle active" id="ps-c1">1</div>
                        <span class="step-label active" id="ps-l1">Account</span>
                        <div class="step-line" id="ps-line1"></div>
                    </div>
                    <div class="step-item">
                        <div class="step-circle" id="ps-c2">2</div>
                        <span class="step-label" id="ps-l2">Personal</span>
                        <div class="step-line" id="ps-line2"></div>
                    </div>
                    <div class="step-item" style="flex:0;">
                        <div class="step-circle" id="ps-c3">3</div>
                        <span class="step-label" id="ps-l3">Emergency</span>
                    </div>
                </div>

                <form method="POST" action="" id="patient-reg-form" onsubmit="return validatePatientForm()">
                    <input type="hidden" name="patient_register" value="1">
                    
                    <div class="step-panel active" id="p-step-1">
                        <div class="section-label">Account Details</div>
                        <div class="fg">
                            <label>Username *</label>
                            <input type="text" name="reg_username" id="p-uname" class="fi" placeholder="Choose a username" required>
                        </div>
                        <div class="fg">
                            <label>Email Address *</label>
                            <input type="email" name="reg_email" id="p-email" class="fi" placeholder="you@gmail.com" required>
                        </div>
                        <div class="fg">
                            <label>Password *</label>
                            <div class="pw-wrap">
                                <input type="password" name="reg_password" id="p-pw1" class="fi" placeholder="8+ chars, upper+lower+number+symbol" required>
                                <button type="button" class="pw-eye" onclick="togglePw('p-pw1',this)"><i class="fa fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="fg">
                            <label>Confirm Password *</label>
                            <div class="pw-wrap">
                                <input type="password" id="p-pw2" class="fi" placeholder="Repeat your password" required>
                                <button type="button" class="pw-eye" onclick="togglePw('p-pw2',this)"><i class="fa fa-eye"></i></button>
                            </div>
                            <p id="p-pw-match-error" style="color:#ef4444; font-size:11px; margin-top:4px; display:none;">⚠️ Passwords do not match.</p>
                        </div>
                        <button type="button" id="btn-next-personal" class="btn-p" onclick="pStep(2)">
                            Next: Personal Details <i class="fa fa-arrow-right" style="margin-left:6px;"></i>
                        </button>
                    </div>

                    <div class="step-panel" id="p-step-2">
                        <div class="section-label">Personal Information</div>
                        <div class="fg-row">
                            <div><label>First Name *</label><input type="text" name="first_name" class="fi" placeholder="First"></div>
                            <div><label>Last Name *</label><input type="text" name="last_name" class="fi" placeholder="Last"></div>
                        </div>
                        <div class="fg-row">
                            <div>
                                <label>Date of Birth *</label>
                                <input type="date" name="dob" id="p-dob" class="fi">
                                <p id="dob-error" style="color:#ef4444; font-size:11px; margin-top:4px; display:none;">⚠️ Date of Birth cannot be a future date.</p>
                            </div>
                            <div><label>Gender</label>
                                <select name="gender" class="fi"><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select>
                            </div>
                        </div>
                        <div class="fg-row">
                            <div><label>Blood Group</label>
                                <select name="blood_group" class="fi"><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>O+</option><option>O-</option><option>AB+</option><option>AB-</option><option value="N/A">Unknown</option></select>
                            </div>
                            <div><label>Phone *</label><input type="tel" name="phone" class="fi" placeholder="+252 XX XXX XXXX"></div>
                        </div>
                        <div class="fg">
                            <label>Home Address</label>
                            <input type="text" name="address" class="fi" placeholder="Street, District, City">
                        </div>
                        <div class="btn-row">
                            <button type="button" class="btn-secondary" onclick="pStep(1)"><i class="fa fa-arrow-left" style="margin-right:5px;"></i>Back</button>
                            <button type="button" id="btn-next-emergency" class="btn-p" onclick="pStep(3)">Next: Emergency <i class="fa fa-arrow-right" style="margin-left:5px;"></i></button>
                        </div>
                    </div>

                    <div class="step-panel" id="p-step-3">
                        <div class="section-label">Emergency Contact</div>
                        <div class="fg">
                            <label>Contact Name</label>
                            <input type="text" name="emergency_name" class="fi" placeholder="Full name of emergency contact">
                        </div>
                        <div class="fg">
                            <label>Contact Phone</label>
                            <input type="tel" name="emergency_phone" class="fi" placeholder="+252 XX XXX XXXX">
                        </div>
                        <div class="btn-row">
                            <button type="button" class="btn-secondary" onclick="pStep(2)"><i class="fa fa-arrow-left" style="margin-right:5px;"></i>Back</button>
                            <button type="submit" class="btn-p">
                                <i class="fa fa-check" style="margin-right:6px;"></i>Complete Registration
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="half-sep"></div>

    <div class="half">
        <div class="half__scroll">

            <?php if ($active_side === 'staff' && $error): ?>
                <div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success && $success !== 'FORGOT_SENT' && $active_side === 'staff'): ?>
                <div class="alert alert-success"><i class="fa fa-circle-check"></i><?= h($success) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error']) && $_GET['error'] == 'unauthorized'): ?>
                <div class="alert alert-error"><i class="fa fa-ban"></i>Access Denied. Please log in again.</div>
            <?php endif; ?>

            <h2 class="f-title">Staff <em>Gateway</em></h2>
            <p class="f-sub">Authorised personnel only.</p>

            <div class="sub-tabs">
                <button class="sub-tab active" id="s-tab-login" onclick="showSPanel('login')">Sign In</button>
                <button class="sub-tab" id="s-tab-reg" onclick="showSPanel('reg')">Register Slot</button>
            </div>

            <div id="s-login">
                <form method="POST" action="">
                    <input type="hidden" name="login_action" value="1">
                    <div class="fg">
                        <label>Department Role</label>
                        <select name="role" class="fi" style="text-transform: capitalize;">
                            <option value="admin">Admin</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="lab">Laboratory</option>
                            <option value="pharmacy">Pharmacy</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Username / Email</label>
                        <input type="text" name="username" class="fi" placeholder="Staff username or email" required>
                    </div>
                    <div class="fg">
                        <label>Password</label>
                        <div class="pw-wrap">
                            <input type="password" name="password" id="s-pw" class="fi" placeholder="••••••••" required>
                            <button type="button" class="pw-eye" onclick="togglePw('s-pw',this)"><i class="fa fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="forgot-wrap">
                        <button type="button" class="forgot-link" onclick="openForgot('staff')">
                            <i class="fa fa-key" style="font-size:10px;margin-right:3px;"></i>Forgot password?
                        </button>
                    </div>
                    <button type="submit" class="btn-p">
                        <i class="fa fa-shield-halved" style="margin-right:8px;"></i>System Authentication
                    </button>
                </form>
            </div>

            <div id="s-reg" style="display:none;">
                <form method="POST" action="" onsubmit="return validateStaffForm()">
                    <input type="hidden" name="staff_register" value="1">
                    <div class="section-label">Verify Registration Parameters</div>
                    
                    <div class="fg">
                        <label>Assigned Department *</label>
                        <select name="staff_role" id="s-reg-role" class="fi">
                            <option value="admin">Administration</option>
                            <option value="doctor">Medical Doctor</option>
                            <option value="nurse">Clinical Nursing</option>
                            <option value="receptionist">Front Desk Receptionist</option>
                            <option value="lab">Laboratory Consultant</option>
                            <option value="pharmacy">Pharmacy Operations</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>HGH Registration Slot ID *</label>
                        <input type="text" name="staff_reg_id" class="fi" placeholder="e.g., REG-2026-XXXX" required>
                        <div class="reg-id-hint"><i class="fa fa-circle-info"></i>Must match pre-allocated infrastructure entry code.</div>
                    </div>
                    <div class="fg-row">
                        <div><label>First Name *</label><input type="text" name="staff_first_name" class="fi" required></div>
                        <div><label>Last Name *</label><input type="text" name="staff_last_name" class="fi" required></div>
                    </div>
                    <div class="fg">
                        <label>Desired Username *</label>
                        <input type="text" name="staff_username" class="fi" placeholder="Choose system handle" required>
                    </div>
                    <div class="fg">
                        <label>Official Email Address *</label>
                        <input type="email" name="staff_email" class="fi" placeholder="prefix@hargeisagrouphospital.org" required>
                    </div>
                    <div class="fg">
                        <label>Contact Number</label>
                        <input type="tel" name="staff_phone" class="fi" placeholder="+252 XX XXX XXXX">
                    </div>
                    <div class="fg">
                        <label>Set Access Password *</label>
                        <div class="pw-wrap">
                            <input type="password" name="staff_password" id="s-reg-pw1" class="fi" placeholder="Minimum 8 characters combined" required>
                            <button type="button" class="pw-eye" onclick="togglePw('s-reg-pw1',this)"><i class="fa fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="fg">
                        <label>Confirm Access Password *</label>
                        <div class="pw-wrap">
                            <input type="password" id="s-reg-pw2" class="fi" placeholder="Repeat access keys" required>
                            <button type="button" class="pw-eye" onclick="togglePw('s-reg-pw2',this)"><i class="fa fa-eye"></i></button>
                        </div>
                        <p id="s-pw-match-error" style="color:#ef4444; font-size:11px; margin-top:4px; display:none;">⚠️ Passwords do not match.</p>
                    </div>
                    <button type="submit" class="btn-p" style="margin-top:15px;">
                        <i class="fa fa-id-card" style="margin-right:6px;"></i>Claim Secure Slot Row
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<div class="modal-overlay" id="forgotModal">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeForgot()"><i class="fa fa-xmark"></i></button>
        <h3 class="modal-title">Reset <em>Keys</em></h3>
        <p class="modal-desc">Enter your account profile registry email. System triggers cryptographic tokens if structural verification passes.</p>
        <form method="POST" action="">
            <input type="hidden" name="forgot_action" value="1">
            <input type="hidden" name="forgot_role" id="forgotTargetRole" value="">
            <div class="fg">
                <label>Registered Email Address</label>
                <input type="email" name="forgot_email" class="fi" placeholder="Enter account email profile" required>
            </div>
            <button type="submit" class="btn-p">Dispatch Verification Vector</button>
        </form>
    </div>
</div>

<script>
    // System-wide dynamic text rendering alignment engine
    function initGliderCopy() {
        const isStaff = document.getElementById('portalFrame').classList.contains('staff-active');
        document.getElementById('copy-patient').style.display = isStaff ? 'none' : 'block';
        document.getElementById('copy-staff').style.display = isStaff ? 'block' : 'none';
    }

    function togglePortalSide(side) {
        const frame = document.getElementById('portalFrame');
        if (side === 'staff') {
            frame.classList.add('staff-active');
        } else {
            frame.classList.remove('staff-active');
        }
        initGliderCopy();
    }

    function showPPanel(type) {
        document.getElementById('p-login').style.display = type === 'login' ? 'block' : 'none';
        document.getElementById('p-reg').style.display = type === 'reg' ? 'block' : 'none';
        document.getElementById('p-tab-login').classList.toggle('active', type === 'login');
        document.getElementById('p-tab-reg').classList.toggle('active', type === 'reg');
    }

    function showSPanel(type) {
        document.getElementById('s-login').style.display = type === 'login' ? 'block' : 'none';
        document.getElementById('s-reg').style.display = type === 'reg' ? 'block' : 'none';
        document.getElementById('s-tab-login').classList.toggle('active', type === 'login');
        document.getElementById('s-tab-reg').classList.toggle('active', type === 'reg');
    }

    function togglePw(fieldId, btn) {
        const el = document.getElementById(fieldId);
        if (el.type === 'password') {
            el.type = 'text';
            btn.innerHTML = '<i class="fa fa-eye-slash"></i>';
        } else {
            el.type = 'password';
            btn.innerHTML = '<i class="fa fa-eye"></i>';
        }
    }

    function pStep(n) {
        // Simple client side form step transition validation tracker
        if (n === 2) {
            const uname = document.getElementById('p-uname').value;
            const email = document.getElementById('p-email').value;
            const p1 = document.getElementById('p-pw1').value;
            const p2 = document.getElementById('p-pw2').value;
            if(!uname || !email || !p1 || !p2) { alert('Please enter all required account credentials.'); return; }
            if(p1 !== p2) { document.getElementById('p-pw-match-error').style.display = 'block'; return; }
            document.getElementById('p-pw-match-error').style.display = 'none';
        }
        
        if (n === 3) {
            const dob = document.getElementById('p-dob').value;
            if(dob) {
                const selectedDate = new Date(dob);
                const today = new Date();
                if(selectedDate > today) {
                    document.getElementById('dob-error').style.display = 'block';
                    return;
                }
            }
            document.getElementById('dob-error').style.display = 'none';
        }

        for (let i = 1; i <= 3; i++) {
            const panel = document.getElementById('p-step-' + i);
            const circle = document.getElementById('ps-c' + i);
            const label = document.getElementById('ps-l' + i);
            const line = document.getElementById('ps-line' + i);

            if (i === n) {
                panel.classList.add('active');
                circle.className = 'step-circle active';
                label.className = 'step-label active';
            } else {
                panel.classList.remove('active');
                if (i < n) {
                    circle.className = 'step-circle done';
                    circle.innerHTML = '<i class="fa fa-check"></i>';
                    label.className = 'step-label done';
                    if(line) line.className = 'step-line done';
                } else {
                    circle.className = 'step-circle';
                    circle.innerHTML = i;
                    label.className = 'step-label';
                    if(line) line.className = 'step-line';
                }
            }
        }
    }

    function openForgot(role) {
        document.getElementById('forgotTargetRole').value = role;
        document.getElementById('forgotModal').classList.add('open');
    }

    function closeForgot() {
        document.getElementById('forgotModal').classList.remove('open');
    }

    function validatePatientForm() {
        const p1 = document.getElementById('p-pw1').value;
        const p2 = document.getElementById('p-pw2').value;
        if(p1 !== p2) { alert('Account execution halt: Passwords mismatch.'); return false; }
        return true;
    }

    function validateStaffForm() {
        const p1 = document.getElementById('s-reg-pw1').value;
        const p2 = document.getElementById('s-reg-pw2').value;
        if(p1 !== p2) { 
            document.getElementById('s-pw-match-error').style.display = 'block'; 
            return false; 
        }
        document.getElementById('s-pw-match-error').style.display = 'none';
        return true;
    }

    // Trigger base template load positioning initialization
    window.onload = initGliderCopy;
</script>
</body>
</html>