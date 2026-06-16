<?php
// Include database connection
include("db.php");

// Fetch doctors for the list (using the table structure from image 7)
$doctors_query = "SELECT * FROM doctors ORDER BY full_name ASC";
$doctors_result = $conn->query($doctors_query);

// Logic for saving/updating a doctor's profile via form submission
if (isset($_POST['save_doctor'])) {
    $doctor_id = $_POST['doctor_id'] ?? null;
    $full_name = $_POST['full_name'];
    $specialization = $_POST['specialization'];
    $department = $_POST['department'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $availability = $_POST['availability'];

    // For simplicity, we handle image upload básica. In production, this needs more security.
    $image_path = $_POST['current_image'] ?? 'images/default_doctor.png';
    if (!empty($_FILES['profile_photo']['name'])) {
        $target_dir = "uploads/";
        $image_name = time() . '_' . basename($_FILES["profile_photo"]["name"]);
        $target_file = $target_dir . $image_name;
        if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    if ($doctor_id) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE doctors SET full_name=?, specialization=?, department=?, phone=?, email=?, availability=?, profile_photo=? WHERE doctor_id=?");
        $stmt->bind_param("sssssssi", $full_name, $specialization, $department, $phone, $email, $availability, $image_path, $doctor_id);
    } else {
        // Create new record
        $stmt = $conn->prepare("INSERT INTO doctors (full_name, specialization, department, phone, email, availability, profile_photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $full_name, $specialization, $department, $phone, $email, $availability, $image_path);
    }

    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?status=success");
        exit;
    } else {
        $error = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Doctor Profiles</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #0f172a; /* Main button color */
            --primary-teal: #0098a8; /* From Image 0 buttons */
            --bg-color: #f8fafc;
            --sidebar-color: #ffffff;
            --text-dark: #1e293b;
            --border-color: #e2e8f0;
            --radius-input: 10px;
            --radius-card: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-color); color: var(--text-dark); font-family: 'Plus Jakarta Sans', sans-serif; display: flex; }

        /* Sidebar similar to Image 1-4 */
        .sidebar { width: 260px; height: 100vh; background: var(--sidebar-color); border-right: 1px solid var(--border-color); padding: 24px; position: fixed; }
        .logo { font-size: 20px; font-weight: 700; color: #000; margin-bottom: 40px; display: flex; align-items: center; gap: 10px; }
        .nav-item { padding: 14px 18px; color: #64748b; text-decoration: none; border-radius: 12px; font-weight: 500; display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .nav-item:hover { background: #f1f5f9; color: var(--text-dark); }
        .nav-item.active { background: var(--primary-dark); color: #fff; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15); }

        /* Main Content */
        .main-content { margin-left: 260px; flex-grow: 1; min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { height: 70px; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(8px); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 40px; position: sticky; top: 0; z-index: 10; }
        .topbar h2 { font-size: 16px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .avatar-circle { width: 42px; height: 42px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #475569; }

        .container { padding: 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .page-header h1 { font-size: 28px; font-weight: 800; }
        .page-header p { color: #64748b; margin-top: 4px; }

        /* Use the Button styling from Image 4 (+ Book New) */
        .btn-add { background: var(--primary-dark); color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: transform 0.1s; }
        .btn-add:active { transform: translateY(1px); }

        /* Cards layout matching Image 0 */
        .doctor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
        .doc-card { background: #fff; border-radius: var(--radius-card); border: 1px solid var(--border-color); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: box-shadow 0.2s; position: relative; }
        .doc-card:hover { box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .doc-image-container { height: 200px; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid var(--border-color); }
        .doc-image { width: 100%; height: 100%; object-fit: cover; }
        
        .doc-info { padding: 20px; }
        .doc-name { font-size: 18px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; }
        
        /* Matching styles from image 0 */
        .doc-specialization { color: #166534; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .doc-department { color: #64748b; font-size: 13px; font-weight: 500; margin-bottom: 12px; }
        
        .doc-availability { color: var(--text-dark); font-size: 14px; font-weight: 500; margin-bottom: 15px; }

        .doc-actions { border-top: 1px solid var(--border-color); padding: 15px 20px; display: flex; gap: 10px; background: #fafafa; }
        .btn-edit { flex: 1; border: 1px solid var(--border-color); background: #fff; color: var(--text-dark); padding: 10px; border-radius: 10px; font-weight: 600; cursor: pointer; text-align: center; font-size: 13px; text-decoration: none; display: inline-block; }
        .btn-edit:hover { background: #f8fafc; }

        /* The Pop-up Form (Modal) */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #fff; padding: 32px; border-radius: 20px; width: 500px; max-width: 90%; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid var(--border-color); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-header h3 { font-size: 20px; font-weight: 800; }
        .close-modal { background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; }
        
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
        .input-style { width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-input); font-family: inherit; font-size: 14px; outline: none; }
        .input-style:focus { border-color: #a5b4fc; box-shadow: 0 0 0 4px rgba(165, 180, 252, 0.2); }

        .file-input { border: 1px dashed var(--border-color); padding: 10px; border-radius: var(--radius-input); background: #fcfcfc; cursor: pointer; display: block; width: 100%; }

        /* Submit button matching Image 0 Style, but with Indigo base */
        .btn-submit { background-color: var(--primary-teal); background-image: linear-gradient(to right, var(--primary-teal), #007785); color: #fff; width: 100%; border: none; padding: 14px; border-radius: 25px; font-weight: 700; font-size: 14px; cursor: pointer; margin-top: 10px; }
        
        .success-banner { padding: 10px; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px; text-align: center; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo">
            <svg style="width:24px;height:24px" viewBox="0 0 24 24"><path fill="currentColor" d="M10,2H14V6H18V10H22V14H18V18H14V22H10V18H6V14H2V10H6V6H10V2M11,4V8H7V10H4V14H7V16H11V20H13V16H17V14H20V10H17V8H13V4H11M12,10.5A1.5,1.5 0 1,1 10.5,12A1.5,1.5 0 0,1 12,10.5Z"/></svg>
            HARGEISA HOSPITAL
        </div>
        <nav>
            <a href="#" class="nav-item">Dashboard</a>
            <a href="#" class="nav-item active">
                <svg style="width:18px;height:18px" viewBox="0 0 24 24"><path fill="currentColor" d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,14C16.42,14 20,15.79 20,18V20H4V18C4,15.79 7.58,14 12,14Z"/></svg>
                Manage Doctors
            </a>
            <a href="#" class="nav-item">System Reports</a>
            <a href="#" class="nav-item">Appointments</a>
        </nav>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <h2>Hospital Management System</h2>
            <div style="display:flex; align-items:center; gap: 15px;">
                <span style="color: #64748b; font-size: 14px; font-weight: 600;">System Admin</span>
                <div class="avatar-circle">A</div>
            </div>
        </header>

        <div class="container">
            <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                <div class="success-banner">Doctor profile saved successfully.</div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h1>Manage Doctor Profiles</h1>
                    <p>Create and edit public profiles for doctors.</p>
                </div>
                <button class="btn-add" onclick="openAddModal()">
                    <span style="font-size:18px">+</span>
                    Create New
                </button>
            </div>

            <div class="doctor-grid">
                <?php if ($doctors_result && $doctors_result->num_rows > 0): ?>
                    <?php while($row = $doctors_result->fetch_assoc()): ?>
                        <div class="doc-card" id="doc-<?php echo $row['doctor_id']; ?>">
                            <div class="doc-image-container">
                                <img src="<?php echo !empty($row['profile_photo']) ? htmlspecialchars($row['profile_photo']) : 'images/default_doctor.png'; ?>" alt="<?php echo htmlspecialchars($row['full_name']); ?>" class="doc-image">
                            </div>
                            <div class="doc-info">
                                <p class="doc-specialization"><?php echo strtoupper(htmlspecialchars($row['specialization'])); ?></p>
                                <p class="doc-name"><?php echo htmlspecialchars($row['full_name']); ?></p>
                                <p class="doc-department">Department: <?php echo htmlspecialchars($row['department']); ?></p>
                                <p class="doc-availability">Available: <?php echo htmlspecialchars($row['availability']); ?></p>
                            </div>
                            <div class="doc-actions">
                                <button class="btn-edit" 
                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                                    Edit Profile
                                </button>
                                <a href="?delete=<?php echo $row['doctor_id']; ?>" class="btn-edit" style="color: #b91c1c; border-color: #fecaca" onclick="return confirm('Delete this profile permanently?')">Delete</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="grid-column: 1/-1; text-align: center; color: #64748b; padding: 40px; background: #fff; border-radius: 12px;">No doctor profiles found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Create New Profile</h3>
                <button class="close-modal" onclick="closeModal()">×</button>
            </div>
            
            <form id="doctorForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="doctor_id" name="doctor_id">
                <input type="hidden" id="current_image" name="current_image">

                <div class="form-group">
                    <label class="form-label" for="full_name">Legal Full Name (with Title)</label>
                    <input type="text" id="full_name" name="full_name" class="input-style" placeholder="e.g. Datin Dr. Angela Loo Voon Pei" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="profile_photo">Profile Photo</label>
                    <input type="file" id="profile_photo" name="profile_photo" class="file-input" accept="image/*">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="specialization">Main Specialization</label>
                        <input type="text" id="specialization" name="specialization" class="input-style" placeholder="e.g. OPHTHALMOLOGY" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="department">Department</label>
                        <input type="text" id="department" name="department" class="input-style" placeholder="e.g. Internal Medicine" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="input-style" placeholder="0123456789">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="input-style" placeholder="doctor@example.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="availability">Availability (Location/Hours)</label>
                    <input type="text" id="availability" name="availability" class="input-style" placeholder="e.g. Hargeisa Main (Mon 9AM-1PM)">
                </div>

                <button type="submit" name="save_doctor" class="btn-submit">
                    Save Profile
                </button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modalOverlay');
        const form = document.getElementById('doctorForm');
        const titleId = document.getElementById('modalTitle');

        function openAddModal() {
            // Reset form for fresh creation
            titleId.innerText = "Create New Profile";
            form.reset();
            document.getElementById('doctor_id').value = '';
            document.getElementById('current_image').value = '';
            modal.classList.add('active');
        }

        function openEditModal(doctorData) {
            titleId.innerText = "Edit Doctor Profile";
            form.reset(); // clear any previous validations

            // Fill form fields with existing data
            document.getElementById('doctor_id').value = doctorData.doctor_id;
            document.getElementById('full_name').value = doctorData.full_name;
            document.getElementById('specialization').value = doctorData.specialization;
            document.getElementById('department').value = doctorData.department;
            document.getElementById('phone').value = doctorData.phone;
            document.getElementById('email').value = doctorData.email;
            document.getElementById('availability').value = doctorData.availability;
            document.getElementById('current_image').value = doctorData.profile_photo;

            modal.classList.add('active');
        }

        function closeModal() {
            modal.classList.remove('active');
        }

        // Close when clicking outside content area
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
    </script>
</body>
</html>