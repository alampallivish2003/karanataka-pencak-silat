<?php
// bracket.php - Single Elimination Bracket View (fixed column name)

session_start();
include 'db_config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: index.php');
    exit();
}

$participants = [];
$tournament_id = 0;
$tournament_name = 'Unknown Tournament';

if (isset($_GET['tournament_id']) && is_numeric($_GET['tournament_id'])) {
    $tournament_id = (int)$_GET['tournament_id'];

    // Get tournament name (optional but nice)
    $t_check = $conn->query("SELECT name FROM tournaments WHERE id = $tournament_id");
    if ($row = $t_check->fetch_assoc()) {
        $tournament_name = $row['name'];
    }

    // Get participants - FIXED: use CONCAT(first_name, last_name) instead of p.name
    $sql = "
        SELECT e.id, 
               CONCAT(p.first_name, ' ', p.last_name) AS player_name, 
               u.district_name AS district 
        FROM entries e 
        LEFT JOIN players p ON e.player_id = p.id 
        LEFT JOIN users u ON p.district_head_id = u.id 
        WHERE e.tournament_id = ?
        ORDER BY RAND()";  // random order each time (same as shuffle below)

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tournament_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $participants[] = ($row['district'] ? $row['district'] . ' - ' : '') . $row['player_name'];
    }

    $stmt->close();

    // Shuffle again for fresh bracket on every refresh (optional - can remove if you want fixed order)
    shuffle($participants);

    // Generate simple single-elimination bracket
    function generateBracket($players) {
        $num = count($players);
        if ($num === 0) return [];

        // Add bye if odd number of players
        if ($num % 2 !== 0) {
            $players[] = 'Bye';
        }

        $bracket = [];
        for ($i = 0; $i < count($players); $i += 2) {
            $left  = $players[$i] ?? 'TBD';
            $right = $players[$i + 1] ?? 'Bye';
            $bracket[] = [$left, $right];
        }

        return $bracket;
    }

    $bracket = generateBracket($participants);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bracket - Tournament #<?= $tournament_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 40px 20px; background: #f8f9fa; }
        .match-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .vs { font-weight: bold; color: #6c757d; margin: 0 15px; }
        .bye { color: #6c757d; font-style: italic; }
        h2 { text-align: center; margin-bottom: 30px; }
        .refresh-note { text-align: center; color: #6c757d; margin-top: 30px; }
    </style>
</head>
<body>

<div class="container">
    <h2>Single Elimination Bracket<br>
        <small class="text-muted"><?= htmlspecialchars($tournament_name) ?> (ID: <?= $tournament_id ?>)</small>
    </h2>

    <?php if (empty($participants)): ?>
        <div class="alert alert-info text-center">
            No participants registered for this tournament yet.
        </div>
    <?php else: ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php foreach ($bracket as $index => $match): ?>
                    <div class="match-card d-flex align-items-center justify-content-between">
                        <div class="participant left flex-grow-1 text-end">
                            <?= htmlspecialchars($match[0]) === 'Bye' 
                                ? '<span class="bye">Bye</span>' 
                                : htmlspecialchars($match[0]) ?>
                        </div>
                        <div class="vs">VS</div>
                        <div class="participant right flex-grow-1">
                            <?= htmlspecialchars($match[1]) === 'Bye' 
                                ? '<span class="bye">Bye</span>' 
                                : htmlspecialchars($match[1]) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <p class="refresh-note">
            Refresh the page to generate a new random bracket.
        </p>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a href="admin.php" class="btn btn-secondary">Back to Admin Dashboard</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>