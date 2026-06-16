<?php
include("db.php");
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM prescription_items WHERE prescription_id = $id";
    $result = $conn->query($sql);

    while($item = $result->fetch_assoc()) {
        echo "<div class='med-item'>";
        echo "<strong>Medication:</strong> " . htmlspecialchars($item['medication_name']) . "<br>";
        echo "<strong>Dosage:</strong> " . htmlspecialchars($item['dosage']) . " | ";
        echo "<strong>Freq:</strong> " . htmlspecialchars($item['frequency']) . "<br>";
        echo "<strong>Duration:</strong> " . htmlspecialchars($item['duration']) . "<br>";
        echo "<div class='instruction-box'><strong>Doctor's Notes:</strong> " . htmlspecialchars($item['instructions'] ?? 'None') . "</div>";
        echo "</div>";
    }
}
?>