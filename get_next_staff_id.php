<?php
// get_next_staff_id.php
include("db.php");

$dept = $_GET['department'] ?? '';

// Map user-friendly dropdown roles to their database equivalents and shorthand prefixes
$prefixes = [
    'doctor'           => 'DOC',
    'nurse'            => 'NRS',
    'operations admin' => 'ADM',
    'lab technician'   => 'LAB',
    'pharmacist'       => 'PHM',
    'receptionist'     => 'REC'


];

if (!array_key_exists($dept, $prefixes)) {
    echo json_encode(['success' => false, 'id' => '']);
    exit;
}

$prefix = "HGH-" . $prefixes[$dept] . "-";
$escaped_dept = mysqli_real_escape_string($conn, $dept);

// Count how many users already share this specific department role
$query = "SELECT COUNT(*) as total FROM users WHERE LOWER(role) = LOWER('$escaped_dept')";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

// Increment current total by 1 and pad with zeroes to keep a consistent length (e.g., 001)
$next_number = str_pad(($row['total'] + 1), 3, '0', STR_PAD_LEFT);
$generated_id = $prefix . $next_number;

echo json_encode(['success' => true, 'id' => $generated_id]);
exit;