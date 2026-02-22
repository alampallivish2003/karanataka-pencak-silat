<!-- update_status.php -->
<?php
session_start();
include 'db_config.php';

if (isset($_SESSION['role']) && $_SESSION['role'] == 'district_head' && isset($_POST['status']) && isset($_POST['tournament_id'])) {
    $status = $_POST['status'];
    $tournament_id = (int)$_POST['tournament_id'];
    $user_id = $_SESSION['user_id'];

    if ($status == 'cash') {
        $sql = "UPDATE entries e LEFT JOIN players p ON e.player_id = p.id 
                SET e.payment_status = 'cash' 
                WHERE e.tournament_id = $tournament_id AND p.district_head_id = $user_id AND e.payment_status = 'pending'";
        $conn->query($sql);
        echo 'success';
    }
}
?>