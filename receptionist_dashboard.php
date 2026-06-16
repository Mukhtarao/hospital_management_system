<?php
/* ================= SAFE SESSION ================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("db.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'receptionist') {
    header("Location: index.php");
    exit();
}

/* ================= USER INFO ================= */
$recName  = $_SESSION['username'] ?? 'Receptionist';
$recEmail = $_SESSION['email'] ?? '';
$initial  = strtoupper($recName[0]);

/* ================= PAGE ROUTER ================= */
$page = $_GET['page'] ?? 'home';

/* ================= DATABASE ANALYTICS ================= */
function getCount($conn, $sql) {
    $result = $conn->query($sql);
    return ($result) ? $result->fetch_assoc()['total'] : 0;
}

$today = date('Y-m-d');
$todayReg     = getCount($conn, "SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = '$today'");
$totalPatients = getCount($conn, "SELECT COUNT(*) as total FROM patients");
$pendingAppt   = getCount($conn, "SELECT COUNT(*) as total FROM appointments WHERE appointment_date >= '$today' AND status='scheduled'");

/* ================= CHART DATA FETCHING ================= */
// 1. Registration Trend (Last 7 Days)
$days = [];
$regCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($date));
    $count = getCount($conn, "SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) = '$date'");
    $days[] = $label;
    $regCounts[] = $count;
}

// 2. Demographics (By Age)
$kids = getCount($conn, "SELECT COUNT(*) as total FROM patients WHERE age < 18");
$adults = getCount($conn, "SELECT COUNT(*) as total FROM patients WHERE age >= 18 AND age < 60");
$seniors = getCount($conn, "SELECT COUNT(*) as total FROM patients WHERE age >= 60");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard | HGH Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        .main { 
            flex-grow: 1; margin-left: var(--sidebar-w); padding: calc(var(--nav-h) + 40px) 40px 40px;
            transition: margin-left 0.4s var(--ease); min-width: 0;
        }
        body.collapsed .main { margin-left: var(--sidebar-c); }

        /* ===== CONTENT CARD STYLING ===== */
        .content-card { background: #ffffff; padding: 35px; border-radius: 24px; border: 1px solid #edf2f7; }

        /* ===== DASHBOARD STATS ===== */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 25px; border-radius: 20px; border: 1px solid #edf2f7; display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }

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

    <p class="menu-label">Reception Tasks</p>
    <nav class="menu">
        <a href="?page=home" class="<?= $page=='home'?'active':'' ?>"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
        <a href="?page=register_patient" class="<?= $page=='register_patient'?'active':'' ?>"><i class="fa-solid fa-user-plus"></i> <span>New Patient</span></a>
        <a href="?page=update_patient" class="<?= $page=='update_patient'?'active':'' ?>"><i class="fa-solid fa-user-pen"></i> <span>Update Info</span></a>
        <a href="?page=manage_appointments" class="<?= $page=='manage_appointments'?'active':'' ?>"><i class="fa-solid fa-calendar-check"></i> <span>Manage Appts</span></a>
        
        <a href="?page=billing" class="<?= $page=='billing'?'active':'' ?>"><i class="fa-solid fa-file-invoice-dollar"></i> <span>Billing & Dues</span></a>
        
        <a href="logout.php" class="logout-link">
            <i class="fa-solid fa-power-off"></i> <span>Logout System</span>
        </a>
    </nav>
</aside>

<header class="header">
    <div class="header-left">
        <button class="top-toggle-btn" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>
        <h2 style="font-family:'Playfair Display'; color:var(--emerald-900); margin:0; font-size:20px;">HGH Reception Portal</h2>
    </div>

    <div class="user-action-pill" onclick="openProfileModal()">
        <div class="user-meta">
            <span id="display_user" class="u-name"><?= htmlspecialchars($recName) ?></span>
            <span class="u-role">Front Desk</span>
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

<header class="header">
    <div class="header-left">
        <button class="top-toggle-btn" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>
        <h2 style="font-family:'Playfair Display'; color:var(--emerald-900); margin:0; font-size:20px;">HGH Reception Portal</h2>
    </div>

    <div class="user-action-pill" onclick="openProfileModal()">
        <div class="user-meta">
            <span id="display_user" class="u-name"><?= htmlspecialchars($recName) ?></span>
            <span class="u-role">Front Desk</span>
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

<main class="main">
    <?php if($page == 'home'): ?>
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#f0fdf4; color:var(--emerald-600);"><i class="fa-solid fa-id-card"></i></div>
                <div class="stat-data"><h3><?= $todayReg ?></h3><p>New Reg Today</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff; color:#2563eb;"><i class="fa-solid fa-users"></i></div>
                <div class="stat-data"><h3><?= $totalPatients ?></h3><p>Total Patients</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff7ed; color:#ea580c;"><i class="fa-solid fa-clock"></i></div>
                <div class="stat-data"><h3><?= $pendingAppt ?></h3><p>Pending Appts</p></div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:25px;">
            <div style="background:#fff; padding:30px; border-radius:24px; border:1px solid #edf2f7; height: 400px;">
                <h4 style="margin-bottom:20px; font-family:'Playfair Display';">Registration Growth (Last 7 Days)</h4>
                <div style="height: 300px;"><canvas id="regTrendChart"></canvas></div>
            </div>
            <div style="background:#fff; padding:30px; border-radius:24px; border:1px solid #edf2f7; height: 400px;">
                <h4 style="margin-bottom:20px; font-family:'Playfair Display';">Patient Demographics</h4>
                <div style="height: 300px;"><canvas id="demChart"></canvas></div>
            </div>
        </div>

    <?php else: 
        // Intercept and route the billing tag parameter safely to receptionist_billing.php
        if ($page == 'manage_appointments') {
            $filename = "receptionist_manage_appointments.php";
        } elseif ($page == 'billing') {
            $filename = "receptionist_billing.php";
        } else {
            $filename = "receptionist_" . htmlspecialchars($page) . ".php";
        }
        
        if(file_exists($filename)) { 
            // Strips out padding wrapper for billing view window to preserve grid stretch ratios
            if ($page == 'billing') {
                include($filename); 
            } else {
                echo '<div class="content-card">';
                include($filename); 
                echo '</div>';
            }
        } else { 
            echo "<div class='content-card'><h3>Module in Development</h3><p>The page <b>$page</b> is not linked yet.</p></div>"; 
        }
    endif; ?>
</main>

<div id="pModal" class="modal-overlay">
    <div class="modal-card">
        <button class="modal-close-btn" onclick="closeProfileModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-header">
            <h3>Receptionist Profile Update</h3>
            <p>Update your credentials and identity</p>
        </div>

        <form id="receptionProfileForm" enctype="multipart/form-data">
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
                <label>Display Name</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="name" value="<?= htmlspecialchars($recName) ?>" required>
                </div>
            </div>

            <div class="input-field">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" value="<?= htmlspecialchars($recEmail) ?>" required>
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
        const icon = document.querySelector('.top-toggle-btn i');
        if(document.body.classList.contains('collapsed')) {
            icon.classList.replace('fa-bars-staggered', 'fa-indent');
        } else {
            icon.classList.replace('fa-indent', 'fa-bars-staggered');
        }
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

    /* PROFILE AJAX SUBMISSION */
    document.getElementById('receptionProfileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const saveBtn = document.getElementById('saveBtn');
        const formData = new FormData(this);

        saveBtn.innerHTML = '<span>Saving...</span> <i class="fa-solid fa-spinner fa-spin"></i>';
        saveBtn.disabled = true;

        fetch('update_profile.php', { method: 'POST', body: formData })
        .then(() => {
            closeProfileModal();
            window.location.reload(); 
        })
        .catch(() => {
            alert("Error saving profiles settings.");
            saveBtn.innerHTML = '<span>Save Changes</span> <i class="fa-solid fa-arrow-right"></i>';
            saveBtn.disabled = false;
        });
    });

    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) { closeProfileModal(); }
    };

    // Registration Trend Chart
    const ctx1 = document.getElementById('regTrendChart')?.getContext('2d');
    if(ctx1){
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: <?= json_encode($days) ?>,
                datasets: [{ 
                    label: 'New Patients', 
                    data: <?= json_encode($regCounts) ?>, 
                    backgroundColor: '#16a34a', 
                    borderRadius: 8 
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // Demographics Chart
    const ctx2 = document.getElementById('demChart')?.getContext('2d');
    if(ctx2){
        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: ['Adults (18-60)', 'Children (<18)', 'Seniors (60+)'],
                datasets: [{ 
                    data: [<?= $adults ?>, <?= $kids ?>, <?= $seniors ?>], 
                    backgroundColor: ['#052e16', '#16a34a', '#4ade80'] 
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
</script>
</body>
</html>