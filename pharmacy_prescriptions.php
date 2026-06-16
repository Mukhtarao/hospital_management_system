<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("db.php");

/* ================= DATABASE FETCH: MEDICINES ================= */
$med_query = $conn->query("SELECT medicine_id, medicine_name FROM medicines WHERE quantity > 0 ORDER BY medicine_name ASC");
$med_options = "";
if ($med_query) {
    while($m = $med_query->fetch_assoc()){
        $med_options .= "<option value='".htmlspecialchars($m['medicine_name'])."'>";
    }
}

/* ================= AUTO-ID GENERATION ================= */
$id_res = $conn->query("SELECT MAX(prescription_id) as max_id FROM prescriptions");
$id_row = $id_res->fetch_assoc();
$next_id = ($id_row['max_id'] ?? 0) + 1;
$display_id = "PX-" . str_pad($next_id, 4, '0', STR_PAD_LEFT);

/* ================= DISPENSE & SAVE LOGIC ================= */
if (isset($_POST['dispense'])) {
    $prescription_id = $next_id;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $patient_full_name = $conn->real_escape_string($first_name . " " . $last_name);

    $notes   = isset($_POST['instructions']) ? $conn->real_escape_string($_POST['instructions']) : '';
    $user_id = $_SESSION['user_id'] ?? 1;

    /* 1. Insert parent prescription record */
    $stmt = $conn->prepare(
        "INSERT INTO prescriptions (prescription_id, patient_id, doctor_id, status, created_at)
         VALUES (?, 0, ?, 'Dispensed', NOW())"
    );
    $stmt->bind_param("ii", $prescription_id, $user_id);

    if ($stmt->execute()) {
        /* 2. Loop medication rows */
        foreach ($_POST['medicine_name'] as $i => $med_name) {
            if (empty(trim($med_name))) continue;

            $dosage = $_POST['dosage'][$i]    ?? '';
            $freq   = $_POST['frequency'][$i] ?? '';
            $dur    = $_POST['duration'][$i]  ?? '';
            $qty    = (int)($_POST['quantity'][$i] ?? 0);

            /* Resolve medicine_id from name */
            $find = $conn->prepare("SELECT medicine_id FROM medicines WHERE medicine_name = ? LIMIT 1");
            $find->bind_param("s", $med_name);
            $find->execute();
            $res = $find->get_result();

            if ($row = $res->fetch_assoc()) {
                $medicine_id = $row['medicine_id'];

                /*
                 * Store patient name inside instructions so the receipt can recover it.
                 * Format:  Walk-in: John Doe | <pharmacist notes>
                 */
                $combined_notes = "Walk-in: " . $patient_full_name . " | " . $notes;

                /* 3. Insert into prescription_medicines (7 columns) */
                $stmt2 = $conn->prepare(
                    "INSERT INTO prescription_medicines
                        (prescription_id, medicine_id, dosage, frequency, duration, instructions, quantity)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt2->bind_param("iissssi",
                    $prescription_id, $medicine_id,
                    $dosage, $freq, $dur,
                    $combined_notes, $qty
                );
                $stmt2->execute();

                /* 4. Deduct stock */
                $upd = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE medicine_id = ?");
                $upd->bind_param("ii", $qty, $medicine_id);
                $upd->execute();
            }
        }

        /*
         * PRG pattern: store the new ID in session, then redirect to GET.
         * This prevents double-submission on browser refresh.
         */
        $_SESSION['show_receipt_id'] = $prescription_id;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

/* Pick up the receipt ID set before the redirect, then clear it */
$show_receipt_id = $_SESSION['show_receipt_id'] ?? null;
unset($_SESSION['show_receipt_id']);

/* Recalculate display ID after possible insert */
$id_res2   = $conn->query("SELECT MAX(prescription_id) as max_id FROM prescriptions");
$id_row2   = $id_res2->fetch_assoc();
$next_id2  = ($id_row2['max_id'] ?? 0) + 1;
$display_id = "PX-" . str_pad($next_id2, 4, '0', STR_PAD_LEFT);
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    :root {
        --hgh-dark: #052e16;
        --hgh-accent: #16a34a;
        --border-color: #eef2f6;
        --text-main: #1e293b;
        --text-muted: #64748b;
    }

    .prescribe-container { animation: fadeIn 0.6s ease-out; font-family: 'Plus Jakarta Sans', sans-serif; padding: 10px; max-width: 900px; margin: 0 auto; }

    .glass-card {
        background: #ffffff; border-radius: 28px; padding: 35px; border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.04); margin-bottom: 25px;
    }

    .section-title {
        display: flex; align-items: center; gap: 12px; font-size: 13px; font-weight: 800;
        text-transform: uppercase; letter-spacing: 1.5px; color: var(--hgh-accent); margin-bottom: 30px;
    }
    .section-title::before { content: ""; width: 4px; height: 18px; background: var(--hgh-accent); border-radius: 10px; }

    .med-item { background: #fcfdfe; padding: 25px; border-radius: 24px; border: 1.5px solid #f1f5f9; margin-bottom: 20px; position: relative; }
    .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .input-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
    .input-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }

    .input-group input, .input-group textarea {
        padding: 14px 18px; border-radius: 14px; border: 1.5px solid #e5e7eb;
        font-size: 14px; color: var(--text-main); transition: 0.3s; outline: none;
    }
    .input-group input:focus { border-color: var(--hgh-accent); }

    .qty-display {
        background: #f0fdf4 !important;
        border: 2px solid var(--hgh-accent) !important;
        color: var(--hgh-dark) !important;
        font-weight: 800;
        text-align: center;
    }

    .btn-add {
        background: transparent; color: var(--hgh-dark); border: 2px dashed #cbd5e1;
        padding: 15px; width: 100%; border-radius: 18px; font-weight: 700; cursor: pointer;
        display: flex; justify-content: center; align-items: center; gap: 10px; transition: 0.3s;
        margin-bottom: 20px;
    }
    .btn-add:hover { border-color: var(--hgh-accent); background: #f0fdf4; }

    .btn-submit {
        background: var(--hgh-dark); color: #fff; padding: 18px 45px; border-radius: 18px;
        border: none; font-weight: 700; cursor: pointer; transition: 0.3s; width: 100%;
    }
    .btn-submit:hover { background: #064e21; transform: translateY(-2px); }

    .remove-btn {
        position: absolute; top: 15px; right: 15px; background: #fee2e2; color: #dc2626;
        border: none; border-radius: 8px; padding: 5px 10px; font-size: 11px; cursor: pointer; font-weight: 700;
    }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="prescribe-container">
    <div style="margin-bottom: 30px;">
        <h2 style="color: var(--hgh-dark); font-weight: 800; font-size: 28px; margin: 0;">Pharmacy Portal</h2>
        <p style="color: var(--text-muted);">Dispensing for Walk-in Patient</p>
    </div>

    <form method="POST" id="dispenseForm">
        <div class="glass-card">
            <div class="section-title">Identity & Tracking</div>
            <div class="input-row">
                <div class="input-group">
                    <label>Prescription ID</label>
                    <input type="text" value="<?= htmlspecialchars($display_id) ?>" readonly style="background: #f8fafc;">
                </div>
                <div class="input-row" style="gap: 10px;">
                    <div class="input-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" required placeholder="Enter first name">
                    </div>
                    <div class="input-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required placeholder="Enter last name">
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="section-title">Medication & Regimen</div>
            <div id="medicationWrapper">
                <div class="med-item">
                    <div class="input-group">
                        <label>Medicine Name</label>
                        <input type="text" name="medicine_name[]" list="medList" required placeholder="Search medicine...">
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Dosage</label>
                            <input type="text" name="dosage[]" placeholder="e.g. 500mg">
                        </div>
                        <div class="input-group">
                            <label>Freq (per day)</label>
                            <input type="text" name="frequency[]" class="calc-freq" oninput="calculateTotal(this)" placeholder="e.g. 2">
                        </div>
                    </div>
                    <div class="input-row">
                        <div class="input-group">
                            <label>Days</label>
                            <input type="text" name="duration[]" class="calc-dur" oninput="calculateTotal(this)" placeholder="e.g. 7">
                        </div>
                        <div class="input-group">
                            <label>Qty</label>
                            <input type="number" name="quantity[]" class="qty-display" readonly required>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn-add" onclick="addNewRow()">+ Add Medication</button>

            <div class="input-group">
                <label>Pharmacist Instructions / Notes</label>
                <textarea name="instructions" rows="3" placeholder="Additional notes for the patient..."></textarea>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" name="dispense" class="btn-submit">Complete Sale & Print Receipt</button>
            </div>
        </div>
    </form>
</div>

<datalist id="medList"><?= $med_options ?></datalist>

<script>
    function calculateTotal(input) {
        const item = input.closest('.med-item');
        const freq = item.querySelector('.calc-freq').value;
        const dur  = item.querySelector('.calc-dur').value;
        const qtyBox = item.querySelector('.qty-display');
        const fNum = parseFloat(freq);
        const dNum = parseFloat(dur);
        if (!isNaN(fNum) && !isNaN(dNum) && fNum > 0 && dNum > 0) {
            qtyBox.value = Math.ceil(fNum * dNum);
            qtyBox.readOnly = true;
        } else {
            qtyBox.value = '';
            qtyBox.readOnly = false;
        }
    }

    function addNewRow() {
        const wrapper  = document.getElementById('medicationWrapper');
        const firstItem = document.querySelector('.med-item');
        const newItem  = firstItem.cloneNode(true);
        newItem.querySelectorAll('input').forEach(i => { i.value = ''; i.readOnly = false; });

        // Re-attach oninput for cloned frequency/duration inputs
        newItem.querySelector('.calc-freq').addEventListener('input', function(){ calculateTotal(this); });
        newItem.querySelector('.calc-dur').addEventListener('input',  function(){ calculateTotal(this); });

        const removeBtn = document.createElement('button');
        removeBtn.innerHTML  = 'Remove';
        removeBtn.className  = 'remove-btn';
        removeBtn.type       = 'button';
        removeBtn.onclick    = function() { this.closest('.med-item').remove(); };
        newItem.appendChild(removeBtn);
        wrapper.appendChild(newItem);
    }

    /* Open receipt popup — triggered after the PRG redirect */
    <?php if ($show_receipt_id): ?>
    (function() {
        const url = 'get_receipt.php?id=<?= (int)$show_receipt_id ?>';
        window.open(url, 'PharmacyReceipt', 'width=900,height=800,toolbar=no,status=no,menubar=no,scrollbars=yes');
    })();
    <?php endif; ?>
</script>