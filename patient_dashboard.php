<?php
session_start();
include("db.php");
date_default_timezone_set('Asia/Kuala_Lumpur');

/* ================= SECURITY & SESSION ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Generate CSRF Token for Secure Profile Submissions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ================= DATA HYDRATION (PREPARED STATEMENTS) ================= */
if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_q = $stmt->get_result();
    if ($user_q && $user_q->num_rows > 0) {
        $_SESSION['username'] = $user_q->fetch_assoc()['username'];
    }
    $stmt->close();
}

$name = $_SESSION['username'] ?? 'Patient';
$initial = !empty($name) ? strtoupper($name[0]) : 'P';
$page = $_GET['page'] ?? 'dashboard';

$patient_id = 0;
$patient_since = "N/A";

$stmt = $conn->prepare("SELECT patient_id, created_at FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$q = $stmt->get_result();
if ($q && $q->num_rows > 0) {
    $row = $q->fetch_assoc();
    $patient_id = $row['patient_id'];
    $patient_since = date("Y", strtotime($row['created_at']));
}
$stmt->close();

// Stats aggregation logic
$appointments_count = 0;
$next_appointment = "None";
$prescriptions_count = 0;
$lab_count = 0;

if ($patient_id) {
    // 1. Next Appointment
    $stmt = $conn->prepare("SELECT appointment_date FROM appointments 
        WHERE patient_id = ?
        AND (
            appointment_date > CURDATE()
            OR (appointment_date = CURDATE() AND appointment_time >= CURTIME())
        )
        ORDER BY appointment_date ASC, appointment_time ASC
        LIMIT 1");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $a = $stmt->get_result();
    if ($a && $a->num_rows > 0) {
        $next_appointment = $a->fetch_assoc()['appointment_date'];
    }
    $stmt->close();
    
    // 2. Total Counts
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $appointments_count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $prescriptions_count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM lab_request_tests t JOIN lab_requests r ON t.lab_request_id = r.lab_request_id WHERE r.patient_id = ? AND t.status = 'Completed'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $lab_count = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

}

/* ================= EDIT APPOINTMENT - ONLY PENDING ================= */
if (isset($_POST['update_appointment']) && $patient_id) {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $new_date = $_POST['appointment_date'] ?? '';
    $new_time = $_POST['appointment_time'] ?? '';
    $new_reason = trim($_POST['reason'] ?? '');

    if ($appointment_id <= 0 || empty($new_date) || empty($new_time)) {
        echo "<script>alert('Please select appointment date and time.');</script>";
    } else {
        $nowDate = date('Y-m-d');
        $nowTime = date('H:i:s');

        if ($new_date < $nowDate || ($new_date === $nowDate && $new_time <= $nowTime)) {
            echo "<script>alert('You cannot choose a passed date or time.');</script>";
        } else {
            $check = $conn->prepare("SELECT appointment_id, doctor_id, status FROM appointments WHERE appointment_id = ? AND patient_id = ? LIMIT 1");
            $check->bind_param("ii", $appointment_id, $patient_id);
            $check->execute();
            $app = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$app) {
                echo "<script>alert('Appointment not found.');</script>";
            } elseif (strtolower($app['status']) !== 'pending') {
                echo "<script>alert('This appointment is already confirmed/scheduled and cannot be edited.');</script>";
            } else {
                $doctor_id = (int)$app['doctor_id'];

                $slot_check = $conn->prepare("SELECT appointment_id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled' AND appointment_id != ? LIMIT 1");
                $slot_check->bind_param("issi", $doctor_id, $new_date, $new_time, $appointment_id);
                $slot_check->execute();
                $taken = $slot_check->get_result();
                $slot_check->close();

                if ($taken && $taken->num_rows > 0) {
                    echo "<script>alert('This slot is already taken. Please choose another time.');</script>";
                } else {
                    $update = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, reason = ? WHERE appointment_id = ? AND patient_id = ? AND status = 'Pending'");
                    $update->bind_param("sssii", $new_date, $new_time, $new_reason, $appointment_id, $patient_id);
                    if ($update->execute()) {
                        echo "<script>alert('Appointment updated successfully.'); window.location='patient_dashboard.php';</script>";
                        exit();
                    } else {
                        echo "<script>alert('Unable to update appointment.');</script>";
                    }
                    $update->close();
                }
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
    <title>Patient Portal | Hargeisa General Hospital</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --emerald-900: #052e16; 
            --emerald-600: #16a34a; 
            --emerald-400: #4ade80;
            --emerald-50: #f0fdf4;
            --slate-500: #64748b;
            --sidebar-w: 280px; 
            --sidebar-c: 85px; 
            --nav-h: 80px;
            --ease: cubic-bezier(.4, 0, .2, 1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; overflow-x: hidden; display: flex; color: var(--text-main); }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed; left: 0; top: 0; width: var(--sidebar-w); height: 100vh;
            background: var(--emerald-900); z-index: 1001; transition: width 0.4s var(--ease);
            display: flex; flex-direction: column; overflow: hidden; padding: 0;
        }
        body.collapsed .sidebar { width: var(--sidebar-c); }

        .side-head { height: var(--nav-h); display: flex; align-items: center; padding: 0 20px; background: rgba(0,0,0,0.2); }
        .logo-bundle { display: flex; align-items: center; gap: 15px; text-decoration: none; min-width: 200px; } 
        .logo-icon { width: 45px; height: 45px; background: #fff; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--emerald-900); font-size: 20px; flex-shrink: 0; }         
        
        .logo-text, .menu span, .menu-label { white-space: nowrap; transition: opacity 0.3s ease; opacity: 1; }
        body.collapsed .logo-text, body.collapsed .menu span, body.collapsed .menu-label { opacity: 0; pointer-events: none; }

        .menu-label { font-size: 10px; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 1.5px; margin: 25px 20px 10px; }
        .menu { flex: 1; display: flex; flex-direction: column; padding: 0; list-style: none; }
        
        .menu a {
            display: flex; align-items: center; gap: 20px; padding: 14px 18px; margin: 0 15px 5px;
            border-radius: 14px; text-decoration: none; color: rgba(255,255,255,0.6); transition: 0.3s;
        }
        .menu a i { font-size: 18px; min-width: 25px; text-align: center; }
        .menu a:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .menu a.active { background: var(--emerald-600); color: #fff; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); }
        .logout-link { margin-top: auto !important; margin-bottom: 20px !important; color: #f87171 !important; }

        /* ===== TOPBAR ===== */
        .header {
            position: fixed; left: var(--sidebar-w); top: 0; right: 0; height: var(--nav-h);
            background: #fff; border-bottom: 1px solid #eef2f6; display: flex; 
            align-items: center; justify-content: space-between; padding: 0 35px; 
            transition: left 0.4s var(--ease); z-index: 1000;
        }
        body.collapsed .header { left: var(--sidebar-c); }

        .header-left { display: flex; align-items: center; gap: 20px; }
        .top-toggle-btn { background: #f1f5f9; border: none; width: 42px; height: 42px; border-radius: 12px; color: var(--emerald-900); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: 0.2s; }
        .top-toggle-btn:hover { background: var(--emerald-600); color: #fff; }

        /* ===== USER PILL CONTAINER ===== */
        .user-action-pill { display: flex; align-items: center; gap: 12px; padding: 6px 16px 6px 8px; background: #f8fafc; border-radius: 50px; border: 1px solid #e2e8f0; cursor: pointer; transition: 0.2s; user-select: none; }
        .user-action-pill:hover { border-color: var(--emerald-600); }
        .user-meta { text-align: right; }
        .u-name { display: block; font-size: 13px; font-weight: 700; color: var(--emerald-900); line-height: 1.2; }
        .u-role { display: block; font-size: 10px; color: var(--emerald-600); font-weight: 600; text-transform: uppercase; }
        .u-avatar-top { width: 38px; height: 38px; border-radius: 50%; background: var(--emerald-600); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .u-avatar-top img { width: 100%; height: 100%; object-fit: cover; }
        
        /* ===== MAIN CONTAINER ===== */
        .main { 
            flex-grow: 1; margin-left: var(--sidebar-w); padding: calc(var(--nav-h) + 40px) 40px 40px; 
            transition: margin-left 0.4s var(--ease); min-width: 0;
        }
        body.collapsed .main { margin-left: var(--sidebar-c); }

        /* STATS CARDS */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .stat-card {
            background: #fff; padding: 25px; border-radius: 20px; border: 1px solid #edf2f7;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.02); display: flex; align-items: center; gap: 20px;
        }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-data { display: flex; flex-direction: column; }
        .stat-data h3 { font-size: 24px; font-weight: 700; color: var(--text-main); line-height: 1.2; }
        .stat-data p { font-size: 13px; color: var(--text-muted); margin: 0; }
        .stat-meta-sub { font-size: 11px; font-weight: 600; color: var(--emerald-600); margin-top: 2px; }

        /* TABLES & CONTENT CARDS */
        .content-card { background: #fff; border-radius: 24px; padding: 35px; border: 1px solid #edf2f7; }
        .saas-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .saas-table th { text-align: left; padding: 15px; font-size: 12px; color: #94a3b8; border-bottom: 1px solid #f1f5f9; text-transform: uppercase; letter-spacing: 0.5px; }
        .saas-table td { padding: 20px 15px; font-size: 14px; border-bottom: 1px solid #f8fafc; }
        
        .status-pill { padding: 6px 14px; border-radius: 100px; font-size: 11px; font-weight: 700; }
        .status-confirmed { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }

        .btn-primary {
            background: var(--emerald-900); color: #fff; padding: 12px 24px; border-radius: 12px;
            text-decoration: none; font-weight: 700; font-size: 13px; transition: var(--transition);
            display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
        }
        .btn-primary:hover { background: var(--emerald-600); transform: translateY(-2px); }

        /* ===== PROFILE MODAL ===== */
        .modal-overlay { position: fixed; inset: 0; background: rgba(5, 46, 22, 0.6); backdrop-filter: blur(12px); display: none; align-items: center; justify-content: center; z-index: 2000; }
        .modal-card { background: #ffffff; width: 100%; max-width: 460px; border-radius: 32px; padding: 40px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); }
        .modal-close-btn { position: absolute; top: 25px; right: 25px; width: 36px; height: 36px; border-radius: 12px; background: var(--emerald-50); border: none; color: var(--emerald-900); cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; }
        .modal-close-btn:hover { background: #fee2e2; color: #ef4444; }
        .modal-header { text-align: center; margin-bottom: 30px; }
        .modal-header h3 { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--emerald-900); }
        .modal-header p { font-size: 13px; color: var(--slate-500); }

        .image-upload-wrapper { text-align: center; margin-bottom: 30px; }
        .profile-image-container { position: relative; width: 110px; height: 110px; margin: 0 auto 10px; }
        .profile-image-container img, .initial-avatar-large { width: 110px; height: 110px; border-radius: 35px; object-fit: cover; border: 4px solid var(--emerald-50); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .initial-avatar-large { background: var(--emerald-600); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 40px; font-weight: 700; }
        .camera-trigger { position: absolute; bottom: -5px; right: -5px; background: var(--emerald-900); color: #fff; width: 36px; height: 36px; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid #fff; transition: var(--transition); }
        .camera-trigger:hover { background: var(--emerald-600); transform: scale(1.1); }

        .input-field { margin-bottom: 20px; }
        .input-field label { display: block; font-size: 12px; font-weight: 700; color: var(--emerald-900); margin-bottom: 8px; }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i { position: absolute; left: 15px; color: var(--emerald-600); font-size: 14px; }
        .input-wrapper input { width: 100%; padding: 14px 14px 14px 45px; border-radius: 15px; border: 1.5px solid #e2e8f0; background: #f8fafc; transition: var(--transition); font-family: inherit; }
        .input-wrapper input:focus { border-color: var(--emerald-600); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1); }

        .save-profile-btn { width: 100%; padding: 16px; border-radius: 16px; border: none; background: var(--emerald-900); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; transition: var(--transition); margin-top: 10px; font-family: inherit; }
        .save-profile-btn:hover { background: var(--emerald-600); transform: translateY(-2px); }

        .btn-edit-appointment {
            background: var(--emerald-600);
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }
        .btn-edit-appointment:hover { background: var(--emerald-900); transform: translateY(-1px); }
        .locked-text { color:#94a3b8; font-size:12px; font-weight:800; display:inline-flex; align-items:center; gap:6px; }
        .appointment-modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.48); z-index:3000; align-items:center; justify-content:center; padding:20px; }
        .appointment-modal-card { width:100%; max-width:430px; background:#fff; border-radius:24px; padding:28px; position:relative; box-shadow:0 30px 70px rgba(15,23,42,.20); border:1px solid #edf2f7; }
        .appointment-modal-card h3 { font-family:'Playfair Display'; color:var(--emerald-900); font-size:24px; margin-bottom:6px; }
        .appointment-modal-card p { color:#64748b; font-size:13px; margin-bottom:20px; line-height:1.5; }
        .appointment-modal-card label { display:block; font-size:11px; font-weight:800; color:#94a3b8; text-transform:uppercase; margin:12px 0 7px; letter-spacing:.5px; }
        .appointment-modal-close { position:absolute; top:16px; right:16px; width:34px; height:34px; border:none; border-radius:10px; background:#f1f5f9; color:#64748b; cursor:pointer; }
        .appointment-edit-input { width:100%; border:1.5px solid #e2e8f0; background:#f8fafc; border-radius:14px; padding:13px 15px; font-family:inherit; font-size:14px; outline:none; }
        .appointment-edit-input:focus { border-color:var(--emerald-600); background:#fff; box-shadow:0 0 0 4px rgba(22,163,74,.08); }
        .appointment-cancel-btn { border:none; background:#e5e7eb; color:#334155; padding:11px 16px; border-radius:12px; font-weight:800; cursor:pointer; }
        .appointment-save-btn { border:none; background:var(--emerald-900); color:#fff; padding:11px 18px; border-radius:12px; font-weight:800; cursor:pointer; }
    </style>
</head>

<body class="<?= (isset($_SESSION['sidebar_collapsed']) && $_SESSION['sidebar_collapsed']) ? 'collapsed' : '' ?>">

<aside class="sidebar">
    <div class="side-head">
        <div class="logo-bundle">
            <div class="logo-icon"><i class="fa-solid fa-staff-snake"></i></div>
            <div class="logo-text">
                <h1 style="color:#fff; font-family:'Playfair Display'; font-size:16px; margin:0;">HGH Patient</h1>
                <span style="color:var(--emerald-400); font-size:9px;">Hargeisa General Hospital</span>
            </div>
        </div>
    </div>

    <p class="menu-label">Medical Portal</p>
    <nav class="menu">
        <a href="?page=dashboard" class="<?= $page=='dashboard'?'active':'' ?>">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <a href="?page=book_appointment" class="<?= $page=='book_appointment'?'active':'' ?>">
            <i class="fa-solid fa-calendar-check"></i> <span>Book Appointment</span>
        </a>
        <a href="?page=view_reports" class="<?= $page=='view_reports'?'active':'' ?>">
            <i class="fa-solid fa-file-medical"></i> <span>Health Reports</span>
        </a>
        <a href="?page=patient_profile" class="<?= $page=='patient_profile'?'active':'' ?>">
            <i class="fa-solid fa-user-gear"></i> <span>My Profile</span>
        </a>
        
        <a href="logout.php" class="logout-link">
            <i class="fa-solid fa-power-off"></i> <span>Logout System</span>
        </a>
    </nav>
</aside>

<header class="header">
    <div class="header-left">
        <button class="top-toggle-btn" onclick="toggleSidebar()">
            <i class="fa-solid <?= (isset($_SESSION['sidebar_collapsed']) && $_SESSION['sidebar_collapsed']) ? 'fa-indent' : 'fa-bars-staggered' ?>"></i>
        </button>
        <h2 style="font-family:'Playfair Display'; color:var(--emerald-900); margin:0; font-size:20px;">Welcome Back, <?= htmlspecialchars($name) ?></h2>
    </div>

    <div class="user-action-pill" onclick="openProfileModal()">
        <div class="user-meta">
            <span class="u-name"><?= htmlspecialchars($name) ?></span>
            <span class="u-role">Patient ID: <?= htmlspecialchars($patient_id) ?></span>
        </div>
        <div class="u-avatar-top">
            <?php 
                $profilePic = !empty($_SESSION['profile_photo']) ? 'uploads/'.$_SESSION['profile_photo'] : null;
                if($profilePic && file_exists($profilePic)): 
            ?>
                <img src="<?= htmlspecialchars($profilePic) ?>" alt="User">
            <?php else: ?>
                <?= htmlspecialchars($initial) ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="main">
    <?php
    switch($page) {
        case 'book_appointment': include "book_appointment.php"; break;
        case 'view_reports':     include "patient_reports.php"; break;
        case 'patient_profile':  include "patient_profile.php"; break; // <-- Routing fix implemented here!
        default:
    ?>
    
    <div style="margin-bottom: 35px;">
        <h1 style="font-family:'Playfair Display'; font-size:32px; color:var(--emerald-900);">My Health Overview</h1>
        <p style="color:#64748b; margin-top:5px;">Access your medical records and upcoming HGH consultations.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#eff6ff; color:#2563eb;"><i class="fa-solid fa-calendar-days"></i></div>
            <div class="stat-data">
                <h3><?= htmlspecialchars($appointments_count) ?></h3>
                <p>Appointments</p>
                <span class="stat-meta-sub">Next: <?= $next_appointment != 'None' ? date("M d", strtotime($next_appointment)) : 'None' ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff7ed; color:#ea580c;"><i class="fa-solid fa-pills"></i></div>
            <div class="stat-data">
                <h3><?= htmlspecialchars($prescriptions_count) ?></h3>
                <p>Prescriptions</p>
                <span class="stat-meta-sub">Current Meds</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0fdf4; color:var(--emerald-600);"><i class="fa-solid fa-vial-circle-check"></i></div>
            <div class="stat-data">
                <h3><?= htmlspecialchars($lab_count) ?></h3>
                <p>Lab Results</p>
                <span class="stat-meta-sub">Finalized</span>
            </div>
        </div>
        <div class="stat-card" style="background: var(--emerald-900); border:none;">
            <div class="stat-icon" style="background:rgba(255,255,255,0.1); color:#fff;"><i class="fa-solid fa-id-card"></i></div>
            <div class="stat-data">
                <h3 style="color:#fff;"><?= htmlspecialchars($patient_since) ?></h3>
                <p style="color:rgba(255,255,255,0.6);">Member Since</p>
                <span class="stat-meta-sub" style="color:var(--emerald-400);">Registered Patient</span>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
            <h3 style="font-family:'Playfair Display'; color:var(--emerald-900); font-size:22px;">Upcoming Schedule</h3>
            <a href="?page=book_appointment" class="btn-primary"><i class="fa-solid fa-plus"></i> Book New</a>
        </div>

        <table class="saas-table">
            <thead>
                <tr>
                    <th>DATE & TIME</th>
                    <th>DOCTOR</th>
                    <th>SERVICE</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($patient_id) {
                    $stmt = $conn->prepare("
                        SELECT 
                            a.appointment_id,
                            a.appointment_date,
                            a.appointment_time,
                            a.reason,
                            a.status,
                            COALESCE(u.full_name, u.username, 'Assigning...') AS doc
                        FROM appointments a 
                        LEFT JOIN users u ON a.doctor_id = u.user_id 
                        WHERE a.patient_id = ?
                        AND (
                            a.appointment_date > CURDATE()
                            OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME())
                        )
                        ORDER BY a.appointment_date ASC, a.appointment_time ASC
                        LIMIT 5
                    ");
                    $stmt->bind_param("i", $patient_id);
                    $stmt->execute();
                    $list = $stmt->get_result();
                    
                    if ($list->num_rows > 0) {
                        while($r = $list->fetch_assoc()) {
                            $statusLower = strtolower($r['status']);
                            $st_class = in_array($statusLower, ['confirmed', 'scheduled']) ? 'status-confirmed' : 'status-pending';
                            $timeShort = substr($r['appointment_time'], 0, 5);
                            $reasonSafe = htmlspecialchars($r['reason'] ?? '', ENT_QUOTES, 'UTF-8');
                            echo "<tr>
                                <td><div style='font-weight:700; color:var(--emerald-900);'>".date("M d, Y", strtotime($r['appointment_date']))."</div><div style='font-size:12px; color:#94a3b8;'>".htmlspecialchars($r['appointment_time'])."</div></td>
                                <td>Dr. " . htmlspecialchars($r['doc'] ?? 'Assigning...') . "</td>
                                <td>General Consultation</td>
                                <td><span class='status-pill {$st_class}'>".htmlspecialchars(ucfirst($r['status']))."</span></td>
                                <td>";

                            if ($statusLower === 'pending') {
                                echo "<button type='button' class='btn-edit-appointment' onclick=\"openEditAppointmentModal('".(int)$r['appointment_id']."', '".htmlspecialchars($r['appointment_date'], ENT_QUOTES)."', '".htmlspecialchars($timeShort, ENT_QUOTES)."', '".$reasonSafe."')\"><i class='fa-solid fa-pen'></i> Edit</button>";
                            } else {
                                echo "<span class='locked-text'><i class='fa-solid fa-lock'></i> Locked</span>";
                            }

                            echo "</td></tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' style='text-align:center; padding:60px; color:#94a3b8;'><i class='fa-regular fa-calendar-xmark' style='font-size:30px; margin-bottom:10px; display:block;'></i> No upcoming appointments found.</td></tr>";
                    }
                    $stmt->close();
                }
                ?>
            </tbody>
        </table>
    </div>

    <div id="editAppointmentModal" class="appointment-modal-overlay">
        <div class="appointment-modal-card">
            <button type="button" class="appointment-modal-close" onclick="closeEditAppointmentModal()"><i class="fa-solid fa-xmark"></i></button>
            <h3>Edit Appointment</h3>
            <p>Only pending appointments can be edited. Confirmed appointments are locked.</p>

            <form method="POST" id="editAppointmentForm">
                <input type="hidden" name="appointment_id" id="editAppointmentId">

                <label>Appointment Date</label>
                <input type="date" name="appointment_date" id="editAppointmentDate" class="appointment-edit-input" required>

                <label>Appointment Time</label>
                <input type="time" name="appointment_time" id="editAppointmentTime" class="appointment-edit-input" required>

                <label>Reason</label>
                <textarea name="reason" id="editAppointmentReason" class="appointment-edit-input" rows="4" placeholder="Describe symptoms or clinical concerns..."></textarea>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:18px;">
                    <button type="button" class="appointment-cancel-btn" onclick="closeEditAppointmentModal()">Cancel</button>
                    <button type="submit" name="update_appointment" class="appointment-save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <?php break; } ?>
</main>

<div id="pModal" class="modal-overlay">
    <div class="modal-card">
        <button class="modal-close-btn" onclick="closeProfileModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-header">
            <h3>Patient Profile Update</h3>
            <p>Update your credentials and medical identity</p>
        </div>

        <form id="patientProfileForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="image-upload-wrapper">
                <div class="profile-image-container">
                    <?php if($profilePic && file_exists($profilePic)): ?>
                        <img id="previewImg" src="<?= htmlspecialchars($profilePic) ?>" alt="Profile">
                    <?php else: ?>
                        <div id="initialAvatar" class="initial-avatar-large"><?= htmlspecialchars($initial) ?></div>
                        <img id="previewImg" src="" style="display:none;" alt="Preview">
                    <?php endif; ?>

                    <label for="photoInput" class="camera-trigger">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                </div>
                <input type="file" name="photo" id="photoInput" hidden onchange="handleImagePreview(this)">
            </div>

            <div class="input-field">
                <label>Display Name</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
                </div>
            </div>

            <div class="input-field">
                <label>Update Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-key"></i>
                    <input type="password" name="password" placeholder="Leave empty to keep current">
                </div>
            </div>

            <button type="submit" class="save-profile-btn" id="saveBtn">
                <span>Save Changes</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>
    </div>
</div>

<script>
    function toggleSidebar() { 
        document.body.classList.toggle('collapsed'); 
        const isCollapsed = document.body.classList.contains('collapsed');
        const icon = document.querySelector('.top-toggle-btn i');
        
        icon.classList.toggle('fa-bars-staggered', !isCollapsed);
        icon.classList.toggle('fa-indent', isCollapsed);

        fetch('toggle_sidebar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'collapsed=' + (isCollapsed ? 1 : 0)
        });
    }

    function openProfileModal() { document.getElementById('pModal').style.display = 'flex'; }
    function closeProfileModal() { document.getElementById('pModal').style.display = 'none'; }

    function handleImagePreview(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewImg = document.getElementById('previewImg');
                const initialAvatar = document.getElementById('initialAvatar');
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
                if(initialAvatar) initialAvatar.style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    /* AJAX SUBMISSION WITH CSRF VERIFICATION */
    document.getElementById('patientProfileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const saveBtn = document.getElementById('saveBtn');
        const formData = new FormData(this);

        saveBtn.innerHTML = '<span>Saving...</span> <i class="fa-solid fa-spinner fa-spin"></i>';
        saveBtn.disabled = true;

        fetch('update_profile.php', { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok) throw new Error();
            closeProfileModal();
            window.location.reload(); 
        })
        .catch(() => {
            alert("Error saving profile settings.");
            saveBtn.innerHTML = '<span>Save Changes</span> <i class="fa-solid fa-arrow-right"></i>';
            saveBtn.disabled = false;
        });
    });



    function openEditAppointmentModal(id, date, time, reason) {
        const modal = document.getElementById('editAppointmentModal');
        document.getElementById('editAppointmentId').value = id;
        document.getElementById('editAppointmentDate').value = date;
        document.getElementById('editAppointmentTime').value = time;
        document.getElementById('editAppointmentReason').value = reason || '';

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        document.getElementById('editAppointmentDate').min = `${yyyy}-${mm}-${dd}`;

        modal.style.display = 'flex';
    }

    function closeEditAppointmentModal() {
        const modal = document.getElementById('editAppointmentModal');
        if (modal) modal.style.display = 'none';
    }

    const editAppointmentForm = document.getElementById('editAppointmentForm');
    if (editAppointmentForm) {
        editAppointmentForm.addEventListener('submit', function(e) {
            const d = document.getElementById('editAppointmentDate').value;
            const t = document.getElementById('editAppointmentTime').value;
            if (!d || !t) {
                e.preventDefault();
                alert('Please select a date and time.');
                return;
            }

            const selected = new Date(d + 'T' + t);
            const now = new Date();
            if (selected <= now) {
                e.preventDefault();
                alert('You cannot choose a passed date or time.');
            }
        });
    }
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) { closeProfileModal(); }
    };
</script>
</body>
</html>