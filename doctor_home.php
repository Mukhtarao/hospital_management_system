<?php
/* ================= DATABASE SAFETY ================= */
$patients_count = 0;
$appointments_today = 0;
$pending_labs = 0;

/* ================= TOTAL PATIENTS ================= */
$check_patients = mysqli_query($conn, "SHOW TABLES LIKE 'patients'");
if (mysqli_num_rows($check_patients) > 0) {
    $patients_count = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS total FROM patients")
    )['total'];
}

/* ================= TODAY APPOINTMENTS ================= */
$check_appointments = mysqli_query($conn, "SHOW TABLES LIKE 'appointments'");
if (mysqli_num_rows($check_appointments) > 0) {
    $today = date('Y-m-d');
    $appointments_today = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE appointment_date = '$today'")
    )['total'];
}

/* ================= PENDING LAB RESULTS ================= */
$check_labs = mysqli_query($conn, "SHOW TABLES LIKE 'lab_tests'");
if (mysqli_num_rows($check_labs) > 0) {
    $pending_labs = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS total FROM lab_tests WHERE status = 'Pending'")
    )['total'];
}
?>

<h2>Doctor Dashboard</h2>
<p style="color:#6b7280;">Overview of today’s activities</p>

<!-- ================= STATS CARDS ================= -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:25px;">

    <div style="background:#fff;padding:22px;border-radius:16px;">
        <h4>Total Patients</h4>
        <h2><?= $patients_count ?></h2>
    </div>

    <div style="background:#fff;padding:22px;border-radius:16px;">
        <h4>Today’s Appointments</h4>
        <h2><?= $appointments_today ?></h2>
    </div>

    <div style="background:#fff;padding:22px;border-radius:16px;">
        <h4>Pending Lab Results</h4>
        <h2><?= $pending_labs ?></h2>
    </div>

</div>

<!-- ================= QUICK INFO ================= -->
<div style="background:#fff;padding:24px;border-radius:16px;margin-top:30px;">

<h3>Quick Overview</h3>
<p style="color:#6b7280;margin-top:6px;">
You can manage patients, add diagnoses, prescribe medications, and review lab results using the menu on the left.
</p>

<ul style="margin-top:15px;color:#374151;line-height:1.8;">
    <li>✔ Review patient medical records</li>
    <li>✔ Add diagnosis and treatment notes</li>
    <li>✔ Prescribe medications</li>
    <li>✔ Request and review lab tests</li>
</ul>

</div>

<!-- ================= FOOTER INFO ================= -->
<div style="margin-top:25px;color:#6b7280;font-size:14px;">
Last updated: <?= date("d M Y, h:i A") ?>
</div>
