<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include("db.php");

/* ================= SECURITY & SESSION VALIDATION ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

// Fetch current active doctor ID from session context
$doctor_id = $_SESSION['user_id'] ?? 1; 
$current_date_focus = date('Y-m-d'); 

/* ================= GENERATE CALENDAR WEEK MATRIX (MON-SAT) ================= */
$monday_timestamp = strtotime('monday this week', strtotime($current_date_focus));
$week_days_matrix = [];

// Exact clinical shift hour blocks requested: 8-12 and 2-4 (12-14 structural break hidden)
$defined_time_slots = [
    '08:00:00' => '08:00 AM - 09:00 AM',
    '09:00:00' => '09:00 AM - 10:00 AM',
    '10:00:00' => '10:00 AM - 11:00 AM',
    '11:00:00' => '11:00 AM - 12:00 PM',
    '14:00:00' => '02:00 PM - 03:00 PM',
    '15:00:00' => '03:00 PM - 04:00 PM'
];

for ($i = 0; $i < 6; $i++) { // 6 Days: Monday to Saturday matching your matrix
    $date_string = date('Y-m-d', strtotime("+$i days", $monday_timestamp));
    $week_days_matrix[$date_string] = [
        'day_name'     => strtoupper(date('D', strtotime($date_string))),
        'day_num'      => date('d', strtotime($date_string)),
        'slots'        => [],
        'booked_count' => 0,
        'total_slots'  => count($defined_time_slots),
        'day_color'    => 'green' // Base initial configuration
    ];

    // Seed empty default slots
    foreach ($defined_time_slots as $time_raw => $time_formatted) {
        $week_days_matrix[$date_string]['slots'][$time_raw] = [
            'formatted' => $time_formatted,
            'status'    => 'available',
            'patient'   => ''
        ];
    }
}

/* ================= DATABASE LIVE SYNC & DATA MATCHING ================= */
$start_week_bound = date('Y-m-d', $monday_timestamp);
$end_week_bound   = date('Y-m-d', strtotime('+5 days', $monday_timestamp));

$query = "SELECT appointment_date, appointment_time, patient_name 
          FROM appointments 
          WHERE doctor_id = ? 
          AND appointment_date BETWEEN ? AND ?";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("iss", $doctor_id, $start_week_bound, $end_week_bound);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $db_date = $row['appointment_date'];
        $db_time = $row['appointment_time'];
        
        // Normalize time text syntax variables to prevent matching parsing issues
        $normalized_time = (strlen($db_time) == 5) ? $db_time . ":00" : $db_time;
        
        if (isset($week_days_matrix[$db_date]['slots'][$normalized_time])) {
            $week_days_matrix[$db_date]['slots'][$normalized_time]['status'] = 'booked';
            $week_days_matrix[$db_date]['slots'][$normalized_time]['patient'] = $row['patient_name'];
            $week_days_matrix[$db_date]['booked_count']++;
        }
    }
    $stmt->close();
}

/* ================= EVALUATE DAY-HEADER COLOR-CODE ALGORITHMS ================= */
foreach ($week_days_matrix as $date_key => &$day_data) {
    if ($day_data['booked_count'] === 0) {
        $day_data['day_color'] = 'green';  // All slots empty
    } elseif ($day_data['booked_count'] === $day_data['total_slots']) {
        $day_data['day_color'] = 'red';    // All slots full
    } else {
        $day_data['day_color'] = 'orange'; // Some are taken, some available
    }
}
unset($day_data); // Clear pointer reference safely
?>

<style>
    .master-calendar-card {
        background: #ffffff;
        border-radius: 32px;
        padding: 40px;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.02);
        max-width: 1200px;
        margin: 0 auto;
        border: 1px solid #edf2f7;
    }
    
    .calendar-head-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 35px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eef2f6;
    }

    .calendar-head-flex h3 {
        font-family: 'Playfair Display', serif;
        font-size: 26px;
        color: #052e16;
        font-weight: 700;
    }

    /* Roster Rule Key Legend Indicators */
    .roster-legend {
        display: flex;
        gap: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .legend-node { display: flex; align-items: center; gap: 8px; color: #64748b; }
    .status-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
    
    .status-dot.clr-green  { background: #10b981; }
    .status-dot.clr-orange { background: #f59e0b; }
    .status-dot.clr-red    { background: #ef4444; }

    .horizontal-scroll-viewport {
        width: 100%;
        overflow-x: auto;
        padding-bottom: 15px;
    }

    .calendar-columns-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 16px;
        min-width: 1000px; /* Safeguards data density columns sizing from distortion */
    }

    .calendar-day-pillar {
        border-radius: 24px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    /* Dynamic Header Ring Changes Dependent on Allocation Loads */
    .pillar-date-badge {
        text-align: center;
        padding: 14px;
        border-radius: 18px;
        background: #ffffff;
        border-top: 5px solid transparent;
        box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        transition: all 0.25s ease;
    }

    .calendar-day-pillar.hdr-green  .pillar-date-badge { border-top-color: #10b981; }
    .calendar-day-pillar.hdr-orange .pillar-date-badge { border-top-color: #f59e0b; }
    .calendar-day-pillar.hdr-red    .pillar-date-badge { border-top-color: #ef4444; }

    .pillar-date-badge .lbl-day-name {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 700;
        letter-spacing: 1px;
    }

    .pillar-date-badge .lbl-day-num {
        font-size: 26px;
        font-weight: 700;
        color: #052e16;
        margin-top: 2px;
        line-height: 1;
    }

    /* Hourly Work Allocation Slot Styling Node Elements */
    .time-slot-box {
        padding: 16px 10px;
        border-radius: 16px;
        text-align: center;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 2px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease-in-out;
    }

    /* Empty / Open State: Clean and highlighted in crisp emerald green border text outline */
    .time-slot-box.state-available {
        border-left: 4px solid #10b981;
    }
    .time-slot-box.state-available .txt-time { color: #052e16; font-weight: 700; font-size: 11px; }
    .time-slot-box.state-available .txt-status { color: #10b981; font-size: 9px; font-weight: 700; text-transform: uppercase; margin-top: 2px; letter-spacing: 0.3px; }

    /* Taken / Booked State: Turns solid red card with light text indicators */
    .time-slot-box.state-booked {
        background: #ef4444;
        border-color: #ef4444;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.12);
    }
    .time-slot-box.state-booked .txt-time { font-weight: 600; font-size: 11px; opacity: 0.95; }
    
    .time-slot-box .txt-patient-assignment {
        font-size: 10px;
        color: #ffe4e6; /* Cream highlight font color for smooth readability contrast over solid red block background profiles */
        font-weight: 600;
        white-space: nowrap;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 4px;
    }
</style>

<div class="master-calendar-card">
    <div class="calendar-head-flex">
        <div>
            <h3>Doctor Master Schedule Roster</h3>
            <p style="font-size: 13px; color: #64748b; margin-top: 3px;">Monitored operational hours, allocations, and patient capacity flags</p>
        </div>
        
        <div class="roster-legend">
            <div class="legend-node"><span class="status-dot clr-green"></span> All Slots Empty</div>
            <div class="legend-node"><span class="status-dot clr-orange"></span> Partially Booked</div>
            <div class="legend-node"><span class="status-dot clr-red"></span> Completely Full</div>
        </div>
    </div>

    <div class="horizontal-scroll-viewport">
        <div class="calendar-columns-grid">
            <?php foreach ($week_days_matrix as $date_string => $day_info): ?>
                <div class="calendar-day-pillar hdr-<?= $day_info['day_color'] ?>">
                    <div class="pillar-date-badge">
                        <div class="lbl-day-name"><?= $day_info['day_name'] ?></div>
                        <div class="lbl-day-num"><?= $day_info['day_num'] ?></div>
                    </div>
                    
                    <?php foreach ($day_info['slots'] as $time_raw => $slot): ?>
                        <?php if ($slot['status'] === 'booked'): ?>
                            <div class="time-slot-box state-booked">
                                <span class="txt-time"><?= $slot['formatted'] ?></span>
                                <span class="txt-patient-assignment" title="<?= htmlspecialchars($slot['patient']) ?>">
                                    <i class="fa-solid fa-user-injured" style="font-size: 8px; margin-right: 2px;"></i> 
                                    <?= htmlspecialchars($slot['patient']) ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="time-slot-box state-available">
                                <span class="txt-time"><?= $slot['formatted'] ?></span>
                                <span class="txt-status">Open Shift</span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>