<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include("config.php");

@$_UserId=$_POST['userid'];
@$_BatchId=$_POST['batch_selected_id'];

if(isset($_POST['sendsms']))
{
		if(!$_UserId)
		{
		$_SESSION['Message']="<div style='color:red'>No user selected</div>";
		}
		else{
			include("gradingsystem.php");
			@$_grade_obj=new GradingSystem;

			foreach($_UserId as $selecteduser)
			{
			@$_Mobile="";
			@$Exam_Results="";
					
			//Get mobile number from users	
			$_SQL_H=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$selecteduser'");
			if($rowm=mysqli_fetch_array($_SQL_H,MYSQLI_ASSOC)){
			$_Mobile=$rowm["mobile"];
			}

			$_SQL_EXECUTE_SP="SELECT *,su.userid FROM tblmark mk 
			INNER JOIN tblsystemuser su ON mk.userid=su.userid
			INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
			INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
			INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
			INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
			INNER JOIN tblbatch bh ON sa.batchid=bh.batchid
			WHERE su.userid='$selecteduser' AND sa.batchid='$_BatchId' GROUP BY mk.assignmentid";

			foreach ($dbo->query($_SQL_EXECUTE_SP) as $row)
			{
			@$_ExamScore=0;
			@$_ClassScore=0;
			@$_Subject="";
			@$_final_grade="";
			$_Subject=$row['subject'];
			@$_Batch=$row["batch"];

			 $_SQL_TOT=mysqli_query($con,"SELECT * FROM tblmark mk 
			 WHERE mk.assignmentid='$row[assignmentid]' AND mk.testtype='Exam Score' AND mk.userid='$selecteduser'");
			 if($row_ex=mysqli_fetch_array($_SQL_TOT,MYSQLI_ASSOC)){
			$_ExamScore=$row_ex['mark'];
			 }

			$_SQL_TOT2=mysqli_query($con,"SELECT * FROM tblmark mk 
			 WHERE mk.assignmentid='$row[assignmentid]' AND mk.testtype='Class Score' AND mk.userid='$selecteduser'");
			 if($row_cl=mysqli_fetch_array($_SQL_TOT2,MYSQLI_ASSOC)){
			$_ClassScore=$row_cl['mark'];
			 }
			@$_TotalScore=$_ExamScore+$_ClassScore;		

			$_grade_obj->setMark($_TotalScore);
			$_final_grade=$_grade_obj->getMark($_TotalScore);
			//Get all subjects
			$Exam_Results=$Exam_Results. $_Subject."-".$_final_grade.",";
			}
			//Send exam results to all students
			$message=$_Batch.":".$Exam_Results;
			$Mesg_Len=strlen($message);
			$message=substr($message,0,$Mesg_Len-1).".";

			$phone=$_Mobile;

			//Storage for exams result
			include("code.php");
			$_SMSExamId=$code;
		$_SQLCheck=mysqli_query($con,"SELECT * FROM tblsmsexamresults WHERE userid='$selecteduser' AND batchid='$_BatchId'");
		if(mysqli_num_rows($_SQLCheck)>0){
			//UPDATE SMS REPORT OF A STUDENT
		$_SQLUPDATE=mysqli_query($con,"UPDATE tblsmsexamresults SET mobile='$phone',examresults='$message' WHERE userid='$selecteduser' AND batchid='$_BatchId'");
		if($_SQLUPDATE){/*Update successful */}
		}else{
			$_SQL_SMSRESULT=mysqli_query($con,"INSERT INTO tblsmsexamresults(smsexamid,userid,mobile,batchid,status,examresults,entrydatetime,recordedby)
			VALUES('$_SMSExamId','$selecteduser','$phone','$_BatchId','active','$message',NOW(),'$_SESSION[USERID]')");
			if($_SQL_SMSRESULT){

			}
			include("bulksms/bulksms.php");
			}
		}
		}
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
     document.getElementById("sendsms").style.display="block";
  }
  else if(selall==false){
    uncheckBox();
    document.getElementById("sendsms").style.display="none";
  }  
 }
 function hidebutton(){
 	  document.getElementById("sendsms").style.display="none";
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
<style type="text/css">
#showst{
	width:50%;
	float:left;
}
#showst2{
	margin-left:-5px;
	float:left;
}
</style>
</head>
<body onload="hidebutton()">
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style=""><br/>
<table width="100%">
		<tr>
			<td valign="top" width="40%" align="center">
				
		<div class="form-entry" align="left">
			<h3>SMS Reporting Of Students Examination Results</h3>
				<?php
				echo $_SESSION['Message'];
				?>
			<form method="post" action="smsreport.php">
				<div id="showst">
				<?php
				$_SQL_5=mysqli_query($con,"SELECT * FROM tblbatch");
				echo "<select id='batchid' name='batchid' class='validate[required]'>";
				echo "<option value=''>Select Batch</option>";
				while($rowl=mysqli_fetch_array($_SQL_5,MYSQLI_ASSOC)){
				echo "<option value='$rowl[batchid]'>$rowl[batch]</option>";
				}
				echo "</select>";
				?>
			</div>
				<div id="showst2">
				<button class="button-show" id="showstudent" name="showstudent"><i class="fa fa-search"></i> Show Student</button>
				</div>
			</form><br/><br/>
<form method="post" id="formID" name="formID" action="smsreport.php">

<?php
if(isset($_POST["showstudent"])){
	echo "<input type='hidden' id='batch_selected_id' name='batch_selected_id' value='$_POST[batchid]' />";

			$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblstudentterminalreport str ON su.userid=str.userid
			
			WHERE su.systemtype='Student' AND str.batchid='$_POST[batchid]' ORDER BY su.userid ASC");
			@$_Batch="";
			$_SQL_6=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_POST[batchid]'");
			if($rowb=mysqli_fetch_array($_SQL_6,MYSQLI_ASSOC)){
			$_Batch=$rowb['batch'];
			}
			echo "<table >";
			echo "<caption>BATCH: ".strtoupper($_Batch)."</caption>";
			echo "<thead><th><input type='checkbox' id='all' name='all' Onclick='selectAll()' /></th><th>*</th><th>MOBILE</th><th>EMAIL</th><th>STUDENTS</th></thead>";
			echo "<tbody>";
			@$serial=0;
			while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
			{
			echo "<tr >";
			echo "<td align='center'>";
			echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
			echo "</td>";
			echo "<td align='center'>";
			echo $serial=$serial+1;
			echo "</td>";

			echo "<td width='25%'>";
			echo $row["mobile"];
			echo "</td>";
				echo "<td width='25%'>";
			echo $row["email"];
			echo "</td>";
			
			
			echo "<td width='50%'>";
			echo $row['firstname']." ". $row['othernames']." ". $row['surname']."(".$row['userid'].")";
			echo "</td>";
			echo "</tr>";
			}	
			echo "</tbody>";
			echo "</table>";
			if(mysqli_num_rows($_SQL_2)>0){
			echo "<button class='button-pay' id='sendsms' name='sendsms'><i class='fa fa-send'></i> SEND SMS</button>";
		}
		}
	?>
</div>
</div>

</td>
</tr>
</table>
</div>
</body>
</html>