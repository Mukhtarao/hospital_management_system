<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("db.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'receptionist') {
    header("Location: index.php");
    exit();
}

/* ================= DEFAULTS ================= */
$patient = null;
$message = "";
$error   = "";

/* ================= SEARCH LOGIC ================= */
if (!empty($_GET['search'])) {
    $search_raw = trim($_GET['search']);
    $search     = mysqli_real_escape_string($conn, $search_raw);

    // Search logic: ID (numeric short), Phone (numeric long), or Name (string)
    if (ctype_digit($search_raw) && strlen($search_raw) <= 6) {
        $sql = "SELECT * FROM patients WHERE patient_id = '$search'";
    } elseif (ctype_digit($search_raw)) {
        $sql = "SELECT * FROM patients WHERE phone = '$search'";
    } else {
        $sql = "SELECT * FROM patients WHERE full_name LIKE '%$search%' OR first_name LIKE '%$search%' OR last_name LIKE '%$search%'";
    }

    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $patient = mysqli_fetch_assoc($res);
    } else {
        $error = "No patient found matching \"$search_raw\".";
    }
}

/* ================= UPDATE LOGIC ================= */
if (isset($_POST['update_patient'])) {
    $patient_id = (int)$_POST['patient_id'];
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $dob        = $_POST['dob'];
    $gender     = mysqli_real_escape_string($conn, $_POST['gender']);
    $blood      = mysqli_real_escape_string($conn, $_POST['blood_group']);
    $phone      = mysqli_real_escape_string($conn, $_POST['phone']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $address    = mysqli_real_escape_string($conn, $_POST['address']);
    $em_name    = mysqli_real_escape_string($conn, $_POST['emergency_name']);
    $em_phone   = mysqli_real_escape_string($conn, $_POST['emergency_phone']);
    $history    = mysqli_real_escape_string($conn, $_POST['medical_history']);

    if (!$first_name || !$last_name || !$dob || !$phone) {
        $error = "Required fields (Name, DOB, Phone) cannot be empty.";
    } else {
        $full_name = $first_name . ' ' . $last_name;
        $age = date_diff(date_create($dob), date_create('today'))->y;

        $update_query = "UPDATE patients SET 
            first_name='$first_name', last_name='$last_name', full_name='$full_name', 
            date_of_birth='$dob', age='$age', gender='$gender', blood_group='$blood', 
            phone='$phone', email='$email', address='$address', 
            emergency_contact_name='$em_name', emergency_contact_phone='$em_phone', 
            medical_history='$history' 
            WHERE patient_id='$patient_id'";

        if (mysqli_query($conn, $update_query)) {
            $message = "Record for <b>$full_name</b> updated successfully.";
            // Refresh patient data
            $res = mysqli_query($conn, "SELECT * FROM patients WHERE patient_id='$patient_id'");
            $patient = mysqli_fetch_assoc($res);
        } else {
            $error = "Database Error: " . mysqli_error($conn);
        }
    }
}
?>

<style>
    .title-area { margin-bottom: 25px; }
    .title-area h2 { font-family: 'Playfair Display'; color: var(--g900); font-size: 28px; }
    .title-area p { color: #64748b; font-size: 14px; }

    .search-box { 
        background: #fff; padding: 20px; border-radius: 16px; 
        border: 1px solid #e2e8f0; display: flex; gap: 10px; margin-bottom: 25px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }

    .form-card { background: #fff; padding: 30px; border-radius: 20px; border: 1px solid #e2e8f0; }
    
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
    
    .field-group { margin-bottom: 18px; }
    label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase; }

    input, select, textarea {
        width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #cbd5e1;
        font-family: inherit; font-size: 14px; transition: 0.2s;
    }
    input:focus, select:focus, textarea:focus {
        outline: none; border-color: var(--g600); box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }
    textarea { resize: none; height: 80px; }

    .btn-search { background: var(--g900); color: #fff; border: none; padding: 0 25px; border-radius: 10px; font-weight: 600; cursor: pointer; }
    .btn-update { background: var(--g600); color: #fff; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; }
    .btn-update:hover { background: var(--g900); }

    .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
    .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bcf0da; }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
</style>

<div class="title-area">
    <h2>Update Patient Records</h2>
    <p>Locate a patient by ID, Name, or Phone to modify their information.</p>
</div>

<form method="GET" class="search-box">
    <input type="hidden" name="page" value="update_patient">
    <input type="text" name="search" placeholder="Search by ID, Name, or Phone Number..." 
           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" required
           style="flex: 1; border-color: #e2e8f0;">
    <button type="submit" class="btn-search">
        <i class="fa-solid fa-magnifying-glass"></i> &nbsp;Search
    </button>
</form>

<?php if ($message): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $message ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fa-solid fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<?php if ($patient): ?>
<div class="form-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; padding-bottom:15px; border-bottom:1px solid #f1f5f9;">
        <h3 style="color:var(--g900); font-size:18px;">Editing Record: <span style="color:var(--g600);">#<?= $patient['patient_id'] ?></span></h3>
        <span style="font-size:12px; background:#f1f5f9; padding:5px 12px; border-radius:20px; font-weight:600;">Last Updated: <?= $patient['created_at'] ?? 'N/A' ?></span>
    </div>

    <form method="POST">
        <input type="hidden" name="patient_id" value="<?= $patient['patient_id'] ?>">

        <div class="grid-2">
            <div class="field-group">
                <label>First Name</label>
                <input type="text" name="first_name" value="<?= htmlspecialchars($patient['first_name']) ?>" required>
            </div>
            <div class="field-group">
                <label>Last Name</label>
                <input type="text" name="last_name" value="<?= htmlspecialchars($patient['last_name']) ?>" required>
            </div>
        </div>

        <div class="grid-3">
            <div class="field-group">
                <label>Date of Birth</label>
                <input type="date" name="dob" value="<?= $patient['date_of_birth'] ?>" required>
            </div>
            <div class="field-group">
                <label>Gender</label>
                <select name="gender">
                    <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option <?= $patient['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field-group">
                <label>Blood Group</label>
                <select name="blood_group">
                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                        <option <?= $patient['blood_group'] === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="field-group">
                <label>Phone Number</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($patient['phone']) ?>" required>
            </div>
            <div class="field-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($patient['email']) ?>">
            </div>
        </div>

        <div class="field-group">
            <label>Current Address</label>
            <textarea name="address"><?= htmlspecialchars($patient['address']) ?></textarea>
        </div>

        <div class="grid-2" style="background:#f8fafc; padding:20px; border-radius:12px; margin-bottom:20px;">
            <div class="field-group" style="margin-bottom:0;">
                <label>Emergency Contact Name</label>
                <input type="text" name="emergency_name" value="<?= htmlspecialchars($patient['emergency_contact_name']) ?>">
            </div>
            <div class="field-group" style="margin-bottom:0;">
                <label>Emergency Contact Phone</label>
                <input type="text" name="emergency_phone" value="<?= htmlspecialchars($patient['emergency_contact_phone']) ?>">
            </div>
        </div>

        <div class="field-group">
            <label>Medical History</label>
            <textarea name="medical_history"><?= htmlspecialchars($patient['medical_history']) ?></textarea>
        </div>

        <div style="text-align:right; margin-top:20px;">
            <button type="submit" name="update_patient" class="btn-update">
                <i class="fa-solid fa-floppy-disk"></i> &nbsp;Save Changes
            </button>
        </div>
    </form>
</div>
<?php endif; ?>