<?php
/**
 * HGH Core Live Billing Preview Controller
 */

include_once("db.php");
include_once("billing_module.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit();
}

$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$visit_id   = isset($_POST['visit_id']) ? (int)$_POST['visit_id'] : 0;

if ($patient_id <= 0 && $visit_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing Patient ID or Visit ID.'
    ]);
    exit();
}

try {

    if ($visit_id > 0) {
        $live_data = getLivePatientBill($conn, $visit_id);
    } else {
        $live_data = getLivePatientBillByPatient($conn, $patient_id);
    }

    if ($live_data) {
        echo json_encode([
            'success' => true,
            'message' => 'Live billing metrics retrieved successfully.',
            'data' => [
                'visit_id'           => (int)$live_data['visit_id'],
                'patient_id'         => (int)$live_data['patient_id'],
                'consultation_total' => (float)$live_data['consultation_total'],
                'doctor_fee_total'   => (float)$live_data['doctor_fee_total'],
                'lab_tests_total'    => (float)$live_data['lab_tests_total'],
                'medications_total'  => (float)$live_data['medications_total'],
                'procedures_total'   => (float)$live_data['procedures_total'],
                'tax_amount'         => (float)$live_data['tax_amount'],
                'discount_amount'    => (float)$live_data['discount_amount'],
                'grand_total'        => (float)$live_data['grand_total'],
                'current_status'     => $live_data['visit_status']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No active, unbilled visit found.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Preview billing error: ' . $e->getMessage()
    ]);
}