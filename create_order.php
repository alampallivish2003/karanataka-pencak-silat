<!-- create_order.php -->
<?php
session_start();
include 'db_config.php';

if (isset($_SESSION['role']) && $_SESSION['role'] == 'district_head' && isset($_POST['tournament_id'])) {
    $tournament_id = (int)$_POST['tournament_id'];
    $user_id = $_SESSION['user_id'];

    // Calculate total fee for pending entries in this tournament for this district head
    $sql = "SELECT SUM(fee) AS total_fee FROM entries e LEFT JOIN players p ON e.player_id = p.id 
            WHERE e.tournament_id = $tournament_id AND p.district_head_id = $user_id AND e.payment_status = 'pending'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $amount = ($row['total_fee'] ?? 0) * 100; // in paise

    if ($amount > 0) {
        $key_id = 'rzp_test_XXXXXXXXXXXX'; // Replace with your Razorpay test key_id
        $secret = 'XXXXXXXXXXXXXXXXXXXX'; // Replace with your Razorpay test secret

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.razorpay.com/v1/orders");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $key_id . ":" . $secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'amount' => $amount,
            'currency' => 'INR',
            'receipt' => 'rcpt_' . time()
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);

        $order = json_decode($response, true);
        if (isset($order['id'])) {
            // Store order_id in session for verification
            $_SESSION['razorpay_order_id'] = $order['id'];
            $_SESSION['payment_tournament_id'] = $tournament_id;
            echo json_encode(['order_id' => $order['id'], 'amount' => $amount / 100]);
        } else {
            echo json_encode(['error' => 'Failed to create order']);
        }
    } else {
        echo json_encode(['error' => 'No pending fees']);
    }
}
?>