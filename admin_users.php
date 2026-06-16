<?php
/* ================= SESSION & DATABASE ================= */
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include("db.php");

/* ================= ADD NEW STAFF (TOKEN SLOT ROUTINE) ================= */
if (isset($_POST['add_user'])) {
    $registration_id = mysqli_real_escape_string($conn, trim($_POST['registration_id']));
    $department      = mysqli_real_escape_string($conn, trim($_POST['department']));

    if (!empty($registration_id) && !empty($department)) {
        $insert_query = "INSERT INTO users 
                            (full_name, username, email, password, role, registration_id, status) 
                         VALUES 
                            (NULL, NULL, NULL, NULL, '$department', '$registration_id', 'Pending')";
        mysqli_query($conn, $insert_query);
    }
}

/* ================= UPDATE EXISTING STAFF PROFILE ================= */
if (isset($_POST['update_user'])) {
    $user_id   = (int)$_POST['user_id'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $role      = mysqli_real_escape_string($conn, $_POST['role']);
    $status    = mysqli_real_escape_string($conn, $_POST['status']);

    mysqli_query($conn, "
        UPDATE users SET 
            full_name='$full_name', 
            username='$username', 
            email='$email', 
            role='$role', 
            status='$status' 
        WHERE user_id=$user_id
    ");
}

/* ================= ARCHIVE STAFF MEMBER ================= */
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if (isset($_SESSION['user_id']) && $delete_id !== (int)$_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM users WHERE user_id=$delete_id");
    }
}

/* ================= FILTER & SEARCH EXECUTION ================= */
$search = $_GET['search'] ?? '';
$where = "";
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where = "WHERE full_name LIKE '%$search%' OR username LIKE '%$search%' OR email LIKE '%$search%' OR role LIKE '%$search%'";
}

$users = mysqli_query($conn, "SELECT user_id, full_name, username, email, role, status FROM users $where ORDER BY role, full_name");
?>

<style>
    :root {
        --emerald-950: #022c22;
        --emerald-900: #052e16; 
        --emerald-600: #16a34a; 
        --emerald-50: #f0fdf4;
        --slate-900: #0f172a;
        --slate-700: #334155;
        --slate-400: #94a3b8;
        --border-color: #e2e8f0;
        --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
        --shadow-md: 0 12px 30px rgba(5, 46, 22, 0.06);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .dashboard-header { 
        display: flex; justify-content: space-between; align-items: center; 
        margin-bottom: 35px; background: #fff; padding: 25px 30px; 
        border-radius: 24px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);
    }
    .dashboard-header h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: var(--emerald-900); margin: 0; }
    .dashboard-header p { color: var(--slate-400); font-size: 13px; margin-top: 4px; font-weight: 500; }
    
    .interactive-action-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; }
    .search-wrapper { position: relative; display: flex; align-items: center; }
    .search-wrapper i { position: absolute; left: 16px; color: var(--slate-400); font-size: 15px; }
    
    .premium-search-input { 
        padding: 14px 16px 14px 48px; border-radius: 16px; border: 1.5px solid var(--border-color); 
        width: 340px; font-family: inherit; font-size: 14px; background: #fff; transition: var(--transition);
    }
    .premium-search-input:focus { border-color: var(--emerald-600); outline: none; }
    
    .btn-action-primary { 
        background: var(--emerald-900); color: #fff; border: none; padding: 14px 28px; 
        border-radius: 16px; font-weight: 700; font-size: 14px; cursor: pointer; transition: var(--transition);
        display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 12px rgba(5, 46, 22, 0.15);
    }
    .btn-action-primary:hover { background: var(--emerald-600); transform: translateY(-2px); }
    
    .table-container-card { background: #fff; border-radius: 24px; border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-md); }
    .premium-medical-table { width: 100%; border-collapse: collapse; text-align: left; }
    .premium-medical-table th { background: #f8fafc; padding: 20px 24px; font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--slate-700); font-weight: 700; border-bottom: 1px solid var(--border-color); }
    .premium-medical-table td { padding: 18px 24px; font-size: 14px; color: var(--slate-900); border-bottom: 1px solid #f8fafc; vertical-align: middle; }
    .premium-medical-table tr:hover td { background: #fafcfe; }

    .role-tag { background: var(--emerald-50); color: var(--emerald-600); padding: 6px 12px; border-radius: 10px; font-size: 12px; font-weight: 700; text-transform: capitalize; border: 1px solid rgba(22, 163, 74, 0.15); display: inline-block; }
    .status-pill { padding: 6px 14px; border-radius: 50px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
    .status-pill::before { content:''; width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
    .status-pill-active { background: #dcfce7; color: #15803d; }
    .status-pill-active::before { background: #16a34a; }
    .status-pill-inactive { background: #fee2e2; color: #dc2626; }
    .status-pill-inactive::before { background: #ef4444; }
    .status-pill-pending { background: #fef9c3; color: #a16207; }
    .status-pill-pending::before { background: #ca8a04; }

    .modal-backdrop-blur { display: none; position: fixed; inset: 0; background: rgba(2, 44, 34, 0.45); backdrop-filter: blur(12px); z-index: 9999; align-items: center; justify-content: center; }
    .modal-body-panel { background: #fff; width: 480px; padding: 40px; border-radius: 32px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid rgba(255,255,255,0.8); }
    .modal-body-panel h3 { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--emerald-900); margin-bottom: 4px; }
    
    .field-block { margin-bottom: 22px; }
    .field-block label { display: block; font-size: 11px; font-weight: 700; margin-bottom: 8px; color: var(--slate-700); text-transform: uppercase; letter-spacing: 1px; }
    .input-premium-control { width: 100%; padding: 14px; border-radius: 14px; border: 1.5px solid var(--border-color); font-family: inherit; font-size: 14px; background: #f8fafc; transition: var(--transition); box-sizing: border-box; }
    .input-premium-control:focus { border-color: var(--emerald-600); background: #fff; outline: none; }
    .input-premium-control.readonly-generated { background: #f0fdf4; color: var(--emerald-900); font-weight: 700; cursor: not-allowed; border-color: rgba(22,163,74,0.3); }
    
    .info-annotation-box { background: var(--emerald-50); border-radius: 12px; padding: 12px 16px; display: flex; align-items: flex-start; gap: 10px; margin-bottom: 20px; border: 1px dashed rgba(22, 163, 74, 0.3); }
    .info-annotation-box i { color: var(--emerald-600); margin-top: 2px; }
    .info-annotation-box p { font-size: 12px; color: var(--emerald-900); line-height: 1.5; margin: 0; }
</style>

<div class="dashboard-header">
    <div>
        <h2>Hospital Workforce Hub</h2>
        <p>Issue operational registration tokens and audit existing user access controls.</p>
    </div>
    <button onclick="toggleStaffModal(true)" class="btn-action-primary">
        <i class="fa-solid fa-user-plus"></i> Add New Staff Member
    </button>
</div>

<div class="interactive-action-row">
    <form method="GET" style="display:flex; gap:12px; width: 100%;">
        <input type="hidden" name="page" value="users">
        <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" class="premium-search-input" value="<?= htmlspecialchars($search) ?>" placeholder="Search by staff details or department...">
        </div>
        <button type="submit" class="btn-action-primary" style="background: #fff; color: var(--emerald-900); border: 1px solid var(--border-color); padding: 0 20px;">
            <i class="fa-solid fa-sliders"></i> Filter
        </button>
    </form>
</div>

<div class="table-container-card">
    <table class="premium-medical-table">
        <thead>
            <tr>
                <th>Official Name</th>
                <th>Access Credentials</th>
                <th>Assigned Department</th>
                <th>Account Status</th>
                <th style="text-align:right;">Profile Management</th>
            </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($users) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($users)): ?>
            <tr>
                <td style="font-weight:700; color: var(--emerald-900);">
                    <?= !empty($row['full_name']) ? htmlspecialchars($row['full_name']) : '<span style="color:var(--slate-400); font-weight:500; font-style:italic;">Unassigned</span>' ?>
                </td>
                <td>
                    <div style="font-weight: 600; font-size: 13px; color: var(--slate-900);">
                        <?= !empty($row['username']) ? htmlspecialchars($row['username']) : '<span style="color:var(--slate-400); font-style:italic;">Pending setup</span>' ?>
                    </div>
                    <div style="font-size: 12px; color: var(--slate-400); margin-top: 2px;">
                        <?= !empty($row['email']) ? htmlspecialchars($row['email']) : '' ?>
                    </div>
                </td>
                <td><span class="role-tag"><?= htmlspecialchars(ucwords($row['role'])) ?></span></td>
                <td>
                    <?php
                        $status_lower = strtolower($row['status']);
                        $pill_class = match($status_lower) {
                            'active'  => 'status-pill-active',
                            'pending' => 'status-pill-pending',
                            default   => 'status-pill-inactive'
                        };
                    ?>
                    <span class="status-pill <?= $pill_class ?>">
                        <?= htmlspecialchars($row['status']) ?>
                    </span>
                </td>
                <td style="text-align:right;">
                    <?php if ($row['status'] !== 'Pending'): ?>
                    <a href="#" class="icon-btn" style="margin-right: 10px;"
                       onclick="openStaffEditModal(
                           '<?= $row['user_id'] ?>',
                           '<?= addslashes($row['full_name']) ?>',
                           '<?= addslashes($row['username']) ?>',
                           '<?= addslashes($row['email']) ?>',
                           '<?= $row['role'] ?>',
                           '<?= $row['status'] ?>'
                       )">
                        <i class="fa-solid fa-user-pen" style="color:var(--emerald-600);"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_id']) && $row['user_id'] != $_SESSION['user_id']): ?>
                        <a href="?page=users&delete=<?= $row['user_id'] ?>" class="icon-btn"
                           onclick="return confirm('Revoke account and remove token data from system registry?')">
                            <i class="fa-solid fa-trash-can" style="color:#ef4444;"></i>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" align="center" style="padding:60px; color: var(--slate-400); font-weight: 500;">
                    No medical staff accounts match current parameters.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ADD STAFF MODAL -->
<div id="addStaffModal" class="modal-backdrop-blur">
    <div class="modal-body-panel">
        <h3>Add New Staff Member</h3>
        <p style="color: var(--slate-400); font-size: 13px; margin-bottom: 25px;">
            Provision a new database registration slot for clinic staff validation.
        </p>

        <div class="info-annotation-box">
            <i class="fa-solid fa-circle-info"></i>
            <p>Select a department first. The system automatically fetches database statistics to construct a sequential ID.</p>
        </div>

        <form method="POST">
            <input type="hidden" name="registration_id" id="new_staff_id">

            <div class="field-block">
                <label>Assigned Department *</label>
                <select name="department" id="new_staff_dept" class="input-premium-control" onchange="fetchNextStaffId()" required>
                    <option value="" disabled selected>-- Select Department --</option>
                    <option value="doctor">Doctor</option>
                    <option value="nurse">Nurse</option>
                    <option value="operations admin">Operations Admin</option>
                    <option value="lab technician">Lab Technician</option>
                    <option value="pharmacist">Pharmacist</option>
                    <option value="receptionist">Receptionist</option>
                </select>
            </div>

            <div class="field-block">
                <label>Registration Token Slot ID</label>
                <input id="new_staff_id_display"
                       class="input-premium-control readonly-generated"
                       placeholder="Select a department to generate ID..."
                       readonly>
            </div>

            <div style="margin-top:35px; display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" onclick="toggleStaffModal(false)" style="background:none; border:none; cursor:pointer; font-weight:700; color:var(--slate-400); font-size:14px; padding: 10px 15px;">
                    Dismiss
                </button>
                <button type="submit" name="add_user" id="submit_add_user" class="btn-action-primary" disabled style="opacity:0.5; cursor:not-allowed;">
                    Authorize Staff Entry
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT STAFF MODAL -->
<div id="editStaffModal" class="modal-backdrop-blur">
    <div class="modal-body-panel">
        <h3>Edit Staff Credentials</h3>
        <p style="color: var(--slate-400); font-size: 13px; margin-bottom: 25px;">
            Modify structural access levels or active system parameters.
        </p>

        <form method="POST">
            <input type="hidden" name="user_id" id="edit_uid">

            <div class="field-block">
                <label>Full Legal Name</label>
                <input name="full_name" id="edit_name" class="input-premium-control" required>
            </div>

            <div class="field-block">
                <label>Username</label>
                <input name="username" id="edit_username" class="input-premium-control" required>
            </div>

            <div class="field-block">
                <label>Email Address</label>
                <input name="email" id="edit_email" type="email" class="input-premium-control" required>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div class="field-block">
                    <label>Department Role</label>
                    <select name="role" id="edit_role" class="input-premium-control">
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="operations admin">Operations Admin</option>
                        <option value="lab technician">Lab Technician</option>
                        <option value="pharmacist">Pharmacist</option>
                        <option value="receptionist">Receptionist</option>
                    </select>
                </div>
                <div class="field-block">
                    <label>System Status</label>
                    <select name="status" id="edit_status" class="input-premium-control">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:30px; display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" onclick="toggleEditModal(false)" style="background:none; border:none; cursor:pointer; font-weight:700; color:var(--slate-400); font-size:14px; padding: 10px 15px;">
                    Cancel
                </button>
                <button type="submit" name="update_user" class="btn-action-primary">
                    Commit Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleStaffModal(show) {
    if (!show) {
        document.getElementById('new_staff_dept').value = "";
        document.getElementById('new_staff_id').value = "";
        document.getElementById('new_staff_id_display').value = "";
        document.getElementById('new_staff_id_display').placeholder = "Select a department to generate ID...";
        const submitBtn = document.getElementById('submit_add_user');
        submitBtn.disabled = true;
        submitBtn.style.opacity = "0.5";
        submitBtn.style.cursor = "not-allowed";
    }
    document.getElementById('addStaffModal').style.display = show ? 'flex' : 'none';
}

function toggleEditModal(show) {
    document.getElementById('editStaffModal').style.display = show ? 'flex' : 'none';
}

function fetchNextStaffId() {
    const departmentValue = document.getElementById('new_staff_dept').value;
    const idDisplay       = document.getElementById('new_staff_id_display');
    const idHidden        = document.getElementById('new_staff_id');
    const submitBtn       = document.getElementById('submit_add_user');

    if (!departmentValue) return;

    idDisplay.value = "";
    idHidden.value  = "";
    idDisplay.placeholder = "Generating secure ID sequence...";
    submitBtn.disabled = true;
    submitBtn.style.opacity = "0.5";
    submitBtn.style.cursor = "not-allowed";

    fetch(`get_next_staff_id.php?department=${encodeURIComponent(departmentValue)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                idDisplay.value = data.id;
                idHidden.value  = data.id;
                submitBtn.disabled = false;
                submitBtn.style.opacity = "1";
                submitBtn.style.cursor = "pointer";
            } else {
                idDisplay.placeholder = "Error mapping department counters.";
            }
        })
        .catch(err => {
            console.error("Token Generation Failed:", err);
            idDisplay.placeholder = "Database connectivity timeout.";
        });
}

function openStaffEditModal(id, name, username, email, role, status) {
    document.getElementById('edit_uid').value      = id;
    document.getElementById('edit_name').value     = name;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value    = email;
    document.getElementById('edit_role').value     = role.toLowerCase();
    document.getElementById('edit_status').value   = status;
    toggleEditModal(true);
}
</script>