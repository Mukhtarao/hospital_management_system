<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include("db.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: index.php"); 
    exit(); 
}

$report_data = [];
$report_title = "";
$type = $_POST['report_type'] ?? '';
$date_range = $_POST['date_range'] ?? 'today';
$table_headers = [];

function dateFilter($column, $range) {
    switch ($range) {
        case "today":
            return "DATE($column) = CURDATE()";
        case "7days":
            return "$column >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        case "30days":
            return "$column >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        case "all":
            return "1";
        default:
            return "1";
    }
}

if (isset($_POST['generate']) && !empty($type)) {

    switch ($type) {

        case 'users':
            $report_title = "User Access Directory";
            $table_headers = ['User ID', 'Full Name', 'Username', 'Email', 'Role', 'Status', 'Created At'];
            $condition = dateFilter("u.created_at", $date_range);

            $query = $conn->query("
                SELECT 
                    u.user_id,
                    u.full_name,
                    u.username,
                    u.email,
                    u.role,
                    u.status,
                    u.created_at
                FROM users u
                WHERE $condition
                ORDER BY u.user_id DESC
            ");
            break;

        case 'patients':
            $report_title = "Patient Census Directory";
            $table_headers = ['Patient ID', 'Full Name', 'Gender', 'Age', 'Phone', 'Email', 'Address', 'Created At'];
            $condition = dateFilter("p.created_at", $date_range);

            $query = $conn->query("
                SELECT 
                    p.patient_id,
                    p.full_name,
                    p.gender,
                    p.age,
                    p.phone,
                    p.email,
                    p.address,
                    p.created_at
                FROM patients p
                WHERE $condition
                ORDER BY p.patient_id DESC
            ");
            break;

        case 'inventory':
            $report_title = "Pharmacy & Inventory Log";
            $table_headers = ['Medicine ID', 'Medicine Name', 'Category', 'Quantity', 'Reorder Level', 'Unit Price', 'Expiry Date'];

            $query = $conn->query("
                SELECT 
                    m.medicine_id,
                    m.medicine_name,
                    m.category,
                    m.quantity,
                    m.reorder_level,
                    m.unit_price,
                    m.expiry_date
                FROM medicines m
                ORDER BY m.medicine_id DESC
            ");
            break;

        case 'staff':
            $report_title = "Human Resources Staff Roster";
            $table_headers = ['User ID', 'Full Name', 'Username', 'Email', 'Role', 'Status', 'Created At'];
            $condition = dateFilter("u.created_at", $date_range);

            $query = $conn->query("
                SELECT 
                    u.user_id,
                    u.full_name,
                    u.username,
                    u.email,
                    u.role,
                    u.status,
                    u.created_at
                FROM users u
                WHERE u.role != 'patient'
                AND $condition
                ORDER BY u.role ASC, u.full_name ASC
            ");
            break;

        case 'bills':
            $report_title = "Financial Invoicing Ledger";
            $table_headers = ['Invoice Code', 'Patient', 'Visit ID', 'Consultation', 'Lab Tests', 'Medications', 'Tax', 'Grand Total', 'Paid', 'Balance', 'Status', 'Created At'];
            $condition = dateFilter("i.created_at", $date_range);

            $query = $conn->query("
                SELECT 
                    i.invoice_code,
                    COALESCE(p.full_name, CONCAT('Patient #', i.patient_id)) AS patient_name,
                    i.visit_id,
                    i.consultation_total,
                    i.lab_tests_total,
                    i.medications_total,
                    i.tax_amount,
                    i.grand_total,
                    i.amount_paid,
                    i.balance_due,
                    i.status,
                    i.created_at
                FROM invoices i
                LEFT JOIN patients p ON i.patient_id = p.patient_id
                WHERE $condition
                ORDER BY i.invoice_id DESC
            ");
            break;

        case 'appointments':
            $report_title = "Clinical Appointment Logs";
            $table_headers = ['Appointment ID', 'Patient', 'Doctor', 'Date', 'Time', 'Reason', 'Status', 'Created At'];
            $condition = dateFilter("a.created_at", $date_range);

            $query = $conn->query("
                SELECT 
                    a.appointment_id,
                    COALESCE(p.full_name, CONCAT('Patient #', a.patient_id)) AS patient_name,
                    COALESCE(u.full_name, CONCAT('Doctor #', a.doctor_id)) AS doctor_name,
                    a.appointment_date,
                    a.appointment_time,
                    a.reason,
                    a.status,
                    a.created_at
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.patient_id
                LEFT JOIN users u ON a.doctor_id = u.user_id
                WHERE $condition
                ORDER BY a.appointment_date DESC, a.appointment_time DESC
            ");
            break;

        case 'lab_reports':
            $report_title = "Pathology & Lab Diagnostics Report";
            $table_headers = ['Request ID', 'Visit ID', 'Patient', 'Doctor', 'Test Type', 'Test Cost', 'Test Status', 'Result', 'Requested At', 'Completed At'];
            $condition = dateFilter("lr.requested_at", $date_range);

            $query = $conn->query("
                SELECT 
                    lr.lab_request_id,
                    lr.visit_id,
                    COALESCE(p.full_name, lr.patient_name) AS patient_name,
                    COALESCE(u.full_name, CONCAT('Doctor #', lr.doctor_id)) AS doctor_name,
                    lrt.test_type,
                    lrt.test_cost,
                    lrt.status,
                    lrt.result_text,
                    lr.requested_at,
                    lrt.completed_at
                FROM lab_requests lr
                LEFT JOIN lab_request_tests lrt ON lr.lab_request_id = lrt.lab_request_id
                LEFT JOIN patients p ON lr.patient_id = p.patient_id
                LEFT JOIN users u ON lr.doctor_id = u.user_id
                WHERE $condition
                ORDER BY lr.lab_request_id DESC
            ");
            break;

        default:
            $query = false;
    }

    if ($query && $query->num_rows > 0) {
        while ($row = $query->fetch_assoc()) {
            $report_data[] = $row;
        }
    }
}
?>

<style>
    :root {
        --g900: #052e16; 
        --g600: #16a34a; 
        --g100: #f0fdf4;
        --border: #e2e8f0;
    }

    .report-wrapper { animation: fadeIn 0.5s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .page-header h2 { font-family: 'Playfair Display', serif; color: var(--g900); font-size: 28px; margin-bottom: 5px; }
    .page-header p { color: #64748b; font-size: 14px; margin-bottom: 30px; }

    .filter-card {
        background: #fff; padding: 25px; border-radius: 20px;
        border: 1px solid var(--border); display: flex; gap: 15px;
        align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        margin-bottom: 30px;
    }

    .filter-card select {
        padding: 12px 15px; border-radius: 12px; border: 1px solid var(--border);
        background: #fdfdfd; font-weight: 600; color: var(--g900);
        outline: none; flex: 1; transition: 0.3s; font-family: inherit;
    }

    .filter-card select:focus { border-color: var(--g600); box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1); }

    .btn-generate {
        background: var(--g900); color: white; border: none;
        padding: 12px 30px; border-radius: 12px; font-weight: 700;
        cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; font-family: inherit;
    }

    .btn-generate:hover { background: var(--g600); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(22, 163, 74, 0.2); }

    .report-result {
        background: #fff; border-radius: 24px; border: 1px solid var(--border);
        overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.02);
    }

    .report-toolbar {
        padding: 20px 30px; border-bottom: 1px solid var(--border);
        display: flex; justify-content: space-between; align-items: center;
        background: var(--g100);
    }

    .btn-export {
        background: #fff; border: 1px solid var(--border);
        padding: 10px 18px; border-radius: 10px; font-weight: 700;
        font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 8px; color: var(--g900);
    }

    .saas-table { width: 100%; border-collapse: collapse; }
    .saas-table th {
        background: #f8fafc; padding: 18px 25px; text-align: left;
        font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;
        color: #64748b; border-bottom: 1px solid var(--border);
    }
    .saas-table td { padding: 16px 25px; font-size: 14px; border-bottom: 1px solid #f1f5f9; color: var(--g900); font-weight: 500; }

    .badge-status {
        padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
        background: var(--g100); color: var(--g600); text-transform: uppercase;
    }

    #exportMenu {
        display: none; position: absolute; right: 0; top: 50px;
        background: #fff; border-radius: 12px; border: 1px solid var(--border);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 100; min-width: 180px;
    }
    #exportMenu button {
        width: 100%; padding: 12px 15px; border: none; background: none;
        text-align: left; font-weight: 600; cursor: pointer; font-size: 13px; color: var(--g900);
    }
    #exportMenu button:hover { background: var(--g100); color: var(--g600); }
</style>

<div class="report-wrapper">
    <div class="page-header">
        <h2>System Intelligence</h2>
        <p>Audit system records and export data for institutional compliance.</p>
    </div>

    <div class="filter-card">
        <form method="POST" style="display:contents;">
            <select name="report_type" required>
                <option value="" disabled selected>📁 Select Report Category</option>
                <option value="users" <?= $type == 'users' ? 'selected' : '' ?>>User Access Directory</option>
                <option value="patients" <?= $type == 'patients' ? 'selected' : '' ?>>Patient Census</option>
                <option value="inventory" <?= $type == 'inventory' ? 'selected' : '' ?>>Pharmacy & Inventory</option>
                <option value="staff" <?= $type == 'staff' ? 'selected' : '' ?>>Human Resources (Staff Roster)</option>
                <option value="bills" <?= $type == 'bills' ? 'selected' : '' ?>>Financial Invoices & Billing</option>
                <option value="appointments" <?= $type == 'appointments' ? 'selected' : '' ?>>Clinical Schedules</option>
                <option value="lab_reports" <?= $type == 'lab_reports' ? 'selected' : '' ?>>Pathology & Lab Reports</option>
            </select>

            <select name="date_range">
                <option value="today" <?= $date_range == 'today' ? 'selected' : '' ?>>Period: Today</option>
                <option value="7days" <?= $date_range == '7days' ? 'selected' : '' ?>>Period: Last 7 Days</option>
                <option value="30days" <?= $date_range == '30days' ? 'selected' : '' ?>>Period: Last 30 Days</option>
                <option value="all" <?= $date_range == 'all' ? 'selected' : '' ?>>Period: Full History</option>
            </select>

            <button type="submit" name="generate" class="btn-generate">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate Metrics
            </button>
        </form>
    </div>

    <?php if (!empty($type)): ?>
    <div class="report-result">
        <div class="report-toolbar">
            <div>
                <h3 style="margin:0; font-family:'Playfair Display', serif; color:var(--g900);"><?= htmlspecialchars($report_title) ?></h3>
                <small style="color:var(--g600); font-weight:600;">System Generated • <?= date("F j, Y") ?></small>
            </div>

            <div style="position: relative;">
                <button class="btn-export" onclick="toggleExport(event)">
                    <i class="fa-solid fa-file-export"></i> Export Data <i class="fa-solid fa-chevron-down" style="font-size:10px;"></i>
                </button>
                <div id="exportMenu">
                    <button onclick="downloadReport('excel')"><i class="fa-solid fa-file-excel" style="color:#1d6f42;"></i> Excel Spreadsheet</button>
                    <button onclick="downloadReport('pdf')"><i class="fa-solid fa-file-pdf" style="color:#e11d48;"></i> PDF Document</button>
                </div>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="saas-table">
                <thead>
                    <tr>
                        <?php foreach($table_headers as $head_title): ?>
                            <th><?= htmlspecialchars($head_title) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($report_data)): ?>
                        <?php foreach($report_data as $row): ?>
                            <tr>
                                <?php foreach($row as $key => $val): ?>
                                    <td>
                                        <?php if(in_array(strtolower($key), ['role', 'status', 'category'])): ?>
                                            <span class="badge-status"><?= htmlspecialchars($val ?? '') ?></span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($val ?? '') ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= max(count($table_headers), 1) ?>" style="text-align:center; padding:80px; color:#94a3b8;">
                                <i class="fa-solid fa-folder-open" style="font-size:40px; margin-bottom:15px; display:block; opacity:0.3;"></i>
                                No operational metrics tracked in the selected timeframe.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<form id="downloadForm" method="POST" action="export_report.php" target="_blank">
    <input type="hidden" name="report_type" id="dl_type">
    <input type="hidden" name="date_range" id="dl_range">
    <input type="hidden" name="format" id="dl_format">
</form>

<script>
function toggleExport(e){
    e.stopPropagation();
    let menu = document.getElementById("exportMenu");
    menu.style.display = (menu.style.display === "block") ? "none" : "block";
}

window.onclick = function(event) {
    if (!event.target.closest('.btn-export')) {
        document.getElementById("exportMenu").style.display = "none";
    }
}

function downloadReport(format){
    document.getElementById("dl_type").value = "<?= htmlspecialchars($type) ?>";
    document.getElementById("dl_range").value = "<?= htmlspecialchars($date_range) ?>";
    document.getElementById("dl_format").value = format;
    document.getElementById("downloadForm").submit();
}
</script>