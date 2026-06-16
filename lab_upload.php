<?php
// Maintain user session context across form postbacks and modal layers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("db.php");

// Enable explicit tracing to catch database constraint errors or structural failures immediately
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ================= SECURITY VERIFICATION ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'lab') {
    exit("<p style='color:red; padding:20px; font-family:sans-serif; font-weight:600;'>Unauthorized access. Please log in.</p>");
}

// Extract the active logged-in laboratory technician ID from the active session context
$lab_technician_id = $_SESSION['user_id'] ?? 1;

/* ================= GET COMPREHENSIVE RELATION DATA ================= */
$test_id = $_GET['id'] ?? $_POST['test_id'] ?? null;
$data = null;

if ($test_id) {
    // FIX: Use prepared statement — raw interpolation of $test_id was SQL injection risk
    $stmt = $conn->prepare("
        SELECT 
            t.test_id,
            r.lab_request_id,
            p.patient_id,
            p.full_name AS patient_name,
            t.test_type,
            r.urgency_level,
            r.doctor_id
        FROM lab_request_tests t
        INNER JOIN lab_requests r ON t.lab_request_id = r.lab_request_id
        INNER JOIN patients p ON r.patient_id = p.patient_id
        WHERE t.test_id = ?
    ");
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
    }
    $stmt->close();
}

/* ================= MULTI-TABLE SAVE ENGINE ================= */
if (isset($_POST['submit'])) {

    // FIX: Cast IDs to int; sanitize strings via prepared statements below (no manual escaping needed)
    $form_test_id = (int) $_POST['test_id'];
    $form_req_id  = (int) $_POST['lab_request_id'];
    $result_text  = $_POST['result'] ?? '';
    $notes        = $_POST['notes'] ?? '';
    $patient_id   = (int) $_POST['patient_id'];
    $doctor_id    = !empty($_POST['doctor_id']) ? (int) $_POST['doctor_id'] : null;
    $test_type    = $_POST['test_type'] ?? '';

    // FIX: Validate and sanitize datetime — never trust raw POST for datetime construction
    $raw_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
    $raw_time = preg_match('/^\d{2}:\d{2}$/', $_POST['time'] ?? '')         ? $_POST['time'] : date('H:i');
    $completed_at = $raw_date . ' ' . $raw_time . ':00';

    /* PHYSICAL FILE UPLOAD MANAGEMENT */
    // FIX: Default to NULL (not empty string) so DB stores a true NULL when no file exists
    $file_name = null;

    if (!empty($_FILES['file']['name']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Sanitize filename: strip all chars except alphanumeric, dot, underscore, hyphen
        $safe_basename = preg_replace("/[^A-Za-z0-9._\-]/", "", basename($_FILES['file']['name']));
        $file_name = time() . "_" . $safe_basename;

        if (!is_dir('uploads')) {
            mkdir('uploads', 0755, true); // FIX: 0755 is safer than 0777 for web-accessible dirs
        }
        move_uploaded_file($_FILES['file']['tmp_name'], "uploads/" . $file_name);
    } else {
        // Retain existing file if a replacement report wasn't provided
        $chk = $conn->prepare("SELECT result_file FROM lab_request_tests WHERE test_id = ?");
        $chk->bind_param("i", $form_test_id);
        $chk->execute();
        $chk_result = $chk->get_result();
        if ($chk_result && $chk_result->num_rows > 0) {
            $row = $chk_result->fetch_assoc();
            $file_name = $row['result_file'] ?: null;
        }
        $chk->close();
    }

    try {
        // Start ACID Transaction to guarantee atomic cross-table operations
        $conn->begin_transaction();

        /* ---------------- TABLE 1: UPDATE lab_request_tests ----------------
           Saves the findings, notes, files, and marks individual row Completed.
           FIX: Full prepared statement — was previously raw string interpolation.
        ---------------------------------------------------------------------- */
        $upd = $conn->prepare("
            UPDATE lab_request_tests SET
                result_text  = ?,
                result_notes = ?,
                result_file  = ?,
                status       = 'Completed',
                completed_at = ?
            WHERE test_id = ?
        ");
        $upd->bind_param("ssssi", $result_text, $notes, $file_name, $completed_at, $form_test_id);
        $upd->execute();
        $upd->close();

        /* ---------------- TABLE 2: INSERT INTO laboratory_tests ------------
           Archives the immutable electronic medical record for the doctor's review.
           FIX: Full prepared statement — all values were previously interpolated raw.
        ---------------------------------------------------------------------- */
        $ins = $conn->prepare("
            INSERT INTO laboratory_tests (
                patient_id,
                doctor_id,
                lab_technician_id,
                test_type,
                test_result,
                status,
                test_date
            ) VALUES (?, ?, ?, ?, ?, 'Completed', ?)
        ");
        $ins->bind_param("iiisss", $patient_id, $doctor_id, $lab_technician_id, $test_type, $result_text, $completed_at);
        $ins->execute();
        $ins->close();

        /* ---------------- TABLE 3: DYNAMICALLY UPDATE lab_requests ---------
           FIX: The pending-check query now EXCLUDES the current test_id because
           TABLE 1's UPDATE has already run (status = 'Completed' for this test),
           but within the same transaction the SELECT still sees the pre-commit state
           on some MySQL isolation levels. Excluding current test_id is the safe,
           engine-agnostic approach regardless of isolation level.
        ---------------------------------------------------------------------- */
        $checkPending = $conn->prepare("
            SELECT test_id
            FROM lab_request_tests
            WHERE lab_request_id = ?
              AND test_id != ?
              AND status != 'Completed'
            LIMIT 1
        ");
        $checkPending->bind_param("ii", $form_req_id, $form_test_id);
        $checkPending->execute();
        $pendingResult = $checkPending->get_result();
        $hasPending    = ($pendingResult && $pendingResult->num_rows > 0);
        $checkPending->close();

        // FIX: completed_at and test_type were never written to lab_requests (always NULL in DB)
        if ($hasPending) {
            // Sibling tests still outstanding — mark Processing, leave completed_at NULL
            $updReq = $conn->prepare("
                UPDATE lab_requests
                SET status = 'Processing'
                WHERE lab_request_id = ?
            ");
            $updReq->bind_param("i", $form_req_id);
        } else {
            // All tests done — stamp completed_at and test_type on the master request row
            $updReq = $conn->prepare("
                UPDATE lab_requests
                SET status       = 'Completed',
                    completed_at = ?,
                    test_type    = ?
                WHERE lab_request_id = ?
            ");
            $updReq->bind_param("ssi", $completed_at, $test_type, $form_req_id);
        }
        $updReq->execute();
        $updReq->close();

        // Everything succeeded — commit changes to all 3 tables permanently
        $conn->commit();

        // FIX: Do NOT redirect. Close the modal and refresh only the data table in the parent,
        // keeping the user on the lab_requests page with full state preserved.
        echo "<script>
            // Notify the parent frame that the save succeeded
            if (window.parent && window.parent !== window) {
                // Call the parent's modal-close + table-refresh function if it exists
                if (typeof window.parent.closeLabModal === 'function') {
                    window.parent.closeLabModal(true);
                } else if (typeof window.parent.refreshLabTable === 'function') {
                    window.parent.refreshLabTable();
                    window.parent.closeLabModal();
                } else {
                    // Fallback: post a message the parent can listen for
                    window.parent.postMessage({ type: 'LAB_RESULT_SAVED', success: true }, '*');
                }
            } else {
                // Standalone (not in iframe/modal) — stay on page, just show confirmation
                document.querySelector('.upload-container').innerHTML =
                    '<div style=\"text-align:center;padding:40px 10px;\">' +
                    '<div style=\"font-size:48px;margin-bottom:16px;\">✅</div>' +
                    '<p style=\"color:#052e16;font-size:18px;font-weight:700;margin-bottom:8px;\">All 3 Tables Synced</p>' +
                    '<p style=\"color:#64748b;font-size:14px;\">Result saved successfully.</p>' +
                    '</div>';
            }
        </script>";
        exit();

    } catch (mysqli_sql_exception $e) {
        // Rollback all database operations if any step fails to preserve integrity
        $conn->rollback();
        die("<div style='padding:20px; background:#fee2e2; border:1px solid #ef4444; color:#b91c1c; font-family:monospace; border-radius:8px; margin:20px;'>
                <strong>Critical Database Sync Failure:</strong><br>" . htmlspecialchars($e->getMessage()) . "
             </div>");
    }
}
?>

<style>
    .upload-container { animation: fadeIn 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-family: system-ui, sans-serif; padding: 10px; }
    .modal-form-title { font-family: 'Playfair Display', serif; color: #052e16; font-size: 24px; font-weight: 700; margin: 0 0 4px 0; }
    .modal-form-subtitle { color: #64748b; font-size: 14px; margin: 0 0 25px 0; }
    .form-section-title { font-size: 11px; font-weight: 700; color: #16a34a; text-transform: uppercase; letter-spacing: 0.75px; margin: 20px 0 14px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; }
    .grid-layout { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px; }
    .grid-full { grid-column: span 2; }
    .input-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
    .input-group input, .input-group textarea { width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; box-sizing: border-box; color: #1e293b; }
    .input-group input:focus, .input-group textarea:focus { border-color: #16a34a; outline: none; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1); }
    .input-readonly { background: #f8fafc; color: #64748b; cursor: not-allowed; border-style: dashed !important; }
    .file-drop { border: 2px dashed #cbd5e1; padding: 20px; border-radius: 12px; text-align: center; background: #f8fafc; cursor: pointer; }
    .file-drop:hover { border-color: #16a34a; background: #f0fdf4; }
    .action-bar { display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #f1f5f9; padding-top: 20px; margin-top: 25px; }
    .btn { padding: 10px 22px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-secondary { background: #f1f5f9; color: #475569; }
    .btn-primary { background: #052e16; color: #fff; }
    .btn-primary:hover { background: #16a34a; transform: translateY(-1px); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="upload-container">
    <?php if ($data): ?>
        <h3 class="modal-form-title">Process &amp; Archive Test Result</h3>
        <p class="modal-form-subtitle">Finalizing metrics for order panel reference <strong>#<?= htmlspecialchars($data['lab_request_id']) ?></strong></p>

        <form action="lab_upload.php?id=<?= (int)$data['test_id'] ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="test_id"        value="<?= (int)$data['test_id'] ?>">
            <input type="hidden" name="lab_request_id" value="<?= (int)$data['lab_request_id'] ?>">
            <input type="hidden" name="patient_id"     value="<?= (int)$data['patient_id'] ?>">
            <input type="hidden" name="doctor_id"      value="<?= (int)$data['doctor_id'] ?>">
            <input type="hidden" name="test_type"      value="<?= htmlspecialchars($data['test_type']) ?>">

            <div class="form-section-title">Case Metadata</div>
            <div class="grid-layout">
                <div class="input-group">
                    <label>Patient Name</label>
                    <input type="text" value="<?= htmlspecialchars($data['patient_name']) ?>" class="input-readonly" readonly>
                </div>
                <div class="input-group">
                    <label>Assigned Test Type</label>
                    <input type="text" value="<?= htmlspecialchars($data['test_type']) ?>" style="font-weight: 600; color: #052e16;" class="input-readonly" readonly>
                </div>
            </div>

            <div class="form-section-title">Diagnostic Findings Entry</div>
            <div class="grid-layout">
                <div class="input-group grid-full">
                    <label>Lab Findings Report (Saves to result_text &amp; test_result)</label>
                    <textarea name="result" placeholder="Specify numerical metrics, parameters, or diagnostic summary findings..." required style="height: 100px; resize: none;"></textarea>
                </div>
                <div class="input-group grid-full">
                    <label>Technician Notes (Saves to result_notes)</label>
                    <textarea name="notes" placeholder="Specify observations or technician comments..." style="height: 65px; resize: none;"></textarea>
                </div>
                <div class="input-group">
                    <label>Completion Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="input-group">
                    <label>Completion Time</label>
                    <input type="time" name="time" value="<?= date('H:i') ?>" required>
                </div>
                <div class="input-group grid-full">
                    <label>Attach Official Document Report (PDF/JPG/PNG)</label>
                    <div class="file-drop" onclick="document.getElementById('modalFileInput').click()">
                        <span id="modalFileNameDisplay" style="font-size: 13px; color: #64748b;">Click here to browse and select files</span>
                        <input type="file" name="file" id="modalFileInput" style="display:none" onchange="updateModalFileName(this)">
                    </div>
                </div>
            </div>

            <div class="action-bar">
                <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="submit" class="btn btn-primary">Process and Sync 3 Tables</button>
            </div>
        </form>
    <?php else: ?>
        <div style="text-align: center; padding: 40px 10px;">
            <p style="color: #64748b; font-size:14px;">Please select a valid laboratory task request.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    function updateModalFileName(input) {
        const display = document.getElementById('modalFileNameDisplay');
        if (input.files && input.files.length > 0) {
            display.innerHTML = "<strong style='color:#16a34a;'>Selected file:</strong> " + input.files[0].name;
        }
    }
</script>