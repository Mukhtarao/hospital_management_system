<?php
include("db.php");

/* ================= UPDATE STATUS ================= */
if (isset($_POST['appointment_id']) && isset($_POST['status'])) {
    $id = (int)$_POST['appointment_id'];
    $status = $_POST['status'];

    $conn->query("UPDATE appointments SET status='$status' WHERE appointment_id='$id'");

    // Updated to match the dashboard router link
    echo "<script>window.location='?page=manage_appointments';</script>";
    exit();
}

/* ================= CREATE NEW APPOINTMENT ================= */
if (isset($_POST['create_appointment'])) {
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor_id'];
    $department = $_POST['department'];
    $date = $_POST['date'];
    $time = $_POST['time'];

    if ($patient_id && $doctor_id && $date && $time) {
        $conn->query("
            INSERT INTO appointments 
            (patient_id, doctor_id, department, appointment_date, appointment_time, status)
            VALUES 
            ('$patient_id','$doctor_id','$department','$date','$time','Scheduled')
        ");

        echo "<script>alert('Appointment created successfully');window.location='?page=manage_appointments';</script>";
        exit();
    }
}

/* ================= FETCH DATA FROM DB ================= */
$appointments = $conn->query("
    SELECT 
        a.*,
        p.full_name AS patient_name,
        u.username AS doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN users u ON a.doctor_id = u.user_id
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

$doctors = $conn->query("SELECT user_id, username FROM users WHERE role='doctor'");
?>

<style>
    .title { font-family: 'Playfair Display'; font-size: 26px; color: var(--g900); margin-bottom: 5px; }
    .sub { color: #64748b; margin-bottom: 25px; font-size: 14px; }

    .section-title { font-size: 18px; font-weight: 700; color: var(--g900); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    
    .table-container { overflow-x: auto; margin-bottom: 40px; }
    .table { width: 100%; border-collapse: collapse; background: #fff; }
    .table th { padding: 15px; background: #f8fafc; text-align: left; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; border-bottom: 2px solid #edf2f7; }
    .table td { padding: 15px; border-bottom: 1px solid #edf2f7; font-size: 14px; color: #1e293b; }

    .status-select {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid #e2e8f0;
        cursor: pointer;
    }

    .status-scheduled { background: #f0fdf4; color: #16a34a; border-color: #bcf0da; }
    .status-pending { background: #fff7ed; color: #ea580c; border-color: #ffedd5; }

    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
    .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    
    .form-box { background: #f8fafc; padding: 25px; border-radius: 18px; border: 1px solid #e2e8f0; }
    label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; }
    
    input, select {
        width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #cbd5e1;
        background: #fff; font-family: inherit; font-size: 14px; transition: 0.2s;
    }
    input:focus, select:focus { outline: none; border-color: var(--g600); box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1); }

    /* Centered/Right Aligned Action Button Container */
    .form-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 25px;
    }

    .btn-submit {
        background: var(--g900, #0f172a); color: #fff; padding: 14px 30px; border-radius: 10px;
        border: none; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 14px;
    }
    .btn-submit:hover { background: var(--g600, #1e293b); transform: translateY(-1px); }
</style>

<h2 class="title">Manage Appointments</h2>
<p class="sub">Schedule new visits and update existing appointment statuses.</p>

<div class="table-container">
    <div class="section-title"><i class="fa-solid fa-list-check"></i> Current Schedule</div>
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Department</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $appointments->fetch_assoc()): ?>
            <tr>
                <td style="font-weight: 700;">#A<?= $row['appointment_id'] ?></td>
                <td><?= htmlspecialchars($row['patient_name']) ?></td>
                <td>Dr. <?= htmlspecialchars($row['doctor_name']) ?></td>
                <td><span style="background:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:6px; font-size:12px;"><?= $row['department'] ?></span></td>
                <td><?= date('M d, Y', strtotime($row['appointment_date'])) ?></td>
                <td><?= date('h:i A', strtotime($row['appointment_time'])) ?></td>
                <td>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="appointment_id" value="<?= $row['appointment_id'] ?>">
                        <select name="status" onchange="this.form.submit()" 
                                class="status-select <?= ($row['status']=='Scheduled') ? 'status-scheduled' : 'status-pending' ?>">
                            <option value="Pending" <?= ($row['status'] == 'Pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="Scheduled" <?= ($row['status'] == 'Scheduled') ? 'selected' : '' ?>>Scheduled</option>
                            <option value="Cancelled" <?= ($row['status'] == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<hr style="border: 0; border-top: 1px solid #edf2f7; margin-bottom: 30px;">

<div class="form-box">
    <div class="section-title"><i class="fa-solid fa-calendar-plus"></i> Schedule New Appointment</div>
    <form method="POST">
        <div class="grid">
            <div>
                <label>Patient ID</label>
                <input type="number" name="patient_id" placeholder="Enter Patient ID" required>
            </div>
            <div>
                <label>Assign Doctor</label>
                <select name="doctor_id" required>
                    <option value="">Select Doctor</option>
                    <?php 
                    $doctors->data_seek(0); // Reset pointer to reuse result
                    while($d = $doctors->fetch_assoc()): 
                    ?>
                    <option value="<?= $d['user_id'] ?>">Dr. <?= htmlspecialchars($d['username']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label>Department</label>
                <select name="department" required>
                    <option>General Medicine</option>
                    <option>Cardiology</option>
                    <option>Pediatrics</option>
                    <option>Orthopedics</option>
                    <option>Neurology</option>
                </select>
            </div>
        </div>

        <div class="grid-2" style="margin-top: 20px;">
            <div>
                <label>Appointment Date</label>
                <input type="date" name="date" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div>
                <label>Appointment Time</label>
                <input type="time" name="time" required>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="create_appointment" class="btn-submit">
                Confirm Appointment
            </button>
        </div>
    </form>
</div>