<?php
session_start();
require_once __DIR__ . "/../Config/db.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../register.php"); exit();
}
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$role = 'citizen';
$allowedRoles = ['citizen'];
if ($name === '' || $email === '' || $password === '') {
    $_SESSION['error'] = "All fields are required."; header("Location: ../register.php"); exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email address."; header("Location: ../register.php"); exit();
}
if (strlen($password) < 6) {
    $_SESSION['error'] = "Password must be at least 6 characters."; header("Location: ../register.php"); exit();
}
try {
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Email already registered."; header("Location: ../register.php"); exit();
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $hashedPassword, $role, $phone);
    $stmt->execute();
    $_SESSION['success'] = "Registration successful. Please login.";
    header("Location: ../login.php"); exit();
} catch (Throwable $e) {
    $_SESSION['error'] = "Registration failed: " . $e->getMessage(); header("Location: ../register.php"); exit();
}
?>
