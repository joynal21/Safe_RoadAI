<?php
session_start(); require_once __DIR__ . "/../Config/db.php";
if(!isset($_SESSION['user_id'])){ header('Location: ../login.php'); exit(); }
if(($_SESSION['role']??'')!=='admin'){ $_SESSION['error']='Only admin can dispatch ambulance.'; header('Location: ../pages/citizen-dashboard.php'); exit(); }
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: ../pages/admin-reports.php'); exit(); }
function sr_distance_km(float $lat1,float $lon1,float $lat2,float $lon2): float{ $r=6371; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1); $a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2; return $r*2*atan2(sqrt($a),sqrt(1-$a)); }
try{
 $reportId=(int)($_POST['report_id']??0); if($reportId<=0) throw new RuntimeException('Invalid report ID.');
 $stmt=$conn->prepare("SELECT * FROM reports WHERE id=? LIMIT 1"); $stmt->bind_param('i',$reportId); $stmt->execute(); $report=$stmt->get_result()->fetch_assoc(); if(!$report) throw new RuntimeException('Report not found.');
 if($report['latitude']===null || $report['longitude']===null) throw new RuntimeException('GPS latitude and longitude are required for dispatch.');
 if(($report['status']??'')==='Resolved') throw new RuntimeException('This report is already resolved.');
 if(($report['status']??'')!=='Verified') throw new RuntimeException('Verify the incident before dispatching an ambulance.');
 $lat=(float)$report['latitude']; $lng=(float)$report['longitude'];
 $ambResult=$conn->query("SELECT * FROM ambulance_locations WHERE status='available' AND assigned_report_id IS NULL ORDER BY id ASC");
 $best=null; $bestDistance=PHP_FLOAT_MAX; while($a=$ambResult->fetch_assoc()){ $d=sr_distance_km((float)$a['latitude'],(float)$a['longitude'],$lat,$lng); if($d<$bestDistance){$best=$a;$bestDistance=$d;} }
 if(!$best) throw new RuntimeException('No available ambulance now. Reset demo or mark an ambulance available.');
 $ambulanceId=(int)$best['id']; $speed=max(25,(int)($best['speed_kmh']??40)); $eta=max(1,(int)ceil(($bestDistance/$speed)*60)); $mission='Report #'.$reportId.': '.substr((string)$report['title'],0,120);
 $conn->begin_transaction();
 $up=$conn->prepare("UPDATE ambulance_locations SET status='on_the_way', assigned_report_id=?, destination_latitude=?, destination_longitude=?, current_mission=?, last_ping=NOW() WHERE id=? AND status='available'"); $up->bind_param('iddsi',$reportId,$lat,$lng,$mission,$ambulanceId); $up->execute(); if($up->affected_rows<1) throw new RuntimeException('Ambulance became unavailable. Try again.');
 $ur=$conn->prepare("UPDATE reports SET status='Ambulance Sent', assigned_ambulance_id=?, ambulance_eta_minutes=?, response_started_at=COALESCE(response_started_at,NOW()) WHERE id=?"); $ur->bind_param('iii',$ambulanceId,$eta,$reportId); $ur->execute();
 $admin=(int)$_SESSION['user_id']; $note="Nearest available ambulance {$best['ambulance_name']} dispatched. Distance ".number_format($bestDistance,2)." km, ETA {$eta} minute(s)."; $log=$conn->prepare("INSERT INTO response_logs (report_id,ambulance_id,action,note,created_by) VALUES (?,?,'Ambulance Dispatched',?,?)"); $log->bind_param('iisi',$reportId,$ambulanceId,$note,$admin); $log->execute();
 $conn->commit(); $_SESSION['success']="{$best['ambulance_name']} dispatched successfully. Open Live Map to watch movement."; header('Location: ../pages/map.php'); exit();
}catch(Throwable $e){ if(isset($conn)){try{$conn->rollback();}catch(Throwable $x){}} $_SESSION['error']='Dispatch failed: '.$e->getMessage(); header('Location: ../pages/admin-reports.php'); exit(); }
?>
