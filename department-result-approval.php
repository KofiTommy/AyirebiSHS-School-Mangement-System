<?php
session_start();
include('check-login.php');
include('dbstring.php');
include_once('user-management-utils.php');
include_once('department-result-workflow-utils.php');

drw_ensure_tables($con);
$userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$isAdmin = drw_is_admin();
$isAcademic = drw_is_academic_lead();
$hodDepartments = drw_departments_for_hod($con, $userId);
$isHod = !empty($hodDepartments);
$message = '';

function dra($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function dra_is_hod_for_department($departments, $departmentId){
    foreach($departments as $department){
        if((string)$department['departmentid'] === (string)$departmentId){ return true; }
    }
    return false;
}

if(isset($_POST['workflow_action'])){
    $assignmentId = trim((string)$_POST['assignmentid']);
    $year = trim((string)$_POST['academicyear']);
    $action = trim((string)$_POST['workflow_action']);
    $note = trim((string)$_POST['note']);
    $assignmentSafe = mysqli_real_escape_string($con, $assignmentId);
    $assignmentResult = mysqli_query($con, "SELECT sa.*,ds.departmentid FROM tblsubjectassignment sa INNER JOIN tbldepartmentsubject ds ON ds.classificationid=sa.classificationid WHERE sa.assignmentid='$assignmentSafe' LIMIT 1");
    $assignment = $assignmentResult ? mysqli_fetch_assoc($assignmentResult) : null;
    $allowed = false; $status = '';
    if($assignment){
        if($action === 'submit' && (string)$assignment['userid'] === $userId){
            $allowed = true; $status = 'submitted';
        }elseif(($action === 'hod_approve' || $action === 'return') && $isHod && dra_is_hod_for_department($hodDepartments, $assignment['departmentid'])){
            $allowed = true; $status = $action === 'hod_approve' ? 'hod_approved' : 'returned';
        }elseif(($action === 'academic_approve' || $action === 'return_academic') && $isAcademic){
            $allowed = true; $status = $action === 'academic_approve' ? 'academic_approved' : 'returned';
        }
    }
    if($allowed && drw_update_status($con, $assignmentId, $year, $status, $userId, $note)){
        if($status === 'submitted'){
            drw_notify_hod_of_submission($con, $assignmentId, $userId);
        }elseif($status === 'hod_approved'){
            drw_notify_academic_of_hod_approval($con, $assignmentId, $userId);
        }elseif($status === 'academic_approved'){
            drw_notify_admin_of_academic_approval($con, $assignmentId, $userId);
        }elseif($status === 'returned'){
            drw_notify_teacher_of_return($con, $assignmentId, $userId, $note);
        }
        $message = 'Workflow updated successfully. The next approver has been notified.';
    }else{
        $message = 'This workflow action is not allowed.';
    }
}

$rows = array();
$baseSql = "SELECT sa.assignmentid,sa.userid,sa.classid,sa.batchid,sa.termname,YEAR(sa.datetimeentry) academicyear,
    sub.subject,ce.class_name,b.batch,d.departmentid,d.departmentname,
    CONCAT_WS(' ',u.firstname,u.othernames,u.surname) teacher_name,
    COALESCE(w.status,'draft') workflow_status,COALESCE(w.lastnote,'') lastnote
    FROM tblsubjectassignment sa
    INNER JOIN tbldepartmentsubject ds ON ds.classificationid=sa.classificationid
    INNER JOIN tbldepartment d ON d.departmentid=ds.departmentid AND d.status='active'
    INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
    INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
    LEFT JOIN tblclassentry ce ON ce.class_entryid=sa.classid
    LEFT JOIN tblbatch b ON b.batchid=sa.batchid
    LEFT JOIN tblsystemuser u ON u.userid=sa.userid
    LEFT JOIN tbldepartmentresultworkflow w ON w.assignmentid=sa.assignmentid AND w.academicyear=YEAR(sa.datetimeentry)
    WHERE sa.status='active'";
if(!$isAdmin && !$isAcademic && !$isHod){
    $baseSql .= " AND sa.userid='".mysqli_real_escape_string($con, $userId)."'";
}elseif($isHod && !$isAcademic && !$isAdmin){
    $departmentIds = array();
    foreach($hodDepartments as $department){ $departmentIds[] = "'".mysqli_real_escape_string($con, $department['departmentid'])."'"; }
    $baseSql .= " AND d.departmentid IN (".implode(',', $departmentIds).")";
}elseif($isAcademic){
    $baseSql .= " AND COALESCE(w.status,'draft') IN ('hod_approved','academic_approved','returned')";
}
$result = mysqli_query($con, $baseSql." ORDER BY d.departmentname,b.batch DESC,sa.termname DESC,sub.subject");
if($result){ while($row = mysqli_fetch_assoc($result)){ $rows[] = $row; } }
$role = $isAdmin ? 'Administrator' : ($isAcademic ? 'Assistant Head Academics' : ($isHod ? 'Head of Department' : 'Teacher'));
$homePage = 'teacher-page.php';
if($isAdmin){
    $homePage = (isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'super_user') ? 'super.php' : 'admin.php';
}elseif($isAcademic){
    $homePage = 'assistant-head-academics-page.php';
}
?>
<!doctype html><html><head>
<?php include('links.php'); ?>
<title>Department Result Approval</title>
<style>
body{background:#f4f8fb;font-family:Arial;color:#17314b}.dra{max-width:1250px;margin:25px auto;background:#fff;padding:24px;border-radius:16px}.dra table{width:100%;border-collapse:collapse}.dra th,.dra td{padding:10px;border-bottom:1px solid #dce6ee;text-align:left;font-size:14px;vertical-align:top}.dra form{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}.dra input{padding:8px;color:#102a43;background:#fff;border:1px solid #9db2c5;border-radius:6px}.dra button,.dra .review,.dra .home{background:#087443;color:#fff;border:0;border-radius:6px;padding:8px 10px;text-decoration:none;display:inline-block;font-weight:bold}.dra .review{background:#075985}.dra .home{float:right;background:#334155}.s{padding:4px 7px;border-radius:20px;background:#eef2f7;font-weight:bold;font-size:12px}.scroll{overflow:auto}@media(max-width:760px){.dra{margin:8px;padding:12px}.dra table{min-width:1100px}.dra .home{float:none;margin-bottom:12px}}
</style>
</head><body><main class="dra">
<h1>Department Result Approval</h1>
<a class="home" href="<?php echo dra($homePage); ?>"><i class="fa fa-home"></i> Dashboard Home</a>
<p>Signed in as <strong><?php echo dra($role); ?></strong>. Teacher &rarr; HOD &rarr; Assistant Head Academics &rarr; Administrator release.</p>
<?php if($message !== ''){ ?><p><?php echo dra($message); ?></p><?php } ?>
<div class="scroll"><table><tr><th>Department / Subject</th><th>Teacher</th><th>Class Scope</th><th>Status</th><th>Review &amp; Action</th></tr>
<?php foreach($rows as $row){ ?><tr>
<td><strong><?php echo dra($row['departmentname']); ?></strong><br><?php echo dra($row['subject']); ?></td>
<td><?php echo dra($row['teacher_name']); ?></td>
<td><?php echo dra($row['class_name'].' · '.$row['batch'].' · Semester '.$row['termname'].' · '.$row['academicyear']); ?></td>
<td><span class="s"><?php echo dra(ucwords(str_replace('_', ' ', $row['workflow_status']))); ?></span><br><small><?php echo dra($row['lastnote']); ?></small></td>
<td><?php if($isAdmin || $isAcademic || ($isHod && dra_is_hod_for_department($hodDepartments, $row['departmentid']))){ ?><a class="review" href="department-score-review.php?assignmentid=<?php echo rawurlencode($row['assignmentid']); ?>">View Individual Scores</a><?php } ?>
<form method="post"><input type="hidden" name="assignmentid" value="<?php echo dra($row['assignmentid']); ?>"><input type="hidden" name="academicyear" value="<?php echo dra($row['academicyear']); ?>"><input name="note" placeholder="Comment (optional)">
<?php if($row['userid'] === $userId && in_array($row['workflow_status'], array('draft','returned'), true)){ ?><button name="workflow_action" value="submit">Submit to HOD</button><?php } ?>
<?php if($isHod && $row['workflow_status'] === 'submitted'){ ?><button name="workflow_action" value="hod_approve">Approve for Academic</button><button name="workflow_action" value="return">Return</button><?php } ?>
<?php if($isAcademic && $row['workflow_status'] === 'hod_approved'){ ?><button name="workflow_action" value="academic_approve">Final Academic Approval</button><button name="workflow_action" value="return_academic">Return</button><?php } ?>
</form></td></tr><?php } ?>
<?php if(empty($rows)){ ?><tr><td colspan="5">No department score sheets are available for your current role.</td></tr><?php } ?>
</table></div></main></body></html>
