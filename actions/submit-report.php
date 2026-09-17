<?php
session_start(); require_once __DIR__ . "/../Config/db.php";
if(!isset($_SESSION['user_id'])){ header('Location: ../login.php'); exit(); }
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: ../pages/submit-report.php'); exit(); }
function clean($v){ return trim((string)$v); }
function uploadEvidence(string $field, array $allowed, int $maxBytes): ?string {
    if(empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if($_FILES[$field]['error'] !== UPLOAD_ERR_OK) throw new RuntimeException("Upload failed for $field.");
    if($_FILES[$field]['size'] > $maxBytes) throw new RuntimeException("$field file is too large.");
    $tmp=$_FILES[$field]['tmp_name']; $finfo=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($finfo,$tmp); finfo_close($finfo);
    if(!in_array($mime,$allowed,true)) throw new RuntimeException("Invalid $field file type: $mime");
    $ext=match($mime){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov','video/x-msvideo'=>'avi',default=>'bin'};
    $dir=dirname(__DIR__).'/assets/uploads/reports'; if(!is_dir($dir)) mkdir($dir,0775,true);
    $name=$field.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext; $dest=$dir.'/'.$name;
    if(!move_uploaded_file($tmp,$dest)) throw new RuntimeException("Could not save $field.");
    return 'reports/'.$name;
}
try{
    $title=clean($_POST['title']??''); $road=clean($_POST['road_name']??''); $location=clean($_POST['location']??''); $desc=clean($_POST['description']??'');
    if($title===''||$location===''||$desc==='') throw new RuntimeException('Title, location and description are required.');
    $lat=($_POST['latitude']??'')!=='' ? (float)$_POST['latitude'] : null; $lng=($_POST['longitude']??'')!=='' ? (float)$_POST['longitude'] : null;
    if($lat===null || $lng===null) throw new RuntimeException('GPS latitude and longitude are required. Use current location, click the map, or enter coordinates.');
    if($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) throw new RuntimeException('Invalid GPS coordinates.');
    $severity=clean($_POST['severity']??'Medium'); $type=clean($_POST['emergency_type']??'Road Accident'); $phone=clean($_POST['reporter_phone']??''); $injured=max(0,(int)($_POST['people_injured']??0));
    $vehicles=clean($_POST['vehicles_involved']??''); $landmark=clean($_POST['nearest_landmark']??''); $police=isset($_POST['police_required'])?1:0; $fire=isset($_POST['fire_service_required'])?1:0;
    $image=uploadEvidence('image',['image/jpeg','image/png','image/webp'],8*1024*1024); $video=uploadEvidence('video',['video/mp4','video/webm','video/quicktime','video/x-msvideo'],50*1024*1024);
    $priority=20; $priority += ['Low'=>5,'Medium'=>20,'High'=>40,'Critical'=>55][$severity] ?? 20; $priority += min(20,$injured*7); if($lat!==null&&$lng!==null) $priority+=8; if($image) $priority+=5; if($video) $priority+=7; if($police||$fire) $priority+=5; $priority=min(100,$priority);
    $uid=(int)$_SESSION['user_id'];
    $stmt=$conn->prepare("INSERT INTO reports (user_id,title,road_name,location,latitude,longitude,description,severity,emergency_type,reporter_phone,people_injured,vehicles_involved,nearest_landmark,police_required,fire_service_required,image,video,status,priority_score) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Pending',?)");
    $stmt->bind_param('isssddssssissiissi',$uid,$title,$road,$location,$lat,$lng,$desc,$severity,$type,$phone,$injured,$vehicles,$landmark,$police,$fire,$image,$video,$priority);
    $stmt->execute(); $rid=$stmt->insert_id;
    $note="Citizen submitted emergency report with priority {$priority}/100."; $log=$conn->prepare("INSERT INTO response_logs (report_id, action, note, created_by) VALUES (?, 'Report Submitted', ?, ?)"); $log->bind_param('isi',$rid,$note,$uid); $log->execute();
    $_SESSION['success']='Emergency report submitted successfully. Login as admin to run AI triage and dispatch ambulance.'; header('Location: ../pages/report-details.php?id='.$rid); exit();
}catch(Throwable $e){ $_SESSION['error']='Submit failed: '.$e->getMessage(); header('Location: ../pages/submit-report.php'); exit(); }
?>
