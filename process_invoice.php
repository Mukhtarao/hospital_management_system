<?php
/**
 * Hospital Management System - process_invoice.php
 * Handles the creation of a new invoice from the dashboard modal wizard.
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Include core database context
include("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Gather Basic Information
    $patient_id_raw = trim($_POST['patient_id']);
    // Clean patient ID if user entered "P006" -> keep only 6
    $patient_id = (int)preg_replace('/[^0-9]/', '', $patient_id_raw);
    
    // Fallback if ID parsing fails
    if ($patient_id <= 0) {
        $patient_id = 1; 
    }

    // 2. Collect Breakdown Items (from hidden form fields calculated by JS)
    $doctor_fee  = (float)$_POST['doctor_fee_total'];
    $lab_tests   = (float)$_POST['lab_tests_total'];
    $medications = (float)$_POST['medications_total'];
    $procedures  = (float)$_POST['procedures_total'];
    $tax         = (float)$_POST['tax_total'];
    $grand_total = (float)$_POST['grand_total'];
    
    // Check if any payment was recorded immediately via payment method selection
    $payment_method = $_POST['payment_method'] ?? '';
    $amount_paid = (!empty($payment_method)) ? $grand_total : 0.00;
    $balance_due = max(0.00, $grand_total - $amount_paid);
    
    // Define exact status match
    $status = ($balance_due <= 0) ? 'Paid' : 'Pending';

    // 3. Generate a Unique Invoice Code (e.g., INV-2024-006)
    // Find the current highest ID to iterate cleanly
    $next_id = 1;
    $res = $conn->query("SELECT MAX(invoice_id) as max_id FROM invoices");
    if ($res && $row = $res->fetch_assoc()) {
        $next_id = ((int)$row['max_id']) + 1;
    }
    $invoice_code = "INV-2024-" . str_pad($next_id, 3, '0', STR_PAD_LEFT);

    // 4. Safely Prepare Database Insertion Statement
    // Matches your exact table structures: invoice_id, invoice_code, patient_id, doctor_fee_total, lab_tests_total, medications_total, grand_total, amount_paid, balance_due, status
    $stmt = $conn->prepare("INSERT INTO invoices (invoice_code, patient_id, doctor_fee_total, lab_tests_total, medications_total, grand_total, amount_paid, balance_due, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        $stmt->bind_param("sidddddds", 
            $invoice_code, 
            $patient_id, 
            $doctor_fee, 
            $lab_tests, 
            $medications, 
            $grand_total, 
            $amount_paid, 
            $balance_due, 
            $status
        );
        
        if ($stmt->execute()) {
            // Success! Send user back to billing dashboard with validation flag
            echo "<script>alert('Invoice " . $invoice_code . " successfully generated!'); window.location.href='admin_dashboard.php?page=billing';</script>";
        } else {
            // Execution Error handling
            echo "<script>alert('Database Error: Unable to record invoice entries.'); window.history.back();</script>";
        }
        $stmt->close();
    } else {
        // Preparation Error handling
        echo "<script>alert('System Configuration Fault: Statement preparing engine failed.'); window.history.back();</script>";
    }
} else {
    // Direct link redirection fallback redirect safeguard
    header("Location: admin_dashboard.php?page=billing");
    exit();
}
?>