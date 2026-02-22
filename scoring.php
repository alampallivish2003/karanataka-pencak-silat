<?php
// scoring.php
session_start();
include 'db_config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: index.php');
    exit();
}

// Handle score/result update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_score'])) {
    $entry_id = (int)$_POST['entry_id'];
    $score    = (int)$_POST['score'];
    $result   = $conn->real_escape_string(trim($_POST['result'] ?? ''));

    $sql = "INSERT INTO results (entry_id, score, result) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE score = ?, result = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisii", $entry_id, $score, $result, $score, $result);
    
    if (!$stmt->execute()) {
        $error = "Error saving result: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch entries + current scores/results
$entries = $conn->query("
    SELECT 
        e.id,
        t.name              AS tournament_name,
        CONCAT(p.first_name, ' ', p.last_name) AS player_name,
        p.unique_id,
        e.sub_event,
        r.score,
        r.result
    FROM entries e
    LEFT JOIN tournaments t ON e.tournament_id = t.id
    LEFT JOIN players     p ON e.player_id     = p.id
    LEFT JOIN results     r ON e.id            = r.entry_id
    ORDER BY t.start_date DESC, e.sub_event, p.last_name
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scoring & Results - KSPSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'navbar.php'; ?>

<div class="container mt-5 pt-4">

    <h2 class="mb-4">Scoring & Results Management</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($entries->num_rows === 0): ?>
        <div class="alert alert-info">No entries found in any tournament yet.</div>
    <?php else: ?>

    <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm">
            <thead class="table-dark">
                <tr>
                    <th>Tournament</th>
                    <th>Player</th>
                    <th>Unique ID</th>
                    <th>Sub-Event</th>
                    <th>Score</th>
                    <th>Result / Position</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $entries->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['tournament_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['player_name'] ?? '—') ?></td>
                    <td><code><?= htmlspecialchars($row['unique_id'] ?? '—') ?></code></td>
                    <td><?= htmlspecialchars($row['sub_event'] ?? '—') ?></td>
                    <td class="text-center fw-bold"><?= $row['score'] ?? '—' ?></td>
                    <td><?= htmlspecialchars($row['result'] ?? '—') ?></td>
                    <td>
                        <form method="POST" class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="hidden" name="update_score" value="1">
                            <input type="hidden" name="entry_id" value="<?= $row['id'] ?>">
                            
                            <input type="number" name="score" 
                                   value="<?= $row['score'] ?? 0 ?>" 
                                   class="form-control form-control-sm" style="width:90px;" 
                                   min="0" step="1">

                            <input type="text" name="result" 
                                   value="<?= htmlspecialchars($row['result'] ?? '') ?>" 
                                   class="form-control form-control-sm" 
                                   placeholder="1st / Gold / Win / KO ..." 
                                   style="width:160px;">

                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>