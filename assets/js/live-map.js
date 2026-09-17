(function () {
  'use strict';

  let map = null;
  let entityLayer = null;
  let riskLayer = null;
  let smartLayer = null;
  let running = false;
  let timer = null;
  let lastData = null;
  let activeAmbulanceId = null;
  let riskVisible = true;
  let smartMode = false;
  let initialBoundsFitted = false;
  let lastMapBounds = [];
  let lastDivisionBounds = [];
  const riskMarkers = new Map();
  const defaultCenter = { lat: 23.810331, lng: 90.412521 };

  function $(id) {
    return document.getElementById(id);
  }

  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[character]));
  }

  function badge(status) {
    const slug = String(status || '').toLowerCase().replace(/\s+/g, '-');
    return `<span class="badge ${slug}">${esc(status || 'Unknown')}</span>`;
  }

  function markerHtml(content, className) {
    return L.divIcon({
      className: className || '',
      html: content,
      iconSize: [42, 42],
      iconAnchor: [21, 21]
    });
  }

  function distanceKm(lat1, lon1, lat2, lon2) {
    const radius = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2
      + Math.cos(lat1 * Math.PI / 180)
      * Math.cos(lat2 * Math.PI / 180)
      * Math.sin(dLon / 2) ** 2;
    return radius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function setStatus(text) {
    const element = $('simStatus');
    if (element) element.textContent = text;
  }

  function formatDate(value) {
    if (!value) return 'N/A';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  async function getData() {
    const response = await fetch(`../api/map-data.php?ts=${Date.now()}`, { cache: 'no-store' });
    if (!response.ok) throw new Error(`Map API returned ${response.status}`);
    return response.json();
  }

  function popupReport(report) {
    const roadMatch = report.matched_road_name
      ? `<br><strong>Road matched:</strong> ${esc(report.matched_road_name)}${report.snap_distance_m !== null ? ` · road match ${Number(report.snap_distance_m)} m` : ''}`
      : '<br><span class="mini">No known-road match; showing reported GPS.</span>';
    return `<b>#${report.id} ${esc(report.title)}</b><br>${esc(report.location)}${roadMatch}<br>Status: ${esc(report.report_status)}<br>Priority: ${report.priority_score}/100<br><a href="report-details.php?id=${report.id}">Open details</a>`;
  }

  function popupAmbulance(ambulance) {
    return `<b>${esc(ambulance.ambulance_name)}</b><br>${esc(ambulance.driver_name || 'Driver')}<br>Status: ${esc(ambulance.status)}<br>GPS: ${Number(ambulance.latitude).toFixed(5)}, ${Number(ambulance.longitude).toFixed(5)}<br>Mission: ${esc(ambulance.current_mission || 'Ready for nearest incident')}`;
  }

  function popupRiskRoad(road, summary) {
    const label = road.risk === 'high' ? 'HIGH RISK' : (road.risk === 'caution' ? 'CAUTION' : 'LOW RISK');
    return `<div class="risk-popup">
      <b>${esc(road.road_name)}</b><br>
      <strong>${Number(road.accidents)} accidents</strong> in the last 30 days<br>
      District/coverage: ${esc(road.district || 'Dhaka Division')}<br>
      Tracking: ${road.registered === false ? 'Dynamic road/spot from citizen reports' : 'Registered Dhaka Division corridor'}<br>
      Risk: ${label}<br>
      Road span: ${esc(road.start_label || 'Start')} → ${esc(road.end_label || 'End')}<br>
      <span class="mini">${esc(formatDate(summary?.period_start))} – ${esc(formatDate(summary?.period_end))}</span><br>
      <span class="mini">Source: ${esc(summary?.source_label || 'Road-risk API')}</span>
    </div>`;
  }

  function riskStartIcon(road) {
    const title = `${road.road_name}: ${Number(road.accidents)} accidents · ${road.start_label || 'Beginning of road'}`;
    return L.divIcon({
      className: 'risk-start-leaflet-icon',
      html: `<div class="risk-start-marker-wrap risk-${esc(road.risk || 'low')}" title="${esc(title)}">
        <div class="risk-start-dot"><strong>${Number(road.accidents)}</strong></div>
        <div class="risk-start-note">Beginning of road</div>
      </div>`,
      iconSize: [124, 58],
      // The Leaflet coordinate is the CENTER of the big dot, so the marker
      // remains directly on the mapped road. The note is only attached below it.
      iconAnchor: [62, 19]
    });
  }

  function findActiveMission(data) {
    const moving = (data.ambulances || []).find((ambulance) => (
      ambulance.status === 'on_the_way'
      && ambulance.destination_latitude
      && ambulance.destination_longitude
    ));
    if (moving) {
      return {
        ambulance: moving,
        report: (data.reports || []).find((report) => report.id === moving.assigned_report_id) || null
      };
    }

    const arrived = (data.ambulances || []).find((ambulance) => (
      ambulance.status === 'arrived'
      && ambulance.destination_latitude
      && ambulance.destination_longitude
    ));
    if (arrived) {
      return {
        ambulance: arrived,
        report: (data.reports || []).find((report) => report.id === arrived.assigned_report_id) || null
      };
    }
    return null;
  }

  function initLeaflet() {
    if (!window.L || map) return Boolean(map);

    try {
      map = L.map('map', { zoomControl: true }).setView([defaultCenter.lat, defaultCenter.lng], 9);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(map);

      riskLayer = L.layerGroup().addTo(map);
      entityLayer = L.layerGroup().addTo(map);
      smartLayer = L.layerGroup().addTo(map);
      map.on('click', handleSmartMapClick);

      const leafletStatus = $('leafletStatus');
      if (leafletStatus) leafletStatus.textContent = 'Online map active · zoom position preserved during live refresh';
      const demoStage = $('demoMap');
      if (demoStage) demoStage.classList.add('demo-map-under-leaflet');
      return true;
    } catch (error) {
      const leafletStatus = $('leafletStatus');
      if (leafletStatus) leafletStatus.textContent = 'Using built-in demo map';
      return false;
    }
  }

  function renderRiskLeaflet(data, bounds) {
    if (!riskLayer) return;
    riskLayer.clearLayers();
    riskMarkers.clear();

    (data.risk_roads || []).forEach((road) => {
      const markerPoint = road.display_start_point || road.start_point || road.points?.[0];
      if (!markerPoint) return;
      const lat = Number(markerPoint[0]);
      const lng = Number(markerPoint[1]);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
      const latLng = [lat, lng];
      bounds.push(latLng);

      // CircleMarker is attached directly to the geographic coordinate. It does
      // not drift away from the road when the user zooms in or out.
      const marker = L.circleMarker(latLng, {
        radius: road.registered === false ? 11 : 14,
        color: '#07101f',
        weight: 4,
        fillColor: road.color || '#ffd166',
        fillOpacity: 0.96,
        bubblingMouseEvents: false
      })
        .bindPopup(popupRiskRoad(road, data.risk_summary || {}))
        .bindTooltip(String(Number(road.accidents || 0)), {
          permanent: true,
          direction: 'center',
          className: `risk-count-tooltip risk-count-${road.risk || 'low'}`,
          opacity: 1
        })
        .addTo(riskLayer);

      marker.on('mouseover', () => {
        marker.bindTooltip(`${esc(road.road_name)} · ${Number(road.accidents)} accidents`, {
          permanent: false,
          direction: 'top',
          className: 'risk-name-tooltip',
          offset: [0, -13]
        }).openTooltip();
      });
      marker.on('mouseout', () => {
        marker.unbindTooltip();
        marker.bindTooltip(String(Number(road.accidents || 0)), {
          permanent: true,
          direction: 'center',
          className: `risk-count-tooltip risk-count-${road.risk || 'low'}`,
          opacity: 1
        }).openTooltip();
      });

      riskMarkers.set(String(road.id), marker);
    });

    updateRiskVisibility();
  }

  function renderLeaflet(data) {
    if (!initLeaflet()) return;

    entityLayer.clearLayers();
    const bounds = [];
    renderRiskLeaflet(data, bounds);
    lastDivisionBounds = (data.risk_roads || [])
      .filter((road) => road.registered !== false)
      .map((road) => road.display_start_point || road.start_point || road.points?.[0])
      .filter(Boolean)
      .map((point) => [Number(point[0]), Number(point[1])]);

    (data.reports || []).forEach((report) => {
      const reportLat = Number(report.map_latitude ?? report.latitude);
      const reportLng = Number(report.map_longitude ?? report.longitude);
      if (!reportLat || !reportLng) return;
      const marker = L.marker([reportLat, reportLng], {
        icon: markerHtml('<div class="road-accident-marker"><span>🚨</span></div>', 'accident-road-marker')
      }).bindPopup(popupReport(report));
      entityLayer.addLayer(marker);
      bounds.push([reportLat, reportLng]);
    });

    (data.ambulances || []).forEach((ambulance) => {
      if (!ambulance.latitude || !ambulance.longitude) return;
      const icon = ambulance.status === 'on_the_way'
        ? '<div class="moving-ambulance-icon">🚑</div>'
        : '🚑';
      const marker = L.marker([ambulance.latitude, ambulance.longitude], {
        icon: markerHtml(icon, 'ambulance-marker')
      }).bindPopup(popupAmbulance(ambulance));
      entityLayer.addLayer(marker);
      bounds.push([ambulance.latitude, ambulance.longitude]);

      if (ambulance.destination_latitude && ambulance.destination_longitude) {
        const assignedReport = (data.reports || []).find((report) => report.id === ambulance.assigned_report_id);
        const displayDestinationLat = Number(assignedReport?.map_latitude ?? ambulance.destination_latitude);
        const displayDestinationLng = Number(assignedReport?.map_longitude ?? ambulance.destination_longitude);
        const routeLine = L.polyline([
          [ambulance.latitude, ambulance.longitude],
          [displayDestinationLat, displayDestinationLng]
        ], {
          color: '#36d1ff',
          weight: 5,
          dashArray: '9 10',
          opacity: 0.9
        });
        entityLayer.addLayer(routeLine);
        bounds.push([displayDestinationLat, displayDestinationLng]);
      }
    });

    (data.hotspots || []).forEach((hotspot) => {
      const circle = L.circle([hotspot.avg_latitude, hotspot.avg_longitude], {
        radius: 250 + (hotspot.total_reports * 90),
        color: '#8a5cff',
        weight: 2,
        fillOpacity: 0.12
      });
      circle.bindPopup(`<b>Database hotspot: ${esc(hotspot.road_name)}</b><br>${hotspot.total_reports} submitted reports`);
      entityLayer.addLayer(circle);
    });

    lastMapBounds = bounds.slice();
    if (bounds.length && !initialBoundsFitted) {
      map.fitBounds(bounds, { padding: [36, 36], maxZoom: 11 });
      initialBoundsFitted = true;
    }
  }

  function collectStagePoints(data) {
    const points = [];
    (data.reports || []).forEach((report) => {
      const reportLat = Number(report.map_latitude ?? report.latitude);
      const reportLng = Number(report.map_longitude ?? report.longitude);
      if (reportLat && reportLng) points.push([reportLat, reportLng]);
    });
    (data.ambulances || []).forEach((ambulance) => {
      if (ambulance.latitude && ambulance.longitude) points.push([Number(ambulance.latitude), Number(ambulance.longitude)]);
      if (ambulance.destination_latitude && ambulance.destination_longitude) {
        points.push([Number(ambulance.destination_latitude), Number(ambulance.destination_longitude)]);
      }
    });
    (data.risk_roads || []).forEach((road) => {
      const point = road.display_start_point || road.start_point || road.points?.[0];
      if (point) points.push([Number(point[0]), Number(point[1])]);
    });
    return points;
  }

  function toStagePoint(lat, lng, box) {
    const data = lastData || { reports: [], ambulances: [], risk_roads: [] };
    const points = collectStagePoints(data);

    if (points.length < 2) {
      points.push(
        [defaultCenter.lat - 0.03, defaultCenter.lng - 0.03],
        [defaultCenter.lat + 0.03, defaultCenter.lng + 0.03]
      );
    }

    let minLat = Math.min(...points.map((point) => point[0]));
    let maxLat = Math.max(...points.map((point) => point[0]));
    let minLng = Math.min(...points.map((point) => point[1]));
    let maxLng = Math.max(...points.map((point) => point[1]));
    const padLat = Math.max((maxLat - minLat) * 0.12, 0.008);
    const padLng = Math.max((maxLng - minLng) * 0.12, 0.008);
    minLat -= padLat;
    maxLat += padLat;
    minLng -= padLng;
    maxLng += padLng;

    const x = ((lng - minLng) / (maxLng - minLng)) * box.width;
    const y = (1 - ((lat - minLat) / (maxLat - minLat))) * box.height;
    return {
      x: Math.max(24, Math.min(box.width - 24, x)),
      y: Math.max(24, Math.min(box.height - 24, y))
    };
  }

  function addDemoRiskStartMarker(container, road, point) {
    const marker = document.createElement('button');
    marker.type = 'button';
    marker.className = `demo-risk-start-marker risk-${road.risk || 'low'}`;
    marker.dataset.roadId = String(road.id);
    marker.style.left = `${point.x}px`;
    marker.style.top = `${point.y}px`;
    marker.innerHTML = `<span class="demo-risk-start-dot"><strong>${Number(road.accidents)}</strong></span><small>Tracked spot</small>`;
    marker.title = `${road.road_name}: ${road.accidents} accidents · ${road.district || 'Dhaka Division'}`;
    marker.setAttribute('aria-label', marker.title);
    marker.addEventListener('click', () => focusRiskRoad(road.id));
    container.appendChild(marker);
  }

  function renderDemoRiskRoads(data, box) {
    const container = $('demoRiskRoads');
    if (!container) return;
    container.innerHTML = '';

    (data.risk_roads || []).forEach((road) => {
      const markerPoint = road.display_start_point || road.start_point || road.points?.[0];
      if (!markerPoint) return;
      const point = toStagePoint(Number(markerPoint[0]), Number(markerPoint[1]), box);
      addDemoRiskStartMarker(container, road, point);
    });

    container.classList.toggle('risk-layer-hidden', !riskVisible);
  }

  function renderDemoStage(data) {
    lastData = data;
    const stage = $('demoMap');
    const ambulanceElement = $('demoAmbulance');
    const accidentElement = $('demoAccident');
    const route = $('demoRoute');
    const progress = $('demoRouteProgress');
    const hint = $('demoMapHint');
    if (!stage || !ambulanceElement || !accidentElement || !route || !progress) return;

    const box = { width: stage.clientWidth || 800, height: stage.clientHeight || 560 };
    renderDemoRiskRoads(data, box);

    const mission = findActiveMission(data);
    if (!mission) {
      ambulanceElement.style.display = 'none';
      accidentElement.style.display = 'none';
      route.style.display = 'none';
      progress.style.display = 'none';
      if (hint) {
        hint.style.display = 'block';
        hint.classList.add('risk-only');
        const summary = data.risk_summary || {};
        hint.innerHTML = `${summary.data_mode === 'live_database' ? '● LIVE DATABASE road risk is active.' : 'Road-risk fallback is active.'}<br>Large dots represent GPS-anchored tracked road/spot locations and show the 30-day accident total. Dispatch a verified incident to add the moving ambulance.`;
      }
      return;
    }

    const ambulance = mission.ambulance;
    activeAmbulanceId = ambulance.id;
    const ambulancePoint = toStagePoint(Number(ambulance.latitude), Number(ambulance.longitude), box);
    const displayDestinationLat = Number(mission.report?.map_latitude ?? ambulance.destination_latitude);
    const displayDestinationLng = Number(mission.report?.map_longitude ?? ambulance.destination_longitude);
    const destinationPoint = toStagePoint(displayDestinationLat, displayDestinationLng, box);

    ambulanceElement.style.display = 'grid';
    accidentElement.style.display = 'grid';
    route.style.display = 'block';
    progress.style.display = 'block';
    if (hint) {
      hint.style.display = 'none';
      hint.classList.remove('risk-only');
    }

    ambulanceElement.style.left = `${ambulancePoint.x}px`;
    ambulanceElement.style.top = `${ambulancePoint.y}px`;
    accidentElement.style.left = `${destinationPoint.x}px`;
    accidentElement.style.top = `${destinationPoint.y}px`;

    const dx = destinationPoint.x - ambulancePoint.x;
    const dy = destinationPoint.y - ambulancePoint.y;
    const length = Math.max(1, Math.sqrt(dx * dx + dy * dy));
    const angle = Math.atan2(dy, dx) * 180 / Math.PI;
    route.style.left = `${ambulancePoint.x}px`;
    route.style.top = `${ambulancePoint.y}px`;
    route.style.width = `${length}px`;
    route.style.transform = `rotate(${angle}deg)`;

    const start = ambulance.status === 'arrived' ? destinationPoint : ambulancePoint;
    progress.style.left = `${start.x}px`;
    progress.style.top = `${start.y}px`;
    progress.style.width = `${ambulance.status === 'arrived' ? 0 : Math.max(10, length * 0.35)}px`;
    progress.style.transform = `rotate(${angle}deg)`;
    ambulanceElement.classList.toggle('arrived', ambulance.status === 'arrived');
    ambulanceElement.classList.toggle('driving', ambulance.status === 'on_the_way');
  }

  function renderRiskPanel(data) {
    const roads = [...(data.risk_roads || [])].sort((a, b) => Number(b.accidents) - Number(a.accidents));
    const summary = data.risk_summary || {};
    const panel = $('riskPanel');

    if (panel) {
      panel.innerHTML = roads.length
        ? roads.map((road) => {
          const riskLabel = road.risk === 'high' ? 'High risk' : (road.risk === 'caution' ? 'Caution' : 'Low risk');
          return `<button type="button" class="risk-road-card risk-${esc(road.risk || 'low')}" data-road-id="${esc(road.id)}">
            <span class="risk-road-swatch"></span>
            <span class="risk-road-copy"><strong>${esc(road.road_name)}</strong><small>${esc(road.district || 'Dhaka Division')} · ${road.registered === false ? 'Dynamic road' : 'Registered corridor'} · ${riskLabel}<br>${Number(road.history_accidents || 0)} history + ${Number(road.live_accidents || 0)} new report${Number(road.live_accidents || 0) === 1 ? '' : 's'}</small></span>
            <span class="risk-road-count"><strong>${Number(road.accidents)}</strong><small>live 30-day total</small></span>
          </button>`;
        }).join('')
        : '<div class="timeline-item"><strong>No road-risk data</strong></div>';

      panel.querySelectorAll('[data-road-id]').forEach((button) => {
        button.addEventListener('click', () => focusRiskRoad(button.dataset.roadId));
      });
    }

    const total = $('riskTotalAccidents');
    if (total) total.textContent = Number(summary.total_accidents || 0);
    const red = $('riskRedRoads');
    if (red) red.textContent = Number(summary.red_roads || 0);
    const tracked = $('riskTrackedRoads');
    if (tracked) tracked.textContent = Number(summary.tracked_roads || roads.length || 0);
    const liveState = $('riskLiveState');
    if (liveState) liveState.textContent = summary.data_mode === 'live_database' ? 'LIVE' : 'DEMO';
    const updated = $('riskLastUpdated');
    if (updated) updated.textContent = summary.refreshed_at ? `Updated ${summary.refreshed_at.split(' ')[1] || summary.refreshed_at}` : 'Waiting for refresh';
    const dataMode = $('riskDataMode');
    if (dataMode) {
      dataMode.classList.toggle('live-source', summary.data_mode === 'live_database');
      dataMode.innerHTML = summary.data_mode === 'live_database'
        ? `<strong>● LIVE DATABASE · DHAKA DIVISION</strong> · ${Number(summary.registered_roads || 0)} registered corridors + ${Number(summary.dynamic_roads || 0)} new dynamic roads/spots. Every submitted road is counted. ${Number(summary.history_accidents || 0)} history + ${Number(summary.matched_live_reports || 0)} new submitted = ${Number(summary.total_accidents || 0)} total. Auto-refresh every ${Number(summary.refresh_seconds || 5)} seconds without resetting your zoom.`
        : '<strong>FALLBACK MODE</strong> · Database road history is unavailable, so emergency presentation values are being used.';
    }

    const status = $('riskLayerStatus');
    if (status) {
      status.textContent = `Tracked roads/spots: ${roads.length} · ${esc(summary.source_label || '')} · ${formatDate(summary.period_start)}–${formatDate(summary.period_end)}`;
    }
  }

  function renderMission(data) {
    const element = $('missionPanel');
    if (!element) return;
    const mission = findActiveMission(data);
    if (!mission) {
      element.innerHTML = '<div class="timeline-item"><strong>No active ambulance mission</strong><br><span class="mini">Go to Admin Reports, verify an incident, then dispatch ambulance.</span></div>';
      return;
    }

    const ambulance = mission.ambulance;
    const report = mission.report;
    const km = distanceKm(
      Number(ambulance.latitude),
      Number(ambulance.longitude),
      Number(ambulance.destination_latitude),
      Number(ambulance.destination_longitude)
    );
    const eta = ambulance.status === 'arrived'
      ? 0
      : Math.max(1, Math.ceil((km / Math.max(25, Number(ambulance.speed_kmh || 40))) * 60));

    element.innerHTML = `<div class="mission-card ${ambulance.status === 'on_the_way' ? 'mission-live' : ''}">
      <div class="mission-title"><span>🚑 ${esc(ambulance.ambulance_name)}</span>${badge(ambulance.status === 'on_the_way' ? 'on the way' : ambulance.status)}</div>
      <div class="mission-big">${ambulance.status === 'arrived' ? 'ARRIVED' : 'MOVING NOW'}</div>
      <div class="progress"><span style="width:${ambulance.status === 'arrived' ? 100 : Math.max(8, Math.min(96, 100 - (km * 18)))}%"></span></div>
      <div class="kpi-row" style="margin-top:12px">
        <span class="kpi-pill">📍 ${km.toFixed(2)} km left</span>
        <span class="kpi-pill">⏱ ETA ${eta} min</span>
        <span class="kpi-pill">⚡ ${esc(ambulance.speed_kmh || 40)} km/h</span>
      </div>
      <p class="mini" style="margin-bottom:0">${report ? `Destination: #${report.id} ${esc(report.title)}` : esc(ambulance.current_mission || 'Active response mission')}</p>
    </div>`;
  }

  function renderFeed(logs) {
    const element = $('mapFeed');
    if (!element) return;
    element.innerHTML = logs.length
      ? logs.map((log) => `<div class="timeline-item"><strong>${esc(log.action)}</strong><br><span class="mini">${esc(log.note || '')}</span><br><span class="mini">${esc(log.created_at || '')}</span></div>`).join('')
      : '<div class="timeline-item"><strong>No activity yet</strong></div>';
  }

  function renderFleet(list) {
    const element = $('fleetList');
    if (!element) return;
    element.innerHTML = list.length
      ? list.map((ambulance) => `<div class="timeline-item fleet-live-item"><strong>🚑 ${esc(ambulance.ambulance_name)}</strong> ${badge(ambulance.status)}<br><span class="mini">📍 ${Number(ambulance.latitude).toFixed(4)}, ${Number(ambulance.longitude).toFixed(4)} · ${esc(ambulance.current_mission || 'Ready for nearest incident')}</span></div>`).join('')
      : '<div class="timeline-item"><strong>No fleet data</strong></div>';
  }


  function setSmartPanel(html, active) {
    const panel = $('smartSimulationPanel');
    if (!panel) return;
    panel.classList.toggle('active', Boolean(active));
    panel.innerHTML = html;
  }

  function clearSmartSimulation() {
    if (smartLayer) smartLayer.clearLayers();
    setSmartPanel('<div class="smart-sim-empty"><strong>⚡ Smart Response Simulator</strong><span>Enable the simulator, then click anywhere on the map. It previews the nearest available ambulance without creating a real report.</span></div>', smartMode);
  }

  function toggleSmartSimulator(force) {
    smartMode = typeof force === 'boolean' ? force : !smartMode;
    const button = $('smartSimulatorButton');
    if (button) button.textContent = smartMode ? '✕ Exit What-If Mode' : '⚡ Smart What-If Simulator';
    const shell = $('map');
    if (shell) shell.classList.toggle('smart-simulator-active', smartMode);
    if (map) map.getContainer().style.cursor = smartMode ? 'crosshair' : '';
    if (!smartMode && smartLayer) smartLayer.clearLayers();
    if (smartMode) {
      setSmartPanel('<div class="smart-sim-empty"><strong>⚡ WHAT-IF MODE ACTIVE</strong><span>Click any location on the map. SafeRoad will rank all available ambulances by GPS distance and estimated response time.</span></div>', true);
    } else {
      setSmartPanel('<div class="smart-sim-empty"><strong>Smart simulator ready</strong><span>This preview does not save data or dispatch a real unit.</span></div>', false);
    }
  }

  function handleSmartMapClick(event) {
    if (!smartMode || !lastData || !smartLayer || !event?.latlng) return;
    smartLayer.clearLayers();
    const accidentLat = Number(event.latlng.lat);
    const accidentLng = Number(event.latlng.lng);
    const available = (lastData.ambulances || [])
      .filter((a) => String(a.status || '').toLowerCase() === 'available')
      .map((a) => {
        const km = distanceKm(accidentLat, accidentLng, Number(a.latitude), Number(a.longitude));
        const speed = Math.max(25, Number(a.speed_kmh || 40));
        return { ...a, distance_km: km, eta: Math.max(1, Math.ceil((km / speed) * 60)) };
      })
      .sort((a, b) => a.distance_km - b.distance_km);

    const accidentIcon = L.divIcon({
      className: 'smart-accident-icon',
      html: '<div class="smart-accident-pulse"><span>🚨</span></div>',
      iconSize: [52, 52],
      iconAnchor: [26, 26]
    });
    L.marker([accidentLat, accidentLng], { icon: accidentIcon, keyboard: false })
      .bindPopup(`<b>What-if accident</b><br>GPS: ${accidentLat.toFixed(5)}, ${accidentLng.toFixed(5)}<br><span class="mini">Preview only — not saved to database.</span>`)
      .addTo(smartLayer);

    if (!available.length) {
      setSmartPanel('<div class="smart-sim-result danger"><strong>⚠ No ambulance available</strong><span>All units are currently busy/offline. The simulator will not select an occupied ambulance.</span></div>', true);
      return;
    }

    available.slice(0, 3).forEach((unit, index) => {
      L.polyline([[Number(unit.latitude), Number(unit.longitude)], [accidentLat, accidentLng]], {
        color: index === 0 ? '#36d1ff' : '#8ea1b8',
        weight: index === 0 ? 5 : 2,
        dashArray: index === 0 ? '10 8' : '5 9',
        opacity: index === 0 ? 0.95 : 0.45
      }).addTo(smartLayer);
    });

    const winner = available[0];
    const rankings = available.slice(0, 4).map((unit, index) => `
      <div class="smart-rank-row ${index === 0 ? 'winner' : ''}">
        <span class="smart-rank-no">${index + 1}</span>
        <span><strong>${esc(unit.ambulance_name)}</strong><small>${unit.distance_km.toFixed(2)} km · ETA ${unit.eta} min · ${esc(unit.status)}</small></span>
        ${index === 0 ? '<b>SELECTED</b>' : ''}
      </div>`).join('');

    setSmartPanel(`<div class="smart-sim-result">
      <div class="smart-sim-head"><span>⚡ SMART RESPONSE PREVIEW</span><small>Click another place to test again</small></div>
      <div class="smart-winner"><span>Nearest available unit</span><strong>🚑 ${esc(winner.ambulance_name)}</strong><div><b>${winner.distance_km.toFixed(2)} km</b><b>ETA ${winner.eta} min</b></div></div>
      <div class="smart-ranking">${rankings}</div>
      <p>Decision rule: choose the <strong>minimum Haversine GPS distance</strong> among ambulances whose status is <strong>available</strong>. This is a what-if preview and does not change the database.</p>
    </div>`, true);
  }

  function fitDivision() {
    const bounds = lastDivisionBounds.length ? lastDivisionBounds : lastMapBounds;
    if (!map || !bounds.length) return;
    map.fitBounds(bounds, { padding: [36, 36], maxZoom: 10 });
  }

  function updateRiskVisibility() {
    if (map && riskLayer) {
      if (riskVisible && !map.hasLayer(riskLayer)) riskLayer.addTo(map);
      if (!riskVisible && map.hasLayer(riskLayer)) map.removeLayer(riskLayer);
    }

    const demoRiskRoads = $('demoRiskRoads');
    if (demoRiskRoads) demoRiskRoads.classList.toggle('risk-layer-hidden', !riskVisible);

    const button = $('riskToggleButton');
    if (button) button.textContent = riskVisible ? 'Hide Risk Markers' : 'Show Risk Markers';

    const mapShell = $('map');
    if (mapShell) mapShell.classList.toggle('risk-layer-off', !riskVisible);
  }

  function toggleRiskRoads() {
    riskVisible = !riskVisible;
    updateRiskVisibility();
  }

  function focusRiskRoad(roadId) {
    const id = String(roadId);
    if (!riskVisible) {
      riskVisible = true;
      updateRiskVisibility();
    }

    const marker = riskMarkers.get(id);
    if (map && marker) {
      map.setView(marker.getLatLng(), Math.max(map.getZoom(), 15), { animate: true });
      marker.openPopup();
    }

    document.querySelectorAll('[data-road-id]').forEach((element) => {
      element.classList.toggle('risk-road-focus', element.dataset.roadId === id);
    });
    window.setTimeout(() => {
      document.querySelectorAll('.risk-road-focus').forEach((element) => element.classList.remove('risk-road-focus'));
    }, 1800);
  }

  async function refresh(manual) {
    try {
      const data = await getData();
      lastData = data;
      renderLeaflet(data);
      renderDemoStage(data);
      renderRiskPanel(data);
      renderMission(data);
      renderFeed(data.logs || []);
      renderFleet(data.ambulances || []);
      updateRiskVisibility();

      const mission = findActiveMission(data);
      if (mission && mission.ambulance.status === 'on_the_way') {
        setStatus(running ? 'Simulation: running' : 'Simulation: ready — press Start Movement');
      } else if (mission && mission.ambulance.status === 'arrived') {
        setStatus('Simulation: ambulance arrived');
        pauseSimulation(false);
      } else {
        setStatus('Simulation: waiting for dispatch');
      }

      if (manual && !mission) {
        alert('Road-risk demo refreshed. No active ambulance mission yet. Verify an incident and dispatch an ambulance from Admin Reports.');
      }
    } catch (error) {
      renderFeed([{ action: 'Map data error', note: error.message, created_at: '' }]);
      setStatus('Simulation: API error');
    }
  }

  async function tick() {
    if (!running) return;
    try {
      await fetch(`../api/simulate-ambulance.php?token=browser-demo&ts=${Date.now()}`, { cache: 'no-store' });
      await refresh(false);
    } catch (error) {
      setStatus('Simulation: error');
    }
  }

  async function stepOnce() {
    try {
      setStatus('Simulation: moving one step...');
      await fetch(`../api/simulate-ambulance.php?manual=1&ts=${Date.now()}`, { cache: 'no-store' });
      await refresh(false);
    } catch (error) {
      setStatus('Simulation: error');
    }
  }

  function startSimulation() {
    if (running) return;
    running = true;
    setStatus('Simulation: running');
    tick();
    timer = window.setInterval(tick, 3500);
  }

  function pauseSimulation(updateStatus) {
    running = false;
    if (timer) window.clearInterval(timer);
    timer = null;
    if (updateStatus !== false) setStatus('Simulation: paused');
  }

  function autoStartIfMission() {
    if (!lastData) return;
    const mission = findActiveMission(lastData);
    if (mission && mission.ambulance.status === 'on_the_way' && !running) startSimulation();
  }

  window.SafeRoadMap = {
    refresh,
    startSimulation,
    pauseSimulation,
    stepOnce,
    toggleRiskRoads,
    focusRiskRoad,
    toggleSmartSimulator,
    clearSmartSimulation,
    fitDivision,
    toggleSimulation() {
      if (running) pauseSimulation();
      else startSimulation();
    }
  };

  document.addEventListener('DOMContentLoaded', async () => {
    clearSmartSimulation();
    await refresh(false);
    window.setTimeout(autoStartIfMission, 700);
    window.setInterval(() => refresh(false), 5000);
    window.addEventListener('resize', () => {
      if (lastData) renderDemoStage(lastData);
    });
  });
}());
