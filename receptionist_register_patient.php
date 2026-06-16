<?php
/* ================= FORM SUBMISSION ================= */
$success = "";
$error = "";

include("db.php");

if (isset($_POST['register_patient'])) {

    /* ========= REQUIRED FIELDS ========= */
    $first_name  = mysqli_real_escape_string($conn, $_POST['first_name'] ?? '');
    $last_name   = mysqli_real_escape_string($conn, $_POST['last_name'] ?? '');
    $dob         = $_POST['dob'] ?? '';
    $gender      = mysqli_real_escape_string($conn, $_POST['gender'] ?? '');
    $blood_group = mysqli_real_escape_string($conn, $_POST['blood_group'] ?? '');
    $phone       = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');

    /* ========= OPTIONAL FIELDS ========= */
    $email       = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $address     = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $em_name     = mysqli_real_escape_string($conn, $_POST['emergency_name'] ?? '');
    $em_phone    = mysqli_real_escape_string($conn, $_POST['emergency_phone'] ?? '');
    $history     = mysqli_real_escape_string($conn, $_POST['medical_history'] ?? '');

    /* ========= VALIDATION ========= */
    if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || empty($blood_group) || empty($phone)) {
        $error = "Please fill in all required fields marked with *";
    } else {

        /* ========= AUTO-GENERATED FIELDS ========= */
        $full_name = $first_name . ' ' . $last_name;
        $age = date_diff(date_create($dob), date_create('today'))->y;

        /* ========= INSERT ========= */
        $sql = "INSERT INTO patients (
                    first_name, last_name, full_name, date_of_birth, age, 
                    gender, blood_group, phone, email, address, 
                    emergency_contact_name, emergency_contact_phone, medical_history
                ) VALUES (
                    '$first_name', '$last_name', '$full_name', '$dob', '$age', 
                    '$gender', '$blood_group', '$phone', '$email', '$address', 
                    '$em_name', '$em_phone', '$history'
                )";

        if (mysqli_query($conn, $sql)) {
            $success = "Patient <b>$full_name</b> has been registered successfully.";
        } else {
            $error = "Error registering patient: " . mysqli_error($conn);
        }
    }
}
?>

<style>
    .form-container { background: #fff; border-radius: 20px; }
    .form-title { font-family: 'Playfair Display'; color: var(--g900); font-size: 26px; margin-bottom: 5px; }
    .form-sub { color: #64748b; font-size: 14px; margin-bottom: 25px; }
    
    .input-group { margin-bottom: 18px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
    label span { color: #ef4444; }

    input, select, textarea {
        width: 100%; padding: 12px 16px; border-radius: 12px;
        border: 1px solid #e2e8f0; background: #fcfdfe;
        font-family: inherit; font-size: 14px; transition: all 0.2s ease;
    }
    input:focus, select:focus, textarea:focus {
        outline: none; border-color: var(--g600);
        box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1); background: #fff;
    }
    textarea { resize: none; height: 100px; }

    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }

    .btn-reg {
        background: var(--g900); color: #fff; padding: 14px 28px;
        border-radius: 12px; border: none; font-weight: 700;
        cursor: pointer; transition: 0.3s;
    }
    .btn-reg:hover { background: var(--g600); transform: translateY(-1px); }
    
    .btn-reset {
        background: #f1f5f9; color: #475569; padding: 14px 28px;
        border-radius: 12px; border: none; font-weight: 600;
        cursor: pointer; transition: 0.2s;
    }
    .btn-reset:hover { background: #e2e8f0; }

    .alert { padding: 15px 20px; border-radius: 14px; margin-bottom: 25px; font-size: 14px; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bcf0da; }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }
</style>

<div class="form-container">
    <h2 class="form-title">Patient Registration</h2>
    <p class="form-sub">Enter the patient's personal and medical details to create a new record.</p>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="grid-2">
            <div class="input-group">
                <label>First Name <span>*</span></label>
                <input type="text" name="first_name" placeholder="e.g. John" required>
            </div>
            <div class="input-group">
                <label>Last Name <span>*</span></label>
                <input type="text" name="last_name" placeholder="e.g. Doe" required>
            </div>
        </div>

        <div class="grid-3">
            <div class="input-group">
                <label>Date of Birth <span>*</span></label>
                <input type="date" name="dob" max="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="input-group">
                <label>Gender <span>*</span></label>
                <select name="gender" required>
                    <option value="">Select</option>
                    <option>Male</option>
                    <option>Female</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="input-group">
                <label>Blood Group <span>*</span></label>
                <select name="blood_group" required>
                    <option value="">Select</option>
                    <option>A+</option><option>A-</option>
                    <option>B+</option><option>B-</option>
                    <option>AB+</option><option>AB-</option>
                    <option>O+</option><option>O-</option>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="input-group">
                <label>Phone Number <span>*</span></label>
                <input type="text" name="phone" placeholder="+252 ..." required>
            </div>
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="patient@example.com">
            </div>
        </div>

        <div class="input-group">
            <label>Residential Address</label>
            <textarea name="address" placeholder="Street, District, City..."></textarea>
        </div>

        <div class="grid-2" style="background: #f8fafc; padding: 20px; border-radius: 15px; margin-bottom: 20px; border: 1px dashed #cbd5e1;">
            <div class="input-group" style="margin-bottom: 0;">
                <label>Emergency Contact Name</label>
                <input type="text" name="emergency_name" placeholder="Relative or Friend Name">
            </div>
            <div class="input-group" style="margin-bottom: 0;">
                <label>Emergency Contact Phone</label>
                <input type="text" name="emergency_phone" placeholder="Emergency Phone">
            </div>
        </div>

        <div class="input-group">
            <label>Medical History / Known Allergies</label>
            <textarea name="medical_history" placeholder="Note any previous conditions or chronic illnesses..."></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #edf2f6;">
            <button type="reset" class="btn-reset">Clear Form</button>
            <button type="submit" name="register_patient" class="btn-reg">
                <i class="fa-solid fa-user-check"></i> &nbsp;Complete Registration
            </button>
        </div>
    </form>
</div>