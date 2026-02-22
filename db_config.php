<?php
// ======================================================
// DATABASE CONFIGURATION (XAMPP - PORT 3307)
// ======================================================

$servername = "127.0.0.1";
$db_username = "root";
$db_password = "";
$dbname      = "u730102058_sports";
$port        = 3307; // IMPORTANT: Your MySQL runs on 3307

// ======================================================
// CONNECT TO MYSQL SERVER (WITHOUT SELECTING DB)
// ======================================================

$conn = new mysqli($servername, $db_username, $db_password, "", $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ======================================================
// CREATE DATABASE IF NOT EXISTS
// ======================================================

if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`")) {
    die("Error creating database: " . $conn->error);
}

$conn->select_db($dbname);

// ======================================================
// TABLE DEFINITIONS
// ======================================================

$tables = [

    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('super_admin','admin','district_head') NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        district_name VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_role (role),
        INDEX idx_district (district_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS tournaments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        num_days INT NOT NULL,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_start_date (start_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unique_id VARCHAR(20) UNIQUE NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        aadhar_id VARCHAR(20) NOT NULL,
        gender ENUM('male','female','other') NOT NULL,
        guardian VARCHAR(100) NOT NULL,
        district_name VARCHAR(50) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        dob DATE NOT NULL,
        ipsf_id VARCHAR(20) NOT NULL,
        image VARCHAR(255) DEFAULT NULL,
        district_head_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (district_head_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_unique_id (unique_id),
        INDEX idx_district_head (district_head_id),
        INDEX idx_dob (dob)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT NOT NULL,
        player_id INT NOT NULL,
        weight DECIMAL(5,2) DEFAULT NULL,
        height DECIMAL(5,2) DEFAULT NULL,
        category VARCHAR(50) DEFAULT NULL,
        blood_group VARCHAR(5) DEFAULT NULL,
        event_type ENUM('individual','team') NOT NULL,
        sub_event VARCHAR(50) NOT NULL,
        team_id INT DEFAULT NULL,
        payment_status ENUM('pending','paid_online','cash') DEFAULT 'pending',
        fee DECIMAL(10,2) DEFAULT 100.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
        INDEX idx_tournament (tournament_id),
        INDEX idx_player (player_id),
        INDEX idx_team (team_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entry_id INT NOT NULL UNIQUE,
        score INT DEFAULT 0,
        result VARCHAR(50) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

// Create tables
foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        die("Table creation failed: " . $conn->error);
    }
}

// ======================================================
// INSERT DEFAULT USERS SAFELY
// ======================================================

$defaults = [
    ['username' => 'super', 'password' => 'super123', 'role' => 'super_admin'],
    ['username' => 'admin', 'password' => 'admin123', 'role' => 'admin']
];

$stmt = $conn->prepare("INSERT IGNORE INTO users (username, password, role) VALUES (?, ?, ?)");

foreach ($defaults as $user) {
    $hashed = password_hash($user['password'], PASSWORD_DEFAULT);
    $stmt->bind_param("sss", $user['username'], $hashed, $user['role']);
    $stmt->execute();
}

$stmt->close();

// Uncomment below only for testing
// echo "Database initialized successfully!";
?>
