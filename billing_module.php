<?php
include_once("db.php");

function getLivePatientBill($conn, $visit_id) {
    $visit_id = (int)$visit_id;

    if ($visit_id <= 0) {
        return false;
    }

    $visit_stmt = $conn->prepare("SELECT visit_id, patient_id, status FROM patient_visits WHERE visit_id = ? LIMIT 1");
    $visit_stmt->bind_param("i", $visit_id);
    $visit_stmt->execute();
    $visit = $visit_stmt->get_result()->fetch_assoc();

    if (!$visit) {
        return false;
    }

    $patient_id = (int)$visit['patient_id'];
    $visit_status = $visit['status'];

    $consultation_total = 0.00;
    $doctor_fee_total = 0.00;
    $lab_tests_total = 0.00;
    $procedures_total = 0.00;
    $medications_total = 0.00;
    $discount_amount = 0.00;

    $consult_stmt = $conn->prepare("SELECT COALESCE(SUM(consultation_fee), 0) AS total FROM consultations WHERE visit_id = ?");
    $consult_stmt->bind_param("i", $visit_id);
    $consult_stmt->execute();
    $consultation_total = (float)($consult_stmt->get_result()->fetch_assoc()['total'] ?? 0.00);

    $lab_stmt = $conn->prepare("\n        SELECT lrt.test_type, lrt.test_cost\n        FROM lab_request_tests lrt\n        INNER JOIN lab_requests lr ON lrt.lab_request_id = lr.lab_request_id\n        WHERE lr.visit_id = ?\n    ");
    $lab_stmt->bind_param("i", $visit_id);
    $lab_stmt->execute();
    $lab_res = $lab_stmt->get_result();

    while ($test = $lab_res->fetch_assoc()) {
        $name = strtolower(trim($test['test_type'] ?? ''));
        $cost = (float)$test['test_cost'];

        if (strpos($name, 'x-ray') !== false || strpos($name, 'xray') !== false ||
            strpos($name, 'ct') !== false || strpos($name, 'mri') !== false ||
            strpos($name, 'ultrasound') !== false) {
            $procedures_total += $cost;
        } else {
            $lab_tests_total += $cost;
        }
    }

    $med_stmt = $conn->prepare("\n        SELECT COALESCE(SUM(pi.item_cost), 0) AS total\n        FROM prescription_items pi\n        INNER JOIN prescriptions p ON pi.prescription_id = p.prescription_id\n        WHERE p.visit_id = ?\n    ");
    $med_stmt->bind_param("i", $visit_id);
    $med_stmt->execute();
    $medications_total = (float)($med_stmt->get_result()->fetch_assoc()['total'] ?? 0.00);

    $subtotal = $consultation_total + $doctor_fee_total + $lab_tests_total + $procedures_total + $medications_total;
    $tax_amount = round($subtotal * 0.10, 2);
    $grand_total = round($subtotal + $tax_amount - $discount_amount, 2);

    return [
        'visit_id' => $visit_id,
        'patient_id' => $patient_id,
        'visit_status' => $visit_status,
        'consultation_total' => $consultation_total,
        'doctor_fee_total' => $doctor_fee_total,
        'lab_tests_total' => $lab_tests_total,
        'medications_total' => $medications_total,
        'procedures_total' => $procedures_total,
        'tax_amount' => $tax_amount,
        'discount_amount' => $discount_amount,
        'grand_total' => $grand_total
    ];
}

function getLivePatientBillByPatient($conn, $patient_id) {
    $patient_id = (int)$patient_id;

    $stmt = $conn->prepare("\n        SELECT visit_id\n        FROM patient_visits\n        WHERE patient_id = ?\n        AND status != 'Discharged'\n        ORDER BY visit_id DESC\n        LIMIT 1\n    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $visit = $stmt->get_result()->fetch_assoc();

    if (!$visit) {
        return false;
    }

    return getLivePatientBill($conn, (int)$visit['visit_id']);
}

function computePatientBill($conn, $visit_id) {
    $visit_id = (int)$visit_id;

    if ($visit_id <= 0) {
        return false;
    }

    $bill = getLivePatientBill($conn, $visit_id);

    if (!$bill) {
        return false;
    }

    $subtotal =
        (float)$bill['consultation_total'] +
        (float)$bill['doctor_fee_total'] +
        (float)$bill['lab_tests_total'] +
        (float)$bill['medications_total'] +
        (float)$bill['procedures_total'];

    if ($subtotal <= 0) {
        return false;
    }

    $check = $conn->prepare("SELECT invoice_id FROM invoices WHERE visit_id = ? LIMIT 1");
    $check->bind_param("i", $visit_id);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();

    if ($existing) {
        return (int)$existing['invoice_id'];
    }

    $invoice_code = "INV-" . date("Y") . "-" . str_pad(rand(1, 99999), 5, "0", STR_PAD_LEFT);
    $amount_paid = 0.00;
    $balance_due = (float)$bill['grand_total'];
    $status = "Pending";

    $stmt = $conn->prepare("\n        INSERT INTO invoices\n        (\n            invoice_code, patient_id, visit_id,\n            consultation_total, doctor_fee_total, lab_tests_total, medications_total, procedures_total,\n            grand_total, tax_amount, discount_amount, amount_paid, balance_due, status, created_at, updated_at\n        )\n        VALUES\n        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())\n    ");

    $stmt->bind_param(
        "siidddddddddds",
        $invoice_code,
        $bill['patient_id'],
        $bill['visit_id'],
        $bill['consultation_total'],
        $bill['doctor_fee_total'],
        $bill['lab_tests_total'],
        $bill['medications_total'],
        $bill['procedures_total'],
        $bill['grand_total'],
        $bill['tax_amount'],
        $bill['discount_amount'],
        $amount_paid,
        $balance_due,
        $status
    );

    if ($stmt->execute()) {
        return $conn->insert_id;
    }

    return false;
}
?>
