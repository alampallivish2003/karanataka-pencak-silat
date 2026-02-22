<?php
// login.php
session_start();
include 'db_config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['forgot'])) {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $error = "Please enter your email.";
        } else {
            $stmt = $conn->prepare("SELECT username FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $success = "A password reset link has been sent to <strong>$email</strong>.<br>
                            (For demo/testing: your password remains the same)";
            } else {
                $error = "No account found with this email.";
            }
            $stmt->close();
        }
    } else {
        // Normal login
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];

                if ($user['role'] === 'admin' || $user['role'] === 'super_admin') {
                    header('Location: admin.php');
                } else {
                    header('Location: district_head.php');
                }
                exit();
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "User not found.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KSPSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <h3 class="text-center mb-4">KSPSA Login</h3>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">Sign In</button>
                    </form>

                    <hr>

                    <form method="POST">
                        <input type="hidden" name="forgot" value="1">
                        <div class="mb-3">
                            <label class="form-label">Forgot Password?</label>
                            <input type="email" name="email" class="form-control" placeholder="Enter registered email" required>
                        </div>
                        <button type="submit" class="btn btn-outline-secondary w-100">Send Reset Link</button>
                    </form>

                    <div class="text-center mt-4 small text-muted">
                        Default credentials (demo):<br>
                        Admin → <code>admin / admin123</code><br>
                        District Head → <code>dh_bellary / demo123</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>