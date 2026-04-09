<?php
session_start();
//Declare the variables
@$_Batch_ID=$_POST["batchid"];

if(isset($_POST["print_examanalysis_report"]))
{
      include("dbstring.php");
      include("config.php");
      include("company.php");
      include("remark.php");
      include("gradingsystem.php");
      include("positions.php");

@$_grade_obj=new GradingSystem;
@$_position_obj_1=new Position;


require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(25,90,40);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',$width_cell[0]+55,3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"GHANA EDUCATION SERVICE",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
//$pdf->SetFont('Arial','B',20);
$pdf->Ln(10);

  $text_height = 5;
  $text_length = 70;
  $n=7;
  $pdf->SetFont('Arial','B',12);

  
$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',9);
//Header starts //

//First header column //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);
$pdf->Cell($width_cell[1],10,'STUDENT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'TOTAL',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',9);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
//$pdf->Ln(10);
	
@$_Batch_ID=$_POST["batchid"];
include("dbstring.php");
//echo "<input type='hidden' name='batchid' value='$_Batch_ID' />";

$_SQL_ALLCLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
ON ce.class_entryid=tr.class_entryid GROUP BY tr.class_entryid ORDER BY ce.class_name ASC");

while($row_acl=mysqli_fetch_array($_SQL_ALLCLASS,MYSQLI_ASSOC))
{
@$_ClassName=$row_acl["class_name"];

$k=0;
$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser");
while($row_us=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
ON ce.class_entryid=tr.class_entryid WHERE tr.class_entryid='$row_acl[class_entryid]' AND tr.userid='$row_us[userid]' AND tr.batchid='$_Batch_ID'");

while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{
$names[$k]=$row_us['firstname']." ".$row_us['othernames']." ".$row_us['surname']."(".$row_us['userid'].")";
@$_TotalMark=0;		
@$serial=0;

$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
INNER JOIN tblsystemuser su ON mk.userid=su.userid
INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
WHERE su.userid='$row_ce[userid]' AND ce.class_entryid='$row_ce[class_entryid]' AND
sa.batchid='$_Batch_ID'
ORDER BY su.userid ASC");

@$_TotalGrade=0;

while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){

	$_TotalMark=$_TotalMark+$row['mark'];

}
//Store scores for sorting
$scores[$k]=round($_TotalMark);
$k++;
}	
}
//Sorting in Ascending order
		@$_Temps=0;
		@$_TempName="";
		$count=count($scores);
		for($j=0;$j<$count;$j++)
		{
			for($i=0;$i<$count;$i++)
			{
				if($scores[$j]>$scores[$i])
				{
				$_Temps=$scores[$j];
				$_TempName=$names[$j];

				$scores[$j]=$scores[$i];
				$names[$j]=$names[$i];

				$scores[$i]=$_Temps;
				$names[$i]=$_TempName;
				}
		  }
		}
$pdf->Ln(10);
$_SQL_BA=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_Batch_ID'");
if($rowb=mysqli_fetch_array($_SQL_BA,MYSQLI_ASSOC)){
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$rowb["batch"]." Class Ranked",1,0,'L',$fill); 
}
$pdf->Ln(10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_ClassName,1,0,'L',$fill); 

@$serial=0;
for($g=0;$g<$count;$g++)
{
$pdf->Ln(10);
$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill); 
$pdf->Cell($width_cell[1],10,$names[$g],1,0,'L',$fill); 
$pdf->Cell($width_cell[2],10,$scores[$g],1,0,'C',$fill); 
}

}
//}
/// end of records ///
$pdf->Output();
 //ob_end_flush(); 
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
<?php
include("dbstring.php");
if(isset($_POST["updatemark"])){
@$_MarkId=$_POST["newmarkid"];
@$_User_Id=$_POST["userid"];
@$_NewMark=$_POST["newmark"];

$_SQLUM=mysqli_query($con,"UPDATE tblmark SET mark='$_NewMark' WHERE userid='$_User_Id' AND markid='$_MarkId'");
if($_SQLUM){
	$_SESSION['Message']="<div style='border:1px solid #4f5;color:green;text-align:left;background-color:#efe;padding:5px;'>Mark Successfully Updated</div>";
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<script type="text/javascript">
function checkMark(){
	var total=document.getElementById("totalmark").value;
	var mark=document.getElementById("newmark").value;

	if(mark>total){
		document.getElementById("newmark").value="";
		
	}

}
</script>
</head>
<body>
	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="background-color:white">
	<br/>
<table width="100%">
<tr>
<td width="30%">
<div class="form-entry">
<form id="formID" name="formID" method="post" action="examanalysis-rank.php">
	<h3>EXAMS ANALYSIS:Rank</h3>
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
$_SQL_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr INNER JOIN tblbatch bch ON tr.batchid=bch.batchid
			GROUP BY tr.batchid");

echo "<select id='batchid' name='batchid' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[batchid]'>$row[batch]</option>";
}
echo "</select><br/><br/>";
echo "<button class='button-show' id='show_terminal_report' name='show_terminal_report'><i class='fa fa-search' style='color:white'></i> SHOW REPORT</button> ";
echo "</fieldset>";
?>

<!--<label>* Total Score</label>
<input type="number" id="totalscore" name="totalscore" value="" placeholder="Total Score" class="validate[required,custom[number]]"/><br/><br/>
-->

</form>
</div>
</td>
<td width="70%">
<div class="form-entry">

<form id="formID3" name="formID3" method="post" action="examanalysis-rank.php">
<?php
if(isset($_GET["edit_mark"]))
{
	echo "<h3>UPDATE STUDENT'S MARK</h3>";
$_SQL_ED=mysqli_query($con,"SELECT * FROM tblmark mk INNER JOIN 
	tblsystemuser su ON mk.userid=su.userid WHERE mk.markid='$_GET[edit_mark]'");
echo "<table>";
echo "<caption>Mark of Student</caption>";
echo "<thead><th>STUDENT</th><th>BATCH</th><th>SUBJECT</th><th>MARK</th><th>TOTAL</th></thead>";
echo "<tbody>";
if($rows_m=mysqli_fetch_array($_SQL_ED,MYSQLI_ASSOC))
{
echo "<tr>";
echo "<td width='30%'>";
echo "<input type='hidden' id='newmarkid' name='newmarkid' value='$_GET[edit_mark]'readonly/>";

echo "<input type='hidden' id='userid' name='userid' value='$rows_m[userid]'readonly/>";

echo "$rows_m[firstname] $rows_m[othernames] $rows_m[surname] ($rows_m[userid])";
echo "</td>";

echo "<td>";
@$_BATCH="";
$_SQLBh=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_GET[edit_batch]'");
if($rowb=mysqli_fetch_array($_SQLBh,MYSQLI_ASSOC)){
$_BATCH=$rowb["batch"];
}
echo "<input type='hidden' id='batchid' name='batchid' value='$_GET[edit_batch]'/>";
echo "$_BATCH";
echo "</td>";

echo "<td>";
//echo "<input type='hidden' id='userid' name='userid' value='$rows_m[userid]'readonly/>";
echo "$_GET[edit_subject]";
echo "</td>";

echo "<td>";
echo "<input type='text' id='newmark' name='newmark' value='$rows_m[mark]' onchange='checkMark()' class='validate[required]'/>";
echo "</td>";

echo "<td>";
echo "<input type='text' id='totalmark' name='totalmark' value='$rows_m[totalmark]'readonly/>";
echo "</td>";

echo "</tr>";
}
echo "<tr>";
echo "<td>";
echo "<button class='button-edit' id='updatemark' name='updatemark'><i class='fa fa-edit'></i> UPDATE MARK</button>";
echo "</td>";
echo "</tr>";

echo "</tbody>";
echo "</table>";
}
?>
</form>
	<form id="formID2" name="formID2" method="post" action="examanalysis-rank.php">
		<h3>CLASS RANKING</h3>
<?php
include("gradingsystem.php");
	$_grade_obj=new GradingSystem();
echo $_SESSION['Message'];
if(isset($_POST["show_terminal_report"])){
echo "<button class='button-pay' id='print_examanalysis_report' name='print_examanalysis_report'><i class='fa fa-print'></i> Print Report</button><br/><br/>";		
	
@$_Batch_ID=$_POST["batchid"];
include("dbstring.php");
echo "<input type='hidden' name='batchid' value='$_Batch_ID' />";

$_SQL_ALLCLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
ON ce.class_entryid=tr.class_entryid WHERE tr.batchid = '$_Batch_ID' GROUP BY tr.class_entryid ORDER BY ce.class_name ASC");

while($row_acl=mysqli_fetch_array($_SQL_ALLCLASS,MYSQLI_ASSOC))
{
@$_ClassName=$row_acl["class_name"];

$k=0;
$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser");
while($row_us=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
ON ce.class_entryid=tr.class_entryid WHERE tr.class_entryid='$row_acl[class_entryid]' AND tr.userid='$row_us[userid]' AND tr.batchid='$_Batch_ID'");

while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{
$names[$k]=$row_us['firstname']." ".$row_us['othernames']." ".$row_us['surname']."(".$row_us['userid'].")";
@$_TotalMark=0;		
@$serial=0;

$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
INNER JOIN tblsystemuser su ON mk.userid=su.userid
INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
WHERE su.userid='$row_ce[userid]' AND ce.class_entryid='$row_ce[class_entryid]' AND
sa.batchid='$_Batch_ID'
ORDER BY su.userid ASC");

@$_TotalGrade=0;
while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
	
		$_TotalMark=$_TotalMark+$row['mark'];
	
}
//Store scores for sorting
$scores[$k]=$_TotalMark;
$k++;
}	
}
?>

<?php
//Sorting in Ascending order
		@$_Temps=0;
		@$_TempName="";
		$count=count($scores);
		for($j=0;$j<$count;$j++)
		{
			for($i=0;$i<$count;$i++)
			{
				if($scores[$j]>$scores[$i])
				{
				$_Temps=$scores[$j];
				$_TempName=$names[$j];

				$scores[$j]=$scores[$i];
				$names[$j]=$names[$i];

				$scores[$i]=$_Temps;
				$names[$i]=$_TempName;
				}
		  }
		}

		
echo "<table>";
echo "<caption>";
echo $_ClassName."<br/><br/>";
$_SQL_BA=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_Batch_ID'");
if($rowb=mysqli_fetch_array($_SQL_BA,MYSQLI_ASSOC)){
	echo  $rowb["batch"];
}
echo " Ranked Class";
echo"</caption>";
echo "<thead><th>*</th><th>STUDENT</th><th>TOTAL</th></thead>";
echo "<tbody>";
@$serial=0;
for($g=0;$g<$count;$g++)
{
echo "<tr>";
echo "<td>";
echo $serial=$serial+1;
echo "</td>";

echo "<td>";
echo $names[$g];
echo "</td>";
echo "<td align='center'>";
echo $scores[$g];
echo "</td>";
echo "</tr>";
}
echo "</tbody>";
echo "</table>";
?>

<?php
}
}
?>

</form>
</div>
</td>
</tr>
</table>

<br/><br/>
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
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