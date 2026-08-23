<?php
session_start();
require_once('check-login.php');
require_once('student-permission-utils.php');
require_once('class-teacher-utils.php');
student_permission_ensure_table($con);
$teacherId = $_SESSION['USERID'] ?? '';
if(!(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL']==='user' && $_SESSION['SYSTEMTYPE']==='Teacher')){ header('location:index.php'); exit; }
if(!class_teacher_has_any_assignment($con, $teacherId)){ header('location:teacher-page.php'); exit; }
if(empty($_SESSION['spr_csrf'])) $_SESSION['spr_csrf']=bin2hex(random_bytes(20));
if($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($_SESSION['spr_csrf'],$_POST['csrf']??'')){
    $id=(int)($_POST['id']??0); $status=($_POST['decision']??'')==='approved'?'approved':'declined'; $note=trim($_POST['note']??'');
    $st=mysqli_prepare($con,"UPDATE tblstudentpermissionrequest SET status=?,decision_note=?,decided_at=NOW() WHERE request_id=? AND teacher_id=? AND status='pending'");
    mysqli_stmt_bind_param($st,'ssis',$status,$note,$id,$teacherId); mysqli_stmt_execute($st); mysqli_stmt_close($st);
}
$teacherEsc=mysqli_real_escape_string($con,$teacherId);
$rows=@mysqli_query($con,"SELECT r.*,CONCAT(COALESCE(s.firstname,''),' ',COALESCE(s.surname,'')) student_name FROM tblstudentpermissionrequest r LEFT JOIN tblsystemuser s ON s.userid=r.student_id WHERE r.teacher_id='$teacherEsc' ORDER BY r.status='pending' DESC,r.created_at DESC");
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student Permission Requests</title><style>body{font:16px Arial;background:#f3f6f8;color:#17324d;margin:0}.wrap{max-width:850px;margin:30px auto;padding:0 15px}.card{background:#fff;padding:22px;margin:15px 0;border-radius:10px;border:1px solid #d9e2ea}.top{display:flex;align-items:center;justify-content:space-between;gap:12px}.back,button{display:inline-block;padding:10px 13px;background:#0f4c75;color:#fff;border:0;border-radius:6px;text-decoration:none;cursor:pointer}.no{background:#b91c1c}textarea{box-sizing:border-box;width:100%;height:60px;padding:8px;margin:8px 0;border:1px solid #bfcdda;border-radius:5px}@media(max-width:520px){.top{align-items:flex-start;flex-direction:column}}</style></head><body><main class="wrap"><div class="card top"><div><h1>Student Permission Requests</h1><p>Review requests from students assigned to your class.</p></div><a class="back" href="teacher-page.php">← Back to Teacher Dashboard</a></div><?php if($rows && mysqli_num_rows($rows)>0){ while($r=mysqli_fetch_assoc($rows)){?><article class="card"><h2><?php echo student_permission_escape(trim($r['student_name'])?:$r['student_id']);?></h2><p><b><?php echo student_permission_escape($r['permission_type']);?></b> — <?php echo student_permission_escape($r['start_datetime']);?> to <?php echo student_permission_escape($r['end_datetime']);?></p><p><?php echo student_permission_escape($r['reason']);?></p><?php if($r['status']==='pending'){?><form method="post"><input type="hidden" name="csrf" value="<?php echo $_SESSION['spr_csrf'];?>"><input type="hidden" name="id" value="<?php echo (int)$r['request_id'];?>"><textarea name="note" placeholder="Decision note"></textarea><button name="decision" value="approved">Approve</button><button class="no" name="decision" value="declined">Decline</button></form><?php }else{?><b><?php echo student_permission_escape(ucfirst($r['status']));?></b><?php }} }else{?><div class="card">No student permission requests have been received yet.</div><?php }?></main></body></html>
