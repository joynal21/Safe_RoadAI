<?php
/**
 * SafeRoad AI - Reverse location lookup for the citizen report map.
 *
 * Primary source: OpenStreetMap Nominatim reverse geocoding.
 * Fallback: the local Dhaka Division road registry used by SafeRoad analytics.
 * No database change is required.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../includes/road-registry.php';

function srJson(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function srHaversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earth = 6371.0088;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function srNearestRegistryRoad(float $lat, float $lng): ?array {
    $best = null;
    foreach (srDhakaDivisionRoadRegistry() as $road) {
        $roadBest = INF;
        foreach (($road['points'] ?? []) as $point) {
            if (!is_array($point) || count($point) < 2) continue;
            $roadBest = min($roadBest, srHaversineKm($lat, $lng, (float)$point[0], (float)$point[1]));
        }
        if (!empty($road['display_start_point'])) {
            $p = $road['display_start_point'];
            $roadBest = min($roadBest, srHaversineKm($lat, $lng, (float)$p[0], (float)$p[1]));
        }
        if ($best === null || $roadBest < $best['distance_km']) {
            $best = [
                'road_name' => $road['road_name'] ?? '',
                'district' => $road['district'] ?? 'Dhaka Division',
                'distance_km' => $roadBest,
            ];
        }
    }
    // Only call it a road match when the click is reasonably close to a known corridor.
    return ($best && $best['distance_km'] <= 3.0) ? $best : null;
}

function srFetchUrl(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en',
                'User-Agent: SafeRoadAI-Academic-Project/1.0 (local XAMPP classroom demo)'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        // Common XAMPP CA bundle locations on Windows/Linux. This keeps TLS
        // verification enabled instead of using an insecure certificate bypass.
        $caCandidates = [
            dirname(PHP_BINARY) . '/extras/ssl/cacert.pem',
            dirname(PHP_BINARY) . '/cacert.pem',
            dirname(dirname(PHP_BINARY)) . '/apache/bin/curl-ca-bundle.crt',
        ];
        foreach ($caCandidates as $caFile) {
            if (is_file($caFile)) { curl_setopt($ch, CURLOPT_CAINFO, $caFile); break; }
        }
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_string($body) && $body !== '' && $status >= 200 && $status < 300) return $body;
    }

    if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => "Accept: application/json\r\nAccept-Language: en\r\nUser-Agent: SafeRoadAI-Academic-Project/1.0\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (is_string($body) && $body !== '') return $body;
    }
    return null;
}

$latRaw = $_GET['lat'] ?? null;
$lngRaw = $_GET['lng'] ?? null;
if ($latRaw === null || $lngRaw === null || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
    srJson(['ok' => false, 'message' => 'Latitude and longitude are required.'], 400);
}

$lat = (float)$latRaw;
$lng = (float)$lngRaw;
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    srJson(['ok' => false, 'message' => 'Invalid GPS coordinates.'], 400);
}

// Reverse geocoding is intentionally requested only after a deliberate map click/GPS action.
$url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
    'format' => 'jsonv2',
    'lat' => number_format($lat, 7, '.', ''),
    'lon' => number_format($lng, 7, '.', ''),
    'zoom' => 18,
    'addressdetails' => 1,
    'namedetails' => 1,
    'accept-language' => 'en',
]);

$body = srFetchUrl($url);
if ($body !== null) {
    $json = json_decode($body, true);
    if (is_array($json) && !empty($json['display_name'])) {
        $a = is_array($json['address'] ?? null) ? $json['address'] : [];
        $road = trim((string)($a['road'] ?? $a['pedestrian'] ?? $a['footway'] ?? $a['path'] ?? $a['residential'] ?? ''));
        $landmark = trim((string)($a['amenity'] ?? $a['building'] ?? $a['shop'] ?? $a['tourism'] ?? $a['leisure'] ?? $a['office'] ?? $a['historic'] ?? ''));
        $areaParts = [];
        foreach (['suburb','neighbourhood','quarter','city_district','town','city','municipality','county','state_district','state'] as $key) {
            $value = trim((string)($a[$key] ?? ''));
            if ($value !== '' && !in_array($value, $areaParts, true)) $areaParts[] = $value;
        }
        $area = implode(', ', array_slice($areaParts, 0, 3));
        if ($road === '') {
            $fallback = srNearestRegistryRoad($lat, $lng);
            if ($fallback) $road = $fallback['road_name'];
        }
        srJson([
            'ok' => true,
            'source' => 'OpenStreetMap',
            'road_name' => $road,
            'location' => trim((string)$json['display_name']),
            'nearest_landmark' => $landmark,
            'area' => $area,
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }
}

$fallback = srNearestRegistryRoad($lat, $lng);
if ($fallback) {
    srJson([
        'ok' => true,
        'source' => 'SafeRoad local road registry',
        'road_name' => $fallback['road_name'],
        'location' => $fallback['road_name'] . ', ' . $fallback['district'] . ' (GPS ' . number_format($lat, 5) . ', ' . number_format($lng, 5) . ')',
        'nearest_landmark' => '',
        'area' => $fallback['district'],
        'distance_km' => round($fallback['distance_km'], 2),
        'lat' => $lat,
        'lng' => $lng,
    ]);
}

srJson([
    'ok' => true,
    'source' => 'GPS fallback',
    'road_name' => '',
    'location' => 'Selected GPS: ' . number_format($lat, 5) . ', ' . number_format($lng, 5),
    'nearest_landmark' => '',
    'area' => '',
    'lat' => $lat,
    'lng' => $lng,
    'message' => 'Online road lookup was unavailable. GPS was still captured correctly.'
]);
