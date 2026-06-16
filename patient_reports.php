<?php
/**
 * HGH Patient Health Records Module
 * Synchronized with Hargeisa General Hospital Branding
 */

include("db.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback error containment if user session drops out completely
if (empty($_SESSION['user_id'])) {
    echo "<div style='text-align:center; padding:40px; color:#64748b;'>Invalid access session. Please log in again.</div>";
    return;
}

$user_id = $_SESSION['user_id'];

/* --- Secure Data Fetch Helper --- */
function fetch_patient_data($conn, $sql, $types, ...$params) {
    $stmt = $conn->prepare($sql);
    if(!$stmt) return [];
    if($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* --- 1. Identify Patient --- */
$p_res = fetch_patient_data($conn, "SELECT patient_id, full_name FROM patients WHERE user_id = ? LIMIT 1", "i", $user_id);

if (empty($p_res)) {
    echo "
    <div style='text-align:center; padding:60px; background:#fff; border-radius:24px; border:1px solid #edf2f7;'>
        <i class='fa-solid fa-user-slash' style='font-size:40px; color:#cbd5e1; margin-bottom:20px;'></i>
        <h3 style='font-family:\"Playfair Display\", serif; color:#052e16;'>Profile Not Found</h3>
        <p style='color:#64748b;'>Please complete your profile to view medical reports.</p>
        <a href='?page=patient_profile' style='margin-top:20px; display:inline-block; padding: 12px 24px; background:#052e16; color:#fff; text-decoration:none; border-radius:12px; font-weight:700;'>Complete Profile</a>
    </div>";
    return;
}

$patient_id = $p_res[0]['patient_id'];
$patient_name = $p_res[0]['full_name'];

/* --- 2. Fetch Records --- */
$diagnosis = fetch_patient_data($conn, "SELECT * FROM diagnosis WHERE patient_id=? ORDER BY diagnosis_date DESC", "i", $patient_id);
$prescriptions = fetch_patient_data($conn, "SELECT * FROM prescriptions WHERE patient_id=? ORDER BY created_at DESC", "i", $patient_id);

/* --- Synchronized Laboratory Query Integration --- */
$labs = fetch_patient_data($conn, "
    SELECT 
        t.test_id,
        t.test_type,
        t.status,
        t.created_at,
        COALESCE(t.result_text, t.status) AS test_result,
        COALESCE(t.result_notes, 'No clinical metrics recorded.') AS result_notes,
        COALESCE(t.result_file, '') AS result_file
    FROM lab_request_tests t
    INNER JOIN lab_requests r ON t.lab_request_id = r.lab_request_id
    WHERE r.patient_id = ?
    ORDER BY t.created_at DESC
", "i", $patient_id);

function formatDate($date) {
    return ($date && $date != '0000-00-00 00:00:00' && $date != '0000-00-00') ? date("M d, Y", strtotime($date)) : 'N/A';
}
?>

<style>
    :root {
        --g900: #052e16;
        --g600: #16a34a;
        --g400: #4ade80;
    }
    .rec-container { max-width: 1000px; margin: 0 auto; font-family: 'Plus Jakarta Sans', sans-serif; padding: 15px; box-sizing: border-box; }
    
    .report-header { margin-bottom: 35px; border-bottom: 1px solid #edf2f7; padding-bottom: 20px; }
    .report-header h1 { font-family: 'Playfair Display', serif; font-size: 32px; color: var(--g900); margin: 0; }
    .report-header p { color: #64748b; margin-top: 5px; font-size: 15px; }

    /* Stats Grid */
    .report-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
    .stat-pill { 
        background: #fff; padding: 25px; border-radius: 20px; border: 1px solid #edf2f7;
        display: flex; align-items: center; gap: 18px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
    }
    .stat-pill .icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    .stat-pill .val { display: block; font-size: 24px; font-weight: 800; color: var(--g900); line-height: 1; }
    .stat-pill .lab { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Sections */
    .section-title { font-size: 12px; font-weight: 800; color: var(--g600); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; }
    .section-title::after { content: ''; height: 1px; flex: 1; background: linear-gradient(to right, #edf2f7, transparent); }

    .record-card { background: #fff; border: 1px solid #edf2f7; border-radius: 20px; padding: 28px; margin-bottom: 20px; transition: 0.3s; cursor: pointer; position: relative; }
    .record-card:hover { border-color: var(--g400); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.04); }
    
    .record-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 10px; }
    .record-name { font-size: 18px; font-weight: 700; color: var(--g900); }
    .record-date { font-size: 13px; font-weight: 600; color: #94a3b8; background: #f8fafc; padding: 4px 12px; border-radius: 20px; white-space: nowrap; }

    .details-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; background: #f0fdf4; padding: 20px; border-radius: 16px; border: 1px solid #dcfce7; }
    .detail-item label { display: block; font-size: 10px; font-weight: 800; color: var(--g600); margin-bottom: 6px; text-transform: uppercase; }
    .detail-item p { margin: 0; font-size: 14px; font-weight: 600; color: var(--g900); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .badge { padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; display: inline-block; }
    .sev-mild { background: #dcfce7; color: #166534; }
    .sev-moderate { background: #fef9c3; color: #854d0e; }
    .sev-severe { background: #fee2e2; color: #991b1b; }

    .empty-state { text-align: center; padding: 50px; background: #f8fafc; border-radius: 20px; border: 2px dashed #e2e8f0; color: #94a3b8; margin-bottom: 30px; font-size: 14px; }

    .view-overlay-btn { margin-top: 12px; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: var(--g600); text-decoration: none; }
    .view-overlay-btn:hover { color: var(--g900); }

    /* ================= SYSTEM MODAL OVERLAYS ================= */
    .hgh-modal-backdrop {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background: rgba(5, 46, 22, 0.4); backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center; z-index: 99999;
        opacity: 0; pointer-events: none; transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 20px; box-sizing: border-box;
    }
    .hgh-modal-backdrop.is-active { opacity: 1; pointer-events: auto; }

    .hgh-modal-card {
        background: #fff; border-radius: 24px; width: 100%; max-width: 650px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); transform: scale(0.95);
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex; flex-direction: column; max-height: 90vh; overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .hgh-modal-backdrop.is-active .hgh-modal-card { transform: scale(1); }

    .hgh-modal-header {
        padding: 24px 28px; border-bottom: 1px solid #edf2f7;
        display: flex; justify-content: space-between; align-items: flex-start;
        background: #f8fafc;
    }
    .hgh-modal-header h3 { font-family: 'Playfair Display', serif; font-size: 22px; color: var(--g900); margin: 0; }
    .hgh-modal-header p { margin: 4px 0 0 0; font-size: 13px; color: #64748b; font-weight: 500; }
    
    .hgh-modal-close {
        background: #e2e8f0; border: none; width: 32px; height: 32px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; cursor: pointer;
        color: #475569; transition: all 0.2s;
    }
    .hgh-modal-close:hover { background: #fee2e2; color: #991b1b; }

    .hgh-modal-body { padding: 28px; overflow-y: auto; box-sizing: border-box; flex: 1; }

    .modal-detail-row { margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9; }
    .modal-detail-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
    .modal-detail-row label { display: block; font-size: 11px; font-weight: 800; color: var(--g600); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
    .modal-detail-row div { font-size: 15px; color: #1e293b; font-weight: 600; line-height: 1.6; word-break: break-word; }

    /* Attachment View Window */
    .hgh-attachment-frame {
        width: 100%; border: 1px solid #cbd5e1; border-radius: 12px;
        background: #f1f5f9; margin-top: 10px; height: 320px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.04);
    }
    .hgh-attachment-img {
        width: 100%; max-height: 350px; object-fit: contain; border-radius: 12px;
        border: 1px solid #cbd5e1; background: #27272a; margin-top: 10px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .report-stats, .details-grid { grid-template-columns: 1fr; gap: 15px; }
        .report-header h1 { font-size: 26px; }
        .hgh-modal-card { max-height: 95vh; width: 100%; }
        .hgh-attachment-frame { height: 240px; }
    }
</style>

<div class="rec-container">
    <div class="report-header">
        <h1>Medical Health Records</h1>
        <p>Official clinical history for <b style="color:var(--g900)"><?= htmlspecialchars($patient_name) ?></b></p>
    </div>

    <div class="report-stats">
        <div class="stat-pill">
            <div class="icon" style="background:#f0fdf4; color:var(--g600);"><i class="fa-solid fa-stethoscope"></i></div>
            <div>
                <span class="val"><?= count($diagnosis) ?></span>
                <span class="lab">Diagnoses</span>
            </div>
        </div>
        <div class="stat-pill">
            <div class="icon" style="background:#f0f9ff; color:#0ea5e9;"><i class="fa-solid fa-pills"></i></div>
            <div>
                <span class="val"><?= count($prescriptions) ?></span>
                <span class="lab">Prescriptions</span>
            </div>
        </div>
        <div class="stat-pill" style="background: var(--g900); border:none;">
            <div class="icon" style="background:rgba(255,255,255,0.1); color:var(--g400);"><i class="fa-solid fa-vial"></i></div>
            <div>
                <span class="val" style="color:#fff;"><?= count($labs) ?></span>
                <span class="lab" style="color:rgba(255,255,255,0.5);">Lab Results</span>
            </div>
        </div>
    </div>

    <div class="section-title">Clinical Diagnoses</div>
    <?php if(empty($diagnosis)): ?>
        <div class="empty-state">No clinical diagnoses found in your HGH records.</div>
    <?php else: foreach($diagnosis as $row): 
        $title = htmlspecialchars($row['diagnosis_text'] ?? $row['diagnosis'] ?? 'Clinical Diagnosis');
        $date = formatDate($row['diagnosis_date'] ?? $row['created_at'] ?? '');
        $severity = strtoupper($row['severity'] ?? 'Mild');
        $sev_class = strtolower($row['severity'] ?? 'mild');
        $treatment = htmlspecialchars($row['treatment_plan'] ?? $row['treatment'] ?? 'Follow physician advice');
        $follow_up = formatDate($row['follow_up_date'] ?? '');
        $doctor = htmlspecialchars($row['doctor_name'] ?? 'HGH Staff Physician');
        $diag_file = htmlspecialchars($row['attachment'] ?? $row['file_path'] ?? $row['diagnosis_file'] ?? '');
    ?>
        <div class="record-card" onclick="openDiagnosisModal('<?= addslashes($title) ?>', '<?= $date ?>', '<?= $severity ?>', '<?= $sev_class ?>', '<?= addslashes($treatment) ?>', '<?= $follow_up ?>', '<?= addslashes($doctor) ?>', '<?= $diag_file ?>')">
            <div class="record-top">
                <div class="record-name"><?= $title ?></div>
                <div class="record-date"><?= $date ?></div>
            </div>
            <div class="details-grid">
                <div class="detail-item">
                    <label>Severity Level</label>
                    <span class="badge sev-<?= $sev_class ?>"><?= $severity ?></span>
                </div>
                <div class="detail-item">
                    <label>Treatment Plan</label>
                    <p><?= $treatment ?></p>
                </div>
                <div class="detail-item">
                    <label>Next Follow-Up</label>
                    <p><?= $follow_up ?></p>
                </div>
            </div>
            <div class="view-overlay-btn">
                <i class="fa-solid fa-expand"></i> View Full Clinical Diagnosis Summary 
                <?php if(!empty($diag_file)): ?>&bull; <i class="fa-solid fa-paperclip" style="color:var(--g600);"></i> Has Attachment<?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>

    <div class="section-title" style="margin-top:50px;">Laboratory Findings</div>
    <?php if(empty($labs)): ?>
        <div class="empty-state">No laboratory results processed for this patient ID.</div>
    <?php else: foreach($labs as $row): 
        $test_type = htmlspecialchars($row['test_type'] ?? 'Diagnostic Test');
        $test_date = formatDate($row['created_at'] ?? '');
        $result_text = htmlspecialchars($row['test_result']);
        $notes = htmlspecialchars($row['result_notes']);
        $file_attachment = htmlspecialchars($row['result_file']);
    ?>
        <div class="record-card" style="border-left: 4px solid var(--g600);" onclick="openLabModal('<?= addslashes($test_type) ?>', '<?= $test_date ?>', '<?= addslashes($result_text) ?>', '<?= addslashes($notes) ?>', '<?= $file_attachment ?>')">
            <div class="record-top">
                <div class="record-name">
                    <i class="fa-solid fa-microscope" style="color:var(--g600); margin-right:10px;"></i>
                    <?= $test_type ?>
                </div>
                <div class="record-date"><?= $test_date ?></div>
            </div>
            <div style="background:#f8fafc; padding:15px; border-radius:12px;">
                <label style="font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">Result Status / Outcome</label>
                <p style="font-size:16px; font-weight:700; color:var(--g900); margin:5px 0 0 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    <?= $result_text ?>
                </p>
            </div>
            <div class="view-overlay-btn">
                <i class="fa-solid fa-expand"></i> View Full Comprehensive Lab Findings
                <?php if(!empty($file_attachment)): ?>&bull; <i class="fa-solid fa-paperclip" style="color:var(--g600);"></i> Scan Upload Connected<?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>

    <div class="section-title" style="margin-top:50px;">Digital Prescription Log</div>
    <?php if(empty($prescriptions)): ?>
        <div class="empty-state">No prescription history available.</div>
    <?php else: ?>
        <div class="record-card" style="padding:0; overflow:hidden; cursor:default;">
            <table style="width:100%; border-collapse: collapse;">
                <thead style="background:#f8fafc; border-bottom: 1px solid #edf2f7;">
                    <tr>
                        <th style="text-align:left; padding:18px 25px; font-size:11px; color:#94a3b8; text-transform:uppercase;">Issue Date</th>
                        <th style="text-align:left; padding:18px 25px; font-size:11px; color:#94a3b8; text-transform:uppercase;">Reference Code</th>
                        <th style="text-align:left; padding:18px 25px; font-size:11px; color:#94a3b8; text-transform:uppercase;">Medication Details</th>
                        <th style="text-align:right; padding:18px 25px; font-size:11px; color:#94a3b8; text-transform:uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($prescriptions as $row): 
                        $rx_date = formatDate($row['created_at'] ?? $row['prescription_date'] ?? '');
                        $rx_id = str_pad($row['prescription_id'] ?? $row['id'] ?? 0, 5, '0', STR_PAD_LEFT);
                        $med_name = htmlspecialchars($row['medication'] ?? $row['medicine_name'] ?? $row['medication_name'] ?? 'Prescribed Item');
                        $dosage = htmlspecialchars($row['dosage'] ?? 'As directed');
                        $instructions = htmlspecialchars($row['instructions'] ?? $row['frequency'] ?? 'Take as instructed by pharmacist');
                        $rx_file = htmlspecialchars($row['attachment'] ?? $row['scanned_rx'] ?? '');
                    ?>
                    <tr style="border-bottom:1px solid #edf2f7;">
                        <td style="padding:18px 25px; font-size:14px; font-weight:700; color:var(--g900); white-space:nowrap;"><?= $rx_date ?></td>
                        <td style="padding:18px 25px; font-size:13px; font-family:monospace; color:var(--g600); font-weight:700; white-space:nowrap;">HGH-RX-<?= $rx_id ?></td>
                        <td style="padding:18px 25px; font-size:14px; color:#334155;">
                            <strong><?= $med_name ?></strong>
                            <div style="font-size:12px; color:#64748b; margin-top:4px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= $dosage ?> &bull; <?= $instructions ?>
                            </div>
                        </td>
                        <td style="padding:18px 25px; text-align:right; white-space:nowrap;">
                            <button class="badge" style="background:#f0fdf4; color:var(--g600); border:1px solid #dcfce7; font-weight:700; cursor:pointer; padding:6px 14px; border-radius:8px;" 
                                    onclick="openRxModal('<?= $rx_date ?>', 'HGH-RX-<?= $rx_id ?>', '<?= addslashes($med_name) ?>', '<?= addslashes($dosage) ?>', '<?= addslashes($instructions) ?>', '<?= $rx_file ?>')">
                                <i class="fa-solid fa-eye"></i> View Full RX
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="hgh-modal-backdrop" id="hghUniversalModal" onclick="closeActiveModal(event)">
    <div class="hgh-modal-card">
        <div class="hgh-modal-header">
            <div>
                <h3 id="modalHeaderTitle">Medical Report Profile</h3>
                <p id="modalHeaderSubtitle">Hargeisa General Hospital Archive</p>
            </div>
            <button class="hgh-modal-close" onclick="forceCloseModal()">&times;</button>
        </div>
        <div class="hgh-modal-body" id="modalDynamicBody"></div>
    </div>
</div>

<script>
    const backdrop = document.getElementById('hghUniversalModal');
    const modalTitle = document.getElementById('modalHeaderTitle');
    const modalSubtitle = document.getElementById('modalHeaderSubtitle');
    const modalBody = document.getElementById('modalDynamicBody');

    function renderAttachmentTemplate(fileField) {
        if (!fileField || fileField.trim() === '') return '';
        
        const cleanFile = fileField.trim();
        const extension = cleanFile.split('.').pop().toLowerCase();
        const filePath = "uploads/" + cleanFile;

        let viewerMarkup = '';
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
            viewerMarkup = `<img src="${filePath}" class="hgh-attachment-img" alt="Uploaded Lab Scan Graphic" />`;
        } else if (extension === 'pdf') {
            viewerMarkup = `<iframe src="${filePath}#toolbar=0" class="hgh-attachment-frame"></iframe>`;
        } else {
            viewerMarkup = `
                <div style="padding:15px; background:#f1f5f9; border-radius:10px; border:1px dashed #cbd5e1; display:flex; align-items:center; justify-content:space-between; margin-top:10px;">
                    <span style="font-size:13px; font-weight:600; color:#475569;"><i class="fa-solid fa-file-medical"></i> Clinical File Digital Record (${extension.toUpperCase()})</span>
                    <a href="${filePath}" target="_blank" style="padding:6px 12px; background:var(--g900); color:#fff; text-decoration:none; border-radius:6px; font-size:12px; font-weight:700;">Open Document</a>
                </div>`;
        }

        return `
            <div class="modal-detail-row">
                <label><i class="fa-solid fa-paperclip"></i> Associated Clinical File Document Attachment</label>
                ${viewerMarkup}
                <div style="margin-top:10px; text-align:right;">
                    <a href="${filePath}" target="_blank" style="font-size:12px; font-weight:700; color:var(--g600); text-decoration:underline;">
                        <i class="fa-solid fa-up-right-from-square"></i> Open Attachment in New Window
                    </a>
                </div>
            </div>`;
    }

    function launchModalContent(title, subtitle, contentHtml) {
        modalTitle.innerText = title;
        modalSubtitle.innerText = subtitle;
        modalBody.innerHTML = contentHtml;
        backdrop.classList.add('is-active');
        document.body.style.overflow = 'hidden';
    }

    function forceCloseModal() {
        backdrop.classList.remove('is-active');
        document.body.style.overflow = '';
    }

    function closeActiveModal(e) {
        if (e.target === backdrop) {
            forceCloseModal();
        }
    }

    function openDiagnosisModal(title, date, severity, sevClass, treatment, followUp, doctor, fileField) {
        const html = `
            <div class="modal-detail-row">
                <label>Clinical Condition / Diagnosis</label>
                <div style="font-size: 17px; color: var(--g900);">${title}</div>
            </div>
            <div class="modal-detail-row">
                <label>Classification Status / Severity</label>
                <div><span class="badge sev-${sevClass}">${severity}</span></div>
            </div>
            <div class="modal-detail-row">
                <label>Prescribed Treatment / Clinical Action Plan</label>
                <div style="font-weight: 500; background: #fafafa; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0;">${treatment}</div>
            </div>
            <div class="modal-detail-row">
                <label>Scheduled Follow-Up Consultation</label>
                <div><i class="fa-solid fa-calendar-day" style="color:var(--g600); margin-right:6px;"></i> ${followUp}</div>
            </div>
            <div class="modal-detail-row">
                <label>Attending Medical Practitioner</label>
                <div style="color: #475569;"><i class="fa-solid fa-user-md"></i> ${doctor}</div>
            </div>
            ${renderAttachmentTemplate(fileField)}
        `;
        launchModalContent('Diagnosis Report Details', 'Diagnosed on ' + date, html);
    }

    function openLabModal(testType, testDate, resultText, notes, fileField) {
        const html = `
            <div class="modal-detail-row">
                <label>Ordered Laboratory Test Type</label>
                <div style="font-size: 17px; color: var(--g900);"><i class="fa-solid fa-flask" style="color:var(--g600);"></i> ${testType}</div>
            </div>
            <div class="modal-detail-row">
                <label>Diagnostic Findings Report Outcome</label>
                <div style="font-size: 16px; color: #16a34a; font-weight: 700; background: #f0fdf4; padding: 14px; border-radius: 12px; border: 1px solid #dcfce7;">${resultText}</div>
            </div>
            <div class="modal-detail-row">
                <label>Technician Notes & Observations</label>
                <div style="font-weight: 500; color:#475569; line-height:1.5;">${notes}</div>
            </div>
            ${renderAttachmentTemplate(fileField)}
        `;
        launchModalContent('Laboratory Diagnostic Report', 'Processed on ' + testDate, html);
    }

    function openRxModal(date, rxCode, medName, dosage, instructions, fileField) {
        const html = `
            <div class="modal-detail-row" style="text-align: center; background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #edf2f7; margin-bottom: 25px;">
                <label style="margin-bottom:4px;">Official Rx Tracking ID</label>
                <div style="font-family: monospace; font-size: 20px; color: var(--g600); font-weight: 800;">${rxCode}</div>
                <small style="color:#94a3b8; font-weight:600; display:block; margin-top:4px;">Issued on ${date}</small>
            </div>
            <div class="modal-detail-row">
                <label>Authorized Medication Name</label>
                <div style="font-size: 18px; color: var(--g900);"><i class="fa-solid fa-pills" style="color:#0ea5e9;"></i> ${medName}</div>
            </div>
            <div class="modal-detail-row">
                <label>Required Clinical Dosage</label>
                <div style="font-size:15px; color:#1e293b;">${dosage}</div>
            </div>
            <div class="modal-detail-row">
                <label>Pharmacist Intake Instructions</label>
                <div style="font-weight: 500; color: #475569; line-height: 1.5; background: #f0f9ff; padding: 12px; border-radius: 10px; border: 1px solid #e0f2fe;">${instructions}</div>
            </div>
            <div class="modal-detail-row">
                <label>Signature Status</label>
                <div style="color: var(--g600); display:flex; align-items:center; gap:6px; font-size:14px;"><i class="fa-solid fa-circle-check"></i> Formally Verified & Cryptographically Signed by HGH Administration</div>
            </div>
            ${renderAttachmentTemplate(fileField)}
        `;
        launchModalContent('Digital Prescription Receipt', 'Hargeisa General Hospital Pharmacy Integration', html);
    }

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && backdrop.classList.contains('is-active')) {
            forceCloseModal();
        }
    });
</script>