<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include("db.php");

/* ================= SEARCH LOGIC ================= */
$patient = null;
if (isset($_GET['search']) && !empty($_GET['keyword'])) {
    $keyword = mysqli_real_escape_string($conn, $_GET['keyword']);
    $query = "SELECT * FROM patients 
              WHERE patient_id = '$keyword' 
              OR full_name LIKE '%$keyword%' 
              OR phone LIKE '%$keyword%' 
              LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $patient = mysqli_fetch_assoc($result);
    }
}
?>

<style>
    .page-title h2 { font-family: 'Playfair Display', serif; color: var(--g900); font-size: 28px; }
    .theme-card { 
        background: #fff; border-radius: 24px; border: 1px solid #edf2f7; 
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); padding: 30px; margin-bottom: 30px; 
    }
    
    .search-container { display: flex; gap: 12px; margin-top: 20px; }
    .search-input { 
        flex: 1; padding: 14px 20px; border-radius: 14px; border: 1px solid #e2e8f0;
        font-size: 14px; outline: none; transition: 0.3s;
    }
    .search-input:focus { border-color: var(--g600); box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1); }
    
    .btn-search { 
        background: var(--g900); color: #fff; border: none; padding: 0 30px; 
        border-radius: 14px; font-weight: 700; cursor: pointer; transition: 0.3s;
    }
    .btn-search:hover { background: var(--g600); }

    .patient-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 25px; margin-top: 25px; }
    
    .info-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
    .info-value { font-size: 15px; color: var(--g900); font-weight: 600; margin-bottom: 15px; }
    
    .status-badge { 
        display: inline-block; padding: 4px 12px; border-radius: 20px; 
        font-size: 11px; font-weight: 700; background: #dcfce7; color: #166534; 
    }
</style>

<div class="page-header">
    <div class="page-title">
        <h2>Patient Registry</h2>
        <p>Search and verify patient medical records</p>
    </div>
</div>

<div class="theme-card">
    <h4 style="font-family:'Playfair Display'; margin-bottom: 15px;">Locate Patient</h4>
    <form method="GET" class="search-container">
        <input type="hidden" name="page" value="patients">
        <input type="text" name="keyword" class="search-input" 
               placeholder="Enter Patient ID, Full Name, or Phone..." 
               value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>" required>
        <button type="submit" name="search" class="btn-search">
            <i class="fa-solid fa-magnifying-glass"></i> Search
        </button>
    </form>

    <?php if (!$patient && isset($_GET['search'])): ?>
        <p style="margin-top: 20px; color: #ef4444; font-size: 14px; text-align: center;">
            <i class="fa-solid fa-circle-exclamation"></i> No patient found with that criteria.
        </p>
    <?php endif; ?>
</div>

<?php if ($patient): ?>
<div class="patient-grid">
    
    <div class="theme-card" style="text-align: center;">
        <div style="width: 80px; height: 80px; background: #f1f5f9; color: var(--g600); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 15px;">
            <i class="fa-solid fa-user"></i>
        </div>
        <h3 style="color: var(--g900);"><?= htmlspecialchars($patient['full_name']) ?></h3>
        <span class="status-badge">Active Patient</span>
        
        <div style="text-align: left; margin-top: 25px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
            <div class="info-label">Patient ID</div>
            <div class="info-value">#<?= $patient['patient_id'] ?></div>
            
            <div class="info-label">Phone Number</div>
            <div class="info-value"><?= $patient['phone'] ?? 'Not Provided' ?></div>
            
            <div class="info-label">Registration Date</div>
            <div class="info-value"><?= date("d M Y", strtotime($patient['created_at'])) ?></div>
        </div>
    </div>

    <div class="theme-card">
        <h4 style="font-family:'Playfair Display'; margin-bottom: 25px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">Clinical Overview</h4>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <div class="info-label">Biological Gender</div>
                <div class="info-value"><?= $patient['gender'] ?></div>
            </div>
            <div>
                <div class="info-label">Current Age</div>
                <div class="info-value"><?= $patient['age'] ?> Years</div>
            </div>
            <div>
                <div class="info-label">First Name</div>
                <div class="info-value"><?= htmlspecialchars($patient['first_name']) ?></div>
            </div>
            <div>
                <div class="info-label">Last Name</div>
                <div class="info-value"><?= htmlspecialchars($patient['last_name']) ?></div>
            </div>
        </div>

        <div style="margin-top: 30px; background: #f8fafc; padding: 20px; border-radius: 16px;">
            <h5 style="color: var(--g900); margin-bottom: 10px;"><i class="fa-solid fa-clipboard-list"></i> Nursing Action</h5>
            <p style="font-size: 13px; color: #64748b; line-height: 1.6;">
                Use the sidebar to update this patient's <strong>Vital Signs</strong> or add <strong>Nursing Notes</strong> for the attending physician.
            </p>
        </div>
    </div>

</div>
<?php else: ?>
    <div style="text-align: center; padding: 100px 0; color: #94a3b8;">
        <i class="fa-solid fa-address-book" style="font-size: 60px; margin-bottom: 20px; opacity: 0.2;"></i>
        <p>Search for a patient to view their clinical file.</p>
    </div>
<?php endif; ?>