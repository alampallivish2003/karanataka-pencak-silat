<?php
// admin.php - FULLY UPDATED with toggle for Registered District Heads
session_start();

require_once 'db_config.php';
require_once 'email_config.php';

if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
} else {
    die("Error: vendor/autoload.php not found. Run: composer require phpmailer/phpmailer");
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$success = "";
$error = "";

// Pagination (optional - you can keep or remove)
$limit = 10;
$page_users = max(1, (int)($_GET['page_users'] ?? 1));
$page_tour  = max(1, (int)($_GET['page_tour'] ?? 1));
$offset_users = ($page_users - 1) * $limit;
$offset_tour  = ($page_tour - 1) * $limit;

/* ========================
   HELPER FUNCTIONS
======================== */
function generatePassword($length = 8) {
    return substr(str_shuffle("ABCDEFGHJKLMNPQRSTUVWXYZ23456789"), 0, $length);
}

function sendCredentials($email, $username, $password) {
    $subject = "Your Login Credentials - KSPSA";
    $body = "
        Hello $username,<br><br>
        Your district head account has been created by Admin.<br><br>
        <strong>Login URL:</strong> http://localhost/silat/login.php<br>
        <strong>Username:</strong> $username<br>
        <strong>Password:</strong> $password<br><br>
        Please change your password after first login.<br><br>
        Regards,<br>KSPSA Admin Team
    ";
    return sendEmail($email, $subject, $body);
}

function sendTournamentMail($subject, $message, $conn) {
    $users = $conn->query("SELECT email FROM users WHERE role='district_head'");
    while ($u = $users->fetch_assoc()) {
        sendEmail($u['email'], $subject, $message);
    }
}

function tournamentStatus($start, $days) {
    $startDate = new DateTime($start);
    $endDate   = (clone $startDate)->modify("+$days days");
    $today     = new DateTime();
    if ($today < $startDate) return "Upcoming";
    if ($today > $endDate)   return "Completed";
    return "Ongoing";
}

/* ========================
   STATS
======================== */
$totalDistrictHeads = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='district_head'")->fetch_assoc()['cnt'] ?? 0;
$totalPlayers       = $conn->query("SELECT COUNT(*) as cnt FROM players")->fetch_assoc()['cnt'] ?? 0;
$totalTournaments   = $conn->query("SELECT COUNT(*) as cnt FROM tournaments")->fetch_assoc()['cnt'] ?? 0;

/* ========================
   EDIT FETCH
======================== */
$editUser = null;
if (isset($_GET['edit_user'])) {
    $id = (int)$_GET['edit_user'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=? AND role='district_head'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$editTournament = null;
if (isset($_GET['edit_tournament'])) {
    $id = (int)$_GET['edit_tournament'];
    $stmt = $conn->prepare("SELECT * FROM tournaments WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editTournament = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/* ========================
   CREATE / UPDATE USER
======================== */
if (isset($_POST['save_user'])) {
    $id       = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username || !$email || !$district) {
        $error = "Username, Email, and District are required.";
    } else {
        if ($id > 0) {
            if ($password) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, district_name=?, password=? WHERE id=?");
                $stmt->bind_param("sssssi", $username, $email, $phone, $district, $hashed, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, district_name=? WHERE id=?");
                $stmt->bind_param("ssssi", $username, $email, $phone, $district, $id);
            }
            $stmt->execute();
            $stmt->close();
            $success = "District Head <strong>" . htmlspecialchars($username) . "</strong> updated successfully.";
        } else {
            if (!$password) {
                $error = "Password required for new user.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, role, phone, email, district_name) VALUES (?, ?, 'district_head', ?, ?, ?)");
                $stmt->bind_param("sssss", $username, $hashed, $phone, $email, $district);
                if ($stmt->execute()) {
                    sendCredentials($email, $username, $password);
                    $success = "New District Head <strong>" . htmlspecialchars($username) . "</strong> created and credentials sent.";
                } else {
                    $error = "Failed to create user: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

/* ========================
   DELETE USER
======================== */
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    $username = 'Unknown';
    $stmt = $conn->prepare("SELECT username FROM users WHERE id=? AND role='district_head'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $username = $row['username'];
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND role='district_head'");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "District Head <strong>" . htmlspecialchars($username) . "</strong> deleted successfully.";
    } else {
        $error = "Delete failed: " . $conn->error;
    }
    $stmt->close();
}

/* ========================
   CREATE / UPDATE / DELETE TOURNAMENT
======================== */
if (isset($_POST['save_tournament'])) {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $start    = $_POST['start_date'] ?? '';
    $days     = (int)($_POST['num_days'] ?? 0);
    $location = trim($_POST['location'] ?? '');

    if (!$name || !$start || !$location || $days < 1) {
        $error = "All tournament fields required.";
    } else {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE tournaments SET name=?, start_date=?, num_days=?, location=? WHERE id=?");
            $stmt->bind_param("ssisi", $name, $start, $days, $location, $id);
            if ($stmt->execute()) {
                sendTournamentMail("Tournament Updated", "Tournament '$name' has been updated.", $conn);
                $success = "Tournament <strong>" . htmlspecialchars($name) . "</strong> updated successfully.";
            } else {
                $error = "Update failed: " . $conn->error;
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO tournaments (name, start_date, num_days, location) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $name, $start, $days, $location);
            if ($stmt->execute()) {
                sendTournamentMail("New Tournament Created", "A new tournament '$name' has been created.", $conn);
                $success = "New Tournament <strong>" . htmlspecialchars($name) . "</strong> created successfully.";
            } else {
                $error = "Failed to create tournament: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['delete_tournament'])) {
    $id = (int)$_GET['delete_tournament'];
    $name = 'Unknown';
    $stmt = $conn->prepare("SELECT name FROM tournaments WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) $name = $row['name'];
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM tournaments WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Tournament <strong>" . htmlspecialchars($name) . "</strong> deleted successfully.";
    } else {
        $error = "Delete failed: " . $conn->error;
    }
    $stmt->close();
}

/* ========================
   FETCH DATA
======================== */
$users = $conn->query("SELECT * FROM users WHERE role='district_head' ORDER BY username");
$tournaments = $conn->query("SELECT * FROM tournaments ORDER BY start_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - KSPSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>/* =================================================
   ROOT COLOR SYSTEM (CLEAN VERSION)
================================================= */
:root {
    --primary-blue: #2E3192;
    --accent-yellow: #F7E600;
    --accent-orange: #F7941D;
    --light-bg: #F4F6FA;
    --white: #ffffff;

    --border-color: #e4e7f2;
    --success: #16a34a;
    --text-dark: #1f2937;
}

/* =================================================
   GLOBAL RESET
================================================= */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, sans-serif;
}

body {
    background: var(--light-bg);
    color: var(--text-dark);
    min-height: 100vh;
    line-height: 1.6;
}

/* =================================================
   PAGE CONTAINER
================================================= */
.container,
.main-container,
.wrapper {
    max-width: 1200px;
    margin: auto;
    padding: 30px 5%;
}

/* =================================================
   PAGE TITLE
================================================= */
.page-title,
h1 {
    text-align: center;
    font-size: 26px;
    font-weight: 700;
    color: var(--primary-blue);
    margin-bottom: 25px;
}

/* =================================================
   CARD / PANEL DESIGN
================================================= */
.card,
.box,
.panel {
    background: var(--white);
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 25px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.06);
    border-top: 5px solid var(--primary-blue);
    transition: 0.3s ease;
}

.card:hover,
.box:hover,
.panel:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.08);
}

/* =================================================
   FORMS
================================================= */
form {
    display: grid;
    gap: 18px;
}

label {
    font-weight: 600;
    font-size: 14px;
    color: var(--primary-blue);
}

input,
select,
textarea {
    width: 100%;
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
    transition: 0.3s;
}

input:focus,
select:focus,
textarea:focus {
    border-color: var(--primary-blue);
    outline: none;
    box-shadow: 0 0 5px rgba(46,49,146,0.25);
}

/* =================================================
   BUTTONS (NO RED)
================================================= */
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
    background: var(--accent-orange);
}

/* Secondary */
.btn-secondary {
    background: var(--accent-orange);
}

/* Success */
.btn-success {
    background: var(--success);
}

/* =================================================
   TABLE DESIGN
================================================= */
.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.05);
}

thead {
    background: var(--primary-blue);
    color: white;
}

th,
td {
    padding: 12px 15px;
    text-align: left;
    font-size: 14px;
}

tr:nth-child(even) {
    background: #f2f3f8;
}

tr:hover {
    background: #e8ebff;
}

/* =================================================
   ALERTS (NO RED)
================================================= */
.alert,
.message {
    padding: 12px 15px;
    border-radius: 8px;
    font-weight: 600;
    margin-bottom: 20px;
}

.alert-success {
    background: #e6f9ec;
    color: var(--success);
}

.alert-warning {
    background: #fff8db;
    color: #b7791f;
}

/* =================================================
   ACTION GROUP
================================================= */
.action-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* =================================================
   NAVBAR (CLEAN BLUE)
================================================= */
.navbar,
.admin-header {
    background: var(--primary-blue);
    padding: 15px 5%;
    color: white;
}

.navbar a,
.admin-header a {
    color: white;
    text-decoration: none;
    margin-right: 20px;
    font-weight: 500;
}

.navbar a:hover,
.admin-header a:hover {
    color: var(--accent-yellow);
}

/* =================================================
   RESPONSIVE DESIGN
================================================= */
@media (max-width: 768px) {

    body {
        font-size: 14px;
    }

    h1 {
        font-size: 20px;
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

    .action-group {
        flex-direction: column;
    }

}


</style>

</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4">

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Dashboard -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card stats-card primary">
                <div class="card-body text-center py-4">
                    <i class="bi bi-people-fill fs-1 text-primary mb-3"></i>
                    <h5 class="mb-1">District Heads</h5>
                    <h2 class="fw-bold mb-0"><?= number_format($totalDistrictHeads) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stats-card success">
                <div class="card-body text-center py-4">
                    <i class="bi bi-person-badge-fill fs-1 text-success mb-3"></i>
                    <h5 class="mb-1">Players Registered</h5>
                    <h2 class="fw-bold mb-0"><?= number_format($totalPlayers) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stats-card warning">
                <div class="card-body text-center py-4">
                    <i class="bi bi-trophy-fill fs-1 text-warning mb-3"></i>
                    <h5 class="mb-1">Tournaments</h5>
                    <h2 class="fw-bold mb-0"><?= number_format($totalTournaments) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Create / Edit District Head -->
    <div class="card shadow mb-5">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i><?= $editUser ? "Edit District Head" : "Create New District Head" ?></h5>
            <?php if ($editUser): ?>
                <a href="admin.php" class="btn btn-sm btn-light">Cancel Edit</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="id" value="<?= $editUser['id'] ?? '' ?>">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Username</label>
                    <input name="username" class="form-control" value="<?= htmlspecialchars($editUser['username'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Email</label>
                    <input name="email" type="email" class="form-control" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Phone</label>
                    <input name="phone" class="form-control" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">District Name</label>
                    <input name="district" class="form-control" value="<?= htmlspecialchars($editUser['district_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><?= $editUser ? 'New Password (optional)' : 'Password (required)' ?></label>
                    <div class="input-group">
                        <input id="password" name="password" class="form-control" placeholder="<?= $editUser ? 'Leave blank to keep current' : 'Required' ?>" <?= !$editUser ? 'required' : '' ?>>
                        <button type="button" class="btn btn-outline-secondary" onclick="genPass()">Gen</button>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success w-100 btn-lg">
                        <i class="bi bi-save me-2"></i><?= $editUser ? "Update District Head" : "Create & Send Credentials" ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Registered District Heads - TOGGLE SECTION -->
    <div class="mb-4">
        <button id="toggleUsersBtn" class="btn btn-outline-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2 toggle-btn">
            <i class="bi bi-chevron-down fs-4"></i>
            <span>View Registered District Heads (<?= $totalDistrictHeads ?>)</span>
        </button>
    </div>

    <div id="usersTableSection" class="card shadow mb-5 d-none">
        <div class="card-header bg-light text-black">
            <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Registered District Heads</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>District</th>
                            <th>Phone</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($u = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['district_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                                <td class="text-end">
                                    <a href="?edit_user=<?= $u['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="?delete_user=<?= $u['id'] ?>" class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Delete <?= htmlspecialchars($u['username']) ?>?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create / Edit Tournament -->
    <div class="card shadow mb-5">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-trophy me-2"></i><?= $editTournament ? "Edit Tournament" : "Create New Tournament" ?></h5>
            <?php if ($editTournament): ?>
                <a href="admin.php" class="btn btn-sm btn-light">Cancel Edit</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="save_tournament" value="1">
                <input type="hidden" name="id" value="<?= $editTournament['id'] ?? '' ?>">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Tournament Name</label>
                    <input name="name" class="form-control" value="<?= htmlspecialchars($editTournament['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($editTournament['start_date'] ?? '') ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Days</label>
                    <input type="number" name="num_days" class="form-control" value="<?= htmlspecialchars($editTournament['num_days'] ?? '') ?>" min="1" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Location</label>
                    <input name="location" class="form-control" value="<?= htmlspecialchars($editTournament['location'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success w-100 btn-lg">
                        <i class="bi bi-save me-2"></i><?= $editTournament ? "Update Tournament" : "Create Tournament" ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tournaments List -->
    <div class="card shadow">
        <div class="card-header bg-light text-black d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-trophy-fill me-2"></i>Available Tournaments (<?= $totalTournaments ?>)</h5>
            <div class="btn-group">
                <a href="?page_tour=<?= $page_tour-1 ?>" class="btn btn-sm btn-outline-light <?= $page_tour <= 1 ? 'disabled' : '' ?>">Prev</a>
                <span class="btn btn-sm btn-secondary disabled">Page <?= $page_tour ?> of <?= ceil($totalTournaments / $limit) ?></span>
                <a href="?page_tour=<?= $page_tour+1 ?>" class="btn btn-sm btn-outline-light <?= $page_tour >= ceil($totalTournaments / $limit) ? 'disabled' : '' ?>">Next</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th>Name</th>
                            <th>Start Date</th>
                            <th>Days</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($t = $tournaments->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['name']) ?></td>
                                <td><?= htmlspecialchars($t['start_date']) ?></td>
                                <td><?= $t['num_days'] ?></td>
                                <td><?= htmlspecialchars($t['location']) ?></td>
                                <td><span class="badge bg-<?= tournamentStatus($t['start_date'], $t['num_days']) === 'Ongoing' ? 'success' : (tournamentStatus($t['start_date'], $t['num_days']) === 'Upcoming' ? 'warning' : 'secondary') ?>">
                                    <?= tournamentStatus($t['start_date'], $t['num_days']) ?>
                                </span></td>
                                <td class="text-end">
                                    <a href="?edit_tournament=<?= $t['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="?delete_tournament=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this tournament?')">Delete</a>
                                    <a href="participants.php?tournament_id=<?= $t['id'] ?>" class="btn btn-sm btn-info">View Participants</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Generate password
function genPass() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let pass = '';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById("password").value = pass;
}

// Toggle Registered District Heads
document.getElementById('toggleUsersBtn')?.addEventListener('click', function() {
    const section = document.getElementById('usersTableSection');
    const icon = this.querySelector('i');
    const textSpan = this.querySelector('span');

    const isHidden = section.classList.contains('d-none');

    section.classList.toggle('d-none', !isHidden);
    this.classList.toggle('btn-outline-primary', isHidden);
    this.classList.toggle('btn-outline-secondary', !isHidden);
    
    icon.classList.toggle('bi-chevron-down', isHidden);
    icon.classList.toggle('bi-chevron-up', !isHidden);

    textSpan.textContent = isHidden 
        ? `Hide Registered District Heads (${<?= $totalDistrictHeads ?>})`
        : `View Registered District Heads (${<?= $totalDistrictHeads ?>})`;
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>