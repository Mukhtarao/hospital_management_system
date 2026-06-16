<?php
/**
 * Hospital Management System - admin_billing.php
 * Dynamic Accounting Ledger & Invoice Matrix Component with Integrated Creation & View Wizards
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Include core database context
include("db.php");

/* ==========================================================================
   SECTION 1: AUTOMATIC LEDGER AGGREGATION & UPDATE ENGINE
   IMPORTANT FIX: calculate each invoice by visit_id, not patient_id.
   ========================================================================== */
$invoice_res = $conn->query("SELECT invoice_id, patient_id, visit_id, amount_paid FROM invoices");
if ($invoice_res) {
    while ($inv = $invoice_res->fetch_assoc()) {
        $inv_id   = (int)$inv['invoice_id'];
        $pat_id   = (int)$inv['patient_id'];
        $visit_id = (int)$inv['visit_id'];
        $paid     = (float)$inv['amount_paid'];

        if ($visit_id <= 0) {
            continue;
        }

        $consultation = 0.00;
        $doctor_fee   = 0.00;
        $lab_sum      = 0.00;
        $procedures   = 0.00;
        $med_sum      = 0.00;

        // 1. Consultation fee for this visit only
        $consult_q = $conn->query("
            SELECT COALESCE(SUM(consultation_fee), 0) AS total
            FROM consultations
            WHERE visit_id = $visit_id
        ");
        if ($consult_q) {
            $consultation = (float)($consult_q->fetch_assoc()['total'] ?? 0.00);
        }

        // 2. Lab test cost for this visit only
        $lab_q = $conn->query("
            SELECT test_type, test_cost
            FROM lab_request_tests lrt
            INNER JOIN lab_requests lr ON lrt.lab_request_id = lr.lab_request_id
            WHERE lr.visit_id = $visit_id
        ");
        if ($lab_q) {
            while ($test = $lab_q->fetch_assoc()) {
                $name = strtolower(trim($test['test_type'] ?? ''));
                $cost = (float)$test['test_cost'];

                // Keep your existing UI breakdown: imaging/procedure tests go to procedures_total
                if (strpos($name, 'x-ray') !== false || strpos($name, 'xray') !== false ||
                    strpos($name, 'ct') !== false || strpos($name, 'mri') !== false ||
                    strpos($name, 'ultrasound') !== false) {
                    $procedures += $cost;
                } else {
                    $lab_sum += $cost;
                }
            }
        }

        // 3. Prescription medicine cost for this visit only
        $med_q = $conn->query("
            SELECT COALESCE(SUM(pi.item_cost), 0) AS total
            FROM prescription_items pi
            INNER JOIN prescriptions p ON pi.prescription_id = p.prescription_id
            WHERE p.visit_id = $visit_id
        ");
        if ($med_q) {
            $med_sum = (float)($med_q->fetch_assoc()['total'] ?? 0.00);
        }

        $subtotal = $consultation + $doctor_fee + $lab_sum + $med_sum + $procedures;
        $tax = round($subtotal * 0.10, 2);
        $grand_total = round($subtotal + $tax, 2);
        $remaining_balance = max(0.00, $grand_total - $paid);
        $new_status = ($remaining_balance <= 0) ? 'Paid' : (($paid > 0) ? 'Partial' : 'Pending');

        $up_stmt = $conn->prepare("UPDATE invoices SET
            consultation_total = ?, doctor_fee_total = ?, lab_tests_total = ?,
            medications_total = ?, procedures_total = ?, grand_total = ?,
            tax_amount = ?, balance_due = ?, status = ?
            WHERE invoice_id = ? AND visit_id = ?");

        if ($up_stmt) {
            $up_stmt->bind_param(
                "ddddddddsii",
                $consultation,
                $doctor_fee,
                $lab_sum,
                $med_sum,
                $procedures,
                $grand_total,
                $tax,
                $remaining_balance,
                $new_status,
                $inv_id,
                $visit_id
            );
            $up_stmt->execute();
            $up_stmt->close();
        }
    }
}

/* ==========================================================================
   SECTION 2: CASH RECEIPT & PAYMENT TRANSACTION PROCESSING
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_cash'])) {
    $inv_id = (int)$_POST['invoice_id'];
    $payment_received = (float)$_POST['cash_amount'];

    $stmt = $conn->prepare("SELECT grand_total, amount_paid FROM invoices WHERE invoice_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $inv_id);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($inv) {
            $total_paid = $inv['amount_paid'] + $payment_received;
            $remaining_balance = max(0, $inv['grand_total'] - $total_paid);
            $new_status = ($remaining_balance <= 0) ? 'Paid' : (($total_paid > 0) ? 'Partial' : 'Pending');

            $update = $conn->prepare("UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ? WHERE invoice_id = ?");
            if ($update) {
                $update->bind_param("ddsi", $total_paid, $remaining_balance, $new_status, $inv_id);
                $update->execute();
                $update->close();
            }
            echo "<script>alert('Payment transaction successfully registered!'); window.location.href=window.location.href;</script>";
            exit();
        }
    }
}

/* ==========================================================================
   SECTION 3: METRICS RENDERING & RECORD DISCOVERY PIPELINES
   ========================================================================== */
$metrics = ['collected' => 0.00, 'outstanding' => 0.00, 'consults' => 0.00, 'labs' => 0.00, 'meds' => 0.00, 'procedures' => 0.00, 'paid_count' => 0, 'pending_count' => 0];

$totals_q = $conn->query("SELECT 
    SUM(amount_paid) as collected, SUM(balance_due) as outstanding,
    SUM(consultation_total) as consults, SUM(lab_tests_total) as labs,
    SUM(medications_total) as meds, SUM(procedures_total) as procedures 
    FROM invoices");

if ($totals_q) {
    $res = $totals_q->fetch_assoc();
    foreach (['collected', 'outstanding', 'consults', 'labs', 'meds', 'procedures'] as $field) {
        $metrics[$field] = (float)($res[$field] ?? 0.00);
    }
}

$p_count = $conn->query("SELECT COUNT(*) as cnt FROM invoices WHERE status='Paid'");
if ($p_count) $metrics['paid_count'] = (int)$p_count->fetch_assoc()['cnt'];

$pn_count = $conn->query("SELECT COUNT(*) as cnt FROM invoices WHERE status IN ('Pending','Partial')");
if ($pn_count) $metrics['pending_count'] = (int)$pn_count->fetch_assoc()['cnt'];

// Query table values safely
$records_q = $conn->query("SELECT i.*, COALESCE(p.full_name, 'Walk-in Patient') as patient_name, p.email as patient_meta, DATE_FORMAT(i.created_at, '%Y-%m-%d') as invoice_date
                            FROM invoices i 
                            LEFT JOIN patients p ON i.patient_id = p.patient_id 
                            ORDER BY i.invoice_id DESC");
$records = [];
if ($records_q) {
    $records = $records_q->fetch_all(MYSQLI_ASSOC);
}
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        corePlugins: { preflight: false }
    }
</script>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(2px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeIn { animation: fadeIn 0.2s ease-out forwards; }
    .hidden-modal { display: none !important; }

    /* ==========================================================================
       SECTION 4: PRINTER ISOLATION STYLES SHEET
       ========================================================================== */
    @media print {
        body *, html *, .billing-system-canvas, #invoiceModal, .fixed, .bg-gray-900 {
            visibility: hidden !important;
            background: none !important;
        }
        body, html {
            background-color: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        #invoiceViewerModal, #invoiceViewerModal * {
            visibility: visible !important;
        }
        #invoiceViewerModal {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            box-shadow: none !important;
            border: none !important;
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        #invoiceViewerModal .bg-white {
            border: none !important;
            box-shadow: none !important;
            width: 100% !important;
            max-width: 500px !important;
            margin: 0 auto !important;
            padding: 20px !important;
        }
        #invoiceViewerModal .flex.gap-2, #invoiceViewerModal button, #invoiceViewerModal .close-btn {
            display: none !important;
            visibility: hidden !important;
        }
    }
</style>

<div class="billing-system-canvas" style="font-family: 'Inter', sans-serif; padding: 24px; background-color: #fafbfe; min-height: 100vh; color: #1e293b;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.5px;">Billing & Payments</h1>
            <p style="font-size: 14px; color: #64748b; margin: 4px 0 0 0;">Manage patient invoices, service costs, and payment records</p>
        </div>
        <button onclick="toggleModal(true)" style="background-color: #10b981; color: #ffffff; border: none; padding: 10px 18px; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: opacity 0.2s;">
            <span style="font-size: 16px;">+</span> New Invoice
        </button>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 28px;">
        <div style="background: #ffffff; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Collected</span>
                <h3 style="font-size: 26px; font-weight: 700; color: #10b981; margin: 6px 0 0 0;">$<?= number_format($metrics['collected'], 2) ?></h3>
            </div>
            <div style="width: 42px; height: 42px; background: #e6fbf4; color: #10b981; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: bold;">$</div>
        </div>

        <div style="background: #ffffff; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Outstanding</span>
                <h3 style="font-size: 26px; font-weight: 700; color: #f97316; margin: 6px 0 0 0;">$<?= number_format($metrics['outstanding'], 2) ?></h3>
            </div>
            <div style="width: 42px; height: 42px; background: #fff7ed; color: #f97316; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 16px;">⚠️</div>
        </div>

        <div style="background: #ffffff; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Paid Invoices</span>
                <h3 style="font-size: 26px; font-weight: 700; color: #0f172a; margin: 6px 0 0 0;"><?= $metrics['paid_count'] ?></h3>
            </div>
            <div style="width: 42px; height: 42px; background: #f1f5f9; color: #64748b; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 16px;">✓</div>
        </div>

        <div style="background: #ffffff; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Pending/Overdue</span>
                <h3 style="font-size: 26px; font-weight: 700; color: #ef4444; margin: 6px 0 0 0;"><?= $metrics['pending_count'] ?></h3>
            </div>
            <div style="width: 42px; height: 42px; background: #fee2e2; color: #ef4444; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 16px;">🕒</div>
        </div>
    </div>

    <div style="display: flex; gap: 8px; margin-bottom: 24px; background: #e2e8f0; padding: 4px; border-radius: 10px; width: max-content;">
        <button id="btn-all-invoices" onclick="toggleDashboardTab('tab-all-invoices')" style="border: none; padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 8px; cursor: pointer; background: #ffffff; color: #0f172a; transition: all 0.2s;">All Invoices</button>
        <button id="btn-cost-breakdown" onclick="toggleDashboardTab('tab-cost-breakdown')" style="border: none; padding: 8px 16px; font-size: 13px; font-weight: 600; border-radius: 8px; cursor: pointer; background: transparent; color: #475569; transition: all 0.2s;">Cost Breakdown</button>
    </div>

    <div id="tab-all-invoices" class="billing-dashboard-pane" style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01);">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 600;">
                        <th style="padding: 16px;">Invoice ID</th>
                        <th style="padding: 16px;">Patient</th>
                        <th style="padding: 16px;">Total</th>
                        <th style="padding: 16px;">Paid</th>
                        <th style="padding: 16px;">Balance</th>
                        <th style="padding: 16px;">Status</th>
                        <th style="padding: 16px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="7" style="padding: 32px; text-align: center; color: #64748b;">No active bookkeeping billing items mapped to this installation database.</td></tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                                <td style="padding: 16px; font-weight: 700; color: #0f172a;">INV-2026-<?= str_pad($row['invoice_id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td style="padding: 16px;">
                                    <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['patient_name']) ?></div>
                                    <div style="font-size: 11px; color: #64748b;">P<?= str_pad($row['patient_id'], 3, '0', STR_PAD_LEFT) ?></div>
                                </td>
                                <td style="padding: 16px; font-weight: 600; color: #0f172a;">$<?= number_format($row['grand_total'], 2) ?></td>
                                <td style="padding: 16px; color: #10b981; font-weight: 500;">$<?= number_format($row['amount_paid'], 2) ?></td>
                                <td style="padding: 16px; color: <?= $row['balance_due'] > 0 ? '#f97316' : '#64748b' ?>; font-weight: 500;">$<?= number_format($row['balance_due'], 2) ?></td>
                                <td style="padding: 16px;">
                                    <?php 
                                        $status_label = ucfirst(strtolower($row['status']));
                                        $bg_color = '#f1f5f9'; $txt_color = '#475569';
                                        if ($status_label === 'Paid') { $bg_color = '#d1fae5'; $txt_color = '#065f46'; }
                                        elseif ($status_label === 'Partial') { $bg_color = '#dbeafe'; $txt_color = '#1e40af'; }
                                        elseif ($status_label === 'Pending') { $bg_color = '#ffedd5'; $txt_color = '#9a3412'; }
                                    ?>
                                    <span style="padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: <?= $bg_color ?>; color: <?= $txt_color ?>; display: inline-block;">
                                        <?= $status_label ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                        <button type="button" class="bg-gray-100 text-gray-700 hover:bg-gray-200 border-none font-semibold px-3 py-1.5 rounded-lg text-xs cursor-pointer transition-colors" onclick='openInvoiceViewerModal(<?= json_encode($row) ?>)'>View</button>
                                        <?php if ($row['balance_due'] > 0): ?>
                                            <button type="button" onclick="triggerCashCollectionGateway(<?= $row['invoice_id'] ?>, <?= $row['balance_due'] ?>)" style="background: #10b981; color: #ffffff; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; transition: opacity 0.2s;">Pay</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="tab-cost-breakdown" class="billing-dashboard-pane" style="display: none; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; padding: 20px; background-color: #f8fafc; border-bottom: 1px solid #e2e8f0;">
            <div style="background: #ffffff; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Consultations</span>
                <h4 style="margin: 4px 0 0 0; color: #0f172a; font-size: 18px; font-weight: 700;">$<?= number_format($metrics['consults'], 2) ?></h4>
            </div>
            <div style="background: #ffffff; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Lab Profiles</span>
                <h4 style="margin: 4px 0 0 0; color: #0f172a; font-size: 18px; font-weight: 700;">$<?= number_format($metrics['labs'], 2) ?></h4>
            </div>
            <div style="background: #ffffff; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Pharmacy Dispensing</span>
                <h4 style="margin: 4px 0 0 0; color: #0f172a; font-size: 18px; font-weight: 700;">$<?= number_format($metrics['meds'], 2) ?></h4>
            </div>
            <div style="background: #ffffff; padding: 14px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <span style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Procedures & Imaging</span>
                <h4 style="margin: 4px 0 0 0; color: #0f172a; font-size: 18px; font-weight: 700;">$<?= number_format($metrics['procedures'], 2) ?></h4>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                <thead>
                    <tr style="background: #ffffff; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 600;">
                        <th style="padding: 14px 16px;">Invoice</th>
                        <th style="padding: 14px 16px;">Patient Name</th>
                        <th style="padding: 14px 16px;">Consultation</th>
                        <th style="padding: 14px 16px;">Lab Tests</th>
                        <th style="padding: 14px 16px;">Medications</th>
                        <th style="padding: 14px 16px;">Procedures</th>
                        <th style="padding: 14px 16px;">Tax (10%)</th>
                        <th style="padding: 14px 16px;">Grand Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="8" style="padding: 32px; text-align: center; color: #64748b;">No matching cost breakdown lines resolved.</td></tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                                <td style="padding: 14px 16px; font-weight: 600; color: #0f172a;">INV-2026-<?= str_pad($row['invoice_id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td style="padding: 14px 16px; color: #334155; font-weight: 500;"><?= htmlspecialchars($row['patient_name']) ?></td>
                                <td style="padding: 14px 16px; color: #475569;">$<?= number_format($row['consultation_total'], 2) ?></td>
                                <td style="padding: 14px 16px; color: #475569;">$<?= number_format($row['lab_tests_total'], 2) ?></td>
                                <td style="padding: 14px 16px; color: #475569;">$<?= number_format($row['medications_total'], 2) ?></td>
                                <td style="padding: 14px 16px; color: #475569;">$<?= number_format($row['procedures_total'], 2) ?></td>
                                <td style="padding: 14px 16px; color: #64748b;">$<?= number_format($row['tax_amount'], 2) ?></td>
                                <td style="padding: 14px 16px; font-weight: 700; color: #0f172a;">$<?= number_format($row['grand_total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form id="cashPaymentSubmissionEngine" method="POST" style="display: none !important;">
    <input type="hidden" name="post_cash" value="1">
    <input type="hidden" name="invoice_id" id="hidden_target_invoice_id" value="">
    <input type="hidden" name="cash_amount" id="hidden_target_cash_amount" value="">
</form>

<div id="invoiceModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden-modal">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] flex flex-col font-sans">
        <div class="flex justify-between items-center pb-3 border-b border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 m-0">Create New Invoice</h3>
            <button type="button" onclick="toggleModal(false)" class="text-gray-400 hover:text-gray-600 text-2xl border-none bg-transparent cursor-pointer transition-colors">&times;</button>
        </div>

        <form id="invoiceForm" action="process_invoice.php" method="POST" class="flex-1 overflow-y-auto my-4 pr-1 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patient ID</label>
                    <input type="text" name="patient_id" id="modal_patient_id" required placeholder="e.g. P006" class="w-full border border-gray-200 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 box-border">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patient Name</label>
                    <input type="text" name="patient_name" id="modal_patient_name" required placeholder="Full patient name" class="w-full border border-gray-200 rounded-lg p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 box-border">
                </div>
            </div>

            <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Add Services & Items</span>
                <div class="flex gap-2">
                    <select id="itemSelector" class="flex-1 border border-gray-200 rounded-lg p-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 box-border">
                        <option value="" data-type="" data-price="0">Select a service or medication</option>
                        <optgroup label="Consultations">
                            <option value="General Consultation" data-type="doctor_fee" data-price="50.00">General Consultation ($50.00)</option>
                        </optgroup>
                        <optgroup label="Lab Tests">
                            <option value="Complete Blood Count (CBC)" data-type="lab_test" data-price="35.00">Complete Blood Count ($35.00)</option>
                        </optgroup>
                        <optgroup label="Medications">
                            <option value="Amoxicillin 500mg" data-type="medication" data-price="18.00">Amoxicillin 500mg ($18.00)</option>
                            <option value="Paracetamol 500mg" data-type="medication" data-price="5.00">Paracetamol 500mg ($5.00)</option>
                        </optgroup>
                    </select>
                    <input type="number" id="itemQty" value="1" min="1" class="w-16 border border-gray-200 rounded-lg p-2 text-center text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 box-border">
                    <button type="button" id="addItemBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 rounded-lg flex items-center justify-center text-lg border-none cursor-pointer transition-colors">+</button>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-3 text-sm space-y-1.5 border border-gray-100">
                <div class="flex justify-between text-gray-600"><span>Subtotal:</span><span id="modal_subtotal_lbl">$0.00</span></div>
                <div class="flex justify-between text-gray-600"><span>Tax (10%):</span><span id="modal_tax_lbl">$0.00</span></div>
                <div class="flex justify-between font-bold text-gray-800 pt-1.5 border-t border-gray-200 text-base">
                    <span>Grand Total:</span><span id="modal_grandtotal_lbl">$0.00</span>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="toggleModal(false)" class="px-5 py-2.5 border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-medium rounded-lg text-sm cursor-pointer transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg text-sm cursor-pointer border-none transition-colors shadow-sm">Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<div id="invoiceViewerModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden-modal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 relative flex flex-col font-sans animate-fadeIn">
        
        <div class="text-center border-b border-gray-100 pb-4">
            <h3 class="text-lg font-bold text-gray-800 m-0">Hargeisa Group of Hospitals</h3>
            <p class="text-xs text-gray-400 m-1">Hargeisa, Somaliland | Tel: +252-2-520-100</p>
        </div>

        <div class="grid grid-cols-2 gap-2 my-4 text-xs">
            <div>
                <span class="text-gray-400 block">Patient</span>
                <strong id="v_patient_name" class="text-gray-800 text-sm block">-</strong>
                <span id="v_patient_id" class="text-gray-500">-</span>
            </div>
            <div class="text-right">
                <span class="text-gray-400 block">Invoice Date</span>
                <strong id="v_invoice_date" class="text-gray-800 block">-</strong>
                <span class="text-gray-400 block mt-1">Invoice Reference</span>
                <strong id="v_invoice_code" class="text-gray-800 block">-</strong>
            </div>
        </div>

        <div class="border border-gray-100 rounded-xl overflow-hidden mb-4">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-100">
                        <th class="p-2">Service / Item</th>
                        <th class="p-2">Category</th>
                        <th class="p-2 text-right">Cost</th>
                    </tr>
                </thead>
                <tbody id="v_items_tbody">
                     </tbody>
            </table>
        </div>

        <div class="space-y-1.5 text-xs border-b border-gray-100 pb-3 mb-3 text-gray-600">
            <div class="flex justify-between"><span>Subtotal</span><span id="v_subtotal" class="font-medium text-gray-800">$0.00</span></div>
            <div class="flex justify-between text-red-500"><span>Discount</span><span id="v_discount">-$0.00</span></div>
            <div class="flex justify-between"><span>Tax (10%)</span><span id="v_tax" class="font-medium text-gray-800">$0.00</span></div>
            <div class="flex justify-between text-sm font-bold text-gray-900 pt-1.5 border-t border-gray-100">
                <span>Total</span><span id="v_total">$0.00</span>
            </div>
        </div>

        <div class="flex justify-between items-center text-xs mb-5">
            <div>
                <span class="text-gray-400 block">Paid Amount</span>
                <strong id="v_amount_paid" class="text-emerald-600 font-bold text-sm block">$0.00</strong>
            </div>
            <div class="text-right">
                <span class="text-gray-400 block">Status Matrix</span>
                <span id="v_status_badge" class="px-2.5 py-1 rounded-full font-bold inline-block text-[10px]">Paid</span>
            </div>
        </div>

        <div class="flex gap-2">
            <button type="button" onclick="closeInvoiceViewerModal()" class="close-btn flex-1 py-2.5 border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-semibold rounded-xl text-xs cursor-pointer transition-colors">Close Window</button>
            <button type="button" onclick="window.print()" class="flex-1 py-2.5 border-none bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs cursor-pointer transition-colors shadow-sm">Print Statement</button>
        </div>
    </div>
</div>

<script>
let selectedItems = [];

// Toggle Creation Modal Canvas Overlay
function toggleModal(open) {
    const modal = document.getElementById('invoiceModal');
    if (open) {
        modal.classList.remove('hidden-modal');
        selectedItems = [];
        recalculateAndInjectTotals();
    } else {
        modal.classList.add('hidden-modal');
    }
}

// Switch Viewable Sub-panes tabs
function toggleDashboardTab(targetTabId) {
    document.querySelectorAll('.billing-dashboard-pane').forEach(el => el.style.display = 'none');
    document.getElementById(targetTabId).style.display = 'block';

    const btnAll = document.getElementById('btn-all-invoices');
    const btnBreak = document.getElementById('btn-cost-breakdown');

    if (targetTabId === 'tab-all-invoices') {
        btnAll.style.background = '#ffffff'; btnAll.style.color = '#0f172a';
        btnBreak.style.background = 'transparent'; btnBreak.style.color = '#475569';
    } else {
        btnBreak.style.background = '#ffffff'; btnBreak.style.color = '#0f172a';
        btnAll.style.background = 'transparent'; btnAll.style.color = '#475569';
    }
}

// Cash Entry Shortcut Event Dispatcher
function triggerCashCollectionGateway(invoiceId, balance) {
    const cash = prompt(`Enter cash payment amount received for INV-2026-${String(invoiceId).padStart(3, '0')}:`, balance.toFixed(2));
    if (cash === null) return;
    
    const parsedAmount = parseFloat(cash);
    if (isNaN(parsedAmount) || parsedAmount <= 0) {
        alert("Invalid transaction input context.");
        return;
    }

    document.getElementById('hidden_target_invoice_id').value = invoiceId;
    document.getElementById('hidden_target_cash_amount').value = parsedAmount;
    document.getElementById('cashPaymentSubmissionEngine').submit();
}

// Open and hydrate data into the View Receipt modal window
function openInvoiceViewerModal(data) {
    document.getElementById('v_patient_name').innerText = data.patient_name;
    document.getElementById('v_patient_id').innerText = `P${String(data.patient_id).padStart(3, '0')}`;
    document.getElementById('v_invoice_date').innerText = data.invoice_date;
    document.getElementById('v_invoice_code').innerText = `INV-2026-${String(data.invoice_id).padStart(3, '0')}`;

    const tbody = document.getElementById('v_items_tbody');
    tbody.innerHTML = '';

    const categories = [
        { key: 'consultation_total', label: 'Consultation Fee', group: 'Consultation' },
        { key: 'doctor_fee_total', label: 'Specialist Treatment', group: 'Doctor Fees' },
        { key: 'lab_tests_total', label: 'Laboratory Diagnostic Panels', group: 'Lab Work' },
        { key: 'medications_total', label: 'Prescription Dispensing Portfolio', group: 'Pharmacy' },
        { key: 'procedures_total', label: 'Clinical Operations & Imaging', group: 'Procedures' }
    ];

    let computedSubtotal = 0;
    categories.forEach(cat => {
        const val = parseFloat(data[cat.key]) || 0;
        if (val > 0) {
            computedSubtotal += val;
            const tr = document.createElement('tr');
            tr.className = 'border-b border-gray-50 text-gray-700';
            tr.innerHTML = `
                <td class="p-2 font-medium">${cat.label}</td>
                <td class="p-2 text-gray-400">${cat.group}</td>
                <td class="p-2 text-right font-semibold">$${val.toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
        }
    });

    const taxAmount = parseFloat(data.tax_amount) || 0;
    const grandTotal = parseFloat(data.grand_total) || 0;
    const amountPaid = parseFloat(data.amount_paid) || 0;

    document.getElementById('v_subtotal').innerText = `$${computedSubtotal.toFixed(2)}`;
    document.getElementById('v_tax').innerText = `$${taxAmount.toFixed(2)}`;
    document.getElementById('v_total').innerText = `$${grandTotal.toFixed(2)}`;
    document.getElementById('v_amount_paid').innerText = `$${amountPaid.toFixed(2)}`;

    const badge = document.getElementById('v_status_badge');
    badge.innerText = data.status.toUpperCase();
    if (data.status === 'Paid') {
        badge.className = 'px-2.5 py-1 rounded-full font-bold inline-block text-[10px] bg-emerald-100 text-emerald-800';
    } else if (data.status === 'Partial') {
        badge.className = 'px-2.5 py-1 rounded-full font-bold inline-block text-[10px] bg-blue-100 text-blue-800';
    } else {
        badge.className = 'px-2.5 py-1 rounded-full font-bold inline-block text-[10px] bg-amber-100 text-amber-800';
    }

    document.getElementById('invoiceViewerModal').classList.remove('hidden-modal');
}

function closeInvoiceViewerModal() {
    document.getElementById('invoiceViewerModal').classList.add('hidden-modal');
}

// Add Item Event Hook inside Invoice Creator
document.getElementById('addItemBtn').addEventListener('click', function() {
    const selector = document.getElementById('itemSelector');
    const qtyInput = document.getElementById('itemQty');
    
    const selectedOption = selector.options[selector.selectedIndex];
    const itemName = selectedOption.value;
    const itemType = selectedOption.getAttribute('data-type');
    const itemPrice = parseFloat(selectedOption.getAttribute('data-price'));
    const qty = parseInt(qtyInput.value) || 1;

    if (!itemName) {
        alert('Please select a valid service or medication item first.');
        return;
    }

    selectedItems.push({
        type: itemType,
        total: itemPrice * qty
    });

    selector.selectedIndex = 0;
    qtyInput.value = 1;

    recalculateAndInjectTotals();
});

function recalculateAndInjectTotals() {
    let doctorFee = 0, labTests = 0, medications = 0, procedures = 0;

    selectedItems.forEach(item => {
        if (item.type === 'doctor_fee') doctorFee += item.total;
        else if (item.type === 'lab_test') labTests += item.total;
        else if (item.type === 'medication') medications += item.total;
        else procedures += item.total;
    });

    let subtotal = doctorFee + labTests + medications + procedures;
    let tax = subtotal * 0.10;
    let grandTotal = subtotal + tax;

    document.getElementById('modal_subtotal_lbl').innerText = `$${subtotal.toFixed(2)}`;
    document.getElementById('modal_tax_lbl').innerText = `$${tax.toFixed(2)}`;
    document.getElementById('modal_grandtotal_lbl').innerText = `$${grandTotal.toFixed(2)}`;

    updateHiddenInput('doctor_fee_total', doctorFee);
    updateHiddenInput('lab_tests_total', labTests);
    updateHiddenInput('medications_total', medications);
    updateHiddenInput('procedures_total', procedures);
    updateHiddenInput('tax_total', tax);
    updateHiddenInput('grand_total', grandTotal);
}

function updateHiddenInput(name, value) {
    let input = document.querySelector(`input[name="${name}"]`);
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        document.getElementById('invoiceForm').appendChild(input);
    }
    input.value = value.toFixed(2);
}
</script>