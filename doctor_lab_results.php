<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once("db.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    exit();
}

$doctor_id = $_SESSION['user_id'];

/* ================= SEARCH ================= */
$search = $_GET['search'] ?? '';
$search_param = "%$search%";

/* ================= FETCH DATA ================= */
$sql = "
SELECT 
    t.test_id,
    p.patient_id,
    p.full_name AS patient_name,
    t.test_type,
    t.status,
    t.created_at
FROM lab_request_tests t
INNER JOIN lab_requests r ON t.lab_request_id = r.lab_request_id
INNER JOIN patients p ON r.patient_id = p.patient_id
WHERE r.doctor_id = ?
AND (
    p.patient_id LIKE ?
    OR p.full_name LIKE ?
    OR t.test_type LIKE ?
    OR t.status LIKE ?
)
ORDER BY t.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("issss", $doctor_id, $search_param, $search_param, $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();
?>

<style>
    /* PAGE TITLE & HEADER */
    .page-title h2 { font-family: 'Playfair Display', serif; color: var(--g900); font-size: 28px; font-weight: 700; margin: 0; }
    .page-title p { color: #64748b; font-size: 14px; margin-top: 6px; }

    /* SEARCH BOX */
    .search-container {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 25px;
    }
    .search-bar {
        background: #fff;
        padding: 10px 20px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
        transition: all 0.2s ease;
    }
    .search-bar:focus-within {
        border-color: var(--g600);
        box-shadow: 0 4px 12px -1px rgba(0,0,0,0.08);
    }
    .search-bar input { border: none; outline: none; font-size: 14px; width: 260px; color: #334155; }
    .search-bar input::placeholder { color: #94a3b8; }
    .search-bar i { color: #94a3b8; font-size: 15px; }

    /* TABLE CARD */
    .results-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.02), 0 8px 10px -6px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
    .custom-table thead { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .custom-table th { padding: 16px 24px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.75px; color: #64748b; font-weight: 700; }
    .custom-table td { padding: 16px 24px; font-size: 14px; color: #334155; border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease; }
    .custom-table tr:last-child td { border-bottom: none; }
    .custom-table tr:hover td { background: #f0fdf4; }

    /* STATUS PILLS */
    .status-pill { padding: 6px 14px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
    .status-completed { background: #dcfce7; color: #15803d; }
    .status-pending { background: #fef3c7; color: #b45309; }

    /* VIEW BUTTON */
    .btn-view {
        background: var(--g900);
        color: #15803d;
        border: none;
        padding: 9px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-view:hover { background: var(--g600); transform: translateY(-1px); }
    .btn-view:active { transform: translateY(0); }

    /* MODAL STYLES */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 32, 23, 0.45); /* Rich forest-tinted shadow */
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.25s ease;
    }
    .modal-overlay.active {
        display: flex;
        opacity: 1;
    }
    .modal-content {
        background: #fff;
        width: 90%;
        max-width: 600px;
        border-radius: 24px;
        padding: 36px;
        position: relative;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        transform: scale(0.95);
        transition: transform 0.25s ease;
    }
    .modal-overlay.active .modal-content {
        transform: scale(1);
    }
    .close-modal {
        position: absolute; 
        top: 24px; 
        right: 24px;
        width: 36px; 
        height: 36px; 
        border-radius: 50%;
        border: 1px solid #e2e8f0; 
        background: #fff; 
        cursor: pointer;
        display: flex; 
        align-items: center; 
        justify-content: center;
        color: #64748b;
        transition: all 0.15s ease;
        z-index: 10;
    }
    .close-modal:hover {
        background: #f1f5f9;
        color: #1e293b;
        transform: rotate(90deg);
    }
</style>

<div class="page-header">
    <div class="page-title">
        <h2>Laboratory Results</h2>
        <p>Review and analyze patient test outcomes from the lab.</p>
    </div>
</div>

<div class="search-container">
    <form method="GET" class="search-bar">
        <input type="hidden" name="page" value="lab_results">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search ID, Name or Test...">
    </form>
</div>

<div class="results-card">
    <table class="custom-table">
        <thead>
            <tr>
                <th>Patient ID</th>
                <th>Patient Name</th>
                <th>Test Type</th>
                <th>Request Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight: 700; color: var(--g600);">#<?= htmlspecialchars($row['patient_id']) ?></td>
                    <td style="font-weight: 600; color: var(--g900);"><?= htmlspecialchars($row['patient_name']) ?></td>
                    <td><?= htmlspecialchars($row['test_type']) ?></td>
                    <td style="color: #64748b;"><?= date("M d, Y", strtotime($row['created_at'])) ?></td>
                    <td>
                        <span class="status-pill <?= strtolower($row['status']) == 'completed' ? 'status-completed' : 'status-pending' ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn-view" onclick="openLabResult(<?= (int)$row['test_id'] ?>)">
                            <i class="fa-solid fa-file-medical"></i> View Result
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" align="center" style="padding: 60px; color: #64748b; font-size: 15px;">
                        <i class="fa-solid fa-folder-open" style="font-size: 24px; color: #cbd5e1; margin-bottom: 10px; display: block;"></i>
                        No laboratory results found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="labModal" class="modal-overlay" onclick="handleOverlayClick(event)">
    <div class="modal-content">
        <button type="button" onclick="closeModal()" class="close-modal" aria-label="Close modal">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div id="labContent">
            <div style="text-align:center; padding:30px 20px;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 28px; color: var(--g600);"></i>
                <p style="margin-top:14px; color: #64748b; font-size: 14px;">Retrieving lab data...</p>
            </div>
        </div>
    </div>
</div>

<script>
function openLabResult(id) {
    const modal = document.getElementById("labModal");
    
    // Set view layout triggers
    modal.style.display = "flex";
    setTimeout(() => {
        modal.classList.add("active");
    }, 10);

    // Dynamic clean loader template fallback setup
    document.getElementById("labContent").innerHTML = `
        <div style="text-align:center; padding:30px 20px;">
            <i class="fa-solid fa-spinner fa-spin" style="font-size: 28px; color: var(--g600);"></i>
            <p style="margin-top:14px; color: #64748b; font-size: 14px;">Retrieving lab data...</p>
        </div>`;

    fetch("get_lab_result.php?test_id=" + id)
    .then(res => res.text())
    .then(data => {
        document.getElementById("labContent").innerHTML = data;
    })
    .catch(() => {
        document.getElementById("labContent").innerHTML = `
            <div style="text-align:center; padding:20px; color:#ef4444;">
                <i class="fa-solid fa-circle-exclamation" style="font-size: 24px;"></i>
                <p style="margin-top:10px; font-weight:500;">Error loading result details.</p>
            </div>`;
    });
}

function closeModal() {
    const modal = document.getElementById("labModal");
    modal.classList.remove("active");
    setTimeout(() => {
        modal.style.display = "none";
    }, 250); // Matches smooth transition delay
}

// Allows dismissing the modal smoothly if a user clicks outside the content box
function handleOverlayClick(event) {
    if (event.target === document.getElementById("labModal")) {
        closeModal();
    }
}
</script>