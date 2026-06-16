<?php
include("db.php");

/* ================= VALIDATION ================= */
if (!isset($_POST['report_type'], $_POST['date_range'], $_POST['format'])) {
    die("Invalid request");
}

$type = $_POST['report_type'];
$date_range = $_POST['date_range'];
$format = $_POST['format'];

/* ================= DATE FILTER ================= */
switch ($date_range) {
    case "today":
        $date_condition = "DATE(created_at) = CURDATE()";
        break;
    case "7days":
        $date_condition = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case "30days":
        $date_condition = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    default:
        $date_condition = "1";
}

/* ================= QUERY ================= */
switch ($type) {

    case 'users':
        $title = "User Report";
        $query = $conn->query("
            SELECT full_name, username, email, role, status 
            FROM users 
            WHERE $date_condition
        ");
        break;

    case 'patients':
        $title = "Patient Report";
        $query = $conn->query("
            SELECT patient_id, full_name, gender, age 
            FROM patients 
            WHERE $date_condition
        ");
        break;

    case 'appointments':
        $title = "Appointments Report";
        $query = $conn->query("
            SELECT appointment_id, patient_id, doctor_id, appointment_date 
            FROM appointments 
            WHERE $date_condition
        ");
        break;

    default:
        die("Invalid report type");
}

/* ================= FETCH ================= */
$data = [];
while ($row = $query->fetch_assoc()) {
    $data[] = $row;
}

if (empty($data)) {
    die("No data to export");
}

/* ================= CLEAN OUTPUT ================= */
if (ob_get_length()) ob_clean();

/* ================= EXCEL ================= */
if ($format === "excel") {

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=report.xls");

    echo implode("\t", array_keys($data[0])) . "\n";

    foreach ($data as $row) {
        echo implode("\t", $row) . "\n";
    }

    exit();
}

/* ================= PDF ================= */
if ($format === "pdf") {
?>
<!DOCTYPE html>
<html>
<head>
<title>Report</title>

<style>
body {
    font-family: Arial;
    padding: 40px;
}

/* HEADER */
.header {
    text-align:center;
    margin-bottom:20px;
}

.header img {
    height:50px;
}

h2 {
    text-align:center;
    margin-bottom:10px;
}

.date {
    text-align:center;
    margin-bottom:20px;
    color:#555;
}

/* TABLE */
table {
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th {
    background:#f3f4f6;
    padding:10px;
    border:1px solid #ccc;
}

td {
    padding:10px;
    border:1px solid #ccc;
}

/* FOOTER */
.footer {
    margin-top:60px;
    text-align:right;
}
</style>

</head>

<body onload="window.print()">

<div class="header">
<img src="images/D logo.png">
</div>

<h2><?= $title ?></h2>
<div class="date">Date: <?= date("Y-m-d") ?></div>

<table>

<tr>
<?php foreach(array_keys($data[0]) as $col): ?>
<th><?= ucfirst(str_replace("_"," ",$col)) ?></th>
<?php endforeach; ?>
</tr>

<?php foreach($data as $row): ?>
<tr>
<?php foreach($row as $val): ?>
<td><?= htmlspecialchars($val) ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>

</table>

<div class="footer">

</div>

</body>
</html>
<?php
exit();
}
?>