<?php
session_start();
include('check-login.php');
include('dbstring.php');
include_once('department-result-workflow-utils.php');
include_once('score-entry-utils.php');

drw_ensure_tables($con);

function dsr_esc($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$userId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$assignmentId = isset($_GET['assignmentid']) ? trim((string)$_GET['assignmentid']) : '';
$isAdmin = drw_is_admin();
$isAcademic = drw_is_academic_lead();
$hodDepartments = drw_departments_for_hod($con, $userId);
$hodDepartmentIds = array();
foreach($hodDepartments as $department){
    $hodDepartmentIds[(string)$department['departmentid']] = true;
}

$assignment = null;
$students = array();
$error = '';
if($assignmentId === ''){
    $error = 'No score sheet was selected.';
}else{
    $assignmentSafe = mysqli_real_escape_string($con, $assignmentId);
    $assignmentSql = "SELECT sa.assignmentid,sa.userid AS teacherid,sa.classid,sa.batchid,sa.termname,
        YEAR(sa.datetimeentry) AS academicyear,d.departmentid,d.departmentname,sub.subject,
        ce.class_name,b.batch,CONCAT_WS(' ',u.firstname,u.othernames,u.surname) AS teacher_name
        FROM tblsubjectassignment sa
        INNER JOIN tbldepartmentsubject ds ON ds.classificationid=sa.classificationid
        INNER JOIN tbldepartment d ON d.departmentid=ds.departmentid AND d.status='active'
        INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=sa.classid
        LEFT JOIN tblbatch b ON b.batchid=sa.batchid
        LEFT JOIN tblsystemuser u ON u.userid=sa.userid
        WHERE sa.assignmentid='$assignmentSafe' AND sa.status='active' LIMIT 1";
    $assignmentResult = mysqli_query($con, $assignmentSql);
    $assignment = $assignmentResult ? mysqli_fetch_assoc($assignmentResult) : null;
    if(!$assignment){
        $error = 'This score sheet could not be found.';
    }elseif(!$isAdmin && !$isAcademic && !isset($hodDepartmentIds[(string)$assignment['departmentid']])){
        http_response_code(403);
        $error = 'You do not have permission to review this department score sheet.';
        $assignment = null;
    }
}

if($assignment){
    $context = score_entry_assignment_student_context(
        $con, $assignment['assignmentid'], $assignment['classid'], $assignment['batchid'],
        $assignment['academicyear'], $assignment['termname']
    );
    $studentIds = isset($context['userids']) && is_array($context['userids']) ? $context['userids'] : array();
    if(!empty($studentIds)){
        $ids = array();
        foreach($studentIds as $studentId){
            $studentId = trim((string)$studentId);
            if($studentId !== ''){ $ids[] = "'".mysqli_real_escape_string($con, $studentId)."'"; }
        }
        if(!empty($ids)){
            $scoreSql = "SELECT su.userid,su.firstname,su.othernames,su.surname,
                MAX(CASE WHEN mk.testtype='Class Score' THEN mk.mark END) AS class_mark,
                MAX(CASE WHEN mk.testtype='Class Score' THEN mk.totalmark END) AS class_total,
                MAX(CASE WHEN mk.testtype='Exam Score' THEN mk.mark END) AS exam_mark,
                MAX(CASE WHEN mk.testtype='Exam Score' THEN mk.totalmark END) AS exam_total
                FROM tblsystemuser su
                LEFT JOIN tblmark mk ON mk.userid=su.userid
                    AND mk.assignmentid='$assignmentSafe' AND mk.status='active'
                WHERE su.userid IN (".implode(',', $ids).")
                GROUP BY su.userid,su.firstname,su.othernames,su.surname
                ORDER BY su.firstname,su.othernames,su.surname,su.userid";
            $scoreResult = mysqli_query($con, $scoreSql);
            if($scoreResult){ while($row = mysqli_fetch_assoc($scoreResult)){ $students[] = $row; } }
        }
    }
}
?>
<!doctype html>
<html><head>
<?php include('links.php'); ?>
<title>Individual Score Review</title>
<style>
body{background:#f4f8fb;font-family:Arial;color:#17314b}.dsr{max-width:1180px;margin:25px auto;background:#fff;padding:24px;border-radius:16px}.dsr a{color:#075985;font-weight:bold}.dsr table{width:100%;border-collapse:collapse}.dsr th,.dsr td{padding:11px;border-bottom:1px solid #dce6ee;text-align:left}.dsr th{background:#edf6f2}.dsr .summary{background:#eef7f1;padding:14px;border-radius:10px;margin:16px 0}.dsr .missing{color:#9f1239;font-weight:bold}.dsr .complete{color:#087443;font-weight:bold}.scroll{overflow:auto}@media(max-width:700px){.dsr{margin:8px;padding:14px}.dsr table{min-width:760px}}
</style>
</head><body><main class="dsr">
<p><a href="department-result-approval.php">&larr; Back to Department Result Approval</a></p>
<h1>Individual Student Score Review</h1>
<?php if($error !== ''){ ?><p class="missing"><?php echo dsr_esc($error); ?></p><?php } ?>
<?php if($assignment){ ?>
<div class="summary"><strong><?php echo dsr_esc($assignment['departmentname'].' - '.$assignment['subject']); ?></strong><br>
Teacher: <?php echo dsr_esc($assignment['teacher_name']); ?> &middot; <?php echo dsr_esc($assignment['class_name'].' / '.$assignment['batch'].' / Semester '.$assignment['termname'].' / '.$assignment['academicyear']); ?><br>
Roster source: <?php echo !empty($context['uses_course_registration']) ? 'Course registration' : 'Semester registry'; ?> &middot; Students: <?php echo count($students); ?></div>
<div class="scroll"><table><tr><th>#</th><th>Student</th><th>Index No.</th><th>Class Score</th><th>Exam Score</th><th>Total</th><th>Score Status</th></tr>
<?php foreach($students as $index => $student){
    $hasClass = $student['class_mark'] !== null; $hasExam = $student['exam_mark'] !== null;
    $total = ($hasClass ? (float)$student['class_mark'] : 0) + ($hasExam ? (float)$student['exam_mark'] : 0);
    $possible = ($hasClass ? (float)$student['class_total'] : 0) + ($hasExam ? (float)$student['exam_total'] : 0);
    $complete = $hasClass && $hasExam;
?><tr><td><?php echo $index + 1; ?></td><td><?php echo dsr_esc(trim($student['firstname'].' '.$student['othernames'].' '.$student['surname'])); ?></td><td><?php echo dsr_esc($student['userid']); ?></td><td><?php echo $hasClass ? dsr_esc($student['class_mark'].' / '.$student['class_total']) : '<span class="missing">Not entered</span>'; ?></td><td><?php echo $hasExam ? dsr_esc($student['exam_mark'].' / '.$student['exam_total']) : '<span class="missing">Not entered</span>'; ?></td><td><?php echo $complete ? dsr_esc($total.' / '.$possible) : '—'; ?></td><td class="<?php echo $complete ? 'complete' : 'missing'; ?>"><?php echo $complete ? 'Complete' : 'Incomplete'; ?></td></tr><?php } ?>
<?php if(empty($students)){ ?><tr><td colspan="7">No registered students were found for this subject and semester.</td></tr><?php } ?>
</table></div>
<?php } ?>
</main></body></html>
