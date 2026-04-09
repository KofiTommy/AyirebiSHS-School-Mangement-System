<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
?>


<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_UserId=$_GET['user_id'];
@$_Class=$_GET['class_entryid'];
@$_Batch=$_GET['batch_id'];
@$_Term=$_GET['term_id'];
@$_Recordedby=$_SESSION['USERID'];
//echo $_SESSION['USERID'];

if(isset($_GET['user_id'])){
//Create payment container
include("code.php");
@$_Payment_Id=$code;
@$_Transaction_Code=$transaction_id;
@$_TransId=0;

$_SQL_Payment=mysqli_query($con,"INSERT INTO tbltransaction(transactionid,userid,datetimepayment,recordedby,status)
	VALUES('$_Transaction_Code','$_UserId',NOW(),'$_SESSION[USERID]','active')");
if($_SQL_Payment){
$_TransId=$_Transaction_Code;
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

//Check if the bill is duplicated
$_SQL_CHECK=mysqli_query($con,"SELECT * FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON itm.itemid=ip.itemid
 WHERE bi.userid='$_UserId' AND bi.itempriceid='$row_b[itempriceid]'");
if(mysqli_num_rows($_SQL_CHECK)>0)
{
	if($row_item=mysqli_fetch_array($_SQL_CHECK,MYSQLI_ASSOC)){
	@$_ItemName=$row_item['itemname'];
	}
	
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white;padding:5px;'><i class='fa fa-check' style='color:red'></i> $_ItemName already billed</div>";
}
else{
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblbilling(billid,userid,itempriceid,transactionid,cost,datetimebilled,recordedby,status,referenceid)
	VALUES('$_BillId','$_UserId','$row_b[itempriceid]','$_TransId','$row_b[price]',NOW(),'$_Recordedby','active','$_SESSION[SCHOOLACCOUNT]')");
if($_SQL_EXECUTE){

$_SQL_B=mysqli_query($con,"INSERT INTO accountingbookentries(accountId,cr,created,createdBy,dr,modifiedBy,narration,particulars,refAccountId,transactionId)
VALUES('$_SESSION[SCHOOLACCOUNT]','$row_b[price]',NOW(),'$_Recordedby',0,'','Bills','Bills','$_UserId','$_TransId')");
if($_SQL_B){}

	$_SQL_CHECK_1=mysqli_query($con,"SELECT * FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON itm.itemid=ip.itemid
 WHERE bi.userid='$_UserId' AND bi.itempriceid='$row_b[itempriceid]'");
if(mysqli_num_rows($_SQL_CHECK_1)>0)
{
	if($row_item_1=mysqli_fetch_array($_SQL_CHECK_1,MYSQLI_ASSOC)){
	@$_ItemName_1=$row_item_1['itemname'];
	}


	$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white;padding:5px;'><i class='fa fa-check' style='color:green'></i> $_ItemName_1 Successfully Billed</div>";
	}

	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_ItemName_1 failed to bill,$_Error</div>";
	}
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>No item billed,$_Error</div>";
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
			<!--
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
			<h3>Student Billing 
				</h3>
			<br/>
			<form method="post" id="formID" name="formID" action="student-billing.php">
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student' ORDER BY su.firstname");

			echo "<select id='userid' name='userid' class='validate[required]'>";
			echo "<option value=''>Select Student</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[userid]'>$row[firstname] $row[othernames] $row[surname]($row[userid]) </option>";
				}
				
			echo "</select><br/><br/>";
			?>


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
			?>


			<select id="term" name="term" class="validate[required]">
				<option value="" >Select Semester</option>
				<option value="1">1</option>
				<option value="2">2</option>
				
			</select><br/><br/>

			<div align="center"><button class="button-save" id="bill_student" name="bill_student"><i class="fa fa-plus"></i> BILL STUDENT</button></div>
		</form>
</div>
</td>
-->
<td width="100%">
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
			echo "<form method='post' action='student-billing.php'>";

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
					echo "<a onclick=\"javascript:return confirm('Do you want to Bill?');\"  href='student-billing.php?user_id=$row_1[userid]&batch_id=$row_batch[batchid]&class_entryid=$row_class[class_entryid]&term_id=$row_tr[termname]'><i class='fa fa-plus'></i></a>";
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
					
					echo "<td colspan='3'>";
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
</div>
</body>
</html>