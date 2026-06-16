<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include_once("db.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    exit("<div class='theme-alert alert-error'>Access Denied: Unauthorized portal context.</div>");
}

$success = "";
$error = "";

/* ================= HANDLE FORM ================= */
if (isset($_POST['submit_request'])) {

    $patient_id     = (int)$_POST['patient_id'];
    $patient_name   = mysqli_real_escape_string($conn, trim($_POST['patient_name']));
    $doctor_id      = $_SESSION['user_id'] ?? 1;
    $urgency_level  = mysqli_real_escape_string($conn, $_POST['urgency_level']);
    $clinical_notes = !empty($_POST['clinical_notes']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['clinical_notes'])) . "'" : "NULL";
    $tests          = $_POST['tests'] ?? [];

    /* ================= PRICE CATALOG ================= */
    $price_catalog = [
        'Blood Test' => 35.00,
        'Urine Test' => 20.00,
        'X-Ray'      => 50.00,
        'CT Scan'    => 120.00,
        'MRI'        => 200.00,
        'Ultrasound' => 60.00
    ];

    /* ================= VALIDATE PATIENT ================= */
    $check_patient = mysqli_query($conn, "SELECT patient_id FROM patients WHERE patient_id = $patient_id LIMIT 1");

    if (mysqli_num_rows($check_patient) === 0) {
        $error = "Patient ID does not exist. Please enter a valid record identifier.";
    }
    elseif (empty($patient_name)) {
        $error = "Patient identification name string is required.";
    }
    elseif (count($tests) === 0) {
        $error = "Clinical validation error: Please select at least one laboratory test.";
    }
    else {

        /* ================= FIND OR CREATE ACTIVE VISIT ================= */
        $visit_query = mysqli_query($conn, "
            SELECT visit_id 
            FROM patient_visits 
            WHERE patient_id = $patient_id 
            AND status != 'Discharged'
            ORDER BY visit_date DESC 
            LIMIT 1
        ");

        if ($visit_query && mysqli_num_rows($visit_query) > 0) {
            $visit_row = mysqli_fetch_assoc($visit_query);
            $visit_id = (int)$visit_row['visit_id'];

            mysqli_query($conn, "
                UPDATE patient_visits 
                SET status = 'Lab_Pending'
                WHERE visit_id = $visit_id
            ");
        } else {
            mysqli_query($conn, "
                INSERT INTO patient_visits (patient_id, status) 
                VALUES ($patient_id, 'Lab_Pending')
            ");
            $visit_id = mysqli_insert_id($conn);
        }

        /* ================= INSERT LAB REQUEST WITH VISIT ID ================= */
        $insert = mysqli_query($conn, "
            INSERT INTO lab_requests
            (
                patient_id,
                visit_id,
                patient_name,
                doctor_id,
                urgency_level,
                clinical_notes
            )
            VALUES
            (
                $patient_id,
                $visit_id,
                '$patient_name',
                $doctor_id,
                '$urgency_level',
                $clinical_notes
            )
        ");

        if ($insert) {
            $request_id = mysqli_insert_id($conn);

            /* ================= INSERT SELECTED TESTS WITH COST ================= */
            foreach ($tests as $test) {
                $test = mysqli_real_escape_string($conn, $test);
                $cost = isset($price_catalog[$test]) ? $price_catalog[$test] : 0.00;

                mysqli_query($conn, "
                    INSERT INTO lab_request_tests 
                    (
                        lab_request_id,
                        test_type,
                        test_cost,
                        status
                    )
                    VALUES 
                    (
                        $request_id,
                        '$test',
                        $cost,
                        'Pending'
                    )
                ");
            }

            $success = "Laboratory diagnostic request routed and logged to billing successfully.";
        } else {
            $error = "Pipeline Error: Failed to commit lab queries. " . mysqli_error($conn);
        }
    }
}
?>

<style>
    :root {
        --g900: #052e16; 
        --g600: #16a34a; 
        --g100: #f0fdf4; 
        --border-color: #e2e8f0;
        --bg-input: #f8fafc;
    }

    .dashboard-center-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 85vh;
        width: 100%;
        padding: 30px 20px;
        box-sizing: border-box;
    }

    .form-header-group {
        text-align: center;
        margin-bottom: 30px;
    }

    .theme-title { font-family: 'Playfair Display', serif; color: var(--g900); font-size: 32px; margin: 0; margin-bottom: 8px; }
    .theme-subtitle { color: #64748b; font-size: 15px; margin: 0; }

    .theme-card {
        background: #fff;
        padding: 45px;
        border-radius: 24px;
        border: 1px solid #edf2f7;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.06);
        width: 100%;
        max-width: 1100px;
        box-sizing: border-box;
    }

    .theme-label {
        display: block;
        font-size: 12.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 10px;
        letter-spacing: 0.6px;
    }

    .theme-input {
        width: 100%;
        padding: 15px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        font-family: inherit;
        font-size: 14.5px;
        margin-bottom: 24px;
        box-sizing: border-box;
        transition: all 0.2s ease-in-out;
        color: #1e293b;
    }

    .theme-input:focus {
        outline: none;
        border-color: var(--g600);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.06);
    }

    .test-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .test-item {
        display: flex;
        align-items: center;
        gap: 14px;
        font-size: 14px;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        background: var(--bg-input);
        padding: 16px 20px;
        border-radius: 14px;
        border: 1px solid var(--border-color);
        user-select: none;
        transition: all 0.15s ease-in-out;
    }

    .test-item:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    .test-item.is-selected {
        background: var(--g100);
        border-color: var(--g600);
        color: var(--g900);
    }

    .test-item input[type="checkbox"] {
        accent-color: var(--g600);
        width: 20px;
        height: 20px;
        cursor: pointer;
        margin: 0;
    }

    .theme-btn-submit {
        padding: 16px 45px;
        border-radius: 12px;
        border: none;
        background: var(--g900);
        color: #fff;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%; 
        box-shadow: 0 4px 6px -1px rgba(5, 46, 22, 0.15);
    }

    @media (min-width: 576px) {
        .theme-btn-submit { width: auto; } 
    }

    .theme-btn-submit:hover { 
        background: var(--g600); 
        transform: translateY(-1px);
        box-shadow: 0 6px 12px -1px rgba(22, 163, 74, 0.2);
    }

    .theme-alert {
        padding: 18px;
        border-radius: 14px;
        margin-bottom: 25px;
        font-size: 14.5px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        max-width: 1100px;
        box-sizing: border-box;
    }
    .alert-success { background: var(--g100); color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<div class="dashboard-center-container">

    <div class="form-header-group">
        <h2 class="theme-title">Request Laboratory Test</h2>
        <p class="theme-subtitle">Submit diagnostic orders directly to processing clinical centers</p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="theme-alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="theme-alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="theme-card">
        <form method="POST">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
                <div>
                    <label class="theme-label">Patient ID Reference</label>
                    <input type="number" name="patient_id" class="theme-input" placeholder="e.g., 104" required>
                </div>
                <div>
                    <label class="theme-label">Patient Full Name</label>
                    <input type="text" name="patient_name" class="theme-input" placeholder="Enter full name entry" required>
                </div>
            </div>

            <label class="theme-label" style="margin-bottom: 15px;"><i class="fa-solid fa-microscope" style="color: var(--g600); margin-right:4px;"></i> Test Panels (Select all required metrics)</label>
            
            <div class="test-grid">
                <label class="test-item">
                    <input type="checkbox" name="tests[]" value="Blood Test" onchange="toggleCardStyle(this)"> 
                    <span><i class="fa-solid fa-droplet" style="color:#ef4444; width:16px; margin-right:4px;"></i> Blood Test</span>
                </label>
                <label class="test-item">
                    <input type="checkbox" name="tests[]" value="Urine Test" onchange="toggleCardStyle(this)"> 
                    <span><i class="fa-solid fa-vial" style="color:#eab308; width:16px; margin-right:4px;"></i> Urine Analysis</span>
                </label>
                <label class="test-item">
                    <input type="checkbox" name="tests[]" value="X-Ray" onchange="toggleCardStyle(this)"> 
                    <span><i class="fa-solid fa-bone" style="color:#64748b; width:16px; margin-right:4px;"></i> X-Ray Scan</span>
                </label>
                <label class="test-item">
                    <input type="checkbox" name="tests[]" value="CT Scan" onchange="toggleCardStyle(this)"> 
                    <span><i class="fa-solid fa-circle-radiation" style="color:#3b82f6; width:16px; margin-right:4px;"></i> CT Scan</span>
                </label>
                <label class="test-item">
                    <input type="checkbox" name="tests[]" value="MRI" onchange="toggleCardStyle(this)"> 
                    <span><i class="fa-solid fa-magnet" style="color:#a855f7; width:16px; margin-right:4px;"></i> MRI Screen</span>
                </label>
                <label class="test-item">
                    <input type="checkbox" name="tests[]" value="Ultrasound" onchange="toggleCardStyle(this)"> 
                    <span><i class="fa-solid fa-wave-square" style="color:#06b6d4; width:16px; margin-right:4px;"></i> Ultrasound</span>
                </label>
            </div>

            <label class="theme-label">Clinical Urgency Triaging</label>
            <select name="urgency_level" class="theme-input" style="cursor: pointer;">
                <option value="Routine">🟢 Routine (Standard Processing)</option>
                <option value="Urgent">🟡 Urgent (Priority Turnaround)</option>
                <option value="Emergency">🔴 Emergency (STAT Processing)</option>
            </select>

            <label class="theme-label">Diagnostic Context & Clinical Indications</label>
            <textarea name="clinical_notes" class="theme-input" rows="5" placeholder="Describe symptoms, target parameters, or reasons for checking lab diagnostics..." style="resize: vertical; min-height: 100px; margin-bottom: 30px;"></textarea>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" name="submit_request" class="theme-btn-submit">
                    <i class="fa-solid fa-paper-plane" style="margin-right: 6px;"></i> Route Lab Request Order
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCardStyle(checkbox) {
    const parentLabel = checkbox.closest('.test-item');
    if (checkbox.checked) {
        parentLabel.classList.add('is-selected');
    } else {
        parentLabel.classList.remove('is-selected');
    }
}

document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.test-item input[type="checkbox"]').forEach(function(el) {
        toggleCardStyle(el);
    });
});
</script>