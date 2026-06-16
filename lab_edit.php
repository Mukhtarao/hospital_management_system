<?php
// We check if $conn exists. If not, this page was likely accessed directly.
if (!isset($conn)) {
    include("db.php");
}

/* ================= SECURITY ================= */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'lab') {
    echo "<script>window.location='index.php?error=unauthorized';</script>";
    exit();
}

/* ================= FETCH DATA ================= */
$request_id = $_GET['id'] ?? null;
$existing_data = null;

if ($request_id) {
    $request_id = mysqli_real_escape_string($conn, $request_id);
    
    // Join with tests table to get previously uploaded result_text and files
    $query = $conn->query("
        SELECT r.*, t.result_text, t.result_file, t.notes 
        FROM lab_requests r 
        LEFT JOIN lab_request_tests t ON r.lab_request_id = t.lab_request_id 
        WHERE r.lab_request_id = '$request_id'
    ");
    
    if ($query && $query->num_rows > 0) {
        $existing_data = $query->fetch_assoc();
    }
}

/* ================= UPDATE SUBMISSION ================= */
if (isset($_POST['update_record'])) {
    $rid = mysqli_real_escape_string($conn, $_POST['request_id']);
    $res_text = mysqli_real_escape_string($conn, $_POST['result_text']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $file_sql = "";
    if (!empty($_FILES['file']['name'])) {
        $file_name = time() . "_" . basename($_FILES['file']['name']);
        if (move_uploaded_file($_FILES['file']['tmp_name'], "uploads/" . $file_name)) {
            $file_sql = ", result_file = '$file_name'";
        }
    }

    // Update the test findings
    $conn->query("UPDATE lab_request_tests SET result_text = '$res_text' $file_sql, status = '$status' WHERE lab_request_id = '$rid'");
    
    // Update main request status
    $conn->query("UPDATE lab_requests SET status = '$status' WHERE lab_request_id = '$rid'");

    echo "<script>alert('Record updated successfully'); window.location='lab_dashboard.php?page=lab_records';</script>";
}
?>

<div class="edit-wrapper" style="animation: fadeIn 0.4s ease;">
    <div style="margin-bottom: 25px;">
        <h2 style="font-family:'Playfair Display', serif; color: var(--g900); font-size: 28px;">Edit Lab Record</h2>
        <p style="color: #64748b;">Review and modify findings for Request #<?= htmlspecialchars($request_id) ?></p>
    </div>

    <?php if ($existing_data): ?>
    <div style="background: #fff; border-radius: 20px; border: 1px solid #edf2f7; padding: 35px; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
        
        <div style="display: flex; gap: 40px; background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #e2e8f0;">
            <div>
                <small style="color: #94a3b8; font-weight: 700; text-transform: uppercase; font-size: 11px;">Patient Name</small>
                <div style="font-weight: 700; color: var(--g900);"><?= htmlspecialchars($existing_data['patient_name']) ?></div>
            </div>
            <div>
                <small style="color: #94a3b8; font-weight: 700; text-transform: uppercase; font-size: 11px;">Request Date</small>
                <div style="font-weight: 600;"><?= date("d M Y", strtotime($existing_data['requested_at'])) ?></div>
            </div>
            <div>
                <small style="color: #94a3b8; font-weight: 700; text-transform: uppercase; font-size: 11px;">Current Status</small>
                <div><span class="status-pill status-<?= strtolower($existing_data['status']) ?>"><?= $existing_data['status'] ?></span></div>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="request_id" value="<?= $existing_data['lab_request_id'] ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">Update Status</label>
                    <select name="status" style="width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; font-family: inherit;">
                        <option value="Completed" <?= $existing_data['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Processing" <?= $existing_data['status'] == 'Processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="Pending" <?= $existing_data['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">Diagnostic Results / Findings</label>
                <textarea name="result_text" style="width: 100%; height: 160px; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; font-family: inherit; resize: none;" required><?= htmlspecialchars($existing_data['result_text'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 30px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">Report Attachment</label>
                <input type="file" name="file" style="margin-bottom: 10px;">
                
                <?php if (!empty($existing_data['result_file'])): ?>
                    <div style="background: #f1f5f9; padding: 12px 18px; border-radius: 10px; display: flex; align-items: center; gap: 12px;">
                        <i class="fa-solid fa-file-lines" style="color: var(--g600);"></i>
                        <span style="font-size: 13px;">Current: <strong><?= $existing_data['result_file'] ?></strong></span>
                        <a href="uploads/<?= $existing_data['result_file'] ?>" target="_blank" style="margin-left: auto; font-weight: 700; color: var(--g600); text-decoration: none; font-size: 13px;">View File</a>
                    </div>
                <?php endif; ?>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                <a href="lab_dashboard.php?page=lab_records" style="padding: 12px 25px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 14px;">Cancel</a>
                <button type="submit" name="update_record" style="background: var(--g900); color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.3s;">
                    Save Updated Results
                </button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <div style="text-align: center; padding: 80px 20px; background: #fff; border-radius: 20px; border: 1px solid #edf2f7;">
            <i class="fa-solid fa-magnifying-glass-chart" style="font-size: 50px; color: #cbd5e1; margin-bottom: 20px;"></i>
            <h3 style="color: var(--g900);">Record Not Found</h3>
            <p style="color: #94a3b8;">The ID <strong>#<?= htmlspecialchars($request_id) ?></strong> does not exist or has been removed from the system.</p>
            <a href="lab_dashboard.php?page=lab_records" style="display: inline-block; margin-top: 20px; color: var(--g600); font-weight: 700; text-decoration: none;">Return to Records</a>
        </div>
    <?php endif; ?>
</div>