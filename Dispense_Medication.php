<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("db.php");

$name = $_SESSION['username'] ?? 'Pharmacist';

/* ================= HANDLE SUBMIT ================= */
if (isset($_POST['dispense'])) {

    $prescription_id = (int)$_POST['prescription_id'];

    $items = $conn->query("
        SELECT medicine_id, quantity 
        FROM prescription_items 
        WHERE prescription_id = $prescription_id
    ");

    $error = false;

    while ($row = $items->fetch_assoc()) {
        $medicine_id = $row['medicine_id'];
        $qty = $row['quantity'];

        $check = $conn->query("SELECT quantity FROM medicines WHERE medicine_id = $medicine_id");
        $stock = $check->fetch_assoc()['quantity'];

        if ($stock < $qty) {
            echo "<script>alert('Not enough stock!');</script>";
            $error = true;
            break;
        }
    }

    if (!$error) {

        $items->data_seek(0);

        while ($row = $items->fetch_assoc()) {

            $medicine_id = $row['medicine_id'];
            $qty = $row['quantity'];

            $conn->query("
                UPDATE medicines 
                SET quantity = quantity - $qty
                WHERE medicine_id = $medicine_id
            ");

            $conn->query("
                INSERT INTO dispensed_medicines
                (prescription_id, medicine_id, quantity)
                VALUES ($prescription_id, $medicine_id, $qty)
            ");
        }

        $conn->query("
            UPDATE prescriptions 
            SET status='Dispensed'
            WHERE prescription_id = $prescription_id
        ");

        echo "<script>
            alert('Medication Dispensed Successfully');
            window.location='pharmacy_dashboard.php?page=prescriptions';
        </script>";
    }
}
?>

<style>
/* ===== LOCAL PAGE CSS ===== */

.card{
    background:#fff;
    padding:25px;
    border-radius:18px;
    box-shadow:0 4px 15px rgba(0,0,0,0.05);
    margin-top:20px;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

input, textarea{
    width:100%;
    padding:12px;
    border-radius:10px;
    border:1px solid #e5e7eb;
    margin-top:5px;
    font-size:14px;
}

textarea{
    resize:none;
}

.btn{
    padding:10px 18px;
    border-radius:10px;
    border:none;
    cursor:pointer;
    font-size:14px;
}

.primary{
    background:#020617;
    color:#fff;
}

.cancel{
    background:#e5e7eb;
}
</style>

<h2>Dispense Medication</h2>
<p style="color:#6b7280;">Record medication dispensing to patients</p>

<div class="card">

<h3 style="margin-bottom:20px;">Medication Dispensing Form</h3>

<form method="POST">

<div class="grid">

    <div>
        <label>Prescription ID</label>
        <input type="number" name="prescription_id" placeholder="Enter prescription ID" required>
    </div>

    <div>
        <label>Patient ID</label>
        <input type="number" name="patient_id" placeholder="Enter patient ID">
    </div>

    <div>
        <label>Patient Name</label>
        <input type="text" placeholder="Patient name">
    </div>

    <div>
        <label>Medication Name</label>
        <input type="text" placeholder="Medication name">
    </div>

    <div>
        <label>Quantity Dispensed</label>
        <input type="number" name="quantity" placeholder="e.g., 30">
    </div>

    <div>
        <label>Dosage Form</label>
        <input type="text" placeholder="e.g., Tablets">
    </div>

    <div>
        <label>Strength</label>
        <input type="text" placeholder="e.g., 500mg">
    </div>

    <div>
        <label>Dispense Date</label>
        <input type="date">
    </div>

    <div>
        <label>Dispense Time</label>
        <input type="time">
    </div>

</div>

<div style="margin-top:20px;">
    <label>Patient Instructions</label>
    <textarea placeholder="Instructions given to patient"></textarea>
</div>

<div style="margin-top:20px;">
    <label>Dispensed By</label>
    <input type="text" value="<?= htmlspecialchars($name) ?>" readonly>
</div>

<div style="margin-top:25px;display:flex;gap:10px;justify-content:flex-end;">
    <button class="btn cancel" type="button" onclick="history.back()">Cancel</button>
    <button class="btn primary" name="dispense">Dispense Medication</button>
</div>

</form>

</div>