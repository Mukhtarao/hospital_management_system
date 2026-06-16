<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("db.php");

/* ================= SEARCH LOGIC ================= */
$keyword = $_GET['search'] ?? '';
$where = "";
if (!empty($keyword)) {
    $keyword = mysqli_real_escape_string($conn, $keyword);
    $where = "WHERE patient_name LIKE '%$keyword%' OR patient_id LIKE '%$keyword%' OR lab_request_id LIKE '%$keyword%'";
}

/* ================= FETCH RECORDS ================= */
$query = "SELECT * FROM lab_requests $where ORDER BY requested_at DESC LIMIT 50";
$results = $conn->query($query);
?>

<style>
    .records-container {
        animation: fadeIn 0.4s ease;
    }

    .page-header {
        margin-bottom: 30px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 20px;
    }

    .page-header h2 {
        font-family: 'Playfair Display', serif;
        color: var(--g900);
        font-size: 24px;
    }

    .page-header p {
        color: #64748b;
        font-size: 14px;
    }

    /* SEARCH SECTION */
    .search-section {
        background: #fff;
        padding: 20px;
        border-radius: 16px;
        border: 1px solid #edf2f7;
        margin-bottom: 25px;
    }

    .search-flex {
        display: flex;
        gap: 10px;
    }

    .search-flex input {
        flex: 1;
        padding: 12px 18px;
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        font-family: inherit;
        outline: none;
        transition: 0.2s;
    }

    .search-flex input:focus {
        border-color: var(--g600);
    }

    .btn-search {
        background: var(--g900);
        color: white;
        border: none;
        padding: 0 25px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        transition: 0.2s;
    }

    .btn-search:hover {
        background: var(--g600);
    }

    /* RECORD CARDS */
    .record-card {
        background: #fff;
        border: 1px solid #f1f5f9;
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.2s ease;
    }

    .record-card:hover {
        border-color: var(--g400);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    .patient-meta h4 {
        color: var(--g900);
        font-size: 16px;
        margin-bottom: 4px;
    }

    .patient-meta span {
        font-size: 12px;
        color: #64748b;
    }

    .status-pill {
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .status-completed { background: #dcfce7; color: #166534; }
    .status-pending { background: #fef9c3; color: #854d0e; }
    .status-processing { background: #eff6ff; color: #1e40af; }

    .action-group {
        display: flex;
        gap: 8px;
    }

    .btn-edit-small {
        text-decoration: none;
        background: #f8fafc;
        color: var(--g900);
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid #e2e8f0;
        transition: 0.2s;
    }

    .btn-edit-small:hover {
        background: var(--g600);
        color: white;
        border-color: var(--g600);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 600px) {
        .record-card { flex-direction: column; align-items: flex-start; gap: 15px; }
        .search-flex { flex-direction: column; }
    }
</style>

<div class="records-container">
    <div class="page-header">
        <h2>Update Case Records</h2>
        <p>Search and manage existing laboratory test entries.</p>
    </div>

    <div class="search-section">
        <form method="GET" action="lab_dashboard.php" class="search-flex">
            <input type="hidden" name="page" value="lab_records">
            
            <input type="text" name="search" value="<?= htmlspecialchars($keyword) ?>" placeholder="Search by Patient Name, Request ID, or National ID...">
            <button type="submit" class="btn-search">Search</button>
        </form>
    </div>

    <div class="results-list">
        <?php if ($results && $results->num_rows > 0): ?>
            <?php while($row = $results->fetch_assoc()): ?>
                <?php 
                    $statusClass = 'status-pending';
                    if(strtolower($row['status']) == 'completed') $statusClass = 'status-completed';
                    if(strtolower($row['status']) == 'processing') $statusClass = 'status-processing';
                ?>
                <div class="record-card">
                    <div class="patient-meta">
                        <h4>#<?= $row['lab_request_id'] ?> — <?= htmlspecialchars($row['patient_name']) ?></h4>
                        <span>
                            <i class="fa-regular fa-calendar-check"></i> <?= date("d M Y", strtotime($row['requested_at'])) ?> 
                            &nbsp;•&nbsp; 
                            <i class="fa-solid fa-gauge-high"></i> <?= ucfirst($row['urgency_level']) ?> Priority
                        </span>
                    </div>

                    <div class="action-group">
                        <span class="status-pill <?= $statusClass ?>"><?= $row['status'] ?></span>
                        <a href="lab_edit.php?id=<?= $row['lab_request_id'] ?>" class="btn-edit-small">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding:50px; background:#fff; border-radius:16px; border:1px solid #edf2f7;">
                <i class="fa-solid fa-magnifying-glass" style="font-size:40px; color:#cbd5e1; margin-bottom:15px; display:block;"></i>
                <h3 style="color:#64748b;">No matching records found</h3>
                <p style="color:#94a3b8; font-size:14px;">Please try a different search term.</p>
            </div>
        <?php endif; ?>
    </div>
</div>