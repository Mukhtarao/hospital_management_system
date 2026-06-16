<?php
include("db.php");

$message = "";

if (isset($_POST['submit_booking'])) {
    // Sanitize Inputs based on your DB columns
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $full_name  = $first_name . " " . $last_name;
    $dob        = mysqli_real_escape_string($conn, $_POST['dob']);
    $gender     = mysqli_real_escape_string($conn, $_POST['gender']);
    $blood      = mysqli_real_escape_string($conn, $_POST['blood_group']);
    $phone      = mysqli_real_escape_string($conn, $_POST['phone']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $address    = mysqli_real_escape_string($conn, $_POST['address']);
    $e_name     = mysqli_real_escape_string($conn, $_POST['emergency_name']);
    $e_phone    = mysqli_real_escape_string($conn, $_POST['emergency_phone']);
    $history    = mysqli_real_escape_string($conn, $_POST['medical_history']);

    // Calculate Age (Required by your DB column #6)
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;

    mysqli_begin_transaction($conn);

    try {
        // 1. Insert into 'patients' table using your exact column names
        $sql_patient = "INSERT INTO patients (first_name, last_name, full_name, date_of_birth, age, gender, blood_group, phone, email, address, emergency_contact_name, emergency_contact_phone, medical_history) 
                        VALUES ('$first_name', '$last_name', '$full_name', '$dob', '$age', '$gender', '$blood', '$phone', '$email', '$address', '$e_name', '$e_phone', '$history')";
        
        mysqli_query($conn, $sql_patient);
        $patient_id = mysqli_insert_id($conn);

        // 2. Insert into 'appointments' table for Reception verification
        // Assumes you have an appointments table with a 'status' column
        $sql_appointment = "INSERT INTO appointments (patient_id, status) VALUES ('$patient_id', 'pending')";
        mysqli_query($conn, $sql_appointment);

        mysqli_commit($conn);
        $message = "<div class='alert success'>Booking sent! Reception will verify your appointment shortly.</div>";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "<div class='alert error'>Database Error: Could not complete registration.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Booking | Hargeisa Group Hospital</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #052e16; --emerald: #16a34a; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f0fdf4; padding: 40px; margin: 0; }
        .container { max-width: 800px; margin: auto; background: white; padding: 40px; border-radius: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        h2 { font-family: 'Playfair Display'; color: var(--primary); font-size: 32px; margin-bottom: 20px; text-align: center; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .span-2 { grid-column: span 2; }
        label { display: block; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 8px; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 15px; box-sizing: border-box; }
        .emergency-section { background: #f8fafc; padding: 20px; border-radius: 15px; border-left: 5px solid var(--emerald); margin: 10px 0; }
        .btn { background: var(--primary); color: white; border: none; padding: 18px; width: 100%; border-radius: 15px; font-weight: 700; cursor: pointer; transition: 0.3s; margin-top: 20px; }
        .btn:hover { background: var(--emerald); transform: translateY(-2px); }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<div class="container">
    <h2>Book Your Appointment</h2>
    
    <?php echo $message; ?>

    <form method="POST">
        <div class="form-grid">
            <div>
                <label>First Name</label>
                <input type="text" name="first_name" placeholder="John" required>
            </div>
            <div>
                <label>Last Name</label>
                <input type="text" name="last_name" placeholder="Doe" required>
            </div>
            <div>
                <label>Date of Birth</label>
                <input type="date" name="dob" required>
            </div>
            <div>
                <label>Gender</label>
                <select name="gender" required>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
            <div>
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="+252..." required>
            </div>
            <div>
                <label>Blood Group</label>
                <select name="blood_group">
                    <option value="A+">A+</option>
                    <option value="B+">B+</option>
                    <option value="O+">O+</option>
                    <option value="AB+">AB+</option>
                    <option value="Unknown">Unknown</option>
                </select>
            </div>
            <div class="span-2">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="email@example.com">
            </div>
            <div class="span-2">
                <label>Residential Address</label>
                <textarea name="address" rows="2"></textarea>
            </div>

            <div class="span-2 emergency-section">
                <div class="form-grid">
                    <div>
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_name">
                    </div>
                    <div>
                        <label>Emergency Contact Phone</label>
                        <input type="text" name="emergency_phone">
                    </div>
                </div>
            </div>

            <div class="span-2">
                <label>Medical History / Known Allergies</label>
                <textarea name="medical_history" rows="3"></textarea>
            </div>
        </div>

        <button type="submit" name="submit_booking" class="btn">Send Appointment to Reception</button>
    </form>
</div>

</body>
</html>