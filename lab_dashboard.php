<?php
/* ================= SAFE SESSION ================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("db.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'lab') {
    header("Location: index.php?error=unauthorized");
    exit();
}

/* ================= USER INFO ================= */
$u_id       = $_SESSION['user_id'];
$user_query = mysqli_query($conn, "SELECT username, email FROM users WHERE user_id = '$u_id'");
$user_data  = mysqli_fetch_assoc($user_query);

$techName   = $user_data['username'] ?? 'Technician';
$techEmail  = $user_data['email'] ?? '';
$initial    = !empty($techName) ? strtoupper($techName[0]) : 'T';

/* ================= PAGE ROUTER ================= */
$page = $_GET['page'] ?? 'home';

/* ================= DATABASE ANALYTICS ================= */
function getCount($conn, $sql) {
    $result = $conn->query($sql);
    return ($result) ? $result->fetch_assoc()['total'] : 0;
}

$today = date('Y-m-d');
$pendingTests   = getCount($conn, "SELECT COUNT(*) as total FROM lab_request_tests WHERE status != 'Completed'");
$completedToday = getCount($conn, "SELECT COUNT(*) as total FROM lab_request_tests WHERE status = 'Completed' AND updated_at >= '$today 00:00:00'");
$totalRecords   = getCount($conn, "SELECT COUNT(*) as total FROM lab_request_tests WHERE status = 'Completed'");

/* ================= CHART DATA FETCHING ================= */
$days = [];
$testCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($date));
    $count = getCount($conn, "SELECT COUNT(*) as total FROM lab_request_tests WHERE DATE(updated_at) = '$date' AND status = 'Completed'");
    $days[] = $label;
    $testCounts[] = $count;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Portal | HGH Dashboard</title>
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
        .menu { flex: 1; display: flex; flex-direction: column; padding: 0; padding-left: 15px; padding-right: 15px; }
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
        .content-card { background: #ffffff; padding: 35px; border-radius: 24px; border: 1px solid #edf2f7; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.01); }

        /* ===== DASHBOARD STATS ===== */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 25px; border-radius: 20px; border: 1px solid #edf2f7; display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; }

        /* ===== PROFILE UPDATE MODAL ===== */
        .modal-overlay { position: fixed; inset: 0; background: rgba(5, 46, 22, 0.45); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; opacity: 0; transition: opacity 0.3s var(--ease); }
        .modal-overlay.active { display: flex; opacity: 1; }
        
        .modal-card { background: #ffffff; width: 100%; max-width: 720px; max-height: 90vh; border-radius: 32px; padding: 40px; position: relative; box-shadow: 0 25px 50px -12px rgba(4, 47, 22, 0.15); overflow-y: auto; transform: translateY(-20px) scale(0.98); transition: transform 0.3s var(--ease); }
        .modal-overlay.active .modal-card { transform: translateY(0) scale(1); }
        
        .modal-close-btn { position: absolute; top: 30px; right: 30px; width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; border: none; color: #475569; cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; z-index: 10; }
        .modal-close-btn:hover { transform: rotate(90deg); background: #fee2e2; color: #ef4444; }

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
        .input-wrapper input { width: 100%; padding: 14px 14px 14px 45px; border-radius: 15px; border: 1.5px solid #e2e8f0; background: #f8fafc; transition: var(--transition); font-size: 14px; }
        .input-wrapper input:focus { border-color: var(--emerald-600); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1); }

        .save-profile-btn { width: 100%; padding: 16px; border-radius: 16px; border: none; background: var(--emerald-900); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; transition: var(--transition); margin-top: 10px; }
        .save-profile-btn:hover { background: var(--emerald-600); transform: translateY(-2px); }
    </style>
</head>

<body class="<?= (isset($_SESSION['sidebar_collapsed']) && $_SESSION['sidebar_collapsed']) ? 'collapsed' : '' ?>">

<aside class="sidebar">
    <div class="side-head">
        <div class="logo-bundle">
            <div class="logo-icon"><i class="fa-solid fa-flask-vial"></i></div>
            <div class="logo-text">
                <h1 style="color:#fff; font-family:'Playfair Display'; font-size:16px; margin:0;">Hargeisa Staff</h1>
                <span style="color:var(--emerald-400); font-size:9px;">Est. 1953</span>
            </div>
        </div>
    </div>

    <p class="menu-label">Diagnostic Tasks</p>
    <nav class="menu">
        <a href="?page=home" class="<?= $page=='home'?'active':'' ?>"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
        <a href="?page=lab_requests" class="<?= $page=='lab_requests'?'active':'' ?>"><i class="fa-solid fa-list-check"></i> <span>Test Requests</span></a>
        
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
        <h2 style="font-family:'Playfair Display'; color:var(--emerald-900); margin:0; font-size:20px;">HGH Lab Portal</h2>
    </div>

    <div class="user-action-pill" onclick="openProfileModal()">
        <div class="user-meta">
            <span id="display_user" class="u-name"><?= htmlspecialchars($techName) ?></span>
            <span class="u-role">Lab Technician</span>
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
                <div class="stat-icon" style="background:#fff7ed; color:#ea580c;"><i class="fa-solid fa-hourglass-half"></i></div>
                <div class="stat-data"><h3 style="font-size:22px; font-weight:700; color:#1e293b;"><?= $pendingTests ?></h3><p style="font-size:13px; color:#64748b; margin-top:2px;">Pending Tests</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f0fdf4; color:var(--emerald-600);"><i class="fa-solid fa-vial-circle-check"></i></div>
                <div class="stat-data"><h3 style="font-size:22px; font-weight:700; color:#1e293b;"><?= $completedToday ?></h3><p style="font-size:13px; color:#64748b; margin-top:2px;">Completed Today</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff; color:#2563eb;"><i class="fa-solid fa-database"></i></div>
                <div class="stat-data"><h3 style="font-size:22px; font-weight:700; color:#1e293b;"><?= $totalRecords ?></h3><p style="font-size:13px; color:#64748b; margin-top:2px;">Total History</p></div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:25px;">
            <div style="background:#fff; padding:30px; border-radius:24px; border:1px solid #edf2f7; height: 400px;">
                <h4 style="margin-bottom:20px; font-family:'Playfair Display'; font-size:18px; color:var(--emerald-900);">Activity Analysis (Last 7 Days)</h4>
                <div style="height: 300px;"><canvas id="labActivityChart"></canvas></div>
            </div>
            
            <div style="background:#fff; padding:30px; border-radius:24px; border:1px solid #edf2f7; height: 400px;">
                <h4 style="margin-bottom:20px; font-family:'Playfair Display'; font-size:18px; color:var(--emerald-900);">Quick Actions</h4>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <a href="?page=lab_requests" style="text-decoration:none; padding:18px; background:#f8fafc; border-radius:15px; border:1px solid #e2e8f0; display:flex; align-items:center; gap:14px; color:var(--emerald-900); font-weight:600; font-size:14px; transition: 0.2s;">
                        <i class="fa-solid fa-plus-circle" style="color:var(--emerald-600); font-size: 18px;"></i> View New Requests
                    </a>
                </div>
            </div>
        </div>

    <?php else: 
        $filename = htmlspecialchars($page) . ".php";
        if(file_exists($filename)) { 
            echo '<div class="content-card">';
            include($filename); 
            echo '</div>';
        } else { 
            echo "<div class='content-card'><h3>Module in Development</h3><p>The page <b>$page</b> is not linked yet.</p></div>"; 
        }
    endif; ?>
</main>

<div id="uploadModal" class="modal-overlay" onclick="handleOverlayClick(event)">
    <div class="modal-card">
        <button type="button" onclick="closeUploadModal()" class="modal-close-btn" aria-label="Close form">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="uploadFormContainer">
            </div>
    </div>
</div>

<div id="pModal" class="modal-overlay" onclick="if(event.target===this) closeProfileModal()">
    <div class="modal-card" style="max-width: 480px;">
        <button class="modal-close-btn" onclick="closeProfileModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-header">
            <h3>Technician Profile Update</h3>
            <p style="margin-top:4px;">Update your credentials and identity</p>
        </div>

        <form id="labProfileForm" enctype="multipart/form-data">
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
                    <input type="text" name="name" value="<?= htmlspecialchars($techName) ?>" required>
                </div>
            </div>

            <div class="input-field">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" value="<?= htmlspecialchars($techEmail) ?>" required>
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

    function openProfileModal() {
        const modal = document.getElementById('pModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('active'), 10);
    }
    function closeProfileModal() {
        const modal = document.getElementById('pModal');
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    }

    function openUploadModal(testId) {
        const modal = document.getElementById("uploadModal");
        modal.style.display = "flex";
        setTimeout(() => modal.classList.add("active"), 10);

        document.getElementById("uploadFormContainer").innerHTML = `
            <div style="text-align:center; padding:60px 20px;">
                <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 38px; color: var(--emerald-600);"></i>
                <p style="margin-top:18px; color: #64748b; font-size: 14px; font-weight: 500;">Retrieving digital diagnostic structure...</p>
            </div>`;

        fetch("lab_upload.php?id=" + testId)
        .then(res => res.text())
        .then(htmlContent => {
            document.getElementById("uploadFormContainer").innerHTML = htmlContent;
        })
        .catch(() => {
            document.getElementById("uploadFormContainer").innerHTML = `
                <div style="text-align:center; padding:40px; color:#ef4444;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 32px;"></i>
                    <p style="margin-top:12px; font-weight:600;">Failed to establish connection to the target diagnostic slot.</p>
                </div>`;
        });
    }

    function closeUploadModal() {
        const modal = document.getElementById("uploadModal");
        modal.classList.remove("active");
        setTimeout(() => modal.style.display = "none", 300);
    }

    function handleOverlayClick(e) {
        if (e.target === document.getElementById("uploadModal")) {
            closeUploadModal();
        }
    }

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
    document.getElementById('labProfileForm').addEventListener('submit', function(e) {
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
            alert("Error saving profile settings.");
            saveBtn.innerHTML = '<span>Save Changes</span> <i class="fa-solid fa-arrow-right"></i>';
            saveBtn.disabled = false;
        });
    });

    // Lab Processed Trends Bar Chart
    const ctx1 = document.getElementById('labActivityChart')?.getContext('2d');
    if(ctx1){
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: <?= json_encode($days) ?>,
                datasets: [{ 
                    label: 'Tests Completed', 
                    data: <?= json_encode($testCounts) ?>, 
                    backgroundColor: '#16a34a', 
                    borderRadius: 8 
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
</script>
</body>
</html>