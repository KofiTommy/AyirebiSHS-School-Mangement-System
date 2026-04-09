<?php
session_start();
$_SESSION['Message']="";
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

if(isset($_GET["assign_subject"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsubjectclassification WHERE classificationid='$_GET[assign_subject]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Subject Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Subject failed to delete,Error:$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");

if(isset($_POST["download_score_template"]))
{
@$_BatchId=$_SESSION["batchid"];
@$_ClassId=$_SESSION["classid"];
@$_Term=$_SESSION["termid"];
@$_SubjectId=$_SESSION["subjectid"];
@$_ClassName="";
@$_BatchName="";


$result=mysqli_query($con,"SELECT su.userid,ce.class_name,tr.termname as semester,bh.batchid as batch,sub.subjectid,sub.subject FROM tblsystemuser su 
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
INNER JOIN tblbatch bh ON bh.batchid=tr.batchid
 WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
 AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]'");

while($row=mysqli_fetch_array($result,MYSQLI_ASSOC)){
$items[] = $row;
}
//Check the export button is pressed or not
//if(isset($_POST["export"])) {
//Define the filename with current date
$fileName = "itemdata-".date('d-m-Y').".xls";

//Set header information to export data in excel format
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename='.$fileName);

//Set variable to false for heading
$heading = false;

//Add the MySQL table data to excel file
if(!empty($items)) {
foreach($items as $item) {
if(!$heading) {
echo implode("\t", array_keys($item)) . "\n";
$heading = true;
}
echo implode("\t", array_values($item)) . "\n";
}
}
exit();

//}
}
?>

<html>
<head>
<?php
include("links.php");
?>

<script type="text/javascript">
function selectAll(){
  var selall = document.getElementById("all").checked;
  if(selall==true){
    checkBox();
  }
  else if(selall==false){
    uncheckBox();
  }  
 }
 

 function uncheckBox(){
   var inputs = document.getElementsByName("userid[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=false;
    }
     return false;
 }

  function checkBox(){
var inputs = document.getElementsByName("userid[]");
   
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=true;
    }
 return false;
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
<table width="100%">
<tr>
<td width="30%">
	<div class="form-entry">
<form id="formID" name="formID" method="post" action="download-examscore-template.php">
	<h4>DOWNLOAD EXAM SCORE TEMPLATE</h4>
<?php	
include("dbstring.php");
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa 
	INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid 
	INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
	INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
	WHERE sa.userid='$_SESSION[USERID]' ORDER BY sa.termname ASC");

/*echo "<select id='classid' name='classid' class='validate[required]'>";
	echo "<option value=''>Select Subject</option>";
	while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
	echo "<option value='$row[class_entryid]'>$row[class_name]:Term $row[termname]:$row[subject]</option>";
	}
echo "</select><br/><br/>";

	$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			*/
echo "<table>";
echo "<thead><th>CLASS</th><th>SEM.</th><th>SUBJECT</th><th>TASK</th></thead>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
	echo "<tr><td>";
	echo $row['class_name'];
	echo "</td>";
	echo "<td align='center'>";
	echo $row['termname'];
	echo "</td>";
	echo "<td>";
	echo $row['subject']."(".$row['subjectid'].")";
	echo "</td>";
	echo "<td align='center'>";
	echo "<a href='download-examscore-template.php?class_ID=$row[class_entryid]&term_ID=$row[termname]&batch_ID=$row[batchid]&subject_ID=$row[subjectid]'><i class='fa fa-plus' style='color:blue'></i></a>";
	echo "</td>";
	echo "</tr>";
	}
echo "</table>";
?>

<!--
		<select id="term" name="term" class="validate[required]">
				<option value="" >Select Term</option>
				<option value="1">1</option>
				<option value="2">2</option>
				<option value="3">3</option>
			</select><br/><br/>
		-->

<!--<label>* Total Score</label>
<input type="number" id="totalscore" name="totalscore" value="" placeholder="Total Score" class="validate[required,custom[number]]"/><br/><br/>

<button class="button-pay" id="get_student" name="get_student"><i class="fa fa-plus" style="color:white"></i> Get Student</button>
-->
</form>
</div>
</td>
<td width="70%">
<div class="form-entry">
<form id="formID2" name="formID2" method="post" action="download-examscore-template.php">
<?php
echo $_SESSION['Message'];
include("dbstring.php");
@$serial=0;

if(isset($_GET['class_ID']))
{
@$_BatchId=$_GET['batch_ID'];
@$_ClassId=$_GET['class_ID'];
@$_Term=$_GET['term_ID'];
@$_SubjectId=$_GET['subject_ID'];
@$_ClassName="";
@$_BatchName="";

$_SESSION["batchid"]=$_BatchId;
$_SESSION["classid"]=$_ClassId;
$_SESSION["termid"]=$_Term;
$_SESSION["subjectid"]=$_SubjectId;


$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT *,su.userid FROM tblsystemuser su 
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
 WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
 AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]'");


$_SQL_EXE=mysqli_query($con,"SELECT *,su.userid FROM tblsystemuser su 
INNER JOIN tbltermregistry tr ON su.userid=tr.userid
INNER JOIN tblsubjectassignment sa ON sa.classid=tr.class_entryid AND sa.batchid=tr.batchid AND sa.termname=tr.termname
INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
 WHERE tr.class_entryid='$_ClassId' AND tr.batchid='$_BatchId' AND tr.termname='$_Term' 
 AND su.systemtype='Student' AND sc.subjectid='$_SubjectId' AND sa.userid='$_SESSION[USERID]'");

/*
$_SubjectName="";
$_SQL_SUB=mysqli_query($con,"SELECT * FROM tblsubject WHERE subjectid='$_POST[subjectid]'");
if($row_sub=mysqli_fetch_array($_SQL_SUB,MYSQLI_ASSOC)){
$_SubjectName=$row_sub['subject'];
}
*/
//echo "<div id='subject' name='subject' style='padding:10px;color:blue'>SUBJECT: $_SubjectName </div>";
$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_GET[class_ID]'");
if($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
$_ClassName=$row_cl['class_name'];
}

$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_GET[batch_ID]'");
if($row_bch=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
$_BatchName=$row_bch['batch'];
}
echo "<div align='right'><button id='download_score_template' name='download_score_template' class='button-save'><i class='fa fa-download' style='color:white'></i> DOWNLOAD ". strtoupper($_ClassName)." SCORE TEMPLATE</button></div><br/>";
//echo "<label>*Total Score</label><br/><input type='text' id='totalscore' name='totalscore' placeholder='Enter Total Score' class='validate[required,custom[number]]'/><br/><br/>";
echo "<table width='100%' style='background-color:white'>";
echo "<caption>";
if($row_ss=mysqli_fetch_array($_SQL_EXE,MYSQLI_ASSOC)){
echo strtoupper($_ClassName)." : SEMESTER ".$_Term ." : ". strtoupper($row_ss['subject'])." ---BATCH: ".strtoupper($_BatchName);
}
echo "</caption>";
echo "<thead><th>*</th><th>STUDENT</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
{
$_SQL=mysqli_query($con,"SELECT * FROM tblmark mk WHERE mk.userid='$row[userid]' AND mk.testtype='Class Score'  AND mk.assignmentid='$row[assignmentid]'");
	if(mysqli_num_rows($_SQL)==0){
	echo "<tr>";
	//echo "<td align='center' width='10%'>";
	//echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
	echo "<input type='hidden' id='assignmentid' name='assignmentid' value='$row[assignmentid]' />";
	//echo "</td>";
	echo "<td align='center'>";
	echo $serial=$serial+1;
	echo "</td>";
	echo "<td align='left'>$row[firstname] $row[othernames] $row[surname]($row[userid])</td>";
	echo "</tr>";
	}
}	
echo "</tbody>";
echo "</table>";
}
?>
</form>
</div>
</td>
</tr>
</table>
</div>
</body>
</html>
