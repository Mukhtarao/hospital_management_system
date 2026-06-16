<?php
/**
 * HGH Patient Profile Management
 * Integrated into Dashboard Layout
 */
include("db.php");

if (empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* --- Data Hydration --- */
$stmt = $conn->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$is_new = !$data;

$full_name_val  = htmlspecialchars($data['full_name'] ?? '');
$age_val        = htmlspecialchars($data['age'] ?? '');
$gender_val     = $data['gender'] ?? '';
$blood_val      = $data['blood_group'] ?? '';
$phone_val      = htmlspecialchars($data['phone'] ?? '');
$email_val      = htmlspecialchars($data['email'] ?? '');
$address_val    = htmlspecialchars($data['address'] ?? '');
$emg_name_val   = htmlspecialchars($data['emergency_contact_name'] ?? '');
$emg_phone_val  = htmlspecialchars($data['emergency_contact_phone'] ?? '');
$patient_id_val = $data['patient_id'] ?? 'Pending Assignment';

$blood_groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

/* --- Persistence Logic --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name       = $_POST['full_name'] ?? '';
    $age             = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $gender          = $_POST['gender'] ?? '';
    $blood_group     = $_POST['blood_group'] ?? '';
    $phone           = $_POST['phone'] ?? '';
    $email           = $_POST['email'] ?? '';
    $address         = $_POST['address'] ?? '';
    $emergency_name  = $_POST['emergency_name'] ?? '';
    $emergency_phone = $_POST['emergency_phone'] ?? '';

    if ($is_new) {
        $sql = "INSERT INTO patients (user_id, full_name, age, gender, blood_group, phone, email, address, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $save_stmt = $conn->prepare($sql);
        $save_stmt->bind_param("isisssssss", $user_id, $full_name, $age, $gender, $blood_group, $phone, $email, $address, $emergency_name, $emergency_phone);
    } else {
        $sql = "UPDATE patients SET full_name=?, age=?, gender=?, blood_group=?, phone=?, email=?, address=?, emergency_contact_name=?, emergency_contact_phone=? WHERE user_id=?";
        $save_stmt = $conn->prepare($sql);
        $save_stmt->bind_param("sisssssssi", $full_name, $age, $gender, $blood_group, $phone, $email, $address, $emergency_name, $emergency_phone, $user_id);
    }

    if ($save_stmt->execute()) {
        echo "<script>alert('Patient Information Updated Successfully'); window.location='?page=patient_profile';</script>";
        exit;
    } else {
        echo "<script>alert('System Error: Unable to update profile details.');</script>";
    }
}
?>

<style>
/* Root Fallbacks to handle isolated template insertion */
:root {
    --g900: #052e16;
    --g600: #16a34a;
    --g400: #4ade80;
}

.profile-wrapper { max-width: 900px; margin: 0 auto; padding-top: 10px; font-family: 'Outfit', sans-serif; }

.saas-card { 
    background: #fff; border: 1px solid #edf2f7; 
    border-radius: 24px; padding: 35px; margin-bottom: 25px;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.02); transition: 0.3s ease;
}
.saas-card:hover { border-color: var(--g400); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.05); }

.card-label { 
    font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px;
    color: var(--g600); margin-bottom: 25px; display: flex; align-items: center; gap: 12px;
}
.card-label::after { content: ''; height: 1px; flex: 1; background: linear-gradient(to right, #edf2f7, transparent); }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.input-wrap { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
.input-label { font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; }

.saas-input { 
    font-size: 14px; padding: 14px 18px; border: 1.5px solid #f1f5f9; 
    border-radius: 14px; background: #f8fafc; transition: 0.3s; color: var(--g900); font-weight: 500;
}
.saas-input:focus { outline: none; border-color: var(--g600); background: #fff; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1); }

/* Custom Profile Header */
.profile-header-box { 
    display: flex; align-items: center; gap: 30px; margin-bottom: 35px; 
    background: #fff; padding: 30px; border-radius: 24px; border: 1px solid #edf2f7;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.02);
}
.avatar-xl {
    width: 90px; height: 90px; border-radius: 24px;
    background: var(--g900); color: #fff; font-size: 32px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 10px 20px rgba(5, 46, 22, 0.2);
}

/* Pill Selectors */
.pill-group { display: flex; background: #f1f5f9; padding: 6px; border-radius: 16px; gap: 6px; }
.pill { flex: 1; text-align: center; padding: 10px; font-size: 13px; font-weight: 700; cursor: pointer; border-radius: 12px; color: #94a3b8; transition: 0.3s; }
.pill.active { background: #fff; color: var(--g600); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }

/* Solved visibility issue by enforcing clean high-contrast rules explicitly */
.btn-update { 
    background: #052e16 !important; 
    color: #ffffff !important; 
    border: none; 
    padding: 18px 45px;
    border-radius: 16px; 
    font-weight: 800; 
    cursor: pointer; 
    transition: 0.3s transform ease, 0.3s background-color ease;
    font-size: 14px; 
    display: inline-flex; 
    align-items: center; 
    gap: 10px;
    box-shadow: 0 4px 12px rgba(5, 46, 22, 0.15);
}
.btn-update:hover { 
    background: #15803d !important; 
    transform: translateY(-3px); 
    box-shadow: 0 10px 20px rgba(22, 163, 74, 0.2); 
}

.emergency-theme { border-top: 4px solid #ef4444; }
</style>

<div class="profile-wrapper">
    <div class="profile-header-box">
        <div class="avatar-xl" id="avatarBox">P</div>
        <div>
            <h1 id="headerName" style="font-family:'Playfair Display', serif; font-size: 28px; color: var(--g900); margin: 0;"><?= $full_name_val ?: 'Set Profile Name' ?></h1>
            <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                <span style="font-size: 12px; font-weight: 700; color: var(--g600); background: #f0fdf4; padding: 4px 12px; border-radius: 20px;">Patient Portal</span>
                <span style="color: #94a3b8; font-size: 13px;">ID: <strong style="color: #475569; font-family: monospace;"><?= $patient_id_val ?></strong></span>
            </div>
        </div>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="gender" id="genderInput" value="<?= $gender_val ?>">

        <div class="saas-card">
            <div class="card-label">Identity Overview</div>
            <div class="form-grid">
                <div class="input-wrap">
                    <label class="input-label">Legal Full Name</label>
                    <input type="text" name="full_name" class="saas-input" value="<?= $full_name_val ?>" oninput="syncUI(this.value)" placeholder="e.g. Ahmed Ali" required>
                </div>
                <div class="input-wrap">
                    <label class="input-label">Current Age</label>
                    <input type="number" name="age" class="saas-input" value="<?= $age_val ?>" placeholder="Years">
                </div>
            </div>

            <div class="form-grid">
                <div class="input-wrap">
                    <label class="input-label">Biological Gender</label>
                    <div class="pill-group">
                        <div class="pill <?= $gender_val == 'Male' ? 'active' : '' ?>" onclick="setPill('Male')">Male</div>
                        <div class="pill <?= $gender_val == 'Female' ? 'active' : '' ?>" onclick="setPill('Female')">Female</div>
                    </div>
                </div>
                <div class="input-wrap">
                    <label class="input-label">Blood Group</label>
                    <select name="blood_group" class="saas-input">
                        <option value="">Select Type</option>
                        <?php foreach($blood_groups as $bg): ?>
                            <option value="<?= $bg ?>" <?= $blood_val == $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="saas-card">
            <div class="card-label">Contact & Logistics</div>
            <div class="form-grid">
                <div class="input-wrap">
                    <label class="input-label">Email Address</label>
                    <input type="email" name="email" class="saas-input" value="<?= $email_val ?>" placeholder="email@example.com">
                </div>
                <div class="input-wrap">
                    <label class="input-label">Mobile Phone</label>
                    <input type="text" name="phone" class="saas-input" value="<?= $phone_val ?>" placeholder="+252 ...">
                </div>
            </div>
            <div class="input-wrap" style="margin-bottom:0">
                <label class="input-label">Residential Address</label>
                <textarea name="address" class="saas-input" rows="3" style="resize:none" placeholder="Hargeisa, Somaliland..."><?= $address_val ?></textarea>
            </div>
        </div>

        <div class="saas-card emergency-theme">
            <div class="card-label" style="color:#ef4444">Emergency Contact (SOS)</div>
            <div class="form-grid">
                <div class="input-wrap">
                    <label class="input-label">Next of Kin Name</label>
                    <input type="text" name="emergency_name" class="saas-input" value="<?= $emg_name_val ?>" placeholder="Contact Name">
                </div>
                <div class="input-wrap">
                    <label class="input-label">Kin Phone Number</label>
                    <input type="text" name="emergency_phone" class="saas-input" value="<?= $emg_phone_val ?>" placeholder="+252 ...">
                </div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:15px; margin-bottom: 50px;">
            <button type="submit" name="update_profile" class="btn-update">
                <i class="fa-solid fa-pen-to-square"></i> Update Profile Details
            </button>
        </div>
    </form>
</div>

<script>
function syncUI(val) {
    document.getElementById('headerName').innerText = val || 'Set Profile Name';
    const names = val.trim().split(' ');
    const initials = names.length >= 2 ? names[0][0] + names[names.length-1][0] : (names[0] ? names[0][0] : 'P');
    const avatarBox = document.getElementById('avatarBox');
    if(avatarBox) {
        avatarBox.innerText = initials.toUpperCase();
    }
}

function setPill(val) {
    document.getElementById('genderInput').value = val;
    document.querySelectorAll('.pill').forEach(p => {
        p.classList.toggle('active', p.innerText === val);
    });
}

// Initial Evaluation
syncUI("<?= $full_name_val ?>");
</script>