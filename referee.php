<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'referee') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Update / insert score
if (isset($_POST['update_score'])) {
    $event_id  = (int)$_POST['event_id'];
    $player_id = (int)$_POST['player_id'];
    $score     = (int)$_POST['score'];

    $check = $conn->query("SELECT id FROM scores WHERE event_id=$event_id AND player_id=$player_id");
    if ($check->num_rows > 0) {
        $conn->query("UPDATE scores SET score=$score, updated_by=$user_id WHERE event_id=$event_id AND player_id=$player_id");
    } else {
        $conn->query("INSERT INTO scores (event_id, player_id, score, updated_by) VALUES ($event_id, $player_id, $score, $user_id)");
    }
}

$events = $conn->query("SELECT * FROM events ORDER BY date DESC");
$participants = $conn->query("SELECT ep.*, e.name AS event_name, p.name AS player_name 
                              FROM event_participants ep 
                              LEFT JOIN events e ON ep.event_id = e.id 
                              LEFT JOIN players p ON ep.player_id = p.id");
$scores = $conn->query("SELECT s.*, e.name AS event_name, p.name AS player_name 
                        FROM scores s 
                        LEFT JOIN events e ON s.event_id = e.id 
                        LEFT JOIN players p ON s.player_id = p.id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referee - Sports Event Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { padding-top: 80px; background: #f8f9fa; }
        section { margin-bottom: 3rem; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4">

    <h2>Referee Dashboard</h2>

    <section id="scores">
        <h3>Enter / Update Scores</h3>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr><th>Event</th><th>Player</th><th>Current Score</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php 
                    $participants->data_seek(0);
                    while($row = $participants->fetch_assoc()): 
                        $eid = $row['event_id'];
                        $pid = $row['player_id'];
                        $curr_score = 0;
                        $scores->data_seek(0);
                        while($s = $scores->fetch_assoc()) {
                            if ($s['event_id'] == $eid && $s['player_id'] == $pid) {
                                $curr_score = $s['score'];
                                break;
                            }
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['event_name']) ?></td>
                        <td><?= htmlspecialchars($row['player_name']) ?></td>
                        <td><strong><?= $curr_score ?></strong></td>
                        <td>
                            <form method="POST" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="update_score" value="1">
                                <input type="hidden" name="event_id" value="<?= $eid ?>">
                                <input type="hidden" name="player_id" value="<?= $pid ?>">
                                <input type="number" name="score" class="form-control" style="width:120px;" min="0" value="<?= $curr_score ?>" required>
                                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="events">
        <h3>Events List</h3>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr><th>Name</th><th>Date</th><th>Location</th></tr>
                </thead>
                <tbody>
                    <?php while($e = $events->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['name']) ?></td>
                        <td><?= $e['date'] ?></td>
                        <td><?= htmlspecialchars($e['location']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>