<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");

@$_UserID=$_POST['userid'];
@$_Firstname=$_POST['firstname'];
@$_Surname=$_POST['surname'];
@$_Othernames=$_POST['othernames'];
@$_Gender=$_POST['gender'];
@$_Birthday=$_POST['birthday'];
@$_Age=$_POST['age'];
@$_PostalAddress=$_POST['postaladdress'];
@$_HomeAddress=$_POST['homeaddress'];
@$_HomeTown=$_POST['hometown'];
@$_Email=$_POST['email'];
@$_Mobile=$_POST['mobile'];

@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_Nextofkin_fullname=$_POST['nextoffullname'];
@$_Nextofcontact=$_POST['nextofkincontact'];
@$_Username=$_POST['username'];
@$_Password=md5($_POST['password']);
@$_AccessLevel="user";
@$_SystemType="Student";
@$_Filename=$_POST['filename'];

if(isset($_POST['register_user'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,relationship,nextofkin_fullname,nextofkin_contact,email,mobile,registereddatetime,status,username,password,accesslevel,systemtype,branchid)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender',STR_TO_DATE('$_Birthday','%d-%m-%Y'),'$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_Nextofkin_fullname','$_Nextofcontact','$_Email','$_Mobile',NOW(),'active','$_Username','$_Password','$_AccessLevel','$_SystemType','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
$_SESSION['Message']="<div style='color:green;text-align:center'>Teacher/Student Information Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red'>Teacher/Student Information Failed to save,Error:$_Error</div>";
}
}
?>

<?php
include("dbstring.php");
include("code.php");
@$_MessageId=$code;
@$_Message=$_POST['message'];

if(isset($_POST["send_message"])){
$_SQL=mysqli_query($con,"INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby)
VALUES('$_MessageId','$_Message',NOW(),'active','$_SESSION[USERID]')");
if($_SQL){
$_SESSION['Message']="<div style='color:green;padding:10px;'>Message Successfully Submitted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message failed to submit</div>";
}
}
?>

<?php
if(isset($_GET["delete_message"])){
$_SQL_D=mysqli_query($con,"DELETE FROM tblmessages WHERE messageid='$_GET[delete_message]'");
if($_SQL_D){
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message Successfully Deleted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message failed to delete</div>";
}
}
?>

<html>
<head>
<?php
include("links.php");
?>

</head>

<body class="body-style">
	<!--Header-->
	
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	<?php
	//include("side-menu.php");
	?>
	</div>
<div class="main-platform" align="center" >
	<?php
	echo $_SESSION["Message"];
	?>
<table border="0" width="100%">
<tr>

<td width="100%" valign="top">
<div class="form-entry" align="left">
<form id="formID" name="formID" method="post">
<?php	
include("dbstring.php");
echo "<fieldset><legend>BATCH</legend>";		
echo "<table>";
echo "<tr>";
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
echo "<button class='button-show' id='show_class' name='show_class'><i class='fa fa-search' style='color:white'></i> SHOW</button> ";
echo "</td>";
echo "</tr>";
echo "</table>";
echo "</fieldset>";
?>
</form>
				<?php
				echo $_SESSION['Message'];
				@$_BatchID=$_POST["batchid"];
				if(isset($_POST["show_class"]))
				{
				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblclass cl ON su.userid=cl.userid 
				WHERE cl.batchid='$_BatchID' AND cl.status='active' AND su.systemtype='Student' ORDER BY su.firstname ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>STUDENT CLASS REGISTRATION</caption>";
				echo "<thead><th>*</th><th colspan=1>TASK</th><th>STUDENT</th><th>CLASS</th><th>BATCH</th><th>ENTRY DATE/TIME</th></thead>";
				echo "<tbody>";
				@$serial=0;

				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'>";
				echo $serial=$serial+1 .".";
				echo "</td>";

				echo "<td align='center' ><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-plus' style='color:royalblue'></i></a></td>";
				echo "<td colspan='4' style='background-color:#ffffff;border-bottom:0px solid gray'>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
			
				echo "</tr>";
				
				$_SQL_CLASS=mysqli_query($con,"SELECT *,cl.datetimeentry FROM tblclass cl 
				INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid
				INNER JOIN tblbatch bh ON cl.batchid=bh.batchid
				 WHERE cl.userid='$row[userid]' AND cl.status='active' ORDER BY ce.class_name ASC");
				while($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
				echo "<tr style='background-color:#ffffff;border-bottom:1px solid gray'>";
			
				echo "<td colspan='1'>";
				echo "</td>";

				echo "<td align='center'><a onclick=\"javascript:return confirm('Do you want to remove class?')\" title='Remove class $row_cl[class_name]' href='upload-class-registry.php?delete_class=$row_cl[classid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				echo "<td colspan='1' align='right'>";
				echo "Class:";
				echo "</td>";
				echo "<td colspan='1'>";
				echo $row_cl['class_name'];
				echo "</td>";

				echo "<td colspan='1'>";
				echo $row_cl['batch'];
				echo "</td>";

				echo "<td colspan='1' align='center'>";
				echo $row_cl['datetimeentry'];
				echo "</td>";

				echo "</tr>";
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
