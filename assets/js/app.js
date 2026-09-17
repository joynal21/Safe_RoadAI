function toggleSidebar(){const el=document.getElementById('srSidebar'); if(el) el.classList.toggle('open');}
function confirmAction(message){return confirm(message || 'Are you sure?');}
function fillDemoReport(){
  const q=(name)=>document.querySelector(`[name="${name}"]`);
  if(q('title')) q('title').value='Critical bus and motorcycle collision near City Hospital';
  if(q('emergency_type')) q('emergency_type').value='Road Accident';
  if(q('road_name')) q('road_name').value='Mirpur Road';
  if(q('nearest_landmark')) q('nearest_landmark').value='City Hospital Main Gate';
  if(q('location')) q('location').value='Mirpur Road, near City Hospital, Dhaka';
  if(q('reporter_phone')) q('reporter_phone').value='01700000000';
  if(q('latitude')) q('latitude').value='23.7820100';
  if(q('longitude')) q('longitude').value='90.3506500';
  if(window.SafeRoadLocationPicker) window.SafeRoadLocationPicker.setLocation(23.7820100,90.3506500,'Demo accident',{lookup:false});
  if(q('severity')) q('severity').value='Critical';
  if(q('people_injured')) q('people_injured').value='2';
  if(q('vehicles_involved')) q('vehicles_involved').value='Bus, Motorcycle';
  if(q('description')) q('description').value='A bus hit a motorcycle near the hospital gate. Two people are injured, road is blocked, traffic is heavy, ambulance support is urgently needed.';
  const status=document.getElementById('geoStatus'); if(status) status.textContent='Demo accident placed directly on the Mirpur Road risk segment with GPS coordinates. Submit it, then login as admin to run AI and dispatch ambulance.';
}
function useCurrentLocation(){
  const status=document.getElementById('geoStatus');
  if(!navigator.geolocation){ if(status) status.textContent='GPS is not supported by this browser.'; return; }
  if(status) status.textContent='Reading your current GPS location...';
  navigator.geolocation.getCurrentPosition(pos=>{
    const lat=document.querySelector('[name="latitude"]'); const lng=document.querySelector('[name="longitude"]');
    if(lat) lat.value=pos.coords.latitude.toFixed(7); if(lng) lng.value=pos.coords.longitude.toFixed(7);
    if(window.SafeRoadLocationPicker) window.SafeRoadLocationPicker.setLocation(pos.coords.latitude,pos.coords.longitude,'Current GPS',{lookup:true});
    if(status) status.textContent='GPS captured. Detecting the current road and place name...';
  },()=>{ if(status) status.textContent='Could not read GPS. Use Auto-fill Demo Accident for classroom demo.'; },{enableHighAccuracy:true,timeout:8000});
}
function copyDemo(text){navigator.clipboard?.writeText(text);}
