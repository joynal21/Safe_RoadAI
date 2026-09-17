<?php
session_start(); require_once __DIR__ . "/../Config/db.php";
if(!isset($_SESSION['user_id']) || ($_SESSION['role']??'')!=='admin'){ $_SESSION['error']='Only admin can reset demo.'; header('Location: ../login.php'); exit(); }
try{
 $conn->begin_transaction();
 $conn->query("DELETE FROM response_logs"); $conn->query("DELETE FROM reports");
 $conn->query("UPDATE ambulance_locations SET status='available', assigned_report_id=NULL, destination_latitude=NULL, destination_longitude=NULL, current_mission=NULL, last_ping=NOW()");
 $conn->query("UPDATE ambulance_locations SET latitude=23.8515000, longitude=90.4084000, speed_kmh=42 WHERE id=1");
 $conn->query("UPDATE ambulance_locations SET latitude=23.8067000, longitude=90.3687000, speed_kmh=38 WHERE id=2");
 $conn->query("UPDATE ambulance_locations SET latitude=23.7450000, longitude=90.3922000, speed_kmh=46 WHERE id=3");
 $conn->query("UPDATE ambulance_locations SET latitude=23.7106000, longitude=90.4257000, speed_kmh=44 WHERE id=4");
 $cit=$conn->query("SELECT id FROM users WHERE role='citizen' ORDER BY id LIMIT 1")->fetch_assoc(); $uid=(int)($cit['id']??0);
 $title='Critical bus and motorcycle collision near City Hospital'; $road='Mirpur Road'; $loc='Mirpur Road, near City Hospital, Dhaka'; $lat=23.7820100; $lng=90.3506500; $desc='A bus hit a motorcycle near the hospital gate. Two people are injured, road is blocked, traffic is heavy, ambulance support is urgently needed.'; $sev='Critical'; $type='Road Accident'; $phone='01700000000'; $inj=2; $vehicles='Bus, Motorcycle'; $land='City Hospital Main Gate'; $pol=1; $fire=0; $priority=96;
 $stmt=$conn->prepare("INSERT INTO reports (user_id,title,road_name,location,latitude,longitude,description,severity,emergency_type,reporter_phone,people_injured,vehicles_involved,nearest_landmark,police_required,fire_service_required,status,priority_score) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending',?)");
 $stmt->bind_param('isssddssssissiii',$uid,$title,$road,$loc,$lat,$lng,$desc,$sev,$type,$phone,$inj,$vehicles,$land,$pol,$fire,$priority); $stmt->execute(); $rid=$stmt->insert_id;
 $admin=(int)$_SESSION['user_id']; $note='Demo system reset and one critical accident case seeded.'; $log=$conn->prepare("INSERT INTO response_logs (report_id,action,note,created_by) VALUES (?,'Demo Reset',?,?)"); $log->bind_param('isi',$rid,$note,$admin); $log->execute();
 $conn->commit(); $_SESSION['success']='Demo reset complete. One critical incident is ready for AI analysis and dispatch.'; header('Location: ../pages/admin-reports.php'); exit();
}catch(Throwable $e){ try{$conn->rollback();}catch(Throwable $x){} $_SESSION['error']='Reset failed: '.$e->getMessage(); header('Location: ../pages/admin-control-panel.php'); exit(); }
?>
