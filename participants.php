<?php
// participants.php - Professional UI + Full Event List + Updated Age/Weight Logic (2025)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'district_head'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['tournament_id']) || !is_numeric($_GET['tournament_id'])) {
    die("<div class='alert alert-danger text-center m-5'>Invalid or missing Tournament ID</div>");
}

$tournament_id = (int)$_GET['tournament_id'];

// Fetch tournament name
$t_name = $conn->prepare("SELECT name FROM tournaments WHERE id = ?");
$t_name->bind_param("i", $tournament_id);
$t_name->execute();
$res = $t_name->get_result();

$tournament_name = $res->fetch_assoc()['name'] ?? 'Unknown Tournament';
$t_name->close();

// Fetch participants
$query = "
    SELECT 
    p.unique_id,
    p.first_name,
    p.last_name,
    p.gender,
    p.dob,
    u.district_name AS district,
    e.sub_event AS event_name,
    e.weight_class,
    e.weight,
    e.height
FROM entries e
INNER JOIN players p ON e.player_id = p.id
LEFT JOIN users u ON p.district_head_id = u.id
WHERE e.tournament_id = ?
    ORDER BY p.first_name ASC
";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("<div class='alert alert-danger m-5'>
        <h4>SQL Prepare failed</h4>
        <pre>" . htmlspecialchars($conn->error) . "</pre>
        <br>Query was:<br>
        <pre>" . htmlspecialchars($query) . "</pre>
    </div>");
}
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();

$participants = [];
while ($row = $result->fetch_assoc()) {

    if ($row['dob']) {
        $birth = new DateTime($row['dob']);
        $target = new DateTime(); // or tournament date if you have it
        $interval = $target->diff($birth);
        $row['age_years'] = $interval->y;
    } else {
        $row['age_years'] = null;
    }

    $age = $row['age_years'];

    if ($age <= 6) $row['age_category'] = 'A';
    elseif ($age <= 9) $row['age_category'] = 'B';
    elseif ($age <= 11) $row['age_category'] = 'C';
    elseif ($age <= 13) $row['age_category'] = 'D';
    elseif ($age <= 16) $row['age_category'] = 'E';
    elseif ($age <= 45) $row['age_category'] = 'F';
    elseif ($age <= 60) $row['age_category'] = 'G';
    else $row['age_category'] = 'H';

    $participants[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Participants — <?= htmlspecialchars($tournament_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        body { 
            padding-top: 80px; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }
        .filter-panel {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .table-responsive { 
            max-height: 65vh; 
            overflow-y: auto; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        table th { 
            position: sticky; 
            top: 0; 
            z-index: 10; 
            background: #0d6efd; 
            color: white; 
            font-weight: 600;
        }
        .loading { text-align: center; padding: 4rem; color: #6c757d; }
        .section-title { font-weight: 600; color: #0d6efd; margin-bottom: 1rem; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Filter Panel -->
        <div class="col-lg-3 col-xl-2 filter-panel">
            <h4 class="mb-4 section-title"><i class="bi bi-funnel-fill me-2"></i>Filters</h4>

            <div class="mb-4">
                <label class="form-label fw-bold">Search</label>
                <input type="text" id="searchInput" class="form-control" placeholder="Unique ID, Name, District..." />
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Event</label>
                <select id="eventFilter" class="form-select">
                    <option value="">All Events</option>
                    <option value="tanding">Tanding</option>
                    <option value="free event">Free Event</option>
                    <option value="tungal">Tungal</option>
                    <option value="ganda">Ganda</option>
                    <option value="regu">Regu</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Age Category</label>
                <select id="ageCategory" class="form-select">
                    <option value="">All Age Categories</option>
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

            <div class="mb-4">
                <label class="form-label fw-bold">Weight Class</label>
                <select id="weightClass" class="form-select">
                    <option value="">All Weights</option>
                </select>
                <small id="weightHint" class="form-text d-block mt-1"></small>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Gender</label>
                <select id="gender" class="form-select">
                    <option value="">All Genders</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
            <div class="mb-4">
    <label class="form-label fw-bold">District</label>
    <input type="text" 
           id="districtFilter" 
           class="form-control" 
           placeholder="Search district...">
</div>


            <div class="mb-4">
                <label class="form-label fw-bold">Height Range (cm)</label>
                <div class="input-group">
                    <input type="number" id="heightMin" class="form-control" placeholder="Min" min="50" max="250"/>
                    <span class="input-group-text">–</span>
                    <input type="number" id="heightMax" class="form-control" placeholder="Max" min="50" max="250"/>
                </div>
            </div>

            <button class="btn btn-primary w-100 mb-2" onclick="applyFilters()">
                <i class="bi bi-filter me-1"></i> Apply Filters
            </button>
            <button class="btn btn-success w-100" onclick="downloadExcel()">
                <i class="bi bi-download me-1"></i> Download Excel
            </button>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9 col-xl-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0 section-title">
                    <i class="bi bi-people-fill me-2"></i>
                    Participants – <?= htmlspecialchars($tournament_name) ?>
                </h4>
                <a href="admin.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <div class="table-responsive shadow-sm rounded">
                <table class="table table-hover table-bordered" id="participantsTable">
                   <thead class="table-dark">
    <tr>
        <th>Unique ID</th>
        <th>Name</th>
        <th>District</th>
        <th>Age (years)</th>
        <th>Gender</th>
        <th>Age Category</th>
        <th>Weight Class</th>
        <th>Weight (kg)</th>
        <th>Height (cm)</th>
        <th>Event</th>
    </tr>
</thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

            <div class="text-end mt-4">
                <button class="btn btn-warning btn-lg px-5" id="generateBracketBtn" disabled>
                    <i class="bi bi-brackets me-2"></i> Generate Bracket
                </button>
            </div>

            <form id="bracketForm" action="bracket.php" method="POST" style="display:none;">
                <input type="hidden" name="filtered_data" id="filteredDataInput">
                <input type="hidden" name="tournament_id" value="<?= $tournament_id ?>">
            </form>
        </div>
    </div>
</div>

<script>
// ────────────────────────────────────────────────
// DATA FROM PHP
// ────────────────────────────────────────────────
const allParticipants = <?= json_encode($participants, JSON_NUMERIC_CHECK) ?> || [];
console.log("Participants loaded:", allParticipants.length, "rows");
console.table(allParticipants.slice(0,3)); // show first 3 rows



// Display names
const ageDisplay = {
    "A": "A – SINGA (3–6 yrs)",
    "B": "B – MACAN (7–9 yrs)",
    "C": "C – PRE-TEEN (10–11 yrs)",
    "D": "D – PRE JUNIOR (12–13 yrs)",
    "E": "E – JUNIOR (14–16 yrs)",
    "F": "F – SENIOR (17–45 yrs)",
    "G": "G – MASTER A (46–60 yrs)",
    "H": "H – MASTER B (61+ yrs)"
};

// Detailed weight classes (only shown for C,D,E,F)
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
        "Under 39kg", "A – Over 39kg to 43kg", "B – Over 43kg to 47kg", "C – Over 47kg to 51kg",
        "D – Over 51kg to 55kg", "E – Over 55kg to 59kg", "F – Over 59kg to 63kg", "G – Over 63kg to 67kg",
        "H – Over 67kg to 71kg", "I – Over 71kg to 75kg", "J – Over 75kg to 79kg", "K – Over 79kg to 83kg",
        "L – Over 83kg to 87kg", "OPEN – Over 87kg to 100kg / Above 92kg (F)"
    ],
    "F": [
        "Under 45kg", "A – Over 45kg to 50kg", "B – Over 50kg to 55kg", "C – Over 55kg to 60kg",
        "D – Over 60kg to 65kg", "E – Over 65kg to 70kg", "F – Over 70kg to 75kg", "G – Over 75kg to 80kg",
        "H – Over 80kg to 85kg", "I – Over 85kg to 90kg", "J – Over 90kg to 95kg",
        "OPEN1 – Over 95kg to 110kg (M) / Over 85kg to 100kg (F)", "OPEN2 – Above 110kg (M) / Above 100kg (F)"
    ]
};

// ────────────────────────────────────────────────
// Update weight class filter options
// ────────────────────────────────────────────────
document.getElementById('ageCategory').addEventListener('change', function() {
    const age = this.value;
    const wSelect = document.getElementById('weightClass');
    const hint = document.getElementById('weightHint');

    wSelect.innerHTML = '<option value="">All Weights</option>';
    hint.textContent = '';

    if (!age) {
        wSelect.disabled = false;
        return;
    }

    if (["C","D","E","F"].includes(age)) {
        wSelect.disabled = false;
        if (weightClassesByAge[age]) {
            weightClassesByAge[age].forEach(cls => {
                const opt = document.createElement('option');
                opt.value = cls.split(' – ')[0]; // class letter or "Under ..."
                opt.textContent = cls;
                wSelect.appendChild(opt);
            });
        }
        hint.textContent = "Select specific weight class";
        hint.className = "form-text text-danger d-block mt-1";
    } else {
        wSelect.disabled = true;
        const opt = document.createElement('option');
        opt.value = "GUIDELINE";
        opt.textContent = "Guideline-based (no fixed class)";
        opt.selected = true;
        wSelect.appendChild(opt);

        if (age === 'A' || age === 'B') {
            hint.textContent = "Young age: max 1 year age diff, 3 cm height, 2 kg weight";
        } else if (age === 'G' || age === 'H') {
            hint.textContent = "Master: max 5 kg weight difference";
        }
        hint.className = "form-text text-info d-block mt-1";
    }
});

// ────────────────────────────────────────────────
// Apply all filters
// ────────────────────────────────────────────────
function applyFilters() { 
    const search      = document.getElementById('searchInput').value.toLowerCase().trim();
    const districtVal = document.getElementById('districtFilter').value.toLowerCase().trim();
    const eventVal    = document.getElementById('eventFilter').value.toLowerCase();
    const ageVal      = document.getElementById('ageCategory').value;
    const weightVal   = document.getElementById('weightClass').value.toLowerCase();
    const genderVal   = document.getElementById('gender').value.toLowerCase();
    const hMin        = parseFloat(document.getElementById('heightMin').value) || 0;
    const hMax        = parseFloat(document.getElementById('heightMax').value) || 999;

    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '';

    const filtered = allParticipants.filter(p => {
        const name = (p.first_name + ' ' + (p.last_name || '')).toLowerCase();
        const uid  = (p.unique_id || '').toLowerCase();
        const district = (p.district || '').toLowerCase();

        return (
            (!search || name.includes(search) || uid.includes(search)) &&
            (!districtVal || district.includes(districtVal)) &&
            (!eventVal || (p.event_name || '').toLowerCase().includes(eventVal)) &&
            (!ageVal   || p.age_category === ageVal) &&
            (!weightVal || 
                (weightVal === "") ||
                (weightVal === "guideline" && !p.weight_class) ||
                (p.weight_class && p.weight_class.toLowerCase().includes(weightVal))
            ) &&
            (!genderVal || (p.gender || '').toLowerCase() === genderVal) &&
            (p.height == null || (p.height >= hMin && p.height <= hMax))
        );
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center text-muted py-5">
                    No matching participants found
                </td>
            </tr>
        `;
    } else {
        filtered.forEach(p => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><code>${p.unique_id || '-'}</code></td>
                <td>${p.first_name || ''} ${p.last_name || ''}</td>
                <td>${p.district || '-'}</td>
                <td>${p.age_years !== null && p.age_years !== undefined ? p.age_years : '-'}</td>
                <td>${p.gender ? p.gender.charAt(0).toUpperCase() + p.gender.slice(1) : '-'}</td>
                <td>${ageDisplay[p.age_category] || '-'}</td>
                <td>${p.weight_class || (['A','B','G','H'].includes(p.age_category) ? 'Guideline-based' : '-')}</td>
                <td>${p.weight ? p.weight + ' kg' : '-'}</td>
                <td>${p.height ? p.height + ' cm' : '-'}</td>
                <td>${p.event_name || '-'}</td>
            `;
            tbody.appendChild(row);
        });
    }

    document.getElementById('generateBracketBtn').disabled = filtered.length === 0;
}

// ────────────────────────────────────────────────
// Excel export
// ────────────────────────────────────────────────
function downloadExcel() {
    const rows = [["Unique ID","Name","District","Age (years)","Gender","Age Category","Weight Class","Weight (kg)","Height (cm)","Event"]];

    document.querySelectorAll('#tableBody tr:not(:has(td[colspan]))').forEach(tr => {
        const row = Array.from(tr.cells).map(td => td.textContent.trim());
        rows.push(row);
    });

    if (rows.length <= 1) {
        alert("No data to export.");
        return;
    }

    const ws = XLSX.utils.aoa_to_sheet(rows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Participants");
    const filename = `participants_<?= str_replace(' ', '_', $tournament_name) ?>_<?= date('Y-m-d') ?>.xlsx`;
    XLSX.writeFile(wb, filename);
}

// ────────────────────────────────────────────────
// Bracket generation (filtered data)
// ────────────────────────────────────────────────
document.getElementById('generateBracketBtn')?.addEventListener('click', function() {
    const rows = document.querySelectorAll('#tableBody tr:not([style*="display: none"]):not(:has(td[colspan]))');
    if (rows.length === 0) return alert("No visible participants to generate bracket.");

    const data = [];
    rows.forEach(tr => {
        const cells = tr.cells;
        data.push({
            unique_id:   cells[0].textContent.trim(),
            name:        cells[1].textContent.trim(),
            gender:      cells[2].textContent.trim(),
            age_category:cells[3].textContent.trim(),
            weight_class:cells[4].textContent.trim(),
            weight:      cells[5].textContent.trim(),
            height:      cells[6].textContent.trim(),
            dob:         cells[7].textContent.trim(),
            event_name:  cells[8].textContent.trim()
        });
    });

    document.getElementById('filteredDataInput').value = JSON.stringify(data);
    document.getElementById('bracketForm').submit();
});

// Initial load
document.addEventListener('DOMContentLoaded', () => {
    applyFilters();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// participants.php - Professional UI + Full Event List + Updated Age/Weight Logic (2025)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'district_head'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['tournament_id']) || !is_numeric($_GET['tournament_id'])) {
    die("<div class='alert alert-danger text-center m-5'>Invalid or missing Tournament ID</div>");
}

$tournament_id = (int)$_GET['tournament_id'];

// Fetch tournament name
$t_name = $conn->prepare("SELECT name, start_date FROM tournaments WHERE id = ?");
$t_name->bind_param("i", $tournament_id);
$t_name->execute();
$res = $t_name->get_result();
$tournament = $res->fetch_assoc();
$tournament_name = $tournament['name'] ?? 'Unknown Tournament';
$tournament_start = $tournament['start_date'] ?? date('Y-m-d'); // fallback
$t_name->close();

// Fetch participants
$query = "
    SELECT 
        p.unique_id,
        p.first_name,
        p.last_name,
        p.gender,
        p.dob,
        u.district_name AS district,
        e.sub_event AS event_name,
        e.weight_class,
        e.weight,
        e.height
    FROM entries e
    INNER JOIN players p ON e.player_id = p.id
    LEFT JOIN users u ON p.district_head_id = u.id
    WHERE e.tournament_id = ?
    ORDER BY p.first_name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tournament_id);
$stmt->execute();
$result = $stmt->get_result();
$participants = [];
while ($row = $result->fetch_assoc()) {
    // Age in full years on tournament start date
    if ($row['dob']) {
        $birth = new DateTime($row['dob']);
        $target = new DateTime($tournament_start);
        $interval = $target->diff($birth);
        $row['age_years'] = $interval->y;
    } else {
        $row['age_years'] = null;
    }

    // Age category (same logic, now using tournament-based years)
    $age = $row['age_years'] ?? 0;
    if ($age <= 6) $row['age_category'] = 'A';
    elseif ($age <= 9) $row['age_category'] = 'B';
    elseif ($age <= 11) $row['age_category'] = 'C';
    elseif ($age <= 13) $row['age_category'] = 'D';
    elseif ($age <= 16) $row['age_category'] = 'E';
    elseif ($age <= 45) $row['age_category'] = 'F';
    elseif ($age <= 60) $row['age_category'] = 'G';
    else $row['age_category'] = 'H';

    $participants[] = $row;
}
$stmt->close();
?>

