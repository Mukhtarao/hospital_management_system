<?php
include("db.php");

// 1. FETCH REGISTERED DOCTORS WHO DO NOT HAVE A PROFILE YET
$users_query = mysqli_query($conn, "
    SELECT u.user_id, u.full_name 
    FROM users u 
    LEFT JOIN doctors d ON u.user_id = d.user_id 
    WHERE u.role = 'Doctor' AND d.user_id IS NULL
");

// 2. FORM PROCESSING
if (isset($_POST['save_doctor'])) {
    $doctor_id = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
    $user_id = (int)$_POST['user_id'];
    $specialization = mysqli_real_escape_string($conn, $_POST['specialization']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $availability = mysqli_real_escape_string($conn, $_POST['availability']);
    
    $user_info_query = mysqli_query($conn, "SELECT full_name, email FROM users WHERE user_id = $user_id");
    $user_info = mysqli_fetch_assoc($user_info_query);
    $full_name = mysqli_real_escape_string($conn, $user_info['full_name']);
    $email = mysqli_real_escape_string($conn, $user_info['email']);

    $image_path = $_POST['current_image']; 
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $target_dir = "uploads/doctors/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $file_extension = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
        $file_name = time() . '_' . $user_id . '.' . $file_extension;
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    if ($doctor_id) {
        $sql = "UPDATE doctors SET 
                user_id='$user_id', full_name='$full_name', specialization='$specialization', 
                department='$department', email='$email', availability='$availability', 
                profile_photo='$image_path' WHERE doctor_id=$doctor_id";
    } else {
        $sql = "INSERT INTO doctors (user_id, full_name, specialization, department, email, availability, profile_photo) 
                VALUES ('$user_id', '$full_name', '$specialization', '$department', '$email', '$availability', '$image_path')";
    }
    
    if (mysqli_query($conn, $sql)) {
        echo "<script>window.location.href='admin_dashboard.php?page=roles';</script>";
        exit;
    }
}

$doctors_result = mysqli_query($conn, "SELECT * FROM doctors ORDER BY full_name ASC");
?>

<style>
    :root {
        --g900: #052e16; --g600: #16a34a; --g400: #4ade80;
        --border: #e2e8f0;
    }

    .admin-container { animation: fadeIn 0.5s ease; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
    .title-group h1 { font-family: 'Playfair Display'; font-size: 30px; font-weight: 700; color: var(--g900); margin: 0; }
    .title-group p { color: #64748b; font-size: 14px; margin-top: 4px; }

    .btn-create { 
        background: var(--g900); color: white; padding: 12px 24px; border-radius: 12px; border: none;
        font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;
        transition: 0.3s; box-shadow: 0 4px 12px rgba(5, 46, 22, 0.15);
    }
    .btn-create:hover { background: var(--g600); transform: translateY(-2px); }

    /* Grid Layout */
    .doctor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
    
    .glass-card { 
        background: #ffffff; border-radius: 20px; overflow: hidden; 
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03); border: 1px solid var(--border);
        display: flex; flex-direction: column; transition: 0.3s;
    }
    .glass-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.07); }
    
    .image-box { width: 100%; height: 300px; overflow: hidden; background: #f1f5f9; }
    .profile-photo { width: 100%; height: 100%; object-fit: cover; }
    
    .card-content { padding: 22px; flex-grow: 1; border-top: 1px solid #f8fafc; }
    .spec-tag { color: var(--g600); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }
    .doc-name { font-family: 'Playfair Display'; font-size: 20px; font-weight: 700; color: var(--g900); margin: 0 0 10px 0; }
    .doc-desc { font-size: 13px; color: #64748b; margin: 0 0 15px 0; line-height: 1.6; }
    
    .time-badge { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--g900); font-weight: 600; padding: 10px; background: #f0fdf4; border-radius: 10px; margin-bottom: 20px; }

    .action-btn {
        width: 100%; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0;
        background: #fff; color: var(--g900); font-weight: 700; cursor: pointer; transition: 0.2s;
    }
    .action-btn:hover { background: var(--g900); color: #fff; border-color: var(--g900); }

    /* Modal Styling */
    .modal-wrap { display: none; position: fixed; inset: 0; background: rgba(5, 46, 22, 0.4); backdrop-filter: blur(8px); z-index: 9999; justify-content: center; align-items: center; }
    .modal-box { background: white; width: 500px; padding: 40px; border-radius: 30px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
    .form-input { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 18px; font-family: inherit; font-size: 14px; }
    .form-label { font-weight: 700; font-size: 12px; color: var(--g900); text-transform: uppercase; margin-bottom: 6px; display: block; }
</style>

<div class="admin-container">
    <div class="header-flex">
        <div class="title-group">
            <h1>Staff Registry</h1>
            <p>Manage specialist public profiles and portal credentials.</p>
        </div>
        <button class="btn-create" onclick="openModal()">
            <i class="fa-solid fa-user-plus"></i> Add Doctor Profile
        </button>
    </div>

    <div class="doctor-grid">
        <?php while($row = mysqli_fetch_assoc($doctors_result)): ?>
            <div class="glass-card">
                <div class="image-box">
                    <img src="<?= !empty($row['profile_photo']) ? $row['profile_photo'] : 'assets/img/default-doc.png' ?>" class="profile-photo">
                </div>
                <div class="card-content">
                    <span class="spec-tag"><?= htmlspecialchars($row['specialization']) ?></span>
                    <h3 class="doc-name"><?= htmlspecialchars($row['full_name']) ?></h3>
                    <p class="doc-desc"><?= htmlspecialchars($row['department']) ?></p>
                    <div class="time-badge">
                        <i class="fa-solid fa-clock" style="color:var(--g600);"></i> 
                        <?= htmlspecialchars($row['availability']) ?>
                    </div>
                    <button class="action-btn" onclick='editDoc(<?= json_encode($row) ?>)'>
                        <i class="fa-solid fa-pen-to-square"></i> Edit Credentials
                    </button>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<div id="docModal" class="modal-wrap">
    <div class="modal-box">
        <h2 id="modalTitle" style="font-family:'Playfair Display'; color:var(--g900); margin-top:0; margin-bottom:25px;">Profile Details</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="doctor_id" id="f_id">
            <input type="hidden" name="current_image" id="f_curr_img">
            
            <label class="form-label">Linked User Account</label>
            <select name="user_id" id="f_user" required class="form-input">
                <option value="">-- Choose Account --</option>
                <?php mysqli_data_seek($users_query, 0); 
                      while($u = mysqli_fetch_assoc($users_query)): ?>
                    <option value="<?= $u['user_id'] ?>"><?= $u['full_name'] ?></option>
                <?php endwhile; ?>
            </select>

            <label class="form-label">Medical Specialization</label>
            <input type="text" name="specialization" id="f_spec" placeholder="e.g. Senior Neurosurgeon" class="form-input" required>
            
            <label class="form-label">Professional Bio / Dept</label>
            <textarea name="department" id="f_dept" placeholder="Brief professional summary..." class="form-input" style="height:80px; resize:none;" required></textarea>
            
            <label class="form-label">Schedule / Availability</label>
            <input type="text" name="availability" id="f_avail" placeholder="e.g. Mon-Fri, 08:00 - 16:00" class="form-input" required>

            <label class="form-label">Profile Photo</label>
            <input type="file" name="profile_photo" id="f_photo" style="font-size:12px; margin-bottom:25px;">

            <div style="display:flex; justify-content:flex-end; gap:15px; border-top:1px solid #eee; pt-20px; padding-top:20px;">
                <button type="button" onclick="closeModal()" style="background:none; border:none; cursor:pointer; font-weight:700; color:#64748b;">Dismiss</button>
                <button type="submit" name="save_doctor" class="btn-create">Save Specialist Profile</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() { 
        document.getElementById('docModal').style.display = "flex"; 
        document.getElementById('f_id').value = ""; 
        document.getElementById('f_curr_img').value = "";
        document.getElementById('f_photo').required = true;
        document.getElementById('modalTitle').innerText = "Create Specialist Profile";
        
        let userSelect = document.getElementById('f_user');
        if(userSelect.options[1] && userSelect.options[1].getAttribute('data-temp') === 'true') {
            userSelect.remove(1);
        }
    }

    function closeModal() { document.getElementById('docModal').style.display = "none"; }

    function editDoc(doc) {
        document.getElementById('f_id').value = doc.doctor_id;
        document.getElementById('f_curr_img').value = doc.profile_photo;
        document.getElementById('f_spec').value = doc.specialization;
        document.getElementById('f_dept').value = doc.department;
        document.getElementById('f_avail').value = doc.availability;
        document.getElementById('f_photo').required = false;

        let userSelect = document.getElementById('f_user');
        if(userSelect.options[1] && userSelect.options[1].getAttribute('data-temp') === 'true') {
            userSelect.remove(1);
        }

        let opt = document.createElement('option');
        opt.value = doc.user_id;
        opt.text = doc.full_name;
        opt.selected = true;
        opt.setAttribute('data-temp', 'true');
        userSelect.add(opt, userSelect.options[1]);

        document.getElementById('modalTitle').innerText = "Update Specialist: " + doc.full_name;
        document.getElementById('docModal').style.display = "flex";
    }
</script>