<?php
// district_head.php - PROFESSIONAL UI + Tournament Status (Upcoming/Ongoing/Completed)
session_start();
include 'db_config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'district_head') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;

// Get district name
$stmt = $conn->prepare("SELECT district_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$district_name = $result->fetch_assoc()['district_name'] ?? 'Unknown';
$stmt->close();

// Messages
$player_success = $_SESSION['player_success'] ?? '';
$player_error   = $_SESSION['player_error']   ?? '';
$entry_success  = $_SESSION['entry_success']  ?? '';
$entry_error    = $_SESSION['entry_error']    ?? '';

unset($_SESSION['player_success'], $_SESSION['player_error']);
unset($_SESSION['entry_success'], $_SESSION['entry_error']);

// REGISTER NEW PLAYER
if (isset($_POST['register_player'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $aadhar     = trim($_POST['aadhar_id'] ?? '');
    $gender     = $_POST['gender'] ?? '';
    $guardian   = trim($_POST['guardian'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $dob        = $_POST['dob'] ?? '';
    $ipsf_id    = trim($_POST['ipsf_id'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($aadhar) || empty($gender) ||
        empty($guardian) || empty($phone) || empty($dob) || empty($ipsf_id)) {
        $_SESSION['player_error'] = "All fields are required.";
    } else {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            $_SESSION['player_error'] = "Failed to create uploads folder.";
        } else {
            $image_path = null;
            if (!empty($_FILES['player_image']['name']) && $_FILES['player_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['player_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($ext, $allowed)) {
                    $_SESSION['player_error'] = "Only JPG, JPEG, PNG & GIF allowed.";
                } elseif ($_FILES['player_image']['size'] > 5 * 1024 * 1024) {
                    $_SESSION['player_error'] = "Image size must be less than 5MB.";
                } else {
                    $image_name = 'player_' . time() . '_' . uniqid() . '.' . $ext;
                    $image_path = 'uploads/' . $image_name;
                    $full_path  = $uploadDir . $image_name;
                    if (!move_uploaded_file($_FILES['player_image']['tmp_name'], $full_path)) {
                        $_SESSION['player_error'] = "Failed to upload image.";
                    }
                }
            }

            if (!isset($_SESSION['player_error'])) {
                $dist_code = strtoupper(substr($district_name, 0, 3) ?: 'UNK');
                $serial_result = $conn->query("SELECT COUNT(*) as cnt FROM players WHERE district_head_id = $user_id");
                $serial = ($serial_result->fetch_assoc()['cnt'] ?? 0) + 1;
                $unique_id = "kspsa-$dist_code-" . str_pad($serial, 3, '0', STR_PAD_LEFT);

                $sql = "INSERT INTO players
                        (unique_id, first_name, last_name, aadhar_id, gender, guardian, district_name, phone, dob, ipsf_id, image, district_head_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssssssssssi",
                        $unique_id, $first_name, $last_name, $aadhar, $gender, $guardian,
                        $district_name, $phone, $dob, $ipsf_id, $image_path, $user_id
                    );
                    if ($stmt->execute()) {
                        $_SESSION['player_success'] = "Player <strong>$first_name $last_name</strong> registered!<br>Unique ID: <code>$unique_id</code>";
                    } else {
                        $_SESSION['player_error'] = "Database error: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['player_error'] = "Prepare failed: " . $conn->error;
                }
            }
        }
    }

    header("Location: district_head.php");
    exit();
}

// EDIT PLAYER
if (isset($_POST['edit_player'])) {
    $player_id = (int)$_POST['player_id'];
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $aadhar     = trim($_POST['aadhar_id'] ?? '');
    $gender     = $_POST['gender'] ?? '';
    $guardian   = trim($_POST['guardian'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $dob        = $_POST['dob'] ?? '';
    $ipsf_id    = trim($_POST['ipsf_id'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($aadhar) || empty($gender) ||
        empty($guardian) || empty($phone) || empty($dob) || empty($ipsf_id)) {
        $_SESSION['player_error'] = "All fields are required.";
    } else {
        $image_path = null;
        if (!empty($_FILES['player_image']['name']) && $_FILES['player_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['player_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($ext, $allowed) && $_FILES['player_image']['size'] <= 5 * 1024 * 1024) {
                $image_name = 'player_' . time() . '_' . uniqid() . '.' . $ext;
                $image_path = 'uploads/' . $image_name;
                move_uploaded_file($_FILES['player_image']['tmp_name'], __DIR__ . '/' . $image_path);
            }
        }

        $sql = "UPDATE players SET first_name = ?, last_name = ?, aadhar_id = ?, gender = ?, guardian = ?, phone = ?, dob = ?, ipsf_id = ?" .
               ($image_path ? ", image = ?" : "") .
               " WHERE id = ? AND district_head_id = ?";

        $param_types = "ssssssss" . ($image_path ? "s" : "") . "ii";
        $params = [$first_name, $last_name, $aadhar, $gender, $guardian, $phone, $dob, $ipsf_id];
        if ($image_path) $params[] = $image_path;
        $params[] = $player_id;
        $params[] = $user_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($param_types, ...$params);
        if ($stmt->execute()) {
            $_SESSION['player_success'] = "Player details updated successfully.";
        } else {
            $_SESSION['player_error'] = "Update failed: " . $stmt->error;
        }
        $stmt->close();
    }

    header("Location: district_head.php");
    exit();
}
// DELETE ENTRY FROM TOURNAMENT (only removes from entries table, player still exists)
// DELETE ENTRY FROM TOURNAMENT (only if it belongs to current district head)
if (isset($_GET['delete_entry']) && isset($_GET['tournament_id'])) {
    $entry_id = (int)$_GET['delete_entry'];
    $tournament_id = (int)$_GET['tournament_id'];

    // Verify ownership
    $check = $conn->prepare("
        SELECT e.id 
        FROM entries e 
        JOIN players p ON e.player_id = p.id 
        WHERE e.id = ? AND e.tournament_id = ? AND p.district_head_id = ?
    ");
    $check->bind_param("iii", $entry_id, $tournament_id, $user_id);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM entries WHERE id = ? AND tournament_id = ?");
        $stmt->bind_param("ii", $entry_id, $tournament_id);
        if ($stmt->execute()) {
            $_SESSION['entry_success'] = "Player removed from tournament successfully.";
        } else {
            $_SESSION['entry_error'] = "Failed to remove: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['entry_error'] = "You do not have permission to remove this entry.";
    }
    $check->close();

    header("Location: district_head.php");
    exit();
}
// CONFIRM & SAVE ENTRIES
if (isset($_POST['confirm_entry'])) {
    $tournament_id = (int)($_POST['tournament_id'] ?? 0);
    $sub_event     = trim($_POST['sub_event'] ?? '');
    $players       = $_POST['players'] ?? [];

    if ($tournament_id <= 0 || empty($sub_event) || empty($players)) {
        $_SESSION['entry_error'] = "Invalid data or no players selected.";
    } else {
        $team_id = time() . rand(100, 999);
        $inserted = 0;

        foreach ($players as $p) {
            if (empty($p['player_id'])) continue;

            $player_id     = (int)$p['player_id'];
            $weight        = (float)($p['weight'] ?? 0);
            $height        = (float)($p['height'] ?? 0);
            $weight_class  = trim($p['weight_class'] ?? '');
            $age_category  = trim($p['age_category'] ?? '');
            $blood_group   = trim($p['blood_group'] ?? '');
            $event_type    = (count($players) > 1) ? 'team' : 'individual';

            $sql = "INSERT INTO entries 
                    (tournament_id, player_id, weight, height, weight_class, age_category, blood_group, event_type, sub_event, team_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("iidssssssi",
                    $tournament_id, $player_id, $weight, $height,
                    $weight_class, $age_category, $blood_group,
                    $event_type, $sub_event, $team_id
                );
                if ($stmt->execute()) {
                    $inserted++;
                }
                $stmt->close();
            }
        }

        if ($inserted > 0) {
            $_SESSION['entry_success'] = "$inserted player(s) successfully added!";
        } else {
            $_SESSION['entry_error'] = "No valid players were saved.";
        }
    }

    header("Location: district_head.php");
    exit();
}
// EDIT TOURNAMENT ENTRY DETAILS
if (isset($_POST['edit_entry'])) {
    $entry_id      = (int)$_POST['entry_id'];
    $tournament_id = (int)$_POST['tournament_id'];
    $weight        = (float)($_POST['weight'] ?? 0);
    $height        = (float)($_POST['height'] ?? 0);
    $weight_class  = trim($_POST['weight_class'] ?? '');
    $age_category  = trim($_POST['age_category'] ?? '');
    $blood_group   = trim($_POST['blood_group'] ?? '');

    // Security: only allow edit if entry belongs to current district head
    $check = $conn->prepare("
        SELECT e.id 
        FROM entries e 
        JOIN players p ON e.player_id = p.id 
        WHERE e.id = ? AND e.tournament_id = ? AND p.district_head_id = ?
    ");
    $check->bind_param("iii", $entry_id, $tournament_id, $user_id);
    $check->execute();
    $check_result = $check->get_result();

    if ($check_result->num_rows > 0) {
        $stmt = $conn->prepare("
            UPDATE entries 
            SET weight = ?, height = ?, weight_class = ?, age_category = ?, blood_group = ? 
            WHERE id = ? AND tournament_id = ?
        ");
        $stmt->bind_param("ddssssi", $weight, $height, $weight_class, $age_category, $blood_group, $entry_id, $tournament_id);

        if ($stmt->execute()) {
            $_SESSION['entry_success'] = "Entry details updated successfully.";
        } else {
            $_SESSION['entry_error'] = "Failed to update entry: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['entry_error'] = "You do not have permission to edit this entry.";
    }
    $check->close();

    header("Location: district_head.php");
    exit();
}

// Fetch tournaments
$tournaments = $conn->query("SELECT * FROM tournaments ORDER BY start_date DESC");

// Fetch my registered players
$my_players = $conn->query("
    SELECT id, unique_id, first_name, last_name, gender, dob, aadhar_id, guardian, phone, ipsf_id, image
    FROM players 
    WHERE district_head_id = $user_id 
    ORDER BY first_name, last_name
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>District Head Dashboard - <?= htmlspecialchars($district_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>/* =========================================
   KARNATAKA STATE PENCAK SILAT ASSOCIATION
   Professional UI Theme
   ========================================= */

:root {
    --primary-blue: #2E3192;
    --dark-blue: #1f226d;
    --accent-yellow: #F7E600;
    --accent-orange: #F7941D;
    --accent-red: #ED1C24;
    --black: #111111;
    --light-bg: #F4F6FA;
    --white: #ffffff;
}

/* ===== Global Reset ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: var(--light-bg);
    color: var(--black);
    line-height: 1.6;
}

/* ===== Headers ===== */
h1, h2, h3, h4 {
    color: var(--primary-blue);
    font-weight: 600;
}

h1 {
    font-size: 26px;
}

h2 {
    font-size: 22px;
    margin-bottom: 15px;
}

/* ===== Container Spacing ===== */
.container, .content, .wrapper {
    padding: 30px 5%;
}

/* ===== Cards ===== */
.card, .tournament-card, .box {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    border-top: 5px solid var(--primary-blue);
    transition: 0.3s ease;
}

.card:hover,
.tournament-card:hover,
.box:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
}

/* ===== Buttons ===== */
button,
.btn,
input[type="submit"],
a.button {
    background: var(--primary-blue);
    color: var(--white);
    border: none;
    padding: 10px 18px;
    border-radius: 30px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    transition: 0.3s ease;
    display: inline-block;
}

button:hover,
.btn:hover,
input[type="submit"]:hover,
a.button:hover {
    background: var(--accent-red);
}

/* Secondary button */
.btn-secondary {
    background: var(--accent-orange);
}

/* ===== Status Badges ===== */
.status-badge {
    padding: 6px 14px;
    border-radius: 25px;
    font-size: 12px;
    font-weight: 600;
    color: white;
    display: inline-block;
}

.status-upcoming {
    background: var(--accent-yellow);
    color: black;
}

.status-ongoing {
    background: var(--accent-orange);
}

.status-completed {
    background: var(--accent-red);
}

/* ===== Tables ===== */
table {
    width: 100%;
    border-collapse: collapse;
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.06);
}

table thead {
    background: var(--primary-blue);
    color: white;
}

table th,
table td {
    padding: 12px 15px;
    text-align: left;
}

table tr:nth-child(even) {
    background: #f2f3f8;
}

table tr:hover {
    background: #e8ebff;
}

/* ===== Forms ===== */
input,
select,
textarea {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    margin-bottom: 15px;
    font-size: 14px;
    transition: 0.3s;
}

input:focus,
select:focus,
textarea:focus {
    border-color: var(--primary-blue);
    outline: none;
    box-shadow: 0 0 5px rgba(46,49,146,0.3);
}

/* ===== Navigation / Header ===== */
header, .header, .navbar {
    background: var(--primary-blue);
    padding: 15px 5%;
    color: white;
}

header a,
.navbar a {
    color: white;
    text-decoration: none;
    margin-right: 20px;
    font-weight: 500;
}

header a:hover,
.navbar a:hover {
    color: var(--accent-yellow);
}

/* ===== Hero / Banner Section ===== */
.hero, .banner {
    background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
    color: white;
    padding: 60px 20px;
    text-align: center;
}

/* ===== Alerts ===== */
.alert-success {
    background: #e6f9ec;
    color: #1e7e34;
    padding: 12px;
    border-radius: 8px;
}

.alert-error {
    background: #fdecea;
    color: #b71c1c;
    padding: 12px;
    border-radius: 8px;
}

/* ===== Footer ===== */
footer {
    background: var(--dark-blue);
    color: white;
    text-align: center;
    padding: 20px;
    margin-top: 40px;
}

/* ===== Responsive Grid (auto works with existing divs) ===== */
.row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.col {
    flex: 1 1 300px;
}

/* ===== Mobile Responsive ===== */
@media (max-width: 768px) {

    body {
        font-size: 14px;
    }

    h1 {
        font-size: 20px;
    }

    h2 {
        font-size: 18px;
    }

    table th,
    table td {
        padding: 10px;
        font-size: 13px;
    }

    button,
    .btn,
    input[type="submit"] {
        width: 100%;
        text-align: center;
    }

}

    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-shield-lock-fill text-primary me-2"></i>
                District Head Dashboard
            </h2>
            <p class="text-muted mb-0">Managing <strong><?= htmlspecialchars($district_name) ?></strong></p>
        </div>
        <a href="logout.php" class="btn btn-outline-danger px-4 py-2">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </div>

    <!-- Alerts -->
    <?php if ($player_success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?= $player_success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($player_error): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <?= $player_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($entry_success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?= $entry_success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($entry_error): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <?= $entry_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Register New Player -->
    <div class="card mb-5 shadow-lg">
        <div class="card-header bg-gradient-primary text-purple d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i> Register New Player</h5>
            <button type="button" class="btn btn-light btn-sm px-3" data-bs-toggle="modal" data-bs-target="#viewPlayersModal">
                <i class="bi bi-list-ul me-1"></i> View Players
            </button>
        </div>
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="register_player" value="1">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                            <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">IPSF ID <span class="text-danger">*</span></label>
                        <input type="text" name="ipsf_id" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Photo (optional)</label>
                        <input type="file" name="player_image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">DOB <span class="text-danger">*</span></label>
                        <input type="date" name="dob" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Aadhaar ID <span class="text-danger">*</span></label>
                        <input type="text" name="aadhar_id" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Guardian <span class="text-danger">*</span></label>
                        <input type="text" name="guardian" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Phone <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-person-check-fill me-2"></i> Register Player
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tournaments Section -->
    <h4 class="mb-4 fw-bold text-dark"><i class="bi bi-trophy-fill text-primary me-2"></i> Available Tournaments</h4>

    <?php while ($t = $tournaments->fetch_assoc()): 
        // Calculate tournament status
      $today = new DateTime();
$start = new DateTime($t['start_date']);
$end   = (clone $start)->modify("+{$t['num_days']} days - 1 second"); // end of last day

if ($today < $start) {
    $status_text = "Upcoming";
    $status_class = "status-upcoming";
} elseif ($today <= $end) {
    $status_text = "Ongoing";
    $status_class = "status-ongoing";
} else {
    $status_text = "Completed";
    $status_class = "status-completed";
}
?>

    
        <div class="card mb-5 shadow-lg">
      <div class="card-header bg-gradient-primary text-purple">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h5 class="mb-1 fw-bold">
                <?= htmlspecialchars($t['name']) ?>
                <?php if (!empty($t['location'])): ?>
                    <small class="ms-2 fw-normal text-white-75">
                        (<?= htmlspecialchars($t['location']) ?>)
                    </small>
                <?php endif; ?>
            </h5>
            <small class="text-purple-75">
                <i class="bi bi-calendar-event me-1"></i>
                <?= $t['start_date'] ?> • <?= $t['num_days'] ?> day(s)
            </small>
        </div>
        <span class="status-badge <?= $status_class ?>">
            <?= $status_text ?>
        </span>
    </div>
</div>
<div class="mb-4">
    <label class="form-label fw-bold">Tournament Location (for reference)</label>
    <input type="text" class="form-control" value="<?= htmlspecialchars($t['location'] ?? 'Not specified') ?>" readonly>
</div>
            <div class="card-body p-4">
                <div class="d-flex gap-3 mb-4 flex-wrap">
                    <button class="btn btn-outline-primary flex-fill py-3" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#entryForm<?= $t['id'] ?>">
                        <i class="bi bi-person-plus me-2"></i> Enter Participants
                    </button>
                    <button class="btn btn-outline-info flex-fill py-3" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#viewEntered<?= $t['id'] ?>">
                        <i class="bi bi-eye me-2"></i> View Entered Players
                    </button>
                </div>

                <!-- Enter Participants Form -->
                <div class="collapse mb-4" id="entryForm<?= $t['id'] ?>">
                    <form method="POST" id="entryForm<?= $t['id'] ?>">
                        <input type="hidden" name="preview_entry" value="1">
                        <input type="hidden" name="tournament_id" value="<?= $t['id'] ?>">

                        <div class="mb-4">
                            <label class="form-label fw-bold fs-5">Sub Event <span class="text-danger">*</span></label>
                            <select name="sub_event" class="form-select form-select-lg" id="subEvent<?= $t['id'] ?>" 
                                    onchange="updateTeamFields(<?= $t['id'] ?>)" required>
                                <option value="">Select Sub Event</option>
                                <option value="tanding">Tanding (Individual)</option>
                                <option value="free event">Free Event (Individual)</option>
                                <option value="tungal">Tungal (Individual)</option>
                                <option value="ganda">Ganda (2 players)</option>
                                <option value="regu">Regu (3 players)</option>
                            </select>
                        </div>

                        <div id="playersContainer<?= $t['id'] ?>" class="mt-4"></div>

                        <button type="button" class="btn btn-primary btn-lg w-100 mt-4" 
                                onclick="showPreview(<?= $t['id'] ?>)">
                            <i class="bi bi-eye me-2"></i> Preview & Submit
                        </button>
                    </form>
                </div>

                <!-- View Entered Players -->
                <div class="collapse" id="viewEntered<?= $t['id'] ?>">
                    <?php
                  $stmt = $conn->prepare("
    SELECT p.id, p.unique_id, p.first_name, p.last_name, p.aadhar_id, e.weight, e.height, e.weight_class, 
       e.age_category, e.blood_group, e.sub_event
    FROM entries e
    JOIN players p ON e.player_id = p.id
    WHERE e.tournament_id = ? AND p.district_head_id = ?
    ORDER BY p.first_name
");
$stmt->bind_param("ii", $t['id'], $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    ?>
                    <h5 class="mt-4 mb-3 text-primary fw-bold">Entered Players for <?= htmlspecialchars($t['name']) ?></h5>
<?php if (!empty($t['location'])): ?>
    <p class="text-muted mb-3">
        <i class="bi bi-geo-alt-fill me-1"></i>
        Location: <?= htmlspecialchars($t['location']) ?>
    </p>
<?php endif; ?>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered rounded overflow-hidden">
                                <thead>
                                    <tr>
                                        <th>Unique ID</th>
                                        <th>Name</th>
                                        <th>Aadhaar ID</th>
                                        <th>Weight (kg)</th>
                                        <th>Height (cm)</th>
                                        <th>Weight Class</th>
                                        <th>Age Category</th>
                                        <th>Blood Group</th>
                                        <th>Sub-Event</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
    <?php while ($row = $result->fetch_assoc()): 
        // Get player ID from entries table (we need it for delete)
        $entry_id_query = $conn->prepare("SELECT id FROM entries WHERE tournament_id = ? AND player_id = ?");
        $entry_id_query->bind_param("ii", $t['id'], $row['id']); // assuming p.id is in the SELECT
        $entry_id_query->execute();
        $entry_result = $entry_id_query->get_result();
        $entry_row = $entry_result->fetch_assoc();
        $entry_id = $entry_row['id'] ?? 0;
        $entry_id_query->close();
    ?>
        <tr>
            <td><code><?= htmlspecialchars($row['unique_id']) ?></code></td>
            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
            <td><?= htmlspecialchars($row['aadhar_id'] ?: '-') ?></td>
            <td><?= $row['weight'] ?: '-' ?></td>
            <td><?= $row['height'] ?: '-' ?></td>
            <td><?= htmlspecialchars($row['weight_class'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['age_category'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['blood_group'] ?: '-') ?></td>
            <td><?= htmlspecialchars($row['sub_event']) ?></td>
            <<td>
    <!-- Edit Button -->
    <button class="btn btn-sm btn-warning edit-entry-btn" 
        data-bs-toggle="modal" data-bs-target="#editEntryModal"
        data-entry-id="<?= $entry_id ?>"
        data-tournament-id="<?= $t['id'] ?>"
        data-weight="<?= htmlspecialchars($row['weight'] ?? '') ?>"
        data-height="<?= htmlspecialchars($row['height'] ?? '') ?>"
        data-weight-class="<?= htmlspecialchars($row['weight_class'] ?? '') ?>"
        data-age-category="<?= htmlspecialchars($row['age_category'] ?? '') ?>"
        data-blood-group="<?= htmlspecialchars($row['blood_group'] ?? '') ?>"
        data-sub-event="<?= htmlspecialchars($row['sub_event'] ?? '') ?>">
    <i class="bi bi-pencil"></i> Edit 
</button>

    <!-- Delete Button -->
    <a href="?delete_entry=<?= $entry_id ?>&tournament_id=<?= $t['id'] ?>" 
       class="btn btn-sm btn-danger ms-1" 
       onclick="return confirm('Remove this player from the tournament? This will NOT delete the player from the system.')">
        <i class="bi bi-trash"></i> Delete
    </a>
</td>
        </tr>
    <?php endwhile; ?>
</tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">No players entered for this tournament yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endwhile; ?>

</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-gradient-primary text-purple">
                <h5 class="modal-title fw-bold">Preview & Confirm Participants</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-primary">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Unique ID</th>
                                <th>Weight (kg)</th>
                                <th>Height (cm)</th>
                                <th>Weight Class</th>
                                <th>Age Category</th>
                                <th>Blood Group</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody"></tbody>
                    </table>
                </div>
                <div class="text-center mt-4 fw-bold fs-5 text-primary">
                    Total Players: <span id="totalPlayers" class="text-dark">0</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="confirmEntryForm">
                    <input type="hidden" name="confirm_entry" value="1">
                    <input type="hidden" name="tournament_id" id="confirmTournamentId">
                    <input type="hidden" name="sub_event" id="confirmSubEvent">
                    <div id="confirmHiddenFields"></div>
                    <button type="submit" class="btn btn-success btn-lg px-5">
                        <i class="bi bi-check2-circle me-2"></i> Confirm & Save
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Registered Players Modal (unchanged) -->
<div class="modal fade" id="viewPlayersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-purple">
                <h5 class="modal-title">My Registered Players</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" id="playerSearch" class="form-control" placeholder="Search by name, Unique ID, Aadhaar ID, IPSF ID, phone...">
                </div>

                <?php if ($my_players->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="playersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Photo</th>
                                    <th>Unique ID</th>
                                    <th>Name</th>
                                    <th>Aadhaar ID</th>
                                    <th>Gender</th>
                                    <th>DOB</th>
                                    <th>Guardian</th>
                                    <th>Phone</th>
                                    <th>IPSF ID</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($player = $my_players->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php if ($player['image']): ?>
                                                <img src="<?= htmlspecialchars($player['image']) ?>" class="player-photo" alt="Photo">
                                            <?php else: ?>
                                                <span class="text-muted">No photo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($player['unique_id']) ?></code></td>
                                        <td><?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></td>
                                        <td><?= htmlspecialchars($player['aadhar_id'] ?: '-') ?></td>
                                        <td><?= ucfirst($player['gender'] ?: '-') ?></td>
                                        <td><?= $player['dob'] ? date('d-m-Y', strtotime($player['dob'])) : '-' ?></td>
                                        <td><?= htmlspecialchars($player['guardian'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($player['phone'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($player['ipsf_id'] ?: '-') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning edit-player-btn" 
                                                    data-bs-toggle="modal" data-bs-target="#editPlayerModal"
                                                    data-id="<?= $player['id'] ?>"
                                                    data-fname="<?= htmlspecialchars($player['first_name']) ?>"
                                                    data-lname="<?= htmlspecialchars($player['last_name']) ?>"
                                                    data-gender="<?= $player['gender'] ?>"
                                                    data-dob="<?= $player['dob'] ?>"
                                                    data-aadhar="<?= htmlspecialchars($player['aadhar_id']) ?>"
                                                    data-guardian="<?= htmlspecialchars($player['guardian']) ?>"
                                                    data-phone="<?= htmlspecialchars($player['phone']) ?>"
                                                    data-ipsf="<?= htmlspecialchars($player['ipsf_id']) ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No players registered yet.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Player Modal (unchanged) -->
<div class="modal fade" id="editPlayerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Edit Player Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="edit_player" value="1">
                    <input type="hidden" name="player_id" id="edit_player_id">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="edit_fname" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="edit_lname" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Gender <span class="text-danger">*</span></label>
                            <select name="gender" id="edit_gender" class="form-select" required>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>DOB <span class="text-danger">*</span></label>
                            <input type="date" name="dob" id="edit_dob" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Aadhaar ID <span class="text-danger">*</span></label>
                            <input type="text" name="aadhar_id" id="edit_aadhar" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Guardian <span class="text-danger">*</span></label>
                            <input type="text" name="guardian" id="edit_guardian" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>Phone <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" id="edit_phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label>IPSF ID <span class="text-danger">*</span></label>
                            <input type="text" name="ipsf_id" id="edit_ipsf" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label>New Photo (optional)</label>
                            <input type="file" name="player_image" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- New Modal: Edit Tournament Entry Details -->
<div class="modal fade" id="editEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Edit Entry Details for Tournament</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="edit_entry" value="1">
                    <input type="hidden" name="entry_id" id="edit_entry_id">
                    <input type="hidden" name="tournament_id" id="edit_tournament_id">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Weight (kg)</label>
                            <input type="number" step="0.1" name="weight" id="edit_weight" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label>Height (cm)</label>
                            <input type="number" name="height" id="edit_height" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label>Weight Class</label>
                            <input type="text" name="weight_class" id="edit_weight_class" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label>Age Category</label>
                            <input type="text" name="age_category" id="edit_age_category" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label>Blood Group</label>
                            <input type="text" name="blood_group" id="edit_blood_group" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label>Sub-Event (current)</label>
                            <input type="text" name="sub_event" id="edit_sub_event" class="form-control" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Save Entry Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Age & Weight dropdown logic (unchanged)
const weightClassesByAge = {
    "C": [
        "A – 26kg to 28kg", "B – Over 28kg to 30kg", "C – Over 30kg to 32kg", "D – Over 32kg to 34kg",
        "E – Over 34kg to 36kg", "F – Over 36kg to 38kg", "G – Over 38kg to 40kg", "H – Over 40kg to 42kg",
        "I – Over 42kg to 44kg", "J – Over 44kg to 46kg", "K – Over 46kg to 48kg", "L – Over 48kg to 50kg",
        "M – Over 50kg to 52kg", "N – Over 52kg to 54kg", "O – Over 54kg to 56kg", "P – Over 56kg to 58kg",
        "Q – Over 58kg to 60kg", "R – Over 60kg to 62kg", "S – Over 62kg to 64kg", "OPEN – Over 64kg to 68kg"
    ],
    "D": [
        "A – 30kg to 33kg", "B – Over 33kg to 36kg", "C – Over 36kg to 39kg", "D – Over 39kg to 42kg",
        "E – Over 42kg to 45kg", "F – Over 45kg to 48kg", "G – Over 48kg to 51kg", "H – Over 51kg to 54kg",
        "I – Over 54kg to 57kg", "J – Over 57kg to 60kg", "K – Over 60kg to 63kg", "L – Over 63kg to 66kg",
        "M – Over 66kg to 69kg", "N – Over 69kg to 72kg", "O – Over 72kg to 75kg", "P – Over 75kg to 78kg",
        "OPEN – Over 75kg to 84kg"
    ],
    "E": [
        "UNDER 39kg", "A – Over 39kg to 43kg", "B – Over 43kg to 47kg", "C – Over 47kg to 51kg",
        "D – Over 51kg to 55kg", "E – Over 55kg to 59kg", "F – Over 59kg to 63kg", "G – Over 63kg to 67kg",
        "H – Over 67kg to 71kg", "I – Over 71kg to 75kg", "J – Over 75kg to 79kg", "K – Over 79kg to 83kg",
        "L – Over 83kg to 87kg", "OPEN1 – Over 87kg to 100kg", "OPEN2 – Above 100kg"
    ],
    "F": [
        "UNDER 45kg", "A – Over 45kg to 50kg", "B – Over 50kg to 55kg", "C – Over 55kg to 60kg",
        "D – Over 60kg to 65kg", "E – Over 65kg to 70kg", "F – Over 70kg to 75kg", "G – Over 75kg to 80kg",
        "H – Over 80kg to 85kg", "I – Over 85kg to 90kg", "J – Over 90kg to 95kg",
        "OPEN1 – Over 95kg to 110kg", "OPEN2 – Above 110kg"
    ]
};

// Update team fields
function updateTeamFields(tId) {
    const select = document.getElementById('subEvent' + tId);
    const container = document.getElementById('playersContainer' + tId);
    container.innerHTML = '';

    let count = 1;
    if (select.value === 'ganda') count = 2;
    if (select.value === 'regu') count = 3;

    if (!select.value) {
        container.innerHTML = '<div class="alert alert-warning mt-3">Please select a sub-event first.</div>';
        return;
    }

    for (let i = 0; i < count; i++) {
        const playerNum = i + 1;
        const div = document.createElement('div');
        div.className = 'player-row mb-4 border-bottom pb-3';
        div.innerHTML = `
            <h6 class="player-heading">Player ${playerNum}</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label>Player Name <span class="text-danger">*</span></label>
                    <input type="text" name="players[${i}][name]" class="form-control player-name-input" 
                           required placeholder="Type name to auto-fill Unique ID" 
                           oninput="fetchUniqueId(this, ${tId}, ${i})">
                    <small class="status text-muted d-block mt-1">Start typing name...</small>
                </div>
                <div class="col-md-4">
                    <label>Unique ID <span class="text-danger">*</span></label>
                    <input type="text" name="players[${i}][unique_id]" class="form-control unique-id-input" required placeholder="Auto-filled if name exists">
                    <input type="hidden" name="players[${i}][player_id]" class="player-id-field">
                </div>
                <div class="col-md-3">
                    <label>Weight (kg) <span class="text-danger">*</span></label>
                    <input type="number" step="0.1" name="players[${i}][weight]" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label>Height (cm) <span class="text-danger">*</span></label>
                    <input type="number" name="players[${i}][height]" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>Age Category <span class="text-danger">*</span></label>
                    <select name="players[${i}][age_category]" class="form-select age-category" required>
                        <option value="">Select Age Category</option>
                        <option value="A">A – SINGA (3–6 yrs)</option>
                        <option value="B">B – MACAN (7–9 yrs)</option>
                        <option value="C">C – PRE-TEEN (10–11 yrs)</option>
                        <option value="D">D – PRE JUNIOR (12–13 yrs)</option>
                        <option value="E">E – JUNIOR (14–16 yrs)</option>
                        <option value="F">F – SENIOR (17–45 yrs)</option>
                        <option value="G">G – MASTER A (46–60 yrs)</option>
                        <option value="H">H – MASTER B (61+ yrs)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Weight Class <span class="text-danger">*</span></label>
                    <select name="players[${i}][weight_class]" class="form-select weight-class" required disabled>
                        <option value="">Select Age Category first</option>
                    </select>
                    <small class="weight-hint text-muted d-block mt-1"></small>
                </div>
                <div class="col-md-3">
                    <label>Blood Group <span class="text-danger">*</span></label>
                    <input type="text" name="players[${i}][blood_group]" class="form-control" required placeholder="e.g. O+">
                </div>
            </div>
        `;
        container.appendChild(div);
    }
}

// Auto-fill Unique ID
function fetchUniqueId(input, tId, index) {
    const name = input.value.trim();
    if (name.length < 3) return;

    const row = input.closest('.player-row');
    const uniqueIdField = row.querySelector('.unique-id-input');
    const playerIdField = row.querySelector('.player-id-field');
    const status = row.querySelector('.status');

    status.textContent = 'Searching...';
    status.className = 'status text-muted d-block mt-1';

    fetch('fetch_player.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(name)}`
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.found) {
            uniqueIdField.value = data.unique_id;
            playerIdField.value = data.id;
            status.textContent = '✓ Found in database';
            status.className = 'status text-success d-block mt-1';
        } else {
            uniqueIdField.value = '';
            playerIdField.value = '';
            status.textContent = data.error ? `Error: ${data.error}` : '✗ Not found – enter manually';
            status.className = data.error ? 'status text-danger d-block mt-1' : 'status text-warning d-block mt-1';
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        status.textContent = 'Error checking name: ' + error.message;
        status.className = 'status text-danger d-block mt-1';
    });
}

// Age category change → weight class update
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('age-category')) {
        const ageCat = e.target.value;
        const row = e.target.closest('.player-row');
        const weightSelect = row.querySelector('.weight-class');
        const hint = row.querySelector('.weight-hint');

        weightSelect.innerHTML = '<option value="">Select Weight Class</option>';
        weightSelect.disabled = !ageCat;
        hint.textContent = '';
        hint.className = 'weight-hint text-muted d-block mt-1';

        if (ageCat && ["C", "D", "E", "F"].includes(ageCat) && weightClassesByAge[ageCat]) {
            weightSelect.disabled = false;
            weightClassesByAge[ageCat].forEach(cls => {
                const option = document.createElement('option');
                option.value = cls;
                option.textContent = cls;
                weightSelect.appendChild(option);
            });
            hint.textContent = "Select appropriate weight class";
            hint.className = 'weight-hint text-danger d-block mt-1';
        } else if (ageCat) {
            const option = document.createElement('option');
            option.value = "Guideline-based";
            option.textContent = "Guideline-based (no fixed class)";
            option.selected = true;
            weightSelect.appendChild(option);
            hint.textContent = "No fixed weight class required for this age group";
            hint.className = 'weight-hint text-info d-block mt-1';
        }
    }
});

// Show Preview Modal
function showPreview(tId) {
    const form = document.getElementById('entryForm' + tId);
    const subEvent = form.querySelector('select[name="sub_event"]').value;
    const container = document.getElementById('playersContainer' + tId);
    const rows = container.querySelectorAll('.player-row');

    let html = '';
    let hiddenFields = '';
    let total = 0;

    rows.forEach((row, i) => {
        const nameInput       = row.querySelector('input[name$="[name]"]');
        const uniqueIdInput   = row.querySelector('input[name$="[unique_id]"]');
        const playerIdInput   = row.querySelector('input[name$="[player_id]"]');
        const weightInput     = row.querySelector('input[name$="[weight]"]');
        const heightInput     = row.querySelector('input[name$="[height]"]');
        const weightClassInput = row.querySelector('select[name$="[weight_class]"]');
        const ageCategoryInput = row.querySelector('select[name$="[age_category]"]');
        const bloodInput      = row.querySelector('input[name$="[blood_group]"]');

        if (!nameInput.value.trim() || !uniqueIdInput.value.trim()) return;

        total++;

        html += `
            <tr>
                <td>${total}</td>
                <td>${nameInput.value.trim()}</td>
                <td>${uniqueIdInput.value.trim()}</td>
                <td>${weightInput.value || '-'}</td>
                <td>${heightInput.value || '-'}</td>
                <td>${weightClassInput.value || '-'}</td>
                <td>${ageCategoryInput.value || '-'}</td>
                <td>${bloodInput.value || '-'}</td>
            </tr>
        `;

        hiddenFields += `
            <input type="hidden" name="players[${i}][player_id]" value="${playerIdInput.value || ''}">
            <input type="hidden" name="players[${i}][unique_id]" value="${uniqueIdInput.value.trim()}">
            <input type="hidden" name="players[${i}][name]" value="${nameInput.value.trim()}">
            <input type="hidden" name="players[${i}][weight]" value="${weightInput.value}">
            <input type="hidden" name="players[${i}][height]" value="${heightInput.value}">
            <input type="hidden" name="players[${i}][weight_class]" value="${weightClassInput.value}">
            <input type="hidden" name="players[${i}][age_category]" value="${ageCategoryInput.value}">
            <input type="hidden" name="players[${i}][blood_group]" value="${bloodInput.value}">
        `;
    });

    if (total === 0) {
        alert("No valid players added.\n\nPlease fill name and unique ID for each player.");
        return;
    }

    document.getElementById('previewTableBody').innerHTML = html;
    document.getElementById('totalPlayers').textContent = total;
    document.getElementById('confirmTournamentId').value = tId;
    document.getElementById('confirmSubEvent').value = subEvent;
    document.getElementById('confirmHiddenFields').innerHTML = hiddenFields;

    new bootstrap.Modal(document.getElementById('previewModal')).show();
}
// Populate Edit Entry Modal
document.querySelectorAll('.edit-entry-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_entry_id').value       = this.dataset.entryId;
        document.getElementById('edit_tournament_id').value  = this.dataset.tournamentId;
        document.getElementById('edit_weight').value         = this.dataset.weight;
        document.getElementById('edit_height').value         = this.dataset.height;
        document.getElementById('edit_weight_class').value   = this.dataset.weightClass;
        document.getElementById('edit_age_category').value   = this.dataset.ageCategory;
        document.getElementById('edit_blood_group').value    = this.dataset.bloodGroup;
        document.getElementById('edit_sub_event').value      = this.dataset.subEvent;
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>