<?php
/* ================= SESSION ================= */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include("db.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'pharmacy') {
    header("Location: index.php?error=unauthorized");
    exit();
}

/* ================= USER INFO ================= */
$pharmacyName = $_SESSION['username'] ?? 'Pharmacist';
$pharmacyEmail = $_SESSION['email'] ?? '';
$initial      = strtoupper($pharmacyName[0]);
$current_page = $_GET['page'] ?? 'home';

/* ================= ANALYTICS (For Home Page) ================= */
$today = date('Y-m-d');
$pendingCount = $conn->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'Pending'")->fetch_assoc()['total'] ?? 0;
$dispensedToday = $conn->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'Dispensed' AND DATE(created_at) = '$today'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Portal | HGH</title>
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

        .table-container { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #f1f5f9; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 650px; }
        th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 20px 15px; border-bottom: 1px solid #f8fafc; font-size: 14px; }
        
        .badge { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; }
        .badge-pending { background: #fff7ed; color: #c2410c; }
        .badge-dispensed { background: #f0fdf4; color: #16a34a; }

        .btn-dispense { background: var(--emerald-900); color: white; border: none; padding: 10px 22px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 13px; transition: var(--transition); }
        .btn-dispense:hover { background: var(--emerald-600); }
        .btn-done { color: var(--emerald-600); font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 6px; }

        /* MEDICINE DISPLAY STYLES */
        .med-pill-bundle { display: flex; flex-wrap: wrap; gap: 6px; max-width: 400px; }
        .med-pill { background: #f1f5f9; color: #334155; font-size: 12px; padding: 4px 10px; border-radius: 6px; font-weight: 500; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; }

        /* PROFILE MODAL */
        .modal-overlay { position: fixed; inset: 0; background: rgba(5, 46, 22, 0.6); backdrop-filter: blur(12px); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 2000; }
        .modal-card { background: #ffffff; width: 100%; max-width: 460px; border-radius: 32px; padding: 40px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); }

        /* RECEIPT MODAL FIX - only affects receipt popup, not profile modal */
        .receipt-modal-card {
            width: 95vw !important;
            max-width: 950px !important;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            border-radius: 24px;
        }
        #receiptModal #receiptContent {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            width: 100%;
        }
        #receiptModal #receiptContent .receipt-container {
            width: 100% !important;
            max-width: 760px !important;
            margin: 0 auto !important;
        }
        #receiptModal .no-print {
            display: flex;
            justify-content: center;
            margin-top: 20px !important;
        }
        @media (max-width: 768px) {
            .receipt-modal-card { padding: 18px; }
            #receiptModal .no-print button { width: 100%; padding: 14px 20px !important; }
        }

        .modal-close-btn { position: absolute; top: 25px; right: 25px; width: 36px; height: 36px; border-radius: 12px; background: var(--emerald-50); border: none; color: var(--emerald-900); cursor: pointer; transition: var(--transition); display: flex; align-items: center; justify-content: center; }
        .modal-close-btn:hover { background: #fee2e2; color: #ef4444; }
        .modal-header { text-align: center; margin-bottom: 30px; }
        .modal-header h3 { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--emerald-900); }
        .modal-header p { font-size: 13px; color: var(--slate-500); }

        .image-upload-wrapper { text-align: center; margin-bottom: 20px; }
        .profile-image-container { position: relative; width: 110px; height: 110px; margin: 0 auto 10px; }
        .profile-image-container img, .initial-avatar-large { width: 110px; height: 110px; border-radius: 35px; object-fit: cover; border: 4px solid var(--emerald-50); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .initial-avatar-large { background: var(--emerald-600); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 40px; font-weight: 700; }
        .camera-trigger { position: absolute; bottom: -5px; right: -5px; background: #0c0a09; color: #fff; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid #fff; transition: var(--transition); }
        .camera-trigger:hover { background: var(--emerald-600); transform: scale(1.1); }

        .input-field { margin-bottom: 20px; }
        .input-field label { display: block; font-size: 12px; font-weight: 700; color: var(--emerald-900); margin-bottom: 8px; }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        .input-wrapper i { position: absolute; left: 15px; color: var(--emerald-600); font-size: 14px; }
        .input-wrapper input { width: 100%; padding: 14px 14px 14px 45px; border-radius: 15px; border: 1.5px solid #e2e8f0; background: #f8fafc; transition: var(--transition); font-weight: 500; color: #1e293b; }
        .input-wrapper input:focus { border-color: var(--emerald-600); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1); }

        .save-profile-btn { width: 100%; padding: 16px; border-radius: 16px; border: none; background: #052e16; color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 10px; cursor: pointer; transition: var(--transition); margin-top: 10px; }
        .save-profile-btn:hover { background: var(--emerald-600); transform: translateY(-2px); }

        /* PRINT RECEIPT EXACTLY LIKE POPUP - A4 FORMAT */
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 14mm;
            }

            html,
            body {
                width: 210mm !important;
                min-height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body * {
                visibility: hidden !important;
            }

            #receiptModal,
            #receiptModal *,
            #receiptContent,
            #receiptContent * {
                visibility: visible !important;
            }

            #receiptModal {
                position: static !important;
                inset: auto !important;
                display: block !important;
                background: #ffffff !important;
                backdrop-filter: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                height: auto !important;
                z-index: auto !important;
            }

            #receiptModal .receipt-modal-card {
                position: static !important;
                width: 100% !important;
                max-width: none !important;
                height: auto !important;
                max-height: none !important;
                overflow: visible !important;
                padding: 0 !important;
                margin: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: #ffffff !important;
            }

            #receiptContent {
                position: static !important;
                display: block !important;
                width: 100% !important;
                margin: 0 auto !important;
                padding: 0 !important;
                background: #ffffff !important;
            }

            #receiptContent .receipt-container {
                width: 180mm !important;
                max-width: 180mm !important;
                min-height: auto !important;
                margin: 0 auto !important;
                padding: 18mm 12mm 14mm 12mm !important;
                border: 1px solid #dddddd !important;
                background: #ffffff !important;
                box-shadow: none !important;
                overflow: visible !important;
                zoom: 1 !important;
                transform: none !important;
                page-break-inside: avoid !important;
            }

            #receiptContent .logo img {
                height: 55px !important;
                max-height: 55px !important;
            }

            #receiptContent table.meds {
                width: 100% !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
            }

            #receiptContent thead {
                display: table-header-group !important;
            }

            #receiptContent tr {
                page-break-inside: avoid !important;
            }

            .no-print,
            .no-print *,
            .modal-close-btn,
            .modal-close-btn * {
                display: none !important;
                visibility: hidden !important;
            }
        }
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

    <p class="menu-label">Pharmacy Module</p>
    <nav class="menu">
        <a href="pharmacy_dashboard.php?page=home" class="<?= ($current_page == 'home') ? 'active' : '' ?>">
            <i class="fa fa-th-large"></i> <span>Dashboard</span>
        </a>
        <a href="pharmacy_dashboard.php?page=prescriptions" class="<?= ($current_page == 'prescriptions') ? 'active' : '' ?>">
            <i class="fa fa-file-prescription"></i> <span>Prescriptions</span>
        </a>
        <a href="pharmacy_dashboard.php?page=inventory" class="<?= ($current_page == 'inventory') ? 'active' : '' ?>">
            <i class="fa fa-boxes-stacked"></i> <span>Stock List</span>
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
        <h2 style="font-family:'Playfair Display'; color:var(--emerald-900); margin:0; font-size:20px;">HGH Pharmacy Portal</h2>
    </div>

    <div class="user-action-pill" onclick="openProfileModal()">
        <div class="user-meta">
            <span class="u-name"><?= htmlspecialchars($pharmacyName) ?></span>
            <span class="u-role">Pharmacist</span>
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
    if ($current_page == 'prescriptions') {
        include("pharmacy_prescriptions.php");
    } elseif ($current_page == 'inventory') {
        include("pharmacy_inventory.php");
    } else {
        // DEFAULT DASHBOARD VIEW
    ?>
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff7ed; color:#ea580c;"><i class="fa fa-hourglass-half"></i></div>
                <div><h3 style="font-size:24px;"><?= $pendingCount ?></h3><p style="color:var(--text-muted); font-size:12px; font-weight:600;">Waiting to Dispense</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f0fdf4; color:#16a34a;"><i class="fa-solid fa-clipboard-check"></i></div>
                <div><h3 style="font-size:24px;"><?= $dispensedToday ?></h3><p style="color:var(--text-muted); font-size:12px; font-weight:600;">Dispensed Today</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef2f2; color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div><h3 style="font-size:24px;">1</h3><p style="color:var(--text-muted); font-size:12px; font-weight:600;">Urgent Requests</p></div>
            </div>
        </div>

        <div class="table-container">
            <h3 style="margin-bottom: 25px; font-size: 18px;">Recent Prescriptions</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Medications</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // CONNECTED VIA EXACT SCHEMA STRUCT KEYS: pm.medicine_id = m.medicine_id
                    $query = "SELECT p.*, pt.full_name, GROUP_CONCAT(m.medicine_name SEPARATOR '||') as meds 
                              FROM prescriptions p 
                              JOIN patients pt ON p.patient_id = pt.patient_id 
                              LEFT JOIN prescription_medicines pm ON p.prescription_id = pm.prescription_id 
                              LEFT JOIN medicines m ON pm.medicine_id = m.medicine_id
                              GROUP BY p.prescription_id 
                              ORDER BY p.created_at DESC LIMIT 10";
                              
                    $res = $conn->query($query);

                    if (!$res) {
                        echo "<tr><td colspan='5' style='background:#fef2f2; color:#b91c1c; padding:20px; border-radius:12px;'>";
                        echo "<strong>SQL Joint Error:</strong> " . htmlspecialchars($conn->error) . "<br>";
                        echo "</td></tr>";
                    } else {
                        while($row = $res->fetch_assoc()):
                    ?>
                    <tr>
                        <td style="color:var(--text-muted); font-weight:600;">#PX-<?= $row['prescription_id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['full_name']) ?></strong></td>
                        <td>
                            <div class="med-pill-bundle">
                                <?php 
                                if (!empty($row['meds'])) {
                                    $medArray = explode('||', $row['meds']);
                                    foreach ($medArray as $medication) {
                                        echo '<span class="med-pill"><i class="fa-solid fa-pills" style="margin-right:5px; color:var(--emerald-600); font-size:10px;"></i>' . htmlspecialchars(trim($medication)) . '</span>';
                                    }
                                } else {
                                    echo '<span style="font-size:13px; color:#94a3b8; font-style:italic;">No items specified</span>';
                                }
                                ?>
                            </div>
                        </td>
                        <td><span class="badge <?= $row['status'] == 'Pending' ? 'badge-pending' : 'badge-dispensed' ?>"><?= $row['status'] ?></span></td>
                        <td style="text-align: right;">
                            <?php if($row['status'] == 'Pending'): ?>
                                <button class="btn-dispense" onclick="dispenseAndShowReceipt(<?= $row['prescription_id'] ?>)">Dispense</button>
                            <?php else: ?>
                                <span class="btn-done" onclick="showReceiptOnly(<?= $row['prescription_id'] ?>)" style="cursor:pointer; justify-content: flex-end;"><i class="fa fa-check-circle"></i> DISPENSED</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        endwhile; 
                    } 
                    ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</main>

<div id="receiptModal" class="modal-overlay">
    <div class="modal-card receipt-modal-card">
        <button class="modal-close-btn" onclick="closeModal()"><i class="fa fa-times"></i></button>
        <div id="receiptContent"><p>Loading...</p></div>
        <div class="no-print" style="display:flex; justify-content:center; margin-top:30px;">
            <button onclick="printReceiptOnly()" style="background:var(--emerald-600); color:white; border:none; padding:14px 60px; border-radius:12px; font-weight:700; cursor:pointer;">
                <i class="fa fa-print"></i> Print Receipt
            </button>
        </div>
    </div>
</div>

<div id="pModal" class="modal-overlay">
    <div class="modal-card">
        <button class="modal-close-btn" onclick="closeProfileModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="modal-header">
            <h3>Staff Account Settings</h3>
            <p>Update your professional identity and security</p>
        </div>

        <form id="pharmacyProfileForm" enctype="multipart/form-data">
            <div class="image-upload-wrapper">
                <div class="profile-image-container">
                    <?php if(isset($profilePic) && $profilePic && file_exists($profilePic)): ?>
                        <img id="previewImg" src="<?= $profilePic ?>" alt="Profile" style="border-radius:24px;">
                    <?php else: ?>
                        <div id="initialAvatar" class="initial-avatar-large" style="border-radius:24px; background-color:#16a34a; font-family:sans-serif;">P</div>
                        <img id="previewImg" src="" style="display:none; border-radius:24px;">
                    <?php endif; ?>

                    <label for="photoInput" class="camera-trigger">
                        <i class="fa-solid fa-camera" style="font-size:14px;"></i>
                    </label>
                </div>
                <small style="color:var(--slate-500); font-size:12px; display:block; margin-top:8px;">Click camera to change photo</small>
                <input type="file" name="photo" id="photoInput" hidden onchange="handleImagePreview(this)">
            </div>

            <div class="input-field">
                <label>Professional Name</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user-doctor"></i>
                    <input type="text" name="name" value="<?= htmlspecialchars($pharmacyName) ?>" required style="background-color:#fff;">
                </div>
            </div>

            <div class="input-field">
                <label>Registered Email</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" value="<?= htmlspecialchars($pharmacyEmail) ?>" required style="background-color:#f8fafc;">
                </div>
            </div>

            <div class="input-field">
                <label>Update Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-shield-halved"></i>
                    <input type="password" name="password" placeholder="••••••" style="background-color:#f8fafc;">
                </div>
            </div>

            <button type="submit" class="save-profile-btn" id="saveBtn">
                <span>Save Profile Changes</span>
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
    function closeModal() { document.getElementById('receiptModal').style.display = 'none'; }

    function printReceiptOnly() {
        const receiptBox = document.querySelector('#receiptContent .receipt-container');

        if (!receiptBox) {
            alert('Receipt is still loading. Please try again.');
            return;
        }

        const receiptHTML = receiptBox.outerHTML;
        const basePath = window.location.href.substring(0, window.location.href.lastIndexOf('/') + 1);
        const printWindow = window.open('', '_blank', 'width=900,height=700');

        if (!printWindow) {
            alert('Please allow pop-ups to print the receipt.');
            return;
        }

        printWindow.document.open();
        printWindow.document.write(`
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="${basePath}">
    <title>Medication Receipt</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        @page {
            size: A4 portrait;
            margin: 12mm 14mm;
        }

        html,
        body {
            width: 100%;
            min-height: 0 !important;
            background: #ffffff !important;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #111;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            padding: 0 !important;
            margin: 0 !important;
            overflow: hidden !important;
        }

        .print-page {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: #fff;
            page-break-after: avoid;
            page-break-before: avoid;
        }

        .receipt-container {
            width: 180mm !important;
            max-width: 180mm !important;
            min-height: auto !important;
            margin: 0 auto !important;
            padding: 18mm 12mm 14mm 12mm !important;
            border: 1px solid #dddddd !important;
            background: #ffffff !important;
            box-shadow: none !important;
            overflow: hidden !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .logo { text-align: center; margin-bottom: 8px; }
        .logo img { height: 55px !important; max-height: 55px !important; display: block; margin: 0 auto 6px; }
        .logo h2 { color: #052e16; font-size: 20px; font-weight: 700; line-height: 1.2; }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #052e16;
            margin: 10px 0 12px;
        }

        hr { border: none; border-top: 1px solid #ccc; margin-bottom: 14px; }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .info-table td { padding: 3px 6px; vertical-align: top; line-height: 1.5; }
        .info-table .lbl { font-weight: 700; white-space: nowrap; width: 130px; }
        .info-table .lbl2 { font-weight: 700; white-space: nowrap; width: 80px; text-align: right; padding-left: 20px; }
        .info-table .val2 { text-align: right; }

        .table-wrap { width: 100%; }
        table.meds {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 13px;
        }
        table.meds col.c-med { width: 30%; }
        table.meds col.c-dos { width: 15%; }
        table.meds col.c-frq { width: 20%; }
        table.meds col.c-dur { width: 15%; }
        table.meds col.c-qty { width: 20%; }
        table.meds th {
            background: #f3f4f6 !important;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #ddd;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
            color: #64748b;
        }
        table.meds td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            vertical-align: top;
            word-break: break-word;
            white-space: normal;
        }
        table.meds td:first-child { font-weight: 700; }

        .signature { margin-top: 50px; text-align: center; font-size: 13px; color: #333; }
        .sig-row { display: flex; align-items: flex-end; justify-content: center; gap: 6px; margin-bottom: 4px; }
        .sig-line { width: 180px; border-bottom: 1px solid #555; display: inline-block; }
        .sig-hint { color: #666; font-size: 12px; }

        .no-print,
        .no-print-btn,
        .modal-close-btn,
        button { display: none !important; }

        @media print {
            html, body { height: auto !important; overflow: hidden !important; }
            .print-page { page-break-after: avoid !important; }
            .receipt-container { page-break-inside: avoid !important; break-inside: avoid !important; }
        }
    </style>
</head>
<body>
    <div class="print-page">
        ${receiptHTML}
    </div>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
                window.close();
            }, 300);
        };
    <\/script>
</body>
</html>
        `);
        printWindow.document.close();
    }

    function dispenseAndShowReceipt(id) {
    if (!confirm('Confirm medicine dispensing and generate invoice?')) {
        return;
    }

    fetch('update_dispense.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
        console.log(data);

        if (data.success) {
            alert(data.message);
            showReceiptOnly(id);

            setTimeout(function() {
                window.location.reload();
            }, 1200);
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        alert('Dispense failed: ' + error);
    });
}
    

    function showReceiptOnly(id) {
        document.getElementById('receiptModal').style.display = 'flex';
        fetch('get_receipt.php?id=' + id)
            .then(res => res.text())
            .then(html => { document.getElementById('receiptContent').innerHTML = html; });
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
    document.getElementById('pharmacyProfileForm').addEventListener('submit', function(e) {
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
            saveBtn.innerHTML = '<span>Save Profile Changes</span> <i class="fa-solid fa-arrow-right"></i>';
            saveBtn.disabled = false;
        });
    });

    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) { 
            closeProfileModal(); 
            closeModal();
        }
    };
</script>
</body>
</html>