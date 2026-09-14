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
            drw_notify_teacher_of_hod_approval($con, $assignmentId, $userId);
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

/* Keep the filters in PHP so each role can only filter score sheets it is already allowed to see. */
$filterClass = isset($_GET['classid']) ? trim((string)$_GET['classid']) : '';
$filterBatch = isset($_GET['batchid']) ? trim((string)$_GET['batchid']) : '';
$filterYear = isset($_GET['academicyear']) ? trim((string)$_GET['academicyear']) : '';
$filterTerm = isset($_GET['termname']) ? trim((string)$_GET['termname']) : '';
$filterStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$filterOptions = array('classes' => array(), 'batches' => array(), 'years' => array(), 'terms' => array());
foreach($rows as $row){
    $filterOptions['classes'][(string)$row['classid']] = (string)$row['class_name'];
    $filterOptions['batches'][(string)$row['batchid']] = (string)$row['batch'];
    $filterOptions['years'][(string)$row['academicyear']] = (string)$row['academicyear'];
    $filterOptions['terms'][(string)$row['termname']] = (string)$row['termname'];
}
natcasesort($filterOptions['classes']);
natcasesort($filterOptions['batches']);
krsort($filterOptions['years']);
natcasesort($filterOptions['terms']);
$filteredRows = array();
foreach($rows as $row){
    if($filterClass !== '' && (string)$row['classid'] !== $filterClass){ continue; }
    if($filterBatch !== '' && (string)$row['batchid'] !== $filterBatch){ continue; }
    if($filterYear !== '' && (string)$row['academicyear'] !== $filterYear){ continue; }
    if($filterTerm !== '' && (string)$row['termname'] !== $filterTerm){ continue; }
    if($filterStatus !== '' && (string)$row['workflow_status'] !== $filterStatus){ continue; }
    $filteredRows[] = $row;
}
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
body{background:#f4f8fb;font-family:Arial;color:#17314b}.dra{max-width:1250px;margin:25px auto;background:#fff;padding:24px;border-radius:16px}.dra table{width:100%;border-collapse:collapse}.dra th,.dra td{padding:10px;border-bottom:1px solid #dce6ee;text-align:left;font-size:14px;vertical-align:top}.dra form{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}.dra input,.dra select{padding:9px 10px;color:#102a43!important;background:#fff!important;border:1px solid #52728d;border-radius:6px;opacity:1!important;font-size:14px!important;font-weight:500}.dra input::placeholder{color:#475569!important;opacity:1}.dra select option{color:#102a43!important;background:#fff!important}.dra button,.dra .review,.dra .home,.dra .clear-filters{background:#087443;color:#fff;border:0;border-radius:6px;padding:8px 10px;text-decoration:none;display:inline-block;font-weight:bold}.dra .review{background:#075985}.dra .home{float:right;background:#334155}.dra .clear-filters{background:#64748b}.workflow-filters{display:flex;gap:9px;align-items:end;flex-wrap:wrap;margin:18px 0;padding:14px;background:#edf6f5;border:1px solid #c9e2dc;border-radius:10px}.workflow-filters label{display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:bold;color:#17314b}.workflow-filters select{box-sizing:border-box!important;display:block!important;width:190px!important;min-width:190px!important;height:44px!important;min-height:44px!important;padding:0 34px 0 11px!important;line-height:20px!important;vertical-align:middle!important}.workflow-filters button,.workflow-filters .clear-filters{box-sizing:border-box;height:44px;line-height:20px;padding:11px 12px}.workflow-filters button{cursor:pointer}.filter-count{margin:0 0 10px;font-size:13px;color:#475569}.s{padding:4px 7px;border-radius:20px;background:#eef2f7;font-weight:bold;font-size:12px}.scroll{overflow:auto}@media(max-width:760px){.dra{margin:8px;padding:12px}.dra table{min-width:1100px}.dra .home{float:none;margin-bottom:12px}.workflow-filters select{width:100%!important;min-width:0!important}}
</style>
</head><body><main class="dra">
<h1>Department Result Approval</h1>
<a class="home" href="<?php echo dra($homePage); ?>"><i class="fa fa-home"></i> Dashboard Home</a>
<p>Signed in as <strong><?php echo dra($role); ?></strong>. Teacher &rarr; HOD &rarr; Assistant Head Academics &rarr; Administrator release.</p>
<?php if($message !== ''){ ?><p><?php echo dra($message); ?></p><?php } ?>
<form class="workflow-filters" method="get" action="department-result-approval.php">
<label>Class<select name="classid"><option value="">All classes</option><?php foreach($filterOptions['classes'] as $id => $name){ ?><option value="<?php echo dra($id); ?>"<?php echo $filterClass === (string)$id ? ' selected' : ''; ?>><?php echo dra($name); ?></option><?php } ?></select></label>
<label>Batch<select name="batchid"><option value="">All batches</option><?php foreach($filterOptions['batches'] as $id => $name){ ?><option value="<?php echo dra($id); ?>"<?php echo $filterBatch === (string)$id ? ' selected' : ''; ?>><?php echo dra($name); ?></option><?php } ?></select></label>
<label>Academic year<select name="academicyear"><option value="">All years</option><?php foreach($filterOptions['years'] as $value => $name){ ?><option value="<?php echo dra($value); ?>"<?php echo $filterYear === (string)$value ? ' selected' : ''; ?>><?php echo dra($name); ?></option><?php } ?></select></label>
<label>Semester<select name="termname"><option value="">All semesters</option><?php foreach($filterOptions['terms'] as $value => $name){ ?><option value="<?php echo dra($value); ?>"<?php echo $filterTerm === (string)$value ? ' selected' : ''; ?>>Semester <?php echo dra($name); ?></option><?php } ?></select></label>
<label>Workflow status<select name="status"><option value="">All statuses</option><option value="submitted"<?php echo $filterStatus === 'submitted' ? ' selected' : ''; ?>>Waiting for HOD Approval</option><option value="hod_approved"<?php echo $filterStatus === 'hod_approved' ? ' selected' : ''; ?>>Waiting for Academic Approval</option><option value="academic_approved"<?php echo $filterStatus === 'academic_approved' ? ' selected' : ''; ?>>Academic Approved</option><option value="returned"<?php echo $filterStatus === 'returned' ? ' selected' : ''; ?>>Returned for Correction</option><option value="draft"<?php echo $filterStatus === 'draft' ? ' selected' : ''; ?>>Not Submitted</option></select></label>
<button type="submit"><i class="fa fa-filter"></i> Apply Filters</button><a class="clear-filters" href="department-result-approval.php">Clear</a>
</form>
<p class="filter-count">Showing <strong><?php echo count($filteredRows); ?></strong> of <?php echo count($rows); ?> available subject score sheets.</p>
<div class="scroll"><table><tr><th>Department / Subject</th><th>Teacher</th><th>Class Scope</th><th>Status</th><th>Review &amp; Action</th></tr>
<?php foreach($filteredRows as $row){ ?><tr>
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
<?php if(empty($filteredRows)){ ?><tr><td colspan="5">No score sheets match the selected filters.</td></tr><?php } ?>
</table></div></main></body></html>
