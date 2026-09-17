<?php
header('Content-Type: application/json');
session_start();

$emptyResponse = [
    'reports' => [],
    'ambulances' => [],
    'hotspots' => [],
    'risk_roads' => [],
    'risk_summary' => [],
    'logs' => []
];

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array_merge($emptyResponse, ['error' => 'Login required.']));
    exit();
}

require_once __DIR__ . "/../Config/db.php";
require_once __DIR__ . "/../includes/road-registry.php";

$periodEnd = new DateTimeImmutable('today');
$periodStart = $periodEnd->modify('-29 days');
$periodStartSql = $periodStart->format('Y-m-d 00:00:00');
$baseRoads = srDhakaDivisionRoadRegistry();

function sr_haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
}

function sr_nearest_point_on_segment(float $lat, float $lng, array $a, array $b): array {
    $latRef = deg2rad(($lat + (float)$a[0] + (float)$b[0]) / 3.0);
    $scaleX = max(0.01, cos($latRef));
    $px = $lng * $scaleX; $py = $lat;
    $ax = (float)$a[1] * $scaleX; $ay = (float)$a[0];
    $bx = (float)$b[1] * $scaleX; $by = (float)$b[0];
    $dx = $bx - $ax; $dy = $by - $ay;
    $den = ($dx * $dx) + ($dy * $dy);
    $t = $den > 0 ? ((($px - $ax) * $dx) + (($py - $ay) * $dy)) / $den : 0.0;
    $t = max(0.0, min(1.0, $t));
    $nearestX = $ax + ($t * $dx);
    $nearestLat = $ay + ($t * $dy);
    $nearestLng = $nearestX / $scaleX;
    return [
        'latitude' => $nearestLat,
        'longitude' => $nearestLng,
        'distance_km' => sr_haversine_km($lat, $lng, $nearestLat, $nearestLng)
    ];
}

function sr_nearest_point_on_road(float $lat, float $lng, array $road): array {
    $best = ['latitude' => $lat, 'longitude' => $lng, 'distance_km' => PHP_FLOAT_MAX];
    $points = $road['points'] ?? [];
    if (count($points) < 2) {
        $p = $road['display_start_point'] ?? [$lat, $lng];
        return [
            'latitude' => (float)$p[0],
            'longitude' => (float)$p[1],
            'distance_km' => sr_haversine_km($lat, $lng, (float)$p[0], (float)$p[1])
        ];
    }
    for ($i = 0; $i < count($points) - 1; $i++) {
        $candidate = sr_nearest_point_on_segment($lat, $lng, $points[$i], $points[$i + 1]);
        if ($candidate['distance_km'] < $best['distance_km']) $best = $candidate;
    }
    return $best;
}

function sr_normalize_road_text(string $text): string {
    $text = strtolower(trim($text));
    $text = str_replace(['–', '—', '_', '/', '.', ','], ['-', '-', ' ', ' ', ' ', ' '], $text);
    $text = preg_replace('/\brd\b/u', 'road', $text);
    $text = preg_replace('/\bave\b/u', 'avenue', $text);
    $text = preg_replace('/\bhwy\b/u', 'highway', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

function sr_named_road_index(string $text, array $roads): ?int {
    $text = sr_normalize_road_text($text);
    if ($text === '') return null;
    foreach ($roads as $index => $road) {
        $candidates = array_merge([(string)$road['road_name']], ($road['aliases'] ?? []));
        foreach ($candidates as $alias) {
            $aliasNorm = sr_normalize_road_text((string)$alias);
            if ($aliasNorm !== '' && (str_contains($text, $aliasNorm) || (strlen($text) >= 8 && str_contains($aliasNorm, $text)))) return $index;
        }
    }
    return null;
}

function sr_match_road(float $lat, float $lng, string $roadText, array $roads): ?array {
    $namedIndex = sr_named_road_index($roadText, $roads);
    if ($namedIndex !== null) {
        $nearest = sr_nearest_point_on_road($lat, $lng, $roads[$namedIndex]);
        return array_merge(['index' => $namedIndex, 'match' => 'road_name'], $nearest);
    }

    $best = null;
    foreach ($roads as $index => $road) {
        $nearest = sr_nearest_point_on_road($lat, $lng, $road);
        if ($best === null || $nearest['distance_km'] < $best['distance_km']) {
            $best = array_merge(['index' => $index, 'match' => 'gps'], $nearest);
        }
    }
    // GPS-only matching is intentionally conservative. If it does not match,
    // the report is still counted as its own dynamic road/spot below.
    return ($best !== null && $best['distance_km'] <= 1.75) ? $best : null;
}

function sr_risk_for_count(int $count): array {
    if ($count >= 6) return ['risk' => 'high', 'color' => '#ff3b5c'];
    if ($count >= 3) return ['risk' => 'caution', 'color' => '#ffd166'];
    return ['risk' => 'low', 'color' => '#19e58c'];
}

function sr_dynamic_road_key(string $text): string {
    $norm = sr_normalize_road_text($text);
    if ($norm === '') $norm = 'unidentified-road';
    return 'dynamic-' . substr(sha1($norm), 0, 12);
}

$data = $emptyResponse;
$liveCounts = array_fill(0, count($baseRoads), 0);
$historyCounts = array_fill(0, count($baseRoads), 0);
$dynamicRoads = [];
$registeredMatchedReports = 0;
$dynamicRecentReports = 0;
$historyAccidents = 0;
$roadIndexById = [];
foreach ($baseRoads as $index => $road) $roadIndexById[$road['id']] = $index;

try {
    $historyStmt = $conn->prepare("SELECT road_id, COUNT(*) AS total FROM road_accident_history WHERE occurred_at >= ? GROUP BY road_id");
    $historyStmt->bind_param('s', $periodStartSql);
    $historyStmt->execute();
    $historyResult = $historyStmt->get_result();
    while ($row = $historyResult->fetch_assoc()) {
        $roadId = (string)$row['road_id'];
        if (isset($roadIndexById[$roadId])) {
            $idx = $roadIndexById[$roadId];
            $historyCounts[$idx] = (int)$row['total'];
            $historyAccidents += (int)$row['total'];
        }
    }

    // Every recent report is counted. Registered corridors merge by alias/GPS;
    // any other road name is grouped dynamically so SafeRoad is not limited to
    // the predefined Dhaka Division list.
    $recentStmt = $conn->prepare("SELECT id, road_name, location, latitude, longitude FROM reports WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND created_at >= ? ORDER BY created_at DESC");
    $recentStmt->bind_param('s', $periodStartSql);
    $recentStmt->execute();
    $recentResult = $recentStmt->get_result();
    while ($row = $recentResult->fetch_assoc()) {
        $lat = (float)$row['latitude'];
        $lng = (float)$row['longitude'];
        $roadText = trim((string)($row['road_name'] ?: $row['location']));
        $match = sr_match_road($lat, $lng, $roadText, $baseRoads);
        if ($match !== null) {
            $liveCounts[$match['index']]++;
            $registeredMatchedReports++;
            continue;
        }

        $key = sr_dynamic_road_key($roadText);
        if (!isset($dynamicRoads[$key])) {
            $dynamicRoads[$key] = [
                'id' => $key,
                'road_name' => $roadText !== '' ? $roadText : ('Reported Road #' . (int)$row['id']),
                'district' => 'New reported spot',
                'count' => 0,
                'lat_sum' => 0.0,
                'lng_sum' => 0.0
            ];
        }
        $dynamicRoads[$key]['count']++;
        $dynamicRoads[$key]['lat_sum'] += $lat;
        $dynamicRoads[$key]['lng_sum'] += $lng;
        $dynamicRecentReports++;
    }

    $usingDatabaseCounts = ($historyAccidents + $registeredMatchedReports + $dynamicRecentReports) > 0;
    $riskRoads = [];
    foreach ($baseRoads as $index => $road) {
        $databaseCount = (int)$historyCounts[$index] + (int)$liveCounts[$index];
        $count = $usingDatabaseCounts ? $databaseCount : (int)$road['baseline_accidents'];
        $riskMeta = sr_risk_for_count($count);
        $points = $road['points'];
        $riskRoads[] = [
            'id' => $road['id'],
            'road_name' => $road['road_name'],
            'district' => $road['district'] ?? 'Dhaka Division',
            'registered' => true,
            'accidents' => $count,
            'history_accidents' => (int)$historyCounts[$index],
            'live_accidents' => (int)$liveCounts[$index],
            'risk' => $riskMeta['risk'],
            'color' => $riskMeta['color'],
            'points' => $points,
            'display_start_point' => $road['display_start_point'] ?? $points[0],
            'start_point' => $points[0],
            'end_point' => $points[count($points) - 1],
            'start_label' => $road['start_label'],
            'end_label' => $road['end_label']
        ];
    }

    foreach ($dynamicRoads as $dynamic) {
        $count = (int)$dynamic['count'];
        $lat = $count > 0 ? ((float)$dynamic['lat_sum'] / $count) : 0.0;
        $lng = $count > 0 ? ((float)$dynamic['lng_sum'] / $count) : 0.0;
        $riskMeta = sr_risk_for_count($count);
        $riskRoads[] = [
            'id' => $dynamic['id'],
            'road_name' => $dynamic['road_name'],
            'district' => $dynamic['district'],
            'registered' => false,
            'accidents' => $count,
            'history_accidents' => 0,
            'live_accidents' => $count,
            'risk' => $riskMeta['risk'],
            'color' => $riskMeta['color'],
            'points' => [[$lat, $lng]],
            'display_start_point' => [$lat, $lng],
            'start_point' => [$lat, $lng],
            'end_point' => [$lat, $lng],
            'start_label' => 'First reported GPS',
            'end_label' => 'Dynamic road/spot'
        ];
    }

    $totalAccidents = array_sum(array_column($riskRoads, 'accidents'));
    $highRoads = count(array_filter($riskRoads, static fn(array $road): bool => $road['risk'] === 'high'));
    $cautionRoads = count(array_filter($riskRoads, static fn(array $road): bool => $road['risk'] === 'caution'));
    $matchedRecentReports = $registeredMatchedReports + $dynamicRecentReports;

    $data['risk_roads'] = $riskRoads;
    $data['risk_summary'] = [
        'period_start' => $periodStart->format('Y-m-d'),
        'period_end' => $periodEnd->format('Y-m-d'),
        'days' => 30,
        'coverage' => 'Dhaka Division',
        'registered_roads' => count($baseRoads),
        'dynamic_roads' => count($dynamicRoads),
        'tracked_roads' => count($riskRoads),
        'total_accidents' => $totalAccidents,
        'red_roads' => $highRoads,
        'yellow_roads' => $cautionRoads,
        'green_roads' => count($riskRoads) - $highRoads - $cautionRoads,
        'history_accidents' => $historyAccidents,
        'registered_matched_reports' => $registeredMatchedReports,
        'dynamic_reports' => $dynamicRecentReports,
        'matched_live_reports' => $matchedRecentReports,
        'new_accidents' => $matchedRecentReports,
        'data_mode' => $usingDatabaseCounts ? 'live_database' : 'demo_fallback',
        'source_label' => $usingDatabaseCounts
            ? ('LIVE DB · ' . count($baseRoads) . ' registered roads + ' . count($dynamicRoads) . ' dynamic')
            : 'Emergency fallback baseline',
        'is_demo_history' => true,
        'refreshed_at' => date('Y-m-d H:i:s'),
        'refresh_seconds' => 5
    ];

    $reportQuery = "SELECT r.*, u.name AS reporter_name
        FROM reports r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.latitude IS NOT NULL AND r.longitude IS NOT NULL
        ORDER BY r.created_at DESC
        LIMIT 250";
    $reportResult = $conn->query($reportQuery);
    while ($row = $reportResult->fetch_assoc()) {
        $lat = (float)$row['latitude'];
        $lng = (float)$row['longitude'];
        $roadText = trim((string)($row['road_name'] ?: $row['location']));
        $match = sr_match_road($lat, $lng, $roadText, $baseRoads);
        $matchedRoadId = null;
        $matchedRoadName = null;
        $snapDistanceM = null;
        $roadMatchType = null;
        if ($match !== null) {
            $matchedRoadId = $baseRoads[$match['index']]['id'];
            $matchedRoadName = $baseRoads[$match['index']]['road_name'];
            $snapDistanceM = (int)round(((float)$match['distance_km']) * 1000);
            $roadMatchType = $match['match'];
        } else {
            $matchedRoadId = sr_dynamic_road_key($roadText);
            $matchedRoadName = $roadText !== '' ? $roadText : 'New reported spot';
            $snapDistanceM = 0;
            $roadMatchType = 'dynamic';
        }

        $data['reports'][] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'road_name' => $row['road_name'] ?: $row['title'],
            'location' => $row['location'],
            'description' => $row['description'],
            'latitude' => $lat,
            'longitude' => $lng,
            'map_latitude' => $lat,
            'map_longitude' => $lng,
            'matched_road_id' => $matchedRoadId,
            'matched_road_name' => $matchedRoadName,
            'road_match_type' => $roadMatchType,
            'snap_distance_m' => $snapDistanceM,
            'severity' => strtolower((string)$row['severity']),
            'emergency_type' => $row['emergency_type'],
            'people_injured' => (int)($row['people_injured'] ?? 0),
            'priority_score' => (int)($row['priority_score'] ?? 0),
            'ai_status' => $row['ai_verdict'],
            'confidence_score' => (int)$row['ai_score'],
            'report_status' => $row['status'],
            'assigned_ambulance_id' => $row['assigned_ambulance_id'] ? (int)$row['assigned_ambulance_id'] : null,
            'eta' => $row['ambulance_eta_minutes'] ? (int)$row['ambulance_eta_minutes'] : null,
            'reporter_name' => $row['reporter_name'] ?: 'Citizen',
            'created_at' => $row['created_at']
        ];
    }

    $ambulanceResult = $conn->query("SELECT * FROM ambulance_locations WHERE status <> 'offline' ORDER BY id ASC");
    while ($row = $ambulanceResult->fetch_assoc()) {
        $data['ambulances'][] = [
            'id' => (int)$row['id'],
            'ambulance_name' => $row['ambulance_name'],
            'driver_name' => $row['driver_name'],
            'phone' => $row['phone'],
            'latitude' => (float)$row['latitude'],
            'longitude' => (float)$row['longitude'],
            'status' => $row['status'],
            'assigned_report_id' => $row['assigned_report_id'] ? (int)$row['assigned_report_id'] : null,
            'destination_latitude' => $row['destination_latitude'] !== null ? (float)$row['destination_latitude'] : null,
            'destination_longitude' => $row['destination_longitude'] !== null ? (float)$row['destination_longitude'] : null,
            'speed_kmh' => (int)($row['speed_kmh'] ?? 40),
            'current_mission' => $row['current_mission'],
            'last_ping' => $row['last_ping'],
            'last_updated' => $row['last_updated']
        ];
    }

    $hotspotQuery = "SELECT
            COALESCE(NULLIF(road_name, ''), location) AS road_name,
            COUNT(*) AS total_reports,
            AVG(latitude) AS avg_latitude,
            AVG(longitude) AS avg_longitude,
            SUM(CASE WHEN severity = 'Critical' THEN 1 ELSE 0 END) AS critical_count,
            AVG(priority_score) AS avg_priority
        FROM reports
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL
        GROUP BY COALESCE(NULLIF(road_name, ''), location)
        HAVING total_reports >= 1
        ORDER BY total_reports DESC, critical_count DESC, avg_priority DESC
        LIMIT 20";
    $hotspotResult = $conn->query($hotspotQuery);
    while ($row = $hotspotResult->fetch_assoc()) {
        $data['hotspots'][] = [
            'road_name' => $row['road_name'],
            'total_reports' => (int)$row['total_reports'],
            'avg_latitude' => (float)$row['avg_latitude'],
            'avg_longitude' => (float)$row['avg_longitude'],
            'critical_count' => (int)$row['critical_count'],
            'avg_priority' => round((float)$row['avg_priority'])
        ];
    }

    $logResult = $conn->query("SELECT l.*, a.ambulance_name, r.title AS report_title FROM response_logs l LEFT JOIN ambulance_locations a ON a.id=l.ambulance_id LEFT JOIN reports r ON r.id=l.report_id ORDER BY l.created_at DESC LIMIT 12");
    while ($row = $logResult->fetch_assoc()) {
        $data['logs'][] = [
            'action' => $row['action'],
            'note' => $row['note'],
            'report_id' => $row['report_id'] ? (int)$row['report_id'] : null,
            'ambulance_name' => $row['ambulance_name'],
            'created_at' => $row['created_at']
        ];
    }

    echo json_encode($data);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(array_merge($emptyResponse, [
        'risk_roads' => $data['risk_roads'] ?? [],
        'risk_summary' => $data['risk_summary'] ?? [],
        'error' => $e->getMessage()
    ]));
}
?>
