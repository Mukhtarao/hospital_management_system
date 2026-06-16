<?php

include("db.php");

/* ================= SYSTEM STATISTICS ================= */

// Total users
$total_users = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS total FROM users")
)['total'] ?? 0;

// Total patients
$total_patients = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS total FROM patients")
)['total'] ?? 0;

// Total appointments
$total_appointments = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS total FROM appointments")
)['total'] ?? 0;

// User distribution by role
$roles = [
    'doctor' => 'Doctors',
    'nurse' => 'Nurses',
    'receptionist' => 'Receptionists',
    'lab' => 'Lab Technicians',
    'pharmacy' => 'Pharmacists',
    'admin' => 'Admins'
];

$role_counts = [];
foreach ($roles as $role_key => $role_label) {
    $count = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='$role_key'")
    )['total'] ?? 0;

    $role_counts[$role_label] = $count;
}

// Recent system activities (basic log simulation)
$recent_activities = [
    ["New user registered", "System", "5 minutes ago"],
    ["System backup completed", "System", "1 hour ago"],
    ["Role permissions updated", "Admin User", "2 hours ago"],
    ["New patient record created", "Receptionist", "3 hours ago"],
    ["Database maintenance", "System", "5 hours ago"]
];
?>

<h2>Admin Dashboard</h2>
<p style="color:#6b7280;">Overview of system statistics</p>

<!-- ================= STAT CARDS ================= -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-top:20px;">

<div style="background:#fff;padding:20px;border-radius:14px;">
    <strong>Total Users</strong>
    <h2><?= $total_users ?></h2>
</div>

<div style="background:#fff;padding:20px;border-radius:14px;">
    <strong>Total Patients</strong>
    <h2><?= $total_patients ?></h2>
</div>

<div style="background:#fff;padding:20px;border-radius:14px;">
    <strong>Total Appointments</strong>
    <h2><?= $total_appointments ?></h2>
</div>

<div style="background:#fff;padding:20px;border-radius:14px;">
    <strong>System Status</strong>
    <h2 style="color:green;">Active</h2>
    <small>All systems operational</small>
</div>

</div>

<!-- ================= LOWER SECTION ================= -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-top:30px;">

<!-- USER DISTRIBUTION -->
<div style="background:#fff;padding:20px;border-radius:14px;">
    <h4>User Distribution by Role</h4>

    <?php foreach ($role_counts as $role => $count): 
        $percent = $total_users > 0 ? ($count / $total_users) * 100 : 0;
    ?>
        <div style="margin-top:15px;">
            <div style="display:flex; justify-content:space-between;">
                <span><?= $role ?></span>
                <span><?= $count ?> users</span>
            </div>
            <div style="background:#e5e7eb; height:6px; border-radius:4px; margin-top:6px;">
                <div style="
                    width:<?= $percent ?>%;
                    height:6px;
                    background:#0f172a;
                    border-radius:4px;
                "></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- RECENT ACTIVITIES -->
<div style="background:#fff;padding:20px;border-radius:14px;">
    <h4>Recent System Activities</h4>

    <?php foreach ($recent_activities as $activity): ?>
        <div style="margin-top:15px; border-bottom:1px solid #e5e7eb; padding-bottom:10px;">
            <strong><?= $activity[0] ?></strong>
            <p style="color:#6b7280; font-size:13px;">
                <?= $activity[1] ?>
                <span style="float:right;"><?= $activity[2] ?></span>
            </p>
        </div>
    <?php endforeach; ?>
</div>

</div>
