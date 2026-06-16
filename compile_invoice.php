<?php
/**
 * HGH Core Automated Financial Matrix Calculation Module
 */
include("db.php");

function computePatientBill($conn, $patient_id) {
    $doctor_fee = 0.00;
    $lab_fee = 0.00;
    $med_fee = 0.00;

    // 1. Compile Doctor consultation fees from laboratory_tests assignments
    $doc_q = $conn->prepare("SELECT SUM(doctor_consultation_fee) as total_doc FROM laboratory_tests WHERE patient_id = ?");
    $doc_q->bind_param("i", $patient_id);
    $doc_q->execute();
    $doc_res = $doc_q->get_result()->fetch_assoc();
    $doctor_fee = (float)($doc_res['total_doc'] ?? 0.00);

    // 2. Compile individual Lab tests from your active lab_request_tests records
    $lab_q = $conn->prepare("
        SELECT SUM(lrt.test_cost) as total_lab 
        FROM lab_request_tests lrt
        INNER JOIN lab_requests lr ON lrt.lab_request_id = lr.lab_request_id
        WHERE lr.patient_id = ?
    ");
    $lab_q->bind_param("i", $patient_id);
    $lab_q->execute();
    $lab_res = $lab_q->get_result()->fetch_assoc();
    $lab_fee = (float)($lab_res['total_lab'] ?? 0.00);

    // 3. Compile Prescription costs directly out of your prescription_items table
    $med_q = $conn->prepare("
        SELECT SUM(pi.unit_price) as total_med 
        FROM prescription_items pi
        INNER JOIN prescriptions p ON pi.prescription_id = p.prescription_id
        WHERE p.patient_id = ?
    ");
    $med_q->bind_param("i", $patient_id);
    $med_q->execute();
    $med_res = $med_q->get_result()->fetch_assoc();
    $med_fee = (float)($med_res['total_med'] ?? 0.00);

    // Calculate Grand Combined Total
    $grand_total = $doctor_fee + $lab_fee + $med_fee;
    $invoice_code = "INV-" . date("Y") . "-" . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

    // 4. Update or generate invoice tracking metrics
    $save_q = $conn->prepare("
        INSERT INTO invoices (invoice_code, patient_id, doctor_fee_total, lab_tests_total, medications_total, grand_total, amount_paid, balance_due, status)
        VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, 'Pending')
        ON DUPLICATE KEY UPDATE doctor_fee_total = ?, lab_tests_total = ?, medications_total = ?, grand_total = ?, balance_due = ?
    ");
    
    // Fixed Parameter Matrix String matching 12 specific references:
    $save_q->bind_param(
        "sidddddddddd", 
        $invoice_code, 
        $patient_id, 
        $doctor_fee, 
        $lab_fee, 
        $med_fee, 
        $grand_total, 
        $grand_total, // balance_due on insert
        $doctor_fee,  // duplicate updates start here
        $lab_fee, 
        $med_fee, 
        $grand_total, 
        $grand_total  // balance_due on update
    );
    
    return $save_q->execute();
}
?>