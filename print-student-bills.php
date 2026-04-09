<?php
session_start();
$_SESSION['Message']="";
?>

<?php
//Declare the variables
@$_TransactionId_Bi="";
//@$_Receivedby=$_SESSION['USERID'];
@$_UserId=$_POST['user_id'];
if(isset($_POST["print_category"]))
{
include("dbstring.php");
include("config.php");
include("company.php");
      //Get all the ordered items
require('fpdf181/fpdf.php');


$pdf = new FPDF();
$pdf->AddPage();

foreach($_UserId AS $selecteduserid){
$_UserId=$selecteduserid;

//$_SQL_SU="SELECT * FROM tblsystemuser WHERE userid='$_UserId'";

@$_ClassentryID=$_POST["class"];
@$_Batch_Id=$_POST["batch"];

$_SQL_SU="SELECT * FROM tblsystemuser su INNER JOIN tblclass cl
			 ON su.userid=cl.userid
			 WHERE su.userid='$_UserId' AND cl.class_entryid='$_ClassentryID' AND cl.batchid='$_Batch_Id' 
			 AND su.systemtype='Student'";


if(mysqli_num_rows(mysqli_query($con,$_SQL_SU))>0)
{
$width_cell=array(10,120,60,30,30);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
$pdf->Image("logo/".$_Logo,$width_cell[0]+$width_cell[2]+$width_cell[3],3,22);
$pdf->Ln(15);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($p);

$pdf->SetFont('Arial','B',12);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_Address." ".$_Location,0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'TEL:'. $_Telephone1. " / ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',14);

$text_height = 5;
$text_length = 70;
$n=9;

$pdf->Cell($text_length,$text_height,'BILLS',0,0,'L',true);
$pdf->SetFont('Arial','B',12);

$pdf->Ln($n);
$_Result=mysqli_query($con,$_SQL_SU);

    if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC)){
     @$_StaffName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
     $pdf->Cell($text_length,$text_height,'NAME:'.$_StaffName,0,0,'L',true);
      $pdf->Ln(10);
      }
  
@$_Class_ID=$_POST['class'];
@$_Term_ID=$_POST['term'];

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_ID' ORDER BY ce.class_name ASC");
while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{
@$_Total_Amount=0;
@$_Total_Balance=0;
@$_Total_Amount_Paid=0;

$pdf->SetFont('Arial','B',12);
$pdf->Cell($text_length,$text_height,strtoupper($row_class['class_name']),0,0,'L',true);
$pdf->Ln($n);

$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$_Class_ID' AND tr.termname='$_Term_ID' 
	AND tr.userid='$_UserId' ORDER BY tr.termname ASC");


$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',12);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[1],10,'ITEM',1,0,'C',true);

$pdf->Cell($width_cell[2],10,'AMOUNT',1,0,'C',true);
$pdf->Ln($n);

while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
{
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,"Semester: ".$row_tr['termname'],1,0,'L',true);

$_SQL_BIL=mysqli_query($con,"SELECT SUM(ip.price) AS Total_Amount,transactionid FROM tblbilling bi 
  INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid WHERE bi.userid='$_UserId' 
  AND ip.class_entryid='$row_tr[class_entryid]' AND ip.term='$row_tr[termname]'");
if($row_b=mysqli_fetch_array($_SQL_BIL,MYSQLI_ASSOC)){
$_TransactionId_Bi=$row_b['transactionid'];
}

$pdf->SetFont('Arial','B',12);
$pdf->Ln(10);

$_SQL_EXECUTE_3="SELECT * FROM tblbilling bi INNER JOIN tblsystemuser su 
ON bi.userid=su.userid INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON ip.itemid=itm.itemid
INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
INNER JOIN tblbatch b ON ip.batch=b.batchid
WHERE bi.userid='$_UserId' AND ip.class_entryid='$row_class[class_entryid]' AND ip.term='$row_tr[termname]' ORDER BY ip.term ASC";

@$serial=0;
@$_Total_Amount_single=0;
@$_Total_Amount_Paid_Single=0;
@$_Total_Balance_Single=0;

///header ends///
$pdf->SetFont('Arial','',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;

@$serial=0;
//each record is one row //
foreach ($dbo->query($_SQL_EXECUTE_3) as $row_p) 
{
$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
$pdf->Cell($width_cell[1],10,$row_p['itemname'],1,0,'L',$fill);

$_Total_Amount=$_Total_Amount+$row_p['cost'];
$_Total_Amount_single=$_Total_Amount_single+$row_p['cost'];
$pdf->Cell($width_cell[2],10,$row_p['cost'],1,0,'C',$fill);

$fill = !$fill;
$pdf->Ln(10);
}

//Footer of the table
$pdf->Cell($width_cell[0]+$width_cell[1],10,'Sub Total:',1,0,'R',true);
$pdf->Cell($width_cell[2],10,$_Total_Amount_single,1,0,'C',true);
$_Total_Amount=$_Total_Amount+$_Total_Balance_Single;
$pdf->Ln(10); 
}
$pdf->SetFont('Arial','B',10);

$pdf->Cell($width_cell[0]+$width_cell[1],10,'GRAND TOTAL:',1,0,'R',true);
$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount,1,0,'C',true);
$pdf->Ln(15); 
}

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);

$pdf->SetFont('Arial','',8);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

$pdf->Ln(10); 
$_SQL_AC=mysqli_query($con,"SELECT * FROM tbltransaction tr 
INNER JOIN tblsystemuser su ON tr.recordedby=su.userid 
WHERE tr.transactionid='$_TransactionId_Bi'");
if($row_bi=mysqli_fetch_array($_SQL_AC,MYSQLI_ASSOC)){
$pdf->Cell(0,10,'ADMINISTRATOR:'. strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 
$pdf->Ln(10); 
$pdf->Cell(0,10,'SIGNATURE:.......................................................',0);

$pdf->Ln(85); 
$pdf->Cell(0,10,' ',0);

 }
}
}
$pdf->Output();
}
?>


<?php
//Declare the variables
@$_TransactionId_Bi="";
//@$_Receivedby=$_SESSION['USERID'];
@$_UserId=$_GET['user_id'];
if(isset($_GET["user_id"]))
{
include("dbstring.php");
include("config.php");
include("company.php");
      //Get all the ordered items
require('fpdf181/fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();

$_SQL_SU="SELECT * FROM tblsystemuser WHERE userid='$_UserId'";

$width_cell=array(10,120,60,30,30);
$pdf->SetFont('Arial','B',18);
//Background color of header//
//Heading of the pdf
// Logo
$pdf->Image("logo/".$_Logo,$width_cell[0]+$width_cell[2]+$width_cell[3],3,22);
$pdf->Ln(15);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($p);

$pdf->SetFont('Arial','B',12);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,$_Address." ".$_Location,0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,'TEL:'. $_Telephone1. " / ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',14);

$text_height = 5;
$text_length = 70;
$n=9;

$pdf->Cell($text_length,$text_height,'BILLS',0,0,'L',true);
$pdf->SetFont('Arial','B',12);

$pdf->Ln($n);
$_Result=mysqli_query($con,$_SQL_SU);

    if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC)){
    @$_StaffName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
     $pdf->Cell($text_length,$text_height,'NAME:'.$_StaffName,0,0,'L',true);
      $pdf->Ln(10);
      }
  
@$_Class_ID=$_GET['class_entryid'];
@$_Term_ID=$_GET['term_id'];

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_ID' ORDER BY ce.class_name ASC");
while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{
@$_Total_Amount=0;
@$_Total_Balance=0;
@$_Total_Amount_Paid=0;

$pdf->SetFont('Arial','B',12);
$pdf->Cell($text_length,$text_height,strtoupper($row_class['class_name']),0,0,'L',true);
$pdf->Ln($n);

$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$_Class_ID' AND tr.termname='$_Term_ID' 
	AND tr.userid='$_UserId' ORDER BY tr.termname ASC");


$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',12);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[1],10,'ITEM',1,0,'C',true);

$pdf->Cell($width_cell[2],10,'AMOUNT',1,0,'C',true);
$pdf->Ln($n);

while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
{
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2],10,"Term: ".$row_tr['termname'],1,0,'L',true);

$_SQL_BIL=mysqli_query($con,"SELECT SUM(ip.price) AS Total_Amount,transactionid FROM tblbilling bi 
  INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid WHERE bi.userid='$_UserId' 
  AND ip.class_entryid='$row_tr[class_entryid]' AND ip.term='$row_tr[termname]'");
if($row_b=mysqli_fetch_array($_SQL_BIL,MYSQLI_ASSOC)){
$_TransactionId_Bi=$row_b['transactionid'];
}

$pdf->SetFont('Arial','B',12);
$pdf->Ln(10);

$_SQL_EXECUTE_3="SELECT * FROM tblbilling bi INNER JOIN tblsystemuser su 
ON bi.userid=su.userid INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON ip.itemid=itm.itemid
INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
INNER JOIN tblbatch b ON ip.batch=b.batchid
WHERE bi.userid='$_UserId' AND ip.class_entryid='$row_class[class_entryid]' AND ip.term='$row_tr[termname]' ORDER BY ip.term ASC";

@$serial=0;
@$_Total_Amount_single=0;
@$_Total_Amount_Paid_Single=0;
@$_Total_Balance_Single=0;

///header ends///
$pdf->SetFont('Arial','',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;

@$serial=0;
//each record is one row //
foreach ($dbo->query($_SQL_EXECUTE_3) as $row_p) 
{
$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
$pdf->Cell($width_cell[1],10,$row_p['itemname'],1,0,'L',$fill);

$_Total_Amount=$_Total_Amount+$row_p['cost'];
$_Total_Amount_single=$_Total_Amount_single+$row_p['cost'];
$pdf->Cell($width_cell[2],10,$row_p['cost'],1,0,'C',$fill);

$fill = !$fill;
$pdf->Ln(10);
}

//Footer of the table
$pdf->Cell($width_cell[0]+$width_cell[1],10,'Sub Total:',1,0,'R',true);
$pdf->Cell($width_cell[2],10,$_Total_Amount_single,1,0,'C',true);
$_Total_Amount=$_Total_Amount+$_Total_Balance_Single;
$pdf->Ln(10); 
}
$pdf->SetFont('Arial','B',10);

$pdf->Cell($width_cell[0]+$width_cell[1],10,'GRAND TOTAL:',1,0,'R',true);
$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount,1,0,'C',true);
$pdf->Ln(15); 
}

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);

$pdf->SetFont('Arial','',8);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

$pdf->Ln(10); 
$_SQL_AC=mysqli_query($con,"SELECT * FROM tbltransaction tr 
INNER JOIN tblsystemuser su ON tr.recordedby=su.userid 
WHERE tr.transactionid='$_TransactionId_Bi'");
if($row_bi=mysqli_fetch_array($_SQL_AC,MYSQLI_ASSOC)){
$pdf->Cell(0,10,'ACCOUNTANT:'. strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 
$pdf->Ln(10); 
$pdf->Cell(0,10,'SIGNATURE:.............................................................................................',0);
 }
$pdf->Output();
}
?>


<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];
//echo $_SESSION['USERID'];

if(isset($_POST['bill_student'])){
//Create payment container
include("code.php");
@$_Payment_Id=$code;
@$_Transaction_Code=$transaction_id;

$_SQL_Payment=mysqli_query($con,"INSERT INTO tblpayment(paymentid,userid,transactionid,payment,datetimepayment,recordedby,status)
	VALUES('$_Payment_Id','$_UserId','$_Transaction_Code',0,NOW(),'$_SESSION[USERID]','active')");
if($_SQL_Payment){
@$_TransId=$_Transaction_Code;
}

$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblitemprice ip ON tr.class_entryid=ip.class_entryid AND tr.termname=ip.term
				INNER JOIN tblitem itm ON ip.itemid=itm.itemid
				INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
				WHERE tr.userid='$_UserId' AND ip.class_entryid='$_Class' AND ip.term='$_Term' AND ip.batch='$_Batch'");

while($row_b=mysqli_fetch_array($_SQL_EXECUTE_2,MYSQLI_ASSOC))
{
include("code.php");
@$_BillId=$code;

$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblbilling(billid,userid,itempriceid,transactionid,datetimebilled,recordedby,status)
	VALUES('$_BillId','$_UserId','$row_b[itempriceid]','$_TransId',NOW(),'$_Recordedby','active')");
if($_SQL_EXECUTE){
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:center;background-color:white'>$_BillId Successfully Created</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_BillId failed to create,$_Error</div>";
	}
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
	<br/>
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
				
				<div class="form-entry" align="left">
			
			<h3>Print Student Bills 
				</h3>
			<br/>
		<!--

			<form method="post" id="formID" name="formID" action="print-student-bills.php">
			<fieldset><legend>STUDENT</legend>
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student'  ORDER BY su.firstname");

			echo "<select id='userid' name='userid' class='validate[required]'>";
			echo "<option value=''>Select Student</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[userid]'>$row[firstname] $row[othernames] $row[surname]($row[userid]) </option>";
				}
				
			echo "</select><br/><br/>";
			?>

						<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

			echo "<select id='class_id' name='class_id' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select><br/><br/>";
			?>
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batch' name='batch' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			?>


			<select id="term_id" name="term_id">
				<option value="" class="validate[required]">Select Semester</option>
				<option value="1">1</option>
				<option value="2">2</option>
				
			</select><br/><br/>

			<?php
			echo "<button class='button-print' id='print-student-bills' name='print-student-bills'><i class='fa fa-print'></i> PRINT</button>";
			echo "<br/>";
			?>
			</fieldset><br/><br/>
</form>
-->

<form form="formID2" method="post" action="print-student-bills.php">
<fieldset><legend>CATEGORY</legend>
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

			echo "<select id='class' name='class' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select><br/><br/>";
			?>
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batch' name='batch' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";

			$_SQL_1=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student' ORDER BY su.userid ASC");
				
			while($row_s=mysqli_fetch_array($_SQL_1,MYSQLI_ASSOC)){
				echo "<input type='hidden' id='user_id' name='user_id[]' value='$row_s[userid]' />";
			}

			?>


			<select id="term" name="term">
				<option value="" class="validate[required]">Select Semester</option>
				<option value="1">1</option>
				<option value="2">2</option>
			</select><br/><br/>
			<div align="left"><button class="button-print" id="print_category" name="print_category"><i class="fa fa-print"></i> PRINT</button></div>
	
			</fieldset><br/><br/>
			</form>

		</div>
			</td>
<td width="70%">
<div class="form-entry">
<form id="formID" name="formID" method="post">
<?php	
include("dbstring.php");
echo "<fieldset><legend>FIND STUDENTS</legend>";		
echo "<table>";
echo "<tr>";
echo "<td>";
$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

echo "<select id='class_entryid' name='class_entryid' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
}
echo "</select>";
echo "</td>";
echo "<td>";
$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

echo "<select id='batchid' name='batchid' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[batchid]'>$row[batch]</option>";
}
echo "</select>";
echo "</td>";
echo "<td>";
echo "<button class='button-show' id='show_semester' name='show_semester'><i class='fa fa-search' style='color:white'></i> SHOW</button> ";
echo "</td>";
echo "</tr>";
echo "</table>";
echo "</fieldset>";
?>
</form>
				<?php
				echo $_SESSION['Message'];
				@$_Class_EntryId=$_POST["class_entryid"];
				@$_Batch_Id=$_POST["batchid"];
				if(isset($_POST["show_semester"]))
				{
				@$_Overall_Total=0;
				//$_SQL_EXECUTE_1=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student' ORDER BY su.firstname");
				$_SQL_EXECUTE_1=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblclass cl ON su.userid=cl.userid 
				WHERE cl.batchid='$_Batch_Id' AND cl.class_entryid='$_Class_EntryId' AND su.systemtype='Student' ORDER BY su.firstname ASC");

				echo "<table style='background-color:white'>";
				echo "<caption>STUDENTS BILLING</caption>";
				echo "<thead><th>Class</th><th>Item</th><th>Amount</th><th>Status</th><th>Billed Date/Time</th><th>Action</th></thead>";
				echo "<tbody>";
				
				while($row_1=mysqli_fetch_array($_SQL_EXECUTE_1,MYSQLI_ASSOC)){
				$_UserID=$row_1['userid'];

				echo "<tr style='background-color:#eee;border-bottom:1px solid gray;font-weight:bold'>";
				echo "<td colspan='6'>";
				echo strtoupper($row_1['firstname']." ".$row_1['othernames']." ".$row_1['surname']."(".$row_1['userid'].")");
				echo "</td>";
				echo "</tr>";

		$_SQL_CR=mysqli_query($con,"SELECT * FROM tblclass cl 
		WHERE cl.class_entryid='$_Class_EntryId' AND cl.batchid='$_Batch_Id' AND cl.userid='$row_1[userid]'");
		while($row_cr=mysqli_fetch_array($_SQL_CR,MYSQLI_ASSOC)){
			//Get all the classes regisetred for the studenet
			$_Class_Reg_ID=$row_cr['class_entryid'];
			@$_Class_Inner="";

			$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_Reg_ID' ORDER BY ce.class_name ASC");
			while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
			{
			////echo "<tr>";
			//echo "<td colspan='4' align='left' style='font-weight:bold'>";
			$_Class_Inner =strtoupper($row_class['class_name']);
			//echo "</td>";
			//echo "</tr>";

		$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
		WHERE tr.class_entryid='$row_class[class_entryid]' AND tr.batchid='$_Batch_Id' 
		AND tr.userid='$row_1[userid]' ORDER BY tr.termname ASC");
			while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
			{
			@$_Bactch_Inner="";
			$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch bch WHERE bch.batchid='$row_tr[batchid]'");
			echo "<form method='post' action='print-student-bills.php'>";

			echo "<tr>";
			echo "<td colspan='6' align='left' style='background-color:#fff;font-weight:bold'>";
			echo  $_Class_Inner ;
			echo "<input type='hidden' id='class_id' name='class_id' value='$row_class[class_entryid]' />";
			echo "<input type='hidden' id='term_id' name='term_id' value='$row_tr[termname]' />";
			echo "</td>";
			echo "</tr>";

			while($row_batch=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC))
			{
			//echo "<tr>";
			////echo "<td colspan='4' align='left' style='font-weight:bold'>";
			$_Bactch_Inner = strtoupper($row_batch['batch']);
			//echo "</td>";
			//echo "</tr>";
			echo "<tr>";
			echo "<td colspan='6' style='background-color:#fff;font-weight:bold'>";
			echo "Semester: ".$row_tr['termname'];
			echo "</td>";
			echo "</tr>";

			echo "<tr>";

			echo "<td colspan='6' align='left' style='background-color:#eef;font-weight:bold'>";
			echo "Batch:".$_Bactch_Inner;
			//echo "<input type='hidden' id='class_id' name='class_id' value='$row_class[class_entryid]' />";
			//echo "<input type='hidden' id='term_id' name='term_id' value='$row_tr[termname]' />";
			echo "</td>";
			echo "</tr>";

			
				$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblitemprice ip ON tr.class_entryid=ip.class_entryid AND tr.termname=ip.term
				INNER JOIN tblitem itm ON ip.itemid=itm.itemid
				INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
				INNER JOIN tblbatch b ON ip.batch=b.batchid
				WHERE tr.userid='$_UserID' AND ip.class_entryid='$row_class[class_entryid]' AND ip.term='$row_tr[termname]' AND ip.batch='$row_tr[batchid]' ");

				@$_Total_Amount=0;
				
					while($row_2=mysqli_fetch_array($_SQL_EXECUTE_2,MYSQLI_ASSOC))
					{
					echo "<tr>";
					//echo "<td align='center'>";
					//echo $row_2['class_name'];
					//echo "</td>";

					//echo "<td align='center'>";
					//echo $row_2['term'];
					//echo "</td>";

					echo "<td>";
					//echo $row_2['batch'];
					echo "</td>";

					echo "<td>";
					echo $row_2['itemname'];
					echo "</td>";


					echo "<td align='center'>";
					echo $row_2['price'];
					echo "</td>";

					echo "<td align='center'>";
					//echo $row_2['itempriceid'];
					$_SQL_BILL=mysqli_query($con,"SELECT * FROM tblbilling bi WHERE bi.userid='$_UserID' AND bi.itempriceid='$row_2[itempriceid]'");
					if(mysqli_num_rows($_SQL_BILL)>0)
					{
						$_Total_Amount=$_Total_Amount+$row_2['price'];
					
						echo " <i class='fa fa-check' style='color:green'></i>";
					}
					else{
						echo "<strong style='color:red'>Not Billed</strong>";
					}
					echo "</td>";

					echo "<td colspan='1' align='center'>";
					$_SQL_BILL_DATE=mysqli_query($con,"SELECT * FROM tblbilling bi WHERE bi.userid='$_UserID' AND bi.itempriceid='$row_2[itempriceid]'");
					if($row_bill=mysqli_fetch_array($_SQL_BILL_DATE,MYSQLI_ASSOC))
					{
					echo $row_bill['datetimebilled'];
					}
					
					echo "</td>";	

					echo "<td align='center'>";
					//echo "<a onclick=\"javascript:return confirm('Do you want to print?');\"  href='print-student-bills.php?user_id=$row_1[userid]&batch_id=$row_batch[batchid]&class_entryid=$row_class[class_entryid]&term_id=$row_tr[termname]'><i class='fa fa-plus'></i></a>";
					echo "</td>";
					echo "</tr>";
					}
				}
					echo "<tr style='background-color:#fff;'>";
					echo "<td colspan='2' align='right'>";
					echo "TOTAL:";
					echo "</td>";
					echo "<td>";
					echo $_SESSION['SYMBOL']." ". $_Total_Amount;
					$_Overall_Total=$_Overall_Total+$_Total_Amount;
					echo "</td>";
					
					echo "<td colspan='2'>";
					echo "</td>";

					echo "<td align='center'>";
					echo "<a title='Print student Bills' onclick=\"javascript:return confirm('Do you want to print?');\"  href='print-student-bills.php?user_id=$row_1[userid]&batch_id=$row_batch[batchid]&class_entryid=$row_class[class_entryid]&term_id=$row_tr[termname]'><i class='fa fa-print'></i></a>";
					echo "</td>";

					echo "</tr>";
				}
				echo "<tr style='background-color:#eee;'>";
					echo "<td colspan='2' align='right'>";
					echo "OVERALL TOTAL:";
					echo "</td>";
					echo "<td>";
					echo $_SESSION['SYMBOL']." ". $_Overall_Total;
					echo "</td>";

					echo "<td colspan='3'>";
					echo "</td>";
					
					echo "</tr>";
				}
			}
		}	
echo "</tbody>";
echo "</table>";
}
?>
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