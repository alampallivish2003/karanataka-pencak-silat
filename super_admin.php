<!-- super_admin.php -->
<?php
session_start();
include 'db_config.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'super_admin') {
    header('Location: index.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Code similar to admin, but can create admins too

// Create user
if (isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $district_name = $role == 'district_head' ? $_POST['district_name'] : '';
    $sql = "INSERT INTO users (username, password, role, email, phone, district_name) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $username, $pass, $role, $email, $phone, $district_name);
    $stmt->execute();
    $stmt->close();
    // Send email
    $message = "Your login credentials: Username: $username, Password: {$_POST['password']}";
    mail($email, "Login Credentials", $message);
}

// Other functions same as admin
// Create tournament
if (isset($_POST['create_tournament'])) {
    $name = $_POST['name'];
    $start_date = $_POST['start_date'];
    $num_days = (int)$_POST['num_days'];
    $sql = "INSERT INTO tournaments (name, start_date, num_days) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $name, $start_date, $num_days);
    $stmt->execute();
    $stmt->close();
    // Notify district heads
    $districts = $conn->query("SELECT email FROM users WHERE role = 'district_head'");
    while ($d = $districts->fetch_assoc()) {
        mail($d['email'], "New Tournament", "New tournament created: $name");
    }
}

// Update tournament
if (isset($_POST['update_tournament'])) {
    $id = (int)$_POST['id'];
    $name = $_POST['name'];
    $start_date = $_POST['start_date'];
    $num_days = (int)$_POST['num_days'];
    $conn->query("UPDATE tournaments SET name='$name', start_date='$start_date', num_days=$num_days WHERE id=$id");
}

// Delete tournament
if (isset($_GET['delete_tournament'])) {
    $id = (int)$_GET['delete_tournament'];
    $conn->query("DELETE FROM tournaments WHERE id=$id");
}

$users = $conn->query("SELECT * FROM users");
$tournaments = $conn->query("SELECT * FROM tournaments");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2>Super Admin Dashboard</h2>

        <!-- Create User -->
        <h3>Create User</h3>
        <form method="POST">
            <input type="hidden" name="create_user" value="1">
            <div class="row">
                <div class="col">
                    <input type="text" name="username" placeholder="Username" required>
                </div>
                <div class="col">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <div class="col">
                    <select name="role" required>
                        <option value="admin">Admin</option>
                        <option value="district_head">District Head</option>
                    </select>
                </div>
                <div class="col">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="col">
                    <input type="text" name="phone" placeholder="Phone" required>
                </div>
                <div class="col">
                    <input type="text" name="district_name" placeholder="District Name (for district head)">
                </div>
                <div class="col">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </div>
        </form>

        <!-- Users List -->
        <h3>Users</h3>
        <table class="table">
            <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Email</th><th>Phone</th><th>District</th></tr></thead>
            <tbody>
                <?php while ($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['username'] ?></td>
                        <td><?= $row['role'] ?></td>
                        <td><?= $row['email'] ?></td>
                        <td><?= $row['phone'] ?></td>
                        <td><?= $row['district_name'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Tournaments -->
        <h3>Create Tournament</h3>
        <form method="POST">
            <input type="hidden" name="create_tournament" value="1">
            <div class="row">
                <div class="col">
                    <input type="text" name="name" placeholder="Name" required>
                </div>
                <div class="col">
                    <input type="date" name="start_date" required>
                </div>
                <div class="col">
                    <input type="number" name="num_days" placeholder="Num Days" required>
                </div>
                <div class="col">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </div>
        </form>

        <h3>Tournaments</h3>
        <table class="table">
            <thead><tr><th>ID</th><th>Name</th><th>Date</th><th>Days</th><th>Actions</th></tr></thead>
            <tbody>
                <?php while ($row = $tournaments->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['name'] ?></td>
                        <td><?= $row['start_date'] ?></td>
                        <td><?= $row['num_days'] ?></td>
                        <td>
                            <a href="?edit_tournament=<?= $row['id'] ?>">Edit</a>
                            <a href="?delete_tournament=<?= $row['id'] ?>">Delete</a>
                            <a href="bracket.php?tournament_id=<?= $row['id'] ?>">Bracket</a>
                            <a href="download.php?tournament_id=<?= $row['id'] ?>">Download XLS</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php if (isset($_GET['edit_tournament'])): 
            $id = (int)$_GET['edit_tournament'];
            $t = $conn->query("SELECT * FROM tournaments WHERE id=$id")->fetch_assoc();
        ?>
            <h3>Update Tournament</h3>
            <form method="POST">
                <input type="hidden" name="update_tournament" value="1">
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="row">
                    <div class="col">
                        <input type="text" name="name" value="<?= $t['name'] ?>" required>
                    </div>
                    <div class="col">
                        <input type="date" name="start_date" value="<?= $t['start_date'] ?>" required>
                    </div>
                    <div class="col">
                        <input type="number" name="num_days" value="<?= $t['num_days'] ?>" required>
                    </div>
                    <div class="col">
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>