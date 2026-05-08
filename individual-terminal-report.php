<?php
session_start();
$_SESSION['Message']="";
include("positions.php");
include("class-position.php");
include_once("dbstring.php");
include_once("semester-registry-utils.php");
include_once("report-approval-utils.php");
semester_registry_ensure_academic_year_column($con);

@$_position_obj=new Position;
@$_position_obj_1=new Position;
@$_class_position_obj=new ClassPosition;
?>

<?php
//Declare the variables
@$_UserID=$_SESSION['USERID'];

//@$todayTime =$_POST['today_time2'];
@$_BatchId=$_POST['batchid'];
@$_AcademicYear=trim((string)$_POST['academicyear']);
@$_TermId=$_POST['termid'];
@$_ClassId=$_POST['classid'];
@$_ReportApprovalMessage="";

if(isset($_POST["print_terminal_report"]))
{
      include("dbstring.php");
      include("class-teacher-utils.php");
      ensure_student_terminal_term_column($con);
      include_once("house-master-utils.php");
      if(function_exists('ensure_house_tables')){
          ensure_house_tables($con);
      }
      $_ReportApprovalMeta = report_approval_scope_meta($con, $_BatchId, $_AcademicYear, $_TermId, $_ClassId);
      if(report_approval_is_student_user() && $_ReportApprovalMeta['required'] && !$_ReportApprovalMeta['allowed']){
          $_ReportApprovalMessage = "<div style='margin:12px 0;padding:12px 14px;border-radius:14px;background:#fff7ed;border:1px solid rgba(194,65,12,0.14);color:#c2410c;font-weight:600;'>This class report is waiting for admin approval. Students can only view it after approval.</div>";
      }else{
      include("config.php");
      include("company.php");
      include("remark.php");
      include("gradingsystem.php");
     // include("positions.php");

       //@$_remark_obj=new Remark;
       @$_grade_obj=new GradingSystem;
     
@$_SchoolCloses="";
@$_NextTermBegins="";
$_TermFilter = (isset($_TermId) && trim((string)$_TermId)!=="") ? (int)$_TermId : 0;
$_AcademicYearFilter = trim((string)$_AcademicYear);
@$_AcademicYearLabel=$_AcademicYearFilter;
@$_SemesterLabel=($_TermFilter>0 ? (string)$_TermFilter : '');
if($_TermFilter>0 && $_AcademicYearFilter!==""){
$_AcademicYearFilterEsc=mysqli_real_escape_string($con,$_AcademicYearFilter);
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' AND termname='$_TermFilter' AND academicyear='$_AcademicYearFilterEsc' ORDER BY datetimeentry DESC LIMIT 1");
}elseif($_TermFilter>0){
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' AND termname='$_TermFilter' ORDER BY datetimeentry DESC LIMIT 1");
}else{
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' ORDER BY termname DESC, datetimeentry DESC LIMIT 1");
}
if((!$_SQL_IN || mysqli_num_rows($_SQL_IN)===0) && $_TermFilter>0 && $_AcademicYearFilter===""){
$_SQL_IN=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId' ORDER BY termname DESC, datetimeentry DESC LIMIT 1");
}
if($row_in=mysqli_fetch_array($_SQL_IN,MYSQLI_ASSOC))
{
$_SchoolCloses=$row_in['schoolcloses'];
$_NextTermBegins=$row_in['schoolresumes'];
$_AcademicYearLabel=trim((string)(isset($row_in['academicyear']) ? $row_in['academicyear'] : ''));
if($_AcademicYearLabel===""){
    $_AcademicYearLabel=(trim((string)$row_in['datetimeentry'])!=="" ? date("Y",strtotime((string)$row_in['datetimeentry'])) : "");
}
}

@$_Roll=0;
@$_Attendance=0;
@$_TotalAttendance=0;
@$_Promotedto="";
@$_Conduct="";
@$_Interest="";
@$_Class_Teacher_Remark="";
@$_Head_Teacher_Remark="";
@$_StudentHouseName="Not Assigned";

if(function_exists('get_student_active_house')){
$_StudentHouse=get_student_active_house($con,$_UserID);
if(is_array($_StudentHouse) && !empty($_StudentHouse['housename'])){
	$_StudentHouseName=trim((string)$_StudentHouse['housename']);
}
}

$_TermFilter = (isset($_TermId) && $_TermId!=="") ? (int)$_TermId : 0;
if($_TermFilter > 0){
$_SQL_Terminal=mysqli_query($con,"SELECT * FROM tblstudentterminalreport WHERE userid='$_UserID' AND batchid='$_BatchId' AND (termname='$_TermFilter' OR termname='0') ORDER BY termname DESC, datetimeentry DESC LIMIT 1");
}else{
$_SQL_Terminal=mysqli_query($con,"SELECT * FROM tblstudentterminalreport WHERE userid='$_UserID' AND batchid='$_BatchId' ORDER BY datetimeentry DESC LIMIT 1");
}
if($row_ter=mysqli_fetch_array($_SQL_Terminal,MYSQLI_ASSOC)){
	$_Roll=$row_ter['roll'];
	$_Attendance=$row_ter['attendance'];
	$_TotalAttendance=$row_ter['totalattendance'];
	$_Promotedto=$row_ter['promotedto'];
	$_Conduct=$row_ter['conduct'];
	$_Interest=$row_ter['interest'];
	$_Class_Teacher_Remark=$row_ter['class_teacher_remark'];
	$_Head_Teacher_Remark=$row_ter['head_teacher_remark'];
    if($_SemesterLabel==="" && isset($row_ter['termname'])){
        $_SemesterCandidate = trim((string)$row_ter['termname']);
        if($_SemesterCandidate !== "" && $_SemesterCandidate !== "0"){
            $_SemesterLabel = $_SemesterCandidate;
        }
    }
}
      //Get all the ordered items

  //ob_start();
//Declare the variables
//$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
//$today = date("Y-m-d",$tomorrow);
 //@$_GrandTotal=0;

$_SQL_EXECUTE_SP="SELECT *,su.userid FROM tblmark mk 
INNER JOIN tblsystemuser su ON mk.userid=su.userid
INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
INNER JOIN tblbatch bh ON sa.batchid=bh.batchid
WHERE su.userid='$_UserID' AND sa.batchid='$_BatchId' ".($_TermId!=="" ? " AND sa.termname='".mysqli_real_escape_string($con,$_TermId)."'" : "")." ".($_ClassId!=="" ? " AND sa.classid='".mysqli_real_escape_string($con,$_ClassId)."'" : "")." GROUP BY mk.assignmentid";

require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(45,30,25,30,25,35);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',$width_cell[0]+$width_cell[1]+$width_cell[2],3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"GHANA EDUCATION SERVICE",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
//$pdf->SetFont('Arial','B',20);

  $text_height = 5;
  $text_length = 70;
  $n=7;
  $pdf->SetFont('Arial','B',12);

  //Get the summation of all the marks
  @$_OverallScore=0;
  $_AcademicYearAssignmentSql = ($_AcademicYear!=="" ? " AND ".semester_registry_assignment_year_sql("sa")."='".mysqli_real_escape_string($con,$_AcademicYear)."'" : "");
  $_SQL_OM=mysqli_query($con,"SELECT SUM(mk.mark) AS OverallScore FROM tblmark mk INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
 WHERE sa.batchid='$_BatchId' AND mk.userid='$_UserID' ".($_TermId!=="" ? " AND sa.termname='".mysqli_real_escape_string($con,$_TermId)."'" : "")." ".($_ClassId!=="" ? " AND sa.classid='".mysqli_real_escape_string($con,$_ClassId)."'" : "")." $_AcademicYearAssignmentSql");
 if($row_om=mysqli_fetch_array($_SQL_OM,MYSQLI_ASSOC)){
$_OverallScore=$row_om['OverallScore'];
}

 $_class_position_obj->setClassPosition($_BatchId,$_OverallScore,$_TermId,"",$_AcademicYear,$_UserID);
 $_Get_Class_Position = $_class_position_obj->getClassPosition();
 $_class_position_obj->setClassPosition($_BatchId,$_OverallScore,$_TermId,$_ClassId,$_AcademicYear,$_UserID);
 $_ClassPositionLabel = $_class_position_obj->getClassPosition();
 $_ClassCount = $_class_position_obj->getClassCount();

      $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,"Group Year Position: ". $_Get_Class_Position,0,0,'R',true);
      $pdf->SetFont('Arial','B',10);
      $pdf->Ln($n);
      $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,"Class Position: ". $_ClassPositionLabel." / ".$_ClassCount,0,0,'R',true);
      $pdf->SetFont('Arial','B',10);
      $pdf->Ln($n);

      // Re-query because the context fetch above consumed the first row
 	$_Result=mysqli_query($con,$_SQL_EXECUTE_SP);

      if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC))
      {
        if($_SemesterLabel==="" && isset($row_ps['termname'])){
            $_SemesterCandidate = trim((string)$row_ps['termname']);
            if($_SemesterCandidate !== "" && $_SemesterCandidate !== "0"){
                $_SemesterLabel = $_SemesterCandidate;
            }
        }
      	@$_StudentName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
      $pdf->Cell($text_length,$text_height,'Name: '.$_StudentName,0,0,'L',true);
      $pdf->Ln($n);
       $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'Class/Form: '.$row_ps['class_name'],0,0,'L',true);
       $pdf->Cell($width_cell[3]+$width_cell[4]+$width_cell[5],10,'House: '.$_StudentHouseName,0,0,'L',true);
       $pdf->Ln($n);

       $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'No. On Roll: '.$_Roll,0,0,'L',true);
       $pdf->Cell($width_cell[3]+$width_cell[4]+$width_cell[5],10,'Batch: '.$row_ps['batch'],0,0,'L',true);
       $pdf->Ln($n);

       $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'School Closes: '.$_SchoolCloses,0,0,'L',true);
       $pdf->Cell($width_cell[3]+$width_cell[4]+$width_cell[5],10,'Academic Year: '.($_AcademicYearLabel!=="" ? $_AcademicYearLabel : $row_ps['batch']).' | Semester: '.($_SemesterLabel!=="" ? $_SemesterLabel : 'N/A'),0,0,'L',true);
       $pdf->Ln($n);

       $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,'Next Term Begins: '.$_NextTermBegins,0,0,'L',true);
       $pdf->Ln($n);
      }
  

$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',9);
//Header starts //

//First header column //
$pdf->Cell($width_cell[0],10,'SUBJECT',1,0,'C',true);

$pdf->Cell($width_cell[1],10,'CLASS SCORE',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'EXAM SCORE',1,0,'C',true);

$pdf->Cell($width_cell[3],10,'TOTAL SCORE',1,0,'C',true);

$pdf->Cell($width_cell[4],10,'POS IN SUB',1,0,'C',true);

//$pdf->Cell($width_cell[5],10,'REMARKS',1,0,'C',true);
$pdf->Cell($width_cell[5],10,'GRADE',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',9);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);

@$_AdditionalPrice=0;

@$serial=0;
//each record is one row //
foreach ($dbo->query($_SQL_EXECUTE_SP) as $row) 
{

 $pdf->Cell($width_cell[0],10,$row['subject'],1,0,'L',$fill);

@$_ExamScore=0;
 @$_ClassScore=0;

$_SQL_TOT2=mysqli_query($con,"SELECT * FROM tblmark mk 
 	WHERE mk.assignmentid='$row[assignmentid]' AND mk.testtype='Class Score' AND mk.userid='$_SESSION[USERID]'");
 if($row_cl=mysqli_fetch_array($_SQL_TOT2,MYSQLI_ASSOC)){
$_ClassScore=$row_cl['mark'];
 $pdf->Cell($width_cell[1],10,$_ClassScore,1,0,'C',$fill);
 }

 $_SQL_TOT=mysqli_query($con,"SELECT * FROM tblmark mk 
 	WHERE mk.assignmentid='$row[assignmentid]' AND mk.testtype='Exam Score' AND mk.userid='$_SESSION[USERID]'");
 if($row_ex=mysqli_fetch_array($_SQL_TOT,MYSQLI_ASSOC)){
$_ExamScore=$row_ex['mark'];
 $pdf->Cell($width_cell[2],10,$_ExamScore,1,0,'C',$fill); 
 }


@$_TotalScore=$_ExamScore+$_ClassScore;

 $pdf->Cell($width_cell[3],10,$_TotalScore,1,0,'C',$fill);
 
 //Get the positions
 @$_Final_Position=0;

$_position_obj->setPosition($row['assignmentid'],$_TotalScore);
$_Final_Position= $_position_obj->getPosition();

 $pdf->Cell($width_cell[4],10,$_Final_Position,1,0,'C',$fill);

//$_remark_obj->setMark($_TotalScore);
//$_final_remark=$_remark_obj->getMark($_TotalScore);

 //$pdf->Cell($width_cell[5],10,$_final_remark,1,0,'C',$fill);

 $_grade_obj->setMark($_TotalScore);
$_final_grade=$_grade_obj->getMark($_TotalScore);

$pdf->Cell($width_cell[5],10,$_final_grade,1,0,'C',$fill);

 $fill = !$fill;
 $pdf->Ln(10);
}
 $pdf->Ln(1);
//Footer of the table
$pdf->Cell(0,10,'Attendance:........................'.$_Attendance. '...........................Out of............................ '.$_TotalAttendance. '.............................   Promoted to:..................'.$_Promotedto,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,'Conduct:  '.$_Conduct,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,'Interest(Special Aptitude):  '.$_Interest,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,"Class Teacher's Remarks:  ".$_Class_Teacher_Remark,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,"Head Teacher's Remarks:  ".$_Head_Teacher_Remark,0,0,'L',true);
$pdf->Ln(7);
$pdf->Cell(0,10,'Signature:................................................',0,0,'R',true);


$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(255,255,255);
//$pdf->PutLink("http://www.braintechconsult.com","BTC");

 $pdf->Ln(7); 
 $pdf->SetFont('Arial','U',8);
 $pdf->Cell(0,10,'GRADING(S):',0,0,'L',true);
  $pdf->SetFont('Arial','',8);
 $pdf->Ln(6); 
$pdf->Cell($width_cell[0],10,'1. A1 (80%-100%)',0,0,'L',true);
 $pdf->Cell($width_cell[1],10,'3. B3 (65%-69%) ',0,0,'L',true);
 $pdf->Cell($width_cell[2]+$width_cell[3],10,'5. C5 (55%-59%)',0,0,'C',true);
 $pdf->Cell($width_cell[4]+$width_cell[5],10,'7. D7 (45%-49%)',0,0,'C',true);
 $pdf->Ln(6);
 
 $pdf->Cell($width_cell[0],10,'2. B2 (70%-79%)',0,0,'L',true);
 $pdf->Cell($width_cell[1],10,'4. C4 (60%-64%) ',0,0,'L',true);
 $pdf->Cell($width_cell[2]+$width_cell[3],10,'6 C6 (50%-54%) ',0,0,'C',true);
 $pdf->Cell($width_cell[4]+$width_cell[5],10,'8 E8 (40%-44%)',0,0,'C',true); 
 $pdf->Ln(6); 
 $pdf->Cell($width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,'9. F9 (0%-39%)',0,0,'L',true); 
 $pdf->Ln(6); 

$pdf->SetFont('Arial','B',8);

 //$pdf->Ln(10); 
 //$pdf->Cell(0,10,'Developed by: Brainstorm Technologies Consult',0);
 //$pdf->Ln(6); 
 //$pdf->Cell(0,10,'Accra,Takoradi,Koforidua - 0342-292-121',0);
/// end of records ///
$pdf->Output();
 //ob_end_flush(); 
}
}
?>




<?php
include("dbstring.php");
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

if(isset($_GET["delete_mark"]))
{
    $_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblmark WHERE markid='$_GET[delete_mark]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Mark Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Mark failed to delete,Error:$_Error</div>";
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
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="background-color:white">
<table width="100%">
<tr>
<td width="30%">
<div class="form-entry">
<form id="formID" name="formID" method="post" action="individual-terminal-report.php">
	<h4>EXAMINATION REPORT GENERATION</h4>
<?php	
include("dbstring.php");
/*$_SQL_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
	INNER JOIN tblsubjectassignment sa ON tr.batchid=sa.batchid AND tr.termname=sa.termname
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE tr.userid='$_SESSION[USERID]' ORDER BY tr.termname ASC");

echo "<select id='classid' name='classid' class='validate[required]'>";
	echo "<option value=''>Select Subject</option>";
	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
	echo "<option value='$row[class_entryid]'>$row[class_name]:Term: $row[termname] : $row[subject]</option>";
	}
echo "</select><br/><br/>";
*/
echo "<fieldset><legend>BATCH</legend>";
	$_SelectedTermLabel = "";
	$_SelectedBatchId = isset($_POST['batchid']) ? $_POST['batchid'] : '';
	$_SQL_2=mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Academic Year (Batch)</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					$_Sel = ($_SelectedBatchId==$row['batchid']) ? "selected" : "";
					echo "<option value='$row[batchid]' $_Sel>$row[batch]</option>";
			}	
			echo "</select><br/><br/>";
			$_SelectedAcademicYear = isset($_POST['academicyear']) ? trim((string)$_POST['academicyear']) : '';
			echo "<select id='academicyear' name='academicyear' class='validate[required]'>";
			echo "<option value=''>Select Academic Year</option>";
			$_YearWhereSql = "";
			if($_SelectedBatchId!==''){
				$_SelectedBatchIdEsc = mysqli_real_escape_string($con,$_SelectedBatchId);
				$_YearWhereSql = " WHERE batchid='$_SelectedBatchIdEsc' ";
			}
			$_SQL_YEAR_OPT=mysqli_query($con,"
				SELECT DISTINCT academic_year FROM (
					SELECT CASE
						WHEN TRIM(COALESCE(academicyear,''))<>'' THEN academicyear
						ELSE YEAR(datetimeentry)
					END AS academic_year
					FROM tblschoolinfo
					$_YearWhereSql
					UNION
					SELECT YEAR(datetimeentry) AS academic_year
					FROM tblsubjectassignment
					$_YearWhereSql
				) year_options
				WHERE academic_year IS NOT NULL AND academic_year<>''
				ORDER BY academic_year DESC
			");
			if($_SQL_YEAR_OPT){
				while($row_year=mysqli_fetch_array($_SQL_YEAR_OPT,MYSQLI_ASSOC)){
					$_SelYear = ($_SelectedAcademicYear===(string)$row_year['academic_year']) ? "selected" : "";
					echo "<option value='$row_year[academic_year]' $_SelYear>$row_year[academic_year]</option>";
				}
			}
			echo "</select><br/><br/>";
			$_SelectedTermId = isset($_POST['termid']) ? $_POST['termid'] : '';
			if($_SelectedTermId!==""){
				$_SelectedTermLabel = ($_SelectedAcademicYear!=="" ? $_SelectedAcademicYear." | " : "")."Semester ".$_SelectedTermId;
			}
			echo "<select id='termid' name='termid' class='validate[required]'>";
			echo "<option value=''>Select Semester</option>";
			echo "<option value='1' ".($_SelectedTermId==='1' ? "selected" : "").">1</option>";
			echo "<option value='2' ".($_SelectedTermId==='2' ? "selected" : "").">2</option>";
			echo "<option value='3' ".($_SelectedTermId==='3' ? "selected" : "").">3</option>";
			echo "</select><br/><br/>";

			$_SelectedClassId = isset($_POST['classid']) ? $_POST['classid'] : '';
			$_SelectedClassLabel = "";
			if($_SelectedBatchId!=""){
				$_SQL_CLASS_OPT=mysqli_query($con,"SELECT DISTINCT ce.class_entryid,ce.class_name
					FROM tbltermregistry tr
					INNER JOIN tblclassentry ce ON tr.class_entryid=ce.class_entryid
					WHERE tr.userid='$_SESSION[USERID]'
					  AND tr.batchid='".mysqli_real_escape_string($con,$_SelectedBatchId)."'
					  ".($_SelectedAcademicYear!=="" ? " AND ".semester_registry_resolved_year_sql("tr")."='".mysqli_real_escape_string($con,$_SelectedAcademicYear)."'" : "")."
					ORDER BY ce.class_name ASC");
			}else{
				$_SQL_CLASS_OPT=mysqli_query($con,"SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
			}
			echo "<select id='classid' name='classid' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
			while($row_cls=mysqli_fetch_array($_SQL_CLASS_OPT,MYSQLI_ASSOC)){
				$_SelClass = ($_SelectedClassId==$row_cls['class_entryid']) ? "selected" : "";
				echo "<option value='$row_cls[class_entryid]' $_SelClass>$row_cls[class_name]</option>";
				if($_SelectedClassId!="" && $_SelectedClassId==$row_cls['class_entryid']){
					$_SelectedClassLabel = $row_cls['class_name'];
				}
			}
			echo "</select><br/><br/>";
			$_SelectedScopeApprovalMeta = report_approval_scope_meta($con, $_SelectedBatchId, $_SelectedAcademicYear, $_SelectedTermId, $_SelectedClassId);
			if($_SelectedClassId!=='' && $_SelectedTermId!=='' && $_SelectedAcademicYear!=='' && $_SelectedScopeApprovalMeta['required']){
				$_ApprovalBg = $_SelectedScopeApprovalMeta['allowed'] ? "#ecfdf3" : "#fff7ed";
				$_ApprovalBorder = $_SelectedScopeApprovalMeta['allowed'] ? "rgba(22,101,52,0.14)" : "rgba(194,65,12,0.14)";
				$_ApprovalColor = $_SelectedScopeApprovalMeta['allowed'] ? "#166534" : "#c2410c";
				echo "<div style='margin:0 0 10px;padding:10px 12px;border-radius:14px;background:".$_ApprovalBg.";border:1px solid ".$_ApprovalBorder.";color:".$_ApprovalColor.";font-weight:600;'>".$_SelectedScopeApprovalMeta['status_label']."</div>";
			}
			$_DisableStudentPrint = report_approval_is_student_user() && $_SelectedScopeApprovalMeta['required'] && !$_SelectedScopeApprovalMeta['allowed'];
			echo "<button class='button-print' id='print_terminal_report' name='print_terminal_report' ".($_DisableStudentPrint ? "disabled style='opacity:0.6;cursor:not-allowed;'" : "")."><i class='fa fa-print'></i> ".($_DisableStudentPrint ? "Awaiting Approval" : "Print Report")."</button>";
			if($_SelectedTermLabel!=""){
				echo "<div style='margin-top:8px;padding:6px 8px;background:#eef6ff;border:1px solid #bcd;color:#0b63ce;font-weight:600;'>Selected: ".$_SelectedTermLabel.(($_SelectedClassLabel!="" ? " | Class: ".$_SelectedClassLabel : ""))."</div>";
			}
			if($_ReportApprovalMessage!=""){
				echo $_ReportApprovalMessage;
			}
			echo "</fieldset>";
?>

<!--<label>* Total Score</label>
<input type="number" id="totalscore" name="totalscore" value="" placeholder="Total Score" class="validate[required,custom[number]]"/><br/><br/>
-->

</form>
</div>
</td>
<td width="70%">
	<!--
	<div class="form-entry">
	<form id="formID2" name="formID2" method="post" action="individual-terminal-report.php">
<?php
echo $_SESSION['Message'];
include("dbstring.php");
$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$_SESSION[USERID]' AND su.systemtype='Student'  ORDER BY su.userid");

echo "<table width='100%' style='background-color:white'>";
echo "<caption>";
echo "Examination Report";
echo "</caption>";
echo "<thead><th>SUBJECT</th><th>CLASS</th><th>SEMESTER</th><th>*</th><th>TYPE</th><th>MARK</th><th>POSITION</th></thead>";
echo "<tbody>";
if($row_us=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC))
{
$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsubject");
while($row_rsu=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){

//SUBJECT
echo "<tr style='background-color:#cff;font-weight:bold'>";
//echo "<td colspan='1'></td>";
echo "<td align='left' colspan='7'>";
echo strtoupper($row_rsu['subject']);
echo "</td></tr>";

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
	ON ce.class_entryid=tr.class_entryid GROUP BY tr.class_entryid");
if(mysqli_num_rows($_SQL_CLASS)==0){

}else{
while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
echo "<tr style='background-color:#fee;font-weight:bold'>";
echo "<td colspan='1'></td>";
echo "<td align='left' colspan='6'>";
echo strtoupper($row_ce['class_name']);
echo "</td></tr>";

for($k=1;$k<3;$k++)
{
	$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
		INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
		WHERE su.userid='$row_us[userid]' AND sub.subjectid='$row_rsu[subjectid]' 
		AND ce.class_entryid='$row_ce[class_entryid]' AND sa.termname='$k'
		ORDER BY su.userid ASC");

if(mysqli_num_rows($_SQL_EXECUTE)==0){

}else{
	echo "<tr style='background-color:#dee;font-weight:bold'>";
	echo "<td colspan='2'></td>";
	echo "<td colspan='5'>";
	echo "Semester: ".$k;
	echo "</td></tr>";

	@$_TotalMark=0;
	@$_getAssignment_Id=0;
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
	$_getAssignment_Id=$row['assignmentid'];

	echo "<tr>";
	echo "<td colspan='3' align='right'>";
	//echo "<a onclick=\"javascript:return confirm('Do you to delete mark?')\" href='terminal-report.php?delete_mark=$row[markid]'><i class='fa fa-times' style='color:red'></i></a>";
	echo "</td>";

	echo "<td align='center' width='5%' colspan='1'>";
	echo $serial=$serial+1;
	echo "</td>";

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
	}	
	echo "<tr style='background-color:#fed;font-weight:bold'>";
	echo "<td colspan='4'>";
	echo "</td>";

	echo "<td align='right' colspan='1'>";
	echo "TOTAL:";
	echo "</td>";
	echo "<td align='center'>";
	echo $_TotalMark;
	echo "</td>";

	echo "<td align='center' width='5%'>";
	 //Get the positions
	
	 @$_Final_Position=0;
	$_position_obj_1->setPosition($_getAssignment_Id,$_TotalMark);
	$_Final_Position= $_position_obj_1->getPosition();
	echo $_Final_Position;
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
?>
</form>
</div>
-->
</td>
</tr>
</table>
</div>
</body>
</html>
