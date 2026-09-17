(function () {
  'use strict';
  const mapEl = document.getElementById('reportLocationMap');
  if (!mapEl) return;

  const latInput = document.querySelector('[name="latitude"]');
  const lngInput = document.querySelector('[name="longitude"]');
  const roadInput = document.querySelector('[name="road_name"]');
  const locationInput = document.querySelector('[name="location"]');
  const landmarkInput = document.querySelector('[name="nearest_landmark"]');
  const status = document.getElementById('geoStatus');
  const detected = document.getElementById('detectedLocationCard');
  const detectedRoad = document.getElementById('detectedRoad');
  const detectedArea = document.getElementById('detectedArea');
  const detectedSource = document.getElementById('detectedSource');
  let map = null;
  let marker = null;
  let lookupController = null;
  let lookupSequence = 0;
  let lookupTimer = null;

  function setStatus(text, state) {
    if (!status) return;
    status.classList.remove('lookup', 'success', 'warning');
    if (state) status.classList.add(state);
    const textNode = status.querySelector('span:last-child');
    if (textNode) textNode.textContent = text;
    else status.textContent = text;
  }

  function showDetected(data) {
    if (!detected) return;
    detected.classList.add('show');
    if (detectedRoad) detectedRoad.textContent = data.road_name || 'Road name not available';
    if (detectedArea) detectedArea.textContent = data.area || data.location || 'GPS location selected';
    if (detectedSource) detectedSource.textContent = data.source ? `Detected by ${data.source}` : 'Location detected';
  }

  async function reverseLookup(lat, lng, reason) {
    const seq = ++lookupSequence;
    if (lookupController) lookupController.abort();
    lookupController = new AbortController();
    setStatus('Detecting road and place name from the selected point...', 'lookup');

    try {
      const url = `../api/reverse-location.php?lat=${encodeURIComponent(lat.toFixed(7))}&lng=${encodeURIComponent(lng.toFixed(7))}`;
      const response = await fetch(url, { signal: lookupController.signal, headers: { 'Accept': 'application/json' } });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      if (seq !== lookupSequence) return;
      if (!data || !data.ok) throw new Error(data?.message || 'Location lookup failed');

      // A deliberate map/GPS selection means the detected values should replace the old selection.
      if (roadInput && data.road_name) roadInput.value = data.road_name;
      if (locationInput && data.location) locationInput.value = data.location;
      if (landmarkInput && data.nearest_landmark) landmarkInput.value = data.nearest_landmark;

      showDetected(data);
      const roadText = data.road_name ? ` · ${data.road_name}` : '';
      const sourceText = data.source ? ` · ${data.source}` : '';
      setStatus(`${reason || 'Location selected'}${roadText}${sourceText}`, data.source === 'GPS fallback' ? 'warning' : 'success');
    } catch (error) {
      if (error && error.name === 'AbortError') return;
      if (seq !== lookupSequence) return;
      if (locationInput) locationInput.value = `Selected GPS: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
      showDetected({ road_name: roadInput?.value || '', area: 'GPS saved successfully', source: 'GPS fallback' });
      setStatus(`${reason || 'Location selected'} · GPS saved. Road lookup is unavailable right now.`, 'warning');
    }
  }

  function setLocation(lat, lng, source, options) {
    lat = Number(lat); lng = Number(lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    if (latInput) latInput.value = lat.toFixed(7);
    if (lngInput) lngInput.value = lng.toFixed(7);

    if (map && window.L) {
      if (!marker) {
        marker = L.marker([lat, lng], { draggable: true, title: 'Accident location' }).addTo(map);
        marker.on('dragend', function (event) {
          const point = event.target.getLatLng();
          setLocation(point.lat, point.lng, 'Marker moved', { lookup: true });
        });
      } else {
        marker.setLatLng([lat, lng]);
      }
      if (!options || options.recenter !== false) map.setView([lat, lng], Math.max(map.getZoom(), 14));
    }

    setStatus(`${source || 'Selected location'} · ${lat.toFixed(5)}, ${lng.toFixed(5)}`, 'lookup');
    if (!options || options.lookup !== false) {
      clearTimeout(lookupTimer);
      lookupTimer = setTimeout(function () { reverseLookup(lat, lng, source); }, 1100);
    }
  }

  window.SafeRoadLocationPicker = { setLocation, reverseLookup };

  if (window.L) {
    map = L.map(mapEl, { zoomControl: true }).setView([23.810331, 90.412521], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    map.on('click', function (event) {
      setLocation(event.latlng.lat, event.latlng.lng, 'Map point selected', { lookup: true, recenter: false });
    });
  } else {
    setStatus('Online map is unavailable. Enter latitude and longitude manually or use device GPS.', 'warning');
  }

  let manualTimer = null;
  function syncFromInputs() {
    clearTimeout(manualTimer);
    manualTimer = setTimeout(function () {
      const lat = Number(latInput?.value); const lng = Number(lngInput?.value);
      if (Number.isFinite(lat) && Number.isFinite(lng) && latInput.value !== '' && lngInput.value !== '') {
        setLocation(lat, lng, 'Manual GPS', { lookup: true });
      }
    }, 450);
  }
  latInput?.addEventListener('change', syncFromInputs);
  lngInput?.addEventListener('change', syncFromInputs);
})();
