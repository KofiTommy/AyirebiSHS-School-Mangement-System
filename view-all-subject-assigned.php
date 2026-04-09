<?php
session_start();
$_SESSION['Message']="";
?>

<?php
//Declare the variables
if(isset($_POST["print_all_reports"])){
   include("dbstring.php");
   include("config.php");
   include("company.php");
require('fpdf181/fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(15,20,20,50,30,20);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',100,3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,'TEL:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
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
$pdf->Cell($width_cell[1],10,'SEMESTER',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'SUBJECT ID',1,0,'C',true);
$pdf->Cell($width_cell[3],10,'SUBJECT',1,0,'C',true);
$pdf->Cell($width_cell[4],10,'BATCH',1,0,'C',true);
				
///header ends///

//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);
	
include("dbstring.php");

$_SQL_EXECUTE_USERS=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Teacher'");
//Registered clients

while($row_us=mysqli_fetch_array($_SQL_EXECUTE_USERS,MYSQLI_ASSOC))
{
$_getUser_ID =$row_us['firstname']." ". $row_us['othernames']." ". $row_us['surname']."(".$row_us['userid'].")";
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_getUser_ID,1,0,'L',$fill); 
$pdf->Ln(10);

			$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblclassentry ce
			INNER JOIN tblsubjectassignment sa ON ce.class_entryid=sa.classid WHERE sa.userid='$row_us[userid]' GROUP BY sa.classificationid");
			while($row_v=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
				{
				$_SQL_EXECUTE=mysqli_query($con,"SELECT *,sa.datetimeentry FROM tblsubjectclassification sc
				INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
				INNER JOIN tblsubjectassignment sa ON sa.classificationid=sc.classificationid
				INNER JOIN tblbatch b ON b.batchid=sa.batchid
				 WHERE sa.userid='$row_us[userid]' AND sa.classid='$row_v[class_entryid]' AND sa.classificationid='$row_v[classificationid]'  ORDER BY sa.termname");
				
				if(mysqli_num_rows($_SQL_EXECUTE)==0)	
				{
				}
				else{
				$pdf->SetFont('Arial','B',9);
				$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$row_v['class_name'],1,0,'L',$fill); 
				$pdf->Ln(10);
				@$serial=0;
				$pdf->SetFont('Arial','',9);
							
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill); 
				$pdf->Cell($width_cell[1],10,$row["termname"],1,0,'L',$fill); 
				$pdf->Cell($width_cell[2],10,$row["subjectid"],1,0,'L',$fill); 
				$pdf->Cell($width_cell[3],10,$row["subject"],1,0,'L',$fill); 
				$pdf->Cell($width_cell[4],10,$row["batch"],1,0,'L',$fill); 
				
				$pdf->Ln(10);
				}
				}
				}
	}

/// end of records ///
$pdf->Output();
 //ob_end_flush(); 
}
?>

<?php
@$_UserId=$_POST["userid"];
//Declare the variables
if(isset($_POST["print_report"])){
   include("dbstring.php");
   include("config.php");
   include("company.php");
require('fpdf181/fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(15,20,40,80,30,20);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image('images/logo.png',100,3,22);
     $pdf->Ln(20);

$p=7;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,strtoupper($_CompanyName)." - GES",0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',10);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,'TEL:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
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
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5],10,"LIST OF SUBJECTS ASSIGNED",0,0,'C',true);
$pdf->Ln($p);

//First header column //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);
$pdf->Cell($width_cell[1],10,'SEMESTER',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'SUBJECT ID',1,0,'C',true);
$pdf->Cell($width_cell[3],10,'SUBJECT',1,0,'C',true);
$pdf->Cell($width_cell[4],10,'BATCH',1,0,'C',true);
				
///header ends///

//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);
	
include("dbstring.php");

$_SQL_EXECUTE_USERS=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$_UserId' AND su.systemtype='Teacher'");
//Registered clients

while($row_us=mysqli_fetch_array($_SQL_EXECUTE_USERS,MYSQLI_ASSOC))
{
$_getUser_ID =$row_us['firstname']." ". $row_us['othernames']." ". $row_us['surname']."(".$row_us['userid'].")";
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_getUser_ID,1,0,'L',$fill); 
$pdf->Ln(10);

			$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblclassentry ce
			INNER JOIN tblsubjectassignment sa ON ce.class_entryid=sa.classid WHERE sa.userid='$row_us[userid]' GROUP BY sa.classificationid");
			while($row_v=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
				{
				$_SQL_EXECUTE=mysqli_query($con,"SELECT *,sa.datetimeentry FROM tblsubjectclassification sc
				INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
				INNER JOIN tblsubjectassignment sa ON sa.classificationid=sc.classificationid
				INNER JOIN tblbatch b ON b.batchid=sa.batchid
				 WHERE sa.userid='$row_us[userid]' AND sa.classid='$row_v[class_entryid]' AND sa.classificationid='$row_v[classificationid]'  ORDER BY sa.termname");
				
				if(mysqli_num_rows($_SQL_EXECUTE)==0)	
				{
				}
				else{
				$pdf->SetFont('Arial','B',9);
				$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$row_v['class_name'],1,0,'L',$fill); 
				$pdf->Ln(10);
				@$serial=0;
				$pdf->SetFont('Arial','',9);
							
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				$pdf->Cell($width_cell[0],10,$serial=$serial+1 .".",1,0,'L',$fill); 
				$pdf->Cell($width_cell[1],10,$row["termname"],1,0,'C',$fill); 
				$pdf->Cell($width_cell[2],10,$row["subjectid"],1,0,'L',$fill); 
				$pdf->Cell($width_cell[3],10,$row["subject"],1,0,'L',$fill); 
				$pdf->Cell($width_cell[4],10,$row["batch"],1,0,'L',$fill); 
				
				$pdf->Ln(10);
				}
				}
				}
	}

/// end of records ///
$pdf->Output();
 //ob_end_flush(); 
}
?>

<?php
include("dbstring.php");
@$_ClassificationId=$_POST['classificationid'];
@$_UserId=$_POST['userid'];
//@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_subject_assignment']))
{
	foreach ($_ClassificationId as $_Selected_ClassId) 
	{
		include("code.php");
	@$_AssignmentId=$code;
	@$_UserFullname="";
	//@$_Subject="";
	//Check if subject already registered
	$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject sub INNER JOIN tblsubjectclassification sc ON sub.subjectid=sc.subjectid WHERE sc.classificationid='$_Selected_ClassId'");
	if($row_s=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC)){
	$_Subject=$row_s['subject'];
	$_ClassId=$row_s['classid'];
	//@$_getUser_ID=$row_s['userid'];
	}

	$_SQL_EXECUTE_USER=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa INNER JOIN tblsystemuser su ON sa.userid=su.userid WHERE sa.classificationid='$_Selected_ClassId'");
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

	//$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId' AND sa.userid='$_UserId' AND sa.classid='$_ClassId'");
	$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId'");
	
	if(mysqli_num_rows($_SQL_EXECUTE_2)>0){
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white'><i class='fa fa-check' style='color:red'></i> $_Subject Already Assigned To $_UserFullname</div>";
		
	}else{

	$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsubjectassignment(assignmentid,userid,classid,classificationid,datetimeentry,status,recordedby)
	VALUES('$_AssignmentId','$_UserId','$_ClassId','$_Selected_ClassId',NOW(),'active','$_Recordedby')");
		if($_SQL_EXECUTE)
		{
	
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Subject Successfully Assigned To $_UserFullname</div>";
		}
		else{
			$_Error=mysqli_error($con);
			$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_Subject failed to classify,$_Error</div>";
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

if(isset($_GET["delete_subject"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsubjectassignment WHERE assignmentid='$_GET[delete_subject]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Subject Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Subject failed to delete,Error:$_Error</div>";
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
	<?php
	//include("side-menu.php");
	?>
	</div>

<div class="main-platform" style="">
	<table width="100%">
		<tr>
<td width="100%">
	<div class="form-entry" style="">
		<table>
					<caption>Report Subjects Assigned</caption>
					<tr>
						<td>
						<form id="formID" name="formID" method="post">
							<?php	
							$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE SystemType='Teacher'");


							echo "<select id='userid' name='userid' class='validate[required]'>";
							echo "<option value=''>Select Teacher</option>";
								while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
								echo "<option value='$row[userid]'>".$row['firstname']." ". $row['othernames']." ". $row['surname']."(".$row['userid'].")"."</option>";
								}
							echo "</select><br/><br/>";
							?>
						</td>
						<td width="15%">
						<button class="button-print" name="print_report"><i class="fa fa-print"></i> Print Report</button>
						</td>
					</form>
						<td width="15%">
						<form method="post">
						<button class="button-print" name="print_all_reports"><i class="fa fa-print"></i> Print All Reports</button>							
						</form>
						</td>
					</tr>
				</table>
			
<?php
echo $_SESSION['Message'];

include("dbstring.php");
				//Loop through system users;
$_SQL_EXECUTE_USERS=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Teacher'");
//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>List Of Subjects Assigned</caption>";
				echo "<thead><th colspan=2>Task</th><th>Semester</th><th>Subject Id</th><th>Subject</th><th>Batch</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
while($row_us=mysqli_fetch_array($_SQL_EXECUTE_USERS,MYSQLI_ASSOC))
{
$_getUser_ID =$row_us['firstname']." ". $row_us['othernames']." ". $row_us['surname']."(".$row_us['userid'].")";

echo "<tr style='background-color:#eef;font-weight:bold;border-bottom:1px solid orange'>";
echo "<td colspan='9'>";
echo $_getUser_ID;
echo "</td>";
echo "</tr>";


				$_SQL_EXECUTE_VIEW=mysqli_query($con,"SELECT * FROM tblclassentry ce
					INNER JOIN tblsubjectassignment sa ON ce.class_entryid=sa.classid WHERE sa.userid='$row_us[userid]' GROUP BY sa.classificationid");
				while($row_v=mysqli_fetch_array($_SQL_EXECUTE_VIEW,MYSQLI_ASSOC))
				{
				$_SQL_EXECUTE=mysqli_query($con,"SELECT *,sa.datetimeentry FROM tblsubjectclassification sc
				INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
				INNER JOIN tblsubjectassignment sa ON sa.classificationid=sc.classificationid
				INNER JOIN tblbatch b ON b.batchid=sa.batchid
				 WHERE sa.userid='$row_us[userid]' AND sa.classid='$row_v[class_entryid]' AND sa.classificationid='$row_v[classificationid]'  ORDER BY sa.termname");
				
				if(mysqli_num_rows($_SQL_EXECUTE)==0)	
				{
				echo "<div style='color:red;padding:10px;background-color:white'>There is no subject assigned</div>";
				}
				else{
				echo "<tr style='background-color:#eee;font-weight:bold;border-bottom:1px solid gray'>";
				echo "<td colspan='9'>";
				echo $row_v['class_name'];
				echo "</td>";
				echo "</tr>";				
			
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a title='Delete $row[subject]' onclick=\"javascript:return confirm('Do you want to delete $row[subject]?');\" href='view-all-subject-assigned.php?delete_subject=$row[assignmentid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				
				echo "<td align='center'>";
				echo "<input type='checkbox' id='classificationid' name='classificationid[]' value='$row[classificationid]'>";
				echo "</td>";
				
				echo "<td align='center'>$row[termname]</td>";
				echo "<td align='center'>";
				echo $row['subjectid'];
				echo "</td>";
				echo "<td align='left'>$row[subject]</td>";
				echo "<td align='center'>$row[batch]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
				echo "</tr>";
				}
			}
			
			}
	}
echo "</tbody>";
echo "</table>";
?>
</div>
</td>
</tr>
</table>
</form>
</div>
</body>
</html>
