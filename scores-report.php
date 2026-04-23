<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include("audit_notifications.php");
include_once("semester-registry-utils.php");
semester_registry_ensure_academic_year_column($con);
if(!function_exists('score_report_session_label')){
function score_report_session_label($dateTimeValue, $batchLabel, $termValue){
    $yearValue = "";
    if(trim((string)$dateTimeValue) !== ""){
        $time = strtotime((string)$dateTimeValue);
        if($time){
            $yearValue = date("Y", $time);
        }
    }
    if($yearValue === ""){
        $yearValue = date("Y");
    }

    $batchText = trim((string)$batchLabel);
    if($batchText === ""){
        $batchText = "Not Set";
    }

    $termText = trim((string)$termValue);
    if($termText === ""){
        $termText = "Not Set";
    }

    return trim($yearValue." Batch ".$batchText." Semester ".$termText);
}
}
@$_YearBatchFilter = semester_registry_normalize_year($_GET["year_batch"] ?? "");
@$_YearBatchFilterSafe = mysqli_real_escape_string($con, $_YearBatchFilter);
@$_CurrentClassId = isset($_GET["class_id"]) ? trim($_GET["class_id"]) : "";
@$_CurrentTermId = isset($_GET["term_id"]) ? trim($_GET["term_id"]) : "";
@$_CurrentSubjectId = isset($_GET["subject_id"]) ? trim($_GET["subject_id"]) : "";
@$_CurrentBatchId = isset($_GET["batchid"]) ? trim($_GET["batchid"]) : "";
@$_Mark=$_POST['marks'];
@$_AssignmentId=$_POST['assignmentid'];
@$_UserId=$_POST['userid'];
@$_TotalMark=$_POST['totalscore'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['save_all_mark']))
{
	@$_CheckMark=0;
	foreach ($_Mark as $_Selected_Mark) 
	{
		if($_Selected_Mark>$_TotalMark){
			$_CheckMark=1;
		}
	}
//Check if mark entered is more than the total mark
if($_CheckMark==1){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;padding:10px;background-color:white;'>Total Mark is less than the mark entered</div>";
}else/*No mark is greater than the total mark*/
{

$_TotalUsers =count($_UserId);

for($k=0;$k<$_TotalUsers;$k++)
{
$_Selected_User=$_UserId[$k];
$_Selected_Mark=$_Mark[$k];

		include("code.php");
	@$_MarkId=$code;
	@$_UserFullname="";

	$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.userid='$_Selected_User'");
		
		if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
		$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
		}

	//@$_Subject="";
	//Check if subject already registered
	/*$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject sub INNER JOIN tblsubjectclassification sc ON sub.subjectid=sc.subjectid WHERE sc.classificationid='$_Selected_ClassId'");
	if($row_s=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC)){
	$_Subject=$row_s['subject'];
	$_ClassId=$row_s['classid'];
	//@$_getUser_ID=$row_s['userid'];

	}
	*/

	/*$_SQL_EXECUTE_USER=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa INNER JOIN tblsystemuser su ON sa.userid=su.userid WHERE sa.classificationid='$_Selected_ClassId'");
	if(!mysqli_num_rows($_SQL_EXECUTE_USER)>0){
		$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.userid='$_UserId'");
		
		if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
		$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
		}

	}else{
		if($row_u=mysqli_fetch_array($_SQL_EXECUTE_USER,MYSQLI_ASSOC)){
		$_UserFullname=$row_u['firstname']." ".$row_u['othernames']." ".$row_u['surname']." (".$row_u['userid'].")";
		}
	}
	*/

	//$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId' AND sa.userid='$_UserId' AND sa.classid='$_ClassId'");
	/*$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId'");
	
	if(mysqli_num_rows($_SQL_EXECUTE_2)>0){
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white'><i class='fa fa-check' style='color:red'></i> $_Subject Already Assigned To $_UserFullname</div>";
		
	}else{
		*/

		$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblmark(markid,assignmentid,userid,testtype,mark,totalmark,datetimeentry,status,recordedby)
		VALUES('$_MarkId','$_AssignmentId','$_Selected_User','Class Score','$_Selected_Mark','$_TotalMark',NOW(),'active','$_Recordedby')");
			if($_SQL_EXECUTE)
			{
		
			$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Selected_Mark Successfully Stored for $_UserFullname</div>";
			}
			else{
				$_Error=mysqli_error($con);
				$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Mark failed to save,$_Error</div>";
			}
	}
	}	
	
}
?>

<?php
include("dbstring.php");
@$_Update_subject=$_POST['update_item'];
@$_Update_subjectid=$_POST['update_subjectid'];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsubject SET subject='$_Update_subject' WHERE subjectid='$_Update_subjectid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Subject Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Subject failed to update,$_Error</div>";
	}
}
?>
<?php
include("dbstring.php");

if(isset($_POST["update_mark"]))
{
@$_MarkId = $_POST["markid"];
@$_NewMark = trim($_POST["new_mark"]);
@$_ReturnClass = $_POST["return_class_id"];
@$_ReturnTerm = $_POST["return_term_id"];
@$_ReturnSubject = $_POST["return_subject_id"];
@$_ReturnBatch = $_POST["return_batchid"];
@$_ReturnYearBatch = isset($_POST["return_year_batch"]) ? $_POST["return_year_batch"] : "";

$_MarkIdSafe = mysqli_real_escape_string($con, $_MarkId);
$_SQL_AUTH = mysqli_query($con,"SELECT mk.markid,mk.totalmark,sa.userid AS teacher_userid
 ,mk.mark AS old_mark,mk.userid AS student_userid,mk.testtype
FROM tblmark mk
INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
WHERE mk.markid='$_MarkIdSafe' LIMIT 1");

if($row_auth=mysqli_fetch_array($_SQL_AUTH,MYSQLI_ASSOC))
{
    $isAdmin = (isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL']=="administrator");
    $isOwnerTeacher = ($row_auth['teacher_userid']==$_SESSION['USERID']);

    if(!$isAdmin && !$isOwnerTeacher){
        $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>You are not allowed to edit this mark.</div>";
    } else if(!is_numeric($_NewMark) || $_NewMark<0 || $_NewMark>$row_auth['totalmark']){
        $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Invalid mark. It must be between 0 and ".$row_auth['totalmark'].".</div>";
    } else {
        $_NewMarkSafe = mysqli_real_escape_string($con, $_NewMark);
        $_RecordedbySafe = mysqli_real_escape_string($con, $_SESSION['USERID']);
        $_SQL_EXECUTE=mysqli_query($con,"UPDATE tblmark SET mark='$_NewMarkSafe', datetimeentry=NOW(), recordedby='$_RecordedbySafe' WHERE markid='$_MarkIdSafe'");
        if($_SQL_EXECUTE){
            if (isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'Teacher') {
                logSystemChange(
                    $con,
                    "SCORE_EDIT",
                    "Teacher edited ".$row_auth['testtype']." from ".$row_auth['old_mark']." to ".$_NewMark." for student ".$row_auth['student_userid'].". MarkId: ".$_MarkId
                );
            }
            $_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Mark updated successfully.</div>";
        } else {
            $_Error=mysqli_error($con);
            $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Failed to update mark: $_Error</div>";
        }
    }
} else {
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Mark record not found.</div>";
}

header("Location: scores-report.php?class_id=".urlencode($_ReturnClass)."&term_id=".urlencode($_ReturnTerm)."&subject_id=".urlencode($_ReturnSubject)."&batchid=".urlencode($_ReturnBatch)."&year_batch=".urlencode($_ReturnYearBatch));
exit();
}

if(isset($_POST["bulk_delete_students_scores"]))
{
@$_ReturnClass = isset($_POST["return_class_id"]) ? trim($_POST["return_class_id"]) : "";
@$_ReturnTerm = isset($_POST["return_term_id"]) ? trim($_POST["return_term_id"]) : "";
@$_ReturnSubject = isset($_POST["return_subject_id"]) ? trim($_POST["return_subject_id"]) : "";
@$_ReturnBatch = isset($_POST["return_batchid"]) ? trim($_POST["return_batchid"]) : "";
@$_ReturnYearBatch = isset($_POST["return_year_batch"]) ? trim($_POST["return_year_batch"]) : "";
@$_BulkStudents = (isset($_POST["bulk_userid"]) && is_array($_POST["bulk_userid"])) ? $_POST["bulk_userid"] : array();

$_RedirectUrl = "scores-report.php?class_id=".urlencode($_ReturnClass)."&term_id=".urlencode($_ReturnTerm)."&subject_id=".urlencode($_ReturnSubject)."&batchid=".urlencode($_ReturnBatch)."&year_batch=".urlencode($_ReturnYearBatch);

if($_ReturnClass=="" || $_ReturnSubject=="" || $_ReturnBatch==""){
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Bulk delete failed: missing class, subject or batch context.</div>";
    header("Location: ".$_RedirectUrl);
    exit();
}

if(count($_BulkStudents)<1){
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Select at least one student for bulk delete.</div>";
    header("Location: ".$_RedirectUrl);
    exit();
}

$_StudentInList=array();
$_SeenStudents=array();
foreach($_BulkStudents as $_StudentId){
    $_StudentId=trim($_StudentId);
    if($_StudentId=="" || isset($_SeenStudents[$_StudentId])){
        continue;
    }
    $_SeenStudents[$_StudentId]=1;
    $_StudentInList[]="'".mysqli_real_escape_string($con,$_StudentId)."'";
}

if(count($_StudentInList)<1){
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>No valid student selected for bulk delete.</div>";
    header("Location: ".$_RedirectUrl);
    exit();
}

$_ClassSafe = mysqli_real_escape_string($con, $_ReturnClass);
$_SubjectSafe = mysqli_real_escape_string($con, $_ReturnSubject);
$_BatchSafe = mysqli_real_escape_string($con, $_ReturnBatch);
$_TermSafe = mysqli_real_escape_string($con, $_ReturnTerm);
$_SessionUserSafe = mysqli_real_escape_string($con, $_SESSION['USERID']);
$_StudentInSql = implode(",", $_StudentInList);
$isAdmin = (isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL']=="administrator");
$_TeacherScopeSql = (!$isAdmin ? " AND sa.userid='$_SessionUserSafe' " : "");

if(!$isAdmin){
    $_SQL_ASSIGN = mysqli_query(
        $con,
        "SELECT sa.assignmentid
         FROM tblsubjectassignment sa
         INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
         WHERE sa.userid='$_SessionUserSafe'
           AND sc.classid='$_ClassSafe'
           AND sc.subjectid='$_SubjectSafe'
           AND sa.batchid='$_BatchSafe'
           ".($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")."
           ".($_TermSafe!="" ? " AND sa.termname='$_TermSafe' " : "")."
         LIMIT 1"
    );
    if(!$_SQL_ASSIGN || mysqli_num_rows($_SQL_ASSIGN)<1){
        $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>You are not allowed to bulk delete these scores.</div>";
        header("Location: ".$_RedirectUrl);
        exit();
    }
}

mysqli_begin_transaction($con);
$_DeleteCount=0;
$_DeleteStudentCount=0;
$_DeletedStudents=array();
$_DeleteError="";

$_SQL_CHECK_DELETE = mysqli_query(
    $con,
    "SELECT mk.markid,mk.userid
     FROM tblmark mk
     INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
     INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
     WHERE sc.classid='$_ClassSafe'
       AND sc.subjectid='$_SubjectSafe'
       AND sa.batchid='$_BatchSafe'"
    .($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")
    .($_TermSafe!="" ? " AND sa.termname='$_TermSafe' " : "")
    ." AND mk.userid IN ($_StudentInSql)
       AND mk.testtype IN ('Class Score','Exam Score')
       $_TeacherScopeSql"
);

if($_SQL_CHECK_DELETE){
    while($row_del=mysqli_fetch_array($_SQL_CHECK_DELETE,MYSQLI_ASSOC)){
        $_DeleteCount++;
        $_DeletedStudents[$row_del['userid']]=1;
    }
    $_DeleteStudentCount=count($_DeletedStudents);
}else{
    $_DeleteError=mysqli_error($con);
}

if($_DeleteError==""){
    $_SQL_DELETE = mysqli_query(
        $con,
        "DELETE mk
         FROM tblmark mk
         INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
         INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
         WHERE sc.classid='$_ClassSafe'
           AND sc.subjectid='$_SubjectSafe'
           AND sa.batchid='$_BatchSafe'"
        .($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")
        .($_TermSafe!="" ? " AND sa.termname='$_TermSafe' " : "")
        ." AND mk.userid IN ($_StudentInSql)
           AND mk.testtype IN ('Class Score','Exam Score')
           $_TeacherScopeSql"
    );
    if(!$_SQL_DELETE){
        $_DeleteError=mysqli_error($con);
    }
}

if($_DeleteError!=""){
    mysqli_rollback($con);
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Bulk delete failed: $_DeleteError</div>";
}else{
    mysqli_commit($con);
    if($_DeleteCount>0){
        if (isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'Teacher') {
            logSystemChange(
                $con,
                "SCORE_BULK_DELETE",
                "Teacher bulk deleted ".$_DeleteCount." score row(s) for ".$_DeleteStudentCount." student(s). Types: Class Score/Exam Score. Class: ".$_ReturnClass.", Subject: ".$_ReturnSubject.", Batch: ".$_ReturnBatch
            );
        }
        $_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Bulk delete successful. Removed ".$_DeleteCount." mark row(s) for ".$_DeleteStudentCount." student(s).</div>";
    }else{
        $_SESSION['Message']="<div style='color:#8a6d3b;text-align:center;background-color:white'>No Class Score/Exam Score found for selected students in this report context.</div>";
    }
}

header("Location: ".$_RedirectUrl);
exit();
}

if(isset($_GET["delete_mark"]))
{
$_MarkIdSafe = mysqli_real_escape_string($con, $_GET["delete_mark"]);
$_SQL_AUTH = mysqli_query($con,"SELECT mk.markid,sa.userid AS teacher_userid
 ,mk.mark,mk.userid AS student_userid,mk.testtype
FROM tblmark mk
INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
WHERE mk.markid='$_MarkIdSafe' LIMIT 1");
if($row_auth=mysqli_fetch_array($_SQL_AUTH,MYSQLI_ASSOC))
{
    $isAdmin = (isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL']=="administrator");
    $isOwnerTeacher = ($row_auth['teacher_userid']==$_SESSION['USERID']);
    if(!$isAdmin && !$isOwnerTeacher){
        $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>You are not allowed to delete this mark.</div>";
    } else {
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblmark WHERE markid='$_MarkIdSafe'");
	if($_SQL_EXECUTE){
    if (isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'Teacher') {
        logSystemChange(
            $con,
            "SCORE_DELETE",
            "Teacher deleted ".$row_auth['testtype']." mark ".$row_auth['mark']." for student ".$row_auth['student_userid'].". MarkId: ".$_MarkIdSafe
        );
    }
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Mark Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Mark failed to delete,Error:$_Error</div>";
	}
    }
} else {
    $_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Mark record not found.</div>";
}
}
?>

<html>
<head>
<?php
include("links.php");
?>
</head>
<body>
<div class="header">
<?php
include("menu.php");
?>		
</div>

<div class="main-platform" style="background-color:white"><br/>
<table width="100%">
<tr>
<td width="30%">
<div class="form-entry">
<form id="formID" name="formID" method="post" action="scores-report.php">
<h4>SUBJECTS</h4>
<?php
include("dbstring.php");
echo "<div style='margin-bottom:10px;'>";
echo "<label style='font-weight:bold;'>Academic Year</label><br/>";
echo "<select id='year_batch' name='year_batch' onchange='window.location=\"scores-report.php?year_batch=\"+encodeURIComponent(this.value)' style='width:100%;padding:6px;'>";
echo "<option value=''>All Years</option>";
$_SQL_BF=mysqli_query($con,"SELECT DISTINCT YEAR(datetimeentry) AS academicyear FROM tblsubjectassignment ORDER BY academicyear DESC");
while($row_bf=mysqli_fetch_array($_SQL_BF,MYSQLI_ASSOC)){
    $_YearOption = trim((string)$row_bf["academicyear"]);
    $_sel = ($_YearBatchFilter===$_YearOption) ? "selected" : "";
    echo "<option value='$_YearOption' $_sel>$_YearOption</option>";
}
echo "</select>";
echo "</div>";
?>
<?php	
if(($_SESSION["ACCESSLEVEL"]=="administrator"||$_SESSION["ACCESSLEVEL"]=="user") && ($_SESSION["SYSTEMTYPE"]=="super_user" ||$_SESSION["SYSTEMTYPE"]=="normal_user"||$_SESSION["SYSTEMTYPE"]=="User"))
{
include("dbstring.php");
$_SQL_2=mysqli_query($con,"SELECT sa.*, sa.datetimeentry AS assignment_datetimeentry, sc.*, sub.*, ce.*, bch.batch FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	INNER JOIN tblbatch bch ON bch.batchid=sa.batchid
	".($_YearBatchFilterSafe!="" ? " WHERE ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")."
	ORDER BY ce.class_name,sa.termname ASC");

//echo "<select id='classid' name='classid' class='validate[required]'>";
	//echo "<option value=''>Select Subject</option>";
	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
		$_SessionLabel = score_report_session_label($row['assignment_datetimeentry'], $row['batch'], $row['termname']);
		echo "<div style='padding:5px;background-color:#eee'><a style='color:royalblue;' href='scores-report.php?admin_class_id=$row[class_entryid]&term_id=$row[termname]&subject_id=$row[subjectid]&batchid=$row[batchid]&year_batch=".urlencode($_YearBatchFilter)."'><i class='fa fa-plus' style='color:darkgreen'></i> $row[class_name]: $row[subject] - $_SessionLabel</a></div><br/>";
	}
//echo "</select><br/><br/>";
/*
	$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			*/
}
elseif($_SESSION["ACCESSLEVEL"]=="user" && $_SESSION["SYSTEMTYPE"]=="Teacher")
{
include("dbstring.php");
$_SQL_2=mysqli_query($con,"SELECT sa.*, sa.datetimeentry AS assignment_datetimeentry, sc.*, sub.*, ce.*, bch.batch FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	INNER JOIN tblbatch bch ON bch.batchid=sa.batchid
	WHERE sa.userid='$_SESSION[USERID]' ".($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe' " : "")." ORDER BY ce.class_name,sa.termname ASC");

	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
		$_SessionLabel = score_report_session_label($row['assignment_datetimeentry'], $row['batch'], $row['termname']);
		echo "<div style='padding:5px;background-color:#eee'><a style='color:royalblue;' href='scores-report.php?class_id=$row[class_entryid]&term_id=$row[termname]&subject_id=$row[subjectid]&batchid=$row[batchid]&year_batch=".urlencode($_YearBatchFilter)."'><i class='fa fa-plus' style='color:darkgreen'></i> $row[class_name]: $row[subject] - $_SessionLabel</a></div><br/>";
	}
}
?>

</form>
</div>
</td>
<td width="70%">
<div class="form-entry">
<form id="formID2" name="formID2" method="post" action="scores-report.php">
<input type="hidden" name="return_class_id" value="<?php echo htmlspecialchars($_CurrentClassId,ENT_QUOTES); ?>" />
<input type="hidden" name="return_term_id" value="<?php echo htmlspecialchars($_CurrentTermId,ENT_QUOTES); ?>" />
<input type="hidden" name="return_subject_id" value="<?php echo htmlspecialchars($_CurrentSubjectId,ENT_QUOTES); ?>" />
<input type="hidden" name="return_batchid" value="<?php echo htmlspecialchars($_CurrentBatchId,ENT_QUOTES); ?>" />
<input type="hidden" name="return_year_batch" value="<?php echo htmlspecialchars($_YearBatchFilter,ENT_QUOTES); ?>" />
<?php
echo $_SESSION['Message'];
include("dbstring.php");

if(isset($_GET['class_id']))
{
echo "<div style='margin:8px 0 12px 0;padding:8px;border:1px solid #ddd;background:#fafafa;'>";
echo "<label style='display:inline-block;margin-right:16px;'><input type='checkbox' id='bulk_select_students' onclick='toggleBulkStudents(this)' /> Select All Students</label>";
echo "<button type='submit' name='bulk_delete_students_scores' onclick='return confirmBulkDeleteStudents();' style='background:#b22222;color:white;border:0;padding:8px 10px;cursor:pointer;'><i class='fa fa-trash-o'></i> Delete Selected Students Class + Exam Scores</button>";
echo "</div>";
$_SQL_2=mysqli_query($con,"SELECT sa.*, ".semester_registry_assignment_year_sql("sa")." AS assignment_year, sa.datetimeentry AS assignment_datetimeentry, sc.*, sub.*, ce.* FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE sa.userid='$_SESSION[USERID]' AND sc.subjectid='$_GET[subject_id]' AND sa.batchid='$_GET[batchid]' ".($_YearBatchFilterSafe!="" ? " AND ".semester_registry_assignment_year_sql("sa")."='$_YearBatchFilterSafe'" : "")." ORDER BY ce.class_name,sa.termname ASC");


//$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student'  ORDER BY su.userid");

echo "<table width='100%' style='background-color:white'>";
echo "<caption>";
echo "Scores Report";
echo "</caption>";
echo "<thead><th>*</th><th>SUBJECT</th><th>STUDENT</th><th>CLASS</th><th>SESSION</th><th>TYPE</th><th>MARK</th><th>TOTAL</th></thead>";
echo "<tbody>";
@$serial=0;
while($row_sub=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
{
@$_BatchName="";
$_SQL_Batch=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$row_sub[batchid]'");
if($rowb=mysqli_fetch_array($_SQL_Batch,MYSQLI_ASSOC)){
$_BatchName=$rowb["batch"];	
}
$_SessionHeading = score_report_session_label($row_sub['assignment_datetimeentry'], $_BatchName, $row_sub['termname']);
echo "<tr style='background-color:#FFF;'><td align='left' colspan='8'>".strtoupper($row_sub['subject']).": ".strtoupper($_SessionHeading) ."</td></tr>";


//$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsubject");

//SUBJECT
/*echo "<tr style='background-color:#cff;font-weight:bold'>";
echo "<td colspan='1'></td>";
echo "<td align='left' colspan='7'>";
echo strtoupper($row_rsu['subject']);
echo "</td></tr>";
*/
$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
	ON ce.class_entryid=tr.class_entryid WHERE tr.class_entryid='$row_sub[class_entryid]' AND tr.batchid='$row_sub[batchid]' AND ".semester_registry_resolved_year_sql("tr")."='$row_sub[assignment_year]'");
if(mysqli_num_rows($_SQL_CLASS)==0){
}else{
while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$row_ce[userid]' AND su.systemtype='Student'  ORDER BY su.userid");

if($row_rsu=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC)){
echo "<tr style='background-color:#FFF;font-weight:bold'>";
echo "<td colspan='1'>";
echo "<input type='checkbox' class='bulk-student-checkbox' name='bulk_userid[]' value='$row_rsu[userid]' style='margin-right:6px;' />";
echo $serial=$serial+1 .".";
echo "</td>";
echo "<td align='left' colspan='7'>";
echo strtoupper($row_rsu['firstname']." ".$row_rsu['othernames']." ".$row_rsu['surname']);
echo "(".$row_rsu['userid'].")";
echo "</td></tr>";

for($k=1;$k<3;$k++){
$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
		INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
		WHERE su.userid='$row_rsu[userid]' AND sa.batchid='$row_sub[batchid]' AND ".semester_registry_assignment_year_sql("sa")."='$row_sub[assignment_year]'
		AND ce.class_entryid='$row_ce[class_entryid]' AND sa.termname='$k' 
		AND sub.subjectid='$_GET[subject_id]'
		ORDER BY su.userid ASC");

if(mysqli_num_rows($_SQL_EXECUTE)==0){

}else{
	echo "<tr style='background-color:#FFF;'>";
	echo "<td colspan='2'></td>";
	echo "<td colspan='1'>$row_ce[class_name]</td>";
	echo "<td colspan='5'>";
	echo "SESSION: ".score_report_session_label($row_sub['assignment_datetimeentry'], $_BatchName, $k);
	echo "</td></tr>";

	@$_TotalMark=0;

	while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
	{
	echo "<tr>";
	echo "<td colspan='4' align='right'>";
	echo "<a title='Edit score: $row[mark]' href='scores-report.php?class_id=$_GET[class_id]&term_id=$_GET[term_id]&subject_id=$_GET[subject_id]&batchid=$_GET[batchid]&year_batch=".urlencode($_YearBatchFilter)."&edit_mark=$row[markid]'><i class='fa fa-edit' style='color:royalblue'></i></a> ";
	echo "<a onclick=\"javascript:return confirm('Do you to delete mark?')\" title='Delete score: $row[mark]' href='scores-report.php?class_id=$_GET[class_id]&term_id=$_GET[term_id]&subject_id=$_GET[subject_id]&batchid=$_GET[batchid]&year_batch=".urlencode($_YearBatchFilter)."&delete_mark=$row[markid]'><i class='fa fa-trash-o' style='color:red'></i></a>";
	echo "</td>";

	//echo "<td align='center' width='5%' colspan='1'>";
	//echo $serial=$serial+1;
	//echo "</td>";

	/*echo "<td align='left' width='20%'>";
	echo $row['subject'];
	echo "</td>";
	*/
	echo "<td align='left' width='15%'>";
	echo $row['testtype'];
	echo "</td>";

	echo "<td align='center' width='15%'>";
	echo $row['mark'];
	$_TotalMark=$_TotalMark+$row['mark'];
	echo "</td>";

	echo "</tr>";

    if(isset($_GET['edit_mark']) && $_GET['edit_mark']==$row['markid']){
    echo "<tr style='background-color:#fff7e6'>";
    echo "<td colspan='8'>";
    echo "<form method='post' action='scores-report.php' style='margin:0;display:inline-block'>";
    echo "<input type='hidden' name='markid' value='$row[markid]' />";
    echo "<input type='hidden' name='return_class_id' value='$_GET[class_id]' />";
    echo "<input type='hidden' name='return_term_id' value='$_GET[term_id]' />";
    echo "<input type='hidden' name='return_subject_id' value='$_GET[subject_id]' />";
    echo "<input type='hidden' name='return_batchid' value='$_GET[batchid]' />";
    echo "<input type='hidden' name='return_year_batch' value='".htmlspecialchars($_YearBatchFilter,ENT_QUOTES)."' />";
    echo "<label style='margin-right:8px;'>Edit Mark (Max $row[totalmark])</label>";
    echo "<input type='number' name='new_mark' min='0' max='$row[totalmark]' value='$row[mark]' step='0.01' required style='width:120px;text-align:center;margin-right:8px;' />";
    echo "<button class='button-save' name='update_mark' value='1'><i class='fa fa-save'></i> Save</button> ";
    echo "<a class='button-show' href='scores-report.php?class_id=$_GET[class_id]&term_id=$_GET[term_id]&subject_id=$_GET[subject_id]&batchid=$_GET[batchid]&year_batch=".urlencode($_YearBatchFilter)."'>Cancel</a>";
    echo "</form>";
    echo "</td>";
    echo "</tr>";
    }
	}	
	echo "<tr style='background-color:#eee;font-weight:bold'>";
	echo "<td colspan='6'>";
	echo "</td>";

	echo "<td align='right' colspan='1'>";
	echo "TOTAL:";
	echo "</td>";
	echo "<td align='center'>";
	echo $_TotalMark;
	echo "</td>";
	echo "</tr>";
	}
	}
	}
}
}
}
echo "</tbody>";
echo "</table>";
}
?>
</form>

<form id="formID2" name="formID2" method="post" action="scores-report.php">
<?php 
/*echo $_SESSION['Message'];
include("dbstring.php");

if(isset($_GET['admin_class_id']))
{
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE  sc.subjectid='$_GET[subject_id]' ORDER BY ce.class_name,sa.termname ASC");


//$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student'  ORDER BY su.userid");

echo "<table width='100%' style='background-color:white'>";
echo "<caption>";
echo "</caption>";
echo "<thead><th>SUBJECT</th><th>STUDENT</th><th>CLASS</th><th>SEMESTER</th><th>*</th><th>TYPE</th><th>MARK</th><th>TOTAL</th></thead>";
echo "<tbody>";
while($row_sub=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
{
echo "<tr style='background-color:#dee;font-weight:bold'><td align='left' colspan='8'>".strtoupper($row_sub['subject'])."</td></tr>";




$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
	ON ce.class_entryid=tr.class_entryid WHERE tr.batchid='$row_sub[batchid]'");
if(mysqli_num_rows($_SQL_CLASS)==0){

}else{
while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{

$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$row_ce[userid]' AND su.systemtype='Student'  ORDER BY su.userid");

while($row_rsu=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC)){

echo "<tr style='background-color:#fee;font-weight:bold'>";
echo "<td colspan='1'></td>";
echo "<td align='left' colspan='7'>";
echo strtoupper($row_rsu['firstname']." ".$row_rsu['othernames']." ".$row_rsu['surname']);
echo "</td></tr>";

for($k=1;$k<3;$k++)
{
	$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
		INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
		WHERE su.userid='$row_rsu[userid]'
		AND ce.class_entryid='$row_ce[class_entryid]' AND sa.termname='$k' AND sub.subjectid='$_GET[subject_id]'
		ORDER BY su.userid ASC");

if(mysqli_num_rows($_SQL_EXECUTE)==0){

}else{
	echo "<tr style='background-color:#dee;font-weight:bold'>";
	echo "<td colspan='2'></td>";
	echo "<td colspan='1'>$row_ce[class_name]</td>";
	echo "<td colspan='5'>";
	echo "SEMESTER: ".$k;
	echo "</td></tr>";

	@$_TotalMark=0;
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
	{
	echo "<tr>";
	echo "<td colspan='4' align='right'>";
	echo "<a onclick=\"javascript:return confirm('Do you to delete mark?')\" href='scores-report.php?delete_mark=$row[markid]'><i class='fa fa-times' style='color:red'></i></a>";
	echo "</td>";

	echo "<td align='center' width='5%' colspan='1'>";
	echo $serial=$serial+1;
	echo "</td>";

	echo "<td align='left' width='15%'>";
	echo $row['testtype'];
	echo "</td>";

	echo "<td align='center' width='15%'>";
	echo $row['mark'];
	$_TotalMark=$_TotalMark+$row['mark'];
	echo "</td>";

	echo "</tr>";
	}	
	echo "<tr style='background-color:#fed;font-weight:bold'>";
	echo "<td colspan='6'>";
	echo "</td>";

	echo "<td align='right' colspan='1'>";
	echo "TOTAL:";
	echo "</td>";
	echo "<td align='center'>";
	echo $_TotalMark;
	echo "</td>";
	echo "</tr>";
	}
	}
	}
}
}
}
echo "</tbody>";
echo "</table>";
}
*/
?>
</form>
</div>
</td>
</tr>
</table>

<br/><br/>
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
function toggleBulkStudents(toggle){
  var boxes=document.getElementsByClassName("bulk-student-checkbox");
  for(var i=0;i<boxes.length;i++){
    boxes[i].checked=toggle.checked;
  }
}

function confirmBulkDeleteStudents(){
  var boxes=document.getElementsByClassName("bulk-student-checkbox");
  var selected=0;
  for(var i=0;i<boxes.length;i++){
    if(boxes[i].checked){
      selected++;
    }
  }
  if(selected<1){
    alert("Select at least one student.");
    return false;
  }
  return confirm("Delete both Class Score and Exam Score for "+selected+" selected student(s)? This cannot be undone.");
}

//Get the button
var mybutton = document.getElementById("myBtn");

// When the user scrolls down 20px from the top of the document, show the button
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

// When the user clicks on the button, scroll to the top of the document
function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}
</script>
</div>
</body>
</html>
