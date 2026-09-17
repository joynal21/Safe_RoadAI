<?php
require_once __DIR__ . "/auth-check.php";
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: citizen-dashboard.php");
    exit();
}
?>
