# SafeRoad AI V3 - Final Instructor Demo Guide

## 5-minute presentation sequence

1. Open `http://localhost/saferoad/`.
2. Login as citizen: `citizen@saferoad.test` / `user123`.
3. Open **Report Accident** and click **Auto-fill Demo Accident**.
4. Show the road name **Mirpur Road** and the GPS coordinates. Say: “The demo coordinates are intentionally placed on the road geometry, so the alert will not appear at a random point.”
5. Submit the report.
6. Login as admin: `admin@saferoad.test` / `admin123`.
7. Open **Incidents** → **Analyze** → **Verify** → **Dispatch**.
8. Open **Live Map**.
9. Point out the **LIVE DATABASE** label and the 5-second refresh behavior.
10. Point at one large colored dot and say: “The dot is fixed to the beginning of the mapped road. The number inside it is that road’s 30-day accident total; I removed approximate overlay lines so the risk display never appears beside the real road.”
11. Explain the colors: green 0-2, yellow 3-5, red 6+.
12. Click a road card to focus the map and show its road span and count.
13. Show the 🚨 accident alert attached to its matched road position.
14. Press **Start Movement** and show the 🚑 ambulance moving toward the incident.
15. Finish with **Live Activity** and the incident timeline.

## Best answer if instructor asks “Is this really live?”

“The dashboard is live and database-driven. It polls the backend every five seconds, uses MySQL road history plus newly submitted reports, and recalculates the road totals. The historical records are seeded sample data for the academic demo, not official government records.”

## Best answer if instructor asks “Why do you show count at road start and end?”

“Previously a count badge in the middle could look like an accident marker at an arbitrary location. Now the road itself is the analytical unit. START and END define the segment, and both endpoints display the total accidents recorded for the entire segment during the selected 30-day window.”

## Best answer if instructor asks “How do you stop random GPS points?”

“The backend compares a report's road name and GPS coordinate with known road polylines. If it matches, the presentation marker is projected to the nearest point on that road. The original GPS is still stored in the report, but the road-risk map uses the road-matched display coordinate.”

## AI explanation

“This is an AI-assisted emergency triage prototype. It scores report evidence, GPS, severity, injured people, accident keywords and suspicious content. In future work, I can connect a trained CNN/video model for automatic visual accident verification.”

## Before class

- Start Apache and MySQL.
- Open the project once so the schema updater creates `road_accident_history`.
- Login as admin and use **Reset Demo** if old ambulance missions are still active.
- After reset, confirm the seeded accident is on Mirpur Road and can be analyzed/verified/dispatched.
- Open Live Map once and confirm you see one large beginning-of-road risk dot for each road.
- Keep the browser zoom around 90-100% for the cleanest map layout.


## Faculty demo: nearest ambulance
1. Reset the demo so all four ambulances return to their different base GPS positions.
2. Login as citizen and open Report Accident. Click any point on the location map (not only the sample roads).
3. Submit the report, login as admin, analyze and verify it.
4. In Incident Management, point out the **Nearest Available Unit** column and the calculated distance.
5. Click **Dispatch Nearest**. The backend recalculates all available units and assigns only the closest one.
6. Open Live Map: all four fleet positions are visible and only the selected unit travels to the accident GPS.

For viva: the prototype uses Haversine (straight-line GPS) distance. A production system could replace this with road-network travel time from a routing service.
