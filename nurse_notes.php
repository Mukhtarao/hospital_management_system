<?php
/* ================= SAVE NURSING NOTES ================= */
if (isset($_POST['save_note'])) {
    $patient_id   = mysqli_real_escape_string($conn, $_POST['patient_id']);
    $patient_name = mysqli_real_escape_string($conn, $_POST['patient_name']);
    $note_type    = mysqli_real_escape_string($conn, $_POST['note_type']);
    $observations = mysqli_real_escape_string($conn, $_POST['observations']);
    $intervention = mysqli_real_escape_string($conn, $_POST['intervention']);
    $response     = mysqli_real_escape_string($conn, $_POST['response']);
    $note_time    = $_POST['note_time'];
    $note_date    = $_POST['note_date'];
    $nurse_id     = $_SESSION['user_id'];

    $query = "INSERT INTO nursing_notes (
                patient_id, patient_name, note_type, observations, 
                interventions, patient_response, note_time, note_date, nurse_id
            ) VALUES (
                '$patient_id', '$patient_name', '$note_type', '$observations', 
                '$intervention', '$response', '$note_time', '$note_date', '$nurse_id'
            )";
    
    if(mysqli_query($conn, $query)) {
        $saved = true;
    }
}

/* ================= FETCH RECENT NOTES ================= */
$recent_notes = mysqli_query($conn, "
    SELECT id, patient_name, patient_id, observations, note_time, note_date 
    FROM nursing_notes 
    ORDER BY id DESC LIMIT 5
");
?>

<style>
    .page-title h2 { font-family: 'Playfair Display', serif; color: var(--g900); font-size: 28px; }
    .page-title p { color: #64748b; font-size: 14px; margin-top: 5px; }

    .theme-card { 
        background: #fff; border-radius: 24px; border: 1px solid #edf2f7; 
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); padding: 30px; margin-bottom: 30px; 
    }
    
    .form-group label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
    .form-control { 
        width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #eef2f6; 
        background: #f8fafc; font-size: 14px; transition: 0.3s; 
    }
    .form-control:focus { border-color: var(--g600); outline: none; background: #fff; }

    .btn-save { background: var(--g900); color: #fff; border: none; padding: 12px 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s; }
    .btn-save:hover { background: var(--g600); transform: translateY(-1px); }

    .note-item { 
        padding: 20px; border-radius: 16px; border: 1px solid #f1f5f9; 
        margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;
        transition: 0.3s;
    }
    .note-item:hover { border-color: var(--g400); background: #f0fdf4; }

    .alert-success { background: #dcfce7; color: #166534; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
</style>

<div class="page-header">
    <div class="page-title">
        <h2>Nursing Notes</h2>
        <p>HGH Patient Care & Clinical Documentation</p>
    </div>
</div>

<div class="theme-card">
    <h3 style="font-family: 'Playfair Display'; margin-bottom: 20px; color: var(--g900);">Document Observation</h3>

    <?php if (isset($saved)): ?>
        <div class="alert-success">
            <i class="fa-solid fa-circle-check"></i> Note archived successfully in patient history.
        </div>
    <?php endif; ?>

    <form method="POST">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Patient ID</label>
                <input type="text" name="patient_id" class="form-control" placeholder="HGH-000" required>
            </div>
            <div class="form-group">
                <label>Patient Name</label>
                <input type="text" name="patient_name" class="form-control" placeholder="Full Name" required>
            </div>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label>Note Type</label>
            <select name="note_type" class="form-control">
                <option>General Assessment</option>
                <option>Medication Administration</option>
                <option>Post-Op Monitoring</option>
                <option>Vital Sign Follow-up</option>
                <option>Discharge Planning</option>
            </select>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label>Clinical Observations</label>
            <textarea name="observations" class="form-control" rows="3" placeholder="Symptoms, mood, physical signs..." required></textarea>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            <div class="form-group">
                <label>Nursing Interventions</label>
                <textarea name="intervention" class="form-control" rows="3" placeholder="What actions were taken?"></textarea>
            </div>
            <div class="form-group">
                <label>Patient Response</label>
                <textarea name="response" class="form-control" rows="3" placeholder="How did the patient react?"></textarea>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            <div class="form-group">
                <label>Time of Log</label>
                <input type="time" name="note_time" class="form-control" value="<?= date('H:i') ?>">
            </div>
            <div class="form-group">
                <label>Date of Log</label>
                <input type="date" name="note_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px;">
            <button type="reset" style="background:none; border:none; color:#64748b; font-weight:700; cursor:pointer;">Discard</button>
            <button type="submit" name="save_note" class="btn-save">Save Clinical Note</button>
        </div>
    </form>
</div>

<div class="theme-card">
    <h3 style="font-family: 'Playfair Display'; margin-bottom: 20px; color: var(--g900);">Recent Clinical Logs</h3>

    <?php if ($recent_notes && mysqli_num_rows($recent_notes) > 0): ?>
        <?php while ($note = mysqli_fetch_assoc($recent_notes)): ?>
            <div class="note-item">
                <div>
                    <span style="font-size: 11px; color: var(--g600); font-weight: 800; text-transform: uppercase;">#<?= $note['patient_id'] ?></span>
                    <h4 style="color: var(--g900); margin: 5px 0;"><?= $note['patient_name'] ?></h4>
                    <p style="font-size: 13px; color: #64748b;"><?= substr($note['observations'], 0, 80) ?>...</p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 12px; font-weight: 700; color: #1e293b;"><?= $note['note_time'] ?></div>
                    <div style="font-size: 11px; color: #94a3b8; margin-bottom: 10px;"><?= date('d M Y', strtotime($note['note_date'])) ?></div>
                    <button onclick="viewNote(<?= $note['id'] ?>)" class="view-btn" style="background:#f1f5f9; border:none; padding:6px 12px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer; color:var(--g900);">View Details</button>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="text-align:center; padding:40px; color:#94a3b8;">
            <i class="fa-solid fa-folder-open" style="font-size: 40px; margin-bottom: 15px; opacity:0.3;"></i>
            <p>No nursing notes found for this shift.</p>
        </div>
    <?php endif; ?>
</div>

<script>
function viewNote(noteId) {
    fetch('get_note_details.php?id=' + noteId)
    .then(response => response.text())
    .then(data => {
        // We use the patientModal already defined in your main nurse_dashboard.php
        document.getElementById('patientDetails').innerHTML = data;
        document.getElementById('patientModal').style.display = 'flex';
    });
}
</script>