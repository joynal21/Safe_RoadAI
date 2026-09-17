<?php require_once __DIR__ . "/../includes/auth-check.php"; require_once __DIR__ . "/../includes/ui.php"; sr_page_start('Live Road Safety Command Map','map'); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="hero-card">
  <span class="demo-chip"><span class="live-dot"></span> Live road analytics + emergency response</span>
  <h2>Dhaka Division road-risk intelligence + nearest ambulance response</h2>
  <p>SafeRoad now tracks <strong>20 registered road corridors across Dhaka Division</strong>, and any new road name reported by a citizen is added as a dynamic tracked road/spot. Counts update from the database automatically. Risk dots are geographic markers, so they remain attached to the same GPS point while zooming. Active missions and all four ambulance positions stay visible on the same command map.</p>
  <div class="toolbar" style="margin-top:18px">
    <button class="btn danger" onclick="window.SafeRoadMap && SafeRoadMap.refresh(true)">↻ Refresh Live Data</button>
    <button class="btn ok" onclick="window.SafeRoadMap && SafeRoadMap.startSimulation()">▶ Start Movement</button>
    <button class="btn light" onclick="window.SafeRoadMap && SafeRoadMap.pauseSimulation()">Pause</button>
    <button class="btn warning" onclick="window.SafeRoadMap && SafeRoadMap.stepOnce()">Move One Step</button>
    <button class="btn light" id="riskToggleButton" onclick="window.SafeRoadMap && SafeRoadMap.toggleRiskRoads()">Hide Risk Markers</button>
    <button class="btn light" onclick="window.SafeRoadMap && SafeRoadMap.fitDivision()">◎ Fit Dhaka Division</button>
    <button class="btn smart-btn" id="smartSimulatorButton" onclick="window.SafeRoadMap && SafeRoadMap.toggleSmartSimulator()">⚡ Smart What-If Simulator</button>
  </div>
</div>

<div class="grid grid-4 risk-kpi-grid" style="margin-top:18px">
  <div class="card stat danger"><div class="num" id="riskTotalAccidents">--</div><div class="label">Accidents · last 30 days</div></div>
  <div class="card stat danger"><div class="num" id="riskRedRoads">--</div><div class="label">High-risk roads</div></div>
  <div class="card stat blue"><div class="num" id="riskTrackedRoads">--</div><div class="label">Tracked roads / spots</div></div>
  <div class="card stat"><div class="num live-kpi" id="riskLiveState">LIVE</div><div class="label" id="riskLastUpdated">Waiting for API...</div></div>
</div>

<div class="split" style="margin-top:18px">
  <div class="card">
    <div id="map" class="map-shell tracking-map">
      <div class="road-grid"></div>
      <div id="demoMap" class="demo-map-stage" aria-label="SafeRoad live road map">
        <div class="demo-road demo-road-a"></div>
        <div class="demo-road demo-road-b"></div>
        <div class="demo-road demo-road-c"></div>
        <div id="demoRiskRoads" class="demo-risk-roads" aria-label="Road accident risk layer"></div>
        <div class="demo-route" id="demoRoute"></div>
        <div class="demo-route-progress" id="demoRouteProgress"></div>
        <div class="demo-accident" id="demoAccident" title="Accident destination">🚨</div>
        <div class="demo-ambulance" id="demoAmbulance" title="Ambulance">🚑</div>
        <div class="demo-map-hint" id="demoMapHint">Loading live road-risk data...</div>
      </div>
      <div class="map-legend">
        <strong>Live Map Legend</strong><br>
        <span class="legend-risk-dot red"></span> Red dot: 6+ accidents<br>
        <span class="legend-risk-dot yellow"></span> Yellow dot: 3–5 accidents<br>
        <span class="legend-risk-dot green"></span> Green dot: 0–2 accidents<br>
        ● Dot = GPS-anchored tracked road/spot<br>
        🚨 Road-matched accident alert<br>
        🚑 Ambulance fleet / moving selected unit<br>
        <span id="riskLayerStatus">Risk roads: loading</span><br>
        <span id="simStatus">Simulation: waiting</span><br>
        <span id="leafletStatus">Built-in demo map ready</span>
      </div>
    </div>
    <div id="smartSimulationPanel" class="smart-simulation-panel">
      <div class="smart-sim-empty"><strong>⚡ Smart Response Simulator</strong><span>Enable What-If Mode and click anywhere on the live map to preview the nearest available ambulance.</span></div>
    </div>
  </div>
  <div class="card">
    <h2>Road Risk · Last 30 Days</h2>
    <p class="mini risk-disclaimer" id="riskDataMode">Checking live database...</p>
    <div id="riskPanel" class="risk-road-list"><div class="timeline-item"><strong>Loading road-risk data...</strong></div></div>
    <h2 style="margin-top:18px">Active Mission</h2>
    <div id="missionPanel" class="mission-panel"><div class="timeline-item"><strong>Loading mission...</strong></div></div>
    <h2 style="margin-top:18px">Live Activity</h2>
    <div id="mapFeed" class="timeline"><div class="timeline-item"><strong>Loading feed...</strong></div></div>
    <h2 style="margin-top:18px">Fleet</h2>
    <div id="fleetList" class="timeline"></div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/live-map.js?v=9"></script>
<?php sr_page_end(); ?>
