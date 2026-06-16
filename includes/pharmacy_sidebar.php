<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current = $_GET['page'] ?? '';
$name = $_SESSION['username'] ?? 'User';
$initial = strtoupper($name[0]);
?>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:"Segoe UI",sans-serif;}
body{background:#f8fafc;}

/* SIDEBAR */
.sidebar{
    position:fixed;
    width:260px;
    height:100vh;
    background:#ffffff;
    border-right:1px solid #e5e7eb;
    padding:20px;
}

/* HEADER */
.header{
    position:fixed;
    left:260px;
    right:0;
    height:70px;
    background:#ffffff;
    border-bottom:1px solid #e5e7eb;
    padding:20px 30px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

/* MAIN */
.main{
    margin-left:260px;
    margin-top:70px;
    padding:30px;
}

/* MENU */
.menu a{
    display:block;
    padding:12px;
    margin-bottom:10px;
    border-radius:10px;
    text-decoration:none;
    color:#111827;
    transition:0.2s;
}

.menu a.active,
.menu a:hover{
    background:#020617;
    color:#fff;
}

/* USER ICON */
.user{
    width:38px;
    height:38px;
    background:#e5e7eb;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:bold;
}

/* CARD */
.card{
    background:#fff;
    padding:20px;
    border-radius:18px;
    margin-top:20px;
    box-shadow:0 4px 10px rgba(0,0,0,0.03);
}

/* BUTTONS */
.btn{
    padding:10px 18px;
    border:none;
    border-radius:10px;
    cursor:pointer;
}

.primary{background:#020617;color:#fff;}
.cancel{background:#e5e7eb;}
</style>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>🩺 MediCare HMS</h2>
    <p style="color:#6b7280;">Pharmacy</p>

    <div class="menu" style="margin-top:20px;">

        <a href="pharmacy_dashboard.php?page=prescriptions"
           class="<?= $current == 'prescriptions' ? 'active' : '' ?>">
            View Prescriptions
        </a>

        <a href="pharmacy_dashboard.php?page=dispense"
           class="<?= $current == 'dispense' ? 'active' : '' ?>">
            Dispense Medication
        </a>

        <a href="pharmacy_dashboard.php?page=inventory"
           class="<?= $current == 'inventory' ? 'active' : '' ?>">
            Medicine Inventory
        </a>

    </div>

    <div style="position:absolute;bottom:20px;width:85%;">
        <a href="logout.php"
           style="display:block;text-align:center;padding:10px;border:1px solid #ddd;border-radius:10px;text-decoration:none;">
            Logout
        </a>
    </div>
</div>

<!-- TOPBAR -->
<div class="header">
    <h2>Hospital Management System</h2>
    <div class="user"><?= $initial ?></div>
</div>