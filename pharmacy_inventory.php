<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include("db.php");

/* ================= DATABASE LOGIC: ADD MEDICINE ================= */
if (isset($_POST['add_medicine'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $category = $conn->real_escape_string($_POST['category']);
    $quantity = (int)$_POST['quantity'];
    $reorder = (int)$_POST['reorder_level'];
    $expiry = $_POST['expiry_date'];

    $sql = "INSERT INTO medicines (medicine_name, category, quantity, reorder_level, expiry_date)
            VALUES ('$name', '$category', $quantity, $reorder, '$expiry')";

    if ($conn->query($sql)) {
        echo "<script>alert('Medicine Added Successfully'); window.location.href='pharmacy_dashboard.php?page=inventory';</script>";
    }
}

/* ================= DATABASE LOGIC: UPDATE STOCK ================= */
if (isset($_POST['process_update'])) {
    $id = (int)$_POST['medicine_id'];
    $qty_to_add = (int)$_POST['add_quantity'];
    $sql = "UPDATE medicines SET quantity = quantity + $qty_to_add WHERE medicine_id = $id";
    if ($conn->query($sql)) {
        echo "<script>alert('Stock Updated Successfully'); window.location.href='pharmacy_dashboard.php?page=inventory';</script>";
    }
}

/* ================= DATA FETCHING & STATS ================= */
$query = $conn->query("SELECT * FROM medicines ORDER BY medicine_id ASC");
$total_meds = $conn->query("SELECT COUNT(*) as total FROM medicines")->fetch_assoc()['total'];
$low_stock = $conn->query("SELECT COUNT(*) as low FROM medicines WHERE quantity <= reorder_level")->fetch_assoc()['low'];

// EXPIRATION CALCULATION: Medicines expiring within 30 days or already expired
$expiring_count = $conn->query("SELECT COUNT(*) as expiring FROM medicines WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['expiring'];
?>

<style>
    :root {
        --hgh-green: #052e16;
        --active-green: #16a34a;
        --text-muted: #64748b;
        --danger: #dc2626;
        --warning: #d97706;
    }

    .inventory-wrapper { animation: fadeIn 0.4s ease-out; font-family: 'Plus Jakarta Sans', sans-serif; }

    /* HEADER */
    .header-section { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
    .header-section h2 { color: var(--hgh-green); font-size: 24px; font-weight: 800; margin: 0; }

    /* STATS GRID - 3 COLUMNS */
    .stats-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 35px; }
    .glass-card { background: white; border: 1px solid #eef2f6; border-radius: 24px; padding: 24px; display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
    .icon-box { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 22px; }

    /* TABLE */
    .table-container { background: white; border-radius: 28px; border: 1px solid #f1f5f9; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.02); }
    .modern-table { width: 100%; border-collapse: collapse; }
    .modern-table th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 1.2px; font-weight: 700; border-bottom: 2px solid #f8fafc; }
    .modern-table td { padding: 20px 15px; font-size: 14px; border-bottom: 1px solid #fcfdfe; }
    
    .status-pill { padding: 6px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; }
    .pill-in { background: #f0fdf4; color: var(--active-green); }
    .pill-low { background: #fffbeb; color: var(--warning); }

    /* BUTTONS */
    .btn-add-main { background: var(--hgh-green); color: white; padding: 12px 24px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
    .btn-table-action { background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 10px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-table-action:hover { border-color: var(--active-green); color: var(--active-green); }

    /* MODAL BLUR DESIGN */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(5, 46, 22, 0.4); backdrop-filter: blur(10px); z-index: 9999; align-items: center; justify-content: center; }
    .modal-sheet { background: white; width: 500px; padding: 40px; border-radius: 30px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); animation: slideUp 0.3s ease; }
    
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; }
    .form-group input { width: 100%; padding: 14px; border-radius: 12px; border: 1.5px solid #eef2f6; background: #f9fafb; font-family: inherit; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="inventory-wrapper">
    <div class="header-section">
        <div>
            <h2>Medicine Inventory</h2>
            <p style="color: var(--text-muted); font-size: 14px;">Logistics, procurement, and stock management</p>
        </div>
        <button class="btn-add-main" onclick="openInvModal('addMedicineModal')">
            <i class="fa fa-plus-circle"></i> Add New Medicine
        </button>
    </div>

    <div class="stats-container">
        <div class="glass-card">
            <div class="icon-box" style="background: #f0fdf4; color: #16a34a;"><i class="fa fa-pills"></i></div>
            <div>
                <h3 style="font-size: 22px; margin:0;"><?= $total_meds ?></h3>
                <p style="font-size: 10px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin: 5px 0 0;">Total Inventory</p>
            </div>
        </div>
        <div class="glass-card">
            <div class="icon-box" style="background: #fffbeb; color: #d97706;"><i class="fa fa-triangle-exclamation"></i></div>
            <div>
                <h3 style="font-size: 22px; margin:0;"><?= $low_stock ?></h3>
                <p style="font-size: 10px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin: 5px 0 0;">Low Stock Alerts</p>
            </div>
        </div>
        <div class="glass-card">
            <div class="icon-box" style="background: #fef2f2; color: #dc2626;"><i class="fa fa-calendar-times"></i></div>
            <div>
                <h3 style="font-size: 22px; margin:0;"><?= $expiring_count ?></h3>
                <p style="font-size: 10px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; margin: 5px 0 0;">Expiring Soon</p>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Medicine ID</th>
                    <th>Name & Category</th>
                    <th>Availability</th>
                    <th>Reorder Level</th>
                    <th>Status</th>
                    <th style="text-align: right;">Management</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $query->fetch_assoc()): 
                    $isLow = ($row['quantity'] <= $row['reorder_level']);
                ?>
                <tr>
                    <td style="font-weight: 700; color: var(--text-muted);">#M<?= str_pad($row['medicine_id'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <div style="font-weight: 700; color: var(--hgh-green);"><?= htmlspecialchars($row['medicine_name']) ?></div>
                        <div style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($row['category']) ?></div>
                    </td>
                    <td><span style="font-size: 16px; font-weight: 700;"><?= $row['quantity'] ?></span> <small style="color: var(--text-muted);">UNITS</small></td>
                    <td><?= $row['reorder_level'] ?></td>
                    <td><span class="status-pill <?= $isLow ? 'pill-low' : 'pill-in' ?>"><?= $isLow ? 'Low Stock' : 'Stable' ?></span></td>
                    <td style="text-align: right;">
                        <button class="btn-table-action" onclick="openUpdateInv(<?= $row['medicine_id'] ?>, '<?= htmlspecialchars($row['medicine_name']) ?>')">Update Stock</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addMedicineModal" class="modal-overlay">
    <div class="modal-sheet">
        <h3 style="color: var(--hgh-green); margin-bottom: 25px;">New Medicine Registration</h3>
        <form method="POST">
            <div class="form-group">
                <label>Medicine Name</label>
                <input type="text" name="name" placeholder="e.g. Aspirin" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" placeholder="e.g. Analgesic" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Initial Units</label>
                    <input type="number" name="quantity" required>
                </div>
                <div class="form-group">
                    <label>Reorder Level</label>
                    <input type="number" name="reorder_level" value="10">
                </div>
            </div>
            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date">
            </div>
            <div style="display: flex; gap: 10px; margin-top: 10px;">
                <button type="submit" name="add_medicine" class="btn-add-main" style="flex:1; justify-content:center;">Save Entry</button>
                <button type="button" class="btn-table-action" onclick="closeInvModal('addMedicineModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="updateStockModal" class="modal-overlay">
    <div class="modal-sheet">
        <h3 id="upHeader" style="color: var(--hgh-green); margin-bottom: 10px;">Stock Replenishment</h3>
        <p id="upSub" style="font-size: 13px; color: var(--text-muted); margin-bottom: 25px;"></p>
        <form method="POST">
            <input type="hidden" name="medicine_id" id="up_id">
            <div class="form-group">
                <label>Add Units to Stock</label>
                <input type="number" name="add_quantity" placeholder="0" required autofocus>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" name="process_update" class="btn-add-main" style="flex:1; justify-content:center;">Confirm Update</button>
                <button type="button" class="btn-table-action" onclick="closeInvModal('updateStockModal')">Dismiss</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openInvModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeInvModal(id) { document.getElementById(id).style.display = 'none'; }

    function openUpdateInv(id, name) {
        document.getElementById('up_id').value = id;
        document.getElementById('upHeader').innerText = "Update: " + name;
        document.getElementById('upSub').innerText = "Recording new batch intake for this medication.";
        openInvModal('updateStockModal');
    }

    // Close modal when clicking background
    window.onclick = function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.style.display = 'none';
        }
    }
</script>