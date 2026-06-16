<?php
// Start session if it hasn't been initialized yet
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include("db.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'nurse') {
    header("Location: index.php?error=unauthorized");
    exit();
}

/* ================= USER INFO ================= */
$nurseName  = $_SESSION['username'] ?? 'Nurse';
$nurseEmail = $_SESSION['email'] ?? '';
$initial    = !empty($nurseName) ? strtoupper($nurseName[0]) : 'N';
$current_page = $_GET['page'] ?? 'home';

/* ================= ANALYTICS ================= */
$today = date('Y-m-d');

// Safe calculation for Total Patients
$totalPatientsQuery = $conn->query("SELECT COUNT(*) as total FROM patients");
$totalPatients = ($totalPatientsQuery && $totalPatientsQuery->num_rows > 0) 
    ? $totalPatientsQuery->fetch_assoc()['total'] 
    : 0;

// Robust Vitals Check
$vitalsQuery = $conn->query("SELECT COUNT(*) as total FROM patient_vitals WHERE DATE(created_at) = '$today'");
if (!$vitalsQuery) {
    $vitalsQuery = $conn->query("SELECT COUNT(*) as total FROM vitals WHERE DATE(created_at) = '$today'");
}
$vitalsRecordedToday = ($vitalsQuery && $vitalsQuery->num_rows > 0) 
    ? $vitalsQuery->fetch_assoc()['total'] 
    : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Portal | HGH</title>
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

        * { margin:0; padding:0; box-sizing:border-box; }
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
        .menu { flex: 1; display: flex; flex-direction: column; padding: 0 15px; }
        .menu a { display: flex; align-items: center; gap: 20px; padding: 14px 18px; margin-bottom: 5px; border-radius: 14px; text-decoration: none; color: rgba(255,255,255,0.6); transition: 0.3s; }
        .menu a i { font-size: 18px; min-width: 25px; text-align: center; }
        .menu a:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .menu a.active { background: var(--emerald-600); color: #fff; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); }
        .logout-link { margin-top: auto; margin-bottom: 20px; color: #f87171 !important; }

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

        .user-action-pill { display: flex; align-items: center; gap: 12px; padding: 6px 16px 6px 8px; background: #f8fafc; border-radius: 50px; border: 1px solid #e2e8f0; cursor: pointer; transition: 0.2s; }
        .user-action-pill:hover { border-color: var(--emerald-600); }
        .user-meta { text-align: right; }
        .u-name { display: block; font-size: 13px; font-weight: 700; color: var(--emerald-900); line-height: 1.2; }
        .u-role { display: block; font-size: 10px; color: var(--emerald-600); font-weight: 600; text-transform: uppercase; }
        .u-avatar-top { width: 38px; height: 38px; border-radius: 50%; background: var(--emerald-600); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .u-avatar-top img { width: 100%; height: 100%; object-fit: cover; }

        /* ===== MAIN CENTER CONTENT ===== */
        .content { 
            flex-grow: 1; margin-left: var(--sidebar-w); padding: calc(var(--nav-h) + 40px) 40px 40px;
            transition: margin-left 0.4s var(--ease); min-width: 0;
        }
        body.collapsed .content { margin-left: var(--sidebar-c); }
        
        /* REUSABLE UI COMPONENTS */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }

        .table-container { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; margin-bottom: 35px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 20px 15px; border-bottom: 1px solid #f8fafc; font-size: 14px; }
        
        .badge { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-stable { background: #f0fdf4; color: #16a34a; }
        .badge-critical { background: #fef2f2; color: #ef4444; }
        
        .status-pill { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-scheduled { background: #e0f2fe; color: #0369a1; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }

        .btn-action { background: var(--emerald-900); color: white; border: none; padding: 10px 22px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 13px; transition: var(--transition); text-decoration: none; display: inline-block; }
        .btn-action:hover { background: var(--emerald-600); }

        /* PROFILE MODAL */
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
        .input-wrapper input { width: 100%; padding: 14px 14px 14px 45px; border-radius: 15px; border: 1.5px solid #e2e8f0; background: #f8fafc; transition: var(--transition); }
        .input-wrapper input:focus { border-color: var(--emerald-600); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1); }

        .save-profile-btn { width: 100%; padding: 16px; border-radius: 16px; border: none; background: var(--emerald-900); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; transition: var(--transition); margin-top: 10px; }
        .save-profile-btn:hover { background: var(--emerald-600); transform: translateY(-2px); }
    </style>
</head>
<body class="<?= (isset($_SESSION['sidebar_collapsed']) && $_SESSION['sidebar_collapsed']) ? 'collapsed' : '' ?>">

<aside class="sidebar">
    <div class="side-head">
        <div class="logo-bundle">
            <div class="logo-icon"><i class="fa-solid fa-staff-snake"></i></div>
            <div class="logo-text">
                <h1 style="color:#fff; font-family:'Playfair Display'; font-size:16px; margin:0;">Hargeisa Staff</h1>
                <span style="color:var(--emerald-400); font-size:9px;">Est. 1953</span>
            </div>
        </div>
    </div>

    <p class="menu-label">Patient Care</p>
    <nav class="menu">
        <a href="nurse_dashboard.php?page=patients" class="<?= ($current_page == 'patients' || $current_page == 'home') ? 'active' : '' ?>">
            <i class="fa fa-user-injured"></i> <span>Patient Info</span>
        </a>
        <a href="nurse_dashboard.php?page=vitals" class="<?= ($current_page == 'vitals') ? 'active' : '' ?>">
            <i class="fa fa-heart-pulse"></i> <span>Vital Signs</span>
        </a>
        <a href="nurse_dashboard.php?page=notes" class="<?= ($current_page == 'notes') ? 'active' : '' ?>">
            <i class="fa fa-notes-medical"></i> <span>Nursing Notes</span>
        </a>
        <a href="logout.php" class="logout-link">
            <i class="fa fa-power-off"></i> <span>Logout System</span>
        </a>
    </nav>
</aside>

<header class="header">
    <div class="header-left">
        <button class="top-toggle-btn" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>
        <h2 style="font-family:'Playfair Display'; color:var(--emerald-900); margin:0; font-size:20px;">HGH Nurse Portal</h2>
    </div>

    <div class="user-action-pill" onclick="openProfileModal()">
        <div class="user-meta">
            <span class="u-name"><?= htmlspecialchars($nurseName) ?></span>
            <span class="u-role">Nurse Unit</span>
        </div>
        <div class="u-avatar-top">
            <?php 
                $profilePic = !empty($_SESSION['profile_photo']) ? 'uploads/'.$_SESSION['profile_photo'] : null;
                if($profilePic && file_exists($profilePic)): 
            ?>
                <img src="<?= $profilePic ?>" alt="User">
            <?php else: ?>
                <?= $initial ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="content">
    <?php 
    if ($current_page == 'vitals') {
        include("nurse_vitals.php");
    } elseif ($current_page == 'notes') {
        include("nurse_notes.php");
    } else {
        // DEFAULT ARCHITECTURE VIEW
    ?>
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e0f2fe; color:#0369a1;"><i class="fa fa-users"></i></div>
                <div><h3 style="font-size:24px;"><?= $totalPatients ?></h3><p style="color:var(--text-muted); font-size:12px; font-weight:600;">Total Active Patients</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f0fdf4; color:#16a34a;"><i class="fa-solid fa-heart-pulse"></i></div>
                <div><h3 style="font-size:24px;"><?= $vitalsRecordedToday ?></h3><p style="color:var(--text-muted); font-size:12px; font-weight:600;">Vitals Checked Today</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef2f2; color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div><h3 style="font-size:24px;">0</h3><p style="color:var(--text-muted); font-size:12px; font-weight:600;">Urgent Ward Alerts</p></div>
            </div>
        </div>

        <div class="table-container">
            <h3 style="margin-bottom: 25px; font-size: 18px;">Upcoming Intake Appointments</h3>
            <table>
                <thead>
                    <tr>
                        <th>Patient Name</th>
                        <th>Date Block</th>
                        <th>Time Window</th>
                        <th>Status</th>
                        <th style="text-align: right;">Record Vitals</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $appQuery = "SELECT a.appointment_id, a.patient_id, a.appointment_date, a.appointment_time, a.status, p.full_name 
                                 FROM appointments a 
                                 INNER JOIN patients p ON a.patient_id = p.patient_id 
                                 WHERE a.appointment_date >= '$today'
                                 ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 10";
                    $appRes = $conn->query($appQuery);
                    if ($appRes && $appRes->num_rows > 0):
                        while($appRow = $appRes->fetch_assoc()):
                            $statusClass = strtolower($appRow['status'] ?? 'scheduled');
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($appRow['full_name']) ?></strong></td>
                        <td><i class="fa-regular fa-calendar" style="margin-right: 5px; color: var(--text-muted);"></i> <?= date("d M Y", strtotime($appRow['appointment_date'])) ?></td>
                        <td><i class="fa-regular fa-clock" style="margin-right: 5px; color: var(--text-muted);"></i> <?= htmlspecialchars($appRow['appointment_time']) ?></td>
                        <td><span class="status-pill status-<?= $statusClass ?>"><?= htmlspecialchars($appRow['status']) ?></span></td>
                        <td style="text-align: right;">
                            <a href="nurse_dashboard.php?page=vitals&patient_id=<?= urlencode($appRow['patient_id']) ?>&appointment_id=<?= urlencode($appRow['appointment_id']) ?>" class="btn-action" style="background-color: var(--emerald-600);">
                                <i class="fa-solid fa-file-waveform" style="margin-right: 5px;"></i> Record Vitals
                            </a>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">No upcoming appointments listed for processing.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-container">
            <h3 style="margin-bottom: 25px; font-size: 18px;">Recent Active Patients</h3>
            <table>
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Full Name</th>
                        <th>Contact info</th>
                        <th>Medical Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT * FROM patients ORDER BY created_at DESC LIMIT 10";
                    $res = $conn->query($query);
                    if ($res && $res->num_rows > 0):
                        while($row = $res->fetch_assoc()):
                            $statusClass = (isset($row['status']) && $row['status'] == 'Critical') ? 'badge-critical' : 'badge-stable';
                            $statusText = $row['status'] ?? 'Stable';
                    ?>
                    <tr>
                        <td style="color:var(--text-muted); font-weight:600;">#PT-<?= $row['patient_id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['full_name'] ?? $row['username'] ?? 'Unknown Patient') ?></strong></td>
                        <td><span style="font-size:13px; color:#475569;"><?= htmlspecialchars($row['phone'] ?? $row['email'] ?? 'N/A') ?></span></td>
                        <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        <td style="text-align: right;">
                            <a href="nurse_dashboard.php?page=vitals&patient_id=<?= urlencode($row['patient_id']) ?>" class="btn-action">Record Vitals</a>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    else:
                    ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">No patient records found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</main>

<div id="pModal" class="modal-overlay">
    <div class="modal-card">
        <button class="modal-close-btn" onclick="closeProfileModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-header">
            <h3>Nurse Profile Update</h3>
            <p>Update your credentials and identity</p>
        </div>

        <form id="nurseProfileForm" enctype="multipart/form-data">
            <div class="image-upload-wrapper">
                <div class="profile-image-container">
                    <?php if($profilePic && file_exists($profilePic)): ?>
                        <img id="previewImg" src="<?= $profilePic ?>" alt="Profile">
                    <?php else: ?>
                        <div id="initialAvatar" class="initial-avatar-large"><?= $initial ?></div>
                        <img id="previewImg" src="" style="display:none;">
                    <?php endif; ?>

                    <label for="photoInput" class="camera-trigger">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                </div>
                <input type="file" name="photo" id="photoInput" hidden onchange="handleImagePreview(this)">
            </div>

            <div class="input-field">
                <label>Staff User Name</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user-doctor"></i>
                    <input type="text" name="name" value="<?= htmlspecialchars($nurseName) ?>" required>
                </div>
            </div>

            <div class="input-field">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" value="<?= htmlspecialchars($nurseEmail) ?>" required>
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
                <span>Save Nurse Changes</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>
    </div>
</div>

<script>
    function toggleSidebar() {
        document.body.classList.toggle('collapsed');
    }
    function openProfileModal() {
        document.getElementById('pModal').style.display = 'flex';
    }
    function closeProfileModal() {
        document.getElementById('pModal').style.display = 'none';
    }
</script>
</body>
</html>