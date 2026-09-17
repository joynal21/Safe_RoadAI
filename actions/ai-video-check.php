<?php
session_start();
require_once __DIR__ . "/../Config/db.php";
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
if (($_SESSION['role'] ?? '') !== 'admin') { $_SESSION['error'] = "Only admin can run AI evidence check."; header("Location: ../pages/citizen-dashboard.php"); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: ../pages/admin-reports.php"); exit(); }
$reportId = (int)($_POST['report_id'] ?? 0);
if ($reportId <= 0) { $_SESSION['error'] = "Invalid report ID."; header("Location: ../pages/admin-reports.php"); exit(); }

function saferoadContainsAny(string $text, array $words, int $pointsEach, int $maxPoints, array &$matched): int {
    $points = 0;
    foreach ($words as $word) {
        if (str_contains($text, $word)) { $points += $pointsEach; $matched[] = $word; }
    }
    return min($points, $maxPoints);
}

try {
    $stmt = $conn->prepare("SELECT * FROM reports WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    if (!$report) throw new RuntimeException("Report not found.");

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
    $hasImage = !empty($report['image']) && is_file($uploadDir . $report['image']);
    $hasVideo = !empty($report['video']) && is_file($uploadDir . $report['video']);

    $text = strtolower(($report['title'] ?? '') . ' ' . ($report['road_name'] ?? '') . ' ' . ($report['location'] ?? '') . ' ' . ($report['vehicles_involved'] ?? '') . ' ' . ($report['description'] ?? ''));
    $matched = []; $score = 0; $fakeHits = [];

    if ($hasImage) $score += 24;
    if ($hasVideo) $score += 32;
    if (!$hasImage && !$hasVideo) $score -= 25;

    $accidentWords = ['accident','crash','collision','hit','injured','injury','blood','ambulance','fire','smoke','damaged','overturned','road block','traffic jam','emergency','victim','dead','unconscious','rescue'];
    $vehicleWords = ['car','bus','truck','bike','motorbike','motorcycle','vehicle','van','rickshaw','cng','lorry','microbus'];
    $fakeWords = ['song','music','audio','meme','funny','dance','movie','trailer','cartoon','anime','podcast'];

    $score += saferoadContainsAny($text, $accidentWords, 6, 30, $matched);
    $score += saferoadContainsAny($text, $vehicleWords, 5, 20, $matched);
    if (($report['severity'] ?? '') === 'High') $score += 8;
    if (($report['severity'] ?? '') === 'Critical') $score += 13;
    if ((int)($report['people_injured'] ?? 0) > 0) $score += min(12, (int)$report['people_injured'] * 4);
    if (($report['latitude'] ?? null) !== null && ($report['longitude'] ?? null) !== null) $score += 8;
    if (strlen(trim($report['description'] ?? '')) >= 50) $score += 7;
    if ((int)($report['police_required'] ?? 0) === 1 || (int)($report['fire_service_required'] ?? 0) === 1) $score += 4;
    $score -= saferoadContainsAny($text, $fakeWords, 22, 45, $fakeHits);
    $score = max(0, min(98, $score));

    if (!$hasImage && !$hasVideo && $score < 50) $verdict = 'Invalid Evidence';
    elseif ($score >= 72) $verdict = 'Likely Real';
    elseif ($score >= 45) $verdict = 'Suspicious';
    else $verdict = 'Likely Fake';

    $reasonParts = [];
    $reasonParts[] = $hasVideo ? 'Video evidence found' : 'No video evidence';
    $reasonParts[] = $hasImage ? 'image evidence found' : 'no image evidence';
    if ($matched) $reasonParts[] = 'matched accident/vehicle terms: ' . implode(', ', array_unique($matched));
    if ($fakeHits) $reasonParts[] = 'irrelevant media warning terms: ' . implode(', ', array_unique($fakeHits));
    $reasonParts[] = 'location ' . (($report['latitude'] !== null && $report['longitude'] !== null) ? 'available' : 'missing');
    $reasonParts[] = 'This is a prototype scoring engine, not a trained computer-vision model';
    $reason = implode('. ', $reasonParts) . '.';

    $update = $conn->prepare("UPDATE reports SET ai_verdict = ?, ai_score = ?, ai_reason = ? WHERE id = ?");
    $update->bind_param("sisi", $verdict, $score, $reason, $reportId);
    $update->execute();

    $adminId = (int)$_SESSION['user_id'];
    $note = "AI evidence check result: {$verdict} ({$score}/100).";
    $log = $conn->prepare("INSERT INTO response_logs (report_id, action, note, created_by) VALUES (?, 'AI Check Completed', ?, ?)");
    $log->bind_param("isi", $reportId, $note, $adminId);
    $log->execute();

    $_SESSION['success'] = "AI evidence check complete: $verdict ($score/100).";
    header("Location: ../pages/admin-reports.php"); exit();
} catch (Throwable $e) {
    $_SESSION['error'] = "AI check failed: " . $e->getMessage(); header("Location: ../pages/admin-reports.php"); exit();
}
?>
