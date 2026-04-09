<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include("code.php");
@$_MessageId=$code;
@$_Message=$_POST['message'];
$_UserId=(isset($_POST['userid']) && is_array($_POST['userid'])) ? $_POST['userid'] : array();
$_SelectedRecipient=isset($_POST["recipient"]) ? trim($_POST["recipient"]) : "";
$_SelectedBatchId=isset($_POST["batchid"]) ? trim($_POST["batchid"]) : "";

if(isset($_POST['send_message'])){
		if(empty($_UserId))
		{
		$_SESSION['Message']="<div style='color:red'>No user selected</div>";
		}
		else{
			foreach($_UserId as $selecteduser)
			{	
				$_Mobile="";
				$_SelectedUserSafe=mysqli_real_escape_string($con,$selecteduser);
				//Get mobile number from users	
				$_SQL_H=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$_SelectedUserSafe'");
				if($rowm=mysqli_fetch_array($_SQL_H,MYSQLI_ASSOC)){
				$_Mobile=$rowm["mobile"];
				}
				if($_Mobile!=""){
					$message=$_Message;
					$phone=$_Mobile;
					include("bulksms/bulksms.php");
				}
			}
	   }
}
?>

<?php
if(isset($_GET["delete_message"])){
$_SQL_D=mysqli_query($con,"DELETE FROM tbladministration WHERE messageid='$_GET[delete_message]'");
if($_SQL_D){
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Notice Successfully Deleted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Notice failed to delete</div>";
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
 function toggleBatchFilter(){
   var recipient=document.getElementById("recipient");
   var batchWrap=document.getElementById("batch_wrap");
   if(!recipient || !batchWrap){
     return false;
   }
   if(recipient.value=="Student"){
     batchWrap.style.display="block";
   }
   else{
     batchWrap.style.display="none";
   }
   return false;
 }
 document.addEventListener("DOMContentLoaded",function(){
   toggleBatchFilter();
 });
</script>
</head>
<body class="body-style">
<div class="header">
<?php
include("menu.php");
?>		
</div>
<div class="main-platform" align="center" ><br/>
<div class="form-entry">
<table border="0" width="100%">
<caption>Notification</caption>
<tr>
<td colspan="1" width="50%">
<div class="form-entry" align="center">
<form id="formID" method="post">
<select id="recipient" name="recipient" class="validate[required]" onchange="toggleBatchFilter()">
<option value="">Select Recipient</option>
<option value="Teaching Staff" <?php if($_SelectedRecipient=="Teaching Staff"){ echo "selected"; } ?>>Teaching Staff</option>
<option value="Non-Teaching Staff" <?php if($_SelectedRecipient=="Non-Teaching Staff"){ echo "selected"; } ?>>Non Teaching Staff</option>
<option value="Student" <?php if($_SelectedRecipient=="Student"){ echo "selected"; } ?>>Student</option>
</select>
<div id="batch_wrap" style="margin-top:8px;display:none;">
<select id="batchid" name="batchid">
<option value="">Select Batch</option>
<?php
$_SQL_BATCH=mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");
while($row_bh=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
	$_SelBatch=($_SelectedBatchId==$row_bh['batchid']) ? "selected" : "";
	echo "<option value='$row_bh[batchid]' $_SelBatch>$row_bh[batch]</option>";
}
?>
</select>
</div>
	
</td>
<td width="50%">
<?php
echo $_SESSION['Message'];
?>
<button class="button-show" id="showrecipient" name="showrecipient"><i class="fa fa-search" style="color:white"></i> SHOW USERS</button>
</form>
</td>
</tr>
<tr>
<td width="50%" valign="top" align="center">		
<form method="post" id="formID" name="formID" action="notification.php">
<label>Message</label><br/>
<textarea id="message" name="message" style="background-color:white;" class="validate[required]"></textarea><br/><br/>		
<div align="right"><button class="button-save" id="send_message" name="send_message"><i class="fa fa-send"></i> SEND</button></div>
</div>
</div>
</td>
<td width="50%" valign="top">
<div class="form-entry">
<?php	
if(isset($_POST["showrecipient"])){
	$_SQL_2=false;
	if($_SelectedRecipient=="Student"){
		if($_SelectedBatchId==""){
			echo "<div style='color:red'>Select batch to view students</div>";
		}
		else{
			$_BatchSafe=mysqli_real_escape_string($con,$_SelectedBatchId);
			$_SQL_2=mysqli_query($con,"SELECT DISTINCT su.* FROM tblclass cl INNER JOIN tblsystemuser su ON su.userid=cl.userid WHERE su.systemtype='Student' AND cl.batchid='$_BatchSafe' AND cl.status='active' ORDER BY su.firstname ASC,su.surname ASC");
		}
	}
	else{
		$_RecipientSafe=mysqli_real_escape_string($con,$_SelectedRecipient);
		$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.staffstatus='$_RecipientSafe' ORDER BY su.userid ASC");
	}
	if($_SQL_2){
		echo "<table>";
		echo "<caption>LIST OF USERS</caption>";
		echo "<thead><th><input type='checkbox' id='all' name='all' Onclick='selectAll()' /></th><th>*</th><th>MOBILE</th><th>FULL NAME</th></thead>";
		echo "<tbody>";
		@$serial=0;
		if(mysqli_num_rows($_SQL_2)<1){
			echo "<tr><td colspan='4' align='center'>No users found</td></tr>";
		}
		while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
		{
		echo "<tr class='light'>";
		echo "<td>";
		echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
		echo "</td>";
		echo "<td>";
		echo $serial=$serial+1;
		echo "</td>";
		echo "<td>";
		echo $row['mobile'];
		echo "</td>";			
		echo "<td>";
		echo $row['firstname']." ". $row['othernames']." ". $row['surname']."(".$row['userid'].")";
		echo "</td>";
		echo "</tr>";
		}	
		echo "</tbody>";
		echo "</table>";
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
