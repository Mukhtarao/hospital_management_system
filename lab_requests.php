<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("db.php");

/* ================= SECURITY CHECK ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'lab') {
    exit("<div class='content-card'><h3>Unauthorized Access</h3><p>You do not have permission to view this module.</p></div>");
}

/* ================= SEARCH & FETCH LOGIC ================= */
// Capture search term safely from GET request
$search = isset($_GET['search']) ? trim(mysqli_real_escape_string($conn, $_GET['search'])) : '';

// SQL updated to JOIN tables so it can properly fetch test_type and individual statuses
$query = "SELECT 
            t.test_id,
            r.lab_request_id,
            p.patient_id,
            p.full_name AS patient_name,
            t.test_type,
            r.urgency_level,
            t.status,
            r.requested_at
          FROM lab_request_tests t
          INNER JOIN lab_requests r ON t.lab_request_id = r.lab_request_id
          INNER JOIN patients p ON r.patient_id = p.patient_id";

// Apply multi-column string filtering across joined tables if search is performed
if (!empty($search)) {
    $query .= " WHERE p.full_name LIKE '%$search%' 
                OR r.lab_request_id LIKE '%$search%' 
                OR p.patient_id LIKE '%$search%'
                OR t.test_type LIKE '%$search%'";
}

/* ================= SYSTEM PRIORITY SORTING ================= 
 * 1. 'Pending' test status fields float to the absolute top of the view roster.
 * 2. 'Processing' profiles receive tier-2 priority.
 * 3. 'Completed' logs cascade to the bottom block.
 * 4. Items matching the same status filter chronologically (newest requests first).
 */
$query .= " ORDER BY 
            CASE 
                WHEN LOWER(t.status) = 'pending' THEN 1
                WHEN LOWER(t.status) = 'processing' THEN 2
                WHEN LOWER(t.status) = 'completed' THEN 3
                ELSE 4 
            END ASC, 
            r.requested_at DESC LIMIT 50";

$tests = $conn->query($query);

if (!$tests) {
    die("<div style='padding:20px; background:#fef2f2; border:1px solid #fee2e2; color:#b91c1c; border-radius:12px;'><b>Database Error:</b> " . htmlspecialchars($conn->error) . "</div>");
}
?>

<style>
    .requests-container {
        animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .module-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 25px;
        gap: 20px;
        flex-wrap: wrap;
    }

    .header-text h2 {
        font-family: 'Playfair Display', serif;
        color: var(--emerald-900, #052e16);
        font-size: 26px;
        margin-bottom: 5px;
    }

    /* SEARCH BAR STYLING */
    .search-form {
        display: flex;
        background: #fff;
        padding: 5px;
        border-radius: 12px;
        border: 1.5px solid #e2e8f0;
        width: 100%;
        max-width: 350px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .search-form:focus-within {
        border-color: var(--emerald-600, #16a34a);
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.05);
    }

    .search-input {
        border: none;
        padding: 8px 12px;
        width: 100%;
        outline: none;
        font-size: 14px;
        color: #1e293b;
        background: transparent;
    }

    .btn-search {
        background: var(--emerald-900, #052e16);
        color: #fff;
        border: none;
        padding: 8px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .btn-search:hover {
        background: var(--emerald-600, #16a34a);
    }

    /* TABLE DESIGN */
    .table-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #edf2f7;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        overflow-x: auto;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }

    .custom-table th {
        background: #f8fafc;
        padding: 18px 20px;
        text-align: left;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: 700;
        border-bottom: 1px solid #edf2f7;
    }

    .custom-table td {
        padding: 18px 20px;
        font-size: 14px;
        color: #1e293b;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .custom-table tr:hover {
        background-color: #fcfcfd;
    }

    /* PILL BADGES */
    .badge-pill {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        display: inline-block;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .urgency-routine { background: #eff6ff; color: #1e40af; }
    .urgency-urgent { background: #fff7ed; color: #9a3412; }
    .urgency-emergency { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; }

    .status-pending { background: #fefce8; color: #854d0e; }
    .status-completed { background: #f0fdf4; color: #166534; }
    .status-processing { background: #f5f3ff; color: #5b21b6; }

    /* ACTION BUTTONS */
    .btn-process {
        background: var(--emerald-900, #052e16);
        color: #ffffff !important;
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
    }

    .btn-process:hover {
        background: var(--emerald-600, #16a34a);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(22, 163, 74, 0.15);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="requests-container">
    <div class="module-header">
        <div class="header-text">
            <h2>Laboratory Test Requests</h2>
            <p style="color: #64748b; font-size: 14px;">Manage incoming diagnostic requests from physicians.</p>
        </div>

        <form action="lab_dashboard.php" method="GET" class="search-form">
            <input type="hidden" name="page" value="lab_requests">
            
            <input type="text" name="search" class="search-input" 
                   placeholder="Search ID, Name or Test Type..." 
                   value="<?= htmlspecialchars($search) ?>">
                   
            <button type="submit" class="btn-search" aria-label="Search">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </form>
    </div>

    <div class="table-card">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Patient Name</th>
                    <th>Test Type</th>
                    <th>Date Requested</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tests && $tests->num_rows > 0): ?>
                    <?php while($row = $tests->fetch_assoc()): 
                        $urgencyClass = !empty($row['urgency_level']) ? strtolower($row['urgency_level']) : 'routine';
                        $statusClass  = !empty($row['status']) ? strtolower($row['status']) : 'pending';
                    ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--emerald-900, #052e16);">
                                #<?= htmlspecialchars($row['lab_request_id']) ?>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($row['patient_name']) ?></div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">ID: <?= htmlspecialchars($row['patient_id'] ?? 'N/A') ?></div>
                            </td>
                            <td style="font-weight: 500; color: #334155;">
                                <?= htmlspecialchars($row['test_type'] ?? 'General Examination') ?>
                            </td>
                            <td>
                                <?php 
                                    if (!empty($row['requested_at'])) {
                                        echo date('M d, Y', strtotime($row['requested_at']));
                                    } else {
                                        echo 'N/A';
                                    }
                                ?>
                            </td>
                            <td>
                                <span class="badge-pill urgency-<?= $urgencyClass ?>">
                                    <?= htmlspecialchars(ucfirst($row['urgency_level'] ?? 'Routine')) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-pill status-<?= $statusClass ?>">
                                    <?= htmlspecialchars(ucfirst($row['status'] ?? 'Pending')) ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="lab_dashboard.php?page=lab_upload&id=<?= urlencode($row['test_id']) ?>" class="btn-process">
                                    <i class="fa-solid fa-flask-vial"></i> Process
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 70px 20px; color: #94a3b8;">
                            <i class="fa-solid fa-folder-open" style="font-size: 44px; display: block; margin-bottom: 12px; opacity: 0.3; color: var(--emerald-600);"></i>
                            <span style="font-size: 14px; font-weight: 500;">No matching requests found in the record directory.</span>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>