<?php
session_start();
require_once __DIR__ . "/../Config/db.php";
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if (($_SESSION['role'] ?? '') !== 'admin') { $_SESSION['error'] = "Only admin can delete reports."; header("Location: ../pages/citizen-dashboard.php"); exit(); }
if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: ../pages/admin-reports.php"); exit(); }
$reportId = (int)($_POST['report_id'] ?? 0);
if ($reportId <= 0) { $_SESSION['error'] = "Invalid report ID."; header("Location: ../pages/admin-reports.php"); exit(); }
try {
    $stmt = $conn->prepare("SELECT image, video FROM reports WHERE id = ?");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        foreach (['image','video'] as $field) {
            if (!empty($row[$field])) {
                $relative = ltrim(str_replace('\\', '/', (string)$row[$field]), '/');
                if (!str_contains($relative, '..') && str_starts_with($relative, 'reports/')) {
                    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    if (is_file($path)) unlink($path);
                }
            }
        }
    }
    $delete = $conn->prepare("DELETE FROM reports WHERE id = ?");
    $delete->bind_param("i", $reportId);
    $delete->execute();
    $_SESSION['success'] = "Report deleted.";
    header("Location: ../pages/admin-reports.php"); exit();
} catch (Throwable $e) {
    $_SESSION['error'] = "Delete failed: " . $e->getMessage(); header("Location: ../pages/admin-reports.php"); exit();
}
?>
