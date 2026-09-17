<?php
// Backward-compatible wrapper for the old button/link name.
$_POST['report_id'] = $_POST['report_id'] ?? $_GET['id'] ?? 0;
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once __DIR__ . "/ai-video-check.php";
?>
