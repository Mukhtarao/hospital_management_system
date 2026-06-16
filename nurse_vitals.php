<?php
// Ensure database connectivity is established
if (!isset($conn)) {
    include("db.php");
}

/* ================= PRE-FILL LOGIC ================= */
// Capture parameters safely passed from the dashboard URL
$target_appointment_id = $_GET['appointment_id'] ?? '';
$target_patient_id     = $_GET['patient_id'] ?? '';
$target_patient_name   = '';

// Automatically look up the patient's full name if an ID is present
if (!empty($target_patient_id)) {
    $stmt = $conn->prepare("SELECT full_name FROM patients WHERE patient_id = ?");
    if ($stmt) {
        $patient_id_int = intval($target_patient_id);
        $stmt->bind_param("i", $patient_id_int);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $target_patient_name = $row['full_name'];
        }
        $stmt->close();
    }
}

/* ================= FORM SUBMISSION HANDLING ================= */
$message = "";
$messageClass = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_vitals'])) {
    $appointment_id    = $_POST['appointment_id'] ?? null;
    $patient_id        = $_POST['patient_id'] ?? null;
    $patient_name      = $_POST['patient_name'] ?? '';
    $temperature       = !empty($_POST['temperature']) ? $_POST['temperature'] : null;
    $blood_pressure    = !empty($_POST['blood_pressure']) ? $_POST['blood_pressure'] : null;
    $heart_rate        = !empty($_POST['heart_rate']) ? $_POST['heart_rate'] : null;
    $respiratory_rate  = !empty($_POST['respiratory_rate']) ? $_POST['respiratory_rate'] : null;
    $oxygen_saturation = !empty($_POST['oxygen_saturation']) ? $_POST['oxygen_saturation'] : null;
    $weight            = !empty($_POST['weight']) ? $_POST['weight'] : null;
    $notes             = $_POST['notes'] ?? '';
    $nurse_id          = $_SESSION['user_id'] ?? 1; // Fallback to 1 if session ID is structural

    if (!empty($patient_id)) {
        // Insert statement matches your exact table architecture schema
        $insertQuery = "INSERT INTO vitals (appointment_id, patient_id, patient_name, temperature, blood_pressure, heart_rate, respiratory_rate, oxygen_saturation, weight, notes, nurse_id, recorded_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($insertQuery);
        if ($stmt) {
            $stmt->bind_param(
                "iisssiiiidi", 
                $appointment_id, 
                $patient_id, 
                $patient_name, 
                $temperature, 
                $blood_pressure, 
                $heart_rate, 
                $respiratory_rate, 
                $oxygen_saturation, 
                $weight, 
                $notes, 
                $nurse_id
            );
            
            if ($stmt->execute()) {
                $message = "Patient vital signs saved successfully!";
                $messageClass = "alert-success";
                // Clear out values on successful submit
                $target_appointment_id = '';
                $target_patient_id = '';
                $target_patient_name = '';
            } else {
                $message = "Database Execution Error: Failed to save records. " . $stmt->error;
                $messageClass = "alert-danger";
            }
            $stmt->close();
        } else {
            $message = "SQL Preparation Error: Check table schema layout matching. " . $conn->error;
            $messageClass = "alert-danger";
        }
    } else {
        $message = "Validation Error: Patient ID is required to process submission.";
        $messageClass = "alert-danger";
    }
}
?>

<style>
    .vitals-container { background: #fff; border-radius: 24px; padding: 35px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); border: 1px solid #eef2f6; }
    .vitals-title-group { margin-bottom: 30px; }
    .vitals-title { font-family: 'Playfair Display', serif; color: var(--emerald-900); font-size: 24px; margin-bottom: 5px; }
    .vitals-subtitle { color: var(--text-muted); font-size: 13px; }
    
    .vitals-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 25px; }
    .full-width-field { grid-column: span 2; }
    
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    .form-group label { font-size: 12px; font-weight: 700; color: var(--emerald-900); text-transform: capitalize; }
    .form-group input, .form-group textarea { width: 100%; padding: 14px 16px; border-radius: 14px; border: 1.5px solid #e2e8f0; background: #f8fafc; font-family: inherit; transition: var(--transition); color: var(--text-main); }
    .form-group input:focus, .form-group textarea:focus { border-color: var(--emerald-600); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.08); }
    .form-group input[readonly] { background: #f1f5f9; color: #64748b; border-color: #cbd5e1; cursor: not-allowed; }
    
    .alert-banner { padding: 16px 20px; border-radius: 14px; margin-bottom: 25px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    
    .form-actions { display: flex; justify-content: flex-end; gap: 15px; margin-top: 20px; }
    .btn-secondary { background: #f1f5f9; color: #475569; border: none; padding: 14px 28px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; font-size: 14px; }
    .btn-secondary:hover { background: #e2e8f0; }
    .btn-submit { background: var(--emerald-900); color: #fff; border: none; padding: 14px 32px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: var(--transition); font-size: 14px; box-shadow: 0 4px 12px rgba(5, 46, 22, 0.15); }
    .btn-submit:hover { background: var(--emerald-600); transform: translateY(-1px); }
</style>

<div class="vitals-container">
    <div class="vitals-title-group">
        <h2 class="vitals-title">Update Patient Vital Signs</h2>
        <p class="vitals-subtitle">Record patient vital signs and measurements linked to unique calendar check-in requests</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-banner <?= $messageClass ?>">
            <i class="fa-solid <?= $messageClass === 'alert-success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="vitals-form-grid">
            
            <div class="form-group">
                <label>Appointment ID</label>
                <input type="text" name="appointment_id" value="<?= htmlspecialchars($target_appointment_id) ?>" placeholder="No active selection" readonly>
            </div>

            <div class="form-group">
                <label>Patient ID</label>
                <input type="text" name="patient_id" value="<?= htmlspecialchars($target_patient_id) ?>" placeholder="Enter patient ID" readonly required>
            </div>

            <div class="form-group full-width-field">
                <label>Patient Name</label>
                <input type="text" name="patient_name" value="<?= htmlspecialchars($target_patient_name) ?>" placeholder="Patient designation look-up empty" readonly>
            </div>

            <div class="form-group">
                <label>Temperature (°F)</label>
                <input type="number" step="0.1" name="temperature" placeholder="e.g., 98.6">
            </div>

            <div class="form-group">
                <label>Blood Pressure (mmHg)</label>
                <input type="text" name="blood_pressure" placeholder="e.g., 120/80">
            </div>

            <div class="form-group">
                <label>Heart Rate (bpm)</label>
                <input type="number" name="heart_rate" placeholder="e.g., 72">
            </div>

            <div class="form-group">
                <label>Respiratory Rate (breaths/min)</label>
                <input type="number" name="respiratory_rate" placeholder="e.g., 16">
            </div>

            <div class="form-group">
                <label>Oxygen Saturation (%)</label>
                <input type="number" name="oxygen_saturation" placeholder="e.g., 98">
            </div>

            <div class="form-group">
                <label>Weight (kg)</label>
                <input type="number" step="0.01" name="weight" placeholder="e.g., 70.5">
            </div>

            <div class="form-group full-width-field">
                <label>Additional Notes</label>
                <textarea name="notes" rows="4" placeholder="Any additional clinical observations..."></textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="nurse_dashboard.php?page=patients" class="btn-secondary">Clear Form</a>
            <button type="submit" name="save_vitals" class="btn-submit">Save Vital Signs</button>
        </div>
    </form>
</div>