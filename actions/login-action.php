<?php
session_start();
require_once __DIR__ . "/../Config/db.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php"); exit();
}
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
if ($email === '' || $password === '') {
    $_SESSION['error'] = "Email and password are required."; header("Location: ../login.php"); exit();
}
try {
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows !== 1) {
        $_SESSION['error'] = "No account found with this email."; header("Location: ../login.php"); exit();
    }
    $user = $result->fetch_assoc();
    if (!password_verify($password, $user['password'])) {
        $_SESSION['error'] = "Incorrect password."; header("Location: ../login.php"); exit();
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    header($user['role'] === 'admin' ? "Location: ../pages/admin-dashboard.php" : "Location: ../pages/citizen-dashboard.php");
    exit();
} catch (Throwable $e) {
    $_SESSION['error'] = "Login failed: " . $e->getMessage(); header("Location: ../login.php"); exit();
}
?>
