<?php
require_once __DIR__ . "/../includes/admin-check.php";
require_once __DIR__ . "/../Config/db.php";
require_once __DIR__ . "/../includes/ui.php";
require_once __DIR__ . "/../includes/road-registry.php";

function srAnalyticsNormalizeRoad(string $text): string {
    $text = strtolower(trim($text));
    $text = str_replace(['–','—','_','/','.',','], ['-','-',' ',' ',' ',' '], $text);
    $text = preg_replace('/\brd\b/u', 'road', $text);
    $text = preg_replace('/\bhwy\b/u', 'highway', $text);
    $text = preg_replace('/\bave\b/u', 'avenue', $text);
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

function srAnalyticsMatchRoad(string $text, array $roads): ?int {
    $text = srAnalyticsNormalizeRoad($text);
    if ($text === '') return null;
    foreach ($roads as $i => $road) {
        $aliases = array_merge([(string)$road['road_name']], $road['aliases'] ?? []);
        foreach ($aliases as $alias) {
            $a = srAnalyticsNormalizeRoad((string)$alias);
            if ($a !== '' && (str_contains($text, $a) || (strlen($text) >= 8 && str_contains($a, $text)))) return $i;
        }
    }
    return null;
}

function srAnalyticsRisk(int $count): string {
    if ($count >= 6) return 'High';
    if ($count >= 3) return 'Caution';
    return 'Low';
}

$roads = srDhakaDivisionRoadRegistry();
$totals = [];
$indexById = [];
foreach ($roads as $i => $road) {
    $indexById[$road['id']] = $i;
    $totals[$i] = [
        'road_name' => $road['road_name'],
        'district' => $road['district'] ?? 'Dhaka Division',
        'history' => 0,
        'new' => 0,
        'registered' => true
    ];
}

$historyResult = $conn->query("SELECT road_id, COUNT(*) total FROM road_accident_history WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY road_id");
while ($row = $historyResult->fetch_assoc()) {
    $id = (string)$row['road_id'];
    if (isset($indexById[$id])) $totals[$indexById[$id]]['history'] = (int)$row['total'];
}

$dynamic = [];
$reports30 = $conn->query("SELECT id, road_name, location FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY created_at DESC");
while ($row = $reports30->fetch_assoc()) {
    $text = trim((string)($row['road_name'] ?: $row['location']));
    $idx = srAnalyticsMatchRoad($text, $roads);
    if ($idx !== null) {
        $totals[$idx]['new']++;
    } else {
        $norm = srAnalyticsNormalizeRoad($text);
        if ($norm === '') $norm = 'reported-road-' . (int)$row['id'];
        if (!isset($dynamic[$norm])) {
            $dynamic[$norm] = ['road_name' => $text ?: ('Reported Road #' . (int)$row['id']), 'district' => 'New reported spot', 'history' => 0, 'new' => 0, 'registered' => false];
        }
        $dynamic[$norm]['new']++;
    }
}

$roadRows = array_values($totals);
foreach ($dynamic as $row) $roadRows[] = $row;
foreach ($roadRows as &$row) {
    $row['total'] = (int)$row['history'] + (int)$row['new'];
    $row['risk'] = srAnalyticsRisk($row['total']);
}
unset($row);
usort($roadRows, static fn($a,$b) => $b['total'] <=> $a['total']);

$total30 = array_sum(array_column($roadRows, 'total'));
$recent7 = sr_count($conn,"SELECT COUNT(*) total FROM reports WHERE created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY)");
$avg = $conn->query("SELECT AVG(priority_score) avgp FROM reports")->fetch_assoc();
$bySeverity = $conn->query("SELECT severity, COUNT(*) total FROM reports GROUP BY severity ORDER BY total DESC");

sr_page_start('Accident Hotspot Analytics','analytics');
?>
<div class="hero-card">
  <span class="demo-chip"><span class="live-dot"></span> Dhaka Division road analytics</span>
  <h2>Accident hotspot analytics</h2>
  <p>Twenty registered corridors across all Dhaka Division districts are tracked with a 30-day academic history dataset. New reports increase the matching road automatically, and roads outside the registry are added as dynamic tracked spots.</p>
  <div class="toolbar" style="margin-top:18px"><a class="btn light" href="map.php">Open Live Map</a><a class="btn light" href="admin-dashboard.php">Command Center</a></div>
</div>

<div class="grid grid-4" style="margin-top:18px">
  <div class="card stat danger"><div class="num"><?php echo (int)$total30; ?></div><div class="label">30-Day Road Events</div></div>
  <div class="card stat blue"><div class="num"><?php echo count($roadRows); ?></div><div class="label">Tracked Roads / Spots</div></div>
  <div class="card stat"><div class="num"><?php echo (int)$recent7; ?></div><div class="label">New Reports · 7 Days</div></div>
  <div class="card stat"><div class="num"><?php echo round((float)($avg['avgp']??0)); ?></div><div class="label">Average Priority</div></div>
</div>

<div class="split" style="margin-top:18px">
  <div class="card">
    <div class="section-heading"><div><span class="section-eyebrow">30-DAY TRACKING</span><h2>Dhaka Division Road Risk</h2></div><span class="fleet-count"><?php echo count($roads); ?> registered + <?php echo count($dynamic); ?> dynamic</span></div>
    <div class="table-wrap"><table><thead><tr><th>Road / Spot</th><th>District</th><th>History</th><th>New</th><th>Total</th><th>Risk</th></tr></thead><tbody>
    <?php foreach($roadRows as $r): ?>
      <tr>
        <td><strong><?php echo htmlspecialchars($r['road_name']); ?></strong><br><span class="mini"><?php echo $r['registered'] ? 'Registered corridor' : 'Dynamic from citizen report'; ?></span></td>
        <td><?php echo htmlspecialchars($r['district']); ?></td>
        <td><?php echo (int)$r['history']; ?></td>
        <td><?php echo (int)$r['new']; ?></td>
        <td><strong><?php echo (int)$r['total']; ?></strong></td>
        <td><span class="badge <?php echo strtolower($r['risk']); ?>"><?php echo htmlspecialchars($r['risk']); ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <p class="mini" style="margin-bottom:0;margin-top:12px">Historical rows are seeded academic demo data. New reports are read from the live MySQL reports table.</p>
  </div>
  <div class="card">
    <h2>Submitted Report Severity</h2>
    <div class="timeline">
      <?php if($bySeverity && $bySeverity->num_rows): while($s=$bySeverity->fetch_assoc()): ?>
        <div class="timeline-item"><strong><?php echo htmlspecialchars($s['severity']); ?></strong><br><span class="mini"><?php echo (int)$s['total']; ?> report(s)</span><div class="progress" style="margin-top:8px"><span style="width:<?php echo min(100,(int)$s['total']*20); ?>%"></span></div></div>
      <?php endwhile; else: ?><div class="timeline-item"><strong>No submitted reports yet</strong></div><?php endif; ?>
    </div>
    <div class="location-help" style="margin-top:16px"><strong>How the count works</strong><span>Same-road names are normalized (for example Road/Rd). A matching registered road increases that road. An unrecognized road becomes a dynamic road/spot and is counted from its first report.</span></div>
  </div>
</div>
<?php sr_page_end(); ?>
