<?php
/* ================= SECURITY ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['test_id'])) {
    die("Invalid request.");
}

$doctor_id = $_SESSION['user_id'];
$test_id   = (int) $_GET['test_id'];

/* ================= FETCH TEST DETAILS ================= */
$query = "
    SELECT 
        p.patient_id,
        p.full_name AS patient_name,
        t.test_type,
        t.status,
        t.created_at,
        t.result,
        r.urgency_level,
        r.clinical_notes
    FROM lab_request_tests t
    INNER JOIN lab_requests r ON t.lab_request_id = r.lab_request_id
    INNER JOIN patients p ON r.patient_id = p.patient_id
    WHERE t.test_id = $test_id
      AND r.doctor_id = $doctor_id
    LIMIT 1
";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    die("Test result not found or access denied.");
}

$data = mysqli_fetch_assoc($result);
?>

<h2>Laboratory Test Result</h2>
<p style="color:#6b7280;">Detailed laboratory test information</p>

<div style="background:#fff;padding:28px;border-radius:16px;margin-top:20px;max-width:900px;">

<!-- ================= BASIC INFO ================= -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

<div>
    <label style="color:#6b7280;">Patient ID</label>
    <div style="font-weight:600;"><?= htmlspecialchars($data['patient_id']) ?></div>
</div>

<div>
    <label style="color:#6b7280;">Patient Name</label>
    <div style="font-weight:600;"><?= htmlspecialchars($data['patient_name']) ?></div>
</div>

<div>
    <label style="color:#6b7280;">Test Type</label>
    <div style="font-weight:600;"><?= htmlspecialchars($data['test_type']) ?></div>
</div>

<div>
    <label style="color:#6b7280;">Test Date</label>
    <div style="font-weight:600;">
        <?= date("d M Y", strtotime($data['created_at'])) ?>
    </div>
</div>

<div>
    <label style="color:#6b7280;">Urgency Level</label>
    <div style="font-weight:600;">
        <?= htmlspecialchars($data['urgency_level'] ?? 'Not specified') ?>
    </div>
</div>

<div>
    <label style="color:#6b7280;">Status</label>
    <div>
        <?php if ($data['status'] === 'Completed'): ?>
            <span style="background:#22c55e;color:#fff;padding:6px 12px;border-radius:999px;">
                Completed
            </span>
        <?php else: ?>
            <span style="background:#f59e0b;color:#fff;padding:6px 12px;border-radius:999px;">
                Pending
            </span>
        <?php endif; ?>
    </div>
</div>

</div>

<!-- ================= CLINICAL NOTES ================= -->
<div style="margin-top:25px;">
    <label style="color:#6b7280;">Clinical Notes</label>
    <div style="background:#f9fafb;padding:14px;border-radius:10px;margin-top:6px;">
        <?= nl2br(htmlspecialchars($data['clinical_notes'] ?? 'No clinical notes provided.')) ?>
    </div>
</div>

<!-- ================= TEST RESULT ================= -->
<div style="margin-top:25px;">
    <label style="color:#6b7280;">Lab Result</label>

    <?php if ($data['status'] === 'Completed' && !empty($data['result'])): ?>
        <div style="background:#ecfeff;border:1px solid #22d3ee;padding:16px;border-radius:10px;margin-top:6px;">
            <?= nl2br(htmlspecialchars($data['result'])) ?>
        </div>
    <?php else: ?>
        <div style="background:#fff7ed;border:1px solid #f59e0b;padding:16px;border-radius:10px;margin-top:6px;color:#92400e;">
            Result not available yet. Please check again later.
        </div>
    <?php endif; ?>
</div>

<!-- ================= ACTIONS ================= -->
<div style="margin-top:30px;display:flex;gap:12px;">
    <a href="doctor_dashboard.php?page=lab_results"
       style="padding:12px 20px;border-radius:10px;border:1px solid #e5e7eb;text-decoration:none;color:#0f172a;">
        ← Back to Lab Results
    </a>
</div>

</div>
