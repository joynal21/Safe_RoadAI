<?php
session_start(); require_once __DIR__ . "/../Config/db.php";
if(!isset($_SESSION['user_id'])){ header('Location: ../login.php'); exit(); }
if(($_SESSION['role']??'')!=='admin'){ $_SESSION['error']='Only admin can update status.'; header('Location: ../pages/citizen-dashboard.php'); exit(); }
$reportId=$_SERVER['REQUEST_METHOD']==='POST'?(int)($_POST['report_id']??0):(int)($_GET['id']??0); $status=$_SERVER['REQUEST_METHOD']==='POST'?trim($_POST['status']??''):trim($_GET['status']??'');
$allowed=['Pending','Verified','Ambulance Sent','Resolved','Rejected']; if($reportId<=0 || !in_array($status,$allowed,true)){ $_SESSION['error']='Invalid status update.'; header('Location: ../pages/admin-reports.php'); exit(); }
try{
 $conn->begin_transaction(); $resolvedSql=$status==='Resolved'?", resolved_at=NOW()":""; $stmt=$conn->prepare("UPDATE reports SET status=? $resolvedSql WHERE id=?"); $stmt->bind_param('si',$status,$reportId); $stmt->execute();
 if($status==='Resolved'){
   $amb=$conn->prepare("UPDATE ambulance_locations SET status='available', assigned_report_id=NULL, destination_latitude=NULL, destination_longitude=NULL, current_mission=NULL, last_ping=NOW() WHERE assigned_report_id=?"); $amb->bind_param('i',$reportId); $amb->execute();
 }
 $admin=(int)$_SESSION['user_id']; $note="Admin changed incident status to {$status}."; $log=$conn->prepare("INSERT INTO response_logs (report_id,action,note,created_by) VALUES (?,'Status Updated',?,?)"); $log->bind_param('isi',$reportId,$note,$admin); $log->execute();
 $conn->commit(); $_SESSION['success']='Report status updated to '.$status.'.'; header('Location: ../pages/admin-reports.php'); exit();
}catch(Throwable $e){ try{$conn->rollback();}catch(Throwable $x){} $_SESSION['error']='Status update failed: '.$e->getMessage(); header('Location: ../pages/admin-reports.php'); exit(); }
?>
