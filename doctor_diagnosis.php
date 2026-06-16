<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include_once("db.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    exit("<div class='theme-alert alert-err'>Access Denied: Unauthorized portal context.</div>");
}

$message = "";
$error = "";

/* ================= SAVE DIAGNOSIS & AUTO-BOOK APPOINTMENT ================= */
if (isset($_POST['save_diagnosis'])) {
    $patient_id     = (int)$_POST['patient_id'];
    $diagnosis_text = mysqli_real_escape_string($conn, trim($_POST['diagnosis']));
    $symptoms       = !empty($_POST['symptoms']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['symptoms'])) . "'" : "NULL";
    $treatment_plan = !empty($_POST['treatment_plan']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['treatment_plan'])) . "'" : "NULL";
    $severity       = !empty($_POST['severity']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['severity'])) . "'" : "NULL";
    
    // Capture Date & Time values
    $follow_up_date = !empty($_POST['follow_up_date']) ? mysqli_real_escape_string($conn, $_POST['follow_up_date']) : '';
    $follow_up_time = !empty($_POST['follow_up_time']) ? mysqli_real_escape_string($conn, $_POST['follow_up_time']) : '';
    
    $doctor_id      = $_SESSION['user_id'] ?? 1;
    $date           = date("Y-m-d");

    // Verify patient integrity reference mapping
    $check = mysqli_query($conn, "SELECT patient_id FROM patients WHERE patient_id = $patient_id");

    if (mysqli_num_rows($check) === 0) {
        $error = "Execution Rejected: Patient ID does not exist in our medical index registries.";
    } else {
        // Start transaction to safely combine both row insertions
        mysqli_begin_transaction($conn);

        try {
            // 1. Insert Diagnosis Log Entry
            $db_follow_up_date = !empty($follow_up_date) ? "'$follow_up_date'" : "NULL";
            $insert_diagnosis = mysqli_query($conn, "
                INSERT INTO diagnosis 
                (patient_id, doctor_id, diagnosis_text, diagnosis_date, symptoms, treatment_plan, severity, follow_up_date)
                VALUES
                ($patient_id, $doctor_id, '$diagnosis_text', '$date', $symptoms, $treatment_plan, $severity, $db_follow_up_date)
            ");

            if (!$insert_diagnosis) {
                throw new Exception("Failed to execute diagnosis log entry: " . mysqli_error($conn));
            }

            // 2. Schedule Follow-up Appointment entry automatically if date is selected
            if (!empty($follow_up_date)) {
                // Default fallback to standard clinical morning block if time field is omitted
                $final_time = !empty($follow_up_time) ? $follow_up_time : "10:00:00";
                
                // FIXED: Cleaned up inner string concatenation syntax error inside the double quotes
                $reason_text = mysqli_real_escape_string($conn, "Clinical Follow-up: " . trim($_POST['diagnosis']));
                
                $insert_appointment = mysqli_query($conn, "
                    INSERT INTO appointments 
                    (patient_id, doctor_id, appointment_date, appointment_time, status, reason, created_at)
                    VALUES
                    ($patient_id, $doctor_id, '$follow_up_date', '$final_time', 'Scheduled', '$reason_text', NOW())
                ");

                if (!$insert_appointment) {
                    throw new Exception("Failed to commit automated appointment reservation: " . mysqli_error($conn));
                }
            }

            // If statements clear hurdles without throwing exceptions, commit changes permanently
            mysqli_commit($conn);
            $message = "Clinical diagnosis saved and follow-up appointment successfully booked.";

        } catch (Exception $e) {
            // Rollback database changes on failure to maintain data integrity
            mysqli_rollback($conn);
            $error = "Pipeline Error: " . $e->getMessage();
        }
    }
}
?>

<style>
    /* THEME OVERRIDES */
    .theme-title { font-family: 'Playfair Display', serif; color: #052e16; font-size: 28px; margin: 0; margin-bottom: 5px; }
    .theme-subtitle { color: #64748b; font-size: 14px; margin-top: 0; margin-bottom: 25px; }

    .theme-card {
        background: #fff;
        padding: 30px;
        border-radius: 24px;
        border: 1px solid #edf2f7;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04);
    }

    .theme-card h3 { font-family: 'Playfair Display', serif; color: #052e16; margin-top: 0; margin-bottom: 20px; font-size: 18px; }

    .theme-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .theme-input {
        width: 100%;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        font-family: inherit;
        font-size: 14px;
        margin-bottom: 15px;
        box-sizing: border-box;
    }

    .theme-input:focus {
        outline: none;
        border-color: #16a34a;
        background: #fff;
    }

    .action-button-panel {
        display: flex; 
        justify-content: flex-end; 
        gap: 12px; 
        width: 100%;
    }

    .theme-btn-save {
        padding: 14px 30px;
        border-radius: 12px;
        border: none;
        background: #052e16;
        color: #fff;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .theme-btn-save:hover { background: #16a34a; }

    .theme-btn-reset {
        padding: 14px 30px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #64748b;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .theme-btn-reset:hover { background: #f8fafc; }

    .theme-alert {
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 600;
    }
    .alert-msg { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .alert-err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<h2 class="theme-title">Add Diagnosis</h2>
<p class="theme-subtitle">Record patient diagnosis and treatment plan</p>

<?php if (!empty($message)): ?>
    <div class="theme-alert alert-msg">✓ <?= $message ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="theme-alert alert-err">⚠️ <?= $error ?></div>
<?php endif; ?>

<div class="theme-card">
    <h3>Diagnosis Form</h3>
    <form method="POST">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label class="theme-label">Patient ID</label>
                <input type="number" name="patient_id" class="theme-input" required placeholder="Enter patient ID">
            </div>
            <div>
                <label class="theme-label">Severity Level</label>
                <input type="text" name="severity" class="theme-input" placeholder="e.g. Mild, Moderate, Severe">
            </div>
        </div>

        <label class="theme-label">Diagnosis / Assessment</label>
        <textarea name="diagnosis" class="theme-input" rows="3" required placeholder="Enter primary medical findings..."></textarea>

        <label class="theme-label">Observed Symptoms</label>
        <textarea name="symptoms" class="theme-input" rows="3" placeholder="Describe clinical symptoms presented..."></textarea>

        <label class="theme-label">Treatment Plan / Interventions</label>
        <textarea name="treatment_plan" class="theme-input" rows="3" placeholder="Outline prescriptions, therapies or clinical interventions..."></textarea>

        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; align-items: flex-end; margin-bottom: 15px;">
            <div>
                <label class="theme-label">Follow-up Date</label>
                <input type="date" name="follow_up_date" class="theme-input" style="margin-bottom:0;">
            </div>
            <div>
                <label class="theme-label">Follow-up Time</label>
                <input type="time" name="follow_up_time" class="theme-input" style="margin-bottom:0;">
            </div>
            <div class="action-button-panel">
                <button type="reset" class="theme-btn-reset">Cancel</button>
                <button type="submit" name="save_diagnosis" class="theme-btn-save">Save Diagnosis</button>
            </div>
        </div>
    </form>
</div>