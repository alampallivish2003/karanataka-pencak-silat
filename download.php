<?php
// download.php - Improved version: real CSV with comma separator

include 'db_config.php';

if (!isset($_GET['tournament_id']) || !is_numeric($_GET['tournament_id'])) {
    die("Invalid tournament ID.");
}

$tournament_id = (int)$_GET['tournament_id'];

// Optional: check tournament exists
$check = $conn->query("SELECT name FROM tournaments WHERE id = $tournament_id");
if ($check->num_rows === 0) {
    die("Tournament not found.");
}

// Query with correct column names
$sql = "
    SELECT 
        CONCAT(p.first_name, ' ', p.last_name) AS player_name,
        TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age,
        u.district_name AS district,
        e.sub_event AS event_name,
        e.category,
        e.weight
    FROM entries e
    LEFT JOIN players p ON e.player_id = p.id
    LEFT JOIN users u ON p.district_head_id = u.id
    WHERE e.tournament_id = ?
    ORDER BY age, player_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download (Excel opens this correctly)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="participants_tournament_' . $tournament_id . '.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM — helps Excel recognize encoding and split columns correctly
echo "\xEF\xBB\xBF";

// Column headers
echo "Player Name,Age,District,Event Name,Category,Weight (kg)\n";

// Output rows with proper CSV escaping
while ($row = $result->fetch_assoc()) {
    $escaped = array_map(function($value) {
        // Escape double quotes and wrap in quotes if needed
        $value = str_replace('"', '""', $value);
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . $value . '"';
        }
        return $value;
    }, $row);

    echo implode(',', $escaped) . "\n";
}

$stmt->close();
$conn->close();
exit;