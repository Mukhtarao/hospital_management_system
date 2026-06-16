<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    exit("Unauthorized");
}

$id = intval($_GET['test_id']);

$q = $conn->query("
SELECT 
    t.test_type,
    t.status,
    t.result_text,
    t.result_file,
    t.created_at,
    p.full_name AS patient_name
FROM lab_request_tests t
JOIN lab_requests r ON t.lab_request_id = r.lab_request_id
JOIN patients p ON r.patient_id = p.patient_id
WHERE t.test_id = $id
");

if (!$q) {
    die("SQL ERROR: " . $conn->error);
}

$data = $q->fetch_assoc();
?>

<style>
.result-card {
    font-family: 'Segoe UI';
}

.result-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.result-table th {
    text-align: left;
    background: #f3f4f6;
    padding: 10px;
    width: 35%;
    font-weight: 600;
}

.result-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.result-box {
    background: #f9fafb;
    padding: 12px;
    border-radius: 10px;
    margin-top: 10px;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 999px;
    color: #fff;
    font-size: 12px;
}

.completed { background: #22c55e; }
.pending { background: #f59e0b; }

.file-link {
    display: inline-block;
    margin-top: 10px;
    color: #2563eb;
    font-weight: 500;
    text-decoration: none;
}
</style>

<div class="result-card">

<h3 style="margin-bottom:10px;">Laboratory Result</h3>

<table class="result-table">

<tr>
<th>Patient Name</th>
<td><?= htmlspecialchars($data['patient_name']) ?></td>
</tr>

<tr>
<th>Test Type</th>
<td><?= htmlspecialchars($data['test_type']) ?></td>
</tr>

<tr>
<th>Date</th>
<td><?= date("Y-m-d", strtotime($data['created_at'])) ?></td>
</tr>

<tr>
<th>Status</th>
<td>
<span class="status-badge <?= $data['status']=='Completed'?'completed':'pending' ?>">
<?= htmlspecialchars($data['status']) ?>
</span>
</td>
</tr>

</table>

<!-- RESULT TEXT -->
<h4 style="margin-top:15px;">Test Result</h4>

<?php if (!empty($data['result_text'])): ?>
<div class="result-box">
<?= nl2br(htmlspecialchars($data['result_text'])) ?>
</div>
<?php else: ?>
<p style="color:#6b7280;">No result uploaded yet.</p>
<?php endif; ?>

<!-- FILE -->
<?php if (!empty($data['result_file'])): ?>
<a class="file-link" href="uploads/<?= htmlspecialchars($data['result_file']) ?>" target="_blank">
📎 View Attachment
</a>
<?php endif; ?>

</div>