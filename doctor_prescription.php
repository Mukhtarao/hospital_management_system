<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("db.php");

/* ================= DATABASE FETCH: MEDICINES ================= */
$med_query = $conn->query("SELECT medicine_name FROM medicines WHERE quantity > 0 ORDER BY medicine_name ASC");
$med_options = "";
if ($med_query) {
    while ($m = $med_query->fetch_assoc()) {
        $med_options .= "<option value='" . htmlspecialchars($m['medicine_name'], ENT_QUOTES) . "'>";
    }
}

/* ================= SAVE DATA LOGIC ================= */
if (isset($_POST['submit_prescription'])) {
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id  = $_SESSION['user_id'] ?? 1;

    /* Get active visit */
    $visit_q = $conn->query("
        SELECT visit_id
        FROM patient_visits
        WHERE patient_id = $patient_id
        AND status != 'Discharged'
        ORDER BY visit_date DESC
        LIMIT 1
    ");

    if ($visit_q && $visit_q->num_rows > 0) {
        $visit = $visit_q->fetch_assoc();
        $visit_id = (int)$visit['visit_id'];
    } else {
        $conn->query("
            INSERT INTO patient_visits (patient_id, status)
            VALUES ($patient_id, 'Pharmacy_Pending')
        ");
        $visit_id = $conn->insert_id;
    }

    /* Insert master prescription with visit_id */
    $stmt = $conn->prepare("
        INSERT INTO prescriptions 
        (patient_id, doctor_id, visit_id, status, created_at) 
        VALUES (?, ?, ?, 'Pending', NOW())
    ");
    $stmt->bind_param("iii", $patient_id, $doctor_id, $visit_id);

    if ($stmt->execute()) {
        $prescription_id = $stmt->insert_id;
        $stmt->close();

        if (isset($_POST['medicine_name']) && is_array($_POST['medicine_name'])) {
            foreach ($_POST['medicine_name'] as $i => $med_name) {
                $med_name = trim($med_name);

                if (!empty($med_name)) {
                    $dosage       = $_POST['dosage'][$i] ?? '';
                    $freq         = $_POST['frequency'][$i] ?? '';
                    $dur          = $_POST['duration'][$i] ?? '';
                    $instructions = $_POST['instructions'][$i] ?? '';
                    $qty          = (int)($_POST['quantity'][$i] ?? 0);

                    $find = $conn->prepare("
                        SELECT medicine_id, quantity, unit_price
                        FROM medicines
                        WHERE medicine_name = ?
                        LIMIT 1
                    ");
                    $find->bind_param("s", $med_name);
                    $find->execute();
                    $res = $find->get_result();

                    if ($row = $res->fetch_assoc()) {
                        $medicine_id   = (int)$row['medicine_id'];
                        $current_stock = (int)$row['quantity'];
                        $unit_price    = (float)$row['unit_price'];
                        $item_cost     = $qty * $unit_price;
                        $find->close();

                        if ($qty > 0 && $current_stock >= $qty) {

                            /* Save pharmacy prescription */
                            $ins = $conn->prepare("
                                INSERT INTO prescription_medicines
                                (prescription_id, medicine_id, dosage, frequency, duration, instructions, quantity)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $ins->bind_param(
                                "iissssi",
                                $prescription_id,
                                $medicine_id,
                                $dosage,
                                $freq,
                                $dur,
                                $instructions,
                                $qty
                            );
                            $ins->execute();
                            $ins->close();

                            /* Save billing prescription item */
                            $item = $conn->prepare("
                                INSERT INTO prescription_items
                                (
                                    prescription_id,
                                    medication_name,
                                    quantity,
                                    dosage,
                                    frequency,
                                    duration,
                                    instructions,
                                    unit_price,
                                    item_cost
                                )
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $item->bind_param(
                                "isissssdd",
                                $prescription_id,
                                $med_name,
                                $qty,
                                $dosage,
                                $freq,
                                $dur,
                                $instructions,
                                $unit_price,
                                $item_cost
                            );
                            $item->execute();
                            $item->close();

                            /* Deduct stock */
                            $upd = $conn->prepare("
                                UPDATE medicines 
                                SET quantity = quantity - ? 
                                WHERE medicine_id = ?
                            ");
                            $upd->bind_param("ii", $qty, $medicine_id);
                            $upd->execute();
                            $upd->close();

                        } else {
                            echo "<script>alert('Warning: Quantity for " . htmlspecialchars($med_name) . " exceeds current stock or is invalid!');</script>";
                        }

                    } else {
                        $find->close();
                        echo "<script>alert('Medicine not found in inventory: " . htmlspecialchars($med_name) . "');</script>";
                    }
                }
            }
        }

        $conn->query("
            UPDATE patient_visits 
            SET status = 'Pharmacy_Pending'
            WHERE visit_id = $visit_id
        ");

        echo "<script>alert('Prescription successfully sent to Pharmacy and billing updated'); window.location='doctor_dashboard.php?page=prescription';</script>";
        exit();

    } else {
        $stmt->close();
        echo "<script>alert('Error: Could not save master prescription entry.');</script>";
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    :root {
        --hgh-dark: #052e16;
        --hgh-accent: #16a34a;
        --bg-gray: #f8fafc;
        --border-color: #eef2f6;
        --text-main: #1e293b;
        --text-muted: #64748b;
    }

    .prescribe-container {
        animation: fadeIn 0.6s ease-out;
        font-family: 'Plus Jakarta Sans', sans-serif;
        padding: 10px;
        max-width: 900px;
        margin: 0 auto;
    }

    .form-header { margin-bottom: 30px; }
    .form-header h2 { color: var(--hgh-dark); font-weight: 800; font-size: 28px; margin: 0; letter-spacing: -0.5px; }
    .form-header p  { color: var(--text-muted); font-size: 15px; margin-top: 4px; }

    .glass-card {
        background: #ffffff;
        border-radius: 28px;
        padding: 35px;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.04);
        margin-bottom: 25px;
    }

    .section-title {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--hgh-accent);
        margin-bottom: 30px;
    }
    .section-title::before {
        content: "";
        width: 4px;
        height: 18px;
        background: var(--hgh-accent);
        border-radius: 10px;
    }

    .input-row  { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .full-row   { width: 100%; margin-bottom: 15px; }

    .input-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
    .input-group label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-left: 4px; }

    .input-group input,
    .input-group textarea {
        padding: 14px 18px;
        border-radius: 14px;
        border: 1.5px solid #f1f5f9;
        background: #ffffff;
        font-size: 14px;
        color: var(--text-main);
        transition: all 0.3s ease;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .input-group input:focus,
    .input-group textarea:focus {
        outline: none;
        border-color: var(--hgh-accent);
        box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.08);
    }
    .input-group textarea { resize: vertical; min-height: 80px; }

    .med-item {
        background: #fcfdfe;
        padding: 25px;
        border-radius: 24px;
        border: 1.5px solid #f1f5f9;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.01);
        position: relative;
    }

    .btn-remove-med {
        position: absolute;
        top: 20px;
        right: 20px;
        background: #fef2f2;
        color: #ef4444;
        border: none;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-remove-med:hover { background: #ef4444; color: #fff; }

    .qty-display {
        background: #f0fdf4 !important;
        border: 2px solid var(--hgh-accent) !important;
        color: var(--hgh-dark) !important;
        font-weight: 800 !important;
        text-align: center;
    }

    .btn-add {
        background: transparent;
        color: var(--hgh-dark);
        border: 2px dashed #cbd5e1;
        padding: 18px;
        width: 100%;
        border-radius: 18px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .btn-add:hover { border-color: var(--hgh-accent); color: var(--hgh-accent); background: #f0fdf4; }

    .btn-submit {
        background: var(--hgh-dark);
        color: #fff;
        padding: 18px 45px;
        border-radius: 18px;
        border: none;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 15px;
    }
    .btn-submit:hover { transform: translateY(-4px); box-shadow: 0 15px 30px rgba(5, 46, 22, 0.2); }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="prescribe-container">
    <div class="form-header">
        <h2>Doctor's Prescription Portal</h2>
        <p>Issue clinical orders with automatic stock synchronization.</p>
    </div>

    <form method="POST" id="prescForm">

        <div class="glass-card">
            <div class="section-title">Patient Identification</div>
            <div class="input-row">
                <div class="input-group">
                    <label>Patient ID Number</label>
                    <input type="number" name="patient_id" placeholder="Ex: 10023" required>
                </div>
                <div class="input-group">
                    <label>Search Full Name</label>
                    <input type="text" name="patient_name" placeholder="Find patient record...">
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="section-title">Medication &amp; Regimen</div>

            <div id="medicationWrapper">
                <div class="med-item">
                    <div class="input-group full-row">
                        <label>Medicine Name</label>
                        <input type="text" name="medicine_name[]" list="medList"
                               placeholder="Search inventory..." required>
                    </div>

                    <div class="input-row">
                        <div class="input-group">
                            <label>Dosage</label>
                            <input type="text" name="dosage[]" placeholder="e.g. 500mg">
                        </div>
                        <div class="input-group">
                            <label>Freq (Daily)</label>
                            <input type="number" name="frequency[]" class="calc-freq"
                                   placeholder="Times per day" oninput="calculateTotal(this)">
                        </div>
                    </div>

                    <div class="input-row">
                        <div class="input-group">
                            <label>Duration (Days)</label>
                            <input type="number" name="duration[]" class="calc-dur"
                                   placeholder="Total days" oninput="calculateTotal(this)">
                        </div>
                        <div class="input-group">
                            <label>Total Units</label>
                            <input type="number" name="quantity[]" class="qty-display"
                                   placeholder="0" readonly required>
                        </div>
                    </div>

                    <div class="input-group full-row" style="margin-bottom:0;">
                        <label>Instructions for Pharmacist</label>
                        <textarea name="instructions[]"
                                  placeholder="e.g. Take after meals, avoid dairy..."></textarea>
                    </div>
                </div>
            </div>

            <button type="button" class="btn-add" onclick="addNewRow()">
                <svg width="20" height="20" fill="none" stroke="currentColor"
                     stroke-width="2" viewBox="0 0 24 24">
                    <path d="M12 5v14M5 12h14"></path>
                </svg>
                Add Another Medication
            </button>
        </div>

        <datalist id="medList"><?php echo $med_options; ?></datalist>

        <div class="glass-card">
            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" name="submit_prescription" class="btn-submit">
                    Finalize &amp; Dispatch Prescription
                </button>
            </div>
        </div>

    </form>
</div>

<script>
    function calculateTotal(input) {
        const item = input.closest('.med-item');
        const freq = item.querySelector('.calc-freq').value;
        const dur  = item.querySelector('.calc-dur').value;
        const qtyBox = item.querySelector('.qty-display');
        qtyBox.value = (freq && dur) ? parseInt(freq) * parseInt(dur) : '';
    }

    function addNewRow() {
        const wrapper  = document.getElementById('medicationWrapper');
        const firstItem = document.querySelector('.med-item');
        const newItem   = firstItem.cloneNode(true);

        newItem.querySelectorAll('input, textarea').forEach(el => el.value = '');

        if (!newItem.querySelector('.btn-remove-med')) {
            const btn = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'btn-remove-med';
            btn.innerHTML = 'Remove';
            btn.onclick   = function () { newItem.remove(); };
            newItem.appendChild(btn);
        }

        newItem.style.opacity = '0';
        wrapper.appendChild(newItem);
        setTimeout(() => {
            newItem.style.transition = 'opacity 0.4s ease';
            newItem.style.opacity    = '1';
        }, 10);
    }
</script>