<?php
// fetch_player_by_name.php - improved version with error handling

header('Content-Type: application/json');

// Include database config
if (!file_exists('db_config.php')) {
    echo json_encode(['found' => false, 'error' => 'db_config.php not found']);
    exit;
}

include 'db_config.php';

// Check connection
if ($conn->connect_error) {
    echo json_encode(['found' => false, 'error' => 'Database connection failed']);
    exit;
}

$name = trim($_POST['name'] ?? '');
if (empty($name)) {
    echo json_encode(['found' => false]);
    exit;
}

// Split name (first + last)
$parts = explode(' ', $name, 2);
$first = '%' . $conn->real_escape_string($parts[0]) . '%';
$last  = isset($parts[1]) ? '%' . $conn->real_escape_string($parts[1]) . '%' : '%';

$stmt = $conn->prepare("
    SELECT id, unique_id 
    FROM players 
    WHERE first_name LIKE ? AND last_name LIKE ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode(['found' => false, 'error' => $conn->error]);
    exit;
}

$stmt->bind_param("ss", $first, $last);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'found' => true,
        'id' => $row['id'],
        'unique_id' => $row['unique_id']
    ]);
} else {
    echo json_encode(['found' => false]);
}

$stmt->close();
$conn->close();
?>