<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include("db.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

/* ================= USER INFO ================= */
$doctor_id   = $_SESSION['user_id'];
$doctorName  = $_SESSION['username'] ?? $_SESSION['name'] ?? 'Doctor';
$initial     = strtoupper($doctorName[0]);
$page        = $_GET['page'] ?? 'home';

/* ================= DATABASE ANALYTICS ================= */
$today = date('Y-m-d');
function getCount($conn, $sql) {
    $result = $conn->query($sql);
    return ($result) ? $result->fetch_assoc()['total'] : 0;
}

$todayPatients = getCount($conn, "SELECT COUNT(*) as total FROM appointments WHERE doctor_id = '$doctor_id' AND appointment_date = '$today'");
$pendingLabs   = getCount($conn, "SELECT COUNT(*) as total FROM lab_requests WHERE doctor_id = '$doctor_id' AND status = 'pending'");
$scheduledAppt = getCount($conn, "SELECT COUNT(*) as total FROM appointments WHERE doctor_id = '$doctor_id' AND status = 'scheduled'");

/* ================= REAL DATA FOR TRENDS CHART ================= */
$days_of_week = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$trend_data = [0, 0, 0, 0, 0, 0, 0];

$trend_query = "SELECT DAYOFWEEK(appointment_date) as day_num, COUNT(*) as total 
                FROM appointments 
                WHERE doctor_id = '$doctor_id' 
                AND YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)
                GROUP BY DAYOFWEEK(appointment_date)";
$trend_result = $conn->query($trend_query);
if ($trend_result) {
    while($row = $trend_result->fetch_assoc()) {
        $index = ($row['day_num'] == 1) ? 6 : $row['day_num'] - 2;
        if($index >= 0 && $index < 7) $trend_data[$index] = (int)$row['total'];
    }
}

/* ================= REAL DATA FOR RATIO CHART ================= */
$ratio_labels = [];
$ratio_counts = [];

$ratio_query = "
    SELECT severity AS label, COUNT(*) AS total
    FROM diagnosis
    WHERE doctor_id = '$doctor_id'
      AND severity IS NOT NULL
      AND severity <> ''
    GROUP BY severity
    LIMIT 3
";

$ratio_result = $conn->query($ratio_query);

if ($ratio_result && $ratio_result->num_rows > 0) {
    while ($row = $ratio_result->fetch_assoc()) {
        $ratio_labels[] = $row['label'];
        $ratio_counts[] = (int)$row['total'];
    }
} else {
    $ratio_labels = ['Low', 'Medium', 'High'];
    $ratio_counts = [1, 1, 1];
}
/* ================= CALENDAR SCHEDULER ================= */
$monday_timestamp = strtotime('monday this week', strtotime($today));
$week_days_matrix = [];
$defined_time_slots = [
    '08:00:00' => '08:00 AM - 09:00 AM',
    '09:00:00' => '09:00 AM - 10:00 AM',
    '10:00:00' => '10:00 AM - 11:00 AM',
    '11:00:00' => '11:00 AM - 12:00 PM',
    '14:00:00' => '02:00 PM - 03:00 PM',
    '15:00:00' => '03:00 PM - 04:00 PM'
];

for ($i = 0; $i < 6; $i++) {
    $date_string = date('Y-m-d', strtotime("+$i days", $monday_timestamp));
    $week_days_matrix[$date_string] = [
        'day_name' => strtoupper(date('D', strtotime($date_string))),
        'day_num'  => date('d', strtotime($date_string)),
        'slots'    => [], 'booked_count' => 0,
        'total_slots' => count($defined_time_slots), 'day_color' => 'green'
    ];
    foreach ($defined_time_slots as $time_raw => $time_formatted) {
        $week_days_matrix[$date_string]['slots'][$time_raw] = ['formatted' => $time_formatted, 'status' => 'available', 'patient' => ''];
    }
}

$start_week_date = date('Y-m-d', $monday_timestamp);
$end_week_date   = date('Y-m-d', strtotime('+5 days', $monday_timestamp));
$cal_result = $conn->query("SELECT appointment_date, appointment_time, patient_name, status FROM appointments WHERE doctor_id = '$doctor_id' AND appointment_date BETWEEN '$start_week_date' AND '$end_week_date'");
if ($cal_result) {
    while ($booked = $cal_result->fetch_assoc()) {
        $b_date = $booked['appointment_date'];
        $b_time = strlen($booked['appointment_time']) == 5 ? $booked['appointment_time'].':00' : $booked['appointment_time'];
        if (isset($week_days_matrix[$b_date]['slots'][$b_time])) {
            $week_days_matrix[$b_date]['slots'][$b_time]['status']  = 'booked';
            $week_days_matrix[$b_date]['slots'][$b_time]['patient'] = $booked['patient_name'];
            $week_days_matrix[$b_date]['booked_count']++;
        }
    }
}
foreach ($week_days_matrix as $date_key => &$day_data) {
    if ($day_data['booked_count'] === 0) $day_data['day_color'] = 'green';
    elseif ($day_data['booked_count'] === $day_data['total_slots']) $day_data['day_color'] = 'red';
    else $day_data['day_color'] = 'orange';
}
unset($day_data);

/* ================= FETCH DOCTOR PUBLIC PROFILE DATA ================= */
$doc_profile_q = $conn->query("SELECT * FROM doctors WHERE user_id = '$doctor_id' LIMIT 1");
$doc_profile = ($doc_profile_q && $doc_profile_q->num_rows > 0) ? $doc_profile_q->fetch_assoc() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | HGH Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --emerald-900:#052e16; --emerald-600:#16a34a; --emerald-500:#22c55e;
            --emerald-400:#4ade80; --emerald-50:#f0fdf4;
            --slate-700:#334155; --slate-500:#64748b; --slate-100:#f1f5f9;
            --sidebar-w:280px; --sidebar-c:85px; --nav-h:80px;
            --ease:cubic-bezier(.4,0,.2,1);
            --transition:all 0.3s cubic-bezier(0.4,0,0.2,1);
            --color-green:#10b981; --color-orange:#f59e0b; --color-red:#ef4444;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:#f8fafc;overflow-x:hidden;display:flex;}

        /* SIDEBAR */
        .sidebar{position:fixed;left:0;top:0;width:var(--sidebar-w);height:100vh;background:var(--emerald-900);z-index:1001;transition:width 0.4s var(--ease);display:flex;flex-direction:column;overflow:hidden;}
        body.collapsed .sidebar{width:var(--sidebar-c);}
        .side-head{height:var(--nav-h);display:flex;align-items:center;padding:0 20px;background:rgba(0,0,0,0.2);}
        .logo-bundle{display:flex;align-items:center;gap:15px;text-decoration:none;min-width:200px;}
        .logo-icon{width:45px;height:45px;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--emerald-900);font-size:20px;flex-shrink:0;}
        .logo-text,.menu span,.menu-label{white-space:nowrap;transition:opacity 0.3s ease;opacity:1;}
        body.collapsed .logo-text,body.collapsed .menu span,body.collapsed .menu-label{opacity:0;pointer-events:none;}
        .menu-label{font-size:10px;color:rgba(255,255,255,0.3);text-transform:uppercase;letter-spacing:1.5px;margin:25px 20px 10px;}
        .menu{flex:1;display:flex;flex-direction:column;padding:0 15px;}
        .menu a{display:flex;align-items:center;gap:20px;padding:14px 18px;margin-bottom:5px;border-radius:14px;text-decoration:none;color:rgba(255,255,255,0.6);transition:0.3s;}
        .menu a i{font-size:18px;min-width:25px;text-align:center;}
        .menu a:hover{color:#fff;background:rgba(255,255,255,0.05);}
        .menu a.active{background:var(--emerald-600);color:#fff;box-shadow:0 4px 12px rgba(22,163,74,0.3);}
        .logout-link{margin-top:auto;margin-bottom:20px;color:#f87171 !important;}

        /* TOPBAR */
        .header{position:fixed;left:var(--sidebar-w);top:0;right:0;height:var(--nav-h);background:#fff;border-bottom:1px solid #eef2f6;display:flex;align-items:center;justify-content:space-between;padding:0 35px;transition:left 0.4s var(--ease);z-index:1000;}
        body.collapsed .header{left:var(--sidebar-c);}
        .header-left{display:flex;align-items:center;gap:20px;}
        .top-toggle-btn{background:#f1f5f9;border:none;width:42px;height:42px;border-radius:12px;color:var(--emerald-900);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px;transition:0.2s;}
        .top-toggle-btn:hover{background:var(--emerald-600);color:#fff;}
        .user-action-pill{display:flex;align-items:center;gap:12px;padding:6px 16px 6px 8px;background:#f8fafc;border-radius:50px;border:1px solid #e2e8f0;cursor:pointer;transition:0.2s;}
        .user-action-pill:hover{border-color:var(--emerald-600);}
        .user-meta{text-align:right;}
        .u-name{display:block;font-size:13px;font-weight:700;color:var(--emerald-900);line-height:1.2;}
        .u-role{display:block;font-size:10px;color:var(--emerald-600);font-weight:600;text-transform:uppercase;}
        .u-avatar-top{width:38px;height:38px;border-radius:50%;background:var(--emerald-600);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;overflow:hidden;border:2px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,0.1);}

        /* MAIN */
        .main{flex-grow:1;margin-left:var(--sidebar-w);padding:calc(var(--nav-h) + 40px) 40px 40px;transition:margin-left 0.4s var(--ease);min-width:0;}
        body.collapsed .main{margin-left:var(--sidebar-c);}
        .stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:25px;margin-bottom:30px;}
        .stat-card{background:#fff;padding:25px;border-radius:20px;border:1px solid #edf2f7;display:flex;align-items:center;gap:20px;transition:var(--transition);}
        .stat-card.clickable{cursor:pointer;position:relative;}
        .stat-card.clickable:hover{transform:translateY(-4px);box-shadow:0 12px 25px rgba(5,46,22,0.06);border-color:var(--emerald-400);}
        .stat-icon{width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:24px;}

        /* MODAL BASE */
        .modal-overlay{position:fixed;inset:0;background:rgba(5,46,22,0.45);backdrop-filter:blur(10px);display:none;align-items:center;justify-content:center;z-index:2000;padding:20px;}
        .modal-card{background:#fff;width:100%;max-width:460px;border-radius:32px;padding:40px;position:relative;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);animation:zoomIn 0.3s var(--ease);}
        .modal-card.calendar-large{max-width:1150px;}
        @keyframes zoomIn{from{transform:scale(0.95);opacity:0;}to{transform:scale(1);opacity:1;}}
        .modal-close-btn{position:absolute;top:25px;right:25px;width:36px;height:36px;border-radius:12px;background:var(--emerald-50);border:none;color:var(--emerald-900);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:var(--transition);}
        .modal-close-btn:hover{background:#fee2e2;color:#ef4444;}
        .modal-header{text-align:center;margin-bottom:25px;}
        .modal-header h3{font-family:'Playfair Display',serif;font-size:26px;color:var(--emerald-900);}
        .modal-header p{font-size:13px;color:var(--slate-500);margin-top:4px;}

        /* PROFILE MODAL PANELS */
        .profile-panel{display:none;}
        .profile-panel.active{display:block;}

        /* PROFILE TAB SWITCHER */
        .profile-tab-bar{display:flex;gap:6px;background:#f1f5f9;border-radius:14px;padding:5px;margin-bottom:28px;}
        .profile-tab{flex:1;padding:10px 6px;border-radius:10px;border:none;background:transparent;font-size:12px;font-weight:600;color:var(--slate-500);cursor:pointer;transition:0.2s;font-family:'Plus Jakarta Sans',sans-serif;display:flex;align-items:center;justify-content:center;gap:6px;}
        .profile-tab.active{background:#fff;color:var(--emerald-900);box-shadow:0 2px 8px rgba(0,0,0,0.08);}
        .profile-tab i{font-size:13px;}

        /* FORM FIELDS */
        .image-upload-wrapper{text-align:center;margin-bottom:26px;}
        .profile-image-container{position:relative;width:110px;height:110px;margin:0 auto 10px;}
        .profile-image-container img,.initial-avatar-large{width:110px;height:110px;border-radius:35px;object-fit:cover;border:4px solid var(--emerald-50);box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);}
        .initial-avatar-large{background:var(--emerald-600);color:#fff;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:700;}
        .camera-trigger{position:absolute;bottom:-5px;right:-5px;background:var(--emerald-900);color:#fff;width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;cursor:pointer;border:3px solid #fff;transition:var(--transition);}
        .camera-trigger:hover{background:var(--emerald-600);transform:scale(1.1);}
        .input-field{margin-bottom:16px;}
        .input-field label{display:block;font-size:12px;font-weight:700;color:var(--emerald-900);margin-bottom:7px;}
        .input-wrapper{position:relative;display:flex;align-items:center;}
        .input-wrapper i{position:absolute;left:15px;color:var(--emerald-600);font-size:14px;}
        .input-wrapper input,.input-wrapper select,.input-wrapper textarea{width:100%;padding:13px 14px 13px 45px;border-radius:14px;border:1.5px solid #e2e8f0;background:#f8fafc;transition:var(--transition);font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;color:#1e293b;}
        .input-wrapper textarea{padding-top:13px;resize:vertical;min-height:80px;}
        .input-wrapper input:focus,.input-wrapper select:focus,.input-wrapper textarea:focus{border-color:var(--emerald-600);background:#fff;outline:none;box-shadow:0 0 0 4px rgba(22,163,74,0.1);}

        /* BUTTONS */
        .save-btn{padding:13px 28px;border-radius:14px;border:none;background:var(--emerald-900);color:#fff;font-weight:700;font-size:13px;display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:var(--transition);font-family:'Plus Jakarta Sans',sans-serif;}
        .save-btn:hover{background:var(--emerald-600);transform:translateY(-1px);}
        .save-btn.full{width:100%;justify-content:center;margin-top:4px;}
        .save-btn.outline{background:transparent;border:1.5px solid var(--emerald-600);color:var(--emerald-600);}
        .save-btn.outline:hover{background:var(--emerald-50);}

        /* PUBLIC PROFILE TRIGGER BUTTON */
        .public-profile-trigger{width:100%;margin-top:14px;padding:11px;border-radius:14px;border:1.5px dashed #cbd5e1;background:transparent;color:var(--slate-500);font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:0.2s;font-family:'Plus Jakarta Sans',sans-serif;}
        .public-profile-trigger:hover{border-color:var(--emerald-600);color:var(--emerald-600);background:var(--emerald-50);}
        .public-profile-trigger i{font-size:14px;}

        /* PUBLIC PROFILE CARD PREVIEW */
        .pub-profile-card{background:linear-gradient(135deg,var(--emerald-900) 0%,#064e3b 100%);border-radius:20px;padding:24px;margin-bottom:20px;text-align:center;color:#fff;position:relative;overflow:hidden;}
        .pub-profile-card::before{content:'';position:absolute;width:180px;height:180px;border-radius:50%;border:1px solid rgba(255,255,255,0.07);top:-60px;right:-40px;}
        .pub-profile-card::after{content:'';position:absolute;width:120px;height:120px;border-radius:50%;border:1px solid rgba(255,255,255,0.05);bottom:-30px;left:-20px;}
        .pub-avatar{width:72px;height:72px;border-radius:22px;background:rgba(255,255,255,0.15);backdrop-filter:blur(4px);border:3px solid rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;margin:0 auto 12px;overflow:hidden;}
        .pub-avatar img{width:100%;height:100%;object-fit:cover;}
        .pub-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;margin-bottom:3px;}
        .pub-specialty{font-size:11px;color:var(--emerald-400);font-weight:600;text-transform:uppercase;letter-spacing:.8px;}
        .pub-divider{height:1px;background:rgba(255,255,255,0.1);margin:14px 0;}
        .pub-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;text-align:center;}
        .pub-stat-num{font-size:18px;font-weight:700;color:#fff;}
        .pub-stat-lbl{font-size:9px;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}

        /* FIELD GRID */
        .field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

        /* CALENDAR */
        .calendar-horizontal-scroll{width:100%;overflow-x:auto;padding-bottom:5px;}
        .calendar-wrapper{display:grid;grid-template-columns:repeat(6,1fr);gap:15px;min-width:950px;margin-top:10px;}
        .calendar-column{background:#f8fafc;border-radius:22px;padding:14px;border:1px solid #e2e8f0;display:flex;flex-direction:column;gap:12px;}
        .calendar-day-header{text-align:center;padding:12px 6px;border-radius:16px;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.01);border-top:5px solid transparent;transition:var(--transition);}
        .calendar-column.day-green .calendar-day-header{border-top-color:var(--color-green);}
        .calendar-column.day-orange .calendar-day-header{border-top-color:var(--color-orange);}
        .calendar-column.day-red .calendar-day-header{border-top-color:var(--color-red);}
        .calendar-day-header .day-title{font-size:11px;text-transform:uppercase;color:var(--slate-500);font-weight:700;letter-spacing:0.8px;}
        .calendar-day-header .day-number{font-size:24px;font-weight:700;color:var(--emerald-900);margin-top:2px;line-height:1;}
        .time-slot-pill{padding:15px 8px;border-radius:14px;font-size:11px;font-weight:700;text-align:center;transition:var(--transition);display:flex;flex-direction:column;gap:2px;justify-content:center;align-items:center;border:1px solid #e2e8f0;background:#fff;}
        .time-slot-pill.state-available{border-left:4px solid var(--color-green);color:var(--emerald-900);}
        .time-slot-pill.state-available .slot-status-lbl{font-size:9px;color:var(--color-green);font-weight:700;text-transform:uppercase;letter-spacing:0.3px;margin-top:2px;}
        .time-slot-pill.state-booked{background:var(--color-red);border-color:var(--color-red);color:#fff;box-shadow:0 4px 12px rgba(239,68,68,0.12);}
        .time-slot-pill .patient-lbl{font-size:10px;color:#ffe4e6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;margin-top:3px;width:100%;}
        .header-legend-container{display:flex;justify-content:center;gap:20px;margin:-10px 0 20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;}
        .legend-node-item{display:flex;align-items:center;gap:6px;color:var(--slate-500);}
        .legend-circle{width:10px;height:10px;border-radius:50%;display:inline-block;}
        .legend-circle.c-g{background:var(--color-green);}
        .legend-circle.c-o{background:var(--color-orange);}
        .legend-circle.c-r{background:var(--color-red);}

        /* SCROLLABLE MODAL BODY */
        .modal-body-scroll{max-height:calc(90vh - 200px);overflow-y:auto;padding-right:4px;scrollbar-width:thin;scrollbar-color:#e2e8f0 transparent;}
        .modal-body-scroll::-webkit-scrollbar{width:4px;}
        .modal-body-scroll::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:2px;}

        /* BADGE */
        .availability-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-top:6px;}
        .availability-badge.available{background:rgba(16,185,129,0.12);color:#059669;}
        .availability-badge.unavailable{background:rgba(239,68,68,0.1);color:#dc2626;}

        /* ── SPECIALIST PROFILE FORM (clean style) ── */
        .sp-field { margin-bottom: 20px; }
        .sp-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .09em;
            color: var(--emerald-900);
            margin-bottom: 8px;
        }
        .sp-input {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            color: #1e293b;
            background: #fff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            appearance: none;
            -webkit-appearance: none;
        }
        .sp-input:focus {
            border-color: var(--emerald-600);
            box-shadow: 0 0 0 3px rgba(22,163,74,.1);
        }
        .sp-textarea {
            resize: vertical;
            min-height: 90px;
            line-height: 1.6;
        }
        .sp-file {
            display: block;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px;
            color: var(--slate-500);
        }
        .sp-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 16px;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }
        .sp-btn-dismiss {
            background: none;
            border: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: var(--slate-500);
            cursor: pointer;
            padding: 10px 16px;
            border-radius: 10px;
            transition: .15s;
        }
        .sp-btn-dismiss:hover { color: var(--emerald-900); background: #f1f5f9; }
        .sp-btn-save {
            background: var(--emerald-900);
            color: #fff;
            border: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 700;
            padding: 13px 28px;
            border-radius: 14px;
            cursor: pointer;
            transition: .2s;
            letter-spacing: .01em;
        }
        .sp-btn-save:hover { background: var(--emerald-600); transform: translateY(-1px); box-shadow: 0 6px 20px rgba(22,163,74,.25); }
    </style>
</head>
<body class="<?= (isset($_SESSION['sidebar_collapsed']) && $_SESSION['sidebar_collapsed']) ? 'collapsed' : '' ?>">

<aside class="sidebar">
    <div class="side-head">
        <div class="logo-bundle">
            <div class="logo-icon"><i class="fa-solid fa-staff-snake"></i></div>
            <div class="logo-text">
                <h1 style="color:#fff;font-family:'Playfair Display';font-size:16px;margin:0;">Hargeisa Staff</h1>
                <span style="color:var(--emerald-400);font-size:9px;">Est. 1953</span>
            </div>
        </div>
    </div>
    <p class="menu-label">Analytics</p>
    <nav class="menu">
        <a href="?page=home" class="<?= $page=='home'?'active':'' ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
        <a href="?page=patients" class="<?= $page=='patients'?'active':'' ?>"><i class="fa-solid fa-user-group"></i><span>Patients</span></a>
        <a href="?page=diagnosis" class="<?= $page=='diagnosis'?'active':'' ?>"><i class="fa-solid fa-file-medical"></i><span>Diagnosis</span></a>
        <a href="?page=lab_request" class="<?= $page=='lab_request'?'active':'' ?>"><i class="fa-solid fa-flask"></i><span>Lab Requests</span></a>
        <a href="?page=lab_results" class="<?= $page=='lab_results'?'active':'' ?>"><i class="fa-solid fa-square-poll-vertical"></i><span>Lab Results</span></a>
        <a href="?page=prescription" class="<?= $page=='prescription'?'active':'' ?>"><i class="fa-solid fa-file-prescription"></i><span>Prescriptions</span></a>
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-power-off"></i><span>Logout System</span></a>
    </nav>
</aside>

<header class="header">
    <div class="header-left">
        <button class="top-toggle-btn" onclick="toggleSidebar()"><i class="fa-solid fa-bars-staggered"></i></button>
        <h2 style="font-family:'Playfair Display';color:var(--emerald-900);margin:0;font-size:20px;">HGH Doctor Portal</h2>
    </div>
    <div class="user-action-pill" onclick="openModal('profileModal')">
        <div class="user-meta">
            <span class="u-name"><?= htmlspecialchars($doctorName) ?></span>
            <span class="u-role">Medical Staff</span>
        </div>
        <div class="u-avatar-top">
            <?php
                $profilePic = !empty($_SESSION['profile_photo']) ? 'uploads/'.$_SESSION['profile_photo'] : null;
                if($profilePic && file_exists($profilePic)):
            ?>
                <img src="<?= $profilePic ?>" style="width:100%;height:100%;object-fit:cover;" alt="User">
            <?php else: ?>
                <?= $initial ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="main">
    <?php if($page == 'home'): ?>
        <?php if(isset($_GET['saved']) && $_GET['saved'] === 'public'): ?>
        <div style="display:flex;align-items:center;gap:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:14px 20px;margin-bottom:24px;font-size:13px;color:#15803d;font-weight:600;">
            <i class="fa-solid fa-circle-check" style="font-size:16px;"></i>
            Specialist profile saved successfully.
        </div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
        <div style="display:flex;align-items:center;gap:12px;background:#fef2f2;border:1px solid #fecaca;border-radius:14px;padding:14px 20px;margin-bottom:24px;font-size:13px;color:#b91c1c;font-weight:600;">
            <i class="fa-solid fa-circle-exclamation" style="font-size:16px;"></i>
            <?= $_GET['error'] === 'invalid_photo_type' ? 'Invalid photo format. Use JPG, PNG, GIF or WebP.' : ($_GET['error'] === 'photo_too_large' ? 'Photo exceeds 5MB limit.' : 'An error occurred. Please try again.') ?>
        </div>
        <?php endif; ?>
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#f0fdf4;color:var(--emerald-600);"><i class="fa-solid fa-user-injured"></i></div>
                <div class="stat-data"><h3><?= $todayPatients ?></h3><p>Today's Patients</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff;color:#2563eb;"><i class="fa-solid fa-vials"></i></div>
                <div class="stat-data"><h3><?= $pendingLabs ?></h3><p>Pending Labs</p></div>
            </div>
            <div class="stat-card clickable" onclick="openModal('calendarModal')">
                <div class="stat-icon" style="background:#fff7ed;color:#ea580c;"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="stat-data">
                    <h3><?= str_pad($scheduledAppt, 2, '0', STR_PAD_LEFT) ?></h3>
                    <p style="color:var(--slate-700);font-weight:500;">Weekly Schedule <i class="fa-solid fa-expand" style="font-size:10px;margin-left:3px;color:var(--slate-500)"></i></p>
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:25px;">
            <div style="background:#fff;padding:30px;border-radius:24px;border:1px solid #edf2f7;min-height:400px;">
                <h4 style="margin-bottom:20px;font-family:'Playfair Display';">Consultation Trends</h4>
                <div style="height:300px;"><canvas id="trendChart"></canvas></div>
            </div>
            <div style="background:#fff;padding:30px;border-radius:24px;border:1px solid #edf2f7;min-height:400px;">
                <h4 style="margin-bottom:20px;font-family:'Playfair Display';">Diagnosis Ratio</h4>
                <div style="height:300px;"><canvas id="ratioChart"></canvas></div>
            </div>
        </div>
    <?php else:
        $file = ($page == 'lab_results') ? 'doctor_lab_results.php' : 'doctor_'.htmlspecialchars($page).'.php';
        if(file_exists($file)) { include($file); }
        else { echo "<div style='padding:30px;background:#fff;border-radius:20px;border:1px solid #e2e8f0;'><p style='color:var(--slate-500);font-size:14px;'><i class='fa-solid fa-circle-notch fa-spin' style='margin-right:8px;color:var(--emerald-600)'></i>Section content loading...</p></div>"; }
    endif; ?>
</main>

<!-- ══ CALENDAR MODAL ══ -->
<div id="calendarModal" class="modal-overlay">
    <div class="modal-card calendar-large">
        <button class="modal-close-btn" onclick="closeModal('calendarModal')"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <h3>Doctor Master Schedule Roster</h3>
            <p>Weekly perspective view mapping patient allocations and shift workload codes</p>
        </div>
        <div class="header-legend-container">
            <div class="legend-node-item"><span class="legend-circle c-g"></span>All Shifts Free</div>
            <div class="legend-node-item"><span class="legend-circle c-o"></span>Partially Booked</div>
            <div class="legend-node-item"><span class="legend-circle c-r"></span>Completely Full</div>
        </div>
        <div class="calendar-horizontal-scroll">
            <div class="calendar-wrapper">
                <?php foreach ($week_days_matrix as $date_string => $day_info): ?>
                    <div class="calendar-column day-<?= $day_info['day_color'] ?>">
                        <div class="calendar-day-header">
                            <div class="day-title"><?= $day_info['day_name'] ?></div>
                            <div class="day-number"><?= $day_info['day_num'] ?></div>
                        </div>
                        <?php foreach ($day_info['slots'] as $time_raw => $slot): ?>
                            <?php if ($slot['status'] === 'booked'): ?>
                                <div class="time-slot-pill state-booked">
                                    <span class="slot-time-lbl"><?= $slot['formatted'] ?></span>
                                    <span class="patient-lbl" title="<?= htmlspecialchars($slot['patient']) ?>"><i class="fa-solid fa-user-check" style="font-size:8px;margin-right:2px;"></i><?= htmlspecialchars($slot['patient']) ?></span>
                                </div>
                            <?php else: ?>
                                <div class="time-slot-pill state-available">
                                    <span class="slot-time-lbl"><?= $slot['formatted'] ?></span>
                                    <span class="slot-status-lbl">Open Shift</span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ══ PROFILE MODAL (Account + Public Profile tabs) ══ -->
<div id="profileModal" class="modal-overlay">
    <div class="modal-card">
        <button class="modal-close-btn" onclick="closeModal('profileModal')"><i class="fa-solid fa-xmark"></i></button>

        <div class="modal-header" style="margin-bottom:18px;">
            <h3 id="modal-main-title">Staff Account Settings</h3>
            <p id="modal-main-sub">Update your professional identity and security</p>
        </div>

        <!-- Tab bar -->
        <div class="profile-tab-bar">
            <button class="profile-tab active" id="tab-account" onclick="switchProfileTab('account')">
                <i class="fa-solid fa-gear"></i> Account
            </button>
            <button class="profile-tab" id="tab-public" onclick="switchProfileTab('public')">
                <i class="fa-solid fa-id-card"></i> Public Profile
            </button>
        </div>

        <!-- ── PANEL 1: Account Settings ── -->
        <div class="profile-panel active" id="panel-account">
            <div class="modal-body-scroll">
                <form method="POST" action="update_profile.php" enctype="multipart/form-data">
                    <div class="image-upload-wrapper">
                        <div class="profile-image-container">
                            <?php if($profilePic && file_exists($profilePic)): ?>
                                <img id="previewImg" src="<?= $profilePic ?>" alt="Profile">
                            <?php else: ?>
                                <div id="initialAvatar" class="initial-avatar-large"><?= $initial ?></div>
                                <img id="previewImg" src="" style="display:none; width:110px;height:110px;border-radius:35px;object-fit:cover;">
                            <?php endif; ?>
                            <label for="photoInput" class="camera-trigger"><i class="fa-solid fa-camera"></i></label>
                        </div>
                        <input type="file" name="photo" id="photoInput" hidden accept="image/*" onchange="handleImagePreview(this)">
                    </div>
                    <div class="input-field">
                        <label>Professional Name</label>
                        <div class="input-wrapper"><i class="fa-solid fa-user-doctor"></i><input type="text" name="name" value="<?= htmlspecialchars($doctorName) ?>" required></div>
                    </div>
                    <div class="input-field">
                        <label>Registered Email</label>
                        <div class="input-wrapper"><i class="fa-solid fa-envelope"></i><input type="email" name="email" value="<?= htmlspecialchars($_SESSION['email'] ?? '') ?>" required></div>
                    </div>
                    <div class="input-field">
                        <label>Update Password</label>
                        <div class="input-wrapper"><i class="fa-solid fa-shield-halved"></i><input type="password" name="password" placeholder="Leave empty to keep current"></div>
                    </div>
                    <button type="submit" class="save-btn full" style="margin-top:6px;">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>
                </form>

                <!-- Public Profile trigger -->
                <button class="public-profile-trigger" onclick="switchProfileTab('public')">
                    <i class="fa-solid fa-id-card"></i>
                    View & Edit Public Profile
                    <i class="fa-solid fa-arrow-right" style="margin-left:auto;font-size:11px;"></i>
                </button>
            </div>
        </div>

        <!-- ── PANEL 2: Public Profile ── -->
        <div class="profile-panel" id="panel-public">
            <div class="modal-body-scroll">
                <form method="POST" action="update_public_profile.php" enctype="multipart/form-data">

                    <div class="sp-field">
                        <label class="sp-label">LINKED USER ACCOUNT</label>
                        <select name="user_account" class="sp-input">
                            <option value="<?= $doctor_id ?>">Dr. <?= htmlspecialchars($doctorName) ?></option>
                        </select>
                    </div>

                    <div class="sp-field">
                        <label class="sp-label">MEDICAL SPECIALIZATION</label>
                        <input type="text" name="specialization" class="sp-input" placeholder="e.g. OPHTHALMOLOGY" value="<?= htmlspecialchars($doc_profile['specialization'] ?? '') ?>">
                    </div>

                    <div class="sp-field">
                        <label class="sp-label">PROFESSIONAL BIO / DEPT</label>
                        <textarea name="department" class="sp-input sp-textarea" placeholder="e.g. Consultant Ophthalmologist (Visiting)"><?= htmlspecialchars($doc_profile['department'] ?? '') ?></textarea>
                    </div>

                    <div class="sp-field">
                        <label class="sp-label">SCHEDULE / AVAILABILITY</label>
                        <input type="text" name="availability" class="sp-input" placeholder="e.g. 8:00 - 4:00" value="<?= htmlspecialchars($doc_profile['availability'] ?? '') ?>">
                    </div>

                    <div class="sp-field">
                        <label class="sp-label">PHONE</label>
                        <input type="tel" name="phone" class="sp-input" placeholder="e.g. +252 XX XXX XXXX" value="<?= htmlspecialchars($doc_profile['phone'] ?? '') ?>">
                    </div>

                    <div class="sp-field">
                        <label class="sp-label">EMAIL</label>
                        <input type="email" name="email" class="sp-input" placeholder="doctor@email.com" value="<?= htmlspecialchars($doc_profile['email'] ?? '') ?>">
                    </div>

                    <div class="sp-field">
                        <label class="sp-label">PROFILE PHOTO</label>
                        <input type="file" name="photo" class="sp-file" accept="image/*">
                        <?php if (!empty($doc_profile['profile_photo'])): ?>
                            <p style="font-size:11px;color:var(--emerald-600);margin-top:6px;"><i class="fa-solid fa-circle-check"></i> Current: <?= htmlspecialchars(basename($doc_profile['profile_photo'])) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="sp-actions">
                        <button type="button" class="sp-btn-dismiss" onclick="switchProfileTab('account')">Dismiss</button>
                        <button type="submit" class="sp-btn-save">Save Specialist Profile</button>
                    </div>

                </form>
    </div>

<script>
function toggleSidebar() {
    document.body.classList.toggle('collapsed');
    const icon = document.querySelector('.top-toggle-btn i');
    icon.classList.toggle('fa-bars-staggered');
    icon.classList.toggle('fa-indent');
}
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function switchProfileTab(tab) {
    // Panels
    document.getElementById('panel-account').classList.toggle('active', tab === 'account');
    document.getElementById('panel-public').classList.toggle('active', tab === 'public');
    // Tabs
    document.getElementById('tab-account').classList.toggle('active', tab === 'account');
    document.getElementById('tab-public').classList.toggle('active', tab === 'public');
    // Title
    document.getElementById('modal-main-title').textContent = tab === 'account' ? 'Staff Account Settings' : 'Public Profile';
    document.getElementById('modal-main-sub').textContent   = tab === 'account' ? 'Update your professional identity and security' : 'Information visible to patients and staff directory';
}

function handleImagePreview(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('previewImg');
            const avatar  = document.getElementById('initialAvatar');
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (avatar) avatar.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

if (document.getElementById('trendChart')) {
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: { labels: <?= json_encode($days_of_week) ?>, datasets: [{ data: <?= json_encode($trend_data) ?>, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.04)', fill: true, tension: 0.4 }] },
        options: { responsive: true, maintainAspectRatio: false }
    });
}
if (document.getElementById('ratioChart')) {
    new Chart(document.getElementById('ratioChart').getContext('2d'), {
        type: 'doughnut',
        data: { labels: <?= json_encode($ratio_labels) ?>, datasets: [{ data: <?= json_encode($ratio_counts) ?>, backgroundColor: ['#052e16','#16a34a','#4ade80'] }] },
        options: { responsive: true, maintainAspectRatio: false }
    });
}
</script>
</body>
</html>
