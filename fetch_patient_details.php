<?php
include("db.php");

$id = $_GET['id'] ?? 0;

$query = mysqli_query($conn, "
    SELECT *
    FROM patients
    WHERE patient_id = '$id'
    LIMIT 1
");

if ($row = mysqli_fetch_assoc($query)) {
?>
<div>
    <p><strong>Full Name:</strong> <?= htmlspecialchars($row['full_name']) ?></p>
    <p><strong>Age:</strong> <?= $row['age'] ?></p>
    <p><strong>Gender:</strong> <?= $row['gender'] ?></p>
    <p><strong>Phone:</strong> <?= $row['phone'] ?></p>
    <p><strong>Email:</strong> <?= $row['email'] ?? '-' ?></p>
    <p><strong>Address:</strong> <?= $row['address'] ?? '-' ?></p>
    <p><strong>Registered At:</strong> <?= date("d M Y", strtotime($row['created_at'])) ?></p>
</div>
<?php } else {
    echo "Patient not found";
}
