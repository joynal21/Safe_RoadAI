# SafeRoad AI V3 - Live Road Safety & Emergency Response Command Center

SafeRoad AI is a PHP + MySQL final-year project for accident reporting, AI-assisted triage, admin verification, nearest-ambulance dispatch, live ambulance tracking, and road-level 30-day accident-risk analytics.

## V3 final-presentation improvements

- **Accident alerts are attached to roads instead of looking random.** Reports are matched to the nearest known road geometry and the map uses the road-matched point for presentation.
- **The Auto-fill Demo Accident GPS is now directly on Mirpur Road.** This fixes the earlier mismatch where the title said Mirpur Road but the coordinates were about 1.46 km away from the drawn segment.
- Every risk road now has a visible **START** badge and **END** badge, and **both ends show the 30-day accident total** for that road.
- Road counts are **database-backed and auto-refresh every 5 seconds**.
- A dedicated `road_accident_history` table stores sample 30-day history for the classroom demo. New citizen reports are matched to roads and added to the totals live.
- Risk states update automatically: **green = 0-2**, **yellow = 3-5**, **red = 6+** accidents.
- The dashboard clearly shows whether it is reading the live database or emergency fallback values.
- Active ambulance movement, ETA, fleet state, activity logs, and road-risk analytics are shown on the same command map.
- The built-in fallback map still works if OpenStreetMap tiles are unavailable.

## Demo accounts

Admin:
- Email: `admin@saferoad.test`
- Password: `admin123`

Citizen:
- Email: `citizen@saferoad.test`
- Password: `user123`

## How to run in XAMPP

1. Copy the `saferoad` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL**.
3. Open `http://localhost/saferoad/`.
4. The app automatically creates/updates the `saferoad_ai` database and the required tables.
5. You can also import `database/saferoad_ai.sql` first, but it is not required.

## Recommended final demo flow

1. Login as **Citizen**.
2. Open **Report Accident**.
3. Click **Auto-fill Demo Accident**. Point out that the GPS is on **Mirpur Road**.
4. Submit the accident.
5. Logout and login as **Admin**.
6. Open **Incidents**, click **Analyze**, then **Verify**, then **Dispatch**.
7. Open **Live Map**.
8. First point to the red/yellow/green roads and the **START / END accident-count badges**.
9. Point to **LIVE DATABASE** and the matched-report counter.
10. Click a road to focus it and show its 30-day total and road span.
11. Press **Start Movement** to show the nearest ambulance moving toward the road-matched accident.
12. Finish with the incident timeline / activity feed.

## Viva-safe explanation of “live” data

Use this wording:

> “The map is live in the sense that it reads the MySQL database and refreshes automatically every five seconds. The historical 30-day road-risk events are seeded sample data for this academic prototype, while newly submitted accident reports are matched to road geometry and added to the road totals immediately.”

Do **not** claim that the seeded history is official Bangladesh government accident data.

## AI explanation

The current AI module is an **AI-assisted triage scoring prototype**, not a trained computer-vision model. It uses report evidence, GPS availability, severity, injury count, accident/vehicle keywords, and suspicious irrelevant-media terms to calculate a confidence/priority result. The architecture is ready for a trained CNN/video model to replace or extend that scoring stage later.


## V3.1 map presentation fix
The Live Map no longer draws approximate colored risk polylines over OpenStreetMap. Each tracked road now has one large colored dot anchored to a known on-road point at the beginning of the road, with the 30-day accident count inside the dot and a small “Beginning of road” note. Submitted accident markers keep their reported GPS for display instead of being visually moved onto coarse demo geometry.


## V6 nearest-ambulance upgrade
- Four ambulances are distributed across Dhaka for a clearer fleet demo.
- Citizen reports can use any valid GPS point: current device GPS, manual coordinates, or click-anywhere map selection.
- After verification, dispatch calculates straight-line geographic (Haversine) distance from the accident to every available ambulance and atomically assigns the nearest unit.
- Admin Incident Management shows the currently nearest available unit and its distance before dispatch.
- Busy ambulances are excluded, so simultaneous incidents can be assigned to different available units.

## V8 - Dhaka Division road analytics and Smart What-If Simulator

V8 expands road-risk analytics to 20 registered corridors across all 13 districts of Dhaka Division. Reports on roads outside the registry are still counted by creating a dynamic road/spot group from the submitted road name. Repeated same-road reports increase the same 30-day count.

The Live Map now preserves the operator's zoom and pan during the five-second refresh cycle. Risk locations use GPS-anchored Leaflet circle markers. A new Smart What-If Simulator lets the operator click any map location to preview and rank available ambulances by Haversine distance and estimated response time without creating or dispatching a real incident.

Historical road-risk rows are seeded academic demo data. Newly submitted reports are stored in MySQL and added to the live road counts.
