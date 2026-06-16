<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include("db.php");

date_default_timezone_set('Asia/Kuala_Lumpur');

$user_id = $_SESSION['user_id'] ?? 0;

/* ================= HELPERS ================= */
function tableExists($conn, $table) {
    $table = mysqli_real_escape_string($conn, $table);
    $q = $conn->query("SHOW TABLES LIKE '$table'");
    return ($q && $q->num_rows > 0);
}

function columnExists($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($q && $q->num_rows > 0);
}

function futureSlotsOnly($slots, $date) {
    $today = date('Y-m-d');
    $nowTime = date('H:i');

    if ($date !== $today) {
        return $slots;
    }

    return array_values(array_filter($slots, function($slot) use ($nowTime) {
        return $slot > $nowTime;
    }));
}

function getDoctorSlots($conn, $doctor_id, $date) {
    $doctor_id = (int)$doctor_id;

    $default_slots = ['08:00','09:00','10:00','11:00','14:00','15:00','16:00'];

    if ($doctor_id <= 0 || empty($date)) {
        return $default_slots;
    }

    if (!tableExists($conn, "doctor_availability")) {
        return $default_slots;
    }

    $dayName = date('l', strtotime($date));

    $stmt = $conn->prepare("
        SELECT start_time, end_time
        FROM doctor_availability
        WHERE doctor_id = ?
        AND day_of_week = ?
        AND is_available = 1
        ORDER BY start_time ASC
    ");
    $stmt->bind_param("is", $doctor_id, $dayName);
    $stmt->execute();
    $res = $stmt->get_result();

    $slots = [];

    while ($row = $res->fetch_assoc()) {
        $start = strtotime($row['start_time']);
        $end   = strtotime($row['end_time']);

        while ($start < $end) {
            $slots[] = date('H:i', $start);
            $start = strtotime('+1 hour', $start);
        }
    }

    $slots = !empty($slots) ? array_values(array_unique($slots)) : $default_slots;

    return futureSlotsOnly($slots, $date);
}

/* ================= PATIENT PROFILE ================= */
$stmt_p = $conn->prepare("SELECT patient_id, full_name FROM patients WHERE user_id = ?");
$stmt_p->bind_param("i", $user_id);
$stmt_p->execute();
$p = $stmt_p->get_result();

$patient      = $p ? $p->fetch_assoc() : null;
$patient_id   = $patient['patient_id'] ?? 0;
$patient_name = $patient['full_name'] ?? 'Valued Patient';

if (!$patient_id) {
    echo "<script>
        alert('Please complete your clinical profile before booking an appointment.');
        window.location='?page=patient_profile';
    </script>";
    exit();
}

/* ================= DOCTORS LIST ================= */
$doctor_name_col = columnExists($conn, "users", "full_name") ? "u.full_name" : "u.username";

$specialization_sql = "'General Medicine' AS specialization";
$join_sql = "";

if (tableExists($conn, "doctors")) {
    if (columnExists($conn, "doctors", "user_id") && columnExists($conn, "doctors", "specialization")) {
        $specialization_sql = "COALESCE(d.specialization, 'General Medicine') AS specialization";
        $join_sql = "LEFT JOIN doctors d ON d.user_id = u.user_id";
    } elseif (columnExists($conn, "users", "specialization")) {
        $specialization_sql = "COALESCE(u.specialization, 'General Medicine') AS specialization";
    }
} elseif (columnExists($conn, "users", "specialization")) {
    $specialization_sql = "COALESCE(u.specialization, 'General Medicine') AS specialization";
}

$doctors = $conn->query("
    SELECT 
        u.user_id,
        $doctor_name_col AS doctor_name,
        $specialization_sql
    FROM users u
    $join_sql
    WHERE u.role = 'doctor'
    ORDER BY doctor_name ASC
");

/* ================= AJAX: DOCTOR SLOTS ================= */
if (isset($_GET['get_doctor_slots'])) {
    $date = $_GET['date'] ?? '';
    $doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

    $slots = getDoctorSlots($conn, $doctor_id, $date);

    header('Content-Type: application/json');
    echo json_encode($slots);
    exit();
}

/* ================= AJAX: TAKEN SLOTS ================= */
if (isset($_GET['get_slots'])) {
    $date = $_GET['date'] ?? '';
    $doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

    $taken = [];

    if ($doctor_id > 0 && !empty($date)) {
        $stmt_slots = $conn->prepare("
            SELECT appointment_time
            FROM appointments
            WHERE appointment_date = ?
            AND doctor_id = ?
            AND status != 'Cancelled'
        ");
        $stmt_slots->bind_param("si", $date, $doctor_id);
        $stmt_slots->execute();
        $res = $stmt_slots->get_result();

        while ($r = $res->fetch_assoc()) {
            $taken[] = substr($r['appointment_time'], 0, 5);
        }
    }

    header('Content-Type: application/json');
    echo json_encode($taken);
    exit();
}

/* ================= AJAX: MONTH STATUS ================= */
if (isset($_GET['get_month'])) {
    $year = (int)$_GET['year'];
    $month = (int)$_GET['month'];
    $doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $statuses = [];
    $today = date('Y-m-d');

    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);

        $doctorSlots = getDoctorSlots($conn, $doctor_id, $date);
        $totalSlots = count($doctorSlots);

        if ($date < $today || $totalSlots === 0) {
            $statuses[$date] = 'full';
            continue;
        }

        $takenCount = 0;

        if ($doctor_id > 0) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM appointments
                WHERE appointment_date = ?
                AND doctor_id = ?
                AND status != 'Cancelled'
            ");
            $stmt->bind_param("si", $date, $doctor_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $takenCount = (int)($res->fetch_assoc()['cnt'] ?? 0);
        }

        if ($takenCount === 0) {
            $statuses[$date] = 'free';
        } elseif ($takenCount >= $totalSlots) {
            $statuses[$date] = 'full';
        } else {
            $statuses[$date] = 'partial';
        }
    }

    header('Content-Type: application/json');
    echo json_encode($statuses);
    exit();
}

/* ================= FORM SUBMIT ================= */
if (isset($_POST['submit'])) {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $date      = $_POST['appointment_date'] ?? '';
    $time      = $_POST['appointment_time'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if (!$doctor_id || empty($date) || empty($time)) {
        echo "<script>alert('Please select a doctor, date and time slot before submitting.');</script>";
    } else {
        $allowedSlots = getDoctorSlots($conn, $doctor_id, $date);

        if (!in_array($time, $allowedSlots)) {
            echo "<script>alert('This time slot is no longer available. Please choose another time.');</script>";
        } else {
            $stmt_chk = $conn->prepare("
                SELECT appointment_id
                FROM appointments
                WHERE doctor_id = ?
                AND appointment_date = ?
                AND appointment_time = ?
                AND status != 'Cancelled'
                LIMIT 1
            ");
            $stmt_chk->bind_param("iss", $doctor_id, $date, $time);
            $stmt_chk->execute();
            $chk = $stmt_chk->get_result();

            if ($chk && $chk->num_rows > 0) {
                echo "<script>alert('That slot was just taken. Please choose another time.');</script>";
            } else {
                $status_default = "Pending";

                $stmt_ins = $conn->prepare("
                    INSERT INTO appointments
                    (patient_id, doctor_id, appointment_date, appointment_time, reason, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_ins->bind_param(
                    "iissss",
                    $patient_id,
                    $doctor_id,
                    $date,
                    $time,
                    $reason,
                    $status_default
                );

                if ($stmt_ins->execute()) {
                    echo "<script>alert('Appointment request sent successfully!'); window.location='?page=dashboard';</script>";
                    exit();
                } else {
                    if ($conn->errno == 1062) {
                        echo "<script>alert('This doctor slot is already booked. Please select another slot.');</script>";
                    } else {
                        echo "<script>alert('Database Error: Unable to process appointment.');</script>";
                    }
                }
            }
        }
    }
}
?>

<style>
.booking-wrapper { max-width: 1100px; margin: 0 auto; padding-top: 10px; }
.page-header { margin-bottom: 35px; border-bottom: 1px solid #edf2f7; padding-bottom: 20px; }
.page-header h1 { font-family: 'Playfair Display', serif; font-size: 32px; color: var(--g900, #111827); margin: 0; }
.page-header p { color: #64748b; margin-top: 5px; font-size: 15px; }
.booking-grid { display: grid; grid-template-columns: 1.7fr 1fr; gap: 30px; align-items: start; }
.saas-card { background: #fff; border: 1px solid #edf2f7; border-radius: 24px; padding: 35px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.02); transition: .3s ease; }
.saas-card:hover { border-color: #cbd5e1; }
.card-title { font-size: 13px; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; color: #4b5563; text-transform: uppercase; letter-spacing: 1.5px; }
.card-title::after { content:''; height:1px; flex:1; background: linear-gradient(to right, #edf2f7, transparent); }
.form-group { margin-bottom: 22px; }
.form-group label { display:block; font-size:11px; font-weight:800; color:#94a3b8; margin-bottom:8px; text-transform:uppercase; letter-spacing:.5px; }
.saas-input { width:100%; padding:14px 18px; border:1.5px solid #f1f5f9; border-radius:14px; font-size:14px; transition:.3s; background:#f8fafc; color:#111827; font-weight:500; font-family: inherit; box-sizing: border-box; }
.saas-input:focus { outline:none; border-color:#4b5563; background:#fff; box-shadow:0 0 0 4px rgba(22,163,74,.1); }
.info-note { background:#f0fdf4; border:1px solid #dcfce7; border-radius:16px; padding:18px; display:flex; gap:14px; margin:10px 0 20px; }
.info-note i { color:#4b5563; font-size:18px; margin-top:2px; }
.info-note p { margin:0; font-size:13px; color:#111827; line-height:1.5; font-weight:500; }
.doc-table { width:100%; border-collapse:collapse; }
.doc-table th { text-align:left; font-size:10px; color:#94a3b8; text-transform:uppercase; padding:12px 0; border-bottom:1px solid #f1f5f9; letter-spacing:1px; }
.doc-table td { padding:14px 0; font-size:14px; border-bottom:1px solid #f1f5f9; color:#111827; font-weight:600; }
.status-dot { height:8px; width:8px; background:#4b5563; border-radius:50%; display:inline-block; margin-right:8px; box-shadow:0 0 0 3px rgba(22,163,74,.1); }
.btn-submit { background:#111827; color:#fff; border:none; padding:18px 30px; border-radius:16px; font-weight:800; cursor:pointer; width:100%; transition:.3s; font-size:15px; display:flex; align-items:center; justify-content:center; gap:10px; font-family:inherit; }
.btn-submit:hover { background:#4b5563; transform:translateY(-2px); box-shadow:0 10px 20px rgba(22,163,74,.1); }
.btn-submit:disabled { opacity:.45; cursor:not-allowed; transform:none; }
.cal-container { margin-bottom: 24px; }
.cal-nav-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.cal-month-label { font-size: 15px; font-weight: 800; color: #111827; }
.cal-btn-group { display: flex; gap: 6px; }
.cal-btn { background: #f8fafc; border: 1.5px solid #edf2f7; border-radius: 10px; width: 34px; height: 34px; cursor: pointer; font-size: 16px; color: #64748b; display: flex; align-items: center; justify-content: center; transition: .2s; font-family: inherit; }
.cal-btn:hover { background: #f0fdf4; border-color: #cbd5e1; color: #111827; }
.cal-btn:disabled { opacity: .35; cursor: not-allowed; }
.cal-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 4px; margin-bottom: 14px; }
.day-name { text-align: center; font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; padding: 5px 0; }
.day-cell { aspect-ratio: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 10px; border: 1.5px solid transparent; font-size: 12px; font-weight: 700; cursor: pointer; transition: transform .15s, box-shadow .15s; gap: 3px; user-select: none; }
.day-num { line-height: 1; }
.day-dot { width: 5px; height: 5px; border-radius: 50%; }
.day-cell.empty { cursor: default; }
.day-cell.past { opacity: .3; cursor: not-allowed; }
.day-cell.loading { background: #f8fafc; animation: calPulse 1.2s infinite; }
@keyframes calPulse { 0%,100%{opacity:1}50%{opacity:.45} }
.day-cell.free { background: #dcfce7; border-color: #86efac; }
.day-cell.free .day-num { color: #166534; }
.day-cell.free .day-dot { background: #16a34a; }
.day-cell.free:hover { transform: scale(1.08); box-shadow: 0 4px 12px rgba(22,163,74,.25); }
.day-cell.partial { background: #fef3c7; border-color: #fcd34d; }
.day-cell.partial .day-num { color: #92400e; }
.day-cell.partial .day-dot { background: #d97706; }
.day-cell.partial:hover { transform: scale(1.08); box-shadow: 0 4px 12px rgba(217,119,6,.25); }
.day-cell.full { background: #fee2e2; border-color: #fca5a5; cursor: not-allowed; opacity: .75; }
.day-cell.full .day-num { color: #991b1b; }
.day-cell.full .day-dot { background: #ef4444; }
.day-cell.selected { outline: 3px solid #111827; outline-offset: 2px; }
.cal-legend { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 0; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #64748b; font-weight: 600; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; }
.slots-panel { margin-top: 24px; padding-top: 24px; border-top: 1px solid #edf2f7; display: none; }
.slots-day-label { font-size: 14px; font-weight: 800; color: #111827; margin-bottom: 16px; }
.slots-period { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: #94a3b8; margin-bottom: 10px; margin-top: 18px; }
.slots-period:first-child { margin-top: 0; }
.slots-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 8px; }
.slot-btn { padding: 12px 6px; border-radius: 12px; border: 1.5px solid; text-align: center; font-size: 12px; font-weight: 700; font-family: inherit; cursor: pointer; transition: transform .15s, box-shadow .15s; line-height: 1.3; background: none; }
.slot-end { font-size: 10px; font-weight: 500; opacity: .7; }
.slot-btn.avail { background: #dcfce7; border-color: #86efac; color: #166534; }
.slot-btn.avail:hover { transform: scale(1.05); box-shadow: 0 4px 10px rgba(22,163,74,.2); }
.slot-btn.taken { background: #fee2e2 !important; border-color: #ef4444 !important; color: #991b1b !important; cursor: not-allowed !important; }
.slot-btn.expired { background: #e5e7eb !important; border-color: #cbd5e1 !important; color: #64748b !important; cursor: not-allowed !important; opacity: 0.65; }
.slot-btn.chosen { background: #111827; border-color: #111827; color: #fff; box-shadow: 0 4px 14px rgba(5,46,22,.3); }
.break-bar { background: #f8fafc; border: 1.5px dashed #e2e8f0; border-radius: 12px; padding: 10px; text-align: center; font-size: 11px; font-weight: 700; color: #94a3b8; letter-spacing: .05em; margin-top: 18px; }
.slots-skeleton { display: grid; grid-template-columns: repeat(4,1fr); gap: 8px; }
.slot-skel { height: 52px; border-radius: 12px; background: #f8fafc; animation: calPulse 1.2s infinite; }
.chosen-badge { display: none; margin-top: 20px; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 14px; padding: 14px 18px; font-size: 13px; color: #166534; font-weight: 700; align-items: center; gap: 10px; }
.chosen-badge i { font-size: 16px; }
</style>

<div class="booking-wrapper">
    <div class="page-header">
        <h1>Book Appointment</h1>
        <p>Schedule a professional consultation for <b><?= htmlspecialchars($patient_name, ENT_QUOTES, 'UTF-8') ?></b></p>
    </div>

    <div class="booking-grid">
        <div class="saas-card">
            <div class="card-title">Consultation Details</div>

            <form method="POST" id="bookingForm">
                <input type="hidden" name="appointment_date" id="f_date">
                <input type="hidden" name="appointment_time" id="f_time">

                <div class="form-group">
                    <label>Assigned Specialist</label>
                    <select name="doctor_id" id="doctorSelect" class="saas-input" required onchange="onDoctorChange()">
                        <option value="">Select a Doctor...</option>
                        <?php if ($doctors && $doctors->num_rows > 0): ?>
                            <?php while ($d = $doctors->fetch_assoc()): ?>
                                <option value="<?= intval($d['user_id']) ?>">
                                    Dr. <?= htmlspecialchars($d['doctor_name'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($d['specialization'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Preferred Date</label>
                    <div class="cal-container">
                        <div class="cal-nav-bar">
                            <span class="cal-month-label" id="calMonthLabel"></span>
                            <div class="cal-btn-group">
                                <button type="button" class="cal-btn" id="prevBtn" onclick="changeMonth(-1)">&#8249;</button>
                                <button type="button" class="cal-btn" onclick="changeMonth(1)">&#8250;</button>
                            </div>
                        </div>
                        <div class="cal-grid" id="calGrid"></div>
                        <div class="cal-legend">
                            <div class="legend-item"><div class="legend-dot" style="background:#16a34a"></div>All open</div>
                            <div class="legend-item"><div class="legend-dot" style="background:#d97706"></div>Some taken</div>
                            <div class="legend-item"><div class="legend-dot" style="background:#ef4444"></div>Fully booked</div>
                        </div>
                    </div>
                </div>

                <div class="slots-panel" id="slotsPanel">
                    <div class="slots-day-label" id="slotsDayLabel"></div>
                    <div id="slotsContent"></div>
                    <div class="chosen-badge" id="chosenBadge">
                        <i class="fa-solid fa-circle-check"></i>
                        <span id="chosenText"></span>
                    </div>
                </div>

                <div class="form-group" style="margin-top:24px;">
                    <label>Reason for Consultation</label>
                    <textarea name="reason" class="saas-input" rows="4" placeholder="Describe symptoms or clinical concerns..." style="resize:none;" required></textarea>
                </div>

                <div class="info-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <p>Standard processing: Your request is sent to the HGH clinical triage. You will receive a notification once the status is updated.</p>
                </div>

                <button type="submit" name="submit" class="btn-submit" id="submitBtn">
                    <i class="fa-solid fa-calendar-check"></i> Request Appointment
                </button>
            </form>
        </div>

        <div class="info-column">
            <div class="saas-card" style="margin-bottom:25px; border-top:4px solid #4b5563;">
                <div class="card-title">On-Duty Specialists</div>
                <table class="doc-table">
                    <thead>
                        <tr><th>Physician</th><th>Specialization</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($doctors && $doctors->num_rows > 0):
                            $doctors->data_seek(0);
                            while ($row = $doctors->fetch_assoc()):
                        ?>
                        <tr>
                            <td><span class="status-dot"></span>Dr. <?= htmlspecialchars($row['doctor_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="color:#64748b;font-size:12px;font-weight:500;"><?= htmlspecialchars($row['specialization'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="2" style="color:#94a3b8;font-style:italic;">No active specialists found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="saas-card" style="background:#fff5f5;border:1px dashed #feb2b2;">
                <h4 style="margin:0 0 10px;font-size:14px;color:#c53030;display:flex;align-items:center;gap:8px;">
                    <i class="fa-solid fa-truck-medical"></i> Clinical Emergency?
                </h4>
                <p style="font-size:13px;color:#9b2c2c;line-height:1.6;margin:0;">
                    This form is for routine bookings only. For urgent care, please visit the <b>HGH Emergency Wing</b> or call <b>999</b> immediately.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
let AM_SLOTS = ['08:00','09:00','10:00','11:00'];
let PM_SLOTS = ['14:00','15:00','16:00'];
let ALL_SLOTS = [...AM_SLOTS, ...PM_SLOTS];

let viewYear, viewMonth;
let selDate = null;
let selSlot = null;
let monthCache = {};
let slotCache = {};
let doctorSlotCache = {};

(function init() {
    const n = new Date();
    viewYear = n.getFullYear();
    viewMonth = n.getMonth() + 1;
    loadAndRenderMonth();
})();

function onDoctorChange() {
    selDate = null;
    selSlot = null;
    monthCache = {};
    slotCache = {};
    doctorSlotCache = {};
    clearFields();
    hideSlotsPanel();
    loadAndRenderMonth();
}

function changeMonth(dir) {
    viewMonth += dir;
    if (viewMonth > 12) { viewMonth = 1; viewYear++; }
    if (viewMonth < 1) { viewMonth = 12; viewYear--; }

    const now = new Date();
    if (viewYear < now.getFullYear() || (viewYear === now.getFullYear() && viewMonth < now.getMonth()+1)) {
        viewMonth -= dir;
        return;
    }

    selDate = null;
    selSlot = null;
    clearFields();
    hideSlotsPanel();
    loadAndRenderMonth();
}

async function loadAndRenderMonth() {
    const docId = document.getElementById('doctorSelect').value;
    const ck = `${viewYear}-${viewMonth}-${docId}`;

    renderSkeleton();

    if (!monthCache[ck]) {
        try {
            const url = `?page=book_appointment&get_month=1&year=${viewYear}&month=${viewMonth}&doctor_id=${docId}`;
            const r = await fetch(url);
            monthCache[ck] = await r.json();
        } catch(e) {
            monthCache[ck] = {};
        }
    }

    renderCal(monthCache[ck]);
}

function renderSkeleton() {
    const grid = document.getElementById('calGrid');
    grid.innerHTML = '';
    renderDayNames(grid);

    const first = new Date(viewYear, viewMonth-1, 1).getDay();

    for (let i=0; i<first; i++) appendEl(grid,'div','day-cell empty');

    const days = new Date(viewYear, viewMonth, 0).getDate();

    for (let d=1; d<=days; d++) {
        const el = appendEl(grid,'div','day-cell loading');
        el.innerHTML = `<span class="day-num">${d}</span>`;
    }

    updateHeader();
}

function renderCal(statuses) {
    const grid = document.getElementById('calGrid');
    grid.innerHTML = '';
    renderDayNames(grid);

    const today = new Date();
    today.setHours(0,0,0,0);

    const first = new Date(viewYear, viewMonth-1, 1);

    for (let i=0; i<first.getDay(); i++) appendEl(grid,'div','day-cell empty');

    const days = new Date(viewYear, viewMonth, 0).getDate();

    for (let d=1; d<=days; d++) {
        const date = new Date(viewYear, viewMonth-1, d);
        const dow = date.getDay();
        const past = date < today;
        const weekend = dow === 5 || dow === 6;
        const dk = dateKey(date);
        const el = document.createElement('div');

        let cls = 'day-cell';

        if (past || weekend) {
            cls += ' past';
        } else {
            const st = statuses[dk] || 'free';
            cls += ' ' + st;

            if (st !== 'full') {
                el.addEventListener('click', () => pickDate(date));
            }
        }

        if (selDate && dateKey(selDate) === dk) cls += ' selected';

        el.className = cls;
        el.innerHTML = `<span class="day-num">${d}</span><span class="day-dot"></span>`;
        grid.appendChild(el);
    }

    updateHeader();
}

async function pickDate(date) {
    const docId = document.getElementById('doctorSelect').value;

    if (!docId) {
        alert('Please select a doctor first.');
        return;
    }

    selDate = date;
    selSlot = null;
    clearFields();

    const ck = `${viewYear}-${viewMonth}-${docId}`;
    renderCal(monthCache[ck] || {});

    const panel = document.getElementById('slotsPanel');
    panel.style.display = 'block';

    document.getElementById('slotsDayLabel').textContent =
        date.toLocaleDateString('en-GB', {weekday:'long', day:'numeric', month:'long', year:'numeric'});

    document.getElementById('slotsContent').innerHTML = buildSlotSkeleton();
    document.getElementById('chosenBadge').style.display = 'none';

    const dk = dateKey(date);
    const slotKey = `${dk}-${docId}`;

    if (!doctorSlotCache[slotKey]) {
        try {
            const r = await fetch(`?page=book_appointment&get_doctor_slots=1&date=${dk}&doctor_id=${docId}`);
            doctorSlotCache[slotKey] = await r.json();
        } catch(e) {
            doctorSlotCache[slotKey] = ALL_SLOTS;
        }
    }

    if (!slotCache[slotKey]) {
        try {
            const r = await fetch(`?page=book_appointment&get_slots=1&date=${dk}&doctor_id=${docId}`);
            slotCache[slotKey] = await r.json();
        } catch(e) {
            slotCache[slotKey] = [];
        }
    }

    renderSlots(new Set(slotCache[slotKey]), doctorSlotCache[slotKey]);
}

function renderSlots(taken, allowedSlots) {
    allowedSlots = allowedSlots || ALL_SLOTS;

    const morning = allowedSlots.filter(s => parseInt(s.split(':')[0]) < 12);
    const afternoon = allowedSlots.filter(s => parseInt(s.split(':')[0]) >= 12);

    let h = '';

    h += '<div class="slots-period">Morning</div>';
    h += '<div class="slots-row">' + (morning.length ? morning.map(s => slotHTML(s, taken.has(s))).join('') : '<div style="color:#94a3b8;font-size:12px;">No morning slots</div>') + '</div>';

    h += '<div class="break-bar"><i class="fa-solid fa-mug-saucer"></i>&nbsp; Lunch Break &nbsp;12:00 – 14:00 &nbsp;·&nbsp; Reception closed</div>';

    h += '<div class="slots-period">Afternoon</div>';
    h += '<div class="slots-row">' + (afternoon.length ? afternoon.map(s => slotHTML(s, taken.has(s))).join('') : '<div style="color:#94a3b8;font-size:12px;">No afternoon slots</div>') + '</div>';

    document.getElementById('slotsContent').innerHTML = h;
}

function slotHTML(time, isTaken) {
    const [h,m] = time.split(':');
    const end = String(+h+1).padStart(2,'0') + ':' + m;

    const todayKey = dateKey(new Date());
    const selectedKey = dateKey(selDate);
    const now = new Date();
    const currentTime = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');

    const isExpired = selectedKey === todayKey && time <= currentTime;
    const isChosen = selSlot === time;

    let cls = 'avail';
    let click = `onclick="pickSlot('${time}')"`;

    if (isTaken) {
        cls = 'taken';
        click = '';
    }

    if (isExpired) {
        cls = 'expired';
        click = '';
    }

    if (isChosen && !isTaken && !isExpired) {
        cls = 'chosen';
    }

    return `<button type="button" class="slot-btn ${cls}" ${click}>
        ${time}<br><span class="slot-end">${end}</span>
    </button>`;
}

function pickSlot(time) {
    selSlot = time;

    const dk = dateKey(selDate);
    const docId = document.getElementById('doctorSelect').value;
    const slotKey = `${dk}-${docId}`;

    renderSlots(new Set(slotCache[slotKey] || []), doctorSlotCache[slotKey] || ALL_SLOTS);

    document.getElementById('f_date').value = dk;
    document.getElementById('f_time').value = time;

    const [h,m] = time.split(':');
    const end = String(+h+1).padStart(2,'0') + ':' + m;
    const nice = selDate.toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'});

    document.getElementById('chosenText').textContent = `Appointment set — ${nice}, ${time} – ${end}`;
    document.getElementById('chosenBadge').style.display = 'flex';
}

function dateKey(d) {
    return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
}

function updateHeader() {
    document.getElementById('calMonthLabel').textContent =
        new Date(viewYear, viewMonth-1, 1).toLocaleDateString('en-GB',{month:'long',year:'numeric'});

    const now = new Date();

    document.getElementById('prevBtn').disabled =
        viewYear === now.getFullYear() && viewMonth === now.getMonth()+1;
}

function renderDayNames(grid) {
    ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(n => {
        const el = document.createElement('div');
        el.className = 'day-name';
        el.textContent = n;
        grid.appendChild(el);
    });
}

function appendEl(parent, tag, cls) {
    const el = document.createElement(tag);
    el.className = cls;
    parent.appendChild(el);
    return el;
}

function buildSlotSkeleton() {
    return '<div class="slots-skeleton">' + Array(7).fill('<div class="slot-skel"></div>').join('') + '</div>';
}

function clearFields() {
    document.getElementById('f_date').value = '';
    document.getElementById('f_time').value = '';
}

function hideSlotsPanel() {
    const panel = document.getElementById('slotsPanel');
    const badge = document.getElementById('chosenBadge');

    if(panel) panel.style.display = 'none';
    if(badge) badge.style.display = 'none';
}

document.getElementById('bookingForm').addEventListener('submit', function(e) {
    const docVal = document.getElementById('doctorSelect').value;
    const dateVal = document.getElementById('f_date').value;
    const timeVal = document.getElementById('f_time').value;

    if (!docVal || !dateVal || !timeVal) {
        e.preventDefault();
        alert('Please select a doctor, date and available time slot before booking.');
    }
});
</script>