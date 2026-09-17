<?php require_once __DIR__ . "/../includes/auth-check.php"; require_once __DIR__ . "/../includes/ui.php"; sr_page_start('Submit Accident Report','submit'); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="hero-card report-hero">
  <span class="demo-chip">Citizen Emergency Reporting</span>
  <h2>Report an accident from any location</h2>
  <p>Use your device GPS, click anywhere on the map, or enter latitude and longitude manually. SafeRoad keeps the exact accident GPS and the admin system automatically selects the nearest available ambulance after verification.</p>
  <div class="toolbar" style="margin-top:18px">
    <button type="button" class="btn danger" onclick="fillDemoReport()">⚡ Auto-fill Demo Accident</button>
    <button type="button" class="btn light" onclick="useCurrentLocation()">📍 Use My Current Location</button>
  </div>
</div>

<form action="../actions/submit-report.php" method="POST" enctype="multipart/form-data" class="report-layout" style="margin-top:18px">
  <div class="card report-form-card">
    <div class="section-heading"><div><span class="section-eyebrow">STEP 1</span><h2>Emergency Details</h2></div><span class="section-icon">🚨</span></div>
    <div class="form-grid">
      <div class="field"><label>Report Title *</label><input type="text" name="title" required placeholder="Example: Two vehicle collision near market"></div>
      <div class="field"><label>Emergency Type</label><select name="emergency_type"><option>Road Accident</option><option>Vehicle Fire</option><option>Traffic Block</option><option>Medical Emergency</option><option>Other</option></select></div>
      <div class="field"><label>Road / Area Name <span class="optional">recommended</span></label><input type="text" name="road_name" placeholder="Example: Mirpur Road"><div class="hint">If the same road is reported again, its 30-day accident count increases automatically.</div></div>
      <div class="field"><label>Nearest Landmark</label><input type="text" name="nearest_landmark" placeholder="Hospital, school, market, bridge..."></div>
      <div class="field"><label>Location Details *</label><input type="text" name="location" required placeholder="Describe where the accident happened"></div>
      <div class="field"><label>Reporter Phone</label><input type="text" name="reporter_phone" placeholder="01XXXXXXXXX"></div>
      <div class="field"><label>Latitude *</label><input type="number" step="0.0000001" name="latitude" required placeholder="23.8103310"></div>
      <div class="field"><label>Longitude *</label><input type="number" step="0.0000001" name="longitude" required placeholder="90.4125210"></div>
      <div class="field"><label>Severity *</label><select name="severity" required><option value="Low">Low</option><option value="Medium" selected>Medium</option><option value="High">High</option><option value="Critical">Critical</option></select></div>
      <div class="field"><label>People Injured</label><input type="number" name="people_injured" min="0" value="0"></div>
      <div class="field"><label>Vehicles Involved</label><input type="text" name="vehicles_involved" placeholder="Car, Bus, Motorcycle"></div>
      <div class="field"><label>Support Needed</label><div class="support-options"><label class="check-card"><input type="checkbox" name="police_required"> <span>Police</span></label><label class="check-card"><input type="checkbox" name="fire_service_required"> <span>Fire Service</span></label></div></div>
    </div>
    <div class="field" style="margin-top:16px"><label>Description *</label><textarea name="description" required placeholder="Describe injuries, road blockage, vehicles and the emergency condition."></textarea></div>
    <div class="form-grid" style="margin-top:16px">
      <div class="field"><label>Image Evidence</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><div class="hint">Optional · JPG, PNG or WEBP · max 8 MB</div></div>
      <div class="field"><label>Video Evidence</label><input type="file" name="video" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo"><div class="hint">Optional · MP4, WEBM, MOV or AVI · max 50 MB</div></div>
    </div>
  </div>

  <div class="report-side-column">
    <div class="card location-picker-card">
      <div class="section-heading"><div><span class="section-eyebrow">STEP 2</span><h2>Choose Accident GPS</h2></div><span class="section-icon">📍</span></div>
      <p class="mini">Click <strong>anywhere</strong> on the map. SafeRoad will capture the GPS and automatically fill the road/area and location fields from that point.</p>
      <div id="reportLocationMap" class="report-location-map" aria-label="Choose accident location"></div>
      <div id="geoStatus" class="location-status"><span class="live-dot"></span><span>Select a point on the map or use your current location.</span></div>
      <div id="detectedLocationCard" class="detected-location-card">
        <div class="detected-location-icon">◎</div>
        <div><small>AUTO-DETECTED LOCATION</small><strong id="detectedRoad">Waiting for map selection</strong><span id="detectedArea">Click the exact accident point.</span><em id="detectedSource">Road and place will fill automatically</em></div>
      </div>
      <div class="location-help"><strong>Nearest ambulance logic</strong><span>After admin verification, SafeRoad compares this GPS point with every <em>available</em> ambulance using geographic distance and dispatches the closest unit.</span></div>
    </div>

    <div class="card submit-summary-card">
      <span class="section-eyebrow">READY?</span>
      <h2>Send Emergency Report</h2>
      <p class="mini">The exact GPS can be anywhere. Road name is optional for ambulance dispatch, but entering it helps the live road-risk counter merge repeated accidents on the same road.</p>
      <button class="btn danger submit-main-btn" type="submit">🚨 Submit Emergency Report</button>
      <a class="btn light submit-main-btn" href="citizen-dashboard.php">Cancel</a>
    </div>
  </div>
</form>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/report-location-picker.js?v=2"></script>
<?php sr_page_end(); ?>
