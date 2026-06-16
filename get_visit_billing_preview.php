<?php
/**
 * Hospital Management System - get_billing_preview.php
 * Real-Time Outpatient Service Cost Accumulation Matrix API
 */

include_once("db.php");

header('Content-Type: application/json');

// 1. Verify Request Context
if (!isset($_GET['visit_id'])) {
    echo json_encode(['error' => 'Missing Visit Context ID Reference.']);
    exit();
}

$visit_id = (int)$_GET['visit_id'];

// 2. Prepared Aggregation Pipeline
// Note: Ensure table names here match your active schema (e.g., lab_orders vs lab_requests)
$preview_query = "
    SELECT 
        COALESCE(c.consultation_fee, 0.00) AS total_consultation,
        COALESCE((SELECT SUM(cost) FROM lab_orders WHERE visit_id = pv.visit_id), 0.00) AS total_labs,
        COALESCE((SELECT SUM(total_cost) FROM pharmacy_dispensing WHERE visit_id = pv.visit_id), 0.00) AS total_medications
    FROM patient_visits pv
    LEFT JOIN consultations c ON pv.visit_id = c.visit_id
    WHERE pv.visit_id = ? 
    LIMIT 1
";

$stmt = $conn->prepare($preview_query);

if ($stmt) {
    $stmt->bind_param("i", $visit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        $consultation = (float)$row['total_consultation'];
        $labs         = (float)$row['total_labs'];
        $medications  = (float)$row['total_medications'];
        
        // Structural Ledger Formulas
        $subtotal  = $consultation + $labs + $medications;
        $tax       = $subtotal * 0.10; // Fixed 10% structural structural tax
        $projected = $subtotal + $tax;

        echo json_encode([
            'status'          => 'success',
            'visit_id'        => $visit_id,
            'consultation'    => $consultation,
            'labs'            => $labs,
            'medications'     => $medications,
            'subtotal'        => $subtotal,
            'tax_amount'      => $tax,
            'projected_total' => $projected
        ]);
    } else {
        // Fallback fallback if visit record hasn't logged charges yet
        echo json_encode([
            'status'          => 'empty',
            'visit_id'        => $visit_id,
            'consultation'    => 0.00,
            'labs'            => 0.00,
            'medications'     => 0.00,
            'subtotal'        => 0.00,
            'tax_amount'      => 0.00,
            'projected_total' => 0.00
        ]);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Internal compilation error preparing lookup matrix engine.']);
}