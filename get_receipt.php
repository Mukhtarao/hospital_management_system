<?php
include("db.php");

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Prescription ID is missing.");
}

$id = intval($_GET['id']);

$query = $conn->query("
    SELECT p.*, pt.full_name AS registered_patient, u.full_name AS doctor_name
    FROM prescriptions p
    LEFT JOIN patients pt ON p.patient_id = pt.patient_id
    LEFT JOIN users u ON p.doctor_id = u.user_id
    WHERE p.prescription_id = $id
");
$data = $query->fetch_assoc();
if (!$data) { die("Error: Prescription not found."); }

$items = $conn->query("
    SELECT pm.*, m.medicine_name
    FROM prescription_medicines pm
    JOIN medicines m ON pm.medicine_id = m.medicine_id
    WHERE pm.prescription_id = $id
");

/* ---- Resolve patient display name ---- */
$patient_display_name = $data['registered_patient'] ?? null;

if (!$patient_display_name) {
    $name_query = $conn->query("SELECT instructions FROM prescription_medicines WHERE prescription_id = $id LIMIT 1");
    
    // Check if the query returned a valid row
    if ($name_query && $name_row = $name_query->fetch_assoc()) {
        $instructions_text = $name_row['instructions'] ?? '';
        
        if (!empty($instructions_text) && preg_match('/^Walk-in:\s*(.+?)\s*\|/i', $instructions_text, $m)) {
            $patient_display_name = trim($m[1]);
        } else {
            $patient_display_name = "Walk-in Patient";
        }
    } else {
        $patient_display_name = "Walk-in Patient";
    }
}

$medicine_rows = [];
while ($row = $items->fetch_assoc()) {
    $medicine_rows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medication Receipt - #RX-<?= $id ?></title>
    <style>
        /* ══════════════════════════════════════
           RESET
        ══════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ══════════════════════════════════════
           SCREEN STYLES
        ══════════════════════════════════════ */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
            color: #111;
            padding: 20px;
            margin: 0;
        }

        .receipt-container {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
            padding: 28px 32px;
            border: 1px solid #ddd;
            background: #fff;
            overflow: hidden;
        }

        /* Responsive Fix */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .receipt-container { max-width: 100%; padding: 20px; }
            .info-table td { font-size: 13px; }
            table.meds th, table.meds td { font-size: 12px; padding: 8px; }
        }

        /* ── Logo ── */
        .logo { text-align: center; margin-bottom: 8px; }
        .logo img { height: 65px; display: block; margin: 0 auto 6px; }
        .logo h2  { color: #052e16; font-size: 20px; font-weight: 700; line-height: 1.2; }

        /* ── Title ── */
        .title {
            text-align: center;
            font-size: 18px;
            font-weight: 600;
            color: #052e16;
            margin: 10px 0 12px;
        }

        hr { border: none; border-top: 1px solid #ccc; margin-bottom: 14px; }

        /* ── Info rows ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .info-table td { padding: 3px 6px; vertical-align: top; line-height: 1.5; }
        .info-table .lbl  { font-weight: 700; white-space: nowrap; width: 130px; }
        .info-table .val  { }
        .info-table .lbl2 { font-weight: 700; white-space: nowrap; width: 80px; text-align: right; padding-left: 20px; }
        .info-table .val2 { text-align: right; }

        /* ── Medications table ── */
        .table-wrap { width: 100%; }

        table.meds {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 13px;
        }
        table.meds col.c-med { width: 30%; }
        table.meds col.c-dos { width: 15%; }
        table.meds col.c-frq { width: 20%; }
        table.meds col.c-dur { width: 15%; }
        table.meds col.c-qty { width: 20%; }

        table.meds th {
            background: #f3f4f6;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #ddd;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }
        table.meds td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            vertical-align: top;
            word-break: break-word;
            white-space: normal;
        }
        table.meds td:first-child { font-weight: 700; }

        /* ── Signature ── */
        .signature { margin-top: 50px; text-align: center; font-size: 13px; color: #333; }
        .sig-row { display: flex; align-items: flex-end; justify-content: center; gap: 6px; margin-bottom: 4px; }
        .sig-line { width: 180px; border-bottom: 1px solid #555; display: inline-block; }
        .sig-hint { color: #666; font-size: 12px; }

        /* ── Screen buttons ── */
        .no-print-btn { text-align: center; margin-top: 16px; }
        .no-print-btn button {
            padding: 8px 22px; font-size: 13px; cursor: pointer;
            border: 1px solid #bbb; background: #fff; border-radius: 4px;
            margin: 0 4px; font-family: inherit;
        }
        .no-print-btn button:hover { background: #f5f5f5; }

        /* ══════════════════════════════════════
           PRINT STYLES
        ══════════════════════════════════════ */
        @media print {
            @page { size: A4 portrait; margin: 12mm 15mm; }

            html { zoom: 1 !important; }

            body {
                padding: 0 !important;
                margin: 0 !important;
                background: #fff !important;
                font-size: 11pt !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                display: flex !important;
                justify-content: center !important;
                align-items: flex-start !important;
            }

            .receipt-container {
                width: 100% !important;
                max-width: 760px !important;
                zoom: 1 !important;
                transform: none !important;
                margin: 0 auto !important;
                padding: 28px 32px !important;
                border: 1px solid #ddd !important;
                background: #fff !important;
                position: static !important;
                page-break-inside: avoid !important;
            }

            .no-print-btn { display: none !important; }
            table.meds { table-layout: fixed !important; width: 100% !important; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="receipt-container" id="receipt">

    <div class="logo">
        <img src="images/logo.png" alt="HGH Logo" onerror="this.style.display='none'">
        <h2>HARGEISA GROUP HOSPITAL</h2>
    </div>

    <div class="title">Official Medication Receipt</div>
    <hr>

    <table class="info-table">
        <tr>
            <td class="lbl">Prescription ID:</td>
            <td class="val">#RX-<?= $id ?></td>
            <td class="lbl2">Issued By:</td>
            <td class="val2"><?= htmlspecialchars($data['doctor_name'] ?? 'Pharmacy Staff') ?></td>
        </tr>
        <tr>
            <td class="lbl">Patient Name:</td>
            <td class="val"><?= htmlspecialchars($patient_display_name) ?></td>
            <td class="lbl2">Date:</td>
            <td class="val2"><?= isset($data['created_at']) ? date("d M Y, h:i A", strtotime($data['created_at'])) : 'N/A' ?></td>
        </tr>
    </table>

    <div class="table-wrap">
        <table class="meds">
            <colgroup>
                <col class="c-med">
                <col class="c-dos">
                <col class="c-frq">
                <col class="c-dur">
                <col class="c-qty">
            </colgroup>
            <thead>
                <tr>
                    <th>Medicine Name</th>
                    <th>Dosage</th>
                    <th>Freq (per day)</th>
                    <th>Days</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($medicine_rows)): ?>
                    <?php foreach ($medicine_rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['medicine_name'] ?? 'Unknown Medicine') ?></td>
                        <td><?= htmlspecialchars($row['dosage'] ?? '-')  ?></td>
                        <td><?= htmlspecialchars($row['frequency'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['duration'] ?? '-')  ?></td>
                        <td><?= htmlspecialchars($row['quantity'] ?? '0')  ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">No medications found on this clinical entry.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="signature">
        <div class="sig-row">
            <span>Dispensed by:</span>
            <span class="sig-line"></span>
        </div>
        <p class="sig-hint">Pharmacist Signature &amp; Stamp</p>
    </div>

</div>


</div>

<script>
    var receipt     = document.getElementById('receipt');
    var designWidth = 700;

    function scaleReceipt() {
        var available = document.documentElement.clientWidth - 20;
        if (available < designWidth) {
            var z = available / designWidth;
            receipt.style.zoom = z;
        } else {
            receipt.style.zoom = 1;
        }
    }

    scaleReceipt();
    window.addEventListener('resize', scaleReceipt);

    window.addEventListener('beforeprint', function () {
        receipt.style.zoom      = '';
        receipt.style.transform = '';
    });

    window.addEventListener('afterprint', function () {
        scaleReceipt();
    });
</script>

</body>
</html>