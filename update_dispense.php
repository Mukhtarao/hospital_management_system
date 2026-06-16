<?php
include("db.php");
include_once("billing_module.php");

header('Content-Type: application/json');

$prescription_id = 0;

if (isset($_POST['id'])) {
    $prescription_id = intval($_POST['id']);
} elseif (isset($_GET['id'])) {
    $prescription_id = intval($_GET['id']);
}

if ($prescription_id <= 0) {
    echo json_encode(["success" => false, "message" => "Missing prescription ID"]);
    exit();
}

$stmt = $conn->prepare("\n    SELECT prescription_id, patient_id, visit_id\n    FROM prescriptions\n    WHERE prescription_id = ?\n    LIMIT 1\n");
$stmt->bind_param("i", $prescription_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$row = $result->fetch_assoc()) {
    echo json_encode(["success" => false, "message" => "Prescription not found"]);
    exit();
}

$visit_id = (int)$row['visit_id'];

if ($visit_id <= 0) {
    echo json_encode(["success" => false, "message" => "This prescription has no visit_id"]);
    exit();
}

$update = $conn->prepare("UPDATE prescriptions SET status = 'Dispensed' WHERE prescription_id = ?");
$update->bind_param("i", $prescription_id);

if (!$update->execute()) {
    echo json_encode(["success" => false, "message" => "Failed to update prescription status"]);
    exit();
}

$invoice_id = computePatientBill($conn, $visit_id);

if (!$invoice_id) {
    echo json_encode([
        "success" => false,
        "message" => "Prescription dispensed, but invoice was not generated. Check if this visit has billable services.",
        "visit_id" => $visit_id
    ]);
    exit();
}

$conn->query("UPDATE patient_visits SET status = 'Discharged' WHERE visit_id = $visit_id");

echo json_encode([
    "success" => true,
    "message" => "Medicine dispensed and invoice generated successfully",
    "invoice_id" => $invoice_id,
    "visit_id" => $visit_id
]);
exit();
?>
