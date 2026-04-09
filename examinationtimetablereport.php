 <?php
session_start();
$_SESSION['Message']="";
include("check-login.php");

class Days{
var $_days;

	public function setDay($strdate)
	{
	$strdate = date("l",strtotime($strdate));

//echo $strdate;
		switch ($strdate) {
			case "Sunday":
				$this->_days="Sunday";

				break;
			case "Monday";
				$this->_days="Monday";
				break;
			case "Tuesday":
				$this->_days="Tuesday";
				break;
			case "Wednesday":
				$this->_days="Wednesday";
				break;
			case "Thursday":
				$this->_days="Thursday";
				break;
			case "Friday":
				$this->_days="Friday";
				break;
			case "Saturday":
				$this->_days="Saturday";
				break;
			default:
				return $this->_days="";
				break;
		}
		return $this->_days;
	}
	public function getDay(){
		return $this->_days;
	}
}
?>

<?php
  @$Overall_amount = 0;
  @$from_date = date("Y-m-d",strtotime($_POST['fromdate']));
  @$to_date = date("Y-m-d",strtotime($_POST['todate']));
      
if(isset($_POST["print_timetable"]))
{
@$_ClassID=$_POST["class"];
@$_Term=$_POST["termname"];
@$_Batch=$_POST["batch"];

include("dbstring.php");
include("config.php");
include("company.php");             
require('fpdf181/fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(40,70,40,45,20);
$pdf->SetFont('Arial','B',14);
//Background color of header//
//Heading of the pdf
// Logo
$k=8;
     // $pdf->Image('images/ike.png',5,3,13);
// $pdf->Ln($k);

$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($k);
$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Address: '.$_Address,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Location: '.$_Location,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Tel: '.$_Telephone1.'  '.$_Telephone2,0,0,'C',true);
$pdf->Ln($k);

@$_ClassName="";
$_SQL2=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_ClassID'");
if($row2=mysqli_fetch_array($_SQL2,MYSQLI_ASSOC)){
$_ClassName=$row2["class_name"];
}
@$_Batch_ID="";
$_SQL3=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_Batch'");
if($row3=mysqli_fetch_array($_SQL3,MYSQLI_ASSOC)){
$_Batch_ID=$row3["batch"];
}

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'EXAMINATION TIME TABLE',0,0,'C',true);
$pdf->Ln($k);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'CLASS :'.$_ClassName. '       Batch :'. $_Batch_ID. '     Semester: '.$_Term,0,0,'C',true);
$pdf->Ln($k);

$pdf->SetFillColor(255,255,255);

//$pdf->SetMargin(100);
$pdf->SetFont('Arial','',10);
//Header starts //
   
//First header column //
$pdf->Cell($width_cell[0],10,'DAYS',1,0,'C',true);
//Third header column //
$pdf->Cell($width_cell[1],10,'SUBJECT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'TIME',1,0,'C',true);
//Fourth header column
$pdf->Cell($width_cell[3],10,'DATE',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);
@$serial=0;


//$sql1 = "SELECT * FROM tbltimetable WHERE class_entryid='$_ClassID' AND termname='$_Term' AND batchid='$_Batch'";

$_SQL=mysqli_query($con,"SELECT * FROM tbltimetable tt INNER JOIN tblclassentry ce ON tt.class_entryid=ce.class_entryid
INNER JOIN tblbatch bch  ON tt.batchid=bch.batchid INNER JOIN tblsubject sub ON tt.subjectid=sub.subjectid
WHERE tt.class_entryid='$_ClassID' AND tt.termname='$_Term' AND tt.batchid='$_Batch' ORDER BY tt.tabledate ASC");

$count = mysqli_num_rows( $_SQL);
@$serial =0;
@$_Total=0;
if($count>0)
{  
   while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC))
    {     
    $days = new Days;
    $days->setDay($row['tabledate']);
    $_GetDays=$days->getDay();

    $pdf->Cell($width_cell[0],10,$_GetDays,1,0,'C',$fill);
    $pdf->Cell($width_cell[1],10,$row['subject'],1,0,'L',$fill);
    $pdf->Cell($width_cell[2],10,$row['tablestarttime']."-".$row['tableendtime'],1,0,'C',$fill);
    $pdf->Cell($width_cell[3],10,$row['tabledate'],1,0,'C',$fill);
    
    $fill = !$fill;
    $pdf->Ln(10);                 
   }  
$pdf->Ln(10); 
}

$pdf->Ln(10);
/// end of records ///
$pdf->Output();
mysqli_close($con);
}
?>

<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_SubjectId=$_POST['subjectid'];
@$_ClassId=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
@$_StartTime=$_POST['starttime'];
@$_EndTime=$_POST['endtime'];
@$_Tabledate=$_POST['timetabledate'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['save_timetable']))
{
//Create payment container
include("code.php");
@$_TimeId=$code;
@$_Transaction_Code=$transaction_id;
@$_TransId=0;

$_SQL_Time=mysqli_query($con,"INSERT INTO tbltimetable(timeid,subjectid,tablestarttime,tableendtime,tabledate,class_entryid,termname,batchid,recordedby,status)
	VALUES('$_TimeId','$_SubjectId','$_StartTime','$_EndTime',STR_TO_DATE('$_Tabledate','%d-%m-%Y'),'$_ClassId','$_Term','$_Batch','$_SESSION[USERID]','active')");
if($_SQL_Time){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white;padding:5px;'><i class='fa fa-check' style='color:green'></i> Time Table Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>No Time Table saved,$_Error</div>";
}
}
?>

<?php
include("dbstring.php");
if(isset($_GET["delete_timetable"]))
{
$_SQLDelete=mysqli_query($con,"DELETE FROM tbltimetable WHERE timeid='$_GET[delete_timetable]'");
if($_SQLDelete){

	}
}
?>

<html>
<head>
<?php
include("links.php");
?>

<script>
  var rnd;
function getItemID()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("item-id").value=rnd;
}
</script>

<script type="text/javascript">
var gbatch;
function getBatch()
{
gbatch=getElementById("batch").value;
 //return _batch;  
}
function getStudentBill(str)
{
	if(str=="")
  {
  
  document.getElementById("search-result").innerHTML="";
  return;
  }
  else
  {
    if(window.XMLHttpRequest)
    {
      xmlhttp = new XMLHttpRequest();
    }
    else
    {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function()
    {
      if(this.readyState==4 && this.status==200)
      {
        document.getElementById("search-result").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","display-class-bill.php?search-bill="+str+"&batch="+gbatch,true);
    xmlhttp.send();
  }
}
</script>
</head>

<body>
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	</div>

<div class="main-platform" style="">
	<?php
	echo $_SESSION["Message"];
	?>

	<br/><br/>
	<table width="100%" style="background-color:white">
		<tr>
			<td valign="top" width="30%" align="center">
				
				<div class="form-entry" align="left">
			
			<h3>EXAMINATION TIME TABLE REPORT
				</h3>
			<br/>
			<form method="post" id="formID" name="formID" action="examinationtimetablereport.php">
			
<?php
include("dbstring.php");
if( $_SESSION['SYSTEMTYPE']=="normal_user" || $_SESSION['SYSTEMTYPE']=="super_user" || $_SESSION['SYSTEMTYPE']=="User" || $_SESSION['SYSTEMTYPE']=="Teacher")
{
	$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

			echo "<select id='class' name='class' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select><br/><br/>";
}
elseif( $_SESSION['SYSTEMTYPE']=="Student")
{

$_SQL_C=mysqli_query($con,"SELECT * FROM tblclass cl WHERE cl.userid='$_SESSION[USERID]'");

		echo "<select id='class' name='class' class='validate[required]'>";
		while($rows=mysqli_fetch_array($_SQL_C,MYSQLI_ASSOC))
		{	
		$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$rows[class_entryid]'");

			echo "<option value=''>Select Class</option>";
			while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
			echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
			}
		}
		echo "</select><br/><br/>";
}
?>

			<select id="termname" name="termname" class="validate[required]">
				<option value="" >Select Semester</option>
				<option value="1">1</option>
				<option value="2">2</option>
			</select><br/><br/>

			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batch' name='batch' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			?>
<div align="center"><button class="button-pay" id="print_timetable" name="print_timetable"><i class="fa fa-print"></i> PRINT TIME TABLE</button></div>
</form>
</div>
</td>
<td width="70%">
	<div class="form-entry">
	<?php
	include("dbstring.php");
	$_SQL=mysqli_query($con,"SELECT * FROM tbltimetable tt INNER JOIN tblclassentry ce ON tt.class_entryid=ce.class_entryid
	INNER JOIN tblbatch bch  ON tt.batchid=bch.batchid INNER JOIN tblsubject sub ON tt.subjectid=sub.subjectid");
	echo "<table style='background-color:white'>";
	echo "<caption>EXAMINATION TIME TABLE</caption>";
	echo "<thead><th>*</th><th>DAY</th><th>START TIME</th><th>END START</th><th>DATE</th><th>SUBJECT</th><th>CLASS</th><th>SEMESTER</th><th>BATCH</th></thead>";
	echo "<tbody>";
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
	echo "<tr>";
	//echo "<td align='center'><a title='View $row[subject]' href='examinationtimetablereport.php?view_user=$row[timeid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
	//echo "<td align='center'><a title='Delete $row[subject]' onclick=\"javascript:return confirm('Do you want to delete?');\" href='examinationtimetablereport.php?delete_timetable=$row[timeid]'<i class='fa fa-times' style='color:red'></i></a></td>";
	//echo "<td align='center'><a title='Edit $row[subject]' href='examinationtimetablereport.php?edit_user=$row[timeid]'<i class='fa fa-edit' style='color:green'></i></a></td>";
				
	echo "<td align='center'>";
	echo $serial=$serial+1;
	echo "</td>";

	 $days = new Days;
    $days->setDay($row['tabledate']);
    echo "<td align='center'>";
    echo $_GetDays=$days->getDay();
    echo "</td>";
	
	echo "<td align='center'>";
	echo $row['tablestarttime'];
	echo "</td>";
	
	echo "<td align='center'>";
	echo $row['tableendtime'];
	echo "</td>";
	
	echo "<td align='center'>";
	echo $row['tabledate'];
	echo "</td>";
	

	echo "<td>";
	echo $row['subject'];
	echo "</td>";
	echo "<td align='center'>";
	echo $row['class_name'];
	echo "</td>";
	
	echo "<td align='center'>";
	echo $row['termname'];
	echo "</td>";

	echo "<td align='center'>";
	echo $row['batch'];
	echo "</td>";
	echo "</tr>";
	}
	?>
</div>
</td>
</tr>
</table>
</div>
</body>
</html>