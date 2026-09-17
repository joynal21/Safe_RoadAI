<?php
function sr_base_path(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    return str_contains($script, '/pages/') || str_contains($script, '/actions/') || str_contains($script, '/api/') ? '../' : '';
}
function sr_page_start(string $title, string $active = ''): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $base = sr_base_path();
    $role = $_SESSION['role'] ?? 'guest';
    $name = $_SESSION['user_name'] ?? 'User';
    $initial = strtoupper(substr($name, 0, 1));
    $isAdmin = $role === 'admin';
    $items = $isAdmin ? [
        ['admin-dashboard.php','Command Center','📊','dashboard'],
        ['admin-reports.php','Incidents','🚨','reports'],
        ['map.php','Live Map','🗺️','map'],
        ['ambulance-tracker.php','Fleet Tracking','🚑','ambulance'],
        ['analytics.php','Hotspots','📈','analytics'],
        ['admin-users.php','Users','👥','users'],
        ['admin-control-panel.php','Demo Control','🧭','control'],
    ] : [
        ['citizen-dashboard.php','Citizen Home','🏠','dashboard'],
        ['submit-report.php','Report Accident','🚨','submit'],
        ['my-reports.php','My Reports','🧾','reports'],
        ['track-report.php','Track Status','📍','track'],
        ['map.php','Live Map','🗺️','map'],
        ['ambulance-tracker.php','Ambulance Tracking','🚑','ambulance'],
    ];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . htmlspecialchars($title) . ' - SafeRoad AI</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . $base . 'assets/css/app.css?v=9">';
    echo '</head><body><div class="sr-layout">';
    echo '<aside class="sr-sidebar" id="srSidebar"><div class="sr-brand"><div class="sr-logo">SR</div><div><h2>SafeRoad AI</h2><small>Emergency Command Center</small></div></div><nav class="sr-menu">';
    foreach ($items as $item) {
        [$href,$label,$icon,$key] = $item; $cls = $active === $key ? 'active' : '';
        echo '<a class="' . $cls . '" href="' . htmlspecialchars($href) . '"><span>' . $icon . '</span>' . htmlspecialchars($label) . '</a>';
    }
    echo '<a href="' . $base . 'logout.php"><span>↪</span>Logout</a></nav><div class="sr-sidebar-footer"><strong>Demo login</strong><br>Admin: admin@saferoad.test / admin123<br>Citizen: citizen@saferoad.test / user123</div></aside>';
    echo '<main class="sr-main"><header class="sr-topbar"><div class="toolbar"><button class="btn light mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button><h1>' . htmlspecialchars($title) . '</h1></div><div class="sr-user"><div class="status-light">System Live</div><div class="sr-avatar">' . htmlspecialchars($initial) . '</div><span>' . htmlspecialchars($name) . ' · ' . htmlspecialchars(ucfirst($role)) . '</span></div></header><section class="sr-content">';
    sr_flash_messages();
}
function sr_page_end(): void { $base = sr_base_path(); echo '</section></main></div><script src="' . $base . 'assets/js/app.js?v=7"></script></body></html>'; }
function sr_flash_messages(): void { if (session_status() === PHP_SESSION_NONE) session_start(); if (isset($_SESSION['success'])) { echo '<div class="alert success">' . htmlspecialchars($_SESSION['success']) . '</div>'; unset($_SESSION['success']); } if (isset($_SESSION['error'])) { echo '<div class="alert error">' . htmlspecialchars($_SESSION['error']) . '</div>'; unset($_SESSION['error']); }}
function sr_badge(string $value): string { $slug = strtolower(str_replace([' ', '_'], '-', trim($value))); $slug = preg_replace('/[^a-z0-9\-]/', '', $slug); return '<span class="badge ' . htmlspecialchars($slug) . '">' . htmlspecialchars($value) . '</span>'; }
function sr_count(mysqli $conn, string $sql): int { $r = $conn->query($sql); $row = $r ? $r->fetch_assoc() : ['total'=>0]; return (int)($row['total'] ?? 0); }
function sr_time(?string $value): string { if (!$value) return 'N/A'; return date('d M Y, h:i A', strtotime($value)); }
function sr_priority_label(int $score): string { if ($score >= 85) return 'Critical'; if ($score >= 65) return 'High'; if ($score >= 40) return 'Medium'; return 'Low'; }
?>
