<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include_once("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    exit("Access Denied: Unauthorized role access.");
}

$doctor_id = $_SESSION['user_id'] ?? 0;

$workspace_search = $_GET['workspace_search'] ?? '';
$workspace_search_param = "%" . $workspace_search . "%";

$search = $_GET['search'] ?? '';

if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $stmt_patients = $conn->prepare("
        SELECT patient_id, first_name, last_name, full_name, age, gender, phone, created_at 
        FROM patients 
        WHERE full_name LIKE ? OR phone LIKE ? 
        ORDER BY full_name ASC
    ");
    $stmt_patients->bind_param("ss", $search_param, $search_param);
} else {
    $stmt_patients = $conn->prepare("
        SELECT patient_id, first_name, last_name, full_name, age, gender, phone, created_at 
        FROM patients 
        ORDER BY full_name ASC
    ");
}
$stmt_patients->execute();
$patients = $stmt_patients->get_result();

$stmt_app = $conn->prepare("
    SELECT 
        a.appointment_id,
        a.patient_id,
        a.appointment_date, 
        a.appointment_time, 
        a.status, 
        p.full_name,
        v.temperature,
        v.blood_pressure,
        v.heart_rate,
        v.respiratory_rate,
        v.oxygen_saturation,
        v.weight,
        v.notes AS nurse_notes,
        v.recorded_at AS vitals_time
    FROM appointments a 
    INNER JOIN patients p ON a.patient_id = p.patient_id 
    LEFT JOIN vitals v ON a.appointment_id = v.appointment_id
    WHERE a.doctor_id = ? 
      AND (
          a.appointment_date > CURDATE() 
          OR (a.appointment_date = CURDATE() AND a.appointment_time >= CURTIME())
      )
      AND p.full_name LIKE ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt_app->bind_param("is", $doctor_id, $workspace_search_param);
$stmt_app->execute();
$appointments = $stmt_app->get_result();
?>

<style>
    :root {
        --g900: #052e16;
        --g600: #16a34a; 
        --primary: #22c55e;
        --bg-main: #f8fafc;
        --orange-warn: #ea580c;
        --orange-bg: #fff7ed;
        --green-bg: #f0fdf4;
    }

    .page-title h2 { font-family: 'Playfair Display', serif; color: var(--g900); font-size: 28px; margin: 0; }
    .page-title p { color: #64748b; font-size: 14px; margin-top: 5px; }

    .table-control-header { display: flex; justify-content: space-between; align-items: center; padding: 25px 25px 15px; }
    .table-control-header h3 { font-family: 'Playfair Display', serif; color: var(--g900); margin: 0; font-size: 20px; }

    .search-bar-wrapper { 
        background: #fff; 
        padding: 8px 16px; 
        border-radius: 12px; 
        border: 1px solid #e2e8f0; 
        display: flex; 
        align-items: center; 
        gap: 10px; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.01); 
    }
    .search-bar-wrapper input { border: none; outline: none; font-size: 13.5px; width: 220px; background: transparent; }
    .search-bar-wrapper i { color: #64748b; font-size: 13px; }

    .search-container-bottom { display: flex; justify-content: flex-end; margin-bottom: 25px; }

    .theme-card { background: #fff; border-radius: 24px; border: 1px solid #edf2f7; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); overflow: hidden; margin-bottom: 30px; }
    .theme-card h3.padded-title { font-family: 'Playfair Display', serif; color: var(--g900); padding: 25px 25px 0; margin-bottom: 10px; font-size: 20px; }

    .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
    .custom-table thead { background: #f8fafc; border-bottom: 1px solid #eef2f6; }
    .custom-table th { padding: 18px 25px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; font-weight: 700; }
    .custom-table td { padding: 18px 25px; font-size: 14px; color: #1e293b; border-bottom: 1px solid #f1f5f9; }
    .custom-table tr:hover td { background: #f8fafc; }

    .view-btn { background: var(--g900); color: #fff; border: none; padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.3s; }
    .view-btn:hover { background: var(--g600); }

    .vitals-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all 0.2s ease-in-out;
    }
    .vitals-ready { background: var(--green-bg); color: #15803d; border-color: #bbf7d0; }
    .vitals-ready:hover { background: #15803d; color: #fff; }
    .vitals-missing { background: var(--orange-bg); color: var(--orange-warn); border-color: #ffedd5; cursor: not-allowed; }

    .status-pill { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .status-scheduled { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; }
    .modal-content { background: #fff; width: 100%; max-width: 500px; padding: 35px; border-radius: 24px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); }
    .close-icon-btn { position: absolute; top: 20px; right: 20px; width: 32px; height: 32px; border-radius: 50%; border: none; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; }
    .close-icon-btn:hover { background: #fee2e2; color: #ef4444; }
    .modal-close-bottom { display: block; width: 100%; margin-top: 25px; padding: 12px 0; background: var(--g900); color: #fff; border: none; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; }
    .modal-close-bottom:hover { background: var(--g600); }

    .vitals-grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
    .vital-box-card { background: #f8fafc; padding: 12px 16px; border-radius: 12px; border: 1px solid #f1f5f9; }
    .vital-box-card label { display: block; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 2px; }
    .vital-box-card span { font-size: 15px; font-weight: 600; color: #1e293b; }
</style>

<div class="page-header">
    <div class="page-title">
        <h2>Doctor Command Workspace</h2>
        <p>HGH Centralized Medical Database & Scheduling Desk</p>
    </div>
</div>

<div class="theme-card" style="margin-top: 25px;">
    <div class="table-control-header">
        <h3>Upcoming Appointments & Patient Intake Vitals</h3>
        <form method="GET" class="search-bar-wrapper">
            <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="workspace_search" value="<?= htmlspecialchars($workspace_search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Filter workflow by patient...">
        </form>
    </div>
    
    <table class="custom-table">
        <thead>
            <tr>
                <th>Patient Name</th>
                <th>Allocation Date</th>
                <th>Time Block</th>
                <th>Status</th>
                <th>Nurse Intake Vitals</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($appointments && $appointments->num_rows > 0): ?>
                <?php while ($row = $appointments->fetch_assoc()): 
                    $has_vitals = !empty($row['temperature']) || !empty($row['blood_pressure']) || !empty($row['nurse_notes']);
                    $vitals_json = $has_vitals ? json_encode([
                        'name' => $row['full_name'],
                        'temp' => $row['temperature'] ?? 'Not Recorded',
                        'bp' => $row['blood_pressure'] ?? 'Not Recorded',
                        'hr' => $row['heart_rate'] ?? 'Not Recorded',
                        'rr' => $row['respiratory_rate'] ?? 'Not Recorded',
                        'spo2' => $row['oxygen_saturation'] ?? 'Not Recorded',
                        'weight' => $row['weight'] ?? 'Not Recorded',
                        'notes' => !empty($row['nurse_notes']) ? $row['nurse_notes'] : 'No structural notes attached by staff.'
                    ], JSON_HEX_APOS | JSON_HEX_QUOT) : '{}';
                ?>
                <tr>
                    <td style="font-weight:600; color: var(--g900);"><?= htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><i class="fa-regular fa-calendar" style="margin-right:5px; color:#64748b;"></i> <?= date("d M Y", strtotime($row['appointment_date'])) ?></td>
                    <td><i class="fa-regular fa-clock" style="margin-right:5px; color:#64748b;"></i> <?= date("h:i A", strtotime($row['appointment_time'])) ?></td>
                    <td><span class="status-pill status-<?= strtolower(htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8')) ?>"><?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                        <?php if ($has_vitals): ?>
                            <div class="vitals-badge vitals-ready" onclick='openVitalsModal(<?= $vitals_json ?>)'>
                                <i class="fa-solid fa-heart-pulse"></i> Vitals Ready
                            </div>
                        <?php else: ?>
                            <div class="vitals-badge vitals-missing" title="No vital records added by nurse for this scheduling block">
                                <i class="fa-solid fa-triangle-exclamation"></i> Missing Vitals
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" align="center" style="padding:40px; color:#64748b;">No matching scheduled workflows found for the active timeline.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="search-container-bottom">
    <form method="GET" class="search-bar-wrapper">
        <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="workspace_search" value="<?= htmlspecialchars($workspace_search, ENT_QUOTES, 'UTF-8') ?>">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search master patient index...">
    </form>
</div>

<div class="theme-card">
    <h3 class="padded-title">Master Patient Index Lookup</h3>
    <table class="custom-table">
        <thead>
            <tr>
                <th>ID Reference</th>
                <th>Full Name</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Phone Matrix</th>
                <th>Clinical File</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($patients && $patients->num_rows > 0): ?>
                <?php while ($row = $patients->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:700; color:var(--g600);">#<?= intval($row['patient_id']) ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['age'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['gender'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($row['phone'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <button type="button" class="view-btn" onclick="viewPatient(<?= intval($row['patient_id']) ?>)">
                            <i class="fa-solid fa-folder-open"></i> Open Chart
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" align="center" style="padding:40px; color:#64748b;">No matching registry files located.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="vitalsModal" class="modal-overlay">
    <div class="modal-content">
        <button type="button" onclick="closeVitalsModal()" class="close-icon-btn">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 style="font-family:'Playfair Display', serif; margin-bottom:5px; color:var(--g900); font-size:22px; margin-top:0;">
            <i class="fa-solid fa-clipboard-user" style="color:var(--g600); margin-right:8px;"></i> Nurse Intake Sheet
        </h3>
        <p style="margin:0; font-size:13px; color:#64748b; margin-bottom:20px;">Patient chart profile: <strong id="vitalsTargetPatient" style="color:#1e293b;"></strong></p>
        <div class="vitals-grid-layout">
            <div class="vital-box-card"><label>Body Temperature</label><span id="lblTemp"></span> °F</div>
            <div class="vital-box-card"><label>Blood Pressure</label><span id="lblBP"></span> mmHg</div>
            <div class="vital-box-card"><label>Heart Rate</label><span id="lblHR"></span> bpm</div>
            <div class="vital-box-card"><label>Respiratory Rate</label><span id="lblRR"></span> breaths/min</div>
            <div class="vital-box-card" style="grid-column: span 2;"><label>Oxygen Saturation (SpO₂)</label><span id="lblOxygen"></span> %</div>
            <div class="vital-box-card" style="grid-column: span 2;"><label>Patient Weight</label><span id="lblWeight"></span> kg</div>
        </div>
        <div style="margin-top:20px; background:#f8fafc; padding:15px; border-radius:12px; border:1px solid #edf2f7;">
            <label style="display:block; font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700; margin-bottom:5px;"><i class="fa-solid fa-comment-medical"></i> Nurse Notes & Clinical Observations</label>
            <p id="lblNurseNotes" style="margin:0; font-size:13.5px; color:#334155; line-height:1.6; font-style:italic;"></p>
        </div>
        <button type="button" onclick="closeVitalsModal()" class="modal-close-bottom">Acknowledged & Dismiss</button>
    </div>
</div>

<div id="patientModal" class="modal-overlay">
    <div class="modal-content">
        <button type="button" onclick="closeModal()" class="close-icon-btn">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h3 style="font-family:'Playfair Display', serif; margin-bottom:25px; color:var(--g900); font-size:24px; margin-top:0;">Patient Details</h3>
        <div id="patientDetails" style="text-align:left; font-size:15px; line-height:1.8; color:#475569;"></div>
        <button type="button" onclick="closeModal()" class="modal-close-bottom"><i class="fa-solid fa-xmark"></i> Close</button>
    </div>
</div>

<script>
function openVitalsModal(data) {
    if (!data || !data.name) return;

    document.getElementById("vitalsTargetPatient").innerText = data.name;
    document.getElementById("lblTemp").innerText = data.temp;
    document.getElementById("lblBP").innerText = data.bp;
    document.getElementById("lblHR").innerText = data.hr;
    document.getElementById("lblRR").innerText = data.rr;
    document.getElementById("lblOxygen").innerText = data.spo2;
    document.getElementById("lblWeight").innerText = data.weight;
    document.getElementById("lblNurseNotes").innerText = data.notes;

    document.getElementById("vitalsModal").style.display = "flex";
}

function closeVitalsModal() {
    const modal = document.getElementById("vitalsModal");
    if (modal) modal.style.display = "none";
}

function viewPatient(id) {
    const modal = document.getElementById("patientModal");
    const container = document.getElementById("patientDetails");

    if (!modal || !container) return;

    modal.style.display = "flex";

    container.innerHTML = `
        <div style="text-align:center; padding:20px 0;">
            <i class="fa-solid fa-circle-notch fa-spin" style="font-size:24px; color:var(--g600); margin-bottom:10px;"></i>
            <p style="margin:0; font-size:14px; color:#64748b;">Fetching clinical record datasets...</p>
        </div>
    `;

    fetch("fetch_patient_details.php?id=" + encodeURIComponent(id))
        .then(res => {
            if (!res.ok) throw new Error("Network error");
            return res.text();
        })
        .then(data => {
            container.innerHTML = data;
        })
        .catch(() => {
            container.innerHTML = "<p style='color:#ef4444; font-weight:600;text-align:center;'>Error loading details.</p>";
        });
}

function closeModal() {
    const modal = document.getElementById("patientModal");
    const container = document.getElementById("patientDetails");

    if (modal) modal.style.display = "none";
    if (container) container.innerHTML = "";
}

document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        closeModal();
        closeVitalsModal();
    }
});

window.addEventListener("click", function(event) {
    const patientModal = document.getElementById("patientModal");
    const vitalsModal = document.getElementById("vitalsModal");

    if (event.target === patientModal) closeModal();
    if (event.target === vitalsModal) closeVitalsModal();
});
</script>